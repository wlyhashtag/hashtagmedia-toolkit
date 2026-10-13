<?php
/**
 * Profiel Complianz.
 *
 * De menulink "Upgrade to premium" gaat naar complianz.io en valt al onder
 * "Reclamelinks".
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'     => 'Complianz',
	'detect'   => array(
		'constants' => array( 'cmplz_free', 'cmplz_premium', 'CMPLZ_VERSION' ),
		'classes'   => array( 'COMPLIANZ' ),
	),
	'menu'     => array(),
	'adminbar' => array(),
	'columns'  => array(),
	'css'      => array(
		'.cmplz-upgrade-to-premium',
		'.cmplz-grid-item-footer-upsell-bar',
		'[class*="cmplz"][class*="upsell"]',
		'.cmplz-wrapper a[href*="complianz.io/pricing"]',
		'.cmplz-task-element:has(.cmplz-premium)',
	),
	'hooks'    => null,
);
