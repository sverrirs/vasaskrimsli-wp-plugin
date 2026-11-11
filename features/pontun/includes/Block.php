<?php
/**
 * The order form block, "Verslun: Pöntunarform" (vasaskrimsli/pontun, shortcode [pontunarform]). For the Pantanir page:
 * the text above it is written on the page. The products to choose from are data/pontun.json.
 */

namespace Vasaskrimsli\Pontun;

final class Block {

	public static function register(): void {
		wp_register_script(
			'vasaskrimsli-pontun-editor',
			VASASKRIMSLI_URL . 'features/pontun/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor' ),
			VASASKRIMSLI_VERSION,
			true
		);
		register_block_type( 'vasaskrimsli/pontun', array(
			'api_version'     => 3,
			'title'           => 'Verslun: Pöntunarform',
			'category'        => 'widgets',
			'keywords'        => array( 'verslun', 'pöntun', 'pantanir' ),
			'editor_script'   => 'vasaskrimsli-pontun-editor',
			'supports'        => array( 'html' => false, 'multiple' => false, 'align' => array( 'wide', 'full' ) ),
			// WordPress's own width: wide (the default, wider than the text), full, or '' for the text's width.
			'attributes'      => array( 'align' => array( 'type' => 'string', 'default' => 'wide' ) ),
			'render_callback' => array( self::class, 'render' ),
		) );
		add_shortcode( 'pontunarform', array( self::class, 'render' ) );
	}

	/** data/pontun.json, read once per request. */
	public static function config(): array {
		static $config = null;
		if ( null === $config ) {
			$file   = VASASKRIMSLI_DIR . 'features/pontun/data/pontun.json';
			$config = is_readable( $file ) ? ( json_decode( (string) file_get_contents( $file ), true ) ?: array() ) : array();
		}
		return $config;
	}

	public static function render( $attrs = array() ): string {
		$align = sanitize_key( ( is_array( $attrs ) ? $attrs['align'] ?? null : null ) ?? 'wide' );
		\Vasaskrimsli\Assets::module( 'pontun' );
		$data = array(
			'slod'      => rest_url( 'vasaskrimsli/v1/pontun' ),
			't'         => Rest::stamp(),
			'reitir'    => Pontun::REITIR,
			'maxFjoldi' => Pontun::MAX_FJOLDI,
			'maxLinur'  => Pontun::MAX_LINUR,
			'flokkar'   => self::config()['flokkar'] ?? array(),
			'kapaslod'  => VASASKRIMSLI_URL . 'features/baekur/assets/',
			'sending'   => self::config()['sending'] ?? array(),
		);
		return '<div class="vs-pontun' . ( in_array( $align, array( 'wide', 'full' ), true ) ? ' align' . $align : '' ) . '">'
			. '<script type="application/json" class="vs-pontun__data">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>'
			. '<div class="vs-pontun__app"><p>Pöntunarformið þarf JavaScript. Ef það birtist ekki má senda pöntun í tölvupósti á '
			. esc_html( Store::recipients()[0] ) . '.</p></div></div>';
	}
}
