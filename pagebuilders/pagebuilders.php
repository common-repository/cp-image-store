<?php
/**
 * Main class to interace with the different Content Editors: CPIS_PAGE_BUILDERS class
 */
if ( ! class_exists( 'CPIS_PAGE_BUILDERS' ) ) {
	class CPIS_PAGE_BUILDERS {

		private static $_instance;

		private function __construct(){}
		private static function instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		} // End instance

		public static function run() {
			$instance = self::instance();
			add_action( 'init', array( $instance, 'init' ) );
			add_action( 'after_setup_theme', array( $instance, 'after_setup_theme' ) );
		}

		public static function init() {
			 $instance = self::instance();

			// Gutenberg
			add_action( 'enqueue_block_editor_assets', array( $instance, 'gutenberg_editor' ) );

			// Elementor
			add_action( 'elementor/widgets/register', array( $instance, 'elementor_editor' ) );
			add_action( 'elementor/elements/categories_registered', array( $instance, 'elementor_editor_category' ) );
		}

		public function after_setup_theme() {
			$instance = self::instance();

			// SiteOrigin
			add_filter( 'siteorigin_widgets_widget_folders', array( $instance, 'siteorigin_widgets_collection' ) );
			add_filter( 'siteorigin_panels_widget_dialog_tabs', array( $instance, 'siteorigin_panels_widget_dialog_tabs' ) );
		} // End after_setup_theme

		/**************************** GUTENBERG ****************************/

		/**
		 * Loads the javascript resources to integrate the plugin with the Gutenberg editor
		 */
		public function gutenberg_editor() {
			wp_enqueue_style( 'cpis-gutenberg-editor-css', plugin_dir_url( __FILE__ ) . 'gutenberg/gutenberg.css', array(), CPIS_VERSION );
			wp_enqueue_script( 'cpis-gutenberg-editor', plugin_dir_url( __FILE__ ) . 'gutenberg/gutenberg.js', array(), CPIS_VERSION );
			$url  = CPIS_H_URL;
			$url .= ( ( strpos( '?', $url ) === false ) ? '?' : '&' ) . 'cpis-preview=';
			wp_localize_script(
				'cpis-gutenberg-editor',
				'cpis_settings',
				array(
					'url'               => $url,
					'store-description' => array(
						'title' => __( 'Shortcode Attributes', 'cp-image-store' ),
						'attrs' => array(
							'search'                => __( 'Display the images with the term in the image description, or title.', 'cp-image-store' ) . ' [codepeople-image-store search="people"]',
							'type'                  => __( 'Accepts the slug of image type, and display the images associated with this type.', 'cp-image-store' ) . ' [codepeople-image-store type="clip-art"]',
							'color'                 => __( 'Accepts the slug of color, and display the images associated with this color.', 'cp-image-store' ) . ' [codepeople-image-store color="full-color"]',
							'author'                => __( 'Accepts the slug of image author, and display the images that belong to the author.', 'cp-image-store' ) . ' [codepeople-image-store author="photographer-name"]',
							'category'              => __( 'Accepts the slug of category, and display the images that belong to the category.', 'cp-image-store' ) . ' [codepeople-image-store category="category-name"]',
							'carousel'              => __( 'Displays or hides the images carousel in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store carousel="1"]',
							'show_search_box'       => __( 'Displays or hides the search box in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_search_box="1"]',
							'show_type_filters'     => __( 'Displays or hides the option for filtering the images for type in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_type_filters="1"]',
							'show_color_filters'    => __( 'Displays or hides the option for filtering the images for colors in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_color_filters="1"]',
							'show_author_filters'   => __( 'Displays or hides the option for filtering the images for author in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_author_filters="1"]',
							'show_category_filters' => __( 'Displays or hides the option for filtering the images for category in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_category_filters="1"]',
							'show_ordering'         => __( 'Displays or hides the box for ordering the images in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_ordering="1"]',
						),
					),
				)
			);
		} // End gutenberg_editor

		/**************************** ELEMENTOR ****************************/

		public function elementor_editor_category() {
			require_once dirname( __FILE__ ) . '/elementor/elementor-category.pb.php';
		} // End elementor_editor

		public function elementor_editor() {
			wp_enqueue_style( 'ms-admin-elementor-editor-css', plugin_dir_url( __FILE__ ) . 'elementor/elementor.css', array(), CPIS_VERSION );
			require_once dirname( __FILE__ ) . '/elementor/elementor.pb.php';
		} // End elementor_editor

		/**************************** SITEORIGIN ****************************/

		public function siteorigin_widgets_collection( $folders ) {
			 $folders[] = dirname( __FILE__ ) . '/siteorigin/';
			return $folders;
		} // End siteorigin_widgets_collection

		public function siteorigin_panels_widget_dialog_tabs( $tabs ) {
			 $tabs[] = array(
				 'title'  => __( 'Image Store', 'cp-image-store' ),
				 'filter' => array(
					 'groups' => array( 'cpis-image-store' ),
				 ),
			 );

			 return $tabs;
		} // End siteorigin_panels_widget_dialog_tabs
	} // End CPIS_PAGE_BUILDERS
}
