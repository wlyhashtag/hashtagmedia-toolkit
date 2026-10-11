<?php
/**
 * Opstarten van de plugin.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit;

defined( 'ABSPATH' ) || exit;

/**
 * Start de kern en laadt actieve modules.
 */
final class Plugin {

	/**
	 * Hooks van de kern registreren.
	 */
	public static function start(): void {
		add_action( 'plugins_loaded', array( Registry::class, 'load_enabled' ), 5 );
		add_action( 'init', array( self::class, 'load_textdomain' ), 0 );
		add_action( 'init', array( self::class, 'maybe_upgrade' ), 1 );

		if ( is_admin() ) {
			require_once HM_TOOLKIT_DIR . 'includes/class-admin-page.php';
			Admin_Page::start();
		}
	}

	/**
	 * Vertalingen laden.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'hashtagmedia-toolkit', false, dirname( plugin_basename( HM_TOOLKIT_FILE ) ) . '/languages' );
	}

	/**
	 * Bij activeren: modules synchroniseren en versie vastleggen.
	 */
	public static function activate(): void {
		Registry::sync();
		Settings::set_version( HM_TOOLKIT_VERSION );
	}

	/**
	 * Na een update van de plugin: nieuwe modules krijgen hun standaardstand.
	 * Ze laden vanaf het volgende verzoek.
	 */
	public static function maybe_upgrade(): void {
		if ( Settings::version() !== HM_TOOLKIT_VERSION ) {
			self::activate();
		}
	}
}
