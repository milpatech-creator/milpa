<?php
/**
 * Peer review system — the original app's PeerReview type (rating,
 * comment, endorsements, tied to a target user + an author). Stored as a
 * "milpa_review" CPT rather than a plugin: post_author is the reviewer
 * (WordPress already tracks that natively, no need for a separate meta
 * field for it), post_content is the comment, and target/rating/
 * endorsements are post meta.
 *
 * One review per (author, target) pair — resubmitting updates the
 * existing review rather than stacking duplicates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_post_type( 'milpa_review', array(
		'label'    => 'Milpa Reviews',
		'public'   => false,
		'show_ui'  => true,
		'show_in_menu' => 'edit.php?post_type=product', // tuck it under Products rather than a top-level menu item
		'supports' => array( 'title', 'author', 'editor' ),
		'capability_type' => 'post',
		// REST-exposed so review posts can be created the same way
		// products/media are elsewhere in this plugin (Application
		// Password + curl) — not because the front end talks to this
		// route, it doesn't, the submission form posts to admin-post.php
		// instead, which uses plain update_post_meta() directly and so
		// doesn't need any of this.
		//
		// Deliberately NOT using register_post_meta() to expose
		// target_user_id/rating/endorsements as a REST "meta" field —
		// tried it, every request to this route started 500ing (see
		// BUILD-LOG.md) for a reason I didn't chase down since the real
		// feature doesn't need it. Meta reads/writes for this post type
		// go through update_post_meta()/get_post_meta() directly instead,
		// via a one-off authenticated REST route when scripting against
		// the live site (see the git history for fix-review-meta as the
		// pattern to reuse, though that specific route was temporary and
		// has been removed).
		'show_in_rest' => true,
		'rest_base'    => 'milpa_review',
	) );
} );

function milpa_reviewable_roles() {
	return array( 'producer', 'trader', 'investor' );
}

/**
 * Recomputes and caches the target's average rating + count as user meta,
 * so the directory grid can display it without aggregating on every
 * pageview.
 */
function milpa_recalculate_rating( $target_user_id ) {
	$query = new WP_Query( array(
		'post_type'      => 'milpa_review',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => 'target_user_id',
		'meta_value'     => $target_user_id,
		'fields'         => 'ids',
	) );

	$count = count( $query->posts );
	$sum   = 0;
	foreach ( $query->posts as $review_id ) {
		$sum += (int) get_post_meta( $review_id, 'rating', true );
	}
	$avg = $count > 0 ? round( $sum / $count, 1 ) : 0;

	update_user_meta( $target_user_id, 'milpa_rating_avg', $avg );
	update_user_meta( $target_user_id, 'milpa_review_count', $count );
}

/**
 * Recalculate whenever a review is saved via REST too (seeding/admin use),
 * not just through the front-end admin-post.php handler below.
 */
add_action( 'rest_after_insert_milpa_review', function ( $post ) {
	$target_id = (int) get_post_meta( $post->ID, 'target_user_id', true );
	if ( $target_id ) {
		milpa_recalculate_rating( $target_id );
	}
} );

/**
 * Front-end submission handler. Not a REST route — a plain form POST via
 * admin-post.php, since this only ever needs a redirect-back-with-notice
 * flow, not a JSON API.
 */
add_action( 'admin_post_milpa_submit_review', 'milpa_handle_review_submission' );

function milpa_handle_review_submission() {
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'You must be logged in to leave a review.' ) );
	}
	check_admin_referer( 'milpa_submit_review' );

	$author_id = get_current_user_id();
	$target_id = isset( $_POST['target_user_id'] ) ? absint( $_POST['target_user_id'] ) : 0;
	$rating    = isset( $_POST['rating'] ) ? max( 1, min( 5, absint( $_POST['rating'] ) ) ) : 0;
	$comment   = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
	$endorse_raw = isset( $_POST['endorsements'] ) ? sanitize_text_field( wp_unslash( $_POST['endorsements'] ) ) : '';
	$endorsements = array_filter( array_map( 'trim', explode( ',', $endorse_raw ) ) );

	$target = get_userdata( $target_id );
	$valid_target = $target && array_intersect( milpa_reviewable_roles(), (array) $target->roles );

	if ( ! $valid_target || $target_id === $author_id || ! $rating || '' === $comment ) {
		wp_safe_redirect( add_query_arg( 'milpa_review_error', '1', wp_get_referer() ?: home_url() ) );
		exit;
	}

	// One review per (author, target) — find and update instead of duplicating.
	$existing = get_posts( array(
		'post_type'      => 'milpa_review',
		'author'         => $author_id,
		'meta_key'       => 'target_user_id',
		'meta_value'     => $target_id,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );

	$post_data = array(
		'post_type'    => 'milpa_review',
		'post_status'  => 'publish',
		'post_title'   => sprintf( 'Review of #%d by #%d', $target_id, $author_id ),
		'post_content' => $comment,
		'post_author'  => $author_id,
	);

	if ( $existing ) {
		$post_data['ID'] = $existing[0];
		wp_update_post( $post_data );
		$review_id = $existing[0];
	} else {
		$review_id = wp_insert_post( $post_data );
	}

	update_post_meta( $review_id, 'target_user_id', $target_id );
	update_post_meta( $review_id, 'rating', $rating );
	update_post_meta( $review_id, 'endorsements', implode( ', ', $endorsements ) );

	milpa_recalculate_rating( $target_id );

	wp_safe_redirect( add_query_arg( 'milpa_review_ok', '1', wp_get_referer() ?: home_url() ) );
	exit;
}

/**
 * Renders the review list + submission form for one target user.
 * Called from the directory shortcode's single-profile view.
 */
function milpa_render_reviews_section( $target_id ) {
	$reviews = new WP_Query( array(
		'post_type'      => 'milpa_review',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => 'target_user_id',
		'meta_value'     => $target_id,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	ob_start();
	?>
	<div class="milpa-reviews">
		<h3><?php esc_html_e( 'Reseñas' ); ?> (<?php echo (int) get_user_meta( $target_id, 'milpa_review_count', true ); ?>)</h3>

		<?php if ( isset( $_GET['milpa_review_ok'] ) ) : ?>
			<p class="milpa-notice milpa-notice-ok"><?php esc_html_e( '¡Gracias! Tu reseña fue publicada.' ); ?></p>
		<?php elseif ( isset( $_GET['milpa_review_error'] ) ) : ?>
			<p class="milpa-notice milpa-notice-error"><?php esc_html_e( 'No se pudo publicar la reseña. Verifica los campos e intenta de nuevo.' ); ?></p>
		<?php endif; ?>

		<?php if ( $reviews->have_posts() ) : ?>
			<div class="milpa-review-list">
				<?php while ( $reviews->have_posts() ) : $reviews->the_post();
					$review_id    = get_the_ID();
					$author_id    = get_post_field( 'post_author', $review_id );
					$author       = get_userdata( $author_id );
					$rating       = (int) get_post_meta( $review_id, 'rating', true );
					$endorsements = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $review_id, 'endorsements', true ) ) ) );
					?>
					<div class="milpa-review">
						<div class="milpa-review-head">
							<img src="<?php echo esc_url( get_avatar_url( $author_id, array( 'size' => 36 ) ) ); ?>" alt="" class="milpa-review-avatar">
							<div>
								<strong><?php echo esc_html( $author ? $author->display_name : __( 'Usuario' ) ); ?></strong>
								<span class="milpa-review-stars"><?php echo esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) ); ?></span>
							</div>
							<span class="milpa-review-date"><?php echo esc_html( get_the_date( 'j M Y', $review_id ) ); ?></span>
						</div>
						<p class="milpa-review-comment"><?php echo esc_html( get_the_content() ); ?></p>
						<?php if ( $endorsements ) : ?>
							<div class="milpa-review-endorsements">
								<?php foreach ( $endorsements as $tag ) : ?>
									<span class="milpa-endorsement-pill"><?php echo esc_html( $tag ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		<?php else : ?>
			<p class="milpa-muted"><?php esc_html_e( 'Aún no hay reseñas.' ); ?></p>
		<?php endif; ?>

		<?php if ( is_user_logged_in() && get_current_user_id() !== (int) $target_id ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="milpa-review-form">
				<?php wp_nonce_field( 'milpa_submit_review' ); ?>
				<input type="hidden" name="action" value="milpa_submit_review">
				<input type="hidden" name="target_user_id" value="<?php echo esc_attr( $target_id ); ?>">
				<h4><?php esc_html_e( 'Deja tu reseña' ); ?></h4>
				<label class="milpa-star-input">
					<?php esc_html_e( 'Calificación' ); ?>
					<select name="rating" required>
						<option value="5">★★★★★ (5)</option>
						<option value="4">★★★★☆ (4)</option>
						<option value="3">★★★☆☆ (3)</option>
						<option value="2">★★☆☆☆ (2)</option>
						<option value="1">★☆☆☆☆ (1)</option>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'Comentario' ); ?>
					<textarea name="comment" rows="3" required></textarea>
				</label>
				<label>
					<?php esc_html_e( 'Reconocimientos (separados por coma, opcional)' ); ?>
					<input type="text" name="endorsements" placeholder="<?php esc_attr_e( 'Pagos Puntuales, Comunicación Constante' ); ?>">
				</label>
				<button type="submit" class="milpa-btn"><?php esc_html_e( 'Publicar Reseña' ); ?></button>
			</form>
		<?php elseif ( ! is_user_logged_in() ) : ?>
			<p class="milpa-muted"><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Inicia sesión para dejar una reseña' ); ?></a></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
