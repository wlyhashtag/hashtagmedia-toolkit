<?php
/**
 * Meldingen verzamelen achter één knop in de adminbalk.
 *
 * Werkwijze: vlak vóór de adminbalk wordt getekend (in_admin_header,
 * prioriteit 0) voeren we de meldingen-hooks zelf uit en vangen de uitvoer
 * op. Daarna halen we die hooks leeg, zodat WordPress ze niet nog eens toont.
 *
 * - HashtagMedia-beheerders zien een knop met het aantal; de meldingen staan
 *   in een paneel dat pas opent na een klik.
 * - Klanten zien geen meldingen en geen knop.
 *
 * Meldingen die een pagina zelf in de inhoud zet (zoals "Bericht
 * bijgewerkt" of "Instellingen opgeslagen") blijven gewoon zichtbaar.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Opruimen;

defined( 'ABSPATH' ) || exit;

/**
 * Verzamelt admin-meldingen.
 */
final class Notices {

	/**
	 * ID van het adminbalk-item.
	 */
	private const NODE = 'hm-toolkit-notices';

	/**
	 * Opgevangen HTML.
	 *
	 * @var string
	 */
	private string $html = '';

	/**
	 * Aantal opgevangen meldingen.
	 *
	 * @var int
	 */
	private int $count = 0;

	/**
	 * Script- en stylesheet-URL's.
	 *
	 * @var array{css:string,js:string}
	 */
	private array $assets;

	/**
	 * Constructor.
	 *
	 * @param array{css:string,js:string} $assets URL's van het paneel.
	 */
	public function __construct( array $assets ) {
		$this->assets = $assets;
	}

	/**
	 * Hooks registreren.
	 */
	public function register(): void {
		add_action( 'in_admin_header', array( $this, 'capture' ), -1000 );
		add_action( 'admin_bar_menu', array( $this, 'add_node' ), 999 );
		add_action( 'admin_footer', array( $this, 'render_template' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Hooks die meldingen tonen, afhankelijk van het soort admin.
	 *
	 * @return string[]
	 */
	private static function hooks(): array {
		if ( is_network_admin() ) {
			$first = 'network_admin_notices';
		} elseif ( is_user_admin() ) {
			$first = 'user_admin_notices';
		} else {
			$first = 'admin_notices';
		}
		return array( $first, 'all_admin_notices' );
	}

	/**
	 * Meldingen opvangen (beheerder) of onderdrukken (klant).
	 */
	public function capture(): void {
		$hooks = self::hooks();

		if ( ! hm_toolkit_is_agency_user() ) {
			foreach ( $hooks as $hook ) {
				remove_all_actions( $hook );
			}
			return;
		}

		ob_start();
		foreach ( $hooks as $hook ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Core-hooks, bewust zelf uitgevoerd.
			do_action( $hook );
			remove_all_actions( $hook );
		}
		$this->html  = trim( (string) ob_get_clean() );
		$this->count = self::count_notices( $this->html );

		// Geen meldingen maar wel uitvoer (bijv. een <style>): gewoon op de normale plek tonen.
		if ( 0 === $this->count && '' !== $this->html ) {
			$html = $this->html;
			add_action(
				'all_admin_notices',
				static function () use ( $html ) {
					echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ongewijzigde uitvoer van andere plugins, die zij zelf al zouden tonen.
				}
			);
			$this->html = '';
		}
	}

	/**
	 * Telt de meldingen in de opgevangen HTML.
	 *
	 * @param string $html HTML.
	 * @return int
	 */
	private static function count_notices( string $html ): int {
		if ( '' === $html || ! class_exists( \DOMDocument::class ) ) {
			return '' === $html ? 0 : 1;
		}

		// Elk zichtbaar element op het hoogste niveau telt als één melding.
		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="hm-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$root = $doc->getElementById( 'hm-root' );
		if ( null === $root ) {
			return 1;
		}

		$count = 0;
		foreach ( $root->childNodes as $node ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM-API.
			if ( XML_ELEMENT_NODE === $node->nodeType && ! in_array( strtolower( $node->nodeName ), array( 'script', 'style', 'link', 'template', 'noscript' ), true ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM-API.
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Knop in de adminbalk.
	 *
	 * @param \WP_Admin_Bar $admin_bar Adminbalk.
	 */
	public function add_node( \WP_Admin_Bar $admin_bar ): void {
		if ( 0 === $this->count || ! is_admin() ) {
			return;
		}

		$label = sprintf(
			/* translators: %d: aantal meldingen */
			_n( '%d melding', '%d meldingen', $this->count, 'hashtagmedia-toolkit' ),
			$this->count
		);

		$admin_bar->add_node(
			array(
				'id'     => self::NODE,
				'parent' => 'top-secondary',
				'title'  => sprintf(
					'<span class="ab-icon dashicons dashicons-bell" aria-hidden="true"></span><span class="ab-label hm-toolkit-notices-count">%1$s</span><span class="screen-reader-text">%2$s</span>',
					esc_html( number_format_i18n( $this->count ) ),
					esc_html( $label )
				),
				'href'   => '#',
				'meta'   => array(
					'title' => esc_attr__( 'Meldingen bekijken', 'hashtagmedia-toolkit' ),
				),
			)
		);
	}

	/**
	 * Meldingen in een <template>, zodat common.js ze niet verplaatst en
	 * ze pas in de pagina komen als het paneel opent.
	 */
	public function render_template(): void {
		if ( 0 === $this->count ) {
			return;
		}
		?>
		<template id="hm-toolkit-notices-template">
			<?php echo $this->html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ongewijzigde uitvoer van andere plugins, die zij zelf al zouden tonen. ?>
		</template>
		<div id="hm-toolkit-notices-panel" class="hm-toolkit-notices-panel" role="region" aria-label="<?php esc_attr_e( 'Meldingen', 'hashtagmedia-toolkit' ); ?>" hidden>
			<div class="hm-toolkit-notices-panel__head">
				<strong><?php esc_html_e( 'Meldingen', 'hashtagmedia-toolkit' ); ?></strong>
				<button type="button" class="button-link hm-toolkit-notices-panel__close"><?php esc_html_e( 'Sluiten', 'hashtagmedia-toolkit' ); ?></button>
			</div>
			<div class="hm-toolkit-notices-panel__body"></div>
		</div>
		<?php
	}

	/**
	 * Stylesheet en script, alleen voor beheerders.
	 */
	public function enqueue(): void {
		if ( ! hm_toolkit_is_agency_user() ) {
			return;
		}
		wp_enqueue_style( 'hm-toolkit-opruimen-notices', $this->assets['css'], array(), HM_TOOLKIT_VERSION );
		wp_enqueue_script(
			'hm-toolkit-opruimen-notices',
			$this->assets['js'],
			array(),
			HM_TOOLKIT_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
