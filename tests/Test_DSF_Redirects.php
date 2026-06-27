<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-redirects.php';

class Test_DSF_Redirects extends TestCase {

	public function test_normalize_path_collapses_to_leading_slash_no_trailing() {
		$this->assertSame( '/', DSF_Redirects::normalize_path( '/' ) );
		$this->assertSame( '/', DSF_Redirects::normalize_path( '' ) );
		$this->assertSame( '/old-page', DSF_Redirects::normalize_path( 'old-page' ) );
		$this->assertSame( '/old-page', DSF_Redirects::normalize_path( '/old-page/' ) );
		$this->assertSame( '/a/b', DSF_Redirects::normalize_path( '/a/b/?x=1#frag' ) );
	}

	public function test_normalize_source_extracts_path_from_full_url() {
		$this->assertSame( '/blog/post', DSF_Redirects::normalize_source( 'https://example.com/blog/post/' ) );
		$this->assertSame( '/blog/post', DSF_Redirects::normalize_source( '/blog/post' ) );
		$this->assertSame( '', DSF_Redirects::normalize_source( '   ' ) );
	}

	public function test_sanitize_target_handles_paths_urls_and_tags() {
		$this->assertSame( '/new-page', DSF_Redirects::sanitize_target( 'new-page' ) );
		$this->assertSame( 'https://example.com/x', DSF_Redirects::sanitize_target( 'https://example.com/x' ) );
		$this->assertSame( '/clean', DSF_Redirects::sanitize_target( '<b>/clean</b>' ) );
		$this->assertSame( '', DSF_Redirects::sanitize_target( '' ) );
	}

	public function test_sanitize_type_only_allows_301_or_302() {
		$this->assertSame( 301, DSF_Redirects::sanitize_type( 301 ) );
		$this->assertSame( 302, DSF_Redirects::sanitize_type( '302' ) );
		$this->assertSame( 301, DSF_Redirects::sanitize_type( 307 ) );
		$this->assertSame( 301, DSF_Redirects::sanitize_type( 'nonsense' ) );
	}

	public function test_truthy_reads_spreadsheet_values() {
		foreach ( array( '1', 'true', 'YES', 'on', 'Enabled', true ) as $value ) {
			$this->assertTrue( DSF_Redirects::truthy( $value ), var_export( $value, true ) );
		}
		foreach ( array( '0', 'false', 'no', '', false ) as $value ) {
			$this->assertFalse( DSF_Redirects::truthy( $value ), var_export( $value, true ) );
		}
	}

	public function test_is_loop_only_flags_self_referencing_relative_targets() {
		$this->assertTrue( DSF_Redirects::is_loop( '/a', '/a/' ) );
		$this->assertFalse( DSF_Redirects::is_loop( '/a', '/b' ) );
		// Absolute targets are not loop-checked (host is unknown to the pure helper).
		$this->assertFalse( DSF_Redirects::is_loop( '/a', 'https://example.com/a' ) );
	}

	public function test_sanitize_redirect_rejects_incomplete_and_looping_rows() {
		$this->assertNull( DSF_Redirects::sanitize_redirect( array( 'source' => '/a' ) ) );
		$this->assertNull( DSF_Redirects::sanitize_redirect( array( 'target' => '/b' ) ) );
		$this->assertNull( DSF_Redirects::sanitize_redirect( array( 'source' => '/a', 'target' => '/a' ) ) );
	}

	public function test_sanitize_redirect_builds_normalized_record() {
		$redirect = DSF_Redirects::sanitize_redirect(
			array(
				'source'  => 'https://example.com/old/',
				'target'  => 'new',
				'type'    => '302',
				'enabled' => 'yes',
			)
		);

		$this->assertSame( '/old', $redirect['source'] );
		$this->assertSame( '/new', $redirect['target'] );
		$this->assertSame( 302, $redirect['type'] );
		$this->assertTrue( $redirect['enabled'] );
		$this->assertSame( 0, $redirect['hits'] );
		$this->assertNotEmpty( $redirect['id'] );
	}

	public function test_parse_csv_skips_header_and_invalid_rows() {
		$csv = "source,target,type,enabled\n/old,/new,301,1\n/bad,,301,1\n/temp,https://x.test/,302,0\n";

		$redirects = DSF_Redirects::parse_csv( $csv );

		$this->assertCount( 2, $redirects );
		$this->assertSame( '/old', $redirects[0]['source'] );
		$this->assertSame( '/new', $redirects[0]['target'] );
		$this->assertSame( 301, $redirects[0]['type'] );
		$this->assertTrue( $redirects[0]['enabled'] );

		$this->assertSame( '/temp', $redirects[1]['source'] );
		$this->assertSame( 302, $redirects[1]['type'] );
		$this->assertFalse( $redirects[1]['enabled'] );
	}

	public function test_to_csv_round_trips_through_parse_csv() {
		$original = DSF_Redirects::parse_csv( "/a,/b,301,1\n/c,/d,302,0\n" );
		$csv      = DSF_Redirects::to_csv( $original );
		$again    = DSF_Redirects::parse_csv( $csv );

		$this->assertCount( 2, $again );
		$this->assertSame( '/a', $again[0]['source'] );
		$this->assertSame( '/b', $again[0]['target'] );
		$this->assertSame( 302, $again[1]['type'] );
		$this->assertFalse( $again[1]['enabled'] );
	}

	public function test_to_csv_escapes_cells_with_separators() {
		$csv = DSF_Redirects::to_csv(
			array(
				array( 'source' => '/a,b', 'target' => '/c"d', 'type' => 301, 'enabled' => true ),
			)
		);

		$this->assertStringContainsString( '"/a,b"', $csv );
		$this->assertStringContainsString( '"/c""d"', $csv );
	}
}
