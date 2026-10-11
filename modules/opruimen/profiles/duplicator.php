<?php
/**
 * Profiel Duplicator (Lite).
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'     => 'Duplicator',
	'detect'   => array(
		'constants' => array( 'DUPLICATOR_VERSION', 'DUPLICATOR_PRO_VERSION' ),
	),
	'menu'     => array(
		'duplicator' => array(
			'duplicator-schedules', // Alleen in Pro; in Lite een upsell.
			'duplicator-addons',
			'duplicator-about-us',
			'duplicator-gopro',
		),
	),
	'adminbar' => array(),
	'columns'  => array(),
	'css'      => array(
		'.dup-pro-upsell',
		'.duplicator-upgrade',
		'[class*="dup"][class*="upsell"]',
		'[class*="dup"][class*="go-pro"]',
	),
	'hooks'    => null,
);
