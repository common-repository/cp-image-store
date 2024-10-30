<?php
if ( isset( $_POST['cpis_wizard'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cpis_wizard'] ) ), plugin_basename( __FILE__ ) ) ) {
	$options['paypal']['paypal_email'] = ( ! empty( $_POST['cpis_paypal_email'] ) ) ? sanitize_email( wp_unslash( $_POST['cpis_paypal_email'] ) ) : '';
	$options['store']['items_page']    = ( ! empty( $_POST['cpis_items_page'] ) && 0 < ( $cpis_items_page = @intval( $_POST['cpis_items_page'] ) ) ) ? $cpis_items_page : 10; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments

	$options['store']['columns'] = ( ! empty( $_POST['cpis_columns'] ) && 0 < ( $columns = @intval( $_POST['cpis_columns'] ) ) ) ? $columns : 1; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments


	if ( ! empty( $_POST['cpis_shop_page_title'] ) && ( $cpis_shop_page_title = sanitize_text_field( wp_unslash( $_POST['cpis_shop_page_title'] ) ) ) ) { // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
		$shortcode = '[codepeople-image-store]';
		$page_id   = wp_insert_post(
			array(
				'comment_status' => 'closed',
				'post_title'     => $cpis_shop_page_title,
				'post_content'   => $shortcode,
				'post_status'    => 'publish',
				'post_type'      => 'page',
			)
		);

		$options['store']['store_url'] = get_permalink( $page_id );
	}

	update_option( 'cpis_options', $options );
	print '<div class="updated notice">' . esc_html__( 'Store Wizard Completed', 'cp-image-store' ) . '</div>';
	if ( isset( $_POST['cpis_wizard_goto'] ) && 'images' == $_POST['cpis_wizard_goto'] ) {
		?>
	<script>document.location.href="<?php print esc_js( admin_url( 'post-new.php?post_type=cpis_image' ) ); ?>";</script>
		<?php
	}
}
$cpis_has_been_configured = get_option( 'cpis_has_been_configured', false );
if ( '' == $options['paypal']['paypal_email'] && ! $cpis_has_been_configured ) {
	?>
	<h1 style="text-align:center;"><?php esc_html_e( 'Images Store Wizard', 'cp-image-store' ); ?></h1>
	<form id="cpis_wizard" method="post" action="<?php echo esc_attr( admin_url( 'admin.php?page=image-store-menu-settings' ) ); ?>">
		<div>
			<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Step 1 of 2', 'cp-image-store' ); ?>: <?php esc_html_e( 'Payment Gateway', 'cp-image-store' ); ?></span></h3>
			<hr />
			<table class="form-table">
				<tr valign="top">
					<th scope="row" style="white-space:nowrap;">
						<?php esc_html_e( 'Enter the email address associated to your PayPal account', 'cp-image-store' ); ?>
					</th>
					<td>
						<input type="text" name="cpis_paypal_email" size="40" placeholder="<?php esc_attr_e( 'Email address', 'cp-image-store' ); ?>" /><br />
						<i style="font-weight:normal;"><?php esc_html_e( 'Leave in blank if you want distribute your images for free.', 'cp-image-store' ); ?></i>
					</td>
				</tr>
			</table>
			<div style="border:1px dotted #333333; margin-top:10px; margin-bottom:10px; padding: 10px;">Please, remember that the Instant Payment Notification (IPN) must be enabled in your PayPal account, because if the IPN is disabled PayPal does not notify the payments to your website. Please, visit the following link: <a href="https://developer.paypal.com/api/nvp-soap/ipn/IPNSetup/#link-settingupipnnotificationsonpaypal" target="_blank">How to enable the IPN?</a>. PayPal needs the URL to the IPN Script in your website, however, you simply should enter the URL to the home page.</div>
			<input type="button" class="button" value="<?php esc_attr_e( 'Next step', 'cp-image-store' ); ?>" onclick="jQuery(this).closest('div').hide().next('div').show();">
		</div>
		<div style="display:none;">
			<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Step 2 of 2', 'cp-image-store' ); ?>: <?php esc_html_e( 'Store Page', 'cp-image-store' ); ?></span></h3>
			<hr />
			<table class="form-table">
				<tr valign="top">
					<th><?php esc_html_e( 'Enter the shop page\'s title', 'cp-image-store' ); ?></th>
					<td>
						<input type="text" name="cpis_shop_page_title" size="40" /><br />
						<i><?php esc_html_e( 'Leave in blank if you want to configure the shop\'s page after.', 'cp-image-store' ); ?></i>
					</td>
				</tr>
				<tr valign="top">
					<th><?php esc_html_e( 'Products per page', 'cp-image-store' ); ?></th>
					<td><input type="text" name="cpis_items_page" value="<?php echo esc_attr( @intval( $options['store']['items_page'] ) ); ?>" /></td>
				</tr>
				<tr valign="top">
					<th><?php esc_html_e( 'Number of columns', 'cp-image-store' ); ?></th>
					<td><input type="text" name="cpis_columns" value="3" /></td>
				</tr>
			</table>
			<input type="hidden" id="cpis_wizard_goto" name="cpis_wizard_goto" value="settings" />
			<input type="button" class="button" value="<?php esc_attr_e( 'Previous step', 'cp-image-store' ); ?>" onclick="jQuery(this).closest('div').hide().prev('div').show();" />
			<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save wizard and create my first image', 'cp-image-store' ); ?>" onclick="jQuery('#cpis_wizard_goto').val('images');" />
			<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save wizad and go to the store\'s settings', 'cp-image-store' ); ?>" />
		</div>
		<?php wp_nonce_field( plugin_basename( __FILE__ ), 'cpis_wizard' ); ?>
	</form>
	<script>jQuery(document).on('keydown', '#cpis_wizard input[type="text"]', function(e){var code = e.keyCode || e.which;if(code == 13) {e.preventDefault();e.stopPropagation();return false;}});</script>
	<?php
	update_option( 'cpis_has_been_configured', true );
	$wizard_active = true;
}
