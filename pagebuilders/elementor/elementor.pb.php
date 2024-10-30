<?php
namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Elementor_Image_Store_Widget extends Widget_Base {

	public function get_name() {
		return 'cpis-image-store';
	} // End get_name

	public function get_title() {
		return 'Image Store';
	} // End get_title

	public function get_icon() {
		return 'eicon-product-images';
	} // End get_icon

	public function get_categories() {
		return array( 'cpis-image-store-cat' );
	} // End get_categories

	public function is_reload_preview_required() {
		return false;
	} // End is_reload_preview_required

	protected function register_controls() {
		$this->start_controls_section(
			'cpis_section',
			array(
				'label' => __( 'Image Store', 'cp-image-store' ),
			)
		);

		$this->add_control(
			'shortcode',
			array(
				'label'       => __( 'Image Store Shortcode', 'cp-image-store' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => '[codepeople-image-store]',
				'classes'     => 'cpis-widefat',
				'description' => '<h2>' . __( 'Shortcode Attributes', 'cp-image-store' ) . '</h2>' .
						'<p><b>search:</b> ' . __( 'Display the images with the term in the image description, or title.', 'cp-image-store' ) . ' [codepeople-image-store search="people"]</p>' .
						'<p><b>type:</b> ' . __( 'Accepts the slug of image type, and display the images associated with this type.', 'cp-image-store' ) . ' [codepeople-image-store type="clip-art"]</p>' .
						'<p><b>color:</b> ' . __( 'Accepts the slug of color, and display the images associated with this color.', 'cp-image-store' ) . ' [codepeople-image-store color="full-color"]</p>' .
						'<p><b>author:</b> ' . __( 'Accepts the slug of image author, and display the images that belong to the author.', 'cp-image-store' ) . ' [codepeople-image-store author="photographer-name"]</p>' .
						'<p><b>category:</b> ' . __( 'Accepts the slug of category, and display the images that belong to the category.', 'cp-image-store' ) . ' [codepeople-image-store category="category-name"]</p>' .
						'<p><b>carousel:</b> ' . __( 'Displays or hides the images carousel in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store carousel="1"]</p>' .
						'<p><b>show_search_box:</b> ' . __( 'Displays or hides the search box in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_search_box="1"]</p>' .
						'<p><b>show_type_filters:</b> ' . __( 'Displays or hides the option for filtering the images for type in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_type_filters="1"]</p>' .
						'<p><b>show_color_filters:</b> ' . __( 'Displays or hides the option for filtering the images for colors in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_color_filters="1"]</p>' .
						'<p><b>show_author_filters:</b> ' . __( 'Displays or hides the option for filtering the images for author in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_author_filters="1"]</p>' .
						'<p><b>show_category_filters:</b> ' . __( 'Displays or hides the option for filtering the images for category in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_category_filters="1"]</p>' .
						'<p><b>show_ordering:</b> ' . __( 'Displays or hides the box for ordering the images in the store\'s page. Values allowed 1 or 0.', 'cp-image-store' ) . ' [codepeople-image-store show_ordering="1"]</p>',
			)
		);

		$this->end_controls_section();
	} // End register_controls

	private function _get_shortcode() {
		 $settings = $this->get_settings_for_display();
		return sanitize_text_field( preg_replace( '/[\r\n]/', ' ', $settings['shortcode'] ) );
	} // End _get_shortcode

	protected function render() {
		$shortcode = $this->_get_shortcode();
		if (
			isset( $_REQUEST['action'] ) &&
			(
				'elementor' == $_REQUEST['action'] ||
				'elementor_ajax' == $_REQUEST['action']
			)
		) {
			$url  = CPIS_H_URL;
			$url .= ( ( strpos( '?', $url ) === false ) ? '?' : '&' ) . 'cpis-preview=' . urlencode( $shortcode );
			?>
			<div class="cpis-iframe-container" style="position:relative;">
				<div class="cpis-iframe-overlay" style="position:absolute;top:0;right:0;bottom:0;left:0;"></div>
				<iframe height="0" width="100%" src="<?php print esc_attr( $url ); ?>" scrolling="no">
			</div>
			<?php
		} else {
			print do_shortcode( shortcode_unautop( $shortcode ) );
		}

	} // End render

	public function render_plain_content() {
		echo wp_kses_post( $this->_get_shortcode() );
	} // End render_plain_content

} // End Elementor_Image_Store_Widget

class Elementor_Image_Store_Product_Widget extends Widget_Base {

	public function get_name() {
		return 'cpis-image-store-product';
	} // End get_name

	public function get_title() {
		return 'Product';
	} // End get_title

	public function get_icon() {
		return 'eicon-image';
	} // End get_icon

	public function get_categories() {
		return array( 'cpis-image-store-cat' );
	} // End get_categories

	public function is_reload_preview_required() {
		return false;
	} // End is_reload_preview_required

	protected function register_controls() {
		$this->start_controls_section(
			'cpis_section',
			array(
				'label' => __( 'Product', 'cp-image-store' ),
			)
		);

		$this->add_control(
			'product',
			array(
				'label'       => __( "Enter the product's id", 'cp-image-store' ),
				'type'        => Controls_Manager::NUMBER,
				'description' => '<i>' . __( 'Enter the id of a published product.', 'cp-image-store' ) . '</i>',
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'       => __( "Select the product's layout", 'cp-image-store' ),
				'type'        => Controls_Manager::SELECT,
				'classes'     => 'cpis-widefat',
				'options'     => array(
					'multiple' => __( "Like in the store's page", 'cp-image-store' ),
					'single'   => __( "Like in the product's page", 'cp-image-store' ),
				),
				'default'     => 'single',
				'description' => '<i>' . __( 'Appearance applied to the product.', 'cp-image-store' ) . '</i>',
			)
		);

		$this->end_controls_section();
	} // End register_controls

	private function _get_shortcode( &$product_id = '' ) {
		$attr     = '';
		$settings = $this->get_settings_for_display();

		$product = sanitize_text_field( $settings['product'] );
		if ( ! empty( $product ) ) {
			$attr .= ' id="' . esc_attr( $product ) . '"';
		}
		$product_id = $product;

		$layout = sanitize_text_field( $settings['layout'] );
		if ( ! empty( $layout ) ) {
			$attr .= ' layout="' . esc_attr( $layout ) . '"';
		}

		return '[codepeople-image-store-product' . $attr . ']';
	} // End _get_shortcode

	protected function render() {
		$shortcode = $this->_get_shortcode( $product_id );
		if (
			isset( $_REQUEST['action'] ) &&
			(
				'elementor' == $_REQUEST['action'] ||
				'elementor_ajax' == $_REQUEST['action']
			)
		) {
			if ( empty( $product_id ) ) {
				esc_html_e( "The product's id is required.", 'cp-image-store' );
			} else {
				$url  = CPIS_H_URL;
				$url .= ( ( strpos( $url, '?' ) === false ) ? '?' : '&' ) . 'cpis-preview=' . urlencode( $shortcode );
				?>
				<div class="cpis-iframe-container" style="position:relative;">
					<div class="cpis-iframe-overlay" style="position:absolute;top:0;right:0;bottom:0;left:0;"></div>
					<iframe height="0" width="100%" src="<?php print esc_attr( $url ); ?>" scrolling="no">
				</div>
				<?php
			}
		} else {
			print do_shortcode( shortcode_unautop( $shortcode ) );
		}

	} // End render

	public function render_plain_content() {
		echo wp_kses_post( $this->_get_shortcode() );
	} // End render_plain_content

} // End Elementor_Image_Store_Product_Widget

// Register the widgets
Plugin::instance()->widgets_manager->register( new Elementor_Image_Store_Widget() );
Plugin::instance()->widgets_manager->register( new Elementor_Image_Store_Product_Widget() );
