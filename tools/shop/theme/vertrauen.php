
/* ------------------------------------------------------------------
 * Vertrauen: eBay Bewertungsleiste (Startseite, Produktseiten)
 * und Mengenrabatt Fortschritt im Warenkorb.
 * Werte stehen in rk_ebay_stats() und werden von Hand gepflegt.
 * ------------------------------------------------------------------ */
function rk_ebay_stats() {
	return array(
		'quote'  => 100,
		'neg'    => 0,
		'seit'   => 2020,
		'profil' => 'https://www.ebay.de/fdbk/feedback_profile/rookie_business',
	);
}

function rk_ebay_css() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	return '<style>
.rk-eb{background:#0b0b0b;border-top:1px solid rgba(255,255,255,.12);border-bottom:1px solid rgba(255,255,255,.12);margin:0;padding:0}
.rk-eb__in{max-width:1280px;margin:0 auto;padding:14px 20px;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:10px 34px;font-family:"Barlow Condensed",sans-serif;font-size:17px;letter-spacing:.04em;text-transform:uppercase;color:#fff}
.rk-eb__in a{color:#fff!important;text-decoration:none;display:inline-flex;align-items:center;gap:10px}
.rk-eb__stars{color:#ffb800;letter-spacing:2px;font-size:18px}
.rk-eb__big{font-weight:800;font-size:26px;color:#fff;line-height:1}
.rk-eb__in a:hover .rk-eb__txt{text-decoration:underline}
.rk-eb__it{opacity:.8}
.rk-eb__it b{color:var(--rk-red,#ff3b30)}
.rk-ebp{display:flex;align-items:center;gap:10px;margin:14px 0;padding:10px 14px;border:1px solid rgba(255,255,255,.2);font-size:14px}
.rk-ebp a{color:inherit;text-decoration:underline}
.rk-ebp .rk-eb__stars{font-size:15px}
.rk-cb{margin:0 0 22px;padding:16px 18px;border:1px solid rgba(255,255,255,.2);background:#0b0b0b;color:#fff;font-family:"Barlow Condensed",sans-serif}
.rk-cb__t{font-size:19px;letter-spacing:.03em;text-transform:uppercase;margin-bottom:10px}
.rk-cb__t b{color:var(--rk-red,#ff3b30)}
.rk-cb__bar{position:relative;height:10px;background:rgba(255,255,255,.14)}
.rk-cb__fill{position:absolute;left:0;top:0;bottom:0;background:var(--rk-red,#ff3b30);transition:width .4s}
.rk-cb__tick{position:absolute;top:-4px;width:2px;height:18px;background:rgba(255,255,255,.55)}
.rk-cb__lab{position:relative;height:18px;margin-top:8px;font-size:13px;opacity:.75;letter-spacing:.04em}
.rk-cb__lab span{position:absolute;transform:translateX(-50%);white-space:nowrap}
.rk-cb__lab span:first-child{transform:none}
@media(max-width:700px){.rk-eb__in{font-size:15px;gap:8px 20px}.rk-cb__lab{font-size:11px}}
</style>';
}

function rk_ebay_bar() {
	$s = rk_ebay_stats();
	return rk_ebay_css() . '<section class="rk-eb" aria-label="Bewertungen auf eBay"><div class="rk-eb__in">'
		. '<a href="' . esc_url( $s['profil'] ) . '" target="_blank" rel="noopener"><span class="rk-eb__stars" aria-hidden="true">★★★★★</span><span class="rk-eb__big">' . (int) $s['quote'] . ' %</span><span class="rk-eb__txt">positive Bewertungen auf eBay</span></a>'
		. '<span class="rk-eb__it"><b>' . (int) $s['neg'] . '</b> negative Bewertungen</span>'
		. '<span class="rk-eb__it">Verkäufer auf eBay seit <b>' . (int) $s['seit'] . '</b></span>'
		. '<span class="rk-eb__it">Versand nur <b>1,10 €</b> pro Bestellung</span>'
		. '</div></section>';
}

add_filter( 'render_block', function ( $content ) {
	static $done = false;
	if ( ! $done && is_front_page() && false !== strpos( $content, 'rk-bigsearch' ) ) {
		$done = true;
		return rk_ebay_bar() . $content;
	}
	return $content;
}, 8 );

add_action( 'woocommerce_single_product_summary', function () {
	$s = rk_ebay_stats();
	echo rk_ebay_css() . '<div class="rk-ebp"><span class="rk-eb__stars" aria-hidden="true">★★★★★</span><span>' . (int) $s['quote'] . ' % positive Bewertungen auf <a href="' . esc_url( $s['profil'] ) . '" target="_blank" rel="noopener">eBay</a>, Versand nur 1,10 € pro Bestellung</span></div>'; // phpcs:ignore
}, 35 );

/* Mengenrabatt Fortschritt im Warenkorb (Stufen: 3 = 5 %, 5 = 10 %, 10 = 15 %) */
add_filter( 'render_block', function ( $content, $block ) {
	if ( empty( $block['blockName'] ) || 'woocommerce/cart' !== $block['blockName'] ) {
		return $content;
	}
	$n   = ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
	$bar = rk_ebay_css() . '<div class="rk-cb" id="rk-cb" data-n="' . $n . '"><div class="rk-cb__t" id="rk-cb-t"></div>'
		. '<div class="rk-cb__bar"><div class="rk-cb__fill" id="rk-cb-f"></div>'
		. '<i class="rk-cb__tick" style="left:30%"></i><i class="rk-cb__tick" style="left:50%"></i><i class="rk-cb__tick" style="left:calc(100% - 2px)"></i></div>'
		. '<div class="rk-cb__lab"><span style="left:0">1</span><span style="left:30%">3 Karten: 5 %</span><span style="left:50%">5 Karten: 10 %</span><span style="left:100%;transform:translateX(-100%)">10 Karten: 15 %</span></div></div>'
		. '<script>(function(){var T=[[3,5],[5,10],[10,15]];function render(n){var t=document.getElementById("rk-cb-t"),f=document.getElementById("rk-cb-f");if(!t)return;var nx=null,cur=0;for(var i=0;i<T.length;i++){if(n>=T[i][0])cur=T[i][1];else if(!nx)nx=T[i];}'
		. 'if(!nx){t.innerHTML="<b>"+cur+" % Mengenrabatt aktiv.</b> Mehr geht nicht, danke fürs Wühlen."}else{var d=nx[0]-n;t.innerHTML=(cur?"<b>"+cur+" % Rabatt aktiv.</b> ":"")+"Noch <b>"+d+" "+(d===1?"Karte":"Karten")+"</b> bis <b>"+nx[1]+" % Rabatt</b>"}'
		. 'f.style.width=Math.min(100,n/10*100)+"%"}'
		. 'var el=document.getElementById("rk-cb");render(parseInt(el.getAttribute("data-n"),10)||0);'
		. 'function sub(){if(!window.wp||!wp.data||!wp.data.select("wc/store/cart")){return setTimeout(sub,500)}var last=-1;wp.data.subscribe(function(){try{var c=wp.data.select("wc/store/cart").getCartData();var q=0;(c.items||[]).forEach(function(i){q+=i.quantity});if(q!==last){last=q;render(q)}}catch(e){}})}sub();})();</script>';
	return $bar . $content;
}, 10, 2 );
