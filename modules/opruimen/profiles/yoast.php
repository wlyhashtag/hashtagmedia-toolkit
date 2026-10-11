<?php
/**
 * Profiel Yoast SEO: rustiger voor klanten.
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'     => 'Yoast SEO',
	'detect'   => array(
		'constants' => array( 'WPSEO_VERSION' ),
	),
	'menu'     => array(
		'wpseo_dashboard' => array(
			'wpseo_licenses',       // Upgrades / Premium.
			'wpseo_workouts',       // Workouts (Premium).
			'wpseo_redirects',      // Redirects (Premium-upsell).
			'wpseo_page_academy',   // Academy.
			'wpseo_page_support',   // Support.
			'wpseo_brand_insights', // AI Brand Insights (upsell).
			'wpseo_brand_insights_premium',
		),
	),
	'adminbar' => array( 'wpseo-menu' ),
	'columns'  => array(
		'wpseo-score',
		'wpseo-score-readability',
		'wpseo-title',
		'wpseo-metadesc',
		'wpseo-focuskw',
		'wpseo-links',
		'wpseo-linked',
	),
	'css'      => array(
		'#wpseo-filter',
		'#wpseo-readability-filter',
		'.yoast_premium_upsell',
		'.wpseo-metabox-buy-premium',
		'#wpseo-local-seo-upsell',
		'#sidebar-container.wpseo_content_cell',
		'[class*="yoast"][class*="upsell"]',
		'[class*="yst"][class*="upsell"]',
	),
	'hooks'    => static function () {
		// Metabox onderaan de bewerkpagina.
		add_filter(
			'wpseo_metabox_prio',
			static function () {
				return 'low';
			}
		);
	},
);
