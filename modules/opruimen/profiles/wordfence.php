<?php
/**
 * Profiel Wordfence (free).
 *
 * Upgrade-links in het menu linken naar wordfence.com en vallen al onder
 * "Reclamelinks". Hier alleen wat daarnaast nodig is.
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'     => 'Wordfence',
	'detect'   => array(
		'constants' => array( 'WORDFENCE_VERSION' ),
		'classes'   => array( 'wordfence' ),
	),
	'menu'     => array(
		'Wordfence' => array(
			'WordfenceUpgradeToPremium', // Upgrade To Premium.
			'WordfenceProtectMoreSites', // Protect More Sites.
		),
	),
	'adminbar' => array(),
	'columns'  => array(),
	'css'      => array(
		'.wf-premium-callout',
		'.wf-upgrade-callout',
		'[class*="wf-"][class*="upsell"]',
		'#wf-onboarding-plugin-header',
	),
	'hooks'    => null,
);
