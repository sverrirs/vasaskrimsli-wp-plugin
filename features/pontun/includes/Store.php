<?php
/**
 * The orders, in their own table ({prefix}vs_pantanir). Every order gets the next number: one more than the highest so
 * far, or the starting number set in wp-admin if that is higher, so numbers run in sequence without gaps. The number
 * is a UNIQUE column, so two orders sent at the same moment can not get the same one (the second retries).
 */

namespace Vasaskrimsli\Pontun;

final class Store {

	public const CAPABILITY = 'edit_pages';
	public const DB_VERSION = 1;
	public const STADA      = array(
		'ny'        => 'Ný',
		'afgreidd'  => 'Afgreidd',
		'reikningur' => 'Reikningur sendur',
		'haett'     => 'Hætt við',
	);
	private const OPTION_DB    = 'vs_pontun_db';
	private const DELETED      = 'eytt';
	public const OPTION_UPPHAF = 'vs_pontun_upphaf';    // The lowest number an order may get.
	public const OPTION_TIL    = 'vs_pontun_vidtakendur'; // Who gets each order, one address a line.
	public const OPTION_STADF  = 'vs_pontun_stadfesting'; // '1': a confirmation to the one who ordered.

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vs_pantanir';
	}

	/** Creates or updates the table when the plugin's table version is newer than the site's. */
	public static function install(): void {
		if ( (int) get_option( self::OPTION_DB, 0 ) >= self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( 'CREATE TABLE ' . self::table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			numer bigint(20) unsigned NOT NULL,
			stofnad datetime DEFAULT NULL,
			stada varchar(20) NOT NULL DEFAULT 'ny',
			netfang varchar(200) NOT NULL DEFAULT '',
			gogn longtext NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY numer (numer)
		) " . $wpdb->get_charset_collate() . ';' );
		update_option( self::OPTION_DB, self::DB_VERSION, false );
	}

	/** The number the next order will get. */
	public static function next_number(): int {
		global $wpdb;
		$max = (int) $wpdb->get_var( 'SELECT MAX(numer) FROM ' . self::table() );
		return max( $max + 1, (int) get_option( self::OPTION_UPPHAF, 1 ) );
	}

	/**
	 * Saves an order and gives it the next number.
	 * @return array{numer: int, stofnad: string}|null null when it could not be saved.
	 */
	public static function add( array $pontun ): ?array {
		global $wpdb;
		$stofnad = current_time( 'mysql' );
		for ( $try = 0; $try < 5; $try++ ) {
			$numer = self::next_number();
			$ok    = $wpdb->insert( self::table(), array(
				'numer'   => $numer,
				'stofnad' => $stofnad,
				'stada'   => 'ny',
				'netfang' => (string) ( $pontun['reitir']['netfang'] ?? '' ),
				'gogn'    => wp_json_encode( $pontun, JSON_UNESCAPED_UNICODE ),
			) );
			if ( $ok ) {
				return array( 'numer' => $numer, 'stofnad' => $stofnad );
			}
		}
		return null;
	}

	/** Every order, newest first: rows with gogn decoded. */
	public static function all(): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE stada <> %s ORDER BY numer DESC', self::DELETED ), ARRAY_A ) ?: array();
		return array_map( array( self::class, 'decode' ), $rows );
	}

	public static function get( int $numer ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE numer = %d AND stada <> %s', $numer, self::DELETED ), ARRAY_A );
		return $row ? self::decode( $row ) : null;
	}

	public static function set_status( int $numer, string $stada ): void {
		global $wpdb;
		if ( isset( self::STADA[ $stada ] ) ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET stada = %s WHERE numer = %d AND stada <> %s', $stada, $numer, self::DELETED ) );
		}
	}

	public static function delete( int $numer ): void {
		global $wpdb;
		// Erase the order details atomically but reserve the number, including when the newest order is deleted.
		$wpdb->update(
			self::table(),
			array( 'stofnad' => null, 'stada' => self::DELETED, 'netfang' => '', 'gogn' => '' ),
			array( 'numer' => $numer )
		);
	}

	private static function decode( array $row ): array {
		$row['numer'] = (int) $row['numer'];
		$row['gogn']  = json_decode( (string) $row['gogn'], true ) ?: array( 'reitir' => array(), 'linur' => array() );
		return $row;
	}

	/** Who gets each order: the addresses set in wp-admin, or the site's admin address. */
	public static function recipients(): array {
		$list = array_filter( array_map( 'trim', preg_split( '/[\s,;]+/', (string) get_option( self::OPTION_TIL, '' ) ) ), 'is_email' );
		return $list ? array_values( array_unique( $list ) ) : array( get_option( 'admin_email' ) );
	}
}
