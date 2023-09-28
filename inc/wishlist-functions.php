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

        if ( ! woo_wishlist_is_wishlist_page() && ! is_product() && ! $product->is_type( 'simple' ) ) { // Hide wishlist button for variable products in loop
            return;
        }

        $is_variable_product = $product->is_type( 'variable' ); // check if product is variable (on single product page or in shop loop)
        $is_product_variation = $product->post_type === 'product_variation'; // check if product is variation (on wishlist page)
        $product_id = $product->get_id();  
        $is_in_wishlist = false;

        if ( $is_variable_product === false ) {
            if ($is_product_variation) { // check if product variation is in wishlist
                $is_in_wishlist = woo_wishlist_is_in_wishlist( $product->get_parent_id(), $product_id );
            } else {
                $is_in_wishlist = woo_wishlist_is_in_wishlist( $product_id );
            }
        }

        $title = $is_in_wishlist ? 
            apply_filters('woo_wishlist_remove_from_wishlist_message', __('Remove from wishlist', 'woo-wishlist')) 
            : 
            apply_filters('woo_wishlist_add_to_wishlist_message', __('Add to wishlist', 'woo-wishlist'));

        $attrs = array(
            'href' => '#',
            'data-product-id' => $product_id,
            'title' => $title,
            'class' => 'woo-wishlist-button '.($is_in_wishlist ? "added" : ""),
        );

        if ( $is_variable_product ) {
            $attrs['data-variation-id'] = '0';
            $attrs['data-variations-in-wishlist'] = implode(",", woo_wishlist_get_variations_in_wishlist($product_id));
            $attrs['class'] .= ' variation-selection-needed';
        }

        if ( $is_product_variation ) {
            $attrs['data-product-id'] = $product->get_parent_id();
            $attrs['data-variation-id'] = $product_id;
        }

        $attrs = apply_filters( 'woo_wishlist_button_attrs', $attrs, $product_id );

        $attrs = array_map( function($v, $k) { return $k.'="'.$v.'"'; }, $attrs, array_keys($attrs) );
        $attrs = implode(' ', $attrs);
        $icon = '<i class="woo-wishlist-icon fas fa-heart"></i>';
        $button = '<a '.$attrs.'>'.$icon.'</a>';

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
        if ( !isset( $_POST['product_id'] ) || wc_get_product( $_POST['product_id'] ) === false ) {
            exit;
        }

        if ( isset( $_POST['variation_id'] ) && ( $_POST['variation_id'] === '0' || wc_get_product( $_POST['variation_id'] ) === false ) ) {
            $response = array(
                'message' => __('This variation is unavailable', 'woo-wishlist'),
            );
            echo wp_send_json_error($response);
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
        $variation_id = isset($_POST['variation_id']) ? absint( $_POST['variation_id'] ) : 0;

        $product_to_add = woo_wishlist_prepare_product( $product_id, $variation_id );
        $user_id = get_current_user_id();
        $is_in_wishlist = woo_wishlist_is_in_wishlist( $product_id, $variation_id );
        $wishlist = woo_wishlist_get_wishlist();
        $result = false;
        $action = '';

        if ( $is_in_wishlist === false ) {
            $wishlist[] = $product_to_add;
            if ( !empty($wishlist) ) {
                $result = update_user_meta( $user_id, 'woo_wishlist', $wishlist );
                $action = 'updated';
            } else {
                $result = add_user_meta( $user_id, 'woo_wishlist', $wishlist );
                $action = 'added';
            }
        } else {
            if (($key = array_search($product_to_add, $wishlist)) !== false) {
                unset($wishlist[$key]);
            }
            $result = update_user_meta( $user_id, 'woo_wishlist', $wishlist );
            $action = 'removed';
        }

        $fragments = apply_filters(
            'woo_wishlist_add_to_wishlist_fragments',
            array(
                'a.woo-wishlist-link' => '', // fragments added in `woo_wishlist_header_fragment` function
            )
        );

        if ( $result !== false ) {
            if ($action === 'added' || $action === 'updated') {
                $response = array(
                    'action' => $action,
                    'result' => $result,
                    'message' => __('Product added to wishlist', 'woo-wishlist'),
                    'btn_title' => __('Remove from wishlist', 'woo-wishlist'),
                    'wishlist' => $wishlist,
                    'fragments' => $fragments,
                );
            } else {
                $response = array(
                    'action' => $action,
                    'result' => $result,
                    'message' => __('Product removed from wishlist', 'woo-wishlist'),
                    'btn_title' => __('Add to wishlist', 'woo-wishlist'),
                    'wishlist' => $wishlist,
                    'fragments' => $fragments,
                );
            }
            if ( $variation_id ) {
                $response['variations_in_wishlist'] = woo_wishlist_get_variations_in_wishlist($product_id);
            }
            echo wp_send_json_success($response);
        } else {
            $response = array(
                'action' => $action,
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
    function woo_wishlist_is_in_wishlist( $product_id, $variation_id = 0 ) {
        if ( !is_user_logged_in() ) {
            return false;
        }

        $product_to_check = woo_wishlist_prepare_product( $product_id, $variation_id);
        $user_id = get_current_user_id();
        $wishlist = get_user_meta( $user_id, 'woo_wishlist', true );
        
        $wishlist = is_array( $wishlist ) ? $wishlist : array();

        if (empty($wishlist)) {
            return false;
        }

        if ( in_array( $product_to_check, $wishlist ) ) {
            return true;
        } else {
            return false;
        }
    }
}

if ( !function_exists( 'woo_wishlist_prepare_product' ) ) {
    /**
     * Prepare product for wishlist
     */
    function woo_wishlist_prepare_product( $product_id, $variation_id = 0 ) {
        $product_to_add = '';

        if ( $variation_id ) {
            $product_to_add = $product_id.'-'.$variation_id;
        } else {
            $product_to_add = $product_id;
        }

        return $product_to_add;
    }
}

if ( !function_exists( 'woo_wishlist_get_wishlist' ) ) {
    /**
     * Get wishlist for current user
     */
    function woo_wishlist_get_wishlist() {
        if ( !is_user_logged_in() ) {
            return array();
        }

        $user_id = get_current_user_id();
        $wishlist = get_user_meta( $user_id, 'woo_wishlist', true );
        
        $wishlist = is_array( $wishlist ) ? $wishlist : array();

        return $wishlist;
    }
}

if ( !function_exists( 'woo_wishlist_get_variations_in_wishlist' ) ) {
    /**
     * Get variations in wishlist for product 
     */
    function woo_wishlist_get_variations_in_wishlist($product_id) {
        if ( !is_user_logged_in() ) {
            return array();
        }

        $user_id = get_current_user_id();
        $wishlist = get_user_meta( $user_id, 'woo_wishlist', true );
        
        $wishlist = is_array( $wishlist ) ? $wishlist : array();

        if ( empty($wishlist) ) {
            return array();
        }

        $variations_in_wishlist = array();
        foreach ($wishlist as $product) {
            $product = explode('-', $product);
            if ( $product[0] == $product_id ) {
                $variations_in_wishlist[] = $product[1];
            }
        }

        return $variations_in_wishlist;
    }
}

if ( !function_exists( 'woo_wishlist_link')) {

    // Add to header before cart widget
    add_action( 'storefront_header', 'woo_wishlist_link', 51 ); 
    /**
     * Create wishlist link
     */
    function woo_wishlist_link() {
        $wishlist = woo_wishlist_get_wishlist();
        $icon = '<i class="woo-wishlist-icon fas fa-heart"></i>';
        $title = __('Go to Wishlist', 'woo-wishlist');
        $count = count($wishlist);
        $wishlist_count_text = '';
        $wishlist_url = get_permalink( get_option('woo_wishlist_page_id') );

        if ( $count > 0 ) {
            $wishlist_count_text = '<span class="woo-wishlist-count">'.$count.'</span>';
        }
        
        $button = '<a href="'.$wishlist_url.'" title="'.$title.'" class="woo-wishlist-link">'.$icon.$wishlist_count_text.'</a>';
        
        echo apply_filters( 'woo_wishlist_link', $button, $wishlist );
    }
}

if ( !function_exists( 'woo_wishlist_handheld_footer_bar_links' ) ) {
    add_filter( 'storefront_handheld_footer_bar_links', 'woo_wishlist_handheld_footer_bar_links', 50 );
    /**
     * Add wishlist link to handheld footer bar
     */
    function woo_wishlist_handheld_footer_bar_links( $links ) {
        
        $links['woo_wishlist'] = array(
            'priority' => 40,
            'callback' => 'woo_wishlist_link',
        );

        return $links;
    }
}

if ( ! function_exists( 'woo_wishlist_header_fragment' ) ) {
    add_filter( 'woo_wishlist_add_to_wishlist_fragments', 'woo_wishlist_header_fragment');
	/**
	 * Wishlist Fragments
	 * Ensure wishlist count updates when products are added to the wishlist via AJAX
	 */
	function woo_wishlist_header_fragment( $fragments ) {

		ob_start();
		woo_wishlist_link();
		$fragments['a.woo-wishlist-link'] = ob_get_clean();

		return $fragments;
	}
}

if (!function_exists('woo_wishlist_account_menu_item')) {
    add_filter( 'woocommerce_account_menu_items', 'woo_wishlist_account_menu_item', 10, 2 );
    /**
     * Add wishlist menu item to my account menu
     */
    function woo_wishlist_account_menu_item($items, $endpoints) {
        $items['woo-wishlist'] = __('Wishlist', 'woo-wishlist');
        return $items;
    }
}

if (!function_exists('woo_wishlist_account_menu_item_endpoint')) {
    add_filter( 'woocommerce_get_endpoint_url', 'woo_wishlist_account_menu_item_endpoint', 10, 4 );
    /**
     * Add wishlist endpoint to my account menu
     */
    function woo_wishlist_account_menu_item_endpoint($url, $endpoint, $value, $permalink) {
        if ( $endpoint === 'woo-wishlist' ) {
            $url = get_permalink( get_option('woo_wishlist_page_id') );
        }
        return $url;
    }
}

if (!function_exists('woo_wishlist_shortcode')) {
    add_shortcode( 'woo_wishlist', 'woo_wishlist_shortcode' );
    /**
     * Wishlist shortcode
     */
    function woo_wishlist_shortcode() {
        if ( !is_user_logged_in() ) {
            return '<p>'.__('You must log in to view your wishlist!', 'woo-wishlist').'</p>';
        }

        $wishlist = woo_wishlist_get_wishlist();
        
        // allow to override template
        $template = locate_template( 'woo-wishlist/wishlist.php', false, false, $wishlist );

        if ( !$template ) {
            $template = WOO_WISHLIST_PLUGIN_PATH . 'templates/wishlist.php';
        }

        $columns      = get_option( 'woocommerce_catalog_columns', 4 );
        $rows         = absint( get_option( 'woocommerce_catalog_rows', 4 ) );
        $per_page     = apply_filters( 'loop_shop_per_page', $columns * $rows );
        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

        // find variations in wishlist and extract variation IDs
        $wishlist = array_map( function($v) {
            return count(explode('-', $v, 2)) > 1 ? explode('-', $v, 2)[1] : $v;
        }, $wishlist );

        $args = array(
            'post_type' => array('product', 'product_variation'),
            'posts_per_page' => $per_page,
            'post__in' => $wishlist,
            'paged' => $paged,
        );

        $args = apply_filters( 'woo_wishlist_wishlist_query_args', $args );

        $loop = new WP_Query( $args );

        ob_start();
        include $template;
        $template = ob_get_clean();

        return apply_filters( 'woo_wishlist_shortcode', $template, $wishlist );
    }
}

if (!function_exists('woo_wishlist_setup_loop')) {
    /**
     * Setup loop for wishlist
     */
    function woo_wishlist_setup_loop($loop) {
        wc_setup_loop(
            array(
                'name' => 'woo_wishlist_loop',
                'is_shortcode' => false,
                'is_search' => false,
                'is_paginated' => true,
                'total' => $loop->found_posts,
                'total_pages' => $loop->max_num_pages,
                'per_page' => $loop->get( 'posts_per_page' ),
                'current_page' => max( 1, $loop->get( 'paged', 1 ) ),
            )
        );
    }
}

// Add pagination and wrapper to wishlist template
add_action('woo_wishlist_before_wishlist', 'storefront_sorting_wrapper', 5);
add_action('woo_wishlist_before_wishlist', 'woocommerce_pagination', 20 );
add_action('woo_wishlist_before_wishlist', 'storefront_sorting_wrapper_close', 90);

if (!function_exists('woo_wishlist_is_wishlist_page')) {
    /**
     * Check if current page is wishlist page
     */
    function woo_wishlist_is_wishlist_page() {
        if ( is_page( get_option('woo_wishlist_page_id') ) ) {
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('woo_wishlist_add_all_products_to_cart_button')) {
    add_action('woo_wishlist_before_wishlist', 'woo_wishlist_add_all_products_to_cart_button', 10);
    /**
     * Add all products to cart button
     */
    function woo_wishlist_add_all_products_to_cart_button() {
        $wishlist = woo_wishlist_get_wishlist();
        $products = implode(",", $wishlist);
        $text = __('Add all to cart', 'woo-wishlist');
        $button = '<a href="#" class="button woo-wishlist-add-all-to-cart-button" data-products="'.$products.'">'.$text.'</a>';
        echo apply_filters( 'woo_wishlist_add_all_products_to_cart_button', $button, $wishlist );
    }
}

if ( !function_exists( 'woo_wishlist_add_all_to_cart' ) ) {
    add_action( 'wp_ajax_woo_wishlist_add_all_to_cart', 'woo_wishlist_add_all_to_cart' );
    add_action( 'wp_ajax_nopriv_woo_wishlist_add_all_to_cart', 'woo_wishlist_add_all_to_cart' );
    /**
     * Add all products to cart
     */
    function woo_wishlist_add_all_to_cart() {
        if ( !isset( $_POST['products'] ) ) {
            exit;
        }

        if ( !isset( $_POST['wishlist_nonce'] ) || !wp_verify_nonce( $_POST['wishlist_nonce'], 'woo_wishlist_nonce' ) ) {
            exit;
        }

        $products = explode(",", $_POST['products']);
        $cart = WC()->cart;

        foreach ($products as $product) {
            $product = explode('-', $product, 2);
            $product_id = $product[0];
            $variation_id = isset($product[1]) ? $product[1] : 0;
            $quantity = 1;
            $cart_item_data = array();
            $variation = array();

            if ( $variation_id ) {
                $variation = array(
                    'variation_id' => $variation_id,
                    'variation' => array(),
                    'variation_description' => '',
                );
            }

            if( ! woo_wishlist_is_product_in_cart($product_id) && ! woo_wishlist_is_product_in_cart($variation_id) ){
                $cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );
            }
        }

        ob_start();

		woocommerce_mini_cart();

		$mini_cart = ob_get_clean();

        // update cart widget
        $fragments = apply_filters(
            'woocommerce_add_to_cart_fragments',
            array(
                'a.cart-contents' => '', // fragments added in `storefront_cart_link_fragment` function
                'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
            )
        );

        $response = array(
            'message' => apply_filters('woo_wishlist_all_products_added_message' , __('Products added to cart', 'woo-wishlist')),
            'fragments' => $fragments,
        );
        echo wp_send_json_success($response);
        

        exit;
    }
}

if ( !function_exists( 'woo_wishlist_is_product_in_cart' ) ) {
    /**
     * Check if product is in cart
     * WC()->cart->find_product_in_cart(WC()->cart->generate_cart_id( $product_id )) is not working for variable products without variation array:
     * array( 'attribute_pa_color' => 'blue', 'attribute_logo' => 'Yes' )
     */
    function woo_wishlist_is_product_in_cart( $product_id = 0 ) {
        $found = false;
        if ( !isset($product_id) || 0 == $product_id )
            return $found;

        foreach( WC()->cart->get_cart() as $cart_item ) {
            if ( $cart_item['data']->get_id() == $product_id ) {
                $found = true;
                break;
            }
        }

        return $found;
    }
}

if ( !function_exists( 'woo_wishlist_empty_wishlist_message' ) ) {
    /**
     * Show notice if wishlist is empty.
     */
    function woo_wishlist_empty_wishlist_message() {
        $notice = wc_print_notice(
            wp_kses_post(
                apply_filters( 'woo_wishlist_empty_wishlist_message', __( 'Your wishlist is empty.', 'woo-wishlist' ) )
            ),
            'notice',
            array(),
            true
        );

        $notice = str_replace( 'class="woocommerce-info"', 'class="cart-empty woocommerce-info"', $notice );

        echo '<div class="wc-empty-cart-message">' . $notice . '</div>';
    }
}