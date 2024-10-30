<?php
namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Register the categories
Plugin::$instance->elements_manager->add_category(
	'cpis-image-store-cat',
	array(
		'title' => 'Image Store',
		'icon'  => 'fa fa-plug',
	),
	2 // position
);
