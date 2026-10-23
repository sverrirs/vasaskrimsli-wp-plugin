<?php
/**
 * Loads the React apps built by Vite into build/ (see vite.config.js).
 *
 * The apps bundle their own React 19, so they are ES modules independent of the React
 * version WordPress ships for the block editor.
 */

namespace Vasaskrimsli;

final class Assets {

	/** @param string $entry "admin" or "view". */
	public static function module( string $entry ): void {
		$js  = 'build/' . $entry . '.js';
		$css = 'build/' . $entry . '.css';
		if ( ! file_exists( VASASKRIMSLI_DIR . $js ) ) {
			return; // Not built yet (development checkout without `npm run build`).
		}
		$version = (string) filemtime( VASASKRIMSLI_DIR . $js );
		wp_enqueue_script_module( 'vasaskrimsli-' . $entry, VASASKRIMSLI_URL . $js, array(), $version );
		if ( file_exists( VASASKRIMSLI_DIR . $css ) ) {
			wp_enqueue_style( 'vasaskrimsli-' . $entry, VASASKRIMSLI_URL . $css, array(), (string) filemtime( VASASKRIMSLI_DIR . $css ) );
		}
	}
}
