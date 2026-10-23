<?php
/**
 * The public "Orðsifjafræði" block (and [ordsifjar] shortcode).
 *
 * Renders the full tables as plain HTML first, so the content is in the page source for
 * search engines and readers without JavaScript. The React app then takes over the same
 * container and adds search and filters. Generation headings keep their original ids
 * (#fyrsta-kynslod, ...) so existing links to them keep working.
 */

namespace Vasaskrimsli\Ordsifjar;

final class Block {

	public static function register(): void {
		register_block_type(
			VASASKRIMSLI_DIR . 'features/ordsifjar/block',
			array( 'render_callback' => array( self::class, 'render' ) )
		);
		add_shortcode( 'ordsifjar', array( self::class, 'render' ) );
	}

	public static function render(): string {
		\Vasaskrimsli\Assets::module( 'view' );

		$entries   = array_values( Store::all() );
		$kynslodir = Store::kynslodir();
		$data      = array(
			'entries'   => array_map( static function ( $e ) {
				unset( $e['id'] );
				return $e;
			}, $entries ),
			'kynslodir' => $kynslodir,
		);

		$by_gen = array();
		foreach ( $entries as $e ) {
			$by_gen[ $e['kynslod'] ][] = $e;
		}
		$labels = array();
		foreach ( $kynslodir as $k ) {
			$labels[ $k['kynslod'] ] = $k;
		}
		ksort( $by_gen );

		$html = '';
		foreach ( $by_gen as $gen => $rows ) {
			$k     = $labels[ $gen ] ?? array( 'akkeri' => '', 'heiti_html' => $gen . '. kynslóð', 'skyring_html' => '' );
			$html .= '<section class="vs-ordsifjar__gen">';
			$html .= '<h3 class="wp-block-heading"' . ( $k['akkeri'] ? ' id="' . esc_attr( $k['akkeri'] ) . '"' : '' ) . '>' . wp_kses( $k['heiti_html'], Sanitize::allowed_html() ) . '</h3>';
			if ( $k['skyring_html'] ) {
				$html .= '<p>' . wp_kses( $k['skyring_html'], Sanitize::allowed_html() ) . '</p>';
			}
			$html .= '<figure class="wp-block-table"><table><thead><tr><th>Númer</th><th>Nafn</th><th>Upprunaorð</th><th>Þýðing</th><th>Enska</th></tr></thead><tbody>';
			foreach ( $rows as $e ) {
				$html .= '<tr><td>' . esc_html( $e['numer'] ) . '</td>';
				foreach ( array( 'nafn', 'uppruni', 'thyding', 'enska' ) as $f ) {
					$html .= '<td>' . wp_kses( $e[ $f ], Sanitize::allowed_html() ) . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table></figure></section>';
		}

		return '<div class="vs-ordsifjar alignwide">'
			. '<script type="application/json" class="vs-ordsifjar__data">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>'
			. '<div class="vs-ordsifjar__app">' . $html . '</div></div>';
	}
}
