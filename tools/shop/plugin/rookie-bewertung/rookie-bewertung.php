<?php
/**
 * Plugin Name: Rookie Bewertungs-Mail
 * Description: Fragt Kunden, die an der Kasse zugestimmt haben, einige Tage nach dem Abschluss der Bestellung einmal per E-Mail nach einer Bewertung.
 * Version: 1.0.0
 * Author: Rookie Business
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RK_BEW_FIELD', 'rookie/bewertung' );
define( 'RK_BEW_OPT', 'rk_bew_tage' );

/* ---------- Häkchen an der Kasse (Block-Kasse) ---------- */

add_action( 'woocommerce_init', function () {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}
	woocommerce_register_additional_checkout_field(
		array(
			'id'       => RK_BEW_FIELD,
			'label'    => 'Ja, ihr dürft mich nach Erhalt der Karten einmal per E-Mail um eine Bewertung bitten (freiwillig, jederzeit widerrufbar).',
			'location' => 'order',
			'type'     => 'checkbox',
			'required' => false,
		)
	);
} );

/* ---------- Häkchen an der klassischen Kasse (Fallback) ---------- */

add_action( 'woocommerce_review_order_before_submit', function () {
	echo '<p class="form-row"><label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox"><input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="rk_bewertung" value="1"> <span>Ja, ihr dürft mich nach Erhalt der Karten einmal per E-Mail um eine Bewertung bitten (freiwillig, jederzeit widerrufbar).</span></label></p>';
} );

add_action( 'woocommerce_checkout_create_order', function ( $order ) {
	if ( ! empty( $_POST['rk_bewertung'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$order->update_meta_data( '_rk_bew_ok', '1' );
	}
} );

function rk_bew_consent( $order ) {
	if ( $order->get_meta( '_rk_bew_ok' ) ) {
		return true;
	}
	foreach ( array( '_wc_other/' . RK_BEW_FIELD, '_wc_order/' . RK_BEW_FIELD ) as $k ) {
		$v = $order->get_meta( $k );
		if ( $v && 'false' !== $v && '0' !== $v ) {
			return true;
		}
	}
	return false;
}

/* ---------- Mail planen, wenn die Bestellung abgeschlossen ist ---------- */

function rk_bew_tage() {
	$t = (int) get_option( RK_BEW_OPT, 5 );
	return max( 1, min( 30, $t ) );
}

add_action( 'woocommerce_order_status_completed', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! rk_bew_consent( $order ) || $order->get_meta( '_rk_bew_geplant' ) || $order->get_meta( '_rk_bew_gesendet' ) ) {
		return;
	}
	$when = time() + rk_bew_tage() * DAY_IN_SECONDS;
	wp_schedule_single_event( $when, 'rk_bew_senden', array( (int) $order_id ) );
	$order->update_meta_data( '_rk_bew_geplant', gmdate( 'c', $when ) );
	$order->save();
	$order->add_order_note( 'Bewertungs-Mail geplant für ' . wp_date( 'd.m.Y H:i', $when ) . '.' );
} );

add_action( 'rk_bew_senden', 'rk_bew_senden' );

function rk_bew_mail_html( $order, $preview = false ) {
	$name  = $order ? $order->get_billing_first_name() : 'Max';
	$items = array();
	if ( $order ) {
		foreach ( $order->get_items() as $item ) {
			$pid = $item->get_product_id();
			if ( $pid && 'publish' === get_post_status( $pid ) ) {
				$items[ $pid ] = $item->get_name();
			}
		}
	}
	if ( $preview && ! $items ) {
		$ids = wc_get_products( array( 'limit' => 2, 'status' => 'publish', 'return' => 'ids' ) );
		foreach ( $ids as $pid ) {
			$items[ $pid ] = get_the_title( $pid );
		}
	}
	if ( ! $items ) {
		return '';
	}
	$list = '';
	foreach ( $items as $pid => $title ) {
		$url   = get_permalink( $pid ) . '#reviews';
		$list .= '<li style="margin:0 0 10px"><a href="' . esc_url( $url ) . '" style="color:#ff3b30;font-weight:bold;text-decoration:none">' . esc_html( $title ) . '</a><br><span style="font-size:13px">Jetzt bewerten ➜</span></li>';
	}
	$body  = '<p>Hi ' . esc_html( $name ) . ',</p>';
	$body .= '<p>deine Karten sollten inzwischen bei dir sein. Ich hoffe, alles ist gut angekommen und sie gefallen dir.</p>';
	$body .= '<p>Als kleiner Shop hilft mir jede ehrliche Bewertung enorm. Es dauert nur eine Minute: Sterne vergeben, gern ein Satz dazu.</p>';
	$body .= '<ul style="padding-left:18px">' . $list . '</ul>';
	$body .= '<p>Falls etwas nicht passt, antworte einfach auf diese Mail, dann kläre ich das direkt mit dir.</p>';
	$body .= '<p>Danke dir!<br>Marcel von Rookie Business</p>';
	$body .= '<p style="font-size:12px;color:#888">Du bekommst diese Mail einmalig, weil du an der Kasse zugestimmt hast. Weitere Bewertungsanfragen gibt es zu dieser Bestellung nicht. Wenn du künftig keine mehr möchtest, antworte kurz mit „Keine Bewertungsmails“.</p>';
	return $body;
}

function rk_bew_senden( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || $order->get_meta( '_rk_bew_gesendet' ) || ! rk_bew_consent( $order ) ) {
		return;
	}
	if ( in_array( $order->get_status(), array( 'refunded', 'cancelled', 'failed' ), true ) ) {
		return;
	}
	$to = $order->get_billing_email();
	if ( ! $to || rk_bew_gesperrt( $to ) ) {
		return;
	}
	$body = rk_bew_mail_html( $order );
	if ( ! $body ) {
		return;
	}
	$mailer = WC()->mailer();
	$html   = $mailer->wrap_message( 'Wie gefallen dir deine Karten?', $body );
	$ok     = $mailer->send( $to, 'Wie gefallen dir deine Karten? Kurze Bewertung für Rookie Business', $html, "Content-Type: text/html\r\n" );
	$order->update_meta_data( '_rk_bew_gesendet', $ok ? gmdate( 'c' ) : 'fehler' );
	$order->save();
	$order->add_order_note( $ok ? 'Bewertungs-Mail gesendet.' : 'Bewertungs-Mail konnte nicht gesendet werden.' );
}

/* ---------- Sperrliste (wer keine Mails mehr möchte) ---------- */

function rk_bew_gesperrt( $mail ) {
	$list = (array) get_option( 'rk_bew_sperre', array() );
	return in_array( strtolower( trim( $mail ) ), $list, true );
}

/* ---------- Admin-Seite ---------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Bewertungs-Mail', 'Bewertungs-Mail', 'manage_woocommerce', 'rk-bewertung', 'rk_bew_admin' );
}, 60 );

function rk_bew_admin() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$msg = '';
	if ( isset( $_POST['rk_bew_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rk_bew_nonce'] ) ), 'rk_bew' ) ) {
		if ( isset( $_POST['tage'] ) ) {
			update_option( RK_BEW_OPT, max( 1, min( 30, (int) $_POST['tage'] ) ) );
		}
		if ( isset( $_POST['sperre'] ) ) {
			$lines = preg_split( '/[\s,;]+/', strtolower( sanitize_textarea_field( wp_unslash( $_POST['sperre'] ) ) ) );
			update_option( 'rk_bew_sperre', array_values( array_filter( array_map( 'sanitize_email', $lines ) ) ) );
		}
		$msg = 'Gespeichert.';
		if ( ! empty( $_POST['test'] ) ) {
			$u      = wp_get_current_user();
			$mailer = WC()->mailer();
			$html   = $mailer->wrap_message( 'Wie gefallen dir deine Karten?', rk_bew_mail_html( null, true ) );
			$ok     = $mailer->send( $u->user_email, '[TEST] Wie gefallen dir deine Karten? Kurze Bewertung für Rookie Business', $html, "Content-Type: text/html\r\n" );
			$msg    = $ok ? 'Testmail an ' . esc_html( $u->user_email ) . ' gesendet.' : 'Testmail konnte nicht gesendet werden.';
		}
	}
	$geplant = 0;
	$crons   = _get_cron_array();
	foreach ( (array) $crons as $ev ) {
		if ( isset( $ev['rk_bew_senden'] ) ) {
			$geplant += count( $ev['rk_bew_senden'] );
		}
	}
	$gesendet = count( wc_get_orders( array( 'limit' => -1, 'return' => 'ids', 'meta_key' => '_rk_bew_gesendet', 'meta_compare' => 'EXISTS' ) ) );
	$sperre   = implode( "\n", (array) get_option( 'rk_bew_sperre', array() ) );
	echo '<div class="wrap"><h1>Bewertungs-Mail</h1>';
	if ( $msg ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $msg ) . '</p></div>';
	}
	echo '<p>Kunden, die an der Kasse das Häkchen setzen, bekommen einmalig eine Mail mit Links zu ihren gekauften Karten. Die Mail wird geplant, sobald du die Bestellung auf <strong>Fertiggestellt</strong> setzt.</p>';
	echo '<p><strong>Geplant:</strong> ' . (int) $geplant . ' &nbsp; <strong>Gesendet:</strong> ' . (int) $gesendet . '</p>';
	echo '<form method="post">';
	wp_nonce_field( 'rk_bew', 'rk_bew_nonce' );
	echo '<p><label>Tage nach „Fertiggestellt“: <input type="number" name="tage" min="1" max="30" value="' . (int) rk_bew_tage() . '" style="width:70px"></label></p>';
	echo '<p><label>Keine Bewertungsmails an (eine Adresse pro Zeile):<br><textarea name="sperre" rows="4" cols="50">' . esc_textarea( $sperre ) . '</textarea></label></p>';
	echo '<p><button class="button button-primary">Speichern</button> &nbsp; <button class="button" name="test" value="1">Testmail an mich senden</button></p>';
	echo '</form>';
	echo '<h2>Vorschau</h2><div style="background:#fff;border:1px solid #ddd;padding:16px;max-width:600px">' . wp_kses_post( rk_bew_mail_html( null, true ) ) . '</div></div>';
}
