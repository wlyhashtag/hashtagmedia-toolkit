<?php
/**
 * Gedeelde helpers van de kern, voor modules en thema's.
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is de gebruiker een HashtagMedia-beheerder?
 *
 * Afspraak: HashtagMedia is de enige met manage_options. Klanten hebben
 * dat recht nooit, ook niet met de Klant-rol.
 *
 * @param int|null $user_id Gebruiker; standaard de huidige.
 * @return bool
 */
function hm_toolkit_is_agency_user( ?int $user_id = null ): bool {
	if ( null === $user_id ) {
		return current_user_can( 'manage_options' );
	}
	return user_can( $user_id, 'manage_options' );
}

/**
 * HTML-attributen als string, ge-escapet. Lege waarden (behalve 'alt') vallen weg.
 *
 * @param array<string,scalar> $attr Attributen.
 * @return string Bijv. ' class="a" data-x="1"'.
 */
function hm_toolkit_attr_string( array $attr ): string {
	$out = '';
	foreach ( $attr as $name => $value ) {
		$name = preg_replace( '/[^a-z0-9_:-]/i', '', (string) $name );
		if ( '' === $name || ( '' === (string) $value && 'alt' !== $name ) ) {
			continue;
		}
		$out .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
	}
	return $out;
}
