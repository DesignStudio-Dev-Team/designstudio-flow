<?php
/**
 * Form Connections — dispatches submissions to configured endpoints
 * (Zapier webhooks, Make/Integromat, Salesforce middleware, custom URLs).
 *
 * Submissions are queued via wp_schedule_single_event so a slow endpoint
 * never blocks the user's submit response. One automatic retry is attempted
 * on transient failure.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Connections {

	const CRON_HOOK   = 'dsf_dispatch_connection';
	const TEST_ACTION = 'dsf_test_connection';
	const LOG_META    = '_dsf_connection_log';
	const MAX_ATTEMPTS = 2; // 1 initial + 1 retry.
	const RETRY_DELAY  = 60; // seconds.

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'dsf_form_submitted', array( $this, 'queue_dispatch' ), 10, 3 );
		add_action( self::CRON_HOOK, array( $this, 'process_dispatch' ), 10, 5 );
		add_action( 'wp_ajax_' . self::TEST_ACTION, array( $this, 'handle_test' ) );
	}

	/**
	 * Hooked to dsf_form_submitted — schedule one cron event per enabled connection.
	 */
	public function queue_dispatch( $form_id, $submission, $entry_id ) {
		$connections = $this->get_form_connections( $form_id );
		if ( empty( $connections ) ) {
			return;
		}

		foreach ( $connections as $connection ) {
			if ( empty( $connection['enabled'] ) || empty( $connection['endpointUrl'] ) ) {
				continue;
			}

			wp_schedule_single_event(
				time() + 1,
				self::CRON_HOOK,
				array( intval( $form_id ), intval( $entry_id ), (string) $connection['id'], $submission, 0 )
			);
		}
	}

	/**
	 * Cron callback — performs the actual HTTP POST for one connection.
	 * Retries once on failure.
	 */
	public function process_dispatch( $form_id, $entry_id, $connection_id, $submission, $attempt ) {
		$connection = $this->get_connection( $form_id, $connection_id );
		if ( ! $connection ) {
			return;
		}

		$payload  = $this->build_payload( $form_id, $entry_id, $submission, false );
		$response = $this->dispatch( $connection, $payload );

		$success = ! is_wp_error( $response );
		$status  = $success ? wp_remote_retrieve_response_code( $response ) : $response->get_error_message();

		$this->log_attempt( $entry_id, $connection, $attempt, $success, $status );

		if ( ! $success && $attempt + 1 < self::MAX_ATTEMPTS ) {
			wp_schedule_single_event(
				time() + self::RETRY_DELAY,
				self::CRON_HOOK,
				array( intval( $form_id ), intval( $entry_id ), (string) $connection_id, $submission, $attempt + 1 )
			);
		}
	}

	/**
	 * AJAX: send a sample payload to a connection's URL and report the result.
	 * Used by the "Test" button in the form builder.
	 */
	public function handle_test() {
		if ( ! check_ajax_referer( 'dsf_forms_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'designstudio-flow' ) ), 403 );
		}
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'designstudio-flow' ) ), 403 );
		}

		$endpoint_url = isset( $_POST['endpointUrl'] ) ? esc_url_raw( wp_unslash( $_POST['endpointUrl'] ) ) : '';
		$secret       = isset( $_POST['secret'] ) ? sanitize_text_field( wp_unslash( $_POST['secret'] ) ) : '';
		$timeout      = isset( $_POST['timeout'] ) ? intval( $_POST['timeout'] ) : 8;
		$form_id      = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;

		if ( '' === $endpoint_url || ! wp_http_validate_url( $endpoint_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid HTTPS endpoint URL.', 'designstudio-flow' ) ) );
		}

		$connection = array(
			'endpointUrl' => $endpoint_url,
			'secret'      => $secret,
			'timeout'     => $timeout,
		);

		$payload  = $this->build_payload( $form_id, 0, $this->sample_submission(), true );
		$response = $this->dispatch( $connection, $payload );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array(
				'message' => $response->get_error_message(),
				'code'    => $response->get_error_code(),
			) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: HTTP status code */
				__( 'Endpoint responded with HTTP %d.', 'designstudio-flow' ),
				$status
			),
			'status'  => $status,
			'body'    => substr( (string) wp_remote_retrieve_body( $response ), 0, 500 ),
		) );
	}

	/**
	 * Read the connections array from the form's saved settings.
	 */
	private function get_form_connections( $form_id ) {
		$settings = get_post_meta( $form_id, '_dsf_form_settings', true );
		if ( ! is_array( $settings ) || empty( $settings['connections'] ) || ! is_array( $settings['connections'] ) ) {
			return array();
		}
		return $settings['connections'];
	}

	private function get_connection( $form_id, $connection_id ) {
		foreach ( $this->get_form_connections( $form_id ) as $connection ) {
			if ( isset( $connection['id'] ) && (string) $connection['id'] === (string) $connection_id ) {
				return $connection;
			}
		}
		return null;
	}

	/**
	 * Shape the payload sent to every connection. Flat `fields` map makes this
	 * Zapier-friendly out of the box; the metadata sits at the top level.
	 */
	private function build_payload( $form_id, $entry_id, $submission, $is_test ) {
		$form = $form_id ? get_post( $form_id ) : null;
		return array(
			'form_id'      => intval( $form_id ),
			'form_title'   => $form ? $form->post_title : '',
			'entry_id'     => intval( $entry_id ),
			'submitted_at' => gmdate( 'c' ),
			'site_url'     => home_url(),
			'test'         => (bool) $is_test,
			'fields'       => is_array( $submission ) ? $submission : array(),
		);
	}

	private function sample_submission() {
		return array(
			'first_name' => 'Jane',
			'last_name'  => 'Doe',
			'email'      => 'jane.doe@example.com',
			'phone'      => '+1-555-555-0100',
			'company'    => 'Acme Inc.',
			'message'    => 'This is a test payload from DesignStudio Flow.',
		);
	}

	/**
	 * POST the payload to the endpoint with optional HMAC signature.
	 */
	private function dispatch( $connection, $payload ) {
		$body    = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$timeout = max( 1, min( 120, intval( $connection['timeout'] ?? 8 ) ) );

		$headers = array(
			'Content-Type' => 'application/json; charset=utf-8',
			'Accept'       => 'application/json, text/plain, */*',
			'User-Agent'   => 'DesignStudio-Flow-Forms/' . ( defined( 'DSF_VERSION' ) ? DSF_VERSION : '1.0' ),
		);

		if ( ! empty( $connection['secret'] ) ) {
			$headers['X-DSForm-Signature']   = 'sha256=' . hash_hmac( 'sha256', $body, (string) $connection['secret'] );
			$headers['X-DSForm-Signature-Ts'] = (string) time();
		}

		$response = wp_remote_post(
			$connection['endpointUrl'],
			array(
				'timeout'     => $timeout,
				'redirection' => 3,
				'blocking'    => true,
				'headers'     => $headers,
				'body'        => $body,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'dsf_connection_http_' . $code,
				sprintf( /* translators: %d: HTTP status code */ __( 'Endpoint returned HTTP %d.', 'designstudio-flow' ), $code )
			);
		}

		return $response;
	}

	/**
	 * Append a delivery attempt to the entry's connection log.
	 */
	private function log_attempt( $entry_id, $connection, $attempt, $success, $status ) {
		if ( ! $entry_id ) {
			return;
		}

		$log = get_post_meta( $entry_id, self::LOG_META, true );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'connection_id' => $connection['id'] ?? '',
			'label'         => $connection['label'] ?? '',
			'attempt'       => intval( $attempt ),
			'time'          => gmdate( 'c' ),
			'success'       => (bool) $success,
			'status'        => is_int( $status ) ? $status : substr( (string) $status, 0, 250 ),
		);

		// Keep at most 50 log entries per submission.
		if ( count( $log ) > 50 ) {
			$log = array_slice( $log, -50 );
		}

		update_post_meta( $entry_id, self::LOG_META, $log );
	}
}
