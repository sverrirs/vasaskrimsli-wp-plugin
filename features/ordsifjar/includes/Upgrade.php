<?php
/**
 * One-time data updates that run after the plugin is updated (or first activated).
 *
 * Every step only adds or normalises; nothing is deleted or overwritten wholesale. A backup CSV is
 * written before the first change, each changed entry gets a revision, and every step can safely
 * run again (it skips what is already done), so an interrupted run just continues next time.
 * Each step runs only when coming from an older data version, so a later update never repeats one
 * (e.g. never marks a generation "í vinnslu" again after the owner removed the note).
 */

namespace Vasaskrimsli\Ordsifjar;

final class Upgrade {

	const OPTION  = 'vs_ordsifjar_data_version';
	const LOCK    = 'vs_ordsifjar_upgrade_lock';
	const VERSION = 3;

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
		if ( ! empty( $r['nofn_fyllt'] ) ) {
			$parts[] = sprintf( 'ný nöfn sett inn í %d færslum í kynslóðum 4 til 9 þar sem enn stóð „–“', $r['nofn_fyllt'] )
				. ( empty( $r['nofn_drog'] ) ? '' : sprintf( ' (þar af %d með texta í drögum sem á eftir að yfirfara)', $r['nofn_drog'] ) );
		}
		if ( ! empty( $r['nofn_breytt'] ) ) {
			$parts[] = sprintf( '%d %s í 2. og 3. kynslóð breytt', $r['nofn_breytt'], self::one( $r['nofn_breytt'] ) ? 'nafni' : 'nöfnum' );
		}
		if ( ! empty( $r['nofn_sleppt'] ) ) {
			$n       = count( $r['nofn_sleppt'] );
			$parts[] = sprintf( '%d %s í 2. og 3. kynslóð var ekki breytt því %s hafði þegar verið breytt hér (%s)', $n, self::one( $n ) ? 'nafni' : 'nöfnum', self::one( $n ) ? 'því' : 'þeim', implode( '; ', $r['nofn_sleppt'] ) );
		}
		if ( ! empty( $r['vinnsla'] ) ) {
			$parts[] = sprintf( '%d kynslóðir merktar „í vinnslu“', $r['vinnsla'] );
		}
		if ( ! $parts ) {
			return;
		}
		$backup = $r['backup'] ? ' Afrit af gögnunum frá því fyrir uppfærsluna: ' . $r['backup'] . ' (Orðsifjar, Flytja inn CSV, Eldri afrit).' : '';
		echo '<div class="notice notice-success is-dismissible"><p><strong>Orðsifjar uppfærðar:</strong> '
			. esc_html( implode( ', ', $parts ) . '.' . $backup ) . '</p></div>';
	}

	/** Icelandic singular after a number: 1, 21, 31 ... but not 11. */
	private static function one( int $n ): bool {
		return 1 === $n % 10 && 11 !== $n % 100;
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
			self::run( (int) get_option( self::OPTION, 1 ) );
			update_option( self::OPTION, self::VERSION, false );
		} finally {
			delete_option( self::LOCK );
		}
	}

	/**
	 * @param int $from The data version the site has now; only newer steps run. 1 on a new install.
	 * @return array{backup: string|null, dashes: int, added: int, kynslodir: int, nofn_fyllt: int, nofn_drog: int, nofn_breytt: int, nofn_sleppt: list<string>, vinnsla: int}
	 */
	public static function run( int $from = 1 ): array {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- not allowed on some hosts.
		}
		$report = array( 'backup' => null, 'dashes' => 0, 'added' => 0, 'kynslodir' => 0, 'nofn_fyllt' => 0, 'nofn_drog' => 0, 'nofn_breytt' => 0, 'nofn_sleppt' => array(), 'vinnsla' => 0 );
		if ( $from >= self::VERSION ) {
			return $report; // Already up to date (e.g. the plugin was re-activated).
		}
		if ( Store::all() ) {
			$backup           = Import::backup();
			$report['backup'] = is_wp_error( $backup ) ? null : $backup;
			if ( is_wp_error( $backup ) ) {
				return $report; // Never change data without a backup.
			}
		}

		if ( $from < 2 ) {
			self::version_2( $report );
		}
		if ( $from < 3 ) {
			self::version_3( $report );
		}

		update_option( 'vs_ordsifjar_last_upgrade', $report + array( 'time' => time() ), false );
		return $report;
	}

	/** Data version 2: em-dash separators, gen 4-9 entries (placeholders), gen 5-9 headings. */
	private static function version_2( array &$report ): void {
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
	}

	/**
	 * Data version 3: the 2026 names (data/nofn-2026.json) and generations 3-9 marked "í vinnslu".
	 * Runs after version 2, so on a new install the gen 4-9 placeholders exist before they are filled.
	 */
	private static function version_3( array &$report ): void {
		// 4. Names: fill placeholders (gen 4-9), replace gen 2-3 names that are still as published.
		$rows = json_decode( (string) file_get_contents( VASASKRIMSLI_DIR . Nofn::FILE ), true );
		$plan = Nofn::plan( Store::all(), is_array( $rows ) ? $rows : array() );
		foreach ( $plan['changes'] as $c ) {
			if ( is_wp_error( Store::save( $c['fields'], $c['id'] ) ) ) {
				continue;
			}
			if ( 'breytt' === $c['stada'] ) {
				++$report['nofn_breytt'];
			} else {
				++$report['nofn_fyllt'];
				$report['nofn_drog'] += $c['drog'] ? 1 : 0;
			}
		}
		$report['nofn_sleppt'] = $plan['skipped'];

		// 5. Generations 3-9 "í vinnslu": a note where there is none, and on the gen 3 heading.
		$marked = Nofn::in_progress( Store::kynslodir() );
		if ( $marked['notes'] || $marked['headings'] ) {
			Store::save_kynslodir( $marked['kynslodir'] );
		}
		$report['vinnsla'] = $marked['notes'];
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
