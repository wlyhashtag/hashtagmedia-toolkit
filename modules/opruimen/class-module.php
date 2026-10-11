<?php
/**
 * Module Opruimen.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Opruimen;

use HashtagMedia\Toolkit\Module as Base;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-profiles.php';

/**
 * Ruimt de admin op: meldingen, reclame, dashboard, adminbalk, reacties.
 */
final class Module extends Base {

	/**
	 * Algemene schakelaars en hun standaardstand.
	 */
	private const GENERAL = array(
		'notices'   => true,
		'dashboard' => true,
		'adminbar'  => true,
		'comments'  => true,
		'ad_links'  => true,
		'footer'    => true,
	);

	/**
	 * Adminbalk-items die altijd weg mogen.
	 */
	private const ADMINBAR_NODES = array( 'wp-logo', 'customize', 'search' );

	/**
	 * Profielen.
	 *
	 * @var Profiles|null
	 */
	private ?Profiles $profiles = null;

	/**
	 * Profielen (lazy).
	 *
	 * @return Profiles
	 */
	private function profiles(): Profiles {
		if ( null === $this->profiles ) {
			$this->profiles = new Profiles( $this->path( 'profiles' ) );
		}
		return $this->profiles;
	}

	/**
	 * Actieve profielen: aangezet én plugin actief.
	 *
	 * @return array<string,array>
	 */
	private function active_profiles(): array {
		return $this->profiles()->active(
			function ( $slug ) {
				return (bool) $this->get( 'profile_' . $slug );
			}
		);
	}

	/**
	 * Standaardwaarden: alles aan, ook elk profiel.
	 *
	 * @return array
	 */
	public function defaults(): array {
		$defaults = self::GENERAL;
		foreach ( $this->profiles()->slugs() as $slug ) {
			$defaults[ 'profile_' . $slug ] = true;
		}
		return $defaults;
	}

	/**
	 * Frontend en admin.
	 */
	public function boot(): void {
		if ( $this->get( 'comments' ) ) {
			require_once $this->path( 'includes/class-comments.php' );
			Comments::boot();
		}

		if ( $this->get( 'adminbar' ) ) {
			add_action( 'admin_bar_menu', array( $this, 'clean_admin_bar' ), PHP_INT_MAX );
		}

		foreach ( $this->active_profiles() as $profile ) {
			if ( is_callable( $profile['hooks'] ) ) {
				call_user_func( $profile['hooks'] );
			}
		}
	}

	/**
	 * Alleen admin.
	 */
	public function admin(): void {
		if ( $this->get( 'comments' ) ) {
			Comments::admin();
		}

		if ( $this->get( 'notices' ) ) {
			require_once $this->path( 'admin/class-notices.php' );
			( new Notices(
				array(
					'css' => $this->url( 'assets/notices.css' ),
					'js'  => $this->url( 'assets/notices.js' ),
				)
			) )->register();
		}

		if ( $this->get( 'dashboard' ) ) {
			add_action( 'wp_dashboard_setup', array( $this, 'clean_dashboard' ), PHP_INT_MAX );
		}

		if ( $this->get( 'footer' ) ) {
			add_filter( 'admin_footer_text', '__return_empty_string', 9999 );
		}

		if ( $this->get( 'ad_links' ) ) {
			add_action( 'admin_menu', array( $this, 'remove_external_menu_links' ), PHP_INT_MAX );
			add_filter( 'plugin_action_links', array( $this, 'remove_external_action_links' ), 9999 );
		}

		add_action( 'admin_menu', array( $this, 'apply_profile_menus' ), PHP_INT_MAX );
		add_action( 'current_screen', array( $this, 'apply_profile_columns' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'apply_profile_css' ) );
	}

	/**
	 * Adminbalk opschonen: WordPress-logo, Customizer, zoeken, en de items
	 * uit de profielen.
	 *
	 * @param \WP_Admin_Bar $admin_bar Adminbalk.
	 */
	public function clean_admin_bar( \WP_Admin_Bar $admin_bar ): void {
		$nodes = array_merge( self::ADMINBAR_NODES, Profiles::merge( $this->active_profiles(), 'adminbar' ) );
		foreach ( $nodes as $node ) {
			$admin_bar->remove_node( $node );
		}
	}

	/**
	 * Alle dashboardwidgets weg, behalve die van HashtagMedia (id begint met
	 * hm_toolkit). Welkomstpaneel van WordPress ook weg.
	 */
	public function clean_dashboard(): void {
		global $wp_meta_boxes;

		remove_action( 'welcome_panel', 'wp_welcome_panel' );

		if ( empty( $wp_meta_boxes['dashboard'] ) || ! is_array( $wp_meta_boxes['dashboard'] ) ) {
			return;
		}

		foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
			foreach ( (array) $priorities as $boxes ) {
				foreach ( array_keys( (array) $boxes ) as $id ) {
					if ( 0 !== strpos( (string) $id, 'hm_toolkit' ) ) {
						remove_meta_box( (string) $id, 'dashboard', $context );
					}
				}
			}
		}
	}

	/**
	 * Is een URL extern (andere host dan de site)?
	 *
	 * @param string $url URL of menu-slug.
	 * @return bool
	 */
	private static function is_external( string $url ): bool {
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return false;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) && strtolower( $host ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/**
	 * Menu-items die naar een externe site linken (upgrade, upsell) weghalen.
	 */
	public function remove_external_menu_links(): void {
		global $menu, $submenu;

		foreach ( (array) $submenu as $parent => $items ) {
			foreach ( (array) $items as $item ) {
				if ( isset( $item[2] ) && self::is_external( (string) $item[2] ) ) {
					remove_submenu_page( (string) $parent, (string) $item[2] );
				}
			}
		}

		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && self::is_external( (string) $item[2] ) ) {
				remove_menu_page( (string) $item[2] );
			}
		}
	}

	/**
	 * Externe links (Upgrade, Go Pro, Docs) uit de pluginlijst halen.
	 * Activeren, Deactiveren, Instellingen e.d. zijn intern en blijven.
	 *
	 * @param array $actions Actielinks.
	 * @return array
	 */
	public function remove_external_action_links( $actions ) {
		if ( ! is_array( $actions ) ) {
			return $actions;
		}
		foreach ( $actions as $key => $html ) {
			if ( preg_match( '#href=(["\'])(https?://[^"\']+)\1#i', (string) $html, $match ) && self::is_external( html_entity_decode( $match[2] ) ) ) {
				unset( $actions[ $key ] );
			}
		}
		return $actions;
	}

	/**
	 * Menu-items uit de profielen weghalen.
	 */
	public function apply_profile_menus(): void {
		foreach ( Profiles::merge_menu( $this->active_profiles() ) as $parent => $children ) {
			foreach ( $children as $child ) {
				remove_submenu_page( (string) $parent, (string) $child );
			}
		}
	}

	/**
	 * Kolommen uit de profielen weghalen in elk lijstoverzicht.
	 *
	 * @param \WP_Screen $screen Huidig scherm.
	 */
	public function apply_profile_columns( $screen ): void {
		$columns = Profiles::merge( $this->active_profiles(), 'columns' );
		if ( empty( $columns ) || ! $screen instanceof \WP_Screen ) {
			return;
		}

		add_filter(
			'manage_' . $screen->id . '_columns',
			static function ( $existing ) use ( $columns ) {
				return is_array( $existing ) ? array_diff_key( $existing, array_flip( $columns ) ) : $existing;
			},
			9999
		);
	}

	/**
	 * Selectors uit de profielen verbergen.
	 */
	public function apply_profile_css(): void {
		$selectors = Profiles::merge( $this->active_profiles(), 'css' );
		if ( empty( $selectors ) ) {
			return;
		}

		$handle = 'hm-toolkit-opruimen-profiles';
		wp_register_style( $handle, false, array(), HM_TOOLKIT_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, implode( ",\n", array_map( 'wp_strip_all_tags', $selectors ) ) . " {\n\tdisplay: none !important;\n}" );
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
	 * Tabblad Opruimen.
	 */
	public function render_tab(): void {
		?>
		<h2><?php esc_html_e( 'Algemeen', 'hashtagmedia-toolkit' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$this->checkbox_row( 'notices', __( 'Meldingen', 'hashtagmedia-toolkit' ), __( 'Verzamelen achter één knop in de adminbalk', 'hashtagmedia-toolkit' ), __( 'Alleen HashtagMedia-beheerders zien de knop; klanten zien geen meldingen.', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'dashboard', __( 'Dashboard', 'hashtagmedia-toolkit' ), __( 'Alle widgets van WordPress en plugins verwijderen', 'hashtagmedia-toolkit' ), __( 'Widgets van HashtagMedia blijven staan.', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'adminbar', __( 'Adminbalk', 'hashtagmedia-toolkit' ), __( 'WordPress-logo, Customizer, zoeken en plugin-items verwijderen', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'comments', __( 'Reacties', 'hashtagmedia-toolkit' ), __( 'Reacties en pingbacks volledig uitzetten', 'hashtagmedia-toolkit' ), __( 'Ook het menu Reacties, Instellingen > Discussie, de REST-routes en de reactiefeeds.', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'ad_links', __( 'Reclamelinks', 'hashtagmedia-toolkit' ), __( 'Externe links uit het menu en de pluginlijst verwijderen', 'hashtagmedia-toolkit' ), __( 'Zoals "Upgrade to Pro" en "Get Premium".', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'footer', __( 'Footertekst', 'hashtagmedia-toolkit' ), __( 'Footerteksten van WordPress en plugins verwijderen', 'hashtagmedia-toolkit' ) );
			?>
		</table>

		<h2><?php esc_html_e( 'Pluginprofielen', 'hashtagmedia-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Per plugin worden reclame, upsells, overbodige menu-items en kolommen verborgen. Een profiel werkt alleen als de plugin actief is.', 'hashtagmedia-toolkit' ); ?></p>
		<table class="form-table" role="presentation">
			<?php
			foreach ( $this->profiles()->all() as $slug => $profile ) {
				$this->checkbox_row(
					'profile_' . $slug,
					$profile['name'],
					__( 'Opruimen', 'hashtagmedia-toolkit' ),
					Profiles::is_detected( $profile ) ? __( 'Plugin is actief.', 'hashtagmedia-toolkit' ) : __( 'Plugin is niet actief; profiel doet nu niets.', 'hashtagmedia-toolkit' )
				);
			}
			?>
		</table>
		<?php
	}

	/**
	 * Saneert het tabblad: alleen bekende schakelaars, als true/false.
	 *
	 * @param array $input Invoer.
	 * @return array
	 */
	public function sanitize( array $input ): array {
		return $this->sanitize_checkboxes( $input, array_keys( $this->defaults() ) );
	}
}
