<?php
/**
 * Reusable blog / post-archive templates for DesignStudio Flow.
 *
 * A Blog Template is a site-wide design for the posts page and post archives —
 * category, tag, author, and date (theme-builder style). Each template applies
 * to all blog archives or to specific post categories; its blocks bind to
 * whichever archive is being viewed, reading the posts from the main query
 * WordPress has already prepared (sticky posts, ordering, and pagination all
 * respected). No WooCommerce dependency; theme template files are never
 * modified.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Blog_Templates {

	const POST_TYPE = 'dsf_blog_template';

	private static $instance = null;

	/** Per-request cache of the active-template query (null until first use). */
	private $active_templates = null;

	/** Per-request cache of the resolved template for the current archive. */
	private $resolved_current = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'post_row_actions', array( $this, 'add_edit_link' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Resolve the active, published blog template for the current archive request.
	 *
	 * Category-targeted templates win on matching category archives; "all blog
	 * archives" templates cover the posts page, tag, author, and date archives.
	 * Returns 0 when the request is not a blog archive or no template matches.
	 * Filterable via `dsf_blog_template_id`.
	 *
	 * @return int Template post ID, or 0.
	 */
	public function resolve_template_for_current_archive() {
		if ( null !== $this->resolved_current ) {
			return $this->resolved_current;
		}

		$template_id = 0;
		if ( self::is_blog_archive() ) {
			$template_id = $this->find_matching_template();
		}

		/**
		 * Filter the resolved blog template ID for the current archive.
		 *
		 * @param int $template_id Resolved template post ID (0 = none).
		 */
		$template_id = (int) apply_filters( 'dsf_blog_template_id', $template_id );

		$this->resolved_current = $template_id;
		return $template_id;
	}

	/**
	 * Whether the current request is a blog archive this feature covers.
	 *
	 * @return bool
	 */
	public static function is_blog_archive() {
		if ( ! function_exists( 'is_home' ) ) {
			return false;
		}

		return is_home() || is_category() || is_tag() || is_author() || is_date();
	}

	/**
	 * Find the active template whose assignment matches the current archive.
	 *
	 * @return int
	 */
	private function find_matching_template() {
		if ( null === $this->active_templates ) {
			$this->active_templates = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
					'meta_key'       => '_dsf_bt_active', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
		}
		$templates = $this->active_templates;

		if ( empty( $templates ) ) {
			return 0;
		}

		$current_cat = 0;
		if ( is_category() ) {
			$term        = get_queried_object();
			$current_cat = ( $term && isset( $term->term_id ) ) ? (int) $term->term_id : 0;
		}

		$category_match = 0;
		$all_match      = 0;

		foreach ( $templates as $template ) {
			$assignment = self::get_assignment( $template->ID );

			if ( 'categories' === $assignment['mode'] ) {
				if ( ! $category_match && $current_cat && in_array( $current_cat, $assignment['categoryIds'], true ) ) {
					$category_match = (int) $template->ID;
				}
			} elseif ( ! $all_match ) {
				$all_match = (int) $template->ID;
			}
		}

		return $category_match ? $category_match : $all_match;
	}

	/**
	 * Read and normalize a template's assignment rule.
	 *
	 * @param int $template_id Template post ID.
	 * @return array{mode:string,categoryIds:int[]}
	 */
	public static function get_assignment( $template_id ) {
		$raw  = get_post_meta( $template_id, '_dsf_bt_assignment', true );
		$mode = 'all';
		$cats = array();

		if ( is_array( $raw ) ) {
			$mode = ( isset( $raw['mode'] ) && 'categories' === $raw['mode'] ) ? 'categories' : 'all';
			if ( isset( $raw['categoryIds'] ) && is_array( $raw['categoryIds'] ) ) {
				$cats = array_values( array_unique( array_filter( array_map( 'absint', $raw['categoryIds'] ) ) ) );
			}
		}

		return array(
			'mode'        => $mode,
			'categoryIds' => $cats,
		);
	}

	/**
	 * Sanitize an assignment rule submitted from the editor.
	 *
	 * @param mixed $raw Submitted assignment.
	 * @return array{mode:string,categoryIds:int[]}
	 */
	public static function sanitize_assignment( $raw ) {
		$mode = 'all';
		$cats = array();

		if ( is_array( $raw ) ) {
			$mode = ( isset( $raw['mode'] ) && 'categories' === $raw['mode'] ) ? 'categories' : 'all';
			if ( isset( $raw['categoryIds'] ) && is_array( $raw['categoryIds'] ) ) {
				$cats = array_slice(
					array_values( array_unique( array_filter( array_map( 'absint', $raw['categoryIds'] ) ) ) ),
					0,
					100
				);
			}
		}

		if ( 'categories' === $mode && empty( $cats ) ) {
			// A category rule with no categories can never match — treat as "all".
			$mode = 'all';
		}

		return array(
			'mode'        => $mode,
			'categoryIds' => $cats,
		);
	}

	/**
	 * Build the live archive payload the blog blocks render from, using the main
	 * query WordPress has already run for this archive request.
	 *
	 * @return array
	 */
	public static function build_archive_context_from_main_query() {
		global $wp_query;

		if ( ! $wp_query instanceof WP_Query ) {
			return array();
		}

		$title       = '';
		$description = '';
		if ( is_home() ) {
			$posts_page  = (int) get_option( 'page_for_posts' );
			$title       = $posts_page ? get_the_title( $posts_page ) : __( 'Blog', 'designstudio-flow' );
			$title       = $title ? $title : __( 'Blog', 'designstudio-flow' );
			$description = '';
		} else {
			$title       = get_the_archive_title();
			$description = get_the_archive_description();
		}

		$per_page     = max( 1, (int) $wp_query->get( 'posts_per_page' ) );
		$current_page = max( 1, (int) get_query_var( 'paged' ) );
		$total_pages  = max( 1, (int) $wp_query->max_num_pages );

		return array(
			'title'           => sanitize_text_field( wp_strip_all_tags( (string) $title ) ),
			'descriptionHtml' => wp_kses_post( (string) $description ),
			'posts'           => self::build_post_cards( $wp_query->posts ),
			'total'           => (int) $wp_query->found_posts,
			'perPage'         => $per_page,
			'currentPage'     => $current_page,
			'totalPages'      => $total_pages,
			'pagination'      => self::build_pagination( $current_page, $total_pages ),
		);
	}

	/**
	 * Build a sample archive payload for the editor preview (the editor is not an
	 * archive request, so this queries a representative post set directly).
	 *
	 * @param int $term_id Preview category term ID (0 = latest posts).
	 * @return array|null
	 */
	public static function build_preview_context( $term_id = 0 ) {
		if ( ! class_exists( 'WP_Query' ) ) {
			return null;
		}

		$term_id = absint( $term_id );
		$title   = __( 'Blog', 'designstudio-flow' );
		$args    = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 9,
			'no_found_rows'  => true,
		);

		$description = '';
		if ( $term_id ) {
			$term = get_term( $term_id, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$title            = $term->name;
				$description      = term_description( $term );
				$args['category__in'] = array( $term_id );
			}
		}

		$query = new WP_Query( $args );
		$cards = self::build_post_cards( $query->posts );
		wp_reset_postdata();

		return array(
			'title'           => sanitize_text_field( (string) $title ),
			'descriptionHtml' => wp_kses_post( (string) $description ),
			'posts'           => $cards,
			'total'           => count( $cards ),
			'perPage'         => 9,
			'currentPage'     => 1,
			'totalPages'      => 1,
			'pagination'      => array(),
		);
	}

	/**
	 * Build sanitized post cards (title, excerpt, image, author, categories,
	 * date, reading time) from a list of posts.
	 *
	 * @param array $posts WP_Post objects (or IDs).
	 * @return array[]
	 */
	public static function build_post_cards( $posts ) {
		$cards = array();

		foreach ( (array) $posts as $post ) {
			$post = get_post( $post );
			if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
				continue;
			}

			$image_id  = get_post_thumbnail_id( $post );
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

			$author_id = (int) $post->post_author;

			$categories = array();
			foreach ( array_slice( (array) get_the_category( $post->ID ), 0, 3 ) as $term ) {
				$link         = get_category_link( $term );
				$categories[] = array(
					'name' => sanitize_text_field( (string) $term->name ),
					'url'  => is_wp_error( $link ) ? '' : esc_url_raw( (string) $link ),
				);
			}

			$word_count = str_word_count( wp_strip_all_tags( (string) $post->post_content ) );

			$cards[] = array(
				'id'          => (int) $post->ID,
				'title'       => sanitize_text_field( get_the_title( $post ) ),
				'url'         => esc_url_raw( (string) get_permalink( $post ) ),
				'excerpt'     => sanitize_text_field( wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 32 ) ),
				'date'        => sanitize_text_field( (string) get_the_date( '', $post ) ),
				'dateIso'     => sanitize_text_field( (string) get_the_date( 'c', $post ) ),
				'author'      => array(
					'name'      => sanitize_text_field( (string) get_the_author_meta( 'display_name', $author_id ) ),
					'url'       => esc_url_raw( (string) get_author_posts_url( $author_id ) ),
					'avatarUrl' => esc_url_raw( (string) get_avatar_url( $author_id, array( 'size' => 48 ) ) ),
				),
				'categories'  => $categories,
				'image'       => $image_url ? esc_url_raw( $image_url ) : '',
				'imageAlt'    => $image_id ? sanitize_text_field( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) : '',
				'readingTime' => max( 1, (int) ceil( $word_count / 200 ) ),
			);
		}

		return $cards;
	}

	/**
	 * Build sanitized pagination links for the current archive (capped).
	 *
	 * @param int $current_page Current page number.
	 * @param int $total_pages  Total pages.
	 * @return array[] {label,url,current} entries.
	 */
	private static function build_pagination( $current_page, $total_pages ) {
		$total_pages = min( 50, max( 1, (int) $total_pages ) );
		if ( $total_pages < 2 ) {
			return array();
		}

		$links = array();
		for ( $page = 1; $page <= $total_pages; $page++ ) {
			$links[] = array(
				'label'   => (string) $page,
				'url'     => esc_url_raw( get_pagenum_link( $page ) ),
				'current' => $page === (int) $current_page,
			);
		}

		return $links;
	}

	/**
	 * Add "Edit with DesignStudio Flow" to the blog-template row actions.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Current post.
	 * @return array
	 */
	public function add_edit_link( $actions, $post ) {
		if ( ! $post || self::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_pages', $post->ID ) ) {
			return $actions;
		}

		$url                 = admin_url( 'admin.php?page=dsf-editor&post_id=' . intval( $post->ID ) );
		$actions['dsf_edit'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit with DesignStudio Flow', 'designstudio-flow' ) . '</a>';
		return $actions;
	}

	/**
	 * Add Status + Applies-to columns to the blog-template list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_columns( $columns ) {
		$reordered = array();
		foreach ( $columns as $key => $label ) {
			$reordered[ $key ] = $label;
			if ( 'title' === $key ) {
				$reordered['dsf_bt_status']  = __( 'Status', 'designstudio-flow' );
				$reordered['dsf_bt_applies'] = __( 'Applies To', 'designstudio-flow' );
			}
		}
		return $reordered;
	}

	/**
	 * Render the Status / Applies-to column values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'dsf_bt_status' === $column ) {
			$active = '1' === (string) get_post_meta( $post_id, '_dsf_bt_active', true );
			echo $active
				? '<span class="dsf-pt-active">' . esc_html__( 'Live', 'designstudio-flow' ) . '</span>'
				: '<span aria-hidden="true">' . esc_html__( 'Inactive', 'designstudio-flow' ) . '</span>';
			return;
		}

		if ( 'dsf_bt_applies' === $column ) {
			$assignment = self::get_assignment( $post_id );
			if ( 'categories' === $assignment['mode'] ) {
				$names = array();
				foreach ( $assignment['categoryIds'] as $cat_id ) {
					$term = get_term( $cat_id, 'category' );
					if ( $term && ! is_wp_error( $term ) ) {
						$names[] = $term->name;
					}
				}
				echo $names
					? esc_html( implode( ', ', $names ) )
					: esc_html__( 'Selected categories', 'designstudio-flow' );
			} else {
				echo esc_html__( 'All blog archives', 'designstudio-flow' );
			}
		}
	}
}
