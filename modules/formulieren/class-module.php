<?php
/**
 * Module Formulieren (Gravity Forms).
 *
 * Wat elke site hetzelfde nodig heeft: de honeypot altijd aan, de verzendknop
 * als echte <button> met de knopklasse van het thema, een ACF-keuzeveld met de
 * formulieren van de site en een testformulier met alle veldtypes.
 *
 * Opmaak doet Gravity Forms zelf (Orbital). Het thema geeft zijn kleuren door
 * met het filter gform_default_styles (zie THEMA-AFSPRAKEN.md); daarom heeft
 * deze module geen eigen CSS.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Formulieren;

use HashtagMedia\Toolkit\Admin_Page;
use HashtagMedia\Toolkit\Module as Base;

defined( 'ABSPATH' ) || exit;

/**
 * Honeypot, knop, ACF-formulierkeuze en testformulier.
 */
final class Module extends Base {

	/**
	 * Naam van het ACF-keuzeveld dat de toolkit vult met formulieren.
	 */
	private const ACF_FIELD = 'formulier';

	/**
	 * Titel van het testformulier (ook om een bestaand exemplaar te vinden).
	 */
	private const TEST_TITLE = 'HashtagMedia testformulier';

	/**
	 * Frontend en admin.
	 */
	public function boot(): void {
		add_filter( 'gform_form_post_get_meta', array( $this, 'force_honeypot' ) );
		add_filter( 'gform_submit_button', array( $this, 'submit_button' ) );
		add_filter( 'acf/prepare_field/name=' . self::ACF_FIELD, array( $this, 'form_choices' ) );
	}

	/**
	 * Alleen admin.
	 */
	public function admin(): void {
		add_action( 'admin_post_hm_toolkit_test_form', array( $this, 'handle_test_form' ) );
	}

	/**
	 * Honeypot bij elk formulier aan, ook als hij in Gravity Forms uitstaat.
	 *
	 * @param mixed $form Formulier.
	 * @return mixed
	 */
	public function force_honeypot( $form ) {
		if ( is_array( $form ) ) {
			$form['enableHoneypot'] = true;
		}
		return $form;
	}

	/**
	 * Verzendknop van <input type="submit"> naar <button type="submit">, met de
	 * knopklasse van het thema erbij. Een afbeeldingsknop blijft ongemoeid.
	 *
	 * @param string $html Knop-HTML van Gravity Forms.
	 * @return string
	 */
	public function submit_button( $html ) {
		$tag = new \WP_HTML_Tag_Processor( (string) $html );
		if ( ! $tag->next_tag( 'input' ) || 'submit' !== $tag->get_attribute( 'type' ) ) {
			return $html;
		}

		/**
		 * Knopklasse van het thema voor de verzendknop.
		 *
		 * @param string $class Standaard 'knop'.
		 */
		$tag->add_class( (string) apply_filters( 'hm_toolkit_form_button_class', 'knop' ) );

		$label = (string) $tag->get_attribute( 'value' );
		$attrs = '';
		foreach ( (array) $tag->get_attribute_names_with_prefix( '' ) as $name ) {
			if ( 'value' === $name ) {
				continue;
			}
			$value  = $tag->get_attribute( $name );
			$attrs .= true === $value ? ' ' . $name : sprintf( ' %s="%s"', $name, esc_attr( (string) $value ) );
		}

		return '<button' . $attrs . '>' . esc_html( $label ) . '</button>';
	}

	/**
	 * Vult een ACF-keuzeveld met de naam 'formulier' met de actieve formulieren.
	 * Draait alleen bij het tonen van het veld, niet in de veldgroep-editor.
	 *
	 * @param array|false $field ACF-veld.
	 * @return array|false
	 */
	public function form_choices( $field ) {
		if ( ! is_array( $field ) || 'select' !== ( $field['type'] ?? '' ) || ! class_exists( 'GFAPI' ) ) {
			return $field;
		}

		$field['choices'] = array();
		foreach ( \GFAPI::get_forms() as $form ) {
			$field['choices'][ (string) $form['id'] ] = $form['title'];
		}
		return $field;
	}

	/**
	 * Maakt het testformulier aan (of opent het als het al bestaat).
	 */
	public function handle_test_form(): void {
		if ( ! hm_toolkit_is_agency_user() ) {
			wp_die( esc_html__( 'Je hebt geen toegang tot deze actie.', 'hashtagmedia-toolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'hm_toolkit_test_form' );

		if ( ! class_exists( 'GFAPI' ) ) {
			$this->back( 'no-gf' );
		}

		$id = (int) \GFFormsModel::get_form_id( self::TEST_TITLE );
		if ( ! $id ) {
			$form = require $this->path( 'testformulier.php' );
			$id   = \GFAPI::add_form( $form );
			if ( is_wp_error( $id ) ) {
				$this->back( 'failed' );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=gf_edit_forms&id=' . (int) $id ) );
		exit;
	}

	/**
	 * Terug naar het tabblad met een melding.
	 *
	 * @param string $code Code van de melding.
	 */
	private function back( string $code ): void {
		wp_safe_redirect( Admin_Page::url( $this->slug(), array( 'hm-form' => $code ) ) );
		exit;
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
	 * Eigen formulier op het tabblad, geen knop Opslaan.
	 *
	 * @return bool
	 */
	public function tab_form(): bool {
		return false;
	}

	/**
	 * Tabblad Formulieren.
	 */
	public function render_tab(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen weergave van een vaste melding.
		$code     = isset( $_GET['hm-form'] ) ? sanitize_key( wp_unslash( $_GET['hm-form'] ) ) : '';
		$messages = array(
			'no-gf'  => __( 'Gravity Forms is niet actief.', 'hashtagmedia-toolkit' ),
			'failed' => __( 'Het testformulier kon niet worden aangemaakt.', 'hashtagmedia-toolkit' ),
		);
		if ( isset( $messages[ $code ] ) ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $messages[ $code ] ) );
		}
		?>
		<p><?php esc_html_e( 'Deze module werkt vanzelf op elk Gravity Forms-formulier:', 'hashtagmedia-toolkit' ); ?></p>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'De honeypot tegen spam staat altijd aan, ook als hij in de formulierinstellingen uitstaat.', 'hashtagmedia-toolkit' ); ?></li>
			<li><?php esc_html_e( 'De verzendknop is een echte knop met de knopklasse van het thema.', 'hashtagmedia-toolkit' ); ?></li>
			<li><?php esc_html_e( 'Een ACF-keuzeveld met de naam "formulier" toont automatisch alle formulieren van de site.', 'hashtagmedia-toolkit' ); ?></li>
		</ul>
		<p class="description"><?php esc_html_e( 'Kleuren, randen en afronding komen uit het thema; zie THEMA-AFSPRAKEN.md van de toolkit.', 'hashtagmedia-toolkit' ); ?></p>

		<h2><?php esc_html_e( 'Testformulier', 'hashtagmedia-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Een formulier met alle veldtypes, om de opmaak van het thema te controleren. Bestaat het al, dan wordt het geopend.', 'hashtagmedia-toolkit' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hm_toolkit_test_form">
			<?php wp_nonce_field( 'hm_toolkit_test_form' ); ?>
			<p><button type="submit" class="button"<?php disabled( ! class_exists( 'GFAPI' ) ); ?>><?php esc_html_e( 'Testformulier aanmaken', 'hashtagmedia-toolkit' ); ?></button></p>
		</form>
		<?php
	}
}
