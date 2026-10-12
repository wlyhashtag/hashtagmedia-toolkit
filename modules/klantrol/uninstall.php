<?php
/**
 * Opruimroutine van de module Klantrol.
 *
 * Verwijdert de rol Klant. Gebruikers met die rol worden Abonnee, zodat ze
 * nog kunnen inloggen maar niets meer kunnen beheren. Een HashtagMedia-
 * beheerder kan ze daarna een andere rol geven.
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;

$hm_toolkit_users = get_users(
	array(
		'role'   => 'hm_klant',
		'fields' => 'ID',
	)
);

foreach ( $hm_toolkit_users as $hm_toolkit_user_id ) {
	$hm_toolkit_user = get_userdata( (int) $hm_toolkit_user_id );
	if ( $hm_toolkit_user ) {
		$hm_toolkit_user->remove_role( 'hm_klant' );
		if ( empty( $hm_toolkit_user->roles ) ) {
			$hm_toolkit_user->set_role( 'subscriber' );
		}
	}
}

remove_role( 'hm_klant' );
