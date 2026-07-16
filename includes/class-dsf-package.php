<?php
/**
 * Site Package — export an entire DesignStudio Flow site (pages, posts, every
 * template kind, forms, popups, redirects, and global settings) as one portable
 * ZIP and re-lay it down on a fresh install as a starter set.
 *
 * The per-item export/import machinery (building an item, restoring it, and
 * sideloading media) lives in {@see DSF_Import_Export}; this class composes it
 * across every content domain, bundles the referenced media inside the ZIP, and
 * — crucially — remaps the internal ID references that break when posts get new
 * IDs on the destination site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Package {

	const PACKAGE_FLAG  = '_dsf_package';
	const FORMAT        = '1';
	const EXPORT_ACTION = 'dsf_package_export';
	const IMPORT_ACTION = 'dsf_package_import';

	private static $instance = null;

	/**
	 * url => zip-relative path, built during export.
	 *
	 * @var array<string,string>
	 */
	private $media = array();

	/**
	 * zip-relative path => absolute local file path, built during export.
	 *
	 * @var array<string,string>
	 */
	private $media_files = array();

	/**
	 * Running total of bundled media bytes (guards the export size cap).
	 *
	 * @var int
	 */
	private $media_bytes = 0;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

	/* ---------------------------------------------------------------------
	 * Domain configuration
	 * ------------------------------------------------------------------- */

	/**
	 * Post-type domains exported/imported as generic items, in dependency order:
	 * things that are referenced (layouts, popups, templates) come before the
	 * pages/posts that reference them.
	 *
	 * @return array<string,string> domain key => post type
	 */
	private function post_domains() {
		return array(
			'layouts'      => 'dsf_layout',
			'saved_blocks' => 'dsf_saved_block',
			'templates'    => 'dsf_template',
			'popups'       => 'dsf_popup',
			'pages'        => 'page',
			'posts'        => 'post',
		);
	}

	/**
	 * Theme-builder template domains whose category assignment is remapped by
	 * term slug (product/post category term IDs never survive a site move).
	 *
	 * @return array<string,array>
	 */
	private function template_domains() {
		return array(
			'product_templates' => array(
				'post_type'       => 'dsf_product_template',
				'class'           => 'DSF_Product_Templates',
				'assignment_meta' => '_dsf_pt_assignment',
				'taxonomy'        => 'product_cat',
			),
			'shop_templates'    => array(
				'post_type'       => 'dsf_shop_template',
				'class'           => 'DSF_Shop_Templates',
				'assignment_meta' => '_dsf_st_assignment',
				'taxonomy'        => 'product_cat',
			),
			'blog_templates'    => array(
				'post_type'       => 'dsf_blog_template',
				'class'           => 'DSF_Blog_Templates',
				'assignment_meta' => '_dsf_bt_assignment',
				'taxonomy'        => 'category',
			),
		);
	}

	/**
	 * Non-secret global options carried in the package. Secrets and site-specific
	 * keys (tokens, reCAPTCHA/SMTP credentials, one-shot flags) are deliberately
	 * excluded and defended again by {@see self::secret_option_keys()}.
	 *
	 * @return string[]
	 */
	private function settings_option_keys() {
		$keys = array(
			'dsf_typography',
			'dsf_default_colors',
			'dsf_seo_defaults',
			'dsf_notification_bar',
			'dsf_global_header_footer',
			'dsf_products_enabled',
			'dsf_default_header_id',
			'dsf_default_footer_id',
		);

		/**
		 * Filter the option keys carried in a site package. Never add secrets.
		 *
		 * @param string[] $keys Option keys.
		 */
		$keys = apply_filters( 'dsf_package_settings_options', $keys );

		return array_values( array_diff( array_unique( array_filter( array_map( 'strval', (array) $keys ) ) ), $this->secret_option_keys() ) );
	}

	/**
	 * Options that must never leave the site regardless of the allow-list filter.
	 *
	 * @return string[]
	 */
	private function secret_option_keys() {
		return array(
			'dsf_github_token',
			'dsf_recaptcha_secret_key',
			'dsf_recaptcha_site_key',
			'dsf_mail_smtp',
			'dsf_legacy_flow_pages_migrated',
			'dsf_needs_rewrite_flush',
		);
	}

	/**
	 * Post statuses gathered by the exporter.
	 *
	 * @return string[]
	 */
	private function export_statuses() {
		return array( 'publish', 'draft', 'pending', 'private' );
	}

	/**
	 * Item-selectable domains (post-type domains + forms), in the order shown in
	 * the export picker, mapped to their human label.
	 *
	 * @return array<string,string>
	 */
	private function selectable_domains() {
		return array(
			'pages'             => __( 'Pages', 'designstudio-flow' ),
			'posts'             => __( 'Posts', 'designstudio-flow' ),
			'layouts'           => __( 'Headers & Footers', 'designstudio-flow' ),
			'saved_blocks'      => __( 'Saved Blocks', 'designstudio-flow' ),
			'templates'         => __( 'Templates', 'designstudio-flow' ),
			'product_templates' => __( 'Product Templates', 'designstudio-flow' ),
			'shop_templates'    => __( 'Shop Templates', 'designstudio-flow' ),
			'blog_templates'    => __( 'Blog Templates', 'designstudio-flow' ),
			'popups'            => __( 'Popups', 'designstudio-flow' ),
			'forms'             => __( 'Forms', 'designstudio-flow' ),
		);
	}

	/**
	 * Post type backing a selectable domain.
	 *
	 * @param string $domain Domain key.
	 * @return string Empty when unknown.
	 */
	private function domain_post_type( $domain ) {
		$post_domains = $this->post_domains();
		if ( isset( $post_domains[ $domain ] ) ) {
			return $post_domains[ $domain ];
		}
		$template_domains = $this->template_domains();
		if ( isset( $template_domains[ $domain ] ) ) {
			return $template_domains[ $domain ]['post_type'];
		}
		if ( 'forms' === $domain ) {
			return 'dsf_form';
		}
		return '';
	}

	/**
	 * The pickable {id,label} items for a domain, for the export selection UI.
	 *
	 * @param string $domain Domain key.
	 * @return array<int,array{id:int,label:string}>
	 */
	private function get_domain_choices( $domain ) {
		$post_type = $this->domain_post_type( $domain );
		if ( '' === $post_type ) {
			return array();
		}
		$choices = array();
		foreach ( $this->get_posts_for_type( $post_type ) as $post ) {
			$label = '' !== $post->post_title ? $post->post_title : __( '(no title)', 'designstudio-flow' );
			if ( 'dsf_layout' === $post_type ) {
				$type   = get_post_meta( $post->ID, '_dsf_layout_type', true );
				$label .= ' — ' . ( 'footer' === $type ? __( 'Footer', 'designstudio-flow' ) : __( 'Header', 'designstudio-flow' ) );
			}
			$choices[] = array(
				'id'    => (int) $post->ID,
				'label' => $label,
			);
		}
		return $choices;
	}

	/**
	 * Turn the submitted export form into a selection structure. IDs are cast to
	 * ints, so no unslashing is needed.
	 *
	 * @param array $post_data Raw $_POST.
	 * @return array{items:array<string,int[]>,settings:bool,redirects:bool}
	 */
	private function parse_selection( $post_data ) {
		$raw   = isset( $post_data['dsf_items'] ) && is_array( $post_data['dsf_items'] ) ? $post_data['dsf_items'] : array();
		$items = array();
		foreach ( array_keys( $this->selectable_domains() ) as $domain ) {
			$ids              = ( isset( $raw[ $domain ] ) && is_array( $raw[ $domain ] ) )
				? array_values( array_unique( array_filter( array_map( 'intval', $raw[ $domain ] ) ) ) )
				: array();
			$items[ $domain ] = $ids;
		}
		return array(
			'items'     => $items,
			'settings'  => ! empty( $post_data['dsf_settings'] ),
			'redirects' => ! empty( $post_data['dsf_redirects'] ),
		);
	}

	/**
	 * Allowed IDs for a domain given a selection: null means "everything".
	 *
	 * @param string     $domain    Domain key.
	 * @param array|null $selection Parsed selection or null.
	 * @return int[]|null
	 */
	private function selected_ids( $domain, $selection ) {
		if ( null === $selection ) {
			return null;
		}
		return isset( $selection['items'][ $domain ] ) ? $selection['items'][ $domain ] : array();
	}

	/**
	 * Whether a selection would export nothing at all.
	 *
	 * @param array $selection Parsed selection.
	 * @return bool
	 */
	private function selection_is_empty( $selection ) {
		foreach ( $selection['items'] as $ids ) {
			if ( ! empty( $ids ) ) {
				return false;
			}
		}
		return empty( $selection['settings'] ) && empty( $selection['redirects'] );
	}

	/* =====================================================================
	 * EXPORT
	 * ================================================================== */

	public function handle_export() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to export.', 'designstudio-flow' ) );
		}
		check_admin_referer( self::EXPORT_ACTION );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'The PHP zip extension is required to export a site package.', 'designstudio-flow' ) );
		}

		// Nonce verified by check_admin_referer() above.
		$selection = $this->parse_selection( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $this->selection_is_empty( $selection ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'dsf-tools',
						'tab'     => 'package',
						'dsf_pkg' => 'nothing',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$this->media       = array();
		$this->media_files = array();
		$this->media_bytes = 0;

		$manifest = $this->build_manifest( $selection );
		$this->stream_zip( $manifest );
		exit;
	}

	/**
	 * Assemble the manifest (selected domains/items + the media map).
	 *
	 * @param array|null $selection Null exports everything; otherwise a structure
	 *                              from {@see self::parse_selection()} restricting
	 *                              which items and which settings/redirects travel.
	 * @return array
	 */
	private function build_manifest( $selection = null ) {
		$ie      = DSF_Import_Export::get_instance();
		$domains = array();

		// Generic post-type domains.
		foreach ( $this->post_domains() as $domain => $post_type ) {
			$allowed = $this->selected_ids( $domain, $selection );
			$items   = array();
			foreach ( $this->get_posts_for_type( $post_type ) as $post ) {
				if ( null !== $allowed && ! in_array( (int) $post->ID, $allowed, true ) ) {
					continue;
				}
				$items[] = $this->build_post_item( $post, $ie );
			}
			$domains[ $domain ] = $items;
		}

		// Theme-builder templates (assignment carried by term slug).
		foreach ( $this->template_domains() as $domain => $conf ) {
			$allowed = $this->selected_ids( $domain, $selection );
			$items   = array();
			foreach ( $this->get_posts_for_type( $conf['post_type'] ) as $post ) {
				if ( null !== $allowed && ! in_array( (int) $post->ID, $allowed, true ) ) {
					continue;
				}
				$item = $this->build_post_item( $post, $ie );
				unset( $item['meta'][ $conf['assignment_meta'] ] );
				$item['assignment'] = $this->export_assignment( $post->ID, $conf );
				$items[]            = $item;
			}
			$domains[ $domain ] = $items;
		}

		// Forms (definitions only — never entries).
		$domains['forms'] = $this->export_forms( $this->selected_ids( 'forms', $selection ) );

		// Redirects (URL rules — no ID references to remap).
		$include_redirects    = ( null === $selection || ! empty( $selection['redirects'] ) ) && class_exists( 'DSF_Redirects' );
		$domains['redirects'] = $include_redirects ? DSF_Redirects::get_instance()->get_redirects() : array();

		// Global settings (non-secret allow-list).
		$include_settings    = ( null === $selection || ! empty( $selection['settings'] ) );
		$domains['settings'] = $include_settings ? $this->export_settings() : array();

		return array(
			self::PACKAGE_FLAG => true,
			'plugin'           => 'designstudio-flow',
			'version'          => defined( 'DSF_VERSION' ) ? DSF_VERSION : '1.0',
			'format'           => self::FORMAT,
			'exported_at'      => gmdate( 'c' ),
			'site_url'         => home_url(),
			'package_uid'      => wp_generate_uuid4(),
			'domains'          => $domains,
			'media'            => $this->media,
		);
	}

	/**
	 * Query every exportable post of a type. Pages/posts include non-Flow content
	 * too so a package is a faithful clone, not just the block-built subset.
	 *
	 * @param string $post_type Post type.
	 * @return WP_Post[]
	 */
	private function get_posts_for_type( $post_type ) {
		if ( ! post_type_exists( $post_type ) ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => $this->export_statuses(),
				'posts_per_page'   => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * Build a portable item for a post: the shared export shape plus a stable
	 * uid, real post_content, taxonomy terms, and a featured image, collecting
	 * any referenced media into the ZIP along the way.
	 *
	 * @param WP_Post           $post Post to export.
	 * @param DSF_Import_Export $ie   Shared engine.
	 * @return array
	 */
	private function build_post_item( $post, $ie ) {
		$item        = $ie->build_export_item( $post );
		$item['uid'] = $post->post_type . ':' . $post->ID;

		// Collect media referenced inside the block/settings meta.
		foreach ( $item['meta'] as $value ) {
			$this->collect_media( $value );
		}

		// Real content types carry their post_content and taxonomy terms.
		if ( in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			$item['content'] = $post->post_content;
			$this->collect_media_in_string( (string) $post->post_content );
			$terms = $this->export_terms( $post );
			if ( $terms ) {
				$item['terms'] = $terms;
			}
		}

		// Featured image.
		$thumb_id = (int) get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$thumb_url = wp_get_attachment_url( $thumb_id );
			if ( $thumb_url ) {
				$item['featured_media'] = $thumb_url;
				$this->collect_media_in_string( $thumb_url );
			}
		}

		return $item;
	}

	/**
	 * Export a template's category assignment as term slugs.
	 *
	 * @param int   $post_id Template ID.
	 * @param array $conf    Template domain config.
	 * @return array{mode:string,categorySlugs:string[],categoryTerms:array[]}
	 */
	private function export_assignment( $post_id, $conf ) {
		$assign = array(
			'mode'        => 'all',
			'categoryIds' => array(),
		);
		if ( is_callable( array( $conf['class'], 'get_assignment' ) ) ) {
			$assign = call_user_func( array( $conf['class'], 'get_assignment' ), $post_id );
		}

		$slugs = array();
		$terms = array();
		if ( isset( $assign['mode'] ) && 'categories' === $assign['mode'] && ! empty( $assign['categoryIds'] ) ) {
			foreach ( (array) $assign['categoryIds'] as $term_id ) {
				$term = get_term( (int) $term_id, $conf['taxonomy'] );
				if ( $term && ! is_wp_error( $term ) ) {
					$slugs[] = $term->slug;
					$terms[] = array(
						'slug' => sanitize_title( $term->slug ),
						'name' => sanitize_text_field( $term->name ),
					);
				}
			}
		}

		return array(
			'mode'          => ( ! empty( $slugs ) ) ? 'categories' : 'all',
			'categorySlugs' => $slugs,
			'categoryTerms' => $terms,
		);
	}

	/**
	 * Taxonomy terms attached to a post, as {slug,name} lists keyed by taxonomy.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string,array>
	 */
	private function export_terms( $post ) {
		$out = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			if ( 'post_format' === $taxonomy ) {
				continue;
			}
			$terms = wp_get_object_terms( $post->ID, $taxonomy );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$out[ $taxonomy ][] = array(
					'slug' => $term->slug,
					'name' => $term->name,
				);
			}
		}
		return $out;
	}

	/**
	 * @param int[]|null $allowed Only export these form IDs; null exports all.
	 */
	private function export_forms( $allowed = null ) {
		$forms = array();
		foreach ( $this->get_posts_for_type( 'dsf_form' ) as $form ) {
			if ( null !== $allowed && ! in_array( (int) $form->ID, $allowed, true ) ) {
				continue;
			}
			$forms[] = array(
				'uid'      => 'dsf_form:' . $form->ID,
				'title'    => $form->post_title,
				'slug'     => $form->post_name,
				'status'   => $form->post_status,
				'rows'     => get_post_meta( $form->ID, '_dsf_form_rows', true ),
				'settings' => get_post_meta( $form->ID, '_dsf_form_settings', true ),
			);
		}
		return $forms;
	}

	private function export_settings() {
		$settings = array();
		foreach ( $this->settings_option_keys() as $key ) {
			$value = get_option( $key, null );
			if ( null !== $value ) {
				$settings[ $key ] = $value;
			}
		}
		return $settings;
	}

	/* ------- media collection (export) -------------------------------- */

	/**
	 * Recursively register any media URLs found in a settings value.
	 *
	 * @param mixed $value Settings value.
	 */
	private function collect_media( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$this->collect_media( $item );
			}
			return;
		}
		if ( is_string( $value ) ) {
			$this->collect_media_in_string( $value );
		}
	}

	/**
	 * Register any local upload URLs referenced anywhere in a string (a bare URL,
	 * or inline <img src>/<a href> inside post_content).
	 *
	 * @param string $text Candidate string.
	 */
	private function collect_media_in_string( $text ) {
		$ie = DSF_Import_Export::get_instance();

		// A bare URL value.
		if ( $ie->looks_like_media_url( $text ) ) {
			$this->register_media( $text );
		}

		// URLs embedded in longer markup.
		if ( false !== strpos( $text, 'http' ) && preg_match_all( '#https?://[^\s"\'<>()\\\\]+#i', $text, $matches ) ) {
			foreach ( $matches[0] as $url ) {
				if ( $ie->looks_like_media_url( $url ) ) {
					$this->register_media( $url );
				}
			}
		}
	}

	/**
	 * Add a media URL to the bundle if it resolves to a local file and the export
	 * size budget still allows it. External URLs are left untouched.
	 *
	 * @param string $url Media URL.
	 */
	private function register_media( $url ) {
		if ( isset( $this->media[ $url ] ) ) {
			return;
		}

		$path = $this->resolve_local_media_path( $url );
		if ( ! $path ) {
			return; // External / non-local media stays a URL.
		}

		$max_bytes = (int) apply_filters( 'dsf_package_media_max_bytes', 512 * 1024 * 1024 );
		$size      = (int) filesize( $path );
		if ( $max_bytes > 0 && ( $this->media_bytes + $size ) > $max_bytes ) {
			return; // Over the package size budget — keep it as a URL.
		}
		$this->media_bytes += $size;

		$ext                           = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$relpath                       = 'media/' . md5( $url ) . ( $ext ? '.' . $ext : '' );
		$this->media[ $url ]           = $relpath;
		$this->media_files[ $relpath ] = $path;
	}

	/**
	 * Resolve a media URL to an absolute local file path, or null if it isn't a
	 * file in this site's uploads directory.
	 *
	 * @param string $url Media URL.
	 * @return string|null
	 */
	private function resolve_local_media_path( $url ) {
		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id ) {
			$path = get_attached_file( $attachment_id );
			if ( $path && file_exists( $path ) ) {
				return $path;
			}
		}

		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( $url, $uploads['baseurl'] ) ) {
			$path = wp_normalize_path( $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) ) );
			// Containment: never escape the uploads dir.
			if ( 0 === strpos( $path, wp_normalize_path( $uploads['basedir'] ) ) && file_exists( $path ) ) {
				return $path;
			}
		}

		return null;
	}

	/**
	 * Write the manifest + media into a temp ZIP and stream it as a download.
	 *
	 * @param array $manifest Manifest payload.
	 */
	private function stream_zip( $manifest ) {
		$tmp = wp_tempnam( 'dsf-package' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $tmp );
			wp_die( esc_html__( 'Could not create the package archive.', 'designstudio-flow' ) );
		}

		$zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		foreach ( $this->media_files as $relpath => $abs ) {
			$zip->addFile( $abs, $relpath );
		}
		$zip->close();

		$slug     = sanitize_title( get_bloginfo( 'name' ) );
		$slug     = $slug ? $slug : 'site';
		$filename = 'dsf-package-' . $slug . '-' . gmdate( 'Ymd-His' ) . '.zip';

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a temp file.
		wp_delete_file( $tmp );
	}

	/* =====================================================================
	 * IMPORT
	 * ================================================================== */

	public function handle_import() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to import.', 'designstudio-flow' ) );
		}
		check_admin_referer( self::IMPORT_ACTION );

		$redirect = add_query_arg(
			array(
				'page' => 'dsf-tools',
				'tab'  => 'package',
			),
			admin_url( 'admin.php' )
		);

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_safe_redirect( add_query_arg( 'dsf_pkg', 'no_zip', $redirect ) );
			exit;
		}

		if ( empty( $_FILES['dsf_package_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['dsf_package_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'dsf_pkg', 'no_file', $redirect ) );
			exit;
		}

		$status          = ( isset( $_POST['dsf_import_status'] ) && 'publish' === $_POST['dsf_import_status'] ) ? 'publish' : 'draft';
		$want_settings   = isset( $_POST['dsf_import_settings'] );
		$import_settings = $want_settings && current_user_can( 'manage_options' );

		$result = $this->import_from_zip( sanitize_text_field( $_FILES['dsf_package_file']['tmp_name'] ), $status, $import_settings );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'dsf_pkg', $result->get_error_code(), $redirect ) );
			exit;
		}

		$args = array(
			'dsf_pkg'            => 'done',
			'dsf_pkg_posts'      => (int) $result['posts'],
			'dsf_pkg_forms'      => (int) $result['forms'],
			'dsf_pkg_media_left' => (int) $result['media_skipped'],
			'dsf_pkg_settings'   => $result['settings'] ? 1 : 0,
		);
		if ( $want_settings && ! $import_settings ) {
			$args['dsf_pkg_settings_denied'] = 1;
		}
		wp_safe_redirect( add_query_arg( $args, $redirect ) );
		exit;
	}

	/**
	 * Import a whole-site package ZIP.
	 *
	 * @param string $zip_path        Uploaded ZIP path.
	 * @param string $status          Post status for imported content.
	 * @param bool   $import_settings Whether to also apply settings + redirects.
	 * @return array|WP_Error Result counts, or an error whose code drives the notice.
	 */
	public function import_from_zip( $zip_path, $status, $import_settings ) {
		$dir = $this->extract_package( $zip_path );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$manifest_file = $dir . '/manifest.json';
		// Reading a local extracted file, not a remote URL.
		$manifest = file_exists( $manifest_file ) ? json_decode( (string) file_get_contents( $manifest_file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( ! is_array( $manifest ) || empty( $manifest[ self::PACKAGE_FLAG ] ) || empty( $manifest['domains'] ) || ! is_array( $manifest['domains'] ) ) {
			$this->rrmdir( $dir );
			return new WP_Error( 'invalid', __( 'Invalid package file.', 'designstudio-flow' ) );
		}

		$format = isset( $manifest['format'] ) ? (string) $manifest['format'] : self::FORMAT;
		if ( version_compare( $format, self::FORMAT, '>' ) ) {
			$this->rrmdir( $dir );
			return new WP_Error( 'newer', __( 'This package was exported by a newer version of the plugin.', 'designstudio-flow' ) );
		}

		$media_source = $this->build_media_source( $manifest, $dir );

		$ie = DSF_Import_Export::get_instance();
		// A package is a deliberate whole-site clone, so give its bundled media a
		// far larger budget than a single-item import (re-running is not an option
		// here — it would duplicate every post).
		$ie->begin_media_budget(
			(int) apply_filters( 'dsf_package_import_media_max_files', 2000 ),
			(int) apply_filters( 'dsf_package_import_media_time_budget', 120 )
		);

		$domains  = $manifest['domains'];
		$id_map   = array();
		$posts    = 0;
		$template = $this->template_domains();

		// Pass 1a — generic post domains, in dependency order.
		foreach ( array_keys( $this->post_domains() ) as $domain ) {
			foreach ( $this->domain_items( $domains, $domain ) as $item ) {
				$new_id = $ie->import_item( $item, $status, true, $media_source );
				if ( $new_id ) {
					$this->after_post_import( $new_id, $item, $media_source, $ie );
					if ( ! empty( $item['uid'] ) ) {
						$id_map[ $item['uid'] ] = $new_id;
					}
					++$posts;
				}
			}
		}

		// Pass 1b — theme-builder templates + resolve their assignment by slug.
		foreach ( $template as $domain => $conf ) {
			foreach ( $this->domain_items( $domains, $domain ) as $item ) {
				$new_id = $ie->import_item( $item, $status, true, $media_source );
				if ( ! $new_id ) {
					continue;
				}
				$this->apply_assignment( $new_id, isset( $item['assignment'] ) ? $item['assignment'] : array(), $conf );
				if ( ! empty( $item['uid'] ) ) {
					$id_map[ $item['uid'] ] = $new_id;
				}
				++$posts;
			}
		}

		// Forms.
		$forms = 0;
		foreach ( $this->domain_items( $domains, 'forms' ) as $form ) {
			$new_id = $this->import_form( $form, $status );
			if ( $new_id ) {
				if ( ! empty( $form['uid'] ) ) {
					$id_map[ $form['uid'] ] = $new_id;
				}
				++$forms;
			}
		}

		// Pass 2 — remap every internal ID reference now that new IDs exist.
		$this->remap_references( $id_map );

		// Settings + redirects (opt-in, capability-gated by the caller).
		$did_settings = false;
		if ( $import_settings ) {
			$this->import_settings( isset( $domains['settings'] ) ? $domains['settings'] : array(), $id_map );
			$this->import_redirects( isset( $domains['redirects'] ) ? $domains['redirects'] : array() );
			$did_settings = true;
		}

		$this->rrmdir( $dir );

		return array(
			'posts'         => $posts,
			'forms'         => $forms,
			'settings'      => $did_settings,
			'media_skipped' => $ie->get_media_skipped(),
		);
	}

	/**
	 * Safely extract manifest.json + media/* from the ZIP into a temp dir.
	 * Rejects path-traversal ("Zip Slip") and enforces a total-size cap.
	 *
	 * @param string $zip_path Uploaded ZIP.
	 * @return string|WP_Error Extraction directory or an error.
	 */
	private function extract_package( $zip_path ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return new WP_Error( 'invalid', __( 'Could not open the package archive.', 'designstudio-flow' ) );
		}

		$max_bytes = (int) apply_filters( 'dsf_package_import_max_bytes', 1024 * 1024 * 1024 );
		$total     = 0;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive property.
			$stat   = $zip->statIndex( $i );
			$total += isset( $stat['size'] ) ? (int) $stat['size'] : 0;
			if ( $max_bytes > 0 && $total > $max_bytes ) {
				$zip->close();
				return new WP_Error( 'too_big', __( 'The package is larger than the allowed limit.', 'designstudio-flow' ) );
			}
		}

		$dir = wp_normalize_path( trailingslashit( get_temp_dir() ) . 'dsf-pkg-' . wp_generate_password( 12, false ) );
		if ( ! wp_mkdir_p( $dir ) ) {
			$zip->close();
			return new WP_Error( 'invalid', __( 'Could not create a temporary directory for import.', 'designstudio-flow' ) );
		}
		$base = trailingslashit( $dir );

		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive property.
			$name = $zip->getNameIndex( $i );
			if ( ! $this->is_safe_zip_entry( $name ) ) {
				continue;
			}
			// Whitelist: only the manifest and the media folder are ever extracted.
			if ( 'manifest.json' !== $name && 0 !== strpos( $name, 'media/' ) ) {
				continue;
			}

			$dest = wp_normalize_path( $base . $name );
			if ( 0 !== strpos( $dest, $base ) ) {
				continue; // Escapes the extraction dir — reject.
			}

			if ( '/' === substr( $name, -1 ) ) {
				wp_mkdir_p( $dest );
				continue;
			}

			wp_mkdir_p( dirname( $dest ) );
			$stream = $zip->getStream( $name );
			if ( ! $stream ) {
				continue;
			}
			$out = fopen( $dest, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			if ( $out ) {
				stream_copy_to_stream( $stream, $out );
				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}

		$zip->close();
		return $dir;
	}

	/**
	 * Whether a ZIP entry name is safe to write (no traversal, absolute path, or
	 * null byte).
	 *
	 * @param string $name Entry name.
	 * @return bool
	 */
	private function is_safe_zip_entry( $name ) {
		if ( ! is_string( $name ) || '' === $name ) {
			return false;
		}
		if ( false !== strpos( $name, "\0" ) ) {
			return false;
		}
		$normalized = str_replace( '\\', '/', $name );
		if ( '/' === $normalized[0] || preg_match( '#^[A-Za-z]:#', $normalized ) ) {
			return false;
		}
		foreach ( explode( '/', $normalized ) as $segment ) {
			if ( '..' === $segment ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Map manifest media URLs to their extracted local files (only paths that
	 * actually landed inside the extraction dir).
	 *
	 * @param array  $manifest Manifest.
	 * @param string $dir      Extraction dir.
	 * @return array<string,string> url => local path
	 */
	private function build_media_source( $manifest, $dir ) {
		$source = array();
		if ( empty( $manifest['media'] ) || ! is_array( $manifest['media'] ) ) {
			return $source;
		}
		$base = trailingslashit( wp_normalize_path( $dir ) );
		foreach ( $manifest['media'] as $url => $relpath ) {
			if ( ! is_string( $url ) || ! is_string( $relpath ) || ! $this->is_safe_zip_entry( $relpath ) ) {
				continue;
			}
			$path = wp_normalize_path( $base . $relpath );
			if ( 0 === strpos( $path, $base ) && file_exists( $path ) ) {
				$source[ $url ] = $path;
			}
		}
		return $source;
	}

	/**
	 * Items list for a domain, defensively coerced to an array of arrays.
	 *
	 * @param array  $domains All domains.
	 * @param string $domain  Domain key.
	 * @return array[]
	 */
	private function domain_items( $domains, $domain ) {
		if ( empty( $domains[ $domain ] ) || ! is_array( $domains[ $domain ] ) ) {
			return array();
		}
		return array_filter( $domains[ $domain ], 'is_array' );
	}

	/**
	 * Post-insert extras the shared importer doesn't handle: post_content (with
	 * inline media rewritten), taxonomy terms, and the featured image.
	 *
	 * @param int               $new_id       New post ID.
	 * @param array             $item         Exported item.
	 * @param array             $media_source Bundled media map.
	 * @param DSF_Import_Export $ie           Shared engine.
	 */
	private function after_post_import( $new_id, $item, $media_source, $ie ) {
		if ( isset( $item['content'] ) && '' !== $item['content'] ) {
			$cache   = array();
			$content = preg_replace_callback(
				'#https?://[^\s"\'<>()\\\\]+#i',
				function ( $m ) use ( $ie, $new_id, &$cache, $media_source ) {
					return $ie->import_media_url( $m[0], $new_id, $cache, $media_source );
				},
				(string) $item['content']
			);
			wp_update_post(
				array(
					'ID'           => $new_id,
					'post_content' => wp_slash( wp_kses_post( (string) $content ) ),
				)
			);
		}

		if ( ! empty( $item['terms'] ) && is_array( $item['terms'] ) ) {
			$this->apply_terms( $new_id, $item['terms'] );
		}

		if ( ! empty( $item['featured_media'] ) ) {
			$cache   = array();
			$new_url = $ie->import_media_url( (string) $item['featured_media'], $new_id, $cache, $media_source );
			$att_id  = attachment_url_to_postid( $new_url );
			if ( $att_id ) {
				set_post_thumbnail( $new_id, $att_id );
			}
		}
	}

	/**
	 * Create/get exported taxonomy terms on this site and attach them.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $terms   taxonomy => [{slug,name}].
	 */
	private function apply_terms( $post_id, $terms ) {
		foreach ( $terms as $taxonomy => $list ) {
			if ( ! is_string( $taxonomy ) || ! taxonomy_exists( $taxonomy ) || ! is_array( $list ) ) {
				continue;
			}
			$term_ids = array();
			foreach ( $list as $term ) {
				$slug = isset( $term['slug'] ) ? sanitize_title( $term['slug'] ) : '';
				$name = isset( $term['name'] ) ? sanitize_text_field( $term['name'] ) : $slug;
				if ( '' === $slug && '' === $name ) {
					continue;
				}
				$existing = $slug ? get_term_by( 'slug', $slug, $taxonomy ) : false;
				if ( $existing && ! is_wp_error( $existing ) ) {
					$term_ids[] = (int) $existing->term_id;
					continue;
				}
				$created = wp_insert_term( $name ? $name : $slug, $taxonomy, $slug ? array( 'slug' => $slug ) : array() );
				if ( ! is_wp_error( $created ) && ! empty( $created['term_id'] ) ) {
					$term_ids[] = (int) $created['term_id'];
				}
			}
			if ( $term_ids ) {
				wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
			}
		}
	}

	/**
	 * Resolve an exported template assignment (term slugs) to local term IDs,
	 * falling back to "all" when nothing resolves.
	 *
	 * @param int   $post_id    Template ID.
	 * @param array $assignment Exported assignment.
	 * @param array $conf       Template domain config.
	 */
	private function apply_assignment( $post_id, $assignment, $conf ) {
		$mode  = isset( $assignment['mode'] ) ? (string) $assignment['mode'] : 'all';
		$slugs = isset( $assignment['categorySlugs'] ) && is_array( $assignment['categorySlugs'] ) ? $assignment['categorySlugs'] : array();
		$term_defs = isset( $assignment['categoryTerms'] ) && is_array( $assignment['categoryTerms'] ) ? $assignment['categoryTerms'] : array();

		$ids = array();
		if ( 'categories' === $mode && taxonomy_exists( $conf['taxonomy'] ) ) {
			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', sanitize_title( $slug ), $conf['taxonomy'] );
				if ( ! $term && function_exists( 'wp_insert_term' ) ) {
					$definition = array();
					foreach ( $term_defs as $candidate ) {
						if ( is_array( $candidate ) && sanitize_title( $candidate['slug'] ?? '' ) === sanitize_title( $slug ) ) {
							$definition = $candidate;
							break;
						}
					}
					$name = isset( $definition['name'] ) ? sanitize_text_field( $definition['name'] ) : sanitize_title( $slug );
					$created = wp_insert_term( $name, $conf['taxonomy'], array( 'slug' => sanitize_title( $slug ) ) );
					if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
						$term = get_term( (int) $created['term_id'], $conf['taxonomy'] );
					}
				}
				if ( $term && ! is_wp_error( $term ) ) {
					$ids[] = (int) $term->term_id;
				}
			}
		}

		$resolved_mode = ( 'categories' === $mode && ! empty( $ids ) ) ? 'categories' : 'all';
		update_post_meta(
			$post_id,
			$conf['assignment_meta'],
			array(
				'mode'        => $resolved_mode,
				'categoryIds' => 'categories' === $resolved_mode ? $ids : array(),
			)
		);
	}

	/**
	 * Import a single form definition (never entries) and return its new ID.
	 *
	 * @param array  $form   Form payload.
	 * @param string $status Post status.
	 * @return int|false
	 */
	private function import_form( $form, $status ) {
		$rows = isset( $form['rows'] ) && is_array( $form['rows'] ) ? $form['rows'] : array();
		if ( empty( $rows ) ) {
			return false;
		}
		$title    = isset( $form['title'] ) ? sanitize_text_field( $form['title'] ) : __( 'Imported Form', 'designstudio-flow' );
		$slug     = isset( $form['slug'] ) ? sanitize_title( $form['slug'] ) : '';
		$settings = isset( $form['settings'] ) && is_array( $form['settings'] ) ? $form['settings'] : array();

		$new_id = wp_insert_post(
			array(
				'post_type'   => 'dsf_form',
				'post_status' => $status,
				'post_title'  => $title,
				'post_name'   => $slug,
			),
			true
		);
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return false;
		}
		// Written raw; the form builder re-sanitizes on next save.
		update_post_meta( $new_id, '_dsf_form_rows', wp_slash( $rows ) );
		update_post_meta( $new_id, '_dsf_form_settings', wp_slash( $settings ) );
		return $new_id;
	}

	/* ------- pass 2: reference remapping ------------------------------ */

	/**
	 * Rewrite the known ID-bearing settings keys (header/footer/form/popup/page
	 * references) inside every imported post's meta, using the old->new id map.
	 *
	 * @param array $id_map "post_type:old_id" => new_id.
	 */
	private function remap_references( $id_map ) {
		$layout = array();
		$form   = array();
		$popup  = array();
		$page   = array();
		foreach ( $id_map as $uid => $new_id ) {
			$parts = explode( ':', $uid, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$old = (int) $parts[1];
			switch ( $parts[0] ) {
				case 'dsf_layout':
					$layout[ $old ] = $new_id;
					break;
				case 'dsf_form':
					$form[ $old ] = $new_id;
					break;
				case 'dsf_popup':
					$popup[ $old ] = $new_id;
					break;
				case 'page':
				case 'post':
					$page[ $old ] = $new_id;
					break;
			}
		}

		$ref_maps = array(
			'headerTemplateId'   => $layout,
			'footerTemplateId'   => $layout,
			'formId'             => $form,
			'form_id'            => $form,
			'selectedForm'       => $form,
			'popupId'            => $popup,
			'redirectPageId'     => $page,
			'successPageId'      => $page,
			'confirmationPageId' => $page,
		);

		$meta_keys = array( '_dsf_blocks', '_dsf_settings', '_dsf_popup_settings', '_dsf_form_settings' );
		foreach ( $id_map as $new_id ) {
			foreach ( $meta_keys as $meta_key ) {
				$value = get_post_meta( $new_id, $meta_key, true );
				if ( '' === $value || null === $value || ! is_array( $value ) ) {
					continue;
				}
				$changed   = false;
				$rewritten = $this->remap_ids_in_value( $value, $ref_maps, $changed );
				if ( $changed ) {
					update_post_meta( $new_id, $meta_key, wp_slash( $rewritten ) );
				}
			}
		}
	}

	/**
	 * Recursively rewrite values whose array key names a DSF reference and whose
	 * (numeric) value has a new ID in the matching map. Only known keys are
	 * touched, so unrelated numbers (spacing, opacity…) are never rewritten.
	 *
	 * @param mixed $value    Value to walk.
	 * @param array $ref_maps key name => (old id => new id).
	 * @param bool  $changed  Set true when anything is rewritten.
	 * @return mixed
	 */
	private function remap_ids_in_value( $value, $ref_maps, &$changed ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $key => $child ) {
			if ( isset( $ref_maps[ $key ] ) && ( is_int( $child ) || ( is_string( $child ) && ctype_digit( $child ) ) ) ) {
				$old = (int) $child;
				if ( $old > 0 && isset( $ref_maps[ $key ][ $old ] ) ) {
					$value[ $key ] = $ref_maps[ $key ][ $old ];
					$changed       = true;
					continue;
				}
			}
			$value[ $key ] = $this->remap_ids_in_value( $child, $ref_maps, $changed );
		}
		return $value;
	}

	/**
	 * Apply the non-secret settings from the package (remapping the default
	 * header/footer IDs to the newly imported layouts).
	 *
	 * @param array $settings option key => value.
	 * @param array $id_map   old->new id map.
	 */
	private function import_settings( $settings, $id_map ) {
		if ( ! is_array( $settings ) ) {
			return;
		}
		$allowed = $this->settings_option_keys();
		foreach ( $settings as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}
			if ( in_array( $key, array( 'dsf_default_header_id', 'dsf_default_footer_id' ), true ) ) {
				$old = (int) $value;
				$uid = 'dsf_layout:' . $old;
				if ( $old && isset( $id_map[ $uid ] ) ) {
					$value = (int) $id_map[ $uid ];
				} else {
					continue; // Source layout wasn't in the package — don't point at a stale ID.
				}
			}
			update_option( $key, $value );
		}
	}

	/**
	 * Merge exported redirects into this site's rules (append by source; existing
	 * sources are left untouched).
	 *
	 * @param array $redirects Exported redirects.
	 */
	private function import_redirects( $redirects ) {
		if ( empty( $redirects ) || ! is_array( $redirects ) || ! class_exists( 'DSF_Redirects' ) ) {
			return;
		}
		$existing = DSF_Redirects::get_instance()->get_redirects();
		$sources  = array();
		foreach ( $existing as $rule ) {
			if ( isset( $rule['source'] ) ) {
				$sources[ strtolower( $rule['source'] ) ] = true;
			}
		}
		$merged = $existing;
		foreach ( $redirects as $raw ) {
			$rule = DSF_Redirects::sanitize_redirect( $raw );
			if ( ! $rule ) {
				continue;
			}
			$src = isset( $rule['source'] ) ? strtolower( $rule['source'] ) : '';
			if ( '' === $src || isset( $sources[ $src ] ) ) {
				continue;
			}
			$sources[ $src ] = true;
			$merged[]        = $rule;
		}
		update_option( 'dsf_redirects', $merged, true );
	}

	/* ------- misc ----------------------------------------------------- */

	/**
	 * Recursively delete a directory tree (the temporary extraction dir).
	 *
	 * @param string $dir Directory.
	 */
	private function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->rrmdir( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup of our own temp dir.
		@rmdir( $dir );
	}

	/* =====================================================================
	 * ADMIN UI (rendered inside the Tools page "Site Package" tab)
	 * ================================================================== */

	/**
	 * The export picker: a checkbox per item grouped by type (all pre-checked),
	 * plus site-wide settings/redirects toggles. Submits to handle_export().
	 *
	 * @param bool $can_settings Whether the current user may export global settings.
	 */
	private function render_export_form( $can_settings ) {
		?>
		<form method="post" id="dsf-pkg-export" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::EXPORT_ACTION ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>">

			<p style="margin:8px 0;">
				<button type="button" class="button button-small" data-dsf-select="all"><?php esc_html_e( 'Select all', 'designstudio-flow' ); ?></button>
				<button type="button" class="button button-small" data-dsf-select="none"><?php esc_html_e( 'Deselect all', 'designstudio-flow' ); ?></button>
			</p>

			<?php
			foreach ( $this->selectable_domains() as $domain => $label ) :
				$choices = $this->get_domain_choices( $domain );
				if ( empty( $choices ) ) {
					continue;
				}
				?>
				<details open style="border:1px solid #dcdcde;border-radius:4px;margin-bottom:8px;padding:8px 12px;">
					<summary style="cursor:pointer;font-weight:600;">
						<label style="cursor:pointer;">
							<input type="checkbox" class="dsf-pkg-master" data-domain="<?php echo esc_attr( $domain ); ?>" checked>
							<?php echo esc_html( $label ); ?> <span class="description">(<?php echo (int) count( $choices ); ?>)</span>
						</label>
					</summary>
					<div class="dsf-pkg-items" style="max-height:200px;overflow:auto;margin:8px 0 4px;padding-left:8px;">
						<?php foreach ( $choices as $choice ) : ?>
							<label style="display:block;padding:2px 0;">
								<input type="checkbox" class="dsf-pkg-item" data-domain="<?php echo esc_attr( $domain ); ?>" name="dsf_items[<?php echo esc_attr( $domain ); ?>][]" value="<?php echo esc_attr( $choice['id'] ); ?>" checked>
								<?php echo esc_html( $choice['label'] ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</details>
			<?php endforeach; ?>

			<fieldset style="border:1px solid #dcdcde;border-radius:4px;margin-bottom:12px;padding:8px 12px;">
				<legend style="font-weight:600;padding:0 4px;"><?php esc_html_e( 'Site-wide', 'designstudio-flow' ); ?></legend>
				<label style="display:block;padding:2px 0;<?php echo $can_settings ? '' : 'opacity:.6;'; ?>">
					<input type="checkbox" name="dsf_settings" value="1" <?php checked( $can_settings ); ?> <?php disabled( ! $can_settings ); ?>>
					<?php esc_html_e( 'Global settings (typography, colors, default header/footer, SEO defaults)', 'designstudio-flow' ); ?>
				</label>
				<label style="display:block;padding:2px 0;">
					<input type="checkbox" name="dsf_redirects" value="1" checked>
					<?php esc_html_e( 'Redirects', 'designstudio-flow' ); ?>
				</label>
				<?php if ( ! $can_settings ) : ?>
					<p class="description"><?php esc_html_e( 'Only administrators can export global settings.', 'designstudio-flow' ); ?></p>
				<?php endif; ?>
			</fieldset>

			<p class="description" style="margin-bottom:8px;"><?php esc_html_e( 'Tip: if a selected page references a header, form, or popup you leave out, that link won\'t resolve on the destination — include the things your pages depend on.', 'designstudio-flow' ); ?></p>
			<p class="submit" style="margin-top:0;"><button type="submit" class="button button-primary"><?php esc_html_e( 'Export selected', 'designstudio-flow' ); ?></button></p>
		</form>
		<?php
		$this->print_export_script();
	}

	/**
	 * Progressive-enhancement JS for the picker: per-type "select all" master
	 * checkboxes (with an indeterminate state) and the global select/deselect
	 * buttons. The form works without it; this only saves clicks.
	 */
	private function print_export_script() {
		?>
		<script>
		( function () {
			var root = document.getElementById( 'dsf-pkg-export' );
			if ( ! root ) { return; }
			function items( d ) { return root.querySelectorAll( '.dsf-pkg-item[data-domain="' + d + '"]' ); }
			function syncMaster( d ) {
				var master = root.querySelector( '.dsf-pkg-master[data-domain="' + d + '"]' );
				if ( ! master ) { return; }
				var list = items( d ), checked = 0;
				list.forEach( function ( c ) { if ( c.checked ) { checked++; } } );
				master.checked = list.length > 0 && checked === list.length;
				master.indeterminate = checked > 0 && checked < list.length;
			}
			root.querySelectorAll( '.dsf-pkg-master' ).forEach( function ( m ) {
				m.addEventListener( 'change', function () {
					items( m.getAttribute( 'data-domain' ) ).forEach( function ( c ) { c.checked = m.checked; } );
				} );
			} );
			root.querySelectorAll( '.dsf-pkg-item' ).forEach( function ( c ) {
				c.addEventListener( 'change', function () { syncMaster( c.getAttribute( 'data-domain' ) ); } );
			} );
			root.querySelectorAll( '[data-dsf-select]' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					var on = b.getAttribute( 'data-dsf-select' ) === 'all';
					root.querySelectorAll( '.dsf-pkg-item, .dsf-pkg-master' ).forEach( function ( x ) {
						x.checked = on;
						x.indeterminate = false;
					} );
				} );
			} );
		} )();
		</script>
		<?php
	}

	public function render_admin_tab() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$can_settings = current_user_can( 'manage_options' );
		?>
		<div class="dsf-tools-grid" style="display:grid;grid-template-columns:minmax(0,1fr);gap:20px;max-width:760px;margin-top:16px;">
			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Export Site Package', 'designstudio-flow' ); ?></h2>
				<p><?php esc_html_e( 'Download this site as one portable .zip — with all referenced media — to import into a fresh site as your starter set. Everything is selected by default; untick anything you want to leave out.', 'designstudio-flow' ); ?></p>
				<?php if ( ! class_exists( 'ZipArchive' ) ) : ?>
					<p class="description" style="color:#b32d2e;"><?php esc_html_e( 'The PHP zip extension is not available on this server, so packages cannot be created here.', 'designstudio-flow' ); ?></p>
				<?php else : ?>
					<?php $this->render_export_form( $can_settings ); ?>
				<?php endif; ?>
				<p class="description" style="margin-top:12px;"><?php esc_html_e( 'Secrets are never included: API tokens, reCAPTCHA keys, and mail credentials stay on this site. Form entries are not exported.', 'designstudio-flow' ); ?></p>
			</div>

			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Import Site Package', 'designstudio-flow' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field( self::IMPORT_ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>">
					<p><?php esc_html_e( 'Upload a .zip exported above. Everything is imported as new content — existing pages, posts, templates, and forms are never overwritten.', 'designstudio-flow' ); ?></p>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="dsf-package-file"><?php esc_html_e( 'Package file', 'designstudio-flow' ); ?></label></th>
							<td><input type="file" id="dsf-package-file" name="dsf_package_file" accept=".zip,application/zip" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="dsf-package-status"><?php esc_html_e( 'Imported content status', 'designstudio-flow' ); ?></label></th>
							<td>
								<select id="dsf-package-status" name="dsf_import_status">
									<option value="draft"><?php esc_html_e( 'Draft (recommended)', 'designstudio-flow' ); ?></option>
									<option value="publish"><?php esc_html_e( 'Published', 'designstudio-flow' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Global settings', 'designstudio-flow' ); ?></th>
							<td>
								<label for="dsf-package-settings">
									<input type="checkbox" id="dsf-package-settings" name="dsf_import_settings" value="1" <?php disabled( ! $can_settings ); ?>>
									<?php esc_html_e( 'Also import global settings & redirects (overwrites this site\'s typography, colors, default header/footer, SEO defaults, and adds redirects)', 'designstudio-flow' ); ?>
								</label>
								<?php if ( ! $can_settings ) : ?>
									<p class="description"><?php esc_html_e( 'Requires the Administrator capability to change site settings.', 'designstudio-flow' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'designstudio-flow' ); ?></button></p>
				</form>
			</div>
		</div>
		<?php
	}

	public function show_admin_notices() {
		if ( ! isset( $_GET['dsf_pkg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$code = sanitize_key( wp_unslash( $_GET['dsf_pkg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'done' === $code ) {
			$posts    = isset( $_GET['dsf_pkg_posts'] ) ? intval( $_GET['dsf_pkg_posts'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$forms    = isset( $_GET['dsf_pkg_forms'] ) ? intval( $_GET['dsf_pkg_forms'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$settings = ! empty( $_GET['dsf_pkg_settings'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: content count, 2: forms count, 3: settings note */
						__( 'Site package imported: %1$d content items, %2$d forms.%3$s', 'designstudio-flow' ),
						$posts,
						$forms,
						$settings ? ' ' . __( 'Global settings & redirects were applied.', 'designstudio-flow' ) : ''
					)
				)
			);

			$media_left = isset( $_GET['dsf_pkg_media_left'] ) ? intval( $_GET['dsf_pkg_media_left'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $media_left > 0 ) {
				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %d: media files skipped */
							_n(
								'%d media file exceeded the import limit and kept its original URL. Raise the "dsf_package_import_media_max_files" limit, then import into a clean site to bundle them all.',
								'%d media files exceeded the import limit and kept their original URLs. Raise the "dsf_package_import_media_max_files" limit, then import into a clean site to bundle them all.',
								$media_left,
								'designstudio-flow'
							),
							$media_left
						)
					)
				);
			}

			if ( ! empty( $_GET['dsf_pkg_settings_denied'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
					esc_html__( 'Global settings were not imported because you lack the Administrator capability.', 'designstudio-flow' )
				);
			}
			return;
		}

		$messages = array(
			'nothing' => __( 'Nothing was selected to export.', 'designstudio-flow' ),
			'no_file' => __( 'No package file was uploaded.', 'designstudio-flow' ),
			'no_zip'  => __( 'The PHP zip extension is required to import a site package.', 'designstudio-flow' ),
			'invalid' => __( 'Invalid package. Please upload a .zip exported by DesignStudio Flow.', 'designstudio-flow' ),
			'newer'   => __( 'This package was exported by a newer version of DesignStudio Flow. Please update the plugin first.', 'designstudio-flow' ),
			'too_big' => __( 'The package is larger than the allowed import limit.', 'designstudio-flow' ),
		);
		if ( isset( $messages[ $code ] ) ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $messages[ $code ] ) );
		}
	}
}
