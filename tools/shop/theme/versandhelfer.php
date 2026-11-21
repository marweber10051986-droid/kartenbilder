
/* ===== Versandhelfer: zeigt bei jeder Bestellung, womit sie verschickt wird =====
   12 g pro Karte inkl. Penny Sleeve und Toploader, plus Verpackung.
   Versandarten: 1 = DE Brief, 3 = DE versichert, 4 = EU Maxibrief mit Tracking, 2 = EU versichert */
function rk_vh_info( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return null;
	}
	$karten = 0;
	foreach ( $order->get_items() as $it ) {
		$karten += (int) $it->get_quantity();
	}
	$inst = 0;
	$name = '';
	foreach ( $order->get_shipping_methods() as $m ) {
		$inst = (int) $m->get_instance_id();
		$name = $m->get_name();
		break;
	}
	$land  = $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country();
	$g     = 12 * $karten;
	$porto = 'https://internetmarke.deutschepost.de/';
	$dhl   = 'https://www.dhl.de/de/privatkunden/pakete-versenden/online-frankieren.html';
	$hint  = '';
	if ( 1 === $inst ) {
		$gb = $g + 10;
		if ( $gb <= 50 ) {
			$prod = 'Kompaktbrief';
			$hint = 'bis 50 g und 1 cm dick';
		} elseif ( $gb <= 500 ) {
			$prod = 'Großbrief';
			$hint = 'bis 500 g und 2 cm dick, sonst Maxibrief';
		} elseif ( $gb <= 1000 ) {
			$prod = 'Maxibrief';
			$hint = 'bis 1000 g und 5 cm dick';
		} else {
			$prod = 'Zu schwer für einen Brief: DHL Päckchen nehmen';
			$porto = $dhl;
		}
		$pack = 'Luftpolsterumschlag, ohne Sendungsverfolgung';
	} elseif ( 3 === $inst ) {
		$gb    = $g + 150;
		$prod  = $gb <= 2000 ? 'DHL Paket 2 kg' : ( $gb <= 5000 ? 'DHL Paket 5 kg' : 'DHL Paket 10 kg' );
		$pack  = 'Karton, versichert mit Sendungsverfolgung';
		$porto = $dhl;
	} elseif ( 4 === $inst ) {
		$gb   = $g + 10;
		$prod = $gb <= 1000 ? 'Maxibrief International + Tracking' : 'Maxibrief International bis 2 kg + Tracking';
		$pack = 'Luftpolsterumschlag, Sendungsnummer an den Kunden';
		if ( $gb > 2000 ) {
			$prod  = 'Zu schwer für einen Brief: DHL Päckchen International nehmen';
			$porto = $dhl;
		}
	} elseif ( 2 === $inst ) {
		$gb    = $g + 150;
		$prod  = $gb <= 2000 ? 'DHL Päckchen International' : 'DHL Paket International';
		$pack  = 'Karton, versichert mit Sendungsverfolgung';
		$porto = $dhl;
	} else {
		return array( 'prod' => 'Versandart unbekannt: ' . ( $name ? $name : 'keine' ), 'pack' => '', 'hint' => '', 'gramm' => $g, 'karten' => $karten, 'land' => $land, 'link' => $porto, 'kunde' => $name );
	}
	return array( 'prod' => $prod, 'pack' => $pack, 'hint' => $hint, 'gramm' => $gb, 'karten' => $karten, 'land' => $land, 'link' => $porto, 'kunde' => $name );
}

function rk_vh_html( $order, $mail = false ) {
	$i = rk_vh_info( $order );
	if ( ! $i ) {
		return '';
	}
	$st = $mail ? 'style="border:2px solid #ff3b30;padding:12px 14px;margin:0 0 18px;font-family:Arial,sans-serif"' : 'class="rk-vh"';
	$o  = '<div ' . $st . '>';
	$o .= '<div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#888">So verschickst du diese Bestellung</div>';
	$o .= '<div style="font-size:18px;font-weight:700;margin:4px 0 6px;color:#d0021b">' . esc_html( $i['prod'] ) . '</div>';
	$o .= '<div>' . (int) $i['karten'] . ' Karten, ca. ' . (int) $i['gramm'] . ' g mit Verpackung, nach ' . esc_html( $i['land'] ) . '</div>';
	if ( $i['pack'] ) {
		$o .= '<div>' . esc_html( $i['pack'] ) . '</div>';
	}
	if ( $i['hint'] ) {
		$o .= '<div style="color:#888">Grenze: ' . esc_html( $i['hint'] ) . '</div>';
	}
	$o .= '<div style="color:#888">Kunde hat gewählt: ' . esc_html( $i['kunde'] ) . '</div>';
	$o .= '<div style="margin-top:8px"><a href="' . esc_url( $i['link'] ) . '" target="_blank" rel="noopener">Jetzt frankieren →</a></div>';
	return $o . '</div>';
}

/* Kasten in der Bestellung (klassisch und HPOS) */
add_action( 'add_meta_boxes', function () {
	$screens = array( 'shop_order' );
	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$screens[] = wc_get_page_screen_id( 'shop-order' );
	}
	foreach ( array_unique( $screens ) as $s ) {
		add_meta_box( 'rk-versandhelfer', 'Versand', function ( $post_or_order ) {
			$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
			echo rk_vh_html( $order ); // phpcs:ignore
		}, $s, 'side', 'high' );
	}
} );

/* Spalte in der Bestellübersicht */
foreach ( array( 'manage_edit-shop_order_columns', 'manage_woocommerce_page_wc-orders_columns' ) as $rk_vh_f ) {
	add_filter( $rk_vh_f, function ( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'order_status' === $k ) {
				$new['rk_versand'] = 'Versand';
			}
		}
		if ( ! isset( $new['rk_versand'] ) ) {
			$new['rk_versand'] = 'Versand';
		}
		return $new;
	} );
}
$rk_vh_col = function ( $col, $o ) {
	if ( 'rk_versand' !== $col ) {
		return;
	}
	$order = $o instanceof WC_Order ? $o : wc_get_order( $o );
	$i     = rk_vh_info( $order );
	if ( $i ) {
		echo '<strong>' . esc_html( $i['prod'] ) . '</strong><br><small>' . (int) $i['karten'] . ' Karten, ' . (int) $i['gramm'] . ' g</small>';
	}
};
add_action( 'manage_shop_order_posts_custom_column', $rk_vh_col, 10, 2 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', $rk_vh_col, 10, 2 );

/* In der Mail "Neue Bestellung" an dich ganz oben */
add_action( 'woocommerce_email_order_details', function ( $order, $sent_to_admin ) {
	if ( $sent_to_admin ) {
		echo rk_vh_html( $order, true ); // phpcs:ignore
	}
}, 1, 2 );

add_action( 'admin_head', function () {
	echo '<style>.rk-vh{font-size:13px;line-height:1.5}.rk-vh a{font-weight:600}</style>';
} );
