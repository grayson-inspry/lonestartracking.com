<?php
/**
 * Astra Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra Child
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_ASTRA_CHILD_VERSION', '1.0.0' );



// This simply says "nobody has the authority to resubscribe a subscription ever"
add_filter( 'wcs_can_user_resubscribe_to_subscription', '__return_false' );

/** Disable Ajax Call from WooCommerce */
add_action( 'wp_enqueue_scripts', 'dequeue_woocommerce_cart_fragments', 11 );
function dequeue_woocommerce_cart_fragments() {
	if ( is_front_page() ) {
		wp_dequeue_script( 'wc-cart-fragments' );
	} }


// ** *Enable upload for webp image files.*/
function webp_upload_mimes( $existing_mimes ) {
	$existing_mimes['webp'] = 'image/webp';
	return $existing_mimes;
}
add_filter( 'mime_types', 'webp_upload_mimes' );

// ** Remove total revenue from Store Manager

function remove_dashboard_widgets() {
	if ( current_user_can( 'shop_manager' ) ) {
		// remove WooCommerce Dashboard Status
		remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal' );
	}
}
add_action( 'wp_user_dashboard_setup', 'remove_dashboard_widgets', 20 );
add_action( 'wp_dashboard_setup', 'remove_dashboard_widgets', 20 );

/*
* Remove WooCommerce reports for shop manager
* Remove analytics for shop manager
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




/*Auto Complete all WooCommerce Renewal Subscription orders.*/
add_action( 'woocommerce_subscription_renewal_payment_complete', 'subscription_renewal_payment_complete_callback', 10, 2 );
function subscription_renewal_payment_complete_callback( $subscription, $last_order ) {
	$last_order->update_status( 'completed' );
}


/*Change text on product page */
function change_subscription_product_string( $subscription_string, $product, $include ) {
	if ( $include['sign_up_fee'] ) {
		$subscription_string = str_replace( 'sign-up fee', 'hardware fee', $subscription_string );
	}
	return $subscription_string;
}
add_filter( 'woocommerce_subscriptions_product_price_string', 'change_subscription_product_string', 10, 3 );



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
			$subscription_terms_link = esc_url( get_option( 'subscription_terms_link', '/wp-content/themes/astra-child/eartag.html' ) );
			ob_start();
			?>
			<div class="subscription-checkbox">
				<label style="font-family: 'Gilroy Light';">
					<input type="checkbox" id="terms_accepted" name="terms_accepted" required>
					I understand the <a style="text-decoration:underline;text-underline-position: under;" href="#" onclick="openTermsPopup();return false;">Return Policy of the Satellite Ear Tag.</a><abbr class="required" title="required">*</abbr>
				</label>
			</div>
			<div id="termsPopup" style="display:none; position:fixed; top:20%; left:25%; width:50%; height:60%; background-color:white; border: 2px solid #000; z-index:100; overflow:auto;">
				<span style="position:absolute; top:0px; right:10px; cursor:pointer;" onclick="closeTermsPopup();">&times;</span>
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


function custom_login_logo() {
    ?>
    <style type="text/css">
        .login h1 a {
            background-image: url('https://www.lonestartracking.com/wp-content/uploads/2018/05/LoneStar-Tracking-R.png');
            background-size: contain;
            width: 100%;
            height: 80px;
        }
    </style>
    <?php
}
add_action('login_head', 'custom_login_logo');


add_action('admin_footer_text', function() { echo "Inspry 2"; });
