<?php
/**
 * Enqueues the Milpa Tech brand font (Inter, matching the original app)
 * and the brand stylesheet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'milpa_enqueue_brand_assets' );

function milpa_enqueue_brand_assets() {
	wp_enqueue_style(
		'milpa-inter-font',
		'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'milpa-brand',
		MILPA_CORE_URL . 'assets/css/milpa-brand.css',
		array(),
		MILPA_CORE_VERSION
	);
}
