<?php
/**
 * The public blocks:
 *  - "Orðsifjafræði" (vasaskrimsli/ordsifjar, shortcode [ordsifjar]): the etymology table.
 *  - "Skrímslaskrá" (vasaskrimsli/skrimslaskra, shortcode [skrimslaskra]): an image grid of the same
 *    entries, with three image sizes and a popup showing the entry.
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
		register_block_type(
			VASASKRIMSLI_DIR . 'features/ordsifjar/block-skra',
			array( 'render_callback' => array( self::class, 'render_skra' ) )
		);
		add_shortcode( 'ordsifjar', array( self::class, 'render' ) );
		add_shortcode( 'skrimslaskra', array( self::class, 'render_skra' ) );
	}

	public static function render_skra(): string {
		return self::render_view( 'skra' );
	}

	/**
	 * Adds 'mynd': the URL of the entry's sprite, served from this plugin (assets/sprites/<n>.webp),
	 * or '' when there is none. "351a" uses 351.
	 */
	public static function with_sprite( array $e ): array {
		static $have = null;
		if ( null === $have ) {
			$have = array_flip( array_map( static function ( $f ) {
				return basename( $f, '.webp' );
			}, glob( VASASKRIMSLI_DIR . 'features/ordsifjar/assets/sprites/*.webp' ) ?: array() ) );
		}
		$n              = preg_match( '/^\d+/', (string) $e['numer'], $m ) ? (string) (int) $m[0] : '';
		$found          = '' !== $n && isset( $have[ $n ] );
		$base           = VASASKRIMSLI_URL . 'features/ordsifjar/assets/sprites/';
		$e['mynd']      = $found ? $base . $n . '.webp' : '';
		$e['mynd_stor'] = $found ? $base . 'large/' . $n . '.webp' : ''; // Hover tooltip.
		$e['serstakt']  = Format::serstakt( (string) $e['numer'] );
		return $e;
	}

	public static function render(): string {
		return self::render_view( 'tafla' );
	}

	/** @param string $syn 'tafla' or 'skra'. */
	private static function render_view( string $syn ): string {
		\Vasaskrimsli\Assets::module( 'view' );

		$entries   = array_map( array( self::class, 'with_sprite' ), array_values( Store::all() ) );
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
			if ( 'skra' === $syn ) {
				$html .= self::grid_html( $rows ) . '</section>';
				continue;
			}
			$html .= '<figure class="wp-block-table vs-of__table"><table><thead><tr><th class="vs-of__img"><span class="screen-reader-text">Mynd</span></th><th class="vs-of__num">Númer</th><th>Nafn</th><th class="vs-of__origin">Upprunaorð</th><th>Þýðing</th><th class="vs-of__en">Enska</th></tr></thead><tbody>';
			$cols  = array( 'nafn' => '', 'uppruni' => ' class="vs-of__origin"', 'thyding' => '', 'enska' => ' class="vs-of__en"' );
			foreach ( $rows as $e ) {
				$html .= '<tr><td class="vs-of__img">' . ( $e['mynd'] ? '<img src="' . esc_url( $e['mynd'] ) . '" width="48" height="48" alt="" loading="lazy" decoding="async">' : '' ) . '</td>';
				$html .= '<td class="vs-of__num">' . esc_html( $e['numer'] ) . '</td>';
				foreach ( $cols as $f => $class ) {
					$mark  = 'nafn' === $f ? self::mark_html( $e ) : '';
					$html .= '<td' . $class . '>' . wp_kses( $e[ $f ], Sanitize::allowed_html() ) . $mark . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table></figure></section>';
		}

		return '<div class="vs-ordsifjar alignwide" data-syn="' . esc_attr( $syn ) . '">'
			. '<script type="application/json" class="vs-ordsifjar__data">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>'
			. '<div class="vs-ordsifjar__app"><div class="vs-of">' . $html . '</div></div></div>';
	}

	/** Plain grid for the Skrímslaskrá page before the script runs (and without JavaScript). */
	private static function grid_html( array $rows ): string {
		$html = '<ul class="vs-of__nojs-grid">';
		foreach ( $rows as $e ) {
			$nafn  = Csv::plain( $e['nafn'] );
			$enska = Csv::plain( $e['enska'] );
			$name  = ( '' !== $nafn && '–' !== $nafn ) ? $nafn : $enska;
			$html .= '<li>' . ( $e['mynd_stor'] ? '<img src="' . esc_url( $e['mynd_stor'] ) . '" width="112" height="112" alt="" loading="lazy" decoding="async">' : '' )
				. '<span>#' . esc_html( $e['numer'] ) . '</span><strong>' . esc_html( $name ) . '</strong>'
				. ( $name !== $enska ? '<span>' . esc_html( $enska ) . '</span>' : '' ) . self::mark_html( $e ) . '</li>';
		}
		return $html . '</ul>';
	}

	/** The marking for a special kind (Tímaflækingur), or ''. */
	private static function mark_html( array $e ): string {
		return $e['serstakt'] ? '<span class="vs-of-mark">' . esc_html( $e['serstakt'] ) . '</span>' : '';
	}
}
