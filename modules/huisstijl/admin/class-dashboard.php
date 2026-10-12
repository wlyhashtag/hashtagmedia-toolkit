<?php
/**
 * Klantdashboard: vijf vaste blokken plus technische info voor beheerders.
 *
 * Volgorde (vast, niet versleepbaar):
 * 1. Welkom en hulp       (links)
 * 2. Snelle acties        (rechts)
 * 3. Laatste inzendingen  (links, alleen met Gravity Forms)
 * 4. Recent bewerkt       (links)
 * 5. Handleiding          (rechts, alleen met een link)
 * Beheerders: Technische info (rechts).
 *
 * Andere modules voegen snelle acties toe via het filter
 * hm_toolkit_dashboard_actions, en eigen widgets met een id die begint met
 * hm_toolkit.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Huisstijl;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboardwidgets en het supportformulier.
 */
final class Dashboard {

	/**
	 * Wachttijd tussen twee supportmeldingen per gebruiker, in seconden.
	 */
	private const SUPPORT_WAIT = 60;

	/**
	 * De module.
	 *
	 * @var Module
	 */
	private Module $module;

	/**
	 * Constructor.
	 *
	 * @param Module $module Module.
	 */
	public function __construct( Module $module ) {
		$this->module = $module;
	}

	/**
	 * Hooks registreren.
	 */
	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'add_widgets' ), 20 );
		add_action( 'wp_dashboard_setup', array( $this, 'remove_other_widgets' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_hm_toolkit_support', array( $this, 'handle_support' ) );
		add_filter( 'screen_options_show_screen', array( $this, 'hide_screen_options' ), 10, 2 );

		// Vaste volgorde: opgeslagen volgorde, ingeklapte en verborgen blokken negeren.
		add_filter( 'get_user_option_meta-box-order_dashboard', '__return_empty_string' );
		add_filter( 'get_user_option_closedpostboxes_dashboard', '__return_empty_array' );
		add_filter( 'get_user_option_metaboxhidden_dashboard', '__return_empty_array' );
	}

	/**
	 * Widgets toevoegen.
	 */
	public function add_widgets(): void {
		wp_add_dashboard_widget( 'hm_toolkit_welcome', esc_html__( 'Welkom en hulp', 'hashtagmedia-toolkit' ), array( $this, 'render_welcome' ), null, null, 'normal', 'high' );
		wp_add_dashboard_widget( 'hm_toolkit_actions', esc_html__( 'Snelle acties', 'hashtagmedia-toolkit' ), array( $this, 'render_actions' ), null, null, 'side', 'high' );

		if ( self::can_view_entries() ) {
			wp_add_dashboard_widget( 'hm_toolkit_entries', esc_html__( 'Laatste formulierinzendingen', 'hashtagmedia-toolkit' ), array( $this, 'render_entries' ), null, null, 'normal', 'core' );
		}

		wp_add_dashboard_widget( 'hm_toolkit_recent', esc_html__( 'Recent bewerkt', 'hashtagmedia-toolkit' ), array( $this, 'render_recent' ), null, null, 'normal', 'low' );

		if ( '' !== (string) $this->module->get( 'manual_url' ) ) {
			wp_add_dashboard_widget( 'hm_toolkit_manual', esc_html__( 'Handleiding', 'hashtagmedia-toolkit' ), array( $this, 'render_manual' ), null, null, 'side', 'core' );
		}

		if ( hm_toolkit_is_agency_user() ) {
			wp_add_dashboard_widget( 'hm_toolkit_tech', esc_html__( 'Technische info', 'hashtagmedia-toolkit' ), array( $this, 'render_tech' ), null, null, 'side', 'low' );
		}
	}

	/**
	 * Alle andere widgets weg, zodat het dashboard altijd hetzelfde is.
	 */
	public function remove_other_widgets(): void {
		global $wp_meta_boxes;

		remove_action( 'welcome_panel', 'wp_welcome_panel' );

		if ( empty( $wp_meta_boxes['dashboard'] ) || ! is_array( $wp_meta_boxes['dashboard'] ) ) {
			return;
		}
		foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
			foreach ( (array) $priorities as $boxes ) {
				foreach ( array_keys( (array) $boxes ) as $id ) {
					if ( 0 !== strpos( (string) $id, 'hm_toolkit' ) ) {
						remove_meta_box( (string) $id, 'dashboard', $context );
					}
				}
			}
		}
	}

	/**
	 * Geen Schermopties op het dashboard: de blokken staan vast.
	 *
	 * @param bool       $show   Tonen.
	 * @param \WP_Screen $screen Scherm.
	 * @return bool
	 */
	public function hide_screen_options( $show, $screen ) {
		return ( $screen instanceof \WP_Screen && 'dashboard' === $screen->id ) ? false : $show;
	}

	/**
	 * Stylesheet en script, alleen op het dashboard.
	 *
	 * @param string $hook_suffix Huidige pagina.
	 */
	public function enqueue( $hook_suffix ): void {
		if ( 'index.php' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'hm-toolkit-huisstijl-dashboard', $this->module->url( 'assets/dashboard.css' ), array(), HM_TOOLKIT_VERSION );

		// Blokken vastzetten: niet slepen en niet inklappen.
		wp_add_inline_script(
			'dashboard',
			'jQuery(window).on("load",function(){var $=jQuery,s=$("#dashboard-widgets .meta-box-sortables");if(s.sortable("instance")){s.sortable("destroy");}$("#dashboard-widgets .postbox .hndle, #dashboard-widgets .postbox .handlediv").off("click.postboxes");});'
		);
	}

	/**
	 * Mag de gebruiker formulierinzendingen zien?
	 *
	 * @return bool
	 */
	private static function can_view_entries(): bool {
		return class_exists( 'GFAPI' ) && ( current_user_can( 'gravityforms_view_entries' ) || current_user_can( 'gform_full_access' ) );
	}

	/**
	 * Blok 1: welkom, contact en supportformulier.
	 */
	public function render_welcome(): void {
		$user    = wp_get_current_user();
		$name    = '' !== $user->first_name ? $user->first_name : $user->display_name;
		$email   = (string) $this->module->get( 'contact_email' );
		$hours   = (string) $this->module->get( 'contact_hours' );
		$support = is_email( (string) $this->module->get( 'support_email' ) );
		?>
		<div class="hm-dash-welcome">
			<p class="hm-dash-welcome__title">
				<?php
				/* translators: %s: voornaam van de gebruiker */
				printf( esc_html__( 'Welkom, %s', 'hashtagmedia-toolkit' ), esc_html( $name ) );
				?>
			</p>
			<p>
				<?php
				/* translators: %s: bedrijfsnaam van de klant */
				printf( esc_html__( 'Hier beheer je de website van %s. Kom je er niet uit? We helpen je graag.', 'hashtagmedia-toolkit' ), '<strong>' . esc_html( Module::company_name() ) . '</strong>' );
				?>
			</p>

			<?php if ( '' !== $email || '' !== $hours ) : ?>
				<div class="hm-dash-contact">
					<p class="hm-dash-contact__title"><?php esc_html_e( 'Contact met HashtagMedia', 'hashtagmedia-toolkit' ); ?></p>
					<?php if ( '' !== $email ) : ?>
						<p><span class="dashicons dashicons-email" aria-hidden="true"></span> <a href="<?php echo esc_url( 'mailto:' . antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></p>
					<?php endif; ?>
					<?php if ( '' !== $hours ) : ?>
						<p><span class="dashicons dashicons-clock" aria-hidden="true"></span> <span><?php echo nl2br( esc_html( $hours ) ); ?></span></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			if ( $support ) {
				$this->render_support_form();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Supportformulier met eventuele terugmelding.
	 */
	private function render_support_form(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen weergave van een vaste melding.
		$status   = isset( $_GET['hm-support'] ) ? sanitize_key( wp_unslash( $_GET['hm-support'] ) ) : '';
		$messages = array(
			'sent'    => array( 'success', __( 'Bedankt! Je vraag is verstuurd. We nemen zo snel mogelijk contact op.', 'hashtagmedia-toolkit' ) ),
			'error'   => array( 'error', __( 'Versturen is niet gelukt. Mail ons direct, dan kijken we ernaar.', 'hashtagmedia-toolkit' ) ),
			'invalid' => array( 'error', __( 'Vul een onderwerp en je vraag in.', 'hashtagmedia-toolkit' ) ),
			'wait'    => array( 'warning', __( 'Je hebt net al een vraag gestuurd. Probeer het over een minuut opnieuw.', 'hashtagmedia-toolkit' ) ),
		);
		?>
		<form id="hm-support" class="hm-dash-support" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<p class="hm-dash-contact__title"><?php esc_html_e( 'Stel je vraag', 'hashtagmedia-toolkit' ); ?></p>
			<?php if ( isset( $messages[ $status ] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $messages[ $status ][0] ); ?> inline" role="status"><p><?php echo esc_html( $messages[ $status ][1] ); ?></p></div>
			<?php endif; ?>
			<input type="hidden" name="action" value="hm_toolkit_support">
			<?php wp_nonce_field( 'hm_toolkit_support' ); ?>
			<p>
				<label for="hm-support-subject"><?php esc_html_e( 'Onderwerp', 'hashtagmedia-toolkit' ); ?></label>
				<input type="text" id="hm-support-subject" name="subject" class="widefat" maxlength="150" required>
			</p>
			<p>
				<label for="hm-support-message"><?php esc_html_e( 'Je vraag', 'hashtagmedia-toolkit' ); ?></label>
				<textarea id="hm-support-message" name="message" class="widefat" rows="4" maxlength="5000" required></textarea>
			</p>
			<p class="hm-dash-support__submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Vraag versturen', 'hashtagmedia-toolkit' ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Supportformulier verwerken: e-mail naar HashtagMedia.
	 */
	public function handle_support(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'Je hebt geen toegang tot deze actie.', 'hashtagmedia-toolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'hm_toolkit_support' );

		$to      = sanitize_email( (string) $this->module->get( 'support_email' ) );
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$user    = wp_get_current_user();
		$wait    = 'hm_toolkit_support_' . $user->ID;

		if ( ! is_email( $to ) ) {
			$status = 'error';
		} elseif ( '' === $subject || '' === $message ) {
			$status = 'invalid';
		} elseif ( get_transient( $wait ) ) {
			$status = 'wait';
		} else {
			$subject = mb_substr( $subject, 0, 150 );
			$message = mb_substr( $message, 0, 5000 );
			$site    = get_bloginfo( 'name' );

			$body = implode(
				"\n",
				array(
					$message,
					'',
					'---',
					/* translators: %s: naam van de gebruiker */
					sprintf( __( 'Van: %s', 'hashtagmedia-toolkit' ), $user->display_name . ' <' . $user->user_email . '>' ),
					/* translators: %s: bedrijfsnaam */
					sprintf( __( 'Klant: %s', 'hashtagmedia-toolkit' ), Module::company_name() ),
					/* translators: %s: URL van de website */
					sprintf( __( 'Website: %s', 'hashtagmedia-toolkit' ), home_url( '/' ) ),
					/* translators: %s: omgeving (Live, Staging) */
					sprintf( __( 'Omgeving: %s', 'hashtagmedia-toolkit' ), Module::environment()['label'] ),
				)
			);

			$headers = array();
			if ( is_email( $user->user_email ) ) {
				$headers[] = 'Reply-To: ' . str_replace( array( '"', "\r", "\n" ), '', $user->display_name ) . ' <' . $user->user_email . '>';
			}

			/* translators: 1: sitenaam, 2: onderwerp */
			$sent = wp_mail( $to, sprintf( __( '[Support %1$s] %2$s', 'hashtagmedia-toolkit' ), $site, $subject ), $body, $headers );

			if ( $sent ) {
				set_transient( $wait, 1, self::SUPPORT_WAIT );
				$status = 'sent';
			} else {
				$status = 'error';
			}
		}

		wp_safe_redirect( add_query_arg( 'hm-support', $status, admin_url( 'index.php' ) ) . '#hm_toolkit_welcome' );
		exit;
	}

	/**
	 * Snelle acties, aan te vullen door andere modules.
	 *
	 * @return array<int,array{label:string,url:string,icon:string}>
	 */
	private static function actions(): array {
		$actions = array();

		if ( current_user_can( 'edit_posts' ) ) {
			$actions[] = array(
				'label' => __( 'Nieuw bericht', 'hashtagmedia-toolkit' ),
				'url'   => admin_url( 'post-new.php' ),
				'icon'  => 'dashicons-edit',
			);
		}
		if ( current_user_can( 'upload_files' ) ) {
			$actions[] = array(
				'label' => __( 'Media uploaden', 'hashtagmedia-toolkit' ),
				'url'   => admin_url( 'media-new.php' ),
				'icon'  => 'dashicons-format-image',
			);
		}
		if ( self::can_view_entries() ) {
			$actions[] = array(
				'label' => __( 'Formulierinzendingen', 'hashtagmedia-toolkit' ),
				'url'   => admin_url( 'admin.php?page=gf_entries' ),
				'icon'  => 'dashicons-email-alt',
			);
		}

		/**
		 * Snelle acties op het dashboard. Elke actie: label, url, icon (dashicon).
		 * De frontend-editor zet hier later "Website bewerken" vooraan.
		 *
		 * @param array $actions Acties.
		 */
		$actions = apply_filters( 'hm_toolkit_dashboard_actions', $actions );

		return array_values(
			array_filter(
				(array) $actions,
				static function ( $action ) {
					return is_array( $action ) && ! empty( $action['label'] ) && ! empty( $action['url'] );
				}
			)
		);
	}

	/**
	 * Blok 2: snelle acties.
	 */
	public function render_actions(): void {
		$actions = self::actions();
		if ( empty( $actions ) ) {
			echo '<p>' . esc_html__( 'Geen acties beschikbaar.', 'hashtagmedia-toolkit' ) . '</p>';
			return;
		}
		?>
		<ul class="hm-dash-actions">
			<?php foreach ( $actions as $action ) : ?>
				<li>
					<a class="hm-dash-actions__button" href="<?php echo esc_url( $action['url'] ); ?>">
						<span class="dashicons <?php echo esc_attr( $action['icon'] ?? 'dashicons-arrow-right-alt' ); ?>" aria-hidden="true"></span>
						<span><?php echo esc_html( $action['label'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Blok 3: laatste vijf inzendingen van Gravity Forms.
	 */
	public function render_entries(): void {
		try {
			$entries = \GFAPI::get_entries(
				0,
				array( 'status' => 'active' ),
				array(
					'key'       => 'date_created',
					'direction' => 'DESC',
				),
				array(
					'offset'    => 0,
					'page_size' => 5,
				)
			);
			$unread  = \GFAPI::count_entries(
				0,
				array(
					'status'        => 'active',
					'field_filters' => array(
						array(
							'key'   => 'is_read',
							'value' => 0,
						),
					),
				)
			);
		} catch ( \Throwable $e ) {
			$entries = array();
			$unread  = 0;
		}

		if ( is_wp_error( $entries ) || ! is_array( $entries ) ) {
			$entries = array();
		}

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'Nog geen inzendingen.', 'hashtagmedia-toolkit' ) . '</p>';
			return;
		}

		$unread = is_numeric( $unread ) ? (int) $unread : 0;
		$titles = array();
		?>
		<?php if ( $unread > 0 ) : ?>
			<p class="hm-dash-entries__unread">
				<?php
				/* translators: %s: aantal ongelezen inzendingen */
				echo esc_html( sprintf( _n( '%s ongelezen inzending', '%s ongelezen inzendingen', $unread, 'hashtagmedia-toolkit' ), number_format_i18n( $unread ) ) );
				?>
			</p>
		<?php endif; ?>
		<ul class="hm-dash-list">
			<?php foreach ( $entries as $entry ) : ?>
				<?php
				$form_id = (int) ( $entry['form_id'] ?? 0 );
				if ( ! isset( $titles[ $form_id ] ) ) {
					$form               = \GFAPI::get_form( $form_id );
					$titles[ $form_id ] = is_array( $form ) && ! empty( $form['title'] ) ? (string) $form['title'] : __( 'Formulier', 'hashtagmedia-toolkit' );
				}
				$url  = admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $form_id . '&lid=' . (int) $entry['id'] );
				$time = strtotime( (string) ( $entry['date_created'] ?? '' ) . ' UTC' );
				$new  = empty( $entry['is_read'] );
				?>
				<li class="<?php echo $new ? 'is-unread' : ''; ?>">
					<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $titles[ $form_id ] ); ?></a>
					<?php if ( $new ) : ?>
						<span class="hm-dash-badge"><?php esc_html_e( 'Nieuw', 'hashtagmedia-toolkit' ); ?></span>
					<?php endif; ?>
					<?php if ( $time ) : ?>
						<span class="hm-dash-list__meta">
							<?php
							/* translators: %s: tijd geleden, bijv. "3 uur" */
							echo esc_html( sprintf( __( '%s geleden', 'hashtagmedia-toolkit' ), human_time_diff( $time ) ) );
							?>
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="hm-dash-more"><a href="<?php echo esc_url( admin_url( 'admin.php?page=gf_entries' ) ); ?>"><?php esc_html_e( 'Alle inzendingen', 'hashtagmedia-toolkit' ); ?></a></p>
		<?php
	}

	/**
	 * Blok 4: laatst bewerkte pagina's en berichten, met wie het deed.
	 */
	public function render_recent(): void {
		$types = array_values( array_diff( get_post_types( array( 'show_ui' => true ) ), array( 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'acf-field-group', 'acf-field', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page' ) ) );

		$query = new \WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'posts_per_page'         => 10,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$posts = array_slice(
			array_filter(
				$query->posts,
				static function ( $post ) {
					return current_user_can( 'edit_post', $post->ID );
				}
			),
			0,
			5
		);

		if ( empty( $posts ) ) {
			echo '<p>' . esc_html__( 'Nog niets bewerkt.', 'hashtagmedia-toolkit' ) . '</p>';
			return;
		}
		?>
		<ul class="hm-dash-list">
			<?php foreach ( $posts as $post ) : ?>
				<?php
				$author_id = (int) get_post_meta( $post->ID, '_edit_last', true );
				$author    = $author_id ? get_userdata( $author_id ) : false;
				$type      = get_post_type_object( $post->post_type );
				$title     = '' !== $post->post_title ? $post->post_title : __( '(geen titel)', 'hashtagmedia-toolkit' );
				?>
				<li>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID, 'url' ) ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php if ( $type ) : ?>
						<span class="hm-dash-list__type"><?php echo esc_html( $type->labels->singular_name ); ?></span>
					<?php endif; ?>
					<span class="hm-dash-list__meta">
						<?php
						// Concepten hebben soms geen GMT-tijd; dan omrekenen vanuit de lokale tijd.
						$gmt = '0000-00-00 00:00:00' !== $post->post_modified_gmt ? $post->post_modified_gmt : get_gmt_from_date( $post->post_modified );
						$ago = human_time_diff( (int) strtotime( $gmt . ' UTC' ) );
						if ( $author ) {
							/* translators: 1: tijd geleden, 2: naam */
							echo esc_html( sprintf( __( '%1$s geleden door %2$s', 'hashtagmedia-toolkit' ), $ago, $author->display_name ) );
						} else {
							/* translators: %s: tijd geleden */
							echo esc_html( sprintf( __( '%s geleden', 'hashtagmedia-toolkit' ), $ago ) );
						}
						?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Blok 5: handleiding.
	 */
	public function render_manual(): void {
		?>
		<p><?php esc_html_e( 'Uitleg over het beheren van je website, stap voor stap.', 'hashtagmedia-toolkit' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( (string) $this->module->get( 'manual_url' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Handleiding openen', 'hashtagmedia-toolkit' ); ?></a></p>
		<?php
	}

	/**
	 * Technische info, alleen voor HashtagMedia-beheerders.
	 */
	public function render_tech(): void {
		global $wp_version;

		$theme = wp_get_theme();
		$rows  = array(
			__( 'WordPress', 'hashtagmedia-toolkit' ) => $wp_version,
			__( 'PHP', 'hashtagmedia-toolkit' )       => PHP_VERSION,
			__( 'Omgeving', 'hashtagmedia-toolkit' )  => Module::environment()['label'] . ' (' . ( 'wp-config' === Module::environment()['source'] ? 'wp-config.php' : __( 'domein', 'hashtagmedia-toolkit' ) ) . ')',
			__( 'Thema', 'hashtagmedia-toolkit' )     => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			__( 'HashtagMedia Toolkit', 'hashtagmedia-toolkit' ) => HM_TOOLKIT_VERSION,
		);
		?>
		<table class="hm-dash-tech">
			<?php foreach ( $rows as $label => $value ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td><?php echo esc_html( (string) $value ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}
}
