<?php
/**
 * The download address, as short as it gets: /s/1 (or /?vs_saekja=1 without pretty permalinks; Nidurhal::kort makes
 * the short name from the file name) counts a download of namsefnispakkar/vasaskrimsli-namsefni-pakki-01.zip and sends the browser on
 * to the file with a 302, so the web server delivers it as before. A 302 and "no-store", not a 301: a browser keeps a
 * 301 and would go straight to the file next time, uncounted. The address has no ".zip" on purpose: a web server set
 * to serve .zip addresses as files would never pass it to WordPress. The /s/ rule comes before WordPress's own, so it
 * wins over a page called "s".
 *
 * Counted: a GET from a user agent that is not a bot's, by a visitor not logged in as an editor, and not the same
 * visitor and file again within 30 minutes. It counts the download starting; whether it finished is not known.
 */

namespace Vasaskrimsli\Nidurhal;

final class Saekja {

	private const QUERY_VAR = 'vs_saekja';
	private const REWRITE   = 'vs_nidurhal_rewrite';
	private const REGLA     = '2'; // Raise when the rule changes, so it is flushed again.
	private const AFTUR     = 30 * MINUTE_IN_SECONDS;

	public static function register(): void {
		add_filter( 'query_vars', static function ( $vars ) {
			$vars[] = self::QUERY_VAR;
			return $vars;
		} );
		add_action( 'init', array( self::class, 'rewrite' ) );
		add_action( 'parse_request', array( self::class, 'parse' ), 0 );
	}

	public static function rewrite(): void {
		add_rewrite_rule( '^s/([a-z0-9][a-z0-9\-]*)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
		if ( get_option( self::REWRITE ) !== self::REGLA ) { // Once, after the plugin brings the rule.
			flush_rewrite_rules( false );
			update_option( self::REWRITE, self::REGLA, true );
		}
	}

	/** The download address for a ZIP file in the folder: https://vasaskrimsli.is/s/1 */
	public static function url( string $name ): string {
		$k = Nidurhal::kort( $name );
		return get_option( 'permalink_structure' ) ? home_url( '/s/' . $k ) : add_query_arg( self::QUERY_VAR, $k, home_url( '/' ) );
	}

	/** The ZIP file in the folder with this short name, or null. */
	public static function finna( string $kort ): ?string {
		foreach ( self::skrar() as $name ) {
			if ( Nidurhal::kort( $name ) === $kort ) {
				return $name;
			}
		}
		return null;
	}

	/** The ZIP files in the folder, by name. */
	public static function skrar(): array {
		$files = glob( rtrim( ABSPATH, '/\\' ) . '/' . Nidurhal::MAPPA . '/*.zip' ) ?: array();
		$names = array_values( array_filter( array_map( static fn ( $f ) => Nidurhal::heiti( basename( $f ) ), $files ) ) );
		sort( $names );
		return $names;
	}

	public static function parse( \WP $wp ): void {
		if ( ! isset( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			return;
		}
		$kort = Nidurhal::stutt( strtolower( (string) $wp->query_vars[ self::QUERY_VAR ] ) );
		$name = null === $kort ? null : self::finna( $kort );
		nocache_headers();
		header( 'Cache-Control: no-store' );
		header( 'X-Robots-Tag: noindex' );
		if ( null === $name ) {
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'Skráin fannst ekki.';
			exit;
		}
		if ( 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			self::telja( $name );
		}
		wp_redirect( home_url( '/' . Nidurhal::MAPPA . '/' . rawurlencode( $name ) ), 302, 'Vasaskrimsli' );
		exit;
	}

	private static function telja( string $name ): void {
		$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		if ( Nidurhal::velmenni( $ua ) || current_user_can( 'edit_posts' ) ) {
			return;
		}
		$gestur = Nidurhal::gestur( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), $ua, wp_salt( 'auth' ) );
		$key    = 'vs_nh_d_' . md5( $gestur . $name );
		if ( get_transient( $key ) ) {
			return;
		}
		Store::install();
		if ( Store::add( $name, 'nidurhal' ) ) {
			set_transient( $key, 1, self::AFTUR );
		}
	}
}
