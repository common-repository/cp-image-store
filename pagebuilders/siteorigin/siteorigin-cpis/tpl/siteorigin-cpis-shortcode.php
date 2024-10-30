<?php
$shortcode = ( ! empty( $instance['shortcode'] ) ) ? $instance['product_type'] : '[codepeople-image-store]';
$shortcode = preg_replace( '/[\n\r]/', ' ', $shortcode );
$shortcode = sanitize_text_field( $shortcode );

if ( empty( $shortcode ) ) {
	$shortcode = '[codepeople-image-store]';
}
print wp_kses_post( $shortcode );
