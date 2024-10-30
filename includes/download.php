<?php
	error_reporting( E_ERROR | E_PARSE );

	global $options;
	$options = get_option( 'cpis_options' );

function cpis_copy_download_links( $file ) {
	$ext           = pathinfo( $file, PATHINFO_EXTENSION );
	$new_file_name = md5( $file ) . '.' . $ext;
	$file_path     = CPIS_DOWNLOAD . '/' . $new_file_name;
	$rand          = rand( 1000, 1000000 );
	if ( file_exists( $file_path ) ) {
		return $new_file_name;
	}
	if ( copy( $file, $file_path ) ) {
		return $new_file_name;
	}
	return $file;
} // End cpis_copy_download_links

function cpis_remove_download_links() {
	global $options;

	$now = time();
	$dif = ( ( ! empty( $options['store']['download_link'] ) ) ? $options['store']['download_link'] : 3 ) * 86400;
	$d   = @dir( CPIS_DOWNLOAD );
	while ( false !== ( $entry = $d->read() ) ) {
		if ( '.' != $entry && '..' != $entry && '.htaccess' != $entry ) {
			$file_name = CPIS_DOWNLOAD . '/' . $entry;
			$date      = filemtime( $file_name );
			if ( $now - $date >= $dif ) { // Delete file
				@unlink( $file_name );
			}
		}
	}
	$d->close();
} // End cpis_remove_download_links

function cpis_image_title( $obj ) {
	if ( isset( $obj->post_title ) ) {
		return $obj->post_title;
	}
	return pathinfo( $obj->path, PATHINFO_FILENAME );
} // End cpis_image_title

function cpis_generate_downloads() {
	global $wpdb, $download_links_str, $options;

	cpis_remove_download_links();
	if ( cpis_check_download_permissions() ) {
		$tmp_arr = array();

		$purchase_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . CPIS_PURCHASE . ' WHERE purchase_id=%s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				isset( $_GET['purchase_id'] ) ? sanitize_key( wp_unslash( $_GET['purchase_id'] ) ) : 0
			)
		);

		if ( $purchase_rows ) { // Exists the purchase
			$interval = ( ( ! empty( $options['store']['download_link'] ) ) ? $options['store']['download_link'] : 3 ) * 86400;

			$urls               = array();
			$download_links_str = '';

			foreach ( $purchase_rows as $purchase ) {

				$id  = $purchase->product_id;
				$obj = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT file.path as path, file.url as link, post.post_title as title FROM ' . $wpdb->prefix . 'posts as post, ' . $wpdb->prefix . CPIS_IMAGE . ' as image, ' . $wpdb->prefix . CPIS_IMAGE_FILE . ' as image_file, ' . $wpdb->prefix . CPIS_FILE . ' as file WHERE post.ID=image.id AND image.id=image_file.id_image AND image_file.id_file=file.id AND file.id=%d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$id
					)
				);

				if ( is_null( $obj ) || in_array( $obj->path, $tmp_arr ) ) {
					continue;
				}

				$tmp_arr[] = $obj->path;

				$obj->title = cpis_image_title( $obj );

				$urls[] = $obj;
			}

			if ( count( $urls ) ) {
				if ( empty( $download_links_str ) ) {
					foreach ( $urls as $url ) {
						$download_link = cpis_copy_download_links( $url->path );

						if ( $download_link !== $url->link ) {
							$download_link = CPIS_H_URL . '?cpis-action=f-download' . ( ( isset( $GLOBALS[ CPIS_SESSION_NAME ]['cpis_user_email'] ) ) ? '&cpis_user_email=' . $GLOBALS[ CPIS_SESSION_NAME ]['cpis_user_email'] : '' ) . '&f=' . $download_link . ( ( ! empty( $_REQUEST['purchase_id'] ) ) ? '&purchase_id=' . sanitize_key( $_REQUEST['purchase_id'] ) : '' );
						}

						$download_links_str .= '<div> <a href="' . esc_url( $download_link ) . '">' . $url->title . '</a></div>';
					}
				}
			}

			if ( empty( $download_links_str ) ) {
				$download_links_str = __( 'The list of purchased products is empty', 'cp-image-store' );
			}

			return $download_links_str;
		} // End purchase checking
	}
} //cpis_generate_downloads


if ( isset( $_GET['purchase_id'] ) ) {
	global $download_links_str;
	$download_links_str = cpis_generate_downloads();
}
