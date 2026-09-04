<?php
/**
 * Investor portfolio — the original app's Investor Dashboard (portfolio
 * distribution, total assets, accumulated return, per-crop holdings
 * table) and Transaction Ledger, both rebuilt from real WooCommerce
 * order data instead of a mock in-memory array.
 *
 * Deliberately doesn't touch the existing "Dashboard" page — that's
 * Dokan's own [dokan-dashboard] shortcode, which already covers the
 * seller side (Producer/Trader orders, products, earnings, payouts)
 * correctly. This is the piece that was actually missing: nothing
 * aggregates a buyer's completed orders into "tokens held per crop."
 *
 * The ledger here shows real order data — date, crop, quantity, price,
 * order status — with no invented transaction hashes or blockchain
 * claims. The original app's ledger displayed fake tx hashes as a
 * visual device; carrying that into a real order history would present
 * something false as verified, so it's left out until Phase 3 (real
 * on-chain settlement, per docs/REBUILD-PLAN.md) makes it genuine.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'milpa_portfolio', 'milpa_render_portfolio' );

function milpa_is_crop_product( $product_id ) {
	// A "crop" is any product carrying our token_price field — plain
	// marketplace goods (seeds, machinery, services) don't have it.
	return ! empty( get_field( 'token_price', $product_id ) );
}

function milpa_render_portfolio() {
	if ( ! is_user_logged_in() ) {
		return '<p class="milpa-muted">' . sprintf(
			/* translators: %s: login URL */
			esc_html__( 'Inicia sesión para ver tu portafolio. %s' ),
			'<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Iniciar sesión' ) . '</a>'
		) . '</p>';
	}

	$user_id = get_current_user_id();
	$orders  = wc_get_orders( array(
		'customer_id' => $user_id,
		'status'      => array( 'completed', 'processing' ),
		'limit'       => -1,
	) );

	$holdings = array(); // product_id => tokens held
	$invested = array(); // product_id => MXN paid
	$ledger   = array();

	foreach ( $orders as $order ) {
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			if ( ! milpa_is_crop_product( $product_id ) ) {
				continue;
			}
			$qty   = $item->get_quantity();
			$total = (float) $item->get_total();

			$holdings[ $product_id ] = ( $holdings[ $product_id ] ?? 0 ) + $qty;
			$invested[ $product_id ] = ( $invested[ $product_id ] ?? 0 ) + $total;

			$ledger[] = array(
				'date'    => $order->get_date_created(),
				'crop'    => get_the_title( $product_id ),
				'qty'     => $qty,
				'total'   => $total,
				'status'  => wc_get_order_status_name( $order->get_status() ),
				'order_url' => $order->get_view_order_url(),
			);
		}
	}

	usort( $ledger, function ( $a, $b ) { return $b['date']->getTimestamp() <=> $a['date']->getTimestamp(); } );

	$total_invested = array_sum( $invested );
	$total_value    = 0;
	$total_tokens   = 0;
	foreach ( $holdings as $product_id => $qty ) {
		$current_price = (float) get_field( 'token_price', $product_id );
		$total_value  += $current_price * $qty;
		$total_tokens += $qty;
	}
	$return_pct = $total_invested > 0 ? round( ( ( $total_value - $total_invested ) / $total_invested ) * 100, 1 ) : 0;

	ob_start();
	?>
	<div class="milpa-portfolio">
		<div class="milpa-portfolio-summary">
			<div class="milpa-summary-card">
				<span class="milpa-summary-label"><?php esc_html_e( 'Valor Total de Activos' ); ?></span>
				<span class="milpa-summary-value"><?php echo wc_price( $total_value ); ?></span>
			</div>
			<div class="milpa-summary-card">
				<span class="milpa-summary-label"><?php esc_html_e( 'Total Invertido' ); ?></span>
				<span class="milpa-summary-value"><?php echo wc_price( $total_invested ); ?></span>
			</div>
			<div class="milpa-summary-card">
				<span class="milpa-summary-label"><?php esc_html_e( 'Retorno Acumulado' ); ?></span>
				<span class="milpa-summary-value <?php echo $return_pct >= 0 ? 'milpa-positive' : 'milpa-negative'; ?>"><?php echo esc_html( ( $return_pct >= 0 ? '+' : '' ) . $return_pct . '%' ); ?></span>
			</div>
			<div class="milpa-summary-card">
				<span class="milpa-summary-label"><?php esc_html_e( 'Tokens Totales' ); ?></span>
				<span class="milpa-summary-value"><?php echo esc_html( number_format_i18n( $total_tokens ) ); ?></span>
			</div>
		</div>

		<h3><?php esc_html_e( 'Mis Inversiones Activas' ); ?></h3>
		<?php if ( $holdings ) : ?>
			<div class="milpa-table-scroll">
				<table class="milpa-portfolio-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Cultivo' ); ?></th>
							<th><?php esc_html_e( 'Tokens' ); ?></th>
							<th><?php esc_html_e( 'Precio Promedio de Entrada' ); ?></th>
							<th><?php esc_html_e( 'Precio Actual' ); ?></th>
							<th><?php esc_html_e( 'Valor Actual' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $holdings as $product_id => $qty ) :
							$entry_price   = $invested[ $product_id ] / $qty;
							$current_price = (float) get_field( 'token_price', $product_id );
							?>
							<tr>
								<td><a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>"><?php echo esc_html( get_the_title( $product_id ) ); ?></a></td>
								<td><?php echo esc_html( number_format_i18n( $qty ) ); ?></td>
								<td><?php echo wc_price( $entry_price ); ?></td>
								<td><?php echo wc_price( $current_price ); ?></td>
								<td><?php echo wc_price( $current_price * $qty ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="milpa-muted"><?php esc_html_e( 'Aún no tienes inversiones.' ); ?> <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>"><?php esc_html_e( '¡Visita el Marketplace!' ); ?></a></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Historial de Compras' ); ?></h3>
		<p class="milpa-muted milpa-ledger-note"><?php esc_html_e( 'Registro real de tus órdenes. El asentamiento verificable en blockchain llegará en una fase posterior, sujeta a revisión legal — ver la hoja de ruta del proyecto.' ); ?></p>
		<?php if ( $ledger ) : ?>
			<div class="milpa-table-scroll">
				<table class="milpa-portfolio-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Fecha' ); ?></th>
							<th><?php esc_html_e( 'Cultivo' ); ?></th>
							<th><?php esc_html_e( 'Tokens' ); ?></th>
							<th><?php esc_html_e( 'Monto' ); ?></th>
							<th><?php esc_html_e( 'Estado' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $ledger as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['date']->date_i18n( 'j M Y' ) ); ?></td>
								<td><?php echo esc_html( $row['crop'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['qty'] ) ); ?></td>
								<td><?php echo wc_price( $row['total'] ); ?></td>
								<td><a href="<?php echo esc_url( $row['order_url'] ); ?>"><?php echo esc_html( $row['status'] ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="milpa-muted"><?php esc_html_e( 'Aún no hay transacciones.' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
