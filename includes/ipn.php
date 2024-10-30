<?php
	/* Short and sweet */
	error_reporting( E_ERROR | E_PARSE );
	echo 'Start IPN';
	global $wpdb;

	$ipn_parameters = array();
	$_parameters    = explode( '|', ( isset( $_GET['cpis-action'] ) ? sanitize_text_field( wp_unslash( $_GET['cpis-action'] ) ) : '' ) );

foreach ( $_parameters as $_parameter ) {
	$_parameter_parts = explode( '=', $_parameter );
	if ( count( $_parameter_parts ) == 2 ) {
		if ( 'id[]' == $_parameter_parts[0] ) {
			if ( ! isset( $ipn_parameters['id'] ) || ! is_array( $ipn_parameters['id'] ) ) {
				$ipn_parameters['id'] = array();
			}
			$ipn_parameters['id'][] = $_parameter_parts[1];
		} else {
			$ipn_parameters[ $_parameter_parts[0] ] = $_parameter_parts[1];
		}
	}
}

function register_purchase( $product_id, $purchase_id, $email, $amount, $paypal_data, $purchase_note ) {
	global $wpdb;
	return $wpdb->insert(
		$wpdb->prefix . CPIS_PURCHASE,
		array(
			'product_id'  => $product_id,
			'purchase_id' => $purchase_id,
			'date'        => gmdate( 'Y-m-d H:i:s' ),
			'email'       => $email,
			'amount'      => $amount,
			'paypal_data' => $paypal_data,
			'note'        => $purchase_note,
		),
		array( '%d', '%s', '%s', '%s', '%f', '%s', '%s' )
	);
}

	$item_name      = isset( $_POST['item_name'] ) ? sanitize_text_field( wp_unslash( $_POST['item_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$item_number    = isset( $_POST['item_number'] ) ? sanitize_text_field( wp_unslash( $_POST['item_number'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$payment_status = isset( $_POST['payment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$payment_amount = isset( $_POST['mc_gross'] ) && is_numeric( $_POST['mc_gross'] ) ? floatval( $_POST['mc_gross'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

if ( ! empty( $_POST['tax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	$payment_amount -= isset( $_POST['tax'] ) && is_numeric( $_POST['tax'] ) ? floatval( $_POST['tax'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
}

	$payment_currency = isset( $_POST['mc_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_currency'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$txn_id           = isset( $_POST['txn_id'] ) ? sanitize_text_field( wp_unslash( $_POST['txn_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$receiver_email   = isset( $_POST['receiver_email'] ) ? sanitize_text_field( wp_unslash( $_POST['receiver_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$payer_email      = isset( $_POST['payer_email'] ) ? sanitize_text_field( wp_unslash( $_POST['payer_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$payment_type     = isset( $_POST['payment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

if ( 'Completed' != $payment_status && 'echeck' != $payment_type ) {
	exit;
}
if ( 'echeck' == $payment_type && 'Completed' == $payment_status ) {
	exit;
}

	$paypal_data = '';
foreach ( $_POST as $item => $value ) { // phpcs:ignore WordPress.Security.NonceVerification
	$paypal_data .= sanitize_text_field( $item ) . '=' . sanitize_text_field( $value ) . "\r\n";
}


if ( ! isset( $ipn_parameters['purchase_id'] ) ) {
	exit;
}
	$purchase_id = $ipn_parameters['purchase_id'];

	$options = get_option( 'cpis_options' );

if ( ! isset( $ipn_parameters['id'] ) ) {
	exit;
}

	$ids      = $ipn_parameters['id'];
	$products = array();
	$total    = 0;

foreach ( $ids as $id ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride

	$file = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT file.price as price, image_file.id_image as id_image, file.id as id FROM ' . $wpdb->prefix . CPIS_FILE . ' as file, ' . $wpdb->prefix . CPIS_IMAGE_FILE . ' as image_file WHERE file.id=image_file.id_file AND image_file.id_file=%d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$id
		)
	);

	if ( is_null( $file ) ) {
		exit;
	}
	$products[] = $file;
	$total     += $file->price;
}

	$total = round( $total, 2 );

if ( $payment_amount < $total && abs( $payment_amount - $total ) > 0.2 ) {
	exit;
}

foreach ( $products as $product ) {
	if ( register_purchase( $product->id, $purchase_id, $payer_email, $payment_amount, $paypal_data, '' ) ) {
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . CPIS_IMAGE . ' SET purchases=purchases+1 WHERE id=%d', $product->id_image ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}

	do_action( 'cpis_paypal_ipn_received', $_POST, $products ); // phpcs:ignore WordPress.Security.NonceVerification

	$notification_from_email = $options['notification']['from'];
	$notification_to_email   = $options['notification']['to'];

	$notification_to_payer_subject = $options['notification']['subject_payer'];
	$notification_to_payer_message = $options['notification']['notification_payer'];

	$notification_to_seller_subject = $options['notification']['subject_seller'];
	$notification_to_seller_message = $options['notification']['notification_seller'];

	$cpis_d_url  = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
	$cpis_d_url .= ( ( strpos( $cpis_d_url, '?' ) === false ) ? '?' : '&' ) . 'cpis-action=download';
	$cpis_d_url  = cpis_complete_url( $cpis_d_url );

	$information_payer = "Product: {$item_name}\n" .
						 "Amount: {$payment_amount} {$payment_currency}\n" .
						 'Download Link: ' . $cpis_d_url . "&purchase_id={$ipn_parameters[ 'purchase_id' ]}\n";

	$information_seller = "Product: {$item_name}\n" .
						  "Amount: {$payment_amount} {$payment_currency}\n" .
						  "Buyer Email: {$payer_email}\n" .
						  'Download Link: ' . $cpis_d_url . "&purchase_id={$ipn_parameters['purchase_id']}\n";

	$current_datetime = gmdate( 'Y-m-d h:ia' );

	// Get the buyer name from the buyer email,
	// only if there is an user with the same email than buyer
	$buyer_name = '';
	$buyer_user = get_user_by( 'email', $payer_email );
if ( $buyer_user ) {
	if ( $buyer_user->first_name ) {
		$buyer_name = $buyer_user->first_name;
		if ( $buyer_user->last_name ) {
			$buyer_name .= ' ' . $buyer_user->last_name;
		}
	} else {
		$buyer_name = $buyer_user->display_name;
	}
}

	$notification_to_payer_message = str_replace(
		array(
			'%INFORMATION%',
			'%DATETIME%',
			'%BUYERNAME%',
		),
		array(
			$information_payer,
			$current_datetime,
			$buyer_name,
		),
		$notification_to_payer_message
	);

	$notification_to_seller_message = str_replace(
		array(
			'%INFORMATION%',
			'%DATETIME%',
			'%BUYERNAME%',
		),
		array(
			$information_seller,
			$current_datetime,
			$buyer_name,
		),
		$notification_to_seller_message
	);

	// Send email to payer
	try {
		wp_mail(
			$payer_email,
			$notification_to_payer_subject,
			$notification_to_payer_message,
			"From: \"$notification_from_email\" <$notification_from_email>\r\n" .
				"Content-Type: text/plain; charset=utf-8\n" .
			'X-Mailer: PHP/' . phpversion()
		);
	} catch ( Exception $err ) {
		error_log( $err->getMessage() );
	}

	// Send email to seller
	if ( ! empty( $notification_to_email ) ) {
		try {
			wp_mail(
				$notification_to_email,
				$notification_to_seller_subject,
				$notification_to_seller_message,
				"From: \"$notification_from_email\" <$notification_from_email>\r\n" .
					"Content-Type: text/plain; charset=utf-8\n" .
				'X-Mailer: PHP/' . phpversion()
			);
		} catch ( Exception $err ) {
			error_log( $err->getMessage() );
		}
	}

	echo 'OK';
	exit();
