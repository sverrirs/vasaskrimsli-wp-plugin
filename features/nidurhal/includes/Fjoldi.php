<?php
/**
 * The number of downloads of one ZIP file, as plain text in the page's own style: the block "Námsefni: Fjöldi
 * niðurhala" (vasaskrimsli/nidurhal-fjoldi) on a line of its own, or the shortcode [nidurhal skra="1"] inside a
 * paragraph ("Pakki 1 hefur verið sóttur [nidurhal skra="1"] sinnum."). Both give <span class="vs-nidurhal-fjoldi">42</span>.
 *
 * The file is named by its short name ("1", "klubbur-og-safnmappa"), as in pakki-01, or by its file name
 * ("vasaskrimsli-namsefni-pakki-01.zip"): Nidurhal::kort makes them all the same. The number is the downloads in all.
 */

namespace Vasaskrimsli\Nidurhal;

final class Fjoldi {

	public static function register(): void {
		wp_register_script(
			'vasaskrimsli-nidurhal-fjoldi-editor',
			VASASKRIMSLI_URL . 'features/nidurhal/fjoldi-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
			VASASKRIMSLI_VERSION,
			true
		);
		register_block_type( 'vasaskrimsli/nidurhal-fjoldi', array(
			'api_version'     => 3,
			'title'           => 'Námsefni: Fjöldi niðurhala',
			'category'        => 'widgets',
			'keywords'        => array( 'námsefni', 'niðurhal', 'fjöldi', 'teljari' ),
			'editor_script'   => 'vasaskrimsli-nidurhal-fjoldi-editor',
			'supports'        => array( 'html' => false ),
			'attributes'      => array( 'skra' => array( 'type' => 'string', 'default' => '' ) ),
			'render_callback' => array( self::class, 'render' ),
		) );
		add_shortcode( 'nidurhal', array( self::class, 'render' ) );
		// The files to choose from in the block's settings.
		add_action( 'enqueue_block_editor_assets', static function () {
			$files = array_map( static fn ( $n ) => array( 'kort' => Nidurhal::kort( $n ), 'heiti' => $n ), Saekja::skrar() );
			wp_add_inline_script( 'vasaskrimsli-nidurhal-fjoldi-editor', 'window.vsNidurhalSkrar = ' . wp_json_encode( $files ) . ';', 'before' );
		} );
	}

	/** The block's attributes or the shortcode's: skra = a short name or a file name. */
	public static function render( $attrs = array() ): string {
		$skra = is_array( $attrs ) ? trim( (string) ( $attrs['skra'] ?? '' ) ) : '';
		$name = '' === $skra ? null : Saekja::finna( Nidurhal::kort( $skra ) );
		$n    = null === $name ? 0 : Store::alls( $name );
		return '<span class="vs-nidurhal-fjoldi">' . esc_html( number_format_i18n( $n ) ) . '</span>';
	}
}
