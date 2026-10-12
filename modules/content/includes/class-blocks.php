<?php
/**
 * Gutenberg uit: klassieke editor, klassieke widgets en geen blok-CSS op de
 * frontend. Custom thema's bouwen alles met ACF; de blok-CSS is dan
 * onnodige ballast.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Blokeditor en blok-assets uitschakelen.
 */
final class Blocks {

	/**
	 * Stylesheets van blokken op de frontend.
	 */
	private const STYLES = array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'core-block-supports', 'core-block-supports-duotone' );

	/**
	 * Hooks registreren.
	 */
	public static function register(): void {
		add_filter( 'use_block_editor_for_post', '__return_false', 100 );
		add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
		add_filter( 'use_widgets_block_editor', '__return_false', 100 );
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		// Geen globale stijlen, SVG-filters en blok-ondersteuning op de frontend.
		add_action( 'init', array( self::class, 'remove_global_styles' ), 20 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'dequeue_styles' ), 100 );
		add_action( 'wp_footer', array( self::class, 'dequeue_styles' ), 1 );
	}

	/**
	 * Acties van WordPress voor globale stijlen weghalen.
	 */
	public static function remove_global_styles(): void {
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
		remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_stored_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_stored_styles', 1 );
	}

	/**
	 * Blok-stylesheets uit de wachtrij halen.
	 */
	public static function dequeue_styles(): void {
		foreach ( self::STYLES as $handle ) {
			wp_dequeue_style( $handle );
		}
	}
}
