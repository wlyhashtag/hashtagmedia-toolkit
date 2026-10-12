<?php
/**
 * Module Afbeeldingen.
 *
 * - WebP: WordPress zet JPEG en PNG bij uploaden zelf om via het officiële
 *   filter image_editor_output_format; het origineel blijft bewaard
 *   (original_image), net als bij WordPress' eigen "-scaled"-afbeeldingen.
 * - Maximaal 2560 px via big_image_size_threshold.
 * - Formaten: 150 (vierkant, mediabibliotheek), 480, 768, 1200, 1600 en de
 *   volledige afbeelding (max 2560); proportioneel, nooit bijgesneden.
 * - Focuspunt per afbeelding (post-meta _hm_focus), te kiezen in de
 *   mediabibliotheek. Het thema gebruikt het via hm_image( ..., fit ).
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Afbeeldingen;

use HashtagMedia\Toolkit\Module as Base;

defined( 'ABSPATH' ) || exit;

/**
 * WebP, formaten en focuspunt.
 */
final class Module extends Base {

	/**
	 * Langste zijde in pixels.
	 */
	public const MAX_SIZE = 2560;

	/**
	 * Post-meta voor het focuspunt ("x,y" in procenten).
	 */
	public const FOCUS_META = '_hm_focus';

	/**
	 * Breedtes van de WordPress-formaten (hoogte onbeperkt = niet bijsnijden).
	 */
	private const WIDTHS = array(
		'medium'       => 480,
		'medium_large' => 768,
		'large'        => 1200,
	);

	/**
	 * Extra formaat naast de WordPress-formaten.
	 */
	public const EXTRA_SIZE = 'hm-1600';

	/**
	 * Standaardwaarden.
	 *
	 * @return array
	 */
	public function defaults(): array {
		return array(
			'webp'    => true,
			'quality' => 82,
			'sizes'   => true,
			'focus'   => true,
		);
	}

	/**
	 * Kan de server WebP maken?
	 *
	 * @return bool
	 */
	public static function webp_supported(): bool {
		return wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	}

	/**
	 * Frontend, admin en REST (uploads lopen ook via REST).
	 */
	public function boot(): void {
		add_filter( 'big_image_size_threshold', array( $this, 'max_size' ), 20 );
		add_filter( 'max_srcset_image_width', array( $this, 'max_size' ), 20 );
		add_filter( 'wp_editor_set_quality', array( $this, 'quality' ), 20, 2 );

		if ( $this->get( 'webp' ) ) {
			add_filter( 'image_editor_output_format', array( $this, 'output_format' ), 20 );
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'sync_mime_type' ), 99, 2 );
		}

		if ( $this->get( 'sizes' ) ) {
			foreach ( self::WIDTHS as $name => $width ) {
				add_filter( 'pre_option_' . $name . '_size_w', static fn() => $width );
				add_filter( 'pre_option_' . $name . '_size_h', static fn() => 0 );
			}
			add_filter( 'pre_option_medium_crop', '__return_zero' );
			add_filter( 'pre_option_large_crop', '__return_zero' );
			add_action( 'after_setup_theme', array( $this, 'register_sizes' ), 99 );
			add_filter( 'intermediate_image_sizes_advanced', array( $this, 'limit_sizes' ), 20 );
			add_filter( 'image_size_names_choose', array( $this, 'size_names' ) );
		}

		if ( $this->get( 'focus' ) ) {
			add_filter( 'hm_toolkit_image_focus', array( $this, 'focus_css' ), 10, 2 );
		}
	}

	/**
	 * Alleen admin.
	 */
	public function admin(): void {
		if ( $this->get( 'focus' ) ) {
			require_once $this->path( 'admin/class-focus.php' );
			( new Focus( $this ) )->register();
		}
	}

	/**
	 * Langste zijde.
	 *
	 * @return int
	 */
	public function max_size(): int {
		return self::MAX_SIZE;
	}

	/**
	 * Kwaliteit voor JPEG en WebP.
	 *
	 * @param int    $quality   Huidige kwaliteit.
	 * @param string $mime_type MIME-type.
	 * @return int
	 */
	public function quality( $quality, $mime_type = '' ) {
		if ( in_array( $mime_type, array( 'image/webp', 'image/jpeg' ), true ) ) {
			return max( 40, min( 100, (int) $this->get( 'quality' ) ) );
		}
		return $quality;
	}

	/**
	 * JPEG en PNG worden WebP (hoofdbestand en alle formaten).
	 *
	 * @param array $formats Formaten: bron-MIME => doel-MIME.
	 * @return array
	 */
	public function output_format( $formats ) {
		if ( ! self::webp_supported() ) {
			return $formats;
		}
		$formats               = is_array( $formats ) ? $formats : array();
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	}

	/**
	 * Als het hoofdbestand WebP is geworden, het MIME-type van de bijlage
	 * daarop laten aansluiten.
	 *
	 * @param array $metadata      Metadata.
	 * @param int   $attachment_id Bijlage.
	 * @return array
	 */
	public function sync_mime_type( $metadata, $attachment_id ) {
		$file = (string) get_attached_file( (int) $attachment_id );
		if ( '' === $file || 'webp' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
			return $metadata;
		}
		if ( 'image/webp' !== get_post_mime_type( (int) $attachment_id ) ) {
			wp_update_post(
				array(
					'ID'             => (int) $attachment_id,
					'post_mime_type' => 'image/webp',
				)
			);
		}
		return $metadata;
	}

	/**
	 * Extra formaat 1600 registreren.
	 */
	public function register_sizes(): void {
		add_image_size( self::EXTRA_SIZE, 1600, 0, false );
	}

	/**
	 * Alleen de afgesproken formaten maken; WordPress' 1536 en 2048 vervallen.
	 *
	 * @param array $sizes Formaten.
	 * @return array
	 */
	public function limit_sizes( $sizes ) {
		if ( ! is_array( $sizes ) ) {
			return $sizes;
		}
		unset( $sizes['1536x1536'], $sizes['2048x2048'] );
		return $sizes;
	}

	/**
	 * Formaat 1600 kiesbaar in de editor.
	 *
	 * @param array $names Namen.
	 * @return array
	 */
	public function size_names( $names ) {
		if ( is_array( $names ) ) {
			$names[ self::EXTRA_SIZE ] = __( 'Extra groot (1600)', 'hashtagmedia-toolkit' );
		}
		return $names;
	}

	/**
	 * Focuspunt van een afbeelding als [x, y] in procenten.
	 *
	 * @param int $id Bijlage.
	 * @return array{0:float,1:float}|null
	 */
	public static function focus( int $id ): ?array {
		$value = (string) get_post_meta( $id, self::FOCUS_META, true );
		if ( ! preg_match( '/^(\d{1,3}(?:\.\d+)?),(\d{1,3}(?:\.\d+)?)$/', $value, $m ) ) {
			return null;
		}
		return array( min( 100.0, (float) $m[1] ), min( 100.0, (float) $m[2] ) );
	}

	/**
	 * CSS-waarde voor hm_image_focus().
	 *
	 * @param string $css Huidige waarde.
	 * @param int    $id  Bijlage.
	 * @return string
	 */
	public function focus_css( $css, $id ): string {
		$focus = $id ? self::focus( (int) $id ) : null;
		return $focus ? sprintf( '%s%% %s%%', self::number( $focus[0] ), self::number( $focus[1] ) ) : (string) $css;
	}

	/**
	 * Getal zonder overbodige decimalen.
	 *
	 * @param float $value Waarde.
	 * @return string
	 */
	public static function number( float $value ): string {
		return rtrim( rtrim( number_format( $value, 1, '.', '' ), '0' ), '.' );
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
	 * Tabblad Afbeeldingen.
	 */
	public function render_tab(): void {
		?>
		<?php if ( ! self::webp_supported() ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Deze server kan geen WebP maken (GD of Imagick zonder WebP). Uploads blijven JPEG of PNG; de rest werkt wel. Vraag de hosting om WebP-ondersteuning.', 'hashtagmedia-toolkit' ); ?></p></div>
		<?php endif; ?>
		<table class="form-table" role="presentation">
			<?php
			$this->checkbox_row(
				'webp',
				__( 'WebP', 'hashtagmedia-toolkit' ),
				__( 'JPEG en PNG bij uploaden omzetten naar WebP', 'hashtagmedia-toolkit' ),
				self::webp_supported() ? __( 'De server ondersteunt WebP. Het originele bestand blijft bewaard.', 'hashtagmedia-toolkit' ) : __( 'De server ondersteunt geen WebP.', 'hashtagmedia-toolkit' )
			);
			$this->input_row( 'quality', __( 'Kwaliteit', 'hashtagmedia-toolkit' ), 'number', __( 'Van 40 tot 100. Standaard 82: scherp en klein.', 'hashtagmedia-toolkit' ) );
			$this->checkbox_row(
				'sizes',
				__( 'Formaten', 'hashtagmedia-toolkit' ),
				__( 'Vaste formaten zonder bijsnijden', 'hashtagmedia-toolkit' ),
				/* translators: %d: maximale breedte in pixels */
				sprintf( __( '480, 768, 1200 en 1600 px breed, plus het origineel tot maximaal %d px. Het vierkante miniatuur (150) blijft voor de mediabibliotheek.', 'hashtagmedia-toolkit' ), self::MAX_SIZE )
			);
			$this->checkbox_row( 'focus', __( 'Focuspunt', 'hashtagmedia-toolkit' ), __( 'Focuspunt per afbeelding kiezen in de mediabibliotheek', 'hashtagmedia-toolkit' ), __( 'Het thema laat afbeeldingen passend vullen rond dit punt.', 'hashtagmedia-toolkit' ) );
			?>
		</table>
		<p class="description">
			<?php
			/* translators: %d: maximale breedte in pixels */
			echo esc_html( sprintf( __( 'Afbeeldingen groter dan %d px worden altijd verkleind. Bestaande afbeeldingen blijven zoals ze zijn; dit geldt voor nieuwe uploads.', 'hashtagmedia-toolkit' ), self::MAX_SIZE ) );
			?>
		</p>
		<?php
	}

	/**
	 * Import: kwaliteit binnen dezelfde grenzen als het tabblad.
	 *
	 * @param array $values Waarden uit het bestand.
	 * @return array
	 */
	public function import_settings( array $values ): array {
		$clean = parent::import_settings( $values );
		if ( isset( $clean['quality'] ) ) {
			$clean['quality'] = max( 40, min( 100, (int) $clean['quality'] ) );
		}
		return $clean;
	}

	/**
	 * Saneert het tabblad.
	 *
	 * @param array $input Invoer.
	 * @return array
	 */
	public function sanitize( array $input ): array {
		$clean            = $this->sanitize_checkboxes( $input, array( 'webp', 'sizes', 'focus' ) );
		$clean['quality'] = max( 40, min( 100, absint( $input['quality'] ?? 82 ) ) );
		return $clean;
	}
}
