<?php
/**
 * Plugin Name: Rookie Business Newsletter
 * Description: Newsletter Anmeldung mit Double Opt In. Nach der Bestätigung bekommt jede E-Mail einen persönlichen 5 % Gutschein für die erste Bestellung. Formular per Shortcode [rookie_newsletter].
 * Version: 1.0.2
 * Author: Rookie Business
 * Requires Plugins: woocommerce
 * Text Domain: rookie-newsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const RK_NL_DISCOUNT = 5; // percent, first order only.

/* ------------------------------------------------------------------
 * Subscribers are stored as a private post type (visible in wp-admin).
 * ------------------------------------------------------------------ */
add_action( 'init', function () {
	register_post_type( 'rk_subscriber', array(
		'labels'          => array(
			'name'          => 'Newsletter',
			'singular_name' => 'Abonnent',
			'menu_name'     => 'Newsletter',
			'all_items'     => 'Alle Abonnenten',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-email-alt',
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'    => true,
	) );
} );

add_filter( 'manage_rk_subscriber_posts_columns', function () {
	return array(
		'cb'        => '<input type="checkbox" />',
		'title'     => 'E-Mail',
		'rk_status' => 'Status',
		'rk_coupon' => 'Gutschein',
		'date'      => 'Angemeldet',
	);
} );
add_action( 'manage_rk_subscriber_posts_custom_column', function ( $col, $id ) {
	if ( 'rk_status' === $col ) {
		$labels = array( 'pending' => 'Unbestätigt', 'confirmed' => 'Bestätigt', 'unsubscribed' => 'Abgemeldet' );
		$s      = get_post_meta( $id, '_rk_status', true );
		echo esc_html( $labels[ $s ] ?? $s );
		$c = get_post_meta( $id, '_rk_confirmed_at', true );
		if ( $c ) {
			echo '<br><small>' . esc_html( $c ) . '</small>';
		}
	}
	if ( 'rk_coupon' === $col ) {
		echo esc_html( get_post_meta( $id, '_rk_coupon', true ) );
	}
}, 10, 2 );

function rk_nl_find( $email ) {
	$q = get_posts( array(
		'post_type'   => 'rk_subscriber',
		'post_status' => 'any',
		'title'       => $email,
		'numberposts' => 1,
	) );
	return $q ? $q[0] : null;
}

function rk_nl_find_by_token( $token ) {
	$q = get_posts( array(
		'post_type'   => 'rk_subscriber',
		'post_status' => 'any',
		'numberposts' => 1,
		'meta_key'    => '_rk_token',
		'meta_value'  => $token,
	) );
	return $q ? $q[0] : null;
}

/** Sender on the shop's own domain (never the private admin address). */
function rk_nl_from_address() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/^www\./', '', (string) $host );
	return apply_filters( 'rk_nl_from_address', 'newsletter@' . $host );
}

function rk_nl_mail( $to, $subject, $html ) {
	$body = '<div style="background:#0a0a0a;padding:32px 0;font-family:Arial,sans-serif">'
		. '<div style="max-width:560px;margin:0 auto;background:#141414;color:#fff;padding:32px;border:1px solid #262626">'
		. '<p style="margin:0 0 24px;font-weight:800;font-size:22px;letter-spacing:.02em">ROOKIE <span style="color:#ff3b30">BUSINESS</span></p>'
		. $html
		. '<p style="margin:32px 0 0;color:#8c8c8c;font-size:12px">Rookie Business · Sports Trading Card Singles</p>'
		. '</div></div>';
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: Rookie Business <' . rk_nl_from_address() . '>',
		'Reply-To: Rookie Business <shop@rookie-business.de>',
	);
	return wp_mail( $to, $subject, $body, $headers );
}

/* ------------------------------------------------------------------
 * Signup form
 * ------------------------------------------------------------------ */
add_shortcode( 'rookie_newsletter', function () {
	$privacy = get_privacy_policy_url();
	ob_start();
	?>
	<form class="rk-nl" method="post" novalidate>
		<label class="rk-nl__label" for="rk-nl-email">Newsletter · <?php echo (int) RK_NL_DISCOUNT; ?> % auf deine erste Bestellung</label>
		<div class="rk-nl__row">
			<input id="rk-nl-email" class="rk-nl__email" type="email" name="email" required autocomplete="email" placeholder="deine@email.de">
			<button class="rk-nl__btn" type="submit">Anmelden <span aria-hidden="true">↗</span></button>
		</div>
		<input type="text" name="website" class="rk-nl__hp" tabindex="-1" autocomplete="off" aria-hidden="true">
		<label class="rk-nl__consent">
			<input type="checkbox" name="consent" value="1" required>
			<span>Ja, ich möchte den Newsletter von Rookie Business mit neuen Karten, Drops und Angeboten per E-Mail bekommen. Abmeldung jederzeit über den Link in jeder Mail. Mehr in der <a href="<?php echo esc_url( $privacy ); ?>">Datenschutzerklärung</a>.</span>
		</label>
		<p class="rk-nl__msg" role="status" aria-live="polite"></p>
	</form>
	<script>
	(function(){
		var f=document.currentScript.previousElementSibling; if(!f||!f.classList.contains('rk-nl')) return;
		f.addEventListener('submit',function(e){
			e.preventDefault();
			var m=f.querySelector('.rk-nl__msg'), b=f.querySelector('button'), d=new FormData(f);
			d.append('action','rk_nl_signup'); d.append('nonce','<?php echo esc_js( wp_create_nonce( 'rk_nl' ) ); ?>');
			b.disabled=true; m.textContent='Einen Moment …';
			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',{method:'POST',body:d,credentials:'same-origin'})
				.then(function(r){return r.json();})
				.then(function(r){ m.textContent=r.data&&r.data.message?r.data.message:'Das hat nicht geklappt. Bitte versuch es noch einmal.'; if(r.success){f.reset();} })
				.catch(function(){ m.textContent='Das hat nicht geklappt. Bitte versuch es noch einmal.'; })
				.finally(function(){ b.disabled=false; });
		});
	})();
	</script>
	<?php
	return ob_get_clean();
} );

function rk_nl_signup() {
	check_ajax_referer( 'rk_nl', 'nonce' );
	if ( ! empty( $_POST['website'] ) ) { // honeypot
		wp_send_json_success( array( 'message' => 'Fast geschafft. Bitte bestätige deine Anmeldung in der E-Mail, die wir dir gerade geschickt haben.' ) );
	}
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'Bitte gib eine gültige E-Mail Adresse ein.' ) );
	}
	if ( empty( $_POST['consent'] ) ) {
		wp_send_json_error( array( 'message' => 'Bitte bestätige die Einwilligung zum Newsletter.' ) );
	}
	$email = strtolower( $email );

	// Simple rate limit per IP: 5 signups per hour.
	$ip_key = 'rk_nl_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		wp_send_json_error( array( 'message' => 'Zu viele Versuche. Bitte probier es später noch einmal.' ) );
	}
	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

	$sub = rk_nl_find( $email );
	if ( $sub && 'confirmed' === get_post_meta( $sub->ID, '_rk_status', true ) ) {
		wp_send_json_success( array( 'message' => 'Du bist schon angemeldet. Danke!' ) );
	}
	if ( ! $sub ) {
		$id = wp_insert_post( array( 'post_type' => 'rk_subscriber', 'post_title' => $email, 'post_status' => 'private' ) );
	} else {
		$id = $sub->ID;
	}
	$token = wp_generate_password( 32, false, false );
	update_post_meta( $id, '_rk_token', $token );
	update_post_meta( $id, '_rk_status', 'pending' );
	update_post_meta( $id, '_rk_consent_text', 'Newsletter von Rookie Business mit neuen Karten, Drops und Angeboten' );
	update_post_meta( $id, '_rk_requested_at', current_time( 'mysql' ) );

	$link = add_query_arg( 'rk_nl_confirm', $token, home_url( '/' ) );
	rk_nl_mail(
		$email,
		'Bitte bestätige deine Anmeldung bei Rookie Business',
		'<p style="font-size:16px;line-height:1.6">Fast geschafft! Bitte bestätige mit einem Klick, dass du den Rookie Business Newsletter bekommen möchtest.</p>'
		. '<p style="margin:28px 0"><a href="' . esc_url( $link ) . '" style="background:#ff3b30;color:#fff;padding:14px 22px;text-decoration:none;font-weight:700;display:inline-block">Anmeldung bestätigen</a></p>'
		. '<p style="font-size:14px;line-height:1.6;color:#cfcfcf">Danach bekommst du deinen persönlichen Gutschein über ' . (int) RK_NL_DISCOUNT . ' % für deine erste Bestellung.</p>'
		. '<p style="font-size:12px;color:#8c8c8c">Wenn du dich nicht angemeldet hast, ignoriere diese E-Mail einfach. Ohne Bestätigung wirst du nicht eingetragen.</p>'
	);

	wp_send_json_success( array( 'message' => 'Fast geschafft. Bitte bestätige deine Anmeldung in der E-Mail, die wir dir gerade geschickt haben.' ) );
}
add_action( 'wp_ajax_rk_nl_signup', 'rk_nl_signup' );
add_action( 'wp_ajax_nopriv_rk_nl_signup', 'rk_nl_signup' );

/* ------------------------------------------------------------------
 * Confirm + unsubscribe links
 * ------------------------------------------------------------------ */
function rk_nl_create_coupon( $email ) {
	if ( ! class_exists( 'WC_Coupon' ) ) {
		return '';
	}
	do {
		$code = 'RB5-' . strtoupper( wp_generate_password( 6, false, false ) );
	} while ( wc_get_coupon_id_by_code( $code ) );

	$c = new WC_Coupon();
	$c->set_code( $code );
	$c->set_discount_type( 'percent' );
	$c->set_amount( RK_NL_DISCOUNT );
	$c->set_individual_use( true );
	$c->set_usage_limit( 1 );
	$c->set_usage_limit_per_user( 1 );
	$c->set_email_restrictions( array( $email ) );
	$c->set_description( 'Newsletter Gutschein, erste Bestellung, persönlich für ' . $email );
	$c->update_meta_data( '_rk_first_order', 'yes' );
	$c->save();
	return $code;
}

add_action( 'template_redirect', function () {
	if ( isset( $_GET['rk_nl_confirm'] ) ) {
		$token = sanitize_text_field( wp_unslash( $_GET['rk_nl_confirm'] ) );
		$sub   = $token ? rk_nl_find_by_token( $token ) : null;
		if ( ! $sub ) {
			rk_nl_page( 'Link ungültig', 'Dieser Bestätigungslink ist ungültig oder abgelaufen. Melde dich einfach noch einmal an.' );
		}
		$email = $sub->post_title;
		$code  = get_post_meta( $sub->ID, '_rk_coupon', true );
		if ( 'confirmed' !== get_post_meta( $sub->ID, '_rk_status', true ) ) {
			update_post_meta( $sub->ID, '_rk_status', 'confirmed' );
			update_post_meta( $sub->ID, '_rk_confirmed_at', current_time( 'mysql' ) );
			update_post_meta( $sub->ID, '_rk_confirm_ip', sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		}
		if ( ! $code ) {
			$code = rk_nl_create_coupon( $email );
			update_post_meta( $sub->ID, '_rk_coupon', $code );
			$unsub = add_query_arg( 'rk_nl_unsub', $token, home_url( '/' ) );
			rk_nl_mail(
				$email,
				'Dein persönlicher ' . (int) RK_NL_DISCOUNT . ' % Gutschein von Rookie Business',
				'<p style="font-size:16px;line-height:1.6">Willkommen im Newsletter! Hier ist dein persönlicher Gutschein für deine erste Bestellung:</p>'
				. '<p style="margin:24px 0;font-size:28px;font-weight:800;letter-spacing:.08em;color:#ff3b30">' . esc_html( $code ) . '</p>'
				. '<p style="font-size:14px;line-height:1.6;color:#cfcfcf">' . (int) RK_NL_DISCOUNT . ' % Rabatt auf deine erste Bestellung. Einmal einlösbar und nur mit dieser E-Mail Adresse gültig. Gib den Code einfach im Warenkorb ein.</p>'
				. '<p style="margin:28px 0"><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" style="background:#ff3b30;color:#fff;padding:14px 22px;text-decoration:none;font-weight:700;display:inline-block">Zum Shop</a></p>'
				. '<p style="font-size:12px;color:#8c8c8c">Du willst keinen Newsletter mehr? <a href="' . esc_url( $unsub ) . '" style="color:#8c8c8c">Hier abmelden</a>.</p>'
			);
		}
		rk_nl_page( 'Anmeldung bestätigt', 'Danke! Dein persönlicher Gutschein über ' . (int) RK_NL_DISCOUNT . ' % für die erste Bestellung lautet:', $code );
	}

	if ( isset( $_GET['rk_nl_unsub'] ) ) {
		$token = sanitize_text_field( wp_unslash( $_GET['rk_nl_unsub'] ) );
		$sub   = $token ? rk_nl_find_by_token( $token ) : null;
		if ( $sub ) {
			update_post_meta( $sub->ID, '_rk_status', 'unsubscribed' );
			update_post_meta( $sub->ID, '_rk_unsubscribed_at', current_time( 'mysql' ) );
		}
		rk_nl_page( 'Abgemeldet', 'Du bekommst keinen Newsletter mehr von uns. Schade, aber kein Problem.' );
	}
} );

function rk_nl_page( $title, $text, $code = '' ) {
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	$html = '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
		. '<title>' . esc_html( $title ) . ' · Rookie Business</title></head>'
		. '<body style="margin:0;background:#0a0a0a;color:#fff;font-family:Arial,sans-serif;display:grid;place-items:center;min-height:100vh">'
		. '<div style="max-width:520px;padding:40px;text-align:center">'
		. '<p style="font-weight:800;font-size:22px;margin:0 0 30px">ROOKIE <span style="color:#ff3b30">BUSINESS</span></p>'
		. '<h1 style="font-size:34px;margin:0 0 16px;text-transform:uppercase">' . esc_html( $title ) . '</h1>'
		. '<p style="font-size:16px;line-height:1.6;color:#cfcfcf">' . esc_html( $text ) . '</p>'
		. ( $code ? '<p style="font-size:32px;font-weight:800;letter-spacing:.08em;color:#ff3b30;margin:24px 0">' . esc_html( $code ) . '</p><p style="font-size:13px;color:#8c8c8c">Wir haben dir den Code auch per E-Mail geschickt.</p>' : '' )
		. '<p style="margin-top:30px"><a href="' . esc_url( $shop ) . '" style="background:#fff;color:#0a0a0a;padding:14px 22px;text-decoration:none;font-weight:700;display:inline-block">Zum Shop</a></p>'
		. '</div></body></html>';
	nocache_headers();
	echo $html; // phpcs:ignore
	exit;
}

/* ------------------------------------------------------------------
 * Coupon only valid for the first order of that email address.
 * ------------------------------------------------------------------ */
add_filter( 'woocommerce_coupon_is_valid', function ( $valid, $coupon ) {
	if ( ! $valid || 'yes' !== $coupon->get_meta( '_rk_first_order' ) ) {
		return $valid;
	}
	$email = '';
	if ( WC()->customer ) {
		$email = WC()->customer->get_billing_email();
	}
	if ( ! $email && is_user_logged_in() ) {
		$email = wp_get_current_user()->user_email;
	}
	if ( $email ) {
		$orders = wc_get_orders( array(
			'billing_email' => $email,
			'status'        => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
			'limit'         => 1,
			'return'        => 'ids',
		) );
		if ( $orders ) {
			throw new Exception( 'Dieser Gutschein gilt nur für die erste Bestellung.' );
		}
	}
	return $valid;
}, 10, 2 );
