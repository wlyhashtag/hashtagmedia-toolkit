<?php
/**
 * Plugin Name:       HashtagMedia Toolkit
 * Description:       Beheer, opruiming en huisstijl voor websites van HashtagMedia.
 * Version:           1.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            HashtagMedia
 * Author URI:        https://hashtagmedia.nl
 * Update URI:        https://github.com/wlyhashtag/hashtagmedia-toolkit
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hashtagmedia-toolkit
 * Domain Path:       /languages
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit;

defined( 'ABSPATH' ) || exit;

/*
 * Publieke constanten. Thema's kunnen met defined( 'HM_TOOLKIT_VERSION' )
 * controleren of de plugin actief is.
 */
define( 'HM_TOOLKIT_VERSION', '1.2.0' );
define( 'HM_TOOLKIT_FILE', __FILE__ );
define( 'HM_TOOLKIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'HM_TOOLKIT_URL', plugin_dir_url( __FILE__ ) );

require_once HM_TOOLKIT_DIR . 'includes/class-settings.php';
require_once HM_TOOLKIT_DIR . 'includes/class-module.php';
require_once HM_TOOLKIT_DIR . 'includes/class-registry.php';
require_once HM_TOOLKIT_DIR . 'includes/class-plugin.php';
require_once HM_TOOLKIT_DIR . 'includes/functions.php';
require_once HM_TOOLKIT_DIR . 'includes/theme-helpers.php';

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );

Plugin::start();
