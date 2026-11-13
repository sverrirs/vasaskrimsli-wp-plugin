<?php
/**
 * The counts, in their own table ({prefix}vs_nidurhal): one row for each file and day with the number of downloads
 * counted that day. Nothing about the visitors is stored.
 */

namespace Vasaskrimsli\Nidurhal;

final class Store {

	public const CAPABILITY  = 'edit_pages';
	private const DB_VERSION = 1;
	private const OPTION_DB  = 'vs_nidurhal_db';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vs_nidurhal';
	}

	/** Creates the table when the plugin's table version is newer than the site's. */
	public static function install(): void {
		if ( (int) get_option( self::OPTION_DB, 0 ) >= self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( 'CREATE TABLE ' . self::table() . " (
			dagur date NOT NULL,
			skra varchar(191) NOT NULL,
			fjoldi int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (dagur,skra)
		) " . $wpdb->get_charset_collate() . ';' );
		update_option( self::OPTION_DB, self::DB_VERSION, false );
	}

	/** One more download of the file today (the site's own time zone). */
	public static function add( string $skra ): bool {
		global $wpdb;
		$dagur = current_time( 'Y-m-d' );
		$table = self::table();
		$found = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET fjoldi = fjoldi + 1 WHERE dagur = %s AND skra = %s", $dagur, $skra ) );
		if ( $found ) {
			return true;
		}
		// First download of this file today. Two at the same moment: the second insert fails on the key and updates.
		if ( $wpdb->insert( $table, array( 'dagur' => $dagur, 'skra' => $skra, 'fjoldi' => 1 ) ) ) {
			return true;
		}
		return (bool) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET fjoldi = fjoldi + 1 WHERE dagur = %s AND skra = %s", $dagur, $skra ) );
	}

	/**
	 * Each file with its downloads today, the last 7 and 30 days (today included) and in all, and the first day one
	 * was counted.
	 * @return list<array{skra: string, idag: int, vika: int, manudur: int, alls: int, fyrst: string}>
	 */
	public static function skrar(): array {
		global $wpdb;
		$idag = current_time( 'Y-m-d' );
		$d7   = gmdate( 'Y-m-d', strtotime( $idag . ' -6 days' ) );
		$d30  = gmdate( 'Y-m-d', strtotime( $idag . ' -29 days' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT skra, SUM(CASE WHEN dagur = %s THEN fjoldi ELSE 0 END) AS idag, SUM(CASE WHEN dagur >= %s THEN fjoldi ELSE 0 END) AS vika,
				SUM(CASE WHEN dagur >= %s THEN fjoldi ELSE 0 END) AS manudur, SUM(fjoldi) AS alls, MIN(dagur) AS fyrst
			FROM ' . self::table() . ' GROUP BY skra ORDER BY skra',
			$idag, $d7, $d30
		), ARRAY_A );
		return array_map( static fn ( $r ) => array(
			'skra'    => (string) $r['skra'],
			'idag'    => (int) $r['idag'],
			'vika'    => (int) $r['vika'],
			'manudur' => (int) $r['manudur'],
			'alls'    => (int) $r['alls'],
			'fyrst'   => (string) $r['fyrst'],
		), $rows ?: array() );
	}

	/** Downloads of all files on each of the last $days days, oldest first, days without any included as 0. */
	public static function dagar( int $days = 30 ): array {
		global $wpdb;
		$idag = current_time( 'Y-m-d' );
		$fra  = gmdate( 'Y-m-d', strtotime( $idag . ' -' . ( $days - 1 ) . ' days' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT dagur, SUM(fjoldi) AS n FROM ' . self::table() . ' WHERE dagur >= %s GROUP BY dagur', $fra ), ARRAY_A );
		$out  = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$out[ gmdate( 'Y-m-d', strtotime( $idag . ' -' . $i . ' days' ) ) ] = 0;
		}
		foreach ( $rows ?: array() as $r ) {
			$out[ (string) $r['dagur'] ] = (int) $r['n'];
		}
		return $out;
	}

	/** Every row, for the CSV. */
	public static function allt(): array {
		global $wpdb;
		return $wpdb->get_results( 'SELECT dagur, skra, fjoldi FROM ' . self::table() . ' ORDER BY dagur, skra', ARRAY_A ) ?: array();
	}
}
