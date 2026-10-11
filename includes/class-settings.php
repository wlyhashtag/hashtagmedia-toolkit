<?php
/**
 * De ene optie waarin de hele plugin zijn instellingen bewaart.
 *
 * Structuur van de optie hm_toolkit:
 *
 *     array(
 *         'version'  => '0.1.0',
 *         'modules'  => array( 'opruimen' => true, ... ),   // aan of uit
 *         'settings' => array( 'opruimen' => array( ... ) ), // één deel per module
 *     )
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit;

defined( 'ABSPATH' ) || exit;

/**
 * Lezen en schrijven van de optie hm_toolkit.
 */
final class Settings {

	/**
	 * Naam van de optie in wp_options.
	 */
	public const OPTION = 'hm_toolkit';

	/**
	 * Cache van de optie voor dit verzoek.
	 *
	 * @var array|null
	 */
	private static ?array $data = null;

	/**
	 * De volledige optie, altijd met de drie vaste sleutels.
	 *
	 * @return array
	 */
	public static function all(): array {
		if ( null === self::$data ) {
			$stored     = get_option( self::OPTION, array() );
			self::$data = wp_parse_args(
				is_array( $stored ) ? $stored : array(),
				array(
					'version'  => '',
					'modules'  => array(),
					'settings' => array(),
				)
			);
		}
		return self::$data;
	}

	/**
	 * Opgeslagen versie van de plugin (voor upgrades).
	 *
	 * @return string
	 */
	public static function version(): string {
		return (string) self::all()['version'];
	}

	/**
	 * Versie vastleggen.
	 *
	 * @param string $version Versienummer.
	 */
	public static function set_version( string $version ): void {
		self::all();
		self::$data['version'] = $version;
		self::save();
	}

	/**
	 * Aan/uit-stand van alle bekende modules.
	 *
	 * @return array<string,bool>
	 */
	public static function modules(): array {
		return array_map( 'boolval', (array) self::all()['modules'] );
	}

	/**
	 * Slugs van de modules die aanstaan.
	 *
	 * @return string[]
	 */
	public static function enabled_modules(): array {
		return array_keys( array_filter( self::modules() ) );
	}

	/**
	 * Staat een module aan?
	 *
	 * @param string $slug Module-slug.
	 * @return bool
	 */
	public static function is_enabled( string $slug ): bool {
		return ! empty( self::modules()[ $slug ] );
	}

	/**
	 * Is de module bekend (aan of uit)?
	 *
	 * @param string $slug Module-slug.
	 * @return bool
	 */
	public static function is_known( string $slug ): bool {
		return array_key_exists( $slug, self::modules() );
	}

	/**
	 * Een module aan- of uitzetten.
	 *
	 * @param string $slug    Module-slug.
	 * @param bool   $enabled Aan of uit.
	 */
	public static function set_enabled( string $slug, bool $enabled ): void {
		self::all();
		self::$data['modules'][ $slug ] = $enabled;
		self::save();
	}

	/**
	 * Meerdere modules tegelijk aan- of uitzetten (één schrijfactie).
	 *
	 * @param array<string,bool> $states slug => aan/uit.
	 */
	public static function set_enabled_many( array $states ): void {
		self::all();
		foreach ( $states as $slug => $enabled ) {
			self::$data['modules'][ (string) $slug ] = (bool) $enabled;
		}
		self::save();
	}

	/**
	 * De opgeslagen instellingen van één module.
	 *
	 * @param string $slug Module-slug.
	 * @return array
	 */
	public static function module( string $slug ): array {
		$settings = self::all()['settings'];
		return isset( $settings[ $slug ] ) && is_array( $settings[ $slug ] ) ? $settings[ $slug ] : array();
	}

	/**
	 * Heeft een module opgeslagen instellingen?
	 *
	 * @param string $slug Module-slug.
	 * @return bool
	 */
	public static function has_module_settings( string $slug ): bool {
		return array_key_exists( $slug, (array) self::all()['settings'] );
	}

	/**
	 * Instellingen van één module vervangen.
	 *
	 * @param string $slug   Module-slug.
	 * @param array  $values Gesaneerde waarden.
	 */
	public static function set_module( string $slug, array $values ): void {
		self::all();
		self::$data['settings'][ $slug ] = $values;
		self::save();
	}

	/**
	 * Alleen de instellingen van een module wissen; de aan/uit-stand blijft.
	 *
	 * @param string $slug Module-slug.
	 */
	public static function forget_module_settings( string $slug ): void {
		self::all();
		unset( self::$data['settings'][ $slug ] );
		self::save();
	}

	/**
	 * Een module volledig vergeten: aan/uit-stand en instellingen.
	 *
	 * @param string $slug Module-slug.
	 */
	public static function forget_module( string $slug ): void {
		self::all();
		unset( self::$data['modules'][ $slug ], self::$data['settings'][ $slug ] );
		self::save();
	}

	/**
	 * De hele optie verwijderen (alleen bij het verwijderen van de plugin).
	 */
	public static function delete(): void {
		delete_option( self::OPTION );
		self::$data = null;
	}

	/**
	 * Optie wegschrijven.
	 */
	private static function save(): void {
		update_option( self::OPTION, self::$data, true );
	}
}
