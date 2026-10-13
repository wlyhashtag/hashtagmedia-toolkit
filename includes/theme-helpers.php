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
