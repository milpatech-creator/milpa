<?php
/**
 * Security fixes found during the 2026-09-04 audit (see BUILD-LOG.md for
 * the full scan). Not new features — patches for real issues found live
 * on the site, kept in their own file so the fix and its reasoning stay
 * traceable.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FIX 1 — anonymous REST user enumeration leak.
 *
 * `GET /wp-json/wp/v2/users` with NO authentication was returning, for
 * every user on the site: `is_super_admin` (telling an anonymous caller
 * exactly which account is the highest-value target for a credential
 * attack), the full `meta` object (including our own milpa_location),
 * `woocommerce_meta` (internal admin UI state), and `dokan_meta`
 * (vendor_id). None of that is core WordPress's default anonymous
 * response — core only exposes id/name/url/description/link/slug/
 * avatar_urls to unauthenticated requests, which is expected and needed
 * (storefronts show seller names, post bylines, etc.). Something in the
 * Dokan/WooCommerce stack broadened the schema without gating it to
 * authenticated context.
 *
 * Rather than track down which exact plugin filter is responsible (would
 * need a bisect across the plugin list to be sure), this strips the
 * extra fields at the output layer for anyone who doesn't hold
 * `list_users` — safe regardless of which plugin re-adds them later,
 * and doesn't change anything for real logged-in admin use, where the
 * extra fields still show and are still useful.
 */
add_filter( 'rest_prepare_user', function ( $response, $user, $request ) {
	if ( current_user_can( 'list_users' ) ) {
		return $response;
	}
	$data = $response->get_data();
	unset( $data['is_super_admin'], $data['woocommerce_meta'], $data['dokan_meta'], $data['meta'] );
	$response->set_data( $data );
	return $response;
}, 10, 3 );
