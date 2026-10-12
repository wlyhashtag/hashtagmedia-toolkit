<?php
/**
 * Modules vinden, laden, synchroniseren en opruimen.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit;

defined( 'ABSPATH' ) || exit;

/**
 * Register van modules.
 */
final class Registry {

	/**
	 * Geladen (actieve) modules.
	 *
	 * @var array<string,Module>
	 */
	private static array $loaded = array();

	/**
	 * Cache van gevonden modules voor dit verzoek.
	 *
	 * @var array<string,array>|null
	 */
	private static ?array $found = null;

	/**
	 * Map met modules.
	 *
	 * @return string
	 */
	public static function modules_dir(): string {
		return HM_TOOLKIT_DIR . 'modules/';
	}

	/**
	 * Is dit een geldige slug? Alleen kleine letters, cijfers en streepjes.
	 *
	 * @param string $slug Slug.
	 * @return bool
	 */
	public static function is_valid_slug( string $slug ): bool {
		return 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug );
	}

	/**
	 * Map van een module.
	 *
	 * @param string $slug Slug.
	 * @return string
	 */
	public static function module_dir( string $slug ): string {
		return self::modules_dir() . $slug . '/';
	}

	/**
	 * Klassenaam volgens vaste afspraak: opruimen => ...\Modules\Opruimen\Module,
	 * export-import => ...\Modules\ExportImport\Module.
	 *
	 * @param string $slug Slug.
	 * @return string
	 */
	public static function class_name( string $slug ): string {
		$studly = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $slug ) ) );
		return __NAMESPACE__ . '\\Modules\\' . $studly . '\\Module';
	}

	/**
	 * Alleen de slugs van mappen die een module bevatten (zonder iets te laden).
	 *
	 * @return string[]
	 */
	public static function folder_slugs(): array {
		$slugs = array();
		$dirs  = glob( self::modules_dir() . '*', GLOB_ONLYDIR );
		foreach ( is_array( $dirs ) ? $dirs : array() as $dir ) {
			$slug = basename( $dir );
			if ( self::is_valid_slug( $slug ) && is_readable( $dir . '/module.php' ) ) {
				$slugs[] = $slug;
			}
		}
		sort( $slugs );
		return $slugs;
	}

	/**
	 * Alle modules in de map, met hun beschrijving. Alleen gebruiken op het
	 * instellingenscherm, bij activeren en na een update (niet bij elk verzoek).
	 *
	 * @return array<string,array{name:string,description:string,default:bool}>
	 */
	public static function discover(): array {
		if ( null !== self::$found ) {
			return self::$found;
		}

		self::$found = array();
		foreach ( self::folder_slugs() as $slug ) {
			$meta = self::read_meta( self::module_dir( $slug ) . 'module.php' );
			if ( null === $meta ) {
				continue;
			}
			self::$found[ $slug ] = $meta;
		}

		uasort(
			self::$found,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);

		return self::$found;
	}

	/**
	 * Leest module.php in een eigen scope en controleert de inhoud.
	 *
	 * @param string $file Pad naar module.php.
	 * @return array|null
	 */
	private static function read_meta( string $file ): ?array {
		$meta = ( static function ( $hm_toolkit_file ) {
			return include $hm_toolkit_file;
		} )( $file );

		if ( ! is_array( $meta ) || empty( $meta['name'] ) ) {
			return null;
		}

		return array(
			'name'        => (string) $meta['name'],
			'description' => isset( $meta['description'] ) ? (string) $meta['description'] : '',
			'default'     => ! empty( $meta['default'] ),
			'tab'         => isset( $meta['tab'] ) ? (string) $meta['tab'] : (string) $meta['name'],
		);
	}

	/**
	 * Laadt alle modules die aanstaan. Zoekt de map niet door.
	 */
	public static function load_enabled(): void {
		foreach ( Settings::enabled_modules() as $slug ) {
			$module = self::instantiate( $slug );
			if ( null === $module ) {
				continue;
			}
			self::$loaded[ $slug ] = $module;
			$module->boot();
			if ( is_admin() ) {
				$module->admin();
			}
		}

		/**
		 * Alle actieve modules zijn geladen.
		 *
		 * @param array<string,Module> $loaded Geladen modules.
		 */
		do_action( 'hm_toolkit_modules_loaded', self::$loaded );
	}

	/**
	 * Maakt een module aan als de map en klasse kloppen.
	 *
	 * @param string $slug Slug.
	 * @return Module|null
	 */
	private static function instantiate( string $slug ): ?Module {
		if ( ! self::is_valid_slug( $slug ) ) {
			return null;
		}

		$dir  = self::module_dir( $slug );
		$file = $dir . 'class-module.php';
		if ( ! is_readable( $file ) ) {
			return null;
		}

		require_once $file;

		$class = self::class_name( $slug );
		if ( ! class_exists( $class, false ) || ! is_subclass_of( $class, Module::class ) ) {
			return null;
		}

		return new $class( $slug, $dir );
	}

	/**
	 * Een module-object, ook als de module uitstaat. Een niet-geladen module
	 * wordt aangemaakt maar niet gestart (geen boot of admin): alleen voor
	 * het lezen van standaardwaarden en het saneren van instellingen.
	 *
	 * @param string $slug Slug.
	 * @return Module|null
	 */
	public static function instance( string $slug ): ?Module {
		return self::$loaded[ $slug ] ?? self::instantiate( $slug );
	}

	/**
	 * Een geladen module (alleen voor de kern zelf).
	 *
	 * @param string $slug Slug.
	 * @return Module|null
	 */
	public static function get( string $slug ): ?Module {
		return self::$loaded[ $slug ] ?? null;
	}

	/**
	 * Alle geladen modules.
	 *
	 * @return array<string,Module>
	 */
	public static function loaded(): array {
		return self::$loaded;
	}

	/**
	 * Synchroniseert de optie met de mappen: nieuwe modules krijgen hun
	 * standaardstand, verdwenen modules worden uit de optie gehaald.
	 */
	public static function sync(): void {
		$found = self::discover();

		foreach ( $found as $slug => $meta ) {
			if ( ! Settings::is_known( $slug ) ) {
				Settings::set_enabled( $slug, $meta['default'] );
			}
		}

		foreach ( array_keys( Settings::modules() ) as $slug ) {
			if ( ! isset( $found[ $slug ] ) ) {
				Settings::forget_module( $slug );
			}
		}
	}

	/**
	 * Draait de opruimroutine van een module en wist zijn instellingen.
	 * De module blijft bekend (uit), zodat hij later weer aan kan.
	 *
	 * @param string $slug Slug.
	 */
	public static function cleanup( string $slug ): void {
		if ( ! self::is_valid_slug( $slug ) ) {
			return;
		}

		self::run_uninstall_file( $slug );
		Settings::forget_module_settings( $slug );
	}

	/**
	 * Voert uninstall.php van een module uit in een eigen scope.
	 *
	 * @param string $slug Slug.
	 */
	public static function run_uninstall_file( string $slug ): void {
		$file = self::module_dir( $slug ) . 'uninstall.php';
		if ( is_readable( $file ) ) {
			( static function ( $hm_toolkit_file ) {
				include $hm_toolkit_file;
			} )( $file );
		}
	}
}
