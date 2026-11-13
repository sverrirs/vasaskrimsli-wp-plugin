<?php
/**
 * POST /wp-json/vasaskrimsli/v1/nidurhal: the page's script reports a click on a download link (/s/1) or a ZIP file
 * here. Counted as a click ("smellir"), beside the downloads the download address counts itself (Saekja).
 *
 * Counted only when it looks like a person: the click came from the page's script in a real browser (not one driven
 * by automation), the page's signed stamp is with it, the user agent is not a bot's, the ZIP file is in the folder,
 * and the same visitor has not already been counted for that file in the last 30 minutes (and at most 60 an hour).
 * The visitor is recognised by a keyed hash of address and browser that lives only as long as those limits.
 */

namespace Vasaskrimsli\Nidurhal;

final class Rest {

	private const MAX_AGE   = 30 * DAY_IN_SECONDS; // Pages may be cached for a while.
	private const AFTUR     = 30 * MINUTE_IN_SECONDS;
	private const PER_HOUR  = 60;

	public static function register(): void {
		register_rest_route( 'vasaskrimsli/v1', '/nidurhal', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( self::class, 'click' ),
		) );
	}

	/** A time stamp signed with the site's salt, for the page. */
	public static function stamp( ?int $time = null ): string {
		$time = $time ?? time();
		return $time . '.' . substr( hash_hmac( 'sha256', 'nidurhal' . $time, wp_salt( 'nonce' ) ), 0, 20 );
	}

	private static function age( string $stamp ): ?int {
		$time = explode( '.', $stamp, 2 )[0];
		return ctype_digit( $time ) && hash_equals( self::stamp( (int) $time ), $stamp ) ? time() - (int) $time : null;
	}

	public static function click( \WP_REST_Request $req ): \WP_REST_Response {
		$in   = (array) $req->get_json_params();
		$ua   = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		$age  = is_string( $in['t'] ?? null ) ? self::age( $in['t'] ) : null;
		$kort = is_string( $in['slod'] ?? null ) ? Nidurhal::skra( $in['slod'], home_url( '/' ) ) : null;
		$skra = null === $kort ? null : Saekja::finna( $kort );
		if ( null === $age || $age < 0 || $age > self::MAX_AGE || null === $skra || Nidurhal::velmenni( $ua ) ) {
			return new \WP_REST_Response( array( 'talid' => false ) );
		}
		$gestur = Nidurhal::gestur( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), $ua, wp_salt( 'auth' ) );
		$aftur  = 'vs_nh_s_' . md5( $gestur . $skra );
		$klst   = 'vs_nh_k_' . $gestur;
		$count  = (int) get_transient( $klst );
		if ( get_transient( $aftur ) || $count >= self::PER_HOUR ) {
			return new \WP_REST_Response( array( 'talid' => false ) );
		}
		Store::install(); // The table, if wp-admin has not been opened since the update.
		if ( ! Store::add( $skra, 'smellir' ) ) {
			return new \WP_REST_Response( array( 'talid' => false ), 500 );
		}
		set_transient( $aftur, 1, self::AFTUR );
		set_transient( $klst, $count + 1, HOUR_IN_SECONDS );
		return new \WP_REST_Response( array( 'talid' => true ) );
	}
}
