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

        if ( ! is_product() && ! $product->is_type( 'simple' ) ) { // Hide wishlist button for variable products in loop
            return;
        }

        $product_id = $product->get_id();
        $is_variable_product = $product->is_type( 'variable' );
        $is_in_wishlist = $is_variable_product ? false : woo_wishlist_is_in_wishlist( $product_id );
        $title = $is_in_wishlist ? __('Remove from wishlist', 'woo-wishlist') : __('Add to wishlist', 'woo-wishlist');
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
        $product_to_add = $variation_id ? $product_id.'-'.$variation_id : $product_id;
        $user_id = get_current_user_id();
        $is_in_wishlist = woo_wishlist_is_in_wishlist( $product_id, $variation_id );
        $wishlist = woo_wishlist_get_wishlist();
        $result = false;
        $action = '';

        if ( $is_in_wishlist === false ) {
            if ( !empty($wishlist) ) {
                $wishlist[] = $product_to_add;
                $result = update_user_meta( $user_id, 'woo_wishlist', $wishlist );
                $action = 'updated';
            } else {
                $result = add_user_meta( $user_id, 'woo_wishlist', array($product_to_add) );
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

        $user_id = get_current_user_id();
        $wishlist = get_user_meta( $user_id, 'woo_wishlist', true );
        
        $wishlist = is_array( $wishlist ) ? $wishlist : array();

        $product_to_check = $variation_id ? $product_id.'-'.$variation_id : $product_id;

        if ( in_array( $product_to_check, $wishlist ) ) {
            return true;
        } else {
            return false;
        }
    }
}

if ( !function_exists( 'woo_wishlist_get_wishlist' ) ) {
    /**
     * Get wishlist for current user
     */
    function woo_wishlist_get_wishlist() {
        if ( !is_user_logged_in() ) {
            return false;
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
            return false;
        }

        $user_id = get_current_user_id();
        $wishlist = get_user_meta( $user_id, 'woo_wishlist', true );
        
        $wishlist = is_array( $wishlist ) ? $wishlist : array();

        if ( empty($wishlist) ) {
            return false;
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

        if ( $count > 0 ) {
            $wishlist_count_text = '<span class="woo-wishlist-count">'.$count.'</span>';
        }
        
        $button = '<a href="#" title="'.$title.'" class="woo-wishlist-link">'.$icon.$wishlist_count_text.'</a>';
        
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
        $items['woo_wishlist'] = __('Wishlist', 'woo-wishlist');
        return $items;
    }
}

if (!function_exists('woo_wishlist_account_menu_item_endpoint')) {
    add_action( 'init', 'woo_wishlist_account_menu_item_endpoint' );
    /**
     * Add wishlist endpoint to my account menu
     */
    function woo_wishlist_account_menu_item_endpoint() {
        add_rewrite_endpoint( 'woo_wishlist', EP_ROOT | EP_PAGES );
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
        
        $template = locate_template( 'woo-wishlist/wishlist.php', false, false, $wishlist );

        if ( !$template ) {
            $template = WOO_WISHLIST_PLUGIN_PATH . 'templates/wishlist.php';
        }

        $columns      = get_option( 'woocommerce_catalog_columns', 4 );
        $rows         = absint( get_option( 'woocommerce_catalog_rows', 4 ) );
        $per_page     = apply_filters( 'loop_shop_per_page', $columns * $rows );
        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'post__in' => $wishlist,
            'paged' => $paged,
        );

        $args = apply_filters( 'woo_wishlist_wishlist_query_args', $args );

        ob_start();
        include $template;
        $template = ob_get_clean();

        return apply_filters( 'woo_wishlist_shortcode', $template, $wishlist );
    }
}

if (!function_exists('woo_wishlist_pagination')) {
    add_action( 'woo_wishlist_before_wishlist', 'woo_wishlist_pagination', 10 );
    /**
     * Add pagination to wishlist page
     */
    function woo_wishlist_pagination($loop) {
        $total_pages = $loop->max_num_pages;
        
        if ( $total_pages <= 1 ) {
            return;
        }

        $args = array(
            'base'         => esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) ) ) ),
            'format'       => '',
            'total'        => $total_pages,
            'current'      => max(1, get_query_var('paged')),
            'show_all'     => false,
            'end_size'     => 1,
            'mid_size'     => 2,
            'prev_next'    => true,
            'prev_text'    => __('&laquo; Previous', 'woo-wishlist'),
            'next_text'    => __('Next &raquo;', 'woo-wishlist'),
            'type'         => 'plain',
            'add_args'     => false,
            'add_fragment' => '',
        );
        $args = apply_filters( 'woo_wishlist_pagination_args', $args );

        wc_get_template( 'loop/pagination.php', $args );
    }
}

add_action('woo_wishlist_before_wishlist', 'storefront_sorting_wrapper', 5);
add_action('woo_wishlist_before_wishlist', 'storefront_sorting_wrapper_close', 90);