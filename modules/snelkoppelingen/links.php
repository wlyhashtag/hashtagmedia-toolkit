<?php
/**
 * Alle snelkoppelingen op één plek.
 *
 * Per plugin één groep:
 * - name:   naam op de knop-kaart;
 * - icon:   dashicon;
 * - detect: constants, classes en/of functions; één treffer = actief;
 * - links:  label + url (relatief aan wp-admin). Optioneel 'nonce': de actie
 *           voor wp_nonce_url (voor knoppen die iets doen). Mag ook een
 *           functie zijn die de links teruggeeft.
 *
 * Verandert een plugin zijn adressen na een update? Pas dan alleen dit
 * bestand aan. Adressen gecontroleerd op 12 oktober 2026 met: Complianz 7,
 * Yoast SEO 28, WP Rocket 3.23, FluentSMTP 2.
 *
 * Dit bestand wordt pas ingelezen als de knoppen getoond worden (na init),
 * dus vertalingen werken hier.
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;

return array(
	'thema'        => array(
		'name'   => __( 'Thema-instellingen', 'hashtagmedia-toolkit' ),
		'icon'   => 'dashicons-admin-appearance',
		'detect' => array( 'functions' => array( 'acf_get_options_pages' ) ),
		'links'  => static function () {
			$links = array();
			$pages = acf_get_options_pages();
			foreach ( is_array( $pages ) ? $pages : array() as $page ) {
				if ( empty( $page['menu_slug'] ) ) {
					continue;
				}
				$url     = menu_page_url( (string) $page['menu_slug'], false );
				$links[] = array(
					'label' => (string) ( $page['page_title'] ?? $page['menu_title'] ?? $page['menu_slug'] ),
					'url'   => '' !== $url ? $url : 'admin.php?page=' . rawurlencode( (string) $page['menu_slug'] ),
				);
			}
			$links[] = array(
				'label' => __( 'ACF-veldgroepen', 'hashtagmedia-toolkit' ),
				'url'   => 'edit.php?post_type=acf-field-group',
			);
			return $links;
		},
	),
	'complianz'    => array(
		'name'   => 'Complianz',
		'icon'   => 'dashicons-privacy',
		'detect' => array(
			'constants' => array( 'cmplz_free', 'cmplz_premium', 'CMPLZ_VERSION' ),
			'classes'   => array( 'COMPLIANZ' ),
		),
		'links'  => array(
			array(
				'label' => __( 'Statistieken en Tag Manager', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=complianz#wizard/statistics-configuration',
			),
			array(
				'label' => __( 'Integraties', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=complianz#integrations/integrations-services',
			),
			array(
				'label' => __( 'Cookiebanner', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=complianz#banner',
			),
		),
	),
	'yoast'        => array(
		'name'   => 'Yoast SEO',
		'icon'   => 'dashicons-search',
		'detect' => array( 'constants' => array( 'WPSEO_VERSION' ) ),
		'links'  => array(
			array(
				'label' => __( 'Webmastertools', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=wpseo_page_settings#/site-connections',
			),
			array(
				'label' => __( 'Organisatie en logo', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=wpseo_page_settings#/site-representation',
			),
			array(
				'label' => __( 'Instellingen', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=wpseo_page_settings',
			),
		),
	),
	'gravityforms' => array(
		'name'   => 'Gravity Forms',
		'icon'   => 'dashicons-feedback',
		'detect' => array( 'classes' => array( 'GFForms' ) ),
		'links'  => array(
			array(
				'label' => __( 'Formulieren', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=gf_edit_forms',
			),
			array(
				'label' => __( 'Inzendingen', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=gf_entries',
			),
			array(
				'label' => __( 'Instellingen en licentie', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=gf_settings',
			),
		),
	),
	'wp-rocket'    => array(
		'name'   => 'WP Rocket',
		'icon'   => 'dashicons-performance',
		'detect' => array( 'constants' => array( 'WP_ROCKET_VERSION' ) ),
		'links'  => array(
			array(
				'label' => __( 'Instellingen', 'hashtagmedia-toolkit' ),
				'url'   => 'options-general.php?page=wprocket',
			),
			array(
				'label' => __( 'Cache legen', 'hashtagmedia-toolkit' ),
				'url'   => 'admin-post.php?action=purge_cache&type=all',
				'nonce' => 'purge_cache_all',
			),
		),
	),
	'wordfence'    => array(
		'name'   => 'Wordfence',
		'icon'   => 'dashicons-shield',
		'detect' => array(
			'constants' => array( 'WORDFENCE_VERSION' ),
			'classes'   => array( 'wordfence' ),
		),
		'links'  => array(
			array(
				'label' => __( 'Firewall', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=WordfenceWAF',
			),
			array(
				'label' => __( 'Scan', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=WordfenceScan',
			),
			array(
				'label' => __( 'Inlogbeveiliging (2FA)', 'hashtagmedia-toolkit' ),
				'url'   => 'admin.php?page=WFLS',
			),
		),
	),
	'duplicator'   => array(
		'name'   => 'Duplicator',
		'icon'   => 'dashicons-backup',
		'detect' => array( 'constants' => array( 'DUPLICATOR_VERSION', 'DUPLICATOR_PRO_VERSION' ) ),
		'links'  => static function () {
			$page = defined( 'DUPLICATOR_PRO_VERSION' ) ? 'duplicator-pro' : 'duplicator';
			return array(
				array(
					'label' => __( 'Back-ups', 'hashtagmedia-toolkit' ),
					'url'   => 'admin.php?page=' . $page,
				),
			);
		},
	),
	'fluentsmtp'   => array(
		'name'   => 'FluentSMTP',
		'icon'   => 'dashicons-email-alt',
		'detect' => array( 'constants' => array( 'FLUENTMAIL_PLUGIN_FILE' ) ),
		'links'  => array(
			array(
				'label' => __( 'Verbindingen', 'hashtagmedia-toolkit' ),
				'url'   => 'options-general.php?page=fluent-mail#/connections',
			),
			array(
				'label' => __( 'E-maillog', 'hashtagmedia-toolkit' ),
				'url'   => 'options-general.php?page=fluent-mail#/logs',
			),
		),
	),
);
