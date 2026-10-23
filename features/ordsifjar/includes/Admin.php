<?php
/**
 * wp-admin screen (the editing grid) and the CSV download endpoint.
 */

namespace Vasaskrimsli\Ordsifjar;

final class Admin {

	const SLUG = 'vs-ordsifjar';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_vs_ordsifjar_csv', array( self::class, 'download' ) );
	}

	public static function menu(): void {
		$hook = add_menu_page( 'Orðsifjar', 'Orðsifjar', Store::CAPABILITY, self::SLUG, array( self::class, 'render' ), 'dashicons-book-alt', 21 );
		add_submenu_page( self::SLUG, 'Orðsifjar', 'Allar færslur', Store::CAPABILITY, self::SLUG, array( self::class, 'render' ) );
		add_submenu_page( self::SLUG, 'Flokkar', 'Flokkar', Store::CAPABILITY, 'edit-tags.php?taxonomy=' . Store::TAXONOMY . '&post_type=' . Store::POST_TYPE );
		add_submenu_page( self::SLUG, 'Ruslafata', 'Ruslafata', Store::CAPABILITY, 'edit.php?post_status=trash&post_type=' . Store::POST_TYPE );
		add_action( 'load-' . $hook, array( self::class, 'enqueue' ) );
		// Keep the Orðsifjar menu open on the taxonomy and trash screens.
		add_filter( 'parent_file', static function ( $parent ) {
			$screen = get_current_screen();
			return ( $screen && Store::POST_TYPE === $screen->post_type ) ? self::SLUG : $parent;
		} );
	}

	public static function enqueue(): void {
		add_action( 'admin_enqueue_scripts', static function () {
			\Vasaskrimsli\Assets::module( 'admin' );
		} );
	}

	public static function render(): void {
		$config = array(
			'rest'      => esc_url_raw( rest_url( Rest::NS . Rest::BASE ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'csv'       => array(
				'html'  => self::csv_url( array( 'snid' => '1' ) ),
				'plain' => self::csv_url( array( 'snid' => '0' ) ),
				'afrit' => self::csv_url( array( 'afrit' => '' ) ),
			),
			'flokkarUrl' => admin_url( 'edit-tags.php?taxonomy=' . Store::TAXONOMY . '&post_type=' . Store::POST_TYPE ),
			'trashUrl'   => admin_url( 'edit.php?post_status=trash&post_type=' . Store::POST_TYPE ),
			'revisionUrl' => admin_url( 'revision.php?revision=' ),
		);
		echo '<div class="wrap"><div id="vs-ordsifjar-admin"><p>Hleð…</p></div></div>';
		echo '<script type="application/json" id="vs-ordsifjar-admin-config">' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';
	}

	private static function csv_url( array $args ): string {
		return wp_nonce_url( add_query_arg( array( 'action' => 'vs_ordsifjar_csv' ) + $args, admin_url( 'admin-post.php' ) ), 'vs_ordsifjar_csv' );
	}

	/** Streams a CSV export, or a saved backup when ?afrit= names one. */
	public static function download(): void {
		if ( ! current_user_can( Store::CAPABILITY ) ) {
			wp_die( 'Þú hefur ekki aðgang að þessu.', 403 );
		}
		check_admin_referer( 'vs_ordsifjar_csv' );

		if ( isset( $_GET['afrit'] ) ) {
			$name = basename( sanitize_file_name( wp_unslash( $_GET['afrit'] ) ) );
			$path = Import::backup_dir() . $name;
			if ( ! preg_match( '/^ordsifjar-afrit-[\d-]+\.csv$/', $name ) || ! is_file( $path ) ) {
				wp_die( 'Afritið fannst ekki.', 404 );
			}
			self::send( $name, (string) file_get_contents( $path ) );
		}

		$formatted = isset( $_GET['snid'] ) && '1' === $_GET['snid'];
		$mode      = $formatted ? Csv::MODE_HTML : Csv::MODE_PLAIN;
		$name      = 'ordsifjar-' . ( $formatted ? 'med-snidi' : 'an-snids' ) . '-' . wp_date( 'Y-m-d' ) . '.csv';
		self::send( $name, Csv::export( array_values( Store::all() ), $mode ) );
	}

	private static function send( string $name, string $body ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . strlen( $body ) );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- CSV file download.
		exit;
	}
}
