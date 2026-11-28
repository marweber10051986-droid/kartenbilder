<?php
/**
 * Rookie Business theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', function () {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
} );

add_action( 'wp_enqueue_scripts', function () {
	$ver = wp_get_theme()->get( 'Version' );
	wp_enqueue_style( 'rookie-business', get_stylesheet_uri(), array(), $ver );
	wp_enqueue_script( 'rookie-business', get_theme_file_uri( 'assets/js/rookie.js' ), array(), $ver, array( 'in_footer' => true, 'strategy' => 'defer' ) );
} );

/**
 * Block templates reference theme images as {{img}}/card-1.svg and shop
 * links as {{shop}}. Both are swapped for real URLs when the block renders.
 */
add_filter( 'render_block', function ( $content ) {
	if ( false === strpos( $content, '{{' ) ) {
		return $content;
	}
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	$cart = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
	$content = strtr( $content, array(
		'{{img}}'  => esc_url( get_theme_file_uri( 'assets/img' ) ),
		'{{shop}}' => esc_url( $shop ),
		'{{cart}}' => esc_url( $cart ),
		'{{home}}' => esc_url( home_url( '/' ) ),
	) );

	// Carousel with the 10 newest cards.
	if ( false !== strpos( $content, '{{minprice}}' ) ) {
		$content = str_replace( '{{minprice}}', function_exists( 'rk_seo_min_price' ) ? esc_html( rk_seo_euro( rk_seo_min_price() ) ) : '2 €', $content );
	}
	if ( false !== strpos( $content, '{{bioimg}}' ) ) {
		$bio = get_stylesheet_directory() . '/assets/img/marcel.webp';
		$content = str_replace( '{{bioimg}}', file_exists( $bio ) ? '<img src="' . esc_url( get_stylesheet_directory_uri() . '/assets/img/marcel.webp?v=' . filemtime( $bio ) ) . '" alt="Marcel von Rookie Business" width="88" height="88" loading="lazy">' : '<b>MW</b>', $content );
	}
	if ( false !== strpos( $content, '{{latest}}' ) ) {
		$content = str_replace( '{{latest}}', rk_latest_grid( 20 ), $content );
	}

	// Newsletter form from the Rookie Business Newsletter plugin.
	if ( false !== strpos( $content, '{{newsletter}}' ) ) {
		$content = str_replace( '{{newsletter}}', shortcode_exists( 'rookie_newsletter' ) ? do_shortcode( '[rookie_newsletter]' ) : '', $content );
	}

	// Cache busting: theme images get the theme version appended.
	$ver     = rawurlencode( wp_get_theme()->get( 'Version' ) );
	$img     = preg_quote( esc_url( get_theme_file_uri( 'assets/img' ) ), '#' );
	$content = preg_replace( '#(' . $img . '/[^"\'\s?]+\.(?:webp|png|jpg|svg))#', '$1?v=' . $ver, $content );

	// {{cat:slug}} links to a product category, falling back to the shop page.
	return preg_replace_callback( '/\{\{cat:([a-z0-9\-]+)\}\}/', function ( $m ) use ( $shop ) {
		$link = taxonomy_exists( 'product_cat' ) ? get_term_link( $m[1], 'product_cat' ) : '';
		return esc_url( ( $link && ! is_wp_error( $link ) ) ? $link : $shop );
	}, $content );
} );

/** Pattern category for the shop sections. */
add_action( 'init', function () {
	register_block_pattern_category( 'rookie', array( 'label' => 'Rookie Business' ) );
} );

/** 30 products per shop and category page (5 columns x 6 rows). */
add_filter( 'loop_shop_per_page', function () {
	return 30;
}, 20 );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() && $q->is_main_query() && ( $q->is_post_type_archive( 'product' ) || $q->is_tax( array( 'product_cat', 'product_tag' ) ) ) ) {
		$q->set( 'posts_per_page', 30 );
	}
} );

/** Show whole cards in product grids instead of square crops. */
add_filter( 'single_product_archive_thumbnail_size', function () {
	return 'woocommerce_single';
} );
add_filter( 'woocommerce_get_image_size_gallery_thumbnail', function ( $size ) {
	$size['crop'] = 0;
	$size['height'] = 0;
	return $size;
} );

/**
 * Product page: show the full description right under the add to cart
 * button and drop the separate "Beschreibung" tab.
 */
add_filter( 'render_block_woocommerce/add-to-cart-form', function ( $content ) {
	if ( ! is_product() ) {
		return $content;
	}
	$product = wc_get_product( get_the_ID() );
	if ( ! $product || '' === trim( $product->get_description() ) ) {
		return $content;
	}
	$desc = apply_filters( 'the_content', $product->get_description() );
	return $content . '<div class="rk-product-desc"><p class="rk-label">Beschreibung</p>' . $desc . '</div>';
} );
add_filter( 'woocommerce_product_tabs', function ( $tabs ) {
	unset( $tabs['description'] );
	return $tabs;
}, 98 );

/** Auto scrolling carousel with the newest products. */
/* Frisch gezogen als Raster wie "Recently Added": Set Zeile, Spieler, Preis (27.11.2026) */
function rk_latest_grid( $limit = 20 ) {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return '';
	}
	$products = array_values( array_filter( array_map( 'wc_get_product', rk_frisch_ids( $limit ) ) ) );
	if ( ! $products ) {
		return '<p class="rk-section__lead">Neue Karten sind gleich da.</p>';
	}
	$chips = '';
	foreach ( array( 'nfl' => 'NFL', 'nba' => 'NBA', 'mlb' => 'MLB', 'fussball' => 'Fußball', 'tcg' => 'TCG' ) as $slug => $label ) {
		$t = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $t && ! is_wp_error( $t ) ) {
			$link = get_term_link( $t );
			if ( ! is_wp_error( $link ) ) {
				$chips .= '<a class="rk-ng__chip" href="' . esc_url( add_query_arg( 'orderby', 'date', $link ) ) . '">' . esc_html( $label ) . '</a>';
			}
		}
	}
	$items = '';
	$n     = 0;
	foreach ( $products as $p ) {
		$n++;
		$set    = trim( (string) $p->get_attribute( 'Set' ) );
		$nr     = trim( (string) $p->get_attribute( 'Kartennummer' ) );
		$var    = trim( (string) $p->get_attribute( 'Variante' ) );
		$player = trim( (string) $p->get_attribute( 'Spieler' ) );
		$team   = trim( (string) $p->get_attribute( 'Team' ) );
		$name   = $p->get_name();
		if ( '' === $player ) {
			$player = trim( preg_replace( '/\s+#.*$/', '', $name ) );
		}
		$line = $set;
		if ( '' !== $var && 'base' !== strtolower( $var ) ) {
			$line .= ( $line ? ' · ' : '' ) . $var;
		}
		if ( '' !== $nr ) {
			$line .= ( $line ? ' ' : '' ) . '#' . ltrim( $nr, '#' );
		}
		if ( '' === $line ) {
			$line = $name;
		}
		$rc     = ( false !== stripos( $name, 'Rookie' ) ) ? '<i class="rk-ng__rc">RC</i>' : '';
		$img_id = $p->get_image_id();
		$img    = $img_id ? wp_get_attachment_image( $img_id, 'medium', false, array( 'loading' => ( $n <= 10 ? 'eager' : 'lazy' ), 'alt' => $name, 'sizes' => '(max-width: 640px) 45vw, 220px' ) ) : wc_placeholder_img( 'woocommerce_single' );
		$items .= '<li class="rk-ng__item"><a href="' . esc_url( $p->get_permalink() ) . '" title="' . esc_attr( $name ) . '">'
			. '<span class="rk-ng__img">' . $img . '</span>'
			. '<span class="rk-ng__set">' . esc_html( $line ) . '</span>'
			. '<span class="rk-ng__name">' . esc_html( $player ) . $rc . ( $team ? '<small>' . esc_html( $team ) . '</small>' : '' ) . '</span>'
			. '<span class="rk-ng__price">' . wp_kses_post( $p->get_price_html() ) . '</span>'
			. '</a></li>';
	}
	return '<div class="rk-ng">' . ( $chips ? '<nav class="rk-ng__chips" aria-label="Neu nach Kategorie">' . $chips . '</nav>' : '' ) . '<ul class="rk-ng__grid">' . $items . '</ul></div>';
}

function rk_latest_carousel( $limit = 10 ) {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return '';
	}
	$products = array_values( array_filter( array_map( 'wc_get_product', rk_frisch_ids( $limit ) ) ) );
	if ( ! $products ) {
		return '<p class="rk-section__lead">Neue Karten sind gleich da.</p>';
	}
	$items = '';
	foreach ( $products as $p ) {
		$img_id = $p->get_image_id();
		$img    = $img_id ? wp_get_attachment_image( $img_id, 'woocommerce_single', false, array( 'loading' => 'eager', 'alt' => $p->get_name(), 'sizes' => '220px' ) ) : wc_placeholder_img( 'woocommerce_single' );
		$items .= '<li class="rk-car__item"><a href="' . esc_url( $p->get_permalink() ) . '">'
			. '<span class="rk-car__img">' . $img . '</span>'
			. '<span class="rk-car__name">' . esc_html( $p->get_name() ) . '</span>'
			. '<span class="rk-car__price">' . wp_kses_post( $p->get_price_html() ) . '</span>'
			. '</a></li>';
	}
	// Repeat the set so the loop is seamless even with few cards.
	$reps  = max( 2, (int) ceil( 12 / count( $products ) ) * 2 );
	$track = '';
	for ( $i = 0; $i < $reps; $i++ ) {
		$track .= $i ? str_replace( '<li class="rk-car__item">', '<li class="rk-car__item" aria-hidden="true">', $items ) : $items;
	}
	$secs = max( 30, count( $products ) * $reps * 3 );
	return '<div class="rk-car" style="--rk-car-dur:' . (int) $secs . 's"><ul class="rk-car__track">' . $track . '</ul></div>';
}

/* ------------------------------------------------------------------
 * Filter dropdowns on shop and category pages:
 * Mannschaft, Position, Kartenart, Serie (global product attributes).
 * Uses WooCommerce's own filter_{attribute} query parameters.
 * ------------------------------------------------------------------ */
function rk_filter_taxonomies() {
	return array(
		'pa_team'      => 'Mannschaft',
		'pa_position'  => 'Position',
		'pa_kartenart' => 'Kartenart',
		'pa_serie'     => 'Serie',
	);
}

function rk_filter_options() {
	$term     = is_product_taxonomy() ? get_queried_object() : null;
	$cache_id = 'rk_filters_' . ( $term ? $term->term_id : 'shop' ) . '_' . (int) get_option( 'rk_filters_ver', 1 );
	$cached   = get_transient( $cache_id );
	if ( false !== $cached ) {
		return $cached;
	}
	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	);
	if ( $term ) {
		$args['tax_query'] = array( array( 'taxonomy' => $term->taxonomy, 'field' => 'term_id', 'terms' => $term->term_id ) );
	}
	$ids = get_posts( $args );
	$out = array();
	foreach ( rk_filter_taxonomies() as $tax => $label ) {
		if ( ! taxonomy_exists( $tax ) || ! $ids ) {
			continue;
		}
		$terms = wp_get_object_terms( $ids, $tax, array( 'orderby' => 'name', 'order' => 'ASC' ) );
		if ( is_wp_error( $terms ) || count( $terms ) < 1 ) {
			continue;
		}
		$opts = array();
		foreach ( $terms as $t ) {
			$opts[ $t->slug ] = $t->name;
		}
		$out[ $tax ] = array( 'label' => $label, 'options' => $opts );
	}
	set_transient( $cache_id, $out, DAY_IN_SECONDS );
	return $out;
}

/** Refresh the filter options whenever a product changes. */
add_action( 'save_post_product', function () {
	update_option( 'rk_filters_ver', (int) get_option( 'rk_filters_ver', 1 ) + 1, false );
} );

function rk_filter_form() {
	$filters = rk_filter_options();
	if ( ! $filters ) {
		return '';
	}
	$html   = '<form class="rk-filters alignwide" method="get" action="">';
	$active = false;
	foreach ( $filters as $tax => $f ) {
		$param   = 'filter_' . substr( $tax, 3 );
		$current = isset( $_GET[ $param ] ) ? sanitize_title( wp_unslash( $_GET[ $param ] ) ) : '';
		$active  = $active || '' !== $current;
		$html   .= '<label class="rk-filters__field"><span class="rk-filters__label screen-reader-text">' . esc_html( $f['label'] ) . '</span><select name="' . esc_attr( $param ) . '" aria-label="' . esc_attr( $f['label'] ) . '"' . ( '' !== $current ? ' class="is-set"' : '' ) . '>';
		$html   .= '<option value="">' . esc_html( $f['label'] ) . ': Alle</option>';
		foreach ( $f['options'] as $slug => $name ) {
			$html .= '<option value="' . esc_attr( $slug ) . '"' . selected( $current, $slug, false ) . '>' . esc_html( $name ) . '</option>';
		}
		$html .= '</select></label>';
	}
	if ( isset( $_GET['orderby'] ) ) {
		$html .= '<input type="hidden" name="orderby" value="' . esc_attr( sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) ) . '">';
	}
	$active = $active || ! empty( $_GET['rk_spieler'] ) || ! empty( $_GET['rk_parallel'] ); // phpcs:ignore
	if ( $active ) {
		$reset = remove_query_arg( array_merge( array( 'paged', 'rk_spieler', 'rk_parallel' ), array_map( function ( $t ) { return 'filter_' . substr( $t, 3 ); }, array_keys( rk_filter_taxonomies() ) ) ) );
		$reset = preg_replace( '#/page/\d+/?#', '/', $reset );
		$html .= '<a class="rk-filters__reset rk-link-arrow" href="' . esc_url( $reset ) . '">Filter zurücksetzen</a>';
	}
	$html .= rk_filter_extra_fields();
	$html .= '<noscript><button type="submit">Filtern</button></noscript></form>';
	$html .= '<script>(function(f){if(!f)return;f.querySelectorAll("select").forEach(function(s){s.addEventListener("change",function(){f.querySelectorAll("select").forEach(function(x){x.disabled=!x.value;});var a=f.getAttribute("action")||location.pathname;f.setAttribute("action",location.pathname.replace(/\/page\/\d+\/?$/,"/"));f.submit();});});})(document.currentScript.previousElementSibling);</script>';
	return $html;
}

add_filter( 'render_block_woocommerce/product-collection', function ( $content, $block ) {
	if ( is_admin() || ! ( is_shop() || is_product_taxonomy() ) ) {
		return $content;
	}
	if ( empty( $block['attrs']['query']['inherit'] ) ) {
		return $content;
	}
	return rk_filter_form() . $content;
}, 10, 2 );

/**
 * Frisch gezogen: neue Karten der letzten 24 Stunden zuerst, der Rest kommt aus der
 * täglichen Rotation (3 Karten pro Kategorie, läuft Tag für Tag durch das ganze Inventar).
 */
/* Neue Karten sofort in "Frisch gezogen": Tages Cache bei neuen Produkten leeren (27.11.2026) */
function rk_frisch_flush() {
	$day = (int) floor( current_time( 'timestamp' ) / DAY_IN_SECONDS );
	foreach ( array( 10, 15, 20, 24, 30 ) as $l ) {
		delete_transient( 'rk_frisch_' . $day . '_' . $l );
	}
}
add_action( 'woocommerce_new_product', 'rk_frisch_flush' );
add_action( 'rest_api_init', function () {
	register_rest_route( 'rookie/v1', '/frisch-flush', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'manage_woocommerce' );
		},
		'callback'            => function () {
			rk_frisch_flush();
			return array( 'ok' => true );
		},
	) );
} );

function rk_frisch_ids( $limit = 15 ) {
	$day = (int) floor( current_time( 'timestamp' ) / DAY_IN_SECONDS );
	$key = 'rk_frisch_' . $day . '_' . $limit;
	$ids = get_transient( $key );
	if ( is_array( $ids ) && $ids ) {
		return $ids;
	}
	$base = array(
		'status'       => 'publish',
		'visibility'   => 'catalog',
		'stock_status' => 'instock',
		'return'       => 'ids',
	);
	// Pokémon Mega Sale Karten bleiben draußen, die haben ihren eigenen Banner (27.11.2026).
	$no_sale = function ( $list ) {
		return array_values( array_filter( array_map( 'intval', (array) $list ), function ( $id ) {
			return ! has_term( 'pokemon-mega-sale', 'product_cat', $id );
		} ) );
	};
	// 1. Neu in den letzten 24 Stunden.
	$ids = array_slice( $no_sale( wc_get_products( $base + array(
		'limit'        => $limit + 300,
		'orderby'      => 'date',
		'order'        => 'DESC',
		'date_created' => '>' . ( time() - DAY_IN_SECONDS ),
	) ) ), 0, $limit );
	// 2. Rotation: pro Kategorie 3 Karten, jeden Tag die nächsten 3.
	$rot = array();
	foreach ( array( 'nfl', 'nba', 'mlb', 'fussball', 'tcg' ) as $cat ) {
		$all = wc_get_products( $base + array(
			'limit'    => -1,
			'category' => array( $cat ),
			'orderby'  => 'ID',
			'order'    => 'ASC',
		) );
		$all = $no_sale( $all );
		$n   = count( $all );
		if ( ! $n ) {
			continue;
		}
		$start = ( $day * 3 ) % $n;
		for ( $k = 0; $k < min( 3, $n ); $k++ ) {
			$rot[] = (int) $all[ ( $start + $k ) % $n ];
		}
	}
	mt_srand( $day );
	shuffle( $rot );
	mt_srand();
	foreach ( $rot as $id ) {
		if ( count( $ids ) >= $limit ) {
			break;
		}
		if ( ! in_array( $id, $ids, true ) ) {
			$ids[] = $id;
		}
	}
	set_transient( $key, $ids, DAY_IN_SECONDS + HOUR_IN_SECONDS );
	return $ids;
}

// Neue Karte veröffentlicht: Auswahl von heute neu bauen.
add_action( 'woocommerce_new_product', 'rk_frisch_reset' );
add_action( 'woocommerce_update_product', 'rk_frisch_reset' );
function rk_frisch_reset() {
	$day = (int) floor( current_time( 'timestamp' ) / DAY_IN_SECONDS );
	foreach ( array( 10, 15 ) as $l ) {
		delete_transient( 'rk_frisch_' . $day . '_' . $l );
	}
}

// Täglich kurz nach Mitternacht die Startseite aus dem Cache werfen, damit die neue Rotation sichtbar wird.
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'rk_frisch_daily' ) ) {
		$tz   = wp_timezone();
		$next = new DateTime( 'tomorrow 00:05', $tz );
		wp_schedule_event( $next->getTimestamp(), 'daily', 'rk_frisch_daily' );
	}
} );
add_action( 'rk_frisch_daily', function () {
	rk_frisch_reset();
	do_action( 'litespeed_purge_url', home_url( '/' ) );
} );


/* ---------- Schlanke Produktdaten auf Übersichtsseiten (SEO / Ladezeit) ---------- */
function rk_slim_interactivity( $data ) {
	try {
		if ( is_admin() || ( function_exists( 'is_product' ) && is_product() ) ) {
			return $data;
		}
		if ( ! is_array( $data ) || empty( $data['state']['woocommerce/products']['products'] ) ) {
			return $data;
		}
		$list = $data['state']['woocommerce/products']['products'];
		if ( ! is_array( $list ) ) {
			return $data;
		}
		foreach ( $list as $id => $p ) {
			$p = json_decode( wp_json_encode( $p ), true );
			if ( ! is_array( $p ) ) {
				continue;
			}
			unset( $p['description'], $p['short_description'], $p['attributes'] );
			if ( ! empty( $p['images'] ) && is_array( $p['images'] ) ) {
				$img = reset( $p['images'] );
				$p['images'] = is_array( $img ) ? array( array_intersect_key( $img, array_flip( array( 'id', 'src', 'thumbnail', 'alt', 'name' ) ) ) ) : array();
			}
			$list[ $id ] = $p;
		}
		$data['state']['woocommerce/products']['products'] = $list;
	} catch ( \Throwable $e ) {
		return $data;
	}
	return $data;
}
add_filter( 'script_module_data_@wordpress/interactivity', 'rk_slim_interactivity', 999 );


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
		. '<div class="rk-wk__stat"><b>70<i>€</i></b><span>ab hier Versand in DE gratis</span></div>'
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

/* Frisch gezogen: /shop/?neu=1 zeigt nur die neuesten Karten (alles vom letzten Upload Tag, 24 Stunden bis zur neuesten Karte). */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) return;
	if ( ! ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) ) return;
	if ( empty( $_GET['neu'] ) ) return;
	$newest = null;
	foreach ( wc_get_products( array( 'status' => 'publish', 'visibility' => 'catalog', 'stock_status' => 'instock', 'limit' => 400, 'orderby' => 'date', 'order' => 'DESC' ) ) as $np ) {
		if ( ! has_term( 'pokemon-mega-sale', 'product_cat', $np->get_id() ) ) {
			$newest = $np;
			break;
		}
	}
	if ( ! $newest || ! $newest->get_date_created() ) return;
	$cut = $newest->get_date_created()->getTimestamp() - DAY_IN_SECONDS;
	$q->set( 'date_query', array( array( 'column' => 'post_date_gmt', 'after' => gmdate( 'Y-m-d H:i:s', $cut ), 'inclusive' => true ) ) );
	$tq   = (array) $q->get( 'tax_query' );
	$tq[] = array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array( 'pokemon-mega-sale' ), 'operator' => 'NOT IN' );
	$q->set( 'tax_query', $tq );
	$q->set( 'orderby', 'date' );
	$q->set( 'order', 'DESC' );
} );

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
.rk-eb{background:#0b0b0b;border-bottom:1px solid var(--rk-line,#262626);margin:0;padding:0}
.rk-eb__in{max-width:1340px;margin:0 auto;padding:0 var(--rk-pad,20px);display:grid;grid-template-columns:repeat(3,1fr);list-style:none;font-family:"Barlow Condensed",sans-serif;font-size:14px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#fff;line-height:1.1}
.rk-eb__it{display:flex;align-items:center;justify-content:center;gap:8px;padding:6px 14px;margin:0;color:#fff!important;text-decoration:none;text-align:left}
.rk-eb__it+.rk-eb__it{border-left:1px solid var(--rk-line,#262626)}
.rk-eb__it b{color:var(--rk-red,#ff3b30);font-weight:800}
.rk-eb__ic{flex:none;width:20px;height:20px;display:grid;place-items:center;border:1px solid rgba(255,59,48,.55);border-radius:50%;color:var(--rk-red,#ff3b30)}
.rk-eb__ic svg{width:11px;height:11px}
a.rk-eb__it:hover{background:#141414}
a.rk-eb__it:hover .rk-eb__t{text-decoration:underline;text-underline-offset:3px}
@media(max-width:900px){.rk-eb__in{font-size:12px;padding:0}.rk-eb__it{padding:5px 8px;gap:6px}.rk-eb__ic{width:18px;height:18px}.rk-eb__ic svg{width:10px;height:10px}}
@media(max-width:640px){.rk-eb__in{grid-template-columns:1fr}.rk-eb__it{justify-content:flex-start;padding:4px 20px}.rk-eb__it+.rk-eb__it{border-left:0;border-top:1px solid var(--rk-line,#262626)}}
.rk-eb__stars{color:#ffb800;letter-spacing:2px;font-size:18px}
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
@media(max-width:700px){.rk-cb__lab{font-size:11px}}
</style>';
}

function rk_ebay_bar() {
	$s = rk_ebay_stats();
	return ''; // Leiste entfernt am 26.11.2026, Inhalte stehen jetzt in der Laufschrift oben.
	return rk_ebay_css() . '<section class="rk-eb" aria-label="Warum Rookie Business"><div class="rk-eb__in">'
		. '<a class="rk-eb__it" href="' . esc_url( $s['profil'] ) . '" target="_blank" rel="noopener"><span class="rk-eb__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11v9H4v-9z"/><path d="M7 11l4-8a2.5 2.5 0 0 1 3 3l-1 4h5.5a2 2 0 0 1 2 2.4l-1.4 6.6a2 2 0 0 1-2 1.6H7"/></svg></span><span class="rk-eb__t"><b>' . (int) $s['quote'] . ' %</b> positive Bewertungen auf eBay</span></a>'
		. '<div class="rk-eb__it"><span class="rk-eb__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg></span><span class="rk-eb__t"><b>Ab 70 €</b> versandkostenfrei in DE</span></div>'
		. '<div class="rk-eb__it"><span class="rk-eb__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><span class="rk-eb__t"><b>Über 6 Jahre</b> im Sammelkarten Business</span></div>'
		. '</div></section>';
}

add_filter( 'render_block', function ( $content, $block ) {
	static $done = false;
	if ( $done || empty( $block['blockName'] ) || 'core/template-part' !== $block['blockName'] || false === strpos( $content, 'rk-site-header' ) ) {
		return $content;
	}
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
		return $content;
	}
	$done = true;
	return $content . rk_ebay_bar();
}, 8, 2 );

add_action( 'woocommerce_single_product_summary', function () {
	$s = rk_ebay_stats();
	echo rk_ebay_css() . '<div class="rk-ebp"><span class="rk-eb__stars" aria-hidden="true">★★★★★</span><span>' . (int) $s['quote'] . ' % positive Bewertungen auf <a href="' . esc_url( $s['profil'] ) . '" target="_blank" rel="noopener">eBay</a>, raus innerhalb von 1 Werktag</span></div>'; // phpcs:ignore
}, 35 );

/* Direkt unter dem Preis: Gratisversand ab 70 € (Mengenrabatt am 27.11.2026 beendet) */
add_action( 'woocommerce_single_product_summary', function () {
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	echo '<div class="rk-pv">'
		. '<div class="rk-pv__row"><span class="rk-pv__ic" aria-hidden="true">€</span><span><b>Ab 70 €</b> versandkostenfrei in DE, darunter nur 1,10 € pro Bestellung.</span></div>'
		. '<a class="rk-pv__more" href="' . esc_url( $shop ) . '">Mehr Karten entdecken <span aria-hidden="true">↗</span></a>'
		. '</div>'; // phpcs:ignore
}, 11 );

/* Mengenrabatt Fortschritt im Warenkorb: seit 27.11.2026 aus (Mengenrabatt beendet) */
add_filter( 'render_block', function ( $content, $block ) {
	if ( ! defined( 'RK_MENGENRABATT_AN' ) ) {
		return $content;
	}
	if ( empty( $block['blockName'] ) || 'woocommerce/cart' !== $block['blockName'] ) {
		return $content;
	}
	$n   = ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
	$bar = rk_ebay_css() . '<div class="rk-cb" id="rk-cb" data-n="' . $n . '"><div class="rk-cb__t" id="rk-cb-t"></div>'
		. '<div class="rk-cb__bar"><div class="rk-cb__fill" id="rk-cb-f"></div>'
		. '<i class="rk-cb__tick" style="left:33.33%"></i><i class="rk-cb__tick" style="left:66.66%"></i><i class="rk-cb__tick" style="left:calc(100% - 2px)"></i></div>'
		. '<div class="rk-cb__lab"><span style="left:0">1</span><span style="left:33.33%">10 Karten: 10 %</span><span style="left:66.66%">20 Karten: 20 %</span><span style="left:100%;transform:translateX(-100%)">30 Karten: 25 %</span></div></div>'
		. '<script>(function(){var T=[[10,10],[20,20],[30,25]];function render(n){var t=document.getElementById("rk-cb-t"),f=document.getElementById("rk-cb-f");if(!t)return;var nx=null,cur=0;for(var i=0;i<T.length;i++){if(n>=T[i][0])cur=T[i][1];else if(!nx)nx=T[i];}'
		. 'if(!nx){t.innerHTML="<b>"+cur+" % Mengenrabatt aktiv.</b> Mehr geht nicht, danke fürs Wühlen."}else{var d=nx[0]-n;t.innerHTML=(cur?"<b>"+cur+" % Rabatt aktiv.</b> ":"")+"Noch <b>"+d+" "+(d===1?"Karte":"Karten")+"</b> bis <b>"+nx[1]+" % Rabatt</b>"}'
		. 'f.style.width=Math.min(100,n/30*100)+"%"}'
		. 'var el=document.getElementById("rk-cb");render(parseInt(el.getAttribute("data-n"),10)||0);'
		. 'function sub(){if(!window.wp||!wp.data||!wp.data.select("wc/store/cart")){return setTimeout(sub,500)}var last=-1;wp.data.subscribe(function(){try{var c=wp.data.select("wc/store/cart").getCartData();var q=0;(c.items||[]).forEach(function(i){q+=i.quantity});if(q!==last){last=q;render(q)}}catch(e){}})}sub();})();</script>';
	return $bar . $content;
}, 10, 2 );

/* ------------------------------------------------------------------
 * Mengenrabatt Hinweis im ganzen Shop: kleine Pille unten links,
 * sobald Karten im Warenkorb liegen. Nicht im Warenkorb und Checkout,
 * dort gibt es den grossen Balken. Schliessbar bis sich die Menge aendert.
 * ------------------------------------------------------------------ */
add_action( 'wp_footer', function () {
	if ( ! defined( 'RK_MENGENRABATT_AN' ) || is_admin() || ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
		return;
	}
	$cart = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '/warenkorb/';
	?>
<style>
#rk-nd{position:fixed;left:16px;bottom:16px;z-index:9990;max-width:min(360px,calc(100vw - 32px));background:#0b0b0b;color:#fff;border:1px solid rgba(255,255,255,.25);border-left:3px solid var(--rk-red,#ff3b30);padding:10px 34px 10px 14px;font-family:"Barlow Condensed",sans-serif;font-size:16px;letter-spacing:.03em;text-transform:uppercase;line-height:1.25;box-shadow:0 6px 22px rgba(0,0,0,.35);transform:translateY(140%);opacity:0;transition:transform .35s,opacity .35s;pointer-events:none}
#rk-nd.on{transform:none;opacity:1;pointer-events:auto}
#rk-nd b{color:var(--rk-red,#ff3b30)}
#rk-nd a{color:#fff!important;text-decoration:underline;display:inline-block;margin-top:2px;font-size:14px;opacity:.85}
#rk-nd .rk-nd__bar{height:3px;background:rgba(255,255,255,.18);margin-top:7px}
#rk-nd .rk-nd__bar i{display:block;height:100%;background:var(--rk-red,#ff3b30);transition:width .4s}
#rk-nd button{position:absolute;top:2px;right:4px;background:none;border:0;color:#fff;opacity:.6;font-size:20px;line-height:1;cursor:pointer;padding:4px 6px}
@media(max-width:700px){#rk-nd{left:10px;right:10px;bottom:10px;max-width:none;font-size:15px}}
</style>
<div id="rk-nd" role="status" aria-live="polite"><button type="button" aria-label="Schließen">×</button><span id="rk-nd-t"></span><div class="rk-nd__bar"><i id="rk-nd-f"></i></div><a href="<?php echo esc_url( $cart ); ?>">Zum Warenkorb</a></div>
<script>
(function(){
var T=[[10,10],[20,20],[30,25]],el=document.getElementById('rk-nd'),tx=document.getElementById('rk-nd-t'),fl=document.getElementById('rk-nd-f'),last=-1,closed=-1,busy=false;
function show(n){
 if(n<1){el.classList.remove('on');last=n;return}
 var nx=null,cur=0;for(var i=0;i<T.length;i++){if(n>=T[i][0])cur=T[i][1];else if(!nx)nx=T[i]}
 if(!nx){tx.innerHTML='<b>'+cur+' % Mengenrabatt</b> aktiv. Mehr geht nicht.'}
 else{var d=nx[0]-n;tx.innerHTML=(cur?'<b>'+cur+' %</b> aktiv. ':'')+'Noch <b>'+d+(d===1?' Karte':' Karten')+'</b> bis <b>'+nx[1]+' % Rabatt</b>'}
 fl.style.width=Math.min(100,n/30*100)+'%';
 if(n!==last){closed=-1}
 last=n;
 if(closed!==n)el.classList.add('on');
}
function load(){
 if(busy)return;busy=true;
 fetch('/wp-json/wc/store/v1/cart',{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(c){
  var q=0;(c.items||[]).forEach(function(i){q+=i.quantity});show(q);
 }).catch(function(){}).then(function(){busy=false});
}
el.querySelector('button').addEventListener('click',function(){closed=last;el.classList.remove('on')});
load();
['added_to_cart','removed_from_cart','wc-blocks_added_to_cart'].forEach(function(e){document.addEventListener(e,function(){setTimeout(load,400)});if(window.jQuery)jQuery(document.body).on(e,function(){setTimeout(load,400)})});
document.addEventListener('click',function(ev){if(ev.target.closest&&ev.target.closest('.add_to_cart_button,.single_add_to_cart_button,.wp-block-button__link,button[name=add-to-cart]')){setTimeout(load,1200);setTimeout(load,2800)}});
window.addEventListener('pageshow',function(e){if(e.persisted)load()});
})();
</script>
	<?php
}, 50 );

/* Kartensuche Startseite: roter Balken ueber die ganze Breite, graues Suchfeld */
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	echo '<style>.rk-bigsearch{background:var(--rk-red,#ff3b30)!important;padding:26px var(--rk-pad) 28px!important;width:100%}.rk-bigsearch .rk-kicker{color:#fff!important;margin-bottom:10px}.rk-bigsearch .rk-kicker .rk-dot{background:#fff!important}.rk-bigsearch form>div{background:#d9d9d9!important;border:0!important}.rk-bigsearch form>div:focus-within{box-shadow:0 0 0 3px #0b0b0b}.rk-bigsearch input,.rk-bigsearch input:focus,.rk-bigsearch input:-webkit-autofill{background:#d9d9d9!important;color:#111!important;-webkit-text-fill-color:#111}.rk-bigsearch input::placeholder{color:#555!important}.rk-bigsearch button{background:#0b0b0b!important;color:#fff!important}.rk-bigsearch button:hover{background:#fff!important;color:#0b0b0b!important}</style>';
}, 99 );

/* ------------------------------------------------------------------
 * Echte eBay Bewertungen vor "Verpenn keinen Drop".
 * Texte stehen 1:1 so im eBay Bewertungsprofil. eBay zeigt nur
 * "Letzter Monat", darum steht das Kaufdatum aus dem Verkäufer Cockpit dabei.
 * ------------------------------------------------------------------ */
function rk_ebay_reviews_data() {
	return array(
		array( 'u' => 'anub1zz', 'd' => '08.11.2026', 'i' => 'Leonard Williams #183 Neon Green Pulsar', 't' => 'Alles perfekt! Karte im Top-Zustand, schnell versendet und super verpackt. Sehr zuverlässiger Verkäufer – jederzeit wieder.' ),
		array( 'u' => 'bball_lsa', 'd' => '15.11.2026', 'i' => 'Vince Carter #83 Raptors', 't' => 'Schneller Versand mit guter und sicherer Verpackung. Die Karte ist im top Zustand, wie beschrieben.' ),
		array( 'u' => 'redhothooligan', 'd' => '09.11.2026', 'i' => 'D\'Andre Swift #148 Pink Sparkle Chrome', 't' => 'Top Qualität, schneller Versand. Jederzeit gerne wieder...Vielen Dank !!!' ),
		array( 'u' => 'pay_pat', 'd' => '04.11.2026', 'i' => 'Mewtu 150/165 Holo Rare', 't' => 'Schöne Karte, super Kommunikation und schneller Versand: Alles TOP!' ),
		array( 'u' => 'tyhoon3010', 'd' => '20.10.2026', 'i' => 'Koraidon 047/084 Holo Rare', 't' => 'Top Zustand der Karte und schnelle Lieferung. Sehr gerne wieder.' ),
	);
}

function rk_ebay_reviews_html() {
	$s   = rk_ebay_stats();
	$out = '<style>
.rk-rv{background:#0b0b0b;border-top:1px solid rgba(255,255,255,.12);padding:70px 0 64px;color:#fff}
.rk-rv__in{width:min(1100px,calc(100% - 40px));margin:0 auto}
.rk-rv__k{font-family:var(--rk-mono,monospace);font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--rk-red,#ff3b30);margin:0 0 12px}
.rk-rv__h{font-family:"Barlow Condensed",sans-serif;font-weight:800;font-size:clamp(36px,6vw,64px);line-height:.95;text-transform:uppercase;margin:0 0 10px}
.rk-rv__h span{color:var(--rk-red,#ff3b30)}
.rk-rv__s{opacity:.7;font-size:15px;margin:0 0 30px}
.rk-rv__g{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px}
.rk-rv__c{border:1px solid rgba(255,255,255,.18);background:#111;padding:18px 18px 16px;display:flex;flex-direction:column;gap:12px}
.rk-rv__st{color:#ffb800;letter-spacing:2px;font-size:16px}
.rk-rv__t{font-size:15px;line-height:1.45;margin:0;flex:1}
.rk-rv__m{font-family:var(--rk-mono,monospace);font-size:12px;letter-spacing:.04em;opacity:.65;line-height:1.5;border-top:1px solid rgba(255,255,255,.12);padding-top:10px}
.rk-rv__m b{color:#fff;font-weight:600}
.rk-rv__a{display:inline-block;margin-top:26px;color:#fff!important;font-family:var(--rk-mono,monospace);font-size:13px;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;border-bottom:1px solid var(--rk-red,#ff3b30);padding-bottom:3px}
</style>';
	$out .= '<section class="rk-rv" aria-label="Bewertungen von Käufern auf eBay"><div class="rk-rv__in">'
		. '<p class="rk-rv__k">Echte Käufer, echte Bewertungen</p>'
		. '<h2 class="rk-rv__h">Das sagen <span>Sammler.</span></h2>'
		. '<p class="rk-rv__s">Direkt aus unserem eBay Profil, nichts verändert. Datum ist der Kauftag.</p><div class="rk-rv__g">';
	foreach ( rk_ebay_reviews_data() as $r ) {
		$out .= '<figure class="rk-rv__c" style="margin:0"><div class="rk-rv__st" aria-label="5 von 5 Sternen">★★★★★</div>'
			. '<blockquote class="rk-rv__t" style="margin:0;padding:0;border:0">„' . esc_html( $r['t'] ) . '“</blockquote>'
			. '<figcaption class="rk-rv__m"><b>' . esc_html( $r['u'] ) . '</b> auf eBay<br>Kauf am ' . esc_html( $r['d'] ) . '<br>' . esc_html( $r['i'] ) . '</figcaption></figure>';
	}
	$out .= '</div><a class="rk-rv__a" href="' . esc_url( $s['profil'] ) . '" target="_blank" rel="noopener">Alle Bewertungen auf eBay ↗</a></div></section>';
	return $out;
}

add_filter( 'render_block', function ( $content ) {
	static $done = false;
	if ( $done || ! is_front_page() ) {
		return $content;
	}
	$needle = '<section class="rk-cta">';
	if ( false !== strpos( $content, $needle ) ) {
		$done = true;
		return str_replace( $needle, rk_ebay_reviews_html() . $needle, $content );
	}
	return $content;
}, 9 );

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
	$note = '<div class="rk-dl" style="margin:0 0 22px;padding:14px 18px;border:1px solid rgba(255,255,255,.2);background:#0b0b0b;color:#fff;font-size:15px;line-height:1.5"><b style="color:var(--rk-red,#ff3b30);font-family:var(--rk-mono,monospace);font-size:12px;letter-spacing:.08em;text-transform:uppercase;display:block;margin-bottom:4px">Lieferung</b>Wir verschicken innerhalb von 1 Werktag. Die Sendungsnummer bekommst du per E Mail. Zustellung in Deutschland meist in 1 bis 3 Werktagen, Versand 1,10 € pro Bestellung, ab 70 € Warenwert kostenlos.</div>';
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
	echo '<style>table.wc-block-cart-items .wc-block-cart-item__image img,.wc-block-components-order-summary-item__image img,.wc-block-mini-cart__products-table .wc-block-cart-item__image img{width:72px!important;height:auto!important;max-height:none!important;object-fit:contain!important;aspect-ratio:auto!important}.wc-block-cart-item__image,.wc-block-components-order-summary-item__image{width:auto!important;height:auto!important}.wc-block-cart-items .wc-block-cart-item__image{padding-right:18px!important}table.wc-block-mini-cart-items td.wc-block-cart-item__image{padding-right:0!important}table.wc-block-mini-cart-items td.wc-block-cart-item__image img{width:46px!important}.wc-block-components-order-summary-item__image{margin-right:14px!important}</style>';
}, 99 );

// Hero: Ladenfoto ganz leicht als Hintergrund (5 %)
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	echo '<style>.rk-hero{isolation:isolate}.rk-hero::before{content:"";position:absolute;inset:0;z-index:-1;background:url(' . esc_url( content_url( '/uploads/2026/11/rookie-laden-hero.jpg' ) ) . ') center/cover no-repeat;opacity:.05;pointer-events:none}</style>'; // phpcs:ignore
}, 30 );

// Google Search Console Bestaetigung
add_action( 'wp_head', function () {
	echo '<meta name="google-site-verification" content="II0Fqa-LIip75ZSIfGzMX49l09c1LSGRGHTq4BdsVsc" />' . "\n";
}, 1 );

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
	$c = get_transient( 'rk_sold_pool2' );
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
	$seen = array();
	// 1) Im Shop verkauft: Karten aus echten Bestellungen
	foreach ( wc_get_orders( array( 'status' => array( 'processing', 'completed', 'on-hold' ), 'limit' => 60, 'orderby' => 'date', 'order' => 'DESC' ) ) as $o ) {
		$t = $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0;
		foreach ( $o->get_items() as $it ) {
			$pid = $it->get_product_id();
			if ( $pid && ! isset( $seen[ $pid ] ) ) {
				$seen[ $pid ] = $t;
			}
		}
	}
	// 2) Auf eBay verkauft: vom eBay Abgleich oder vom Shop als verkauft markiert
	$q = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => array( 'draft', 'private', 'trash' ),
		'posts_per_page' => 80,
		'fields'         => 'ids',
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'meta_query'     => array( 'relation' => 'OR', array( 'key' => '_rk_ebay_off', 'compare' => 'EXISTS' ), array( 'key' => '_rk_shop_sold', 'compare' => 'EXISTS' ) ),
	) );
	foreach ( $q as $pid ) {
		if ( ! isset( $seen[ $pid ] ) ) {
			$seen[ $pid ] = (int) get_post_modified_time( 'U', true, $pid );
		}
	}
	arsort( $seen );
	$out = array();
	foreach ( $seen as $pid => $t ) {
		$p = wc_get_product( $pid );
		// Nur zeigen, was wirklich weg ist: noch kaufbare Karten nie als verkauft
		if ( ! $p || ! $p->get_image_id() || ( 'publish' === $p->get_status() && $p->is_in_stock() ) ) {
			continue;
		}
		$out[] = array( 'name' => $p->get_name(), 'img' => $p->get_image( 'medium', array( 'loading' => 'lazy', 'alt' => '' ) ) );
		if ( count( $out ) >= 40 ) {
			break;
		}
	}
	set_transient( 'rk_sold_pool2', $out, 3 * HOUR_IN_SECONDS );
	return $out;
}

// Ersatz, wenn zu wenig verkauft wurde: taeglich wechselnde Karten, die noch zu haben sind
function rk_fokus_items( $n = 6 ) {
	$key = 'rk_fokus_' . gmdate( 'Ymd' );
	$out = get_transient( $key );
	if ( is_array( $out ) ) {
		return $out;
	}
	$ids = wc_get_products( array( 'status' => 'publish', 'stock_status' => 'instock', 'limit' => -1, 'return' => 'ids' ) );
	mt_srand( (int) gmdate( 'Ymd' ) );
	shuffle( $ids );
	mt_srand();
	$out = array();
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p || ! $p->get_image_id() ) {
			continue;
		}
		$out[] = array( 'name' => $p->get_name(), 'img' => $p->get_image( 'medium', array( 'loading' => 'lazy', 'alt' => '' ) ), 'url' => get_permalink( $id ) );
		if ( count( $out ) >= $n ) {
			break;
		}
	}
	set_transient( $key, $out, DAY_IN_SECONDS );
	return $out;
}

function rk_sold_strip_html() {
	$items = rk_sold_items( 6 );
	$fokus = count( $items ) < 4;
	if ( $fokus ) {
		$items = rk_fokus_items( 6 );
		if ( count( $items ) < 4 ) {
			return '';
		}
	}
	$o = '<style>.rk-sold{background:#0b0b0b;border-top:1px solid rgba(255,255,255,.12);padding:56px 0 50px;color:#fff}.rk-sold__in{width:min(1100px,calc(100% - 40px));margin:0 auto}.rk-sold__k{font-family:var(--rk-mono,monospace);font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--rk-red,#ff3b30);margin:0 0 10px}.rk-sold__h{font-family:"Barlow Condensed",sans-serif;font-weight:800;font-size:clamp(32px,5vw,54px);line-height:.95;text-transform:uppercase;margin:0 0 8px}.rk-sold__s{opacity:.7;font-size:15px;margin:0 0 24px}.rk-sold__g{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px}.rk-sold__g li{position:relative;border:1px solid rgba(255,255,255,.14);background:#111;padding:10px}.rk-sold__img{display:block;position:relative;aspect-ratio:3/4;overflow:hidden;background:#0b0b0b}.rk-sold__img img{position:absolute;inset:0;width:100%!important;height:100%!important;object-fit:contain!important;filter:grayscale(1) brightness(.6)}.rk-sold__tag{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate(-12deg);background:var(--rk-red,#ff3b30);color:#fff;font-family:"Barlow Condensed",sans-serif;font-weight:800;font-size:22px;letter-spacing:.08em;padding:2px 12px;text-transform:uppercase}.rk-sold__n{font-size:12px;line-height:1.3;margin-top:8px;opacity:.7;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}</style><section class="rk-sold" aria-label="Zuletzt verkauft"><div class="rk-sold__in"><p class="rk-sold__k">Weg</p><h2 class="rk-sold__h">Zuletzt verkauft<span style="color:var(--rk-red,#ff3b30)">.</span></h2><p class="rk-sold__s">Jede Karte gibt es nur einmal. Diese hier haben schon einen neuen Besitzer.</p><ul class="rk-sold__g">';
	foreach ( $items as $it ) {
		$inner = '<span class="rk-sold__img">' . $it['img'] . '<span class="rk-sold__tag">' . ( $fokus ? 'Noch da' : 'Verkauft' ) . '</span></span><div class="rk-sold__n">' . esc_html( $it['name'] ) . '</div>';
		$o .= '<li>' . ( ! empty( $it['url'] ) ? '<a href="' . esc_url( $it['url'] ) . '" style="color:inherit;text-decoration:none;display:block">' . $inner . '</a>' : $inner ) . '</li>';
	}
	if ( $fokus ) {
		$o = str_replace( array( '>Weg<', 'aria-label="Zuletzt verkauft"', 'Zuletzt verkauft<span', 'Jede Karte gibt es nur einmal. Diese hier haben schon einen neuen Besitzer.' ), array( '>Heute<', 'aria-label="Heute im Fokus"', 'Heute im Fokus<span', 'Jeden Tag andere Karten aus dem Shop. Jede gibt es nur einmal.' ), $o );
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

/* Verpackung nach Preis: 0,80 € Karten in Penny Sleeve + Team Bag, alles andere Penny Sleeve + Toploader. Greift bei jedem Speichern. */
add_action( 'woocommerce_before_product_object_save', function ( $p ) {
	$preis = (float) $p->get_regular_price();
	if ( $preis <= 0 ) {
		return;
	}
	$tl = 'Penny Sleeve und Toploader im gepolsterten';
	$tb = 'Penny Sleeve und Team Bag im gepolsterten';
	list( $von, $nach ) = $preis <= 0.80 ? array( $tl, $tb ) : array( $tb, $tl );
	foreach ( array( 'description', 'short_description' ) as $f ) {
		$t = $p->{"get_$f"}( 'edit' );
		if ( $t && false !== strpos( $t, $von ) ) {
			$p->{"set_$f"}( str_replace( $von, $nach, $t ) );
		}
	}
} );

/* Ratgeber Checklisten: Spalte "Im Shop" live aus dem Bestand füllen.
   Im Seiteninhalt: <div class="rk-cl" data-rkcl="pa_serie-slug|kategorie-slug|jahr"> ... <td class="rk-cl-shop" data-nr="301" data-k="mendoza"></td>
   data-k = Nachname in Kleinbuchstaben ohne Sonderzeichen (leer = jede Karte mit der Nummer). */
function rk_cl_map( $serie, $kat, $jahr ) {
	$key = 'rkcl_' . md5( $serie . $kat . $jahr ) . '_' . get_option( 'rkcl_v', 1 );
	$map = get_transient( $key );
	if ( false !== $map ) {
		return $map;
	}
	$map = array();
	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'tax_query'      => array(
			array( 'taxonomy' => 'pa_serie', 'field' => 'slug', 'terms' => $serie ),
			array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $kat ),
		),
	) );
	$teams = 'Arizona Cardinals|Atlanta Falcons|Baltimore Ravens|Buffalo Bills|Carolina Panthers|Chicago Bears|Cincinnati Bengals|Cleveland Browns|Dallas Cowboys|Denver Broncos|Detroit Lions|Green Bay Packers|Houston Texans|Indianapolis Colts|Jacksonville Jaguars|Kansas City Chiefs|Las Vegas Raiders|Los Angeles Chargers|Los Angeles Rams|Miami Dolphins|Minnesota Vikings|New England Patriots|New Orleans Saints|New York Giants|New York Jets|Philadelphia Eagles|Pittsburgh Steelers|San Francisco 49ers|Seattle Seahawks|Tampa Bay Buccaneers|Tennessee Titans|Washington Commanders|FC Bayern München|Bayer 04 Leverkusen|SV Werder Bremen|RB Leipzig|Eintracht Frankfurt|1\. FSV Mainz 05|VfB Stuttgart|FC Schalke 04|Borussia Dortmund|TSG Hoffenheim|SG Dynamo Dresden|SC Freiburg|1\. FC Köln|Eintracht Braunschweig|1\. FC Union Berlin|FC Energie Cottbus|Borussia Mönchengladbach|1\. FC Kaiserslautern|VfL Bochum 1848|Hamburger SV|SV Darmstadt 98|FC Augsburg|1\. FC Nürnberg|1\. FC Magdeburg|VfL Wolfsburg|VfL Osnabrück|FC St\. Pauli|Holstein Kiel|SpVgg Greuther Fürth|SC Paderborn 07|1\. FC Heidenheim 1846|Hannover 96|DSC Arminia Bielefeld|SV Elversberg|Karlsruher SC|Hertha BSC';
	foreach ( $ids as $id ) {
		$t = html_entity_decode( get_the_title( $id ), ENT_QUOTES );
		if ( false === strpos( $t, $jahr ) || ! preg_match( '/#(\d+)\s(.*)$/', $t, $m ) ) {
			continue;
		}
		$p = wc_get_product( $id );
		if ( ! $p || ! $p->is_in_stock() ) {
			continue;
		}
		$l = preg_replace( '/\s*' . preg_quote( $jahr, '/' ) . '\S*\s.*$/', '', $m[2] );
		$l = preg_replace( '/(?<![\pL\d])(' . $teams . ')(?![\pL\d])/u', '', $l );
		$l = preg_replace( '/\b(AFC|NFC)\b.*\bLeaders\b/', '', $l );
		$l = trim( preg_replace( '/\s+/', ' ', $l ) );
		$v = trim( (string) $p->get_attribute( 'Variante' ) );
		if ( '' === $l && '' !== $v && 'Base' !== $v ) {
			$l = $v;
		}
		$map[ (int) $m[1] ][] = array(
			'k' => preg_replace( '/[^a-z]/', '', strtolower( remove_accents( $t ) ) ),
			'u' => get_permalink( $id ),
			'l' => '' === $l ? 'Base' : $l,
			'p' => (float) $p->get_price(),
		);
	}
	set_transient( $key, $map, HOUR_IN_SECONDS );
	return $map;
}

add_filter( 'the_content', function ( $c ) {
	if ( false === strpos( $c, 'rk-cl-shop' ) || ! preg_match( '/data-rkcl="([^"|]+)\|([^"|]+)\|(\d{4})"/', $c, $m ) || ! function_exists( 'wc_get_product' ) ) {
		return $c;
	}
	do_action( 'litespeed_tag_add', 'rkcl' );
	$map   = rk_cl_map( $m[1], $m[2], $m[3] );
	$found = 0;
	$c     = preg_replace_callback( '/<td class="rk-cl-shop" data-nr="(\d+)" data-k="([a-z]*)"><\/td>/', function ( $x ) use ( $map, &$found ) {
		$items = array();
		foreach ( isset( $map[ (int) $x[1] ] ) ? $map[ (int) $x[1] ] : array() as $i ) {
			if ( '' === $x[2] || false !== strpos( $i['k'], $x[2] ) ) {
				$items[] = $i;
			}
		}
		if ( ! $items ) {
			return '<td class="rk-cl-shop"><a class="rk-cl-such" href="/suchauftrag/">Suchauftrag</a></td>';
		}
		$found++;
		usort( $items, function ( $a, $b ) {
			return $a['p'] <=> $b['p'];
		} );
		$out = array();
		foreach ( $items as $i ) {
			$out[] = '<a href="' . esc_url( $i['u'] ) . '">' . esc_html( $i['l'] ) . ' <b>' . esc_html( number_format( $i['p'], 2, ',', '.' ) . ' €' ) . '</b></a>';
		}
		return '<td class="rk-cl-shop rk-cl-da">' . implode( '', $out ) . '</td>';
	}, $c );
	return str_replace( '<span class="rk-cl-count"></span>', '<span class="rk-cl-count">' . (int) $found . '</span>', $c );
}, 20 );

/* Bestand geändert: Checklisten neu berechnen und Seitencache der Checklisten leeren */
function rk_cl_bump() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	update_option( 'rkcl_v', time(), false );
	do_action( 'litespeed_purge', 'rkcl' );
}
add_action( 'woocommerce_update_product', 'rk_cl_bump' );
add_action( 'woocommerce_new_product', 'rk_cl_bump' );
add_action( 'woocommerce_product_set_stock', 'rk_cl_bump' );
add_action( 'woocommerce_product_set_stock_status', 'rk_cl_bump' );
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'product' === $post->post_type && $new !== $old ) {
		rk_cl_bump();
	}
}, 10, 3 );

/* ===== Ratgeber Layout: Titelbild, Autorenzeile, Shop Karten, Weiterlesen, Übersicht als Kacheln ===== */
define( 'RK_RG_HUB', 3107 );

function rk_rg_is_article( $id = 0 ) {
	$id = $id ? $id : get_the_ID();
	return $id && 'page' === get_post_type( $id ) && RK_RG_HUB === (int) wp_get_post_parent_id( $id ) && 'publish' === get_post_status( $id );
}
function rk_rg_is_any() {
	return is_page() && ( RK_RG_HUB === get_queried_object_id() || RK_RG_HUB === (int) wp_get_post_parent_id( get_queried_object_id() ) );
}
function rk_rg_minutes( $id ) {
	$t = wp_strip_all_tags( preg_replace( '/<(style|script|table)[^>]*>.*?<\/\1>/s', '', get_post_field( 'post_content', $id ) ) );
	return max( 2, (int) ceil( str_word_count( $t, 0, 'äöüÄÖÜß' ) / 210 ) );
}
function rk_rg_tag( $slug ) {
	if ( false !== strpos( $slug, 'checkliste' ) ) {
		return 'Checkliste';
	}
	if ( preg_match( '/kaufen|-vs-/', $slug ) ) {
		return 'Kaufberatung';
	}
	if ( preg_match( '/lagern|zustand|graden/', $slug ) ) {
		return 'Pflege und Zustand';
	}
	if ( false !== strpos( $slug, 'pokemon' ) ) {
		return 'Pokémon';
	}
	return 'Grundlagen';
}
function rk_rg_children() {
	return get_posts( array( 'post_type' => 'page', 'post_parent' => RK_RG_HUB, 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) );
}
function rk_rg_card( $p, $big = false ) {
	$img = get_the_post_thumbnail_url( $p, $big ? 'large' : 'medium_large' );
	$out = '<a class="rk-rg-card' . ( $big ? ' rk-rg-card--big' : '' ) . '" href="' . esc_url( get_permalink( $p ) ) . '">';
	if ( $img ) {
		$out .= '<span class="rk-rg-card__img"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title( $p ) ) . '" loading="lazy" decoding="async" width="1200" height="675"></span>';
	}
	$out .= '<span class="rk-rg-card__body"><span class="rk-rg-tag">' . esc_html( rk_rg_tag( $p->post_name ) ) . '</span>';
	$out .= '<span class="rk-rg-card__title">' . esc_html( get_the_title( $p ) ) . '</span>';
	if ( has_excerpt( $p ) ) {
		$out .= '<span class="rk-rg-card__txt">' . esc_html( wp_trim_words( get_the_excerpt( $p ), $big ? 40 : 22 ) ) . '</span>';
	}
	$out .= '<span class="rk-rg-card__meta">' . rk_rg_minutes( $p->ID ) . ' Min Lesezeit <i>→</i></span></span></a>';
	return $out;
}

/* Shop Karten zum Thema: [rk_karten suche="Rookie|Refractor" serie="topps-flagship" kat="nfl" n="4" sort="preis|neu|zufall" titel="..."] */
add_shortcode( 'rk_karten', function ( $a ) {
	$a = shortcode_atts( array( 'suche' => '', 'serie' => '', 'kat' => '', 'n' => 4, 'sort' => 'preis', 'min' => 0, 'titel' => '', 'link' => '', 'linktext' => 'Alle ansehen' ), $a );
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	do_action( 'litespeed_tag_add', 'rkcl' );
	$key   = 'rkrg2_' . md5( wp_json_encode( $a ) . gmdate( 'Ymd' ) ) . '_' . get_option( 'rkcl_v', 1 );
	$items = get_transient( $key );
	if ( false === $items ) {
		global $wpdb;
		$where = array();
		foreach ( array_filter( array_map( 'trim', explode( '|', $a['suche'] ) ) ) as $s ) {
			$where[] = $wpdb->prepare( 'post_title LIKE %s', '%' . $wpdb->esc_like( $s ) . '%' );
		}
		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'" . ( $where ? ' AND (' . implode( ' OR ', $where ) . ')' : '' ) . ' ORDER BY post_date DESC LIMIT 400';
		$items = array();
		foreach ( $wpdb->get_col( $sql ) as $id ) { // phpcs:ignore
			if ( $a['serie'] && ! has_term( $a['serie'], 'pa_serie', $id ) ) {
				continue;
			}
			if ( $a['kat'] && ! has_term( $a['kat'], 'product_cat', $id ) ) {
				continue;
			}
			$p = wc_get_product( $id );
			if ( ! $p || ! $p->is_in_stock() || (float) $p->get_price() < (float) $a['min'] || ! $p->get_image_id() ) {
				continue;
			}
			$items[] = array( 'u' => get_permalink( $id ), 'n' => html_entity_decode( get_the_title( $id ), ENT_QUOTES ), 'p' => (float) $p->get_price(), 'i' => wp_get_attachment_image_url( $p->get_image_id(), 'medium' ), 'd' => get_post_time( 'U', true, $id ) );
		}
		if ( 'preis' === $a['sort'] ) {
			usort( $items, function ( $x, $y ) { return $y['p'] <=> $x['p']; } );
		} elseif ( 'zufall' === $a['sort'] ) {
			mt_srand( crc32( $key ) );
			for ( $i = count( $items ) - 1; $i > 0; $i-- ) {
				$j = mt_rand( 0, $i );
				$t = $items[ $i ];
				$items[ $i ] = $items[ $j ];
				$items[ $j ] = $t;
			}
			mt_srand();
		}
		$items = array_slice( $items, 0, max( 1, (int) $a['n'] ) );
		set_transient( $key, $items, 6 * HOUR_IN_SECONDS );
	}
	if ( ! $items ) {
		return '';
	}
	$out = '<div class="rk-rg-karten">';
	if ( $a['titel'] ) {
		$out .= '<div class="rk-rg-karten__kopf"><span>' . esc_html( $a['titel'] ) . '</span>' . ( $a['link'] ? '<a href="' . esc_url( $a['link'] ) . '">' . esc_html( $a['linktext'] ) . ' →</a>' : '' ) . '</div>';
	}
	$out .= '<div class="rk-rg-karten__grid">';
	foreach ( $items as $i ) {
		$name = preg_replace( '/\s+20\d\d\s.*$/', '', $i['n'] );
		$out .= '<a class="rk-rg-karte" href="' . esc_url( $i['u'] ) . '"><span class="rk-rg-karte__img"><img src="' . esc_url( $i['i'] ) . '" alt="' . esc_attr( $i['n'] ) . '" loading="lazy" decoding="async"></span><span class="rk-rg-karte__name">' . esc_html( $name ) . '</span><span class="rk-rg-karte__preis">' . esc_html( number_format( $i['p'], 2, ',', '.' ) ) . ' €</span></a>';
	}
	return $out . '</div></div>';
} );

/* Übersicht: [rk_ratgeber_grid] */
add_shortcode( 'rk_ratgeber_grid', function () {
	$kids = rk_rg_children();
	if ( ! $kids ) {
		return '';
	}
	usort( $kids, function ( $x, $y ) {
		return strtotime( $y->post_date ) <=> strtotime( $x->post_date );
	} );
	$out = '<div class="rk-rg-grid">' . rk_rg_card( array_shift( $kids ), true );
	foreach ( $kids as $k ) {
		$out .= rk_rg_card( $k );
	}
	return $out . '</div>';
} );

/* Artikel: Titelbild und Autorenzeile davor, Shop Karten und Weiterlesen danach */
add_filter( 'the_content', function ( $c ) {
	static $busy = false;
	$id = get_the_ID();
	if ( $busy || is_admin() || ! is_page() || get_queried_object_id() !== $id || ! rk_rg_is_article( $id ) ) {
		return $c;
	}
	$busy = true;
	$img  = get_the_post_thumbnail( $id, 'large', array( 'class' => 'rk-rg-cover__img', 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '(max-width: 900px) 100vw, 900px' ) );
	$post = get_post( $id );
	$head = '<div class="rk-rg-kopf">';
	if ( $img ) {
		$head .= '<figure class="rk-rg-cover">' . $img . '<span class="rk-rg-tag">' . esc_html( rk_rg_tag( $post->post_name ) ) . '</span></figure>';
	}
	$head .= '<div class="rk-rg-autor"><img src="' . esc_url( get_theme_file_uri( 'assets/img/marcel.webp' ) ) . '" alt="Marcel Weber" width="44" height="44"><span><b>Marcel Weber</b> Sammler und Händler, seit über 6 Jahren im Kartenbusiness</span><span class="rk-rg-autor__meta">' . rk_rg_minutes( $id ) . ' Min Lesezeit · Stand ' . esc_html( get_the_modified_date( 'd.m.Y', $id ) ) . '</span></div></div>';
	$c = preg_replace( '/<p>/', '<p class="rk-rg-lead">', $c, 1 );

	$more  = array();
	$kids  = rk_rg_children();
	$ids   = wp_list_pluck( $kids, 'ID' );
	$pos   = array_search( $id, $ids, true );
	for ( $i = 1; $i <= count( $kids ) && count( $more ) < 3; $i++ ) {
		$k = $kids[ ( $pos + $i ) % count( $kids ) ];
		if ( $k->ID !== $id ) {
			$more[] = rk_rg_card( $k );
		}
	}
	$foot  = '<div class="rk-rg-fuss"><div class="rk-rg-cta"><div><b>Deine Karte ist nicht dabei?</b><span>Sag uns, was du suchst. Wir melden uns, sobald wir sie haben.</span></div><a class="rk-rg-btn" href="/suchauftrag/">Suchauftrag aufgeben</a></div>';
	$foot .= $more ? '<h2 class="rk-rg-weiter">Weiterlesen</h2><div class="rk-rg-grid rk-rg-grid--3">' . implode( '', $more ) . '</div>' : '';
	$foot .= '<p class="rk-rg-zurueck"><a href="/ratgeber/">← Alle Ratgeber</a></p></div>';
	$busy  = false;
	return $head . '<div class="rk-rg-body">' . $c . '</div>' . $foot;
}, 25 );

add_action( 'wp_head', function () {
	if ( ! rk_rg_is_any() ) {
		return;
	}
	?>
<style id="rk-ratgeber">
.rk-rg-tag{display:inline-block;font:600 12px/1 "Barlow Condensed",Impact,sans-serif;letter-spacing:.12em;text-transform:uppercase;color:#0a0a0a;background:#2ee6d6;padding:6px 9px}
.rk-rg-kopf{max-width:900px!important;margin:0 auto 34px!important}
.rk-rg-cover{position:relative;margin:0 0 18px!important;border:1px solid rgba(255,255,255,.08);overflow:hidden;background:#111}
.rk-rg-cover img{display:block;width:100%;height:auto;aspect-ratio:16/9;object-fit:cover}
.rk-rg-cover .rk-rg-tag{position:absolute;left:16px;top:16px}
.rk-rg-autor{display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:13px;line-height:1.35;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.1)}
.rk-rg-autor img{width:44px;height:44px;border-radius:50%;border:2px solid #ff3b30;object-fit:cover}
.rk-rg-autor b{display:block;font-size:14px}
.rk-rg-autor span{opacity:.85}
.rk-rg-autor__meta{margin-left:auto;opacity:.6!important;white-space:nowrap}
.rk-rg-body{counter-reset:rkh}
.rk-rg-body .rk-rg-lead{font-size:clamp(17px,2vw,20px);line-height:1.6;opacity:.95;border-left:3px solid #ff3b30;padding-left:18px;margin-bottom:34px}
.rk-rg-body h2{position:relative;margin-top:2.4em!important;padding-top:14px}
.rk-rg-body h2::before{content:"";position:absolute;left:0;top:0;width:44px;height:4px;background:#ff3b30}
.rk-rg-body ul:not([class]){list-style:none;padding-left:0}
.rk-rg-body ul:not([class]) li{position:relative;padding:10px 12px 10px 34px;margin:0 0 6px;background:#121214;border:1px solid rgba(255,255,255,.07)}
.rk-rg-body ul:not([class]) li::before{content:"";position:absolute;left:13px;top:17px;width:8px;height:8px;background:#ff3b30;transform:rotate(45deg)}
.rk-rg-body table{width:100%;border-collapse:collapse;font-size:14px;margin:14px 0 24px}
.rk-rg-body th{text-align:left;font:600 15px "Barlow Condensed",Impact,sans-serif;text-transform:uppercase;letter-spacing:.04em;padding:9px 10px;border-bottom:2px solid #ff3b30}
.rk-rg-body td{padding:9px 10px;border-bottom:1px solid rgba(255,255,255,.1)}
.rk-rg-body tbody tr:nth-child(odd) td{background:rgba(255,255,255,.025)}
.rk-rg-tipp{position:relative;margin:28px 0!important;padding:18px 20px 18px 64px;background:linear-gradient(135deg,rgba(46,230,214,.12),rgba(46,230,214,.02));border:1px solid rgba(46,230,214,.35)}
.rk-rg-tipp::before{content:"!";position:absolute;left:18px;top:16px;width:30px;height:30px;border-radius:50%;background:#2ee6d6;color:#0a0a0a;font:700 20px/30px "Barlow Condensed",Impact,sans-serif;text-align:center}
.rk-rg-tipp b{display:block;font:600 18px "Barlow Condensed",Impact,sans-serif;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
.rk-rg-zitat{margin:34px 0!important;padding:8px 0 8px 22px;border-left:4px solid #ff3b30;font:600 clamp(22px,3vw,30px)/1.15 "Barlow Condensed",Impact,sans-serif;text-transform:uppercase}
.rk-rg-step{display:flex;align-items:center;gap:14px}
.rk-rg-step span{flex:0 0 auto;display:inline-grid;place-items:center;width:46px;height:46px;background:#ff3b30;color:#fff;font:700 26px/1 "Barlow Condensed",Impact,sans-serif}
.rk-rg-body h2.rk-rg-step::before{display:none}
.rk-rg-skala{display:grid;grid-template-columns:repeat(6,1fr);gap:4px;margin:22px 0 30px!important}
.rk-rg-skala div{padding:12px 8px 10px;text-align:center;font:600 14px/1.1 "Barlow Condensed",Impact,sans-serif;text-transform:uppercase;letter-spacing:.03em;color:#0a0a0a}
.rk-rg-skala small{display:block;font:500 11px Manrope,Arial,sans-serif;text-transform:none;letter-spacing:0;margin-top:4px;opacity:.75}
.rk-rg-vs{display:grid;grid-template-columns:1fr auto 1fr;gap:14px;align-items:stretch;margin:26px 0 34px!important}
.rk-rg-vs__seite{background:#121214;border:1px solid rgba(255,255,255,.08);padding:16px}
.rk-rg-vs__seite h3{margin:0 0 8px!important}
.rk-rg-vs__seite ul{margin:0;padding-left:18px;font-size:14px}
.rk-rg-vs .rk-rg-karten{margin:0!important;height:100%}.rk-rg-vs .rk-rg-karten__grid{grid-template-columns:1fr 1fr}
.rk-rg-vs__mitte{display:grid;place-items:center;font:700 30px "Barlow Condensed",Impact,sans-serif;color:#ff3b30}
.rk-rg-karten{margin:30px 0 38px!important;padding:18px;background:#101012;border:1px solid rgba(255,255,255,.08)}
.rk-rg-karten__kopf{display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin-bottom:14px}
.rk-rg-karten__kopf span{font:600 20px "Barlow Condensed",Impact,sans-serif;text-transform:uppercase;letter-spacing:.04em}
.rk-rg-karten__kopf a{font-size:13px;color:#ff3b30;text-decoration:none;white-space:nowrap}
.rk-rg-karten__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.rk-rg-karte{display:flex;flex-direction:column;color:#fff;text-decoration:none;min-width:0}.rk-rg-vs>div{min-width:0}.rk-rg-karte__img img{max-width:100%}
.rk-rg-karte__img{display:grid;place-items:center;aspect-ratio:3/4;background:radial-gradient(circle at 50% 30%,#232327,#141416);padding:10px;overflow:hidden}
.rk-rg-karte__img img{max-height:100%;width:auto;object-fit:contain;transition:transform .25s ease}
.rk-rg-karte:hover .rk-rg-karte__img img{transform:scale(1.05) rotate(-1deg)}
.rk-rg-karte__name{font-size:12.5px;line-height:1.35;margin-top:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;opacity:.9}
.rk-rg-karte__preis{font:700 18px "Barlow Condensed",Impact,sans-serif;color:#ff3b30;margin-top:2px}
.rk-rg-split{display:grid;grid-template-columns:1fr 1fr;gap:22px;align-items:center;margin:30px 0!important}
.rk-rg-split img{width:100%;height:auto;display:block;border:1px solid rgba(255,255,255,.08)}
.rk-rg-fuss{max-width:1180px!important;margin:50px auto 0!important}
.rk-rg-cta{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap;max-width:900px;margin:0 auto 46px;padding:22px 24px;background:linear-gradient(120deg,#1a0d0c,#121214 60%);border:1px solid rgba(255,59,48,.35)}
.rk-rg-cta b{display:block;font:600 24px "Barlow Condensed",Impact,sans-serif;text-transform:uppercase}
.rk-rg-cta span{font-size:14px;opacity:.8}
.rk-rg-btn{display:inline-block;background:#ff3b30;color:#fff!important;text-decoration:none!important;font:600 16px "Barlow Condensed",Impact,sans-serif;text-transform:uppercase;letter-spacing:.06em;padding:13px 22px}
.rk-rg-btn:hover{background:#fff;color:#0a0a0a!important}
.rk-rg-weiter{max-width:1180px;margin:0 auto 18px!important}
.rk-rg-zurueck{text-align:center;margin-top:30px!important}
.rk-rg-zurueck a{color:#fff;opacity:.7}
.rk-rg-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:1180px!important;margin-left:auto!important;margin-right:auto!important}
.rk-rg-card{display:flex;flex-direction:column;background:#111113;border:1px solid rgba(255,255,255,.08);color:#fff;text-decoration:none;transition:transform .2s ease,border-color .2s ease}
.rk-rg-card:hover{transform:translateY(-4px);border-color:rgba(255,59,48,.6)}
.rk-rg-card__img{display:block;overflow:hidden;aspect-ratio:16/9;background:#0d0d0f}
.rk-rg-card__img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .35s ease}
.rk-rg-card:hover .rk-rg-card__img img{transform:scale(1.04)}
.rk-rg-card__body{display:flex;flex-direction:column;gap:9px;padding:16px 18px 18px;flex:1}
.rk-rg-card__body .rk-rg-tag{align-self:flex-start}
.rk-rg-card__title{font:600 23px/1.05 "Barlow Condensed",Impact,sans-serif;text-transform:uppercase}
.rk-rg-card__txt{font-size:14px;line-height:1.5;opacity:.75}
.rk-rg-card__meta{margin-top:auto;font-size:12px;opacity:.6;display:flex;justify-content:space-between}
.rk-rg-card__meta i{font-style:normal;color:#ff3b30;opacity:1;font-size:16px}
.rk-rg-card--big{grid-column:1/-1;flex-direction:row}
.rk-rg-card--big .rk-rg-card__img{flex:0 0 58%;aspect-ratio:16/9}
.rk-rg-card--big .rk-rg-card__body{padding:28px 30px;justify-content:center}
.rk-rg-card--big .rk-rg-card__title{font-size:clamp(28px,3.2vw,42px)}
.rk-rg-card--big .rk-rg-card__txt{font-size:15px}
.rk-rg-hub-hero{position:relative;max-width:1180px!important;margin:0 auto 34px!important;min-height:300px;display:flex;align-items:flex-end;padding:34px;background-size:cover;background-position:right center;border:1px solid rgba(255,255,255,.08)}
.rk-rg-hub-hero::before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,#0a0a0a 25%,rgba(10,10,10,.55) 60%,rgba(10,10,10,.1))}
.rk-rg-hub-hero>div{position:relative;max-width:520px}
.rk-rg-hub-hero b{display:block;font:600 clamp(30px,4vw,48px)/1 "Barlow Condensed",Impact,sans-serif;text-transform:uppercase;margin:12px 0 10px}
.rk-rg-hub-hero p{margin:0;opacity:.85}
.rk-rg-themen{display:flex;flex-wrap:wrap;gap:8px;max-width:1180px!important;margin:40px auto 0!important}
.rk-rg-themen a{border:1px solid rgba(255,255,255,.15);padding:9px 14px;color:#fff;text-decoration:none;font:600 15px "Barlow Condensed",Impact,sans-serif;text-transform:uppercase;letter-spacing:.05em}
.rk-rg-themen a:hover{border-color:#ff3b30;color:#ff3b30}
@media (max-width:900px){.rk-rg-grid{grid-template-columns:1fr 1fr}.rk-rg-card--big{flex-direction:column}.rk-rg-card--big .rk-rg-card__img{flex:none}.rk-rg-karten__grid{grid-template-columns:repeat(2,1fr)}}
@media (max-width:600px){.rk-rg-grid{grid-template-columns:1fr}.rk-rg-split,.rk-rg-vs{grid-template-columns:1fr}.rk-rg-vs__mitte{padding:4px}.rk-rg-skala{grid-template-columns:repeat(3,1fr)}.rk-rg-autor__meta{margin-left:0}.rk-rg-hub-hero{padding:22px;min-height:260px}.rk-rg-tipp{padding-left:20px;padding-top:58px}}
</style>
	<?php
}, 40 );


/* Mailversand: Absender und Envelope Sender fest auf shop@rookie-business.de (Hostinger lehnt sonst ab) */
add_action( 'phpmailer_init', function ( $m ) {
	$m->Sender = 'shop@rookie-business.de';
	if ( false === strpos( (string) $m->From, '@rookie-business.de' ) ) {
		$m->From     = 'shop@rookie-business.de';
		$m->FromName = 'Rookie Business';
	}
} );
add_action( 'wp_mail_failed', function ( $e ) {
	update_option( 'rk_mail_last_error', current_time( 'mysql' ) . ' ' . $e->get_error_message(), false );
} );
/* Test: /?rk_mail_test=1 als Admin, schickt eine Testmail an die Admin Adresse */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['rk_mail_test'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore
		return;
	}
	nocache_headers();
	$ok = wp_mail( get_option( 'admin_email' ), 'Rookie Business Mailtest ' . current_time( 'H:i:s' ), 'Wenn du das liest, klappt der Mailversand vom Shop.' );
	wp_die( esc_html( ( $ok ? 'MAIL OK' : 'MAIL FEHLER: ' . get_option( 'rk_mail_last_error' ) ) ), 'Mailtest', array( 'response' => 200 ) );
} );


/* Versand nach Gewicht: 12 g pro Karte inklusive Penny Sleeve und Toploader.
   Deutschland versichert: 7,69 EUR bis 5 kg. EU: 14,49 EUR bis 2 kg mit Sendungsnummer. Guenstigste Versandart steht immer oben. */
function rk_versand_gramm( $contents ) {
	$g = 0;
	foreach ( (array) $contents as $it ) {
		$g += 12 * (int) $it['quantity'];
	}
	return $g;
}
add_filter( 'woocommerce_package_rates', function ( $rates, $package ) {
	$g = rk_versand_gramm( $package['contents'] );
	foreach ( $rates as $r ) {
		if ( 'flat_rate' === $r->get_method_id() && 3 === (int) $r->get_instance_id() ) {
			$r->set_cost( 7.69 );
		}
	}
	uasort( $rates, function ( $a, $b ) {
		return (float) $a->get_cost() <=> (float) $b->get_cost();
	} );
	return $rates;
}, 20, 2 );

/* ===== Versandhelfer: zeigt bei jeder Bestellung, womit sie verschickt wird =====
   12 g pro Karte inkl. Penny Sleeve und Toploader, plus Verpackung.
   Versandarten: 1 = DE Brief 1,10, 3 = DE versichert 7,69 bis 5 kg, 2 = EU 14,49 bis 2 kg mit Sendungsnummer */
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
	$mid  = '';
	foreach ( $order->get_shipping_methods() as $m ) {
		$inst = (int) $m->get_instance_id();
		$name = $m->get_name();
		$mid  = $m->get_method_id();
		break;
	}
	$land  = $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country();
	$g     = 12 * $karten;
	$porto = 'https://internetmarke.deutschepost.de/';
	$dhl   = 'https://www.dhl.de/de/privatkunden/pakete-versenden/online-frankieren.html';
	$hint  = '';
	if ( 1 === $inst || 'free_shipping' === $mid ) {
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
		$prod  = $gb <= 5000 ? 'DHL Paket 5 kg' : 'DHL Paket 10 kg (über 5 kg, Kunde hat nur 7,69 € bezahlt)';
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
		$prod  = $gb <= 2000 ? 'DHL Päckchen International' : 'DHL Paket International (über 2 kg, Kunde hat nur 14,49 € bezahlt)';
		$pack  = 'Karton, mit Sendungsnummer';
		$porto = $dhl;
	} elseif ( in_array( $land, array( 'CH', 'GB' ), true ) ) {
		$gb    = $g + 150;
		$prod  = $gb <= 2000 ? 'DHL Päckchen International mit Zollinhaltserklärung (CN22)' : 'DHL Paket International mit Zollinhaltserklärung (über 2 kg, Kunde hat nur 19,49 € bezahlt)';
		$pack  = 'Karton, versichert mit Sendungsnummer, Zollinhaltserklärung ausfüllen';
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

// v2.1 (26.11.2026): Gratisversand ab 70 €, Kundenkonto Link, Karten verkaufen Formular.

/* Ab 70 € Warenwert: kostenloser Brief, der 1,10 € Brief wird dann ausgeblendet. */
add_filter( 'woocommerce_package_rates', function ( $rates ) {
	$free = false;
	foreach ( $rates as $r ) {
		if ( 'free_shipping' === $r->get_method_id() ) {
			$free = true;
			break;
		}
	}
	if ( $free ) {
		foreach ( $rates as $id => $r ) {
			if ( 'flat_rate' === $r->get_method_id() && 1 === (int) $r->get_instance_id() ) {
				unset( $rates[ $id ] );
			}
		}
	}
	return $rates;
}, 50 );

/* Bewertungen als Platzhalter {{bewertungen}} auf normalen Seiten. */
add_filter( 'render_block', function ( $content ) {
	if ( false !== strpos( $content, '{{bewertungen}}' ) ) {
		$content = str_replace( '{{bewertungen}}', function_exists( 'rk_ebay_reviews_html' ) ? rk_ebay_reviews_html() : '', $content );
	}
	if ( false !== strpos( $content, '{{ankauf_form}}' ) ) {
		$content = str_replace( '{{ankauf_form}}', rk_ankauf_form(), $content );
	}
	return $content;
}, 5 );

/* ===== Karten verkaufen: Formular mit Fotos, Mail an den Shop ===== */
function rk_ankauf_form() {
	$msg = '';
	if ( isset( $_GET['ankauf'] ) ) {
		$msg = 'ok' === $_GET['ankauf']
			? '<div class="rk-ak__ok" role="status"><b>Danke, deine Anfrage ist angekommen.</b> Wir schauen uns deine Karten an und melden uns per E-Mail mit einem unverbindlichen Angebot.</div>'
			: '<div class="rk-ak__err" role="alert">' . esc_html( rk_ankauf_error_text( sanitize_key( wp_unslash( $_GET['ankauf'] ) ) ) ) . '</div>';
	}
	$action = esc_url( admin_url( 'admin-post.php' ) );
	$priv   = esc_url( get_privacy_policy_url() );
	ob_start();
	?>
<div class="rk-ak" id="formular">
<?php echo $msg; // phpcs:ignore ?>
<form class="rk-ak__form" method="post" action="<?php echo $action; // phpcs:ignore ?>" enctype="multipart/form-data">
	<input type="hidden" name="action" value="rk_ankauf">
	<?php wp_nonce_field( 'rk_ankauf', 'rk_ak_nonce' ); ?>
	<div class="rk-ak__grid">
		<label>Name *<input type="text" name="ak_name" required maxlength="80" autocomplete="name"></label>
		<label>E-Mail *<input type="email" name="ak_email" required maxlength="120" autocomplete="email"></label>
		<label>Instagram (optional)<input type="text" name="ak_insta" maxlength="60" placeholder="@deinname"></label>
		<label>Was möchtest du verkaufen?
			<select name="ak_art">
				<option>Einzelne Karten</option>
				<option>Eine ganze Sammlung</option>
				<option>Graded Karten (PSA, BGS, SGC)</option>
				<option>Pokémon oder andere TCG</option>
			</select>
		</label>
		<label>Ungefähre Anzahl Karten<input type="text" name="ak_anzahl" maxlength="40" placeholder="z. B. 15"></label>
		<label>Bevorzugte Auszahlung
			<select name="ak_zahlung">
				<option>PayPal</option>
				<option>Überweisung</option>
			</select>
		</label>
	</div>
	<label>Deine Wunschpreisvorstellung *
		<input type="text" name="ak_preis" required maxlength="200" placeholder="z. B. 60 € für alles oder 5 € pro Karte">
	</label>
	<label>Infos zu den Karten (optional)
		<textarea name="ak_text" rows="5" maxlength="3000" placeholder="Spieler, Set, Nummer, Zustand, Besonderheiten."></textarea>
	</label>
	<label class="rk-ak__file">Fotos von Vorder und Rückseite * <small>JPG, PNG oder WEBP, bis zu 20 Bilder, je max. 8 MB</small>
		<input type="file" name="ak_fotos[]" accept="image/jpeg,image/png,image/webp" multiple required>
	</label>
	<label class="rk-ak__file">Preisliste (optional) <small>PDF, CSV, XLSX oder Bild, max. 8 MB</small>
		<input type="file" name="ak_liste" accept=".pdf,.csv,.xlsx,image/jpeg,image/png,image/webp">
	</label>
	<input type="text" name="ak_web" class="rk-ak__hp" tabindex="-1" autocomplete="off" aria-hidden="true">
	<label class="rk-ak__c"><input type="checkbox" name="ak_ok" value="1" required><span>Ich bin einverstanden, dass Rookie Business meine Angaben und Fotos nutzt, um mir ein Angebot zu machen. Die Fotos werden nur per E-Mail an uns übermittelt und nicht auf der Website gespeichert. Mehr in der <a href="<?php echo $priv; // phpcs:ignore ?>">Datenschutzerklärung</a>.</span></label>
	<button type="submit">Unverbindliches Angebot anfragen</button>
</form>
</div>
	<?php
	return ob_get_clean();
}

function rk_ankauf_error_text( $code ) {
	$t = array(
		'fehlt'   => 'Bitte gib Name, E-Mail, deine Wunschpreisvorstellung und mindestens ein Foto an und bestätige die Einwilligung.',
		'mail'    => 'Bitte gib eine gültige E-Mail Adresse an.',
		'fotos'   => 'Bitte lade nur JPG, PNG oder WEBP Bilder hoch, höchstens 20 Stück und je bis 8 MB.',
		'liste'   => 'Die Preisliste muss PDF, CSV, XLSX oder ein Bild sein und darf höchstens 8 MB groß sein.',
		'zuviel'  => 'Zu viele Anfragen in kurzer Zeit. Bitte versuch es später noch einmal.',
		'senden'  => 'Die Anfrage konnte nicht verschickt werden. Bitte schreib uns direkt an shop@rookie-business.de.',
	);
	return isset( $t[ $code ] ) ? $t[ $code ] : 'Etwas ist schiefgelaufen. Bitte versuch es noch einmal.';
}

add_action( 'admin_post_nopriv_rk_ankauf', 'rk_ankauf_handle' );
add_action( 'admin_post_rk_ankauf', 'rk_ankauf_handle' );
function rk_ankauf_handle() {
	$back = wp_get_referer() ? wp_get_referer() : home_url( '/karten-verkaufen/' );
	$back = remove_query_arg( 'ankauf', $back );
	$go   = function ( $code ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'ankauf', $code, $back ) . '#formular' );
		exit;
	};
	if ( ! isset( $_POST['rk_ak_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rk_ak_nonce'] ) ), 'rk_ankauf' ) ) {
		$go( 'fehler' );
	}
	if ( ! empty( $_POST['ak_web'] ) ) {
		$go( 'ok' ); // Honeypot: Bots bekommen ein Ok, es passiert nichts.
	}
	$ip_key = 'rk_ak_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		$go( 'zuviel' );
	}
	$name  = sanitize_text_field( wp_unslash( $_POST['ak_name'] ?? '' ) );
	$email = sanitize_email( wp_unslash( $_POST['ak_email'] ?? '' ) );
	$preis = sanitize_text_field( wp_unslash( $_POST['ak_preis'] ?? '' ) );
	if ( '' === $name || '' === $preis || empty( $_POST['ak_ok'] ) || empty( $_FILES['ak_fotos']['name'][0] ) ) {
		$go( 'fehlt' );
	}
	if ( ! is_email( $email ) ) {
		$go( 'mail' );
	}
	$insta  = sanitize_text_field( wp_unslash( $_POST['ak_insta'] ?? '' ) );
	$art    = sanitize_text_field( wp_unslash( $_POST['ak_art'] ?? '' ) );
	$anzahl = sanitize_text_field( wp_unslash( $_POST['ak_anzahl'] ?? '' ) );
	$zahl   = sanitize_text_field( wp_unslash( $_POST['ak_zahlung'] ?? '' ) );
	$text   = sanitize_textarea_field( wp_unslash( $_POST['ak_text'] ?? '' ) );

	$max   = 8 * 1024 * 1024;
	$img   = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
	$tmp   = trailingslashit( get_temp_dir() ) . 'rk-ankauf-' . wp_generate_password( 12, false );
	wp_mkdir_p( $tmp );
	$files = array();
	$f     = $_FILES['ak_fotos']; // phpcs:ignore
	$n     = count( (array) $f['name'] );
	if ( $n > 20 ) {
		$go( 'fotos' );
	}
	for ( $i = 0; $i < $n; $i++ ) {
		if ( UPLOAD_ERR_OK !== (int) $f['error'][ $i ] || (int) $f['size'][ $i ] > $max ) {
			$go( 'fotos' );
		}
		$chk = wp_check_filetype_and_ext( $f['tmp_name'][ $i ], $f['name'][ $i ], $img );
		if ( empty( $chk['ext'] ) || ! @getimagesize( $f['tmp_name'][ $i ] ) ) { // phpcs:ignore
			$go( 'fotos' );
		}
		$dest = $tmp . '/foto-' . ( $i + 1 ) . '.' . $chk['ext'];
		if ( ! move_uploaded_file( $f['tmp_name'][ $i ], $dest ) ) {
			$go( 'fotos' );
		}
		$files[] = $dest;
	}
	if ( ! empty( $_FILES['ak_liste']['name'] ) ) {
		$l     = $_FILES['ak_liste']; // phpcs:ignore
		$types = $img + array( 'pdf' => 'application/pdf', 'csv' => 'text/csv', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		$chk   = wp_check_filetype_and_ext( $l['tmp_name'], $l['name'], $types );
		if ( UPLOAD_ERR_OK !== (int) $l['error'] || (int) $l['size'] > $max || empty( $chk['ext'] ) ) {
			$go( 'liste' );
		}
		$dest = $tmp . '/preisliste.' . $chk['ext'];
		if ( move_uploaded_file( $l['tmp_name'], $dest ) ) {
			$files[] = $dest;
		}
	}
	$body  = "Neue Ankauf Anfrage über rookie-business.de\n\n";
	$body .= "Name: $name\nE-Mail: $email\nInstagram: " . ( $insta ? $insta : '-' ) . "\nArt: $art\nAnzahl: " . ( $anzahl ? $anzahl : '-' ) . "\nAuszahlung: $zahl\n\n";
	$body .= "Wunschpreis: $preis\n\n";
	$body .= "Infos zu den Karten:\n" . ( $text ? $text : '-' ) . "\n\nAnhänge: " . count( $files ) . " Dateien\n";
	$ok = wp_mail( 'shop@rookie-business.de', 'Ankauf Anfrage von ' . $name, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ), $files );
	foreach ( $files as $p ) {
		@unlink( $p ); // phpcs:ignore
	}
	@rmdir( $tmp ); // phpcs:ignore
	if ( ! $ok ) {
		$go( 'senden' );
	}
	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );
	wp_mail(
		$email,
		'Deine Ankauf Anfrage bei Rookie Business',
		"Hallo $name,\n\ndanke für deine Anfrage. Wir haben deine Fotos bekommen und schauen uns die Karten an. Du bekommst von uns per E-Mail ein unverbindliches Angebot. Ob du es annimmst, entscheidest du.\n\nViele Grüße\nMarcel von Rookie Business\nshop@rookie-business.de",
		array( 'Reply-To: Rookie Business <shop@rookie-business.de>' )
	);
	$go( 'ok' );
}

/* Kundenkonto: Link im Header neben dem Warenkorb. */
add_filter( 'render_block', function ( $content, $block ) {
	if ( empty( $block['blockName'] ) || 'woocommerce/mini-cart' !== $block['blockName'] || ! function_exists( 'wc_get_page_permalink' ) ) {
		return $content;
	}
	$label = is_user_logged_in() ? 'Mein Konto' : 'Anmelden';
	$icon  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>';
	return '<a class="rk-acc" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" aria-label="' . esc_attr( $label ) . '">' . $icon . '<span class="rk-acc__t">' . esc_html( $label ) . '</span></a>' . $content;
}, 10, 2 );


// Produktseite: Zusätzliche Informationen und Rezensionen als eingeklappte Felder (27.11.2026).
add_filter( 'render_block', function ( $content, $block ) {
	if ( 'woocommerce/product-details' !== ( $block['blockName'] ?? '' ) || ! is_product() ) {
		return $content;
	}
	$tabs = apply_filters( 'woocommerce_product_tabs', array() );
	if ( ! $tabs ) {
		return $content;
	}
	$out = '<div class="rk-acd">';
	foreach ( $tabs as $key => $tab ) {
		if ( empty( $tab['callback'] ) || ! is_callable( $tab['callback'] ) ) {
			continue;
		}
		ob_start();
		call_user_func( $tab['callback'], $key, $tab );
		$inner = ob_get_clean();
		if ( '' === trim( wp_strip_all_tags( $inner, true ) ) && false === strpos( $inner, '<form' ) ) {
			continue;
		}
		$title = wp_strip_all_tags( apply_filters( 'woocommerce_product_' . $key . '_tab_title', $tab['title'], $key ) );
		$out  .= '<details class="rk-acd__item" id="tab-' . esc_attr( $key ) . '"><summary>' . esc_html( $title ) . '</summary><div class="rk-acd__in">' . $inner . '</div></details>';
	}
	$out .= '</div>';
	$start = strpos( $content, '<div class="woocommerce-tabs' );
	$end   = false === $start ? false : strpos( $content, '<style>.rk-rel{', $start );
	if ( false === $end && false !== $start ) {
		$end = strpos( $content, '<section class="rk-rel"', $start );
	}
	if ( false === $start ) {
		return $content;
	}
	if ( false === $end ) {
		$end = strrpos( $content, '</div>' ); // nur der äußere Block bleibt
	}
	return substr( $content, 0, $start ) . $out . substr( $content, $end );
}, 20, 2 );

// Mengenrabatt beendet (27.11.2026): alten Satz aus gespeicherten Produkttexten ausblenden.
add_filter( 'the_content', function ( $c ) {
	if ( false === strpos( $c, 'Mengenrabatt' ) || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $c;
	}
	return preg_replace( '/\s*[^.<>]*Mengenrabatt[^.<>]*\./u', '', $c );
}, 30 );

// v2.2 Dunkel: neue Elemente (Konto, Karten verkaufen, Ankauf) im dunklen Look (26.11.2026).
add_action( 'wp_head', function () {
	echo '<style id="rk-dunkel">' . <<<'RK_DARK'
/* v2.2 Dunkel (26.11.2026): Konto Link, Karten verkaufen, Ankauf Formular im dunklen Look */
.rk-acc{display:inline-flex;align-items:center;gap:8px;color:var(--rk-paper,#fff)!important;text-decoration:none;font-family:var(--rk-mono);font-size:var(--rk-small,13px);letter-spacing:.08em;text-transform:uppercase}
.rk-acc svg{width:18px;height:18px}
.rk-acc:hover{color:var(--rk-red)!important}
.rk-nav a,.rk-acc,.rk-header__right a{white-space:nowrap}
.rk-nav{gap:clamp(16px,1.7vw,30px)!important}
@media(max-width:1300px){.rk-acc__t{display:none}}
.rk-sell{max-width:1180px;margin:0 auto!important;padding:0 0 60px;box-sizing:border-box;color:var(--rk-paper)}
.rk-page .wp-block-post-content>.rk-sell{max-width:1180px!important;width:100%!important}
.rk-sell p{max-width:none}
.rk-sell__hero{padding:60px 0 34px;text-align:center}
.rk-sell__hero .rk-kicker{justify-content:center}
.rk-sell__h1{font-family:var(--rk-display)!important;font-weight:800;text-transform:uppercase;font-size:clamp(44px,7vw,96px)!important;line-height:.92!important;letter-spacing:0!important;margin:16px auto 20px;max-width:14ch}
.rk-sell__lead{font-size:clamp(16px,1.4vw,19px);color:#b5b5b5;max-width:62ch;margin:0 auto 28px!important;line-height:1.6}
.rk-sell__ctas{display:flex;gap:26px;justify-content:center;align-items:center;flex-wrap:wrap}
.rk-sell__trust{list-style:none;padding:0;margin:44px 0 0;display:grid;grid-template-columns:repeat(4,1fr);gap:0;border:1px solid var(--rk-line)}
.rk-sell__trust li{padding:20px 18px;text-align:left;background:var(--rk-panel)}
.rk-sell__trust li+li{border-left:1px solid var(--rk-line)}
.rk-sell__trust b{display:block;font-family:var(--rk-display);font-size:30px;font-weight:800;text-transform:uppercase;color:var(--rk-red);line-height:1}
.rk-sell__trust span{display:block;margin-top:6px;color:#b5b5b5;font-size:14px;line-height:1.45}
.rk-sell__sec{background:var(--rk-panel);border:1px solid var(--rk-line);padding:clamp(24px,4vw,46px);margin:22px 0}
.rk-sell h2,.rk-sell .rk-h2{margin-top:10px!important;margin-bottom:0!important}
.rk-sell h3{margin-top:14px!important}
.rk-sell__p{color:#b5b5b5;font-size:16px;line-height:1.7;max-width:70ch!important}
.rk-sell__steps{list-style:none;padding:0;margin:30px 0 0;display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.rk-sell__steps li{background:var(--rk-ink);border:1px solid var(--rk-line);border-top:3px solid var(--rk-red);padding:22px 20px}
.rk-sell__steps b{display:block;font-family:var(--rk-display);font-weight:800;font-size:44px;line-height:1;color:var(--rk-red)}
.rk-sell__steps h3{font-family:var(--rk-display)!important;font-weight:800;text-transform:uppercase;font-size:22px!important;line-height:1.1!important;margin:12px 0 8px}
.rk-sell__steps p{color:#b5b5b5;font-size:14.5px;line-height:1.6;margin:0}
.rk-sell__go{margin-top:28px;display:flex;justify-content:center}
.rk-sell__two{display:grid;grid-template-columns:1fr 1fr;gap:40px}
.rk-sell__chips{list-style:none;padding:0;margin:20px 0 0;display:flex;flex-wrap:wrap;gap:8px}
.rk-sell__chips li{border:1px solid var(--rk-line);padding:8px 14px;font-family:var(--rk-mono);font-size:12px;letter-spacing:.06em;text-transform:uppercase;background:var(--rk-ink)}
.rk-sell__promise{list-style:none;padding:0;margin:16px 0 0}
.rk-sell__promise li{padding:14px 0 14px 30px;border-bottom:1px solid var(--rk-line);color:#b5b5b5;line-height:1.6;position:relative}
.rk-sell__promise li::before{content:"✓";position:absolute;left:0;top:13px;color:var(--rk-red);font-weight:800}
.rk-sell__promise b{color:var(--rk-paper)}
.rk-sell__who{display:flex;gap:32px;align-items:center}
.rk-sell__img{flex:0 0 140px!important;width:140px!important;height:140px!important}
.rk-sell__faq{margin-top:20px}
.rk-sell .rk-rv{border:1px solid var(--rk-line)!important;margin:22px 0}
.rk-ak{margin-top:22px}
.rk-ak__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:4px 18px}
.rk-ak label{display:flex;flex-direction:column;gap:7px;font-family:var(--rk-mono);font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#b5b5b5;margin-bottom:14px}
.rk-ak small{text-transform:none;letter-spacing:0;font-family:var(--rk-body);color:var(--rk-muted)}
.rk-ak input[type=text],.rk-ak input[type=email],.rk-ak input[type=number],.rk-ak select,.rk-ak textarea{width:100%;box-sizing:border-box;background:var(--rk-ink)!important;color:var(--rk-paper)!important;border:1px solid #3a3a3a!important;border-radius:0!important;padding:13px 14px!important;font:15px var(--rk-body)!important;text-transform:none;letter-spacing:0}
.rk-ak textarea{min-height:130px}
.rk-ak input:focus,.rk-ak select:focus,.rk-ak textarea:focus{outline:none;border-color:var(--rk-red)!important}
.rk-ak__file input{padding:14px!important;background:var(--rk-ink)!important;border:1px dashed #4a4a4a!important;color:#b5b5b5;font:14px var(--rk-body);text-transform:none;letter-spacing:0}
.rk-ak .rk-ak__c{flex-direction:row;align-items:flex-start;gap:10px;font-family:var(--rk-body);font-size:14px;text-transform:none;letter-spacing:0;color:#b5b5b5;line-height:1.5}
.rk-ak .rk-ak__c input{margin-top:3px;width:18px;height:18px;flex:none;accent-color:var(--rk-red)}
.rk-ak button{margin-top:8px;background:var(--rk-red);color:#fff;border:0;border-radius:0;padding:17px 36px;font:500 var(--rk-small,14px) var(--rk-mono);letter-spacing:.08em;text-transform:uppercase;cursor:pointer}
.rk-ak button:hover{background:var(--rk-paper);color:var(--rk-ink)}
.rk-ak__hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;opacity:0}
.rk-ak__ok,.rk-ak__err{padding:16px 18px;margin:0 0 20px;font-size:15px;line-height:1.5;border:1px solid}
.rk-ak__ok{border-color:#2ecc71;background:rgba(46,204,113,.08)}
.rk-ak__err{border-color:var(--rk-red);background:rgba(255,59,48,.08)}
@media(max-width:900px){.rk-sell__trust,.rk-sell__steps{grid-template-columns:1fr 1fr}.rk-sell__trust li:nth-child(3){border-left:0}.rk-sell__trust li:nth-child(n+3){border-top:1px solid var(--rk-line)}.rk-sell__two{grid-template-columns:1fr}.rk-ak__grid{grid-template-columns:1fr}.rk-sell__who{flex-direction:column;align-items:flex-start}}
@media(max-width:560px){.rk-sell__trust,.rk-sell__steps{grid-template-columns:1fr}.rk-sell__trust li+li{border-left:0;border-top:1px solid var(--rk-line)}}
/* Footer: Das Prinzip, Über den Shop, Häufige Fragen (26.11.2026) */
.rk-fx{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:48px;margin:48px 0 0;padding:40px 0 0;border-top:1px solid var(--rk-line)}
.rk-fx__h{font-family:var(--rk-display);font-weight:800;text-transform:uppercase;font-size:26px;line-height:1.02;margin:12px 0 14px;color:var(--rk-paper)}
.rk-fx__p{color:var(--rk-muted);font-size:13px;line-height:1.7;margin:0 0 12px}
.rk-fx .rk-list li{padding:14px 0}
.rk-fx .rk-list p{max-width:none}
.rk-fx__links{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0 0}
.rk-fx__links a{border:1px solid var(--rk-line);padding:6px 10px;font-family:var(--rk-mono);font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:var(--rk-paper)!important;text-decoration:none}
.rk-fx__links a:hover{border-color:var(--rk-red);color:var(--rk-red)!important}
.rk-fx .rk-faq{margin-top:12px}
.rk-fx .rk-faq__item summary{font-size:14px}
.rk-fx .rk-faq__item p{font-size:13px}
@media(max-width:1000px){.rk-fx{grid-template-columns:1fr 1fr}.rk-fx__faq{grid-column:1/-1}}
@media(max-width:640px){.rk-fx{grid-template-columns:1fr;gap:34px}}
.rk-ticker a{color:inherit!important;text-decoration:none}
.rk-ticker a:hover{color:var(--rk-red)!important}
/* Produktseite: Hinweis unter dem Preis */
.rk-pv{margin:14px 0 20px;border:1px solid var(--rk-line);border-left:3px solid var(--rk-red);background:var(--rk-panel);padding:12px 14px;display:grid;gap:8px}
.rk-pv__row{display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:1.45;color:#cfcfcf}
.rk-pv__row b{color:var(--rk-red);font-weight:700}
.rk-pv__ic{flex:none;width:22px;height:22px;display:grid;place-items:center;border:1px solid rgba(255,59,48,.55);border-radius:50%;color:var(--rk-red);font:700 12px var(--rk-mono)}
.rk-pv__more{justify-self:start;margin-top:2px;font-family:var(--rk-mono);font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--rk-paper)!important;text-decoration:none;border-bottom:1px solid var(--rk-red);padding-bottom:3px}
.rk-pv__more:hover{color:var(--rk-red)!important}
/* Produktseite: eingeklappte Felder */
.rk-acd{max-width:1340px;margin:30px auto 10px;display:grid;gap:10px}
.rk-acd__item{border:1px solid var(--rk-line);background:var(--rk-panel)}
.rk-acd__item summary{cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px 20px;font-family:var(--rk-mono);font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:var(--rk-paper)}
.rk-acd__item summary::-webkit-details-marker{display:none}
.rk-acd__item summary::after{content:"+";color:var(--rk-red);font-size:22px;line-height:1;font-family:var(--rk-body)}
.rk-acd__item[open] summary::after{content:"−"}
.rk-acd__item summary:hover{color:var(--rk-red)}
.rk-acd__in{padding:0 20px 20px}
.rk-acd__in > h2:first-child,.rk-acd__in .woocommerce-Reviews-title{display:none}
/* Frisch gezogen Raster */
.rk-ng__chips{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 22px}
.rk-ng__chip{border:1px solid var(--rk-line);padding:7px 14px;font-family:var(--rk-mono);font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--rk-paper)!important;text-decoration:none;background:var(--rk-ink)}
.rk-ng__chip:hover{border-color:var(--rk-red);color:var(--rk-red)!important}
.rk-ng__grid{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px}
.rk-ng__item a{display:flex;flex-direction:column;height:100%;box-sizing:border-box;padding:14px 14px 16px;background:var(--rk-panel);border:1px solid var(--rk-line);color:var(--rk-paper)!important;text-decoration:none;transition:border-color .15s,transform .15s}
.rk-ng__item a:hover{border-color:var(--rk-red);transform:translateY(-2px)}
.rk-ng__img{display:flex;align-items:center;justify-content:center;height:200px;margin:0 0 14px}
.rk-ng__img img{height:200px!important;max-height:200px!important;width:auto!important;max-width:100%!important;object-fit:contain;display:block;margin:0!important;aspect-ratio:226/300}
.rk-ng__set{font-size:12px;line-height:1.4;color:var(--rk-muted);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.8em}
.rk-ng__name{margin-top:6px;font-size:14px;font-weight:600;line-height:1.3}
.rk-ng__name small{display:block;font-weight:400;font-size:12px;color:var(--rk-muted);margin-top:2px}
.rk-ng__rc{font-style:normal;font:700 10px var(--rk-mono);background:var(--rk-red);color:#fff;padding:1px 5px;margin-left:6px;vertical-align:2px}
.rk-ng__price{margin-top:auto;padding-top:10px;font-family:var(--rk-display);font-weight:800;font-size:20px;color:var(--rk-paper)}
.rk-ng__price del{opacity:.5;font-size:15px;margin-right:6px}
.rk-ng__price ins{text-decoration:none;color:var(--rk-red)}
@media(max-width:1150px){.rk-ng__grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:900px){.rk-ng__grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:640px){.rk-ng__grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.rk-ng__img{height:150px}.rk-ng__img img{height:150px!important;max-height:150px!important}.rk-ng__item a{padding:10px 10px 12px}}
/* Ligen: 5 dezente Kacheln (27.11.2026) */
.rk-lg{padding-top:90px!important;padding-bottom:28px!important}
.rk-lg > *{max-width:var(--wp--style--global--content-size,1180px);margin-left:auto!important;margin-right:auto!important}
.rk-lg + .rk-section{padding-top:56px!important}
.rk-lg__head{margin-bottom:18px}
.rk-lg__h{font-family:var(--rk-display)!important;font-weight:800;text-transform:uppercase;font-size:clamp(26px,3vw,36px)!important;line-height:1!important;margin:0!important}
.rk-lg__grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}
.rk-lg__tile{position:relative;overflow:hidden;aspect-ratio:63/88;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;background:var(--rk-panel);border:1px solid var(--rk-line);color:var(--rk-paper)!important;text-decoration:none;transition:border-color .2s}
.rk-lg__bg{position:absolute;inset:0;width:100%!important;height:100%!important;object-fit:cover;opacity:.1;transition:opacity .25s,transform .4s;pointer-events:none;margin:0!important;max-width:none!important}
.rk-lg__name{position:relative;font-family:var(--rk-display);font-weight:800;text-transform:uppercase;font-size:clamp(24px,2.6vw,36px);line-height:1;letter-spacing:.01em}
.rk-lg__sub{position:relative;font-family:var(--rk-mono);font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--rk-muted)}
.rk-lg__tile:hover{border-color:var(--rk-red)}
.rk-lg__tile:hover .rk-lg__bg{opacity:.22;transform:scale(1.03)}
.rk-lg__tile:hover .rk-lg__name{color:var(--rk-red)}
.rk-lg__more{margin-top:14px;text-align:right}
@media(max-width:760px){.rk-lg__grid{grid-template-columns:none;grid-auto-flow:column;grid-auto-columns:38vw;overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:6px}.rk-lg__tile{scroll-snap-align:start}}
RK_DARK
	. '</style>'; // phpcs:ignore
}, 999 );

// v2.3 SEO und Speed (27.11.2026)
// Startseite: das Hero Hintergrundbild ist das LCP Element, deshalb früh laden.
add_action( 'wp_head', function () {
	if ( is_front_page() ) {
		echo '<link rel="preload" as="image" href="' . esc_url( content_url( '/uploads/2026/11/rookie-laden-hero.jpg' ) ) . '" fetchpriority="high">' . "\n"; // phpcs:ignore
	}
}, 1 );
// Kommentar und Kategorie Feeds: keine Links mehr im Kopf, Produkt Feeds per 301 aufs Produkt (Google crawlt sonst /produkt/.../feed/).
add_action( 'wp', function () {
	remove_action( 'wp_head', 'feed_links_extra', 3 );
} );
add_action( 'template_redirect', function () {
	if ( is_feed() && is_singular() ) {
		wp_safe_redirect( get_permalink( get_queried_object_id() ), 301 );
		exit;
	}
	if ( is_feed() && ( is_tax( 'product_cat' ) || is_tax( 'product_tag' ) ) ) {
		wp_safe_redirect( get_term_link( get_queried_object() ), 301 );
		exit;
	}
}, 1 );
// Kategorie Überschrift (H1): "NFL Karten" statt nur "NFL".
add_filter( 'get_the_archive_title', function ( $title ) {
	if ( is_tax( 'product_cat' ) ) {
		$name = single_term_title( '', false );
		return ( false === stripos( $name, 'karten' ) ) ? $name . ' Karten' : $name;
	}
	return $title;
}, 20 );

// Produktseite: Link zur passenden Set Checkliste im Ratgeber (interne Verlinkung).
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! $product ) {
		return;
	}
	$id    = $product->get_id();
	$title = get_the_title( $id );
	$map   = array(
		array( 'topps-flagship-bundesliga', 'fussball', '2026-27 Topps Flagship Bundesliga', '/ratgeber/2026-27-topps-flagship-bundesliga-checkliste/', '2026-27 Topps Flagship Bundesliga Checkliste' ),
		array( 'topps-flagship', 'nfl', '2026 Topps Flagship', '/ratgeber/2026-topps-flagship-nfl-checkliste/', '2026 Topps Flagship NFL Checkliste' ),
	);
	foreach ( $map as $m ) {
		if ( false !== strpos( $title, $m[2] ) && has_term( $m[0], 'pa_serie', $id ) && has_term( $m[1], 'product_cat', $id ) ) {
			echo '<p class="rk-cl-hint" style="margin:6px 0 14px;font-size:14px;opacity:.85">Alle Karten des Sets mit Nummern: <a href="' . esc_url( home_url( $m[3] ) ) . '">' . esc_html( $m[4] ) . '</a></p>';
			return;
		}
	}
}, 13 );


// Pokémon Mega Sale Banner auf der Startseite zwischen "Such dir deine Liga aus" und "Frisch gezogen" (27.11.2026).
function rk_mega_sale_banner() {
	$term = get_term_by( 'slug', 'pokemon-mega-sale', 'product_cat' );
	if ( ! $term || ! $term->count ) {
		return '';
	}
	$link  = get_term_link( $term );
	$cards = get_transient( 'rk_mega_cards' );
	if ( false === $cards ) {
		$cards = array();
		$ids   = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'category' => array( 'pokemon-mega-sale' ), 'status' => 'publish', 'stock_status' => 'instock', 'limit' => 40, 'return' => 'ids', 'orderby' => 'rand' ) ) : array();
		foreach ( $ids as $id ) {
			if ( false === strpos( get_the_title( $id ), ' Holo ' ) ) {
				continue;
			}
			$src = get_the_post_thumbnail_url( $id, 'medium' );
			if ( $src ) {
				$cards[] = array( $src, get_the_title( $id ) );
			}
			if ( count( $cards ) >= 3 ) {
				break;
			}
		}
		set_transient( 'rk_mega_cards', $cards, 6 * HOUR_IN_SECONDS );
	}
	$imgs = '';
	foreach ( $cards as $i => $c ) {
		$imgs .= '<img class="rk-mega__card rk-mega__card--' . ( $i + 1 ) . '" src="' . esc_url( $c[0] ) . '" alt="' . esc_attr( $c[1] ) . '" width="300" height="419" loading="lazy">';
	}
	$css = '<style>'
	. '.rk-mega{max-width:var(--wp--style--global--content-size,1180px);margin:28px auto 0!important;padding:0 var(--wp--style--root--padding-right,20px);box-sizing:content-box}'
	. '.rk-mega__a{position:relative;isolation:isolate;overflow:hidden;display:grid;grid-template-columns:1.25fr 1fr;align-items:center;gap:20px;min-height:300px;padding:44px 48px;background:radial-gradient(120% 140% at 85% 50%,#ff4a3d 0%,#e3201b 45%,#a50d12 100%);color:#fff!important;text-decoration:none!important;box-shadow:0 20px 50px -20px rgba(215,25,31,.6)}'
	. '.rk-mega__a::before{content:"";position:absolute;inset:0;z-index:-1;background:repeating-linear-gradient(-55deg,rgba(255,255,255,.06) 0 2px,transparent 2px 22px)}'
	. '.rk-mega__a::after{content:"SALE";position:absolute;z-index:-1;right:-20px;bottom:-60px;font-family:var(--rk-display);font-weight:800;font-size:260px;line-height:1;letter-spacing:-.02em;color:rgba(0,0,0,.12)}'
	. '.rk-mega__kicker{display:inline-block;background:#111;color:#fff;font-family:var(--rk-mono);font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;padding:6px 12px;transform:rotate(-2deg);margin-bottom:16px}'
	. '.rk-mega__h{font-family:var(--rk-display)!important;font-weight:800;text-transform:uppercase;font-size:clamp(40px,6vw,84px)!important;line-height:.88!important;letter-spacing:-.01em;margin:0 0 14px!important;color:#fff!important;text-shadow:0 4px 0 rgba(0,0,0,.18)}'
	. '.rk-mega__sub{font-family:var(--rk-display);font-weight:800;text-transform:uppercase;font-size:clamp(18px,2.1vw,26px);line-height:1.1;margin:0 0 22px;color:#fff}'
	. '.rk-mega__row{display:flex;flex-wrap:wrap;align-items:center;gap:14px 22px}'
	. '.rk-mega__price{display:flex;align-items:baseline;gap:12px;background:#fff;color:#d7191f;padding:8px 18px 6px;transform:rotate(-3deg);box-shadow:0 10px 24px rgba(0,0,0,.25)}'
	. '.rk-mega__price del{font-family:var(--rk-mono);font-size:18px;color:#111;opacity:.55}'
	. '.rk-mega__price b{font-family:var(--rk-display);font-weight:800;font-size:clamp(38px,4.4vw,56px);line-height:1}'
	. '.rk-mega__price small{font-family:var(--rk-mono);font-size:12px;color:#111;text-transform:uppercase;letter-spacing:.08em}'
	. '.rk-mega__btn{display:inline-flex;align-items:center;gap:10px;background:#111;color:#fff;font-family:var(--rk-mono);font-size:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:15px 22px;border:2px solid #111;transition:background .2s,color .2s}'
	. '.rk-mega__a:hover .rk-mega__btn{background:#fff;color:#111}'
	. '.rk-mega__count{flex-basis:100%;font-family:var(--rk-mono);font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.85}'
	. '.rk-mega__fan{position:relative;height:280px}'
	. '.rk-mega__card{position:absolute;top:50%;left:50%;width:170px!important;height:auto!important;max-width:none!important;margin:0!important;border-radius:8px;box-shadow:0 18px 34px rgba(0,0,0,.45);transition:transform .35s ease}'
	. '.rk-mega__card--1{transform:translate(-125%,-50%) rotate(-13deg)}'
	. '.rk-mega__card--2{transform:translate(-50%,-56%) rotate(0deg);z-index:2}'
	. '.rk-mega__card--3{transform:translate(25%,-50%) rotate(13deg)}'
	. '.rk-mega__a:hover .rk-mega__card--1{transform:translate(-138%,-52%) rotate(-17deg)}'
	. '.rk-mega__a:hover .rk-mega__card--2{transform:translate(-50%,-62%) scale(1.04)}'
	. '.rk-mega__a:hover .rk-mega__card--3{transform:translate(38%,-52%) rotate(17deg)}'
	. '@media(max-width:900px){.rk-mega__a{grid-template-columns:1fr;padding:34px 26px 10px}.rk-mega__fan{height:230px}.rk-mega__card{width:140px!important}.rk-mega__a::after{font-size:170px}}'
	. '@media(max-width:480px){.rk-mega{padding:0 16px}.rk-mega__a{padding:28px 20px 6px}.rk-mega__fan{height:200px}.rk-mega__card{width:118px!important}}'
	. '.rk-mega + .rk-section{padding-top:56px!important}'
	. '@media(prefers-reduced-motion:reduce){.rk-mega__card{transition:none}}'
	. '</style>';
	return $css . '<section class="rk-mega" aria-label="Pokémon Mega Sale">'
		. '<a class="rk-mega__a" href="' . esc_url( $link ) . '">'
		. '<div class="rk-mega__txt">'
		. '<span class="rk-mega__kicker">Nur für kurze Zeit</span>'
		. '<h2 class="rk-mega__h">Pokémon<br>Mega Sale</h2>'
		. '<p class="rk-mega__sub">Alle Pokémon Karten nur 0,50 € pro Stück</p>'
		. '<div class="rk-mega__row">'
		. '<span class="rk-mega__price"><del>2,00 €</del><b>0,50 €</b><small>pro Karte</small></span>'
		. '<span class="rk-mega__btn">Zum Mega Sale <span aria-hidden="true">→</span></span>'
		. '<span class="rk-mega__count">' . (int) $term->count . ' japanische Karten · Holos, Reverse Holos und Rares · echte Scans</span>'
		. '</div></div>'
		. '<div class="rk-mega__fan" aria-hidden="true">' . $imgs . '</div>'
		. '</a></section>';
}
add_filter( 'render_block_core/html', function ( $content ) {
	if ( is_front_page() && false !== strpos( $content, 'rk-section rk-lg' ) && false === strpos( $content, 'rk-mega' ) ) {
		$content .= rk_mega_sale_banner() . rk_grail_banner();
	}
	return $content;
}, 20 );

/* ------------------------------------------------------------------
 * Holy Grails (28.11.2026): unter dem Pokémon Banner, die großen Namen.
 * Alle Karten ab 5 € (ohne Mega Sale), als "Slabs" mit Label.
 * Auswahl: täglich 6 zufällige aus den 30 teuersten, nach Preis sortiert.
 * Alle ansehen: /shop/?grails=1
 * ------------------------------------------------------------------ */
define( 'RK_GRAIL_MIN', 5 );

function rk_grail_query_args( $limit ) {
	return array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_price',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => '_price', 'value' => RK_GRAIL_MIN, 'compare' => '>=', 'type' => 'DECIMAL(10,2)' ),
			array( 'key' => '_stock_status', 'value' => 'instock' ),
		),
		'tax_query'      => array(
			array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array( 'pokemon-mega-sale' ), 'operator' => 'NOT IN' ),
			array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'exclude-from-catalog' ), 'operator' => 'NOT IN' ),
		),
	);
}

function rk_grail_data() {
	$data = get_transient( 'rk_grail_data' );
	if ( false === $data ) {
		$top = get_posts( rk_grail_query_args( 30 ) );
		$cnt = new WP_Query( array_merge( rk_grail_query_args( 1 ), array( 'no_found_rows' => false ) ) );
		shuffle( $top );
		$data = array( 'ids' => array_slice( $top, 0, 10 ), 'count' => (int) $cnt->found_posts );
		set_transient( 'rk_grail_data', $data, 12 * HOUR_IN_SECONDS );
	}
	return $data;
}

function rk_grail_banner() {
	$data = rk_grail_data();
	$ids  = array();
	foreach ( (array) $data['ids'] as $id ) {
		if ( 'publish' === get_post_status( $id ) && 'instock' === get_post_meta( $id, '_stock_status', true ) && has_post_thumbnail( $id ) ) {
			$ids[] = $id;
		}
	}
	if ( count( $ids ) < 3 ) {
		return '';
	}
	$ids = array_slice( $ids, 0, 6 );
	usort( $ids, function ( $a, $b ) {
		return (float) get_post_meta( $b, '_price', true ) <=> (float) get_post_meta( $a, '_price', true );
	} );
	$link  = add_query_arg( 'grails', '1', wc_get_page_permalink( 'shop' ) );
	$slabs = '';
	foreach ( $ids as $i => $id ) {
		$p      = wc_get_product( $id );
		$title  = $p->get_name();
		$player = trim( (string) $p->get_attribute( 'Spieler' ) );
		if ( false !== strpos( $title, '#' ) ) {
			if ( '' === $player ) {
				$player = trim( preg_replace( '/\s#.*$/u', '', $title ) );
			}
			$player = trim( preg_replace( '/\s+Rookie$/u', '', $player ) );
			$rest   = trim( preg_replace( '/^.*?#\S+\s*/u', '', $title ) );
		} else {
			// TCG Titel ohne #: Name bis zum ersten Wort mit Ziffer, Rest darunter.
			$w = preg_split( '/\s+/u', preg_replace( '/\s+-\s+/u', ' ', $title ) );
			$n = array();
			while ( $w && ! preg_match( '/\d/u', $w[0] ) && count( $n ) < 3 ) {
				$n[] = array_shift( $w );
			}
			$player = $n ? implode( ' ', $n ) : $title;
			$rest   = implode( ' ', $w );
		}
		$rest  = preg_replace( '/\s+(NFL|NBA|MLB|NHL)$/u', '', $rest );
		$num   = preg_match( '/#(\S+)/u', $title, $m ) ? '#' . $m[1] : '';
		$rest  = preg_replace( '/^#\S+\s*/u', '', $rest );
		$price = number_format( (float) $p->get_price(), 2, ',', '.' ) . ' €';
		$img   = get_the_post_thumbnail_url( $id, 'medium' );
		$slabs .= '<li class="rk-grail__it"><a class="rk-grail__slab" href="' . esc_url( get_permalink( $id ) ) . '">'
			. '<span class="rk-grail__label"><span class="rk-grail__ln"><b>' . esc_html( $player ) . '</b><i>' . esc_html( $price ) . '</i></span>'
			. '<span class="rk-grail__ls">' . esc_html( trim( $num . ' ' . wp_trim_words( $rest, 6, '…' ) ) ) . '</span></span>'
			. '<span class="rk-grail__win"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( $title ) . '" width="226" height="300" loading="' . ( $i < 3 ? 'eager' : 'lazy' ) . '" decoding="async"></span>'
			. '</a></li>';
	}
	$css = '<style>'
	. '.rk-grail{max-width:var(--wp--style--global--content-size,1180px);margin:28px auto 0!important;padding:0 var(--wp--style--root--padding-right,20px);box-sizing:content-box}'
	. '.rk-grail__box{position:relative;isolation:isolate;overflow:hidden;padding:44px 48px 40px;background:#0a0a0a;color:#fff;border:1px solid #2a2418;box-shadow:0 24px 60px -24px rgba(214,170,74,.35)}'
	. '.rk-grail__box::before{content:"";position:absolute;inset:0;z-index:-2;background:radial-gradient(70% 90% at 12% 0%,rgba(214,170,74,.22),transparent 60%),radial-gradient(60% 80% at 100% 100%,rgba(200,16,46,.16),transparent 60%)}'
	. '.rk-grail__box::after{content:"";position:absolute;inset:0;z-index:-1;opacity:.5;background:repeating-linear-gradient(90deg,rgba(255,255,255,.025) 0 1px,transparent 1px 64px)}'
	. '.rk-grail__head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:18px 28px;margin-bottom:30px}'
	. '.rk-grail__kick{display:inline-flex;align-items:center;gap:8px;font-family:var(--rk-mono);font-size:12px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#d6aa4a;margin-bottom:12px}'
	. '.rk-grail__kick::before{content:"";width:28px;height:1px;background:#d6aa4a}'
	. '.rk-grail__h{font-family:var(--rk-display)!important;font-weight:800;text-transform:uppercase;font-size:clamp(44px,6.4vw,92px)!important;line-height:.86!important;letter-spacing:-.01em;margin:0!important;background:linear-gradient(100deg,#f7e3a8 0%,#d6aa4a 35%,#fff3cf 50%,#b8862b 70%,#f2d488 100%);background-size:220% 100%;-webkit-background-clip:text;background-clip:text;color:transparent!important;animation:rkGold 7s ease-in-out infinite}'
	. '@keyframes rkGold{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}'
	. '.rk-grail__sub{max-width:520px;margin:14px 0 0;font-size:16px;line-height:1.5;color:#cfcfcf}'
	. '.rk-grail__cta{display:grid;gap:10px;justify-items:end;text-align:right}'
	. '.rk-grail__n{font-family:var(--rk-mono);font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#9a9a9a}'
	. '.rk-grail__n b{display:block;font-family:var(--rk-display);font-size:44px;line-height:1;letter-spacing:0;color:#fff}'
	. '.rk-grail__btn{display:inline-flex;align-items:center;gap:10px;background:#d6aa4a;color:#0a0a0a!important;font-family:var(--rk-mono);font-size:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:15px 22px;text-decoration:none!important;border:2px solid #d6aa4a;transition:background .2s,color .2s}'
	. '.rk-grail__btn:hover{background:transparent;color:#d6aa4a!important}'
	. '.rk-grail__row{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:16px}'
	. '.rk-grail__it{margin:0;min-width:0}'
	. '.rk-grail__slab{display:flex;flex-direction:column;height:100%;padding:7px;border-radius:10px;background:linear-gradient(160deg,rgba(255,255,255,.22),rgba(255,255,255,.06) 40%,rgba(255,255,255,.14));box-shadow:inset 0 0 0 1px rgba(255,255,255,.28),0 14px 30px rgba(0,0,0,.55);text-decoration:none!important;color:inherit;transition:transform .3s ease,box-shadow .3s ease}'
	. '.rk-grail__slab:hover{transform:translateY(-6px);box-shadow:inset 0 0 0 1px rgba(214,170,74,.8),0 22px 40px rgba(0,0,0,.6),0 0 0 1px rgba(214,170,74,.35)}'
	. '.rk-grail__label{display:grid;grid-template-columns:minmax(0,1fr);min-width:0;gap:3px;background:#fbfaf6;color:#111;border-radius:5px 5px 2px 2px;padding:7px 8px 6px;border-bottom:3px solid #c8102e;min-height:84px;align-content:start}'
	. '.rk-grail__ln{min-width:0;display:flex;flex-direction:column;align-items:flex-start;gap:3px}'
	. '.rk-grail__ln b{flex:1 1 auto;font-family:var(--rk-display);font-weight:800;text-transform:uppercase;font-size:14px;line-height:1.05;letter-spacing:.01em;overflow:hidden;white-space:normal;max-width:100%;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;min-width:0}'
	. '.rk-grail__ln i{flex:none;font-style:normal;font-family:var(--rk-display);font-weight:800;font-size:16px;line-height:1;color:#c8102e;white-space:nowrap}'
	. '.rk-grail__ls{font-family:var(--rk-mono);font-size:10px;line-height:1.25;max-height:2.5em;color:#555;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}'
	. '.rk-grail__win{position:relative;display:grid;place-items:center;margin-top:7px;padding:10px 8px;border-radius:3px 3px 6px 6px;background:#141414;aspect-ratio:3/4;overflow:hidden}'
	. '.rk-grail__win img{width:auto!important;height:100%!important;max-width:100%!important;object-fit:contain;margin:0!important;aspect-ratio:226/300;display:block}'
	. '.rk-grail__win::after{content:"";position:absolute;inset:0;background:linear-gradient(115deg,transparent 35%,rgba(255,255,255,.18) 50%,transparent 65%);transform:translateX(-120%);transition:transform .7s ease}'
	. '.rk-grail__slab:hover .rk-grail__win::after{transform:translateX(120%)}'
	. '@media(max-width:1080px){.rk-grail__row{grid-template-columns:repeat(3,minmax(0,1fr))}}'
	. '@media(max-width:700px){.rk-grail{padding:0 16px}.rk-grail__box{padding:30px 0 28px}.rk-grail__head{padding:0 20px;margin-bottom:22px}.rk-grail__cta{justify-items:start;text-align:left}.rk-grail__row{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:12px;padding:4px 20px 10px;scrollbar-width:none}.rk-grail__row::-webkit-scrollbar{display:none}.rk-grail__it{flex:0 0 46%;scroll-snap-align:start}}'
	. '.rk-grail + .rk-section{padding-top:56px!important}'
	. '@media(prefers-reduced-motion:reduce){.rk-grail__h{animation:none}.rk-grail__slab,.rk-grail__win::after{transition:none}}'
	. '</style>';
	return $css . '<section class="rk-grail" aria-labelledby="rk-grail-h"><div class="rk-grail__box">'
		. '<div class="rk-grail__head"><div>'
		. '<span class="rk-grail__kick">Die großen Namen · ab ' . RK_GRAIL_MIN . ' €</span>'
		. '<h2 class="rk-grail__h" id="rk-grail-h">Holy Grails</h2>'
		. '<p class="rk-grail__sub">Stars, Legenden, Rookies, Parallels und nummerierte Karten. Jede gibt es nur einmal, echt gescannt und sicher im Toploader verschickt.</p>'
		. '</div><div class="rk-grail__cta">'
		. '<span class="rk-grail__n"><b>' . (int) $data['count'] . '</b>Holy Grails im Shop</span>'
		. '<a class="rk-grail__btn" href="' . esc_url( $link ) . '">Alle Holy Grails <span aria-hidden="true">→</span></a>'
		. '</div></div>'
		. '<ul class="rk-grail__row">' . $slabs . '</ul>'
		. '</div></section>';
}
add_action( 'woocommerce_update_product', function () { delete_transient( 'rk_grail_data' ); } );

/* /shop/?grails=1: alle Karten ab 5 €, teuerste zuerst, mit eigener Überschrift. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || empty( $_GET['grails'] ) ) return;
	if ( ! ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) ) return;
	$mq   = (array) $q->get( 'meta_query' );
	$mq[] = array( 'key' => '_price', 'value' => RK_GRAIL_MIN, 'compare' => '>=', 'type' => 'DECIMAL(10,2)' );
	$q->set( 'meta_query', $mq );
	$tq   = (array) $q->get( 'tax_query' );
	$tq[] = array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array( 'pokemon-mega-sale' ), 'operator' => 'NOT IN' );
	$q->set( 'tax_query', $tq );
	if ( empty( $_GET['orderby'] ) ) {
		$q->set( 'meta_key', '_price' );
		$q->set( 'orderby', 'meta_value_num' );
		$q->set( 'order', 'DESC' );
	}
}, 30 );
add_filter( 'woocommerce_page_title', function ( $t ) {
	return ! empty( $_GET['grails'] ) ? 'Holy Grails' : $t;
} );
add_filter( 'get_the_archive_title', function ( $t ) {
	return ( ! empty( $_GET['grails'] ) && function_exists( 'is_shop' ) && is_shop() ) ? 'Holy Grails' : $t;
}, 30 );
add_action( 'woocommerce_archive_description', function () {
	if ( empty( $_GET['grails'] ) ) return;
	echo '<p class="rk-grail-intro" style="max-width:640px;margin:6px 0 18px;font-size:16px;opacity:.85">Die großen Namen im Shop: alle Karten ab ' . RK_GRAIL_MIN . ' €, die teuersten zuerst. Stars, Legenden, Rookies, Parallels und nummerierte Karten, jede ein Einzelstück mit echten Scans.</p>';
}, 5 );
