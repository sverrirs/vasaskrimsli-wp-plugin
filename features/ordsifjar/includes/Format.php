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

	/** Applies the normalisation that belongs to a field, if any. */
	public static function field( string $field, string $html ): string {
		return 'uppruni' === $field ? self::separators( $html ) : $html;
	}
}
