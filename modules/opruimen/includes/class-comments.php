<?php
/**
 * Reacties volledig uit: frontend, admin, REST en feeds.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Opruimen;

defined( 'ABSPATH' ) || exit;

/**
 * Zet reacties en pingbacks overal uit.
 */
final class Comments {

	/**
	 * Hooks voor frontend en admin.
	 */
	public static function boot(): void {
		add_filter( 'comments_open', '__return_false', 20 );
		add_filter( 'pings_open', '__return_false', 20 );
		add_filter( 'comments_array', '__return_empty_array', 20 );
		add_filter( 'get_comments_number', '__return_zero', 20 );
		add_filter( 'feed_links_show_comments_feed', '__return_false' );
		add_filter( 'rest_endpoints', array( self::class, 'remove_rest_endpoints' ) );
		add_filter( 'wp_headers', array( self::class, 'remove_pingback_header' ) );
		add_action( 'init', array( self::class, 'remove_post_type_support' ), 100 );
		add_action( 'widgets_init', array( self::class, 'unregister_widget' ) );
		add_action( 'template_redirect', array( self::class, 'block_comment_feeds' ) );
		add_action( 'admin_bar_menu', array( self::class, 'remove_admin_bar_node' ), PHP_INT_MAX );
	}

	/**
	 * Hooks alleen voor de admin.
	 */
	public static function admin(): void {
		add_action( 'admin_menu', array( self::class, 'remove_menus' ), PHP_INT_MAX );
		add_action( 'admin_init', array( self::class, 'redirect_admin_pages' ) );
	}

	/**
	 * Reactie-ondersteuning van alle berichttypes weghalen.
	 */
	public static function remove_post_type_support(): void {
		foreach ( get_post_types() as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
			}
			if ( post_type_supports( $post_type, 'trackbacks' ) ) {
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	/**
	 * REST-routes voor reacties weghalen.
	 *
	 * @param array $endpoints Routes.
	 * @return array
	 */
	public static function remove_rest_endpoints( $endpoints ) {
		if ( ! is_array( $endpoints ) ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( 0 === strpos( $route, '/wp/v2/comments' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/**
	 * X-Pingback-header weghalen.
	 *
	 * @param array $headers Headers.
	 * @return array
	 */
	public static function remove_pingback_header( $headers ) {
		if ( is_array( $headers ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	/**
	 * Widget "Recente reacties" uitschakelen.
	 */
	public static function unregister_widget(): void {
		unregister_widget( 'WP_Widget_Recent_Comments' );
	}

	/**
	 * Reactie-feeds doorsturen naar de pagina zelf.
	 */
	public static function block_comment_feeds(): void {
		if ( is_comment_feed() ) {
			$url = is_singular() ? get_permalink() : home_url( '/' );
			wp_safe_redirect( $url ? $url : home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * Reacties uit de adminbalk.
	 *
	 * @param \WP_Admin_Bar $admin_bar Adminbalk.
	 */
	public static function remove_admin_bar_node( \WP_Admin_Bar $admin_bar ): void {
		$admin_bar->remove_node( 'comments' );
	}

	/**
	 * Menu's Reacties en Instellingen > Discussie weghalen.
	 */
	public static function remove_menus(): void {
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}

	/**
	 * Wie de reactiepagina's direct opent, gaat naar het dashboard.
	 */
	public static function redirect_admin_pages(): void {
		global $pagenow;

		if ( in_array( $pagenow, array( 'edit-comments.php', 'comment.php', 'options-discussion.php' ), true ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}
}
