<?php
/**
 * Thema-helpers: altijd beschikbaar zolang de plugin actief is.
 *
 * Deze functies bestaan altijd, ook als de bijbehorende module uitstaat. Dan
 * doen ze het eenvoudigste dat werkt (bijv. een gewone WordPress-afbeelding),
 * zodat een thema nooit vastloopt. Modules vullen ze aan via filters.
 *
 * Thema's controleren of de plugin actief is met function_exists( 'hm_image' )
 * of defined( 'HM_TOOLKIT_VERSION' ).
 *
 * Afgesproken uitzondering: deze publieke helpers gebruiken het korte
 * voorvoegsel hm_ (zie README).
 *
 * @package HashtagMedia\Toolkit
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Afgesproken publieke thema-API met voorvoegsel hm_.

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hm_image_id' ) ) {
	/**
	 * ID van een afbeelding uit een ID, een ACF-afbeeldingsveld (array) of een WP_Post.
	 *
	 * @param mixed $image Afbeelding.
	 * @return int 0 als er geen geldige afbeelding is.
	 */
	function hm_image_id( $image ): int {
		if ( $image instanceof WP_Post ) {
			$image = $image->ID;
		} elseif ( is_array( $image ) ) {
			$image = $image['ID'] ?? ( $image['id'] ?? 0 );
		}
		$id = absint( $image );
		return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
	}
}

if ( ! function_exists( 'hm_get_image' ) ) {
	/**
	 * HTML van een afbeelding met srcset, sizes, breedte, hoogte en lazy loading.
	 *
	 * Argumenten:
	 * - size     Formaat (standaard 'large'; 'full' voor maximaal 2560 px).
	 * - sizes    Het sizes-attribuut, bijv. '(min-width: 1024px) 50vw, 100vw'.
	 * - class    Extra CSS-klassen.
	 * - alt      Alternatieve tekst; standaard die uit de mediabibliotheek.
	 * - loading  'lazy' (standaard) of 'eager' voor de eerste afbeelding boven de vouw.
	 * - priority true = fetchpriority="high" en geen lazy loading (bijv. hero).
	 * - fit      'cover' of 'contain': zet object-fit en het focuspunt als object-position.
	 * - attr     Extra HTML-attributen als array.
	 *
	 * @param mixed $image ID, ACF-afbeeldingsveld of WP_Post.
	 * @param array $args  Argumenten.
	 * @return string Lege string als er geen afbeelding is.
	 */
	function hm_get_image( $image, array $args = array() ): string {
		$id = hm_image_id( $image );
		if ( ! $id ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'size'     => 'large',
				'sizes'    => '',
				'class'    => '',
				'alt'      => null,
				'loading'  => 'lazy',
				'priority' => false,
				'fit'      => '',
				'attr'     => array(),
			)
		);

		$attr = (array) $args['attr'];
		if ( '' !== $args['class'] ) {
			$attr['class'] = trim( 'hm-image ' . $args['class'] );
		} else {
			$attr['class'] = 'hm-image';
		}
		if ( '' !== $args['sizes'] ) {
			$attr['sizes'] = $args['sizes'];
		}
		if ( null !== $args['alt'] ) {
			$attr['alt'] = (string) $args['alt'];
		}
		if ( $args['priority'] ) {
			$attr['loading']       = 'eager';
			$attr['fetchpriority'] = 'high';
		} else {
			$attr['loading'] = 'eager' === $args['loading'] ? 'eager' : 'lazy';
		}
		if ( in_array( $args['fit'], array( 'cover', 'contain' ), true ) ) {
			$style         = 'object-fit:' . $args['fit'] . ';object-position:' . hm_image_focus( $id ) . ';';
			$attr['style'] = isset( $attr['style'] ) ? $style . $attr['style'] : $style;
		}

		/**
		 * Attributen van hm_image() aanpassen (bijv. focuspunt toevoegen).
		 *
		 * @param array $attr Attributen.
		 * @param int   $id   Afbeelding.
		 * @param array $args Argumenten.
		 */
		$attr = (array) apply_filters( 'hm_toolkit_image_attr', $attr, $id, $args );

		return wp_get_attachment_image( $id, $args['size'], false, $attr );
	}
}

if ( ! function_exists( 'hm_image' ) ) {
	/**
	 * Toont een afbeelding; zie hm_get_image() voor de argumenten.
	 *
	 * @param mixed $image ID, ACF-afbeeldingsveld of WP_Post.
	 * @param array $args  Argumenten.
	 */
	function hm_image( $image, array $args = array() ): void {
		echo hm_get_image( $image, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Uitvoer van wp_get_attachment_image(), al ge-escapet.
	}
}

if ( ! function_exists( 'hm_form' ) ) {
	/**
	 * Toont een Gravity Forms-formulier zonder titel en beschrijving, met AJAX.
	 * Doet niets als Gravity Forms niet actief is of het formulier niet bestaat.
	 *
	 * @param mixed $form Formulier-ID, bijv. de waarde van het ACF-veld 'formulier'.
	 */
	function hm_form( $form ): void {
		$id = absint( is_array( $form ) ? ( $form['id'] ?? 0 ) : $form );
		if ( $id && function_exists( 'gravity_form' ) && GFAPI::form_id_exists( $id ) ) {
			gravity_form( $id, false, false, false, null, true );
		}
	}
}

if ( ! function_exists( 'hm_image_focus' ) ) {
	/**
	 * Focuspunt als CSS-waarde voor object-position of background-position.
	 *
	 * @param mixed $image ID, ACF-afbeeldingsveld of WP_Post.
	 * @return string Bijv. '30% 65%'; standaard '50% 50%'.
	 */
	function hm_image_focus( $image ): string {
		$id    = hm_image_id( $image );
		$focus = (string) apply_filters( 'hm_toolkit_image_focus', '50% 50%', $id );
		return preg_match( '/^\d{1,3}(\.\d+)?% \d{1,3}(\.\d+)?%$/', $focus ) ? $focus : '50% 50%';
	}
}

if ( ! function_exists( 'hm_get_field' ) ) {
	/**
	 * HTML van een ACF-veld, bewerkbaar in de frontend-editor.
	 *
	 * Tekst, tekstvak, e-mail en URL komen in een element (standaard <span>),
	 * teksteditor in een <div>, een afbeelding via hm_get_image() en een link
	 * als <a>. Andere veldtypes geven een lege string; gebruik daarvoor
	 * get_field(). Binnen een have_rows()-lus wordt het subveld gebruikt.
	 *
	 * Argumenten:
	 * - post_id  Bericht-ID of 'option' (optiepagina); standaard de huidige pagina.
	 * - tag      Element om tekst heen, bijv. 'h1' (geen extra <span> nodig).
	 * - class    CSS-klassen op het element, de <a> of de afbeelding.
	 * - Voor afbeeldingen ook alle argumenten van hm_get_image().
	 *
	 * @param string $name Veldnaam.
	 * @param array  $args Argumenten.
	 * @return string
	 */
	function hm_get_field( string $name, array $args = array() ): string {
		if ( ! function_exists( 'get_field_object' ) ) {
			return '';
		}

		$post_id = $args['post_id'] ?? false;
		$is_sub  = function_exists( 'get_row' ) && false !== get_row();
		$field   = $is_sub ? get_sub_field_object( $name, false ) : false;
		if ( ! $field ) {
			$is_sub = false;
			$field  = get_field_object( $name, $post_id, false );
		}
		if ( ! $field && function_exists( 'acf_get_field' ) ) {
			// Nog nooit opgeslagen (bijv. een nieuwe pagina): ACF vindt het veld dan niet via de pagina.
			$field = acf_get_field( $name );
			if ( is_array( $field ) ) {
				$field['value'] = get_field( $name, $post_id, false );
			}
		}
		if ( ! is_array( $field ) ) {
			return '';
		}

		/**
		 * Extra attributen op het veld, bijv. voor de frontend-editor.
		 *
		 * @param array      $attr    Attributen.
		 * @param array      $field   ACF-veld met ruwe waarde.
		 * @param int|string $post_id Bericht-ID, 'option' of false (huidige).
		 * @param bool       $is_sub  Subveld van een herhaler of flexibele inhoud.
		 */
		$attr  = (array) apply_filters( 'hm_toolkit_field_attr', array(), $field, $post_id, $is_sub );
		$value = $field['value'];
		$class = (string) ( $args['class'] ?? '' );

		switch ( $field['type'] ) {
			case 'image':
				if ( ! hm_image_id( $value ) ) {
					// Lege afbeelding: alleen in de editor een vlak om op te klikken.
					return $attr ? '<span class="hm-field-empty"' . hm_toolkit_attr_string( $attr ) . '></span>' : '';
				}
				$args['attr'] = array_merge( (array) ( $args['attr'] ?? array() ), $attr );
				return hm_get_image( $value, $args );

			case 'link':
				$link = is_array( $value ) ? $value : array();
				$url  = (string) ( $link['url'] ?? '' );
				if ( '' === $url && ! $attr ) {
					return '';
				}
				$target = '_blank' === ( $link['target'] ?? '' ) ? '_blank' : '';
				$a      = array(
					'href'   => '' === $url ? '#' : esc_url( $url ),
					'class'  => $class,
					'target' => $target,
					'rel'    => $target ? 'noopener' : '',
				);
				return '<a' . hm_toolkit_attr_string( array_merge( array_filter( $a, 'strlen' ), $attr ) ) . '>' . esc_html( (string) ( $link['title'] ?? '' ) ) . '</a>';

			case 'wysiwyg':
				$html = wp_kses_post( (string) apply_filters( 'acf_the_content', (string) $value ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filter van ACF zelf (wpautop, shortcodes).
				$tag  = $args['tag'] ?? 'div';
				break;

			case 'textarea':
				$html = esc_html( (string) $value );
				if ( 'br' === ( $field['new_lines'] ?? '' ) ) {
					$html = nl2br( $html );
				} elseif ( 'wpautop' === ( $field['new_lines'] ?? '' ) ) {
					$html = wpautop( $html );
				}
				$tag = $args['tag'] ?? 'span';
				break;

			case 'text':
			case 'email':
			case 'url':
				$html = esc_html( is_scalar( $value ) ? (string) $value : '' );
				$tag  = $args['tag'] ?? 'span';
				break;

			default:
				return '';
		}

		if ( '' !== $class ) {
			$attr['class'] = trim( $class . ' ' . ( $attr['class'] ?? '' ) );
		}
		$tag = tag_escape( (string) $tag );
		return '<' . $tag . hm_toolkit_attr_string( $attr ) . '>' . $html . '</' . $tag . '>';
	}
}

if ( ! function_exists( 'hm_field' ) ) {
	/**
	 * Toont een ACF-veld; zie hm_get_field() voor de argumenten.
	 *
	 * @param string $name Veldnaam.
	 * @param array  $args Argumenten.
	 */
	function hm_field( string $name, array $args = array() ): void {
		echo hm_get_field( $name, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Opgebouwd met esc_html(), esc_url(), wp_kses_post() en hm_toolkit_attr_string().
	}
}

if ( ! function_exists( 'hm_list_attr' ) ) {
	/**
	 * Attributen voor het element rond een herhaler of flexibele inhoud, zodat
	 * de frontend-editor er een zijpaneel voor kan openen. Wie niet mag
	 * bewerken krijgt niets. Gebruik: <div <?php hm_list_attr( 'behandelingen' ); ?>>
	 *
	 * Standaard wijzigt de klant alleen de inhoud van bestaande rijen. Met
	 * 'rijen' => true mag hij bij een herhaler ook rijen toevoegen, dupliceren,
	 * verplaatsen en verwijderen (bijv. een FAQ); alleen als de opmaak elk
	 * aantal rijen aankan.
	 *
	 * @param string $name Veldnaam van de herhaler of flexibele inhoud.
	 * @param array  $args Argumenten: post_id (bericht-ID of 'option'), rijen (bool).
	 */
	function hm_list_attr( string $name, array $args = array() ): void {
		$post_id = $args['post_id'] ?? false;
		$field   = function_exists( 'get_field_object' ) ? get_field_object( $name, $post_id, false ) : false;
		if ( ! $field && function_exists( 'acf_get_field' ) ) {
			$field = acf_get_field( $name ); // Nog nooit opgeslagen.
		}
		if ( is_array( $field ) ) {
			/**
			 * Extra attributen op een lijst, bijv. voor de frontend-editor.
			 *
			 * @param array      $attr    Attributen.
			 * @param array      $field   ACF-veld (herhaler of flexibele inhoud).
			 * @param int|string $post_id Bericht-ID, 'option' of false (huidige).
			 * @param array      $args    Argumenten van hm_list_attr().
			 */
			echo hm_toolkit_attr_string( (array) apply_filters( 'hm_toolkit_list_attr', array(), $field, $post_id, $args ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hm_toolkit_attr_string() escapet.
		}
	}
}

if ( ! function_exists( 'hm_row_attr' ) ) {
	/**
	 * Attributen voor het element van één rij, binnen have_rows() / the_row().
	 * Gebruik: <div <?php hm_row_attr(); ?>>
	 */
	function hm_row_attr(): void {
		/**
		 * Extra attributen op een rij, bijv. voor de frontend-editor.
		 *
		 * @param array $attr  Attributen.
		 * @param int   $index Rijnummer, vanaf 1 (0 buiten een lus).
		 */
		echo hm_toolkit_attr_string( (array) apply_filters( 'hm_toolkit_row_attr', array(), function_exists( 'get_row_index' ) ? (int) get_row_index() : 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hm_toolkit_attr_string() escapet.
	}
}
