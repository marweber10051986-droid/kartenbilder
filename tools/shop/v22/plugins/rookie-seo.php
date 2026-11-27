<?php
/**
 * Plugin Name: Rookie Business SEO
 * Description: Schlanke SEO Grundlage: Titel, Meta Descriptions, Canonical, Open Graph, strukturierte Daten, noindex für Warenkorb und Kasse, Kategorietexte, Bild Titel und Alt Texte.
 * Version: 1.8.2
 * Author: Rookie Business
 */

defined( 'ABSPATH' ) || exit;

const RK_SEO_BRAND = 'Rookie Business';

/* ---------- Helpers ---------- */

function rk_seo_min_price() {
	$p = get_transient( 'rk_seo_min_price' );
	if ( false === $p ) {
		global $wpdb;
		$p = (float) $wpdb->get_var(
			"SELECT MIN(CAST(pm.meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta} pm
			 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_stock_status' AND s.meta_value = 'instock'
			 WHERE pm.meta_key = '_price' AND pm.meta_value > 0 AND p.post_type = 'product' AND p.post_status = 'publish'"
		);
		$p = $p > 0 ? $p : 2;
		set_transient( 'rk_seo_min_price', $p, 12 * HOUR_IN_SECONDS );
	}
	return (float) $p;
}

function rk_seo_euro( $v ) {
	return number_format( (float) $v, ( floor( $v ) == $v ? 0 : 2 ), ',', '.' ) . ' €';
}

function rk_seo_cut( $s, $max = 158 ) {
	$s = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $s ) ) );
	if ( mb_strlen( $s ) <= $max ) {
		return $s;
	}
	$s = mb_substr( $s, 0, $max - 1 );
	return rtrim( mb_substr( $s, 0, mb_strrpos( $s, ' ' ) ), ' ,.;:' ) . '…';
}

function rk_seo_cat_texts() {
	return array(
		'nfl'         => array(
			'title' => 'NFL Karten kaufen: Football Sammelkarten einzeln',
			'desc'  => 'NFL Sammelkarten einzeln und günstig kaufen ab 0,80 €: Rookies, Autos, Patches und Numbered. Echte Scans, Versand in Deutschland ab 1,10 €.',
			'h2'    => 'NFL Sammelkarten einzeln kaufen',
			'text'  => '<p>Hier findest du Football Karten als Einzelkarten statt Lotterie im Booster. Rookie Cards der neuen Draft Klasse, Autographs, Patch Karten, Numbered Parallels und Inserts von Topps, Panini Prizm, Donruss, Mosaic, Select und Chrome. Von Patrick Mahomes und Josh Allen über Cam Ward bis zu Legenden wie Eric Dickerson.</p><p>Jede NFL Karte ist mit echtem Scan von Vorder und Rückseite gelistet. So siehst du Zentrierung, Ecken und Kanten vor dem Kauf. Filtere oben nach Mannschaft, Position, Kartenart und Serie und finde gezielt die Karten für deine Team Sammlung oder dein nächstes Grading.</p>',
		),
		'nba'         => array(
			'title' => 'NBA Karten kaufen: Basketball Sammelkarten einzeln',
			'desc'  => 'NBA Sammelkarten einzeln kaufen: Rookies, Refractors, Autos und Inserts von Topps, Panini, Upper Deck. Echte Scans, Versand in Deutschland ab 1,10 €.',
			'h2'    => 'NBA Sammelkarten einzeln kaufen',
			'text'  => '<p>Basketball Karten als Singles: Rookie Cards, Topps Chrome Refractors, Panini Prizm und Donruss, Upper Deck Klassiker und Inserts aus vielen Jahrgängen. Ideal, um deine Lieblingsspieler und Teams gezielt zu sammeln, ohne ganze Boxen zu kaufen.</p><p>Jede Karte ist echt gescannt, Vorder und Rückseite. Filtere nach Mannschaft, Position, Kartenart und Serie.</p>',
		),
		'mlb'         => array(
			'title' => 'MLB Karten kaufen: Baseball Sammelkarten einzeln',
			'desc'  => 'MLB Baseball Karten einzeln kaufen: Topps Chrome, Stadium Club, Heritage, Rookies und Autos. Echte Scans von Vorder und Rückseite, Versand ab 1,10 €.',
			'h2'    => 'MLB Baseball Karten einzeln kaufen',
			'text'  => '<p>Baseball Sammelkarten von Topps, Topps Chrome, Stadium Club, Heritage und Bowman. Rookie Cards, Autographs, Parallels und Stars wie Shohei Ohtani. Jede Karte einzeln, mit echtem Scan.</p><p>Filtere nach Mannschaft, Position, Kartenart und Serie.</p>',
		),
		'fussball'    => array(
			'title' => 'Fußball Karten kaufen: Topps Bundesliga, Donruss und Panini',
			'desc'  => 'Fußball Sammelkarten einzeln kaufen: Topps Flagship Bundesliga 2026/27, Donruss Road to FIFA World Cup 26, Panini. Echte Scans, Versand ab 1,10 €.',
			'h2'    => 'Fußball Sammelkarten einzeln kaufen',
			'text'  => '<p>Soccer Cards als Einzelkarten: Topps Flagship Bundesliga 2026/27 mit Rookies, Base Karten und Inserts wie Stars of the Bundesliga, Chrome Icons, Global Gladiators oder Matchwinner. Dazu Donruss Road to FIFA World Cup 26, Topps Chrome, Panini und Inserts wie Net Marvels oder Zero Gravity. Stars wie Lionel Messi, Michael Olise oder Manuel Neuer, Rookies und Nationalspieler aus ganz Europa.</p><p>Jede Fußball Karte ist echt gescannt. Versand innerhalb Deutschlands ab 1,10 €, in die EU für 14,49 € mit Sendungsnummer.</p>',
		),
		'pokemon'     => array(
			'title' => 'Pokémon Karten einzeln kaufen: Singles, V, ex und Holos',
			'desc'  => 'Pokémon Einzelkarten kaufen: V, VMAX, ex, Holos und Promos aus aktuellen und älteren Sets. Deutsche und englische Karten, echte Scans, Versand ab 1,10 €.',
			'h2'    => 'Pokémon Einzelkarten kaufen',
			'text'  => '<p>Pokémon TCG Singles für Sammler und Spieler: V, VMAX, VSTAR, ex Karten, Holos, Reverse Holos und Promos, zum Beispiel aus Karmesin und Purpur 151 oder Pokémon GO. Jede Karte einzeln mit echtem Scan.</p><p>Ergänze gezielt dein Set oder dein Deck.</p>',
		),
		'tcg'         => array(
			'title' => 'TCG Karten einzeln kaufen: Pokémon und mehr',
			'desc'  => 'Trading Card Game Singles einzeln kaufen: Pokémon und weitere TCG Karten mit echtem Scan. Schneller Versand ab 1,10 €, EU Versand.',
			'h2'    => 'TCG Einzelkarten kaufen',
			'text'  => '<p>Trading Card Game Singles mit echtem Scan, allen voran Pokémon. Einzelkarten für Sammlung und Deck, ohne Booster Lotterie.</p>',
		),
		'sportkarten' => array(
			'title' => 'Sportkarten einzeln kaufen: NFL, NBA, MLB und Fußball',
			'desc'  => 'Sportkarten Singles in Deutschland kaufen: NFL, NBA, MLB und Fußball Trading Cards von Topps, Panini und Donruss. Echte Scans, Versand ab 1,10 €.',
			'h2'    => 'Sportkarten Singles kaufen',
			'text'  => '<p>Sports Trading Cards als Einzelkarten: Football, Basketball, Baseball und Fußball. Rookies, Autos, Patches, Numbered Parallels und Inserts von Topps, Panini, Donruss und Upper Deck. Jede Karte mit echtem Scan von Vorder und Rückseite.</p><p>Der Bestand wächst laufend. Ab 70 € Warenwert ist der Versand in Deutschland kostenlos.</p>',
		),
	);
}

function rk_seo_noindex() {
	if ( is_search() || is_404() ) {
		return true;
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		$hp = wc_get_product( get_queried_object_id() );
		if ( $hp && 'hidden' === $hp->get_catalog_visibility() ) {
			return true;
		}
	}
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
		return true;
	}
	foreach ( array_keys( $_GET ) as $k ) { // phpcs:ignore
		if ( 0 === strpos( $k, 'filter_' ) || in_array( $k, array( 'rk-feed', 'orderby', 'min_price', 'max_price', 'rk_team', 'rk_position', 'rk_kartenart', 'rk_serie', 'add-to-cart' ), true ) || 0 === strpos( $k, 'rk_' ) ) {
			return true;
		}
	}
	return false;
}

/* ---------- Titel ---------- */

add_filter( 'pre_get_document_title', function ( $t ) {
	if ( is_front_page() ) {
		return 'Sportkarten & Trading Cards einzeln kaufen | ' . RK_SEO_BRAND;
	}
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return 'Trading Cards Shop: Sportkarten und Pokémon Singles | ' . RK_SEO_BRAND;
	}
	if ( is_tax( 'product_cat' ) ) {
		$term  = get_queried_object();
		$texts = rk_seo_cat_texts();
		$base  = isset( $texts[ $term->slug ] ) ? $texts[ $term->slug ]['title'] : $term->name . ' Karten einzeln kaufen';
		$paged = (int) get_query_var( 'paged' );
		return $base . ( $paged > 1 ? ' | Seite ' . $paged : '' ) . ' | ' . RK_SEO_BRAND;
	}
	if ( is_singular( 'product' ) ) {
		return esc_html( rk_seo_product_title( get_queried_object_id() ) );
	}
	if ( is_page( 'karten-verkaufen' ) ) {
		return 'Sammelkarten verkaufen: Sportkarten und Pokémon ankaufen lassen | ' . RK_SEO_BRAND;
	}
	return $t;
}, 20 );

/* ---------- Meta ---------- */

function rk_seo_description() {
	$min = rk_seo_euro( rk_seo_min_price() );
	if ( is_tax( 'product_tag', 'wuehlkiste' ) ) {
		return 'Hunderte Base Cards für je 0,80 €: NFL, NBA, MLB und Fußball. Echte Scans, Versand in Deutschland 1,10 €. Stöbern und Sets komplettieren.';
	}
	if ( is_page( 'karten-verkaufen' ) ) {
		return 'Sammelkarten verkaufen: Schick uns Fotos deiner NFL, NBA, MLB, Fußball oder Pokémon Karten und bekomm ein faires, unverbindliches Angebot. Auszahlung per PayPal oder Überweisung.';
	}
	if ( is_page( 'suchauftrag' ) ) {
		return 'Du suchst eine bestimmte Karte? Schick uns Spieler, Set und Nummer, wir melden uns, sobald wir sie für dich haben.';
	}
	if ( is_front_page() ) {
		return 'Sportkarten Singles und Trading Cards einzeln kaufen ab ' . $min . ': NFL, NBA, MLB, Fußball, Pokémon. Echte Scans, ab 70 € versandkostenfrei in Deutschland.';
	}
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return 'Alle Sportkarten und Pokémon Singles im Überblick: Rookies, Autos, Patches und Holos ab ' . $min . '. Echte Scans, schneller Versand, ab 70 € versandkostenfrei.';
	}
	if ( is_tax( 'product_cat' ) ) {
		$term  = get_queried_object();
		$texts = rk_seo_cat_texts();
		return isset( $texts[ $term->slug ] ) ? $texts[ $term->slug ]['desc'] : $term->name . ' Karten einzeln kaufen bei ' . RK_SEO_BRAND . '. Echte Scans, Versand ab 1,10 €.';
	}
	if ( is_singular( 'product' ) ) {
		$p = wc_get_product( get_the_ID() );
		if ( ! $p ) {
			return '';
		}
		$price = $p->get_price() ? ' für ' . rk_seo_euro( $p->get_price() ) : '';
		$state = $p->is_in_stock() ? 'Sofort lieferbar.' : 'Aktuell ausverkauft.';
		return rk_seo_cut( $p->get_name() . ' als Einzelkarte kaufen' . $price . '. Echter Scan von Vorder und Rückseite. ' . $state . ' Versand ab 1,10 €, ab 70 € gratis, EU Versand.' );
	}
	if ( is_singular() ) {
		$post = get_post();
		return rk_seo_cut( has_excerpt( $post ) ? $post->post_excerpt : $post->post_content );
	}
	return '';
}

function rk_seo_canonical() {
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_tax() || ( function_exists( 'is_shop' ) && is_shop() ) ) {
		$url   = is_tax() ? get_term_link( get_queried_object() ) : get_permalink( wc_get_page_id( 'shop' ) );
		$paged = (int) get_query_var( 'paged' );
		return $paged > 1 ? trailingslashit( $url ) . 'page/' . $paged . '/' : $url;
	}
	return '';
}

function rk_seo_image() {
	if ( is_singular( 'product' ) && has_post_thumbnail() ) {
		return wp_get_attachment_image_url( get_post_thumbnail_id(), 'large' );
	}
	return get_stylesheet_directory_uri() . '/assets/img/eric-dickerson-topps-pink-foil-los-angeles-rams-nfl.webp';
}

add_action( 'wp_head', function () {
	if ( rk_seo_noindex() ) {
		echo '<meta name="robots" content="noindex, follow">' . "\n";
		return;
	}
	$desc = rk_seo_description();
	$can  = rk_seo_canonical();
	$url  = $can ? $can : ( is_singular() ? get_permalink() : home_url( add_query_arg( array() ) ) );
	$img  = rk_seo_image();
	$type = is_singular( 'product' ) ? 'product' : 'website';
	if ( $desc ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	if ( $can ) {
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $can ) );
	}
	printf( '<meta property="og:locale" content="de_DE"><meta property="og:site_name" content="%s"><meta property="og:type" content="%s"><meta property="og:title" content="%s"><meta property="og:url" content="%s">', esc_attr( RK_SEO_BRAND ), $type, esc_attr( wp_get_document_title() ), esc_url( $url ) );
	if ( $desc ) {
		printf( '<meta property="og:description" content="%s">', esc_attr( $desc ) );
	}
	printf( '<meta property="og:image" content="%s"><meta name="twitter:card" content="summary_large_image">' . "\n", esc_url( $img ) );

	if ( is_front_page() ) {
		$ld = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type'       => 'OnlineStore',
					'@id'         => home_url( '/#store' ),
					'name'        => RK_SEO_BRAND,
					'url'         => home_url( '/' ),
					'logo'        => get_stylesheet_directory_uri() . '/assets/img/marcel.webp',
					'email'       => 'shop@rookie-business.de',
					'description' => $desc,
					'address'     => array( '@type' => 'PostalAddress', 'addressLocality' => 'Brilon', 'postalCode' => '59929', 'addressCountry' => 'DE' ),
					'areaServed'  => 'EU',
					'sameAs'      => array( 'https://www.instagram.com/rookiebusiness.de/', 'https://www.tiktok.com/@rookie_business', 'https://www.ebay.de/str/rookiebusiness26' ),
				),
				array(
					'@type'           => 'WebSite',
					'@id'             => home_url( '/#website' ),
					'url'             => home_url( '/' ),
					'name'            => RK_SEO_BRAND,
					'inLanguage'      => 'de-DE',
					'publisher'       => array( '@id' => home_url( '/#store' ) ),
					'potentialAction' => array(
						'@type'       => 'SearchAction',
						'target'      => home_url( '/?s={search_term_string}&post_type=product' ),
						'query-input' => 'required name=search_term_string',
					),
				),
			),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}, 1 );

// WordPress eigene Canonical auf Seiten, die wir selbst setzen, nicht doppelt ausgeben.
add_action( 'wp', function () {
	if ( rk_seo_noindex() ) {
		remove_action( 'wp_head', 'rel_canonical' );
	}
	if ( is_front_page() ) {
		remove_action( 'wp_head', 'rel_canonical' );
	}
} );

/* ---------- Sitemap aufräumen ---------- */

add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}, 10, 2 );
add_filter( 'wp_sitemaps_taxonomies', function ( $t ) {
	unset( $t['category'], $t['post_tag'], $t['product_shipping_class'], $t['product_brand'] );
	return $t;
} );
add_filter( 'wp_sitemaps_post_types', function ( $t ) {
	if ( ! wp_count_posts( 'post' )->publish ) {
		unset( $t['post'] );
	}
	return $t;
} );
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $type ) {
	if ( 'page' === $type ) {
		$ex = array_filter( array( wc_get_page_id( 'cart' ), wc_get_page_id( 'checkout' ), wc_get_page_id( 'myaccount' ) ) );
		$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? $args['post__not_in'] : array(), $ex );
	}
	if ( 'product' === $type ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'exclude-from-catalog' ), 'operator' => 'NOT IN' ) ); // phpcs:ignore
	}
	return $args;
}, 10, 2 );

/* ---------- Kategorietexte unter dem Raster ---------- */

add_filter( 'render_block', function ( $html, $block ) {
	$wk = is_tax( 'product_tag', 'wuehlkiste' );
	if ( 'woocommerce/product-collection' !== $block['blockName'] || ! ( is_tax( 'product_cat' ) || $wk ) || (int) get_query_var( 'paged' ) > 1 ) {
		return $html;
	}
	static $done = false;
	if ( $done ) {
		return $html;
	}
	$done  = true;
	$term  = get_queried_object();
	$texts = rk_seo_cat_texts();
	$t     = rk_seo_cat_body( $term->slug );
	if ( ! $t ) {
		$t = isset( $texts[ $term->slug ] ) ? $texts[ $term->slug ] : null;
	}
	$intro = rk_seo_cat_intro( $term->slug );
	$pre   = $intro ? '<div class="rk-cat-intro alignwide"><p style="max-width:820px;margin:0 0 20px;line-height:1.55;opacity:.85">' . wp_kses_post( $intro ) . '</p></div>' : '';
	$post  = $t ? '<section class="rk-seo-text alignwide"><h2>' . esc_html( $t['h2'] ) . '</h2>' . wp_kses_post( $t['text'] ) . '</section>' : '';
	return $pre . $html . $post;
}, 10, 2 );

/* ---------- Bilder: Titel Attribut ---------- */

add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $att ) {
	if ( empty( $attr['alt'] ) ) {
		$parent = $att->post_parent ? get_the_title( $att->post_parent ) : '';
		$attr['alt'] = $parent ? $parent : get_the_title( $att );
	}
	if ( empty( $attr['title'] ) ) {
		$attr['title'] = $attr['alt'];
	}
	return $attr;
}, 20, 2 );

/* ---------- Einmalige Bild Aktualisierung per REST ---------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'rookie/v1', '/seo-images', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'manage_woocommerce' );
		},
		'callback'            => function ( WP_REST_Request $r ) {
			$page = max( 1, (int) $r->get_param( 'page' ) );
			$ids  = get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 100, 'paged' => $page, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
			$done = 0;
			foreach ( $ids as $pid ) {
				$p = wc_get_product( $pid );
				if ( ! $p ) {
					continue;
				}
				$name  = $p->get_name();
				$atts  = array_filter( array_merge( array( (int) $p->get_image_id() ), array_map( 'intval', $p->get_gallery_image_ids() ) ) );
				$i     = 0;
				foreach ( $atts as $aid ) {
					$side  = 0 === $i ? 'Vorderseite' : ( 1 === $i ? 'Rückseite' : 'Bild ' . ( $i + 1 ) );
					$label = $name . ( false === stripos( $name, 'karte' ) ? ' Sammelkarte ' : ' ' ) . $side;
					if ( get_post_field( 'post_title', $aid ) !== $label ) {
						wp_update_post( array( 'ID' => $aid, 'post_title' => $label ) );
					}
					update_post_meta( $aid, '_wp_attachment_image_alt', $label );
					$i++;
					$done++;
				}
			}
			return array( 'page' => $page, 'products' => count( $ids ), 'images' => $done, 'more' => 100 === count( $ids ) );
		},
	) );
} );

/* ---------- Gemeinsame Produktdaten ---------- */

function rk_seo_attr( $p, $name ) {
	$v = $p->get_attribute( $name );
	return $v ? trim( html_entity_decode( $v ) ) : '';
}

function rk_seo_brand( $p ) {
	$b = rk_seo_attr( $p, 'Hersteller' );
	if ( $b ) {
		return $b;
	}
	foreach ( array( 'Topps', 'Panini', 'Donruss', 'Upper Deck', 'Bowman', 'Leaf', 'Fleer', 'Pokémon', 'Pokemon' ) as $k ) {
		if ( false !== stripos( $p->get_name(), $k ) ) {
			return 'Pokemon' === $k ? 'Pokémon' : $k;
		}
	}
	return RK_SEO_BRAND;
}

function rk_seo_is_used( $p ) {
	return (bool) preg_match( '/gebraucht|bespielt|played|gemischt|poor|excellent|good/i', rk_seo_attr( $p, 'Zustand' ) );
}

/* ---------- Strukturierte Daten für Produkte ergänzen ---------- */

add_filter( 'woocommerce_structured_data_product', function ( $m, $p ) {
	$m['brand'] = array( '@type' => 'Brand', 'name' => rk_seo_brand( $p ) );
	if ( empty( $m['sku'] ) ) {
		$m['sku'] = $p->get_sku() ? $p->get_sku() : (string) $p->get_id();
	}
	$cond = rk_seo_is_used( $p ) ? 'https://schema.org/UsedCondition' : 'https://schema.org/NewCondition';
	if ( isset( $m['offers'][0] ) ) {
		$m['offers'][0]['itemCondition']   = $cond;
		$m['offers'][0]['shippingDetails'] = array(
			array(
				'@type'               => 'OfferShippingDetails',
				'shippingRate'        => array( '@type' => 'MonetaryAmount', 'value' => '1.10', 'currency' => 'EUR' ),
				'shippingDestination' => array( '@type' => 'DefinedRegion', 'addressCountry' => 'DE' ),
				'deliveryTime'        => array(
					'@type'        => 'ShippingDeliveryTime',
					'handlingTime' => array( '@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY' ),
					'transitTime'  => array( '@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 3, 'unitCode' => 'DAY' ),
				),
			),
		);
		$m['offers'][0]['hasMerchantReturnPolicy'] = array(
			'@type'                => 'MerchantReturnPolicy',
			'applicableCountry'    => 'DE',
			'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
			'merchantReturnDays'   => 14,
			'returnMethod'         => 'https://schema.org/ReturnByMail',
			'returnFees'           => 'https://schema.org/ReturnShippingFees',
			'returnShippingFeesAmount' => array( '@type' => 'MonetaryAmount', 'value' => '1.10', 'currency' => 'EUR' ),
		);
		$vf = $p->get_date_on_sale_from() ? $p->get_date_on_sale_from() : $p->get_date_created();
		if ( $vf ) {
			$m['offers'][0]['validFrom'] = $vf->date( 'c' );
		}
	}
	return $m;
}, 10, 2 );

/* ---------- Interne Links unter der Produktbeschreibung ---------- */

add_filter( 'the_content', function ( $c ) {
	if ( ! is_singular( 'product' ) || ! in_the_loop() || ! is_main_query() ) {
		return $c;
	}
	$p = wc_get_product( get_the_ID() );
	if ( ! $p ) {
		return $c;
	}
	$links = array();
	$s     = function ( $q ) {
		return esc_url( add_query_arg( array( 's' => $q, 'post_type' => 'product' ), home_url( '/' ) ) );
	};
	$player = rk_seo_attr( $p, 'Spieler' ) ?: rk_seo_attr( $p, 'Karte' );
	if ( $player ) {
		$links[] = '<a href="' . $s( $player ) . '">Mehr Karten von ' . esc_html( $player ) . '</a>';
	}
	foreach ( array( 'pa_team' => 'Mehr Karten von', 'pa_serie' => 'Mehr Karten aus' ) as $tax => $label ) {
		$terms = get_the_terms( $p->get_id(), $tax );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$cat   = wc_get_product_terms( $p->get_id(), 'product_cat', array( 'orderby' => 'parent', 'order' => 'DESC' ) );
			$base  = $cat ? get_term_link( $cat[0] ) : get_permalink( wc_get_page_id( 'shop' ) );
			$links[] = '<a href="' . esc_url( add_query_arg( 'filter_' . substr( $tax, 3 ), $terms[0]->slug, $base ) ) . '">' . $label . ' ' . esc_html( $terms[0]->name ) . '</a>';
		}
	}
	foreach ( wc_get_product_terms( $p->get_id(), 'product_cat' ) as $t ) {
		if ( $t->parent ) {
			$links[] = '<a href="' . esc_url( get_term_link( $t ) ) . '">Alle ' . esc_html( $t->name ) . ' Karten</a>';
		}
	}
	if ( ! $links ) {
		return $c;
	}
	return $c . '<nav class="rk-more" aria-label="Ähnliche Karten"><p class="rk-more__title">Weiter stöbern</p>' . implode( '', array_unique( $links ) ) . '</nav>';
}, 20 );

add_action( 'wp_head', function () {
	if ( is_singular( 'product' ) ) {
		echo '<style>.rk-more{display:flex;flex-wrap:wrap;gap:10px;margin:28px 0 0;padding-top:20px;border-top:1px solid #262626}.rk-more__title{width:100%;margin:0 0 4px;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#8a8a8a}.rk-more a{border:1px solid #333;padding:8px 14px;color:#fff;text-decoration:none;font-size:13px}.rk-more a:hover{border-color:#e10600;color:#e10600}</style>';
	}
}, 50 );

/* ---------- Google Shopping Feed: /?rk-feed=google ---------- */

add_action( 'template_redirect', function () {
	if ( empty( $_GET['rk-feed'] ) || 'google' !== $_GET['rk-feed'] ) { // phpcs:ignore
		return;
	}
	do_action( 'litespeed_control_set_nocache', 'Google Feed immer frisch' );
	$xml = get_transient( 'rk_google_feed' );
	if ( false === $xml ) {
		$xml = rk_seo_build_feed();
		set_transient( 'rk_google_feed', $xml, HOUR_IN_SECONDS );
	}
	nocache_headers();
	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo $xml; // phpcs:ignore
	exit;
} );

function rk_seo_eu_countries() {
	return array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK' );
}

function rk_seo_build_feed() {
	$e   = function ( $s ) {
		return htmlspecialchars( html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' ), ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	};
	$ids = wc_get_products( array( 'status' => 'publish', 'stock_status' => 'instock', 'limit' => -1, 'return' => 'ids' ) );
	$out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel><title>Rookie Business</title><link>' . esc_url( home_url( '/' ) ) . '</link><description>Sportkarten und Trading Cards Singles</description>' . "\n";
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p || ! $p->get_price() || ! $p->get_image_id() || 'hidden' === $p->get_catalog_visibility() ) {
			continue;
		}
		$cats  = wc_get_product_terms( $id, 'product_cat', array( 'orderby' => 'parent' ) );
		$ptype = implode( ' > ', wp_list_pluck( $cats, 'name' ) );
		$desc  = $p->get_description() ? $p->get_description() : $p->get_short_description();
		$out  .= '<item>';
		$out  .= '<g:id>' . $e( $p->get_sku() ? $p->get_sku() : $id ) . '</g:id>';
		$out  .= '<g:title>' . $e( mb_substr( html_entity_decode( $p->get_name() ), 0, 150 ) ) . '</g:title>';
		preg_match_all( '/\b((?:19|20)\d\d(?:-\d\d)?)\b/', html_entity_decode( $p->get_name() ), $rk_ym );
		if ( ! empty( $rk_ym[1] ) ) {
			$desc = 'Jahr: ' . end( $rk_ym[1] ) . '. ' . $desc;
		}
		$out  .= '<g:description>' . $e( mb_substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $desc ) ) ), 0, 4900 ) ) . '</g:description>';
		$out  .= '<g:link>' . $e( get_permalink( $id ) ) . '</g:link>';
		$out  .= '<g:image_link>' . $e( wp_get_attachment_image_url( $p->get_image_id(), 'full' ) ) . '</g:image_link>';
		foreach ( array_slice( $p->get_gallery_image_ids(), 0, 5 ) as $gid ) {
			$out .= '<g:additional_image_link>' . $e( wp_get_attachment_image_url( $gid, 'full' ) ) . '</g:additional_image_link>';
		}
		$out .= '<g:availability>in_stock</g:availability>';
		$out .= '<g:price>' . number_format( (float) $p->get_price(), 2, '.', '' ) . ' EUR</g:price>';
		$out .= '<g:condition>' . ( rk_seo_is_used( $p ) ? 'used' : 'new' ) . '</g:condition>';
		$out .= '<g:brand>' . $e( rk_seo_brand( $p ) ) . '</g:brand>';
		$out .= '<g:identifier_exists>no</g:identifier_exists>';
		$out .= '<g:product_type>' . $e( $ptype ) . '</g:product_type>';
		$out .= '<g:custom_label_0>' . $e( $cats ? end( $cats )->name : '' ) . '</g:custom_label_0>';
		$out .= '<g:shipping><g:country>DE</g:country><g:service>Brief</g:service><g:price>1.10 EUR</g:price></g:shipping>';
		foreach ( rk_seo_eu_countries() as $cc ) {
			$out .= '<g:shipping><g:country>' . $cc . '</g:country><g:service>Versand mit Sendungsnummer</g:service><g:price>14.49 EUR</g:price></g:shipping>';
		}
		$out .= '<g:shipping_weight>20 g</g:shipping_weight>';
		$out .= "</item>\n";
	}
	return $out . '</channel></rss>';
}

// Feed nach Produktänderungen neu bauen.
add_action( 'woocommerce_update_product', function () {
	delete_transient( 'rk_google_feed' );
} );
add_action( 'woocommerce_product_set_stock', function () {
	delete_transient( 'rk_google_feed' );
} );

/* ---------- Ratgeber ---------- */

add_action( 'init', function () {
	add_post_type_support( 'page', 'excerpt' );
} );

function rk_seo_is_ratgeber() {
	if ( ! is_page() ) {
		return false;
	}
	$hub = get_page_by_path( 'ratgeber' );
	return $hub && ( get_the_ID() === $hub->ID || (int) wp_get_post_parent_id( get_the_ID() ) === $hub->ID );
}

add_filter( 'pre_get_document_title', function ( $t ) {
	return rk_seo_is_ratgeber() ? get_the_title() . ' | ' . RK_SEO_BRAND : $t;
}, 30 );

add_action( 'wp_head', function () {
	if ( ! rk_seo_is_ratgeber() ) {
		return;
	}
	$post = get_post();
	$hub  = get_page_by_path( 'ratgeber' );
	$crumbs = array( array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => home_url( '/' ) ), array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Ratgeber', 'item' => get_permalink( $hub ) ) );
	$graph  = array();
	if ( $post->post_parent ) {
		$crumbs[] = array( '@type' => 'ListItem', 'position' => 3, 'name' => get_the_title(), 'item' => get_permalink() );
		$graph[]  = array(
			'@type'            => 'Article',
			'headline'         => get_the_title(),
			'description'      => $post->post_excerpt,
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'inLanguage'       => 'de-DE',
			'mainEntityOfPage' => get_permalink(),
			'image'            => rk_seo_image(),
			'author'           => array( '@type' => 'Person', 'name' => 'Marcel Weber', 'url' => home_url( '/' ) ),
			'publisher'        => array( '@type' => 'Organization', 'name' => RK_SEO_BRAND, 'url' => home_url( '/' ) ),
		);
	}
	$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $crumbs );
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 5 );

/* ---------- Ratgeber Seiten anlegen oder aktualisieren ---------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'rookie/v1', '/ratgeber-setup', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'edit_pages' );
		},
		'callback'            => function () {
			$data = json_decode( file_get_contents( __DIR__ . '/ratgeber.json' ), true );
			$save = function ( $item, $parent = 0 ) {
				$path     = $parent ? 'ratgeber/' . $item['slug'] : $item['slug'];
				$existing = get_page_by_path( $path );
				$args     = array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $item['title'],
					'post_name'    => $item['slug'],
					'post_excerpt' => $item['excerpt'],
					'post_content' => "<!-- wp:html -->\n" . trim( $item['body'] ) . "\n<!-- /wp:html -->",
					'post_parent'  => $parent,
				);
				if ( $existing ) {
					$args['ID'] = $existing->ID;
				}
				return wp_insert_post( wp_slash( $args ) );
			};
			$hub = $save( $data['hub'] );
			$ids = array();
			foreach ( $data['articles'] as $i => $a ) {
				$id = $save( $a, $hub );
				wp_update_post( array( 'ID' => $id, 'menu_order' => $i ) );
				$ids[] = get_permalink( $id );
			}
			return array( 'hub' => get_permalink( $hub ), 'articles' => $ids );
		},
	) );
} );

/* ---------- Google Kundenrezensionen: Opt in auf der Bestellbestätigung ---------- */

const RK_GCR_MERCHANT = 5903592651;

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
		return;
	}
	$order_id = absint( get_query_var( 'order-received' ) );
	$order    = $order_id ? wc_get_order( $order_id ) : false;
	$key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore
	if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) || $order->get_meta( '_rk_gcr_shown' ) || $order->has_status( array( 'failed', 'cancelled', 'pending' ) ) ) {
		return;
	}
	$country = $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country();
	$days    = 'DE' === $country ? 4 : 8;
	$date    = gmdate( 'Y-m-d', strtotime( '+' . $days . ' weekdays' ) );
	$data    = array(
		'merchant_id'             => RK_GCR_MERCHANT,
		'order_id'                => (string) $order->get_order_number(),
		'email'                   => $order->get_billing_email(),
		'delivery_country'        => $country,
		'estimated_delivery_date' => $date,
		'opt_in_style'            => 'CENTER_DIALOG',
	);
	$order->update_meta_data( '_rk_gcr_shown', 1 );
	$order->save();
	?>
	<script src="https://apis.google.com/js/platform.js?onload=rkRenderOptIn" async defer></script>
	<script>
	window.rkRenderOptIn = function () {
		window.gapi.load('surveyoptin', function () {
			window.gapi.surveyoptin.render(<?php echo wp_json_encode( $data ); ?>);
		});
	};
	</script>
	<?php
}, 50 );

/* ---------- Individuelle Produkttexte aus den Kartendaten ---------- */

function rk_seo_pos_name( $pos, $league ) {
	$map = array(
		'QB' => 'Quarterback', 'WR' => 'Wide Receiver', 'RB' => 'Running Back', 'TE' => 'Tight End', 'DL' => 'Defensive Line', 'DE' => 'Defensive End', 'DT' => 'Defensive Tackle', 'LB' => 'Linebacker', 'DB' => 'Defensive Back', 'CB' => 'Cornerback', 'S' => 'Safety', 'OL' => 'Offensive Line', 'OT' => 'Offensive Tackle', 'K' => 'Kicker', 'FB' => 'Fullback',
		'PG' => 'Point Guard', 'SG' => 'Shooting Guard', 'SF' => 'Small Forward', 'PF' => 'Power Forward', 'G' => 'Guard', 'F' => 'Forward',
		'OF' => 'Outfielder', 'SS' => 'Shortstop', '1B' => 'First Baseman', '2B' => 'Second Baseman', '3B' => 'Third Baseman', 'SP' => 'Starting Pitcher', 'RP' => 'Relief Pitcher', 'DH' => 'Designated Hitter',
	);
	if ( 'C' === $pos ) {
		return 'NBA' === $league ? 'Center' : ( 'MLB' === $league ? 'Catcher' : 'Center' );
	}
	if ( 'P' === $pos ) {
		return 'MLB' === $league ? 'Pitcher' : 'Punter';
	}
	return isset( $map[ $pos ] ) ? $map[ $pos ] . ' (' . $pos . ')' : $pos;
}

function rk_seo_condition_text( $z, $sports ) {
	$z = strtolower( $z );
	if ( false !== strpos( $z, 'stark' ) ) {
		return 'Ungegradet (Raw) mit deutlichen Gebrauchsspuren. Bitte schau dir die Scans genau an.';
	}
	if ( false !== strpos( $z, 'gebraucht' ) ) {
		return 'Ungegradet (Raw), gebraucht. Leichte Spuren an Ecken und Kanten sind möglich.';
	}
	if ( false !== strpos( $z, 'bespielt' ) ) {
		return 'Leicht bespielt. Kleine Spuren an Kanten, Ecken oder Oberfläche sind möglich.';
	}
	if ( false !== strpos( $z, 'near mint' ) ) {
		return 'Near Mint, also nahezu perfekt.';
	}
	return $sports ? 'Ungegradet (Raw).' : ( $z ? ucfirst( $z ) . '.' : '' );
}

function rk_seo_generate_description( $p ) {
	$a      = function ( $n ) use ( $p ) {
		return rk_seo_attr( $p, $n );
	};
	$e      = 'esc_html';
	$name   = html_entity_decode( $p->get_name() );
	$league = $a( 'Liga' );
	$v      = $p->get_id() % 3;
	$kart   = $a( 'Kartenart' );
	if ( false !== stripos( $kart, 'Konvolut' ) ) {
		return null;
	}
	$sports = $a( 'Spieler' ) && 'Pokémon' !== $league;
	$cat    = array(
		'NFL'     => array( 'NFL Sammelkarte', 'American Football', '/produktkategorie/sportkarten/nfl/', 'NFL Karten' ),
		'NBA'     => array( 'NBA Sammelkarte', 'Basketball', '/produktkategorie/sportkarten/nba/', 'NBA Karten' ),
		'MLB'     => array( 'MLB Sammelkarte', 'Baseball', '/produktkategorie/sportkarten/mlb/', 'MLB Baseball Karten' ),
		'Fußball' => array( 'Fußball Sammelkarte', 'Fußball', '/produktkategorie/sportkarten/fussball/', 'Fußball Sammelkarten' ),
	);
	$out = array();

	if ( $sports ) {
		$c       = isset( $cat[ $league ] ) ? $cat[ $league ] : array( 'Sportkarte', 'Sport', '/produktkategorie/sportkarten/', 'Sportkarten' );
		$player  = $a( 'Spieler' );
		$team    = $a( 'Team' );
		$set     = $a( 'Set' );
		$season  = $a( 'Saison' );
		$maker   = $a( 'Hersteller' );
		$no      = $a( 'Kartennummer' );
		$variant = $a( 'Variante' );
		$pos     = $a( 'Position' );
		$rookie  = 'Ja' === $a( 'Rookie Card' ) || preg_match( '/\bRookie\b/i', $name . ' ' . $a( 'Variante' ) );
		$auto    = 'Ja' === $a( 'Autogramm' ) || preg_match( '/\bauto\b/i', $name );
		$patch   = preg_match( '/patch|relic|memorabilia|jersey (swatch|card|relic)|game.used/i', str_ireplace( array( 'New Jersey', html_entity_decode( $a( 'Set' ) ), html_entity_decode( $a( 'Serie' ) ) ), '', html_entity_decode( $name ) . ' ' . $a( 'Variante' ) ) );
		$numb    = $a( 'Nummerierung' );
		if ( ! $numb && preg_match( '#\s/(\d{1,4})\b#', $name, $m ) ) {
			$numb = '/' . $m[1];
		}
		$is_var  = $variant && 'Base' !== $variant && 'Team Card' !== $variant;
		$label   = trim( $player . ( $rookie ? ' Rookie Card' : ' Karte' ) . ( $no ? ' #' . $no : '' ) );
		$of      = 'Fußball' === $league ? 'von ' : 'der ';

		$intros = array(
			'Du suchst eine <strong>' . $e( $label ) . '</strong>? Hier bekommst du genau diese Karte' . ( $team ? ' ' . $of . $e( $team ) : '' ) . ' aus <strong>' . $e( $set ) . '</strong> als Einzelkarte.',
			'<strong>' . $e( $label ) . '</strong>' . ( $team ? ', ' . $e( $team ) : '' ) . ', aus dem Set <strong>' . $e( $set ) . '</strong>. Eine originale ' . $e( $c[0] ) . ( $maker ? ' von ' . $e( $maker ) : '' ) . ', die du hier einzeln kaufst.',
			'Diese <strong>' . $e( $player ) . '</strong> Karte' . ( $no ? ' mit der Nummer #' . $e( $no ) : '' ) . ' stammt aus <strong>' . $e( $set ) . '</strong>' . ( $team ? ' und zeigt ihn im Trikot ' . $of . $e( $team ) : '' ) . '.',
		);
		$intro = $intros[ $v ];
		if ( $is_var ) {
			$intro .= ' Variante: <strong>' . $e( $variant ) . '</strong>' . ( $numb ? ', nummeriert ' . $e( $numb ) : '' ) . '.';
		} elseif ( $numb ) {
			$intro .= ' Die Karte ist nummeriert: ' . $e( $numb ) . '.';
		}
		$out[] = '<p>' . $intro . '</p>';

		$ctx = array();
		if ( $rookie ) {
			$ctx[] = 'Als Rookie Card stammt sie aus der ersten Saison von ' . $e( $player ) . ( $league && 'Fußball' !== $league ? ' in der ' . $e( $league ) : ' als Profi' ) . '. Rookie Cards sind die gefragtesten Karten eines Spielers, gerade für Sammler' . ( $team ? ' ' . $of . $e( $team ) : '' ) . '.';
		}
		if ( $auto ) {
			$ctx[] = 'Die Karte trägt ein Autogramm des Spielers.';
		}
		if ( $patch ) {
			$ctx[] = 'Sie enthält ein eingearbeitetes Stück Trikot oder Material (Patch oder Relic).';
		}
		if ( $numb ) {
			$ctx[] = 'Durch die Nummerierung ' . $e( $numb ) . ' ist die Auflage dieser Variante begrenzt.';
		} elseif ( $is_var && preg_match( '/refractor|prizm|foil|holo|laser|wave|pixel|chrome|border|glitter/i', $variant ) ) {
			$ctx[] = 'Die ' . $e( $variant ) . ' Version ist eine Parallel der Base Karte und wird seltener gezogen.';
		}
		if ( preg_match( '/^(19|200[0-9])/', $season ) ) {
			$ctx[] = 'Eine Karte aus der Saison ' . $e( $season ) . ', ideal für Sammler von Retro Karten.';
		}
		if ( 'Team Card' === $variant || 'Team' === $pos ) {
			$ctx[] = 'Eine Team Karte, perfekt für alle, die ihr Team komplett sammeln.';
		}
		if ( $ctx ) {
			$out[] = '<p>' . implode( ' ', $ctx ) . '</p>';
		}

		$rows = array(
			'Spieler'      => $player,
			'Team'         => $team,
			'Liga'         => $league,
			'Position'     => ( $pos && 'Team' !== $pos ) ? rk_seo_pos_name( $pos, $league ) : '',
			'Set'          => $set,
			'Saison'       => $season,
			'Hersteller'   => $maker,
			'Kartennummer' => $no ? '#' . $no : '',
			'Variante'     => $variant,
			'Nummerierung' => $numb,
			'Rookie Card'  => $rookie ? 'Ja' : '',
			'Autogramm'    => $auto ? 'Ja' : '',
		);
	} else {
		$c      = array( 'Pokémon Karte', 'Pokémon', '/produktkategorie/tcg/pokemon/', 'Pokémon Karten' );
		$card   = $a( 'Karte' ) ? $a( 'Karte' ) : $name;
		$no     = $a( 'Nummer' );
		$set    = $a( 'Set' );
		$year   = $a( 'Jahr' );
		$rar    = $a( 'Seltenheit' );
		$lang   = $a( 'Sprache' );
		if ( ! $lang ) {
			$lang = preg_match( '/\bdeutsch\b/i', $name ) ? 'Deutsch' : ( preg_match( '/\benglisch|english\b/i', $name ) ? 'Englisch' : '' );
		}
		$rev    = preg_match( '/reverse/i', $name );
		$intros = array(
			'Du suchst <strong>' . $e( $card ) . ( $no ? ' ' . $e( $no ) : '' ) . '</strong>? Hier bekommst du genau diese Pokémon Karte aus dem Set <strong>' . $e( $set ) . '</strong>' . ( $year ? ' (' . $e( $year ) . ')' : '' ) . ' als Single.',
			'<strong>' . $e( $card ) . ( $no ? ' ' . $e( $no ) : '' ) . '</strong> aus <strong>' . $e( $set ) . '</strong>' . ( $year ? ', erschienen ' . $e( $year ) : '' ) . '. Eine originale Pokémon Karte, die du hier einzeln kaufst.',
			'Diese Pokémon Karte <strong>' . $e( $card ) . '</strong>' . ( $no ? ' mit der Nummer ' . $e( $no ) : '' ) . ' stammt aus dem Set <strong>' . $e( $set ) . '</strong>' . ( $year ? ' (' . $e( $year ) . ')' : '' ) . '.',
		);
		$intro  = $intros[ $v ];
		if ( $rar ) {
			$intro .= ' Seltenheit: ' . $e( $rar ) . ( $rev ? ', Reverse Holo' : '' ) . '.';
		}
		if ( $lang ) {
			$intro .= ' Sprache: ' . $e( $lang ) . '.';
		}
		$out[] = '<p>' . $intro . '</p>';
		$ctx   = array();
		if ( 'Japanisch' === $lang ) {
			$ctx[] = 'Japanische Karten sind bei Sammlern wegen ihrer Druckqualität und eigener Sets beliebt. Mehr dazu im <a href="/ratgeber/japanische-pokemon-karten-sammeln/">Ratgeber zu japanischen Pokémon Karten</a>.';
		}
		if ( preg_match( '/illustration|secret|ultra|special|art rare|shiny|ssr|super rare|ex\b|\bv\b|vmax|vstar/i', $rar . ' ' . $card ) ) {
			$ctx[] = 'Eine der besonderen Karten des Sets, gefragt bei Sammlern und Spielern.';
		}
		if ( $ctx ) {
			$out[] = '<p>' . implode( ' ', $ctx ) . '</p>';
		}
		$rows = array(
			'Karte'      => $card,
			'Nummer'     => $no,
			'Set'        => $set,
			'Jahr'       => $year,
			'Seltenheit' => $rar . ( $rev ? ', Reverse Holo' : '' ),
			'Sprache'    => $lang,
		);
	}

	$rows['Zustand'] = $a( 'Zustand' );
	$li = '';
	foreach ( $rows as $k => $val ) {
		if ( '' !== trim( (string) $val ) ) {
			$li .= '<li><strong>' . $e( $k ) . ':</strong> ' . $e( $val ) . '</li>';
		}
	}
	$out[] = '<p><strong>Kartendetails</strong></p><ul>' . $li . '</ul>';

	$cond  = rk_seo_condition_text( $a( 'Zustand' ), $sports );
	$out[] = '<p><strong>Zustand:</strong> ' . $e( $cond ) . ' Die Bilder sind echte Scans genau dieser Karte, Vorder und Rückseite, keine Stockfotos. So prüfst du Ecken, Kanten, Oberfläche' . ( $sports ? ' und Zentrierung selbst und siehst, ob sich ein Grading bei PSA oder BGS für dich lohnt.' : ' und Zentrierung selbst.' ) . '</p>';
	$out[] = '<p><strong>Versand:</strong> Die Karte kommt in Penny Sleeve und Toploader im gepolsterten Umschlag und geht innerhalb von einem Werktag raus. In Deutschland zahlst du 1,10 € pro Bestellung, egal wie viele Karten du kaufst, ab 70 € Warenwert ist der Versand kostenlos. Versicherter Versand mit Sendungsverfolgung kostet in Deutschland 7,69 €, in die EU verschicken wir für 14,49 € mit Sendungsnummer.</p>';
	$out[] = '<p>Noch mehr Karten findest du bei unseren <a href="' . esc_url( $c[2] ) . '">' . $e( $c[3] ) . '</a>.</p>';

	if ( $sports ) {
		$short = $label . ( $team ? ', ' . $team : '' ) . ', ' . $set . ( $is_var ? ', ' . $variant : '' ) . ( $numb && false === strpos( $variant, '/' ) ? ' ' . $numb : '' ) . ': originale ' . $c[0] . ' als Einzelkarte, mit echten Scans von Vorder und Rückseite.';
	} else {
		$short = $card . ( $no ? ' ' . $no : '' ) . ', ' . $set . ( $rar ? ', ' . $rar : '' ) . ( $lang ? ', ' . $lang : '' ) . ': originale Pokémon Karte als Single, mit echten Scans von Vorder und Rückseite.';
	}
	return array( 'description' => implode( "\n", $out ), 'short' => '<p>' . esc_html( $short ) . '</p>' );
}

function rk_seo_apply_description( $id ) {
	$p = wc_get_product( $id );
	if ( ! $p ) {
		return false;
	}
	$gen = rk_seo_generate_description( $p );
	if ( ! $gen ) {
		return false;
	}
	if ( ! get_post_meta( $id, '_rk_desc_old', true ) ) {
		update_post_meta( $id, '_rk_desc_old', array( 'd' => $p->get_description(), 's' => $p->get_short_description() ) );
	}
	$p->set_description( $gen['description'] );
	$p->set_short_description( $gen['short'] );
	$p->update_meta_data( '_rk_desc_v2', 1 );
	$p->save();
	return true;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'rookie/v1', '/descriptions', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'manage_woocommerce' );
		},
		'callback'            => function ( WP_REST_Request $r ) {
			$dry  = (bool) $r->get_param( 'dry' );
			$ids  = array_filter( array_map( 'intval', (array) $r->get_param( 'ids' ) ) );
			$page = max( 1, (int) $r->get_param( 'page' ) );
			if ( ! $ids ) {
				$ids = get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 50, 'paged' => $page, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
			}
			$res = array();
			$n   = 0;
			foreach ( $ids as $id ) {
				if ( $dry ) {
					$gen   = rk_seo_generate_description( wc_get_product( $id ) );
					$res[] = array( 'id' => $id, 'name' => get_the_title( $id ), 'gen' => $gen );
				} elseif ( rk_seo_apply_description( $id ) ) {
					$n++;
				}
			}
			return $dry ? $res : array( 'page' => $page, 'count' => count( $ids ), 'updated' => $n, 'more' => 50 === count( $ids ) );
		},
	) );
	register_rest_route( 'rookie/v1', '/descriptions-restore', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'manage_woocommerce' );
		},
		'callback'            => function () {
			$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_rk_desc_old' ) );
			foreach ( $ids as $id ) {
				$old = get_post_meta( $id, '_rk_desc_old', true );
				$p   = wc_get_product( $id );
				$p->set_description( $old['d'] );
				$p->set_short_description( $old['s'] );
				$p->delete_meta_data( '_rk_desc_v2' );
				$p->save();
			}
			return array( 'restored' => count( $ids ) );
		},
	) );
} );

// Upload per REST (Shop Upload): Text sofort beim Anlegen schreiben, Attribute sind dann schon gespeichert.
add_action( 'woocommerce_rest_insert_product_object', function ( $p, $request, $creating ) {
	if ( $creating && $p && ! get_post_meta( $p->get_id(), '_rk_desc_v2', true ) ) {
		rk_seo_apply_description( $p->get_id() );
	}
}, 20, 3 );

// Fallback für alles andere: Text kurz nach dem Anlegen.
add_action( 'woocommerce_new_product', function ( $id ) {
	wp_schedule_single_event( time() + 120, 'rk_seo_gen_desc', array( (int) $id ) );
} );
add_action( 'rk_seo_gen_desc', function ( $id ) {
	if ( ! get_post_meta( $id, '_rk_desc_v2', true ) ) {
		rk_seo_apply_description( $id );
	}
} );


/* EU Versand in den strukturierten Daten (14,49 €, 4 bis 10 Werktage), passend zum Merchant Center */
add_filter( 'woocommerce_structured_data_product', function ( $m ) {
	if ( empty( $m['offers'][0] ) || ! function_exists( 'rk_seo_eu_countries' ) ) {
		return $m;
	}
	$m['offers'][0]['shippingDetails'][] = array(
		'@type'               => 'OfferShippingDetails',
		'shippingRate'        => array( '@type' => 'MonetaryAmount', 'value' => '14.49', 'currency' => 'EUR' ),
		'shippingDestination' => array( '@type' => 'DefinedRegion', 'addressCountry' => rk_seo_eu_countries() ),
		'deliveryTime'        => array(
			'@type'        => 'ShippingDeliveryTime',
			'handlingTime' => array( '@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY' ),
			'transitTime'  => array( '@type' => 'QuantitativeValue', 'minValue' => 4, 'maxValue' => 10, 'unitCode' => 'DAY' ),
		),
	);
	return $m;
}, 20 );

/* ---------- 1.5.0: Wühlkiste, Autor Archiv, Weiterleitungen ---------- */

// Aus den Schlagworten nur die Wühlkiste in die Sitemap.
add_filter( 'wp_sitemaps_taxonomies_query_args', function ( $args, $tax ) {
	if ( 'product_tag' === $tax ) {
		$args['slug'] = array( 'wuehlkiste' );
	}
	return $args;
}, 10, 2 );

add_filter( 'pre_get_document_title', function ( $t ) {
	if ( is_tax( 'product_tag', 'wuehlkiste' ) ) {
		$paged = (int) get_query_var( 'paged' );
		return 'Sportkarten für 0,80 €: Base Cards einzeln kaufen' . ( $paged > 1 ? ' | Seite ' . $paged : '' ) . ' | ' . RK_SEO_BRAND;
	}
	return $t;
}, 21 );

// Autor Archive bringen nichts und verraten den Login Namen: auf die Startseite.
// Zusammengelegte Produkte: alte Adresse per 301 auf das verbleibende Produkt.
add_action( 'template_redirect', function () {
	if ( is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
	if ( is_404() ) {
		$map  = array(
			'kyrie-irving-3-dallas-mavericks-2025-26-topps-chrome-update-nba-2' => 'kyrie-irving-3-dallas-mavericks-2025-26-topps-chrome-update-nba',
		);
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH ), '/' ); // phpcs:ignore
		$slug = basename( $path );
		if ( 0 === strpos( $path, 'produkt/' ) && isset( $map[ $slug ] ) ) {
			wp_safe_redirect( home_url( '/produkt/' . $map[ $slug ] . '/' ), 301 );
			exit;
		}
	}
}, 5 );

/* ---------- 1.6.0: Kategorie Einleitung oben, aktualisierte Texte unten ---------- */

function rk_seo_cat_intro( $slug ) {
	$i = array(
		'sportkarten' => 'Sportkarten Singles mit echtem Scan von Vorder und Rückseite: NFL, NBA, MLB und Fußball. Du kaufst genau die Karte, die du siehst, und zahlst in Deutschland 1,10 € Versand pro Bestellung, egal wie viele Karten, ab 70 € Warenwert gar nichts.',
		'nfl' => 'NFL Karten einzeln kaufen: Rookie Cards, Parallels und Inserts aus 2026 Topps Flagship, Topps Heritage, Panini Prizm und Topps Chrome. Jede Football Karte mit echtem Scan, Versand ab 1,10 €.',
		'nba' => 'NBA Karten einzeln kaufen: Rookie Cards, Refractors und Inserts von Topps Flagship, Topps Chrome, Panini Select und Donruss. Jede Basketball Karte mit echtem Scan, Versand ab 1,10 €.',
		'mlb' => 'MLB Karten einzeln kaufen: Baseball Rookie Cards, Parallels und Inserts von Topps Series, Topps Chrome und Stadium Club. Jede Karte mit echtem Scan, Versand ab 1,10 €.',
		'fussball' => 'Fußball Sammelkarten einzeln kaufen: 2026-27 Topps Flagship Bundesliga mit Rookies, Matchwinner, Beast Mode und Global Gladiators, dazu Match Attax und Donruss Road to World Cup. Echte Scans, Versand ab 1,10 €.',
		'tcg' => 'TCG Einzelkarten kaufen, allen voran Pokémon: Holos, ex, V und Promos aus deutschen und japanischen Sets. Jede Karte mit echtem Scan, Versand ab 1,10 €.',
		'pokemon' => 'Pokémon Karten einzeln kaufen: Holos, Reverse Holos, ex, V und Promos aus deutschen und japanischen Sets. Jede Karte mit echtem Scan, Versand ab 1,10 €.',
	);
	return isset( $i[ $slug ] ) ? $i[ $slug ] : '';
}

function rk_seo_cat_body( $slug ) {
	$b = array(
		'sportkarten' => array( 'h2' => 'Sportkarten Singles kaufen', 'text' => '<p>Bei Rookie Business findest du Sports Trading Cards als Einzelkarten statt Booster Lotterie. Im Bestand sind Football Karten aus 2026 Topps Flagship und Topps Heritage, Basketball Karten von Topps Chrome, Panini Prizm und Donruss, Baseball Karten von Topps und Fußball Karten aus Topps Flagship Bundesliga und Match Attax. Dazu Rookie Cards, Parallels, Refractors, Inserts und nummerierte Karten.</p><p>Jede Karte ist einzeln gescannt, so prüfst du Zentrierung, Ecken und Kanten vor dem Kauf. Base Cards gibt es schon für 0,80 € in der <a href="/produkt-schlagwort/wuehlkiste/">Wühlkiste</a>. Versand in Deutschland kostet 1,10 € pro Bestellung, in die EU geht es mit Sendungsnummer.</p>' ),
		'nfl' => array( 'h2' => 'NFL Sammelkarten einzeln kaufen', 'text' => '<p>Hier findest du Football Karten als Einzelkarten statt Lotterie im Booster. Der Schwerpunkt liegt auf 2026 Topps Flagship und 2026 Topps Heritage, dazu Panini Prizm, Topps Chrome, Topps Resurgence und Topps 1991. Viele Rookie Cards der neuen Draft Klasse, Parallels und Inserts, mit Stars wie Josh Allen, Lamar Jackson, CeeDee Lamb, Caleb Williams, Brock Bowers und Drake Maye.</p><p>Jede NFL Karte ist mit echtem Scan von Vorder und Rückseite gelistet. So siehst du Zentrierung, Ecken und Kanten vor dem Kauf. Filtere oben nach Mannschaft, Position, Kartenart und Serie und stell dir gezielt deine Team Sammlung zusammen. Der Versand in Deutschland kostet 1,10 € pro Bestellung und ab 70 € Warenwert nichts.</p>' ),
		'nba' => array( 'h2' => 'NBA Sammelkarten einzeln kaufen', 'text' => '<p>Basketball Karten als Singles: aktuelle Rookie Cards aus 2025-26 Topps Flagship und Topps Chrome Update, Panini Select, Prizm, Donruss und Hoops, dazu Klassiker aus 2003-04 Topps, Upper Deck und die 1980-81 Topps Inserts. Stars wie Giannis Antetokounmpo, Anthony Edwards, Jalen Brunson und Paul George und Rookies wie Drake Powell, Egor Demin oder Khaman Maluach.</p><p>Jede Karte ist echt gescannt, Vorder und Rückseite. Filtere nach Mannschaft, Position, Kartenart und Serie und sammel gezielt deine Lieblingsspieler, ohne ganze Boxen zu kaufen. Der Versand in Deutschland kostet 1,10 € pro Bestellung und ab 70 € Warenwert nichts.</p>' ),
		'mlb' => array( 'h2' => 'MLB Baseball Karten einzeln kaufen', 'text' => '<p>Baseball Sammelkarten als Einzelkarten: Topps Series 1 und 2, Topps Chrome, Topps Silver Pack und Stadium Club, mit Rookie Cards, Gold Foil und Rainbow Foil Parallels und Inserts. Dabei sind Namen wie Shohei Ohtani, Bryce Harper, Juan Soto, Mookie Betts und Derek Jeter.</p><p>Jede Karte ist einzeln gescannt, Vorder und Rückseite, so weißt du genau, was du bekommst. Filtere nach Mannschaft, Position, Kartenart und Serie. Der Versand in Deutschland kostet 1,10 € pro Bestellung, ab 70 € Warenwert ist er kostenlos.</p>' ),
		'fussball' => array( 'h2' => 'Fußball Sammelkarten einzeln kaufen', 'text' => '<p>Der Schwerpunkt liegt auf der Bundesliga: 2026-27 Topps Flagship Bundesliga mit Base Cards, Rookies aus Bundesliga und 2. Bundesliga, Future Stars, Parallels und den Inserts Matchwinner, Beast Mode, Global Gladiators, Stars of the Bundesliga und Chrome Icons. Dazu Topps Match Attax Bundesliga und Donruss Road to FIFA World Cup 26 mit Nationalspielern. Spieler wie Michael Olise, Nico Schlotterbeck, Saïd El Mala, Yan Diomande oder Serhou Guirassy findest du hier als Einzelkarte.</p><p>Jede Karte ist echt gescannt. Viele Base Cards gibt es für 0,80 € in der <a href="/produkt-schlagwort/wuehlkiste/">Wühlkiste</a>. Versand in Deutschland ab 1,10 €, in die EU für 14,49 € mit Sendungsnummer.</p>' ),
		'tcg' => array( 'h2' => 'TCG Einzelkarten kaufen', 'text' => '<p>Trading Card Game Singles mit echtem Scan, allen voran Pokémon. Einzelkarten für deine Sammlung oder dein Deck, ohne Booster Lotterie. Die ganze Auswahl findest du bei den <a href="/produktkategorie/tcg/pokemon/">Pokémon Karten</a>.</p>' ),
		'pokemon' => array( 'h2' => 'Pokémon Einzelkarten kaufen', 'text' => '<p>Pokémon TCG Singles für Sammler und Spieler: Karten aus deutschen Sets wie Sonne &amp; Mond, Schwert &amp; Schild, Dunkelnacht, Wachsendes Chaos und Entwicklungen in Paldea und aus japanischen Sets wie Shiny Treasure ex, Scarlet ex, VSTAR Universe und Crimson Haze. Holo Rares, Double Rares, Ultra Rares, Art Rares und Promos, jede Karte einzeln mit echtem Scan.</p><p>Ergänze gezielt dein Set oder dein Deck. Der Versand in Deutschland kostet 1,10 € pro Bestellung, ab 70 € Warenwert ist er kostenlos.</p>' ),
		'wuehlkiste' => array( 'h2' => 'Base Cards für 0,80 € einzeln kaufen', 'text' => '<p>In der Wühlkiste liegen reine Base Cards aus NFL, NBA, MLB und Fußball, jede für 0,80 €. Perfekt, um ein Set zu komplettieren, dein Lieblingsteam zu sammeln oder einfach zu stöbern. Jede Karte ist echt gescannt, Vorder und Rückseite.</p><p>Die Karten kommen in Penny Sleeve und Team Bag, der Versand in Deutschland kostet 1,10 € pro Bestellung, egal wie viele Karten. Mehr Auswahl findest du bei den <a href="/produktkategorie/sportkarten/">Sportkarten</a>.</p>' ),
	);
	return isset( $b[ $slug ] ) ? $b[ $slug ] : null;
}

/* ---------- 1.7.0: Produkt Titel kürzen statt abschneiden ---------- */
// Reihenfolge: voller Name, ohne Team, mit kurzen Setnamen und ohne Liga Kürzel, dann noch kürzere Setnamen.
// Die erste Variante, die mit " | Rookie Business" in 65 Zeichen passt, gewinnt, sonst die erste ohne Marke.
function rk_seo_product_title( $id ) {
	$n    = html_entity_decode( trim( wp_strip_all_tags( get_the_title( $id ) ) ), ENT_QUOTES, 'UTF-8' );
	$team = '';
	$p    = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
	if ( $p ) {
		foreach ( $p->get_attributes() as $a ) {
			if ( in_array( wc_attribute_label( $a->get_name() ), array( 'Team', 'Mannschaft' ), true ) ) {
				if ( $a->is_taxonomy() ) {
					$terms = wc_get_product_terms( $id, $a->get_name(), array( 'fields' => 'names' ) );
					$team  = $terms ? $terms[0] : '';
				} else {
					$o    = $a->get_options();
					$team = $o ? $o[0] : '';
				}
				break;
			}
		}
	}
	$c = array( $n );
	$x = $n;
	if ( $team && false !== strpos( $x, ' ' . $team . ' ' ) ) {
		$x   = str_replace( ' ' . $team . ' ', ' ', $x );
		$c[] = $x;
	}
	$m1  = array(
		'/ Topps Flagship Bundesliga\b/u'         => ' Topps Bundesliga',
		'/ Topps Match Attax Bundesliga\b/u'      => ' Match Attax',
		'/ Donruss Road to FIFA World Cup 26\b/u' => ' Donruss Road to World Cup',
		'/ Pokémon Karte /u'                      => ' Pokémon ',
		'/ (NFL|NBA|MLB)$/u'                      => '',
	);
	$x   = preg_replace( array_keys( $m1 ), array_values( $m1 ), $x );
	$c[] = $x;
	$m2  = array(
		'/ Panini /u'                 => ' ',
		'/ Topps Chrome Update\b/u'   => ' Chrome Update',
		'/ Topps Signature Class\b/u' => ' Signature Class',
		'/ Topps Flagship\b/u'        => ' Topps',
	);
	$x   = preg_replace( array_keys( $m2 ), array_values( $m2 ), $x );
	$c[] = $x;
	$b   = ' | ' . RK_SEO_BRAND;
	foreach ( $c as $s ) {
		if ( mb_strlen( $s . $b ) <= 65 ) {
			return $s . $b;
		}
	}
	foreach ( $c as $s ) {
		if ( mb_strlen( $s ) <= 65 ) {
			return $s;
		}
	}
	$y = mb_substr( $x, 0, 66 );
	return rtrim( mb_substr( $y, 0, mb_strrpos( $y, ' ' ) ), ' ,.;:-|&' );
}
