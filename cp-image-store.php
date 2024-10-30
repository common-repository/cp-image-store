<?php
/*
Plugin Name: CP Image Store with Slideshow
Plugin URI: http://wordpress.dwbooster.com/content-tools/image-store#download
Description: Image Store is an online store for the sale of image files: images, predefined pictures, clipart, drawings, vector images. For payment processing, Image Store uses PayPal, which is the most widely used payment gateway, safe and easy to use.
Version: 1.1.5
Author: CodePeople
Author URI: http://wordpress.dwbooster.com/content-tools/image-store
Text Domain: cp-image-store
License: GPLv2
*/

require_once 'banner.php';
$codepeople_promote_banner_plugins['cp-image-store-with-slideshow'] = array(
	'plugin_name' => 'CP Image Store with Slideshow',
	'plugin_url'  => 'https://wordpress.org/support/plugin/cp-image-store/reviews/#new-post',
);

// Feedback system
require_once 'feedback/cp-feedback.php';
new CP_FEEDBACK( plugin_basename( dirname( __FILE__ ) ), __FILE__, 'https://wordpress.dwbooster.com/contact-us' );

add_filter( 'option_sbp_settings', 'cp_image_store_troubleshoot' );
if ( ! function_exists( 'cp_image_store_troubleshoot' ) ) {
	function cp_image_store_troubleshoot( $option ) {
		if ( ! is_admin() ) {
			// Solves a conflict caused by the "Speed Booster Pack" plugin
			if ( is_array( $option ) && isset( $option['jquery_to_footer'] ) ) {
				unset( $option['jquery_to_footer'] );
			}
		}
		return $option;
	} // End cp_image_store_troubleshoot
}

define( 'CPIS_SESSION_NAME', 'cpis_session_20200814' );
if ( ! function_exists( 'cpis_start_session' ) ) {
	function cpis_start_session() {
		 $GLOBALS[ CPIS_SESSION_NAME ] = array();
		$set_cookie                    = true;
		if ( isset( $_COOKIE[ CPIS_SESSION_NAME ] ) ) {
			$GLOBALS['CPIS_SESSION_ID'] = sanitize_text_field( wp_unslash( $_COOKIE[ CPIS_SESSION_NAME ] ) );
			$_stored_session            = get_transient( $GLOBALS['CPIS_SESSION_ID'] );
			if ( false !== $_stored_session ) {
				$GLOBALS[ CPIS_SESSION_NAME ] = $_stored_session;
				$set_cookie                   = false;
			}
		}

		if ( $set_cookie ) {
			$GLOBALS['CPIS_SESSION_ID'] = uniqid( '', true );
			if ( ! headers_sent() ) {
				@setcookie( CPIS_SESSION_NAME, $GLOBALS['CPIS_SESSION_ID'], 0, '/' );
			}
		}
	}
	cpis_start_session();
}

if ( ! function_exists( 'cpis_session_dump' ) ) {
	function cpis_session_dump() {
		set_transient( $GLOBALS['CPIS_SESSION_ID'], $GLOBALS[ CPIS_SESSION_NAME ], 24 * 60 * 60 );
		delete_expired_transients(true);
	}
	add_action( 'shutdown', 'cpis_session_dump', 99, 0 );
}

// Global variable used to print the images preview in the website footer

global $cpis_images_preview, $cpis_errors, $cpis_layout, $cpis_layouts;
$cpis_errors = array();

$cpis_images_preview = '';
$cpis_upload_path    = wp_upload_dir();
$cpis_layouts        = array();
$cpis_layout         = array();

// CONST
define( 'CPIS_VERSION', '1.1.5' );
define( 'CPIS_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'CPIS_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'CPIS_ADMIN_URL', rtrim( admin_url( get_current_blog_id() ), '/' ) . '/' );
define( 'CPIS_H_URL', rtrim( get_home_url( get_current_blog_id() ), '/' ) . ( ( strpos( get_current_blog_id(), '?' ) === false ) ? '/' : '' ) );

define( 'CPIS_UPLOAD_DIR', ( ( file_exists( CPIS_PLUGIN_DIR . '/uploads' ) ) ? CPIS_PLUGIN_DIR . '/uploads' : $cpis_upload_path['basedir'] . '/cpis_uploads' ) );
define( 'CPIS_UPLOAD_URL', ( ( file_exists( CPIS_PLUGIN_DIR . '/uploads' ) ) ? CPIS_PLUGIN_URL . '/uploads' : $cpis_upload_path['baseurl'] . '/cpis_uploads' ) );

define( 'CPIS_DOWNLOAD', dirname( __FILE__ ) . '/downloads' );
define( 'CPIS_IMAGE_STORE_SLUG', 'image-store-menu' );
define( 'CPIS_IMAGES_URL', CPIS_PLUGIN_URL . '/images' );
define( 'CPIS_TEXT_DOMAIN', 'cp-image-store' );
define( 'CPIS_SC_EXPIRE', 3 ); // Time for shopping cart expiration, default 3 days
define( 'CPIS_SAFE_DOWNLOAD', false );

// TABLE NAMES
define( 'CPIS_IMAGE', 'cpis_image' );
define( 'CPIS_FILE', 'cpis_file' );
define( 'CPIS_IMAGE_FILE', 'cpis_image_file' );
define( 'CPIS_PURCHASE', 'cpis_purchase' );

// INCLUDES
require_once CPIS_PLUGIN_DIR . '/includes/image.php';
require_once CPIS_PLUGIN_DIR . '/pagebuilders/pagebuilders.php';

// Load the addons
function cpis_loading_add_ons() {
	$path = dirname( __FILE__ ) . '/addons';
	if ( file_exists( $path ) ) {
		$addons = dir( $path );
		while ( false !== ( $entry = $addons->read() ) ) {
			if ( strlen( $entry ) > 3 && 'php' == strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) ) ) {
				require_once $addons->path . '/' . $entry;
			}
		}
	}
}
cpis_loading_add_ons();

// Load the page builders
CPIS_PAGE_BUILDERS::run();

if ( ! function_exists( 'cpis_fixingCacheConflict' ) ) {
	function cpis_fixingCacheConflict() {
		if ( is_admin() ) {
			return;
		}

		// For WP Super Cache plugin
		global  $cache_rejected_uri;

		if ( ! empty( $cache_rejected_uri ) ) {
			$to_exlude          = array( 'cpis-download-page' );
			$cache_rejected_uri = array_merge( $cache_rejected_uri, $to_exlude );
		}
	}
} // End cpis_fixingCacheConflict

// Fixes possible conflict with cache plugins
cpis_fixingCacheConflict();

/**
* Plugin activation
*/
register_activation_hook( __FILE__, 'cpis_install' );
if ( ! function_exists( 'cpis_install' ) ) {
	function cpis_install( $networkwide ) {

		global $wpdb;
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $networkwide ) {

				$old_blog = $wpdb->blogid;

				// Get all blog ids
				$blogids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );

					// Set default options
					cpis_default_options();

					// Create database structure
					cpis_create_db( true );
				}

				switch_to_blog( $old_blog );
				return;
			}
		}

		cpis_default_options();
		cpis_create_db( true );

	} // End cpis_install
} // End plugin activation

// The first time the plugin is activated, it loads a Wizard with the basic settings of the store.
add_action( 'activated_plugin', 'cpis_redirect_to_settings', 10, 2 );
if ( ! function_exists( 'cpis_redirect_to_settings' ) ) {
	function cpis_redirect_to_settings( $plugin, $network_activation ) {
		if (
			empty( $_REQUEST['_ajax_nonce'] ) &&
			plugin_basename( __FILE__ ) == $plugin &&
			( ! isset( $_POST['action'] ) || 'activate-selected' != $_POST['action'] ) && // phpcs:ignore WordPress.Security.NonceVerification
			( ! isset( $_POST['action2'] ) || 'activate-selected' != $_POST['action2'] ) // phpcs:ignore WordPress.Security.NonceVerification
		) {
			wp_redirect( admin_url( 'admin.php?page=image-store-menu-settings' ) );
			exit;
		}
	}
}
// A new blog has been created in a multisite WordPress
add_action( 'wpmu_new_blog', 'cpis_install_new_blog', 10, 6 );

function cpis_install_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
	global $wpdb;
	if ( is_plugin_active_for_network() ) {
		$current_blog = $wpdb->blogid;
		switch_to_blog( $blog_id );
		cpis_default_options();
		cpis_create_db( true );
		switch_to_blog( $current_blog );
	}
}

if ( ! function_exists( 'cpis_default_options' ) ) {
	function cpis_default_options() {
		$cpis_defaul_options = array(
			// PayPal settings
			'paypal'       => array(
				'activate_paypal'  => true,
				'activate_sandbox' => false,
				'paypal_email'     => '',
				'currency_symbol'  => '$',
				'currency'         => 'USD',
				'language'         => 'Eng',
				'shopping_cart'    => false,
			),

			// Images settings
			'image'        => array(
				'thumbnail'      => array(
					'width'  => 150,
					'height' => 150,
				),
				'intermediate'   => array(
					'width'  => 400,
					'height' => 400,
				),
				'unit'           => 'In',
				'set_watermark'  => true,
				'watermark_text' => 'Image Store',
				'license'        => array(
					'title'       => '',
					'description' => '',
				),
			),

			// Display settings
			'display'      => array(
				'carousel' => array(
					'activate'        => true,
					'autorun'         => false,
					'transition_time' => 5, // In seconds
				),
				'preview'  => true,
			),

			// Store settings
			'store'        => array(
				'store_url'             => '',
				'show_search_box'       => true,
				'show_type_filters'     => true,
				'show_color_filters'    => true,
				'show_author_filters'   => true,
				'show_category_filters' => true,
				'show_ordering'         => true,
				'show_pagination'       => true,
				'items_page'            => 12,
				'columns'               => 3,
				'social_buttons'        => true,
				'facebook_app_id'       => '',
				'pack_files'            => false,
				'download_link'         => 3,
				'download_limit'        => 3,
				'display_promotion'     => true,
			),

			// Payment notifications
			'notification' => array(
				'from'                => 'put_your@emailhere.com',
				'to'                  => 'put_your@emailhere.com',
				'subject_payer'       => 'Thank you for your purchase...',
				'subject_seller'      => 'New product purchased...',
				'notification_payer'  => "We have received your purchase notification with the following information:\n\n%INFORMATION%\n\nThe download link is assigned an expiration time, please download the purchased product now.\n\nThank you.\n\nBest regards.",
				'notification_seller' => "New purchase made with the following information:\n\n%INFORMATION%\n\nBest regards.",
			),
		);
		$cpis_options = get_option( 'cpis_options', array() );
		if ( function_exists( 'array_replace_recursive' ) ) {
			$cpis_defaul_options = array_replace_recursive( $cpis_defaul_options, $cpis_options );
		}
		update_option( 'cpis_options', $cpis_defaul_options );
	}
} // End cpis_default_options

if ( ! function_exists( 'cpis_create_db' ) ) {
	function cpis_create_db( $installing = false ) {
		try {
			if ( ! $installing && ! empty( $GLOBALS[ CPIS_SESSION_NAME ]['cpis_created_db'] ) ) {
				return;
			}
			$GLOBALS[ CPIS_SESSION_NAME ]['cpis_created_db'] = true;

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();

			$db_queries   = array();
			$db_queries[] = 'CREATE TABLE ' . $wpdb->prefix . CPIS_IMAGE . " (
                id mediumint(9) NOT NULL,
                purchases mediumint(9) NOT NULL DEFAULT 0,
                preview TEXT,
                UNIQUE KEY id (id)
             ) $charset_collate;";

			$db_queries[] = 'CREATE TABLE ' . $wpdb->prefix . CPIS_FILE . " (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                path VARCHAR(255) NOT NULL,
                url VARCHAR(255) NOT NULL,
                width FLOAT,
                height FLOAT,
                price FLOAT,
                UNIQUE KEY id (id)
             ) $charset_collate;";

			$db_queries[] = 'CREATE TABLE ' . $wpdb->prefix . CPIS_IMAGE_FILE . " (
                id_image mediumint(9) NOT NULL,
                id_file mediumint(9) NOT NULL,
                UNIQUE KEY image_file (id_image, id_file)
             ) $charset_collate;";

			$db_queries[] = 'CREATE TABLE ' . $wpdb->prefix . CPIS_PURCHASE . " (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                product_id mediumint(9) NOT NULL,
                purchase_id varchar(50) NOT NULL,
                date DATETIME NOT NULL,
                checking_date DATETIME,
                email VARCHAR(255) NOT NULL,
                amount FLOAT NOT NULL DEFAULT 0,
                downloads INT NOT NULL DEFAULT 0,
                paypal_data TEXT,
                note TEXT,
                UNIQUE KEY id (id)
             ) $charset_collate;";

			dbDelta( $db_queries ); // Running the queries
		} catch ( Exception $exp ) {
			error_log( $exp->getMessage() );
		}
	}
} // End cpis_create_db

/** REGISTER POST TYPES AND TAXONOMIES */

/**
* Init Image Store post types
*/
if ( ! function_exists( 'cpis_init_post_types' ) ) {
	function cpis_init_post_types() {
		if ( ! post_type_exists( 'cpis_image' ) ) {
			// Post Types
			// Create image post type
			register_post_type(
				'cpis_image',
				array(
					'description'         => __( 'This is where you can add new image to your store.', 'cp-image-store' ),
					'capability_type'     => 'post',
					'supports'            => array( 'title', 'editor', 'comments' ),
					'exclude_from_search' => false,
					'taxonomies'          => array(),
					'public'              => true,
					'show_ui'             => true,
					'show_in_nav_menus'   => true,
					'show_in_menu'        => CPIS_IMAGE_STORE_SLUG,
					'labels'              => array(
						'name'               => __( 'Images', 'cp-image-store' ),
						'singular_name'      => __( 'Image', 'cp-image-store' ),
						'add_new'            => __( 'Add New', 'cp-image-store' ),
						'add_new_item'       => __( 'Add New Image', 'cp-image-store' ),
						'edit_item'          => __( 'Edit Image', 'cp-image-store' ),
						'new_item'           => __( 'New Image', 'cp-image-store' ),
						'view_item'          => __( 'View Image', 'cp-image-store' ),
						'search_items'       => __( 'Search Images', 'cp-image-store' ),
						'not_found'          => __( 'No images found', 'cp-image-store' ),
						'not_found_in_trash' => __( 'No images found in Trash', 'cp-image-store' ),
						'menu_name'          => __( 'Images for Sale', 'cp-image-store' ),
						'parent_item_colon'  => '',
					),
					'query_var'           => true,
					'has_archive'         => true,
					'rewrite'             => ( ( get_option( 'cpis_friendly_url', false ) * 1 ) ? true : false ),
				)
			);

			add_filter( 'manage_cpis_image_posts_columns', 'cpis_image_columns' );
			add_action( 'manage_cpis_image_posts_custom_column', 'cpis_image_columns_data', 2 );

			if ( get_option( 'cpis_friendly_url', false ) * 1 && empty( $GLOBALS[ CPIS_SESSION_NAME ]['cpis_flush_rewrite_rules'] ) ) {
				flush_rewrite_rules();
				$GLOBALS[ CPIS_SESSION_NAME ]['cpis_flush_rewrite_rules'] = 1;
			}
		}
	}
}// End cpis_init_post_types

/**
* Init Image Store taxonomies
*/
if ( ! function_exists( 'cpis_init_taxonomies' ) ) {
	function cpis_init_taxonomies() {

		if ( ! taxonomy_exists( 'cpis_category' ) ) {
			// Create Author taxonomy
			register_taxonomy(
				'cpis_category',
				array(
					'cpis_image',
				),
				array(
					'hierarchical'      => true,
					'label'             => __( 'Images Categories', 'cp-image-store' ),
					'labels'            => array(
						'name'          => __( 'Images Categories', 'cp-image-store' ),
						'singular_name' => __( 'Images Category', 'cp-image-store' ),
						'search_items'  => __( 'Search by Categories', 'cp-image-store' ),
						'all_items'     => __( 'All Images Categories', 'cp-image-store' ),
						'edit_item'     => __( 'Edit Category', 'cp-image-store' ),
						'update_item'   => __( 'Update Category', 'cp-image-store' ),
						'add_new_item'  => __( 'Add New Image Category', 'cp-image-store' ),
						'new_item_name' => __( 'New Category Name', 'cp-image-store' ),
						'menu_name'     => __( 'Images Categories', 'cp-image-store' ),
					),
					'public'            => true,
					'show_ui'           => true,
					'show_admin_column' => true,
					'query_var'         => true,
					'sort'              => false,
					'rewrite'           => false,
				)
			);
		}

		if ( ! taxonomy_exists( 'cpis_author' ) ) {
			// Create Author taxonomy
			register_taxonomy(
				'cpis_author',
				array(
					'cpis_image',
				),
				array(
					'hierarchical'      => false,
					'label'             => __( 'Authors', 'cp-image-store' ),
					'labels'            => array(
						'name'          => __( 'Authors', 'cp-image-store' ),
						'singular_name' => __( 'Author', 'cp-image-store' ),
						'search_items'  => __( 'Search by Authors', 'cp-image-store' ),
						'all_items'     => __( 'All Authors', 'cp-image-store' ),
						'edit_item'     => __( 'Edit Author', 'cp-image-store' ),
						'update_item'   => __( 'Update Author', 'cp-image-store' ),
						'add_new_item'  => __( 'Add New Author', 'cp-image-store' ),
						'new_item_name' => __( 'New Author Name', 'cp-image-store' ),
						'menu_name'     => __( 'Authors', 'cp-image-store' ),
					),
					'public'            => true,
					'show_ui'           => true,
					'show_admin_column' => true,
					'query_var'         => true,
					'rewrite'           => false,
				)
			);
		}

		if ( ! taxonomy_exists( 'cpis_color' ) ) {
			// Create Color taxonomy
			register_taxonomy(
				'cpis_color',
				array(
					'cpis_image',
				),
				array(
					'hierarchical'      => false,
					'label'             => __( 'Colors Scheme', 'cp-image-store' ),
					'labels'            => array(
						'name'          => __( 'Colors Schemes', 'cp-image-store' ),
						'singular_name' => __( 'Color Scheme', 'cp-image-store' ),
						'search_items'  => __( 'Search by Colors', 'cp-image-store' ),
						'all_items'     => __( 'All Colors Schemes', 'cp-image-store' ),
						'edit_item'     => __( 'Edit Color Scheme', 'cp-image-store' ),
						'update_item'   => __( 'Update Color Scheme', 'cp-image-store' ),
						'add_new_item'  => __( 'Add New Color Scheme', 'cp-image-store' ),
						'new_item_name' => __( 'New Color Scheme Name', 'cp-image-store' ),
						'menu_name'     => __( 'Colors Schemes', 'cp-image-store' ),
					),
					'public'            => true,
					'show_ui'           => true,
					'show_admin_column' => true,
					'query_var'         => true,
					'rewrite'           => false,
				)
			);

			wp_insert_term(
				'Black and white',
				'cpis_color'
			);
			wp_insert_term(
				'Full color',
				'cpis_color'
			);
		}

		if ( ! taxonomy_exists( 'cpis_type' ) ) {
			// Register artist taxonomy
			register_taxonomy(
				'cpis_type',
				array(
					'cpis_image',
				),
				array(
					'hierarchical'      => false,
					'label'             => __( 'Types', 'cp-image-store' ),
					'labels'            => array(
						'name'          => __( 'Types', 'cp-image-store' ),
						'singular_name' => __( 'Type', 'cp-image-store' ),
						'search_items'  => __( 'Search Types', 'cp-image-store' ),
						'all_items'     => __( 'All Types', 'cp-image-store' ),
						'edit_item'     => __( 'Edit Type', 'cp-image-store' ),
						'update_item'   => __( 'Update Type', 'cp-image-store' ),
						'add_new_item'  => __( 'Add New Type', 'cp-image-store' ),
						'new_item_name' => __( 'New Type Name', 'cp-image-store' ),
						'menu_name'     => __( 'Types', 'cp-image-store' ),
					),
					'public'            => true,
					'show_ui'           => true,
					'show_admin_column' => true,
					'query_var'         => true,
					'rewrite'           => false,
				)
			);

			wp_insert_term(
				'Photo',
				'cpis_type'
			);

			wp_insert_term(
				'Clip art',
				'cpis_type'
			);

			wp_insert_term(
				'Line drawing',
				'cpis_type'
			);
		}

		do_action( 'image_store_register_taxonomy' );
	}
} // End cpis_init_taxonomies

// The plugin ini
add_action( 'init', 'cpis_init', 1 );
add_action( 'widgets_init', 'cpis_load_widgets' );
if ( ! function_exists( 'cpis_init' ) ) {
	function cpis_init() {
		global $cpis_layout;

		load_plugin_textdomain( 'cp-image-store', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Load selected layout
		if ( false !== get_option( 'cpis_layout' ) ) {
			$cpis_layout = get_option( 'cpis_layout' );
		}

		// Create post types
		cpis_init_post_types();

		// Create taxonomies
		cpis_init_taxonomies();

		add_action( 'save_post', 'cpis_save_image', 10, 3 );

		if ( ! is_admin() ) {
			add_action( 'wp_footer', 'cpis_footer' );
			add_filter( 'get_pages', 'cpis_exclude_pages' );
			if ( isset( $_REQUEST ) && isset( $_REQUEST['cpis-action'] ) ) {
				$options      = get_option( 'cpis_options' );
				$_cpis_action = strtolower( sanitize_text_field( wp_unslash( $_REQUEST['cpis-action'] ) ) );
				switch ( $_cpis_action ) {
					case 'buynow':
						include CPIS_PLUGIN_DIR . '/includes/submit.php';
						exit;
					break;

					case 'f-download':
						cpis_download_file();
						break;

					default:
						$cpis_action = sanitize_text_field( wp_unslash( $_REQUEST['cpis-action'] ) );
						if (
							stripos( $cpis_action, 'ipn|' ) !== false ||
							( $_GET['cpis-action'] = get_transient( $cpis_action ) ) !== false
						) {
							delete_transient( $cpis_action );
							if ( ! empty( $options['store']['debug_payment'] ) ) {
								@error_log( 'Image Store payment gateway GET parameters: ' . json_encode( $_GET ) ); // @codingStandardsIgnoreLine
								@error_log( 'Image Store payment gateway POST parameters: ' . json_encode( $_POST ) ); // @codingStandardsIgnoreLine
							}
							include CPIS_PLUGIN_DIR . '/includes/ipn.php';
							exit;
						}
						break;
				}
			}
			add_shortcode( 'codepeople-image-store', 'cpis_replace_shortcode' );
			add_shortcode( 'codepeople-image-store-product', 'cpis_replace_product_shortcode' );
			add_filter( 'the_content', 'cpis_the_content', 1 );
			add_filter( 'the_excerpt', 'cpis_the_excerpt', 1 );
			add_filter( 'get_the_excerpt', 'cpis_the_excerpt', 1 );
			add_action( 'wp_head', 'cpis_load_meta', 1 );
		}
		cpis_preview();
	}
} // End cpis_ini

if ( ! function_exists( 'cpis_load_layouts' ) ) {
	/**
	 * Get the list of available layouts
	 */
	function cpis_load_layouts() {
		global $cpis_layouts;

		$tpls_dir = dir( CPIS_PLUGIN_DIR . '/layouts' );
		while ( false !== ( $entry = $tpls_dir->read() ) ) {
			if ( '.' != $entry && '..' != $entry && is_dir( $tpls_dir->path . '/' . $entry ) && file_exists( $tpls_dir->path . '/' . $entry . '/config.ini' ) ) {
				if ( ( $ini_array = parse_ini_file( $tpls_dir->path . '/' . $entry . '/config.ini' ) ) !== false ) { // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
					if ( ! empty( $ini_array['style_file'] ) ) {
						$ini_array['style_file'] = CPIS_PLUGIN_URL . '/layouts/' . $entry . '/' . $ini_array['style_file'];
					}
					if ( ! empty( $ini_array['script_file'] ) ) {
						$ini_array['script_file'] = CPIS_PLUGIN_URL . '/layouts/' . $entry . '/' . $ini_array['script_file'];
					}
					if ( ! empty( $ini_array['thumbnail'] ) ) {
						$ini_array['thumbnail'] = CPIS_PLUGIN_URL . '/layouts/' . $entry . '/' . $ini_array['thumbnail'];
					}
					$cpis_layouts[ $ini_array['id'] ] = $ini_array;
				}
			}
		}
	}
}

if ( ! function_exists( 'cpis_load_widgets' ) ) {
	function cpis_load_widgets() {
		register_widget( 'CPISProductWidget' );
	}
}


if ( ! function_exists( 'cpis_footer' ) ) {
	function cpis_footer() {
		global $cpis_images_preview;
		print $cpis_images_preview; // @codingStandardsIgnoreLine
	}
}

if ( ! function_exists( 'cpis_load_meta' ) ) {
	function cpis_load_meta() {
		global $post;
		if ( isset( $post ) ) {
			if ( 'cpis_image' == $post->post_type ) {
				cpis_display_content( $post->ID, 'facebook_meta', 'echo' );
			}
		}
	}
}

add_filter( 'display_post_states', 'cpis_display_post_states', 10, 2 );
if ( ! function_exists( 'cpis_display_post_states' ) ) {
	function cpis_display_post_states( $post_states, $post ) {
		if ( 'cpis-download-page' == $post->post_name ) {
			$post_states['cpis-download-page'] = 'Image Store - ' . __( 'Download Page', 'cp-image-store' );
		}

		return $post_states;
	} //  End cpis_display_post_states
}

add_action( 'admin_init', 'cpis_admin_init', 1 );
if ( ! function_exists( 'cpis_admin_init' ) ) {

	function _cpis_create_pages( $slug, $title ) {
		if ( isset( $GLOBALS[ CPIS_SESSION_NAME ][ $slug ] ) ) {
			return $GLOBALS[ CPIS_SESSION_NAME ][ $slug ];
		}

		$page = get_page_by_path( $slug );
		if ( is_null( $page ) ) {
			if ( is_admin() ) {
				if ( false != ( $id = wp_insert_post( // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
					array(
						'comment_status' => 'closed',
						'post_name'      => $slug,
						'post_title'     => __( $title, 'cp-image-store' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
						'post_status'    => 'publish',
						'post_type'      => 'page',
						'post_content'   => '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->',
					)
				)
					)
				) {
					$GLOBALS[ CPIS_SESSION_NAME ][ $slug ] = get_permalink( $id );
				}
			}
		} else {
			if ( is_admin() && 'publish' != $page->post_status ) {
				$page->post_status = 'publish';
				wp_update_post( $page );
			}
			$GLOBALS[ CPIS_SESSION_NAME ][ $slug ] = get_permalink( $page->ID );
		}

		$GLOBALS[ CPIS_SESSION_NAME ][ $slug ] = ( isset( $GLOBALS[ CPIS_SESSION_NAME ][ $slug ] ) ) ? $GLOBALS[ CPIS_SESSION_NAME ][ $slug ] : CPIS_H_URL;
		return $GLOBALS[ CPIS_SESSION_NAME ][ $slug ];
	}

	function cpis_admin_init() {
		global $wpdb;

		$plugin = plugin_basename( __FILE__ );
		add_filter( 'plugin_action_links_' . $plugin, 'cpis_customAdjustmentsLink' );

		// Create database
		// cpis_create_db();

		if ( isset( $_REQUEST['cpis-action'] ) ) {
			if ( 'paypal-data' == $_REQUEST['cpis-action'] ) {
				if ( isset( $_REQUEST['data'] ) && isset( $_REQUEST['from'] ) && isset( $_REQUEST['to'] ) ) {
					$where = $wpdb->prepare( 'DATEDIFF(date, %s)>=0 AND DATEDIFF(date, %s)<=0', sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ), sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) );
					switch ( $_REQUEST['data'] ) {
						case 'residence_country':
							print cpis_getFromPayPalData( array( 'residence_country' => 'residence_country' ), 'COUNT(*) AS count', '', $where, array( 'residence_country' ), array( 'count' => 'DESC' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							break;
						case 'mc_currency':
							print cpis_getFromPayPalData( array( 'mc_currency' => 'mc_currency' ), 'SUM(amount) AS sum', '', $where, array( 'mc_currency' ), array( 'sum' => 'DESC' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							break;
						case 'product_name':
							$from   = $wpdb->posts . ' AS posts,' . $wpdb->prefix . CPIS_IMAGE_FILE . ' AS image_file';
							$where .= ' AND product_id = image_file.id_file AND posts.ID = image_file.id_image';

							$json = cpis_getFromPayPalData( array( 'mc_currency' => 'mc_currency' ), 'SUM(amount) AS sum, post_title', $from, $where, array( 'product_id', 'mc_currency' ) );
							$obj  = json_decode( $json );
							foreach ( $obj as $key => $value ) {
								$obj[ $key ]->post_title .= ' [' . $value->mc_currency . ']';
							}
							print json_encode( $obj ); // phpcs:ignore WordPress.Security.EscapeOutput
							break;
					}
				}
				exit;
			} elseif ( 'csv' == $_REQUEST['cpis-action'] ) {
				header( 'Content-type: application/octet-stream' );
				header( 'Content-Disposition: attachment; filename=export.csv' );

				$from_day   = ( isset( $_POST['from_day'] ) ) ? @intval( $_POST['from_day'] ) : gmdate( 'j' );
				$from_month = ( isset( $_POST['from_month'] ) ) ? @intval( $_POST['from_month'] ) : gmdate( 'm' );
				$from_year  = ( isset( $_POST['from_year'] ) ) ? @intval( $_POST['from_year'] ) : gmdate( 'Y' );
				$buyer      = ( ! empty( $_POST['buyer'] ) ) ? sanitize_text_field( wp_unslash( $_POST['buyer'] ) ) : '';
				$buyer      = trim( $buyer );

				$to_day   = ( isset( $_POST['to_day'] ) ) ? @intval( $_POST['to_day'] ) : gmdate( 'j' );
				$to_month = ( isset( $_POST['to_month'] ) ) ? @intval( $_POST['to_month'] ) : gmdate( 'm' );
				$to_year  = ( isset( $_POST['to_year'] ) ) ? @intval( $_POST['to_year'] ) : gmdate( 'Y' );

				$csv_header = array( __( 'Date', 'cp-image-store' ), __( 'Product', 'cp-image-store' ), __( 'Buyer', 'cp-image-store' ), __( 'Amount', 'cp-image-store' ), __( 'Currency', 'cp-image-store' ), __( 'Download link', 'cp-image-store' ), __( 'Notes', 'cp-image-store' ), '' );
				$dlurl      = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
				$dlurl     .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' );

				$_select .= 'SELECT purchase.*, posts.*';
				$_from    = ' FROM ' . $wpdb->prefix . CPIS_PURCHASE . ' AS purchase, ' . $wpdb->prefix . 'posts AS posts, ' . $wpdb->prefix . CPIS_IMAGE_FILE . ' AS image_file';
				$_where   = $wpdb->prepare(
					" WHERE posts.ID = image_file.id_image
							AND image_file.id_file = purchase.product_id
							AND DATEDIFF(purchase.date, '%d-%d-%d')>=0
							AND DATEDIFF(purchase.date, '%d-%d-%d')<=0 ",
					$from_year,
					$from_month,
					$from_day,
					$to_year,
					$to_month,
					$to_day
				);
				if ( ! empty( $buyer ) ) {
					$_where .= $wpdb->prepare( 'AND purchase.email LIKE %s', '%' . $buyer . '%' );
				}

				$rows              = $wpdb->get_results( $_select . $_from . $_where ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$_count_csv_header = count( $csv_header );
				for ( $i = 0; $i < $_count_csv_header; $i++ ) {
					echo '"' . str_replace( '"', '""', $csv_header[ $i ] ) . '",'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo "\n";
				foreach ( $rows as $row ) {
					$currency = '';
					if ( preg_match( '/mc_currency=([^\s]*)/', $row->paypal_data, $matches ) ) {
						$currency = strtoupper( $matches[1] );
					}

					echo '"' . str_replace( '"', '""', $row->date ) . '",'; // phpcs:ignore WordPress.Security.EscapeOutput
					echo '"' . str_replace( '"', '""', ( ( empty( $row->post_title ) ) ? $row->ID : $row->post_title ) ) . '",'; // phpcs:ignore WordPress.Security.EscapeOutput
					echo '"' . str_replace( '"', '""', $row->email ) . '",'; // phpcs:ignore WordPress.Security.EscapeOutput
					echo '"' . str_replace( '"', '""', $row->amount ) . '",'; // phpcs:ignore WordPress.Security.EscapeOutput
					echo '"' . str_replace( '"', '""', $currency ) . '",'; // phpcs:ignore WordPress.Security.EscapeOutput
					echo '"' . str_replace( '"', '""', $dlurl . 'cpis-action=download&purchase_id=' . $row->purchase_id ) . '",'; // phpcs:ignore WordPress.Security.EscapeOutput
					echo "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				exit;
			} elseif ( 'import' == $_REQUEST['cpis-action'] ) {
				// Changing upload directory for cpis_image
				add_filter( 'upload_dir', 'cpis_upload_dir' );

				try {
					if ( empty( $_POST['cpis_import'] ) || ! ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cpis_import'] ) ), 'session_id_' . session_id() ) || ! current_user_can( 'manage_options' ) ) ) {
						throw new Exception( __( 'You have not sufficient privileges to import images', 'cp-image-store' ) );
					}
					require_once __DIR__ . '/includes/import.php';
					cpis_import();
				} catch ( Exception $err ) {
					global $cpis_recursive_call;
					$cpis_recursive_call = 0;
					print '<div style="text-align:center;"><h1>' . wp_kses_post( $err->getMessage() ) . '</h1></div>';
				}
				exit;
			}
		}

		// Init the metaboxs for images
		add_meta_box( 'cpis_image_metabox', __( "Image's data", 'cp-image-store' ), 'cpis_image_metabox', 'cpis_image', 'normal', 'high' );

		// Save images
		// add_action( 'save_post', 'cpis_save_image', 10, 3 );

		add_action( 'admin_head', 'cpis_removemediabuttons' );

		// Changing upload directory for cpis_image
		add_filter( 'upload_dir', 'cpis_upload_dir' );

		// Set a new media button for store insertion
		add_action( 'media_buttons', 'cpis_store_button', 100 );

		_cpis_create_pages( 'cpis-download-page', 'Download Page' );

		if ( isset( $_REQUEST['cpis-action'] ) ) {
			switch ( strtolower( sanitize_text_field( wp_unslash( $_REQUEST['cpis-action'] ) ) ) ) {
				case 'remove-image':
					if ( ! empty( $_REQUEST['image'] ) || ! is_numeric( $_REQUEST['image'] ) ) {
						print cpis_remove_image( intval( $_REQUEST['image'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					} else {
						print '{ "error" : "Image ID is required" }';
					}
					exit;
				break;
			}
		}
	}
} // End cpis_admin_ini

if ( ! function_exists( 'cpis_customAdjustmentsLink' ) ) {
	function cpis_customAdjustmentsLink( $links ) {
		$customAdjustments_link = '<a href="http://wordpress.dwbooster.com/contact-us" target="_blank">' . __( 'Request custom changes', 'cp-image-store' ) . '</a>';
		array_unshift( $links, $customAdjustments_link );
		$help_link = '<a href="https://wordpress.org/support/plugin/cp-image-store#new-post" target="_blank">' . __( 'Help', 'cp-image-store' ) . '</a>';
		array_unshift( $links, $help_link );
		return $links;
	}
} // End cpis_customAdjustmentsLink

if ( ! function_exists( 'cpis_preview' ) ) {
	function cpis_preview() {
		$user          = wp_get_current_user();
		$allowed_roles = array( 'editor', 'administrator', 'author' );

		if ( array_intersect( $allowed_roles, $user->roles ) ) {
			if ( ! empty( $_REQUEST['cpis-preview'] ) ) {
				// Sanitizing variable
				$preview = sanitize_text_field( wp_unslash( $_REQUEST['cpis-preview'] ) );
				$preview = strip_tags( $preview );

				// Remove every shortcode that is not in the music store list
				remove_all_shortcodes();

				add_shortcode( 'codepeople-image-store', 'cpis_replace_shortcode' );
				add_shortcode( 'codepeople-image-store-product', 'cpis_replace_product_shortcode' );

				if (
					has_shortcode( $preview, 'codepeople-image-store' ) ||
					has_shortcode( $preview, 'codepeople-image-store-product' )
				) {
					print '<!DOCTYPE html>';
					$plus = '+25';

					$if_empty = __( 'There are no products that satisfy the block\'s settings', 'cp-image-store' );
					$output   = do_shortcode( $preview ) . '<div style="clear:both;"></div>';
					if ( preg_match( '/^\s*$/', $output ) ) {
						$output = '<div>' . $if_empty . '</div>';
					}
					print '<script type="text/javascript">var min_screen_width = 0;</script>';
					print '<style>body{width:640px;-ms-transform: scale(0.78);-moz-transform: scale(0.78);-o-transform: scale(0.78);-webkit-transform: scale(0.78);transform: scale(0.78);-ms-transform-origin: 0 0;-moz-transform-origin: 0 0;-o-transform-origin: 0 0;-webkit-transform-origin: 0 0;transform-origin: 0 0;}</style>';

					// Deregister all scripts and styles for loading only the plugin styles.
					global  $wp_styles, $wp_scripts;
					if ( ! empty( $wp_scripts ) ) {
						$wp_scripts->reset();
					}
					cpis_enqueue_scripts();
					if ( ! empty( $wp_styles ) ) {
						$wp_styles->do_items();
					}
					if ( ! empty( $wp_scripts ) ) {
						$wp_scripts->do_items();
					}

					print '<div class="cpis-preview-container">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput

					print '<script type="text/javascript">jQuery(window).on("load", function(){ var frameEl = window.frameElement; if(frameEl) frameEl.height = jQuery(".cpis-preview-container").outerHeight(true)*0.78+25; });</script><style>.cpis-image-store-left{max-width:200px !important;} .cpis-image-store-right{width: calc(100% - 220px) !important;}</style>';
					exit;
				}
			}
		}
	} // End cpis_preview
}

if ( ! function_exists( 'cpis_store_button' ) ) {
	function cpis_store_button() {
		global $post;

		if ( isset( $post ) && 'cpis_image' != $post->post_type ) {
			print '<a href="javascript:cpis_insert_store();" title="' . esc_attr( __( 'Insert Image Store', 'cp-image-store' ) ) . '"><img src="' . esc_url( CPIS_PLUGIN_URL . '/images/image-store-icon.png' ) . '" alt="' . esc_attr( __( 'Insert Image Store', 'cp-image-store' ) ) . '" /></a>';

			print '<a href="javascript:cpis_insert_product_window();" title="' . esc_attr( __( 'Insert Image Product', 'cp-image-store' ) ) . '"><img src="' . esc_url( CPIS_PLUGIN_URL . '/images/image-store-insert-product.png' ) . '" alt="' . esc_attr( __( 'Insert Image Product', 'cp-image-store' ) ) . '" /></a>';
		}
	}
} // End cpis_store_button

add_action( 'admin_menu', 'cpis_menu_links', 10 );
if ( ! function_exists( 'cpis_menu_links' ) ) {
	function cpis_menu_links() {
		if ( is_admin() ) {

			add_options_page( 'Image Store', 'Image Store', 'manage_options', CPIS_IMAGE_STORE_SLUG . '-settings-page', 'cpis_settings_page' );

			add_menu_page( 'Image Store', 'Image Store', 'edit_pages', CPIS_IMAGE_STORE_SLUG, null, CPIS_IMAGES_URL . '/image-store-menu-icon.png' );

			// Settings Submenu
			add_submenu_page( CPIS_IMAGE_STORE_SLUG, 'Image Store Settings', 'Store Settings', 'manage_options', CPIS_IMAGE_STORE_SLUG . '-settings', 'cpis_settings_page' );

			// Importing Submenu
			add_submenu_page( CPIS_IMAGE_STORE_SLUG, __( 'Importing Area', 'cp-image-store' ), __( 'Importing Area', 'cp-image-store' ), 'manage_options', CPIS_IMAGE_STORE_SLUG . '-import', 'cpis_import_page' );

			// Sales report submenu
			add_submenu_page( CPIS_IMAGE_STORE_SLUG, 'Image Store Sales Report', 'Sales Report', 'manage_options', CPIS_IMAGE_STORE_SLUG . '-reports', 'cpis_reports_page' );

			// Submenu for taxonomies
			add_submenu_page( CPIS_IMAGE_STORE_SLUG, __( 'Author', 'cp-image-store' ), __( 'Authors', 'cp-image-store' ), 'edit_pages', 'edit-tags.php?taxonomy=cpis_author' );

			add_submenu_page( CPIS_IMAGE_STORE_SLUG, __( 'Color Scheme', 'cp-image-store' ), __( 'Color Schemes', 'cp-image-store' ), 'edit_pages', 'edit-tags.php?taxonomy=cpis_color' );

			add_submenu_page( CPIS_IMAGE_STORE_SLUG, __( 'Type', 'cp-image-store' ), __( 'Types', 'cp-image-store' ), 'edit_pages', 'edit-tags.php?taxonomy=cpis_type' );

			add_submenu_page( CPIS_IMAGE_STORE_SLUG, __( 'Category', 'cp-image-store' ), __( 'Categories', 'cp-image-store' ), 'edit_pages', 'edit-tags.php?taxonomy=cpis_category' );

			add_action( 'parent_file', 'cpis_tax_menu_correction' );

			// Remove the taxonomies box from side column
			remove_meta_box( 'tagsdiv-cpis_type', 'cpis_image', 'side' );
			remove_meta_box( 'tagsdiv-cpis_color', 'cpis_image', 'side' );
			remove_meta_box( 'tagsdiv-cpis_author', 'cpis_image', 'side' );

		}
	}
} // End cpis_ini

// highlight the proper top level menu for taxonomies submenus
if ( ! function_exists( 'cpis_tax_menu_correction' ) ) {
	function cpis_tax_menu_correction( $parent_file ) {
		global $current_screen;
		$taxonomy = $current_screen->taxonomy;
		if ( 'cpis_author' == $taxonomy || 'cpis_color' == $taxonomy || 'cpis_type' == $taxonomy ) {
			$parent_file = CPIS_IMAGE_STORE_SLUG;
		}
		return $parent_file;
	} // End tax_menu_correction
} // End cpis_tax_menu_correction

if ( ! function_exists( 'cpis_exclude_pages' ) ) {
	function cpis_exclude_pages( $pages ) {
		$exclude = array();
		$length  = count( $pages );

		$p = get_page_by_path( 'cpis-download-page' );
		if ( ! is_null( $p ) ) {
			$exclude[] = $p->ID;
		}

		for ( $i = 0; $i < $length; $i++ ) {
			$page = &$pages[ $i ];

			if ( isset( $page ) && in_array( $page->ID, $exclude ) ) {
				// Finally, delete something(s)
				unset( $pages[ $i ] );
			}
		}

		return $pages;
	}
} // End cpis_exclude_pages

/**
 * Settings form of store
 */
if ( ! function_exists( 'cpis_settings_page' ) ) {
	function cpis_settings_page() {
		global $wpdb, $cpis_layouts, $cpis_layout;

		cpis_load_layouts();

		$options = get_option( 'cpis_options' );

		include_once dirname( __FILE__ ) . '/includes/wizard.php';
		if ( ! empty( $wizard_active ) ) {
			return;
		}

		if ( isset( $_POST['cpis_settings'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cpis_settings'] ) ), plugin_basename( __FILE__ ) ) ) {
			$noptions = array();

			$cpis_currency_symbol = isset( $_POST['cpis_currency_symbol'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_currency_symbol'] ) ) : '';
			$cpis_currency        = isset( $_POST['cpis_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_currency'] ) ) : '';
			$cpis_language        = isset( $_POST['cpis_language'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_language'] ) ) : '';

			if ( ! empty( $_POST['cpis_layout'] ) ) {
				$_cpis_layout = sanitize_text_field( wp_unslash( $_POST['cpis_layout'] ) );
				$cpis_layout  = $cpis_layouts[ $_cpis_layout ];
				update_option( 'cpis_layout', $cpis_layout );
			} else {
				delete_option( 'cpis_layout' );
				$cpis_layout = array();
			}

			$noptions['paypal'] = array(
				'activate_paypal'  => isset( $_POST['cpis_activate_paypal'] ) ? true : false,
				'activate_sandbox' => isset( $_POST['cpis_activate_sandbox'] ) ? true : false,
				'paypal_email'     => isset( $_POST['cpis_paypal_email'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_paypal_email'] ) ) : '',
				'currency_symbol'  => ! empty( $cpis_currency_symbol ) ? sanitize_text_field( wp_unslash( $_POST['cpis_currency_symbol'] ) ) : '$',
				'currency'         => ! empty( $cpis_currency ) ? sanitize_text_field( wp_unslash( $_POST['cpis_currency'] ) ) : 'USD',
				'language'         => ! empty( $cpis_language ) ? sanitize_text_field( wp_unslash( $_POST['cpis_language'] ) ) : 'Eng',
				'tax'              => ( ! empty( $_POST['cpis_tax'] ) && ( $cpis_tax = sanitize_text_field( wp_unslash( $_POST['cpis_tax'] ) ) ) != '' ) ? @floatval( $cpis_tax ) : '', // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
				'shopping_cart'    => false,
			);

			$thumbnail_w = isset( $_POST['cpis_thumbnail_width'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_thumbnail_width'] ) ) : '';
			$thumbnail_h = isset( $_POST['cpis_thumbnail_height'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_thumbnail_height'] ) ) : '';

			$intermediate_w = isset( $_POST['cpis_intermediate_width'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_intermediate_width'] ) ) : '';
			$intermediate_h = isset( $_POST['cpis_intermediate_height'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_intermediate_height'] ) ) : '';

			$noptions['image'] = array(
				'unit'           => isset( $_POST['cpis_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_unit'] ) ) : '',
				'set_watermark'  => false,
				'watermark_text' => 'Image Store',
				'license'        => array(
					'title'       => isset( $_POST['cpis_license_title'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_license_title'] ) ) : '',
					'description' => isset( $_POST['cpis_license_description'] ) ? wp_kses_post( wp_unslash( $_POST['cpis_license_description'] ) ) : '',
				),
				'thumbnail'      => array(
					'width'  => ( ( is_numeric( $thumbnail_w ) && $thumbnail_w > 0 ) ? $thumbnail_w : $options['image']['thumbnail']['width'] ),
					'height' => ( ( is_numeric( $thumbnail_h ) && $thumbnail_h > 0 ) ? $thumbnail_h : $options['image']['thumbnail']['height'] ),
				),
				'intermediate'   => array(
					'width'  => ( ( is_numeric( $intermediate_w ) && $intermediate_w > 0 ) ? $intermediate_w : $options['image']['intermediate']['width'] ),
					'height' => ( ( is_numeric( $intermediate_h ) && $intermediate_h > 0 ) ? $intermediate_h : $options['image']['intermediate']['height'] ),
				),
			);

			$noptions['display'] = array(
				'carousel' => array(
					'activate'        => isset( $_POST['cpis_activate_carousel'] ) ? true : false,
					'autorun'         => isset( $_POST['cpis_autorun_carousel'] ) ? true : false,
					'transition_time' => isset( $_POST['cpis_carousel_transition_time'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_carousel_transition_time'] ) ) : '',
				),
				'preview'  => isset( $_POST['cpis_activate_preview'] ) ? true : false,
			);

			$noptions['store'] = array(
				'store_url'                         => isset( $_POST['cpis_store_url'] ) ? esc_url_raw( wp_unslash( $_POST['cpis_store_url'] ) ) : '',
				'show_search_box'                   => isset( $_POST['cpis_show_search_box'] ) ? true : false,
				'show_color_filters'                => isset( $_POST['cpis_show_color_filters'] ) ? true : false,
				'show_type_filters'                 => isset( $_POST['cpis_show_type_filters'] ) ? true : false,
				'show_author_filters'               => isset( $_POST['cpis_show_author_filters'] ) ? true : false,
				'show_category_filters'             => isset( $_POST['cpis_show_category_filters'] ) ? true : false,
				'show_ordering'                     => isset( $_POST['cpis_show_ordering'] ) ? true : false,
				'show_pagination'                   => isset( $_POST['cpis_show_pagination'] ) ? true : false,
				'items_page'                        => isset( $_POST['cpis_items_page'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_items_page'] ) ) : '',
				'social_buttons'                    => isset( $_POST['cpis_social_buttons'] ) ? true : false,
				'facebook_app_id'                   => isset( $_POST['cpis_facebook_app_id'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_facebook_app_id'] ) ) : '',
				'columns'                           => isset( $_POST['cpis_columns'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_columns'] ) ) : '',
				'pack_files'                        => false,
				'download_link'                     => isset( $_POST['cpis_download_link'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_download_link'] ) ) : '',
				'download_limit'                    => isset( $_POST['cpis_download_limit'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_download_limit'] ) ) : '',
				'display_promotion'                 => false,
				'debug_payment'                     => isset( $_POST['cpis_debug_payment'] ) ? true : false,
				'download_link_for_registered_only' => isset( $_POST['cpis_download_link_for_registered_only'] ) ? true : false,
			);

			$noptions['notification'] = array(
				'from'                => isset( $_POST['cpis_from'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_from'] ) ) : '',
				'to'                  => isset( $_POST['cpis_to'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_to'] ) ) : '',
				'subject_payer'       => isset( $_POST['cpis_subject_payer'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_subject_payer'] ) ) : '',
				'subject_seller'      => isset( $_POST['cpis_subject_seller'] ) ? sanitize_text_field( wp_unslash( $_POST['cpis_subject_seller'] ) ) : '',
				'notification_payer'  => isset( $_POST['cpis_notification_payer'] ) ? wp_kses_data( wp_unslash( $_POST['cpis_notification_payer'] ) ) : '',
				'notification_seller' => isset( $_POST['cpis_notification_seller'] ) ? wp_kses_data( wp_unslash( $_POST['cpis_notification_seller'] ) ) : '',
			);

			update_option( 'cpis_options', $noptions );
			update_option( 'cpis_safe_download', isset( $_POST['cpis_safe_download'] ) ? true : false );
			update_option( 'cpis_friendly_url', isset( $_POST['cpis_friendly_url'] ) ? 1 : 0 );
			update_option( 'cpis_prevent_cache', isset( $_POST['cpis_prevent_cache'] ) ? 1 : 0 );

			$options = $noptions;
			do_action( 'cpis_save_settings' );
			unset( $GLOBALS[ CPIS_SESSION_NAME ]['cpis_flush_rewrite_rules'] );
			?>
			<div class="updated" style="margin:5px 0;"><strong><?php esc_html_e( 'Settings Updated', 'cp-image-store' ); ?></strong></div>
			<?php
			if ( empty( $noptions['paypal']['paypal_email'] ) ) {
				print '<div class="updated" style="margin:5px 0;"><strong>' . esc_html__( 'If you want to sell the images, must enter the email associated to your PayPal account.', 'cp-image-store' ) . '</strong></div>';
			}
		}

		$options = get_option( 'cpis_options' );
		if ( 'put_your@emailhere.com' == $options['notification']['from'] ) {
			$user_email = get_the_author_meta( 'user_email', get_current_user_id() );
			$host       = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			preg_match( '/[^\.\/]+(\.[^\.\/]+)?$/', $host, $matches );
			$domain = $matches[0];
			$pos    = strpos( $user_email, $domain );
			if ( false === $pos ) {
				$options['notification']['from'] = 'admin@' . $domain;
			}
		}

		if ( 'put_your@emailhere.com' == $options['notification']['to'] ) {
			if ( ! isset( $user_email ) ) {
				$user_email = get_the_author_meta( 'user_email', get_current_user_id() );
			}
			if ( ! empty( $user_email ) ) {
				$options['notification']['to'] = $user_email;
			}
		}
		?>
		<p style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;">
			To request a customization, <a href="http://wordpress.dwbooster.com/contact-us" target="_blank">CLICK HERE</a><br />
			I've a question: <a href="https://wordpress.org/support/plugin/cp-image-store#new-post" target="_blank">CLICK HERE</a><br />
			<br />If you want test the premium version of Image Store go to the following links:<br/> <a href="https://demos.dwbooster.com/image-store/wp-login.php" target="_blank">Administration area: Click to access the administration area demo</a><br/>
			<a href="https://demos.dwbooster.com/image-store/" target="_blank">Public page: Click to access the Store Page</a>
		</p>

		<form method="post" action="<?php echo isset( $_SERVER['REQUEST_URI'] ) ? esc_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : ''; ?>">
			<input type="hidden" name="tab" value="settings" />

			<!-- STORE CONFIG -->
			<div class="postbox">
				<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Store page config', 'cp-image-store' ); ?></span></h3>
				<div class="inside">
					<table class="form-table">
						<tr valign="top">
							<th><?php esc_html_e( 'URL of store page', 'cp-image-store' ); ?></th>
							<td>
								<input type="text" name="cpis_store_url" size="40" value="<?php echo esc_attr( $options['store']['store_url'] ); ?>" />
								<br />
								<em><?php esc_html_e( 'Set the URL of page where the store was inserted', 'cp-image-store' ); ?></em>
							</td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Store layout', 'cp-image-store' ); ?></th>
							<td>
								<select name="cpis_layout" id="cpis_layout">
									<option value=""><?php esc_html_e( 'Default layout', 'cp-image-store' ); ?></option>
								<?php
								foreach ( $cpis_layouts as $id => $layout ) {
									print '<option value="' . esc_attr( $id ) . '" ' . ( ( ! empty( $cpis_layout ) && $id == $cpis_layout['id'] ) ? 'SELECTED' : '' ) . ' thumbnail="' . esc_url( $layout['thumbnail'] ) . '">' . esc_html( $layout['title'] ) . '</option>';
								}
								?>
								</select>
								<div id="cpis_layout_thumbnail">
								<?php
								if ( ! empty( $cpis_layout ) ) {
									print '<img src="' . esc_url( $cpis_layout['thumbnail'] ) . '" title="' . esc_attr( $cpis_layout['title'] ) . '" />';
								}
								?>
								</div>
							</td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Display a search box', 'cp-image-store' ); ?></th>
							<td><input type="checkbox" name="cpis_show_search_box" size="40" value="1" <?php if ( isset( $options['store']['show_search_box'] ) && $options['store']['show_search_box'] ) {
								echo 'checked';} ?> /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Allow filtering by type', 'cp-image-store' ); ?></th>
							<td><input type="checkbox" name="cpis_show_type_filters" size="40" value="1" <?php if ( isset( $options['store']['show_type_filters'] ) && $options['store']['show_type_filters'] ) {
								echo 'checked';} ?> /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Allow filtering by color', 'cp-image-store' ); ?></th>
							<td><input type="checkbox" name="cpis_show_color_filters" size="40" value="1" <?php if ( isset( $options['store']['show_color_filters'] ) && $options['store']['show_color_filters'] ) {
								echo 'checked';} ?> /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Allow filtering by author', 'cp-image-store' ); ?></th>
							<td><input type="checkbox" name="cpis_show_author_filters" size="40" value="1" <?php if ( isset( $options['store']['show_author_filters'] ) && $options['store']['show_author_filters'] ) {
								echo 'checked';} ?> /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Allow filtering by category', 'cp-image-store' ); ?></th>
							<td><input type="checkbox" name="cpis_show_category_filters" size="40" value="1" <?php if ( isset( $options['store']['show_category_filters'] ) && $options['store']['show_category_filters'] ) {
								echo 'checked';} ?> /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Allow sorting results', 'cp-image-store' ); ?></th>
							<td>
								<input type="checkbox" name="cpis_show_ordering" <?php echo ( ( $options['store']['show_ordering'] ) ? 'CHECKED' : '' ); ?> />
							</td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Allow pagination', 'cp-image-store' ); ?></th>
							<td><input type="checkbox" name="cpis_show_pagination" size="40" value="1" <?php if ( isset( $options['store']['show_pagination'] ) && $options['store']['show_pagination'] ) {
								echo 'checked';} ?> /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Items per page', 'cp-image-store' ); ?></th>
							<td><input type="text" name="cpis_items_page" value="<?php echo esc_attr( $options['store']['items_page'] ); ?>" /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Number of columns', 'cp-image-store' ); ?></th>
							<td><input type="text" name="cpis_columns" value="<?php echo esc_attr( $options['store']['columns'] ); ?>" /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Uses friendly URLs on products', 'cp-image-store' ); ?></th>
							<td><input type="checkbox" name="cpis_friendly_url" value="1" <?php if ( get_option( 'cpis_friendly_url', false ) ) {
								echo 'checked';} ?> /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Prevent the products pages be cached', 'cp-image-store' ); ?></th>
							<td><input type="checkbox" name="cpis_prevent_cache" value="1" <?php if ( get_option( 'cpis_prevent_cache', true ) ) {
								echo 'checked';} ?> /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Show buttons for sharing in social networks', 'cp-image-store' ); ?></th>
							<td>
								<input type="checkbox" name="cpis_social_buttons" <?php echo ( ( $options['store']['social_buttons'] ) ? 'CHECKED' : '' ); ?> /> <em><?php esc_html_e( 'The option enables the buttons for sharing the products in social networks', 'cp-image-store' ); ?></em>
							</td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Facebook app id for sharing in Facebook', 'cp-image-store' ); ?></th>
							<td>
								<input type="text" name="cpis_facebook_app_id" value="<?php echo esc_attr( ( ! empty( $options['store']['facebook_app_id'] ) ) ? $options['store']['facebook_app_id'] : '' ); ?>" size="40" /><br />
								<em><?php print wp_kses_post( __( 'Click the link to generate the Facebook App and get its ID: <a target="_blank" href="https://developers.facebook.com/apps">https://developers.facebook.com/apps</a>', 'cp-image-store' ) ); ?></em>
							</td>
						</tr>
					</table>
				</div>
			</div>
			<!-- LINKS CONFIG -->
			<div class="postbox">
				<div class="inside">
					<table class="form-table">
						<tr>
							<th>
								<?php esc_html_e( 'Restrict the access to registered users only', 'cp-image-store' ); ?>
							</th>
							<td>
								<input type="checkbox" name="cpis_download_link_for_registered_only" id="cpis_download_link_for_registered_only" <?php echo( ! empty( $options['store']['download_link_for_registered_only'] ) ? 'CHECKED' : '' ); ?> /> <?php esc_html_e( 'Display the free download links only for registered users', 'cp-image-store' ); ?>
							</td>
						</tr>
					</table>
				</div>
			</div>
			<!-- IMAGES CONFIG -->
			<div class="postbox">
				<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Images config', 'cp-image-store' ); ?></span></h3>
				<div class="inside">
					<table class="form-table">
						<tr valign="top">
							<th><?php esc_html_e( 'Units', 'cp-image-store' ); ?></th>
							<td><input type="text" name="cpis_unit" value=<?php echo esc_attr( $options['image']['unit'] ); ?> /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Set watermark', 'cp-image-store' ); ?></th>
							<td><input type="checkbox" disabled />
								<br /><?php print wp_kses_post( __( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', 'cp-image-store' ) ); ?>
							</td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Watermark text', 'cp-image-store' ); ?></th>
							<td><input type="text" placeholder="Image Store" disabled />
							<br /><?php print wp_kses_post( __( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', 'cp-image-store' ) ); ?>
							</td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Watermark position', 'cp-image-store' ); ?></th>
							<td>
								<input type="radio" CHECKED DISABLED /> <?php esc_html_e( 'at bottom', 'cp-image-store' ); ?><br />
								<input type="radio" DISABLED /> <?php esc_html_e( 'at middle', 'cp-image-store' ); ?>
							</td>
						</tr>

						<tr>
							<td colspan="2">
								<h2><?php esc_html_e( 'Thumbnail', 'cp-image-store' ); ?></h2>
							</td>
						</tr>

						<tr valign="top">
							<th><?php esc_html_e( 'Width', 'cp-image-store' ); ?></th>
							<td><input type="text" name="cpis_thumbnail_width" value="<?php echo esc_attr( $options['image']['thumbnail']['width'] ); ?>" /> px</td>
						</tr>

						<tr valign="top">
							<th><?php esc_html_e( 'Height', 'cp-image-store' ); ?></th>
							<td><input type="text" name="cpis_thumbnail_height" value="<?php echo esc_attr( $options['image']['thumbnail']['height'] ); ?>" /> px</td>
						</tr>

						<tr>
							<td colspan="2">
								<h2><?php esc_html_e( 'Intermediate', 'cp-image-store' ); ?></h2>
							</td>
						</tr>

						<tr valign="top">
							<th><?php esc_html_e( 'Width', 'cp-image-store' ); ?></th>
							<td><input type="text" name="cpis_intermediate_width" value="<?php echo esc_attr( $options['image']['intermediate']['width'] ); ?>" /> px</td>
						</tr>

						<tr valign="top">
							<th><?php esc_html_e( 'Height', 'cp-image-store' ); ?></th>
							<td><input type="text" name="cpis_intermediate_height" value="<?php echo esc_attr( $options['image']['intermediate']['height'] ); ?>" /> px</td>
						</tr>


						<tr>
							<td colspan="2">
								<h2><?php esc_html_e( 'Images license' ); ?></h2>
							</td>
						</tr>

						<tr valign="top">
							<th><?php esc_html_e( 'Images license title', 'cp-image-store' ); ?></th>
							<td><input type="text" name="cpis_license_title" value="<?php echo esc_attr( $options['image']['license']['title'] ); ?>" /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Images license description', 'cp-image-store' ); ?></th>
							<td><textarea name="cpis_license_description" cols="60" rows="5"><?php echo esc_textarea( $options['image']['license']['description'] ); ?></textarea></td>
						</tr>

						<tr>
							<td colspan="2">
								<h2><?php esc_html_e( 'Images effects' ); ?></h2>
							</td>
						</tr>

						<tr valign="top">
							<th><?php esc_html_e( 'Show carousel of related images', 'cp-image-store' ); ?></th>
							<td>
								<input type="checkbox" name="cpis_activate_carousel" <?php echo ( ( $options['display']['carousel']['activate'] ) ? 'CHECKED' : '' ); ?> /><br />
							</td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Set carousel autorun', 'cp-image-store' ); ?></th>
							<td>
								<input type="checkbox" name="cpis_autorun_carousel" <?php echo ( ( $options['display']['carousel']['autorun'] ) ? 'CHECKED' : '' ); ?> /><br />
							</td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Carousel transition time', 'cp-image-store' ); ?></th>
							<td><input type="text" name="cpis_carousel_transition_time" value="<?php echo esc_attr( $options['display']['carousel']['transition_time'] ); ?>" /></td>
						</tr>
						<tr valign="top">
							<th><?php esc_html_e( 'Display preview on mouse over', 'cp-image-store' ); ?></th>
							<td>
								<input type="checkbox" name="cpis_activate_preview" <?php echo ( ( $options['display']['preview'] ) ? 'CHECKED' : '' ); ?> />
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- PAYPAL BOX -->
			<div class="postbox">
				<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Paypal Payment Configuration', 'cp-image-store' ); ?></span></h3>
				<div class="inside">
					<table class="form-table">
						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Enable Paypal Payments?', 'cp-image-store' ); ?></th>
						<td><input type="checkbox" name="cpis_activate_paypal" value="1" <?php if ( $options['paypal']['activate_paypal'] ) {
							echo 'checked';} ?> /></td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Enable Paypal Sandbox?', 'cp-image-store' ); ?></th>
						<td><input type="checkbox" name="cpis_activate_sandbox" value="1" <?php if ( $options['paypal']['activate_sandbox'] ) {
							echo 'checked';} ?> /><br />
						<?php esc_html_e( 'For testing the selling process, use the PayPal sandbox, but don\'t forget uncheck it in the final website', 'cp-image-store' ); ?>
						</td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Paypal email', 'cp-image-store' ); ?></th>
						<td><input type="text" name="cpis_paypal_email" size="40" value="<?php echo esc_attr( $options['paypal']['paypal_email'] ); ?>" /><br />
						<?php esc_html_e( 'If you want to sell the images, must enter the email associated to your PayPal account.', 'cp-image-store' ); ?>
						</td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Currency', 'cp-image-store' ); ?></th>
						<td><input type="text" name="cpis_currency" value="<?php echo esc_attr( $options['paypal']['currency'] ); ?>" /><br>
						<b>USD</b> (United States dollar), <b>EUR</b> (Euro), <b>GBP</b> (Pound sterling) (<a href="https://developer.paypal.com/docs/api/reference/currency-codes/" target="_blank">PayPal Currency Codes</a>)</td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Currency Symbol', 'cp-image-store' ); ?></th>
						<td><input type="text" name="cpis_currency_symbol" value="<?php echo esc_attr( $options['paypal']['currency_symbol'] ); ?>" /></td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Paypal language', 'cp-image-store' ); ?></th>
						<td><input type="text" name="cpis_language" value="<?php echo esc_attr( $options ['paypal']['language'] ); ?>" /><br>
						<b>EN</b> (English), <b>ES</b> (Spain), <b>DE</b> (Germany) (<a href="https://developer.paypal.com/docs/api/reference/locale-codes/" target="_blank">PayPal Localee Codes</a>)</td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Apply taxes (in percentage)', 'cp-image-store' ); ?></th>
						<td><input type="text" name="cpis_tax" value="<?php if ( ! empty( $options ['paypal']['tax'] ) ) {
							echo esc_attr( $options ['paypal']['tax'] );} ?>" /></td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Use shopping cart', 'cp-image-store' ); ?></th>
						<td><input type="checkbox" disabled />
						<br /><?php print wp_kses_post( __( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', 'cp-image-store' ) ); ?>
						</td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Download link valid for', 'cp-image-store' ); ?></th>
						<td><input type="text" name="cpis_download_link" value="<?php echo esc_attr( $options['store']['download_link'] ); ?>" /> <?php esc_html_e( 'day(s)', 'cp-image-store' ); ?></td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Number of downloads allowed by purchase', 'cp-image-store' ); ?></th>
						<td><input type="text" name="cpis_download_limit" value="<?php echo esc_attr( $options['store']['download_limit'] ); ?>" /></td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Use safe downloads', 'cp-image-store' ); ?></th>
						<td><input type="checkbox" name="cpis_safe_download"
						<?php
						$cpis_safe_download = get_option( 'cpis_safe_download' );
						if ( ! empty( $cpis_safe_download ) && $cpis_safe_download ) {
							echo 'CHECKED';
						}
						?> /></td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Pack all purchased files as a single ZIP file', 'cp-image-store' ); ?></th>
						<td><input type="checkbox" disabled >
						<?php
						if ( ! class_exists( 'ZipArchive' ) ) {
							echo '<br /><span class="explain-text">' . esc_html__( "Your server can't create Zipped files dynamically. Please, contact to your hosting provider for enable ZipArchive in the PHP script", 'cp-image-store' ) . '</span>';
						}
						?>
						<br /><?php print wp_kses_post( __( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', 'cp-image-store' ) ); ?>
						</td>
						</tr>
						<tr>
							<td colspan="2">
								<div style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;">
									<p style="font-size:1.3em;">If you detect any issue with the payments or downloads please: <a href="#" onclick="jQuery('.cpis-troubleshoot-area').show();return false;">CLICK HERE [ + ]</a></p>
									<div class="cpis-troubleshoot-area" style="display:none;">
										<p><b>An user has paid for a product but has not received the download link</b></p>
										<p><b>Possible causes:</b></p>
										<p><span style="font-size:1.3em;">*</span> The Instant Payment Notification (IPN) is not enabled in your PayPal account, in whose case the website won't notified about the payments. Please, visit the following link: <a href="https://developer.paypal.com/api/nvp-soap/ipn/IPNSetup/#link-settingupipnnotificationsonpaypal" target="_blank">How to enable the IPN?</a>. PayPal needs the URL to the IPN Script in your website, however, you simply should enter the URL to the home page, because the store will send the correct URL to the IPN Script.</p>
										<p><span style="font-size:1.3em;">*</span> The status of the payment is different to "Completed". If the payment status is different to "Completed" the Store won't generate the download link, or send the notification emails, to protect the sellers against frauds. PayPal will contact to the store even if the payment is "Pending" or has "Failed".</p>
										<p><b>But if the IPN is enabled, how can be detected the cause of issue?</b></p>
										<p>In this case you should check the IPN history (<a href="https://developer.paypal.com/api/nvp-soap/ipn/IPNOperations/#link-viewipnmessagesanddetails" target="_blank">CLICK HERE</a>)  for checking all variables that your PayPal account has sent to your website, and pays special attention to the "payment_status" variable (<a href="https://developer.paypal.com/api/nvp-soap/ipn/IPNOperations/#link-viewipnmessagesanddetails" target="_blank">CLICK HERE</a>)</p>
										<p><b>The IPN is enabled, and the status of the payment in the PayPal account is "Completed", the purchase has been registered in the sales reports of the Store (the menu option in your WordPress: "Image Store/Sales Report") but the buyer has not received the notification email. What is the cause?</b></p>
										<p><span style="font-size:1.3em;">*</span> Enter an email address belonging to your website's domain through the attribute: "Notification "from" email" in the store's settings ( accessible from the menu option: "Image Store/Store Settings"). The email services (like AOL, YAHOO, MSN, etc.) check the email addresses in the "Sender" header of the emails, and if they do not belong to the websites that send the emails, can be classified as spam or even worst, as "Phishing" emails.</p>
										<p><span style="font-size:1.3em;">*</span> The email address in the "From" attribute belongs to the store's domain, but the buyer is not receiving the notification email. In this case you should ask the hosting provider the accesses to the SMTP server (all hosting providers include one), and install any of the plugin for SMTP connection distributed for free through the WordPress directory.</p>
										<p><b>The buyer has received the notification email with the download link, but cannot download the files.</b></p>
										<p><span style="font-size:1.3em;">*</span> The Image Store plugin prevents the direct access to the files for security reasons. From the download page, the store checks the number of downloads, the buyer email, or the expiration time for the download link, so, the plugin works as proxy between the browser, and the product's file, so, the PHP Script should have assigned sufficient memory to load the file. Pay attention, the amount of memory assigned to the PHP Script in the web server can be bigger than the file's size, however, you should to consider that all the concurrent accesses to your website are sharing the same PHP memory, and if two buyers are downloading a same file at the same time, the PHP Script in the server should to load in memory the file twice.</p>
										<p><a href="#" onclick="jQuery('.cpis-troubleshoot-area').hide();return false;">CLOSE SECTION [ - ]</a></p>
									</div>
								</div>
								<div style="border:1px solid #ddd;padding:15px;">
									<input type="checkbox" name="cpis_debug_payment" <?php if ( ! empty( $options['store']['debug_payment'] ) ) {
										print 'CHECKED';} ?> /> <b><?php esc_html_e( 'Debugging Payment Process', 'cp-image-store' ); ?></b><br /><br />
									<i><?php print wp_kses_post( __( "(If the checkbox is ticked the plugin will create two new entries in the error  logs file on your server, with the texts <b>Image Store payment gateway GET parameters</b> and <b>Image Store payment gateway POST parameters</b>.  If after a purchase, none of these entries appear in the error logs file, the payment notification has not reached the plugin's code)", 'cp-image-store' ) ); ?></i>
								</div>
							</td>
						</tr>
					 </table>
				</div>
			</div>

			<!--DISCOUNT BOX -->
			<div class="postbox">
				<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Discount Settings', 'cp-image-store' ); ?></span></h3>
				<div class="inside">
					<div style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;"><?php print wp_kses_post( __( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', 'cp-image-store' ) ); ?></div><br />
					<div><input type="checkbox" disabled /> <?php esc_html_e( 'Display discount promotions in the image store page', 'cp-image-store' ); ?></div>
					<h4><?php esc_html_e( 'Scheduled Discounts', 'cp-image-store' ); ?></h4>
					<table class="form-table cpis_discount_table" style="border:1px dotted #dfdfdf;">
						<tr>
							<td style="font-weight:bold;"><?php esc_html_e( 'Percent of discount', 'cp-image-store' ); ?></td>
							<td style="font-weight:bold;"><?php esc_html_e( 'In Sales over than ... ', 'cp-image-store' );
							echo( $options['paypal']['currency'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
							<td style="font-weight:bold;"><?php esc_html_e( 'Valid from dd/mm/yyyy', 'cp-image-store' ); ?></td>
							<td style="font-weight:bold;"><?php esc_html_e( 'Valid to dd/mm/yyyy', 'cp-image-store' ); ?></td>
							<td style="font-weight:bold;"><?php esc_html_e( 'Promotional text', 'cp-image-store' ); ?></td>
							<td style="font-weight:bold;"><?php esc_html_e( 'Status', 'cp-image-store' ); ?></td>
							<td></td>
						</tr>
					</table>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Percent of discount (*)', 'cp-image-store' ); ?></th>
							<td><input type="text" disabled /> %</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Valid for sales over than (*)', 'cp-image-store' ); ?></th>
							<td><input type="text" disabled /> <?php echo $options['paypal']['currency']; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Valid from (dd/mm/yyyy)', 'cp-image-store' ); ?></th>
							<td><input type="text" disabled /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Valid to (dd/mm/yyyy)', 'cp-image-store' ); ?></th>
							<td><input type="text" disabled /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Promotional text', 'cp-image-store' ); ?></th>
							<td><textarea cols="60" disabled></textarea></td>
						</tr>
						<tr><td colspan="2"><input type="button" class="button" value="<?php esc_attr_e( 'Add/Update Discount', 'cp-image-store' ); ?>" disabled /></td></tr>
					</table>
				</div>
			</div>

			<!--COUPONS BOX -->
			<div class="postbox">
				<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Coupons Settings', 'cp-image-store' ); ?></span></h3>
				<div class="inside">
					<div style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;"><?php print wp_kses_post( __( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', 'cp-image-store' ) ); ?></div>
					<h4><?php esc_html_e( 'Coupons List', 'cp-image-store' ); ?></h4>
					<table class="form-table cpis_coupon_table" style="border:1px dotted #dfdfdf;">
						<tr>
							<td style="font-weight:bold;"><?php esc_html_e( 'Percent of discount', 'cp-image-store' ); ?></td>
							<td style="font-weight:bold;"><?php esc_html_e( 'Coupon', 'cp-image-store' ); ?></td>
							<td style="font-weight:bold;"><?php esc_html_e( 'Valid from dd/mm/yyyy', 'cp-image-store' ); ?></td>
							<td style="font-weight:bold;"><?php esc_html_e( 'Valid to dd/mm/yyyy', 'cp-image-store' ); ?></td>
							<td style="font-weight:bold;"><?php esc_html_e( 'Status', 'cp-image-store' ); ?></td>
							<td></td>
						</tr>
					</table>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Percent of discount (*)', 'cp-image-store' ); ?></th>
							<td><input type="text" disabled /> %</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Coupon (*)', 'cp-image-store' ); ?></th>
							<td><input type="text" disabled /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Valid from (dd/mm/yyyy)', 'cp-image-store' ); ?></th>
							<td><input type="text" disabled /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Valid to (dd/mm/yyyy)', 'cp-image-store' ); ?></th>
							<td><input type="text" disabled /></td>
						</tr>
						<tr><td colspan="2"><input type="button" class="button" value="<?php esc_attr_e( 'Add/Update Coupon', 'cp-image-store' ); ?>" disabled /></td></tr>
					</table>
				</div>
			</div>

			<!-- NOTIFICATIONS BOX -->
			<div class="postbox">
				<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Notification Settings', 'cp-image-store' ); ?></span></h3>
				<div class="inside">
					<table class="form-table">
						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Notification "from" email', 'cp-image-store' ); ?></th>
						<td><input type="text" name="cpis_from" size="40" value="<?php echo esc_attr( $options['notification']['from'] ); ?>" /></td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Send notification to email', 'cp-image-store' ); ?></th>
						<td><input type="text" name="cpis_to" size="40" value="<?php echo esc_attr( $options['notification']['to'] ); ?>" /></td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Email subject confirmation to user', 'cp-image-store' ); ?></th>
						<td><input type="text" name="cpis_subject_payer" size="40" value="<?php echo esc_attr( $options['notification']['subject_payer'] ); ?>" /></td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Email confirmation to user', 'cp-image-store' ); ?></th>
						<td><textarea name="cpis_notification_payer" cols="60" rows="5"><?php echo esc_textarea( $options['notification']['notification_payer'] ); ?></textarea></td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Email subject notification to admin', 'cp-image-store' ); ?></th>
						<td><input type="text" name="cpis_subject_seller" size="40" value="<?php echo esc_attr( $options['notification']['subject_seller'] ); ?>" /></td>
						</tr>

						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Email notification to admin', 'cp-image-store' ); ?></th>
						<td><textarea name="cpis_notification_seller"  cols="60" rows="5"><?php echo esc_textarea( $options['notification']['notification_seller'] ); ?></textarea></td>
						</tr>
					</table>
				</div>
			</div>
			<?php
				do_action( 'cpis_show_settings' );
				wp_nonce_field( plugin_basename( __FILE__ ), 'cpis_settings' );
			?>
			<div class="submit"><input type="submit" class="button-primary" value="<?php esc_attr_e( 'Update Settings', 'cp-image-store' ); ?>" /></div>
			</form>
		<?php
	}
} // End cpis_settings_page

if ( ! function_exists( 'cpis_import_page' ) ) {
	function cpis_import_page() {
		?>
		<form method="post" action="<?php echo isset( $_SERVER['REQUEST_URI'] ) ? esc_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : ''; ?>" id="import_form" target="_blank" >
			<input type="hidden" name="cpis-action" value="import" />
			<!-- NOTIFICATIONS BOX -->
			<div class="postbox">
				<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Importing Area', 'cp-image-store' ); ?></span></h3>
				<div class="inside">
					<table class="form-table">
						<tr valign="top">
						<th scope="row"><?php esc_html_e( 'URL to XML file', 'cp-image-store' ); ?></th>
						<td>
							<input type="text" name="cpis_xml_url" size="40" value="" />
							<input type="submit" value="<?php esc_html_e( 'Import', 'cp-image-store' ); ?>" class="button-primary" />
						</td>
						</tr>
					</table>
					<div>
						<p>This module is experimental and allows to import all products and their data at the same time.</p>

						<p>Simply upload to your server a directory with all images and a XML file to define the products, with their data and attributes. The directory should be accessible and it can be deleted after completing the importing process. After upload the directory with the data, enter in the field above, the <b>absolute URL to the XML file</b>, and press the <b>"Import"</b> button.</p>

						<p>The structure of the XML file is:</p>
<pre>
<b style="color:blue;">&lt;?xml version="1.0" encoding="utf-8"?&gt;</b>
<b style="color:blue;">&lt;products&gt;</b>
	<b style="color:blue;">&lt;product&gt;</b>
		<b style="color:blue;">&lt;title&gt;</b>Title of the first product<b style="color:blue;">&lt;/title&gt;</b>
		<b style="color:blue;">&lt;description&gt;</b>Description of the first product<b style="color:blue;">&lt;/description&gt;</b>
		<b style="color:blue;">&lt;author&gt;</b>Name of the first product author<b style="color:blue;">&lt;/author&gt;</b>
		<b style="color:blue;">&lt;type&gt;</b>Type of product (Clip art, Line drawing, etc.)<b style="color:blue;">&lt;/type&gt;</b>
		<b style="color:blue;">&lt;color&gt;</b>Scheme color (Full color, Black and white, etc.)<b style="color:blue;">&lt;/color&gt;</b>
		<b style="color:blue;">&lt;thumbnail&gt;</b>first_thumbnail.jpg<b style="color:blue;">&lt;/thumbnail&gt;</b>
		<b style="color:blue;">&lt;image width="number" height="number" price="number"&gt;</b>first_image_for_selling.jpg<b style="color:blue;">&lt;/image&gt;</b>
		<b style="color:blue;">&lt;image width="number" height="number" price="number"&gt;</b>second_image_for_selling.jpg<b style="color:blue;">&lt;/image&gt;</b>
		<b style="color:blue;">&lt;image width="number" height="number" price="number"&gt;</b>third_image_for_selling.jpg<b style="color:blue;">&lt;/image&gt;</b>
	<b style="color:blue;">&lt;/product&gt;</b>
	<b style="color:blue;">&lt;product&gt;</b>
		<b style="color:blue;">&lt;title&gt;</b>Title of the second product<b style="color:blue;">&lt;/title&gt;</b>
		<b style="color:blue;">&lt;description&gt;</b>Description of the second product<b style="color:blue;">&lt;/description&gt;</b>
		<b style="color:blue;">&lt;author&gt;</b>Name of the second product author<b style="color:blue;">&lt;/author&gt;</b>
		<b style="color:blue;">&lt;type&gt;</b>Type of product (Clip art, Line drawing, etc.)<b style="color:blue;">&lt;/type&gt;</b>
		<b style="color:blue;">&lt;color&gt;</b>Scheme color (Full color, Black and white, etc.)<b style="color:blue;">&lt;/color&gt;</b>
		<b style="color:blue;">&lt;thumbnail&gt;</b>second_thumbnail.jpg<b style="color:blue;">&lt;/thumbnail&gt;</b>
		<b style="color:blue;">&lt;image width="number" height="number" price="number"&gt;</b>first_image_for_selling.jpg<b style="color:blue;">&lt;/image&gt;</b>
		<b style="color:blue;">&lt;image width="number" height="number" price="number"&gt;</b>second_image_for_selling.jpg<b style="color:blue;">&lt;/image&gt;</b>
		<b style="color:blue;">&lt;image width="number" height="number" price="number"&gt;</b>third_image_for_selling.jpg<b style="color:blue;">&lt;/image&gt;</b>
	<b style="color:blue;">&lt;/product&gt;</b>
<b style="color:blue;">&lt;/products&gt;</b>
</pre>
						<p>
							- Each <b>&lt;product&gt;&lt;/product&gt;</b> node represents a product in the store.<br>
							- The URLs to the thumbnails and images must be <b>relative</b> to the XML file.<br>
							- The nodes: <b>author</b>, <b>type</b>, and <b>color</b> will generate new terms associated to the products.<br>
							- There are multiple image nodes associated to a same product, because it is possible to sell different formats of an image from a same product, the <b>width</b>, <b>height</b> and <b>price</b> are the attributes of the images displayed in the public website, and its corresponding price.<br>
						</p>
					</div>
				</div>
			</div>
			<?php wp_nonce_field( 'session_id_' . session_id(), 'cpis_import' ); ?>
		</form>
		<?php
	}
} // End cpis_import_page

if ( ! function_exists( 'cpis_reports_page' ) ) {
	function cpis_reports_page() {
		global $wpdb;
		if ( isset( $_POST['cpis_purchase_stats'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cpis_purchase_stats'] ) ), plugin_basename( __FILE__ ) ) ) {
			if ( isset( $_POST['delete_purchase_id'] ) && is_numeric( $_POST['delete_purchase_id'] ) ) { // Delete the purchase
				$wpdb->query(
					$wpdb->prepare(
						'DELETE FROM ' . $wpdb->prefix . CPIS_PURCHASE . ' WHERE id=%d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						intval( $_POST['delete_purchase_id'] )
					)
				);
			}

			if ( isset( $_POST['reset_purchase_id'] ) && is_numeric( $_POST['reset_purchase_id'] ) ) { // Delete the purchase
				$wpdb->query(
					$wpdb->prepare(
						'UPDATE ' . $wpdb->prefix . CPIS_PURCHASE . ' SET checking_date = NOW(), downloads = 0 WHERE id=%d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						intval( $_POST['reset_purchase_id'] )
					)
				);
			}

			if ( isset( $_POST['show_purchase_id'] ) && is_numeric( $_POST['show_purchase_id'] ) ) { // Delete the purchase
				$paypal_data = '<div class="cpis-paypal-data"><h3>' . esc_html__( 'PayPal data', 'cp-image-store' ) . '</h3>' . $wpdb->get_var(
					$wpdb->prepare(
						'SELECT paypal_data FROM ' . $wpdb->prefix . CPIS_PURCHASE . ' WHERE id=%d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						intval( $_POST['show_purchase_id'] )
					)
				) . '</div>';
				$paypal_data = preg_replace( '/\n+/', '<br />', $paypal_data );
			}
		}

		$group_by_arr = array(
			'no_group'      => 'Group by',
			'cpis_category' => 'Categories',
			'cpis_author'   => 'Authors',
			'cpis_color'    => 'Colors Schemes',
			'cpis_type'     => 'Type of Image',
		);

		$from_day   = ( isset( $_POST['from_day'] ) ) ? @intval( $_POST['from_day'] ) : gmdate( 'j' );
		$from_month = ( isset( $_POST['from_month'] ) ) ? @intval( $_POST['from_month'] ) : gmdate( 'm' );
		$from_year  = ( isset( $_POST['from_year'] ) ) ? @intval( $_POST['from_year'] ) : gmdate( 'Y' );
		$buyer      = ( ! empty( $_POST['buyer'] ) ) ? sanitize_text_field( wp_unslash( $_POST['buyer'] ) ) : '';
		$buyer      = trim( $buyer );

		$to_day   = ( isset( $_POST['to_day'] ) ) ? @intval( $_POST['to_day'] ) : gmdate( 'j' );
		$to_month = ( isset( $_POST['to_month'] ) ) ? @intval( $_POST['to_month'] ) : gmdate( 'm' );
		$to_year  = ( isset( $_POST['to_year'] ) ) ? @intval( $_POST['to_year'] ) : gmdate( 'Y' );

		$group_by   = ( isset( $_POST['group_by'] ) ) ? sanitize_text_field( wp_unslash( $_POST['group_by'] ) ) : 'no_group';
		if ( ! in_array( $group_by, array_keys( $group_by_arr ) ) ) $group_by = 'no_group';

		$to_display = ( isset( $_POST['to_display'] ) ) ? sanitize_text_field( wp_unslash( $_POST['to_display'] ) ) : 'sales';

		$_select = '';
		$_from   = ' FROM ' . $wpdb->prefix . CPIS_PURCHASE . ' AS purchase, ' . $wpdb->prefix . 'posts AS posts, ' . $wpdb->prefix . CPIS_IMAGE_FILE . ' AS image_file';
		$_where  = $wpdb->prepare(
			" WHERE posts.ID = image_file.id_image
						  AND image_file.id_file = purchase.product_id
						  AND DATEDIFF(purchase.date, '%d-%d-%d')>=0
						  AND DATEDIFF(purchase.date, '%d-%d-%d')<=0 ",
			$from_year,
			$from_month,
			$from_day,
			$to_year,
			$to_month,
			$to_day
		);
		if ( ! empty( $buyer ) ) {
			$_where .= $wpdb->prepare( 'AND purchase.email LIKE %s', '%' . $buyer . '%' );
		}

		$_group        = '';
		$_order        = '';
		$_date_dif     = floor( max( abs( strtotime( $to_year . '-' . $to_month . '-' . $to_day ) - strtotime( $from_year . '-' . $from_month . '-' . $from_day ) ) / ( 60 * 60 * 24 ), 1 ) );
		$_table_header = array( 'Date', 'Product', 'Buyer', 'Amount', 'Currency', 'Download link', '' );

		if ( 'no_group' == $group_by ) {
			if ( 'sales' == $to_display ) {
				$_select .= 'SELECT purchase.*, posts.*';
			} else {
				$_select .= $wpdb->prepare( 'SELECT SUM(purchase.amount)/%d as purchase_average, SUM(purchase.amount) as purchase_total, COUNT(posts.ID) as purchase_count, posts.*', $_date_dif );
				$_group   = ' GROUP BY posts.ID';
				if ( 'amount' == $to_display ) {
					$_table_header = array( 'Product', 'Amount of Sales', 'Total' );
					$_order        = ' ORDER BY purchase_count DESC';
				} else {
					$_table_header = array( 'Product', 'Daily Average', 'Total' );
					$order         = ' ORDER BY purchase_average DESC';
				}
			}
		} else {
			$_select .= $wpdb->prepare( 'SELECT SUM(purchase.amount)/%d as purchase_average, SUM(purchase.amount) as purchase_total, COUNT(posts.ID) as purchase_count, terms.name as term_name, terms.slug as term_slug', $_date_dif );

			$_from  .= ", {$wpdb->prefix}term_taxonomy as taxonomy,
						 {$wpdb->prefix}term_relationships as term_relationships,
						 {$wpdb->prefix}terms as terms";
			$_where .= $wpdb->prepare(
				' AND taxonomy.taxonomy = %s
						 AND taxonomy.term_taxonomy_id=term_relationships.term_taxonomy_id
						 AND term_relationships.object_id=posts.ID
						 AND taxonomy.term_id=terms.term_id',
				$group_by
			);
			$_group  = ' GROUP BY terms.term_id';
			$_order  = ' ORDER BY terms.slug;';

			if ( 'amount' == $to_display ) {
				$_order        = ' ORDER BY purchase_count DESC';
				$_table_header = array( $group_by_arr[ $group_by ], 'Amount of Sales', 'Total' );
			} else {
				$order = ' ORDER BY purchase_average DESC';
				if ( 'sales' == $to_display ) {
					$_table_header = array( $group_by_arr[ $group_by ], 'Total' );
				} else {
					$_table_header = array( $group_by_arr[ $group_by ], 'Daily Average', 'Total' );
				}
			}
		}

		$purchase_list = $wpdb->get_results( $_select . $_from . $_where . $_group . $_order ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		?>
		<form method="post" action="<?php echo isset( $_SERVER['REQUEST_URI'] ) ? esc_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : ''; ?>" id="purchase_form">
		<?php wp_nonce_field( plugin_basename( __FILE__ ), 'cpis_purchase_stats' ); ?>
		<input type="hidden" name="tab" value="reports" />
		<!-- FILTER REPORT -->
		<div class="postbox" style="margin-top:20px;">
			<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Filter the sales reports', 'cp-image-store' ); ?></span></h3>
			<div class="inside">
				<div>
					<h4><?php esc_html_e( 'Filter by date', 'cp-image-store' ); ?></h4>

					<?php
						$months_list = array(
							'01' => __( 'January', 'cp-image-store' ),
							'02' => __( 'February', 'cp-image-store' ),
							'03' => __( 'March', 'cp-image-store' ),
							'04' => __( 'April', 'cp-image-store' ),
							'05' => __( 'May', 'cp-image-store' ),
							'06' => __( 'June', 'cp-image-store' ),
							'07' => __( 'July', 'cp-image-store' ),
							'08' => __( 'August', 'cp-image-store' ),
							'09' => __( 'September', 'cp-image-store' ),
							'10' => __( 'October', 'cp-image-store' ),
							'11' => __( 'November', 'cp-image-store' ),
							'12' => __( 'December', 'cp-image-store' ),
						);
						?>
					<label><?php esc_html_e( 'Buyer: ', 'cp-image-store' ); ?></label><input type="text" name="buyer" id="buyer" value="<?php print esc_attr( $buyer ); ?>" />
					<label><?php esc_html_e( 'From: ', 'cp-image-store' ); ?></label>
					<select name="from_day">
					<?php
					for ( $i = 1; $i <= 31; $i++ ) {
						print '<option value="' . esc_attr( $i ) . '"' . ( ( $from_day == $i ) ? ' SELECTED' : '' ) . '>' . esc_html( $i ) . '</option>';
					}
					?>
					</select>
					<select name="from_month">
					<?php
					foreach ( $months_list as $month => $name ) {
						print '<option value="' . esc_attr( $month ) . '"' . ( ( $from_month == $month ) ? ' SELECTED' : '' ) . '>' . esc_html( $name ) . '</option>';
					}
					?>
					</select>
					<input type="text" name="from_year" value="<?php print esc_attr( $from_year ); ?>" size="5" />

					<label><?php esc_html_e( 'To: ', 'cp-image-store' ); ?></label>
					<select name="to_day">
					<?php
					for ( $i = 1; $i <= 31; $i++ ) {
						print '<option value="' . esc_attr( $i ) . '"' . ( ( $to_day == $i ) ? ' SELECTED' : '' ) . '>' . esc_html( $i ) . '</option>';
					}
					?>
					</select>
					<select name="to_month">
					<?php
					foreach ( $months_list as $month => $name ) {
						print '<option value="' . esc_attr( $month ) . '"' . ( ( $to_month == $month ) ? ' SELECTED' : '' ) . '>' . esc_html( $name ) . '</option>';
					}
					?>
					</select>
					<input type="text" name="to_year" value="<?php print esc_attr( $to_year ); ?>" size="5" />
					<input type="submit" value="<?php esc_attr_e( 'Search', 'cp-image-store' ); ?>" class="button-primary" />
					<input type="button" value="<?php esc_attr_e( 'Export CSV', 'cp-image-store' ); ?>" class="button" onclick="cpis_export_csv( this );" />
				</div>

				<div style="float:left;margin-right:20px;">
					<h4><?php esc_html_e( 'Grouping the sales', 'cp-image-store' ); ?></h4>
					<label><?php esc_html_e( 'By: ', 'cp-image-store' ); ?></label>
					<select name="group_by">
					<?php
					foreach ( $group_by_arr as $key => $value ) {
						print '<option value="' . esc_attr( $key ) . '"' . ( ( isset( $group_by ) && $group_by == $key ) ? ' SELECTED' : '' ) . '>' . esc_html( $value ) . '</option>';
					}
					?>
					</select>
				</div>
				<div style="float:left;margin-right:20px;">
					<h4><?php esc_html_e( 'Display', 'cp-image-store' ); ?></h4>
					<label><input type="radio" name="to_display" <?php echo ( ( ! isset( $to_display ) || 'sales' == $to_display ) ? 'CHECKED' : '' ); ?> value="sales" /> <?php esc_html_e( 'Sales', 'cp-image-store' ); ?></label>
					<label><input type="radio" name="to_display" <?php echo ( ( isset( $to_display ) && 'amount' == $to_display ) ? 'CHECKED' : '' ); ?> value="amount" /> <?php esc_html_e( 'Amount of sales', 'cp-image-store' ); ?></label>
					<label><input type="radio" name="to_display" <?php echo ( ( isset( $to_display ) && 'average' == $to_display ) ? 'CHECKED' : '' ); ?> value="average" /> <?php esc_html_e( 'Daily average', 'cp-image-store' ); ?></label>
				</div>
				<div style="clear:both;"></div>
			</div>
		</div>
		<!-- PURCHASE LIST -->
		<div class="postbox">
			<h3 class='hndle' style="padding:5px;"><span><?php esc_html_e( 'Store sales report', 'cp-image-store' ); ?></span></h3>
			<div class="inside">
				<?php
				if ( ! empty( $paypal_data ) ) {
					print $paypal_data; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				if ( count( $purchase_list ) ) {
					print '
							<div>
								<label style="margin-right: 20px;" ><input type="checkbox" onclick="cpis_load_report(this, \'sales_by_country\', \'' . esc_js( __( 'Sales by country', 'cp-image-store' ) ) . '\', \'residence_country\', \'Pie\', \'residence_country\', \'count\');" /> ' . esc_js( __( 'Sales by country', 'cp-image-store' ) ) . '</label>
								<label style="margin-right: 20px;" ><input type="checkbox" onclick="cpis_load_report(this, \'sales_by_currency\', \'' . esc_js( __( 'Sales by currency', 'cp-image-store' ) ) . '\', \'mc_currency\', \'Bar\', \'mc_currency\', \'sum\');" /> ' . esc_html__( 'Sales by currency', 'cp-image-store' ) . '</label>
								<label><input type="checkbox" onclick="cpis_load_report(this, \'sales_by_product\', \'' . esc_js( __( 'Sales by product', 'cp-image-store' ) ) . '\', \'product_name\', \'Bar\', \'post_title\', \'sum\');" /> ' . esc_html__( 'Sales by product', 'cp-image-store' ) . '</label>
							</div>';
				}
				?>

				<div id="charts_content" >
					<div id="sales_by_country"></div>
					<div id="sales_by_currency"></div>
					<div id="sales_by_product"></div>
				</div>

				<table class="form-table" style="border-bottom:1px solid #CCC;margin-bottom:10px;">
					<THEAD>
						<TR style="border-bottom:1px solid #CCC;">
						<?php
						foreach ( $_table_header as $_header ) {
							print '<TH>' . esc_html( $_header ) . '</TH>';
						}
						?>
						</TR>
					</THEAD>
					<TBODY>
					<?php
					$totals = array( 'UNDEFINED' => 0 );

					$dlurl  = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
					$dlurl .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' ) . 'cpis-action=download&purchase_id=';

					if ( count( $purchase_list ) ) {
						foreach ( $purchase_list as $purchase ) {
							if ( 'no_group' == $group_by ) {

								if ( 'sales' == $to_display ) {
									if ( preg_match( '/mc_currency=([^\s]*)/', $purchase->paypal_data, $matches ) ) {
										$currency = strtoupper( $matches[1] );
										if ( ! isset( $totals[ $currency ] ) ) {
											$totals[ $currency ] = $purchase->amount;
										} else {
											$totals[ $currency ] += $purchase->amount;
										}
									} else {
										$currency             = '';
										$totals['UNDEFINED'] += $purchase->amount;
									}

									echo '
										<TR>
											<TD>' . esc_html( $purchase->date ) . '</TD>
											<TD><a href="' . esc_url( get_permalink( $purchase->ID ) ) . '" target="_blank">' . wp_kses_post( $purchase->post_title ) . '</a></TD>
											<TD>' . esc_html( $purchase->email ) . '</TD>
											<TD>' . esc_html( $purchase->amount ) . '</TD>
											<TD>' . esc_html( $currency ) . '</TD>
											<TD><a href="' . esc_url( $dlurl . $purchase->purchase_id ) . '" target="_blank">Download Link</a></TD>
											<TD style="white-space:nowrap;">
												<input type="button" class="button-primary" onclick="cpis_delete_purchase(' . esc_js( $purchase->id ) . ');" value="Delete">
												<input type="button" class="button-primary" onclick="cpis_reset_purchase(' . esc_js( $purchase->id ) . ');" value="Reset Time and Downloads">
												<input type="button" class="button-primary" onclick="cpis_show_purchase(' . esc_js( $purchase->id ) . ');" value="PayPal Info">
											</TD>
										</TR>
									';
								} elseif ( 'amount' == $to_display ) {
									echo '
										<TR>
											<TD><a href="' . esc_url( get_permalink( $purchase->ID ) ) . '" target="_blank">' . wp_kses_post( $purchase->post_title ) . '</a></TD>
											<TD>' . esc_html( round( $purchase->purchase_count * 100 ) / 100 ) . '</TD>
											<TD>' . esc_html( round( $purchase->purchase_total * 100 ) / 100 ) . '</TD>
										</TR>
									';
								} else {
									echo '
										<TR>
											<TD><a href="' . esc_url( get_permalink( $purchase->ID ) ) . '" target="_blank">' . wp_kses_post( $purchase->post_title ) . '</a></TD>
											<TD>' . esc_html( $purchase->purchase_average ) . '</TD>
											<TD>' . esc_html( round( $purchase->purchase_total * 100 ) / 100 ) . '</TD>
										</TR>
									';
								}
							} else {

								if ( 'sales' == $to_display ) {
									echo '
											<TR>
												<TD><a href="' . esc_url( get_term_link( $purchase->term_slug, $group_by ) ) . '" target="_blank">' . wp_kses_post( $purchase->term_name ) . '</a></TD>
												<TD>' . esc_html( round( $purchase->purchase_total * 100 ) / 100 ) . '</TD>
											</TR>
										';
								} elseif ( 'amount' == $to_display ) {
									echo '
											<TR>
												<TD><a href="' . esc_url( get_term_link( $purchase->term_slug, $group_by ) ) . '" target="_blank">' . wp_kses_post( $purchase->term_name ) . '</a></TD>
												<TD>' . esc_html( round( $purchase->purchase_count * 100 ) / 100 ) . '</TD>
												<TD>' . esc_html( round( $purchase->purchase_total * 100 ) / 100 ) . '</TD>
											</TR>
										';
								} else {
									echo '
											<TR>
												<TD><a href="' . esc_url( get_term_link( $purchase->term_slug, $group_by ) ) . '" target="_blank">' . wp_kses_post( $purchase->term_name ) . '</a></TD>
												<TD>' . esc_html( $purchase->purchase_average ) . '</TD>
												<TD>' . esc_html( round( $purchase->purchase_total * 100 ) / 100 ) . '</TD>
											</TR>
										';
								}
							}
						}
					} else {
						echo '
                            <TR>
                                <TD COLSPAN="6">
                                    ' . esc_html__( 'There are not sales registered with those filter options', 'cp-image-store' ) . '
                                </TD>
                            </TR>
                        ';
					}
					?>
					</TBODY>
				</table>

				<?php
				if ( count( $totals ) > 1 || $totals['UNDEFINED'] ) {
					?>
						<table style="border: 1px solid #CCC;">
							<TR><TD COLSPAN="2" style="border-bottom:1px solid #CCC;">TOTALS</TD></TR>
							<TR><TD style="border-bottom:1px solid #CCC;">CURRENCY</TD><TD style="border-bottom:1px solid #CCC;">AMOUNT</TD></TR>
					<?php
					foreach ( $totals as $currency => $amount ) {
						if ( $amount ) {
							print '<TR><TD><b>' . esc_html( $currency ) . '</b></TD><TD>' . esc_html( $amount ) . '</TD></TR>';
						}
					}
					?>
						</table>
					<?php
				}
				?>
			</div>
		</div>
		</form>
		<?php
	}
} // End cpis_reports_page

 add_action( 'admin_enqueue_scripts', 'cpis_admin_enqueue_scripts' );
if ( ! function_exists( 'cpis_admin_enqueue_scripts' ) ) {
	function cpis_admin_enqueue_scripts( $hook ) {
		global $post;

		if (
			'image-store_page_image-store-menu-settings' == $hook || 'settings_page_image-store-menu-settings-page' == $hook
		) {
			wp_enqueue_style( 'jquery-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.3/themes/smoothness/jquery-ui.css', array(), CPIS_VERSION );
			wp_enqueue_style( 'cpis-admin-style', CPIS_PLUGIN_URL . '/css/admin.css', array(), CPIS_VERSION );
			wp_enqueue_script( 'json2' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'cpis-admin-script', CPIS_PLUGIN_URL . '/js/admin.js', array( 'jquery', 'json2', 'jquery-ui-core', 'jquery-ui-datepicker' ), CPIS_VERSION, true );
		} elseif (
			isset( $post ) && 'cpis_image' == $post->post_type
		) {
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'jquery-ui-dialog' );
			wp_enqueue_script( 'cpis-admin-script', CPIS_PLUGIN_URL . '/js/admin.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'media-upload', 'json2', 'jquery-ui-datepicker' ), CPIS_VERSION, true );

			// Scripts and styles required for metaboxs
			wp_enqueue_style( 'jquery-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.3/themes/smoothness/jquery-ui.css', array(), CPIS_VERSION );
			wp_enqueue_style( 'cpis-admin-style', CPIS_PLUGIN_URL . '/css/admin.css', array(), CPIS_VERSION );
			wp_localize_script(
				'cpis-admin-script',
				'image_store',
				array(
					'post_id' => $post->ID,
					'hurl'    => CPIS_ADMIN_URL,
				)
			);
		} elseif (
			'image-store_page_image-store-menu-reports' == $hook
		) {
			wp_enqueue_style( 'cpis-admin-style', CPIS_PLUGIN_URL . '/css/admin.css', array(), CPIS_VERSION );
			wp_enqueue_script( 'cpis-admin-script-chart', CPIS_PLUGIN_URL . '/js/Chart.min.js', array( 'jquery' ), CPIS_VERSION, true );
			wp_enqueue_script( 'cpis-admin-script', CPIS_PLUGIN_URL . '/js/admin.js', array( 'jquery' ), CPIS_VERSION, true );
			wp_localize_script( 'cpis-admin-script', 'cpis_global', array( 'aurl' => CPIS_ADMIN_URL ) );
		} elseif ( isset( $post ) ) {

			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-dialog' );
			wp_enqueue_script( 'cpis-admin-script', CPIS_PLUGIN_URL . '/js/admin.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog' ), CPIS_VERSION, true );

			$tags  = '<div title="' . esc_attr__( 'Insert a Product', 'cp-image-store' ) . '"><div style="padding:20px;">';
			$tags .= '<div>' . esc_html__( 'Enter the Image ID:', 'cp-image-store' ) . '<br /><input id="product_id" name="product_id" style="width:100%" /></div>';
			$tags .= '<div>' . esc_html__( 'Select the Layout:', 'cp-image-store' ) . '<br /><select id="layout" name="layout" style="width:100%"><option value="single">Single</option><option value="multiple">Multiple</option></select><br /><em>' . esc_html__( 'If the product is inserted in a page with other products, it is recommended the use of Multiple layout.', 'cp-image-store' ) . '</em></div>';

			$tags .= '</div></div>';

			wp_localize_script( 'cpis-admin-script', 'image_store', array( 'tags' => $tags ) );
		}
	}
} // End cpis_admin_enqueue_scripts

 add_action( 'wp_enqueue_scripts', 'cpis_enqueue_scripts' );
if ( ! function_exists( 'cpis_enqueue_scripts' ) ) {
	function cpis_enqueue_scripts() {
		if (
			! is_admin()
		) {
			global $cpis_layout;
			$options = get_option( 'cpis_options' );

			wp_enqueue_style( 'jquery-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.3/themes/smoothness/jquery-ui.css', array(), CPIS_VERSION );
			wp_enqueue_style( 'cpis-style', CPIS_PLUGIN_URL . '/css/public.css', array(), CPIS_VERSION );

			wp_enqueue_script( 'json2' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-position' );
			wp_enqueue_script( 'cpis-carousel', CPIS_PLUGIN_URL . '/js/jquery.carouFredSel-6.2.1-packed.js', array(), CPIS_VERSION );
			wp_enqueue_script( 'cpis-script', CPIS_PLUGIN_URL . '/js/public.js', array( 'jquery', 'json2', 'jquery-ui-core', 'cpis-carousel', 'jquery-ui-position' ), CPIS_VERSION );

			// Load resources of layout
			if ( ! empty( $cpis_layout ) ) {
				if ( ! empty( $cpis_layout['style_file'] ) ) {
					wp_enqueue_style( 'cpis-css-layout', $cpis_layout['style_file'], array( 'cpis-style' ), CPIS_VERSION );
				}
				if ( ! empty( $cpis_layout['script_file'] ) ) {
					wp_enqueue_script( 'cpis-js-layout', $cpis_layout['script_file'], array( 'cpis-script' ), CPIS_VERSION );
				}
			}

			$cpis_sc_url  = _cpis_create_pages( 'cpis-shopping-cart', 'Shopping Cart' );
			$cpis_sc_url .= ( ( strpos( $cpis_sc_url, '?' ) === false ) ? '?' : '&' ) . 'cpis-action=viewcart';

			$arr = array(
				'scurl'             => cpis_complete_url( $cpis_sc_url ),
				'hurl'              => CPIS_H_URL,
				'thumbnail_w'       => $options['image']['thumbnail']['width'],
				'thumbnail_h'       => $options['image']['thumbnail']['height'],
				'file_required_str' => __( 'It is required select at least a file', 'cp-image-store' ),
			);
			if ( $options['display']['carousel']['activate'] ) {
				$arr['carousel_autorun']         = ( $options['display']['carousel']['autorun'] ) ? 1 : 0;
				$arr['carousel_transition_time'] = $options['display']['carousel']['transition_time'];
			}

			wp_localize_script( 'cpis-script', 'image_store', $arr );
		}
	}
} // End cpis_enqueue_scripts

if ( ! function_exists( 'cpis_upload_dir' ) ) {
	function cpis_upload_dir( $path ) {
		global $post;

		if ( ! file_exists( CPIS_UPLOAD_DIR ) ) {
			@mkdir( CPIS_UPLOAD_DIR, 0755 );
		}
		if ( ! file_exists( CPIS_UPLOAD_DIR . '/files' ) ) {
			@mkdir( CPIS_UPLOAD_DIR . '/files', 0755 );
		}
		if ( ! file_exists( CPIS_UPLOAD_DIR . '/previews' ) ) {
			@mkdir( CPIS_UPLOAD_DIR . '/previews', 0755 );
		}

		if (
			( isset( $post ) && 'cpis_image' == $post->post_type ) ||
			(
				isset( $_REQUEST['cpis-action'] ) && 'import' == sanitize_text_field( wp_unslash( $_REQUEST['cpis-action'] ) ) &&
				isset( $_POST['cpis_import'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cpis_import'] ) ), 'session_id_' . session_id() ) &&
				current_user_can( 'manage_options' )
			)
		) {
			$path['path']    = CPIS_UPLOAD_DIR . '/files' . $path['subdir'];
			$path['url']     = CPIS_UPLOAD_URL . '/files' . $path['subdir'];
			$path['basedir'] = CPIS_UPLOAD_DIR . '/files';
			$path['baseurl'] = CPIS_UPLOAD_URL . '/files';
			return $path;
		}
		return $path;
	}
}// End cpis_upload_dir

if ( ! function_exists( 'cpis_removemediabuttons' ) ) {
	function cpis_removemediabuttons() {
		global $post;
		if ( isset( $post ) && 'cpis_image' == $post->post_type ) {
			remove_action( 'media_buttons', 'media_buttons' );
		}
	}
} // End cpis_removemediabuttons

if ( ! function_exists( 'cpis_the_excerpt' ) ) {
	function cpis_the_excerpt( $the_excerpt ) {
		global $post;
		if (
			/* is_search() && */
			isset( $post ) &&
			'cpis_image' == $post->post_type
		) {
			return cpis_display_content( $post->ID, 'multiple', 'return' );
		}

		return $the_excerpt;
	}
} // End cpis_the_excerpt

if ( ! function_exists( 'cpis_the_content' ) ) {
	function cpis_the_content( $the_content ) {
		global $post;

		if (
			/* in_the_loop() &&  */
			$post &&
			( 'cpis_image' == $post->post_type )
		) {
			return cpis_display_content( $post->ID, ( ( is_singular() ) ? 'single' : 'multiple' ), 'return' );
		} else {
			if ( isset( $_REQUEST ) && isset( $_REQUEST['cpis-action'] ) ) {
				switch ( strtolower( sanitize_text_field( wp_unslash( $_REQUEST['cpis-action'] ) ) ) ) {
					case 'download':
						global $cpis_errors;

						include CPIS_PLUGIN_DIR . '/includes/download.php';
						if ( empty( $cpis_errors ) ) {
							$the_content .= '<div>' . $download_links_str . '</div>';
						} else {
							$error = ( ! empty( $_REQUEST['error_mssg'] ) ) ? wp_kses_post( wp_unslash( $_REQUEST['error_mssg'] ) ) : '';

							if ( ( ! get_option( 'cpis_safe_download', CPIS_SAFE_DOWNLOAD ) && ! empty( $cpis_errors ) ) || ! empty( $GLOBALS[ CPIS_SESSION_NAME ]['cpis_user_email'] ) ) {
								$error .= '<li>' . implode( '</li><li>', $cpis_errors ) . '</li>';
							}

							$the_content .= ( ! empty( $error ) ) ? '<div class="cpis-error-mssg"><ul>' . $error . '</ul></div>' : '';

							if ( get_option( 'cpis_safe_download', CPIS_SAFE_DOWNLOAD ) ) {
								$dlurl        = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
								$dlurl       .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' ) . 'cpis-action=download' . ( ( isset( $_REQUEST['purchase_id'] ) ) ? '&purchase_id=' . sanitize_key( $_REQUEST['purchase_id'] ) : '' );
								$the_content .= '
									<form action="' . $dlurl . '" method="POST" >
										<div style="text-align:center;">
											<div>
												' . esc_html__( 'Type the email address used to purchase our products', 'cp-image-store' ) . '
											</div>
											<div>
												<input type="text" name="cpis_user_email" /> <input type="submit" value="Get Products" />
											</div>
										</div>
									</form>
								';
							}
						}
						break;
				}
			}
		}
		return $the_content;
	}
} // End cpis_the_content

if ( ! function_exists( 'cpis_replace_product_shortcode' ) ) {
	function cpis_replace_product_shortcode( $atts ) {
		extract( // phpcs:ignore WordPress.PHP.DontExtract
			shortcode_atts(
				array(
					'id'     => '',
					'layout' => 'single',
				),
				$atts
			)
		);
		$id = trim( $id );
		if ( ! empty( $id ) ) {
			$p = get_post( $id );
			if ( ! empty( $p ) && 'cpis_image' == $p->post_type ) {
				return cpis_display_content( $id, $layout, 'return' );
			}
		}
		return '';
	}
} // End cpis_replace_product_shortcode

if ( ! function_exists( 'cpis_replace_shortcode' ) ) {
	// Private functions to create the query for products selection
	function _cpis_filter_by_taxonomy( $taxonomy, $taxonomy_value, $hierarchical = false ) {
		global $wpdb;
		if ( $hierarchical ) {

			$args = array(
				'hide_empty'   => 1,
				'hierarchical' => 1,
			);

			if ( ! is_numeric( $taxonomy_value ) ) {
				$term = get_term_by( 'slug', $taxonomy_value, $taxonomy );
				if ( false == $term ) {
					return '(1=1)';
				}
				$args['child_of'] = $term->term_id;
			} else {
				$args['child_of'] = $taxonomy_value;
			}

			$terms = get_terms( $taxonomy, $args );
		}

		$_where = $wpdb->prepare( '(taxonomy.taxonomy=%s AND ', $taxonomy );

		if ( $hierarchical && $terms ) {
			$_where .= '(' . ( ( is_numeric( $taxonomy_value ) ) ? "terms.term_id=$taxonomy_value" : $wpdb->prepare( 'terms.slug=%s', $taxonomy_value ) );

			foreach ( $terms as $term ) {
				$_where .= ' OR terms.term_id=' . @intval( $term->term_id );
			}
			$_where .= ')';
		} else {

			if ( is_numeric( $taxonomy_value ) ) {
				$_where .= "terms.term_id=$taxonomy_value";
			} else {
				$_where .= $wpdb->prepare( 'terms.slug=%s', $taxonomy_value );
			}
		}

		$_where .= ')';
		return $_where;
	}

	function _cpis_create_select_filter( $name, $option_none, $taxonomy, $hierarchical = 0 ) {
		$page_id     = 'cpis_page_' . get_the_ID();
		$option_none = __( $option_none, 'cp-image-store' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText

		if (
			isset( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ][ $taxonomy ] ) &&
			! is_null( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ][ $taxonomy ] ) &&
			! is_numeric( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ][ $taxonomy ] ) ) {
			$obj = get_term_by( 'slug', $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ][ $taxonomy ], $taxonomy );
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ][ $taxonomy ] = $obj->term_id;
		}

		$select = wp_dropdown_categories( 'name=' . $name . '&show_option_none=' . $option_none . '&orderby=name&echo=0&taxonomy=' . $taxonomy . '&hide_if_empty=1&hierarchical=' . $hierarchical . ( ( isset( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ][ $taxonomy ] ) ) ? '&selected=' . $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ][ $taxonomy ] : '' ) );
		$select = preg_replace( '#<select([^>]*)>#', "<select$1 onchange='return this.form.submit()'>", $select );
		return $select;
	}

	function _cpis_create_search_filter( $str ) {
		global $wpdb;
		$filter = '';
		$str    = trim( preg_replace( '/\s+/', ' ', $str ) );
		$terms  = explode( ' ', $str );
		if ( count( $terms ) ) {
			foreach ( $terms as $term ) {
				$term    = '%' . $term . '%';
				$filter .= $wpdb->prepare( '( post_title LIKE %s OR ', $term );
				$filter .= $wpdb->prepare( 'post_content LIKE %s OR ', $term );
				$filter .= $wpdb->prepare( 'post_excerpt LIKE %s ) AND ', $term );
			}
		}

		return $filter;
	}

	function cpis_replace_shortcode( $atts, $content, $tag ) {
		global $wpdb;

		$page_id = 'cpis_page_' . get_the_ID();
		if ( ! isset( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ] = array();
		}

		$options = get_option( 'cpis_options' );

		// Generated image store
		$top_ten_carousel = '';
		$page_links       = '';
		$header           = '';
		$left             = '';
		$right            = '';

		// Set session variable for pagination
		if ( ! isset( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_page'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_page'] = 0;
		}
		if ( isset( $_REQUEST ) && isset( $_REQUEST['cpis_page'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_page'] = @intval( $_REQUEST['cpis_page'] );
		}

		// Create session variables from attributes
		if ( ! isset( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_search_terms'] ) && ! empty( $atts['search'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_search_terms'] = $atts['search'];
		}
		if ( ! isset( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_type'] ) && ! empty( $atts['type'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_type'] = $atts['type'];
		}
		if ( ! isset( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_category'] ) && ! empty( $atts['category'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_category'] = $atts['category'];
		}
		if ( ! isset( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_author'] ) && ! empty( $atts['author'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_author'] = $atts['author'];
		}
		if ( ! isset( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_color'] ) && ! empty( $atts['color'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_color'] = $atts['color'];
		}
		if ( ! isset( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_ordering'] ) ) {
			if ( ! empty( $atts['orderby'] ) && in_array( $atts['orderby'], array( 'post_title', 'purchases', 'post_date' ) ) ) {
				$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_ordering'] = $atts['orderby'];
			} else {
				$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_ordering'] = 'post_title';
			}
		}

		// Extract search terms
		if ( isset( $_REQUEST['search_terms'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_search_terms'] = sanitize_text_field( wp_unslash( $_REQUEST['search_terms'] ) );
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_page']         = 0;
			$filter = _cpis_create_search_filter( sanitize_text_field( wp_unslash( $_REQUEST['search_terms'] ) ) );
		} else {
			$filter = '';
		}

		// Extract product filters

		if ( isset( $_REQUEST['filter_by_type'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_type'] = sanitize_text_field( wp_unslash( $_REQUEST['filter_by_type'] ) );
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_page'] = 0;
		}

		if ( isset( $_REQUEST['filter_by_category'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_category'] = sanitize_text_field( wp_unslash( $_REQUEST['filter_by_category'] ) );
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_page']     = 0;
		}

		if ( isset( $_REQUEST['filter_by_author'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_author'] = sanitize_text_field( wp_unslash( $_REQUEST['filter_by_author'] ) );
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_page']   = 0;
		}

		if ( isset( $_REQUEST['filter_by_color'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_color'] = sanitize_text_field( wp_unslash( $_REQUEST['filter_by_color'] ) );
			$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_page']  = 0;
		}

		if ( isset( $_REQUEST['ordering_by'] ) ) {
			$cpis_ordering = sanitize_text_field( wp_unslash( $_REQUEST['ordering_by'] ) );
			if ( in_array( $cpis_ordering, array( 'post_title', 'purchases', 'post_date' ) ) ) {
				$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_ordering'] = $cpis_ordering;
				$GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_page']     = 0;
			}
		}

		// Query clauses
		$_select   = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT posts.ID';
		$_from     = 'FROM ' . $wpdb->prefix . 'posts as posts,' . $wpdb->prefix . CPIS_IMAGE . ' as posts_data';
		$_where    = "WHERE  $filter posts.ID = posts_data.id AND posts.post_status='publish' AND posts.post_type='cpis_image' ";
		$_order_by = 'ORDER BY ' . ( ( 'purchases' != $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_ordering'] ) ? 'posts' : 'posts_data' ) . '.' . $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_ordering'] . ' ' . ( ( 'post_title' == $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_ordering'] ) ? 'ASC' : 'DESC' );
		$_limit    = '';

		if ( ( ! empty( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_type'] ) && -1 != $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_type'] ) ||
			( ! empty( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_color'] ) && -1 != $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_color'] ) ||
			( ! empty( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_author'] ) && -1 != $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_author'] ) ||
			( ! empty( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_category'] ) && -1 != $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_category'] )
		) {
			$_select_sub = 'SELECT DISTINCT posts.ID';

			// Load the taxonomy tables
			$_from_sub = "$_from, " . $wpdb->prefix . 'term_taxonomy as taxonomy, ' . $wpdb->prefix . 'term_relationships as term_relationships, ' . $wpdb->prefix . 'terms as terms';

			$_where_sub = "$_where AND taxonomy.term_taxonomy_id=term_relationships.term_taxonomy_id AND term_relationships.object_id=posts.ID AND taxonomy.term_id=terms.term_id ";

			// Filter by type
			if ( ! empty( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_type'] ) && -1 != $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_type'] ) {
				$_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND " . _cpis_filter_by_taxonomy( 'cpis_type', $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_type'] ) . ')';
			}

			if ( ! empty( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_author'] ) && -1 != $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_author'] ) {
				$_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND " . _cpis_filter_by_taxonomy( 'cpis_author', $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_author'] ) . ')';
			}

			if ( ! empty( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_color'] ) && -1 != $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_color'] ) {
				$_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND " . _cpis_filter_by_taxonomy( 'cpis_color', $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_color'] ) . ')';
			}

			if ( ! empty( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_category'] ) && -1 != $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_category'] ) {
					$_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND " . _cpis_filter_by_taxonomy( 'cpis_category', $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_category'], true ) . ')';
			}

			// End taxonomies
		}

		$query = $_select . ' ' . $_from . ' ' . $_where . ' ' . $_order_by . ' ' . $_limit;

		if ( $options['store']['show_pagination'] && is_numeric( $options['store']['items_page'] ) && $options['store']['items_page'] > 1 ) {
			$page = $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_page'];

			$_limit = $wpdb->prepare(
				'LIMIT %d, %d',
				$page * $options['store']['items_page'],
				$options['store']['items_page']
			);

			$query  .= ' ' . $_limit;
			$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			$total       = $wpdb->get_var( 'SELECT FOUND_ROWS();' );
			$total_pages = ceil( $total / $options['store']['items_page'] );

			// Make page links
			$page_links .= "<DIV class='cpis-image-store-pagination'>";
			$page_href   = '?' . ( ( ! empty( $_SERVER['QUERY_STRING'] ) ) ? preg_replace( '/(&)?cpis_page=\d+/', '', sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) ) . '&' : '' );

			for ( $i = 0, $h = $total_pages; $i < $h; $i++ ) {
				if ( $page == $i ) {
					$page_links .= "<span class='page-selected'>" . ( $i + 1 ) . '</span>';
				} else {
					$page_links .= "<a class='page-link' href='" . $page_href . 'cpis_page=' . $i . "'>" . ( $i + 1 ) . '</a>';
				}
			}
			$page_links .= '</DIV>';
		} else {
			$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Create carousel
		if (
			( isset( $atts['carousel'] ) && $atts['carousel'] * 1 ) ||
			( ! isset( $atts['carousel'] ) && $options['display']['carousel']['activate'] )
		) {
			$thumb_width  = $options['image']['thumbnail']['width'];
			$thumb_height = $options['image']['thumbnail']['height'];

			$carousel_order_by = 'ORDER BY posts_data.purchases DESC';
			$carousel_limit    = 'LIMIT 0, 10';
			$carousel_query    = $_select . ' ' . $_from . ' ' . $_where . ' ' . $carousel_order_by . ' ' . $carousel_limit;
			$carousel_results  = $wpdb->get_results( $carousel_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( count( $carousel_results ) ) {
				foreach ( $carousel_results as $result ) {
					$top_ten_carousel .= cpis_display_content( $result->ID, 'carousel', 'return' );
				}

				$top_ten_carousel = "
                <div class='cpis-column-title'>" . esc_html__( 'Top-Ten of filtered images', 'cp-image-store' ) . "</div>
                <div id='cpis-image-store-carousel'><div class='cpis-carousel-container' ><ul>" . $top_ten_carousel . '</ul></div></div>
                ';
			}
		}

		// Create filters and sorting fields
		$_show_search_box       = ( isset( $atts['show_search_box'] ) ) ? $atts['show_search_box'] * 1 : $options['store']['show_search_box'];
		$_show_type_filters     = ( isset( $atts['show_type_filters'] ) ) ? $atts['show_type_filters'] * 1 : $options['store']['show_type_filters'];
		$_show_color_filters    = ( isset( $atts['show_color_filters'] ) ) ? $atts['show_color_filters'] * 1 : $options['store']['show_color_filters'];
		$_show_author_filters   = ( isset( $atts['show_author_filters'] ) ) ? $atts['show_author_filters'] * 1 : $options['store']['show_author_filters'];
		$_show_category_filters = ( isset( $atts['show_category_filters'] ) ) ? $atts['show_category_filters'] * 1 : $options['store']['show_category_filters'];

		// Create filter section
		if ( $_show_search_box ||
			$_show_type_filters ||
			$_show_color_filters ||
			$_show_author_filters ||
			$_show_category_filters ||
			! empty( $options['image']['license']['description'] )
		) {
			$left .= "
                    <div class='cpis-image-store-left'>
                        <form method='post' data-ajax='false'>
                ";

			if ( $_show_search_box ) {
				$left .= "<div class='cpis-column-title'>" . esc_html__( 'Search by', 'cp-image-store' ) . '</div>';
				$left .= "
                <div class='cpis-filter'>
                    <input type='search' name='search_terms' placeholder='" . esc_attr__( 'Search...', 'cp-image-store' ) . "' value='" . esc_attr( ( isset( $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_search_terms'] ) ) ? $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_search_terms'] : '' ) . "' style='width:100%;' />
                </div>
                ";
			}

			if (
				$_show_type_filters ||
				$_show_color_filters ||
				$_show_author_filters ||
				$_show_category_filters
			) {
				$left .= "<div class='cpis-column-title'>" . esc_html__( 'Filter by', 'cp-image-store' ) . '</div>';
			}

			if ( $_show_type_filters ) {
				$str = _cpis_create_select_filter( 'filter_by_type', 'All images', 'cpis_type' );
				if ( ! empty( $str ) ) {
					$left .= "<div class='cpis-filter'><label>" . esc_html__( ' type: ', 'cp-image-store' ) . "</label>$str</div>";
				}
			}

			if ( $_show_color_filters ) {
				$str = _cpis_create_select_filter( 'filter_by_color', 'All color schemes', 'cpis_color' );
				if ( ! empty( $str ) ) {
					$left .= "<div class='cpis-filter'><label>" . esc_html__( ' color scheme: ', 'cp-image-store' ) . "</label>$str</div>";
				}
			}

			if ( $_show_author_filters ) {
				$str = _cpis_create_select_filter( 'filter_by_author', 'All authors', 'cpis_author' );
				if ( ! empty( $str ) ) {
					$left .= "<div class='cpis-filter'><label>" . esc_html__( ' authors: ', 'cp-image-store' ) . "</label>$str</div>";
				}
			}

			if ( $_show_category_filters ) {
				$str = _cpis_create_select_filter( 'filter_by_category', 'All categories', 'cpis_category', 1 );
				if ( ! empty( $str ) ) {
					$left .= "<div class='cpis-filter'><label>" . esc_html__( ' categories: ', 'cp-image-store' ) . "</label>$str</div>";
				}
			}

			$left .= '
                    </form>
                ';

			if ( ! empty( $options['image']['license']['description'] ) ) {
				$license_title = "<div class='cpis-license-title cpis-link'>" . ( ( ! empty( $options['image']['license']['title'] ) ) ? esc_html__( $options['image']['license']['title'], 'cp-image-store' ) : esc_html__( 'License', 'cp-image-store' ) ) . '</div>'; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText

				$left .= "
                        $license_title
                        </div>
                    ";

				$left .= "<div class='cpis-license-container'>
                                <div class='cpis-license-close'>[x]</div>
                                <div style='clear:both;'></div>
                                $license_title
                                <div class='cpis-license-description'>" . $options['image']['license']['description'] . '</div>
                         </div>';
			} else {
				$left .= '
                    </div>
                ';
			}
		}

		// Create header
		$_show_ordering = ( isset( $atts['show_ordering'] ) ) ? $atts['show_ordering'] * 1 : $options['store']['show_ordering'];

		if ( $_show_ordering ) {
			$header .= "<div class='cpis-image-store-header'>";

			if ( $_show_ordering ) {
				// Create sorting
				$header .= "
                            <div class='cpis-image-store-ordering'>
                                <form method='POST' data-ajax='false'>
                        " .
									__( 'Order by: ', 'cp-image-store' ) .
						"
                                    <select id='ordering_by' name='ordering_by' onchange='this.form.submit();'>
                                        <option value='post_title' " . ( ( 'post_title' == $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_ordering'] ) ? 'SELECTED' : '' ) . '>' . esc_html__( 'Title', 'cp-image-store' ) . "</option>
                                        <option value='purchases' " . ( ( 'purchases' == $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_ordering'] ) ? 'SELECTED' : '' ) . '>' . esc_html__( 'Popularity', 'cp-image-store' ) . "</option>
                                        <option value='post_date' " . ( ( 'post_date' == $GLOBALS[ CPIS_SESSION_NAME ][ $page_id ]['cpis_ordering'] ) ? 'SELECTED' : '' ) . '>' . esc_html__( 'Date', 'cp-image-store' ) . '</option>
                                    </select>
                                </form>
                            </div>
                        ';
			}

			$header .= "<div style='clear:both;'></div></div>";

		}

		// Create items section
		$right .= "
            <div class='cpis-image-store-right'>
        " . $header . $top_ten_carousel;

		$width = ( 100 - 2 * ( min( $options['store']['columns'], max( count( $results ), 1 ) ) - 1 ) ) / min( $options['store']['columns'], max( count( $results ), 1 ) ) - 1;

		$right       .= "<div class='cpis-image-store-items'>";
		$item_counter = 0;
		$margin       = '';
		foreach ( $results as $result ) {
			$right .= "<div style='width:{$width}%;{$margin}' class='cpis-image-store-item'>" . cpis_display_content( $result->ID, 'store', 'return' ) . '</div>';
			$item_counter++;
			$margin = 'margin-left:2%;';
			if ( 0 == $item_counter % $options['store']['columns'] ) {
				$right .= "<div style='clear:both;'></div>";
				$margin = '';
			}
		}
		$right .= "<div style='clear:both;'></div>";
		$right .= '</div>';

		// End right column
		$right .= $page_links . '</div>';

		return "<div class='cpis-image-store'>" . $left . $right . "<div style='clear:both;' ></div></div>";
	}
} // End cpis_replace_shortcode

if ( ! function_exists( 'cpis_complete_url' ) ) {
	function cpis_complete_url( $url ) {
		if ( get_option( 'cpis_prevent_cache', true ) ) {
			$url = add_query_arg( '_cpisr', time(), remove_query_arg( '_cpisr', $url ) );
		}
		return $url;

	}
} // End cpis_complete_url

if ( ! function_exists( 'cpis_setError' ) ) {
	function cpis_setError( $error_text ) {
		global $cpis_errors;
		$cpis_errors[] = __( $error_text, 'cp-image-store' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
	}
} // End cpis_setError

if ( ! function_exists( 'cpis_getIP' ) ) {
	function cpis_getIP() {
		 $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		}

		return str_replace( array( ':', '.' ), array( '_', '_' ), $ip );
	}
}

if ( ! function_exists( 'cpis_check_download_permissions' ) ) {
	function cpis_check_download_permissions() {
		if ( get_transient( 'cpis_penalized_ip_' . cpis_getIP() ) !== false ) {
			esc_html_e( 'The purchase has not being registered yet or the ID is incorrect, please, try again in 30 minutes.', 'cp-image-store' );
			return false;
		}
		delete_transient( 'cpis_penalized_ip_' . cpis_getIP() );
		global $wpdb;

		// Check if the file is a file managed by the plugin
		if ( isset( $_REQUEST['f'] ) ) {
			$_file = sanitize_text_field( wp_unslash( $_REQUEST['f'] ) );
			$f_tmp = basename( $_file );
			if ( empty( $f_tmp ) || ! file_exists( CPIS_DOWNLOAD . '/' . $f_tmp ) ) {
				return false;
			}
		}

		// Check if download for free or the user is an admin
		if ( ! empty( $GLOBALS[ CPIS_SESSION_NAME ]['cpis_download_for_free'] ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		// and check the existence of a parameter with the purchase_id
		if ( empty( $_REQUEST['purchase_id'] ) ) {
			cpis_setError( 'The purchase id is required' );
			return false;
		}

		if ( get_option( 'cpis_safe_download', CPIS_SAFE_DOWNLOAD ) ) {
			if ( ! empty( $_REQUEST['cpis_user_email'] ) ) {
				$GLOBALS[ CPIS_SESSION_NAME ]['cpis_user_email'] = sanitize_text_field( wp_unslash( $_REQUEST['cpis_user_email'] ) );
			}

			// Check if the user has typed the email used to purchase the product
			if ( empty( $GLOBALS[ CPIS_SESSION_NAME ]['cpis_user_email'] ) ) {
				$dlurl  = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
				$dlurl .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' ) . 'cpis-action=download&purchase_id=' . sanitize_key( $_REQUEST['purchase_id'] );
				cpis_setError( 'Please, go to the download page, and enter the email address used in products purchasing' );
				return false;
			}
			$data = $wpdb->get_row( $wpdb->prepare( 'SELECT CASE WHEN checking_date IS NULL THEN DATEDIFF(NOW(), date) ELSE DATEDIFF(NOW(), checking_date) END AS days, downloads, id FROM ' . $wpdb->prefix . CPIS_PURCHASE . ' WHERE purchase_id=%s AND email=%s ORDER BY checking_date DESC, date DESC', array( sanitize_key( $_REQUEST['purchase_id'] ), $GLOBALS[ CPIS_SESSION_NAME ]['cpis_user_email'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$data = $wpdb->get_row( $wpdb->prepare( 'SELECT CASE WHEN checking_date IS NULL THEN DATEDIFF(NOW(), date) ELSE DATEDIFF(NOW(), checking_date) END AS days, downloads, id FROM ' . $wpdb->prefix . CPIS_PURCHASE . ' WHERE purchase_id=%s ORDER BY checking_date DESC, date DESC', array( sanitize_key( $_REQUEST['purchase_id'] ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$options        = get_option( 'cpis_options' );
		$valid_download = ( ! empty( $options['store']['download_link'] ) ) ? $options['store']['download_link'] : 3;
		if ( is_null( $data ) ) {
			if ( ! isset( $_REQUEST['timeout'] ) ) {
				cpis_setError(
					'<div id="cpis_error_mssg"></div>
                    <script>
                        var timeout_text = "' . esc_attr__( 'The store should be processing the purchase. You will be redirected in', 'cp-image-store' ) . '";
                    </script>'
				);
			} else {
				set_transient( 'cpis_penalized_ip_' . cpis_getIP(), true, 1800 );
				cpis_setError( 'The purchase has not being registered yet or the ID is incorrect, please, try again in 30 minutes.' );
			}
			return false;
		} elseif ( $valid_download < $data->days ) {
			cpis_setError( 'The download link has expired, please contact to the vendor' );
			return false;
		} elseif ( $options['store']['download_limit'] > 0 && $options['store']['download_limit'] <= $data->downloads ) {
			cpis_setError( 'The number of downloads has reached its limit, please contact to the vendor' );
			return false;
		}
		if ( isset( $_file ) && ! isset( $GLOBALS[ CPIS_SESSION_NAME ]['cpis_donwloads'] ) ) {
			$GLOBALS[ CPIS_SESSION_NAME ]['cpis_donwloads'] = 1;
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . CPIS_PURCHASE . ' SET downloads=downloads+1 WHERE id=%d', $data->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return true;
	}
} // End cpis_check_download_permissions

if ( ! function_exists( 'cpis_mime_content_type' ) ) {
	function cpis_mime_content_type( $filename ) {
		$idx   = strtolower( end( explode( '.', $filename ) ) );
		$mimet = array(
			'ai'      => 'application/postscript',
			'3gp'     => 'audio/3gpp',
			'flv'     => 'video/x-flv',
			'aif'     => 'audio/x-aiff',
			'aifc'    => 'audio/x-aiff',
			'aiff'    => 'audio/x-aiff',
			'asc'     => 'text/plain',
			'atom'    => 'application/atom+xml',
			'avi'     => 'video/x-msvideo',
			'bcpio'   => 'application/x-bcpio',
			'bmp'     => 'image/bmp',
			'cdf'     => 'application/x-netcdf',
			'cgm'     => 'image/cgm',
			'cpio'    => 'application/x-cpio',
			'cpt'     => 'application/mac-compactpro',
			'crl'     => 'application/x-pkcs7-crl',
			'crt'     => 'application/x-x509-ca-cert',
			'csh'     => 'application/x-csh',
			'css'     => 'text/css',
			'dcr'     => 'application/x-director',
			'dir'     => 'application/x-director',
			'djv'     => 'image/vnd.djvu',
			'djvu'    => 'image/vnd.djvu',
			'doc'     => 'application/msword',
			'dtd'     => 'application/xml-dtd',
			'dvi'     => 'application/x-dvi',
			'dxr'     => 'application/x-director',
			'eps'     => 'application/postscript',
			'etx'     => 'text/x-setext',
			'ez'      => 'application/andrew-inset',
			'gif'     => 'image/gif',
			'gram'    => 'application/srgs',
			'grxml'   => 'application/srgs+xml',
			'gtar'    => 'application/x-gtar',
			'hdf'     => 'application/x-hdf',
			'hqx'     => 'application/mac-binhex40',
			'html'    => 'text/html',
			'html'    => 'text/html',
			'ice'     => 'x-conference/x-cooltalk',
			'ico'     => 'image/x-icon',
			'ics'     => 'text/calendar',
			'ief'     => 'image/ief',
			'ifb'     => 'text/calendar',
			'iges'    => 'model/iges',
			'igs'     => 'model/iges',
			'jpe'     => 'image/jpeg',
			'jpeg'    => 'image/jpeg',
			'jpg'     => 'image/jpeg',
			'js'      => 'application/x-javascript',
			'kar'     => 'audio/midi',
			'latex'   => 'application/x-latex',
			'm3u'     => 'audio/x-mpegurl',
			'man'     => 'application/x-troff-man',
			'mathml'  => 'application/mathml+xml',
			'me'      => 'application/x-troff-me',
			'mesh'    => 'model/mesh',
			'm4a'     => 'audio/x-m4a',
			'mid'     => 'audio/midi',
			'midi'    => 'audio/midi',
			'mif'     => 'application/vnd.mif',
			'mov'     => 'video/quicktime',
			'movie'   => 'video/x-sgi-movie',
			'mp2'     => 'audio/mpeg',
			'mp3'     => 'audio/mpeg',
			'mp4'     => 'video/mp4',
			'm4v'     => 'video/x-m4v',
			'mpe'     => 'video/mpeg',
			'mpeg'    => 'video/mpeg',
			'mpg'     => 'video/mpeg',
			'mpga'    => 'audio/mpeg',
			'ms'      => 'application/x-troff-ms',
			'msh'     => 'model/mesh',
			'mxu m4u' => 'video/vnd.mpegurl',
			'nc'      => 'application/x-netcdf',
			'oda'     => 'application/oda',
			'ogg'     => 'application/ogg',
			'pbm'     => 'image/x-portable-bitmap',
			'pdb'     => 'chemical/x-pdb',
			'pdf'     => 'application/pdf',
			'pgm'     => 'image/x-portable-graymap',
			'pgn'     => 'application/x-chess-pgn',
			'php'     => 'application/x-httpd-php',
			'php4'    => 'application/x-httpd-php',
			'php3'    => 'application/x-httpd-php',
			'phtml'   => 'application/x-httpd-php',
			'phps'    => 'application/x-httpd-php-source',
			'png'     => 'image/png',
			'pnm'     => 'image/x-portable-anymap',
			'ppm'     => 'image/x-portable-pixmap',
			'ppt'     => 'application/vnd.ms-powerpoint',
			'ps'      => 'application/postscript',
			'qt'      => 'video/quicktime',
			'ra'      => 'audio/x-pn-realaudio',
			'ram'     => 'audio/x-pn-realaudio',
			'ras'     => 'image/x-cmu-raster',
			'rdf'     => 'application/rdf+xml',
			'rgb'     => 'image/x-rgb',
			'rm'      => 'application/vnd.rn-realmedia',
			'roff'    => 'application/x-troff',
			'rtf'     => 'text/rtf',
			'rtx'     => 'text/richtext',
			'sgm'     => 'text/sgml',
			'sgml'    => 'text/sgml',
			'sh'      => 'application/x-sh',
			'shar'    => 'application/x-shar',
			'shtml'   => 'text/html',
			'silo'    => 'model/mesh',
			'sit'     => 'application/x-stuffit',
			'skd'     => 'application/x-koan',
			'skm'     => 'application/x-koan',
			'skp'     => 'application/x-koan',
			'skt'     => 'application/x-koan',
			'smi'     => 'application/smil',
			'smil'    => 'application/smil',
			'snd'     => 'audio/basic',
			'spl'     => 'application/x-futuresplash',
			'src'     => 'application/x-wais-source',
			'sv4cpio' => 'application/x-sv4cpio',
			'sv4crc'  => 'application/x-sv4crc',
			'svg'     => 'image/svg+xml',
			'swf'     => 'application/x-shockwave-flash',
			't'       => 'application/x-troff',
			'tar'     => 'application/x-tar',
			'tcl'     => 'application/x-tcl',
			'tex'     => 'application/x-tex',
			'texi'    => 'application/x-texinfo',
			'texinfo' => 'application/x-texinfo',
			'tgz'     => 'application/x-tar',
			'tif'     => 'image/tiff',
			'tiff'    => 'image/tiff',
			'tr'      => 'application/x-troff',
			'tsv'     => 'text/tab-separated-values',
			'txt'     => 'text/plain',
			'ustar'   => 'application/x-ustar',
			'vcd'     => 'application/x-cdlink',
			'vrml'    => 'model/vrml',
			'vxml'    => 'application/voicexml+xml',
			'wav'     => 'audio/x-wav',
			'wbmp'    => 'image/vnd.wap.wbmp',
			'wbxml'   => 'application/vnd.wap.wbxml',
			'wml'     => 'text/vnd.wap.wml',
			'wmlc'    => 'application/vnd.wap.wmlc',
			'wmlc'    => 'application/vnd.wap.wmlc',
			'wmls'    => 'text/vnd.wap.wmlscript',
			'wmlsc'   => 'application/vnd.wap.wmlscriptc',
			'wmlsc'   => 'application/vnd.wap.wmlscriptc',
			'wrl'     => 'model/vrml',
			'xbm'     => 'image/x-xbitmap',
			'xht'     => 'application/xhtml+xml',
			'xhtml'   => 'application/xhtml+xml',
			'xls'     => 'application/vnd.ms-excel',
			'xml xsl' => 'application/xml',
			'xpm'     => 'image/x-xpixmap',
			'xslt'    => 'application/xslt+xml',
			'xul'     => 'application/vnd.mozilla.xul+xml',
			'xwd'     => 'image/x-xwindowdump',
			'xyz'     => 'chemical/x-xyz',
			'zip'     => 'application/zip',
		);

		if ( isset( $mimet[ $idx ] ) ) {
			return $mimet[ $idx ];
		} else {
			return 'application/octet-stream';
		}
	}
}

if ( ! function_exists( 'cpis_checkMemory' ) ) {
	// Check if the PHP memory is sufficient
	function cpis_checkMemory( $files = array() ) {
		$required = 0;

		$m = ini_get( 'memory_limit' );
		$m = trim( $m );
		$l = strtolower( $m[ strlen( $m ) - 1 ] ); // last
		switch ( $l ) {
			// The 'G' modifier is available since PHP 5.1.0
			case 'g':
				$m *= 1024;
			case 'm':
				$m *= 1024;
			case 'k':
				$m *= 1024;
		}

		foreach ( $files as $file ) {
			$memory_available = $m - memory_get_usage( true );
			if ( file_exists( $file ) ) {
				$required += filesize( $file );
				if ( $required >= $memory_available - 100 ) {
					return false;
				}
			} else {
				return false;
			}
		}
		return true;
	}
} // cpis_checkMemory

if ( ! function_exists( 'cpis_download_file' ) ) {
	function cpis_download_file() {
		global $wpdb, $cpis_errors;

		if ( isset( $_REQUEST['f'] ) && cpis_check_download_permissions() ) {
			$file = basename( sanitize_text_field( wp_unslash( $_REQUEST['f'] ) ) );
			try {
				header( 'Content-Type: ' . cpis_mime_content_type( $file ) );
				header( 'Content-Disposition: attachment; filename="' . $file . '"' );

				@ob_get_clean();
				@ob_start();

				$h = fopen( CPIS_DOWNLOAD . '/' . $file, 'rb' );
				if ( $h ) {
					while ( ! feof( $h ) ) {
						echo fread( $h, 1024 * 8 ); // phpcs:ignore WordPress.Security.EscapeOutput
						@ob_flush();
						flush();
					}
					fclose( $h );
				} else {
					print 'The file cannot be opened';
				}
			} catch ( Exception $err ) {
				@unlink( CPIS_DOWNLOAD . '/.htaccess' );
				header( 'location:' . CPIS_PLUGIN_URL . '/downloads/' . $file );
			}
			exit;
		} else {
			$dlurl  = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
			$dlurl .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' ) . 'cpis-action=download' . ( ( ! empty( $_REQUEST['purchase_id'] ) ) ? '&purchase_id=' . sanitize_key( $_REQUEST['purchase_id'] ) : '' );
			header( 'location: ' . $dlurl );
		}
		exit;
	}
} // End cpis_download_file

if ( ! function_exists( 'cpis_apply_taxes' ) ) {
	function cpis_apply_taxes( $v ) {
		$options = get_option( 'cpis_options' );
		if (
			$options &&
			! empty( $options['paypal'] ) &&
			! empty( $options['paypal']['tax'] )
		) {
			$v *= ( 1 + $options['paypal']['tax'] / 100 );
		}
		return $v;
	} // End cpis_apply_taxes
}

// From PayPal Data RAW
/*
  $fieldsArr, array( 'fields name' => 'alias', ... )
  $selectAdd, used if is required complete the results like: COUNT(*) as count
  $groupBy, array( 'alias', ... ) the alias used in the $fieldsArr parameter
  $orderBy, array( 'alias' => 'direction', ... ) the alias used in the $fieldsArr parameter, direction = ASC or DESC
*/
if ( ! function_exists( 'cpis_getFromPayPalData' ) ) {
	function cpis_getFromPayPalData( $fieldsArr, $selectAdd = '', $from = '', $where = '', $groupBy = array(), $orderBy = array(), $returnAs = 'json' ) {
		global $wpdb;

		$_select  = 'SELECT ';
		$_from    = 'FROM ' . $wpdb->prefix . CPIS_PURCHASE . ( ( ! empty( $from ) ) ? ',' . $from : '' );
		$_where   = 'WHERE ' . ( ( ! empty( $where ) ) ? $where : 1 );
		$_groupBy = ( ! empty( $groupBy ) ) ? 'GROUP BY ' : '';
		$_orderBy = ( ! empty( $orderBy ) ) ? 'ORDER BY ' : '';

		$separator = '';
		foreach ( $fieldsArr as $key => $value ) {
			$length    = strlen( $key ) + 1;
			$_select  .= $separator . '
							SUBSTRING(paypal_data,
							LOCATE("' . $key . '", paypal_data)+' . $length . ',
							LOCATE("\r\n", paypal_data, LOCATE("' . $key . '", paypal_data))-(LOCATE("' . $key . '", paypal_data)+' . $length . ')) AS ' . $value;
			$separator = ',';
		}

		if ( ! empty( $selectAdd ) ) {
			$_select .= $separator . $selectAdd;
		}

		$separator = '';
		foreach ( $groupBy as $value ) {
			$_groupBy .= $separator . $value;
			$separator = ',';
		}

		$separator = '';
		foreach ( $orderBy as $key => $value ) {
			$_orderBy .= $separator . $key . ' ' . $value;
			$separator = ',';
		}

		$query = $_select . ' ' . $_from . ' ' . $_where . ' ' . $_groupBy . ' ' . $_orderBy;

		$result = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $result ) ) {
			switch ( $returnAs ) {
				case 'json':
					return json_encode( $result );
				break;
				default:
					return $result;
				break;
			}
		}
	}
} // End cpis_getFromPayPalData

 /**
  * CPISProductWidget Class
  */
class CPISProductWidget extends WP_Widget {

	/** constructor */
	public function __construct() {
		parent::__construct( false, $name = 'Image Store Product' );
	}

	public function widget( $args, $instance ) {
		extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract
		$title = apply_filters( 'widget_title', $instance['title'] );

		$defaults   = array( 'product_id' => '' );
		$instance_p = wp_parse_args( (array) $instance, $defaults );

		$product_id = $instance_p['product_id'];

		$atts = array( 'id' => $product_id );

		?>
			  <?php echo wp_kses_post( $before_widget );
				if ( $title ) {
					echo wp_kses_post( $before_title . $title . $after_title );
				}
					$atts['layout'] = 'widget';
					print cpis_replace_product_shortcode( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>

			  <?php echo wp_kses_post( $after_widget ); ?>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* Strip tags (if needed) and update the widget settings. */
		$instance['title']      = strip_tags( $new_instance['title'] );
		$instance['product_id'] = $new_instance['product_id'] * 1;

		return $instance;
	}

	public function form( $instance ) {

		/* Set up some default widget settings. */
		$defaults = array(
			'title'      => '',
			'product_id' => '',
		);
		$instance = wp_parse_args( (array) $instance, $defaults );

		$title      = $instance['title'];
		$product_id = $instance['product_id'];

		?>
			<p><label for="<?php print esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:' ); ?> <input class="widefat" id="<?php print esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php print esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php print esc_attr( $title ); ?>" /></label></p>

			<p>
				<label for="<?php print esc_attr( $this->get_field_id( 'product_id' ) ); ?>"><?php esc_html_e( 'Enter the product ID:', 'cp-image-store' ); ?><br />
					<input class="widefat" id="<?php print esc_attr( $this->get_field_id( 'product_id' ) ); ?>" name="<?php print esc_attr( $this->get_field_name( 'product_id' ) ); ?>" value="<?php print esc_attr( $product_id ); ?>" />
				</label>
			</p>
		<?php
	}
} // clase CPISProductWidget
