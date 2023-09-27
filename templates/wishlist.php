<?php
/**
 * Wishlist template
 * 
 * @package Woo Wishlist
 * 
 * You can override this template by copying it to yourtheme/woo-wishlist/wishlist.php
 * 
 * Template variables:
 * @var $wishlist - array of wishlist product IDs
 * @var $args - WP_Query args
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( empty($wishlist) ) {
    woo_wishlist_empty_wishlist_message();
    return;
}
?>

<?php
    woo_wishlist_setup_loop( $loop );

    if ( $loop->have_posts() ) :
        /**
         * Hook: woo_wishlist_before_wishlist.
         * 
         * @hooked storefront_sorting_wrapper - 5
         * @hooked woo_wishlist_add_all_products_to_cart_button - 10
         * @hooked woocommerce_pagination - 20
         * @hooked storefront_sorting_wrapper_close - 90
         */
        do_action( 'woo_wishlist_before_wishlist', $loop ); 
        
        woocommerce_product_loop_start();

        while ( $loop->have_posts() ) : $loop->the_post();
            wc_get_template_part( 'content', 'product' );
        endwhile;
        
        woocommerce_product_loop_end();

        /**
         * Hook: woo_wishlist_after_wishlist.
         */
        do_action( 'woo_wishlist_after_wishlist', $loop );
    endif;

    wp_reset_postdata();
    wc_reset_loop();
?>