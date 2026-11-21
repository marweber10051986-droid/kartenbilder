
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
		$out .= '<span class="rk-rg-card__img"><img src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async" width="1200" height="675"></span>';
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
	$key   = 'rkrg_' . md5( wp_json_encode( $a ) . gmdate( 'Ymd' ) ) . '_' . get_option( 'rkcl_v', 1 );
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
			$items[] = array( 'u' => get_permalink( $id ), 'n' => html_entity_decode( get_the_title( $id ), ENT_QUOTES ), 'p' => (float) $p->get_price(), 'i' => wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ), 'd' => get_post_time( 'U', true, $id ) );
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
