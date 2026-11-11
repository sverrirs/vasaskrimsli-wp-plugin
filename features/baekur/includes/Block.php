<?php
/**
 * The book catalogue blocks: one per series ("Verslun: Lestrarbækurnar", vasaskrimsli/baekur-<series>, shortcode
 * [baekur rod="<series>"]). Each is the series' list and its filter, nothing more: the headings and texts about the
 * books are written on the WordPress page itself, so they can be edited without a new plugin version. A series sold in
 * lots (Lestrarbækurnar, Auðlesið) lists the lots; each lot shows its books.
 *
 * Settings per block: alignment (left, center, right), direction (horizontal rows or a vertical list), the gap
 * between items and their size (small, medium, large).
 *
 * Renders the list as plain HTML first (covers, names, status and price, a link to the shop), so the page works for
 * search engines and readers without JavaScript. The React app then adds the filter and the book viewer.
 * The data is data/baekur.json (made by migration/baekur.py); prices come from the shop when it can be reached.
 */

namespace Vasaskrimsli\Baekur;

final class Block {

	/** Series id => block name (shown as "Verslun: <name>"). A new series in baekur-skra.json needs a line here. */
	public const RADIR = array(
		'ordsifjaskra'  => 'Orðsifjaskrá Vasaskrímslanna',
		'storubaekur'   => 'Vasaskrímslabækurnar',
		'lestrarbaekur' => 'Lestrarbækurnar',
		'audlesid'      => 'Auðlesið',
		'verkefnahefti' => 'Verkefnahefti',
		'veggspjold'    => 'Veggspjöld',
		'smaspjold'     => 'Smáspjöld',
	);

	/**
	 * The status words and filter labels in the gender of what the list shows: a book (kvk), books sold together in a
	 * lot (bækur, kvk plural: ft), or a volume, booklet or poster (bindi, hefti, spjald: hk).
	 */
	public const ORD = array(
		'kvk' => array( 'faanleg' => 'Fáanleg', 'vaentanleg' => 'Væntanleg', 'oll' => 'Allar', 'faanlegar' => 'Fáanlegar', 'vaentanlegar' => 'Væntanlegar', 'kemur' => 'Þessi bók er væntanleg og fæst ekki enn.' ),
		'ft'  => array( 'faanleg' => 'Fáanlegar', 'vaentanleg' => 'Væntanlegar', 'oll' => 'Allar', 'faanlegar' => 'Fáanlegar', 'vaentanlegar' => 'Væntanlegar', 'kemur' => 'Þessar bækur eru væntanlegar og fást ekki enn.' ),
		'hk'  => array( 'faanleg' => 'Fáanlegt', 'vaentanleg' => 'Væntanlegt', 'oll' => 'Öll', 'faanlegar' => 'Fáanleg', 'vaentanlegar' => 'Væntanleg', 'kemur' => 'Þetta er væntanlegt og fæst ekki enn.' ),
	);

	public const ATTRIBUTES = array(
		'jofnun' => array( 'type' => 'string', 'default' => 'left' ),   // left, center, right
		'stefna' => array( 'type' => 'string', 'default' => 'larett' ), // larett (rows), lodrett (a list)
		'bil'    => array( 'type' => 'number', 'default' => 24 ),       // px between items
		'staerd' => array( 'type' => 'string', 'default' => 'litil' ),  // litil, midlungs, stor
		'align'  => array( 'type' => 'string', 'default' => 'wide' ),   // WordPress's own width: wide, full or none
	);

	public static function register(): void {
		wp_register_script(
			'vasaskrimsli-baekur-editor',
			VASASKRIMSLI_URL . 'features/baekur/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
			VASASKRIMSLI_VERSION,
			true
		);
		wp_add_inline_script( 'vasaskrimsli-baekur-editor', 'window.vsBaekurRadir = ' . wp_json_encode( self::RADIR ) . ';', 'before' );
		foreach ( self::RADIR as $id => $title ) {
			register_block_type( 'vasaskrimsli/baekur-' . $id, array(
				'api_version'     => 3,
				'title'           => 'Verslun: ' . $title,
				'category'        => 'widgets',
				'keywords'        => array( 'verslun', 'bækur' ),
				'editor_script'   => 'vasaskrimsli-baekur-editor',
				'attributes'      => self::ATTRIBUTES,
				'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
				'render_callback' => static fn( $attrs ) => self::render( $id, (array) $attrs ),
			) );
		}
		add_shortcode( 'baekur', static fn( $atts ) => self::render( sanitize_key( $atts['rod'] ?? '' ), (array) $atts ) );
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

	/**
	 * The block's settings, checked: unknown values fall back to the defaults, the gap is 0 to 120 px.
	 * @return array{jofnun: string, stefna: string, bil: int, staerd: string}
	 */
	public static function settings( array $attrs ): array {
		$pick = static fn( $v, array $ok, string $default ) => in_array( $v, $ok, true ) ? $v : $default;
		return array(
			'jofnun' => $pick( $attrs['jofnun'] ?? null, array( 'left', 'center', 'right' ), 'left' ),
			'stefna' => $pick( $attrs['stefna'] ?? null, array( 'larett', 'lodrett' ), 'larett' ),
			'bil'    => max( 0, min( 120, (int) ( $attrs['bil'] ?? 24 ) ) ),
			'staerd' => $pick( $attrs['staerd'] ?? null, array( 'litil', 'midlungs', 'stor' ), 'litil' ),
		);
	}

	public static function render( string $id, array $attrs = array() ): string {
		$data = self::data();
		$rod  = self::rod( $data, $id );
		if ( ! $rod ) {
			return '';
		}
		\Vasaskrimsli\Assets::module( 'baekur' );
		$set   = self::settings( $attrs );
		$ord   = self::ORD[ $rod['kyn'] ?? 'kvk' ] ?? self::ORD['kvk'];
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
				. '<span class="vs-bk__stada">' . esc_html( trim( ( $ord[ $b['stada'] ] ?? '' ) . ( '' !== $price ? ', ' . $price : '' ) ) ) . '</span>'
				. ( $vara ? '<a href="' . esc_url( $vara['slod'] ) . '">Kaupa í vefverslun</a>' : '' )
				. '</li>';
		}
		$html .= '</ul>';

		$client = array(
			'grunnur' => $base,
			'verslun' => $data['verslun'] ?? array(),
			'vorur'   => $vorur,
			'rod'     => $rod,
			'ord'     => $ord,
		);
		$classes = 'vs-baekur is-' . $set['jofnun'] . ' is-' . $set['stefna'] . ' is-' . $set['staerd']
			. ( '' !== ( $attrs['align'] ?? 'wide' ) ? ' align' . sanitize_key( $attrs['align'] ?? 'wide' ) : '' );
		return '<div class="' . esc_attr( $classes ) . '" id="' . esc_attr( $rod['akkeri'] ?? $id ) . '" style="--bk-bil:' . $set['bil'] . 'px">'
			. '<script type="application/json" class="vs-baekur__data">' . wp_json_encode( $client, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>'
			. '<div class="vs-baekur__app"><div class="vs-bk">' . $html . '</div></div></div>';
	}
}
