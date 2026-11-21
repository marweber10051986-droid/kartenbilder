
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
	$teams = 'Arizona Cardinals|Atlanta Falcons|Baltimore Ravens|Buffalo Bills|Carolina Panthers|Chicago Bears|Cincinnati Bengals|Cleveland Browns|Dallas Cowboys|Denver Broncos|Detroit Lions|Green Bay Packers|Houston Texans|Indianapolis Colts|Jacksonville Jaguars|Kansas City Chiefs|Las Vegas Raiders|Los Angeles Chargers|Los Angeles Rams|Miami Dolphins|Minnesota Vikings|New England Patriots|New Orleans Saints|New York Giants|New York Jets|Philadelphia Eagles|Pittsburgh Steelers|San Francisco 49ers|Seattle Seahawks|Tampa Bay Buccaneers|Tennessee Titans|Washington Commanders';
	foreach ( $ids as $id ) {
		$t = html_entity_decode( get_the_title( $id ), ENT_QUOTES );
		if ( false === strpos( $t, $jahr ) || ! preg_match( '/#(\d+)\s(.*)$/', $t, $m ) ) {
			continue;
		}
		$p = wc_get_product( $id );
		if ( ! $p || ! $p->is_in_stock() ) {
			continue;
		}
		$l = preg_replace( '/\s*' . preg_quote( $jahr, '/' ) . '\s.*$/', '', $m[2] );
		$l = preg_replace( '/\b(' . $teams . ')\b/', '', $l );
		$l = preg_replace( '/\b(AFC|NFC)\b.*\bLeaders\b/', '', $l );
		$l = trim( preg_replace( '/\s+/', ' ', $l ) );
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
