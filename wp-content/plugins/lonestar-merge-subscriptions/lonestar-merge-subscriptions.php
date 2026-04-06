<?php
/**
 * Plugin Name: LoneStar Merge Subscriptions
 * Description: Merge multiple WooCommerce subscriptions for a customer into a single annual subscription with prorated credits.
 * Version: 1.3.1
 * Author: LoneStar Tracking
 * Requires Plugins: woocommerce, woocommerce-subscriptions
 * Text Domain: lonestar-merge-subs
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LSMS_VERSION', '1.3.1' );
define( 'LSMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LSMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Product/variation IDs for subscription types.
define( 'LSMS_PRODUCT_ANNUAL', 7117 );
define( 'LSMS_VARIATION_ASSET', 7118 );
define( 'LSMS_VARIATION_VEHICLE', 7119 );

// Prices.
define( 'LSMS_PRICE_ASSET', 149.95 );
define( 'LSMS_PRICE_VEHICLE', 199.95 );

require_once LSMS_PLUGIN_DIR . 'includes/class-subscription-merger.php';
require_once LSMS_PLUGIN_DIR . 'includes/class-admin-page.php';

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WC_Subscriptions' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>LoneStar Merge Subscriptions</strong> requires WooCommerce Subscriptions to be active.</p></div>';
        } );
        return;
    }

    LSMS_Admin_Page::init();
} );
