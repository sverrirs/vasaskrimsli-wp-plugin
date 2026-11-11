<?php
/**
 * Frontend order access for the printer. WordPress owns authentication, and the separate capability grants only
 * portal access and the transition to Afgreidd. Existing order editors may also use the portal.
 */

namespace Vasaskrimsli\Pontun;

final class Portal {

	public const ROLE       = 'vs_prentsmidja';
	public const CAPABILITY = 'vs_afgreida_pantanir';
	private const SHORTCODE = 'pantanir_prentsmidju';
	private const ACTION    = 'vs_pontun_afgreida';

	private static int $page_id = 0;

	public static function register(): void {
		add_action( 'init', static function () {
			add_shortcode( self::SHORTCODE, array( self::class, 'render' ) );
		} );
		add_action( 'admin_init', array( self::class, 'install' ) );
		add_action( 'template_redirect', array( self::class, 'prepare_page' ), 0 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'handle' ) );
	}

	public static function install(): void {
		add_role( self::ROLE, 'Prentsmiðja (Pantanir)', array( 'read' => true, self::CAPABILITY => true ) );
	}

	private static function is_portal_page( int $page_id ): bool {
		if ( $page_id < 1 ) {
			return false;
		}
		$page = get_post( $page_id );
		return $page && 'page' === $page->post_type && 'publish' === $page->post_status
			&& has_shortcode( $page->post_content, self::SHORTCODE );
	}

	public static function prepare_page(): void {
		$page_id = get_queried_object_id();
		if ( ! is_page() || ! self::is_portal_page( $page_id ) ) {
			return;
		}
		self::$page_id = $page_id;
		// The list, details and login form must never be stored in a shared page cache.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'X-Robots-Tag: noindex, noarchive', true );
		wp_enqueue_style(
			'vasaskrimsli-pontun-portal',
			VASASKRIMSLI_URL . 'features/pontun/portal.css',
			array(),
			VASASKRIMSLI_VERSION
		);
	}

	private static function allowed(): bool {
		return is_user_logged_in() && ( current_user_can( self::CAPABILITY ) || current_user_can( Store::CAPABILITY ) );
	}

	private static function number( $value ): int {
		return (int) filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
	}

	private static function url( int $page_id, int $numer = 0 ): string {
		$url = get_permalink( $page_id );
		return $numer ? add_query_arg( 'pontun', $numer, $url ) : $url;
	}

	private static function nonce_action( int $page_id, int $numer ): string {
		return self::ACTION . '_' . $page_id . '_' . $numer;
	}

	public static function handle(): void {
		if ( ! self::allowed() ) {
			wp_die( 'Þú hefur ekki aðgang að pöntunum.', '', array( 'response' => 403 ) );
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( 'Ógild beiðni.', '', array( 'response' => 405 ) );
		}
		$page_id = self::number( $_POST['sida'] ?? null );
		$numer   = self::number( $_POST['pontun'] ?? null );
		if ( ! $numer || ! self::is_portal_page( $page_id ) ) {
			wp_die( 'Ógild beiðni.', '', array( 'response' => 400 ) );
		}
		check_admin_referer( self::nonce_action( $page_id, $numer ) );
		$changed = Store::complete( $numer );
		if ( false === $changed ) {
			wp_die( 'Ekki tókst að vista stöðuna.', '', array( 'response' => 500 ) );
		}
		$result = StatusChange::finish( $numer, 'afgreidd', $changed, '1' === ( $_POST['senda_stodu'] ?? '' ) );
		wp_safe_redirect( add_query_arg( 'skilabod', $result, self::url( $page_id, $numer ) ), 303 );
		exit;
	}

	public static function render(): string {
		// Render private data only on the actual page prepared above, never in excerpts, feeds or the REST API.
		if ( ! self::$page_id ) {
			return '';
		}
		$numer = self::number( $_GET['pontun'] ?? null );
		$url   = self::url( self::$page_id );
		if ( ! is_user_logged_in() ) {
			return '<div class="vs-prent vs-prent__innskraning"><p>Skráðu þig inn til að skoða pantanir.</p>'
				. wp_login_form( array(
					'echo'           => false,
					'redirect'       => self::url( self::$page_id, $numer ),
					'remember'       => false,
					'label_username' => 'Notandanafn eða netfang',
					'label_password' => 'Lykilorð',
					'label_log_in'   => 'Skrá inn',
				) )
				. '<p><a href="' . esc_url( wp_lostpassword_url( $url ) ) . '">Gleymt lykilorð?</a></p></div>';
		}
		$logout = '<a href="' . esc_url( wp_logout_url( $url ) ) . '">Skrá út</a>';
		if ( ! self::allowed() ) {
			return '<div class="vs-prent"><p>Þú hefur ekki aðgang að pöntunum.</p><p>' . $logout . '</p></div>';
		}
		Store::install();
		return '<div class="vs-prent"><div class="vs-prent__stjorn">' . $logout . '</div>'
			. ( $numer ? self::detail( $url, $numer ) : self::orders() ) . '</div>';
	}

	private static function orders(): string {
		$orders = Store::all();
		if ( ! $orders ) {
			return '<p>Engar pantanir enn.</p>';
		}
		$html = '<ul class="vs-prent__pantanir">';
		foreach ( $orders as $order ) {
			$html .= '<li><div class="vs-prent__haus">'
				. '<a href="' . esc_url( self::url( self::$page_id, $order['numer'] ) ) . '">Pöntun nr. ' . (int) $order['numer'] . '</a>'
				. '<span>' . esc_html( Store::STADA[ $order['stada'] ] ?? $order['stada'] ) . '</span></div>'
				. '<p class="vs-prent__lysing">' . esc_html( $order['gogn']['reitir']['vidtakandi'] ?? '' )
				. ' · ' . esc_html( mysql2date( 'j.n.Y H:i', $order['stofnad'] ) ) . '</p>';
			foreach ( $order['gogn']['linur'] as $lina ) {
				$html .= '<div>' . esc_html( Pontun::lina( $lina ) ) . '</div>';
			}
			$html .= '</li>';
		}
		return $html . '</ul>';
	}

	private static function detail( string $url, int $numer ): string {
		$html  = '<p><a href="' . esc_url( $url ) . '">← Allar pantanir</a></p>';
		$order = Store::get( $numer );
		if ( ! $order ) {
			return $html . '<p>Pöntunin fannst ekki.</p>';
		}
		$html .= '<h2>Pöntun nr. ' . $numer . '</h2>';
		$message = StatusChange::MESSAGES[ sanitize_key( $_GET['skilabod'] ?? '' ) ] ?? '';
		if ( $message ) {
			$html .= '<p role="status">' . esc_html( $message ) . '</p>';
		}
		$html .= '<p>Staða: <strong>' . esc_html( Store::STADA[ $order['stada'] ] ?? $order['stada'] ) . '</strong></p>'
			. '<pre class="vs-prent__pontun">' . esc_html( Pontun::texti( $numer, mysql2date( 'j.n.Y H:i', $order['stofnad'] ), $order['gogn'] ) ) . '</pre>';
		if ( in_array( $order['stada'], Store::OPEN_STATUSES, true ) ) {
			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
				. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">'
				. '<input type="hidden" name="sida" value="' . self::$page_id . '">'
				. '<input type="hidden" name="pontun" value="' . $numer . '">'
				. wp_nonce_field( self::nonce_action( self::$page_id, $numer ), '_wpnonce', false, false )
				. StatusChange::checkbox( $order )
				. '<button class="wp-element-button" type="submit">Merkja sem afgreidda</button></form>';
		}
		return $html;
	}
}
