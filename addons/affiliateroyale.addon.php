<?php
if ( ! class_exists( 'MS_AffiliateRoyale' ) ) {
	class MS_AffiliateRoyale {

		private static $_instance;
		private function __construct() {
			if ( is_admin() ) {
				add_action( 'cpis_show_settings', array( &$this, 'show_settings' ), 11 );
				add_action( 'cpis_save_settings', array( &$this, 'save_settings' ), 11 );
			} else {
				add_action( 'cpis_paypal_form_html_before_submit', array( &$this, 'paypal_form_html_output' ), 11, 2 );
				add_action( 'cpis_paypal_ipn_received', array( &$this, 'capture_ipn' ), 11, 2 );
			}
		}

		private function is_active() {
			return get_option( 'cpis_affiliate_royale_active' );
		}

		public function paypal_form_html_output( $product, $purchase_id ) {
			if (
				$this->is_active() &&
				( $wafp_custom_args = do_shortcode( '[wafp_custom_args]' ) ) != '[wafp_custom_args]' // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
			) {
				echo $wafp_custom_args; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		} // End paypal_form_html_output

		public function capture_ipn( $ipn_post, $products ) {
			if ( $this->is_active() ) {
				$custom_array = array();

				// Load up the custom vals if they're there
				if ( isset( $ipn_post['custom'] ) && ! empty( $ipn_post['custom'] ) ) {
					$custom_array = wp_parse_args( $ipn_post['custom'] );
				}

				// Make sure we have what we need to track this payment
				if (
					isset( $custom_array['aff_id'] ) &&
					class_exists( 'WafpTransaction' ) &&
					isset( $ipn_post['txn_id'] ) &&
					isset( $ipn_post['mc_gross'] )
				) {
					$products_titles = array();
					foreach ( $products as $product ) {
						$id                     = ( isset( $product->id_image ) ) ? $product->id_image : $product->image_id;
						$products_titles[ $id ] = get_the_title( $id );
					}

					$_COOKIE['wafp_click'] = $custom_array['aff_id'];
					WafpTransaction::track(
						(float) $ipn_post['mc_gross'],
						$ipn_post['txn_id'],
						( ! empty( $products_titles ) ) ? implode( '|', $products_titles ) : 'Store Purchase'
					);
				}
			}
		} // End capture_ipn

		public function show_settings() {
			$cpis_affiliate_royale_active = get_option( 'cpis_affiliate_royale_active' );
			?>
			<div class="postbox">
				<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Affiliate Royale Integration', 'cp-image-store' ); ?></span></h3>
				<div class="inside">
					<?php esc_html_e( 'If the Affiliate Royale plugin is installed on the website, and you want integrate it with the  Store, tick the checkbox:', 'cp-image-store' ); ?>
					<input type="checkbox" name="cpis_affiliate_royale_active" <?php print( ( $cpis_affiliate_royale_active ) ? 'CHECKED' : '' ); ?> />
				</div>
			</div>
			<?php
		} // End show_settings

		public function save_settings() {
			update_option( 'cpis_affiliate_royale_active', ( isset( $_REQUEST['cpis_affiliate_royale_active'] ) ) ? true : false );
		} // End save_settings

		public static function init() {
			if ( null == self::$_instance ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		} // End init
	} // End MS_AffiliateRoyale
}

MS_AffiliateRoyale::init();
