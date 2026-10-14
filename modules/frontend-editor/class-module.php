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
 * hoogste niveau van een bericht of een ACF-optiepagina.
 *
 * Deel 2: lijsten (herhaler, flexibele inhoud) in een zijpaneel. Het thema
 * markeert een lijst met hm_list_attr() en elke rij met hm_row_attr(). Een
 * herhaler mag rijen toevoegen, dupliceren, verplaatsen en verwijderen; bij
 * flexibele inhoud verandert alleen de inhoud van bestaande secties. Een lijst
 * wordt als geheel opgeslagen; subvelden van een ander type blijven ongemoeid.
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
	 * Veldtypes die als lijst in het zijpaneel te bewerken zijn.
	 */
	private const LISTS = array( 'repeater', 'flexible_content' );

	/**
	 * Staan er op deze pagina velden die de gebruiker mag bewerken?
	 *
	 * @var bool
	 */
	private bool $has_fields = false;

	/**
	 * Lijsten op deze pagina die de gebruiker mag bewerken: veldsleutel => array( veld, doel ).
	 *
	 * @var array
	 */
	private array $lists = array();

	/**
	 * Frontend en admin.
	 */
	public function boot(): void {
		add_filter( 'hm_toolkit_field_attr', array( $this, 'field_attr' ), 10, 4 );
		add_filter( 'hm_toolkit_list_attr', array( $this, 'list_attr' ), 10, 3 );
		add_filter( 'hm_toolkit_row_attr', array( $this, 'row_attr' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_node' ), 90 );
		add_action( 'wp_footer', array( $this, 'enqueue' ), 5 );
		add_action( 'wp_head', array( $this, 'hide_button' ), 20 );
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
		if ( is_admin() || ! is_user_logged_in() || ! is_array( $field ) || empty( $field['key'] ) || ! in_array( $field['type'] ?? '', self::TYPES, true ) ) {
			return $attr;
		}

		// Subveld van een lijst die te bewerken is: alleen merken, bewerken gaat in het zijpaneel.
		if ( $is_sub ) {
			if ( ! isset( $this->lists[ $field['parent'] ?? '' ] ) ) {
				return $attr;
			}
			return array_merge( (array) $attr, array( 'data-hm-sub' => (string) $field['key'] ) );
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
	 * Markeert een lijst (herhaler of flexibele inhoud) als bewerkbaar voor wie dat mag.
	 *
	 * @param array      $attr    Attributen.
	 * @param array      $field   ACF-veld.
	 * @param int|string $post_id Bericht-ID, 'option' of false.
	 * @return array
	 */
	public function list_attr( $attr, $field, $post_id ) {
		if ( is_admin() || ! is_user_logged_in() || ! is_array( $field ) || empty( $field['key'] ) || ! in_array( $field['type'] ?? '', self::LISTS, true )
			|| ! function_exists( 'acf_get_field_group' ) || ! acf_get_field_group( $field['parent'] ?? 0 ) ) {
			return $attr;
		}

		$target = self::target( $post_id );
		if ( ! $target || ! self::can_edit( $field, $target ) ) {
			return $attr;
		}

		$this->has_fields             = true;
		$this->lists[ $field['key'] ] = array( $field, $target );
		return array_merge(
			(array) $attr,
			array(
				'data-hm-list'  => (string) $field['key'],
				'data-hm-post'  => (string) $target,
				'data-hm-label' => (string) ( $field['label'] ?? '' ),
				'tabindex'      => '0',
			)
		);
	}

	/**
	 * Markeert een rij van een lijst die te bewerken is.
	 *
	 * @param array $attr  Attributen.
	 * @param int   $index Rijnummer vanaf 1.
	 * @return array
	 */
	public function row_attr( $attr, $index ) {
		$loop = function_exists( 'acf_get_loop' ) ? acf_get_loop( 'active' ) : false;
		if ( $index > 0 && is_array( $loop ) && isset( $this->lists[ $loop['field']['key'] ?? '' ] ) ) {
			return array_merge( (array) $attr, array( 'data-hm-row' => (string) ( $index - 1 ) ) );
		}
		return $attr;
	}

	/**
	 * Gegevens van de lijsten voor het zijpaneel: velden per soort rij en de huidige rijen.
	 *
	 * @return array
	 */
	private function lists_data(): array {
		$out = array();
		foreach ( $this->lists as $key => list( $field, $target ) ) {
			$layouts = array();
			foreach ( self::layouts( $field ) as $name => $layout ) {
				$layouts[ $name ] = array(
					'label'  => $layout['label'],
					'fields' => array(),
				);
				foreach ( $layout['sub_fields'] as $sub ) {
					if ( in_array( $sub['type'] ?? '', self::TYPES, true ) ) {
						$layouts[ $name ]['fields'][] = array(
							'key'   => (string) $sub['key'],
							'label' => (string) ( $sub['label'] ?? '' ),
							'type'  => (string) $sub['type'],
						);
					}
				}
			}

			$rows = array();
			foreach ( self::rows( $field, $target ) as $row ) {
				$name   = 'flexible_content' === $field['type'] ? (string) ( $row['acf_fc_layout'] ?? '' ) : '';
				$values = array();
				$images = array();
				foreach ( $layouts[ $name ]['fields'] ?? array() as $sub ) {
					$raw = self::cell( $row, $sub );
					if ( 'image' === $sub['type'] ) {
						$id = hm_image_id( $raw );
						if ( $id ) {
							$images[ $sub['key'] ] = array(
								'url'  => (string) wp_get_attachment_image_url( $id, 'thumbnail' ),
								'name' => basename( (string) get_attached_file( $id ) ),
							);
						}
						$raw = $id;
					} elseif ( 'link' === $sub['type'] ) {
						$raw = array(
							'title'  => (string) ( is_array( $raw ) ? ( $raw['title'] ?? '' ) : '' ),
							'url'    => (string) ( is_array( $raw ) ? ( $raw['url'] ?? '' ) : '' ),
							'target' => ( is_array( $raw ) && '_blank' === ( $raw['target'] ?? '' ) ) ? '_blank' : '',
						);
					} elseif ( 'wysiwyg' === $sub['type'] ) {
						// Zoals de pagina het toont (alinea's), zodat het paneel niets platslaat.
						$raw = wp_kses_post( (string) apply_filters( 'acf_the_content', is_scalar( $raw ) ? (string) $raw : '' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filter van ACF zelf.
					} else {
						$raw = is_scalar( $raw ) ? (string) $raw : '';
					}
					$values[ $sub['key'] ] = $raw;
				}
				$rows[] = array(
					'layout' => $name,
					'values' => (object) $values,
					'images' => (object) $images,
				);
			}

			$out[] = array(
				'key'     => (string) $key,
				'post'    => (string) $target,
				'label'   => (string) ( $field['label'] ?? '' ),
				'flex'    => 'flexible_content' === $field['type'],
				'min'     => (int) ( $field['min'] ?? 0 ),
				'max'     => (int) ( $field['max'] ?? 0 ),
				'layouts' => (object) $layouts,
				'rows'    => $rows,
			);
		}
		return $out;
	}

	/**
	 * Soorten rij van een lijst: naam => array( label, sub_fields ). Een herhaler heeft er één, zonder naam.
	 *
	 * @param array $field Herhaler of flexibele inhoud.
	 * @return array
	 */
	private static function layouts( array $field ): array {
		if ( 'flexible_content' !== $field['type'] ) {
			return array(
				'' => array(
					'label'      => '',
					'sub_fields' => (array) ( $field['sub_fields'] ?? array() ),
				),
			);
		}
		$out = array();
		foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
			$out[ (string) $layout['name'] ] = array(
				'label'      => (string) ( $layout['label'] ?? '' ),
				'sub_fields' => (array) ( $layout['sub_fields'] ?? array() ),
			);
		}
		return $out;
	}

	/**
	 * Opgeslagen rijen van een lijst (ruwe waarden).
	 *
	 * @param array      $field  Herhaler of flexibele inhoud.
	 * @param int|string $target Bericht-ID of 'option'.
	 * @return array
	 */
	private static function rows( array $field, $target ): array {
		$rows = get_field( $field['key'], $target, false );
		return is_array( $rows ) ? array_values( array_filter( $rows, 'is_array' ) ) : array();
	}

	/**
	 * Waarde van een subveld in een rij; ACF geeft de rij met de veldsleutel of de veldnaam.
	 *
	 * @param array $row Rij.
	 * @param array $sub Subveld.
	 * @return mixed
	 */
	private static function cell( array $row, array $sub ) {
		return $row[ $sub['key'] ] ?? ( $row[ $sub['name'] ?? '' ] ?? '' );
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
	 * Knop "Visueel bewerken" in de adminbalk. Een thema met wp_body_open() laat de
	 * adminbalk tekenen voordat de velden bekend zijn; daarom staat de knop er altijd
	 * en blijft hij verborgen tot de pagina bewerkbare velden blijkt te hebben
	 * (page.css toont hem dan).
	 *
	 * @param \WP_Admin_Bar $admin_bar Adminbalk.
	 */
	public function admin_bar_node( \WP_Admin_Bar $admin_bar ): void {
		if ( is_admin() ) {
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
	 * Verbergt de knop tot page.css hem toont (zie admin_bar_node()).
	 */
	public function hide_button(): void {
		if ( is_admin_bar_showing() ) {
			echo '<style>#wp-admin-bar-hm-toolkit-bewerken{display:none}</style>' . "\n";
		}
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
				'lists'  => $this->lists_data(),
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
		$focus = array();
		foreach ( (array) $request->get_param( 'changes' ) as $change ) {
			$change = is_array( $change ) ? $change : array();

			// Focuspunt van een afbeelding: opslaan doet de module Afbeeldingen (filter hm_toolkit_save_focus).
			if ( 'focus' === ( $change['key'] ?? '' ) ) {
				$id    = absint( $change['post'] ?? 0 );
				$value = is_scalar( $change['value'] ?? null ) ? (string) $change['value'] : '';
				if ( ! has_filter( 'hm_toolkit_save_focus' ) || ! wp_attachment_is_image( $id ) || ! current_user_can( 'edit_post', $id ) || ! preg_match( '/^\d{1,3}(\.\d+)?,\d{1,3}(\.\d+)?$/', $value ) ) {
					return new \WP_Error( 'hm_toolkit_focus', __( 'Het focuspunt kon niet worden opgeslagen. Er is niets opgeslagen.', 'hashtagmedia-toolkit' ), array( 'status' => 403 ) );
				}
				$focus[ $id ] = $value;
				continue;
			}

			$field  = acf_get_field( sanitize_text_field( (string) ( $change['key'] ?? '' ) ) );
			$target = 'option' === ( $change['post'] ?? '' ) ? 'option' : absint( $change['post'] ?? 0 );

			$is_list = is_array( $field ) && in_array( $field['type'], self::LISTS, true );

			if ( ! is_array( $field ) || ( ! $is_list && ! in_array( $field['type'], self::TYPES, true ) ) || ! acf_get_field_group( $field['parent'] ?? 0 )
				|| ! $target || ( 'option' !== $target && ! get_post( $target ) ) || ! self::can_edit( $field, $target ) ) {
				return new \WP_Error( 'hm_toolkit_veld', __( 'Je mag een van deze velden niet bewerken. Er is niets opgeslagen.', 'hashtagmedia-toolkit' ), array( 'status' => 403 ) );
			}

			$value = $is_list ? self::sanitize_list( $field, $target, $change['value'] ?? null ) : self::sanitize_value( $field['type'], $change['value'] ?? null );
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
		foreach ( $focus as $id => $value ) {
			apply_filters( 'hm_toolkit_save_focus', false, $id, $value ); // Leegt zelf de cache als het punt verandert.
		}

		return rest_ensure_response(
			array(
				'saved' => count( $clean ) + count( $focus ),
				'cache' => self::purge_cache( array_unique( array_column( $clean, 1 ) ) ),
			)
		);
	}

	/**
	 * Saneert en bouwt de rijen van een lijst. Bij flexibele inhoud blijven aantal,
	 * volgorde en soort van de rijen zoals ze zijn. Bij een herhaler geeft "from"
	 * aan uit welke bestaande rij een rij komt (leeg bij een nieuwe rij); daaruit
	 * komen de waarden van subvelden die de editor niet kent.
	 *
	 * @param array      $field  Herhaler of flexibele inhoud.
	 * @param int|string $target Bericht-ID of 'option'.
	 * @param mixed      $value  Waarde uit de editor: array( 'rows' => array( array( 'from', 'values' ) ) ).
	 * @return array|null Null als de waarde niet geldig is.
	 */
	private static function sanitize_list( array $field, $target, $value ): ?array {
		if ( ! is_array( $value ) || ! is_array( $value['rows'] ?? null ) ) {
			return null;
		}

		$rows = array_values( $value['rows'] );
		$old  = self::rows( $field, $target );
		$flex = 'flexible_content' === $field['type'];
		$min  = (int) ( $field['min'] ?? 0 );
		$max  = (int) ( $field['max'] ?? 0 );
		if ( count( $rows ) > 200 || ( $max && count( $rows ) > $max ) || ( $min && count( $rows ) < min( $min, count( $old ) ) ) || ( $flex && count( $rows ) !== count( $old ) ) ) {
			return null;
		}

		$layouts = self::layouts( $field );
		$out     = array();
		foreach ( $rows as $i => $row ) {
			$from = is_array( $row ) && isset( $row['from'] ) && is_numeric( $row['from'] ) ? (int) $row['from'] : null;
			if ( ! is_array( $row ) || ( null !== $from && ! isset( $old[ $from ] ) ) || ( $flex && $from !== $i ) ) {
				return null;
			}

			$name = $flex ? (string) ( $old[ $i ]['acf_fc_layout'] ?? '' ) : '';
			if ( ! isset( $layouts[ $name ] ) ) {
				$out[] = $old[ $i ]; // Soort rij bestaat niet meer: niet aanraken.
				continue;
			}

			$vals = is_array( $row['values'] ?? null ) ? $row['values'] : array();
			$new  = $flex ? array( 'acf_fc_layout' => $name ) : array();
			foreach ( $layouts[ $name ]['sub_fields'] as $sub ) {
				$k = (string) $sub['key'];
				if ( ! in_array( $sub['type'], self::TYPES, true ) ) {
					if ( null !== $from ) {
						$new[ $k ] = self::cell( $old[ $from ], $sub ); // Onbekend type: ongemoeid.
					}
					continue;
				}
				if ( ! isset( $vals[ $k ] ) ) {
					$new[ $k ] = null !== $from ? self::cell( $old[ $from ], $sub ) : '';
				} elseif ( 'image' === $sub['type'] && in_array( $vals[ $k ], array( '', 0, '0' ), true ) ) {
					$new[ $k ] = ''; // Afbeelding leeg gelaten of gewist.
				} else {
					$clean = self::sanitize_value( $sub['type'], $vals[ $k ] );
					if ( null === $clean ) {
						return null;
					}
					$new[ $k ] = $clean;
				}
			}
			$out[] = $new;
		}
		return $out;
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
