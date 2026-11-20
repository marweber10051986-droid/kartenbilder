
/* ------------------------------------------------------------------
 * 1) Spieler Suche und Parallel Filter im Shop (Titelsuche)
 * ------------------------------------------------------------------ */
function rk_parallel_options() {
	return array(
		'Rookie'               => 'Rookie Cards',
		'Rainbow Foil'         => 'Rainbow Foil',
		'Crackle Foil'         => 'Crackle Foil',
		'Diamante Foil'        => 'Diamante Foil',
		'Football Leather Foil' => 'Football Leather Foil',
		'Sandglitter'          => 'Sandglitter',
		'Pink Sparkle Chrome'  => 'Pink Sparkle Chrome',
		'Blue White Pixel'     => 'Blue White Pixel',
		'Neon Green Pulsar'    => 'Neon Green Pulsar',
		'Refractor'            => 'Refractor',
		'Reverse Holo'         => 'Reverse Holo',
		'Holo'                 => 'Holo',
		'Gold'                 => 'Gold',
		'Silver'               => 'Silver',
		'Purple'               => 'Purple',
	);
}

function rk_filter_extra_fields() {
	return '<a class="rk-filters__want" href="' . esc_url( home_url( '/suchauftrag/' ) ) . '">Karte nicht gefunden? Suchauftrag aufgeben →</a>';
}

add_action( 'wp_head', function () {
	echo '<style>.rk-filters{grid-template-columns:repeat(4,minmax(0,1fr))!important}.rk-filters .rk-filters__txt{width:100%;font-family:var(--rk-body);font-size:15px;background:var(--rk-ink);color:var(--rk-paper);border:1px solid var(--rk-line);padding:12px 14px;border-radius:0;box-sizing:border-box;height:100%}.rk-filters .rk-filters__txt:focus{outline:none;border-color:var(--rk-red)}.rk-filters .rk-filters__txt.is-set{border-color:var(--rk-red)}.rk-filters__reset,.rk-filters__want{grid-column:1/-1;justify-self:start}.rk-filters__reset{order:98}.rk-filters__want{order:99}.rk-filters__want{color:#fff!important;font-size:14px;opacity:.8;text-decoration:underline}@media(max-width:700px){.rk-filters{grid-template-columns:1fr 1fr!important}}</style>';
}, 98 );

function rk_title_like_where( $where, $q ) {
	global $wpdb;
	$t = $q->get( 'rk_title_like' );
	if ( $t && is_array( $t ) ) {
		foreach ( $t as $x ) {
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like( $x ) . '%' ); // phpcs:ignore
		}
	}
	return $where;
}
add_filter( 'posts_where', 'rk_title_like_where', 10, 2 );

add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
		return;
	}
	$terms = array();
	foreach ( array( 'rk_spieler', 'rk_parallel' ) as $k ) {
		if ( ! empty( $_GET[ $k ] ) ) { // phpcs:ignore
			$v = trim( sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) ); // phpcs:ignore
			if ( '' !== $v ) {
				$terms[] = $v;
			}
		}
	}
	if ( $terms ) {
		$q->set( 'rk_title_like', $terms );
	}
} );

/* ------------------------------------------------------------------
 * 2) Mehr Karten vom selben Spieler und Team auf der Produktseite
 * ------------------------------------------------------------------ */
function rk_rel_ids( $args, $exclude, $n ) {
	$a = array_merge( array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'fields'              => 'ids',
		'posts_per_page'      => $n,
		'suppress_filters'    => false,
		'post__not_in'        => $exclude,
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'meta_query'          => array( array( 'key' => '_stock_status', 'value' => 'instock' ) ), // phpcs:ignore
	), $args );
	return get_posts( $a );
}

function rk_rel_list( $ids ) {
	$o = '<ul class="rk-rel__list">';
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$o .= '<li><a href="' . esc_url( get_permalink( $id ) ) . '"><span class="rk-rel__img">' . $p->get_image( 'medium' ) . '</span><span class="rk-rel__n">' . esc_html( $p->get_name() ) . '</span><span class="rk-rel__p">' . wp_kses_post( $p->get_price_html() ) . '</span></a></li>';
	}
	return $o . '</ul>';
}

add_action( 'woocommerce_after_single_product_summary', function () {
	global $product;
	if ( ! $product ) {
		return;
	}
	$pid    = $product->get_id();
	$player = trim( (string) $product->get_attribute( 'Spieler' ) );
	$teams  = wc_get_product_terms( $pid, 'pa_team', array( 'fields' => 'all' ) );
	$team   = ( $teams && ! is_wp_error( $teams ) ) ? $teams[0] : null;
	$shop   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	$seen   = array( $pid );
	$out    = '';
	if ( '' !== $player ) {
		$ids = rk_rel_ids( array( 'rk_title_like' => array( $player ) ), $seen, 6 );
		if ( $ids ) {
			$seen = array_merge( $seen, $ids );
			$out .= '<div class="rk-rel__blk"><div class="rk-rel__head"><h2>Mehr von ' . esc_html( $player ) . '</h2><a href="' . esc_url( add_query_arg( 'rk_spieler', rawurlencode( $player ), $shop ) ) . '">Alle ansehen →</a></div>' . rk_rel_list( $ids ) . '</div>';
		}
	}
	if ( $team ) {
		$ids = rk_rel_ids( array( 'tax_query' => array( array( 'taxonomy' => 'pa_team', 'field' => 'term_id', 'terms' => array( $team->term_id ) ) ) ), $seen, 6 ); // phpcs:ignore
		if ( $ids ) {
			$out .= '<div class="rk-rel__blk"><div class="rk-rel__head"><h2>Mehr aus ' . esc_html( $team->name ) . '</h2><a href="' . esc_url( add_query_arg( 'filter_team', $team->slug, $shop ) ) . '">Alle ansehen →</a></div>' . rk_rel_list( $ids ) . '</div>';
		}
	}
	if ( '' === $out ) {
		return;
	}
	echo '<style>.rk-rel{max-width:1100px;margin:50px auto 10px;padding:0 20px;clear:both}.rk-rel__blk{margin:0 0 40px}.rk-rel__head{display:flex;justify-content:space-between;align-items:baseline;gap:16px;border-bottom:1px solid rgba(255,255,255,.15);padding-bottom:10px;margin-bottom:16px}.rk-rel__head h2{font-family:"Barlow Condensed",sans-serif;font-weight:800;font-size:clamp(26px,4vw,38px);text-transform:uppercase;margin:0;line-height:1}.rk-rel__head a{color:#fff!important;font-size:13px;letter-spacing:.06em;text-transform:uppercase;text-decoration:underline;white-space:nowrap}.rk-rel__list{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px}.rk-rel__list a{display:flex;flex-direction:column;gap:8px;text-decoration:none;color:#fff!important;border:1px solid rgba(255,255,255,.14);background:#111;padding:10px;height:100%;box-sizing:border-box}.rk-rel__list a:hover{border-color:var(--rk-red,#ff3b30)}.rk-rel__img{display:block;position:relative;aspect-ratio:3/4;background:#0b0b0b;overflow:hidden}.rk-rel__img img{position:absolute;inset:0;width:100%!important;height:100%!important;max-height:none!important;object-fit:contain!important;aspect-ratio:auto!important;display:block}.rk-rel__n{font-size:13px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.rk-rel__p{font-size:14px;font-weight:600;margin-top:auto}</style><section class="rk-rel" aria-label="Weitere Karten">' . $out . '</section>'; // phpcs:ignore
}, 15 );

/* ------------------------------------------------------------------
 * 3) Suchauftrag: Formular [rk_suchauftrag], Mail an Shop und Backup
 * ------------------------------------------------------------------ */
add_shortcode( 'rk_suchauftrag', function () {
	$st = isset( $_GET['sa'] ) ? sanitize_key( wp_unslash( $_GET['sa'] ) ) : ''; // phpcs:ignore
	$pp = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
	$o  = '<style>.rk-sa{max-width:720px;margin:0 auto;padding:10px 20px 60px;color:#fff}.rk-sa label{display:block;font-family:var(--rk-mono,monospace);font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#aaa;margin:18px 0 6px}.rk-sa input[type=text],.rk-sa input[type=email],.rk-sa textarea{width:100%;box-sizing:border-box;background:#0b0b0b;color:#fff;border:1px solid rgba(255,255,255,.25);padding:13px 14px;font:15px var(--rk-body,sans-serif);border-radius:0}.rk-sa textarea{min-height:140px}.rk-sa input:focus,.rk-sa textarea:focus{outline:none;border-color:var(--rk-red,#ff3b30)}.rk-sa .rk-sa__c{display:flex;gap:10px;align-items:flex-start;font-family:var(--rk-body,sans-serif);font-size:14px;text-transform:none;letter-spacing:0;color:#ccc}.rk-sa .rk-sa__c input{margin-top:3px}.rk-sa button{margin-top:22px;background:var(--rk-red,#ff3b30);color:#fff;border:0;padding:15px 34px;font:600 14px var(--rk-mono,monospace);letter-spacing:.08em;text-transform:uppercase;cursor:pointer}.rk-sa button:hover{background:#fff;color:#0b0b0b}.rk-sa__hp{position:absolute;left:-9999px;height:0;overflow:hidden}.rk-sa__ok,.rk-sa__err{padding:16px 18px;margin:0 0 20px;border:1px solid;font-size:15px}.rk-sa__ok{border-color:#2ecc71}.rk-sa__err{border-color:var(--rk-red,#ff3b30)}</style><div class="rk-sa">';
	if ( 'ok' === $st ) {
		$o .= '<p class="rk-sa__ok">Danke! Dein Suchauftrag ist angekommen. Wir melden uns per E Mail, sobald wir die Karte haben oder wenn wir nachfragen müssen.</p>';
	} elseif ( 'fehler' === $st ) {
		$o .= '<p class="rk-sa__err">Das hat leider nicht geklappt. Bitte prüfe E Mail und Beschreibung und versuche es nochmal.</p>';
	} elseif ( 'zuviele' === $st ) {
		$o .= '<p class="rk-sa__err">Das waren gerade viele Anfragen. Bitte versuche es später nochmal.</p>';
	}
	$o .= '<p>Du suchst eine bestimmte Karte, die nicht im Shop liegt? Schreib uns, was du suchst. Wir schauen in unserem Bestand und beim nächsten Einkauf nach und melden uns bei dir. Kostenlos und unverbindlich.</p>'
		. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
		. '<input type="hidden" name="action" value="rk_suchauftrag"><input type="hidden" name="rk_t" value="" id="rk-sa-t">'
		. '<div class="rk-sa__hp" aria-hidden="true"><label>Webseite<input type="text" name="rk_web" tabindex="-1" autocomplete="off"></label></div>'
		. '<label for="rk-sa-w">Gesuchte Karte(n)</label><textarea id="rk-sa-w" name="wunsch" required maxlength="2000" placeholder="Spieler, Set, Jahr, Parallel oder Kartennummer. Eine Karte pro Zeile."></textarea>'
		. '<label for="rk-sa-b">Preisvorstellung (optional)</label><input type="text" id="rk-sa-b" name="budget" maxlength="80" placeholder="z. B. bis 5 € pro Karte">'
		. '<label for="rk-sa-n">Name (optional)</label><input type="text" id="rk-sa-n" name="name" maxlength="80" autocomplete="name">'
		. '<label for="rk-sa-m">Deine E Mail</label><input type="email" id="rk-sa-m" name="email" required maxlength="120" autocomplete="email">'
		. '<label class="rk-sa__c"><input type="checkbox" name="ok" value="1" required><span>Ich bin einverstanden, dass ihr mich per E Mail zu meinem Suchauftrag kontaktiert.' . ( $pp ? ' <a href="' . esc_url( $pp ) . '" style="color:#fff">Datenschutz</a>' : '' ) . '</span></label>'
		. '<button type="submit">Suchauftrag senden</button></form></div>'
		. '<script>try{document.getElementById("rk-sa-t").value="1"}catch(e){}</script>';
	return $o;
} );

function rk_suchauftrag_back( $s ) {
	wp_safe_redirect( add_query_arg( 'sa', $s, home_url( '/suchauftrag/' ) ) );
	exit;
}

function rk_suchauftrag_handle() {
	if ( ! empty( $_POST['rk_web'] ) || empty( $_POST['rk_t'] ) ) { // phpcs:ignore
		rk_suchauftrag_back( 'ok' ); // Bot: tut so als ob.
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
	$key = 'rk_sa_' . md5( $ip );
	$cnt = (int) get_transient( $key );
	if ( $cnt >= 5 ) {
		rk_suchauftrag_back( 'zuviele' );
	}
	set_transient( $key, $cnt + 1, HOUR_IN_SECONDS );
	$mail   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore
	$wunsch = isset( $_POST['wunsch'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wunsch'] ) ) : ''; // phpcs:ignore
	$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : ''; // phpcs:ignore
	$budget = isset( $_POST['budget'] ) ? sanitize_text_field( wp_unslash( $_POST['budget'] ) ) : ''; // phpcs:ignore
	if ( ! is_email( $mail ) || strlen( $wunsch ) < 3 || empty( $_POST['ok'] ) ) { // phpcs:ignore
		rk_suchauftrag_back( 'fehler' );
	}
	$log   = get_option( 'rk_suchauftraege', array() );
	$log[] = array( 't' => current_time( 'mysql' ), 'mail' => $mail, 'name' => $name, 'wunsch' => $wunsch, 'budget' => $budget );
	update_option( 'rk_suchauftraege', array_slice( $log, -200 ), false );
	$body  = "Neuer Suchauftrag\n\nName: " . ( $name ? $name : '-' ) . "\nE Mail: " . $mail . "\nPreisvorstellung: " . ( $budget ? $budget : '-' ) . "\n\nGesucht:\n" . $wunsch . "\n";
	$to    = array_unique( array_filter( array( 'shop@rookie-business.de', get_option( 'admin_email' ) ) ) );
	wp_mail( $to, 'Suchauftrag von ' . ( $name ? $name : $mail ), $body, array( 'Reply-To: ' . $mail ) );
	rk_suchauftrag_back( 'ok' );
}
add_action( 'admin_post_nopriv_rk_suchauftrag', 'rk_suchauftrag_handle' );
add_action( 'admin_post_rk_suchauftrag', 'rk_suchauftrag_handle' );

/* ------------------------------------------------------------------
 * 4) Lieferinfo im Warenkorb und Checkout
 * ------------------------------------------------------------------ */
add_filter( 'render_block', function ( $content, $block ) {
	if ( empty( $block['blockName'] ) || ! in_array( $block['blockName'], array( 'woocommerce/cart', 'woocommerce/checkout' ), true ) ) {
		return $content;
	}
	$note = '<div class="rk-dl" style="margin:0 0 22px;padding:14px 18px;border:1px solid rgba(255,255,255,.2);background:#0b0b0b;color:#fff;font-size:15px;line-height:1.5"><b style="color:var(--rk-red,#ff3b30);font-family:var(--rk-mono,monospace);font-size:12px;letter-spacing:.08em;text-transform:uppercase;display:block;margin-bottom:4px">Lieferung</b>Wir verschicken innerhalb von 1 Werktag. Die Sendungsnummer bekommst du per E Mail. Zustellung in Deutschland meist in 1 bis 3 Werktagen, Versand 1,10 € pro Bestellung.</div>';
	return $note . $content;
}, 20, 2 ); /* nach WooCommerce, sonst klaut der Hinweis das data-block-name der Kasse */

/* ------------------------------------------------------------------
 * Aufraeumen: keine doppelten "Aehnliche Produkte", Kartenbilder ganz zeigen
 * ------------------------------------------------------------------ */
add_filter( 'render_block', function ( $content, $block ) {
	if ( ! function_exists( 'is_product' ) || ! is_product() || empty( $block['blockName'] ) ) {
		return $content;
	}
	if ( 'core/heading' === $block['blockName'] && false !== strpos( $content, 'Ähnliche Produkte' ) ) {
		return '';
	}
	if ( 'woocommerce/product-collection' === $block['blockName'] && ! empty( $block['attrs']['collection'] ) && false !== strpos( (string) $block['attrs']['collection'], 'related' ) ) {
		return '';
	}
	return $content;
}, 10, 2 );

/* Warenkorb, Checkout und Mini Warenkorb: Bild in Originalformat statt 1:1 Ausschnitt */
add_filter( 'woocommerce_store_api_cart_item_images', function ( $images ) {
	foreach ( (array) $images as $k => $im ) {
		$is_arr = is_array( $im );
		$id     = (int) ( $is_arr ? ( $im['id'] ?? 0 ) : ( $im->id ?? 0 ) );
		$m      = $id ? wp_get_attachment_image_src( $id, 'medium' ) : false;
		if ( ! $m ) {
			continue;
		}
		if ( $is_arr ) {
			$images[ $k ]['thumbnail'] = $m[0];
			$images[ $k ]['src']       = $m[0];
			$images[ $k ]['srcset']    = '';
			$images[ $k ]['thumbnail_srcset'] = '';
			$images[ $k ]['thumbnail_sizes']  = '';
		} else {
			$images[ $k ]->thumbnail = $m[0];
			$images[ $k ]->src       = $m[0];
			$images[ $k ]->srcset    = '';
			$images[ $k ]->thumbnail_srcset = '';
			$images[ $k ]->thumbnail_sizes  = '';
		}
	}
	return $images;
}, 10, 1 );

add_action( 'wp_head', function () {
	echo '<style>table.wc-block-cart-items .wc-block-cart-item__image img,.wc-block-components-order-summary-item__image img,.wc-block-mini-cart__products-table .wc-block-cart-item__image img{width:72px!important;height:auto!important;max-height:none!important;object-fit:contain!important;aspect-ratio:auto!important}.wc-block-cart-item__image,.wc-block-components-order-summary-item__image{width:auto!important;height:auto!important}</style>';
}, 99 );

// Hero: Ladenfoto ganz leicht als Hintergrund (5 %)
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	echo '<style>.rk-hero{isolation:isolate}.rk-hero::before{content:"";position:absolute;inset:0;z-index:-1;background:url(' . esc_url( content_url( '/uploads/2026/11/rookie-laden-hero.jpg' ) ) . ') center/cover no-repeat;opacity:.05;pointer-events:none}</style>'; // phpcs:ignore
}, 30 );
