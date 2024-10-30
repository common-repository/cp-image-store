<?php
	global $wpdb;

function cpis_make_seed() {
	list($usec, $sec) = explode( ' ', microtime() );
	return intval( (float) $sec + ( (float) $usec * 1000000 ) );
}

	$options      = get_option( 'cpis_options' );
	$currency     = ( ! empty( $options['paypal']['currency'] ) ) ? $options['paypal']['currency'] : 'USD';
	$language     = ( ! empty( $options['paypal']['language'] ) ) ? $options['paypal']['language'] : 'EN';
	$paypal_email = $options['paypal']['paypal_email'];

	$notify_url_params = 'ipn';

	$returnurl  = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
	$returnurl .= ( ( strpos( $returnurl, '?' ) === false ) ? '?' : '&' ) . 'cpis-action=download';
	$returnurl  = cpis_complete_url( $returnurl );

	$cancel_url = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
if ( empty( $cancel_url ) ) {
	$cancel_url = CPIS_H_URL;
}

	$amount = 0;
	$title  = ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	$id     = '|'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

if ( $paypal_email ) { // Check for sealer email
	mt_srand( cpis_make_seed() );
	$randval = mt_rand( 1, 999999 );

	$number = 0;

	$purchase_id = md5( $randval . uniqid( '', true ) );

	if ( ! empty( $_POST['image_file'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$connector = '';
		$filter    = '';
		if ( ! is_array( $_POST['image_file'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$_POST['image_file'] = array( sanitize_text_field( wp_unslash( $_POST['image_file'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		foreach ( $_POST['image_file'] as $image_id ) { // @codingStandardsIgnoreLine
			$filter   .= $wpdb->prepare( $connector . 'file.id=%d', @intval( $image_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$connector = ' OR ';
		}

		$products = $wpdb->get_results( 'SELECT file.id as id, file.price as price, posts.post_title as title FROM ((' . $wpdb->prefix . CPIS_IMAGE_FILE . ' as image_file INNER JOIN ' . $wpdb->prefix . 'posts as posts ON posts.ID = image_file.id_image) INNER JOIN ' . $wpdb->prefix . CPIS_FILE . " as file ON image_file.id_file = file.id) WHERE posts.post_status='publish' AND ($filter)" ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( $products ) {
			foreach ( $products as $product ) {
				$amount += $product->price;
				$title  .= $product->title . '(' . $product->price . ')'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				$number++;
				$id .= 'id[]=' . $product->id . '|'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
	}

	$amount = round( $amount, 2 );

	if ( $amount > 0 ) {
		$notify_url_params .= $id . 'purchase_id=' . $purchase_id . '|rtn_act=purchased_product_cpis';
		$transient_id = uniqid( 'cpis-ipn-', true );
		set_transient( $transient_id, $notify_url_params, 24 * 60 *60 );
		$notify_url_params = $transient_id;

		// Removes invalid characters from products names
		$title = html_entity_decode( $title, ENT_COMPAT, 'UTF-8' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		?>
<form action="https://www.<?php print esc_attr( ( $options['paypal']['activate_sandbox'] ) ? 'sandbox.' : '' ); ?>paypal.com/cgi-bin/webscr" name="ppform<?php print esc_attr( $randval ); ?>" method="post">
<input type="hidden" name="charset" value="utf-8" />
<input type="hidden" name="business" value="<?php print esc_attr( $paypal_email ); ?>" />
<input type="hidden" name="item_name" value="<?php print esc_attr( sanitize_text_field( $title ) ); ?>" />
<input type="hidden" name="item_number" value="Item Number <?php print esc_attr( $number ); ?>" />
<input type="hidden" name="amount" value="<?php print esc_attr( $amount ); ?>" />
<input type="hidden" name="currency_code" value="<?php print esc_attr( $currency ); ?>" />
<input type="hidden" name="lc" value="<?php print esc_attr( $language ); ?>" />
<input type="hidden" name="return" value="<?php print esc_url( $returnurl . '&purchase_id=' . $purchase_id ); ?>" />
<input type="hidden" name="cancel_return" value="<?php print esc_url( $cancel_url ); ?>" />
<input type="hidden" name="notify_url" value="<?php print esc_url( CPIS_H_URL . '?cpis-action=' . $notify_url_params  ); ?>" />
<input type="hidden" name="cmd" value="_xclick" />
<input type="hidden" name="page_style" value="Primary" />
<input type="hidden" name="no_shipping" value="1" />
<input type="hidden" name="no_note" value="1" />
<input type="hidden" name="bn" value="NetFactorSL_SI_Custom" />
<input type="hidden" name="ipn_test" value="1" />
		<?php
		if ( ! empty( $options['paypal']['tax'] ) ) {
			print '<input type="hidden" name="tax_rate" value="' . esc_attr( $options['paypal']['tax'] ) . '" />';
		}
		?>
</form>
		<?php do_action( 'cpis_paypal_form_html_before_submit', $products, $purchase_id ); ?>
<script type="text/javascript">document.ppform<?php print esc_js( $randval ); ?>.submit();</script>
		<?php
		exit;
	}
}

	header( 'location: ' . $cancel_url );
