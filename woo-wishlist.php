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

require_once plugin_dir_path( __FILE__ ) . 'inc/wishlist-functions.php';