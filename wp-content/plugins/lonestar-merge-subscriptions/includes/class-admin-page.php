<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LSMS_Admin_Page {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_lsms_search_customers', array( __CLASS__, 'ajax_search_customers' ) );
        add_action( 'wp_ajax_lsms_get_subscriptions', array( __CLASS__, 'ajax_get_subscriptions' ) );
        add_action( 'wp_ajax_lsms_preview_merge', array( __CLASS__, 'ajax_preview_merge' ) );
        add_action( 'wp_ajax_lsms_execute_merge', array( __CLASS__, 'ajax_execute_merge' ) );
    }

    public static function add_menu() {
        add_submenu_page(
            'woocommerce',
            'Merge Subscriptions',
            'Merge Subscriptions',
            'manage_woocommerce',
            'lsms-merge-subscriptions',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function enqueue_assets( $hook ) {
        if ( $hook !== 'woocommerce_page_lsms-merge-subscriptions' ) {
            return;
        }

        wp_enqueue_style(
            'lsms-admin',
            LSMS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            LSMS_VERSION
        );

        wp_enqueue_script(
            'lsms-admin',
            LSMS_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            LSMS_VERSION,
            true
        );

        wp_localize_script( 'lsms-admin', 'lsms', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'lsms_nonce' ),
        ) );
    }

    public static function render_page() {
        ?>
        <div class="wrap lsms-wrap">
            <h1>Merge Subscriptions</h1>

            <!-- Step 1: Search Customer -->
            <div class="lsms-card" id="lsms-step-search">
                <h2>Step 1: Find Customer</h2>
                <div class="lsms-search-row">
                    <input type="text" id="lsms-customer-search" placeholder="Search by name or email..." class="regular-text" />
                    <button type="button" id="lsms-search-btn" class="button button-primary">Search</button>
                </div>
                <div id="lsms-customer-results"></div>
            </div>

            <!-- Step 2: View Subscriptions -->
            <div class="lsms-card lsms-hidden" id="lsms-step-subscriptions">
                <h2>Step 2: Review Subscriptions</h2>
                <div id="lsms-customer-info"></div>
                <table class="wp-list-table widefat fixed striped" id="lsms-sub-table">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" id="lsms-select-all" checked /></th>
                            <th>Sub #</th>
                            <th>Items</th>
                            <th>Period</th>
                            <th>Total</th>
                            <th>Last Paid</th>
                            <th>Paid Through</th>
                            <th>Days Left</th>
                            <th>Credit</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="8" style="text-align:right;"><strong>Total Proration Credit:</strong></td>
                            <td><strong id="lsms-total-credit"></strong></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="lsms-pricing-override">
                    <h3>Pricing Override <small>(optional)</small></h3>
                    <p class="description">Leave blank to use default prices. Enter a custom price OR a discount percentage — not both.</p>
                    <table class="form-table">
                        <tr>
                            <th>Asset Price</th>
                            <td>
                                <input type="number" id="lsms-price-asset" step="0.01" min="0" placeholder="<?php echo esc_attr( LSMS_PRICE_ASSET ); ?>" class="small-text" />
                                <span class="lsms-price-default">Default: $<?php echo number_format( LSMS_PRICE_ASSET, 2 ); ?>/yr</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Vehicle Price</th>
                            <td>
                                <input type="number" id="lsms-price-vehicle" step="0.01" min="0" placeholder="<?php echo esc_attr( LSMS_PRICE_VEHICLE ); ?>" class="small-text" />
                                <span class="lsms-price-default">Default: $<?php echo number_format( LSMS_PRICE_VEHICLE, 2 ); ?>/yr</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Discount %</th>
                            <td>
                                <input type="number" id="lsms-discount-pct" step="0.1" min="0" max="100" placeholder="0" class="small-text" />
                                <span class="lsms-price-default">Applied to default prices (overridden by custom price above)</span>
                            </td>
                        </tr>
                    </table>
                </div>

                <br />
                <button type="button" id="lsms-preview-btn" class="button button-primary">Preview Merge</button>
                <button type="button" id="lsms-back-search" class="button">Back</button>
            </div>

            <!-- Step 3: Preview -->
            <div class="lsms-card lsms-hidden" id="lsms-step-preview">
                <h2>Step 3: Preview Consolidated Subscription</h2>
                <div id="lsms-preview-content"></div>
                <br />
                <div class="lsms-warning">
                    <strong>Warning:</strong> This will create a new subscription and cancel all selected subscriptions. This action cannot be automatically undone.
                </div>
                <br />
                <button type="button" id="lsms-execute-btn" class="button button-primary button-hero">Confirm &amp; Execute Merge</button>
                <button type="button" id="lsms-back-subs" class="button">Back</button>
            </div>

            <!-- Step 4: Result -->
            <div class="lsms-card lsms-hidden" id="lsms-step-result">
                <h2>Step 4: Complete</h2>
                <div id="lsms-result-content"></div>
            </div>

            <div id="lsms-loading" class="lsms-hidden">
                <span class="spinner is-active"></span> Working...
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public static function ajax_search_customers() {
        check_ajax_referer( 'lsms_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
        if ( empty( $search ) ) {
            wp_send_json_error( 'Please enter a search term.' );
        }

        $customers = LSMS_Subscription_Merger::search_customers( $search );
        wp_send_json_success( $customers );
    }

    public static function ajax_get_subscriptions() {
        check_ajax_referer( 'lsms_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $customer_id = absint( $_POST['customer_id'] ?? 0 );
        if ( ! $customer_id ) {
            wp_send_json_error( 'Invalid customer ID.' );
        }

        $subscriptions = LSMS_Subscription_Merger::get_customer_subscriptions( $customer_id );

        // Attach proration data.
        foreach ( $subscriptions as &$sub ) {
            $sub['proration'] = LSMS_Subscription_Merger::calculate_proration( $sub );
        }

        wp_send_json_success( $subscriptions );
    }

    public static function ajax_preview_merge() {
        check_ajax_referer( 'lsms_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $customer_id = absint( $_POST['customer_id'] ?? 0 );
        $sub_ids     = array_map( 'absint', $_POST['subscription_ids'] ?? array() );

        if ( ! $customer_id || empty( $sub_ids ) ) {
            wp_send_json_error( 'Missing customer or subscription IDs.' );
        }

        $price_overrides = self::parse_price_overrides();

        $all_subs = LSMS_Subscription_Merger::get_customer_subscriptions( $customer_id );

        // Filter to only selected subs.
        $selected = array_filter( $all_subs, function ( $s ) use ( $sub_ids ) {
            return in_array( $s['id'], $sub_ids, true );
        } );

        if ( empty( $selected ) ) {
            wp_send_json_error( 'No matching subscriptions found.' );
        }

        $preview = LSMS_Subscription_Merger::build_preview( array_values( $selected ), $price_overrides );
        $stripe  = LSMS_Subscription_Merger::get_stripe_details( $customer_id );

        wp_send_json_success( array(
            'preview' => $preview,
            'stripe'  => $stripe,
        ) );
    }

    public static function ajax_execute_merge() {
        check_ajax_referer( 'lsms_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $customer_id = absint( $_POST['customer_id'] ?? 0 );
        $sub_ids     = array_map( 'absint', $_POST['subscription_ids'] ?? array() );

        if ( ! $customer_id || empty( $sub_ids ) ) {
            wp_send_json_error( 'Missing customer or subscription IDs.' );
        }

        $price_overrides = self::parse_price_overrides();

        $all_subs = LSMS_Subscription_Merger::get_customer_subscriptions( $customer_id );
        $selected = array_values( array_filter( $all_subs, function ( $s ) use ( $sub_ids ) {
            return in_array( $s['id'], $sub_ids, true );
        } ) );

        if ( empty( $selected ) ) {
            wp_send_json_error( 'No matching subscriptions found.' );
        }

        $preview = LSMS_Subscription_Merger::build_preview( $selected, $price_overrides );
        $result  = LSMS_Subscription_Merger::execute_merge( $customer_id, $selected, $preview );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    /**
     * Parse pricing override values from the AJAX request.
     */
    private static function parse_price_overrides() {
        $asset_price   = isset( $_POST['price_asset'] ) && $_POST['price_asset'] !== '' ? floatval( $_POST['price_asset'] ) : null;
        $vehicle_price = isset( $_POST['price_vehicle'] ) && $_POST['price_vehicle'] !== '' ? floatval( $_POST['price_vehicle'] ) : null;
        $discount_pct  = isset( $_POST['discount_pct'] ) && $_POST['discount_pct'] !== '' ? floatval( $_POST['discount_pct'] ) : null;

        return array(
            'asset_price'   => $asset_price,
            'vehicle_price' => $vehicle_price,
            'discount_pct'  => $discount_pct,
        );
    }
}
