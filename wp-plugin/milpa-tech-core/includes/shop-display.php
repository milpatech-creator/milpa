<?php
/**
 * Renders the risk badge, funding progress bar, and projected yield on
 * each crop's card in the WooCommerce shop/archive loop, pulled from the
 * ACF "Crop Investment Details" fields. Products without those fields
 * (e.g. plain marketplace inputs/services) are left untouched.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'woocommerce_after_shop_loop_item_title', 'milpa_render_crop_investment_summary', 5 );

function milpa_render_crop_investment_summary() {
	global $product;

	if ( ! $product || ! function_exists( 'get_field' ) ) {
		return;
	}

	$product_id = $product->get_id();
	$risk       = get_field( 'risk_level', $product_id );
	$total      = (float) get_field( 'total_tokens', $product_id );
	$sold       = (float) get_field( 'sold_tokens', $product_id );
	$yield      = get_field( 'yield_projection', $product_id );

	if ( ! $risk && ! $total ) {
		return; // Not a tokenized crop listing — nothing to show.
	}

	if ( $risk ) {
		printf(
			'<span class="milpa-risk-badge milpa-risk-%1$s">%1$s</span>',
			esc_attr( $risk )
		);
	}

	if ( $total > 0 ) {
		$pct = min( 100, round( ( $sold / $total ) * 100 ) );
		printf(
			'<div class="milpa-fund-bar"><div class="milpa-fund-bar-fill" style="width:%1$s%%"></div></div>',
			esc_attr( $pct )
		);
		printf(
			'<div class="milpa-fund-label"><span>%1$s / %2$s tokens</span><span>%3$s%%</span></div>',
			esc_html( number_format_i18n( $sold ) ),
			esc_html( number_format_i18n( $total ) ),
			esc_html( $pct )
		);
	}

	if ( $yield ) {
		printf(
			'<div class="milpa-yield">Rendimiento proyectado: <b>%s%%</b></div>',
			esc_html( $yield )
		);
	}
}
