<?php
/**
 * wp-admin → Niðurhal: downloads of each ZIP file today, in the last 7 and 30 days and in all, the last 30 days as
 * bars, and a CSV of every day. A short version of the table is a widget on the Dashboard (Stjórnborð).
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

	/** "vasaskrimsli-namsefni-pakki-01.zip" from "/namsefnispakkar/vasaskrimsli-namsefni-pakki-01.zip". */
	private static function heiti( string $skra ): string {
		return basename( $skra );
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
		fputcsv( $out, array( 'Dagur', 'Skrá', 'Niðurhal' ), ',', '"', '' );
		foreach ( Store::allt() as $r ) {
			fputcsv( $out, array( $r['dagur'], $r['skra'], (int) $r['fjoldi'] ), ',', '"', '' );
		}
		fclose( $out );
		exit;
	}

	private static function tafla( array $skrar, bool $stutt ): string {
		$cols = $stutt
			? array( 'idag' => 'Í dag', 'vika' => '7 dagar', 'alls' => 'Alls' )
			: array( 'idag' => 'Í dag', 'vika' => '7 dagar', 'manudur' => '30 dagar', 'alls' => 'Alls' );
		$html = '<table class="widefat striped"><thead><tr><th>Skrá</th>';
		foreach ( $cols as $label ) {
			$html .= '<th style="text-align:right">' . esc_html( $label ) . '</th>';
		}
		$html .= $stutt ? '' : '<th>Fyrst talið</th>';
		$html .= '</tr></thead><tbody>';
		$sum   = array_fill_keys( array_keys( $cols ), 0 );
		foreach ( $skrar as $s ) {
			$html .= '<tr><td title="' . esc_attr( $s['skra'] ) . '">' . esc_html( self::heiti( $s['skra'] ) ) . '</td>';
			foreach ( array_keys( $cols ) as $k ) {
				$sum[ $k ] += $s[ $k ];
				$html      .= '<td style="text-align:right">' . number_format_i18n( $s[ $k ] ) . '</td>';
			}
			$html .= $stutt ? '' : '<td>' . esc_html( mysql2date( 'j.n.Y', $s['fyrst'] ) ) . '</td>';
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
		$skrar = Store::skrar();
		echo '<div class="wrap"><h1 class="wp-heading-inline">Niðurhal</h1>';
		if ( $skrar ) {
			echo ' <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vs_nidurhal_csv' ), 'vs_nidurhal_csv' ) ) . '">Sækja CSV</a>';
		}
		echo '<p style="max-width:760px">Hve oft fólk hefur smellt á tengla í ZIP-skrár á síðum með blokkinni <strong>Námsefni: Niðurhalsteljari</strong>. '
			. 'Vefþjónninn afhendir skrárnar sjálfur, svo það er smellurinn sem er talinn, ekki hvort niðurhalinu lauk. '
			. 'Ekki talið: leitarvélar og önnur forrit, sami gestur aftur á sömu skrá innan 30 mínútna, og smellir þeirra sem eru innskráðir sem ritstjórar. '
			. 'Ekkert er geymt um gestina sjálfa, aðeins fjöldi á dag fyrir hverja skrá.</p>';
		if ( ! $skrar ) {
			echo '<p><strong>Ekkert niðurhal talið enn.</strong> Settu blokkina Námsefni: Niðurhalsteljari á síðuna með ZIP-skránum (t.d. Námsefni).</p></div>';
			return;
		}
		echo '<h2>Síðustu 30 dagar</h2>' . self::sulur( Store::dagar( 30 ) );
		echo '<h2 style="margin-top:24px">Eftir skrám</h2>' . self::tafla( $skrar, false ) . '</div>';
	}

	public static function widget(): void {
		Store::install();
		$skrar = Store::skrar();
		if ( ! $skrar ) {
			echo '<p>Ekkert niðurhal talið enn.</p>';
			return;
		}
		echo self::tafla( $skrar, true );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">Nánar um niðurhal</a></p>';
	}
}
