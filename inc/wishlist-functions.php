<?php
/*
* Wishlist functions
*/

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

add_action( 'woocommerce_after_add_to_cart_button', 'woo_wishlist_add_button', 20 ); // Add to single product page
add_action( 'woocommerce_after_shop_loop_item', 'woo_wishlist_add_button', 20 ); // Add to product loop
/**
 * Add wishlist button
 */
function woo_wishlist_add_button() {
    global $product;

    if ( ! $product->is_type( 'simple' ) ) {
        return;
    }

    $product_id = $product->get_id();
    $wishlist_url = get_permalink( get_option('woo_wishlist_page_id') );
    $wishlist_url = add_query_arg( 'add_to_wishlist', $product_id, $wishlist_url );
    $wishlist_url = wp_nonce_url( $wishlist_url, 'woo_wishlist_nonce' );
    $title = __('Add to wishlist', 'woo-wishlist');
    $icon = '<i class="fas fa-heart"></i>';
    $button = '<a href="'.$wishlist_url.'" title="'.$title.'" class="woo-wishlist-button" data-product-id="'.$product_id.'">'.$icon.'</a>';
    
    echo apply_filters( 'woo_wishlist_button', $button, $product_id );
}