<?php
/**
 * Module Beheermenu.
 *
 * - Volgorde van het hoofdmenu, voor iedereen. Items die niet in de
 *   opgeslagen volgorde staan (nieuwe plugins), komen vanzelf onderaan.
 * - Verborgen items (hoofd- en submenu):
 *   - klanten: echt weg uit het menu;
 *   - HashtagMedia-beheerders: achter "Alles tonen" onderaan het menu.
 *
 * Let op: verbergen is weergave, geen beveiliging. Wat een klant mag,
 * regelt de module Klantrol met rechten.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Beheermenu;

use HashtagMedia\Toolkit\Module as Base;

defined( 'ABSPATH' ) || exit;

/**
 * Volgorde en zichtbaarheid van het beheermenu.
 */
final class Module extends Base {

	/**
	 * CSS-klasse voor verborgen items (alleen bij beheerders).
	 */
	private const HIDDEN_CLASS = 'hm-menu-hidden';

	/**
	 * Maximale lengte van een menu-slug.
	 */
	private const MAX_SLUG = 300;

	/**
	 * Standaardwaarden.
	 *
	 * @return array
	 */
	public function defaults(): array {
		return array(
			'order'      => array(),
			'hidden'     => array(),
			'hidden_sub' => array(),
		);
	}

	/**
	 * Alleen admin.
	 */
	public function admin(): void {
		if ( ! empty( $this->get( 'order' ) ) ) {
			add_filter( 'custom_menu_order', '__return_true' );
			add_filter( 'menu_order', array( $this, 'menu_order' ), 999 );
		}

		if ( ! empty( $this->get( 'hidden' ) ) || ! empty( $this->get( 'hidden_sub' ) ) ) {
			add_action( 'admin_menu', array( $this, 'apply_hidden' ), PHP_INT_MAX );
			add_filter( 'add_menu_classes', array( $this, 'mark_hidden_top' ) );
			add_action( 'admin_head', array( $this, 'restore_toggle_state' ), 1 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_toggle' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings' ) );
	}

	/**
	 * Verborgen hoofdmenu-items (slugs).
	 *
	 * @return string[]
	 */
	private function hidden(): array {
		return array_map( 'strval', (array) $this->get( 'hidden' ) );
	}

	/**
	 * Verborgen submenu-items als lijst van [parent, slug].
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	private function hidden_sub(): array {
		$pairs = array();
		foreach ( (array) $this->get( 'hidden_sub' ) as $pair ) {
			if ( is_array( $pair ) && isset( $pair[0], $pair[1] ) ) {
				$pairs[] = array( (string) $pair[0], (string) $pair[1] );
			}
		}
		return $pairs;
	}

	/**
	 * Zijn er verborgen items voor deze gebruiker achter "Alles tonen"?
	 *
	 * @return bool
	 */
	private function has_hidden(): bool {
		return ! empty( $this->hidden() ) || ! empty( $this->hidden_sub() );
	}

	/**
	 * Volgorde: opgeslagen items eerst; WordPress zet onbekende items
	 * (nieuwe plugins) zelf daarna, onderaan.
	 *
	 * @param array $menu_order Huidige volgorde (slugs).
	 * @return array
	 */
	public function menu_order( $menu_order ) {
		if ( ! is_array( $menu_order ) ) {
			return $menu_order;
		}
		$saved = array_values( array_intersect( array_map( 'strval', (array) $this->get( 'order' ) ), $menu_order ) );
		return array_values( array_unique( array_merge( $saved, array_diff( $menu_order, $saved ) ) ) );
	}

	/**
	 * Klant: verborgen items echt weghalen. Beheerder: submenu-items markeren
	 * (hoofdmenu-items gaan via add_menu_classes).
	 */
	public function apply_hidden(): void {
		global $submenu;

		if ( ! hm_toolkit_is_agency_user() ) {
			foreach ( $this->hidden_sub() as $pair ) {
				remove_submenu_page( $pair[0], $pair[1] );
			}
			foreach ( $this->hidden() as $slug ) {
				remove_menu_page( $slug );
			}
			return;
		}

		foreach ( $this->hidden_sub() as $pair ) {
			if ( empty( $submenu[ $pair[0] ] ) || ! is_array( $submenu[ $pair[0] ] ) ) {
				continue;
			}
			foreach ( $submenu[ $pair[0] ] as $index => $item ) {
				if ( isset( $item[2] ) && $item[2] === $pair[1] ) {
					$classes = isset( $item[4] ) ? (string) $item[4] : '';
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Een klasse aan een submenu-item toevoegen kan alleen via $submenu.
					$submenu[ $pair[0] ][ $index ][4] = trim( $classes . ' ' . self::HIDDEN_CLASS );
					if ( ! isset( $submenu[ $pair[0] ][ $index ][3] ) ) {
						// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Index 3 (paginatitel) moet bestaan als index 4 gezet is.
						$submenu[ $pair[0] ][ $index ][3] = (string) $item[0];
					}
				}
			}
		}
	}

	/**
	 * Beheerder: verborgen hoofdmenu-items een klasse geven.
	 *
	 * @param array $menu Menu.
	 * @return array
	 */
	public function mark_hidden_top( $menu ) {
		if ( ! is_array( $menu ) || ! hm_toolkit_is_agency_user() ) {
			return $menu;
		}
		$hidden = $this->hidden();
		foreach ( $menu as $index => $item ) {
			if ( isset( $item[2] ) && in_array( (string) $item[2], $hidden, true ) ) {
				$menu[ $index ][4] = trim( ( isset( $item[4] ) ? (string) $item[4] : '' ) . ' ' . self::HIDDEN_CLASS );
			}
		}
		return $menu;
	}

	/**
	 * Stand van "Alles tonen" herstellen vóór het menu getekend wordt,
	 * zodat het niet verspringt. Opgeslagen in de browser van de beheerder.
	 */
	public function restore_toggle_state(): void {
		if ( ! hm_toolkit_is_agency_user() ) {
			return;
		}
		wp_print_inline_script_tag( 'try{if(window.localStorage.getItem("hmToolkitMenuShowAll")==="1"){document.documentElement.classList.add("hm-menu-show-all");}}catch(e){}' );
	}

	/**
	 * Knop "Alles tonen" voor beheerders.
	 */
	public function enqueue_toggle(): void {
		if ( ! hm_toolkit_is_agency_user() || ! $this->has_hidden() ) {
			return;
		}
		$this->enqueue_style( 'menu', 'assets/menu.css' );
		$this->enqueue_script( 'menu', 'assets/menu.js' );
		wp_localize_script(
			$this->handle( 'menu' ),
			'hmToolkitMenu',
			array(
				'showAll'  => __( 'Alles tonen', 'hashtagmedia-toolkit' ),
				'showLess' => __( 'Minder tonen', 'hashtagmedia-toolkit' ),
			)
		);
	}

	/**
	 * Slepen op het eigen tabblad.
	 *
	 * @param string $hook_suffix Huidige pagina.
	 */
	public function enqueue_settings( $hook_suffix ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen bepalen welk tabblad open is.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( 'toplevel_page_hashtagmedia' !== $hook_suffix || $this->slug() !== $tab ) {
			return;
		}
		$this->enqueue_style( 'settings', 'assets/settings.css' );
		$this->enqueue_script( 'settings', 'assets/settings.js', array( 'jquery', 'jquery-ui-sortable' ) );
		wp_localize_script(
			$this->handle( 'settings' ),
			'hmToolkitMenuSettings',
			array(
				/* translators: %s: naam van het menu-item */
				'moved' => __( '%s verplaatst.', 'hashtagmedia-toolkit' ),
			)
		);
	}

	/**
	 * Leesbare titel van een menu-item: tellers en HTML eruit.
	 *
	 * @param string $title Ruwe titel.
	 * @return string
	 */
	private static function clean_title( string $title ): string {
		$title = (string) preg_replace( '#<span[^>]*class=["\'][^"\']*(count|update-plugins|awaiting-mod|menu-counter|plugin-count|pending-count)[^"\']*["\'][^>]*>.*?</span>(\s*</span>)?#is', '', $title );
		$title = trim( wp_strip_all_tags( $title ) );
		return '' !== $title ? $title : __( '(zonder naam)', 'hashtagmedia-toolkit' );
	}

	/**
	 * Heeft een tabblad.
	 *
	 * @return bool
	 */
	public function has_tab(): bool {
		return true;
	}

	/**
	 * Tabblad: sleepbare lijst van het huidige menu.
	 */
	public function render_tab(): void {
		global $menu, $submenu;

		$hidden     = $this->hidden();
		$hidden_sub = array_map(
			static function ( $pair ) {
				return $pair[0] . "\n" . $pair[1];
			},
			$this->hidden_sub()
		);
		?>
		<p><?php esc_html_e( 'Sleep items naar de gewenste plek. Zet een schakelaar uit om een item te verbergen.', 'hashtagmedia-toolkit' ); ?></p>
		<p class="screen-reader-text" id="hm-menu-keys"><?php esc_html_e( 'Gebruik de pijltjestoetsen omhoog en omlaag om het item te verplaatsen.', 'hashtagmedia-toolkit' ); ?></p>
		<ul class="description hm-menu-help">
			<li><?php esc_html_e( 'Klanten zien verborgen items helemaal niet.', 'hashtagmedia-toolkit' ); ?></li>
			<li><?php esc_html_e( 'Beheerders vinden ze onder "Alles tonen" onderaan het menu.', 'hashtagmedia-toolkit' ); ?></li>
			<li><?php esc_html_e( 'Nieuwe menu-items van plugins verschijnen vanzelf onderaan.', 'hashtagmedia-toolkit' ); ?></li>
		</ul>

		<ol class="hm-menu-list" id="hm-menu-list">
			<?php foreach ( (array) $menu as $item ) : ?>
				<?php
				if ( ! isset( $item[2] ) ) {
					continue;
				}
				$slug         = (string) $item[2];
				$is_separator = isset( $item[4] ) && false !== strpos( (string) $item[4], 'wp-menu-separator' );
				$title        = $is_separator ? __( 'Scheidingslijn', 'hashtagmedia-toolkit' ) : self::clean_title( (string) $item[0] );
				$children     = ( ! $is_separator && ! empty( $submenu[ $slug ] ) ) ? (array) $submenu[ $slug ] : array();
				$id           = 'hm-menu-' . md5( $slug );
				?>
				<li class="hm-menu-item<?php echo $is_separator ? ' is-separator' : ''; ?>">
					<input type="hidden" name="hm_toolkit[order][]" value="<?php echo esc_attr( $slug ); ?>">
					<input type="hidden" name="hm_toolkit[known][]" value="<?php echo esc_attr( $slug ); ?>">
					<div class="hm-menu-item__row">
						<button type="button" class="hm-menu-item__handle" aria-describedby="hm-menu-keys">
							<span class="dashicons dashicons-move" aria-hidden="true"></span>
							<span class="screen-reader-text">
								<?php
								/* translators: %s: naam van het menu-item */
								printf( esc_html__( '%s verplaatsen', 'hashtagmedia-toolkit' ), esc_html( $title ) );
								?>
							</span>
						</button>
						<span class="hm-menu-item__title"><?php echo esc_html( $title ); ?></span>
						<span class="hm-menu-item__actions">
							<?php if ( count( $children ) > 1 ) : ?>
								<button type="button" class="button-link hm-menu-expand" aria-expanded="false" aria-controls="<?php echo esc_attr( $id . '-sub' ); ?>">
									<?php esc_html_e( 'Submenu', 'hashtagmedia-toolkit' ); ?>
								</button>
							<?php endif; ?>
							<label class="hm-toggle" title="<?php esc_attr_e( 'Zichtbaar', 'hashtagmedia-toolkit' ); ?>">
								<input type="checkbox" role="switch" class="hm-toggle__input" name="hm_toolkit[visible][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( ! in_array( $slug, $hidden, true ) ); ?>>
								<span class="screen-reader-text">
									<?php
									/* translators: %s: naam van het menu-item */
									printf( esc_html__( '%s zichtbaar', 'hashtagmedia-toolkit' ), esc_html( $title ) );
									?>
								</span>
							</label>
						</span>
					</div>
					<?php if ( count( $children ) > 1 ) : ?>
						<ul class="hm-menu-sub" id="<?php echo esc_attr( $id . '-sub' ); ?>" hidden>
							<?php foreach ( $children as $child ) : ?>
								<?php
								if ( ! isset( $child[2] ) ) {
									continue;
								}
								$key         = $slug . "\n" . (string) $child[2];
								$value       = (string) wp_json_encode( array( $slug, (string) $child[2] ) );
								$child_title = self::clean_title( (string) $child[0] );
								?>
								<li class="hm-menu-sub__item">
									<input type="hidden" name="hm_toolkit[known_sub][]" value="<?php echo esc_attr( $value ); ?>">
									<span class="hm-menu-item__title"><?php echo esc_html( $child_title ); ?></span>
									<label class="hm-toggle">
										<input type="checkbox" role="switch" class="hm-toggle__input" name="hm_toolkit[visible_sub][]" value="<?php echo esc_attr( $value ); ?>" <?php checked( ! in_array( $key, $hidden_sub, true ) ); ?>>
										<span class="screen-reader-text">
											<?php
											/* translators: %s: naam van het submenu-item */
											printf( esc_html__( '%s zichtbaar', 'hashtagmedia-toolkit' ), esc_html( $child_title ) );
											?>
										</span>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
		<p class="screen-reader-text" id="hm-menu-live" aria-live="polite"></p>

		<p class="hm-menu-reset">
			<button type="submit" class="button-link button-link-delete" name="hm_toolkit[reset]" value="1" onclick="return window.confirm(<?php echo esc_attr( (string) wp_json_encode( __( 'Volgorde en verborgen items terugzetten naar de standaard van WordPress?', 'hashtagmedia-toolkit' ) ) ); ?>);">
				<?php esc_html_e( 'Terugzetten naar standaard', 'hashtagmedia-toolkit' ); ?>
			</button>
		</p>
		<?php
	}

	/**
	 * Eén slug opschonen.
	 *
	 * @param mixed $slug Invoer.
	 * @return string
	 */
	private static function clean_slug( $slug ): string {
		$slug = is_scalar( $slug ) ? trim( wp_strip_all_tags( (string) $slug ) ) : '';
		return mb_substr( $slug, 0, self::MAX_SLUG );
	}

	/**
	 * Lijst slugs opschonen.
	 *
	 * @param mixed $items Invoer.
	 * @return string[]
	 */
	private static function clean_list( $items ): array {
		$clean = array();
		foreach ( is_array( $items ) ? array_slice( $items, 0, 500 ) : array() as $slug ) {
			$slug = self::clean_slug( $slug );
			if ( '' !== $slug ) {
				$clean[] = $slug;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Lijst submenu-paren (JSON [parent, slug]) opschonen.
	 *
	 * @param mixed $items Invoer.
	 * @return array<string,array{0:string,1:string}> sleutel "parent\nslug".
	 */
	private static function clean_pairs( $items ): array {
		$pairs = array();
		foreach ( is_array( $items ) ? array_slice( $items, 0, 2000 ) : array() as $json ) {
			$pair = is_string( $json ) ? json_decode( $json, true ) : null;
			if ( ! is_array( $pair ) || ! isset( $pair[0], $pair[1] ) ) {
				continue;
			}
			$parent = self::clean_slug( $pair[0] );
			$slug   = self::clean_slug( $pair[1] );
			if ( '' !== $parent && '' !== $slug ) {
				$pairs[ $parent . "\n" . $slug ] = array( $parent, $slug );
			}
		}
		return $pairs;
	}

	/**
	 * Import: alleen lijsten met menu-slugs en paren [hoofdmenu, submenu].
	 *
	 * @param array $values Waarden uit het bestand.
	 * @return array
	 */
	public function import_settings( array $values ): array {
		$clean = array();
		foreach ( array( 'order', 'hidden' ) as $key ) {
			if ( array_key_exists( $key, $values ) ) {
				$clean[ $key ] = self::clean_list( $values[ $key ] );
			}
		}
		if ( array_key_exists( 'hidden_sub', $values ) ) {
			$pairs = array();
			foreach ( is_array( $values['hidden_sub'] ) ? $values['hidden_sub'] : array() as $pair ) {
				$pairs[] = is_array( $pair ) ? (string) wp_json_encode( array_values( $pair ) ) : '';
			}
			$clean['hidden_sub'] = array_values( self::clean_pairs( $pairs ) );
		}
		return $clean;
	}

	/**
	 * Saneert het tabblad. Verborgen = getoonde items minus aangezette.
	 *
	 * @param array $input Invoer.
	 * @return array
	 */
	public function sanitize( array $input ): array {
		if ( ! empty( $input['reset'] ) ) {
			return $this->defaults();
		}

		$known   = self::clean_list( $input['known'] ?? array() );
		$visible = self::clean_list( $input['visible'] ?? array() );

		$known_sub   = self::clean_pairs( $input['known_sub'] ?? array() );
		$visible_sub = self::clean_pairs( $input['visible_sub'] ?? array() );

		// Verborgen items die nu niet in het menu staan (bijv. plugin tijdelijk
		// uit) blijven verborgen, zodat ze niet terugkomen na opslaan.
		$old_hidden = array_diff( $this->hidden(), $known );
		$old_sub    = array();
		foreach ( $this->hidden_sub() as $pair ) {
			$key = $pair[0] . "\n" . $pair[1];
			if ( ! isset( $known_sub[ $key ] ) ) {
				$old_sub[ $key ] = $pair;
			}
		}

		return array(
			'order'      => self::clean_list( $input['order'] ?? array() ),
			'hidden'     => array_values( array_unique( array_merge( array_diff( $known, $visible ), $old_hidden ) ) ),
			'hidden_sub' => array_values( array_merge( array_diff_key( $known_sub, $visible_sub ), $old_sub ) ),
		);
	}
}
