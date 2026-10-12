<?php
/**
 * Module Snelkoppelingen.
 *
 * Knoppen naar de juiste plek in de vaste plugins en de optiepagina('s) van
 * het thema. Alle adressen staan in links.php. Een knop verschijnt alleen als
 * de plugin actief is. Alleen HashtagMedia-beheerders zien ze: op het
 * tabblad Snelkoppelingen en (optioneel) in een blok op het dashboard.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Snelkoppelingen;

use HashtagMedia\Toolkit\Module as Base;

defined( 'ABSPATH' ) || exit;

/**
 * Snelkoppelingen naar andere plugins.
 */
final class Module extends Base {

	/**
	 * Groepen voor dit verzoek (pas opgebouwd als ze nodig zijn).
	 *
	 * @var array|null
	 */
	private ?array $groups = null;

	/**
	 * Standaardwaarden.
	 *
	 * @return array
	 */
	public function defaults(): array {
		return array(
			'dashboard' => true,
		);
	}

	/**
	 * Alleen admin.
	 */
	public function admin(): void {
		if ( $this->get( 'dashboard' ) ) {
			add_action( 'wp_dashboard_setup', array( $this, 'add_widget' ), 30 );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Dashboardblok, alleen voor HashtagMedia-beheerders.
	 */
	public function add_widget(): void {
		if ( ! hm_toolkit_is_agency_user() || array() === $this->active_groups() ) {
			return;
		}
		wp_add_dashboard_widget(
			'hm_toolkit_shortcuts',
			esc_html__( 'Snelkoppelingen', 'hashtagmedia-toolkit' ),
			array( $this, 'render_widget' ),
			null,
			null,
			'side',
			'low'
		);
	}

	/**
	 * Stijl op het dashboard en op het eigen tabblad.
	 *
	 * @param string $hook_suffix Huidige pagina.
	 */
	public function enqueue( $hook_suffix ): void {
		if ( ! hm_toolkit_is_agency_user() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen bepalen welk tabblad open is.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$own = 'toplevel_page_hashtagmedia' === $hook_suffix && $this->slug() === $tab;
		if ( $own || ( 'index.php' === $hook_suffix && $this->get( 'dashboard' ) ) ) {
			$this->enqueue_style( 'shortcuts', 'assets/shortcuts.css' );
		}
	}

	/**
	 * Alle groepen uit links.php, na de filter.
	 *
	 * Thema's kunnen een groep toevoegen met de filter hm_toolkit_shortcuts,
	 * in dezelfde vorm als links.php.
	 *
	 * @return array
	 */
	private function all_groups(): array {
		$groups = require $this->path( 'links.php' );
		$groups = apply_filters( 'hm_toolkit_shortcuts', is_array( $groups ) ? $groups : array() );
		return is_array( $groups ) ? $groups : array();
	}

	/**
	 * Groepen waarvan de plugin actief is, met kant-en-klare URL's.
	 *
	 * @return array<string,array{name:string,icon:string,links:array}>
	 */
	private function active_groups(): array {
		if ( null !== $this->groups ) {
			return $this->groups;
		}

		$this->groups = array();
		foreach ( $this->all_groups() as $key => $group ) {
			if ( ! is_array( $group ) || ! self::is_active( $group['detect'] ?? array() ) ) {
				continue;
			}
			$links = $group['links'] ?? array();
			if ( is_callable( $links ) ) {
				$links = call_user_func( $links );
			}
			$links = self::resolve_links( is_array( $links ) ? $links : array() );
			if ( array() === $links ) {
				continue;
			}
			$this->groups[ sanitize_key( (string) $key ) ] = array(
				'name'  => (string) ( $group['name'] ?? $key ),
				'icon'  => sanitize_html_class( (string) ( $group['icon'] ?? 'dashicons-admin-links' ) ),
				'links' => $links,
			);
		}
		return $this->groups;
	}

	/**
	 * Namen van bekende plugins die niet actief zijn (voor het tabblad).
	 *
	 * @return string[]
	 */
	private function inactive_names(): array {
		$active = $this->active_groups();
		$names  = array();
		foreach ( $this->all_groups() as $key => $group ) {
			if ( is_array( $group ) && ! isset( $active[ sanitize_key( (string) $key ) ] ) ) {
				$names[] = (string) ( $group['name'] ?? $key );
			}
		}
		return $names;
	}

	/**
	 * Is de plugin actief? Eén treffer is genoeg.
	 *
	 * @param array $detect constants, classes en/of functions.
	 * @return bool
	 */
	private static function is_active( $detect ): bool {
		if ( ! is_array( $detect ) || array() === $detect ) {
			return true;
		}
		foreach ( (array) ( $detect['constants'] ?? array() ) as $constant ) {
			if ( defined( (string) $constant ) ) {
				return true;
			}
		}
		foreach ( (array) ( $detect['classes'] ?? array() ) as $class ) {
			if ( class_exists( (string) $class, false ) ) {
				return true;
			}
		}
		foreach ( (array) ( $detect['functions'] ?? array() ) as $function ) {
			if ( function_exists( (string) $function ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Links omzetten naar volledige URL's; ongeldige links vallen af.
	 *
	 * @param array $links Links uit links.php.
	 * @return array<int,array{label:string,url:string}>
	 */
	private static function resolve_links( array $links ): array {
		$clean = array();
		foreach ( $links as $link ) {
			if ( ! is_array( $link ) || empty( $link['label'] ) || empty( $link['url'] ) ) {
				continue;
			}
			$url = (string) $link['url'];
			if ( ! preg_match( '#^https?://#i', $url ) ) {
				$url = admin_url( ltrim( $url, '/' ) );
			}
			if ( ! empty( $link['nonce'] ) ) {
				$url = wp_nonce_url( $url, (string) $link['nonce'] );
			}
			$clean[] = array(
				'label' => (string) $link['label'],
				'url'   => $url,
			);
		}
		return $clean;
	}

	/**
	 * Knoppen van één groep.
	 *
	 * @param array  $group Groep.
	 * @param string $css_class Klassen voor de knoppen.
	 */
	private static function render_buttons( array $group, string $css_class ): void {
		foreach ( $group['links'] as $link ) {
			printf(
				'<a class="%1$s" href="%2$s">%3$s</a>',
				esc_attr( $css_class ),
				esc_url( $link['url'] ),
				esc_html( $link['label'] )
			);
		}
	}

	/**
	 * Dashboardblok: compacte lijst.
	 */
	public function render_widget(): void {
		echo '<ul class="hm-shortcuts-list">';
		foreach ( $this->active_groups() as $group ) {
			echo '<li>';
			printf(
				'<span class="hm-shortcuts-list__name"><span class="dashicons %1$s" aria-hidden="true"></span>%2$s</span>',
				esc_attr( $group['icon'] ),
				esc_html( $group['name'] )
			);
			echo '<span class="hm-shortcuts-list__links">';
			self::render_buttons( $group, 'button button-small' );
			echo '</span></li>';
		}
		echo '</ul>';
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
	 * Tabblad Snelkoppelingen.
	 */
	public function render_tab(): void {
		$groups   = $this->active_groups();
		$inactive = $this->inactive_names();
		?>
		<p><?php esc_html_e( 'Direct naar de juiste plek in de plugins van deze site. Alleen plugins die actief zijn staan erbij.', 'hashtagmedia-toolkit' ); ?></p>

		<?php if ( array() === $groups ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'Geen van de bekende plugins is actief.', 'hashtagmedia-toolkit' ); ?></p></div>
		<?php else : ?>
			<div class="hm-shortcuts">
				<?php foreach ( $groups as $group ) : ?>
					<div class="hm-shortcuts__card">
						<h2 class="hm-shortcuts__title"><span class="dashicons <?php echo esc_attr( $group['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $group['name'] ); ?></h2>
						<div class="hm-shortcuts__links"><?php self::render_buttons( $group, 'button' ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( array() !== $inactive ) : ?>
			<p class="description">
				<?php
				/* translators: %s: lijst met pluginnamen */
				echo esc_html( sprintf( __( 'Niet actief: %s.', 'hashtagmedia-toolkit' ), implode( ', ', $inactive ) ) );
				?>
			</p>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<?php
			$this->checkbox_row(
				'dashboard',
				__( 'Dashboard', 'hashtagmedia-toolkit' ),
				__( 'Snelkoppelingen ook op het dashboard tonen', 'hashtagmedia-toolkit' ),
				__( 'Alleen HashtagMedia-beheerders zien dit blok; klanten nooit.', 'hashtagmedia-toolkit' )
			);
			?>
		</table>
		<?php
	}

	/**
	 * Saneert het tabblad.
	 *
	 * @param array $input Invoer.
	 * @return array
	 */
	public function sanitize( array $input ): array {
		return $this->sanitize_checkboxes( $input, array( 'dashboard' ) );
	}
}
