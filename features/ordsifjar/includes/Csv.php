<?php
/**
 * CSV export, parsing and import diffing for orðsifjar entries.
 *
 * Pure PHP with no WordPress calls, so it can be tested directly (tests/run.php).
 * HTML sanitising is injected as a callable; in WordPress that is Sanitize::html().
 *
 * Entry shape used everywhere:
 *   [ 'id' => int, 'numer' => string, 'kynslod' => int, 'nafn' => html, 'uppruni' => html,
 *     'thyding' => html, 'enska' => html, 'flokkar' => string[], 'athugasemdir' => html ]
 */

namespace Vasaskrimsli\Ordsifjar;

final class Csv {

	/** Fields that hold formatted text (HTML). */
	const HTML_FIELDS = array( 'nafn', 'uppruni', 'thyding', 'enska', 'athugasemdir' );

	/** Column order of both exports. HTML fields get a "_html" suffix in the formatted file. */
	const FIELDS = array( 'id', 'numer', 'kynslod', 'nafn', 'uppruni', 'thyding', 'enska', 'flokkar', 'athugasemdir' );

	const MODE_HTML  = 'html';
	const MODE_PLAIN = 'plain';

	/** Separator for several categories in one cell. */
	const FLOKKAR_SEP = ';';

	public static function header( string $mode ): array {
		return array_map(
			static function ( $f ) use ( $mode ) {
				return ( self::MODE_HTML === $mode && in_array( $f, self::HTML_FIELDS, true ) ) ? $f . '_html' : $f;
			},
			self::FIELDS
		);
	}

	/** HTML to the plain text used in the unformatted export: tags removed, <br> becomes a newline. */
	public static function plain( string $html ): string {
		$text  = preg_replace( '#<br\s*/?>#i', "\n", $html );
		$text  = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$lines = array_map(
			static function ( $line ) {
				return trim( preg_replace( '/[ \t\x{00A0}]+/u', ' ', $line ) );
			},
			explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $text ) )
		);
		return trim( implode( "\n", $lines ) );
	}

	/** Plain text typed into a spreadsheet back to safe HTML: escaped, newlines become <br>. */
	public static function plain_to_html( string $text ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", trim( $text ) );
		return implode( '<br>', array_map( static function ( $l ) {
			return htmlspecialchars( $l, ENT_NOQUOTES | ENT_HTML5, 'UTF-8' );
		}, explode( "\n", $text ) ) );
	}

	/** Builds the CSV file contents (UTF-8 with BOM, comma separated, CRLF rows). */
	public static function export( array $entries, string $mode ): string {
		$out = fopen( 'php://temp', 'w+' );
		fwrite( $out, "\xEF\xBB\xBF" );
		self::put( $out, self::header( $mode ) );
		foreach ( $entries as $e ) {
			$row = array();
			foreach ( self::FIELDS as $f ) {
				$v = $e[ $f ] ?? '';
				if ( 'flokkar' === $f ) {
					$v = implode( self::FLOKKAR_SEP . ' ', (array) $v );
				} elseif ( self::MODE_PLAIN === $mode && in_array( $f, self::HTML_FIELDS, true ) ) {
					$v = self::plain( (string) $v );
				}
				$row[] = (string) $v;
			}
			self::put( $out, $row );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out );
		return $csv;
	}

	private static function put( $handle, array $row ): void {
		fputcsv( $handle, $row, ',', '"', '', "\r\n" );
	}

	/**
	 * Parses an uploaded CSV. Accepts either export, comma or semicolon separated
	 * (Excel with Icelandic settings saves with semicolons), with or without a BOM.
	 *
	 * @return array{mode: string, rows: array<int, array>, errors: string[]}
	 */
	public static function parse( string $csv ): array {
		$csv = preg_replace( '/^\xEF\xBB\xBF/', '', $csv );
		if ( 1 !== preg_match( '//u', $csv ) ) { // Valid UTF-8, without needing mbstring.
			return array( 'mode' => '', 'rows' => array(), 'errors' => array( 'Skráin er ekki UTF-8. Vistaðu hana sem „CSV UTF-8“.' ) );
		}
		$first_line = strtok( $csv, "\n" );
		$delimiter  = substr_count( (string) $first_line, ';' ) > substr_count( (string) $first_line, ',' ) ? ';' : ',';

		$in = fopen( 'php://temp', 'w+' );
		fwrite( $in, $csv );
		rewind( $in );
		$header = fgetcsv( $in, null, $delimiter, '"', '' );
		if ( ! $header ) {
			return array( 'mode' => '', 'rows' => array(), 'errors' => array( 'Skráin er tóm.' ) );
		}
		$header = array_map( static function ( $h ) {
			return strtolower( trim( (string) $h ) );
		}, $header );

		$mode = in_array( 'nafn_html', $header, true ) ? self::MODE_HTML : ( in_array( 'nafn', $header, true ) ? self::MODE_PLAIN : '' );
		if ( '' === $mode ) {
			fclose( $in );
			return array( 'mode' => '', 'rows' => array(), 'errors' => array( 'Dálkinn „nafn“ eða „nafn_html“ vantar í fyrstu línu.' ) );
		}
		$known   = self::header( $mode );
		$unknown = array_diff( array_filter( $header ), $known );
		$errors  = array();
		if ( $unknown ) {
			$errors[] = 'Óþekktir dálkar: ' . implode( ', ', $unknown ) . '. Leyfðir dálkar: ' . implode( ', ', $known ) . '.';
		}

		$rows = array();
		$line = 1;
		while ( false !== ( $cells = fgetcsv( $in, null, $delimiter, '"', '' ) ) ) {
			++$line;
			if ( array( null ) === $cells || '' === trim( implode( '', $cells ) ) ) {
				continue; // Blank lines, e.g. ones Excel leaves at the end.
			}
			$row = array( '_line' => $line );
			foreach ( $header as $i => $col ) {
				$field         = preg_replace( '/_html$/', '', $col );
				$row[ $field ] = isset( $cells[ $i ] ) ? (string) $cells[ $i ] : null;
			}
			$rows[] = $row;
		}
		fclose( $in );
		return array( 'mode' => $mode, 'rows' => $rows, 'errors' => $errors );
	}

	/**
	 * Works out what an import would do, without changing anything.
	 *
	 * Rules:
	 *  - A row whose id matches an existing entry updates it; any other row creates a new entry.
	 *  - A column missing from the file leaves that field alone.
	 *  - Formatted file: cells are sanitised HTML and replace the stored value when different.
	 *  - Plain file: a cell only counts as changed when its text differs from the stored text
	 *    with formatting removed. Unchanged cells keep their formatting; changed cells become
	 *    plain text and are reported in 'formatting_lost'.
	 *  - Entries not in the file are never touched or deleted.
	 *
	 * @param array<int, array> $existing Entries keyed by id.
	 * @param array             $parsed   Result of parse().
	 * @param callable          $sanitize HTML sanitiser.
	 */
	public static function diff( array $existing, array $parsed, callable $sanitize ): array {
		$plan = array( 'create' => array(), 'update' => array(), 'unchanged' => 0, 'errors' => $parsed['errors'], 'not_in_file' => 0 );
		$mode = $parsed['mode'];
		$seen = array();

		foreach ( $parsed['rows'] as $row ) {
			$line = $row['_line'];
			$id   = isset( $row['id'] ) && '' !== trim( (string) $row['id'] ) ? trim( (string) $row['id'] ) : '';
			if ( '' !== $id && ! ctype_digit( $id ) ) {
				$plan['errors'][] = "Lína {$line}: id „{$id}“ er ekki tala.";
				continue;
			}
			$id = (int) $id;
			if ( $id && isset( $seen[ $id ] ) ) {
				$plan['errors'][] = "Lína {$line}: id {$id} kemur líka fyrir í línu {$seen[ $id ]}.";
				continue;
			}
			if ( $id ) {
				$seen[ $id ] = $line;
			}
			$current = $id && isset( $existing[ $id ] ) ? $existing[ $id ] : null;

			$incoming = array();
			$lost     = array();
			foreach ( self::FIELDS as $f ) {
				if ( 'id' === $f || ! array_key_exists( $f, $row ) || null === $row[ $f ] ) {
					continue;
				}
				$cell = $row[ $f ];
				if ( 'kynslod' === $f ) {
					$cell = trim( $cell );
					if ( ! ctype_digit( $cell ) || (int) $cell < 1 ) {
						$plan['errors'][] = "Lína {$line}: kynslóð „{$cell}“ verður að vera heil tala, 1 eða hærri.";
						continue 2;
					}
					$incoming[ $f ] = (int) $cell;
				} elseif ( 'numer' === $f ) {
					$incoming[ $f ] = trim( $cell );
				} elseif ( 'flokkar' === $f ) {
					$incoming[ $f ] = self::split_flokkar( $cell );
				} elseif ( self::MODE_HTML === $mode ) {
					$incoming[ $f ] = $sanitize( trim( $cell ) );
				} elseif ( $current && self::plain( $current[ $f ] ) === self::plain( self::plain_to_html( $cell ) ) ) {
					continue; // Same text as stored: keep the stored formatting.
				} else {
					$incoming[ $f ] = $sanitize( self::plain_to_html( $cell ) );
					if ( $current && self::plain( $current[ $f ] ) !== $current[ $f ] ) {
						$lost[] = $f;
					}
				}
			}

			if ( ! $current ) {
				if ( '' === self::plain( $incoming['nafn'] ?? '' ) ) {
					$plan['errors'][] = "Lína {$line}: ný færsla verður að hafa nafn.";
					continue;
				}
				if ( ! isset( $incoming['kynslod'] ) ) {
					$plan['errors'][] = "Lína {$line}: ný færsla verður að hafa kynslóð.";
					continue;
				}
				$plan['create'][] = array( 'line' => $line, 'missing_id' => $id ?: null, 'fields' => $incoming );
				continue;
			}

			$changes = array();
			foreach ( $incoming as $f => $value ) {
				$old = $current[ $f ] ?? null;
				if ( 'flokkar' === $f ? self::same_set( (array) $old, $value ) : (string) $old === (string) $value ) {
					continue;
				}
				$changes[ $f ] = array( 'from' => $old, 'to' => $value );
			}
			if ( $changes ) {
				$plan['update'][] = array(
					'line'            => $line,
					'id'              => $id,
					'changes'         => $changes,
					'formatting_lost' => array_values( array_intersect( $lost, array_keys( $changes ) ) ),
				);
			} else {
				++$plan['unchanged'];
			}
		}
		$plan['not_in_file'] = count( array_diff_key( $existing, $seen ) );
		return $plan;
	}

	public static function split_flokkar( string $cell ): array {
		$parts = array_map( 'trim', explode( self::FLOKKAR_SEP, $cell ) );
		return array_values( array_unique( array_filter( $parts, 'strlen' ) ) );
	}

	private static function same_set( array $a, array $b ): bool {
		sort( $a );
		sort( $b );
		return $a === $b;
	}
}
