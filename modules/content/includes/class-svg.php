<?php
/**
 * SVG-upload, alleen voor HashtagMedia-beheerders, altijd gesaneerd.
 *
 * Elke SVG gaat bij het uploaden door enshrined/svg-sanitize (dezelfde
 * bibliotheek als de plugin Safe SVG): scripts, event-handlers, externe
 * verwijzingen en onbekende elementen gaan eruit. Lukt saneren niet, dan
 * wordt de upload geweigerd.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Veilige SVG-upload.
 */
final class Svg {

	/**
	 * MIME-type van SVG.
	 */
	private const MIME = 'image/svg+xml';

	/**
	 * Map met de sanitizer.
	 *
	 * @var string
	 */
	private string $lib;

	/**
	 * Constructor.
	 *
	 * @param string $lib Map van enshrined/svg-sanitize (src).
	 */
	public function __construct( string $lib ) {
		$this->lib = trailingslashit( $lib );
	}

	/**
	 * Hooks registreren (ook voor REST-uploads, dus niet alleen in de admin).
	 */
	public function register(): void {
		add_filter( 'upload_mimes', array( $this, 'allow_mime' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'check_filetype' ), 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_upload' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'sanitize_upload' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'metadata' ), 10, 2 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_for_js' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'media_styles' ) );
	}

	/**
	 * Mag deze gebruiker SVG uploaden?
	 *
	 * @return bool
	 */
	private static function allowed(): bool {
		return hm_toolkit_is_agency_user();
	}

	/**
	 * SVG toevoegen aan toegestane bestandstypes (alleen beheerders).
	 *
	 * @param array $mimes Types.
	 * @return array
	 */
	public function allow_mime( $mimes ) {
		if ( is_array( $mimes ) && self::allowed() ) {
			$mimes['svg'] = self::MIME;
		}
		return $mimes;
	}

	/**
	 * WordPress herkent de inhoud van SVG niet altijd goed; extensie leidend
	 * maken voor .svg (de inhoud wordt hierna toch gesaneerd).
	 *
	 * @param array  $data     Gecontroleerde gegevens.
	 * @param string $file     Pad naar het bestand.
	 * @param string $filename Bestandsnaam.
	 * @param array  $mimes    Toegestane types.
	 * @return array
	 */
	public function check_filetype( $data, $file, $filename, $mimes ) {
		unset( $file );
		if ( ! self::allowed() || ! is_array( $data ) ) {
			return $data;
		}
		if ( 'svg' === strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) ) && ( ! is_array( $mimes ) || in_array( self::MIME, $mimes, true ) ) ) {
			$data['ext']  = 'svg';
			$data['type'] = self::MIME;
		}
		return $data;
	}

	/**
	 * Is dit bestand een SVG?
	 *
	 * @param array $file Uploadgegevens.
	 * @return bool
	 */
	private static function is_svg( array $file ): bool {
		$ext = strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) );
		return in_array( $ext, array( 'svg', 'svgz' ), true ) || self::MIME === ( $file['type'] ?? '' );
	}

	/**
	 * Sanitizer laden (alleen als hij nodig is en nog niet bestaat).
	 *
	 * @return \enshrined\svgSanitize\Sanitizer|null
	 */
	private function sanitizer() {
		if ( ! class_exists( '\enshrined\svgSanitize\Sanitizer' ) ) {
			spl_autoload_register(
				function ( $class_name ) {
					$prefix = 'enshrined\\svgSanitize\\';
					if ( 0 !== strpos( $class_name, $prefix ) ) {
						return;
					}
					$file = $this->lib . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
					if ( is_readable( $file ) ) {
						require_once $file;
					}
				}
			);
		}
		if ( ! class_exists( '\enshrined\svgSanitize\Sanitizer' ) ) {
			return null;
		}
		$sanitizer = new \enshrined\svgSanitize\Sanitizer();
		$sanitizer->removeRemoteReferences( true );
		return $sanitizer;
	}

	/**
	 * Saneert de SVG vóór WordPress hem opslaat.
	 *
	 * @param array $file Uploadgegevens.
	 * @return array
	 */
	public function sanitize_upload( $file ) {
		if ( ! is_array( $file ) || ! self::is_svg( $file ) ) {
			return $file;
		}

		if ( ! self::allowed() ) {
			$file['error'] = __( 'Alleen beheerders mogen SVG-bestanden uploaden.', 'hashtagmedia-toolkit' );
			return $file;
		}

		if ( 'svgz' === strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ) ) {
			$file['error'] = __( 'Gecomprimeerde SVG (.svgz) wordt niet ondersteund. Upload een gewone .svg.', 'hashtagmedia-toolkit' );
			return $file;
		}

		$path      = (string) ( $file['tmp_name'] ?? '' );
		$sanitizer = $this->sanitizer();
		$dirty     = ( '' !== $path && is_readable( $path ) ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Lokaal tijdelijk uploadbestand.
		$clean     = false;

		// Alleen iets dat op een SVG lijkt, en nooit met PHP-code erin.
		if ( $sanitizer && is_string( $dirty ) && false !== stripos( $dirty, '<svg' ) && false === strpos( $dirty, '<?php' ) && false === strpos( $dirty, '<?=' ) ) {
			try {
				$clean = $sanitizer->sanitize( $dirty );
			} catch ( \Throwable $e ) {
				$clean = false;
			}
		}

		if ( ! is_string( $clean ) || '' === trim( $clean ) ) {
			$file['error'] = __( 'Dit SVG-bestand kon niet veilig gemaakt worden en is niet geüpload.', 'hashtagmedia-toolkit' );
			return $file;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Lokaal tijdelijk uploadbestand overschrijven met de schone versie.
		if ( false === file_put_contents( $path, $clean ) ) {
			$file['error'] = __( 'Het SVG-bestand kon niet worden opgeslagen.', 'hashtagmedia-toolkit' );
			return $file;
		}

		$file['size'] = strlen( $clean );
		return $file;
	}

	/**
	 * Afmetingen uit de SVG lezen (viewBox of width/height).
	 *
	 * @param string $path Pad naar de SVG.
	 * @return array{0:int,1:int}|null
	 */
	private static function dimensions( string $path ): ?array {
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$svg = file_get_contents( $path, false, null, 0, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Lokaal bestand in uploads.
		if ( ! is_string( $svg ) || ! preg_match( '/<svg\b[^>]*>/i', $svg, $tag ) ) {
			return null;
		}
		$width  = preg_match( '/\swidth=["\']\s*([\d.]+)\s*(px)?\s*["\']/i', $tag[0], $w ) ? (float) $w[1] : 0;
		$height = preg_match( '/\sheight=["\']\s*([\d.]+)\s*(px)?\s*["\']/i', $tag[0], $h ) ? (float) $h[1] : 0;
		if ( ( ! $width || ! $height ) && preg_match( '/\sviewBox=["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*["\']/i', $tag[0], $v ) ) {
			$width  = (float) $v[1];
			$height = (float) $v[2];
		}
		return ( $width > 0 && $height > 0 ) ? array( (int) round( $width ), (int) round( $height ) ) : null;
	}

	/**
	 * Breedte en hoogte opslaan, zodat thema en mediabibliotheek ze kennen.
	 *
	 * @param array $metadata      Metadata.
	 * @param int   $attachment_id Bijlage.
	 * @return array
	 */
	public function metadata( $metadata, $attachment_id ) {
		if ( self::MIME !== get_post_mime_type( $attachment_id ) ) {
			return $metadata;
		}
		$metadata = is_array( $metadata ) ? $metadata : array();
		$size     = self::dimensions( (string) get_attached_file( $attachment_id ) );
		if ( $size ) {
			$metadata['width']  = $size[0];
			$metadata['height'] = $size[1];
		}
		$metadata['file'] = _wp_relative_upload_path( (string) get_attached_file( $attachment_id ) );
		return $metadata;
	}

	/**
	 * Mediabibliotheek: SVG als afbeelding tonen.
	 *
	 * @param array    $response   Gegevens voor JavaScript.
	 * @param \WP_Post $attachment Bijlage.
	 * @return array
	 */
	public function prepare_for_js( $response, $attachment ) {
		if ( ! is_array( $response ) || self::MIME !== ( $response['mime'] ?? '' ) ) {
			return $response;
		}
		$width  = (int) ( $response['width'] ?? 0 );
		$height = (int) ( $response['height'] ?? 0 );
		if ( ( ! $width || ! $height ) && $attachment instanceof \WP_Post ) {
			$size = self::dimensions( (string) get_attached_file( $attachment->ID ) );
			if ( $size ) {
				list( $width, $height ) = $size;
			}
		}
		$full              = array(
			'url'         => $response['url'],
			'width'       => $width ? $width : 512,
			'height'      => $height ? $height : 512,
			'orientation' => $width >= $height ? 'landscape' : 'portrait',
		);
		$response['image'] = array( 'src' => $response['url'] );
		$response['sizes'] = array(
			'full'      => $full,
			'medium'    => $full,
			'thumbnail' => $full,
		);
		return $response;
	}

	/**
	 * SVG's netjes laten passen in de rasterweergave van de mediabibliotheek.
	 */
	public function media_styles(): void {
		echo '<style>.attachment-preview .thumbnail img[src$=".svg"],.media-modal img[src$=".svg"]{width:100%;height:auto;padding:8px;box-sizing:border-box;}</style>';
	}
}
