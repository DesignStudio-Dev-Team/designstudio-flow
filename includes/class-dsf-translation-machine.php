<?php
/**
 * Machine-translation orchestration.
 *
 * Ties the bounded extractor, the configured provider, and the reassembler
 * together. Output is always a draft-quality prefill: it is marked machine
 * prefilled, any existing review is cleared, and a provider outage degrades to
 * "nothing was translated" rather than losing the source content.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Machine {

	/** @var self|null */
	private static $instance = null;

	/** @var DSF_Translation_Extractor */
	private $extractor;

	/** @var DSF_Translation_Reassembler */
	private $reassembler;

	/** @var callable Resolves the configured provider. */
	private $provider_resolver;

	/**
	 * @param array $services Optional service overrides for tests.
	 */
	public function __construct( $services = array() ) {
		$services                = is_array( $services ) ? $services : array();
		$this->extractor         = $services['extractor'] ?? DSF_Translation_Extractor::get_instance();
		$this->reassembler       = $services['reassembler'] ?? DSF_Translation_Reassembler::get_instance();
		$this->provider_resolver = isset( $services['provider_resolver'] ) && is_callable( $services['provider_resolver'] )
			? $services['provider_resolver']
			: array( 'DSF_Translation_Providers', 'get_active_provider' );
	}

	/**
	 * Return the shared orchestrator.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Whether a usable provider is configured. */
	public function is_available() {
		$provider = call_user_func( $this->provider_resolver );
		return $provider instanceof DSF_Translation_Provider && $provider->is_configured();
	}

	/**
	 * Produce a machine-prefilled document for one target member.
	 *
	 * The returned document is sanitized and ready for the clone/review
	 * workflow to store. Nothing is persisted here.
	 *
	 * @param array  $member          Target relationship member.
	 * @param string $source_language Curated source language.
	 * @param string $target_language Curated target language.
	 * @return array{document:array,translated:int,failed:array,truncated:bool}|WP_Error
	 */
	public function prefill( $member, $source_language, $target_language ) {
		$provider = call_user_func( $this->provider_resolver );
		if ( ! $provider instanceof DSF_Translation_Provider ) {
			return new WP_Error( 'dsf_translation_provider_missing', __( 'No translation service is configured. Translate this content manually.', 'designstudio-flow' ) );
		}
		if ( ! $provider->is_configured() ) {
			return new WP_Error( 'dsf_translation_provider_config', __( 'The translation service is not configured correctly. Translate this content manually.', 'designstudio-flow' ) );
		}

		$source_language = DSF_Multilingual_Settings::normalize_locale_code( $source_language );
		$target_language = DSF_Multilingual_Settings::normalize_locale_code( $target_language );
		if ( '' === $source_language || '' === $target_language || $source_language === $target_language ) {
			return new WP_Error( 'dsf_translation_pair', __( 'Choose a different source and target language.', 'designstudio-flow' ) );
		}

		$extracted = $this->extractor->extract_member( $member );
		if ( $extracted instanceof WP_Error ) {
			return $extracted;
		}
		if ( empty( $extracted['segments'] ) ) {
			return array(
				'document'   => array(),
				'translated' => 0,
				'failed'     => array(),
				'truncated'  => ! empty( $extracted['truncated'] ),
			);
		}

		$texts = array();
		foreach ( $extracted['segments'] as $index => $segment ) {
			$texts[ $index ] = $segment['text'];
		}

		$result = $provider->translate( $texts, $source_language, $target_language );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$translated = array();
		foreach ( is_array( $result['translations'] ?? null ) ? $result['translations'] : array() as $index => $text ) {
			if ( ! isset( $extracted['segments'][ $index ] ) || ! is_string( $text ) ) {
				continue;
			}
			$segment         = $extracted['segments'][ $index ];
			$segment['text'] = $text;
			$translated[]    = $segment;
		}

		$applied = $this->reassembler->apply_to_member( $member, $translated );
		if ( $applied instanceof WP_Error ) {
			return $applied;
		}

		return array(
			'document'   => $applied['document'],
			'translated' => $applied['applied'],
			'failed'     => is_array( $result['failed'] ?? null ) ? $result['failed'] : array(),
			'truncated'  => ! empty( $extracted['truncated'] ),
		);
	}

	/**
	 * Record that a member's current content came from a machine.
	 *
	 * Machine output is never reviewed output, so any earlier review is cleared
	 * at the same time and the member returns to `Needs review`.
	 *
	 * @param DSF_Translation_Workflow $workflow      Workflow service.
	 * @param string                   $group_uuid    Portable group UUID.
	 * @param string                   $language      Target language.
	 * @param callable                 $authorization Capability callback.
	 * @return true|WP_Error
	 */
	public function mark_prefilled( $workflow, $group_uuid, $language, $authorization ) {
		if ( ! $workflow instanceof DSF_Translation_Workflow ) {
			return new WP_Error( 'dsf_translation_workflow', __( 'The translation workflow service is unavailable.', 'designstudio-flow' ) );
		}

		$cleared = $workflow->clear_review( $group_uuid, $language, $authorization );
		if ( $cleared instanceof WP_Error ) {
			return $cleared;
		}

		$marked = $workflow->set_machine_prefilled( $group_uuid, $language, true, $authorization );
		return $marked instanceof WP_Error ? $marked : true;
	}
}
