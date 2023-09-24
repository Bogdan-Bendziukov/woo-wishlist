<?php
/**
 * Wishlist functions
 *
 * @package  woo-wishlist
*/

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( !function_exists( 'woo_wishlist_add_button' ) ) {
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
        $is_in_wishlist = woo_wishlist_is_in_wishlist( $product_id );
        $title = $is_in_wishlist ? __('Remove from wishlist', 'woo-wishlist') : __('Add to wishlist', 'woo-wishlist');
        $icon = '<i class="woo-wishlist-icon fas fa-heart"></i>';
        $button = '<a href="#" title="'.$title.'" class="woo-wishlist-button '.($is_in_wishlist ? "added" : "").'" data-product-id="'.$product_id.'">'.$icon.'</a>';

        echo apply_filters( 'woo_wishlist_button', $button, $product_id );
    }
}

if ( !function_exists( 'woo_wishlist_add_to_wishlist' ) ) {
    add_action( 'wp_ajax_woo_wishlist_add_to_wishlist', 'woo_wishlist_add_to_wishlist' );
    add_action( 'wp_ajax_nopriv_woo_wishlist_add_to_wishlist', 'woo_wishlist_add_to_wishlist' );
    /**
     * Add product to wishlist
     */
    function woo_wishlist_add_to_wishlist() {
        if ( !isset( $_POST['product_id'] ) ) {
            exit;
        }

        if ( !isset( $_POST['wishlist_nonce'] ) || !wp_verify_nonce( $_POST['wishlist_nonce'], 'woo_wishlist_nonce' ) ) {
            exit;
        }

        if ( !is_user_logged_in() ) {
            $response = array(
                'message' => __('You must log in to add items to wishlist!', 'woo-wishlist'),
            );
            echo wp_send_json_error($response);
            exit;
        }

        $product_id = absint( $_POST['product_id'] );
        $user_id = get_current_user_id();
        $is_in_wishlist = woo_wishlist_is_in_wishlist( $product_id );
        $wishlist = get_user_meta( $user_id, 'woo_wishlist', true );
        $wishlist = is_array( $wishlist ) ? $wishlist : array();
        $result = false;
        $action = '';

        if ( $is_in_wishlist === false ) {
            if ( !empty($wishlist) ) {
                $wishlist[] = $product_id;
                $result = update_user_meta( $user_id, 'woo_wishlist', $wishlist );
                $action = 'updated';
            } else {
                $result = add_user_meta( $user_id, 'woo_wishlist', array($product_id) );
                $action = 'added';
            }
        } else {
            if (($key = array_search($product_id, $wishlist)) !== false) {
                unset($wishlist[$key]);
            }
            $result = update_user_meta( $user_id, 'woo_wishlist', $wishlist );
            $action = 'removed';
        }

        if ( $result !== false ) {
            if ($action === 'added' || $action === 'updated') {
                $response = array(
                    'action' => $action,
                    'result' => $result,
                    'message' => __('Product added to wishlist', 'woo-wishlist'),
                    'btn_title' => __('Remove from wishlist', 'woo-wishlist'),
                    'wishlist' => $wishlist
                );
            } else {
                $response = array(
                    'action' => $action,
                    'result' => $result,
                    'message' => __('Product removed from wishlist', 'woo-wishlist'),
                    'btn_title' => __('Add to wishlist', 'woo-wishlist'),
                    'wishlist' => $wishlist
                );
            }
            echo wp_send_json_success($response);
        } else {
            $response = array(
                'result' => $result,
                'message' => __('Error occured', 'woo-wishlist'),
            );
            echo wp_send_json_error($response);
        }

        exit;
    }
}

if ( !function_exists( 'woo_wishlist_is_in_wishlist' ) ) {
    /**
     * Check if product is in wishlist
     */
    function woo_wishlist_is_in_wishlist( $product_id ) {
        if ( !is_user_logged_in() ) {
            return false;
        }

        $user_id = get_current_user_id();
        $wishlist = get_user_meta( $user_id, 'woo_wishlist', true );
        
        $wishlist = is_array( $wishlist ) ? $wishlist : array();

        if ( in_array( $product_id, $wishlist ) ) {
            return true;
        } else {
            return false;
        }
    }
}