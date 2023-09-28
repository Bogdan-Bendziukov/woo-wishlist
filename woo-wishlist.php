<?php
/*
 * Plugin Name:       Woo Wishlist
 * Plugin URI:        https://github.com/Bogdan-Bendziukov/woo-wishlist
 * Description:       Adds a wishlist feature to a Wordpress e-commerce website that uses the WooCommerce plugin and the Storefront theme
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.2
 * Author:            Bogdan Bendziukov
 * Author URI:        https://bogdan.kyiv.ua/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       woo-wishlist
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

define( 'WOO_WISHLIST_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

add_action('admin_notices', 'woo_wishlist_admin_notice');
/**
 * Add notices to wp-admin if no WooCommerce/Storefront activated
 */
function woo_wishlist_admin_notice() {
    $current_theme = wp_get_theme();

    if ( current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' ) ) {
        if ( !class_exists( 'woocommerce' ) ) {
            $woo_plugin_url = 'https://wordpress.org/plugins/woocommerce/';
            echo '<div data-dismissible="notice-woo-wishlist-woocommerce-forever" class="notice notice-warning is-dismissible">
                <p>'.sprintf(__('Please install and activate <a href="%1$s" target="_blank">WooCommerce</a> to use Woo Wishlist plugin.','woo-wishlist'), $woo_plugin_url).'</p>
            </div>';
        }
        if ( $current_theme->get('Name') !== 'Storefront' ) {
            $storefront_theme_url = 'https://wordpress.org/themes/storefront/';
            echo '<div data-dismissible="notice-woo-wishlist-storefront-forever" class="notice notice-warning is-dismissible">
                <p>'.sprintf(__('Please install and activate <a href="%1$s" target="_blank">Storefront</a> theme to use Woo Wishlist plugin.','woo-wishlist'), $storefront_theme_url).'</p>
            </div>';
        }
    }
}

add_action( 'plugins_loaded', 'woo_wishlist_load_textdomain' );
/**
 * Load plugin textdomain.
 */
function woo_wishlist_load_textdomain() {
    load_plugin_textdomain( 'woo-wishlist', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'wp_enqueue_scripts', 'woo_wishlist_enqueue_scripts' );
/**
 * Enqueue scripts and styles
 */
function woo_wishlist_enqueue_scripts() {
    wp_enqueue_style( 'woo-wishlist', plugin_dir_url( __FILE__ ) . 'assets/css/woo-wishlist.css', array(), '1.0.0' );
    wp_enqueue_script( 'woo-wishlist', plugin_dir_url( __FILE__ ) . 'assets/js/woo-wishlist.js', array('jquery'), '1.0.0', true );
    wp_add_inline_style( 'woo-wishlist', woo_wishlist_inline_styles() );

    wp_localize_script( 'woo-wishlist', 'woo_wishlist',
		array(
			'ajax_url' => admin_url('admin-ajax.php'),
            'wishlist_nonce' => wp_create_nonce('woo_wishlist_nonce'),
            'variation_required_message' => apply_filters('woo_wishlist_variation_required_message', __('Please choose product options before adding to wishlist', 'woo-wishlist')),
            'add_to_wishlist_message' => apply_filters('woo_wishlist_add_to_wishlist_message', __('Add to wishlist', 'woo-wishlist')),
            'remove_from_wishlist_message' => apply_filters('woo_wishlist_remove_from_wishlist_message', __('Remove from wishlist', 'woo-wishlist')),
		)
	);
}

/**
 * Add inline styles
 */
function woo_wishlist_inline_styles() {
    if ( !class_exists( 'Storefront_Customizer' ) ) {
        return;
    }

    $Storefront_Customizer = new Storefront_Customizer();
    $storefront_theme_mods = $Storefront_Customizer->get_storefront_theme_mods();
    $text_color = $storefront_theme_mods['text_color'];
    $accent_color = $storefront_theme_mods['accent_color'];

    $styles = '
        .woo-wishlist-button {
            color: '.$text_color.';
        }
        .woo-wishlist-button:hover {
            color: '.$accent_color.';
        }
        .woo-wishlist-button.added {
            color: '.$accent_color.';
        }
        .woo-wishlist-button.added:hover {
            color: '.$accent_color.';
        }
    ';

    return apply_filters( 'woo_wishlist_inline_styles', $styles );
}

register_activation_hook( __FILE__, 'woo_wishlist_activate' );
/**
 * Activation hook
 */
function woo_wishlist_activate() {

    // Create Wishlist page
    wc_create_page(
        sanitize_title_with_dashes( _x( 'woo wishlist', 'page_slug', 'save' ) ),
        'woo_wishlist_page_id',
        __( 'Woo Wishlist', 'woo-wishlist' ),
        '<!-- wp:shortcode -->[woo_wishlist]<!-- /wp:shortcode -->'
    );
}

add_action('template_redirect', 'woo_wishlist_template_redirect');
/**
 * Redirect to Login page if user on wishlist page
 */
function woo_wishlist_template_redirect() {
    if ( woo_wishlist_is_wishlist_page() && !is_user_logged_in() ) {
        $wc_account_page_id = get_option('woocommerce_myaccount_page_id');
        if ( get_permalink( $wc_account_page_id ) ) {
            wp_redirect( get_permalink( $wc_account_page_id ) );
        } else {
            wp_redirect( wp_login_url( get_permalink() ) );
        }
        exit;
    }
}

require_once WOO_WISHLIST_PLUGIN_PATH . 'inc/wishlist-functions.php';