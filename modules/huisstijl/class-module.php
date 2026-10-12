<?php
/**
 * Module Huisstijl en dashboard.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Huisstijl;

use HashtagMedia\Toolkit\Module as Base;

defined( 'ABSPATH' ) || exit;

/**
 * Login, kleurenschema, footer, omgevingslabel en het klantdashboard.
 */
final class Module extends Base {

	/**
	 * Naam van het kleurenschema.
	 */
	public const SCHEME = 'hashtagmedia';

	/**
	 * Standaardwaarden.
	 *
	 * @return array
	 */
	public function defaults(): array {
		return array(
			'login'         => true,
			'scheme'        => true,
			'footer'        => true,
			'environment'   => true,
			'dashboard'     => true,
			'contact_email' => '',
			'contact_hours' => '',
			'support_email' => '',
			'manual_url'    => '',
		);
	}

	/**
	 * Per site anders: gaan niet mee bij exporteren en importeren.
	 *
	 * @return string[]
	 */
	public function site_settings(): array {
		return array(
			'contact_email',
			'contact_hours',
			'manual_url',
		);
	}

	/**
	 * Bedrijfsnaam van de klant. De module Bedrijfsgegevens kan deze via het
	 * filter hm_toolkit_company_name aanvullen; anders de sitenaam.
	 *
	 * @return string
	 */
	public static function company_name(): string {
		/**
		 * Bedrijfsnaam van de klant.
		 *
		 * @param string $name Standaard de sitenaam.
		 */
		return (string) apply_filters( 'hm_toolkit_company_name', get_bloginfo( 'name' ) );
	}

	/**
	 * Frontend en admin.
	 */
	public function boot(): void {
		if ( $this->get( 'login' ) ) {
			add_action( 'login_enqueue_scripts', array( $this, 'login_styles' ) );
			add_filter( 'login_headerurl', array( $this, 'login_url' ) );
			add_filter( 'login_headertext', array( $this, 'login_text' ) );
		}

		if ( $this->get( 'environment' ) ) {
			add_action( 'admin_bar_menu', array( $this, 'environment_node' ), 100 );
			add_action( 'wp_enqueue_scripts', array( $this, 'environment_styles' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'environment_styles' ) );
		}
	}

	/**
	 * Alleen admin.
	 */
	public function admin(): void {
		if ( $this->get( 'scheme' ) ) {
			add_action( 'admin_init', array( $this, 'register_scheme' ) );
			add_filter( 'get_user_option_admin_color', array( $this, 'force_scheme' ) );
			add_action( 'admin_init', array( $this, 'remove_scheme_picker' ) );
		}

		if ( $this->get( 'footer' ) ) {
			add_filter( 'admin_footer_text', array( $this, 'footer_text' ), PHP_INT_MAX );
		}

		if ( $this->get( 'dashboard' ) ) {
			require_once $this->path( 'admin/class-dashboard.php' );
			( new Dashboard( $this ) )->register();
		}
	}

	/**
	 * Loginpagina: stylesheet met logo en kleuren.
	 */
	public function login_styles(): void {
		$this->enqueue_style( 'login', 'assets/login.css', array( 'login' ) );
		wp_add_inline_style(
			$this->handle( 'login' ),
			sprintf( '.login h1 a { background-image: url("%s"); }', esc_url( $this->url( 'assets/logo.svg' ) ) )
		);
	}

	/**
	 * Logo op de loginpagina linkt naar de eigen site.
	 *
	 * @return string
	 */
	public function login_url(): string {
		return home_url( '/' );
	}

	/**
	 * Tekst achter het logo (voor schermlezers).
	 *
	 * @return string
	 */
	public function login_text(): string {
		return get_bloginfo( 'name' );
	}

	/**
	 * HashtagMedia-kleurenschema registreren.
	 */
	public function register_scheme(): void {
		wp_admin_css_color(
			self::SCHEME,
			__( 'HashtagMedia', 'hashtagmedia-toolkit' ),
			$this->url( 'assets/scheme/colors.min.css' ),
			array( '#00151f', '#0077a3', '#00adef', '#e7f1f6' ),
			array(
				'base'    => '#e7f1f6',
				'focus'   => '#ffffff',
				'current' => '#ffffff',
			)
		);
	}

	/**
	 * Kleurkeuze uit het profiel halen. WordPress koppelt deze pas na
	 * plugins_loaded, dus pas hier verwijderen.
	 */
	public function remove_scheme_picker(): void {
		remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
	}

	/**
	 * Iedereen gebruikt het HashtagMedia-schema.
	 *
	 * @return string
	 */
	public function force_scheme(): string {
		return self::SCHEME;
	}

	/**
	 * Footertekst links onderin de admin.
	 *
	 * @return string
	 */
	public function footer_text(): string {
		$text = sprintf(
			/* translators: %s: link naar HashtagMedia */
			esc_html__( 'Website door %s', 'hashtagmedia-toolkit' ),
			'<a href="https://hashtagmedia.nl" target="_blank" rel="noopener">HashtagMedia</a>'
		);

		if ( $this->get( 'dashboard' ) && is_email( (string) $this->get( 'support_email' ) ) ) {
			$text .= ' &middot; ' . sprintf(
				/* translators: %s: link naar het supportformulier */
				esc_html__( 'Hulp nodig? %s', 'hashtagmedia-toolkit' ),
				'<a href="' . esc_url( admin_url( 'index.php#hm_toolkit_welcome' ) ) . '">' . esc_html__( 'Support', 'hashtagmedia-toolkit' ) . '</a>'
			);
		}

		return $text;
	}

	/**
	 * Tweedelige extensies waarbij het domein uit drie delen bestaat.
	 */
	private const SECOND_LEVEL = array( 'co.uk', 'org.uk', 'me.uk', 'com.au', 'net.au', 'co.nz', 'co.za', 'com.br', 'com.tr', 'co.jp' );

	/**
	 * Omgevingstype bepalen aan de hand van een hostnaam.
	 *
	 * - localhost, IP-adres, .local, .test: local
	 * - subdomein (alles behalve www): staging
	 * - hoofddomein of www: production
	 *
	 * @param string $host Hostnaam, bijv. staging.klant.nl.
	 * @return string local, staging of production.
	 */
	public static function environment_for_host( string $host ): string {
		$host = strtolower( trim( $host, ". \t\n\r\0\x0B" ) );

		if ( '' === $host || 'localhost' === $host || false !== filter_var( $host, FILTER_VALIDATE_IP ) || preg_match( '/\.(local|test|localhost)$/', $host ) ) {
			return 'local';
		}

		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		$labels = explode( '.', $host );
		$count  = count( $labels );
		$base   = ( $count >= 3 && in_array( implode( '.', array_slice( $labels, -2 ) ), self::SECOND_LEVEL, true ) ) ? 3 : 2;

		return $count > $base ? 'staging' : 'production';
	}

	/**
	 * Omgeving van deze site.
	 *
	 * WP_ENVIRONMENT_TYPE in wp-config.php (of als serverinstelling) gaat
	 * altijd voor. Staat die er niet, dan bepaalt het domein het: subdomein
	 * is Staging, hoofddomein is Live. Dit gebeurt bij elk verzoek opnieuw,
	 * dus na verhuizen met Duplicator klopt het label meteen.
	 *
	 * @return array{type:string,label:string,live:bool,source:string}
	 */
	public static function environment(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$explicit = defined( 'WP_ENVIRONMENT_TYPE' ) || false !== getenv( 'WP_ENVIRONMENT_TYPE' );
		if ( $explicit ) {
			$type   = wp_get_environment_type();
			$source = 'wp-config';
		} else {
			$type   = self::environment_for_host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			$source = 'domein';
		}

		/**
		 * Omgevingstype van deze site (local, development, staging, production).
		 *
		 * @param string $type   Type.
		 * @param string $source wp-config of domein.
		 */
		$type = (string) apply_filters( 'hm_toolkit_environment_type', $type, $source );

		$labels = array(
			'local'       => __( 'Lokaal', 'hashtagmedia-toolkit' ),
			'development' => __( 'Ontwikkeling', 'hashtagmedia-toolkit' ),
			'staging'     => __( 'Staging', 'hashtagmedia-toolkit' ),
			'production'  => __( 'Live', 'hashtagmedia-toolkit' ),
		);
		if ( ! isset( $labels[ $type ] ) ) {
			$type = 'production';
		}

		$cache = array(
			'type'   => $type,
			'label'  => $labels[ $type ],
			'live'   => 'production' === $type,
			'source' => $source,
		);
		return $cache;
	}

	/**
	 * Omgevingslabel in de adminbalk.
	 *
	 * @param \WP_Admin_Bar $admin_bar Adminbalk.
	 */
	public function environment_node( \WP_Admin_Bar $admin_bar ): void {
		$environment = self::environment();

		$admin_bar->add_node(
			array(
				'id'     => 'hm-toolkit-environment',
				'parent' => 'top-secondary',
				'title'  => esc_html( $environment['label'] ),
				'meta'   => array(
					'class' => $environment['live'] ? 'hm-env-live' : 'hm-env-test',
					'title' => $environment['live']
						? esc_attr__( 'Je werkt op de live website.', 'hashtagmedia-toolkit' )
						: esc_attr__( 'Dit is een testomgeving; wijzigingen staan niet op de live website.', 'hashtagmedia-toolkit' ),
				),
			)
		);
	}

	/**
	 * Kleuren van het omgevingslabel (klein, inline bij de adminbalk).
	 */
	public function environment_styles(): void {
		if ( ! is_admin_bar_showing() ) {
			return;
		}
		$css = '#wpadminbar #wp-admin-bar-hm-toolkit-environment > .ab-item{font-weight:600;padding:0 10px;}'
			. '#wpadminbar #wp-admin-bar-hm-toolkit-environment.hm-env-live > .ab-item{background:#00a32a;color:#fff;}'
			. '#wpadminbar #wp-admin-bar-hm-toolkit-environment.hm-env-test > .ab-item{background:#dba617;color:#1d2327;}';
		wp_add_inline_style( 'admin-bar', $css );
	}

	/**
	 * Heeft een tabblad.
	 *
	 * @return bool
	 */
	public function has_tab(): bool {
		return true;
	}

	/**
	 * Tabblad Huisstijl en dashboard.
	 */
	public function render_tab(): void {
		?>
		<h2><?php esc_html_e( 'Contact en support', 'hashtagmedia-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Deze gegevens ziet de klant in het welkomstblok op het dashboard.', 'hashtagmedia-toolkit' ); ?></p>
		<table class="form-table" role="presentation">
			<?php
			$this->input_row( 'contact_email', __( 'E-mailadres', 'hashtagmedia-toolkit' ), 'email', __( 'Contactadres van HashtagMedia voor deze klant.', 'hashtagmedia-toolkit' ) );
			$this->textarea_row( 'contact_hours', __( 'Bereikbaarheid', 'hashtagmedia-toolkit' ), __( 'Bijvoorbeeld de dagen en tijden waarop jullie bereikbaar zijn.', 'hashtagmedia-toolkit' ) );
			$this->input_row( 'support_email', __( 'Supportmeldingen naar', 'hashtagmedia-toolkit' ), 'email', __( 'Hierheen gaat het supportformulier. Leeg = geen formulier op het dashboard.', 'hashtagmedia-toolkit' ) );
			$this->input_row( 'manual_url', __( 'Handleiding', 'hashtagmedia-toolkit' ), 'url', __( 'Link naar een PDF of video, bijvoorbeeld uit de mediabibliotheek. Leeg = blok verborgen.', 'hashtagmedia-toolkit' ), 'https://' );
			?>
		</table>

		<h2><?php esc_html_e( 'Onderdelen', 'hashtagmedia-toolkit' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$this->checkbox_row( 'dashboard', __( 'Dashboard', 'hashtagmedia-toolkit' ), __( 'Vast klantdashboard met welkom, snelle acties, inzendingen en recent bewerkt', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'login', __( 'Loginpagina', 'hashtagmedia-toolkit' ), __( 'HashtagMedia-logo en -kleuren op de loginpagina', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'scheme', __( 'Kleurenschema', 'hashtagmedia-toolkit' ), __( 'HashtagMedia-kleurenschema voor alle gebruikers', 'hashtagmedia-toolkit' ), __( 'De keuze voor een kleurenschema in het profiel verdwijnt.', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'footer', __( 'Footertekst', 'hashtagmedia-toolkit' ), __( '"Website door HashtagMedia" met een link naar support', 'hashtagmedia-toolkit' ) );
			$environment = self::environment();
			$this->checkbox_row(
				'environment',
				__( 'Omgevingslabel', 'hashtagmedia-toolkit' ),
				__( 'Label Live of Staging in de adminbalk', 'hashtagmedia-toolkit' ),
				sprintf(
					/* translators: 1: huidige omgeving, 2: hoe die bepaald is */
					__( 'Automatisch: subdomein = Staging, hoofddomein = Live. WP_ENVIRONMENT_TYPE in wp-config.php gaat altijd voor. Nu: %1$s (%2$s).', 'hashtagmedia-toolkit' ),
					$environment['label'],
					'wp-config' === $environment['source'] ? __( 'uit wp-config.php', 'hashtagmedia-toolkit' ) : __( 'herkend aan het domein', 'hashtagmedia-toolkit' )
				)
			);
			?>
		</table>
		<?php
	}

	/**
	 * Import: dezelfde regels als het tabblad voor e-mail.
	 *
	 * @param array $values Waarden uit het bestand.
	 * @return array
	 */
	public function import_settings( array $values ): array {
		$clean = parent::import_settings( $values );
		if ( isset( $clean['support_email'] ) ) {
			$clean['support_email'] = sanitize_email( (string) $clean['support_email'] );
		}
		return $clean;
	}

	/**
	 * Saneert het tabblad.
	 *
	 * @param array $input Invoer.
	 * @return array
	 */
	public function sanitize( array $input ): array {
		$clean = $this->sanitize_checkboxes( $input, array( 'login', 'scheme', 'footer', 'environment', 'dashboard' ) );

		$clean['contact_email'] = sanitize_email( (string) ( $input['contact_email'] ?? '' ) );
		$clean['contact_hours'] = sanitize_textarea_field( (string) ( $input['contact_hours'] ?? '' ) );
		$clean['support_email'] = sanitize_email( (string) ( $input['support_email'] ?? '' ) );
		$clean['manual_url']    = esc_url_raw( (string) ( $input['manual_url'] ?? '' ), array( 'http', 'https' ) );

		return $clean;
	}
}
