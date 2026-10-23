<?php
/**
 * CSV import in two steps: preview (changes nothing) and apply.
 *
 * Before applying, the current data is written to a formatted CSV backup in
 * wp-content/uploads/vasaskrimsli-backups/, so any import can be undone by importing that file.
 */

namespace Vasaskrimsli\Ordsifjar;

final class Import {

	const PREVIEW_TTL = HOUR_IN_SECONDS;

	public static function preview( string $csv ): array {
		$plan  = Csv::diff( Store::all(), Csv::parse( $csv ), array( Sanitize::class, 'html' ) );
		$token = strtolower( wp_generate_password( 24, false ) );
		set_transient(
			'vs_ordsifjar_import_' . $token,
			array(
				'plan'        => $plan,
				'user'        => get_current_user_id(),
				'fingerprint' => self::fingerprint(),
			),
			self::PREVIEW_TTL
		);
		return array( 'token' => $token ) + $plan;
	}

	/** @return array|\WP_Error */
	public static function apply( string $token ) {
		$key    = 'vs_ordsifjar_import_' . $token;
		$stored = get_transient( $key );
		if ( ! is_array( $stored ) || (int) $stored['user'] !== get_current_user_id() ) {
			return new \WP_Error( 'vs_ordsifjar_import_expired', 'Forskoðunin er útrunnin. Hladdu skránni upp aftur.', array( 'status' => 410 ) );
		}
		if ( self::fingerprint() !== $stored['fingerprint'] ) {
			delete_transient( $key );
			return new \WP_Error( 'vs_ordsifjar_import_stale', 'Gögnunum var breytt eftir forskoðunina. Hladdu skránni upp aftur til að sjá nýja forskoðun.', array( 'status' => 409 ) );
		}
		$plan = $stored['plan'];
		if ( $plan['errors'] ) {
			return new \WP_Error( 'vs_ordsifjar_import_errors', 'Lagaðu villurnar í skránni og reyndu aftur.', array( 'status' => 400 ) );
		}
		delete_transient( $key );

		$backup = self::backup();
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}

		$created = 0;
		$updated = 0;
		$failed  = array();
		foreach ( $plan['create'] as $row ) {
			$r = Store::save( $row['fields'] );
			is_wp_error( $r ) ? $failed[] = "Lína {$row['line']}: " . $r->get_error_message() : ++$created;
		}
		foreach ( $plan['update'] as $row ) {
			$fields = array_map( static function ( $c ) {
				return $c['to'];
			}, $row['changes'] );
			$r = Store::save( $fields, (int) $row['id'] );
			is_wp_error( $r ) ? $failed[] = "Lína {$row['line']}: " . $r->get_error_message() : ++$updated;
		}
		return array( 'created' => $created, 'updated' => $updated, 'failed' => $failed, 'backup' => $backup );
	}

	/** Seeds an empty install from the bundled data. Never runs if any entry exists, even in the trash. */
	public static function seed(): void {
		if ( ! Store::kynslodir() ) {
			$k = json_decode( (string) file_get_contents( VASASKRIMSLI_DIR . 'features/ordsifjar/data/kynslodir.json' ), true );
			Store::save_kynslodir( is_array( $k ) ? $k : array() );
		}
		if ( Store::count_all_statuses() > 0 ) {
			return;
		}
		$plan = Csv::diff( array(), Csv::parse( (string) file_get_contents( VASASKRIMSLI_DIR . 'features/ordsifjar/data/seed.csv' ) ), array( Sanitize::class, 'html' ) );
		foreach ( $plan['create'] as $row ) {
			Store::save( $row['fields'] );
		}
	}

	/** Writes the current data to a dated formatted CSV and returns its file name. */
	public static function backup() {
		$dir = self::backup_dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'vs_ordsifjar_backup', 'Gat ekki búið til afritamöppu. Ekkert var flutt inn.', array( 'status' => 500 ) );
		}
		if ( ! file_exists( $dir . '.htaccess' ) ) {
			file_put_contents( $dir . '.htaccess', "Require all denied\nDeny from all\n" );
			file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" );
		}
		$name = 'ordsifjar-afrit-' . wp_date( 'Y-m-d-His' ) . '.csv';
		if ( false === file_put_contents( $dir . $name, Csv::export( array_values( Store::all() ), Csv::MODE_HTML ) ) ) {
			return new \WP_Error( 'vs_ordsifjar_backup', 'Gat ekki vistað afrit. Ekkert var flutt inn.', array( 'status' => 500 ) );
		}
		return $name;
	}

	public static function backup_dir(): string {
		return trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'vasaskrimsli-backups/';
	}

	/** Backups, newest first. */
	public static function backups(): array {
		$files = glob( self::backup_dir() . 'ordsifjar-afrit-*.csv' ) ?: array();
		rsort( $files );
		return array_map( 'basename', $files );
	}

	/** Changes whenever any entry changes, so a stale preview is never applied. */
	private static function fingerprint(): string {
		return md5( wp_json_encode( Store::all() ) );
	}
}
