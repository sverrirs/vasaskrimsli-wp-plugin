<?php
/**
 * The pure parts of the download counter, testable without WordPress: which file a link or download address is,
 * which visitor is a bot, and the anonymous key a visitor's repeats are recognised by.
 */

namespace Vasaskrimsli\Nidurhal;

final class Nidurhal {

	/** The folder (under the site's root) the ZIP files are in. */
	public const MAPPA = 'namsefnispakkar';

	/** User agents of crawlers, link previews, monitors and scripts. A browser never matches. */
	private const VELMENNI = '/bot\b|bot\/|crawl|spider|slurp|archiver|facebookexternalhit|embedly|preview|lighthouse|pagespeed|gtmetrix|monitor|uptime|curl|wget|python|httpclient|okhttp|java\/|go-http|libwww|perl|ruby|axios|node-fetch|headless|phantom|selenium|playwright|puppeteer/i';

	/** A file name as it may be: letters, digits, "-", "_" and "." only, ending in .zip, not hidden. */
	public static function heiti( string $name ): ?string {
		return preg_match( '/^[A-Za-z0-9_\-][A-Za-z0-9_.\-]*\.zip$/i', $name ) && ! str_contains( $name, '..' ) ? $name : null;
	}

	/**
	 * A file's short name, for the shortest download address (/s/1): the file name without ".zip", without the
	 * "vasaskrimsli-namsefni-" every file starts with, and "pakki-01" as just "1".
	 * "vasaskrimsli-namsefni-pakki-01.zip" is "1", "vasaskrimsli-namsefni-klubbur-og-safnmappa.zip" "klubbur-og-safnmappa".
	 */
	public static function kort( string $name ): string {
		$k = strtolower( preg_replace( '/\.zip$/i', '', $name ) );
		$k = preg_replace( '/^vasaskrimsli-namsefni-/', '', $k );
		return preg_match( '/^pakki-0*(\d+)$/', $k, $m ) ? $m[1] : $k;
	}

	/**
	 * The short name of the ZIP file a link on this site leads to: the download address (/s/1, or ?vs_saekja=1) or the
	 * file itself (/namsefnispakkar/vasaskrimsli-namsefni-pakki-01.zip), both "1". Null when the link goes elsewhere or
	 * is something else.
	 */
	public static function skra( string $url, string $home ): ?string {
		$u = parse_url( $url );
		$h = parse_url( $home );
		if ( ! is_array( $u ) || ! is_array( $h ) || empty( $u['host'] ) || empty( $h['host'] ) || self::host( $u['host'] ) !== self::host( $h['host'] ) ) {
			return null;
		}
		$path = rawurldecode( $u['path'] ?? '' );
		parse_str( $u['query'] ?? '', $q );
		if ( isset( $q['vs_saekja'] ) && is_string( $q['vs_saekja'] ) ) {
			return self::stutt( $q['vs_saekja'] );
		}
		if ( preg_match( '#^/s/([^/]+)/?$#', $path, $m ) ) {
			return self::stutt( $m[1] );
		}
		if ( preg_match( '#^/(?:[A-Za-z0-9_\-]+/)*([^/]+\.zip)$#i', $path, $m ) && null !== self::heiti( $m[1] ) ) {
			return self::kort( $m[1] );
		}
		return null;
	}

	/** A short name as it may be: lower-case letters, digits and "-" only. */
	public static function stutt( string $k ): ?string {
		return preg_match( '/^[a-z0-9][a-z0-9\-]{0,80}$/', $k ) ? $k : null;
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
