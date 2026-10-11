<?php
/**
 * Pluginprofielen: per plugin één bestand in profiles/ dat reclame,
 * upsells, menu-items, kolommen en adminbalk-items weghaalt.
 *
 * Een profielbestand geeft een array terug:
 *
 *     array(
 *         'name'     => 'Yoast SEO',
 *         'detect'   => array( 'constants' => array(), 'classes' => array() ),
 *         'menu'     => array( 'parent-slug' => array( 'submenu-slug', ... ) ),
 *         'adminbar' => array( 'node-id', ... ),
 *         'columns'  => array( 'kolom-sleutel', ... ),
 *         'css'      => array( 'selector', ... ),   // verborgen in de admin
 *         'hooks'    => static function () {},      // optioneel, eigen filters
 *     )
 *
 * Na een plugin-update die reclame terugbrengt: alleen dat profiel aanpassen.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Opruimen;

defined( 'ABSPATH' ) || exit;

/**
 * Laadt en past de profielen toe.
 */
final class Profiles {

	/**
	 * Map met profielbestanden.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Alle profielen, geladen bij eerste gebruik.
	 *
	 * @var array<string,array>|null
	 */
	private ?array $all = null;

	/**
	 * Constructor.
	 *
	 * @param string $dir Map met profielbestanden.
	 */
	public function __construct( string $dir ) {
		$this->dir = trailingslashit( $dir );
	}

	/**
	 * Slugs van alle profielbestanden (zonder ze te laden).
	 *
	 * @return string[]
	 */
	public function slugs(): array {
		$files = glob( $this->dir . '*.php' );
		$slugs = array_map(
			static function ( $file ) {
				return basename( $file, '.php' );
			},
			is_array( $files ) ? $files : array()
		);
		sort( $slugs );
		return $slugs;
	}

	/**
	 * Alle profielen met hun gegevens.
	 *
	 * @return array<string,array>
	 */
	public function all(): array {
		if ( null !== $this->all ) {
			return $this->all;
		}

		$this->all = array();
		foreach ( $this->slugs() as $slug ) {
			$profile = ( static function ( $hm_toolkit_file ) {
				return include $hm_toolkit_file;
			} )( $this->dir . $slug . '.php' );

			if ( is_array( $profile ) && ! empty( $profile['name'] ) ) {
				$this->all[ $slug ] = wp_parse_args(
					$profile,
					array(
						'detect'   => array(),
						'menu'     => array(),
						'adminbar' => array(),
						'columns'  => array(),
						'css'      => array(),
						'hooks'    => null,
					)
				);
			}
		}

		return $this->all;
	}

	/**
	 * Is de plugin van dit profiel actief?
	 *
	 * @param array $profile Profiel.
	 * @return bool
	 */
	public static function is_detected( array $profile ): bool {
		$detect = (array) $profile['detect'];

		foreach ( (array) ( $detect['constants'] ?? array() ) as $constant ) {
			if ( defined( $constant ) ) {
				return true;
			}
		}
		foreach ( (array) ( $detect['classes'] ?? array() ) as $class ) {
			if ( class_exists( $class, false ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Profielen die aanstaan én waarvan de plugin actief is.
	 *
	 * @param callable $is_enabled Geeft per profiel-slug aan of het aanstaat.
	 * @return array<string,array>
	 */
	public function active( callable $is_enabled ): array {
		return array_filter(
			$this->all(),
			static function ( $profile, $slug ) use ( $is_enabled ) {
				return $is_enabled( $slug ) && self::is_detected( $profile );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Een lijst uit alle actieve profielen samenvoegen.
	 *
	 * @param array<string,array> $profiles Actieve profielen.
	 * @param string              $key      Sleutel (adminbar, columns, css).
	 * @return string[]
	 */
	public static function merge( array $profiles, string $key ): array {
		$merged = array();
		foreach ( $profiles as $profile ) {
			$merged = array_merge( $merged, array_map( 'strval', (array) $profile[ $key ] ) );
		}
		return array_values( array_unique( $merged ) );
	}

	/**
	 * Menu-items van alle actieve profielen: parent => submenu-slugs.
	 *
	 * @param array<string,array> $profiles Actieve profielen.
	 * @return array<string,string[]>
	 */
	public static function merge_menu( array $profiles ): array {
		$merged = array();
		foreach ( $profiles as $profile ) {
			foreach ( (array) $profile['menu'] as $parent => $children ) {
				$merged[ $parent ] = array_merge( $merged[ $parent ] ?? array(), (array) $children );
			}
		}
		return $merged;
	}
}
