<?php
/**
 * PHPMailer OAuth token provider for DesignStudio Flow.
 *
 * PHPMailer authenticates Gmail / Microsoft 365 SMTP with the XOAUTH2 mechanism,
 * which needs a base64-encoded auth string built from the mailbox address and a
 * fresh access token. Rather than pull in league/oauth2-client, we implement the
 * bundled PHPMailer OAuthTokenProvider interface directly and defer to a callback
 * that returns (refreshing when necessary) a valid access token.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . WPINC . '/PHPMailer/OAuthTokenProvider.php';

class DSF_Mail_OAuth_Provider implements \PHPMailer\PHPMailer\OAuthTokenProvider {

	/**
	 * Mailbox address the token authenticates.
	 *
	 * @var string
	 */
	private $email;

	/**
	 * Returns a valid OAuth2 access token (callable).
	 *
	 * @var callable
	 */
	private $token_callback;

	/**
	 * @param string   $email          Mailbox address.
	 * @param callable $token_callback Returns a valid access token string.
	 */
	public function __construct( $email, callable $token_callback ) {
		$this->email          = (string) $email;
		$this->token_callback = $token_callback;
	}

	/**
	 * Build the base64-encoded XOAUTH2 auth string PHPMailer hands to the server.
	 *
	 * @return string Empty string when no token is available (auth will fail cleanly).
	 */
	public function getOauth64() {
		$access_token = (string) call_user_func( $this->token_callback );

		if ( '' === $access_token || '' === $this->email ) {
			return '';
		}

		return base64_encode( 'user=' . $this->email . "\001auth=Bearer " . $access_token . "\001\001" );
	}
}
