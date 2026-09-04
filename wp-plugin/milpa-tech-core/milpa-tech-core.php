<?php
/**
 * Plugin Name: Milpa Tech Core
 * Description: Custom functionality for the Milpa Tech WordPress rebuild — crop investment data schema, marketplace card display, and brand assets. Requires WooCommerce and Advanced Custom Fields; Dokan recommended.
 * Version: 0.3.0
 * Author: Milpa Tech
 * Text Domain: milpa-tech-core
 *
 * This plugin is the canonical, version-controlled home for site logic that
 * used to live as ad-hoc Code Snippets entries during initial staging setup.
 * See /docs/BUILD-LOG.md in the repo root for the history of that migration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MILPA_CORE_VERSION', '0.3.0' );
define( 'MILPA_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MILPA_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once MILPA_CORE_PATH . 'includes/fields-crop-investment.php';
require_once MILPA_CORE_PATH . 'includes/assets.php';
require_once MILPA_CORE_PATH . 'includes/shop-display.php';
require_once MILPA_CORE_PATH . 'includes/branding.php';
require_once MILPA_CORE_PATH . 'includes/roles.php';
