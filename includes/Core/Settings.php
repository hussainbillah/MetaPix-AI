<?php

namespace MetaPixAI\Core;

/**
 * Settings Management Class
 */
class Settings {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register settings group
        register_setting('metapix_ai_settings', 'metapix_ai_license_key');
        register_setting('metapix_ai_settings', 'metapix_ai_openai_api_key');
        register_setting('metapix_ai_settings', 'metapix_ai_mode');
        register_setting('metapix_ai_settings', 'metapix_ai_auto_optimize_images');
        register_setting('metapix_ai_settings', 'metapix_ai_auto_optimize_content');
        register_setting('metapix_ai_settings', 'metapix_ai_auto_schema');
        register_setting('metapix_ai_settings', 'metapix_ai_performance_monitoring');
        register_setting('metapix_ai_settings', 'metapix_ai_pagespeed_api_key');
        register_setting('metapix_ai_settings', 'metapix_ai_ga4_property_id');
        register_setting('metapix_ai_settings', 'metapix_ai_weekly_reports');
    }
    
    /**
     * Get setting value
     */
    public static function get($key, $default = null) {
        return get_option("metapix_ai_{$key}", $default);
    }
    
    /**
     * Update setting value
     */
    public static function set($key, $value) {
        return update_option("metapix_ai_{$key}", $value);
    }
    
    /**
     * Delete setting
     */
    public static function delete($key) {
        return delete_option("metapix_ai_{$key}");
    }
    
    /**
     * Get all settings
     */
    public static function get_all() {
        return [
            'license_key' => self::get('license_key', ''),
            'openai_api_key' => self::get('openai_api_key', ''),
            'mode' => self::get('mode', 'manual'),
            'auto_optimize_images' => self::get('auto_optimize_images', false),
            'auto_optimize_content' => self::get('auto_optimize_content', false),
            'auto_schema' => self::get('auto_schema', false),
            'performance_monitoring' => self::get('performance_monitoring', true),
            'pagespeed_api_key' => self::get('pagespeed_api_key', ''),
            'ga4_property_id' => self::get('ga4_property_id', ''),
            'weekly_reports' => self::get('weekly_reports', true),
        ];
    }
}