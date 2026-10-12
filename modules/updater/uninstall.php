<?php
/**
 * Opruimen van de module Updates: de opgeslagen releasegegevens (cache).
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;

delete_site_transient( 'hm_toolkit_release' );
