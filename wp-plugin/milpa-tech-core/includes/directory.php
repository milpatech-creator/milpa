<?php
/**
 * Community directory — the original app's CommunityDirectoryView.tsx:
 * a filterable list of Producers/Investors/Traders, each linking to a
 * profile with peer reviews (see reviews.php). One shortcode,
 * [milpa_directory], with two render modes selected by a query var:
 * the grid (default) and a single profile (?milpa_user=ID).
 *
 * Deliberately not built on BuddyPress's own Members directory, even
 * though BuddyPress is active — that directory doesn't know about our
 * Producer/Trader/Investor roles or ratings, and retrofitting its
 * templates would fight the plugin more than just querying WP_User_Query
 * directly here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'milpa_directory', 'milpa_render_directory' );

add_action( 'init', function () {
	register_meta( 'user', 'milpa_location', array(
		'type' => 'string', 'single' => true, 'show_in_rest' => true,
		'auth_callback' => function () { return current_user_can( 'edit_users' ); },
	) );
} );

/** A simple, free-text location field, same profile-editing pattern as vendor_type in roles.php. */
add_action( 'show_user_profile', 'milpa_render_location_field' );
add_action( 'edit_user_profile', 'milpa_render_location_field' );
function milpa_render_location_field( $user ) {
	$value = get_user_meta( $user->ID, 'milpa_location', true );
	?>
	<table class="form-table">
		<tr>
			<th><label for="milpa_location"><?php esc_html_e( 'Ubicación (Directorio Milpa Tech)' ); ?></label></th>
			<td><input type="text" name="milpa_location" id="milpa_location" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="Oaxaca, México"></td>
		</tr>
	</table>
	<?php
}
add_action( 'personal_options_update', 'milpa_save_location_field' );
add_action( 'edit_user_profile_update', 'milpa_save_location_field' );
function milpa_save_location_field( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['milpa_location'] ) ) {
		return;
	}
	update_user_meta( $user_id, 'milpa_location', sanitize_text_field( wp_unslash( $_POST['milpa_location'] ) ) );
}

function milpa_role_label( $role ) {
	$labels = array(
		'producer' => 'Productor',
		'trader'   => 'Comerciante',
		'investor' => 'Inversionista',
	);
	return $labels[ $role ] ?? ucfirst( $role );
}

function milpa_render_directory() {
	$user_id = isset( $_GET['milpa_user'] ) ? absint( $_GET['milpa_user'] ) : 0;
	if ( $user_id ) {
		return milpa_render_single_profile( $user_id );
	}
	return milpa_render_directory_grid();
}

function milpa_render_directory_grid() {
	$active_role = isset( $_GET['milpa_role'] ) ? sanitize_key( $_GET['milpa_role'] ) : 'all';
	$roles       = array( 'all', 'producer', 'trader', 'investor' );
	if ( ! in_array( $active_role, $roles, true ) ) {
		$active_role = 'all';
	}

	$query_args = array(
		'role__in' => 'all' === $active_role ? array( 'producer', 'trader', 'investor' ) : array( $active_role ),
		'orderby'  => 'display_name',
		'order'    => 'ASC',
	);
	$users = get_users( $query_args );

	ob_start();
	?>
	<div class="milpa-directory">
		<div class="milpa-directory-tabs">
			<?php foreach ( $roles as $role ) :
				$url = esc_url( add_query_arg( 'milpa_role', $role, remove_query_arg( 'milpa_user' ) ) );
				$label = 'all' === $role ? __( 'Todos' ) : milpa_role_label( $role ) . 's';
				?>
				<a href="<?php echo $url; ?>" class="milpa-tab <?php echo $active_role === $role ? 'is-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>

		<?php if ( $users ) : ?>
			<div class="milpa-directory-grid">
				<?php foreach ( $users as $user ) :
					$roles_display = array_values( array_intersect( array( 'producer', 'trader', 'investor' ), (array) $user->roles ) );
					$primary_role  = $roles_display[0] ?? 'investor';
					$avg    = (float) get_user_meta( $user->ID, 'milpa_rating_avg', true );
					$count  = (int) get_user_meta( $user->ID, 'milpa_review_count', true );
					$location = get_user_meta( $user->ID, 'milpa_location', true );
					$profile_url = esc_url( add_query_arg( 'milpa_user', $user->ID ) );
					?>
					<a href="<?php echo $profile_url; ?>" class="milpa-directory-card">
						<img src="<?php echo esc_url( get_avatar_url( $user->ID, array( 'size' => 64 ) ) ); ?>" alt="" class="milpa-directory-avatar">
						<div class="milpa-directory-name"><?php echo esc_html( $user->display_name ); ?></div>
						<span class="milpa-role-pill milpa-role-<?php echo esc_attr( $primary_role ); ?>"><?php echo esc_html( milpa_role_label( $primary_role ) ); ?></span>
						<?php if ( $location ) : ?>
							<div class="milpa-directory-location"><?php echo esc_html( $location ); ?></div>
						<?php endif; ?>
						<div class="milpa-directory-rating">
							<?php if ( $count > 0 ) : ?>
								<?php echo esc_html( str_repeat( '★', round( $avg ) ) . str_repeat( '☆', 5 - round( $avg ) ) ); ?>
								<span class="milpa-muted">(<?php echo $count; ?>)</span>
							<?php else : ?>
								<span class="milpa-muted"><?php esc_html_e( 'Sin reseñas aún' ); ?></span>
							<?php endif; ?>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="milpa-muted"><?php esc_html_e( 'Aún no hay usuarios registrados en esta categoría.' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function milpa_render_single_profile( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! array_intersect( array( 'producer', 'trader', 'investor' ), (array) $user->roles ) ) {
		return '<p>' . esc_html__( 'Usuario no encontrado.' ) . '</p>';
	}

	$roles_display = array_values( array_intersect( array( 'producer', 'trader', 'investor' ), (array) $user->roles ) );
	$avg     = (float) get_user_meta( $user_id, 'milpa_rating_avg', true );
	$count   = (int) get_user_meta( $user_id, 'milpa_review_count', true );
	$location = get_user_meta( $user_id, 'milpa_location', true );
	$bio      = get_the_author_meta( 'description', $user_id );
	$back_url = esc_url( remove_query_arg( 'milpa_user' ) );

	ob_start();
	?>
	<div class="milpa-directory milpa-profile">
		<a href="<?php echo $back_url; ?>" class="milpa-back-link">&larr; <?php esc_html_e( 'Volver al directorio' ); ?></a>

		<div class="milpa-profile-head">
			<img src="<?php echo esc_url( get_avatar_url( $user_id, array( 'size' => 96 ) ) ); ?>" alt="" class="milpa-profile-avatar">
			<div>
				<h2><?php echo esc_html( $user->display_name ); ?></h2>
				<?php foreach ( $roles_display as $r ) : ?>
					<span class="milpa-role-pill milpa-role-<?php echo esc_attr( $r ); ?>"><?php echo esc_html( milpa_role_label( $r ) ); ?></span>
				<?php endforeach; ?>
				<?php if ( $location ) : ?><div class="milpa-directory-location"><?php echo esc_html( $location ); ?></div><?php endif; ?>
				<div class="milpa-directory-rating">
					<?php if ( $count > 0 ) : ?>
						<?php echo esc_html( str_repeat( '★', round( $avg ) ) . str_repeat( '☆', 5 - round( $avg ) ) ); ?>
						<span class="milpa-muted"><?php echo esc_html( $avg ); ?> (<?php echo (int) $count; ?> <?php esc_html_e( 'reseñas' ); ?>)</span>
					<?php else : ?>
						<span class="milpa-muted"><?php esc_html_e( 'Sin reseñas aún' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php if ( $bio ) : ?><p class="milpa-profile-bio"><?php echo esc_html( $bio ); ?></p><?php endif; ?>

		<?php if ( in_array( 'producer', (array) $user->roles, true ) || in_array( 'trader', (array) $user->roles, true ) ) :
			$store_url = function_exists( 'dokan_get_store_url' ) ? dokan_get_store_url( $user_id ) : '';
			if ( $store_url ) : ?>
				<a class="milpa-btn" href="<?php echo esc_url( $store_url ); ?>"><?php esc_html_e( 'Ver tienda' ); ?></a>
			<?php endif;
		endif; ?>

		<?php echo milpa_render_reviews_section( $user_id ); ?>
	</div>
	<?php
	return ob_get_clean();
}
