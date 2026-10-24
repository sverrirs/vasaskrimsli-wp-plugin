<?php
/**
 * One-time data updates that run after the plugin is updated (or first activated).
 *
 * Every step only adds or normalises; nothing is deleted or overwritten wholesale. A backup CSV is
 * written before the first change, each changed entry gets a revision, and every step can safely
 * run again (it skips what is already done), so an interrupted run just continues next time.
 */

namespace Vasaskrimsli\Ordsifjar;

final class Upgrade {

	const OPTION  = 'vs_ordsifjar_data_version';
	const LOCK    = 'vs_ordsifjar_upgrade_lock';
	const VERSION = 2;

	/** Generations 5-9 headings. Gen 4 already comes from the original page. */
	const NEW_GENERATIONS = array(
		5 => array( 'fimmta-kynslod', 'Fimmta' ),
		6 => array( 'sjotta-kynslod', 'Sjötta' ),
		7 => array( 'sjounda-kynslod', 'Sjöunda' ),
		8 => array( 'attunda-kynslod', 'Áttunda' ),
		9 => array( 'niunda-kynslod', 'Níunda' ),
	);

	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'maybe_run' ) );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
	}

	/** Shows once what the last data update did, then forgets it. */
	public static function notice(): void {
		$r = get_option( 'vs_ordsifjar_last_upgrade' );
		if ( ! is_array( $r ) || ! current_user_can( Store::CAPABILITY ) ) {
			return;
		}
		delete_option( 'vs_ordsifjar_last_upgrade' );
		$parts = array();
		if ( $r['added'] ) {
			$parts[] = sprintf( '%d nýjar færslur bættust við (kynslóðir 4 til 9)', $r['added'] );
		}
		if ( $r['dashes'] ) {
			$parts[] = sprintf( 'strik í Upprunaorð samræmd í %d færslum', $r['dashes'] );
		}
		if ( $r['kynslodir'] ) {
			$parts[] = sprintf( '%d kynslóðafyrirsagnir bættust við', $r['kynslodir'] );
		}
		if ( ! $parts ) {
			return;
		}
		$backup = $r['backup'] ? ' Afrit af gögnunum frá því fyrir uppfærsluna: ' . $r['backup'] . ' (Orðsifjar, Flytja inn CSV, Eldri afrit).' : '';
		echo '<div class="notice notice-success is-dismissible"><p><strong>Orðsifjar uppfærðar:</strong> '
			. esc_html( implode( ', ', $parts ) . '.' . $backup ) . '</p></div>';
	}

	public static function maybe_run(): void {
		if ( (int) get_option( self::OPTION, 1 ) >= self::VERSION || ! current_user_can( Store::CAPABILITY ) ) {
			return;
		}
		// add_option() fails if the lock exists, so two admin requests cannot run this at once.
		if ( ! add_option( self::LOCK, time(), '', false ) ) {
			if ( time() - (int) get_option( self::LOCK ) < 10 * MINUTE_IN_SECONDS ) {
				return;
			}
			update_option( self::LOCK, time(), false ); // A run that died; take over.
		}
		try {
			self::run();
			update_option( self::OPTION, self::VERSION, false );
		} finally {
			delete_option( self::LOCK );
		}
	}

	/** @return array{backup: string|null, dashes: int, added: int, kynslodir: int} */
	public static function run(): array {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- not allowed on some hosts.
		}
		$report = array( 'backup' => null, 'dashes' => 0, 'added' => 0, 'kynslodir' => 0 );
		if ( Store::all() ) {
			$backup           = Import::backup();
			$report['backup'] = is_wp_error( $backup ) ? null : $backup;
			if ( is_wp_error( $backup ) ) {
				return $report; // Never change data without a backup.
			}
		}

		// 1. Upprunaorð separators: hyphens and en dashes become em dashes.
		foreach ( Store::all() as $id => $e ) {
			$normalised = Format::separators( $e['uppruni'] );
			if ( $normalised !== $e['uppruni'] && ! is_wp_error( Store::save( array( 'uppruni' => $normalised ), $id ) ) ) {
				++$report['dashes'];
			}
		}

		// 2. Gen 4-9 entries from the national list, for numbers not present in any status.
		$present = self::numbers_in_any_status();
		$list    = json_decode( (string) file_get_contents( VASASKRIMSLI_DIR . 'features/ordsifjar/data/national.json' ), true );
		foreach ( is_array( $list ) ? $list : array() as $p ) {
			if ( $p['kynslod'] < 4 || isset( $present[ (string) $p['numer'] ] ) ) {
				continue;
			}
			$link   = 'https://pokemondb.net/pokedex/' . rawurlencode( $p['slug'] );
			$result = Store::save( array(
				'numer'   => (string) $p['numer'],
				'kynslod' => (int) $p['kynslod'],
				'nafn'    => '–',
				'uppruni' => '–',
				'thyding' => '–',
				'enska'   => '<a href="' . esc_url( $link ) . '">' . esc_html( $p['enska'] ) . '</a>',
			) );
			if ( ! is_wp_error( $result ) ) {
				++$report['added'];
			}
		}

		// 3. Headings for generations 5-9, styled like "Fjórða kynslóð (óstaðfest)". Existing ones are kept.
		$kynslodir = Store::kynslodir();
		$have      = array_column( $kynslodir, 'kynslod' );
		foreach ( self::NEW_GENERATIONS as $n => list( $anchor, $ordinal ) ) {
			if ( ! in_array( $n, $have, true ) ) {
				$kynslodir[] = array( 'kynslod' => $n, 'akkeri' => $anchor, 'heiti_html' => "<em>{$ordinal} kynslóð</em> (óstaðfest)", 'skyring_html' => '' );
				++$report['kynslodir'];
			}
		}
		if ( $report['kynslodir'] ) {
			Store::save_kynslodir( $kynslodir );
		}

		update_option( 'vs_ordsifjar_last_upgrade', $report + array( 'time' => time() ), false );
		return $report;
	}

	/** Every Númer used by an entry, including drafts and the trash, so deleted ones are not re-added. */
	private static function numbers_in_any_status(): array {
		$posts = get_posts( array(
			'post_type'        => Store::POST_TYPE,
			'post_status'      => 'any',
			'numberposts'      => -1,
			'suppress_filters' => true,
		) );
		$trash = get_posts( array(
			'post_type'        => Store::POST_TYPE,
			'post_status'      => 'trash',
			'numberposts'      => -1,
			'suppress_filters' => true,
		) );
		$out = array();
		foreach ( array_merge( $posts, $trash ) as $p ) {
			$out[ (string) Store::decode( $p->post_content )['numer'] ] = true;
		}
		return $out;
	}
}
