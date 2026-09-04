<?php
/**
 * Producer / Investor / Trader roles.
 *
 * The app has three signup paths (see types.ts UserProfile.role), but WP/
 * Dokan/WooCommerce only natively know about two capability sets: Dokan's
 * "seller" (a store owner who can list and manage products) and
 * WooCommerce's "customer" (can browse and buy, nothing more).
 *
 * Producers and Traders are both sellers in Dokan's terms — a Producer
 * lists crop-token products, a Trader lists marketplace goods/services —
 * so both get the "seller" capability set, and a `milpa_vendor_type` user
 * meta field (set at registration) tells them apart for display/filtering.
 * Investors get the plain "customer" set.
 *
 * Capabilities are cloned live from whatever Dokan/WooCommerce actually
 * registered, rather than hardcoded here, so this stays correct even if
 * those plugins change their capability list in an update.
 *
 * Runs on `init` (not just an activation hook) and is idempotent, because
 * deploys to this site go over FTP, which doesn't trigger WordPress's
 * plugin-activation hook the way installing through wp-admin would.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'milpa_ensure_roles', 20 );

function milpa_ensure_roles() {
	if ( ! get_role( 'producer' ) ) {
		milpa_clone_role( 'seller', 'producer', 'Producer' );
	}
	if ( ! get_role( 'trader' ) ) {
		milpa_clone_role( 'seller', 'trader', 'Trader' );
	}
	if ( ! get_role( 'investor' ) ) {
		milpa_clone_role( 'customer', 'investor', 'Investor' );
	}
}

function milpa_clone_role( $source_slug, $new_slug, $display_name ) {
	$source = get_role( $source_slug );
	if ( ! $source ) {
		// Source role not registered yet (e.g. Dokan/WooCommerce not active) —
		// milpa_ensure_roles() will retry on the next request.
		return;
	}
	add_role( $new_slug, $display_name, $source->capabilities );
}

/**
 * Vendor type: distinguishes Producer stores (crop tokens) from Trader
 * stores (inputs/seeds/machinery/services) — both are Dokan "seller"-
 * capability holders, so this is the only thing telling them apart.
 * Exposed as a profile field for admins; set programmatically at
 * registration once the signup flow is built.
 */
add_action( 'show_user_profile', 'milpa_render_vendor_type_field' );
add_action( 'edit_user_profile', 'milpa_render_vendor_type_field' );

function milpa_render_vendor_type_field( $user ) {
	if ( ! in_array( 'producer', (array) $user->roles, true ) && ! in_array( 'trader', (array) $user->roles, true ) ) {
		return;
	}
	$value = get_user_meta( $user->ID, 'milpa_vendor_type', true );
	?>
	<h2><?php esc_html_e( 'Milpa Tech' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><label for="milpa_vendor_type"><?php esc_html_e( 'Vendor Type' ); ?></label></th>
			<td>
				<select name="milpa_vendor_type" id="milpa_vendor_type">
					<option value="producer" <?php selected( $value, 'producer' ); ?>><?php esc_html_e( 'Producer — crop token listings' ); ?></option>
					<option value="trader" <?php selected( $value, 'trader' ); ?>><?php esc_html_e( 'Trader — marketplace goods/services' ); ?></option>
				</select>
			</td>
		</tr>
	</table>
	<?php
}

add_action( 'personal_options_update', 'milpa_save_vendor_type_field' );
add_action( 'edit_user_profile_update', 'milpa_save_vendor_type_field' );

function milpa_save_vendor_type_field( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['milpa_vendor_type'] ) ) {
		return;
	}
	$value = sanitize_key( wp_unslash( $_POST['milpa_vendor_type'] ) );
	if ( in_array( $value, array( 'producer', 'trader' ), true ) ) {
		update_user_meta( $user_id, 'milpa_vendor_type', $value );
	}
}
