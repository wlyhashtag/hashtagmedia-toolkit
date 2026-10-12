<?php
/**
 * Module Updates.
 *
 * Nieuwe versies komen uit de releases van de openbare GitHub-repo. Elke
 * release heeft de zip hashtagmedia-toolkit-X.Y.Z.zip (zie de workflow
 * .github/workflows/release.yml).
 *
 * Werkt via WordPress zelf: de pluginkop heeft "Update URI" naar GitHub, en
 * WordPress vraagt via de filter update_plugins_github.com of er een update
 * is. Daardoor verschijnt de update onder Plugins en Dashboard › Updates,
 * werkt de schakelaar voor automatische updates, en ziet ManageWP hem ook.
 * Een plugin met dezelfde naam op wordpress.org kan nooit over deze heen.
 *
 * De releasegegevens worden 12 uur bewaard (bij een fout 1 uur), zodat er
 * niet bij elk verzoek naar GitHub wordt gevraagd.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Updater;

use HashtagMedia\Toolkit\Module as Base;

defined( 'ABSPATH' ) || exit;

/**
 * Updates van de toolkit via GitHub-releases.
 */
final class Module extends Base {

	/**
	 * Repo op GitHub.
	 */
	public const REPO = 'wlyhashtag/hashtagmedia-toolkit';

	/**
	 * Map- en slugnaam van de plugin.
	 */
	private const SLUG = 'hashtagmedia-toolkit';

	/**
	 * Cache van de laatste release.
	 */
	private const CACHE = 'hm_toolkit_release';

	/**
	 * Frontend, admin en cron (automatische updates draaien via cron).
	 */
	public function boot(): void {
		add_filter( 'update_plugins_github.com', array( $this, 'check_update' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'after_update' ), 10, 2 );
	}

	/**
	 * Alleen admin.
	 */
	public function admin(): void {
		add_action( 'admin_post_hm_toolkit_check_updates', array( $this, 'handle_check' ) );
	}

	/**
	 * Bestandsnaam van de plugin, bv. hashtagmedia-toolkit/hashtagmedia-toolkit.php.
	 *
	 * @return string
	 */
	private static function basename(): string {
		return plugin_basename( HM_TOOLKIT_FILE );
	}

	/**
	 * Laatste release van GitHub (uit de cache als die er is).
	 *
	 * @param bool $force Cache overslaan.
	 * @return array{version:string,package:string,url:string,notes:string,date:string,checked?:int}|null
	 */
	public static function release( bool $force = false ): ?array {
		$cached = $force ? false : get_site_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return isset( $cached['version'] ) ? $cached : null;
		}

		$release = self::fetch();
		if ( null !== $release ) {
			$release['checked'] = time();
		}
		// Bij een fout een lege waarde bewaren, zodat we GitHub niet blijven bestoken.
		set_site_transient(
			self::CACHE,
			$release ?? array(
				'error'   => true,
				'checked' => time(),
			),
			$release ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS
		);
		return $release;
	}

	/**
	 * Laatste release ophalen bij de GitHub-API.
	 *
	 * @return array|null
	 */
	private static function fetch(): ?array {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
					'User-Agent'           => 'HashtagMedia-Toolkit/' . HM_TOOLKIT_VERSION . '; ' . home_url( '/' ),
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? self::parse( $data ) : null;
	}

	/**
	 * Release-gegevens van GitHub controleren en omzetten.
	 *
	 * @param array $data Antwoord van de API.
	 * @return array|null
	 */
	public static function parse( array $data ): ?array {
		if ( ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}

		$version = ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			return null;
		}

		// Alleen de zip met precies de verwachte naam, uit deze repo.
		$name    = self::SLUG . '-' . $version . '.zip';
		$prefix  = 'https://github.com/' . self::REPO . '/releases/download/';
		$package = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			$asset = is_array( $asset ) ? $asset : array();
			$url   = (string) ( $asset['browser_download_url'] ?? '' );
			if ( (string) ( $asset['name'] ?? '' ) === $name && 0 === strpos( $url, $prefix ) ) {
				$package = $url;
				break;
			}
		}
		if ( '' === $package ) {
			return null;
		}

		$url = (string) ( $data['html_url'] ?? '' );
		return array(
			'version' => $version,
			'package' => $package,
			'url'     => 0 === strpos( $url, 'https://github.com/' . self::REPO . '/' ) ? $url : 'https://github.com/' . self::REPO . '/releases',
			'notes'   => substr( (string) ( $data['body'] ?? '' ), 0, 20000 ),
			'date'    => (string) ( $data['published_at'] ?? '' ),
		);
	}

	/**
	 * WordPress vraagt of er een update is (filter update_plugins_github.com).
	 *
	 * @param array|false $update      Huidige waarde.
	 * @param array       $plugin_data Pluginkop.
	 * @param string      $plugin_file Bestandsnaam van de plugin.
	 * @return array|false
	 */
	public function check_update( $update, $plugin_data, $plugin_file ) {
		if ( self::basename() !== $plugin_file ) {
			return $update;
		}

		$release = self::release();
		if ( null === $release ) {
			return $update;
		}

		return array(
			'id'           => 'https://github.com/' . self::REPO,
			'slug'         => self::SLUG,
			'plugin'       => $plugin_file,
			'version'      => $release['version'],
			'new_version'  => $release['version'],
			'url'          => 'https://github.com/' . self::REPO,
			'package'      => $release['package'],
			'requires_php' => (string) ( $plugin_data['RequiresPHP'] ?? '' ),
			'requires'     => (string) ( $plugin_data['RequiresWP'] ?? '' ),
		);
	}

	/**
	 * Venster "Details bekijken" bij de update.
	 *
	 * @param false|object|array $result Huidig resultaat.
	 * @param string             $action Actie.
	 * @param object             $args   Argumenten.
	 * @return false|object|array
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || self::SLUG !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$release = self::release();
		if ( null === $release ) {
			return $result;
		}

		$notes = self::notes_html( $release['notes'] );
		$link  = sprintf(
			'<p><a href="%1$s" target="_blank" rel="noopener">%2$s</a></p>',
			esc_url( $release['url'] ),
			esc_html__( 'Deze release op GitHub bekijken', 'hashtagmedia-toolkit' )
		);

		return (object) array(
			'name'          => 'HashtagMedia Toolkit',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => '<a href="https://hashtagmedia.nl">HashtagMedia</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => '6.5',
			'requires_php'  => '8.1',
			'last_updated'  => $release['date'],
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'Beheer, opruiming en huisstijl voor websites van HashtagMedia.', 'hashtagmedia-toolkit' ) . '</p>',
				'changelog'   => $notes . $link,
			),
		);
	}

	/**
	 * Release-opmerkingen (Markdown van GitHub) eenvoudig als HTML.
	 *
	 * @param string $markdown Tekst.
	 * @return string
	 */
	private static function notes_html( string $markdown ): string {
		$html = '';
		$list = false;
		foreach ( preg_split( '/\R/', $markdown ) as $line ) {
			$line = trim( $line );
			$text = esc_html( $line );
			$text = (string) preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
			$text = (string) preg_replace_callback(
				'#https://[^\s<]+#',
				static function ( $m ) {
					$url = html_entity_decode( $m[0] );
					return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a>';
				},
				$text
			);
			$item = preg_match( '/^[*-]\s+/', $line );

			if ( $list && ! $item ) {
				$html .= '</ul>';
				$list  = false;
			}
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^#{1,6}\s+/', $line ) ) {
				$html .= '<h4>' . preg_replace( '/^#{1,6}\s+/', '', $text ) . '</h4>';
			} elseif ( $item ) {
				$html .= ( $list ? '' : '<ul>' ) . '<li>' . preg_replace( '/^[*-]\s+/', '', $text ) . '</li>';
				$list  = true;
			} else {
				$html .= '<p>' . $text . '</p>';
			}
		}
		if ( $list ) {
			$html .= '</ul>';
		}
		return wp_kses_post( $html );
	}

	/**
	 * Vangnet: heet de uitgepakte map anders dan hashtagmedia-toolkit, dan
	 * hernoemen, zodat de plugin actief blijft na de update.
	 *
	 * @param string|\WP_Error $source        Uitgepakte map.
	 * @param string           $remote_source Bovenliggende map.
	 * @param \WP_Upgrader     $upgrader      Upgrader.
	 * @param array            $hook_extra    Extra gegevens.
	 * @return string|\WP_Error
	 */
	public function fix_source_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( is_wp_error( $source ) || ! is_array( $hook_extra ) || self::basename() !== ( $hook_extra['plugin'] ?? '' ) ) {
			return $source;
		}

		$wanted = trailingslashit( $remote_source ) . self::SLUG . '/';
		if ( trailingslashit( $source ) === $wanted || ! $wp_filesystem ) {
			return $source;
		}
		if ( ! $wp_filesystem->exists( trailingslashit( $source ) . self::SLUG . '.php' ) ) {
			return new \WP_Error( 'hm_toolkit_package', __( 'Dit updatebestand bevat de HashtagMedia Toolkit niet.', 'hashtagmedia-toolkit' ) );
		}
		return $wp_filesystem->move( $source, $wanted ) ? $wanted : $source;
	}

	/**
	 * Na een update van de toolkit de cache legen.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader.
	 * @param array        $options  Gegevens van de update.
	 */
	public function after_update( $upgrader, $options ): void {
		if ( ! is_array( $options ) || 'plugin' !== ( $options['type'] ?? '' ) ) {
			return;
		}
		$plugins = (array) ( $options['plugins'] ?? array( $options['plugin'] ?? '' ) );
		if ( in_array( self::basename(), $plugins, true ) ) {
			delete_site_transient( self::CACHE );
		}
	}

	/**
	 * Knop "Nu controleren": cache legen en WordPress opnieuw laten kijken.
	 */
	public function handle_check(): void {
		if ( ! current_user_can( 'update_plugins' ) || ! hm_toolkit_is_agency_user() ) {
			wp_die( esc_html__( 'Je hebt geen toegang tot deze actie.', 'hashtagmedia-toolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'hm_toolkit_check_updates' );

		delete_site_transient( self::CACHE );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'hashtagmedia',
					'tab'        => $this->slug(),
					'hm-checked' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
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
	 * Eigen formulier (knop Nu controleren), geen instellingen.
	 *
	 * @return bool
	 */
	public function tab_form(): bool {
		return false;
	}

	/**
	 * Tabblad Updates.
	 */
	public function render_tab(): void {
		$release = self::release();
		$latest  = $release['version'] ?? '';
		$newer   = '' !== $latest && version_compare( $latest, HM_TOOLKIT_VERSION, '>' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen weergave van een vaste melding.
		if ( isset( $_GET['hm-checked'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Opnieuw gecontroleerd bij GitHub.', 'hashtagmedia-toolkit' ) );
		}
		?>
		<p><?php esc_html_e( 'Nieuwe versies van de toolkit komen van GitHub en verschijnen onder Plugins › Updates, net als andere plugins. ManageWP ziet ze ook.', 'hashtagmedia-toolkit' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Geïnstalleerd', 'hashtagmedia-toolkit' ); ?></th>
				<td><?php echo esc_html( HM_TOOLKIT_VERSION ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Nieuwste op GitHub', 'hashtagmedia-toolkit' ); ?></th>
				<td>
					<?php if ( null === $release ) : ?>
						<?php esc_html_e( 'Kon GitHub niet bereiken. Over een uur wordt het opnieuw geprobeerd, of klik op Nu controleren.', 'hashtagmedia-toolkit' ); ?>
					<?php else : ?>
						<a href="<?php echo esc_url( $release['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $latest ); ?></a>
						<?php if ( $newer ) : ?>
							&nbsp;<a class="button button-primary button-small" href="<?php echo esc_url( self_admin_url( 'plugins.php?plugin_status=upgrade' ) ); ?>"><?php esc_html_e( 'Bijwerken', 'hashtagmedia-toolkit' ); ?></a>
						<?php else : ?>
							&nbsp;<span class="description"><?php esc_html_e( 'Je hebt de nieuwste versie.', 'hashtagmedia-toolkit' ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Laatst gecontroleerd', 'hashtagmedia-toolkit' ); ?></th>
				<td>
					<?php
					$cache   = get_site_transient( self::CACHE );
					$checked = is_array( $cache ) ? (int) ( $cache['checked'] ?? 0 ) : 0;
					echo esc_html(
						$checked
							/* translators: %s: tijdsduur, bv. "5 minuten" */
							? sprintf( __( '%s geleden', 'hashtagmedia-toolkit' ), human_time_diff( $checked ) )
							: __( 'Onbekend', 'hashtagmedia-toolkit' )
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Automatisch bijwerken', 'hashtagmedia-toolkit' ); ?></th>
				<td>
					<?php
					$auto = in_array( self::basename(), (array) get_site_option( 'auto_update_plugins', array() ), true );
					echo esc_html( $auto ? __( 'Aan', 'hashtagmedia-toolkit' ) : __( 'Uit', 'hashtagmedia-toolkit' ) );
					?>
					<p class="description"><?php esc_html_e( 'Aan- of uitzetten onder Plugins, in de kolom Automatische updates.', 'hashtagmedia-toolkit' ); ?></p>
				</td>
			</tr>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hm_toolkit_check_updates">
			<?php wp_nonce_field( 'hm_toolkit_check_updates' ); ?>
			<?php submit_button( __( 'Nu controleren', 'hashtagmedia-toolkit' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}
}
