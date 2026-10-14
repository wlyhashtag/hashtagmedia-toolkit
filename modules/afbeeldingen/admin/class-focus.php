<?php
/**
 * Focuspunt kiezen in de mediabibliotheek.
 *
 * Een extra veld bij de bijlagegegevens (werkt in het mediavenster én op het
 * bewerkscherm van een bijlage): klik op het belangrijkste deel van de
 * afbeelding; met pijltjestoetsen kan het ook. Opslaan gaat via WordPress'
 * eigen mechanisme voor bijlagevelden.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Afbeeldingen;

defined( 'ABSPATH' ) || exit;

/**
 * Veld, opslaan en scripts.
 */
final class Focus {

	/**
	 * De module.
	 *
	 * @var Module
	 */
	private Module $module;

	/**
	 * Constructor.
	 *
	 * @param Module $module Module.
	 */
	public function __construct( Module $module ) {
		$this->module = $module;
	}

	/**
	 * Hooks registreren.
	 */
	public function register(): void {
		add_filter( 'attachment_fields_to_edit', array( $this, 'field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save' ), 10, 2 );
		add_action( 'wp_enqueue_media', array( $this, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_on_attachment_screen' ) );
	}

	/**
	 * Veld toevoegen aan afbeeldingen.
	 *
	 * @param array    $fields Velden.
	 * @param \WP_Post $post   Bijlage.
	 * @return array
	 */
	public function field( $fields, $post ) {
		if ( ! is_array( $fields ) || ! $post instanceof \WP_Post || ! wp_attachment_is_image( $post->ID ) ) {
			return $fields;
		}

		$focus = Module::focus( $post->ID ) ?? array( 50.0, 50.0 );
		$x     = Module::number( $focus[0] );
		$y     = Module::number( $focus[1] );
		$src   = wp_get_attachment_image_url( $post->ID, 'medium' );

		$html = sprintf(
			'<div class="hm-focus">' .
				'<div class="hm-focus__frame" tabindex="0" role="application" aria-label="%1$s" aria-describedby="hm-focus-help-%2$d">' .
					'<img src="%3$s" alt="" draggable="false">' .
					'<span class="hm-focus__dot" style="left:%4$s%%;top:%5$s%%" aria-hidden="true"></span>' .
				'</div>' .
				'<p class="description" id="hm-focus-help-%2$d">%6$s</p>' .
				'<button type="button" class="button-link hm-focus__reset">%7$s</button> ' .
				'<span class="hm-focus__value" aria-live="polite">%4$s%% %5$s%%</span>' .
				'<input type="hidden" class="hm-focus__input" name="attachments[%2$d][hm_focus]" id="attachments-%2$d-hm_focus" value="%4$s,%5$s">' .
			'</div>',
			esc_attr__( 'Focuspunt kiezen', 'hashtagmedia-toolkit' ),
			(int) $post->ID,
			esc_url( (string) $src ),
			esc_attr( $x ),
			esc_attr( $y ),
			esc_html__( 'Klik op het belangrijkste deel van de afbeelding (of gebruik de pijltjestoetsen). Dat deel blijft altijd in beeld.', 'hashtagmedia-toolkit' ),
			esc_html__( 'Terug naar het midden', 'hashtagmedia-toolkit' )
		);

		$fields['hm_focus'] = array(
			'label' => __( 'Focuspunt', 'hashtagmedia-toolkit' ),
			'input' => 'html',
			'html'  => $html,
		);

		return $fields;
	}

	/**
	 * Focuspunt opslaan (mediavenster en bewerkscherm).
	 *
	 * @param array $post       Bijlagegegevens.
	 * @param array $attachment Ingevulde velden.
	 * @return array
	 */
	public function save( $post, $attachment ) {
		if ( ! is_array( $post ) || empty( $post['ID'] ) || ! is_array( $attachment ) || ! isset( $attachment['hm_focus'] ) ) {
			return $post;
		}

		$id = (int) $post['ID'];
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return $post;
		}

		$this->module->set_focus( $id, (string) $attachment['hm_focus'] );

		return $post;
	}

	/**
	 * Script en stijl voor het mediavenster.
	 */
	public function enqueue(): void {
		wp_enqueue_style( 'hm-toolkit-afbeeldingen-focus', $this->module->url( 'assets/focus.css' ), array(), HM_TOOLKIT_VERSION );
		wp_enqueue_script(
			'hm-toolkit-afbeeldingen-focus',
			$this->module->url( 'assets/focus.js' ),
			array( 'jquery' ),
			HM_TOOLKIT_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Ook op het bewerkscherm van een bijlage.
	 *
	 * @param string $hook_suffix Pagina.
	 */
	public function enqueue_on_attachment_screen( $hook_suffix ): void {
		if ( 'post.php' === $hook_suffix && 'attachment' === get_post_type() ) {
			$this->enqueue();
		}
	}
}
