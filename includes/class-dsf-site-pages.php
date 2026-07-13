<?php
/**
 * Site-wide page features for DesignStudio Flow: login, search, user dashboard.
 *
 * These blocks turn ordinary DSF pages into a designable login page, search
 * page, and member dashboard. Unlike the Woo store fragments, their data is a
 * small sanitized payload built per request and localized for the Vue blocks
 * (the forms themselves post to core WordPress endpoints — wp-login.php and
 * the page's own URL — so authentication and search never leave core paths).
 * Pages using the personalized blocks are never page-cached.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Site_Pages {

	/**
	 * Decide which site features a page's blocks actually need.
	 *
	 * @param array $blocks Raw page blocks.
	 * @return array{login:bool,search:bool,dashboard:bool,any:bool}
	 */
	public static function blocks_need_context( $blocks ) {
		$needs = array(
			'login'     => false,
			'search'    => false,
			'dashboard' => false,
			'any'       => false,
		);

		foreach ( (array) $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';

			if ( 'site-login' === $type ) {
				$needs['login'] = true;
			} elseif ( 'site-search' === $type ) {
				$needs['search'] = true;
			} elseif ( 'user-dashboard' === $type ) {
				$needs['dashboard'] = true;
			}
		}

		$needs['any'] = $needs['login'] || $needs['search'] || $needs['dashboard'];
		return $needs;
	}

	/**
	 * Build the sanitized per-request payload the site blocks render from.
	 *
	 * @param array $needs   Needs from blocks_need_context().
	 * @param int   $page_id Current page ID (search form posts back to it).
	 * @return array
	 */
	public static function build_site_context( $needs, $page_id ) {
		$page_id   = absint( $page_id );
		$permalink = $page_id ? get_permalink( $page_id ) : home_url( '/' );
		$permalink = $permalink ? $permalink : home_url( '/' );

		$context = array(
			'isLoggedIn' => is_user_logged_in(),
			'pageId'     => $page_id,
			'pageUrl'    => esc_url_raw( $permalink ),
		);

		if ( ! empty( $needs['login'] ) || ! empty( $needs['dashboard'] ) ) {
			$context = array_merge( $context, self::build_auth_context( $permalink ) );
		}

		if ( ! empty( $needs['dashboard'] ) ) {
			$context['accountUrls']  = self::build_account_urls();
			$context['recentOrders'] = self::build_recent_orders();
		}

		if ( ! empty( $needs['search'] ) ) {
			$context['search'] = self::build_search_results( $permalink );
		}

		return $context;
	}

	/**
	 * Login/logout endpoints plus the current user's public display details.
	 *
	 * @param string $permalink Current page URL (used as the login redirect).
	 * @return array
	 */
	private static function build_auth_context( $permalink ) {
		$auth = array(
			'loginAction'     => esc_url_raw( site_url( 'wp-login.php', 'login_post' ) ),
			'loginUrl'        => esc_url_raw( wp_login_url( $permalink ) ),
			'redirectTo'      => esc_url_raw( $permalink ),
			'lostPasswordUrl' => esc_url_raw( wp_lostpassword_url( $permalink ) ),
			'registerUrl'     => get_option( 'users_can_register' ) ? esc_url_raw( wp_registration_url() ) : '',
			'logoutUrl'       => esc_url_raw( wp_logout_url( $permalink ) ),
			'user'            => null,
		);

		if ( is_user_logged_in() ) {
			$user         = wp_get_current_user();
			$auth['user'] = array(
				'displayName' => sanitize_text_field( $user->display_name ),
				'avatarUrl'   => esc_url_raw( get_avatar_url( $user->ID, array( 'size' => 96 ) ) ),
			);
		}

		return $auth;
	}

	/**
	 * The dashboard's quick-link destinations (WooCommerce account endpoints when
	 * available, core profile otherwise).
	 *
	 * @return array
	 */
	private static function build_account_urls() {
		$urls = array(
			'orders'      => '',
			'downloads'   => '',
			'addresses'   => '',
			'editAccount' => esc_url_raw( admin_url( 'profile.php' ) ),
			'account'     => '',
		);

		if ( ! function_exists( 'wc_get_account_endpoint_url' ) || ! function_exists( 'wc_get_page_id' ) ) {
			return $urls;
		}

		if ( wc_get_page_id( 'myaccount' ) > 0 ) {
			$urls['account']     = esc_url_raw( wc_get_account_endpoint_url( 'dashboard' ) );
			$urls['orders']      = esc_url_raw( wc_get_account_endpoint_url( 'orders' ) );
			$urls['downloads']   = esc_url_raw( wc_get_account_endpoint_url( 'downloads' ) );
			$urls['addresses']   = esc_url_raw( wc_get_account_endpoint_url( 'edit-address' ) );
			$urls['editAccount'] = esc_url_raw( wc_get_account_endpoint_url( 'edit-account' ) );
		}

		return $urls;
	}

	/**
	 * The logged-in visitor's most recent orders (their own only).
	 *
	 * @return array[]
	 */
	private static function build_recent_orders() {
		if ( ! is_user_logged_in() || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer' => get_current_user_id(),
				'limit'    => 3,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		$list = array();
		foreach ( (array) $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_order_number' ) ) {
				continue;
			}
			$date   = $order->get_date_created();
			$list[] = array(
				'number' => sanitize_text_field( (string) $order->get_order_number() ),
				'date'   => $date ? sanitize_text_field( wp_date( get_option( 'date_format' ), $date->getTimestamp() ) ) : '',
				'status' => sanitize_text_field( wc_get_order_status_name( $order->get_status() ) ),
				'total'  => wp_kses_post( (string) $order->get_formatted_order_total() ),
				'url'    => esc_url_raw( (string) $order->get_view_order_url() ),
			);
		}

		return $list;
	}

	/**
	 * Run the search for the current request (?s=… on the designed search page)
	 * and build a sanitized results payload.
	 *
	 * @param string $permalink Current page URL (form action / pagination base).
	 * @return array
	 */
	private static function build_search_results( $permalink ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only search query.
		$query = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$query = mb_substr( $query, 0, 100 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only pagination.
		$page = isset( $_GET['dsf_paged'] ) ? min( 50, max( 1, absint( $_GET['dsf_paged'] ) ) ) : 1;

		$search = array(
			'query'      => $query,
			'action'     => esc_url_raw( $permalink ),
			'results'    => array(),
			'total'      => 0,
			'totalPages' => 0,
			'pagination' => array(),
		);

		if ( '' === $query ) {
			return $search;
		}

		$post_types = array( 'page', 'post' );
		if ( class_exists( 'WooCommerce' ) ) {
			$post_types[] = 'product';
		}

		$wp_query = new WP_Query(
			array(
				's'              => $query,
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'paged'          => $page,
			)
		);

		foreach ( $wp_query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$type_labels         = array(
				'post'    => __( 'Article', 'designstudio-flow' ),
				'page'    => __( 'Page', 'designstudio-flow' ),
				'product' => __( 'Product', 'designstudio-flow' ),
			);
			$thumb               = get_the_post_thumbnail_url( $post, 'thumbnail' );
			$search['results'][] = array(
				'id'      => (int) $post->ID,
				'title'   => sanitize_text_field( get_the_title( $post ) ),
				'url'     => esc_url_raw( (string) get_permalink( $post ) ),
				'type'    => isset( $type_labels[ $post->post_type ] ) ? $type_labels[ $post->post_type ] : sanitize_text_field( $post->post_type ),
				'excerpt' => sanitize_text_field( wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 28 ) ),
				'image'   => $thumb ? esc_url_raw( $thumb ) : '',
			);
		}

		$search['total']      = (int) $wp_query->found_posts;
		$search['totalPages'] = min( 50, max( 1, (int) $wp_query->max_num_pages ) );

		if ( $search['totalPages'] > 1 ) {
			for ( $n = 1; $n <= $search['totalPages']; $n++ ) {
				$search['pagination'][] = array(
					'label'   => (string) $n,
					'url'     => esc_url_raw(
						add_query_arg(
							array(
								's'         => rawurlencode( $query ),
								'dsf_paged' => $n,
							),
							$permalink
						)
					),
					'current' => $n === $page,
				);
			}
		}

		wp_reset_postdata();

		return $search;
	}
}
