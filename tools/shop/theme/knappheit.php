
/* ------------------------------------------------------------------
 * Echte Knappheit: Nur 1 Stück, Zuletzt verkauft (Startseite), Hinweis
 * ------------------------------------------------------------------ */
add_filter( 'woocommerce_get_availability', function ( $a, $product ) {
	if ( $product && $product->is_in_stock() && $product->managing_stock() ) {
		$q = (int) $product->get_stock_quantity();
		if ( 1 === $q ) {
			$a['availability'] = 'Nur 1 Stück da. Ist sie weg, ist sie weg. Kein Nachschub.';
		} elseif ( $q > 1 && $q <= 3 ) {
			$a['availability'] = 'Nur noch ' . $q . ' Stück da.';
		}
	}
	return $a;
}, 20, 2 );

function rk_sold_items( $n = 6 ) {
	$c = get_transient( 'rk_sold_pool' );
	if ( ! is_array( $c ) ) {
		$c = rk_sold_pool();
	}
	return rk_sold_pick( $c, $n );
}

// Taeglich andere Auswahl aus dem Pool, damit es niemand nachverfolgen kann
function rk_sold_pick( $pool, $n ) {
	if ( count( $pool ) <= $n ) {
		return $pool;
	}
	mt_srand( (int) gmdate( 'Ymd' ) );
	$keys = array_keys( $pool );
	for ( $i = count( $keys ) - 1; $i > 0; $i-- ) {
		$j = mt_rand( 0, $i );
		$t = $keys[ $i ]; $keys[ $i ] = $keys[ $j ]; $keys[ $j ] = $t;
	}
	mt_srand();
	$o = array();
	foreach ( array_slice( $keys, 0, $n ) as $k ) {
		$o[] = $pool[ $k ];
	}
	return $o;
}

function rk_sold_pool() {
	$ids = wc_get_products( array(
		'status'       => 'draft',
		'stock_status' => 'outofstock',
		'orderby'      => 'modified',
		'order'        => 'DESC',
		'limit'        => 60,
		'return'       => 'objects',
	) );
	$out = array();
	foreach ( $ids as $p ) {
		if ( ! $p->get_image_id() || '' === (string) $p->get_stock_quantity() || (int) $p->get_stock_quantity() > 0 ) {
			continue;
		}
		$out[] = array(
			'name' => $p->get_name(),
			'img'  => $p->get_image( 'medium', array( 'loading' => 'lazy', 'alt' => '' ) ),
		);
		if ( count( $out ) >= 40 ) {
			break;
		}
	}
	set_transient( 'rk_sold_pool', $out, 6 * HOUR_IN_SECONDS );
	return $out;
}

function rk_sold_strip_html() {
	$items = rk_sold_items( 6 );
	if ( count( $items ) < 4 ) {
		return '';
	}
	$o = '<style>.rk-sold{background:#0b0b0b;border-top:1px solid rgba(255,255,255,.12);padding:56px 0 50px;color:#fff}.rk-sold__in{width:min(1100px,calc(100% - 40px));margin:0 auto}.rk-sold__k{font-family:var(--rk-mono,monospace);font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--rk-red,#ff3b30);margin:0 0 10px}.rk-sold__h{font-family:"Barlow Condensed",sans-serif;font-weight:800;font-size:clamp(32px,5vw,54px);line-height:.95;text-transform:uppercase;margin:0 0 8px}.rk-sold__s{opacity:.7;font-size:15px;margin:0 0 24px}.rk-sold__g{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px}.rk-sold__g li{position:relative;border:1px solid rgba(255,255,255,.14);background:#111;padding:10px}.rk-sold__img{display:block;position:relative;aspect-ratio:3/4;overflow:hidden;background:#0b0b0b}.rk-sold__img img{position:absolute;inset:0;width:100%!important;height:100%!important;object-fit:contain!important;filter:grayscale(1) brightness(.6)}.rk-sold__tag{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate(-12deg);background:var(--rk-red,#ff3b30);color:#fff;font-family:"Barlow Condensed",sans-serif;font-weight:800;font-size:22px;letter-spacing:.08em;padding:2px 12px;text-transform:uppercase}.rk-sold__n{font-size:12px;line-height:1.3;margin-top:8px;opacity:.7;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}</style><section class="rk-sold" aria-label="Zuletzt verkauft"><div class="rk-sold__in"><p class="rk-sold__k">Weg</p><h2 class="rk-sold__h">Zuletzt verkauft<span style="color:var(--rk-red,#ff3b30)">.</span></h2><p class="rk-sold__s">Jede Karte gibt es nur einmal. Diese hier haben schon einen neuen Besitzer.</p><ul class="rk-sold__g">';
	foreach ( $items as $it ) {
		$o .= '<li><span class="rk-sold__img">' . $it['img'] . '<span class="rk-sold__tag">Verkauft</span></span><div class="rk-sold__n">' . esc_html( $it['name'] ) . '</div></li>';
	}
	return $o . '</ul></div></section>';
}

add_filter( 'render_block', function ( $content ) {
	static $done = false;
	if ( $done || ! is_front_page() ) {
		return $content;
	}
	$needle = '<section class="rk-rv"';
	if ( false !== strpos( $content, $needle ) ) {
		$done = true;
		return str_replace( $needle, rk_sold_strip_html() . $needle, $content );
	}
	return $content;
}, 10 );
