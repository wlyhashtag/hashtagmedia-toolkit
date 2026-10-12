<?php
/**
 * Module Basisbeveiliging.
 *
 * Alleen wat Wordfence niet standaard regelt: XML-RPC volledig dicht, de
 * bestandseditors voor thema's en plugins weg, en de WordPress-versie niet
 * meer zichtbaar in de broncode. Loginbeveiliging, 2FA en firewall blijven
 * bij Wordfence.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Beveiliging;

use HashtagMedia\Toolkit\Module as Base;

defined( 'ABSPATH' ) || exit;

/**
 * XML-RPC, bestandseditors en versienummer.
 */
final class Module extends Base {

	/**
	 * Rechten van de bestandseditors.
	 */
	private const EDITOR_CAPS = array( 'edit_themes', 'edit_plugins', 'edit_files' );

	/**
	 * Standaardwaarden.
	 *
	 * @return array
	 */
	public function defaults(): array {
		return array(
			'xmlrpc'       => true,
			'file_edit'    => true,
			'hide_version' => true,
		);
	}

	/**
	 * Frontend, admin, REST en XML-RPC.
	 */
	public function boot(): void {
		if ( $this->get( 'xmlrpc' ) ) {
			$this->disable_xmlrpc();
		}

		if ( $this->get( 'file_edit' ) ) {
			add_filter( 'map_meta_cap', array( $this, 'block_file_editors' ), 10, 2 );
		}

		if ( $this->get( 'hide_version' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
			add_filter( 'style_loader_src', array( $this, 'hide_version_in_url' ), 9999 );
			add_filter( 'script_loader_src', array( $this, 'hide_version_in_url' ), 9999 );
		}
	}

	/**
	 * XML-RPC volledig uit: verzoeken naar xmlrpc.php krijgen 403, methodes
	 * en pingbacks verdwijnen, en de verwijzingen in de head en headers gaan weg.
	 */
	private function disable_xmlrpc(): void {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			status_header( 403 );
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'XML-RPC is uitgeschakeld.';
			exit;
		}

		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array', PHP_INT_MAX );
		add_filter( 'pings_open', '__return_false', 20 );
		add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
		remove_action( 'wp_head', 'rsd_link' );
	}

	/**
	 * X-Pingback-header weghalen.
	 *
	 * @param array $headers Headers.
	 * @return array
	 */
	public function remove_pingback_header( $headers ) {
		if ( is_array( $headers ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	/**
	 * Thema- en plugin-editor voor niemand beschikbaar (ook niet voor
	 * beheerders), net als DISALLOW_FILE_EDIT in wp-config.php.
	 *
	 * @param string[] $caps Benodigde rechten.
	 * @param string   $cap  Gevraagd recht.
	 * @return string[]
	 */
	public function block_file_editors( $caps, $cap ) {
		if ( in_array( $cap, self::EDITOR_CAPS, true ) ) {
			$caps[] = 'do_not_allow';
		}
		return $caps;
	}

	/**
	 * Versienummer van WordPress in ?ver= vervangen door een korte hash, zodat
	 * de cache bij een update nog steeds ververst maar de versie niet te lezen is.
	 * Versies van thema's en plugins blijven ongemoeid.
	 *
	 * @param string $src URL.
	 * @return string
	 */
	public function hide_version_in_url( $src ) {
		global $wp_version;

		if ( ! is_string( $src ) || false === strpos( $src, 'ver=' ) ) {
			return $src;
		}

		$query = (string) wp_parse_url( $src, PHP_URL_QUERY );
		parse_str( $query, $args );
		if ( isset( $args['ver'] ) && (string) $args['ver'] === (string) $wp_version ) {
			$src = add_query_arg( 'ver', substr( wp_hash( 'hm-toolkit-ver-' . $wp_version ), 0, 8 ), $src );
		}

		return $src;
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
	 * Tabblad Basisbeveiliging.
	 */
	public function render_tab(): void {
		?>
		<p><?php esc_html_e( 'Loginbeveiliging, tweestapsverificatie en de firewall regelt Wordfence. Deze module doet alleen wat daarnaast nodig is.', 'hashtagmedia-toolkit' ); ?></p>
		<table class="form-table" role="presentation">
			<?php
			$this->checkbox_row( 'xmlrpc', __( 'XML-RPC', 'hashtagmedia-toolkit' ), __( 'XML-RPC en pingbacks volledig uitzetten', 'hashtagmedia-toolkit' ), __( 'xmlrpc.php geeft dan een 403. Niet nodig voor ManageWP, wel voor de oude WordPress-app en Jetpack.', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'file_edit', __( 'Bestandseditors', 'hashtagmedia-toolkit' ), __( 'Thema- en plugin-editor uitzetten', 'hashtagmedia-toolkit' ), __( 'Ook voor beheerders. Code wijzig je via git en een nieuwe versie, niet in de browser.', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'hide_version', __( 'WordPress-versie', 'hashtagmedia-toolkit' ), __( 'Versienummer verbergen in de broncode en feeds', 'hashtagmedia-toolkit' ), __( 'Het bestand readme.html van WordPress toont de versie nog wel; dat kan alleen de server blokkeren.', 'hashtagmedia-toolkit' ) );
			?>
		</table>
		<?php
	}

	/**
	 * Saneert het tabblad.
	 *
	 * @param array $input Invoer.
	 * @return array
	 */
	public function sanitize( array $input ): array {
		return $this->sanitize_checkboxes( $input, array_keys( $this->defaults() ) );
	}
}
