
/**
 * Frisch gezogen: neue Karten der letzten 24 Stunden zuerst, der Rest kommt aus der
 * täglichen Rotation (3 Karten pro Kategorie, läuft Tag für Tag durch das ganze Inventar).
 */
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
	// 1. Neu in den letzten 24 Stunden.
	$ids = wc_get_products( $base + array(
		'limit'        => $limit,
		'orderby'      => 'date',
		'order'        => 'DESC',
		'date_created' => '>' . ( time() - DAY_IN_SECONDS ),
	) );
	// 2. Rotation: pro Kategorie 3 Karten, jeden Tag die nächsten 3.
	$rot = array();
	foreach ( array( 'nfl', 'nba', 'mlb', 'fussball', 'tcg' ) as $cat ) {
		$all = wc_get_products( $base + array(
			'limit'    => -1,
			'category' => array( $cat ),
			'orderby'  => 'ID',
			'order'    => 'ASC',
		) );
		$n = count( $all );
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
