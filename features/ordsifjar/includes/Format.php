<?php
/**
 * Field-specific text normalisation. Pure PHP (no WordPress), tested in tests/run.php.
 */

namespace Vasaskrimsli\Ordsifjar;

final class Format {

	/** Separator between word parts in Upprunaorð (em dash). */
	const SEPARATOR = "\u{2014}";

	/**
	 * Makes every separator in an Upprunaorð value the same em dash: "Kam-eld-ljón" and
	 * "Lauk–eðla" become "Kam—eld—ljón" and "Lauk—eðla". Only text is touched, never tags or
	 * link addresses. A value that is nothing but a dash (a placeholder) is left as it is.
	 */
	public static function separators( string $html ): string {
		$dash = '(?:[\x{002D}\x{2010}-\x{2015}\x{2212}]|&(?:ndash|mdash|minus|hyphen|dash);|&#(?:45|820[8-9]|821[0-3]|8722);|&#x(?:2d|201[0-5]|2212);)';
		if ( preg_match( '/^\s*' . $dash . '\s*$/iu', Csv::plain( $html ) ) || preg_match( '/^\s*' . $dash . '\s*$/iu', $html ) ) {
			return $html;
		}
		$parts = preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $parts as $i => $part ) {
			if ( 0 === $i % 2 ) { // Text between tags.
				$parts[ $i ] = preg_replace( '/\s*' . $dash . '\s*/iu', self::SEPARATOR, $part );
			}
		}
		return implode( '', $parts );
	}

	/**
	 * Special kinds of vasaskrímsli, shown as a marking in the Skrímslaskrá and the Orðsifjafræði table.
	 * Tímaflækingar are the Paradox Pokémon: travellers from the past or the future (Koraidon and Miraidon included).
	 */
	const SERSTAKT = array(
		'Tímaflækingur' => array( array( 984, 995 ), array( 1005, 1010 ), array( 1020, 1023 ) ),
	);

	/** The special kind of an entry ("Tímaflækingur"), or '' for none. "984a" counts as 984. */
	public static function serstakt( string $numer ): string {
		if ( ! preg_match( '/^\d+/', $numer, $m ) ) {
			return '';
		}
		$n = (int) $m[0];
		foreach ( self::SERSTAKT as $heiti => $bil ) {
			foreach ( $bil as $b ) {
				if ( $n >= $b[0] && $n <= $b[1] ) {
					return $heiti;
				}
			}
		}
		return '';
	}

	/**
	 * The special and regional forms of an entry, from data/formir.json (migration/formir.py).
	 * "26a" counts as 26. A form's picture is assets/sprites/formir/<slug>.webp, '' when $have lacks it.
	 *
	 * The name is the Icelandic one ("i", with {n} for $name) and falls back to the English one ("h").
	 *
	 * @param array  $formir Decoded formir.json: national number => list of {s: slug, h: name, i: Icelandic name}.
	 * @param array  $have   Slugs that have a picture, as keys.
	 * @param string $name   The monster's Icelandic name (its English one when it has none yet).
	 * @return list<array{id: string, heiti: string, enska: string, slug: string}> id is the form's slug (it names
	 *         the form in the visitor's collection); slug is '' when there is no picture.
	 */
	public static function formir( array $formir, array $have, string $numer, string $name = '' ): array {
		if ( ! preg_match( '/^\d+/', $numer, $m ) ) {
			return array();
		}
		$out = array();
		foreach ( $formir[ (string) (int) $m[0] ] ?? array() as $f ) {
			$slug  = (string) ( $f['s'] ?? '' );
			$heiti = '' !== $name && ! empty( $f['i'] ) ? str_replace( '{n}', $name, (string) $f['i'] ) : (string) ( $f['h'] ?? '' );
			$out[] = array(
				'id'    => $slug,
				'heiti' => $heiti,
				'enska' => (string) ( $f['h'] ?? '' ),
				'slug'  => isset( $have[ $slug ] ) ? $slug : '',
			);
		}
		return $out;
	}

	/**
	 * Þýðing with a "Fjölskylda:" line at the end, built from the evolution chain, when the text has none:
	 * "Fjölskylda: Laufmjási → Blómkisi (#907) → Blómblöff (#908)." The monster itself has no number;
	 * stages with several monsters read "A eða B" / "A, B eða C". Unchanged for monsters that do not evolve.
	 *
	 * @param int   $self   The monster's national number.
	 * @param array $stages Its "k" in throun.json: national numbers at each stage.
	 * @param array $names  National number => plain-text name; numbers without one are left out.
	 */
	public static function with_family( string $thyding, int $self, array $stages, array $names ): string {
		if ( preg_match( '/Fjölskylda\s*:/u', Csv::plain( $thyding ) ) ) {
			return $thyding;
		}
		$parts = array();
		$count = 0;
		foreach ( $stages as $stage ) {
			$labels = array();
			foreach ( $stage as $n ) {
				if ( ! isset( $names[ (int) $n ] ) || '' === $names[ (int) $n ] ) {
					continue;
				}
				$labels[] = htmlspecialchars( $names[ (int) $n ], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . ( (int) $n === $self ? '' : ' (#' . (int) $n . ')' );
				++$count;
			}
			if ( $labels ) {
				$last    = array_pop( $labels );
				$parts[] = $labels ? implode( ', ', $labels ) . ' eða ' . $last : $last;
			}
		}
		if ( $count < 2 ) {
			return $thyding;
		}
		$line  = '<strong>Fjölskylda:</strong> ' . implode( ' → ', $parts ) . '.';
		$plain = trim( Csv::plain( $thyding ) );
		return ( '' === $plain || '–' === $plain ) ? $line : rtrim( $thyding ) . '<br>' . $line;
	}

	/** Applies the normalisation that belongs to a field, if any. */
	public static function field( string $field, string $html ): string {
		return 'uppruni' === $field ? self::separators( $html ) : $html;
	}
}
