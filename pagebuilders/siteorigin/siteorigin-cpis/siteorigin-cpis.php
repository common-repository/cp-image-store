<?php
/*
Widget Name: Image Store
Description: Inserts the Image Store shortcode.
Documentation: https://wordpress.dwbooster.com/content-tools/image-store
*/

class SiteOrigin_ImageStore extends SiteOrigin_Widget {

	public function __construct() {
		parent::__construct(
			'siteorigin-image-store',
			__( 'Image Store', 'cp-image-store' ),
			array(
				'description'   => __( 'Inserts the Image Store shortcode', 'cp-image-store' ),
				'panels_groups' => array( 'cpis-image-store' ),
				'help'          => 'https://wordpress.dwbooster.com/content-tools/image-store',
			),
			array(),
			array(
				'shortcode' => array(
					'type'    => 'textarea',
					'label'   => __( 'Image Store Shortcode', 'cp-image-store' ),
					'default' => '[codepeople-image-store]',
				),
			),
			plugin_dir_path( __FILE__ )
		);
	} // End __construct

	public function get_template_name( $instance ) {
		return 'siteorigin-cpis-shortcode';
	} // End get_template_name

	public function get_style_name( $instance ) {
		return '';
	} // End get_style_name

} // End Class SiteOrigin_ImageStore

// Registering the widget
siteorigin_widget_register( 'siteorigin-image-store', __FILE__, 'SiteOrigin_ImageStore' );
