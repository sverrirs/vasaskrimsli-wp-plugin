<?php
/**
 * The products in the web shop (Háskólaprent's Shopify store): name, address and prices of every product a book
 * links to. Read from the store's public product JSON and kept for six hours; when the store can not be reached,
 * the copy made by migration/baekur.py is used, so the page never waits on or breaks with the store.
 */

namespace Vasaskrimsli\Baekur;

final class Verslun {

	private const CACHE = 6 * HOUR_IN_SECONDS;
	private const RETRY = HOUR_IN_SECONDS; // After a failed read: try again in an hour, meanwhile the fallback.

	/**
	 * @param array  $fallback handle => product, from baekur.json.
	 * @param string $shop     The store's address.
	 * @return array handle => product, as fresh as the cache allows.
	 */
	public static function vorur( array $fallback, string $shop ): array {
		$out = array();
		foreach ( $fallback as $handle => $vara ) {
			$key   = 'vs_baekur_vara_' . md5( $handle );
			$cache = get_transient( $key );
			if ( false === $cache ) {
				$cache = self::fetch( $shop, (string) $handle ) ?? array( 'villa' => true );
				set_transient( $key, $cache, empty( $cache['villa'] ) ? self::CACHE : self::RETRY );
			}
			$out[ $handle ] = empty( $cache['villa'] ) ? $cache : $vara;
		}
		return $out;
	}

	private static function fetch( string $shop, string $handle ): ?array {
		$res = wp_remote_get( $shop . '/products/' . rawurlencode( $handle ) . '.json', array( 'timeout' => 3 ) );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}
		return self::parse( json_decode( wp_remote_retrieve_body( $res ), true ), $shop, $handle );
	}

	/**
	 * A product as the catalogue keeps it, from the store's product JSON; null when it is not a product.
	 * @return array{id: string, heiti: string, slod: string, afbrigdi: array<array{heiti: string, verd: int}>}|null
	 */
	public static function parse( $json, string $shop, string $handle ): ?array {
		$p = is_array( $json ) ? ( $json['product'] ?? null ) : null;
		if ( ! is_array( $p ) || empty( $p['variants'] ) || ! is_array( $p['variants'] ) ) {
			return null;
		}
		$afbrigdi = array();
		foreach ( $p['variants'] as $v ) {
			$title      = (string) ( $v['title'] ?? '' );
			$afbrigdi[] = array(
				'heiti' => 'Default Title' === $title ? '' : $title,
				'verd'  => (int) round( (float) ( $v['price'] ?? 0 ) ),
			);
		}
		return array(
			'id'       => (string) ( $p['id'] ?? '' ),
			'heiti'    => (string) ( $p['title'] ?? $handle ),
			'slod'     => rtrim( $shop, '/' ) . '/products/' . $handle,
			'afbrigdi' => $afbrigdi,
		);
	}

	/** 12800 -> "12.800 kr." */
	public static function kr( int $verd ): string {
		return number_format( $verd, 0, ',', '.' ) . ' kr.';
	}

	/** "12.800 kr." for one price, "frá 2.900 kr." when the options differ, '' without prices. */
	public static function verd( ?array $vara ): string {
		$prices = array_filter( array_column( $vara['afbrigdi'] ?? array(), 'verd' ) );
		if ( ! $prices ) {
			return '';
		}
		$low = min( $prices );
		return ( max( $prices ) > $low ? 'frá ' : '' ) . self::kr( $low );
	}
}
