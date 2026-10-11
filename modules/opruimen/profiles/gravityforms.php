<?php
/**
 * Profiel Gravity Forms.
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'     => 'Gravity Forms',
	'detect'   => array(
		'classes' => array( 'GFForms' ),
	),
	'menu'     => array(
		'gf_edit_forms' => array(
			'gf_addons', // Add-ons (winkel); add-ons installeren doet HashtagMedia.
		),
	),
	'adminbar' => array(),
	'columns'  => array(),
	'css'      => array(
		'.gform-upgrade-banner',
		'[class*="gform"][class*="upsell"]',
	),
	'hooks'    => null,
);
