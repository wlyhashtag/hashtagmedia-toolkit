<?php
/**
 * Module Content.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Content;

use HashtagMedia\Toolkit\Module as Base;

defined( 'ABSPATH' ) || exit;

/**
 * Dupliceren, SVG, Gutenberg uit, revisies en beschermde pagina's.
 */
final class Module extends Base {

	/**
	 * Hoogste aantal revisies dat je kunt instellen.
	 */
	private const MAX_REVISIONS = 50;

	/**
	 * Standaardwaarden.
	 *
	 * @return array
	 */
	public function defaults(): array {
		return array(
			'duplicate'       => true,
			'svg'             => true,
			'gutenberg_off'   => true,
			'limit_revisions' => true,
			'revisions'       => 5,
			'protect'         => true,
			'protected_pages' => array(),
		);
	}

	/**
	 * Frontend, admin en REST.
	 */
	public function boot(): void {
		if ( $this->get( 'svg' ) ) {
			require_once $this->path( 'includes/class-svg.php' );
			( new Svg( $this->path( 'lib/svg-sanitizer' ) ) )->register();
		}

		if ( $this->get( 'gutenberg_off' ) ) {
			require_once $this->path( 'includes/class-blocks.php' );
			Blocks::register();
		}

		if ( $this->get( 'limit_revisions' ) ) {
			add_filter( 'wp_revisions_to_keep', array( $this, 'revisions_to_keep' ), 20 );
		}

		if ( $this->get( 'protect' ) ) {
			add_filter( 'map_meta_cap', array( $this, 'protect_pages' ), 10, 4 );
			add_filter( 'display_post_states', array( $this, 'protected_state' ), 10, 2 );
		}
	}

	/**
	 * Alleen admin.
	 */
	public function admin(): void {
		if ( $this->get( 'duplicate' ) ) {
			require_once $this->path( 'admin/class-duplicate.php' );
			( new Duplicate() )->register();
		}
	}

	/**
	 * Aantal revisies dat bewaard blijft.
	 *
	 * @return int
	 */
	public function revisions_to_keep(): int {
		return max( 0, min( self::MAX_REVISIONS, (int) $this->get( 'revisions' ) ) );
	}

	/**
	 * Pagina's die WordPress zelf al belangrijk vindt: homepage,
	 * berichtenpagina en privacyverklaring.
	 *
	 * @return array<int,string> ID => reden
	 */
	public static function automatic_pages(): array {
		$pages = array();
		$front = (int) get_option( 'page_on_front' );
		$blog  = (int) get_option( 'page_for_posts' );
		$priv  = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $front && 'page' === get_option( 'show_on_front' ) ) {
			$pages[ $front ] = __( 'homepage', 'hashtagmedia-toolkit' );
		}
		if ( $blog ) {
			$pages[ $blog ] = __( 'berichtenpagina', 'hashtagmedia-toolkit' );
		}
		if ( $priv ) {
			$pages[ $priv ] = __( 'privacyverklaring', 'hashtagmedia-toolkit' );
		}
		return $pages;
	}

	/**
	 * Alle beschermde pagina-ID's.
	 *
	 * @return int[]
	 */
	private function protected_ids(): array {
		$ids = array_map( 'intval', (array) $this->get( 'protected_pages' ) );
		return array_values( array_unique( array_filter( array_merge( $ids, array_keys( self::automatic_pages() ) ) ) ) );
	}

	/**
	 * Klanten mogen beschermde pagina's niet verwijderen of in de
	 * prullenbak gooien. HashtagMedia-beheerders wel.
	 *
	 * @param string[] $caps    Benodigde rechten.
	 * @param string   $cap     Gevraagd recht.
	 * @param int      $user_id Gebruiker.
	 * @param array    $args    Extra argumenten (post-ID).
	 * @return string[]
	 */
	public function protect_pages( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'delete_post', 'delete_page' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		if ( in_array( (int) $args[0], $this->protected_ids(), true ) && ! hm_toolkit_is_agency_user( (int) $user_id ) ) {
			$caps[] = 'do_not_allow';
		}
		return $caps;
	}

	/**
	 * Label "Beschermd" in de paginalijst.
	 *
	 * @param array    $states Labels.
	 * @param \WP_Post $post   Pagina.
	 * @return array
	 */
	public function protected_state( $states, $post ) {
		if ( is_array( $states ) && $post instanceof \WP_Post && in_array( $post->ID, $this->protected_ids(), true ) ) {
			$states['hm_toolkit_protected'] = __( 'Beschermd', 'hashtagmedia-toolkit' );
		}
		return $states;
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
	 * Tabblad Content.
	 */
	public function render_tab(): void {
		?>
		<h2><?php esc_html_e( 'Onderdelen', 'hashtagmedia-toolkit' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$this->checkbox_row( 'duplicate', __( 'Dupliceren', 'hashtagmedia-toolkit' ), __( 'Link "Dupliceren" bij berichten, pagina\'s en eigen berichttypes', 'hashtagmedia-toolkit' ), __( 'De kopie wordt een concept, met ACF-velden, Yoast-gegevens, uitgelichte afbeelding en categorieën.', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'svg', __( 'SVG-upload', 'hashtagmedia-toolkit' ), __( 'SVG-bestanden uploaden, alleen voor HashtagMedia-beheerders', 'hashtagmedia-toolkit' ), __( 'Elke SVG wordt bij het uploaden opgeschoond: scripts en externe verwijzingen gaan eruit.', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'gutenberg_off', __( 'Gutenberg', 'hashtagmedia-toolkit' ), __( 'Blokeditor, blokwidgets en blok-CSS uitzetten', 'hashtagmedia-toolkit' ), __( 'Berichten en widgets gebruiken de klassieke editor; de frontend laadt geen blok-stylesheets.', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'limit_revisions', __( 'Revisies', 'hashtagmedia-toolkit' ), __( 'Aantal revisies per bericht beperken', 'hashtagmedia-toolkit' ) );
			$this->input_row( 'revisions', __( 'Maximaal aantal revisies', 'hashtagmedia-toolkit' ), 'number', __( 'Per bericht of pagina. 0 = geen revisies bewaren.', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row( 'protect', __( 'Belangrijke pagina\'s', 'hashtagmedia-toolkit' ), __( 'Klanten kunnen beschermde pagina\'s niet verwijderen', 'hashtagmedia-toolkit' ), __( 'HashtagMedia-beheerders kunnen dat wel. Beschermde pagina\'s krijgen het label "Beschermd" in de lijst.', 'hashtagmedia-toolkit' ) );
			?>
		</table>

		<h2><?php esc_html_e( 'Beschermde pagina\'s', 'hashtagmedia-toolkit' ); ?></h2>
		<?php $this->render_page_picker(); ?>
		<?php
	}

	/**
	 * Lijst van pagina's om te beschermen.
	 */
	private function render_page_picker(): void {
		$automatic = self::automatic_pages();
		$selected  = array_map( 'intval', (array) $this->get( 'protected_pages' ) );
		$pages     = get_pages(
			array(
				'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'sort_column' => 'menu_order,post_title',
			)
		);

		if ( empty( $pages ) ) {
			echo '<p>' . esc_html__( 'Er zijn nog geen pagina\'s.', 'hashtagmedia-toolkit' ) . '</p>';
			return;
		}
		?>
		<p><?php esc_html_e( 'De homepage, berichtenpagina en privacyverklaring zijn altijd beschermd. Kies hieronder welke pagina\'s daarnaast beschermd zijn.', 'hashtagmedia-toolkit' ); ?></p>
		<fieldset class="hm-checklist">
			<legend class="screen-reader-text"><?php esc_html_e( 'Beschermde pagina\'s', 'hashtagmedia-toolkit' ); ?></legend>
			<?php foreach ( $pages as $page ) : ?>
				<?php
				$id    = 'hm-content-page-' . $page->ID;
				$auto  = isset( $automatic[ $page->ID ] );
				$title = '' !== $page->post_title ? $page->post_title : __( '(geen titel)', 'hashtagmedia-toolkit' );
				$depth = count( get_post_ancestors( $page ) );
				?>
				<label class="hm-toggle" for="<?php echo esc_attr( $id ); ?>" style="<?php echo esc_attr( 'padding-left:' . ( $depth * 20 ) . 'px' ); ?>">
					<input type="checkbox" role="switch" class="hm-toggle__input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $this->field_name( 'protected_pages' ) ); ?>[]" value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php checked( $auto || in_array( $page->ID, $selected, true ) ); ?> <?php disabled( $auto ); ?>>
					<span class="hm-toggle__text">
						<?php echo esc_html( $title ); ?>
						<?php if ( $auto ) : ?>
							<em class="description">
								<?php
								/* translators: %s: soort pagina, bijv. homepage */
								printf( esc_html__( '(altijd, %s)', 'hashtagmedia-toolkit' ), esc_html( $automatic[ $page->ID ] ) );
								?>
							</em>
						<?php endif; ?>
					</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Saneert het tabblad.
	 *
	 * @param array $input Invoer.
	 * @return array
	 */
	public function sanitize( array $input ): array {
		$clean = $this->sanitize_checkboxes( $input, array( 'duplicate', 'svg', 'gutenberg_off', 'limit_revisions', 'protect' ) );

		$clean['revisions'] = max( 0, min( self::MAX_REVISIONS, absint( $input['revisions'] ?? 5 ) ) );

		$ids = array();
		foreach ( (array) ( $input['protected_pages'] ?? array() ) as $id ) {
			$id = absint( $id );
			if ( $id && 'page' === get_post_type( $id ) ) {
				$ids[] = $id;
			}
		}
		$clean['protected_pages'] = array_values( array_unique( $ids ) );

		return $clean;
	}
}
