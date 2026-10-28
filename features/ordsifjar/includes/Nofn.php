<?php
/**
 * The 2026 name decisions (data/nofn-2026.json, made by migration/nofn.py) and the "in progress"
 * marking of generations 3 to 9. Pure PHP (no WordPress), tested in tests/run.php; Upgrade saves
 * what these functions return, after a backup, one revision per changed entry.
 *
 * Never overwrites the owner's own text:
 *   "nýtt"   (gen 4-9) fills Nafn, Upprunaorð and Þýðing one by one, only where the field is still the
 *            placeholder "–".
 *   "breytt" (gen 3, and #233 in gen 2: Grafíkja2 -> Grafíkja²) replaces the entry only while its Nafn is still
 *            the published name (fyrra_nafn).
 *            If the owner has changed the name it is left alone and reported as skipped.
 */

namespace Vasaskrimsli\Ordsifjar;

final class Nofn {

	const FILE   = 'features/ordsifjar/data/nofn-2026.json';
	/** Data version 4. nofn-2026.json stays as version 3 wrote it: it is the "fyrri" text this file replaces. */
	const TEXT_FILE = 'features/ordsifjar/data/thyding-2026.json';
	const FIELDS = array( 'nafn', 'uppruni', 'thyding' );

	/** Note for generations whose names are still being worked on. */
	const NOTE = '<em>Nöfnin í þessari kynslóð eru í vinnslu og geta breyst.</em>';
	/** Generations that get NOTE where their note is empty (4 has its own note). */
	const NOTE_GENERATIONS = array( 3, 5, 6, 7, 8, 9 );
	/** Generations whose heading gets " (í vinnslu)" unless it already says so (5-9 say "óstaðfest"). */
	const HEADING_GENERATIONS = array( 3 );
	const HEADING_SUFFIX      = ' (í vinnslu)';

	/** True for a field nobody has written in yet: just a dash. */
	public static function is_placeholder( string $html ): bool {
		return in_array( Csv::plain( $html ), array( "\u{2013}", '-', "\u{2014}" ), true );
	}

	/**
	 * What to change. Entries are matched on Númer exactly ("351a" is not 351).
	 *
	 * @param array $entries Stored entries keyed by id (Store::all()).
	 * @param array $rows    Rows of nofn-2026.json.
	 * @return array{changes: list<array{id: int, numer: string, stada: string, drog: bool, fields: array}>, skipped: list<string>}
	 *         skipped: "298 Mitt nafn" for gen 2-3 entries whose name the owner had changed.
	 */
	public static function plan( array $entries, array $rows ): array {
		$by_numer = array();
		foreach ( $entries as $id => $e ) {
			$by_numer[ (string) $e['numer'] ][] = $id;
		}
		$out = array( 'changes' => array(), 'skipped' => array() );
		foreach ( $rows as $r ) {
			$new = array();
			foreach ( self::FIELDS as $f ) {
				if ( '' !== trim( (string) ( $r[ $f ] ?? '' ) ) ) {
					$new[ $f ] = (string) $r[ $f ];
				}
			}
			foreach ( $by_numer[ (string) $r['numer'] ] ?? array() as $id ) {
				$e      = $entries[ $id ];
				$fields = array();
				if ( 'nýtt' === $r['stada'] ) {
					foreach ( $new as $f => $v ) {
						if ( self::is_placeholder( (string) $e[ $f ] ) && ! self::is_placeholder( $v ) ) {
							$fields[ $f ] = $v;
						}
					}
				} elseif ( 'breytt' === $r['stada'] && isset( $new['nafn'] ) ) {
					$current = Csv::plain( (string) $e['nafn'] );
					if ( Csv::plain( $new['nafn'] ) === $current ) {
						continue; // Already done (or the owner typed the new name himself).
					}
					if ( Csv::plain( (string) ( $r['fyrra_nafn'] ?? '' ) ) !== $current ) {
						$out['skipped'][] = $e['numer'] . ' ' . $current;
						continue;
					}
					foreach ( $new as $f => $v ) {
						if ( $v !== $e[ $f ] ) {
							$fields[ $f ] = $v;
						}
					}
				}
				if ( $fields ) {
					$out['changes'][] = array(
						'id'     => (int) $id,
						'numer'  => (string) $e['numer'],
						'stada'  => (string) $r['stada'],
						'drog'   => ! empty( $r['drog'] ),
						'fields' => $fields,
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Data version 4: the rewritten Þýðing texts (data/thyding-2026.json, made by migration/thyding.py).
	 * A row {numer, nafn, fyrri, thyding} replaces the entry's Þýðing only while the entry still has that
	 * name and still shows exactly the text version 3 wrote (fyrri). Text the owner has edited is kept.
	 *
	 * @return array{changes: list<array{id: int, numer: string, fields: array}>, skipped: list<string>}
	 *         skipped: "999 Gemmérgull" for entries whose Þýðing (or name) the owner had changed.
	 */
	public static function plan_texts( array $entries, array $rows ): array {
		$by_numer = array();
		foreach ( $entries as $id => $e ) {
			$by_numer[ (string) $e['numer'] ][] = $id;
		}
		$out = array( 'changes' => array(), 'skipped' => array() );
		foreach ( $rows as $r ) {
			$new = (string) ( $r['thyding'] ?? '' );
			if ( '' === Csv::plain( $new ) ) {
				continue;
			}
			foreach ( $by_numer[ (string) $r['numer'] ] ?? array() as $id ) {
				$e       = $entries[ $id ];
				$current = Csv::plain( (string) $e['thyding'] );
				if ( Csv::plain( $new ) === $current ) {
					continue; // Already done.
				}
				if ( Csv::plain( (string) $e['nafn'] ) !== Csv::plain( (string) $r['nafn'] )
					|| Csv::plain( (string) ( $r['fyrri'] ?? '' ) ) !== $current ) {
					$out['skipped'][] = $e['numer'] . ' ' . Csv::plain( (string) $e['nafn'] );
					continue;
				}
				$out['changes'][] = array( 'id' => (int) $id, 'numer' => (string) $e['numer'], 'fields' => array( 'thyding' => $new ) );
			}
		}
		return $out;
	}

	/**
	 * Marks generations 3 to 9 as in progress: NOTE where the note is empty, and " (í vinnslu)" on the
	 * gen 3 heading unless it already says "vinnslu" or "óstaðfest". A note the owner wrote is kept.
	 *
	 * @return array{kynslodir: array, notes: int, headings: int}
	 */
	public static function in_progress( array $kynslodir ): array {
		$notes    = 0;
		$headings = 0;
		foreach ( $kynslodir as $i => $k ) {
			$n = (int) ( $k['kynslod'] ?? 0 );
			if ( in_array( $n, self::NOTE_GENERATIONS, true ) && '' === Csv::plain( (string) ( $k['skyring_html'] ?? '' ) ) ) {
				$kynslodir[ $i ]['skyring_html'] = self::NOTE;
				++$notes;
			}
			$heading = (string) ( $k['heiti_html'] ?? '' );
			if ( in_array( $n, self::HEADING_GENERATIONS, true ) && '' !== Csv::plain( $heading ) && ! preg_match( '/vinnslu|óstaðfest/iu', Csv::plain( $heading ) ) ) {
				$kynslodir[ $i ]['heiti_html'] = $heading . self::HEADING_SUFFIX;
				++$headings;
			}
		}
		return array( 'kynslodir' => $kynslodir, 'notes' => $notes, 'headings' => $headings );
	}
}
