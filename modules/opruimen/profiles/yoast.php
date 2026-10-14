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
			'wpseo_upgrade_sidebar', // Upgrade-knop onderaan het menu.
		),
		'tools.php'       => array(
			'wpseo_redirects_tools', // Yoast Redirects (Premium-upsell).
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
		'#webinar-promo-notification',
		// Premium-knoppen in de metabox; zonder id-houder blijft er een lege rand over.
		'.yst-root:has(> #yoast-additional-keyphrase-metabox-modal-open-button)',
		'.yst-root:has(> #yoast-internal-linking-suggestions-metabox-modal-open-button)',
	),
	'hooks'    => static function () {
		// Zonder wpseo_manage_options (de klant) hangt het Yoast-menu aan
		// Academy en bevat het alleen upsells. SEO per pagina blijft.
		add_action(
			'admin_menu',
			static function () {
				remove_menu_page( 'wpseo_page_academy' );
			},
			PHP_INT_MAX
		);

		// Metabox onderaan de bewerkpagina.
		add_filter(
			'wpseo_metabox_prio',
			static function () {
				return 'low';
			}
		);
	},
);
