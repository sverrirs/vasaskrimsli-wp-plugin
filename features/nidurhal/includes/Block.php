<?php
/**
 * The block "Námsefni: Niðurhalsteljari" (vasaskrimsli/nidurhal, shortcode [nidurhalsteljari]). Shows nothing: it adds
 * a small script to the page that reports clicks on links to ZIP files on this site (teljari.js). Your own clicks while
 * logged in as an editor are not counted, so the script is left out for you.
 */

namespace Vasaskrimsli\Nidurhal;

final class Block {

	public static function register(): void {
		wp_register_script(
			'vasaskrimsli-nidurhal-editor',
			VASASKRIMSLI_URL . 'features/nidurhal/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor' ),
			VASASKRIMSLI_VERSION,
			true
		);
		wp_register_script(
			'vasaskrimsli-nidurhal',
			VASASKRIMSLI_URL . 'features/nidurhal/teljari.js',
			array(),
			VASASKRIMSLI_VERSION,
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
		register_block_type( 'vasaskrimsli/nidurhal', array(
			'api_version'     => 3,
			'title'           => 'Námsefni: Niðurhalsteljari',
			'category'        => 'widgets',
			'keywords'        => array( 'námsefni', 'niðurhal', 'zip', 'teljari' ),
			'editor_script'   => 'vasaskrimsli-nidurhal-editor',
			'supports'        => array( 'html' => false, 'multiple' => false ),
			'render_callback' => array( self::class, 'render' ),
		) );
		add_shortcode( 'nidurhalsteljari', array( self::class, 'render' ) );
	}

	public static function render(): string {
		static $done = false;
		if ( $done || current_user_can( 'edit_posts' ) ) {
			return '';
		}
		$done = true;
		wp_enqueue_script( 'vasaskrimsli-nidurhal' );
		wp_add_inline_script(
			'vasaskrimsli-nidurhal',
			'window.vsNidurhal = ' . wp_json_encode( array( 'slod' => rest_url( 'vasaskrimsli/v1/nidurhal' ), 't' => Rest::stamp() ) ) . ';',
			'before'
		);
		// Not '': WordPress 7.1 leaves out the scripts a block enqueued when its output is empty (seen on the test site).
		return '<!-- vs-nidurhal -->';
	}
}
