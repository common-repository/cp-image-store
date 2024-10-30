<?php
/*
Widget Name: Image Store Product
Description: Inserts a product's shortcode.
Documentation: https://wordpress.dwbooster.com/content-tools/image-store
*/

class SiteOrigin_ImageStore_Product extends SiteOrigin_Widget {

	public function __construct() {
		parent::__construct(
			'siteorigin-imagestore-product',
			__( 'Image Store Product', 'cp-image-store' ),
			array(
				'description'   => __( 'Inserts the Product shortcode', 'cp-image-store' ),
				'panels_groups' => array( 'cpis-image-store' ),
				'help'          => 'https://wordpress.dwbooster.com/content-tools/image-store',
			),
			array(),
			array(
				'product' => array(
					'type'  => 'number',
					'label' => __( "Enter the product's id", 'cp-image-store' ),
				),
				'layout'  => array(
					'type'    => 'select',
					'label'   => __( "Select the product's layout", 'cp-image-store' ),
					'default' => 'single',
					'options' => array(
						'multiple' => __( 'Short', 'cp-image-store' ),
						'single'   => __( 'Completed', 'cp-image-store' ),
					),
				),
			),
			plugin_dir_path( __FILE__ )
		);
	} // End __construct

	public function get_template_name( $instance ) {
		return 'siteorigin-cpis-product-shortcode';
	} // End get_template_name

	public function get_style_name( $instance ) {
		return '';
	} // End get_style_name

} // End Class SiteOrigin_ImageStore_Product

// Registering the widget
siteorigin_widget_register( 'siteorigin-imagestore-product', __FILE__, 'SiteOrigin_ImageStore_Product' );
