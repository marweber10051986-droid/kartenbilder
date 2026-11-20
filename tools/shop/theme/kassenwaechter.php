
/* ------------------------------------------------------------------
 * Kassenwaechter: prueft jede Stunde, ob die Kasse fuer Gaeste sauber
 * ausgeliefert wird, und schickt eine Mail, wenn sie kaputt ist und
 * wenn sie wieder laeuft. Test von Hand als Admin: /?rk_kasse_test=1
 * ------------------------------------------------------------------ */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'rk_kasse_check' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'rk_kasse_check' );
	}
	if ( isset( $_GET['rk_kasse_test'] ) && current_user_can( 'manage_options' ) ) {
		$r = rk_kasse_check_run( true );
		wp_die( '<h2>Kassenwächter</h2><p>' . esc_html( $r['ok'] ? 'Alles OK: ' : 'FEHLER: ' ) . esc_html( implode( ' · ', $r['info'] ) ) . '</p>', 'Kassenwächter', array( 'response' => 200 ) );
	}
} );
add_action( 'rk_kasse_check', 'rk_kasse_check_run' );

function rk_kasse_check_run( $manual = false ) {
	$info = array();
	$ok   = true;
	$ids  = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'status' => 'publish', 'stock_status' => 'instock', 'limit' => 1, 'return' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) ) : array();
	if ( empty( $ids ) ) {
		return array( 'ok' => true, 'info' => array( 'kein Produkt auf Lager, Prüfung übersprungen' ) );
	}
	$args = array( 'timeout' => 25, 'redirection' => 0, 'headers' => array( 'Cache-Control' => 'no-cache' ) );
	$add  = wp_remote_get( add_query_arg( array( 'add-to-cart' => $ids[0], 'rk_check' => time() ), home_url( '/' ) ), $args );
	if ( is_wp_error( $add ) ) {
		$ok = false;
		$info[] = 'Startseite nicht erreichbar: ' . $add->get_error_message();
	} else {
		$args['cookies'] = wp_remote_retrieve_cookies( $add );
		$res  = wp_remote_get( add_query_arg( 'rk_check', time(), wc_get_checkout_url() ), $args );
		if ( is_wp_error( $res ) ) {
			$ok = false;
			$info[] = 'Kasse nicht erreichbar: ' . $res->get_error_message();
		} else {
			$code = wp_remote_retrieve_response_code( $res );
			$body = wp_remote_retrieve_body( $res );
			$info[] = 'HTTP ' . $code;
			if ( 200 !== (int) $code ) {
				$ok = false;
				$info[] = 'Kasse liefert keinen Status 200 (Weiterleitung oder Fehler)';
			}
			$tag = preg_match( '/<div[^>]*data-block-name="woocommerce\/checkout"[^>]*>/', $body, $m ) ? $m[0] : '';
			if ( '' === $tag || false === strpos( $tag, 'wp-block-woocommerce-checkout' ) ) {
				$ok = false;
				$info[] = 'Kassen-Block ohne Kennzeichen, die Kasse lädt dann nicht';
			} else {
				$info[] = 'Kassen-Block OK';
			}
			if ( false === strpos( $body, 'checkout-frontend' ) ) {
				$ok = false;
				$info[] = 'Kassen-Skript fehlt';
			} else {
				$info[] = 'Kassen-Skript OK';
			}
		}
	}
	$was = get_option( 'rk_kasse_status', 'ok' );
	$now = $ok ? 'ok' : 'kaputt';
	if ( ! $manual && $was !== $now ) {
		$to   = array( 'marweber10051986@gmail.com', get_option( 'admin_email' ) );
		$subj = $ok ? 'Rookie Business: Kasse läuft wieder' : 'ACHTUNG Rookie Business: Kasse ist kaputt';
		$text = ( $ok ? "Die Kasse auf rookie-business.de funktioniert wieder.\n\n" : "Die Kasse auf rookie-business.de funktioniert gerade nicht. Kunden können nicht bestellen.\n\n" )
			. 'Prüfung: ' . implode( ' · ', $info ) . "\n\nKasse: " . wc_get_checkout_url() . "\nGeprüft: " . wp_date( 'd.m.Y H:i' );
		wp_mail( array_unique( array_filter( $to ) ), $subj, $text );
	}
	if ( ! $manual ) {
		update_option( 'rk_kasse_status', $now, false );
	}
	update_option( 'rk_kasse_last', wp_date( 'd.m.Y H:i' ) . ' ' . $now . ': ' . implode( ' · ', $info ), false );
	return array( 'ok' => $ok, 'info' => $info );
}
