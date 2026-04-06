<?php
/**
 * Setup Child Theme Styles
 */
function inspry_child_theme_enqueue_styles() {
	wp_enqueue_style( 'inspry_child_theme-style', get_stylesheet_directory_uri() . '/style.css', false, '1.0' );
}
add_action( 'wp_enqueue_scripts', 'inspry_child_theme_enqueue_styles', 20 );

/**
 * Setup Child Theme Defaults
 *
 * @param array $defaults registered option defaults with kadence theme.
 * @return array
 */
function inspry_child_theme_change_option_defaults( $defaults ) {
	$new_defaults = '{"custom_logo":29293}';
	$new_defaults = json_decode( $new_defaults, true );
	return wp_parse_args( $new_defaults, $defaults );
}
add_filter( 'kadence_theme_options_defaults', 'inspry_child_theme_change_option_defaults', 20 );

/**
 * Enable the Block Editor (Gutenberg) for WooCommerce Products
 */
add_filter( 'use_block_editor_for_post_type', 'enable_gutenberg_for_products', 10, 2 );
function enable_gutenberg_for_products( $can_edit, $post_type ) {
    if ( $post_type === 'product' ) {
        return true;
    }
    return $can_edit;
}

/**
 * Enable REST API for Products (Required for Gutenberg to load)
 */
add_filter( 'woocommerce_register_post_type_product', 'enable_products_rest_api' );
function enable_products_rest_api( $args ) {
    $args['show_in_rest'] = true;
    return $args;
}

/**
 * Code moved from old child theme below
 */
/**
 * Cannot re-subscribe to exisiting subscriptions
 */
add_filter( 'wcs_can_user_resubscribe_to_subscription', '__return_false' );

/**
 * Enable webp image support
 */
function webp_upload_mimes( $existing_mimes ) {
	$existing_mimes['webp'] = 'image/webp';
	return $existing_mimes;
}
add_filter( 'mime_types', 'webp_upload_mimes' );

/**
 * Auto complete all WooCommerce renewal subscription orders
 */
add_action( 'woocommerce_subscription_renewal_payment_complete', 'subscription_renewal_payment_complete_callback', 10, 2 );
function subscription_renewal_payment_complete_callback( $subscription, $last_order ) {
	$last_order->update_status( 'completed' );
}

/**
 * Change text for backorder products
 */
function backorder_text( $availability ) {
	foreach ( $availability as $i ) {
		$availability = str_replace( 'Available on backorder', 'Out of Stock - Backordered', $availability );
		$availability = str_replace( 'In stock (can be backordered)', 'In Stock', $availability );
		$availability = str_replace( 'This product is currently out of stock and unavailable', 'Call for availability', $availability );
	}
	return $availability;
}
add_filter( 'woocommerce_get_availability', 'backorder_text' );

/**
 * Change "sign up fee" to "hardware fee"
 */
function change_subscription_product_string( $subscription_string, $product, $include ) {
	if ( $include['sign_up_fee'] ) {
		$subscription_string = str_replace( 'sign-up fee', 'hardware fee', $subscription_string );
	}
	return $subscription_string;
}
add_filter( 'woocommerce_subscriptions_product_price_string', 'change_subscription_product_string', 10, 3 );

/**
 * Change billing phone field label and mask
 */
add_filter( 'woocommerce_checkout_fields', 'custom_override_checkout_fields' );
function custom_override_checkout_fields( $fields ) {
	$fields['billing']['billing_phone']['label']             = 'Phone (5555551212)'; // replace 'Your new label' with the actual label you want
	$fields['billing']['billing_phone']['custom_attributes'] = array( 'pattern' => '\d{9}' );
	return $fields;
}

/**
 * Ear Tag Accept Checkbox
 * Custom plugin moved into child theme functions.php on 3/4/25
 */
function subscription_checkbox_shortcode() {
	// Replace 123 with the actual ID of the product you want to target
	$target_product_id = 33021;

	if ( class_exists( 'WC_Product' ) && is_a( $GLOBALS['product'], 'WC_Product' ) ) {
		$current_product_id = $GLOBALS['product']->get_id();

		// Check if current product is the specific product
		if ( $current_product_id == $target_product_id ) {
			$subscription_terms_link = esc_url( get_option( 'subscription_terms_link', '/wp-content/themes/inspry-child-theme/eartag.html' ) );
			ob_start();
			?>
			<div class="subscription-checkbox">
				<label>
					<input type="checkbox" id="terms_accepted" name="terms_accepted" required>
					I understand the <a style="text-decoration:underline;text-underline-position: under;" href="#" onclick="openTermsPopup();return false;">Return Policy of the Satellite Ear Tag.</a><abbr class="required" title="required">*</abbr>
				</label>
			</div>
			<div id="termsPopup" style="display:none; position:fixed; top:20%; left:25%; width:50%; height:60%; background-color:white; border: 2px solid #000; z-index:100; overflow:auto;">
				<span style="font-size: 48px; position:absolute; top:0px; right:10px; cursor:pointer;" onclick="closeTermsPopup();">&times;</span>
				<iframe src="<?php echo $subscription_terms_link; ?>" style="width:100%; height:100%;"></iframe>
			</div>
			<script>
			function openTermsPopup() {
				document.getElementById('termsPopup').style.display = 'block';
			}

			function closeTermsPopup() {
				document.getElementById('termsPopup').style.display = 'none';
			}

			// Close the popup when clicking outside of it
			window.onclick = function(event) {
				var modal = document.getElementById('termsPopup');
				if (event.target == modal) {
					closeTermsPopup();
				}
			}

			document.addEventListener('DOMContentLoaded', function () {
				var checkbox = document.getElementById('terms_accepted');
				var addToCartButton = document.querySelector('.single_add_to_cart_button');
				addToCartButton.disabled = true; // Initially disable
				addToCartButton.title = 'Kindly check the terms of subscription';
				checkbox.addEventListener('change', function () {
					addToCartButton.disabled = !checkbox.checked;
					addToCartButton.title = checkbox.checked ? '' : 'Kindly check the terms of subscription';
				});
			});
			</script>
			<?php
			return ob_get_clean();
		}
	}

	return ''; // Non-targeted product, non-subscription product, or WooCommerce not active
}
add_shortcode( 'subscription_checkbox', 'subscription_checkbox_shortcode' );


/**
 * Add custom class to the <body> tag based on ACF Checkbox
 */
add_filter( 'body_class', 'inspry_add_acf_body_class' );
function inspry_add_acf_body_class( $classes ) {

    // 1. Check if we are on a single page/post
    if ( is_singular() ) {
        
        // 2. Get the value of your ACF field (Key: gradient_background)
        // Note: ACF True/False fields usually return 1 (true) or 0 (false)
        $has_gradient = get_field( 'gradient_background' );

        // 3. If the checkbox is checked, add our class to the array
        if ( $has_gradient ) {
            $classes[] = 'gradient-background';
        }
    }

    // 4. Return the updated list of classes to WordPress
    return $classes;
}


