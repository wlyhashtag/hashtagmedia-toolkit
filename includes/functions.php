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
