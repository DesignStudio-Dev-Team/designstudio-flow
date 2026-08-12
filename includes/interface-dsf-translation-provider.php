<?php
/**
 * The machine-translation provider contract.
 *
 * Machine translation is a convenience, never a publishing authority. The
 * contract is deliberately narrow: a provider receives a bounded list of plain
 * segments and returns replacements or per-segment failures. It never sees the
 * page structure, never persists anything, and its output is always treated as
 * untrusted input that still needs human review.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implemented by every translation provider adapter.
 */
interface DSF_Translation_Provider {

	/**
	 * Stable provider identifier.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human-readable provider name.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Whether the provider has a complete, valid configuration.
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Verify the endpoint and read its supported languages.
	 *
	 * Implementations must not send any site content in this request.
	 *
	 * @return array{languages:string[]}|WP_Error
	 */
	public function check_health();

	/**
	 * Translate a bounded list of plain-text segments.
	 *
	 * @param string[] $segments        Segment texts keyed by caller index.
	 * @param string   $source_language Curated source language.
	 * @param string   $target_language Curated target language.
	 * @return array{translations:array<int|string,string>,failed:array<int|string,string>}|WP_Error
	 */
	public function translate( $segments, $source_language, $target_language );
}
