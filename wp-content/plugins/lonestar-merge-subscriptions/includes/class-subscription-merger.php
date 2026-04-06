<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LSMS_Subscription_Merger {

    /**
     * Filter callback to suppress AutomateWoo workflows during merge cancellations.
     */
    public static function suppress_automatewoo( $should_run, $workflow ) {
        return false;
    }

    /**
     * Search customers by name or email.
     */
    public static function search_customers( $search ) {
        $args = array(
            'search'         => '*' . esc_attr( $search ) . '*',
            'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'display_name' ),
            'number'         => 20,
            'orderby'        => 'display_name',
        );

        $query = new WP_User_Query( $args );
        $results = array();

        foreach ( $query->get_results() as $user ) {
            $results[] = array(
                'id'    => $user->ID,
                'name'  => $user->first_name . ' ' . $user->last_name,
                'email' => $user->user_email,
            );
        }

        return $results;
    }

    /**
     * Get all active subscriptions for a customer.
     */
    public static function get_customer_subscriptions( $customer_id ) {
        $subscriptions = wcs_get_subscriptions( array(
            'customer_id'            => $customer_id,
            'subscription_status'    => 'active',
            'subscriptions_per_page' => -1,
        ) );

        $results = array();

        foreach ( $subscriptions as $sub ) {
            $last_paid = $sub->get_date( 'last_order_date_paid' );
            if ( ! $last_paid ) {
                $last_paid = $sub->get_date( 'date_created' );
            }

            $items = array();
            foreach ( $sub->get_items() as $item ) {
                $product    = $item->get_product();
                $variation_id = $item->get_variation_id();
                $product_id   = $item->get_product_id();

                // Classify as asset or vehicle.
                $type = self::classify_item( $product_id, $variation_id, $product );

                $meta_data = array();
                foreach ( $item->get_meta_data() as $meta ) {
                    $data = $meta->get_data();
                    if ( strpos( $data['key'], '_' ) !== 0 ) {
                        $meta_data[] = array(
                            'key'   => $data['key'],
                            'value' => $data['value'],
                        );
                    }
                }

                $items[] = array(
                    'name'         => $item->get_name(),
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'quantity'     => $item->get_quantity(),
                    'total'        => (float) $item->get_total(),
                    'unit_price'   => $item->get_quantity() > 0 ? round( (float) $item->get_total() / $item->get_quantity(), 2 ) : 0,
                    'type'         => $type,
                    'meta_data'    => $meta_data,
                    'sku'          => $product ? $product->get_sku() : '',
                );
            }

            $results[] = array(
                'id'               => $sub->get_id(),
                'status'           => $sub->get_status(),
                'billing_period'   => $sub->get_billing_period(),
                'billing_interval' => $sub->get_billing_interval(),
                'total'            => (float) $sub->get_total(),
                'last_paid'        => $last_paid,
                'start_date'       => $sub->get_date( 'start' ),
                'next_payment'     => $sub->get_date( 'next_payment' ),
                'items'            => $items,
                'payment_method'   => $sub->get_payment_method(),
            );
        }

        return $results;
    }

    /**
     * Classify a line item as 'asset' or 'vehicle'.
     */
    public static function classify_item( $product_id, $variation_id, $product = null ) {
        // Known vehicle variations/products.
        if ( $variation_id == LSMS_VARIATION_VEHICLE ) {
            return 'vehicle';
        }

        if ( $variation_id == LSMS_VARIATION_ASSET ) {
            return 'asset';
        }

        // Check SKU and name for vehicle keywords.
        if ( $product ) {
            $sku  = strtolower( $product->get_sku() );
            $name = strtolower( $product->get_name() );

            if ( strpos( $sku, 'vehicle' ) !== false || strpos( $name, 'vehicle' ) !== false
                || strpos( $name, 'scorpion' ) !== false || strpos( $name, 'discovery' ) !== false ) {
                return 'vehicle';
            }
        }

        // Default to asset.
        return 'asset';
    }

    /**
     * Calculate proration for a subscription.
     *
     * @return array With keys: paid_through, days_total, days_remaining, credit
     */
    public static function calculate_proration( $subscription, $as_of_date = null ) {
        if ( ! $as_of_date ) {
            $as_of_date = current_time( 'timestamp' );
        } elseif ( is_string( $as_of_date ) ) {
            $as_of_date = strtotime( $as_of_date );
        }

        $last_paid = strtotime( $subscription['last_paid'] );

        if ( $subscription['billing_period'] === 'year' ) {
            $interval    = (int) $subscription['billing_interval'];
            $paid_through = strtotime( "+{$interval} year", $last_paid );
        } elseif ( $subscription['billing_period'] === 'month' ) {
            $interval    = (int) $subscription['billing_interval'];
            $paid_through = strtotime( "+{$interval} month", $last_paid );
        } elseif ( $subscription['billing_period'] === 'week' ) {
            $interval    = (int) $subscription['billing_interval'];
            $paid_through = strtotime( "+{$interval} week", $last_paid );
        } else {
            $paid_through = strtotime( '+1 year', $last_paid );
        }

        $total_days     = ( $paid_through - $last_paid ) / DAY_IN_SECONDS;
        $remaining_days = max( 0, ( $paid_through - $as_of_date ) / DAY_IN_SECONDS );
        $credit         = ( $remaining_days / $total_days ) * $subscription['total'];

        return array(
            'paid_through'   => date( 'Y-m-d', $paid_through ),
            'days_total'     => round( $total_days ),
            'days_remaining' => round( $remaining_days ),
            'credit'         => round( $credit, 2 ),
        );
    }

    /**
     * Build a consolidated subscription preview.
     *
     * @return array With keys: line_items, total, proration_credit, renewal_date, daily_rate, prepaid_days
     */
    public static function build_preview( $subscriptions, $price_overrides = array() ) {
        $asset_qty    = 0;
        $vehicle_qty  = 0;
        $total_credit = 0;
        $asset_meta   = array();
        $vehicle_meta = array();

        foreach ( $subscriptions as $sub ) {
            $proration = self::calculate_proration( $sub );
            $total_credit += $proration['credit'];

            foreach ( $sub['items'] as $item ) {
                if ( $item['type'] === 'vehicle' ) {
                    $vehicle_qty += $item['quantity'];
                    foreach ( $item['meta_data'] as $meta ) {
                        $vehicle_meta[] = array(
                            'key'     => $meta['key'],
                            'value'   => $meta['value'],
                            'from_sub' => $sub['id'],
                        );
                    }
                } else {
                    $asset_qty += $item['quantity'];
                    foreach ( $item['meta_data'] as $meta ) {
                        $asset_meta[] = array(
                            'key'     => $meta['key'],
                            'value'   => $meta['value'],
                            'from_sub' => $sub['id'],
                        );
                    }
                }
            }
        }

        // Determine final prices: custom price > discount % > default.
        $discount_pct = isset( $price_overrides['discount_pct'] ) && $price_overrides['discount_pct'] > 0
            ? $price_overrides['discount_pct'] : 0;

        if ( ! empty( $price_overrides['asset_price'] ) && $price_overrides['asset_price'] > 0 ) {
            $asset_price = round( $price_overrides['asset_price'], 2 );
        } elseif ( $discount_pct > 0 ) {
            $asset_price = round( LSMS_PRICE_ASSET * ( 1 - $discount_pct / 100 ), 2 );
        } else {
            $asset_price = LSMS_PRICE_ASSET;
        }

        if ( ! empty( $price_overrides['vehicle_price'] ) && $price_overrides['vehicle_price'] > 0 ) {
            $vehicle_price = round( $price_overrides['vehicle_price'], 2 );
        } elseif ( $discount_pct > 0 ) {
            $vehicle_price = round( LSMS_PRICE_VEHICLE * ( 1 - $discount_pct / 100 ), 2 );
        } else {
            $vehicle_price = LSMS_PRICE_VEHICLE;
        }

        $line_items = array();

        if ( $asset_qty > 0 ) {
            $line_items[] = array(
                'type'         => 'asset',
                'name'         => 'Annual Subscription Plans - Asset (Oyster, Yabby, Titan, GB130, Barra)',
                'product_id'   => LSMS_PRODUCT_ANNUAL,
                'variation_id' => LSMS_VARIATION_ASSET,
                'quantity'     => $asset_qty,
                'unit_price'   => $asset_price,
                'line_total'   => round( $asset_qty * $asset_price, 2 ),
                'meta_data'    => $asset_meta,
            );
        }

        if ( $vehicle_qty > 0 ) {
            $line_items[] = array(
                'type'         => 'vehicle',
                'name'         => 'Annual Subscription Plans - Vehicle (DiscoveryLTE, ScorpionLTE)',
                'product_id'   => LSMS_PRODUCT_ANNUAL,
                'variation_id' => LSMS_VARIATION_VEHICLE,
                'quantity'     => $vehicle_qty,
                'unit_price'   => $vehicle_price,
                'line_total'   => round( $vehicle_qty * $vehicle_price, 2 ),
                'meta_data'    => $vehicle_meta,
            );
        }

        $annual_total = 0;
        foreach ( $line_items as $item ) {
            $annual_total += $item['line_total'];
        }

        $daily_rate   = $annual_total / 365;
        $prepaid_days = $daily_rate > 0 ? round( $total_credit / $daily_rate ) : 0;
        $renewal_date = date( 'Y-m-d', strtotime( "+{$prepaid_days} days" ) );

        return array(
            'line_items'       => $line_items,
            'annual_total'     => round( $annual_total, 2 ),
            'proration_credit' => round( $total_credit, 2 ),
            'daily_rate'       => round( $daily_rate, 2 ),
            'prepaid_days'     => $prepaid_days,
            'renewal_date'     => $renewal_date,
            'asset_qty'        => $asset_qty,
            'vehicle_qty'      => $vehicle_qty,
        );
    }

    /**
     * Get Stripe payment details from a customer's existing subscription.
     */
    public static function get_stripe_details( $customer_id ) {
        $subscriptions = wcs_get_subscriptions( array(
            'customer_id'            => $customer_id,
            'subscription_status'    => 'active',
            'subscriptions_per_page' => 1,
        ) );

        if ( empty( $subscriptions ) ) {
            return null;
        }

        $sub = reset( $subscriptions );
        $stripe_customer_id = $sub->get_meta( '_stripe_customer_id' );
        $stripe_source_id   = $sub->get_meta( '_stripe_source_id' );

        return array(
            'payment_method'       => $sub->get_payment_method(),
            'payment_method_title' => $sub->get_payment_method_title(),
            'stripe_customer_id'   => $stripe_customer_id,
            'stripe_source_id'     => $stripe_source_id,
        );
    }

    /**
     * Execute the merge: create new subscription and cancel old ones.
     *
     * @return array|WP_Error New subscription ID or error.
     */
    public static function execute_merge( $customer_id, $subscriptions, $preview ) {
        $stripe = self::get_stripe_details( $customer_id );
        if ( ! $stripe ) {
            return new WP_Error( 'no_stripe', 'Could not retrieve Stripe payment details for this customer.' );
        }

        // Get customer billing/shipping from first subscription.
        $first_sub = wcs_get_subscription( $subscriptions[0]['id'] );
        if ( ! $first_sub ) {
            return new WP_Error( 'no_sub', 'Could not load the first subscription.' );
        }

        $billing  = $first_sub->get_address( 'billing' );
        $shipping = $first_sub->get_address( 'shipping' );

        // Create the new subscription.
        $new_sub = wcs_create_subscription( array(
            'customer_id'      => $customer_id,
            'billing_period'   => 'year',
            'billing_interval' => 1,
            'start_date'       => gmdate( 'Y-m-d H:i:s' ),
        ) );

        if ( is_wp_error( $new_sub ) ) {
            return $new_sub;
        }

        // Set billing and shipping.
        $new_sub->set_address( $billing, 'billing' );
        $new_sub->set_address( $shipping, 'shipping' );

        // Set payment method.
        $new_sub->set_payment_method( $stripe['payment_method'] );
        $new_sub->set_payment_method_title( $stripe['payment_method_title'] );
        $new_sub->update_meta_data( '_stripe_customer_id', $stripe['stripe_customer_id'] );
        $new_sub->update_meta_data( '_stripe_source_id', $stripe['stripe_source_id'] );

        // Add line items.
        foreach ( $preview['line_items'] as $line ) {
            $product = wc_get_product( $line['variation_id'] ? $line['variation_id'] : $line['product_id'] );
            if ( ! $product ) {
                continue;
            }

            $item = new WC_Order_Item_Product();
            $item->set_product( $product );
            $item->set_quantity( $line['quantity'] );
            $item->set_subtotal( $line['line_total'] );
            $item->set_total( $line['line_total'] );

            // Copy metadata (IMEI numbers, subscription-type, etc.) from old subs.
            if ( ! empty( $line['meta_data'] ) ) {
                foreach ( $line['meta_data'] as $meta ) {
                    $item->add_meta_data( $meta['key'], $meta['value'], false );
                }
            }

            $new_sub->add_item( $item );
        }

        // Recalculate totals.
        $new_sub->calculate_totals();

        // Set next payment date based on proration.
        $new_sub->update_dates( array(
            'next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( $preview['renewal_date'] ) ),
        ) );

        // Add a note documenting the merge.
        $old_ids = array_map( function ( $s ) { return '#' . $s['id']; }, $subscriptions );
        $new_sub->add_order_note( sprintf(
            'Merged from %d subscriptions (%s). Proration credit: $%s. Prepaid days: %d.',
            count( $subscriptions ),
            implode( ', ', $old_ids ),
            number_format( $preview['proration_credit'], 2 ),
            $preview['prepaid_days']
        ) );

        // Store merge metadata for audit.
        $new_sub->update_meta_data( '_lsms_merged_from', wp_json_encode( array_column( $subscriptions, 'id' ) ) );
        $new_sub->update_meta_data( '_lsms_proration_credit', $preview['proration_credit'] );
        $new_sub->update_meta_data( '_lsms_merge_date', gmdate( 'Y-m-d H:i:s' ) );

        $new_sub->set_status( 'active' );
        $new_sub->save();

        // Suppress AutomateWoo workflows during cancellation so the customer
        // does not receive a flood of cancellation emails.
        $automatewoo_suppressed = false;
        if ( class_exists( 'AutomateWoo\Workflows' ) || class_exists( 'AutomateWoo\Workflow_Manager' ) ) {
            add_filter( 'automatewoo/workflow/should_run', array( __CLASS__, 'suppress_automatewoo' ), 10, 2 );
            $automatewoo_suppressed = true;
        }

        // Also suppress WooCommerce Subscriptions' built-in cancellation emails.
        remove_action( 'cancelled_subscription', array( 'WC_Subscriptions_Email', 'send_cancelled_email' ), 10 );

        // Cancel old subscriptions.
        $cancelled = array();
        foreach ( $subscriptions as $sub_data ) {
            $old_sub = wcs_get_subscription( $sub_data['id'] );
            if ( $old_sub ) {
                // Tag the sub so our filter knows to suppress it.
                $old_sub->update_meta_data( '_lsms_merge_cancellation', 'yes' );
                $old_sub->save_meta_data();

                $old_sub->add_order_note( sprintf(
                    'Cancelled and merged into subscription #%d by LoneStar Merge Subscriptions plugin.',
                    $new_sub->get_id()
                ) );
                $old_sub->update_status( 'cancelled' );
                $cancelled[] = $sub_data['id'];
            }
        }

        // Re-enable AutomateWoo workflows.
        if ( $automatewoo_suppressed ) {
            remove_filter( 'automatewoo/workflow/should_run', array( __CLASS__, 'suppress_automatewoo' ), 10 );
        }

        // Re-enable WooCommerce Subscriptions cancellation emails.
        add_action( 'cancelled_subscription', array( 'WC_Subscriptions_Email', 'send_cancelled_email' ), 10 );

        return array(
            'new_subscription_id' => $new_sub->get_id(),
            'cancelled'           => $cancelled,
        );
    }
}
