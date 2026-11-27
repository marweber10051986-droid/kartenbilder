
/* ---------------------------------------------------------------------------
 * Lieferschein (27.11.2026): Knopf auf der Bestellseite und Sammeldruck aus der
 * Bestellliste. Öffnet eine A4 Seite und startet direkt den Druckdialog.
 * ------------------------------------------------------------------------ */
function rk_ls_url( $ids ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=rk_lieferschein&ids=' . implode( ',', array_map( 'absint', (array) $ids ) ) ), 'rk_lieferschein' );
}

add_action( 'add_meta_boxes', function () {
	foreach ( array( 'shop_order', 'woocommerce_page_wc-orders' ) as $s ) {
		add_meta_box( 'rk-lieferschein', 'Lieferschein', function ( $post_or_order ) {
			$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
			if ( ! $order ) {
				return;
			}
			$n = 0;
			foreach ( $order->get_items() as $it ) {
				$n += (int) $it->get_quantity();
			}
			echo '<p style="margin:0 0 8px">' . (int) $n . ' ' . ( 1 === $n ? 'Karte' : 'Karten' ) . ' mit Fach aus dem Kartenbestand.</p>';
			echo '<a class="button button-primary" style="width:100%;text-align:center;font-weight:600" target="_blank" rel="noopener" href="' . esc_url( rk_ls_url( $order->get_id() ) ) . '">Lieferschein drucken</a>';
		}, $s, 'side', 'high' );
	}
} );

foreach ( array( 'bulk_actions-edit-shop_order', 'bulk_actions-woocommerce_page_wc-orders' ) as $rk_ls_f ) {
	add_filter( $rk_ls_f, function ( $a ) {
		$a['rk_lieferschein'] = 'Lieferscheine drucken';
		return $a;
	} );
}
foreach ( array( 'handle_bulk_actions-edit-shop_order', 'handle_bulk_actions-woocommerce_page_wc-orders' ) as $rk_ls_f ) {
	add_filter( $rk_ls_f, function ( $redirect, $action, $ids ) {
		if ( 'rk_lieferschein' !== $action || ! $ids ) {
			return $redirect;
		}
		return str_replace( '&amp;', '&', rk_ls_url( $ids ) );
	}, 10, 3 );
}

add_action( 'admin_post_rk_lieferschein', function () {
	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( 'Keine Berechtigung' );
	}
	check_admin_referer( 'rk_lieferschein' );
	$ids   = array_filter( array_map( 'absint', explode( ',', isset( $_GET['ids'] ) ? sanitize_text_field( wp_unslash( $_GET['ids'] ) ) : '' ) ) );
	$mail  = get_option( 'woocommerce_email_from_address' );
	$pages = '';
	foreach ( $ids as $id ) {
		$o = wc_get_order( $id );
		if ( ! $o ) {
			continue;
		}
		$addr = $o->get_formatted_shipping_address();
		if ( ! $addr ) {
			$addr = $o->get_formatted_billing_address();
		}
		$m    = $o->get_shipping_methods();
		$m    = $m ? reset( $m )->get_name() : '';
		$rows = '';
		$pos  = 0;
		$sum  = 0;
		foreach ( $o->get_items() as $it ) {
			$p   = $it->get_product();
			$qty = (int) $it->get_quantity();
			$sum += $qty;
			$pos++;
			$sku   = $p ? $p->get_sku() : '';
			$slots = $p ? (string) $p->get_meta( '_rk_slots' ) : '';
			$fach  = $slots ? $slots : $sku;
			$img   = $p ? wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' ) : '';
			$rows .= '<tr><td class="c">' . $pos . '</td>'
				. '<td class="im">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="">' : '' ) . '</td>'
				. '<td><b>' . esc_html( $it->get_name() ) . '</b></td>'
				. '<td class="sku">' . esc_html( $fach ) . '</td>'
				. '<td class="c q">' . $qty . '</td>'
				. '<td class="c"><span class="box"></span></td></tr>';
		}
		$note   = trim( (string) $o->get_customer_note() );
		$date   = $o->get_date_created() ? $o->get_date_created()->date_i18n( 'd.m.Y' ) : '';
		$pages .= '<section class="p">'
			. '<header><div class="logo">ROOKIE <span>BUSINESS</span></div><div class="claim">Sports Trading Card Singles</div></header>'
			. '<div class="from">Rookie Business · Zur Weißen Frau 35 · 59929 Brilon</div>'
			. '<div class="top"><div class="to">' . wp_kses( $addr, array( 'br' => array() ) ) . '</div>'
			. '<table class="meta">'
			. '<tr><th>Lieferschein</th><td>' . esc_html( $o->get_order_number() ) . '</td></tr>'
			. '<tr><th>Bestellung</th><td>#' . esc_html( $o->get_order_number() ) . '</td></tr>'
			. '<tr><th>Bestelldatum</th><td>' . esc_html( $date ) . '</td></tr>'
			. '<tr><th>Lieferdatum</th><td>' . esc_html( date_i18n( 'd.m.Y' ) ) . '</td></tr>'
			. ( $m ? '<tr><th>Versand</th><td>' . esc_html( $m ) . '</td></tr>' : '' )
			. '</table></div>'
			. '<h1>Lieferschein</h1>'
			. '<p class="hi">Hallo ' . esc_html( $o->get_shipping_first_name() ? $o->get_shipping_first_name() : $o->get_billing_first_name() ) . ', danke für deine Bestellung! Hier ist alles, was in deinem Umschlag steckt.</p>'
			. '<table class="it"><thead><tr><th class="c">Pos.</th><th></th><th>Karte</th><th>Fach</th><th class="c">Menge</th><th class="c">Gepackt</th></tr></thead><tbody>' . $rows . '</tbody>'
			. '<tfoot><tr><td colspan="4">Karten gesamt</td><td class="c q">' . (int) $sum . '</td><td></td></tr></tfoot></table>'
			. ( $note ? '<div class="note"><b>Hinweis von dir:</b> ' . esc_html( $note ) . '</div>' : '' )
			. '<div class="thx"><b>Danke, dass du bei Rookie Business kaufst.</b> Jede Karte ist in Penny Sleeve und Toploader verpackt. Passt was nicht? Schreib uns einfach' . ( $mail ? ' an ' . esc_html( $mail ) : '' ) . ', wir kümmern uns. Über eine Bewertung freuen wir uns riesig.</div>'
			. '<footer>Rookie Business · Zur Weißen Frau 35 · 59929 Brilon · rookie-business.de' . ( $mail ? ' · ' . esc_html( $mail ) : '' ) . '</footer>'
			. '</section>';
	}
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Lieferschein</title><style>
	@page{size:A4;margin:0}
	*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;color:#111;background:#e9e9e9;font-size:12.5px}
	.bar{position:sticky;top:0;padding:12px 16px;background:#111;color:#fff;font-size:14px;display:flex;gap:12px;align-items:center;z-index:2}
	.bar button{background:#ff3b30;color:#fff;border:0;padding:9px 16px;font-weight:700;cursor:pointer}
	.p{width:210mm;min-height:297mm;margin:12px auto;background:#fff;padding:16mm 16mm 22mm;position:relative;page-break-after:always}
	header{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:3px solid #111;padding-bottom:6mm}
	.logo{font-weight:900;font-size:26px;letter-spacing:.02em}.logo span{color:#d7191f}
	.claim{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:#666}
	.from{font-size:8.5px;color:#555;text-decoration:underline;margin:12mm 0 2mm}
	.top{display:flex;justify-content:space-between;gap:10mm;align-items:flex-start}
	.to{font-size:13.5px;line-height:1.45;min-height:30mm}
	.meta{border-collapse:collapse;font-size:11.5px}.meta th{text-align:left;font-weight:400;color:#666;padding:2px 10px 2px 0}.meta td{font-weight:700;text-align:right;padding:2px 0}
	h1{font-size:24px;margin:12mm 0 2mm;letter-spacing:.01em}
	.hi{margin:0 0 6mm;color:#333}
	.it{width:100%;border-collapse:collapse}
	.it th{background:#111;color:#fff;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;text-align:left;padding:7px 8px}
	.it td{border-bottom:1px solid #ddd;padding:6px 8px;vertical-align:middle}
	.it tbody tr:nth-child(even) td{background:#f6f6f6}
	.it .c{text-align:center}.it .q{font-weight:700;font-size:14px}
	.it .im{width:34px;padding:3px 4px}.it .im img{height:44px;width:auto;display:block}
	.it .sku{font-family:Menlo,Consolas,monospace;font-size:11px;white-space:nowrap}
	.it tfoot td{border:0;border-top:2px solid #111;font-weight:700;padding-top:8px}
	.box{display:inline-block;width:14px;height:14px;border:1.5px solid #111}
	.note{margin-top:6mm;padding:8px 10px;border-left:3px solid #d7191f;background:#fff5f5}
	.thx{margin-top:8mm;padding:10px 12px;background:#f3f3f3;line-height:1.5}
	footer{position:absolute;left:16mm;right:16mm;bottom:10mm;border-top:1px solid #ccc;padding-top:3mm;font-size:9px;color:#777;text-align:center}
	@media print{body{background:#fff}.bar{display:none}.p{margin:0;box-shadow:none}}
	</style></head><body><div class="bar"><button onclick="window.print()">Drucken</button><span>' . count( $ids ) . ' Lieferschein(e). Der Druckdialog öffnet sich automatisch.</span></div>' . $pages . '<script>window.addEventListener("load",function(){setTimeout(function(){window.print()},300)})</script></body></html>'; // phpcs:ignore
	exit;
} );
