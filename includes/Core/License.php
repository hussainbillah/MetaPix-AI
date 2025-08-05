<?php

namespace MetaPixAI\Core;

/**
 * License Management Class
 */
class License {
    
    /**
     * License server URL
     */
    const LICENSE_SERVER = 'https://api.metapix.ai/v1/license';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', [$this, 'validate_license']);
        add_action('wp_ajax_metapix_activate_license', [$this, 'ajax_activate_license']);
        add_action('wp_ajax_metapix_deactivate_license', [$this, 'ajax_deactivate_license']);
    }
    
    /**
     * Validate license on admin load
     */
    public function validate_license() {
        $license_key = get_option('metapix_ai_license_key', '');
        
        if (empty($license_key)) {
            return;
        }
        
        // Check if we need to validate (once per day)
        $last_check = get_transient('metapix_ai_license_check');
        if ($last_check !== false) {
            return;
        }
        
        $this->check_license($license_key);
        set_transient('metapix_ai_license_check', time(), DAY_IN_SECONDS);
    }
    
    /**
     * Check license with server
     */
    public function check_license($license_key) {
        $response = wp_remote_post(self::LICENSE_SERVER . '/check', [
            'body' => [
                'license_key' => $license_key,
                'domain' => get_site_url(),
                'version' => METAPIX_AI_VERSION
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['success'])) {
            return false;
        }
        
        if ($data['success']) {
            // Update license data in database
            $this->update_license_data($data['license']);
            return true;
        } else {
            // License invalid - create notification
            Database::create_notification([
                'type' => 'error',
                'title' => 'License Invalid',
                'message' => $data['message'] ?? 'Your license key is invalid or expired.',
                'priority' => 'high'
            ]);
            return false;
        }
    }
    
    /**
     * Update license data in database
     */
    private function update_license_data($license_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_license_usage';
        
        $data = [
            'license_key' => $license_data['key'],
            'domain' => get_site_url(),
            'plan' => $license_data['plan'],
            'sites_limit' => $license_data['sites_limit'],
            'sites_used' => $license_data['sites_used'],
            'api_calls_limit' => $license_data['api_calls_limit'],
            'api_calls_used' => $license_data['api_calls_used'],
            'reset_date' => $license_data['reset_date'],
            'status' => $license_data['status'],
            'expires_at' => $license_data['expires_at']
        ];
        
        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE license_key = %s",
            $license_data['key']
        ));
        
        if ($existing) {
            $wpdb->update($table, $data, ['license_key' => $license_data['key']]);
        } else {
            $wpdb->insert($table, $data);
        }
        
        // Update WordPress options
        update_option('metapix_ai_plan', $license_data['plan']);
    }
    
    /**
     * Get license status
     */
    public static function get_status() {
        $license_key = get_option('metapix_ai_license_key', '');
        
        if (empty($license_key)) {
            return [
                'valid' => false,
                'plan' => 'free',
                'message' => 'No license key provided'
            ];
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_license_usage';
        
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE license_key = %s",
            $license_key
        ));
        
        if (!$license) {
            return [
                'valid' => false,
                'plan' => 'free',
                'message' => 'License not found'
            ];
        }
        
        $is_expired = $license->expires_at && strtotime($license->expires_at) < time();
        $is_over_limit = $license->api_calls_used >= $license->api_calls_limit;
        
        return [
            'valid' => $license->status === 'active' && !$is_expired,
            'plan' => $license->plan,
            'sites_used' => $license->sites_used,
            'sites_limit' => $license->sites_limit,
            'api_calls_used' => $license->api_calls_used,
            'api_calls_limit' => $license->api_calls_limit,
            'expires_at' => $license->expires_at,
            'is_expired' => $is_expired,
            'is_over_limit' => $is_over_limit,
            'message' => $this->get_status_message($license, $is_expired, $is_over_limit)
        ];
    }
    
    /**
     * Get status message
     */
    private function get_status_message($license, $is_expired, $is_over_limit) {
        if ($license->status !== 'active') {
            return 'License is inactive';
        }
        
        if ($is_expired) {
            return 'License has expired';
        }
        
        if ($is_over_limit) {
            return 'API call limit exceeded';
        }
        
        return 'License is active';
    }
    
    /**
     * Check if feature is available
     */
    public static function can_use_feature($feature) {
        $status = self::get_status();
        
        if (!$status['valid']) {
            return false;
        }
        
        $plan = $status['plan'];
        
        $features = [
            'free' => ['basic_image_seo'],
            'pro' => ['basic_image_seo', 'content_optimizer', 'performance_monitoring'],
            'business' => ['basic_image_seo', 'content_optimizer', 'performance_monitoring', 'competitor_intelligence'],
            'agency' => ['basic_image_seo', 'content_optimizer', 'performance_monitoring', 'competitor_intelligence', 'white_label']
        ];
        
        return in_array($feature, $features[$plan] ?? []);
    }
    
    /**
     * Get plan limits
     */
    public static function get_plan_limits($plan = null) {
        if (!$plan) {
            $status = self::get_status();
            $plan = $status['plan'];
        }
        
        $limits = [
            'free' => [
                'sites' => 1,
                'api_calls' => 100,
                'features' => ['basic_image_seo']
            ],
            'pro' => [
                'sites' => 3,
                'api_calls' => 1000,
                'features' => ['basic_image_seo', 'content_optimizer', 'performance_monitoring']
            ],
            'business' => [
                'sites' => 10,
                'api_calls' => 5000,
                'features' => ['basic_image_seo', 'content_optimizer', 'performance_monitoring', 'competitor_intelligence']
            ],
            'agency' => [
                'sites' => 300,
                'api_calls' => 50000,
                'features' => ['basic_image_seo', 'content_optimizer', 'performance_monitoring', 'competitor_intelligence', 'white_label']
            ]
        ];
        
        return $limits[$plan] ?? $limits['free'];
    }
    
    /**
     * AJAX: Activate license
     */
    public function ajax_activate_license() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        
        if (empty($license_key)) {
            wp_send_json_error(['message' => 'License key is required']);
        }
        
        $response = wp_remote_post(self::LICENSE_SERVER . '/activate', [
            'body' => [
                'license_key' => $license_key,
                'domain' => get_site_url(),
                'version' => METAPIX_AI_VERSION
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Failed to connect to license server']);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['success'])) {
            wp_send_json_error(['message' => 'Invalid response from license server']);
        }
        
        if ($data['success']) {
            update_option('metapix_ai_license_key', $license_key);
            $this->update_license_data($data['license']);
            
            wp_send_json_success([
                'message' => 'License activated successfully',
                'license' => $data['license']
            ]);
        } else {
            wp_send_json_error(['message' => $data['message'] ?? 'License activation failed']);
        }
    }
    
    /**
     * AJAX: Deactivate license
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $license_key = get_option('metapix_ai_license_key', '');
        
        if (empty($license_key)) {
            wp_send_json_error(['message' => 'No license key found']);
        }
        
        $response = wp_remote_post(self::LICENSE_SERVER . '/deactivate', [
            'body' => [
                'license_key' => $license_key,
                'domain' => get_site_url()
            ],
            'timeout' => 15
        ]);
        
        // Clear local license data regardless of server response
        delete_option('metapix_ai_license_key');
        delete_option('metapix_ai_plan');
        
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_license_usage';
        $wpdb->delete($table, ['license_key' => $license_key]);
        
        wp_send_json_success(['message' => 'License deactivated successfully']);
    }
    
    /**
     * Increment API usage
     */
    public static function increment_api_usage($calls = 1) {
        $license_key = get_option('metapix_ai_license_key', '');
        
        if (empty($license_key)) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_license_usage';
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET api_calls_used = api_calls_used + %d WHERE license_key = %s",
            $calls,
            $license_key
        ));
        
        return $result !== false;
    }
    
    /**
     * Check if API calls are available
     */
    public static function has_api_calls($calls = 1) {
        $status = self::get_status();
        
        if (!$status['valid']) {
            return false;
        }
        
        return ($status['api_calls_used'] + $calls) <= $status['api_calls_limit'];
    }
}