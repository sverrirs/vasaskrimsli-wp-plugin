<?php
/**
 * The book catalogue blocks:
 *  - "Verslun" (vasaskrimsli/verslun, shortcode [verslun]): every series, with links between them. For the Verslun page.
 *  - one block per series (vasaskrimsli/baekur-<series>, shortcode [baekur rod="<series>"]): that series alone.
 *
 * Renders the books as plain HTML first (covers, names, status and price, a link to the shop), so the page works for
 * search engines and readers without JavaScript. The React app then adds filters, search and the book viewer.
 * The data is data/baekur.json (made by migration/baekur.py); prices come from the shop when it can be reached.
 */

namespace Vasaskrimsli\Baekur;

final class Block {

	/** Series id => block title. A new series in baekur-skra.json needs a line here to get its own block. */
	public const RADIR = array(
		'storubaekur'   => 'Vasaskrímslabækurnar',
		'lestrarbaekur' => 'Lestrarbækurnar',
		'audlesid'      => 'Auðlesið',
		'ordsifjaskra'  => 'Orðsifjaskrá Vasaskrímslanna',
		'verkefnahefti' => 'Verkefnahefti',
		'spjold'        => 'Veggspjöld og smáspjöld',
	);

	public const STADA = array(
		'faanleg'    => 'Fáanleg',
		'vaentanleg' => 'Væntanleg',
	);

	public static function register(): void {
		wp_register_script(
			'vasaskrimsli-baekur-editor',
			VASASKRIMSLI_URL . 'features/baekur/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor' ),
			VASASKRIMSLI_VERSION,
			true
		);
		wp_add_inline_script( 'vasaskrimsli-baekur-editor', 'window.vsBaekurRadir = ' . wp_json_encode( self::RADIR ) . ';', 'before' );
		$common = array(
			'api_version'   => 3,
			'category'      => 'widgets',
			'editor_script' => 'vasaskrimsli-baekur-editor',
			'supports'      => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
		);
		register_block_type( 'vasaskrimsli/verslun', $common + array(
			'title'           => 'Verslun: allar bækur',
			'render_callback' => static fn() => self::render( '' ),
		) );
		foreach ( self::RADIR as $id => $title ) {
			register_block_type( 'vasaskrimsli/baekur-' . $id, $common + array(
				'title'           => $title,
				'render_callback' => static fn() => self::render( $id ),
			) );
		}
		add_shortcode( 'verslun', static fn() => self::render( '' ) );
		add_shortcode( 'baekur', static fn( $atts ) => self::render( sanitize_key( $atts['rod'] ?? '' ) ) );
	}

	/** data/baekur.json, read once per request. */
	public static function data(): array {
		static $data = null;
		if ( null === $data ) {
			$file = VASASKRIMSLI_DIR . 'features/baekur/data/baekur.json';
			$data = is_readable( $file ) ? ( json_decode( (string) file_get_contents( $file ), true ) ?: array() ) : array();
		}
		return $data;
	}

	/**
	 * The series to show: one by id, or all ('').
	 * @return array[]
	 */
	public static function radir( array $data, string $rod ): array {
		$all = $data['radir'] ?? array();
		return '' === $rod ? $all : array_values( array_filter( $all, static fn( $r ) => ( $r['id'] ?? '' ) === $rod ) );
	}

	/** @param string $rod A series id, or '' for every series. */
	public static function render( string $rod ): string {
		$data  = self::data();
		$radir = self::radir( $data, $rod );
		if ( ! $radir ) {
			return '';
		}
		\Vasaskrimsli\Assets::module( 'baekur' );
		$shop  = (string) ( $data['verslun']['slod'] ?? '' );
		$vorur = Verslun::vorur( $data['vorur'] ?? array(), $shop );
		$base  = VASASKRIMSLI_URL . 'features/baekur/';

		$html = '';
		if ( '' === $rod && count( $radir ) > 1 ) {
			$html .= '<nav class="vs-bk__nav" aria-label="Vöruflokkar"><ul>';
			foreach ( $radir as $r ) {
				$html .= '<li><a href="#' . esc_attr( $r['akkeri'] ) . '">' . esc_html( $r['heiti'] ) . '</a></li>';
			}
			$html .= '</ul></nav>';
		}
		foreach ( $radir as $r ) {
			$html .= '<section class="vs-bk__rod" id="' . esc_attr( $r['akkeri'] ) . '"><h2>' . esc_html( $r['heiti'] ) . '</h2>';
			foreach ( $r['lysing'] ?? array() as $p ) {
				$html .= '<p>' . esc_html( $p ) . '</p>';
			}
			if ( ! empty( $r['atridi'] ) ) {
				$html .= '<ul class="vs-bk__atridi"><li>' . implode( '</li><li>', array_map( 'esc_html', $r['atridi'] ) ) . '</li></ul>';
			}
			$html .= '<ul class="vs-bk__grid">';
			foreach ( $r['baekur'] as $b ) {
				$vara  = $vorur[ $b['vara'] ?? '' ] ?? null;
				$state = self::STADA[ $b['stada'] ] ?? '';
				$price = 'faanleg' === $b['stada'] ? Verslun::verd( $vara ) : '';
				$html .= '<li class="vs-bk__bok is-' . esc_attr( $b['stada'] ) . '">'
					. '<img src="' . esc_url( $base . $b['kapa'] ) . '" alt="' . esc_attr( 'Forsíða: ' . $b['heiti'] ) . '" loading="lazy" decoding="async">'
					. '<strong>' . esc_html( $b['heiti'] ) . '</strong>'
					. ( '' !== ( $b['efni'] ?? '' ) ? '<span>' . esc_html( $b['efni'] ) . '</span>' : '' )
					. '<span class="vs-bk__stada">' . esc_html( trim( $state . ( '' !== $price ? ', ' . $price : '' ) ) ) . '</span>'
					. ( $vara ? '<a href="' . esc_url( $vara['slod'] ) . '">Kaupa í vefverslun</a>' : '' )
					. '</li>';
			}
			$html .= '</ul></section>';
		}

		$client = array(
			'grunnur' => $base,
			'verslun' => $data['verslun'] ?? array(),
			'vorur'   => $vorur,
			'radir'   => $radir,
			'stada'   => self::STADA,
			'allar'   => '' === $rod,
		);
		return '<div class="vs-baekur alignwide">'
			. '<script type="application/json" class="vs-baekur__data">' . wp_json_encode( $client, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>'
			. '<div class="vs-baekur__app"><div class="vs-bk">' . $html . '</div></div></div>';
	}
}
