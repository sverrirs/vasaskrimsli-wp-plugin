<?php
/**
 * POST /wp-json/vasaskrimsli/v1/pontun: the form sends an order here. Open to visitors, so it is guarded without a
 * login: a hidden field people never fill in, a signed time stamp from the page (an order sent less than five seconds
 * after the page loaded, or more than a week later, is refused) and at most five orders an hour from one address.
 *
 * A saved order is emailed to the recipients set in wp-admin (the owner and the printer), with the orderer's address
 * as Reply-To, and, if that is turned on, a confirmation with the number goes to the one who ordered.
 */

namespace Vasaskrimsli\Pontun;

final class Rest {

	private const MIN_SECONDS = 5;
	private const MAX_AGE     = WEEK_IN_SECONDS;
	private const PER_HOUR    = 5;

	public static function register(): void {
		register_rest_route( 'vasaskrimsli/v1', '/pontun', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( self::class, 'submit' ),
		) );
	}

	/** A time stamp signed with the site's salt, for the form. */
	public static function stamp( ?int $time = null ): string {
		$time = $time ?? time();
		return $time . '.' . substr( hash_hmac( 'sha256', (string) $time, wp_salt( 'nonce' ) ), 0, 20 );
	}

	/** Seconds since the stamp was made, or null when it is not one of ours. */
	public static function age( string $stamp ): ?int {
		[ $time, $sig ] = array_pad( explode( '.', $stamp, 2 ), 2, '' );
		return ctype_digit( $time ) && hash_equals( self::stamp( (int) $time ), $stamp ) ? time() - (int) $time : null;
	}

	public static function submit( \WP_REST_Request $req ) {
		$in = (array) $req->get_json_params();
		if ( '' !== ( $in['vefsida'] ?? '' ) ) {
			return new \WP_REST_Response( array( 'villur' => array( 'almennt' => 'Pöntunin var ekki send.' ) ), 400 ); // The hidden field.
		}
		$age = is_string( $in['t'] ?? null ) ? self::age( $in['t'] ) : null;
		if ( null === $age || $age > self::MAX_AGE ) {
			return new \WP_REST_Response( array( 'villur' => array( 'almennt' => 'Síðan er orðin of gömul. Endurhlaðið síðunni og sendið aftur.' ) ), 400 );
		}
		if ( $age < self::MIN_SECONDS ) {
			return new \WP_REST_Response( array( 'villur' => array( 'almennt' => 'Pöntunin var send of hratt. Reynið aftur eftir smástund.' ) ), 400 );
		}
		$key   = 'vs_pontun_ip_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$count = (int) get_transient( $key );
		if ( $count >= self::PER_HOUR ) {
			return new \WP_REST_Response( array( 'villur' => array( 'almennt' => 'Of margar pantanir á stuttum tíma. Reynið aftur síðar eða hafið samband.' ) ), 429 );
		}

		$checked = Pontun::check( $in, Block::config() );
		if ( $checked['villur'] ) {
			return new \WP_REST_Response( array( 'villur' => $checked['villur'] ), 400 );
		}
		Store::install(); // The table, if wp-admin has not been opened since the update.
		$saved = Store::add( $checked['pontun'] );
		if ( ! $saved ) {
			return new \WP_REST_Response( array( 'villur' => array( 'almennt' => 'Ekki tókst að vista pöntunina. Reynið aftur eða hafið samband.' ) ), 500 );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		self::mail( $saved['numer'], $saved['stofnad'], $checked['pontun'] );
		return new \WP_REST_Response( array( 'numer' => $saved['numer'] ) );
	}

	private static function mail( int $numer, string $stofnad, array $pontun ): void {
		$r     = $pontun['reitir'];
		$texti = Pontun::texti( $numer, mysql2date( 'j.n.Y H:i', $stofnad ), $pontun );
		$reply = 'Reply-To: ' . $r['netfang'];
		wp_mail( Store::recipients(), 'Ný pöntun nr. ' . $numer . ': ' . $r['vidtakandi'], $texti, array( $reply ) );
		if ( '0' !== get_option( Store::OPTION_STADF, '1' ) ) {
			wp_mail(
				$r['netfang'],
				'Pöntun nr. ' . $numer . ' móttekin',
				"Takk fyrir pöntunina. Hún hefur fengið númerið {$numer}. Reikningur verður sendur á " . $r['netfang_greid'] . ".\n\n" . $texti
			);
		}
	}
}
