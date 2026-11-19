
/* ------------------------------------------------------------------
 * 80-Cent-Wühlkiste: Abschnitt auf der Startseite ({{wuehlkiste}}).
 * Zeigt Base Cards mit dem Schlagwort "wuehlkiste", täglich neu gemischt.
 * ------------------------------------------------------------------ */
function rk_wk_data() {
	$key  = 'rk_wk_' . wp_date( 'Ymd' );
	$data = get_transient( $key );
	if ( is_array( $data ) ) {
		return $data;
	}
	$ids = wc_get_products( array(
		'tag'          => array( 'wuehlkiste' ),
		'status'       => 'publish',
		'stock_status' => 'instock',
		'limit'        => -1,
		'return'       => 'ids',
	) );
	$counts = array();
	foreach ( array( 'mlb' => 'MLB', 'nba' => 'NBA', 'nfl' => 'NFL', 'tcg' => 'TCG', 'fussball' => 'Fußball' ) as $slug => $label ) {
		$c = count( wc_get_products( array(
			'tag'          => array( 'wuehlkiste' ),
			'category'     => array( $slug ),
			'status'       => 'publish',
			'stock_status' => 'instock',
			'limit'        => -1,
			'return'       => 'ids',
		) ) );
		$counts[ $slug ] = array( $label, $c );
	}
	mt_srand( (int) wp_date( 'Ymd' ) );
	shuffle( $ids );
	mt_srand();
	$data = array(
		'total'  => count( $ids ),
		'pick'   => array_slice( $ids, 0, 15 ),
		'counts' => $counts,
	);
	set_transient( $key, $data, HOUR_IN_SECONDS );
	return $data;
}

function rk_wuehlkiste_section() {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return '';
	}
	$d = rk_wk_data();
	if ( $d['total'] < 5 ) {
		return '';
	}
	$link  = get_term_link( 'wuehlkiste', 'product_tag' );
	$link  = is_wp_error( $link ) ? home_url( '/shop/' ) : $link;
	$items = '';
	foreach ( $d['pick'] as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$img_id = $p->get_image_id();
		$img    = $img_id ? wp_get_attachment_image( $img_id, 'woocommerce_single', false, array( 'loading' => 'lazy', 'alt' => $p->get_name(), 'sizes' => '220px' ) ) : wc_placeholder_img( 'woocommerce_single' );
		$items .= '<li class="rk-car__item"><a href="' . esc_url( $p->get_permalink() ) . '">'
			. '<span class="rk-car__img">' . $img . '<span class="rk-wk__tag">0,80 €</span></span>'
			. '<span class="rk-car__name">' . esc_html( $p->get_name() ) . '</span>'
			. '<span class="rk-car__price">' . wp_kses_post( $p->get_price_html() ) . '</span>'
			. '</a></li>';
	}
	$n     = max( 1, substr_count( $items, '<li ' ) );
	$reps  = max( 2, (int) ceil( 12 / $n ) * 2 );
	$track = '';
	for ( $i = 0; $i < $reps; $i++ ) {
		$track .= $i ? str_replace( '<li class="rk-car__item">', '<li class="rk-car__item" aria-hidden="true">', $items ) : $items;
	}
	$secs  = max( 30, $n * $reps * 3 );
	$chips = '';
	foreach ( $d['counts'] as $slug => $c ) {
		$chips .= $c[1] ? '<a class="rk-wk__chip" href="' . esc_url( add_query_arg( 'product_cat', $slug, $link ) ) . '">' . esc_html( $c[0] ) . ' <b>' . (int) $c[1] . '</b></a>' : '<span class="rk-wk__chip rk-wk__chip--leer">' . esc_html( $c[0] ) . ' <b>0</b></span>';
	}
	$total = (int) $d['total'];

	$css = '<style>
.rk-main{overflow-x:clip}
.rk-wk{position:relative;isolation:isolate;padding-top:90px!important;padding-bottom:90px!important}
.rk-wk::before{content:"";position:absolute;top:0;bottom:0;left:calc(50% - 50vw);right:calc(50% - 50vw);background:var(--rk-panel,#141414);border-top:1px solid var(--rk-line,#262626);border-bottom:1px solid var(--rk-line,#262626);z-index:-1}
.rk-wk__badge{display:inline-block;background:var(--rk-red,#ff3b30);color:#fff;font-family:"Barlow Condensed",sans-serif;font-weight:800;font-size:15px;letter-spacing:.08em;text-transform:uppercase;padding:4px 10px;transform:rotate(-3deg);margin-bottom:10px}
.rk-wk__stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.12);margin:8px 0 28px}
.rk-wk__stat{background:#0b0b0b;padding:18px 20px}
.rk-wk__stat b{display:block;font-family:"Barlow Condensed",sans-serif;font-weight:800;font-size:clamp(30px,4vw,46px);line-height:1;color:#fff}
.rk-wk__stat b i{font-style:normal;color:var(--rk-red,#ff3b30)}
.rk-wk__stat span{display:block;margin-top:6px;font-size:13px;opacity:.7;letter-spacing:.04em;text-transform:uppercase}
.rk-wk .rk-car__track{animation-direction:reverse}
.rk-wk .rk-car__img{position:relative}
.rk-wk__tag{position:absolute;top:8px;right:8px;background:var(--rk-red,#ff3b30);color:#fff;font-family:"Barlow Condensed",sans-serif;font-weight:800;font-size:14px;padding:3px 7px;transform:rotate(4deg);box-shadow:0 4px 10px rgba(0,0,0,.5)}
.rk-wk__foot{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:18px;margin-top:26px}
.rk-wk__chips{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.rk-wk__chips>span{font-size:13px;opacity:.7;text-transform:uppercase;letter-spacing:.06em;margin-right:4px}
.rk-wk__chip{border:1px solid rgba(255,255,255,.25);color:#fff!important;text-decoration:none;padding:8px 14px;font-family:"Barlow Condensed",sans-serif;font-weight:700;font-size:17px;letter-spacing:.04em;text-transform:uppercase;transition:border-color .2s,background .2s}
.rk-wk__chip b{color:var(--rk-red,#ff3b30);margin-left:4px}
.rk-wk__chip--leer{opacity:.35;cursor:default}.rk-wk__chip:hover{border-color:var(--rk-red,#ff3b30);background:rgba(255,59,48,.12)}
@media(max-width:700px){.rk-wk__stats{grid-template-columns:1fr}.rk-wk__stat{padding:14px 16px}.rk-wk__foot{flex-direction:column;align-items:flex-start}}
</style>';

	return $css . '<section class="rk-section rk-wk" id="wuehlkiste">'
		. '<div class="rk-section__head"><div>'
		. '<p class="rk-label">02 / Wühlkiste</p>'
		. '<span class="rk-wk__badge">Jede Karte 0,80 €</span>'
		. '<h2 class="rk-h2">Die 80-Cent-Wühlkiste<span class="rk-red">.</span></h2>'
		. '<p class="rk-section__lead">' . $total . ' Base Cards von Superstars bis Fan-Favoriten. Set komplettieren, Lieblingsteam sammeln oder einfach stöbern: Hier kostet jede Karte 0,80 €, egal wer drauf ist.</p>'
		. '</div><a class="rk-link-arrow" href="' . esc_url( $link ) . '">Ab in die Kiste <span aria-hidden="true">↗</span></a></div>'
		. '<div class="rk-wk__stats">'
		. '<div class="rk-wk__stat"><b>0,80<i>€</i></b><span>pro Karte, egal ob Star oder Rollenspieler</span></div>'
		. '<div class="rk-wk__stat"><b>10 = 8<i>€</i></b><span>zehn Karten, acht Euro, plus Mengenrabatt</span></div>'
		. '<div class="rk-wk__stat"><b>' . $total . '</b><span>Karten zum Durchwühlen, täglich neu gemischt</span></div>'
		. '</div>'
		. '<div class="rk-car" style="--rk-car-dur:' . (int) $secs . 's"><ul class="rk-car__track">' . $track . '</ul></div>'
		. '<div class="rk-wk__foot"><div class="rk-wk__chips"><span>Wühlen nach Sportart:</span>' . $chips . '</div>'
		. '<a class="rk-btn" href="' . esc_url( $link ) . '">Jetzt wühlen <span aria-hidden="true">↗</span></a></div>'
		. '</section>';
}

add_filter( 'render_block', function ( $content ) {
	if ( false !== strpos( $content, '{{wuehlkiste}}' ) ) {
		$content = str_replace( '{{wuehlkiste}}', rk_wuehlkiste_section(), $content );
	}
	return $content;
}, 9 );

function rk_wk_reset() {
	delete_transient( 'rk_wk_' . wp_date( 'Ymd' ) );
}
add_action( 'woocommerce_update_product', 'rk_wk_reset' );
add_action( 'woocommerce_new_product', 'rk_wk_reset' );
