<?php
/**
 * Opruimroutine van de module Huisstijl en dashboard.
 *
 * Deze module bewaart niets buiten zijn eigen instellingen (die ruimt de
 * kern op). De wachttijd van het supportformulier is een transient die
 * vanzelf verloopt. Het kleurenschema wordt niet in het gebruikersprofiel
 * opgeslagen, dus daar valt niets terug te zetten.
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;
