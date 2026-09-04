<?php
/**
 * Site logo + favicon, sourced from the original export's SVG mark
 * (public/milpa_tech_logo_transparent.svg — the corn/circuit gradient icon,
 * no background). Injected via JS/wp_head rather than the Customizer's
 * Site Icon/Logo pickers, since those require a Media Library upload and
 * this environment doesn't have file-upload access to that admin screen.
 *
 * Twenty Twenty-Five is a block theme, so there's no classic header.php to
 * edit directly — `.wp-block-site-title a` is the generic core-block
 * selector this targets instead, confirmed against the live markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', 'milpa_render_favicon' );

function milpa_render_favicon() {
	printf(
		'<link rel="icon" type="image/svg+xml" href="%s">',
		esc_url( MILPA_CORE_URL . 'assets/img/milpa-mark.svg' )
	);
}

add_action( 'wp_footer', 'milpa_render_header_logo_script' );

function milpa_render_header_logo_script() {
	$mark_url = esc_url( MILPA_CORE_URL . 'assets/img/milpa-mark.svg' );
	?>
	<script>
	(function () {
		var titleLink = document.querySelector( '.wp-block-site-title a' );
		if ( ! titleLink || titleLink.querySelector( '.milpa-mark' ) ) {
			return;
		}
		var img = document.createElement( 'img' );
		img.src = '<?php echo $mark_url; ?>';
		img.alt = '';
		img.className = 'milpa-mark';
		titleLink.prepend( img );
	})();
	</script>
	<?php
}
