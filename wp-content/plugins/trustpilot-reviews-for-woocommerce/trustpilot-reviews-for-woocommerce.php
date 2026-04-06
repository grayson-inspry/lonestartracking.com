<?php
/**
 * Plugin Name: Trustpilot Reviews for WooCommerce
 * Plugin URI: https://yourwebsite.com
 * Description: Display your Trustpilot reviews on your WooCommerce store using shortcodes.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: trustpilot-reviews-wc
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Trustpilot_Reviews_WC {

    private static $instance = null;
    private $option_name = 'trustpilot_reviews_wc_settings';
    private $debug_messages = array();

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_shortcode('trustpilot_reviews', array($this, 'render_reviews_shortcode'));
        add_shortcode('trustpilot_widget', array($this, 'render_widget_shortcode'));
        add_action('wp_ajax_refresh_trustpilot_cache', array($this, 'ajax_refresh_cache'));
        add_action('wp_ajax_test_trustpilot_connection', array($this, 'ajax_test_connection'));
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'trustpilot-reviews-wc',
            plugin_dir_url(__FILE__) . 'assets/css/trustpilot-reviews.css',
            array(),
            '1.0.0'
        );
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            __('Trustpilot Reviews Settings', 'trustpilot-reviews-wc'),
            __('Trustpilot Reviews', 'trustpilot-reviews-wc'),
            'manage_options',
            'trustpilot-reviews-wc',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));

        add_settings_section(
            'trustpilot_reviews_wc_main',
            __('Trustpilot Configuration', 'trustpilot-reviews-wc'),
            array($this, 'section_callback'),
            'trustpilot-reviews-wc'
        );

        add_settings_field(
            'business_unit_id',
            __('Business Unit ID', 'trustpilot-reviews-wc'),
            array($this, 'render_business_unit_field'),
            'trustpilot-reviews-wc',
            'trustpilot_reviews_wc_main'
        );

        add_settings_field(
            'domain',
            __('Your Domain', 'trustpilot-reviews-wc'),
            array($this, 'render_domain_field'),
            'trustpilot-reviews-wc',
            'trustpilot_reviews_wc_main'
        );

        add_settings_field(
            'api_key',
            __('API Key (Optional)', 'trustpilot-reviews-wc'),
            array($this, 'render_api_key_field'),
            'trustpilot-reviews-wc',
            'trustpilot_reviews_wc_main'
        );

        add_settings_field(
            'cache_duration',
            __('Cache Duration (hours)', 'trustpilot-reviews-wc'),
            array($this, 'render_cache_duration_field'),
            'trustpilot-reviews-wc',
            'trustpilot_reviews_wc_main'
        );

        add_settings_field(
            'reviews_count',
            __('Number of Reviews to Display', 'trustpilot-reviews-wc'),
            array($this, 'render_reviews_count_field'),
            'trustpilot-reviews-wc',
            'trustpilot_reviews_wc_main'
        );

        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'trustpilot-reviews-wc'),
            array($this, 'render_debug_mode_field'),
            'trustpilot-reviews-wc',
            'trustpilot_reviews_wc_main'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['business_unit_id'])) {
            $sanitized['business_unit_id'] = sanitize_text_field($input['business_unit_id']);
        }
        
        if (isset($input['domain'])) {
            $sanitized['domain'] = sanitize_text_field(str_replace(array('http://', 'https://', 'www.'), '', $input['domain']));
        }
        
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        if (isset($input['cache_duration'])) {
            $sanitized['cache_duration'] = absint($input['cache_duration']);
        }
        
        if (isset($input['reviews_count'])) {
            $sanitized['reviews_count'] = absint($input['reviews_count']);
        }

        if (isset($input['debug_mode'])) {
            $sanitized['debug_mode'] = (bool) $input['debug_mode'];
        }

        // Clear cache when settings are updated
        delete_transient('trustpilot_reviews_cache');
        
        return $sanitized;
    }

    /**
     * Section callback
     */
    public function section_callback() {
        echo '<p>' . esc_html__('Configure your Trustpilot integration settings below. You can find your Business Unit ID in your Trustpilot Business account under Integrations.', 'trustpilot-reviews-wc') . '</p>';
        echo '<p><strong>' . esc_html__('Available Shortcodes:', 'trustpilot-reviews-wc') . '</strong></p>';
        echo '<ul>';
        echo '<li><code>[trustpilot_reviews]</code> - ' . esc_html__('Display reviews in a custom styled format', 'trustpilot-reviews-wc') . '</li>';
        echo '<li><code>[trustpilot_widget]</code> - ' . esc_html__('Display official Trustpilot TrustBox widget', 'trustpilot-reviews-wc') . '</li>';
        echo '</ul>';
    }

    /**
     * Render Business Unit ID field
     */
    public function render_business_unit_field() {
        $options = get_option($this->option_name);
        $value = isset($options['business_unit_id']) ? $options['business_unit_id'] : '';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[business_unit_id]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Find this in your Trustpilot Business account (e.g., 5c12345678901234567890ab)', 'trustpilot-reviews-wc') . '</p>';
    }

    /**
     * Render Domain field
     */
    public function render_domain_field() {
        $options = get_option($this->option_name);
        $value = isset($options['domain']) ? $options['domain'] : '';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[domain]" value="' . esc_attr($value) . '" class="regular-text" placeholder="example.com" />';
        echo '<p class="description">' . esc_html__('Your business domain as registered on Trustpilot (without http:// or www.). This is the RECOMMENDED method.', 'trustpilot-reviews-wc') . '</p>';
    }

    /**
     * Render API Key field
     */
    public function render_api_key_field() {
        $options = get_option($this->option_name);
        $value = isset($options['api_key']) ? $options['api_key'] : '';
        echo '<input type="password" name="' . esc_attr($this->option_name) . '[api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Optional: Your Trustpilot API key for authenticated access. Get this from your Trustpilot Business account under Integrations → API.', 'trustpilot-reviews-wc') . '</p>';
    }

    /**
     * Render Cache Duration field
     */
    public function render_cache_duration_field() {
        $options = get_option($this->option_name);
        $value = isset($options['cache_duration']) ? $options['cache_duration'] : 6;
        echo '<input type="number" name="' . esc_attr($this->option_name) . '[cache_duration]" value="' . esc_attr($value) . '" min="1" max="72" class="small-text" />';
        echo '<p class="description">' . esc_html__('How long to cache reviews (1-72 hours). Recommended: 6 hours.', 'trustpilot-reviews-wc') . '</p>';
    }

    /**
     * Render Reviews Count field
     */
    public function render_reviews_count_field() {
        $options = get_option($this->option_name);
        $value = isset($options['reviews_count']) ? $options['reviews_count'] : 5;
        echo '<input type="number" name="' . esc_attr($this->option_name) . '[reviews_count]" value="' . esc_attr($value) . '" min="1" max="20" class="small-text" />';
        echo '<p class="description">' . esc_html__('Number of reviews to fetch and display (1-20).', 'trustpilot-reviews-wc') . '</p>';
    }

    /**
     * Render Debug Mode field
     */
    public function render_debug_mode_field() {
        $options = get_option($this->option_name);
        $value = isset($options['debug_mode']) ? $options['debug_mode'] : false;
        echo '<label><input type="checkbox" name="' . esc_attr($this->option_name) . '[debug_mode]" value="1" ' . checked(1, $value, false) . ' />';
        echo ' ' . esc_html__('Enable debug mode (shows detailed error information)', 'trustpilot-reviews-wc') . '</label>';
        echo '<p class="description">' . esc_html__('Only enable this for troubleshooting. Disable in production.', 'trustpilot-reviews-wc') . '</p>';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_name);
                do_settings_sections('trustpilot-reviews-wc');
                submit_button(__('Save Settings', 'trustpilot-reviews-wc'));
                ?>
            </form>

            <hr>
            
            <h2><?php esc_html_e('Test Connection', 'trustpilot-reviews-wc'); ?></h2>
            <p><?php esc_html_e('Test your Trustpilot API connection to diagnose issues.', 'trustpilot-reviews-wc'); ?></p>
            <button type="button" class="button button-primary" id="test-trustpilot-connection">
                <?php esc_html_e('Test Connection', 'trustpilot-reviews-wc'); ?>
            </button>
            <div id="connection-test-results" style="margin-top:15px;"></div>

            <script>
            jQuery(document).ready(function($) {
                $('#test-trustpilot-connection').on('click', function() {
                    var $button = $(this);
                    var $results = $('#connection-test-results');
                    
                    $button.prop('disabled', true);
                    $results.html('<p>Testing connection...</p>');
                    
                    $.post(ajaxurl, {
                        action: 'test_trustpilot_connection',
                        nonce: '<?php echo wp_create_nonce('trustpilot_test_connection'); ?>'
                    }, function(response) {
                        $button.prop('disabled', false);
                        if (response.success) {
                            $results.html('<div style="background:#d4edda;border:1px solid #c3e6cb;padding:15px;border-radius:4px;">' + response.data + '</div>');
                        } else {
                            $results.html('<div style="background:#f8d7da;border:1px solid #f5c6cb;padding:15px;border-radius:4px;">' + response.data + '</div>');
                        }
                    }).fail(function() {
                        $button.prop('disabled', false);
                        $results.html('<div style="background:#f8d7da;border:1px solid #f5c6cb;padding:15px;border-radius:4px;">AJAX request failed.</div>');
                    });
                });
            });
            </script>

            <hr>
            
            <h2><?php esc_html_e('Cache Management', 'trustpilot-reviews-wc'); ?></h2>
            <p><?php esc_html_e('Click the button below to refresh the cached reviews.', 'trustpilot-reviews-wc'); ?></p>
            <button type="button" class="button" id="refresh-trustpilot-cache">
                <?php esc_html_e('Refresh Cache Now', 'trustpilot-reviews-wc'); ?>
            </button>
            <span id="cache-refresh-status"></span>

            <script>
            jQuery(document).ready(function($) {
                $('#refresh-trustpilot-cache').on('click', function() {
                    var $button = $(this);
                    var $status = $('#cache-refresh-status');
                    
                    $button.prop('disabled', true);
                    $status.text('<?php esc_html_e('Refreshing...', 'trustpilot-reviews-wc'); ?>');
                    
                    $.post(ajaxurl, {
                        action: 'refresh_trustpilot_cache',
                        nonce: '<?php echo wp_create_nonce('trustpilot_refresh_cache'); ?>'
                    }, function(response) {
                        $button.prop('disabled', false);
                        if (response.success) {
                            $status.text('<?php esc_html_e('Cache refreshed successfully!', 'trustpilot-reviews-wc'); ?>');
                        } else {
                            $status.text('<?php esc_html_e('Error refreshing cache.', 'trustpilot-reviews-wc'); ?>');
                        }
                    });
                });
            });
            </script>

            <hr>

            <h2><?php esc_html_e('Shortcode Examples', 'trustpilot-reviews-wc'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Shortcode', 'trustpilot-reviews-wc'); ?></th>
                        <th><?php esc_html_e('Description', 'trustpilot-reviews-wc'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[trustpilot_reviews]</code></td>
                        <td><?php esc_html_e('Display reviews with default settings', 'trustpilot-reviews-wc'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[trustpilot_reviews count="3"]</code></td>
                        <td><?php esc_html_e('Display 3 reviews', 'trustpilot-reviews-wc'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[trustpilot_reviews layout="grid"]</code></td>
                        <td><?php esc_html_e('Display reviews in grid layout (options: list, grid, carousel)', 'trustpilot-reviews-wc'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[trustpilot_reviews show_rating="yes"]</code></td>
                        <td><?php esc_html_e('Show overall rating summary', 'trustpilot-reviews-wc'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[trustpilot_widget type="micro"]</code></td>
                        <td><?php esc_html_e('Display Trustpilot TrustBox widget (types: micro, mini, slider)', 'trustpilot-reviews-wc'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * AJAX handler for cache refresh
     */
    public function ajax_refresh_cache() {
        check_ajax_referer('trustpilot_refresh_cache', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        
        delete_transient('trustpilot_reviews_cache');
        delete_transient('trustpilot_business_info_cache');
        
        // Pre-fetch new data
        $this->fetch_reviews();
        
        wp_send_json_success();
    }

    /**
     * AJAX handler for connection test
     */
    public function ajax_test_connection() {
        check_ajax_referer('trustpilot_test_connection', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $options = get_option($this->option_name);
        $business_unit_id = isset($options['business_unit_id']) ? trim($options['business_unit_id']) : '';
        $domain = isset($options['domain']) ? trim($options['domain']) : '';
        $api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
        
        $output = '<h4>Connection Test Results</h4>';
        
        // Check configuration
        $output .= '<p><strong>Configuration:</strong></p>';
        $output .= '<ul>';
        $output .= '<li>Business Unit ID: ' . (empty($business_unit_id) ? '<span style="color:orange;">Not set</span>' : '<span style="color:green;">' . esc_html(substr($business_unit_id, 0, 8) . '...') . '</span>') . '</li>';
        $output .= '<li>Domain: ' . (empty($domain) ? '<span style="color:orange;">Not set</span>' : '<span style="color:green;">' . esc_html($domain) . '</span>') . '</li>';
        $output .= '<li>API Key: ' . (empty($api_key) ? '<span style="color:gray;">Not set (optional)</span>' : '<span style="color:green;">Set (' . strlen($api_key) . ' chars)</span>') . '</li>';
        $output .= '</ul>';
        
        if (empty($business_unit_id) && empty($domain)) {
            wp_send_json_error($output . '<p style="color:red;"><strong>Error:</strong> Please configure at least a Business Unit ID or Domain.</p>');
        }
        
        $output .= '<p><strong>API Tests:</strong></p>';
        $success = false;
        
        // Test 1: Public Page Scraping (most reliable without API key)
        if (!empty($domain)) {
            $page_url = 'https://www.trustpilot.com/review/' . $domain;
            
            $output .= '<div style="background:#f8f9fa;padding:10px;margin:10px 0;border-radius:4px;">';
            $output .= '<p><strong>Method 1: Public Page Extraction</strong></p>';
            $output .= '<code style="font-size:11px;word-break:break-all;">' . esc_html($page_url) . '</code><br><br>';
            
            $response = wp_remote_get($page_url, array(
                'timeout' => 15,
                'headers' => array(
                    'Accept' => 'text/html,application/xhtml+xml',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ),
            ));
            
            if (is_wp_error($response)) {
                $output .= '<p style="color:red;">❌ Request Error: ' . esc_html($response->get_error_message()) . '</p>';
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                $output .= '<p>Response Code: <strong>' . esc_html($code) . '</strong></p>';
                
                if ($code === 200) {
                    // Try to find JSON-LD data
                    if (preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $body, $match)) {
                        $json_data = json_decode($match[1], true);
                        if ($json_data && isset($json_data['aggregateRating'])) {
                            $output .= '<p style="color:green;">✅ Found structured data!</p>';
                            $output .= '<p>Trust Score: <strong>' . esc_html($json_data['aggregateRating']['ratingValue'] ?? 'N/A') . '</strong></p>';
                            $output .= '<p>Total Reviews: <strong>' . esc_html($json_data['aggregateRating']['reviewCount'] ?? 'N/A') . '</strong></p>';
                            if (isset($json_data['review']) && count($json_data['review']) > 0) {
                                $output .= '<p>Sample reviews found: <strong>' . count($json_data['review']) . '</strong></p>';
                                $success = true;
                            }
                        }
                    }
                    
                    // Try __NEXT_DATA__
                    if (!$success && preg_match('/<script[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/si', $body, $match)) {
                        $next_data = json_decode($match[1], true);
                        if ($next_data && isset($next_data['props']['pageProps'])) {
                            $output .= '<p style="color:green;">✅ Found Next.js page data!</p>';
                            $page_props = $next_data['props']['pageProps'];
                            if (isset($page_props['businessUnit'])) {
                                $bu = $page_props['businessUnit'];
                                $output .= '<p>Business: <strong>' . esc_html($bu['displayName'] ?? $bu['identifyingName'] ?? 'Found') . '</strong></p>';
                                $output .= '<p>Trust Score: <strong>' . esc_html($bu['trustScore'] ?? $bu['score']['trustScore'] ?? 'N/A') . '</strong></p>';
                                $success = true;
                            }
                        }
                    }
                    
                    if (!$success) {
                        $output .= '<p style="color:orange;">⚠️ Page loaded but could not extract review data. Trustpilot may have changed their page structure.</p>';
                    }
                } else {
                    $output .= '<p style="color:red;">❌ Could not load Trustpilot page. Check if your domain is correct.</p>';
                }
            }
            $output .= '</div>';
        }
        
        // Test 2: TrustBox Widget Endpoint
        if (!empty($business_unit_id)) {
            $widget_url = sprintf(
                'https://widget.trustpilot.com/trustbox-data/53aa8807dec7e10d38f59f32?businessUnitId=%s&locale=en-US&reviewsPerPage=1',
                $business_unit_id
            );
            
            $output .= '<div style="background:#f8f9fa;padding:10px;margin:10px 0;border-radius:4px;">';
            $output .= '<p><strong>Method 2: TrustBox Widget Data</strong></p>';
            $output .= '<code style="font-size:11px;word-break:break-all;">' . esc_html($widget_url) . '</code><br><br>';
            
            $response = wp_remote_get($widget_url, array(
                'timeout' => 15,
                'headers' => array('Accept' => 'application/json'),
            ));
            
            if (is_wp_error($response)) {
                $output .= '<p style="color:red;">❌ Request Error: ' . esc_html($response->get_error_message()) . '</p>';
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                $output .= '<p>Response Code: <strong>' . esc_html($code) . '</strong></p>';
                
                if ($code === 200) {
                    $data = json_decode($body, true);
                    if (!empty($data) && (isset($data['reviews']) || isset($data['businessEntity']))) {
                        $output .= '<p style="color:green;">✅ Widget data available!</p>';
                        $success = true;
                    } else {
                        $output .= '<p style="color:orange;">⚠️ Response received but format unexpected</p>';
                    }
                } else {
                    $output .= '<p style="color:red;">❌ Widget endpoint returned ' . $code . '</p>';
                }
            }
            $output .= '</div>';
        }
        
        // Test 3: Authenticated API (if API key provided)
        if (!empty($api_key) && !empty($business_unit_id)) {
            $api_url = sprintf(
                'https://api.trustpilot.com/v1/business-units/%s/reviews?perPage=1',
                $business_unit_id
            );
            
            $output .= '<div style="background:#f8f9fa;padding:10px;margin:10px 0;border-radius:4px;">';
            $output .= '<p><strong>Method 3: Authenticated API</strong></p>';
            $output .= '<code style="font-size:11px;word-break:break-all;">' . esc_html($api_url) . '</code><br><br>';
            
            $response = wp_remote_get($api_url, array(
                'timeout' => 15,
                'headers' => array(
                    'Accept' => 'application/json',
                    'apikey' => $api_key,
                ),
            ));
            
            if (is_wp_error($response)) {
                $output .= '<p style="color:red;">❌ Request Error: ' . esc_html($response->get_error_message()) . '</p>';
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                $output .= '<p>Response Code: <strong>' . esc_html($code) . '</strong></p>';
                
                if ($code === 200) {
                    $data = json_decode($body, true);
                    if (!empty($data['reviews'])) {
                        $output .= '<p style="color:green;">✅ API key works! Retrieved reviews.</p>';
                        $success = true;
                    }
                } elseif ($code === 401 || $code === 403) {
                    $output .= '<p style="color:red;">❌ API key invalid or insufficient permissions</p>';
                } else {
                    $output .= '<p style="color:red;">❌ API error: ' . esc_html(substr($body, 0, 200)) . '</p>';
                }
            }
            $output .= '</div>';
        }
        
        // Summary
        $output .= '<hr>';
        if ($success) {
            $output .= '<p style="color:green;"><strong>✅ At least one method works!</strong> Save settings and try the shortcode.</p>';
            $output .= '<p>Remember to click "Refresh Cache Now" after saving settings.</p>';
        } else {
            $output .= '<p style="color:red;"><strong>❌ No working method found.</strong></p>';
            $output .= '<p><strong>Recommendations:</strong></p>';
            $output .= '<ol>';
            $output .= '<li>Verify your domain is exactly as it appears on Trustpilot (e.g., <code>lonestartracking.com</code>)</li>';
            $output .= '<li>Use the <code>[trustpilot_widget]</code> shortcode instead - it uses Trustpilot\'s official JavaScript and always works</li>';
            $output .= '<li>If you have a Trustpilot Business API key, add it above</li>';
            $output .= '</ol>';
        }
        
        wp_send_json_success($output);
    }

    /**
     * Log debug message
     */
    private function log_debug($message, $data = null) {
        $options = get_option($this->option_name);
        if (!empty($options['debug_mode'])) {
            $log_entry = '[Trustpilot Reviews] ' . $message;
            if ($data !== null) {
                $log_entry .= ' | Data: ' . print_r($data, true);
            }
            error_log($log_entry);
        }
    }

    /**
     * Get debug info array
     */
    private function get_debug_info() {
        $options = get_option($this->option_name);
        return array(
            'business_unit_id' => isset($options['business_unit_id']) ? $options['business_unit_id'] : '(not set)',
            'domain' => isset($options['domain']) ? $options['domain'] : '(not set)',
            'cache_duration' => isset($options['cache_duration']) ? $options['cache_duration'] : 6,
            'reviews_count' => isset($options['reviews_count']) ? $options['reviews_count'] : 5,
            'debug_mode' => !empty($options['debug_mode']) ? 'enabled' : 'disabled',
        );
    }

    /**
     * Fetch reviews from Trustpilot
     */
    private function fetch_reviews() {
        $options = get_option($this->option_name);
        $business_unit_id = isset($options['business_unit_id']) ? trim($options['business_unit_id']) : '';
        $domain = isset($options['domain']) ? trim($options['domain']) : '';
        $api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
        $cache_duration = isset($options['cache_duration']) ? absint($options['cache_duration']) : 6;
        $reviews_count = isset($options['reviews_count']) ? absint($options['reviews_count']) : 5;
        $debug_mode = !empty($options['debug_mode']);

        $this->log_debug('Starting fetch_reviews', array(
            'business_unit_id' => $business_unit_id,
            'domain' => $domain,
            'has_api_key' => !empty($api_key),
            'reviews_count' => $reviews_count
        ));

        // Store debug info for display
        $this->debug_messages = array();

        if (empty($business_unit_id) && empty($domain)) {
            $this->debug_messages[] = 'ERROR: Both Business Unit ID and Domain are empty. Please configure at least one in Settings → Trustpilot Reviews.';
            $this->log_debug('Error: No business_unit_id or domain configured');
            return false;
        }

        // Check cache first
        $cached = get_transient('trustpilot_reviews_cache');
        if ($cached !== false) {
            $this->debug_messages[] = 'INFO: Returning cached reviews.';
            $this->log_debug('Returning cached data');
            return $cached;
        }

        $this->debug_messages[] = 'INFO: Cache empty or expired, fetching fresh data...';

        // Method 1: Try with API key if provided
        if (!empty($business_unit_id) && !empty($api_key)) {
            $this->debug_messages[] = 'INFO: Attempting authenticated API request...';
            $result = $this->fetch_with_api_key($business_unit_id, $api_key, $reviews_count, $cache_duration);
            if ($result) {
                return $result;
            }
        }

        // Method 2: Try the Trustpilot page scraping method
        if (!empty($domain)) {
            $this->debug_messages[] = 'INFO: Attempting to fetch from Trustpilot public page...';
            $result = $this->fetch_from_public_page($domain, $reviews_count, $cache_duration);
            if ($result) {
                return $result;
            }
        }

        // Method 3: Try alternative widget endpoint with business unit ID
        if (!empty($business_unit_id)) {
            $this->debug_messages[] = 'INFO: Attempting TrustBox data endpoint...';
            $result = $this->fetch_from_trustbox_endpoint($business_unit_id, $reviews_count, $cache_duration);
            if ($result) {
                return $result;
            }
        }

        $this->debug_messages[] = 'ERROR: All fetch methods failed. Consider using the [trustpilot_widget] shortcode instead, which uses official Trustpilot JavaScript.';
        return false;
    }

    /**
     * Fetch reviews using API key
     */
    private function fetch_with_api_key($business_unit_id, $api_key, $count, $cache_duration) {
        $api_url = sprintf(
            'https://api.trustpilot.com/v1/business-units/%s/reviews?perPage=%d',
            $business_unit_id,
            $count
        );

        $this->debug_messages[] = 'INFO: API URL: ' . $api_url;

        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
                'apikey' => $api_key,
            ),
        ));

        if (is_wp_error($response)) {
            $this->debug_messages[] = 'ERROR: API request failed - ' . $response->get_error_message();
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $this->debug_messages[] = 'INFO: API response code: ' . $response_code;

        if ($response_code === 200) {
            $data = json_decode($body, true);
            if (!empty($data) && isset($data['reviews'])) {
                $this->debug_messages[] = 'SUCCESS: Retrieved ' . count($data['reviews']) . ' reviews via authenticated API';
                set_transient('trustpilot_reviews_cache', $data, $cache_duration * HOUR_IN_SECONDS);
                return $data;
            }
        }

        $this->debug_messages[] = 'ERROR: Authenticated API failed. Response: ' . substr($body, 0, 200);
        return false;
    }

    /**
     * Fetch reviews from Trustpilot public page
     */
    private function fetch_from_public_page($domain, $count, $cache_duration) {
        $page_url = 'https://www.trustpilot.com/review/' . $domain;
        
        $this->debug_messages[] = 'INFO: Fetching public page: ' . $page_url;

        $response = wp_remote_get($page_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ),
        ));

        if (is_wp_error($response)) {
            $this->debug_messages[] = 'ERROR: Page request failed - ' . $response->get_error_message();
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $this->debug_messages[] = 'INFO: Page response code: ' . $response_code;

        if ($response_code !== 200) {
            $this->debug_messages[] = 'ERROR: Non-200 response from public page';
            return false;
        }

        // Try to extract JSON-LD data
        $reviews = $this->extract_jsonld_reviews($body, $count);
        
        if ($reviews) {
            $this->debug_messages[] = 'SUCCESS: Extracted ' . count($reviews['reviews']) . ' reviews from public page';
            set_transient('trustpilot_reviews_cache', $reviews, $cache_duration * HOUR_IN_SECONDS);
            return $reviews;
        }

        // Try to extract from __NEXT_DATA__ script
        $reviews = $this->extract_nextdata_reviews($body, $count);
        
        if ($reviews) {
            $this->debug_messages[] = 'SUCCESS: Extracted ' . count($reviews['reviews']) . ' reviews from page data';
            set_transient('trustpilot_reviews_cache', $reviews, $cache_duration * HOUR_IN_SECONDS);
            return $reviews;
        }

        $this->debug_messages[] = 'ERROR: Could not extract reviews from public page';
        return false;
    }

    /**
     * Extract reviews from JSON-LD structured data
     */
    private function extract_jsonld_reviews($html, $count) {
        // Look for JSON-LD script tags
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            foreach ($matches[1] as $json_str) {
                $data = json_decode($json_str, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Check for review data
                    if (isset($data['review']) && is_array($data['review'])) {
                        $reviews = array();
                        // Get all available reviews (up to 20) to allow for filtering
                        $max_fetch = max($count, 20);
                        foreach (array_slice($data['review'], 0, $max_fetch) as $review) {
                            $reviews[] = array(
                                'id' => md5($review['datePublished'] . ($review['author']['name'] ?? '')),
                                'stars' => isset($review['reviewRating']['ratingValue']) ? intval($review['reviewRating']['ratingValue']) : 5,
                                'title' => $review['headline'] ?? '',
                                'text' => $review['reviewBody'] ?? '',
                                'createdAt' => $review['datePublished'] ?? '',
                                'consumer' => array(
                                    'displayName' => $review['author']['name'] ?? __('Anonymous', 'trustpilot-reviews-wc'),
                                ),
                            );
                        }
                        
                        $business_info = array();
                        if (isset($data['aggregateRating'])) {
                            $business_info = array(
                                'trustScore' => floatval($data['aggregateRating']['ratingValue'] ?? 0),
                                'numberOfReviews' => intval($data['aggregateRating']['reviewCount'] ?? 0),
                            );
                        }
                        
                        if (!empty($reviews)) {
                            return array(
                                'reviews' => $reviews,
                                'businessInfo' => $business_info,
                            );
                        }
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Extract reviews from Next.js __NEXT_DATA__ script
     */
    private function extract_nextdata_reviews($html, $count) {
        if (preg_match('/<script[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/si', $html, $match)) {
            $data = json_decode($match[1], true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($data['props']['pageProps'])) {
                $page_props = $data['props']['pageProps'];
                
                $reviews = array();
                $reviews_data = $page_props['reviews'] ?? $page_props['businessUnit']['reviews'] ?? array();
                
                // Get all available reviews (up to 20) to allow for filtering
                $max_fetch = max($count, 20);
                if (is_array($reviews_data)) {
                    foreach (array_slice($reviews_data, 0, $max_fetch) as $review) {
                        $reviews[] = array(
                            'id' => $review['id'] ?? md5(json_encode($review)),
                            'stars' => intval($review['rating'] ?? $review['stars'] ?? 5),
                            'title' => $review['title'] ?? $review['heading'] ?? '',
                            'text' => $review['text'] ?? $review['content'] ?? '',
                            'createdAt' => $review['createdAt'] ?? $review['dates']['publishedDate'] ?? '',
                            'consumer' => array(
                                'displayName' => $review['consumer']['displayName'] ?? $review['consumer']['name'] ?? __('Anonymous', 'trustpilot-reviews-wc'),
                            ),
                        );
                    }
                }
                
                $business_info = array();
                if (isset($page_props['businessUnit'])) {
                    $bu = $page_props['businessUnit'];
                    $business_info = array(
                        'trustScore' => floatval($bu['trustScore'] ?? $bu['score']['trustScore'] ?? 0),
                        'numberOfReviews' => intval($bu['numberOfReviews'] ?? 0),
                    );
                }
                
                if (!empty($reviews)) {
                    return array(
                        'reviews' => $reviews,
                        'businessInfo' => $business_info,
                    );
                }
            }
        }
        
        return false;
    }

    /**
     * Fetch from TrustBox data endpoint
     */
    private function fetch_from_trustbox_endpoint($business_unit_id, $count, $cache_duration) {
        // Try the micro-review card endpoint
        $api_url = sprintf(
            'https://widget.trustpilot.com/trustbox-data/53aa8807dec7e10d38f59f32?businessUnitId=%s&locale=en-US&reviewsPerPage=%d',
            $business_unit_id,
            $count
        );

        $this->debug_messages[] = 'INFO: TrustBox URL: ' . $api_url;

        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            $this->debug_messages[] = 'ERROR: TrustBox request failed - ' . $response->get_error_message();
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $this->debug_messages[] = 'INFO: TrustBox response code: ' . $response_code;

        if ($response_code === 200) {
            $data = json_decode($body, true);
            if (!empty($data)) {
                $transformed = $this->transform_widget_data($data);
                if (!empty($transformed['reviews'])) {
                    $this->debug_messages[] = 'SUCCESS: Retrieved ' . count($transformed['reviews']) . ' reviews from TrustBox';
                    set_transient('trustpilot_reviews_cache', $transformed, $cache_duration * HOUR_IN_SECONDS);
                    return $transformed;
                }
            }
        }

        $this->debug_messages[] = 'ERROR: TrustBox endpoint failed. Response: ' . substr($body, 0, 200);
        return false;
    }

    /**
     * Alternative method to fetch reviews (fallback) - kept for compatibility
     */
    private function fetch_reviews_alternative($domain, $count) {
        $options = get_option($this->option_name);
        $cache_duration = isset($options['cache_duration']) ? absint($options['cache_duration']) : 6;
        return $this->fetch_from_public_page($domain, $count, $cache_duration);
    }

    /**
     * Transform widget data to standard format
     */
    private function transform_widget_data($data) {
        $reviews = array();
        
        if (isset($data['reviews']) && is_array($data['reviews'])) {
            foreach ($data['reviews'] as $review) {
                $reviews[] = array(
                    'id' => isset($review['id']) ? $review['id'] : '',
                    'stars' => isset($review['stars']) ? $review['stars'] : 5,
                    'title' => isset($review['title']) ? $review['title'] : '',
                    'text' => isset($review['text']) ? $review['text'] : '',
                    'createdAt' => isset($review['createdAt']) ? $review['createdAt'] : '',
                    'consumer' => array(
                        'displayName' => isset($review['consumer']['displayName']) ? $review['consumer']['displayName'] : __('Anonymous', 'trustpilot-reviews-wc'),
                    ),
                );
            }
        }

        return array(
            'reviews' => $reviews,
            'businessInfo' => isset($data['businessEntity']) ? $data['businessEntity'] : array(),
        );
    }

    /**
     * Render stars HTML
     */
    private function render_stars($rating) {
        $rating = floatval($rating);
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5;
        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
        
        $html = '<div class="trustpilot-stars">';
        
        for ($i = 0; $i < $full_stars; $i++) {
            $html .= '<span class="star star-full">★</span>';
        }
        
        if ($half_star) {
            $html .= '<span class="star star-half">★</span>';
        }
        
        for ($i = 0; $i < $empty_stars; $i++) {
            $html .= '<span class="star star-empty">☆</span>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render reviews shortcode
     */
    public function render_reviews_shortcode($atts) {
        $options = get_option($this->option_name);
        $default_count = isset($options['reviews_count']) ? $options['reviews_count'] : 5;
        $debug_mode = !empty($options['debug_mode']);
        
        $atts = shortcode_atts(array(
            'count' => $default_count,
            'layout' => 'list', // list, grid, carousel
            'show_rating' => 'yes',
            'show_date' => 'yes',
            'min_stars' => 1,
        ), $atts, 'trustpilot_reviews');

        // Initialize debug messages array
        $this->debug_messages = array();
        
        $data = $this->fetch_reviews();

        // Build debug output
        $debug_output = '';
        if ($debug_mode) {
            $debug_info = $this->get_debug_info();
            $debug_output .= '<div class="trustpilot-debug" style="background:#f5f5f5;border:2px solid #ff6600;padding:15px;margin:15px 0;font-family:monospace;font-size:13px;border-radius:5px;">';
            $debug_output .= '<h4 style="margin:0 0 10px;color:#ff6600;">🔧 Trustpilot Reviews Debug Info</h4>';
            
            $debug_output .= '<div style="margin-bottom:10px;"><strong>Configuration:</strong></div>';
            $debug_output .= '<ul style="margin:0 0 15px 20px;padding:0;">';
            foreach ($debug_info as $key => $value) {
                $display_value = $value;
                if ($key === 'business_unit_id' && !empty($value) && $value !== '(not set)') {
                    $display_value = substr($value, 0, 8) . '...' . substr($value, -4);
                }
                $debug_output .= '<li>' . esc_html($key) . ': <code>' . esc_html($display_value) . '</code></li>';
            }
            $debug_output .= '</ul>';
            
            if (!empty($this->debug_messages)) {
                $debug_output .= '<div style="margin-bottom:10px;"><strong>Debug Log:</strong></div>';
                $debug_output .= '<div style="background:#fff;padding:10px;border:1px solid #ddd;max-height:300px;overflow-y:auto;">';
                foreach ($this->debug_messages as $msg) {
                    $color = '#333';
                    if (strpos($msg, 'ERROR') === 0) $color = '#dc3545';
                    if (strpos($msg, 'SUCCESS') === 0) $color = '#28a745';
                    if (strpos($msg, 'INFO') === 0) $color = '#17a2b8';
                    $debug_output .= '<div style="color:' . $color . ';margin-bottom:5px;word-break:break-all;">' . esc_html($msg) . '</div>';
                }
                $debug_output .= '</div>';
            }
            
            if ($data) {
                $debug_output .= '<div style="margin-top:15px;"><strong>Data Retrieved:</strong> ';
                $debug_output .= 'Reviews: ' . count($data['reviews'] ?? array());
                if (isset($data['businessInfo'])) {
                    $debug_output .= ' | Business Info: Yes';
                }
                $debug_output .= '</div>';
            }
            
            $debug_output .= '<div style="margin-top:10px;font-size:11px;color:#666;">Disable debug mode in Settings → Trustpilot Reviews when done troubleshooting.</div>';
            $debug_output .= '</div>';
        }

        if (!$data || empty($data['reviews'])) {
            $error_html = '<div class="trustpilot-error" style="padding:15px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;color:#856404;">';
            $error_html .= '<strong>' . esc_html__('Unable to load Trustpilot reviews.', 'trustpilot-reviews-wc') . '</strong><br>';
            $error_html .= esc_html__('Please check your settings in Settings → Trustpilot Reviews.', 'trustpilot-reviews-wc');
            
            if (!$debug_mode) {
                $error_html .= '<br><br><em>' . esc_html__('Tip: Enable Debug Mode in settings to see detailed error information.', 'trustpilot-reviews-wc') . '</em>';
            }
            
            $error_html .= '</div>';
            
            return $debug_output . $error_html;
        }

        $min_stars = intval($atts['min_stars']);
        
        // Filter by minimum stars FIRST
        $filtered_reviews = array_filter($data['reviews'], function($review) use ($min_stars) {
            return isset($review['stars']) && intval($review['stars']) >= $min_stars;
        });
        
        // THEN slice to get requested count
        $reviews = array_slice($filtered_reviews, 0, intval($atts['count']));

        ob_start();
        
        // Output debug info first if enabled
        if ($debug_mode) {
            echo $debug_output;
        }
        ?>
        <div class="trustpilot-reviews-container layout-<?php echo esc_attr($atts['layout']); ?>">
            
            <?php if ($atts['show_rating'] === 'yes' && isset($data['businessInfo'])) : ?>
            <div class="trustpilot-overall-rating">
                <div class="trustpilot-logo">
                    <svg viewBox="0 0 126 31" xmlns="http://www.w3.org/2000/svg" width="120">
                        <path d="M33.3 10.7h8.4v1.5h-3.3V21h-1.8v-8.8h-3.3v-1.5zm8.8 3.2h1.6v1.4h0c.5-1.1 1.3-1.6 2.5-1.6.2 0 .3 0 .5 0v1.6c-.2 0-.4-.1-.7-.1-1.5 0-2.2.9-2.2 2.6V21h-1.7v-7.1zm8.5 7.3c-2.2 0-3.6-1.5-3.6-3.7s1.4-3.7 3.6-3.7c2.2 0 3.6 1.5 3.6 3.7s-1.4 3.7-3.6 3.7zm0-1.4c1.2 0 1.9-1 1.9-2.4 0-1.4-.7-2.4-1.9-2.4s-1.9 1-1.9 2.4c0 1.4.7 2.4 1.9 2.4zm8.7 1.4c-2.2 0-3.6-1.5-3.6-3.7s1.4-3.7 3.6-3.7c2.2 0 3.6 1.5 3.6 3.7s-1.4 3.7-3.6 3.7zm0-1.4c1.2 0 1.9-1 1.9-2.4 0-1.4-.7-2.4-1.9-2.4s-1.9 1-1.9 2.4c0 1.4.7 2.4 1.9 2.4zm4.8-5.9h1.6v1.2h0c.5-.9 1.3-1.4 2.4-1.4 1.8 0 2.6 1.1 2.6 2.8V21h-1.7v-4.2c0-1.2-.4-1.9-1.5-1.9-1.2 0-1.8.8-1.8 2.2V21h-1.7v-7.1zm8.3-3.2h1.7v3.5h0c.5-.8 1.3-1.3 2.4-1.3 1.8 0 3.1 1.5 3.1 3.7s-1.4 3.6-3.2 3.6c-1 0-1.9-.4-2.4-1.3h0V21h-1.7v-10.3zm3.7 8.9c1.2 0 1.9-1 1.9-2.4 0-1.4-.7-2.4-1.9-2.4s-1.9 1-1.9 2.4c0 1.4.7 2.4 1.9 2.4zm4.3-2.3c0-2.2 1.5-3.7 3.7-3.7 2.2 0 3.7 1.5 3.7 3.7s-1.5 3.7-3.7 3.7c-2.2 0-3.7-1.5-3.7-3.7zm5.6 0c0-1.4-.7-2.4-1.9-2.4s-1.9 1-1.9 2.4c0 1.4.7 2.4 1.9 2.4s1.9-1 1.9-2.4zm3 3.5l3-4.2-2.8-4.1h2l1.9 2.9 1.9-2.9h2l-2.8 4.1 3 4.2h-2l-2.1-3.1-2.1 3.1h-2z" fill="#191919"/>
                        <path d="M24 10.7l-1.8 5.5h-5.8l4.7 3.4-1.8 5.5 4.7-3.4 4.7 3.4-1.8-5.5 4.7-3.4h-5.8L24 10.7z" fill="#00B67A"/>
                        <path d="M28.9 22.1l-.4-1.3-4.5 3.3 4.9-2z" fill="#005128"/>
                    </svg>
                </div>
                <?php if (isset($data['businessInfo']['trustScore'])) : ?>
                <div class="trustpilot-score">
                    <?php echo $this->render_stars($data['businessInfo']['trustScore']); ?>
                    <span class="score-text">
                        <?php printf(
                            esc_html__('TrustScore %s | %s reviews', 'trustpilot-reviews-wc'),
                            '<strong>' . esc_html(number_format($data['businessInfo']['trustScore'], 1)) . '</strong>',
                            esc_html(number_format($data['businessInfo']['numberOfReviews'] ?? count($reviews)))
                        ); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php 
            // Inline styles for carousel to override any theme conflicts
            $list_style = '';
            $item_style = '';
            if ($atts['layout'] === 'carousel') {
                $list_style = 'display:flex;flex-direction:row;flex-wrap:nowrap;overflow-x:auto;gap:1.5rem;padding-bottom:1rem;-webkit-overflow-scrolling:touch;';
                $item_style = 'flex:0 0 350px;min-width:300px;max-width:400px;flex-shrink:0;';
            }
            ?>
            <div class="trustpilot-reviews-list" <?php echo $list_style ? 'style="' . esc_attr($list_style) . '"' : ''; ?>>
                <?php foreach ($reviews as $review) : ?>
                <div class="trustpilot-review-item" <?php echo $item_style ? 'style="' . esc_attr($item_style) . '"' : ''; ?>>
                    <div class="review-header">
                        <?php echo $this->render_stars($review['stars']); ?>
                        <?php if (!empty($review['title'])) : ?>
                        <h4 class="review-title"><?php echo esc_html($review['title']); ?></h4>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($review['text'])) : ?>
                    <div class="review-content">
                        <p><?php echo esc_html($review['text']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="review-footer">
                        <span class="review-author">
                            <?php echo esc_html($review['consumer']['displayName'] ?? __('Anonymous', 'trustpilot-reviews-wc')); ?>
                        </span>
                        <?php if ($atts['show_date'] === 'yes' && !empty($review['createdAt'])) : ?>
                        <span class="review-date">
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($review['createdAt']))); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="trustpilot-footer">
                <a href="https://www.trustpilot.com/review/<?php echo esc_attr($options['domain'] ?? ''); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('See all reviews on Trustpilot', 'trustpilot-reviews-wc'); ?> →
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Trustpilot TrustBox widget shortcode
     */
    public function render_widget_shortcode($atts) {
        $options = get_option($this->option_name);
        $business_unit_id = isset($options['business_unit_id']) ? $options['business_unit_id'] : '';
        $domain = isset($options['domain']) ? $options['domain'] : '';
        
        $atts = shortcode_atts(array(
            'type' => 'micro', // micro, mini, slider, carousel, grid
            'theme' => 'light', // light, dark
            'height' => '150px',
            'width' => '100%',
        ), $atts, 'trustpilot_widget');

        if (empty($business_unit_id)) {
            return '<p class="trustpilot-error">' . esc_html__('Please configure your Trustpilot Business Unit ID in settings.', 'trustpilot-reviews-wc') . '</p>';
        }

        // Widget template IDs
        $template_ids = array(
            'micro' => '5419b6a8b0d04a076446a9ad',
            'mini' => '53aa8807dec7e10d38f59f32',
            'slider' => '54ad5defc6454f065c28af8b',
            'carousel' => '53aa8912dec7e10d38f59f36',
            'grid' => '539adbd6dec7e10e686debee',
        );

        $template_id = isset($template_ids[$atts['type']]) ? $template_ids[$atts['type']] : $template_ids['micro'];

        ob_start();
        ?>
        <!-- TrustBox widget -->
        <div class="trustpilot-widget" 
             data-locale="en-US" 
             data-template-id="<?php echo esc_attr($template_id); ?>" 
             data-businessunit-id="<?php echo esc_attr($business_unit_id); ?>" 
             data-style-height="<?php echo esc_attr($atts['height']); ?>" 
             data-style-width="<?php echo esc_attr($atts['width']); ?>" 
             data-theme="<?php echo esc_attr($atts['theme']); ?>">
            <a href="https://www.trustpilot.com/review/<?php echo esc_attr($domain); ?>" target="_blank" rel="noopener">Trustpilot</a>
        </div>
        <script type="text/javascript" src="//widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js" async></script>
        <!-- End TrustBox widget -->
        <?php
        return ob_get_clean();
    }
}

// Initialize plugin
function trustpilot_reviews_wc_init() {
    Trustpilot_Reviews_WC::get_instance();
}
add_action('plugins_loaded', 'trustpilot_reviews_wc_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create default options
    $default_options = array(
        'business_unit_id' => '',
        'domain' => '',
        'cache_duration' => 6,
        'reviews_count' => 5,
    );
    
    if (!get_option('trustpilot_reviews_wc_settings')) {
        add_option('trustpilot_reviews_wc_settings', $default_options);
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    delete_transient('trustpilot_reviews_cache');
    delete_transient('trustpilot_business_info_cache');
});
