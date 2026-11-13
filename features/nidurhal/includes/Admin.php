<?php
/**
 * wp-admin → Niðurhal: every ZIP file in the folder with its short download link to copy onto a page, its downloads
 * today, in the last 7 and 30 days and in all, and its clicks on the page; the last 30 days as bars; and a CSV of
 * every day. A short version of the table is a widget on the Dashboard (Stjórnborð).
 */

namespace Vasaskrimsli\Nidurhal;

final class Admin {

	public const SLUG = 'vs-nidurhal';

	public static function register(): void {
		add_action( 'admin_menu', static function () {
			add_menu_page( 'Niðurhal', 'Niðurhal', Store::CAPABILITY, self::SLUG, array( self::class, 'render' ), 'dashicons-download', 23 );
		} );
		add_action( 'wp_dashboard_setup', static function () {
			if ( current_user_can( Store::CAPABILITY ) ) {
				wp_add_dashboard_widget( 'vs_nidurhal', 'Niðurhal námsefnis', array( self::class, 'widget' ) );
			}
		} );
		add_action( 'admin_post_vs_nidurhal_csv', array( self::class, 'csv' ) );
	}

	public static function csv(): void {
		if ( ! current_user_can( Store::CAPABILITY ) ) {
			wp_die( 'Þú hefur ekki aðgang að þessu.' );
		}
		check_admin_referer( 'vs_nidurhal_csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="nidurhal-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // So Excel reads it as UTF-8.
		fputcsv( $out, array( 'Dagur', 'Skrá', 'Niðurhal', 'Smellir á síðunni' ), ',', '"', '' );
		foreach ( Store::allt() as $r ) {
			fputcsv( $out, array( $r['dagur'], $r['skra'], (int) $r['nidurhal'], (int) $r['smellir'] ), ',', '"', '' );
		}
		fclose( $out );
		exit;
	}

	/** Every file in the folder, and any file counted that is gone from it, with its counts (0 when none). */
	private static function rodir(): array {
		$counts = Store::skrar();
		$rows   = array();
		$zero   = array( 'idag' => 0, 'vika' => 0, 'manudur' => 0, 'alls' => 0, 'smellir' => 0, 'fyrst' => '' );
		foreach ( array_unique( array_merge( Saekja::skrar(), array_keys( $counts ) ) ) as $name ) {
			$rows[ $name ] = ( $counts[ $name ] ?? $zero ) + array( 'til' => in_array( $name, Saekja::skrar(), true ) );
		}
		ksort( $rows, SORT_NATURAL );
		return $rows;
	}

	private static function tafla( array $rows, bool $stutt ): string {
		$cols = $stutt
			? array( 'vika' => '7 dagar', 'alls' => 'Alls' )
			: array( 'idag' => 'Í dag', 'vika' => '7 dagar', 'manudur' => '30 dagar', 'alls' => 'Alls', 'smellir' => 'Smellir á síðunni' );
		$html = '<table class="widefat striped"><thead><tr><th>' . ( $stutt ? 'Skrá' : 'Skrá og hlekkur' ) . '</th>';
		foreach ( $cols as $label ) {
			$html .= '<th style="text-align:right">' . esc_html( $label ) . '</th>';
		}
		$html .= $stutt ? '' : '<th>Fyrst talið</th>';
		$html .= '</tr></thead><tbody>';
		$sum   = array_fill_keys( array_keys( $cols ), 0 );
		foreach ( $rows as $name => $r ) {
			$html .= '<tr><td>';
			if ( $stutt ) {
				$html .= esc_html( Nidurhal::kort( $name ) );
			} else {
				$html .= '<code>' . esc_html( $name ) . '</code>';
				$html .= $r['til']
					? '<br><input type="text" readonly value="' . esc_attr( Saekja::url( $name ) ) . '" onfocus="this.select()" style="width:100%;max-width:320px;margin-top:4px" aria-label="' . esc_attr( 'Niðurhalshlekkur fyrir ' . $name ) . '">'
					: '<br><em>Skráin er ekki lengur í möppunni.</em>';
			}
			$html .= '</td>';
			foreach ( array_keys( $cols ) as $k ) {
				$sum[ $k ] += $r[ $k ];
				$html      .= '<td style="text-align:right">' . number_format_i18n( $r[ $k ] ) . '</td>';
			}
			$html .= $stutt ? '' : '<td>' . esc_html( $r['fyrst'] ? mysql2date( 'j.n.Y', $r['fyrst'] ) : '' ) . '</td>';
			$html .= '</tr>';
		}
		$html .= '</tbody><tfoot><tr><th>Samtals</th>';
		foreach ( $sum as $n ) {
			$html .= '<th style="text-align:right">' . number_format_i18n( $n ) . '</th>';
		}
		return $html . ( $stutt ? '' : '<th></th>' ) . '</tr></tfoot></table>';
	}

	private static function sulur( array $dagar ): string {
		$max  = max( 1, max( $dagar ) );
		$html = '<div role="img" aria-label="Niðurhal á dag síðustu 30 daga" style="display:flex;align-items:flex-end;gap:3px;height:140px;max-width:760px;padding:8px 0;border-bottom:1px solid #c3c4c7">';
		foreach ( $dagar as $dagur => $n ) {
			$label = mysql2date( 'j.n.', $dagur ) . ': ' . $n;
			$html .= '<div title="' . esc_attr( $label ) . '" style="flex:1;min-width:4px;height:' . max( $n ? 3 : 1, round( 100 * $n / $max ) ) . '%;background:' . ( $n ? '#2271b1' : '#dcdcde' ) . ';border-radius:2px 2px 0 0"></div>';
		}
		$keys = array_keys( $dagar );
		return $html . '</div><p class="description" style="display:flex;justify-content:space-between;max-width:760px;margin-top:4px"><span>'
			. esc_html( mysql2date( 'j.n.Y', reset( $keys ) ) ) . '</span><span>Mest á einum degi: ' . number_format_i18n( max( $dagar ) )
			. '</span><span>' . esc_html( mysql2date( 'j.n.Y', end( $keys ) ) ) . '</span></p>';
	}

	public static function render(): void {
		Store::install();
		$rows = self::rodir();
		echo '<div class="wrap"><h1 class="wp-heading-inline">Niðurhal</h1>';
		if ( Store::skrar() ) {
			echo ' <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vs_nidurhal_csv' ), 'vs_nidurhal_csv' ) ) . '">Sækja CSV</a>';
		}
		echo '<div style="max-width:760px"><p>Hver ZIP-skrá í möppunni <code>' . esc_html( Nidurhal::MAPPA ) . '/</code> hefur stuttan niðurhalshlekk, t.d. <code>'
			. esc_html( home_url( '/s/1' ) ) . '</code>. Settu þá hlekki á síðuna (í stað slóðar skrárinnar): hlekkurinn telur niðurhalið og sendir svo skrána. '
			. 'Talið er þegar niðurhal hefst; hvort því lauk sést ekki.</p>'
			. '<p><strong>Niðurhal</strong> telur alla sem nota hlekkinn, líka þá sem fengu hann sendan. <strong>Smellir á síðunni</strong> telur aðeins smelli fólks á síðu með blokkinni Námsefni: Niðurhalsteljari. '
			. 'Ekki talið: leitarvélar og önnur forrit, sami gestur aftur á sömu skrá innan 30 mínútna, og þú sjálf(ur) þegar þú ert innskráð(ur). Ekkert er geymt um gestina, aðeins fjöldi á dag.</p></div>';
		if ( ! $rows ) {
			echo '<p><strong>Engar ZIP-skrár í möppunni ' . esc_html( Nidurhal::MAPPA ) . '/.</strong></p></div>';
			return;
		}
		echo '<h2>Niðurhal síðustu 30 daga</h2>' . self::sulur( Store::dagar( 30 ) );
		echo '<h2 style="margin-top:24px">Eftir skrám</h2>' . self::tafla( $rows, false ) . '</div>';
	}

	public static function widget(): void {
		Store::install();
		$rows = array_filter( self::rodir(), static fn ( $r ) => $r['alls'] > 0 );
		if ( ! $rows ) {
			echo '<p>Ekkert niðurhal talið enn.</p>';
		} else {
			echo self::tafla( $rows, true );
		}
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">Niðurhal og hlekkir</a></p>';
	}
}
