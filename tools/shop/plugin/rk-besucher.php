<?php
/**
 * Plugin Name: RK Besucher
 * Description: Eigene, cookielose Besucherstatistik für Rookie Business. Speichert keine IP Adressen und keine Namen, nur anonyme Tages IDs, Seite, Herkunft, Gerät und Zeitzone.
 * Version: 1.0.0
 * Author: Rookie Business
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'RKB_VER', '1.0.0' );
define( 'RKB_KEEP_DAYS', 90 );

function rkb_table() { global $wpdb; return $wpdb->prefix . 'rk_hits'; }

register_activation_hook( __FILE__, 'rkb_install' );
function rkb_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = rkb_table(); $c = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE $t (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ts datetime NOT NULL,
		vid char(12) NOT NULL,
		pv char(12) NOT NULL,
		path varchar(255) NOT NULL,
		title varchar(255) NOT NULL DEFAULT '',
		ref_host varchar(120) NOT NULL DEFAULT '',
		ref varchar(255) NOT NULL DEFAULT '',
		src varchar(60) NOT NULL DEFAULT '',
		device varchar(20) NOT NULL DEFAULT '',
		browser varchar(30) NOT NULL DEFAULT '',
		os varchar(30) NOT NULL DEFAULT '',
		tz varchar(60) NOT NULL DEFAULT '',
		lang varchar(20) NOT NULL DEFAULT '',
		product_id bigint(20) unsigned NOT NULL DEFAULT 0,
		customer tinyint(1) NOT NULL DEFAULT 0,
		dur int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY ts (ts),
		KEY vid (vid),
		KEY pv (pv)
	) $c;" );
	if ( ! wp_next_scheduled( 'rkb_cleanup' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'rkb_cleanup' ); }
}
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'rkb_cleanup' ); } );
add_action( 'rkb_cleanup', function () {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . rkb_table() . ' WHERE ts < %s', gmdate( 'Y-m-d H:i:s', time() - RKB_KEEP_DAYS * DAY_IN_SECONDS ) ) );
} );

// ---------- Tracking (works with page cache, because the beacon is JS) ----------
add_action( 'wp_footer', function () {
	if ( is_admin() || current_user_can( 'edit_posts' ) || is_preview() ) { return; }
	$pid = ( function_exists( 'is_product' ) && is_product() ) ? get_the_ID() : 0;
	$url = esc_url_raw( rest_url( 'rk/v1/hit' ) );
	?>
<script>(function(){try{if(navigator.webdriver)return;var u=<?php echo wp_json_encode( $url ); ?>,p=Math.random().toString(36).slice(2,14),t0=Date.now(),act=0,last=Date.now();
var d={pv:p,path:location.pathname+location.search,title:document.title.slice(0,250),ref:document.referrer,tz:(Intl.DateTimeFormat().resolvedOptions().timeZone||''),lang:(navigator.language||''),pid:<?php echo (int) $pid; ?>,w:screen.width};
function send(o){var b=JSON.stringify(o);if(navigator.sendBeacon){navigator.sendBeacon(u,new Blob([b],{type:'application/json'}));}else{fetch(u,{method:'POST',body:b,headers:{'Content-Type':'application/json'},keepalive:true});}}
send(d);document.addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden'){act+=Date.now()-last;send({pv:p,dur:Math.round(act/1000)});}else{last=Date.now();}});}catch(e){}})();</script>
	<?php
}, 99 );

add_action( 'rest_api_init', function () {
	register_rest_route( 'rk/v1', '/hit', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'rkb_hit' ) );
} );

function rkb_bot( $ua ) { return $ua === '' || preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|preview|headless|lighthouse|pingdom|uptime|curl|wget|python|monitor/i', $ua ); }

function rkb_ua( $ua ) {
	$dev = preg_match( '/iPad|Tablet/i', $ua ) ? 'Tablet' : ( preg_match( '/Mobi|iPhone|Android/i', $ua ) ? 'Handy' : 'Computer' );
	$br = 'Andere';
	foreach ( array( 'Edg' => 'Edge', 'SamsungBrowser' => 'Samsung', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'CriOS' => 'Chrome', 'Chrome' => 'Chrome', 'Safari' => 'Safari' ) as $k => $v ) { if ( strpos( $ua, $k ) !== false ) { $br = $v; break; } }
	if ( strpos( $ua, 'Instagram' ) !== false ) { $br = 'Instagram App'; } elseif ( strpos( $ua, 'FBAN' ) !== false || strpos( $ua, 'FBAV' ) !== false ) { $br = 'Facebook App'; }
	$os = 'Andere';
	foreach ( array( 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Android' => 'Android', 'Windows' => 'Windows', 'Mac OS X' => 'macOS', 'Linux' => 'Linux' ) as $k => $v ) { if ( strpos( $ua, $k ) !== false ) { $os = $v; break; } }
	return array( $dev, $br, $os );
}

function rkb_src( $host, $path ) {
	$h = strtolower( $host );
	if ( $h === '' ) { return preg_match( '/utm_source=([a-z0-9_\-]+)/i', $path, $m ) ? ucfirst( $m[1] ) : 'Direkt'; }
	$map = array( 'google' => 'Google', 'bing' => 'Bing', 'duckduckgo' => 'DuckDuckGo', 'ecosia' => 'Ecosia', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'fb.' => 'Facebook', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'ebay' => 'eBay', 'whatnot' => 'Whatnot', 't.co' => 'X', 'twitter' => 'X', 'x.com' => 'X', 'reddit' => 'Reddit', 'paypal' => 'PayPal', 'natureholix' => 'Natureholix' );
	foreach ( $map as $k => $v ) { if ( strpos( $h, $k ) !== false ) { return $v; } }
	return $h;
}

function rkb_hit( WP_REST_Request $r ) {
	global $wpdb;
	$j = json_decode( $r->get_body(), true );
	if ( ! is_array( $j ) || empty( $j['pv'] ) ) { return new WP_REST_Response( null, 204 ); }
	$pv = substr( preg_replace( '/[^a-z0-9]/', '', (string) $j['pv'] ), 0, 12 );
	if ( isset( $j['dur'] ) && ! isset( $j['path'] ) ) {
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . rkb_table() . ' SET dur = %d WHERE pv = %s AND ts > %s', min( 3600, max( 0, (int) $j['dur'] ) ), $pv, gmdate( 'Y-m-d H:i:s', time() - 6 * HOUR_IN_SECONDS ) ) );
		return new WP_REST_Response( null, 204 );
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 400 ) : '';
	if ( rkb_bot( $ua ) ) { return new WP_REST_Response( null, 204 ); }
	$path = substr( (string) ( $j['path'] ?? '/' ), 0, 255 );
	if ( strpos( $path, '/__rktest' ) === 0 ) { return new WP_REST_Response( array( 'ok' => true ), 200 ); }
	// anonymous visitor id: hash of IP + browser + secret salt that changes every day; the IP itself is never stored
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$vid = substr( hash( 'sha256', $ip . '|' . $ua . '|' . wp_salt( 'auth' ) . '|' . gmdate( 'Y-m-d' ) ), 0, 12 );
	$ref = (string) ( $j['ref'] ?? '' ); $rh = (string) wp_parse_url( $ref, PHP_URL_HOST );
	if ( $rh && strpos( $rh, wp_parse_url( home_url(), PHP_URL_HOST ) ) !== false ) { $rh = ''; $ref = ''; $src = 'Intern'; } else { $src = rkb_src( $rh, $path ); }
	list( $dev, $br, $os ) = rkb_ua( $ua );
	$wpdb->insert( rkb_table(), array(
		'ts' => current_time( 'mysql' ), 'vid' => $vid, 'pv' => $pv, 'path' => $path,
		'title' => substr( wp_strip_all_tags( (string) ( $j['title'] ?? '' ) ), 0, 255 ),
		'ref_host' => substr( $rh, 0, 120 ), 'ref' => substr( strtok( $ref, '?' ) ?: '', 0, 255 ), 'src' => substr( $src, 0, 60 ),
		'device' => $dev, 'browser' => $br, 'os' => $os,
		'tz' => substr( preg_replace( '/[^A-Za-z_\/\-]/', '', (string) ( $j['tz'] ?? '' ) ), 0, 60 ),
		'lang' => substr( preg_replace( '/[^A-Za-z\-]/', '', (string) ( $j['lang'] ?? '' ) ), 0, 20 ),
		'product_id' => (int) ( $j['pid'] ?? 0 ), 'customer' => is_user_logged_in() ? 1 : 0,
	) );
	return new WP_REST_Response( null, 204 );
}

// ---------- Admin page ----------
add_action( 'admin_menu', function () {
	add_menu_page( 'Besucher', 'Besucher', 'manage_woocommerce', 'rk-besucher', 'rkb_page', 'dashicons-chart-area', 3 );
} );

function rkb_region( $tz, $lang ) {
	$c = array( 'Europe/Berlin' => 'Deutschland', 'Europe/Vienna' => 'Österreich', 'Europe/Zurich' => 'Schweiz', 'Europe/Amsterdam' => 'Niederlande', 'Europe/Brussels' => 'Belgien', 'Europe/Paris' => 'Frankreich', 'Europe/London' => 'Großbritannien', 'Europe/Warsaw' => 'Polen', 'Europe/Rome' => 'Italien', 'Europe/Madrid' => 'Spanien', 'Europe/Copenhagen' => 'Dänemark', 'Europe/Stockholm' => 'Schweden', 'Europe/Prague' => 'Tschechien', 'Europe/Luxembourg' => 'Luxemburg' );
	if ( isset( $c[ $tz ] ) ) {
		$n = $c[ $tz ];
		if ( $tz === 'Europe/Berlin' && stripos( $lang, 'de' ) !== 0 ) { $n .= ' (Sprache ' . $lang . ')'; }
		return $n;
	}
	if ( strpos( $tz, 'America/' ) === 0 ) { return 'Amerika (' . str_replace( '_', ' ', substr( $tz, 8 ) ) . ')'; }
	return $tz ? str_replace( '_', ' ', $tz ) : 'unbekannt';
}

function rkb_bar( $rows, $label, $val, $max = 10 ) {
	$rows = array_slice( $rows, 0, $max ); if ( ! $rows ) { return '<p class="rkb-empty">Noch keine Daten.</p>'; }
	$m = max( 1, max( array_map( function ( $r ) use ( $val ) { return (int) $r->$val; }, $rows ) ) ); $h = '<table class="rkb-t">';
	foreach ( $rows as $r ) {
		$w = round( 100 * (int) $r->$val / $m );
		$h .= '<tr><td class="l">' . esc_html( $r->$label ) . '</td><td class="b"><span style="width:' . $w . '%"></span></td><td class="n">' . (int) $r->$val . '</td></tr>';
	}
	return $h . '</table>';
}

function rkb_page() {
	global $wpdb; $t = rkb_table();
	$wpdb->query( 'SET SESSION group_concat_max_len = 30000' );
	$range = isset( $_GET['r'] ) ? sanitize_key( $_GET['r'] ) : '7';
	$days = array( 'heute' => 0, '7' => 6, '30' => 29, '90' => 89 );
	$d = isset( $days[ $range ] ) ? $days[ $range ] : 6;
	$from = wp_date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) - $d * DAY_IN_SECONDS );
	$w = $wpdb->prepare( 'WHERE ts >= %s', $from );
	$k = $wpdb->get_row( "SELECT COUNT(*) pv, COUNT(DISTINCT vid, DATE(ts)) v, AVG(NULLIF(dur,0)) d FROM $t $w" );
	$one = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT vid, DATE(ts) dd FROM $t $w GROUP BY vid, DATE(ts) HAVING COUNT(*) = 1) x" );
	$live = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT vid) FROM $t WHERE ts >= %s", wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 1800 ) ) );
	$pp = $wpdb->get_results( "SELECT IF(MAX(title)='',path,MAX(title)) l, path, COUNT(*) n FROM $t $w GROUP BY path ORDER BY n DESC LIMIT 15" );
	$prod = $wpdb->get_results( "SELECT product_id, COUNT(*) n, COUNT(DISTINCT vid) v FROM $t $w AND product_id > 0 GROUP BY product_id ORDER BY n DESC LIMIT 15" );
	$src = $wpdb->get_results( "SELECT src l, COUNT(DISTINCT vid, DATE(ts)) n FROM $t $w AND src <> 'Intern' GROUP BY src ORDER BY n DESC" );
	$dev = $wpdb->get_results( "SELECT device l, COUNT(DISTINCT vid, DATE(ts)) n FROM $t $w GROUP BY device ORDER BY n DESC" );
	$brw = $wpdb->get_results( "SELECT CONCAT(browser,' auf ',os) l, COUNT(DISTINCT vid, DATE(ts)) n FROM $t $w GROUP BY browser, os ORDER BY n DESC" );
	$reg = $wpdb->get_results( "SELECT tz, lang, COUNT(DISTINCT vid, DATE(ts)) n FROM $t $w GROUP BY tz, lang ORDER BY n DESC" );
	$rg = array(); foreach ( $reg as $r ) { $l = rkb_region( $r->tz, $r->lang ); $rg[ $l ] = ( $rg[ $l ] ?? 0 ) + $r->n; } arsort( $rg );
	$rg = array_map( function ( $l, $n ) { return (object) array( 'l' => $l, 'n' => $n ); }, array_keys( $rg ), $rg );
	$hours = $wpdb->get_results( "SELECT HOUR(ts) h, COUNT(*) n FROM $t $w GROUP BY h" ); $hh = array_fill( 0, 24, 0 ); foreach ( $hours as $r ) { $hh[ (int) $r->h ] = (int) $r->n; }
	$byday = $wpdb->get_results( "SELECT DATE(ts) dd, COUNT(*) n, COUNT(DISTINCT vid) v FROM $t $w GROUP BY dd ORDER BY dd" );
	$visits = $wpdb->get_results( "SELECT vid, DATE(ts) dd, MIN(ts) a, MAX(ts) b, COUNT(*) n, SUM(dur) s, MAX(device) device, MAX(browser) browser, MAX(os) os, MAX(tz) tz, MAX(lang) lang, MAX(customer) cu,
		SUBSTRING_INDEX(GROUP_CONCAT(src ORDER BY ts SEPARATOR '|'),'|',1) src, GROUP_CONCAT(CONCAT(DATE_FORMAT(ts,'%H:%i'),'~',IF(title='',path,title),'~',dur) ORDER BY ts SEPARATOR '§') pages
		FROM $t $w GROUP BY vid, DATE(ts) ORDER BY b DESC LIMIT 60" );
	$fmt = function ( $s ) { $s = (int) round( $s ); return $s >= 60 ? floor( $s / 60 ) . ' min ' . ( $s % 60 ) . ' s' : $s . ' s'; };
	echo '<div class="wrap rkb"><h1>Besucher auf rookie-business.de</h1><p class="rkb-sub">Anonyme Statistik ohne Cookies. Keine IP Adressen, keine Namen. Ein Besucher ist pro Tag eine anonyme ID. Du selbst wirst nicht mitgezählt, wenn du eingeloggt bist.</p><p class="rkb-r">';
	foreach ( array( 'heute' => 'Heute', '7' => '7 Tage', '30' => '30 Tage', '90' => '90 Tage' ) as $kk => $l ) { echo '<a class="button' . ( $kk === $range ? ' button-primary' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=rk-besucher&r=' . $kk ) ) . '">' . esc_html( $l ) . '</a> '; }
	echo '</p><div class="rkb-k"><div><b>' . (int) $k->v . '</b>Besucher</div><div><b>' . (int) $k->pv . '</b>Seitenaufrufe</div><div><b>' . ( $k->v ? number_format( $k->pv / $k->v, 1, ',', '' ) : '0' ) . '</b>Seiten pro Besuch</div><div><b>' . esc_html( $fmt( $k->d ) ) . '</b>Ø Zeit pro Seite</div><div><b>' . ( $k->v ? round( 100 * $one / $k->v ) : 0 ) . ' %</b>nur 1 Seite gesehen</div><div class="live"><b>' . $live . '</b>gerade da (30 min)</div></div>';
	// days chart
	if ( count( $byday ) > 1 ) { $m = max( 1, max( array_map( function ( $r ) { return (int) $r->v; }, $byday ) ) ); echo '<div class="rkb-c"><h2>Besucher pro Tag</h2><div class="rkb-days">'; foreach ( $byday as $r ) { echo '<div title="' . esc_attr( wp_date( 'd.m.', strtotime( $r->dd ) ) . ': ' . $r->v . ' Besucher, ' . $r->n . ' Aufrufe' ) . '"><span style="height:' . round( 100 * $r->v / $m ) . '%"></span><i>' . esc_html( wp_date( 'd.m.', strtotime( $r->dd ) ) ) . '</i><em>' . (int) $r->v . '</em></div>'; } echo '</div></div>'; }
	$m = max( 1, max( $hh ) ); echo '<div class="rkb-c"><h2>Uhrzeit (Seitenaufrufe)</h2><div class="rkb-days h">'; foreach ( $hh as $h => $n ) { echo '<div title="' . $h . ' Uhr: ' . $n . '"><span style="height:' . round( 100 * $n / $m ) . '%"></span><i>' . $h . '</i></div>'; } echo '</div></div>';
	echo '<div class="rkb-g"><div class="rkb-c"><h2>Woher sie kommen</h2>' . rkb_bar( $src, 'l', 'n' ) . '</div><div class="rkb-c"><h2>Region (aus Zeitzone und Sprache)</h2>' . rkb_bar( $rg, 'l', 'n' ) . '</div>';
	echo '<div class="rkb-c"><h2>Gerät</h2>' . rkb_bar( $dev, 'l', 'n' ) . '<h2>Browser</h2>' . rkb_bar( $brw, 'l', 'n', 6 ) . '</div>';
	echo '<div class="rkb-c"><h2>Meistgesehene Seiten</h2>' . rkb_bar( $pp, 'l', 'n', 15 ) . '</div>';
	echo '<div class="rkb-c"><h2>Meistgesehene Karten</h2>';
	if ( $prod ) { echo '<table class="rkb-t">'; foreach ( $prod as $r ) { $p = function_exists( 'wc_get_product' ) ? wc_get_product( $r->product_id ) : null; echo '<tr><td class="l">' . ( $p ? '<a href="' . esc_url( get_edit_post_link( $r->product_id ) ) . '">' . esc_html( $p->get_name() ) . '</a> <small>' . wp_kses_post( wc_price( $p->get_price() ) ) . ( $p->is_in_stock() ? '' : ' · verkauft' ) . '</small>' : '#' . (int) $r->product_id ) . '</td><td class="n">' . (int) $r->n . ' Aufrufe · ' . (int) $r->v . ' Besucher</td></tr>'; } echo '</table>'; } else { echo '<p class="rkb-empty">Noch keine Karte angesehen.</p>'; }
	echo '</div></div><div class="rkb-c"><h2>Einzelne Besuche (neueste zuerst)</h2><p class="rkb-sub">Wer: anonyme ID. Wann, woher, mit welchem Gerät und welche Seiten in welcher Reihenfolge, mit Verweildauer.</p><table class="widefat striped rkb-v"><thead><tr><th>Wann</th><th>Besucher</th><th>Woher</th><th>Gerät</th><th>Region</th><th>Seiten</th></tr></thead><tbody>';
	foreach ( $visits as $v ) {
		$pg = array(); foreach ( explode( '§', (string) $v->pages ) as $x ) { $q = explode( '~', $x ); if ( count( $q ) >= 3 ) { $pg[] = '<li><span>' . esc_html( $q[0] ) . '</span> ' . esc_html( wp_html_excerpt( $q[1], 90, '…' ) ) . ( (int) $q[2] ? ' <em>' . esc_html( $fmt( $q[2] ) ) . '</em>' : '' ) . '</li>'; } }
		echo '<tr><td>' . esc_html( wp_date( 'd.m. H:i', strtotime( $v->a ) ) ) . '<br><small>' . esc_html( $fmt( strtotime( $v->b ) - strtotime( $v->a ) ) ) . '</small></td><td><code>' . esc_html( $v->vid ) . '</code>' . ( $v->cu ? '<br><small>eingeloggter Kunde</small>' : '' ) . '</td><td>' . esc_html( $v->src ) . '</td><td>' . esc_html( $v->device . ', ' . $v->browser . ' auf ' . $v->os ) . '</td><td>' . esc_html( rkb_region( $v->tz, $v->lang ) ) . '</td><td><details><summary>' . (int) $v->n . ' Seiten</summary><ol>' . implode( '', $pg ) . '</ol></details></td></tr>';
	}
	if ( ! $visits ) { echo '<tr><td colspan="6">Noch keine Besuche. Die Zählung läuft ab jetzt.</td></tr>'; }
	echo '</tbody></table></div></div>';
	echo '<style>.rkb .rkb-sub{color:#646970;margin:4px 0 10px}.rkb-k{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:14px 0}.rkb-k div{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px;color:#50575e}.rkb-k b{display:block;font-size:26px;color:#1d2327;line-height:1.2}.rkb-k .live b{color:#c8102e}.rkb-c{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 14px;margin:0 0 12px}.rkb-c h2{font-size:14px;margin:4px 0 10px}.rkb-g{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:12px}.rkb-t{width:100%;border-collapse:collapse}.rkb-t td{padding:3px 4px;vertical-align:middle}.rkb-t .l{width:45%;word-break:break-word}.rkb-t .b span{display:block;height:10px;background:#c8102e;border-radius:3px;min-width:2px}.rkb-t .n{text-align:right;white-space:nowrap;color:#50575e}.rkb-days{display:flex;align-items:flex-end;gap:3px;height:140px;padding-top:16px}.rkb-days div{flex:1;height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;position:relative}.rkb-days span{display:block;width:100%;background:#c8102e;border-radius:3px 3px 0 0;min-height:2px}.rkb-days i{font-style:normal;font-size:10px;color:#646970;margin-top:3px}.rkb-days em{position:absolute;top:0;font-style:normal;font-size:10px}.rkb-days.h span{background:#1d2327}.rkb-v ol{margin:6px 0 0 18px}.rkb-v li span{color:#646970}.rkb-v li em{color:#c8102e;font-style:normal}.rkb-empty{color:#646970}</style>';
}

// small widget on the dashboard
add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	wp_add_dashboard_widget( 'rkb_w', 'Besucher heute', function () {
		global $wpdb; $t = rkb_table(); $f = wp_date( 'Y-m-d 00:00:00' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) pv, COUNT(DISTINCT vid) v FROM $t WHERE ts >= %s", $f ) );
		echo '<p style="font-size:15px"><b>' . (int) $r->v . '</b> Besucher · <b>' . (int) $r->pv . '</b> Seitenaufrufe heute. <a href="' . esc_url( admin_url( 'admin.php?page=rk-besucher&r=heute' ) ) . '">Alles ansehen</a></p>';
	} );
} );
