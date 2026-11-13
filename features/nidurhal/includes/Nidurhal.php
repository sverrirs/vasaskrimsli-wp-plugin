<?php
/**
 * The pure parts of the download counter, testable without WordPress: which link is a download we count, which
 * visitor is a bot, and the anonymous key a visitor's repeat clicks are recognised by.
 */

namespace Vasaskrimsli\Nidurhal;

final class Nidurhal {

	/** User agents of crawlers, link previews, monitors and scripts. A browser never matches. */
	private const VELMENNI = '/bot\b|bot\/|crawl|spider|slurp|archiver|facebookexternalhit|embedly|preview|lighthouse|pagespeed|gtmetrix|monitor|uptime|curl|wget|python|httpclient|okhttp|java\/|go-http|libwww|perl|ruby|axios|node-fetch|headless|phantom|selenium|playwright|puppeteer/i';

	/**
	 * The path of a ZIP file on this site ("/namsefnispakkar/vasaskrimsli-namsefni-pakki-01.zip"), or null when the
	 * link goes elsewhere, is not a ZIP file, or has characters a file name here never has.
	 */
	public static function slod( string $url, string $home ): ?string {
		$u = parse_url( $url );
		$h = parse_url( $home );
		if ( ! is_array( $u ) || ! is_array( $h ) || empty( $u['host'] ) || empty( $u['path'] ) || empty( $h['host'] ) ) {
			return null;
		}
		if ( self::host( $u['host'] ) !== self::host( $h['host'] ) ) {
			return null;
		}
		$path = rawurldecode( $u['path'] );
		if ( ! preg_match( '#^/[A-Za-z0-9/_.\-]+\.zip$#i', $path ) || str_contains( $path, '..' ) || str_contains( $path, '//' ) ) {
			return null;
		}
		return $path;
	}

	/** vasaskrimsli.is and www.vasaskrimsli.is are the same site. */
	private static function host( string $host ): string {
		$host = strtolower( $host );
		return str_starts_with( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/** True for a crawler, a link preview, a monitor or a script, and for a request with no user agent at all. */
	public static function velmenni( string $ua ): bool {
		return '' === trim( $ua ) || 1 === preg_match( self::VELMENNI, $ua );
	}

	/** An anonymous key for one visitor (address and browser), so the address itself is never stored. */
	public static function gestur( string $ip, string $ua, string $salt ): string {
		return substr( hash_hmac( 'sha256', $ip . '|' . $ua, $salt ), 0, 24 );
	}
}
