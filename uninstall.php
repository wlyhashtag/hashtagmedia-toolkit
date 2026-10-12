<?php
/**
 * Plugin verwijderen: opruimroutine van elke module, daarna de optie wissen.
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

defined( 'HM_TOOLKIT_DIR' ) || define( 'HM_TOOLKIT_DIR', plugin_dir_path( __FILE__ ) );

require_once HM_TOOLKIT_DIR . 'includes/class-settings.php';
require_once HM_TOOLKIT_DIR . 'includes/class-module.php';
require_once HM_TOOLKIT_DIR . 'includes/class-registry.php';

foreach ( HashtagMedia\Toolkit\Registry::folder_slugs() as $hm_toolkit_slug ) {
	HashtagMedia\Toolkit\Registry::run_uninstall_file( $hm_toolkit_slug );
}

HashtagMedia\Toolkit\Settings::delete();
