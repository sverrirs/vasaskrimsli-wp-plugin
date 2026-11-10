<?php
/**
 * The book catalogue blocks: one per series (vasaskrimsli/baekur-<series>, shortcode [baekur rod="<series>"]). Each is
 * the series' list of books and its filter, nothing more: the headings and texts about the books are written on the
 * WordPress page itself, so they can be edited without a new plugin version. A series sold in lots (Lestrarbækurnar,
 * ten to a lot) lists the lots; each lot shows its books.
 *
 * Renders the list as plain HTML first (covers, names, status and price, a link to the shop), so the page works for
 * search engines and readers without JavaScript. The React app then adds the filter and the book viewer.
 * The data is data/baekur.json (made by migration/baekur.py); prices come from the shop when it can be reached.
 */

namespace Vasaskrimsli\Baekur;

final class Block {

	/** Series id => block title. A new series in baekur-skra.json needs a line here to get its own block. */
	public const RADIR = array(
		'ordsifjaskra'  => 'Orðsifjaskrá Vasaskrímslanna',
		'storubaekur'   => 'Vasaskrímslabækurnar',
		'lestrarbaekur' => 'Lestrarbækurnar',
		'audlesid'      => 'Auðlesið',
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
		foreach ( self::RADIR as $id => $title ) {
			register_block_type( 'vasaskrimsli/baekur-' . $id, array(
				'api_version'     => 3,
				'title'           => $title,
				'category'        => 'widgets',
				'editor_script'   => 'vasaskrimsli-baekur-editor',
				'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
				'render_callback' => static fn() => self::render( $id ),
			) );
		}
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

	/** The series with this id, or null. */
	public static function rod( array $data, string $id ): ?array {
		foreach ( $data['radir'] ?? array() as $r ) {
			if ( ( $r['id'] ?? '' ) === $id ) {
				return $r;
			}
		}
		return null;
	}

	/**
	 * What the list shows: the series' lots when it is sold in lots (with the cover of each lot's first book), else
	 * its books.
	 * @return array[]
	 */
	public static function items( array $rod ): array {
		if ( empty( $rod['pakkar'] ) ) {
			return $rod['baekur'];
		}
		$covers = array_column( $rod['baekur'], 'kapa', 'id' );
		return array_map( static fn( $p ) => $p + array( 'kapa' => $covers[ $p['baekur'][0] ] ?? '' ), $rod['pakkar'] );
	}

	public static function render( string $id ): string {
		$data = self::data();
		$rod  = self::rod( $data, $id );
		if ( ! $rod ) {
			return '';
		}
		\Vasaskrimsli\Assets::module( 'baekur' );
		$vorur = Verslun::vorur( $data['vorur'] ?? array(), (string) ( $data['verslun']['slod'] ?? '' ) );
		$base  = VASASKRIMSLI_URL . 'features/baekur/';

		$html = '<ul class="vs-bk__grid">';
		foreach ( self::items( $rod ) as $b ) {
			$vara  = $vorur[ $b['vara'] ?? '' ] ?? null;
			$price = 'faanleg' === $b['stada'] ? Verslun::verd( $vara ) : '';
			$html .= '<li class="vs-bk__bok is-' . esc_attr( $b['stada'] ) . '">'
				. '<img src="' . esc_url( $base . $b['kapa'] ) . '" alt="' . esc_attr( 'Forsíða: ' . $b['heiti'] ) . '" loading="lazy" decoding="async">'
				. '<strong>' . esc_html( $b['heiti'] ) . '</strong>'
				. ( '' !== ( $b['efni'] ?? '' ) ? '<span>' . esc_html( $b['efni'] ) . '</span>' : '' )
				. '<span class="vs-bk__stada">' . esc_html( trim( ( self::STADA[ $b['stada'] ] ?? '' ) . ( '' !== $price ? ', ' . $price : '' ) ) ) . '</span>'
				. ( $vara ? '<a href="' . esc_url( $vara['slod'] ) . '">Kaupa í vefverslun</a>' : '' )
				. '</li>';
		}
		$html .= '</ul>';

		$client = array(
			'grunnur' => $base,
			'verslun' => $data['verslun'] ?? array(),
			'vorur'   => $vorur,
			'rod'     => $rod,
			'stada'   => self::STADA,
		);
		return '<div class="vs-baekur alignwide" id="' . esc_attr( $rod['akkeri'] ?? $id ) . '">'
			. '<script type="application/json" class="vs-baekur__data">' . wp_json_encode( $client, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>'
			. '<div class="vs-baekur__app"><div class="vs-bk">' . $html . '</div></div></div>';
	}
}
