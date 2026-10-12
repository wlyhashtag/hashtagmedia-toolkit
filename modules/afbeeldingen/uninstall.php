<?php
/**
 * Opruimroutine van de module Afbeeldingen.
 *
 * Verwijdert de focuspunten (post-meta _hm_focus). Omgezette WebP-bestanden
 * en de bewaarde originelen zijn content en blijven staan.
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;

delete_post_meta_by_key( '_hm_focus' );
