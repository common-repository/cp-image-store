<?php
if ( ! function_exists( 'cpis_import' ) ) {
	function cpis_import() {
		global $wpdb, $cpis_total_products_processed, $cpis_recursive_call;
		$cpis_total_products_processed = 0;
		$cpis_recursive_call           = 1;

		if ( isset( $_REQUEST['cpis_total_products_processed'] ) ) {
			$cpis_total_products_processed = @intval( $_REQUEST['cpis_total_products_processed'] );
		}
		$cpis_total_products_processed = abs( $cpis_total_products_processed );

		$allowed_image_attrs = array( 'width', 'height', 'price' );
		$allowed_terms       = array( 'author', 'type', 'color' );

		if ( empty( $_REQUEST['cpis_xml_url'] ) ) {
			throw new Exception( esc_html__( 'The URL to the XML file is required', 'cp-image-store' ) );
		}

		$cpis_xml_url = sanitize_text_field( wp_unslash( $_REQUEST['cpis_xml_url'] ) );
		if ( empty( $cpis_xml_url ) ) {
			throw new Exception( esc_html__( 'The URL to the XML file is required', 'cp-image-store' ) );
		}

		$path = rtrim( get_home_path(), '/' ) . '/' . ltrim( parse_url( $cpis_xml_url, PHP_URL_PATH ), '/' );
		if ( ! file_exists( $path ) ) {
			throw new Exception( esc_html__( 'The XML file is not accessible', 'cp-image-store' ) );
		}

		register_shutdown_function( 'cpis_shutdown' );
		$dir = dirname( $path );

		if ( ( $xmlObj = simplexml_load_file( $path ) ) === false ) { // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
			throw new Exception( esc_html__( 'The file structure is wrong', 'cp-image-store' ) );
		}

		if ( ! property_exists( $xmlObj, 'product' ) ) {
			throw new Exception( esc_html__( 'The products to import are required', 'cp-image-store' ) );
		}

		$options = get_option( 'cpis_options' );
		$counter = -1;
		foreach ( $xmlObj->product as $p ) {
			$counter++;
			if ( $counter < $cpis_total_products_processed ) {
				continue;
			}

			$post = array(
				'post_title'   => '',
				'post_content' => '',
				'post_type'    => 'cpis_image',
				'post_status'  => 'publish',
			);

			if ( property_exists( $p, 'title' ) ) {
				$post['post_title'] = $p->title->__toString();
			}

			if ( property_exists( $p, 'description' ) ) {
				$post['post_content'] = $p->description->__toString();
			}

			// Create the post image
			$img_id = wp_insert_post( $post );

			// Create terms
			foreach ( $allowed_terms as $term ) {
				if ( property_exists( $p, $term ) ) {
					$terms_arr = array();
					foreach ( $p->{$term} as $t ) {
						$terms_arr[] = $t->__toString();
					}
					wp_set_object_terms( $img_id, $terms_arr, 'cpis_' . $term, true );
				}
			}

			// Create the thumbnail and intermediate images
			if ( property_exists( $p, 'thumbnail' ) ) {
				$img_data        = array( 'id' => $img_id );
				$img_data_format = array( '%d' );

				$original_path  = $dir . '/' . ltrim( $p->thumbnail->__toString(), '/' );
				$t              = preg_replace( '/[^\d]/', '', microtime() );
				$ext            = pathinfo( $original_path, PATHINFO_EXTENSION );
				$thumbnail_path = CPIS_UPLOAD_DIR . '/previews/' . $t . '.' . $ext;
				$thumbnail_url  = CPIS_UPLOAD_URL . '/previews/' . $t . '.' . $ext;
				if (
					cpis_watermarkImage( $original_path, $thumbnail_path, $options['image']['thumbnail']['width'], $options['image']['thumbnail']['height'] )
				) {
					$t                 = preg_replace( '/[^\d]/', '', microtime() );
					$intermediate_path = CPIS_UPLOAD_DIR . '/previews/' . $t . '.' . $ext;
					$intermediate_url  = CPIS_UPLOAD_URL . '/previews/' . $t . '.' . $ext;
					if (
						cpis_watermarkImage( $original_path, $intermediate_path, $options['image']['intermediate']['width'], $options['image']['intermediate']['height'] )
					) {
						$preview = serialize(
							array(
								'thumbnail_path'    => $thumbnail_path,
								'thumbnail_url'     => $thumbnail_url,
								'intermediate_path' => $intermediate_path,
								'intermediate_url'  => $intermediate_url,
							)
						);

						$img_data['preview'] = $preview;
						$img_data_format[]   = '%s';

						$wpdb->insert(
							$wpdb->prefix . CPIS_IMAGE,
							$img_data,
							$img_data_format
						);
					} else {
						throw new Exception( esc_html__( 'The intermediate image has not been generated', 'cp-image-store' ) );
					}
				} else {
					throw new Exception( esc_html__( 'The thumbnail image has not been generated', 'cp-image-store' ) );
				}
			}

			// Create the images for selling
			if ( property_exists( $p, 'image' ) ) {
				// Create the images directory
				$images_dir = gmdate( 'Y/m' );
				if (
					! ( ( $uploads = wp_upload_dir( $images_dir ) ) && false === $uploads['error'] ) // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
				) {
					throw new Exception( esc_html__( 'Has not been possible create the subfolders in the "Uploads" directory for storing the images', 'cp-image-store' ) );
				}

				foreach ( $p->image as $image ) {
					$image_data        = array();
					$image_data_format = array();

					$original_path = $dir . '/' . ltrim( $image->__toString(), '/' );
					if ( ! file_exists( $original_path ) ) {
						throw new Exception( esc_html__( 'The images does not exists', 'cp-image-store' ) . ': ' . $original_path );
					}

					$wp_filetype = wp_check_filetype( $original_path, null );
					if ( ! $wp_filetype['type'] || ! $wp_filetype['ext'] ) {
						throw new Exception( esc_html__( 'The file type is not supported', 'cp-image-store' ) . ': ' . $original_path );
					}

					$filename = wp_unique_filename( $uploads['path'], basename( $original_path ) );
					$new_path = $uploads['path'] . '/' . $filename;
					$new_url  = $uploads['url'] . '/' . $filename;
					if ( false === @copy( $original_path, $new_path ) ) {
						throw new Exception( esc_html__( 'The file cannot be copied to the final location', 'cp-image-store' ) . ': ' . $uploads['path'] );
					}

					$image_data['url']   = $new_url;
					$image_data_format[] = '%s';
					$image_data['path']  = $new_path;
					$image_data_format[] = '%s';

					foreach ( $image->attributes() as $attr => $attr_value ) {
						$attr = strtolower( $attr );
						if ( in_array( $attr, $allowed_image_attrs ) ) {
							$image_data[ $attr ] = @floatval( $attr_value->__toString() );
							$image_data_format[] = '%f';
						}
					}

					$wpdb->insert(
						$wpdb->prefix . CPIS_FILE,
						$image_data,
						$image_data_format
					);

					$wpdb->insert(
						$wpdb->prefix . CPIS_IMAGE_FILE,
						array(
							'id_image' => $img_id,
							'id_file'  => $wpdb->insert_id,
						),
						array(
							'%d',
							'%d',
						)
					);
				}
			}

			$cpis_total_products_processed++;
		}

		throw new Exception( '<div style="text-align:center;"><h1>' . esc_html__( 'All Images Imported', 'cp-image-store' ) . '</h1></div>' );
	}
} // End cpis_import

if ( ! function_exists( 'cpis_shutdown' ) ) {
	function cpis_shutdown() {
		global $cpis_total_products_processed, $cpis_recursive_call;
		if ( empty( $cpis_recursive_call ) ) {
			return;
		}
		?>
		<form id="cpis_shutdown" action="<?php print esc_attr( get_admin_url( get_current_blog_id() ) ); ?>" method="post">
			<input type="hidden" name="cpis-action" value="import" />
			<input type="hidden" name="cpis_total_products_processed" value="<?php print esc_attr( $cpis_total_products_processed ); ?>" />
			<input type="hidden" name="cpis_xml_url" value="<?php print esc_attr( isset( $_REQUEST['cpis_xml_url'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cpis_xml_url'] ) ) : '' ); ?>" />
			<?php wp_nonce_field( 'session_id_' . session_id(), 'cpis_import' ); ?>
		</form>
		<script>document.getElementById( 'cpis_shutdown' ).submit();</script>
		<?php
	}
} // End cpis_shutdown
