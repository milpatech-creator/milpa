<?php
/**
 * Registers the "Crop Investment Details" ACF field group on WooCommerce
 * products. This is the schema that turns a plain WooCommerce product into
 * a tokenized crop listing: location, risk, token economics, harvest date,
 * sustainability score, NFT/traceability fields (kept blank until the
 * Phase 3 legal review clears real on-chain tokenization), and a manually
 * updated development log.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'acf/init', 'milpa_register_crop_fields' );

function milpa_register_crop_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key'      => 'group_milpa_crop_investment',
		'title'    => 'Crop Investment Details',
		'fields'   => array(
			array( 'key' => 'field_milpa_tab_investment', 'label' => 'Investment Details', 'name' => '', 'type' => 'tab' ),
			array(
				'key'          => 'field_milpa_crop_location',
				'label'        => 'Location',
				'name'         => 'crop_location',
				'type'         => 'text',
				'instructions' => 'e.g. Oaxaca, Mexico',
				'required'     => 1,
			),
			array(
				'key'      => 'field_milpa_crop_type',
				'label'    => 'Crop Type',
				'name'     => 'crop_type',
				'type'     => 'select',
				'choices'  => array(
					'Granos'     => 'Granos',
					'Cafe'       => 'Cafe',
					'Frutales'   => 'Frutales',
					'Agave'      => 'Agave',
					'Hortalizas' => 'Hortalizas',
					'Otro'       => 'Otro',
				),
				'required' => 1,
				'ui'       => 1,
			),
			array(
				'key'      => 'field_milpa_risk_level',
				'label'    => 'Risk Level',
				'name'     => 'risk_level',
				'type'     => 'select',
				'choices'  => array(
					'BAJO'  => 'BAJO (Low)',
					'MEDIO' => 'MEDIO (Medium)',
					'ALTO'  => 'ALTO (High)',
				),
				'required' => 1,
				'ui'       => 1,
			),
			array(
				'key'          => 'field_milpa_token_price',
				'label'        => 'Price per Token (MXN)',
				'name'         => 'token_price',
				'type'         => 'number',
				'instructions' => 'Keep in sync with the WooCommerce product price.',
				'required'     => 1,
				'min'          => 0,
				'step'         => 0.01,
			),
			array(
				'key'      => 'field_milpa_total_tokens',
				'label'    => 'Total Tokens Issued',
				'name'     => 'total_tokens',
				'type'     => 'number',
				'required' => 1,
				'min'      => 1,
			),
			array(
				'key'          => 'field_milpa_sold_tokens',
				'label'        => 'Tokens Sold',
				'name'         => 'sold_tokens',
				'type'         => 'number',
				'instructions' => 'Manual for now — a later pass will compute this from completed WooCommerce orders instead of being hand-entered.',
				'min'          => 0,
			),
			array(
				'key'   => 'field_milpa_yield_projection',
				'label' => 'Projected Annual Yield (%)',
				'name'  => 'yield_projection',
				'type'  => 'number',
				'step'  => 0.1,
			),
			array(
				'key'            => 'field_milpa_harvest_date',
				'label'          => 'Estimated Harvest Date',
				'name'           => 'harvest_date',
				'type'           => 'date_picker',
				'display_format' => 'd/m/Y',
				'return_format'  => 'Y-m-d',
			),
			array(
				'key'   => 'field_milpa_sustainability_score',
				'label' => 'Sustainability Score (0-100)',
				'name'  => 'sustainability_score',
				'type'  => 'range',
				'min'   => 0,
				'max'   => 100,
				'step'  => 1,
			),
			array( 'key' => 'field_milpa_tab_nft', 'label' => 'NFT / Blockchain Traceability', 'name' => '', 'type' => 'tab' ),
			array(
				'key'          => 'field_milpa_nft_contract',
				'label'        => 'NFT Contract Address',
				'name'         => 'nft_contract',
				'type'         => 'text',
				'instructions' => 'Leave blank until Phase 3 (real on-chain tokenization) is legally cleared.',
			),
			array( 'key' => 'field_milpa_nft_token_id', 'label' => 'NFT Token ID', 'name' => 'nft_token_id', 'type' => 'text' ),
			array(
				'key'       => 'field_milpa_nft_standard',
				'label'     => 'NFT Standard',
				'name'      => 'nft_standard',
				'type'      => 'select',
				'choices'   => array( 'ERC-721' => 'ERC-721', 'ERC-1155' => 'ERC-1155' ),
				'allow_null' => 1,
				'ui'        => 1,
			),
			array( 'key' => 'field_milpa_opensea_url', 'label' => 'OpenSea Link', 'name' => 'opensea_url', 'type' => 'url' ),
			array( 'key' => 'field_milpa_tab_update', 'label' => 'Latest Field Update', 'name' => '', 'type' => 'tab' ),
			array(
				'key'          => 'field_milpa_development_stage',
				'label'        => 'Current Development Stage',
				'name'         => 'development_stage',
				'type'         => 'text',
				'instructions' => 'e.g. Vegetative Growth and Grain Filling',
			),
			array(
				'key'   => 'field_milpa_development_progress',
				'label' => 'Progress to Harvest (%)',
				'name'  => 'development_progress_pct',
				'type'  => 'range',
				'min'   => 0,
				'max'   => 100,
				'step'  => 1,
			),
			array( 'key' => 'field_milpa_days_to_harvest', 'label' => 'Days to Harvest', 'name' => 'days_to_harvest', 'type' => 'number', 'min' => 0 ),
			array(
				'key'   => 'field_milpa_health_score',
				'label' => 'Crop Health Score (0-100)',
				'name'  => 'health_score',
				'type'  => 'range',
				'min'   => 0,
				'max'   => 100,
				'step'  => 1,
			),
			array(
				'key'          => 'field_milpa_latest_update_note',
				'label'        => 'Latest Update Note',
				'name'         => 'latest_update_note',
				'type'         => 'textarea',
				'instructions' => 'Short producer-written update, shown on the crop listing.',
				'rows'         => 3,
			),
		),
		'location' => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'product',
				),
			),
		),
		'menu_order'             => 0,
		'position'               => 'normal',
		'style'                  => 'default',
		'label_placement'        => 'top',
		'instruction_placement'  => 'label',
		'active'                 => true,
		'description'            => 'Tokenized crop investment data shown on WooCommerce Crop product listings.',
	) );
}
