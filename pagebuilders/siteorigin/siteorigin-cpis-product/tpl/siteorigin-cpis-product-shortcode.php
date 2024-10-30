<?php
$product = trim( ! empty( $instance['product'] ) ? $instance['product'] : '' );
$product = @intval( $product );
if ( $product ) {
	$shortcode = '[codepeople-image-store-product id="' . esc_attr( $product ) . '"';
	$layout    = sanitize_text_field( ! empty( $instance['layout'] ) ? $instance['layout'] : '' );
	if ( ! empty( $layout ) ) {
		$shortcode .= ' layout="' . esc_attr( $layout ) . '"';
	}
	$shortcode .= ']';
}
print ! empty( $shortcode ) ? wp_kses_post( $shortcode ) : '';
