<?php
/**
 * The counts, in their own table ({prefix}vs_nidurhal): one row for each ZIP file and day with the downloads
 * through the download address ("nidurhal") and the people's clicks on the file's links ("smellir") that day. Nothing
 * about the visitors is stored.
 */

namespace Vasaskrimsli\Nidurhal;

final class Store {

	public const CAPABILITY  = 'edit_pages';
	private const DB_VERSION = 2;
	private const OPTION_DB  = 'vs_nidurhal_db';
	private const DALKAR     = array( 'nidurhal', 'smellir' );

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vs_nidurhal';
	}

	/** Creates or updates the table when the plugin's table version is newer than the site's. */
	public static function install(): void {
		if ( (int) get_option( self::OPTION_DB, 0 ) >= self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( 'CREATE TABLE ' . self::table() . " (
			dagur date NOT NULL,
			skra varchar(191) NOT NULL,
			nidurhal int(10) unsigned NOT NULL DEFAULT 0,
			smellir int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (dagur,skra)
		) " . $wpdb->get_charset_collate() . ';' );
		update_option( self::OPTION_DB, self::DB_VERSION, false );
	}

	/** One more download ("nidurhal") or click ("smellir") of the file today (the site's own time zone). */
	public static function add( string $skra, string $dalkur ): bool {
		if ( ! in_array( $dalkur, self::DALKAR, true ) ) {
			return false;
		}
		global $wpdb;
		$dagur = current_time( 'Y-m-d' );
		$table = self::table();
		$sql   = $wpdb->prepare( "UPDATE {$table} SET {$dalkur} = {$dalkur} + 1 WHERE dagur = %s AND skra = %s", $dagur, $skra );
		if ( $wpdb->query( $sql ) ) {
			return true;
		}
		// The first today. Two at the same moment: the second insert fails on the key and updates instead.
		return (bool) $wpdb->insert( $table, array( 'dagur' => $dagur, 'skra' => $skra, $dalkur => 1 ) ) || (bool) $wpdb->query( $sql );
	}

	/**
	 * Each file with its downloads today, the last 7 and 30 days (today included) and in all, its clicks in
	 * all, and the first day anything was counted.
	 * @return array<string, array{idag: int, vika: int, manudur: int, alls: int, smellir: int, fyrst: string}>
	 */
	public static function skrar(): array {
		global $wpdb;
		$idag = current_time( 'Y-m-d' );
		$d7   = gmdate( 'Y-m-d', strtotime( $idag . ' -6 days' ) );
		$d30  = gmdate( 'Y-m-d', strtotime( $idag . ' -29 days' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT skra, SUM(CASE WHEN dagur = %s THEN nidurhal ELSE 0 END) AS idag, SUM(CASE WHEN dagur >= %s THEN nidurhal ELSE 0 END) AS vika,
				SUM(CASE WHEN dagur >= %s THEN nidurhal ELSE 0 END) AS manudur, SUM(nidurhal) AS alls, SUM(smellir) AS smellir, MIN(dagur) AS fyrst
			FROM ' . self::table() . ' GROUP BY skra',
			$idag, $d7, $d30
		), ARRAY_A );
		$out = array();
		foreach ( $rows ?: array() as $r ) {
			$out[ (string) $r['skra'] ] = array(
				'idag'    => (int) $r['idag'],
				'vika'    => (int) $r['vika'],
				'manudur' => (int) $r['manudur'],
				'alls'    => (int) $r['alls'],
				'smellir' => (int) $r['smellir'],
				'fyrst'   => (string) $r['fyrst'],
			);
		}
		return $out;
	}

	/** Downloads of all files on each of the last $days days, oldest first, days without any as 0. */
	public static function dagar( int $days = 30 ): array {
		global $wpdb;
		$idag = current_time( 'Y-m-d' );
		$fra  = gmdate( 'Y-m-d', strtotime( $idag . ' -' . ( $days - 1 ) . ' days' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT dagur, SUM(nidurhal) AS n FROM ' . self::table() . ' WHERE dagur >= %s GROUP BY dagur', $fra ), ARRAY_A );
		$out  = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$out[ gmdate( 'Y-m-d', strtotime( $idag . ' -' . $i . ' days' ) ) ] = 0;
		}
		foreach ( $rows ?: array() as $r ) {
			$out[ (string) $r['dagur'] ] = (int) $r['n'];
		}
		return $out;
	}

	/** Downloads of one file in all. */
	public static function alls( string $skra ): int {
		global $wpdb;
		self::install();
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(nidurhal) FROM ' . self::table() . ' WHERE skra = %s', $skra ) );
	}

	/** Every row, for the CSV. */
	public static function allt(): array {
		global $wpdb;
		return $wpdb->get_results( 'SELECT dagur, skra, nidurhal, smellir FROM ' . self::table() . ' ORDER BY dagur, skra', ARRAY_A ) ?: array();
	}
}
