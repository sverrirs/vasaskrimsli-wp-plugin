<?php
/**
 * The single allow-list for formatted text in orðsifjar fields.
 *
 * Mirrors the formatting the original page uses: bold, italics, links, line breaks and
 * super/subscript. Must match ALLOWED in migration/extract.py and the editor in src/admin.
 */

namespace Vasaskrimsli\Ordsifjar;

final class Sanitize {

	public static function allowed_html(): array {
		return array(
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
			'sup'    => array(),
			'sub'    => array(),
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
		);
	}

	public static function html( $value ): string {
		$value = trim( (string) $value );
		$value = preg_replace( '#<br\s*/?>#i', '<br>', $value );
		$value = preg_replace( '#\s*<br>\s*$#', '', $value ); // Editors leave a trailing break.
		return wp_kses( $value, self::allowed_html() );
	}

	/** Sanitised HTML plus the field's own normalisation (e.g. Upprunaorð separators). */
	public static function field( $value, string $field ): string {
		return Format::field( $field, self::html( $value ) );
	}

	public static function text( $value ): string {
		return sanitize_text_field( (string) $value );
	}
}
