<?php
/**
 * Module Instellingen export/import.
 *
 * - Exporteren: welke modules aanstaan en hun instellingen, als JSON.
 * - Importeren: zo'n bestand uploaden.
 * - HashtagMedia-standaard: standaard.json in deze map, in één klik.
 *
 * Alleen de toolkit zelf; andere plugins hebben hun eigen export. Wat per
 * site verschilt (Module::site_settings()) gaat nooit mee. Elke module saneert
 * zijn eigen waarden (Module::import_settings()); onbekende modules en
 * sleutels worden overgeslagen.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\ExportImport;

use HashtagMedia\Toolkit\Module as Base;
use HashtagMedia\Toolkit\Registry;
use HashtagMedia\Toolkit\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Instellingen bewaren, overzetten en de standaard toepassen.
 */
final class Module extends Base {

	/**
	 * Herkenning en versie van het bestandsformaat.
	 */
	private const PLUGIN = 'hashtagmedia-toolkit';
	private const FORMAT = 1;

	/**
	 * Grootste bestand dat we inlezen (bytes).
	 */
	private const MAX_BYTES = 512000;

	/**
	 * Alleen admin.
	 */
	public function admin(): void {
		add_action( 'admin_post_hm_toolkit_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_hm_toolkit_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_hm_toolkit_import_standard', array( $this, 'handle_standard' ) );
	}

	/**
	 * Pad naar de HashtagMedia-standaard.
	 *
	 * @return string
	 */
	private function standard_file(): string {
		return $this->path( 'standaard.json' );
	}

	/**
	 * Alle instellingen als array voor het exportbestand.
	 *
	 * @return array
	 */
	public static function export_data(): array {
		$data = array(
			'plugin'   => self::PLUGIN,
			'format'   => self::FORMAT,
			'version'  => HM_TOOLKIT_VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => home_url( '/' ),
			'modules'  => array(),
			'settings' => array(),
		);

		foreach ( array_keys( Registry::discover() ) as $slug ) {
			$data['modules'][ $slug ] = Settings::is_enabled( $slug );
			$module                   = Registry::instance( $slug );
			$values                   = $module ? $module->export_settings() : array();
			if ( array() !== $values ) {
				$data['settings'][ $slug ] = $values;
			}
		}

		return $data;
	}

	/**
	 * Een exportbestand toepassen.
	 *
	 * @param mixed $data Gedecodeerde JSON.
	 * @return array{modules:int,settings:int}|WP_Error
	 */
	public static function apply( $data ) {
		if ( ! is_array( $data ) || self::PLUGIN !== ( $data['plugin'] ?? '' ) ) {
			return new WP_Error( 'invalid', '' );
		}
		if ( self::FORMAT !== (int) ( $data['format'] ?? 0 ) ) {
			return new WP_Error( 'format', '' );
		}

		$found    = Registry::discover();
		$modules  = is_array( $data['modules'] ?? null ) ? $data['modules'] : array();
		$settings = is_array( $data['settings'] ?? null ) ? $data['settings'] : array();
		$states   = array();
		$changed  = 0;

		foreach ( array_keys( $found ) as $slug ) {
			if ( array_key_exists( $slug, $modules ) && is_scalar( $modules[ $slug ] ) ) {
				$states[ $slug ] = rest_sanitize_boolean( $modules[ $slug ] );
			}
		}
		if ( array() !== $states ) {
			Settings::set_enabled_many( $states );
		}

		foreach ( array_keys( $found ) as $slug ) {
			if ( ! isset( $settings[ $slug ] ) || ! is_array( $settings[ $slug ] ) ) {
				continue;
			}
			$module = Registry::instance( $slug );
			if ( null === $module ) {
				continue;
			}
			$clean = $module->import_settings( $settings[ $slug ] );
			if ( array() === $clean ) {
				continue;
			}
			// Samenvoegen: sitegebonden waarden blijven zoals ze op deze site zijn.
			Settings::set_module( $slug, array_merge( Settings::module( $slug ), $clean ) );
			++$changed;
		}

		return array(
			'modules'  => count( $states ),
			'settings' => $changed,
		);
	}

	/**
	 * JSON-bestand inlezen.
	 *
	 * @param string $file Pad.
	 * @return mixed|WP_Error
	 */
	private static function read_json( string $file ) {
		if ( ! is_readable( $file ) || filesize( $file ) > self::MAX_BYTES ) {
			return new WP_Error( 'invalid', '' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Lokaal bestand.
		$json = file_get_contents( $file );
		$data = is_string( $json ) ? json_decode( $json, true ) : null;
		return is_array( $data ) ? $data : new WP_Error( 'invalid', '' );
	}

	/**
	 * Alleen HashtagMedia-beheerders.
	 */
	private static function require_capability(): void {
		if ( ! hm_toolkit_is_agency_user() ) {
			wp_die( esc_html__( 'Je hebt geen toegang tot deze actie.', 'hashtagmedia-toolkit' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Terug naar het tabblad met een melding.
	 *
	 * @param string $result Code van de melding.
	 */
	private function back( string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'hashtagmedia',
					'tab'       => $this->slug(),
					'hm-import' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Download van het exportbestand.
	 */
	public function handle_export(): void {
		self::require_capability();
		check_admin_referer( 'hm_toolkit_export' );

		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$name = sprintf( 'hashtagmedia-toolkit-%s-%s.json', sanitize_file_name( $host ), gmdate( 'Y-m-d' ) );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-download, geen HTML.
		echo wp_json_encode( self::export_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Geüpload bestand importeren.
	 */
	public function handle_import(): void {
		self::require_capability();
		check_admin_referer( 'hm_toolkit_import' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Bestandsupload: alleen fout, grootte, naam en tijdelijk pad; de inhoud wordt hieronder als JSON gecontroleerd.
		$upload = isset( $_FILES['hm_toolkit_file'] ) && is_array( $_FILES['hm_toolkit_file'] ) ? $_FILES['hm_toolkit_file'] : array();
		$error  = isset( $upload['error'] ) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
		$tmp    = isset( $upload['tmp_name'] ) ? (string) $upload['tmp_name'] : '';
		$name   = isset( $upload['name'] ) ? sanitize_file_name( (string) $upload['name'] ) : '';
		$size   = isset( $upload['size'] ) ? (int) $upload['size'] : 0;
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput

		if ( UPLOAD_ERR_OK !== $error || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			$this->back( 'upload' );
		}
		if ( 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || $size > self::MAX_BYTES ) {
			$this->back( 'invalid' );
		}

		$data   = self::read_json( $tmp );
		$result = is_wp_error( $data ) ? $data : self::apply( $data );
		$this->back( is_wp_error( $result ) ? $result->get_error_code() : 'imported' );
	}

	/**
	 * HashtagMedia-standaard toepassen.
	 */
	public function handle_standard(): void {
		self::require_capability();
		check_admin_referer( 'hm_toolkit_import_standard' );

		$data   = self::read_json( $this->standard_file() );
		$result = is_wp_error( $data ) ? $data : self::apply( $data );
		$this->back( is_wp_error( $result ) ? 'standard-missing' : 'standard' );
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
	 * Dit tabblad heeft eigen formulieren (o.a. een upload).
	 *
	 * @return bool
	 */
	public function tab_form(): bool {
		return false;
	}

	/**
	 * Melding na een actie (alleen weergave van een vaste waarde).
	 */
	private function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen weergave van een vaste melding.
		$code     = isset( $_GET['hm-import'] ) ? sanitize_key( wp_unslash( $_GET['hm-import'] ) ) : '';
		$messages = array(
			'imported'         => array( 'success', __( 'Instellingen geïmporteerd.', 'hashtagmedia-toolkit' ) ),
			'standard'         => array( 'success', __( 'HashtagMedia-standaard toegepast.', 'hashtagmedia-toolkit' ) ),
			'upload'           => array( 'error', __( 'Er is geen bestand ontvangen. Kies een JSON-bestand en probeer het opnieuw.', 'hashtagmedia-toolkit' ) ),
			'invalid'          => array( 'error', __( 'Dit is geen geldig exportbestand van de HashtagMedia Toolkit. Er is niets gewijzigd.', 'hashtagmedia-toolkit' ) ),
			'format'           => array( 'error', __( 'Dit exportbestand komt uit een andere versie van de toolkit en kan niet worden ingelezen. Er is niets gewijzigd.', 'hashtagmedia-toolkit' ) ),
			'standard-missing' => array( 'error', __( 'De HashtagMedia-standaard ontbreekt of is beschadigd. Er is niets gewijzigd.', 'hashtagmedia-toolkit' ) ),
		);
		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $code ][0] ),
			esc_html( $messages[ $code ][1] )
		);
	}

	/**
	 * Begin van een eigen formulier naar admin-post.
	 *
	 * @param string $action Actie (ook de nonce-naam).
	 * @param bool   $upload Met bestandsupload.
	 */
	private static function form_open( string $action, bool $upload = false ): void {
		printf(
			'<form method="post" action="%1$s"%2$s>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$upload ? ' enctype="multipart/form-data"' : ''
		);
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( $action ) );
		wp_nonce_field( $action );
	}

	/**
	 * Onclick-bevestiging.
	 *
	 * @param string $text Vraag.
	 * @return string
	 */
	private static function confirm( string $text ): string {
		return 'return window.confirm(' . (string) wp_json_encode( $text ) . ');';
	}

	/**
	 * Tabblad Export en import.
	 */
	public function render_tab(): void {
		$standard = self::read_json( $this->standard_file() );
		$skipped  = __( 'Wat per site verschilt gaat niet mee: contactgegevens en bereikbaarheid, de link naar de handleiding en de beschermde pagina\'s. Instellingen van andere plugins (Yoast, WP Rocket, Complianz, Gravity Forms) ook niet; die hebben hun eigen export.', 'hashtagmedia-toolkit' );

		$this->render_notice();
		?>
		<h2><?php esc_html_e( 'HashtagMedia-standaard', 'hashtagmedia-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Zet alle modules en instellingen in één keer zoals HashtagMedia ze standaard gebruikt. Handig op een nieuwe site.', 'hashtagmedia-toolkit' ); ?></p>
		<?php if ( is_array( $standard ) ) : ?>
			<p class="description">
				<?php
				/* translators: %s: versienummer van de toolkit */
				echo esc_html( sprintf( __( 'Standaard uit toolkit-versie %s.', 'hashtagmedia-toolkit' ), (string) ( $standard['version'] ?? '?' ) ) );
				?>
			</p>
			<?php self::form_open( 'hm_toolkit_import_standard' ); ?>
				<p><button type="submit" class="button button-primary" onclick="<?php echo esc_attr( self::confirm( __( 'De HashtagMedia-standaard toepassen? Huidige instellingen van de toolkit worden overschreven.', 'hashtagmedia-toolkit' ) ) ); ?>"><?php esc_html_e( 'Standaard toepassen', 'hashtagmedia-toolkit' ); ?></button></p>
			</form>
		<?php else : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Het standaardbestand ontbreekt in deze versie van de toolkit.', 'hashtagmedia-toolkit' ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Exporteren', 'hashtagmedia-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Download welke modules aanstaan en hun instellingen als JSON-bestand.', 'hashtagmedia-toolkit' ); ?></p>
		<?php self::form_open( 'hm_toolkit_export' ); ?>
			<p><button type="submit" class="button"><?php esc_html_e( 'Instellingen downloaden', 'hashtagmedia-toolkit' ); ?></button></p>
		</form>

		<h2><?php esc_html_e( 'Importeren', 'hashtagmedia-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Upload een exportbestand van een andere site. Huidige instellingen van de toolkit worden overschreven.', 'hashtagmedia-toolkit' ); ?></p>
		<?php self::form_open( 'hm_toolkit_import', true ); ?>
			<p>
				<label class="screen-reader-text" for="hm-toolkit-file"><?php esc_html_e( 'Exportbestand (JSON)', 'hashtagmedia-toolkit' ); ?></label>
				<input type="file" id="hm-toolkit-file" name="hm_toolkit_file" accept=".json,application/json" required>
			</p>
			<p><button type="submit" class="button" onclick="<?php echo esc_attr( self::confirm( __( 'Dit bestand importeren? Huidige instellingen van de toolkit worden overschreven.', 'hashtagmedia-toolkit' ) ) ); ?>"><?php esc_html_e( 'Importeren', 'hashtagmedia-toolkit' ); ?></button></p>
		</form>

		<p class="description"><?php echo esc_html( $skipped ); ?></p>
		<?php
	}
}
