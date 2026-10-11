<?php
/**
 * Instellingenpagina "HashtagMedia" met tabbladen.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit;

defined( 'ABSPATH' ) || exit;

/**
 * Menu-item, tabbladen en het opslaan van instellingen.
 */
final class Admin_Page {

	/**
	 * Slug van de pagina.
	 */
	public const SLUG = 'hashtagmedia';

	/**
	 * Recht dat nodig is voor de pagina en alle acties.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Menu-icoon (hekje), als base64-SVG zodat WordPress het mee-kleurt.
	 */
	private const ICON = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBhdGggZmlsbD0iYmxhY2siIGZpbGwtcnVsZT0iZXZlbm9kZCIgZD0iTTcuNSAyaDJsLS42IDRoMy41bC42LTRoMmwtLjYgNEgxN3YyaC0yLjlsLS41IDRIMTZ2MmgtMi43bC0uNiA0aC0ybC42LTRINy44bC0uNiA0aC0ybC42LTRIM3YtMmgzLjFsLjUtNEg0VjZoMi45ek04LjYgOGwtLjUgNGgzLjVsLjUtNHoiLz48L3N2Zz4=';

	/**
	 * Hooks registreren.
	 */
	public static function start(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_post_hm_toolkit_save_modules', array( self::class, 'handle_save_modules' ) );
		add_action( 'admin_post_hm_toolkit_save_settings', array( self::class, 'handle_save_settings' ) );
		add_action( 'admin_post_hm_toolkit_cleanup', array( self::class, 'handle_cleanup' ) );
	}

	/**
	 * Menu-item "HashtagMedia".
	 */
	public static function register_menu(): void {
		$hook = add_menu_page(
			__( 'HashtagMedia', 'hashtagmedia-toolkit' ),
			__( 'HashtagMedia', 'hashtagmedia-toolkit' ),
			self::CAPABILITY,
			self::SLUG,
			array( self::class, 'render' ),
			self::ICON,
			81
		);

		// Alleen op deze pagina de map doorzoeken, zodat nieuwe modules verschijnen.
		add_action( 'load-' . $hook, array( Registry::class, 'sync' ) );
	}

	/**
	 * URL van de pagina, eventueel met tabblad.
	 *
	 * @param string $tab  Tabblad.
	 * @param array  $args Extra query-argumenten.
	 * @return string
	 */
	public static function url( string $tab = 'modules', array $args = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::SLUG,
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Tabbladen: Modules plus elk actief module-tabblad.
	 *
	 * @return array<string,string> slug => label
	 */
	private static function tabs(): array {
		$tabs  = array( 'modules' => __( 'Modules', 'hashtagmedia-toolkit' ) );
		$found = Registry::discover();

		foreach ( Registry::loaded() as $slug => $module ) {
			if ( $module->has_tab() && isset( $found[ $slug ] ) ) {
				$tabs[ $slug ] = $found[ $slug ]['name'];
			}
		}

		return $tabs;
	}

	/**
	 * Huidig tabblad uit de URL (alleen lezen, geen actie).
	 *
	 * @param array<string,string> $tabs Beschikbare tabbladen.
	 * @return string
	 */
	private static function current_tab( array $tabs ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen navigatie.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'modules';
		return isset( $tabs[ $tab ] ) ? $tab : 'modules';
	}

	/**
	 * Pagina tekenen.
	 */
	public static function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$tabs    = self::tabs();
		$current = self::current_tab( $tabs );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'HashtagMedia', 'hashtagmedia-toolkit' ); ?></h1>
			<hr class="wp-header-end">
			<?php self::render_notice(); ?>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Onderdelen', 'hashtagmedia-toolkit' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( self::url( $slug ) ); ?>" class="nav-tab<?php echo $slug === $current ? ' nav-tab-active' : ''; ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'modules' === $current ) {
				self::render_modules_tab();
			} else {
				self::render_module_tab( $current );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Bevestiging na opslaan (alleen lezen van een vaste waarde).
	 */
	private static function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen weergave van een vaste melding.
		$notice   = isset( $_GET['hm-notice'] ) ? sanitize_key( wp_unslash( $_GET['hm-notice'] ) ) : '';
		$messages = array(
			'saved'   => __( 'Instellingen opgeslagen.', 'hashtagmedia-toolkit' ),
			'modules' => __( 'Modules bijgewerkt.', 'hashtagmedia-toolkit' ),
			'cleaned' => __( 'Gegevens van de module opgeruimd.', 'hashtagmedia-toolkit' ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $messages[ $notice ] )
			);
		}
	}

	/**
	 * Tabblad Modules: per module een schakelaar met één regel uitleg.
	 */
	private static function render_modules_tab(): void {
		$found = Registry::discover();
		?>
		<p><?php esc_html_e( 'Zet onderdelen aan of uit. Een module die uitstaat, laadt geen code; de instellingen blijven bewaard.', 'hashtagmedia-toolkit' ); ?></p>

		<?php if ( empty( $found ) ) : ?>
			<p><em><?php esc_html_e( 'Er zijn nog geen modules.', 'hashtagmedia-toolkit' ); ?></em></p>
			<?php
			return;
		endif;
		?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hm_toolkit_save_modules">
			<?php wp_nonce_field( 'hm_toolkit_save_modules' ); ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<td class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Aan', 'hashtagmedia-toolkit' ); ?></span></td>
						<th scope="col"><?php esc_html_e( 'Module', 'hashtagmedia-toolkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Uitleg', 'hashtagmedia-toolkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Beheer', 'hashtagmedia-toolkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $found as $slug => $meta ) : ?>
						<?php
						$enabled = Settings::is_enabled( $slug );
						$id      = 'hm-module-' . $slug;
						?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="modules[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $enabled ); ?>>
							</th>
							<td><label for="<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $meta['name'] ); ?></strong></label></td>
							<td><?php echo esc_html( $meta['description'] ); ?></td>
							<td>
								<?php if ( $enabled && Registry::get( $slug ) && Registry::get( $slug )->has_tab() ) : ?>
									<a href="<?php echo esc_url( self::url( $slug ) ); ?>"><?php esc_html_e( 'Instellingen', 'hashtagmedia-toolkit' ); ?></a>
								<?php elseif ( ! $enabled && Settings::has_module_settings( $slug ) ) : ?>
									<a href="<?php echo esc_url( self::cleanup_url( $slug ) ); ?>" class="delete" onclick="return window.confirm(<?php echo esc_attr( wp_json_encode( __( 'Instellingen en gegevens van deze module definitief verwijderen?', 'hashtagmedia-toolkit' ) ) ); ?>);"><?php esc_html_e( 'Gegevens opruimen', 'hashtagmedia-toolkit' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Modules opslaan', 'hashtagmedia-toolkit' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Tabblad van een module: formulier van de kern, velden van de module.
	 *
	 * @param string $slug Module-slug.
	 */
	private static function render_module_tab( string $slug ): void {
		$module = Registry::get( $slug );
		if ( null === $module ) {
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hm_toolkit_save_settings">
			<input type="hidden" name="module" value="<?php echo esc_attr( $slug ); ?>">
			<?php wp_nonce_field( 'hm_toolkit_save_' . $slug ); ?>
			<?php $module->render_tab(); ?>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * URL om de gegevens van een uitgeschakelde module op te ruimen.
	 *
	 * @param string $slug Module-slug.
	 * @return string
	 */
	private static function cleanup_url( string $slug ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'hm_toolkit_cleanup',
					'module' => $slug,
				),
				admin_url( 'admin-post.php' )
			),
			'hm_toolkit_cleanup_' . $slug
		);
	}

	/**
	 * Stopt als de gebruiker het recht niet heeft.
	 */
	private static function require_capability(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Je hebt geen toegang tot deze actie.', 'hashtagmedia-toolkit' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Opslaan: welke modules aanstaan.
	 */
	public static function handle_save_modules(): void {
		self::require_capability();
		check_admin_referer( 'hm_toolkit_save_modules' );

		$posted = isset( $_POST['modules'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['modules'] ) ) : array();

		$states = array();
		foreach ( array_keys( Registry::discover() ) as $slug ) {
			$states[ $slug ] = in_array( $slug, $posted, true );
		}
		Settings::set_enabled_many( $states );

		wp_safe_redirect( self::url( 'modules', array( 'hm-notice' => 'modules' ) ) );
		exit;
	}

	/**
	 * Opslaan: instellingen van één module, gesaneerd door de module zelf.
	 */
	public static function handle_save_settings(): void {
		self::require_capability();

		$slug = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
		check_admin_referer( 'hm_toolkit_save_' . $slug );

		$module = Registry::get( $slug );
		if ( null === $module || ! $module->has_tab() ) {
			wp_die( esc_html__( 'Onbekende module.', 'hashtagmedia-toolkit' ), '', array( 'response' => 400 ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Gesaneerd door $module->sanitize().
		$input = isset( $_POST['hm_toolkit'] ) && is_array( $_POST['hm_toolkit'] ) ? wp_unslash( $_POST['hm_toolkit'] ) : array();

		Settings::set_module( $slug, $module->sanitize( $input ) );

		wp_safe_redirect( self::url( $slug, array( 'hm-notice' => 'saved' ) ) );
		exit;
	}

	/**
	 * Opruimen: opruimroutine van een uitgeschakelde module draaien.
	 */
	public static function handle_cleanup(): void {
		self::require_capability();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce wordt hieronder gecontroleerd met de slug erin.
		$slug = isset( $_GET['module'] ) ? sanitize_key( wp_unslash( $_GET['module'] ) ) : '';
		check_admin_referer( 'hm_toolkit_cleanup_' . $slug );

		if ( '' === $slug || Settings::is_enabled( $slug ) ) {
			wp_die( esc_html__( 'Zet de module eerst uit.', 'hashtagmedia-toolkit' ), '', array( 'response' => 400 ) );
		}

		Registry::cleanup( $slug );

		wp_safe_redirect( self::url( 'modules', array( 'hm-notice' => 'cleaned' ) ) );
		exit;
	}
}
