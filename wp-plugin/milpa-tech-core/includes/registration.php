<?php
/**
 * Producer / Trader / Investor signup.
 *
 * Dokan already adds a "customer / vendor" role choice to WooCommerce's
 * native registration form (My Account page) — that's the real WordPress-
 * native registration mechanism, so this builds on it instead of writing a
 * separate signup form from scratch:
 *
 * 1. Relabels "customer" -> Investor and "vendor" -> Producer/Trader, and
 *    injects a second-level Producer/Trader choice that only appears when
 *    "vendor" is picked (see the inline script in milpa_render_role_choice()).
 * 2. On `user_register` (after Dokan's own handler has already assigned
 *    its native "seller" or "customer" role), layers our custom role on
 *    top: producer/trader users end up with BOTH "seller" and
 *    "producer"/"trader" — this keeps every native Dokan feature (store
 *    pages, vendor dashboard, admin vendor list, order attribution)
 *    working exactly as Dokan expects, while still giving us a real,
 *    distinct role to query/display by. Investors get "customer" +
 *    "investor" the same way.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'woocommerce_register_form', 'milpa_render_role_choice', 20 );

function milpa_render_role_choice() {
	?>
	<p class="form-row form-row-wide milpa-vendor-type-row" style="display:none;">
		<label class="radio">
			<input type="radio" name="milpa_vendor_type" value="producer" checked="checked">
			<?php esc_html_e( 'Productor — quiero tokenizar un cultivo' ); ?>
		</label>
		<label class="radio">
			<input type="radio" name="milpa_vendor_type" value="trader">
			<?php esc_html_e( 'Comerciante — quiero vender insumos, semillas o servicios' ); ?>
		</label>
	</p>
	<script>
	(function () {
		var customerInput = document.querySelector( '.dokan-role-customer' );
		var sellerInput = document.querySelector( '.dokan-role-seller' );
		var vendorTypeRow = document.querySelector( '.milpa-vendor-type-row' );
		if ( ! customerInput || ! sellerInput || ! vendorTypeRow ) {
			return;
		}
		// Relabel Dokan's default English copy to match the app's roles.
		customerInput.parentElement.lastChild.textContent = <?php echo wp_json_encode( ' ' . __( 'Inversionista — quiero invertir en cultivos tokenizados' ) ); ?>;
		sellerInput.parentElement.lastChild.textContent = <?php echo wp_json_encode( ' ' . __( 'Productor o Comerciante — quiero vender en el marketplace' ) ); ?>;

		function toggle() {
			vendorTypeRow.style.display = sellerInput.checked ? '' : 'none';
		}
		customerInput.addEventListener( 'change', toggle );
		sellerInput.addEventListener( 'change', toggle );
		toggle();
	})();
	</script>
	<?php
}

add_action( 'user_register', 'milpa_apply_role_on_registration', 20 );

function milpa_apply_role_on_registration( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}

	if ( in_array( 'seller', (array) $user->roles, true ) ) {
		$vendor_type = isset( $_POST['milpa_vendor_type'] ) ? sanitize_key( wp_unslash( $_POST['milpa_vendor_type'] ) ) : 'producer';
		if ( ! in_array( $vendor_type, array( 'producer', 'trader' ), true ) ) {
			$vendor_type = 'producer';
		}
		$user->add_role( $vendor_type );
		update_user_meta( $user_id, 'milpa_vendor_type', $vendor_type );
	} elseif ( in_array( 'customer', (array) $user->roles, true ) ) {
		$user->add_role( 'investor' );
	}
}
