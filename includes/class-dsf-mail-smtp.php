<?php
/**
 * Mail / SMTP delivery for DesignStudio Flow.
 *
 * Routes every wp_mail() call (form notifications, WordPress core mail, etc.)
 * through a chosen transport by hooking phpmailer_init. Four transports are
 * supported:
 *
 *   - php       Default PHP mail() — no SMTP, nothing is overridden.
 *   - sendgrid  Authenticated SMTP using a SendGrid API key.
 *   - google    Gmail / Google Workspace over SMTP + XOAUTH2 (one-click connect).
 *   - outlook   Microsoft 365 / Outlook over SMTP + XOAUTH2 (one-click connect).
 *
 * For the OAuth transports the site owner registers an OAuth app once (Client ID
 * + Secret) and then connects their mailbox with a single click; we store the
 * resulting refresh token and mint short-lived access tokens on demand. Settings
 * live in a single non-autoloaded option because they are only read while sending
 * mail and hold credentials we would rather not load on every request.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Mail_SMTP {

	const OPTION               = 'dsf_mail_smtp';
	const CAP                  = 'manage_options';
	const SAVE_ACTION          = 'dsf_mail_save';
	const TEST_ACTION          = 'dsf_mail_test';
	const AUTH_ACTION          = 'dsf_mail_authorize';
	const CALLBACK_ACTION      = 'dsf_mail_callback';
	const DISCONNECT_ACTION    = 'dsf_mail_disconnect';
	const STATE_TRANSIENT      = 'dsf_mail_oauth_state';
	const TEST_ERROR_TRANSIENT = 'dsf_mail_test_error';
	const TEST_DEBUG_TRANSIENT = 'dsf_mail_test_debug';
	const LAST_ERROR_OPTION    = 'dsf_mail_last_error';
	const CLEAR_LOG_ACTION     = 'dsf_mail_clear_log';
	const CLEANUP_HOOK         = 'dsf_mail_log_cleanup';
	const LOG_DB_VERSION_OPT   = 'dsf_mail_log_db_version';
	const LOG_DB_VERSION       = '1';
	const LOG_RETENTION_DAYS   = 30;
	const LOG_LIST_LIMIT       = 100;

	/**
	 * Mailers that authenticate via OAuth2.
	 *
	 * @var string[]
	 */
	private static $oauth_mailers = array( 'google', 'outlook' );

	/**
	 * Setting keys holding credentials that are encrypted at rest.
	 *
	 * @var string[]
	 */
	private static $secret_keys = array(
		'sendgrid_api_key',
		'google_client_secret',
		'google_refresh_token',
		'google_access_token',
		'outlook_client_secret',
		'outlook_refresh_token',
		'outlook_access_token',
	);

	/**
	 * Captured PHPMailer error message during a test send.
	 *
	 * @var string
	 */
	private $mail_error = '';

	/**
	 * Whether the current send is a diagnostic test (enables SMTP debug capture).
	 *
	 * @var bool
	 */
	private $is_testing = false;

	/**
	 * Captured SMTP conversation during a test send.
	 *
	 * @var string
	 */
	private $debug_log = '';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
		add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ), 9999 );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ), 9999 );

		// Remember the most recent delivery failure so it can be surfaced in admin.
		add_action( 'wp_mail_failed', array( $this, 'record_last_error' ) );

		// Email log: capture every send result.
		add_action( 'wp_mail_succeeded', array( $this, 'log_success' ) );
		add_action( 'wp_mail_failed', array( $this, 'log_failure' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'prune_log' ) );
		$this->maybe_create_log_table();
		$this->maybe_schedule_cleanup();

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'maybe_admin_notice' ) );
		}

		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( $this, 'handle_test' ) );
		add_action( 'admin_post_' . self::AUTH_ACTION, array( $this, 'handle_authorize' ) );
		add_action( 'admin_post_' . self::CALLBACK_ACTION, array( $this, 'handle_callback' ) );
		add_action( 'admin_post_' . self::DISCONNECT_ACTION, array( $this, 'handle_disconnect' ) );
		add_action( 'admin_post_' . self::CLEAR_LOG_ACTION, array( $this, 'handle_clear_log' ) );
	}

	/* -----------------------------------------------------------------
	 * Settings storage
	 * ----------------------------------------------------------------- */

	/**
	 * Default settings shape.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'mailer'                => 'php',
			'from_email'            => '',
			'from_name'             => '',
			'force_from'            => false,
			'log_enabled'           => true,

			'sendgrid_api_key'      => '',

			'google_client_id'      => '',
			'google_client_secret'  => '',
			'google_refresh_token'  => '',
			'google_access_token'   => '',
			'google_token_expires'  => 0,
			'google_email'          => '',
			'google_needs_reauth'   => 0,

			'outlook_client_id'     => '',
			'outlook_client_secret' => '',
			'outlook_refresh_token' => '',
			'outlook_access_token'  => '',
			'outlook_token_expires' => 0,
			'outlook_email'         => '',
			'outlook_needs_reauth'  => 0,
		);
	}

	/**
	 * Merged settings (stored values over defaults).
	 *
	 * @return array
	 */
	public function get_settings() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$settings = array_merge( self::defaults(), $stored );

		// Decrypt secrets back to plaintext for in-memory use.
		foreach ( self::$secret_keys as $key ) {
			if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
				$settings[ $key ] = DSF_Crypto::decrypt( (string) $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Persist the settings array (non-autoloaded — read only when mailing).
	 * Credentials are encrypted at rest so a database leak does not expose them.
	 *
	 * @param array $settings Full settings array.
	 */
	private function save_settings( $settings ) {
		foreach ( self::$secret_keys as $key ) {
			if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
				$settings[ $key ] = DSF_Crypto::encrypt( (string) $settings[ $key ] );
			}
		}
		update_option( self::OPTION, $settings, false );
	}

	/**
	 * Merge a partial set of provider token fields into the stored option.
	 *
	 * Used while sending mail (token refresh) so we never clobber unrelated keys.
	 *
	 * @param string $provider google|outlook.
	 * @param array  $fields   Keyed by short name (access_token, refresh_token, ...).
	 */
	private function update_provider( $provider, $fields ) {
		$settings = $this->get_settings();
		foreach ( $fields as $key => $value ) {
			$settings[ $provider . '_' . $key ] = $value;
		}
		$this->save_settings( $settings );
	}

	/**
	 * Whether the selected mailer has enough configuration to take over delivery.
	 *
	 * When false we leave wp_mail() on its default transport rather than break it.
	 *
	 * @param string $mailer   Selected mailer.
	 * @param array  $settings Settings.
	 * @return bool
	 */
	private function is_configured( $mailer, $settings ) {
		switch ( $mailer ) {
			case 'sendgrid':
				return '' !== trim( (string) $settings['sendgrid_api_key'] );
			case 'google':
			case 'outlook':
				return '' !== (string) $settings[ $mailer . '_refresh_token' ]
					&& '' !== (string) $settings[ $mailer . '_client_id' ]
					&& '' !== (string) $settings[ $mailer . '_client_secret' ];
			default:
				return false;
		}
	}

	/* -----------------------------------------------------------------
	 * Provider configuration
	 * ----------------------------------------------------------------- */

	/**
	 * OAuth + SMTP endpoints for a provider.
	 *
	 * @param string $provider google|outlook.
	 * @return array
	 */
	private function provider_config( $provider ) {
		if ( 'outlook' === $provider ) {
			return array(
				'auth_url'    => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
				'token_url'   => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
				'scope'       => 'openid email offline_access https://outlook.office.com/SMTP.Send',
				'extra_auth'  => array(
					'response_mode' => 'query',
					'prompt'        => 'consent',
				),
				'smtp_host'   => 'smtp.office365.com',
				'smtp_port'   => 587,
				'smtp_secure' => 'tls',
			);
		}

		return array(
			'auth_url'    => 'https://accounts.google.com/o/oauth2/v2/auth',
			'token_url'   => 'https://oauth2.googleapis.com/token',
			'scope'       => 'https://mail.google.com/ openid email',
			'extra_auth'  => array(
				'access_type' => 'offline',
				'prompt'      => 'consent',
			),
			'smtp_host'   => 'smtp.gmail.com',
			'smtp_port'   => 465,
			'smtp_secure' => 'ssl',
		);
	}

	/**
	 * The redirect URI the OAuth app must whitelist for a provider.
	 *
	 * @param string $provider google|outlook.
	 * @return string
	 */
	public function get_redirect_uri( $provider ) {
		return add_query_arg(
			array(
				'action'   => self::CALLBACK_ACTION,
				'provider' => $provider,
			),
			admin_url( 'admin-post.php' )
		);
	}

	/* -----------------------------------------------------------------
	 * Outgoing mail
	 * ----------------------------------------------------------------- */

	/**
	 * Point PHPMailer at the configured transport.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance (by ref).
	 */
	public function configure_phpmailer( $phpmailer ) {
		$settings = $this->get_settings();
		$mailer   = $settings['mailer'];

		if ( 'php' === $mailer || ! $this->is_configured( $mailer, $settings ) ) {
			return;
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's public API uses PascalCase properties.
		$phpmailer->isSMTP();
		$phpmailer->SMTPAuth = true;
		$phpmailer->Timeout  = 20;

		switch ( $mailer ) {
			case 'sendgrid':
				$phpmailer->Host       = apply_filters( 'dsf_mail_smtp_host', 'smtp.sendgrid.net', $mailer );
				$phpmailer->Port       = (int) apply_filters( 'dsf_mail_smtp_port', 587, $mailer );
				$phpmailer->SMTPSecure = 'tls';
				$phpmailer->Username   = 'apikey';
				$phpmailer->Password   = trim( (string) $settings['sendgrid_api_key'] );
				break;

			case 'google':
			case 'outlook':
				$config                = $this->provider_config( $mailer );
				$host                  = ( 'outlook' === $mailer ) ? $this->outlook_host( $settings ) : $config['smtp_host'];
				$phpmailer->Host       = apply_filters( 'dsf_mail_smtp_host', $host, $mailer );
				$phpmailer->Port       = (int) apply_filters( 'dsf_mail_smtp_port', $config['smtp_port'], $mailer );
				$phpmailer->SMTPSecure = $config['smtp_secure'];
				$phpmailer->AuthType   = 'XOAUTH2';
				$phpmailer->setOAuth( $this->build_oauth_provider( $mailer, $settings ) );
				break;
		}

		// Capture the SMTP conversation only while running a diagnostic test send.
		if ( $this->is_testing ) {
			$phpmailer->SMTPDebug   = 2; // DEBUG_SERVER: client + server messages.
			$phpmailer->Debugoutput = array( $this, 'capture_debug' );
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$this->apply_from( $phpmailer, $settings, $mailer );
	}

	/**
	 * Pick the right Outlook/Microsoft SMTP host for the connected mailbox.
	 *
	 * Personal accounts (outlook.com, hotmail.com, live.com, …) use the consumer
	 * endpoint; Microsoft 365 / business accounts use Office 365.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	private function outlook_host( $settings ) {
		$email    = strtolower( (string) $settings['outlook_email'] );
		$personal = array( 'outlook.com', 'hotmail.com', 'live.com', 'msn.com', 'passport.com', 'hotmail.co.uk', 'live.co.uk' );

		$at = strrpos( $email, '@' );
		if ( false !== $at ) {
			$domain = substr( $email, $at + 1 );
			if ( in_array( $domain, $personal, true ) ) {
				return 'smtp-mail.outlook.com';
			}
		}

		return 'smtp.office365.com';
	}

	/**
	 * Set the From address on the PHPMailer instance when one is configured.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @param array                         $settings  Settings.
	 * @param string                        $mailer    Active mailer.
	 */
	private function apply_from( $phpmailer, $settings, $mailer ) {
		$from_email = trim( (string) $settings['from_email'] );

		// OAuth transports must send as the connected mailbox.
		if ( '' === $from_email && in_array( $mailer, self::$oauth_mailers, true ) ) {
			$from_email = (string) $settings[ $mailer . '_email' ];
		}

		if ( '' === $from_email || ! is_email( $from_email ) ) {
			return;
		}

		try {
			$phpmailer->setFrom( $from_email, (string) $settings['from_name'], false );
		} catch ( \Exception $e ) {
			// Leave the existing From if PHPMailer rejects it.
			unset( $e );
		}
	}

	/**
	 * Override the wp_mail() From address when "force from" is enabled.
	 *
	 * @param string $email Default from email.
	 * @return string
	 */
	public function filter_from_email( $email ) {
		$settings = $this->get_settings();
		$mailer   = $settings['mailer'];

		$from = trim( (string) $settings['from_email'] );
		if ( '' === $from && in_array( $mailer, self::$oauth_mailers, true ) ) {
			$from = (string) $settings[ $mailer . '_email' ];
		}

		if ( ! empty( $settings['force_from'] ) && '' !== $from && is_email( $from ) ) {
			return $from;
		}

		return $email;
	}

	/**
	 * Override the wp_mail() From name when "force from" is enabled.
	 *
	 * @param string $name Default from name.
	 * @return string
	 */
	public function filter_from_name( $name ) {
		$settings = $this->get_settings();
		if ( ! empty( $settings['force_from'] ) && '' !== trim( (string) $settings['from_name'] ) ) {
			return (string) $settings['from_name'];
		}
		return $name;
	}

	/**
	 * Build the PHPMailer OAuth token provider for a mailbox.
	 *
	 * @param string $provider google|outlook.
	 * @param array  $settings Settings.
	 * @return DSF_Mail_OAuth_Provider
	 */
	private function build_oauth_provider( $provider, $settings ) {
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-mail-oauth-provider.php';

		$email = (string) $settings[ $provider . '_email' ];
		if ( '' === $email ) {
			$email = trim( (string) $settings['from_email'] );
		}

		$self = $this;
		return new DSF_Mail_OAuth_Provider(
			$email,
			static function () use ( $self, $provider ) {
				return $self->get_access_token( $provider );
			}
		);
	}

	/**
	 * Return a valid access token for a provider, refreshing it when stale.
	 *
	 * Public so the token-provider callback can reach it.
	 *
	 * @param string $provider google|outlook.
	 * @return string
	 */
	public function get_access_token( $provider ) {
		$settings = $this->get_settings();

		$access  = (string) $settings[ $provider . '_access_token' ];
		$expires = (int) $settings[ $provider . '_token_expires' ];

		// 60s safety margin so a token does not expire mid-handshake.
		if ( '' !== $access && $expires > ( time() + 60 ) ) {
			return $access;
		}

		$refresh = (string) $settings[ $provider . '_refresh_token' ];
		if ( '' === $refresh ) {
			return '';
		}

		$config   = $this->provider_config( $provider );
		$response = wp_remote_post(
			$config['token_url'],
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => $settings[ $provider . '_client_id' ],
					'client_secret' => $settings[ $provider . '_client_secret' ],
					'refresh_token' => $refresh,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		$data = $this->parse_token_response( $response );
		if ( empty( $data['access_token'] ) ) {
			// A present-but-rejected refresh token means the connection was revoked
			// or expired — flag it so the admin is prompted to reconnect.
			$this->update_provider( $provider, array( 'needs_reauth' => 1 ) );
			return '';
		}

		$fields = array(
			'access_token'  => (string) $data['access_token'],
			'token_expires' => time() + (int) ( isset( $data['expires_in'] ) ? $data['expires_in'] : 3600 ),
			'needs_reauth'  => 0,
		);
		// Providers occasionally rotate the refresh token.
		if ( ! empty( $data['refresh_token'] ) ) {
			$fields['refresh_token'] = (string) $data['refresh_token'];
		}

		$this->update_provider( $provider, $fields );

		return (string) $data['access_token'];
	}

	/* -----------------------------------------------------------------
	 * OAuth connect flow
	 * ----------------------------------------------------------------- */

	/**
	 * Kick off the OAuth authorization-code flow (redirects to the provider).
	 */
	public function handle_authorize() {
		$this->guard();

		$provider = $this->get_provider_param();
		check_admin_referer( self::AUTH_ACTION . '_' . $provider );

		$settings = $this->get_settings();
		if ( '' === (string) $settings[ $provider . '_client_id' ] || '' === (string) $settings[ $provider . '_client_secret' ] ) {
			$this->back( 'oauth_missing_creds' );
		}

		$state = wp_generate_password( 24, false );
		set_transient(
			self::STATE_TRANSIENT,
			array(
				'state'    => $state,
				'provider' => $provider,
			),
			15 * MINUTE_IN_SECONDS
		);

		$config = $this->provider_config( $provider );
		$args   = array_merge(
			array(
				'client_id'     => $settings[ $provider . '_client_id' ],
				'redirect_uri'  => $this->get_redirect_uri( $provider ),
				'response_type' => 'code',
				'scope'         => $config['scope'],
				'state'         => $state,
			),
			$config['extra_auth']
		);

		// wp_redirect (not safe_redirect) — destination is an external provider.
		wp_redirect( add_query_arg( array_map( 'rawurlencode', $args ), $config['auth_url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Provider redirect target: exchange the auth code for tokens.
	 */
	public function handle_callback() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to connect a mailbox.', 'designstudio-flow' ) );
		}

		$provider = $this->get_provider_param();

		$saved = get_transient( self::STATE_TRANSIENT );
		delete_transient( self::STATE_TRANSIENT );

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( ! is_array( $saved ) || empty( $saved['state'] ) || ! hash_equals( $saved['state'], $state ) || $saved['provider'] !== $provider ) {
			$this->back( 'oauth_state' );
		}

		if ( isset( $_GET['error'] ) ) {
			$this->back( 'oauth_denied' );
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( '' === $code ) {
			$this->back( 'oauth_no_code' );
		}

		$settings = $this->get_settings();
		$config   = $this->provider_config( $provider );

		$response = wp_remote_post(
			$config['token_url'],
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => $settings[ $provider . '_client_id' ],
					'client_secret' => $settings[ $provider . '_client_secret' ],
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $this->get_redirect_uri( $provider ),
				),
			)
		);

		$data = $this->parse_token_response( $response );
		if ( empty( $data['access_token'] ) || empty( $data['refresh_token'] ) ) {
			$this->back( 'oauth_token' );
		}

		$email  = $this->detect_email( $provider, $data );
		$fields = array(
			'access_token'  => (string) $data['access_token'],
			'refresh_token' => (string) $data['refresh_token'],
			'token_expires' => time() + (int) ( isset( $data['expires_in'] ) ? $data['expires_in'] : 3600 ),
			'email'         => $email,
			'needs_reauth'  => 0,
		);
		$this->update_provider( $provider, $fields );

		// Clear any stale delivery error now that the mailbox is (re)connected.
		delete_option( self::LAST_ERROR_OPTION );

		// Default the From address to the connected mailbox when none is set.
		if ( $email ) {
			$settings = $this->get_settings();
			if ( '' === trim( (string) $settings['from_email'] ) ) {
				$settings['from_email'] = $email;
				$this->save_settings( $settings );
			}
		}

		$this->back( 'oauth_connected' );
	}

	/**
	 * Forget a connected mailbox.
	 */
	public function handle_disconnect() {
		$this->guard();

		$provider = $this->get_provider_param();
		check_admin_referer( self::DISCONNECT_ACTION . '_' . $provider );

		$this->update_provider(
			$provider,
			array(
				'refresh_token' => '',
				'access_token'  => '',
				'token_expires' => 0,
				'email'         => '',
				'needs_reauth'  => 0,
			)
		);

		$this->back( 'oauth_disconnected' );
	}

	/**
	 * Best-effort mailbox address from the OAuth id_token.
	 *
	 * @param string $provider google|outlook.
	 * @param array  $data     Token response.
	 * @return string
	 */
	private function detect_email( $provider, $data ) {
		if ( empty( $data['id_token'] ) ) {
			return '';
		}

		$parts = explode( '.', (string) $data['id_token'] );
		if ( count( $parts ) < 2 ) {
			return '';
		}

		$payload = json_decode( $this->base64url_decode( $parts[1] ), true );
		if ( ! is_array( $payload ) ) {
			return '';
		}

		$candidates = ( 'outlook' === $provider )
			? array( 'email', 'preferred_username', 'upn' )
			: array( 'email' );

		foreach ( $candidates as $claim ) {
			if ( ! empty( $payload[ $claim ] ) && is_email( $payload[ $claim ] ) ) {
				return sanitize_email( $payload[ $claim ] );
			}
		}

		return '';
	}

	/**
	 * Decode a base64url segment (JWT payload).
	 *
	 * @param string $value Base64url string.
	 * @return string
	 */
	private function base64url_decode( $value ) {
		$value = strtr( (string) $value, '-_', '+/' );
		$pad   = strlen( $value ) % 4;
		if ( $pad ) {
			$value .= str_repeat( '=', 4 - $pad );
		}
		return (string) base64_decode( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Decode an OAuth token endpoint response into an array.
	 *
	 * @param array|WP_Error $response wp_remote_post result.
	 * @return array
	 */
	private function parse_token_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return array();
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : array();
	}

	/* -----------------------------------------------------------------
	 * Test email
	 * ----------------------------------------------------------------- */

	/**
	 * Send a test message and report the result.
	 */
	public function handle_test() {
		$this->guard();
		check_admin_referer( self::TEST_ACTION );

		$to = isset( $_POST['dsf_test_email'] ) ? sanitize_email( wp_unslash( $_POST['dsf_test_email'] ) ) : '';
		if ( '' === $to || ! is_email( $to ) ) {
			$to = sanitize_email( (string) get_option( 'admin_email', '' ) );
		}

		if ( '' === $to || ! is_email( $to ) ) {
			$this->store_test_error( __( 'Enter a valid recipient address.', 'designstudio-flow' ) );
			$this->back( 'test_failed' );
		}

		// Don't let an unconfigured mailer fall back to PHP mail and report a
		// misleading success — tell the user to finish setup first.
		$settings = $this->get_settings();
		if ( 'php' !== $settings['mailer'] && ! $this->is_configured( $settings['mailer'], $settings ) ) {
			$this->store_test_error( __( 'The selected mailer is not fully configured. Finish setup — and connect your mailbox for Gmail/Outlook — before sending a test.', 'designstudio-flow' ) );
			$this->back( 'test_failed' );
		}

		$this->mail_error = '';
		$this->debug_log  = '';
		$this->is_testing = true;
		add_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] DesignStudio Flow test email', 'designstudio-flow' ),
			wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES )
		);
		$body = __( 'This is a test email from DesignStudio Flow. If you received it, your Mail / SMTP settings are working.', 'designstudio-flow' );

		$sent = wp_mail( $to, $subject, $body );

		remove_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );
		$this->is_testing = false;

		if ( '' !== $this->debug_log ) {
			set_transient( self::TEST_DEBUG_TRANSIENT, $this->redact( $this->debug_log ), 120 );
		}

		if ( $sent && '' === $this->mail_error ) {
			delete_option( self::LAST_ERROR_OPTION );
			$this->back( 'test_sent' );
		}

		$this->store_test_error( '' !== $this->mail_error ? $this->mail_error : __( 'Unknown error.', 'designstudio-flow' ) );
		$this->back( 'test_failed' );
	}

	/**
	 * Record the PHPMailer failure message for the next page load.
	 *
	 * @param WP_Error $wp_error Mail error.
	 */
	public function capture_mail_error( $wp_error ) {
		if ( is_wp_error( $wp_error ) ) {
			$this->mail_error = $wp_error->get_error_message();
		}
	}

	/**
	 * Collect a line of PHPMailer SMTP debug output during a test send.
	 *
	 * @param string $str   Debug line.
	 * @param int    $level Debug level.
	 */
	public function capture_debug( $str, $level ) {
		unset( $level );
		$this->debug_log .= trim( (string) $str ) . "\n";
	}

	/**
	 * Persist the latest delivery failure so it can be shown in admin.
	 *
	 * Fires for every failed wp_mail() (form notifications included), not just tests.
	 *
	 * @param WP_Error $wp_error Mail error.
	 */
	public function record_last_error( $wp_error ) {
		if ( ! is_wp_error( $wp_error ) ) {
			return;
		}

		// Ignore the default PHP mailer — there is nothing for us to diagnose.
		$settings = $this->get_settings();
		if ( 'php' === $settings['mailer'] ) {
			return;
		}

		update_option(
			self::LAST_ERROR_OPTION,
			array(
				'message' => sanitize_text_field( $wp_error->get_error_message() ),
				'time'    => time(),
			),
			false
		);
	}

	/**
	 * Strip access tokens / long base64 blobs from captured debug output.
	 *
	 * @param string $log Raw debug log.
	 * @return string
	 */
	private function redact( $log ) {
		// XOAUTH2 auth strings and bearer tokens are long base64 runs — mask them.
		$log = preg_replace( '/[A-Za-z0-9_\-\.\+\/=]{40,}/', '[redacted]', (string) $log );
		return mb_substr( (string) $log, 0, 4000 );
	}

	/**
	 * Stash a test error so the notice can show it after the redirect.
	 *
	 * @param string $message Error message.
	 */
	private function store_test_error( $message ) {
		set_transient( self::TEST_ERROR_TRANSIENT, sanitize_text_field( $message ), 120 );
	}

	/* -----------------------------------------------------------------
	 * Email log (retained for LOG_RETENTION_DAYS, then pruned)
	 * ----------------------------------------------------------------- */

	/**
	 * Fully-qualified log table name.
	 *
	 * @return string
	 */
	private function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'dsf_mail_log';
	}

	/**
	 * Create / upgrade the log table once per schema version.
	 */
	private function maybe_create_log_table() {
		if ( self::LOG_DB_VERSION === get_option( self::LOG_DB_VERSION_OPT ) ) {
			return;
		}

		global $wpdb;
		$table           = $this->log_table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			to_email varchar(255) NOT NULL DEFAULT '',
			subject varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT '',
			mailer varchar(20) NOT NULL DEFAULT '',
			error text NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::LOG_DB_VERSION_OPT, self::LOG_DB_VERSION, false );
	}

	/**
	 * Ensure the daily prune event is scheduled.
	 */
	private function maybe_schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Log a successful send.
	 *
	 * @param array $mail_data wp_mail() data (to, subject, headers, attachments).
	 */
	public function log_success( $mail_data ) {
		$this->insert_log( 'sent', is_array( $mail_data ) ? $mail_data : array(), '' );
	}

	/**
	 * Log a failed send.
	 *
	 * @param WP_Error $wp_error Mail error (data carries the mail payload).
	 */
	public function log_failure( $wp_error ) {
		if ( ! is_wp_error( $wp_error ) ) {
			return;
		}
		$data = $wp_error->get_error_data();
		$this->insert_log( 'failed', is_array( $data ) ? $data : array(), $wp_error->get_error_message() );
	}

	/**
	 * Insert one row into the email log (when logging is enabled).
	 *
	 * @param string $status sent|failed.
	 * @param array  $data   Mail payload.
	 * @param string $error  Error message (failures only).
	 */
	private function insert_log( $status, $data, $error ) {
		$settings = $this->get_settings();
		if ( empty( $settings['log_enabled'] ) ) {
			return;
		}

		$to = '';
		if ( isset( $data['to'] ) ) {
			$to = is_array( $data['to'] ) ? implode( ', ', array_map( 'strval', $data['to'] ) ) : (string) $data['to'];
		}

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->log_table(),
			array(
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'to_email'   => mb_substr( sanitize_text_field( $to ), 0, 255 ),
				'subject'    => mb_substr( sanitize_text_field( (string) ( isset( $data['subject'] ) ? $data['subject'] : '' ) ), 0, 255 ),
				'status'     => ( 'failed' === $status ) ? 'failed' : 'sent',
				'mailer'     => (string) $settings['mailer'],
				'error'      => mb_substr( sanitize_text_field( (string) $error ), 0, 1000 ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Opportunistically bound growth between the daily cron runs.
		if ( 1 === wp_rand( 1, 50 ) ) {
			$this->prune_log();
		}
	}

	/**
	 * Delete log rows older than the retention window. (Daily cron callback.)
	 */
	public function prune_log() {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::LOG_RETENTION_DAYS * DAY_IN_SECONDS ) );
		$table  = $this->log_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name; value is prepared.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * Fetch recent log rows within the retention window, newest first.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	private function get_log_entries( $limit ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::LOG_RETENTION_DAYS * DAY_IN_SECONDS ) );
		$table  = $this->log_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT created_at, to_email, subject, status, mailer, error FROM `{$table}` WHERE created_at >= %s ORDER BY created_at DESC, id DESC LIMIT %d", $cutoff, (int) $limit ) );
	}

	/**
	 * Empty the email log.
	 */
	public function handle_clear_log() {
		$this->guard();
		check_admin_referer( self::CLEAR_LOG_ACTION );

		global $wpdb;
		$table = $this->log_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name, no user input.
		$wpdb->query( "DELETE FROM `{$table}`" );

		$this->back( 'log_cleared' );
	}

	/* -----------------------------------------------------------------
	 * Save settings
	 * ----------------------------------------------------------------- */

	/**
	 * Persist the settings form. Token fields are preserved (not in the form).
	 */
	public function handle_save() {
		$this->guard();
		check_admin_referer( self::SAVE_ACTION );

		$settings = $this->get_settings();

		$mailer = isset( $_POST['mailer'] ) ? sanitize_key( wp_unslash( $_POST['mailer'] ) ) : 'php';
		if ( ! in_array( $mailer, array( 'php', 'sendgrid', 'google', 'outlook' ), true ) ) {
			$mailer = 'php';
		}
		$settings['mailer'] = $mailer;

		$settings['from_email']  = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';
		$settings['from_name']   = isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '';
		$settings['force_from']  = isset( $_POST['force_from'] );
		$settings['log_enabled'] = isset( $_POST['log_enabled'] );

		$settings['sendgrid_api_key'] = isset( $_POST['sendgrid_api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['sendgrid_api_key'] ) ) ) : '';

		foreach ( self::$oauth_mailers as $provider ) {
			$new_id     = isset( $_POST[ $provider . '_client_id' ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $provider . '_client_id' ] ) ) ) : '';
			$new_secret = isset( $_POST[ $provider . '_client_secret' ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $provider . '_client_secret' ] ) ) ) : '';

			// Changing the OAuth app invalidates any token issued by the old one —
			// drop the connection so the user is prompted to reconnect cleanly.
			$creds_changed = ( $new_id !== (string) $settings[ $provider . '_client_id' ] )
				|| ( $new_secret !== (string) $settings[ $provider . '_client_secret' ] );

			$settings[ $provider . '_client_id' ]     = $new_id;
			$settings[ $provider . '_client_secret' ] = $new_secret;

			if ( $creds_changed ) {
				$settings[ $provider . '_refresh_token' ] = '';
				$settings[ $provider . '_access_token' ]  = '';
				$settings[ $provider . '_token_expires' ] = 0;
				$settings[ $provider . '_email' ]         = '';
				$settings[ $provider . '_needs_reauth' ]  = 0;
			}
		}

		$this->save_settings( $settings );
		$this->back( 'saved' );
	}

	/* -----------------------------------------------------------------
	 * Shared admin helpers
	 * ----------------------------------------------------------------- */

	private function guard() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to manage mail settings.', 'designstudio-flow' ) );
		}
	}

	/**
	 * Warn admins, anywhere in wp-admin, when configured mail can't be delivered.
	 */
	public function maybe_admin_notice() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		// Don't repeat the warning on the Mail / SMTP tab itself.
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$tab  = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'dsf-tools' === $page && 'mail' === $tab ) {
			return;
		}

		$settings = $this->get_settings();
		$mailer   = $settings['mailer'];
		if ( 'php' === $mailer ) {
			return;
		}

		$tab_url = add_query_arg(
			array(
				'page' => 'dsf-tools',
				'tab'  => 'mail',
			),
			admin_url( 'admin.php' )
		);

		// A revoked / expired OAuth connection — mail is actively failing.
		if ( in_array( $mailer, self::$oauth_mailers, true ) && ! empty( $settings[ $mailer . '_needs_reauth' ] ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'DesignStudio Flow Mail:', 'designstudio-flow' ),
				esc_html__( 'your connected mailbox was rejected — the token may have been revoked or expired, and email is not being sent.', 'designstudio-flow' ),
				esc_url( $tab_url ),
				esc_html__( 'Reconnect now', 'designstudio-flow' )
			);
			return;
		}

		// A mailer is chosen but incomplete — wp_mail() silently falls back to PHP.
		if ( ! $this->is_configured( $mailer, $settings ) ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'DesignStudio Flow Mail:', 'designstudio-flow' ),
				esc_html__( 'a mailer is selected but not fully configured, so email falls back to the default PHP mailer.', 'designstudio-flow' ),
				esc_url( $tab_url ),
				esc_html__( 'Finish setup', 'designstudio-flow' )
			);
		}
	}

	/**
	 * Read and validate the provider request parameter.
	 *
	 * @return string google|outlook.
	 */
	private function get_provider_param() {
		$provider = isset( $_REQUEST['provider'] ) ? sanitize_key( wp_unslash( $_REQUEST['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $provider, self::$oauth_mailers, true ) ) {
			wp_die( esc_html__( 'Unknown mail provider.', 'designstudio-flow' ) );
		}
		return $provider;
	}

	/**
	 * Redirect back to the Mail / SMTP tab with a status flag.
	 *
	 * @param string $status Status key.
	 */
	private function back( $status ) {
		$url = add_query_arg(
			array(
				'page'     => 'dsf-tools',
				'tab'      => 'mail',
				'dsf_mail' => $status,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/* -----------------------------------------------------------------
	 * Admin UI (rendered inside the Tools page "Mail / SMTP" tab)
	 * ----------------------------------------------------------------- */

	public function render_admin_tab() {
		if ( ! current_user_can( self::CAP ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'You need the Administrator role to manage mail settings.', 'designstudio-flow' ) . '</p></div>';
			return;
		}

		$this->render_notice();
		$this->render_last_error_notice();

		$settings = $this->get_settings();
		$post_url = admin_url( 'admin-post.php' );
		$mailer   = $settings['mailer'];
		?>
		<div class="dsf-tools-grid" style="display:grid;gap:20px;max-width:820px;margin-top:16px;">
			<form method="post" action="<?php echo esc_url( $post_url ); ?>">
				<?php wp_nonce_field( self::SAVE_ACTION ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">

				<div class="card" style="padding:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Mailer', 'designstudio-flow' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Choose how DesignStudio Flow (and all WordPress email) is delivered.', 'designstudio-flow' ); ?></p>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="dsf-mailer"><?php esc_html_e( 'Send mail with', 'designstudio-flow' ); ?></label></th>
							<td>
								<select id="dsf-mailer" name="mailer" data-dsf-mailer-select>
									<option value="php" <?php selected( $mailer, 'php' ); ?>><?php esc_html_e( 'Default (PHP mail)', 'designstudio-flow' ); ?></option>
									<option value="sendgrid" <?php selected( $mailer, 'sendgrid' ); ?>><?php esc_html_e( 'SendGrid', 'designstudio-flow' ); ?></option>
									<option value="google" <?php selected( $mailer, 'google' ); ?>><?php esc_html_e( 'Google / Gmail (one-click)', 'designstudio-flow' ); ?></option>
									<option value="outlook" <?php selected( $mailer, 'outlook' ); ?>><?php esc_html_e( 'Microsoft / Outlook (one-click)', 'designstudio-flow' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsf-from-email"><?php esc_html_e( 'From email', 'designstudio-flow' ); ?></label></th>
							<td>
								<input type="email" id="dsf-from-email" name="from_email" class="regular-text" value="<?php echo esc_attr( $settings['from_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
								<p class="description"><?php esc_html_e( 'For Gmail / Outlook this must be the connected mailbox (or a permitted alias).', 'designstudio-flow' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsf-from-name"><?php esc_html_e( 'From name', 'designstudio-flow' ); ?></label></th>
							<td><input type="text" id="dsf-from-name" name="from_name" class="regular-text" value="<?php echo esc_attr( $settings['from_name'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Force from', 'designstudio-flow' ); ?></th>
							<td><label><input type="checkbox" name="force_from" value="1" <?php checked( ! empty( $settings['force_from'] ) ); ?>> <?php esc_html_e( 'Override the From email/name set by other plugins or themes.', 'designstudio-flow' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Email log', 'designstudio-flow' ); ?></th>
							<td>
								<label><input type="checkbox" name="log_enabled" value="1" <?php checked( ! empty( $settings['log_enabled'] ) ); ?>> <?php esc_html_e( 'Keep a log of sent email.', 'designstudio-flow' ); ?></label>
								<p class="description">
									<?php
									printf(
										/* translators: %d: number of retention days */
										esc_html__( 'Entries are stored for up to %d days, then deleted automatically. Only the recipient, subject, mailer, and status are recorded — never the message body.', 'designstudio-flow' ),
										(int) self::LOG_RETENTION_DAYS
									);
									?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="card dsf-mailer-section" data-dsf-mailer="sendgrid" style="padding:20px;<?php echo 'sendgrid' === $mailer ? '' : 'display:none;'; ?>">
					<h2 style="margin-top:0;"><?php esc_html_e( 'SendGrid', 'designstudio-flow' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Create an API key with "Mail Send" permission, then paste it below.', 'designstudio-flow' ); ?>
						<a href="https://app.sendgrid.com/settings/api_keys" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open SendGrid API keys', 'designstudio-flow' ); ?></a>
					</p>
					<p class="description"><?php esc_html_e( 'Make sure your From email uses a domain or sender you have verified in SendGrid.', 'designstudio-flow' ); ?></p>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="dsf-sendgrid-key"><?php esc_html_e( 'API key', 'designstudio-flow' ); ?></label></th>
							<td><input type="password" id="dsf-sendgrid-key" name="sendgrid_api_key" class="regular-text" value="<?php echo esc_attr( $settings['sendgrid_api_key'] ); ?>" autocomplete="off"></td>
						</tr>
					</table>
				</div>

				<?php
				$this->render_oauth_section( 'google', __( 'Google / Gmail', 'designstudio-flow' ), $settings, $mailer );
				$this->render_oauth_section( 'outlook', __( 'Microsoft / Outlook', 'designstudio-flow' ), $settings, $mailer );
				?>

				<p class="submit" style="margin:0;"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'designstudio-flow' ); ?></button></p>
			</form>

			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Send a test email', 'designstudio-flow' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Save your settings first, then send a test message to confirm delivery.', 'designstudio-flow' ); ?></p>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
					<?php wp_nonce_field( self::TEST_ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::TEST_ACTION ); ?>">
					<input type="email" name="dsf_test_email" class="regular-text" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" placeholder="<?php esc_attr_e( 'recipient@example.com', 'designstudio-flow' ); ?>" required>
					<button type="submit" class="button"><?php esc_html_e( 'Send test', 'designstudio-flow' ); ?></button>
				</form>
			</div>

			<?php $this->render_log_card( $settings, $post_url ); ?>
		</div>

		<script>
		( function () {
			var select = document.querySelector( '[data-dsf-mailer-select]' );
			if ( ! select ) {
				return;
			}
			var sections = document.querySelectorAll( '.dsf-mailer-section' );
			function sync() {
				sections.forEach( function ( section ) {
					section.style.display = ( section.getAttribute( 'data-dsf-mailer' ) === select.value ) ? '' : 'none';
				} );
			}
			select.addEventListener( 'change', sync );
			sync();
		}() );
		</script>
		<?php
	}

	/**
	 * Render the credentials + connect card for an OAuth provider.
	 *
	 * @param string $provider google|outlook.
	 * @param string $label    Display label.
	 * @param array  $settings Settings.
	 * @param string $mailer   Active mailer.
	 */
	private function render_oauth_section( $provider, $label, $settings, $mailer ) {
		$connected    = '' !== (string) $settings[ $provider . '_refresh_token' ];
		$has_creds    = '' !== (string) $settings[ $provider . '_client_id' ] && '' !== (string) $settings[ $provider . '_client_secret' ];
		$account      = (string) $settings[ $provider . '_email' ];
		$needs_reauth = $connected && ! empty( $settings[ $provider . '_needs_reauth' ] );
		$redirect_ui  = $this->get_redirect_uri( $provider );
		$post_url     = admin_url( 'admin-post.php' );
		$console      = ( 'outlook' === $provider )
			? 'https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade'
			: 'https://console.cloud.google.com/apis/credentials';
		?>
		<div class="card dsf-mailer-section" data-dsf-mailer="<?php echo esc_attr( $provider ); ?>" style="padding:20px;<?php echo $provider === $mailer ? '' : 'display:none;'; ?>">
			<h2 style="margin-top:0;"><?php echo esc_html( $label ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: %s: provider label */
					esc_html__( 'Register an OAuth app for %s, then connect your mailbox with one click. Add this exact redirect URI to the app:', 'designstudio-flow' ),
					esc_html( $label )
				);
				?>
			</p>
			<p><code style="word-break:break-all;"><?php echo esc_html( $redirect_ui ); ?></code></p>
			<p class="description">
				<a href="<?php echo esc_url( $console ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the developer console to create the app and get your Client ID & secret', 'designstudio-flow' ); ?></a>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="dsf-<?php echo esc_attr( $provider ); ?>-id"><?php esc_html_e( 'Client ID', 'designstudio-flow' ); ?></label></th>
					<td><input type="text" id="dsf-<?php echo esc_attr( $provider ); ?>-id" name="<?php echo esc_attr( $provider ); ?>_client_id" class="regular-text" value="<?php echo esc_attr( $settings[ $provider . '_client_id' ] ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><label for="dsf-<?php echo esc_attr( $provider ); ?>-secret"><?php esc_html_e( 'Client secret', 'designstudio-flow' ); ?></label></th>
					<td><input type="password" id="dsf-<?php echo esc_attr( $provider ); ?>-secret" name="<?php echo esc_attr( $provider ); ?>_client_secret" class="regular-text" value="<?php echo esc_attr( $settings[ $provider . '_client_secret' ] ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Connection', 'designstudio-flow' ); ?></th>
					<td>
						<?php if ( $connected ) : ?>
							<span style="color:#1a7f37;font-weight:600;">
								<?php
								if ( $account ) {
									printf(
										/* translators: %s: connected mailbox address */
										esc_html__( 'Connected as %s', 'designstudio-flow' ),
										'<code>' . esc_html( $account ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									);
								} else {
									esc_html_e( 'Connected', 'designstudio-flow' );
								}
								?>
							</span>
							<?php
							$disconnect_url = wp_nonce_url(
								add_query_arg(
									array(
										'action'   => self::DISCONNECT_ACTION,
										'provider' => $provider,
									),
									$post_url
								),
								self::DISCONNECT_ACTION . '_' . $provider
							);
							?>
							<a href="<?php echo esc_url( $disconnect_url ); ?>" style="margin-left:12px;color:#b32d2e;"><?php esc_html_e( 'Disconnect', 'designstudio-flow' ); ?></a>
							<?php if ( $needs_reauth ) : ?>
								<?php
								$reauth_url = wp_nonce_url(
									add_query_arg(
										array(
											'action'   => self::AUTH_ACTION,
											'provider' => $provider,
										),
										$post_url
									),
									self::AUTH_ACTION . '_' . $provider
								);
								?>
								<p class="description" style="color:#b32d2e;margin-top:8px;">
									<?php esc_html_e( 'This connection was rejected (token revoked or expired). Email is not being delivered.', 'designstudio-flow' ); ?>
									<a href="<?php echo esc_url( $reauth_url ); ?>"><?php esc_html_e( 'Reconnect', 'designstudio-flow' ); ?></a>
								</p>
							<?php endif; ?>
						<?php elseif ( $has_creds ) : ?>
							<?php
							$authorize_url = wp_nonce_url(
								add_query_arg(
									array(
										'action'   => self::AUTH_ACTION,
										'provider' => $provider,
									),
									$post_url
								),
								self::AUTH_ACTION . '_' . $provider
							);
							?>
							<a href="<?php echo esc_url( $authorize_url ); ?>" class="button"><?php esc_html_e( 'Connect mailbox', 'designstudio-flow' ); ?></a>
							<p class="description"><?php esc_html_e( 'Uses the Client ID and secret already saved. Re-save first if you just changed them.', 'designstudio-flow' ); ?></p>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'Enter the Client ID and secret above and click "Save settings" — a Connect button will appear here.', 'designstudio-flow' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the email log card (recent entries + clear control).
	 *
	 * @param array  $settings Settings.
	 * @param string $post_url admin-post.php URL.
	 */
	private function render_log_card( $settings, $post_url ) {
		if ( empty( $settings['log_enabled'] ) ) {
			?>
			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Email log', 'designstudio-flow' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Logging is turned off. Enable "Email log" above and save to start recording sends.', 'designstudio-flow' ); ?></p>
			</div>
			<?php
			return;
		}

		$entries = $this->get_log_entries( self::LOG_LIST_LIMIT );
		?>
		<div class="card" style="padding:20px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Email log', 'designstudio-flow' ); ?></h2>

			<p class="description" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
				<span>
					<?php
					printf(
						/* translators: %d: number of retention days */
						esc_html__( 'Showing the most recent sends. Entries older than %d days are removed automatically.', 'designstudio-flow' ),
						(int) self::LOG_RETENTION_DAYS
					);
					?>
				</span>
				<?php if ( ! empty( $entries ) ) : ?>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Clear the entire email log?', 'designstudio-flow' ) ); ?>');">
						<?php wp_nonce_field( self::CLEAR_LOG_ACTION ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::CLEAR_LOG_ACTION ); ?>">
						<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Clear log', 'designstudio-flow' ); ?></button>
					</form>
				<?php endif; ?>
			</p>

			<?php if ( empty( $entries ) ) : ?>
				<p><?php esc_html_e( 'No email has been logged yet.', 'designstudio-flow' ); ?></p>
			<?php else : ?>
				<div style="max-height:420px;overflow:auto;">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'To', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'Subject', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'Mailer', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'Status', 'designstudio-flow' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $entries as $entry ) : ?>
								<?php
								$gmt    = isset( $entry->created_at ) ? (string) $entry->created_at : '';
								$local  = $gmt ? get_date_from_gmt( $gmt, 'Y-m-d H:i' ) : '';
								$failed = ( isset( $entry->status ) && 'failed' === $entry->status );
								?>
								<tr>
									<td style="white-space:nowrap;"><?php echo esc_html( $local ); ?></td>
									<td><?php echo esc_html( (string) $entry->to_email ); ?></td>
									<td><?php echo esc_html( (string) $entry->subject ); ?></td>
									<td><?php echo esc_html( (string) $entry->mailer ); ?></td>
									<td>
										<?php if ( $failed ) : ?>
											<span style="color:#b32d2e;font-weight:600;"><?php esc_html_e( 'Failed', 'designstudio-flow' ); ?></span>
											<?php if ( ! empty( $entry->error ) ) : ?>
												<br><span class="description" title="<?php echo esc_attr( (string) $entry->error ); ?>"><?php echo esc_html( mb_substr( (string) $entry->error, 0, 80 ) ); ?></span>
											<?php endif; ?>
										<?php else : ?>
											<span style="color:#1a7f37;font-weight:600;"><?php esc_html_e( 'Sent', 'designstudio-flow' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Surface the most recent real-world delivery failure (form notice, etc.).
	 */
	private function render_last_error_notice() {
		$last = get_option( self::LAST_ERROR_OPTION );
		if ( ! is_array( $last ) || empty( $last['message'] ) ) {
			return;
		}

		$when = ! empty( $last['time'] )
			? sprintf(
				/* translators: %s: human-readable time difference */
				__( '%s ago', 'designstudio-flow' ),
				human_time_diff( (int) $last['time'], time() )
			)
			: '';

		printf(
			'<div class="notice notice-warning"><p>%s <code>%s</code> %s</p></div>',
			esc_html__( 'Last delivery error:', 'designstudio-flow' ),
			esc_html( $last['message'] ),
			$when ? '<em>(' . esc_html( $when ) . ')</em>' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Render the result notice after an action redirect.
	 */
	private function render_notice() {
		if ( empty( $_GET['dsf_mail'] ) ) {
			return;
		}

		$status = sanitize_key( wp_unslash( $_GET['dsf_mail'] ) );

		if ( 'test_failed' === $status ) {
			$error = get_transient( self::TEST_ERROR_TRANSIENT );
			delete_transient( self::TEST_ERROR_TRANSIENT );
			$debug = get_transient( self::TEST_DEBUG_TRANSIENT );
			delete_transient( self::TEST_DEBUG_TRANSIENT );

			echo '<div class="notice notice-error is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: %s: mail error message */
					__( 'Test email failed: %s', 'designstudio-flow' ),
					$error ? $error : __( 'Unknown error.', 'designstudio-flow' )
				)
			);
			echo '</p>';
			if ( $debug ) {
				echo '<details style="margin:0 0 8px;"><summary style="cursor:pointer;">' . esc_html__( 'Show connection log', 'designstudio-flow' ) . '</summary>';
				echo '<pre style="white-space:pre-wrap;max-height:280px;overflow:auto;background:#f6f7f7;padding:10px;border:1px solid #dcdcde;">' . esc_html( $debug ) . '</pre>';
				echo '</details>';
			}
			echo '</div>';
			return;
		}

		$messages = array(
			'saved'               => array( 'success', __( 'Mail settings saved.', 'designstudio-flow' ) ),
			'test_sent'           => array( 'success', __( 'Test email sent. Check the inbox.', 'designstudio-flow' ) ),
			'oauth_connected'     => array( 'success', __( 'Mailbox connected.', 'designstudio-flow' ) ),
			'oauth_disconnected'  => array( 'success', __( 'Mailbox disconnected.', 'designstudio-flow' ) ),
			'log_cleared'         => array( 'success', __( 'Email log cleared.', 'designstudio-flow' ) ),
			'oauth_missing_creds' => array( 'error', __( 'Save a Client ID and secret before connecting.', 'designstudio-flow' ) ),
			'oauth_state'         => array( 'error', __( 'Could not verify the authorization response. Please try connecting again.', 'designstudio-flow' ) ),
			'oauth_denied'        => array( 'error', __( 'Authorization was cancelled or denied.', 'designstudio-flow' ) ),
			'oauth_no_code'       => array( 'error', __( 'The provider did not return an authorization code.', 'designstudio-flow' ) ),
			'oauth_token'         => array( 'error', __( 'Could not exchange the authorization code for a token. Check the Client ID, secret, and redirect URI.', 'designstudio-flow' ) ),
		);

		if ( isset( $messages[ $status ] ) ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $messages[ $status ][0] ),
				esc_html( $messages[ $status ][1] )
			);
		}
	}
}
