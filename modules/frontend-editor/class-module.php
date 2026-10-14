<?php
/**
 * Module Frontend-editor.
 *
 * Velden die het thema toont met hm_field() worden voor wie ze mag bewerken
 * gemarkeerd met data-attributen. Een knop in de adminbalk zet de bewerkmodus
 * aan (assets/editor.js); Publiceren stuurt alle wijzigingen in één keer naar
 * de REST-route hm-toolkit/v1/velden. Daar wordt elk veld opnieuw gecontroleerd:
 * bestaat het, mag de gebruiker het bewerken, en is de waarde geldig. Pas als
 * alles klopt wordt er opgeslagen en de cache van WP Rocket geleegd.
 *
 * Deel 1: tekst, tekstvak, teksteditor, e-mail, URL, afbeelding en link op het
 * hoogste niveau van een bericht of een ACF-optiepagina. Subvelden (herhalers,
 * flexibele inhoud) volgen met het zijpaneel.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\FrontendEditor;

use HashtagMedia\Toolkit\Module as Base;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend-editor.
 */
final class Module extends Base {

	/**
	 * Veldtypes die inline te bewerken zijn.
	 */
	private const TYPES = array( 'text', 'textarea', 'email', 'url', 'wysiwyg', 'image', 'link' );

	/**
	 * Staan er op deze pagina velden die de gebruiker mag bewerken?
	 *
	 * @var bool
	 */
	private bool $has_fields = false;

	/**
	 * Frontend en admin.
	 */
	public function boot(): void {
		add_filter( 'hm_toolkit_field_attr', array( $this, 'field_attr' ), 10, 4 );
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_node' ), 90 );
		add_action( 'wp_footer', array( $this, 'enqueue' ), 5 );
	}

	/**
	 * Markeert een veld als bewerkbaar voor wie dat mag.
	 *
	 * @param array      $attr    Attributen.
	 * @param array      $field   ACF-veld.
	 * @param int|string $post_id Bericht-ID, 'option' of false.
	 * @param bool       $is_sub  Subveld.
	 * @return array
	 */
	public function field_attr( $attr, $field, $post_id, $is_sub ) {
		if ( $is_sub || is_admin() || ! is_user_logged_in() || ! is_array( $field ) || empty( $field['key'] ) || ! in_array( $field['type'] ?? '', self::TYPES, true ) ) {
			return $attr;
		}

		$target = self::target( $post_id );
		if ( ! $target || ! self::can_edit( $field, $target ) ) {
			return $attr;
		}

		$this->has_fields = true;
		$attr             = array_merge(
			(array) $attr,
			array(
				'data-hm-field' => (string) $field['key'],
				'data-hm-post'  => (string) $target,
				'data-hm-type'  => (string) $field['type'],
				'data-hm-label' => (string) ( $field['label'] ?? '' ),
			)
		);
		if ( 'image' === $field['type'] ) {
			$attr['data-hm-value'] = (string) hm_image_id( $field['value'] ?? 0 );
			$attr['tabindex']      = '0';
		}
		return $attr;
	}

	/**
	 * Bericht-ID of 'option' waar het veld bij hoort; 0 als dat niet ondersteund is
	 * (termen, gebruikers, optiepagina's met een eigen post_id).
	 *
	 * @param mixed $post_id Bericht-ID, 'option' of false (huidige).
	 * @return int|string
	 */
	private static function target( $post_id ) {
		$id = function_exists( 'acf_get_valid_post_id' ) ? acf_get_valid_post_id( $post_id ) : 0;
		if ( 'option' === $id || 'options' === $id ) {
			return 'option';
		}
		return is_numeric( $id ) ? (int) $id : 0;
	}

	/**
	 * Mag de huidige gebruiker dit veld bewerken?
	 *
	 * @param array      $field  ACF-veld.
	 * @param int|string $target Bericht-ID of 'option'.
	 * @return bool
	 */
	private static function can_edit( array $field, $target ): bool {
		if ( 'option' !== $target ) {
			return current_user_can( 'edit_post', (int) $target );
		}

		// Optieveld: het recht van een optiepagina waar de veldgroep op staat.
		$group = function_exists( 'acf_get_field_group' ) ? acf_get_field_group( $field['parent'] ?? 0 ) : false;
		foreach ( (array) ( $group['location'] ?? array() ) as $rules ) {
			foreach ( (array) $rules as $rule ) {
				if ( 'options_page' !== ( $rule['param'] ?? '' ) || '==' !== ( $rule['operator'] ?? '' ) || ! function_exists( 'acf_get_options_page' ) ) {
					continue;
				}
				$page = acf_get_options_page( (string) $rule['value'] );
				if ( is_array( $page ) && current_user_can( (string) ( $page['capability'] ?? 'edit_posts' ) ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Knop "Visueel bewerken" in de adminbalk, alleen als er iets te bewerken is.
	 *
	 * @param \WP_Admin_Bar $admin_bar Adminbalk.
	 */
	public function admin_bar_node( \WP_Admin_Bar $admin_bar ): void {
		if ( is_admin() || ! $this->has_fields ) {
			return;
		}
		$admin_bar->add_node(
			array(
				'id'    => 'hm-toolkit-bewerken',
				'title' => '<span class="ab-icon dashicons dashicons-admin-customizer" aria-hidden="true"></span>' . esc_html__( 'Visueel bewerken', 'hashtagmedia-toolkit' ),
				'href'  => '#',
			)
		);
	}

	/**
	 * Scripts en stijlen, alleen als er velden te bewerken zijn.
	 */
	public function enqueue(): void {
		if ( ! $this->has_fields ) {
			return;
		}

		wp_enqueue_media();
		// De design tokens van WordPress 7 (--wpds-*), zodat de editor de kleuren van WordPress volgt.
		$deps = wp_style_is( 'wp-theme', 'registered' ) ? array( 'dashicons', 'wp-theme' ) : array( 'dashicons' );
		wp_enqueue_style( 'hm-toolkit-editor', $this->url( 'assets/page.css' ), $deps, HM_TOOLKIT_VERSION );
		wp_enqueue_script( 'hm-toolkit-editor', $this->url( 'assets/editor.js' ), array( 'media-views' ), HM_TOOLKIT_VERSION, true );
		wp_localize_script(
			'hm-toolkit-editor',
			'hmToolkitEditor',
			array(
				'rest'   => esc_url_raw( rest_url( 'hm-toolkit/v1/velden' ) ),
				'search' => esc_url_raw( rest_url( 'wp/v2/search' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'ui'     => $this->url( 'assets/ui.css' ) . '?ver=' . rawurlencode( HM_TOOLKIT_VERSION ),
				'title'  => is_singular() ? get_the_title( get_queried_object_id() ) : wp_get_document_title(),
				'focus'  => has_filter( 'hm_toolkit_image_focus' ) ? 1 : 0,
			)
		);
	}

	/**
	 * REST-route om te publiceren.
	 */
	public function register_route(): void {
		register_rest_route(
			'hm-toolkit/v1',
			'/velden',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'publish' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'changes' => array(
						'type'     => 'array',
						'required' => true,
						'minItems' => 1,
						'maxItems' => 200,
					),
				),
			)
		);
	}

	/**
	 * Publiceert alle wijzigingen. Eerst wordt alles gecontroleerd; bij één fout
	 * wordt niets opgeslagen.
	 *
	 * @param \WP_REST_Request $request Verzoek.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function publish( \WP_REST_Request $request ) {
		if ( ! function_exists( 'acf_get_field' ) ) {
			return new \WP_Error( 'hm_toolkit_acf', __( 'ACF is niet actief.', 'hashtagmedia-toolkit' ), array( 'status' => 500 ) );
		}

		$clean = array();
		foreach ( (array) $request->get_param( 'changes' ) as $change ) {
			$change = is_array( $change ) ? $change : array();
			$field  = acf_get_field( sanitize_text_field( (string) ( $change['key'] ?? '' ) ) );
			$target = 'option' === ( $change['post'] ?? '' ) ? 'option' : absint( $change['post'] ?? 0 );

			if ( ! is_array( $field ) || ! in_array( $field['type'], self::TYPES, true ) || ! acf_get_field_group( $field['parent'] ?? 0 )
				|| ! $target || ( 'option' !== $target && ! get_post( $target ) ) || ! self::can_edit( $field, $target ) ) {
				return new \WP_Error( 'hm_toolkit_veld', __( 'Je mag een van deze velden niet bewerken. Er is niets opgeslagen.', 'hashtagmedia-toolkit' ), array( 'status' => 403 ) );
			}

			$value = self::sanitize_value( $field['type'], $change['value'] ?? null );
			if ( null === $value ) {
				return new \WP_Error(
					'hm_toolkit_waarde',
					/* translators: %s: naam van het veld. */
					sprintf( __( 'De waarde van "%s" klopt niet. Er is niets opgeslagen.', 'hashtagmedia-toolkit' ), $field['label'] ),
					array( 'status' => 400 )
				);
			}

			$clean[] = array( $field['key'], $target, $value );
		}

		foreach ( $clean as list( $key, $target, $value ) ) {
			update_field( $key, wp_slash( $value ), $target ); // ACF verwacht geslashte waarden, net als $_POST.
		}

		return rest_ensure_response(
			array(
				'saved' => count( $clean ),
				'cache' => self::purge_cache( array_unique( array_column( $clean, 1 ) ) ),
			)
		);
	}

	/**
	 * Saneert een waarde per veldtype.
	 *
	 * @param string $type  Veldtype.
	 * @param mixed  $value Waarde uit de editor.
	 * @return mixed Null als de waarde niet geldig is.
	 */
	private static function sanitize_value( string $type, $value ) {
		if ( 'link' === $type ) {
			if ( ! is_array( $value ) ) {
				return null;
			}
			return array(
				'title'  => sanitize_text_field( (string) ( $value['title'] ?? '' ) ),
				'url'    => esc_url_raw( (string) ( $value['url'] ?? '' ) ),
				'target' => '_blank' === ( $value['target'] ?? '' ) ? '_blank' : '',
			);
		}

		if ( ! is_scalar( $value ) ) {
			return null;
		}

		switch ( $type ) {
			case 'image':
				$id = absint( $value );
				return wp_attachment_is_image( $id ) ? $id : null;
			case 'wysiwyg':
				return wp_kses_post( (string) $value );
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'email':
				$email = sanitize_email( (string) $value );
				return ( '' === trim( (string) $value ) || '' !== $email ) ? $email : null;
			case 'url':
				return esc_url_raw( (string) $value );
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Cache van WP Rocket legen: de gewijzigde berichten, of alles bij een optieveld.
	 *
	 * @param array $targets Bericht-ID's en/of 'option'.
	 * @return bool Is er cache geleegd?
	 */
	private static function purge_cache( array $targets ): bool {
		if ( ! function_exists( 'rocket_clean_post' ) || ! function_exists( 'rocket_clean_domain' ) ) {
			return false;
		}
		if ( in_array( 'option', $targets, true ) ) {
			rocket_clean_domain();
			return true;
		}
		foreach ( $targets as $id ) {
			rocket_clean_post( (int) $id );
		}
		return true;
	}
}
