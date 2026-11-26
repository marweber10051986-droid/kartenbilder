<?php
/**
 * Plugin Name: Rookie Business Mengenrabatt
 * Description: Staffelrabatt im Warenkorb: ab 10 Karten 10 %, ab 20 Karten 20 %, ab 30 Karten 25 % auf den Warenwert. Nicht mit Gutscheinen kombinierbar, es gilt immer der höhere Rabatt.
 * Version: 1.2.0
 * Author: Rookie Business
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Tiers: minimum number of cards => percent. Highest matching tier wins. */
function rk_mr_tiers() {
	return apply_filters( 'rk_mr_tiers', array( 30 => 25, 20 => 20, 10 => 10 ) );
}

function rk_mr_percent_for( $qty ) {
	foreach ( rk_mr_tiers() as $min => $pct ) {
		if ( $qty >= $min ) {
			return (float) $pct;
		}
	}
	return 0.0;
}

add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	// Not combinable: if a coupon is in the cart, it already won the comparison.
	if ( $cart->get_applied_coupons() ) {
		return;
	}
	$qty = (int) $cart->get_cart_contents_count();
	$pct = rk_mr_percent_for( $qty );
	if ( $pct <= 0 ) {
		return;
	}
	$base = (float) $cart->get_subtotal() - (float) $cart->get_discount_total();
	if ( $base <= 0 ) {
		return;
	}
	$amount = round( $base * $pct / 100, 2 );
	$cart->add_fee( sprintf( 'Mengenrabatt %s %% (ab %d Karten)', wc_format_localized_decimal( $pct ), rk_mr_min_for( $qty ) ), -$amount, false );
}, 20 );

function rk_mr_min_for( $qty ) {
	foreach ( rk_mr_tiers() as $min => $pct ) {
		if ( $qty >= $min ) {
			return (int) $min;
		}
	}
	return 0;
}

/** Hint in the cart how many cards are missing for the next tier. */
add_action( 'woocommerce_before_cart', 'rk_mr_hint' );
add_action( 'woocommerce_before_checkout_form', 'rk_mr_hint' );
function rk_mr_hint() {
	if ( ! WC()->cart ) {
		return;
	}
	$qty   = (int) WC()->cart->get_cart_contents_count();
	$tiers = rk_mr_tiers();
	ksort( $tiers );
	foreach ( $tiers as $min => $pct ) {
		if ( $qty < $min ) {
			$missing = $min - $qty;
			wc_print_notice( sprintf( 'Noch %d %s und du bekommst %s %% Mengenrabatt.', $missing, 1 === $missing ? 'Karte' : 'Karten', wc_format_localized_decimal( $pct ) ), 'notice' );
			return;
		}
	}
}

/**
 * Coupons and the volume discount do not stack. A coupon is only accepted
 * when it is worth more than the volume discount the cart already gets.
 */
add_filter( 'woocommerce_coupon_is_valid', function ( $valid, $coupon ) {
	if ( ! $valid || ! WC()->cart ) {
		return $valid;
	}
	$cart = WC()->cart;
	$pct  = rk_mr_percent_for( (int) $cart->get_cart_contents_count() );
	if ( $pct <= 0 ) {
		return $valid;
	}
	$subtotal    = (float) $cart->get_subtotal();
	$tier_amount = $subtotal * $pct / 100;
	$type        = $coupon->get_discount_type();
	$amount      = (float) $coupon->get_amount();
	$coupon_val  = ( 'percent' === $type ) ? $subtotal * $amount / 100 : $amount;
	if ( $coupon_val <= $tier_amount ) {
		throw new Exception( sprintf( 'Rabatte sind nicht kombinierbar. Du bekommst bereits %s %% Mengenrabatt, der ist höher als dieser Gutschein.', wc_format_localized_decimal( $pct ) ) );
	}
	return $valid;
}, 20, 2 );
