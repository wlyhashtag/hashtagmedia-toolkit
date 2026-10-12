<?php
/**
 * Dupliceren als concept.
 *
 * Kopieert een bericht, pagina of eigen berichttype als concept, inclusief
 * alle meta (dus ACF-velden, Yoast-gegevens en de uitgelichte afbeelding)
 * en alle taxonomieën (categorieën, tags, eigen taxonomieën).
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit\Modules\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Link "Dupliceren" en de afhandeling ervan.
 */
final class Duplicate {

	/**
	 * Meta die niet mee mag naar de kopie.
	 */
	private const SKIP_META = array(
		'_edit_lock',
		'_edit_last',
		'_wp_old_slug',
		'_wp_old_date',
		'_wp_trash_meta_status',
		'_wp_trash_meta_time',
		'_wp_desired_post_slug',
		'_encloseme',
		'_pingme',
	);

	/**
	 * Hooks registreren.
	 */
	public function register(): void {
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_action( 'post_submitbox_misc_actions', array( $this, 'submitbox_link' ) );
		add_action( 'admin_post_hm_toolkit_duplicate', array( $this, 'handle' ) );
	}

	/**
	 * Mag de huidige gebruiker dit bericht dupliceren?
	 *
	 * @param \WP_Post $post Bericht.
	 * @return bool
	 */
	private static function can_duplicate( \WP_Post $post ): bool {
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! $type->show_ui || in_array( $post->post_type, array( 'attachment', 'revision', 'wp_block', 'acf-field-group', 'acf-field' ), true ) ) {
			return false;
		}
		return current_user_can( 'edit_post', $post->ID ) && current_user_can( $type->cap->create_posts );
	}

	/**
	 * URL om te dupliceren.
	 *
	 * @param \WP_Post $post Bericht.
	 * @return string
	 */
	private static function url( \WP_Post $post ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'hm_toolkit_duplicate',
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'hm_toolkit_duplicate_' . $post->ID
		);
	}

	/**
	 * Actielink in de lijst.
	 *
	 * @param array    $actions Acties.
	 * @param \WP_Post $post    Bericht.
	 * @return array
	 */
	public function row_action( $actions, $post ) {
		if ( is_array( $actions ) && $post instanceof \WP_Post && 'trash' !== $post->post_status && self::can_duplicate( $post ) ) {
			$actions['hm_toolkit_duplicate'] = sprintf(
				'<a href="%1$s" aria-label="%2$s">%3$s</a>',
				esc_url( self::url( $post ) ),
				/* translators: %s: titel */
				esc_attr( sprintf( __( '"%s" dupliceren als concept', 'hashtagmedia-toolkit' ), get_the_title( $post ) ) ),
				esc_html__( 'Dupliceren', 'hashtagmedia-toolkit' )
			);
		}
		return $actions;
	}

	/**
	 * Link in het blok Publiceren van de bewerkpagina.
	 *
	 * @param \WP_Post $post Bericht.
	 */
	public function submitbox_link( $post ): void {
		if ( ! $post instanceof \WP_Post || 'auto-draft' === $post->post_status || ! self::can_duplicate( $post ) ) {
			return;
		}
		printf(
			'<div class="misc-pub-section hm-toolkit-duplicate"><span class="dashicons dashicons-admin-page" aria-hidden="true" style="color:#8c8f94;margin-right:4px;"></span><a href="%1$s">%2$s</a></div>',
			esc_url( self::url( $post ) ),
			esc_html__( 'Dupliceren als concept', 'hashtagmedia-toolkit' )
		);
	}

	/**
	 * Dupliceren afhandelen.
	 */
	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce wordt hieronder gecontroleerd met het ID erin.
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'hm_toolkit_duplicate_' . $id );

		$post = $id ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || ! self::can_duplicate( $post ) ) {
			wp_die( esc_html__( 'Je mag dit niet dupliceren.', 'hashtagmedia-toolkit' ), '', array( 'response' => 403 ) );
		}

		$new_id = self::duplicate( $post );
		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ), '', array( 'response' => 500 ) );
		}

		wp_safe_redirect( (string) get_edit_post_link( $new_id, 'url' ) );
		exit;
	}

	/**
	 * Maakt de kopie.
	 *
	 * @param \WP_Post $post Origineel.
	 * @return int|\WP_Error Nieuw ID.
	 */
	public static function duplicate( \WP_Post $post ) {
		$new_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => $post->post_type,
					'post_status'    => 'draft',
					/* translators: %s: originele titel */
					'post_title'     => sprintf( __( '%s (kopie)', 'hashtagmedia-toolkit' ), $post->post_title ),
					'post_content'   => $post->post_content,
					'post_excerpt'   => $post->post_excerpt,
					'post_parent'    => $post->post_parent,
					'menu_order'     => $post->menu_order,
					'post_password'  => $post->post_password,
					'comment_status' => $post->comment_status,
					'ping_status'    => $post->ping_status,
					'post_author'    => get_current_user_id(),
				)
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		// Alle meta: ACF-velden, Yoast-gegevens, uitgelichte afbeelding, paginasjabloon.
		foreach ( get_post_meta( $post->ID ) as $key => $values ) {
			if ( in_array( $key, self::SKIP_META, true ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				add_post_meta( $new_id, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}

		// Alle taxonomieën.
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $new_id, array_map( 'intval', $terms ), $taxonomy );
			}
		}

		// Nog één keer opslaan, zodat plugins (zoals Yoast) de kopie met alle meta zien.
		wp_update_post( array( 'ID' => $new_id ) );

		/**
		 * Een bericht is gedupliceerd.
		 *
		 * @param int      $new_id Nieuw ID.
		 * @param \WP_Post $post   Origineel.
		 */
		do_action( 'hm_toolkit_post_duplicated', $new_id, $post );

		return $new_id;
	}
}
