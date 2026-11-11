<?php
/**
 * wp-admin → Pantanir: every order with its number, date, who ordered, what, and its status; one order in full; the
 * settings (who gets each order, the next number, the confirmation to the one who ordered); and a CSV of them all.
 */

namespace Vasaskrimsli\Pontun;

final class Admin {

	public const SLUG = 'vs-pantanir';

	public static function register(): void {
		add_action( 'admin_menu', static function () {
			add_menu_page( 'Pantanir', 'Pantanir', Store::CAPABILITY, self::SLUG, array( self::class, 'render' ), 'dashicons-cart', 22 );
		} );
		add_action( 'admin_post_vs_pontun', array( self::class, 'handle' ) );
	}

	private static function url( array $args = array() ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	/** Status changes, deletion, settings and the CSV, all through admin-post.php with a nonce. */
	public static function handle(): void {
		if ( ! current_user_can( Store::CAPABILITY ) ) {
			wp_die( 'Þú hefur ekki aðgang að pöntunum.' );
		}
		check_admin_referer( 'vs_pontun' );
		$do    = sanitize_key( $_POST['gera'] ?? $_GET['gera'] ?? '' );
		$numer = (int) ( $_POST['numer'] ?? 0 );
		if ( 'csv' !== $do && 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( 'Ógild beiðni.', '', array( 'response' => 405 ) );
		}
		if ( 'stada' === $do ) {
			Store::set_status( $numer, sanitize_key( $_POST['stada'] ?? '' ) );
			wp_safe_redirect( self::url( array( 'numer' => $numer, 'skilabod' => 'stada' ) ) );
		} elseif ( 'eyda' === $do ) {
			Store::delete( $numer );
			wp_safe_redirect( self::url( array( 'skilabod' => 'eytt' ) ) );
		} elseif ( 'stillingar' === $do ) {
			update_option( Store::OPTION_TIL, sanitize_textarea_field( wp_unslash( $_POST['vidtakendur'] ?? '' ) ), false );
			update_option( Store::OPTION_UPPHAF, max( 1, (int) ( $_POST['upphaf'] ?? 1 ) ), false );
			update_option( Store::OPTION_STADF, empty( $_POST['stadfesting'] ) ? '0' : '1', false );
			wp_safe_redirect( self::url( array( 'skilabod' => 'stillingar' ) ) );
		} elseif ( 'csv' === $do ) {
			self::csv();
		}
		exit;
	}

	private static function csv(): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="pantanir-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // So Excel reads it as UTF-8.
		fputcsv( $out, array_merge( array( 'Númer', 'Dagsetning', 'Staða', 'Vörur' ), array_column( Pontun::REITIR, 0 ) ), ',', '"', '' );
		foreach ( Store::all() as $o ) {
			$row = array( $o['numer'], $o['stofnad'], Store::STADA[ $o['stada'] ] ?? $o['stada'], implode( '; ', array_map( array( Pontun::class, 'lina' ), $o['gogn']['linur'] ) ) );
			foreach ( array_keys( Pontun::REITIR ) as $id ) {
				$value = (string) ( $o['gogn']['reitir'][ $id ] ?? '' );
				// Spreadsheet applications can interpret an orderer's text as a formula when opening the CSV.
				$row[] = preg_match( '/^[\s\p{Z}\x{FEFF}]*[=+\-@]/u', $value ) ? "'" . $value : $value;
			}
			fputcsv( $out, $row, ',', '"', '' );
		}
		fclose( $out );
	}

	private static function form( string $do, string $inner, string $extra = '' ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . $extra . '>'
			. '<input type="hidden" name="action" value="vs_pontun"><input type="hidden" name="gera" value="' . esc_attr( $do ) . '">'
			. wp_nonce_field( 'vs_pontun', '_wpnonce', true, false ) . $inner . '</form>';
	}

	private static function status_select( array $o ): string {
		$html = '<select name="stada">';
		foreach ( Store::STADA as $k => $label ) {
			$html .= '<option value="' . esc_attr( $k ) . '"' . selected( $o['stada'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html . '</select>';
	}

	public static function render(): void {
		Store::install();
		$msg = array( 'stada' => 'Staðan var uppfærð.', 'eytt' => 'Pöntuninni var eytt.', 'stillingar' => 'Stillingarnar voru vistaðar.' )[ sanitize_key( $_GET['skilabod'] ?? '' ) ] ?? '';
		echo '<div class="wrap"><h1 class="wp-heading-inline">Pantanir</h1>';
		if ( $msg ) {
			echo '<div class="notice notice-success"><p>' . esc_html( $msg ) . '</p></div>';
		}
		$numer = (int) ( $_GET['numer'] ?? 0 );
		$order = $numer ? Store::get( $numer ) : null;
		if ( $order ) {
			self::render_one( $order );
		} else {
			self::render_list();
		}
		echo '</div>';
	}

	private static function render_one( array $o ): void {
		echo '<p><a href="' . esc_url( self::url() ) . '">← Allar pantanir</a></p>';
		echo '<h2>Pöntun nr. ' . (int) $o['numer'] . '</h2>';
		echo '<pre style="white-space:pre-wrap;font-size:14px;background:#fff;padding:16px;border:1px solid #dcdcde;max-width:760px">'
			. esc_html( Pontun::texti( $o['numer'], mysql2date( 'j.n.Y H:i', $o['stofnad'] ), $o['gogn'] ) ) . '</pre>';
		echo self::form( 'stada', '<input type="hidden" name="numer" value="' . (int) $o['numer'] . '"><p><label>Staða ' . self::status_select( $o ) . '</label> <button class="button button-primary">Vista stöðu</button></p>' );
		echo self::form(
			'eyda',
			'<input type="hidden" name="numer" value="' . (int) $o['numer'] . '"><p><button class="button button-link-delete">Eyða pöntuninni</button></p>',
			' onsubmit="return confirm(\'Eyða pöntun nr. ' . (int) $o['numer'] . ' fyrir fullt og allt?\')"'
		);
	}

	private static function render_list(): void {
		echo ' <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vs_pontun&gera=csv' ), 'vs_pontun' ) ) . '">Sækja CSV</a>';
		$orders = Store::all();
		echo '<p>Næsta pöntun fær númerið <strong>' . (int) Store::next_number() . '</strong>.</p>';
		if ( ! $orders ) {
			echo '<p>Engar pantanir enn.</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Nr.</th><th>Dagsetning</th><th>Viðtakandi</th><th>Pantandi</th><th>Vörur</th><th>Staða</th></tr></thead><tbody>';
			foreach ( $orders as $o ) {
				$r = $o['gogn']['reitir'];
				echo '<tr><td><a href="' . esc_url( self::url( array( 'numer' => $o['numer'] ) ) ) . '"><strong>' . (int) $o['numer'] . '</strong></a></td>'
					. '<td>' . esc_html( mysql2date( 'j.n.Y H:i', $o['stofnad'] ) ) . '</td>'
					. '<td>' . esc_html( $r['vidtakandi'] ?? '' ) . '</td>'
					. '<td>' . esc_html( ( $r['nafn'] ?? '' ) . ', ' . ( $r['netfang'] ?? '' ) ) . '</td>'
					. '<td>' . esc_html( implode( '; ', array_map( array( Pontun::class, 'lina' ), $o['gogn']['linur'] ) ) ) . '</td>'
					. '<td>' . esc_html( Store::STADA[ $o['stada'] ] ?? $o['stada'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<h2 style="margin-top:32px">Stillingar</h2>';
		echo self::form( 'stillingar', '<table class="form-table"><tr><th><label for="vs-vidtakendur">Viðtakendur pantana</label></th><td>'
			. '<textarea id="vs-vidtakendur" name="vidtakendur" rows="3" cols="50">' . esc_textarea( (string) get_option( Store::OPTION_TIL, '' ) ) . '</textarea>'
			. '<p class="description">Eitt netfang í hverri línu, t.d. þitt og prentsmiðjunnar. Hver pöntun er send á öll. Tómt: netfang vefsins (' . esc_html( get_option( 'admin_email' ) ) . ').</p></td></tr>'
			. '<tr><th><label for="vs-upphaf">Lægsta pöntunarnúmer</label></th><td><input id="vs-upphaf" name="upphaf" type="number" min="1" value="' . (int) get_option( Store::OPTION_UPPHAF, 1 ) . '">'
			. '<p class="description">Pantanir fá númer í röð: einu hærra en sú síðasta. Settu hér fyrsta númerið, t.d. á eftir síðustu pöntun úr gamla forminu. Það lækkar aldrei númer sem þegar hafa verið gefin.</p></td></tr>'
			. '<tr><th>Staðfesting</th><td><label><input type="checkbox" name="stadfesting" value="1"' . checked( '0' !== get_option( Store::OPTION_STADF, '1' ), true, false ) . '> Senda þeim sem pantar staðfestingu með pöntunarnúmerinu</label></td></tr>'
			. '</table><p><button class="button button-primary">Vista stillingar</button></p>' );
	}
}
