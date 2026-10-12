<?php
/**
 * Module Klantrol.
 *
 * Eén rol "Klant" (hm_klant). Beveiligingsprincipe:
 *
 * - In de database (rol) staan alleen rechten die ook zonder deze module
 *   veilig zijn: content, media, categorieën en Gravity Forms-inzendingen en
 *   -formulieren bewerken.
 * - Rechten die alleen veilig zijn mét de regels van deze module (menu's en
 *   widgets, collega's beheren) worden tijdens het draaien gegeven
 *   (user_has_cap). Staat de module uit of is hij weg, dan vervallen ze.
 * - Een klant ziet alleen andere klanten, kan alleen klanten aanmaken,
 *   bewerken en verwijderen, en kan niemand een andere rol geven.
 *   HashtagMedia-beheerders zijn onzichtbaar en onaantastbaar.
 * - Nooit: ongefilterde HTML, plugins, thema's, instellingen, Customizer.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Klantrol;

use HashtagMedia\Toolkit\Module as Base;

defined( 'ABSPATH' ) || exit;

/**
 * De rol Klant en de regels eromheen.
 */
final class Module extends Base {

	/**
	 * Slug van de rol.
	 */
	public const ROLE = 'hm_klant';

	/**
	 * Verhoog dit als STORED_CAPS verandert; de rol wordt dan bijgewerkt.
	 */
	private const ROLE_VERSION = 1;

	/**
	 * Rechten in de rol zelf (veilig zonder deze module).
	 */
	private const STORED_CAPS = array(
		'read',
		'level_0',
		'level_1',
		'level_2',
		'level_3',
		'level_4',
		'level_5',
		'level_6',
		'level_7',
		'upload_files',
		'manage_categories',
		'edit_posts',
		'edit_others_posts',
		'edit_published_posts',
		'edit_private_posts',
		'publish_posts',
		'read_private_posts',
		'delete_posts',
		'delete_others_posts',
		'delete_published_posts',
		'delete_private_posts',
		'edit_pages',
		'edit_others_pages',
		'edit_published_pages',
		'edit_private_pages',
		'publish_pages',
		'read_private_pages',
		'delete_pages',
		'delete_others_pages',
		'delete_published_pages',
		'delete_private_pages',
		// Gravity Forms: inzendingen en formulieren bewerken; niet aanmaken,
		// verwijderen, instellingen of add-ons.
		'gravityforms_view_entries',
		'gravityforms_edit_entries',
		'gravityforms_delete_entries',
		'gravityforms_export_entries',
		'gravityforms_view_entry_notes',
		'gravityforms_edit_entry_notes',
		'gravityforms_edit_forms',
		'gravityforms_preview_forms',
	);

	/**
	 * Altijd tijdens het draaien: menu's en widgets.
	 */
	private const RUNTIME_CAPS = array( 'edit_theme_options' );

	/**
	 * Tijdens het draaien als "collega's beheren" aanstaat.
	 */
	private const USER_CAPS = array( 'list_users', 'create_users', 'edit_users', 'delete_users', 'promote_users' );

	/**
	 * Nooit voor een klant, ook niet als een andere plugin ze toevoegt.
	 */
	private const NEVER_CAPS = array(
		'unfiltered_html',
		'unfiltered_upload',
		'manage_options',
		'install_plugins',
		'activate_plugins',
		'edit_plugins',
		'update_plugins',
		'delete_plugins',
		'install_themes',
		'switch_themes',
		'edit_themes',
		'update_themes',
		'delete_themes',
		'update_core',
		'edit_files',
		'import',
		'export',
		'manage_privacy_options',
		'gform_full_access',
		'gravityforms_create_form',
		'gravityforms_delete_forms',
		'gravityforms_edit_settings',
		'gravityforms_view_settings',
		'gravityforms_uninstall',
		'gravityforms_view_addons',
		'gravityforms_addon_browser',
		'gravityforms_system_status',
		'wpseo_manage_options',
	);

	/**
	 * Gebruikersrechten die op een specifieke gebruiker slaan.
	 */
	private const USER_META_CAPS = array( 'edit_user', 'delete_user', 'remove_user', 'promote_user' );

	/**
	 * Standaardwaarden.
	 *
	 * @return array
	 */
	public function defaults(): array {
		return array(
			'colleagues'   => true,
			'role_version' => 0,
		);
	}

	/**
	 * Is deze gebruiker een klant (en geen HashtagMedia-beheerder)?
	 *
	 * @param \WP_User|null $user Gebruiker; standaard de huidige.
	 * @return bool
	 */
	public static function is_klant( ?\WP_User $user = null ): bool {
		$user = $user ?? wp_get_current_user();
		return $user instanceof \WP_User && $user->exists() && in_array( self::ROLE, (array) $user->roles, true ) && ! in_array( 'administrator', (array) $user->roles, true );
	}

	/**
	 * Heeft een gebruiker alleen de rol Klant?
	 *
	 * @param \WP_User $user Gebruiker.
	 * @return bool
	 */
	private static function is_klant_only( \WP_User $user ): bool {
		return array( self::ROLE ) === array_values( (array) $user->roles );
	}

	/**
	 * Frontend, admin en REST.
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'ensure_role' ), 1 );
		add_filter( 'user_has_cap', array( $this, 'user_has_cap' ), 10, 4 );
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );
		add_filter( 'editable_roles', array( $this, 'editable_roles' ) );
		add_action( 'pre_get_users', array( $this, 'only_klanten' ) );
		add_action( 'user_register', array( $this, 'enforce_role_on_register' ) );
		add_action( 'set_user_role', array( $this, 'enforce_role_on_change' ), 10, 2 );
		add_action( 'add_user_role', array( $this, 'enforce_role_on_change' ), 10, 2 );
	}

	/**
	 * Alleen admin.
	 */
	public function admin(): void {
		add_filter( 'views_users', array( $this, 'hide_user_views' ) );
		add_action( 'admin_menu', array( $this, 'clean_appearance_menu' ), PHP_INT_MAX );
		add_action( 'admin_init', array( $this, 'redirect_themes_screen' ) );
	}

	/**
	 * Rol aanmaken of bijwerken als hij ontbreekt of verouderd is.
	 */
	public function ensure_role(): void {
		$role = get_role( self::ROLE );
		if ( $role && (int) $this->get( 'role_version' ) === self::ROLE_VERSION ) {
			return;
		}

		if ( ! $role ) {
			$role = add_role( self::ROLE, __( 'Klant', 'hashtagmedia-toolkit' ), array_fill_keys( self::STORED_CAPS, true ) );
		} else {
			foreach ( array_keys( (array) $role->capabilities ) as $cap ) {
				if ( ! in_array( $cap, self::STORED_CAPS, true ) ) {
					$role->remove_cap( $cap );
				}
			}
			foreach ( self::STORED_CAPS as $cap ) {
				$role->add_cap( $cap );
			}
		}

		if ( $role ) {
			$this->update( array( 'role_version' => self::ROLE_VERSION ) );
		}
	}

	/**
	 * Rechten tijdens het draaien geven en verbieden.
	 *
	 * @param array    $allcaps Rechten van de gebruiker.
	 * @param string[] $caps    Gevraagde primitieve rechten.
	 * @param array    $args    Argumenten.
	 * @param \WP_User $user    Gebruiker.
	 * @return array
	 */
	public function user_has_cap( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args );
		if ( ! is_array( $allcaps ) || ! $user instanceof \WP_User || ! self::is_klant( $user ) ) {
			return $allcaps;
		}

		foreach ( self::RUNTIME_CAPS as $cap ) {
			$allcaps[ $cap ] = true;
		}
		foreach ( self::USER_CAPS as $cap ) {
			$allcaps[ $cap ] = (bool) $this->get( 'colleagues' );
		}
		foreach ( self::NEVER_CAPS as $cap ) {
			$allcaps[ $cap ] = false;
		}

		return $allcaps;
	}

	/**
	 * Regels per gebruiker en voor de Customizer.
	 *
	 * @param string[] $caps    Benodigde rechten.
	 * @param string   $cap     Gevraagd recht.
	 * @param int      $user_id Handelende gebruiker.
	 * @param array    $args    Argumenten (doelgebruiker).
	 * @return string[]
	 */
	public function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( ! is_array( $caps ) ) {
			return $caps;
		}

		$actor = get_userdata( (int) $user_id );
		if ( ! $actor || ! self::is_klant( $actor ) ) {
			return $caps;
		}

		// Customizer en sitebewerker: nooit.
		if ( in_array( $cap, array( 'customize', 'edit_css' ), true ) ) {
			$caps[] = 'do_not_allow';
			return $caps;
		}

		if ( ! in_array( $cap, self::USER_META_CAPS, true ) || empty( $args[0] ) ) {
			return $caps;
		}

		$target_id = (int) $args[0];

		// Eigen profiel bewerken mag; zichzelf verwijderen of een rol geven niet.
		if ( $target_id === (int) $user_id ) {
			if ( 'edit_user' !== $cap ) {
				$caps[] = 'do_not_allow';
			}
			return $caps;
		}

		// Alleen andere klanten; beheerders en alle andere rollen zijn onaantastbaar.
		$target = get_userdata( $target_id );
		if ( ! $target || ! self::is_klant_only( $target ) || ! $this->get( 'colleagues' ) ) {
			$caps[] = 'do_not_allow';
		}

		return $caps;
	}

	/**
	 * Een klant kan alleen de rol Klant toekennen.
	 *
	 * @param array $roles Rollen.
	 * @return array
	 */
	public function editable_roles( $roles ) {
		if ( ! is_array( $roles ) || ! self::is_klant() ) {
			return $roles;
		}
		return isset( $roles[ self::ROLE ] ) ? array( self::ROLE => $roles[ self::ROLE ] ) : array();
	}

	/**
	 * Een klant ziet in gebruikerslijsten (admin en REST) alleen klanten.
	 *
	 * @param \WP_User_Query $query Query.
	 */
	public function only_klanten( $query ): void {
		if ( ! $query instanceof \WP_User_Query || ! self::is_klant() ) {
			return;
		}
		if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$query->set( 'role', '' );
		$query->set( 'role__not_in', array() );
		$query->set( 'role__in', array( self::ROLE ) );
	}

	/**
	 * Geen tellers per rol boven de gebruikerslijst (die verraden beheerders).
	 *
	 * @param array $views Weergaven.
	 * @return array
	 */
	public function hide_user_views( $views ) {
		return self::is_klant() ? array() : $views;
	}

	/**
	 * Vangnet: wat een klant aanmaakt, is altijd een klant.
	 *
	 * @param int $user_id Nieuwe gebruiker.
	 */
	public function enforce_role_on_register( $user_id ): void {
		if ( ! self::is_klant() ) {
			return;
		}
		$user = get_userdata( (int) $user_id );
		if ( $user && ! self::is_klant_only( $user ) ) {
			$user->set_role( self::ROLE );
		}
	}

	/**
	 * Vangnet: een klant kan niemand een andere rol geven.
	 *
	 * @param int    $user_id Gebruiker.
	 * @param string $role    Nieuwe rol.
	 */
	public function enforce_role_on_change( $user_id, $role ): void {
		if ( self::ROLE === $role || '' === (string) $role || ! self::is_klant() ) {
			return;
		}
		$user = get_userdata( (int) $user_id );
		if ( $user ) {
			$user->set_role( self::ROLE );
		}
	}

	/**
	 * Weergave-menu voor klanten: alleen Menu's en Widgets.
	 */
	public function clean_appearance_menu(): void {
		global $submenu;

		if ( ! self::is_klant() || empty( $submenu['themes.php'] ) ) {
			return;
		}
		foreach ( (array) $submenu['themes.php'] as $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';
			if ( ! in_array( $slug, array( 'nav-menus.php', 'widgets.php' ), true ) ) {
				remove_submenu_page( 'themes.php', $slug );
			}
		}
	}

	/**
	 * Het themascherm doorsturen naar Menu's.
	 */
	public function redirect_themes_screen(): void {
		global $pagenow;

		if ( self::is_klant() && in_array( $pagenow, array( 'themes.php', 'customize.php', 'site-editor.php', 'theme-install.php' ), true ) ) {
			wp_safe_redirect( admin_url( 'nav-menus.php' ) );
			exit;
		}
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
	 * Tabblad Klantrol.
	 */
	public function render_tab(): void {
		$count = count(
			get_users(
				array(
					'role'   => self::ROLE,
					'fields' => 'ID',
				)
			)
		);
		?>
		<p>
			<?php
			/* translators: %d: aantal gebruikers */
			echo esc_html( sprintf( _n( 'Er is %d gebruiker met de rol Klant.', 'Er zijn %d gebruikers met de rol Klant.', $count, 'hashtagmedia-toolkit' ), $count ) );
			?>
			<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Klant toevoegen', 'hashtagmedia-toolkit' ); ?></a>
		</p>

		<div class="hm-role-summary">
			<div>
				<h3><?php esc_html_e( 'Een klant mag', 'hashtagmedia-toolkit' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Pagina\'s, berichten, media en categorieën beheren', 'hashtagmedia-toolkit' ); ?></li>
					<li><?php esc_html_e( 'ACF-optiepagina\'s en Yoast per pagina invullen', 'hashtagmedia-toolkit' ); ?></li>
					<li><?php esc_html_e( 'Menu\'s en widgets bewerken', 'hashtagmedia-toolkit' ); ?></li>
					<li><?php esc_html_e( 'Formulieren, meldingen en bevestigingen bewerken; inzendingen bekijken en exporteren', 'hashtagmedia-toolkit' ); ?></li>
					<li><?php esc_html_e( 'Collega\'s toevoegen, bewerken en verwijderen, alleen met de rol Klant', 'hashtagmedia-toolkit' ); ?></li>
				</ul>
			</div>
			<div>
				<h3><?php esc_html_e( 'Een klant mag niet', 'hashtagmedia-toolkit' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Plugins, thema\'s, Customizer of instellingen', 'hashtagmedia-toolkit' ); ?></li>
					<li><?php esc_html_e( 'Ongefilterde HTML of scripts invoegen', 'hashtagmedia-toolkit' ); ?></li>
					<li><?php esc_html_e( 'Formulieren aanmaken of verwijderen, formulierinstellingen', 'hashtagmedia-toolkit' ); ?></li>
					<li><?php esc_html_e( 'HashtagMedia-beheerders zien of bewerken', 'hashtagmedia-toolkit' ); ?></li>
					<li><?php esc_html_e( 'Iemand een andere rol dan Klant geven', 'hashtagmedia-toolkit' ); ?></li>
				</ul>
			</div>
		</div>

		<table class="form-table" role="presentation">
			<?php
			$this->checkbox_row( 'colleagues', __( 'Collega\'s', 'hashtagmedia-toolkit' ), __( 'Klanten mogen collega\'s met de rol Klant beheren', 'hashtagmedia-toolkit' ), __( 'Uit: alleen HashtagMedia beheert gebruikers.', 'hashtagmedia-toolkit' ) );
			?>
		</table>
		<?php
	}

	/**
	 * Saneert het tabblad; de rolversie blijft behouden.
	 *
	 * @param array $input Invoer.
	 * @return array
	 */
	public function sanitize( array $input ): array {
		$clean                 = $this->sanitize_checkboxes( $input, array( 'colleagues' ) );
		$clean['role_version'] = (int) $this->get( 'role_version' );
		return $clean;
	}
}
