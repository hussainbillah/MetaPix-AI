<?php
/**
 * Plugin Name: MetaPix AI
 * Plugin URI: https://metapix.ai
 * Description: Fully autonomous SEO assistant for content creators, bloggers, and agencies. Optimizes images, content, metadata, linking, schema, and performance using AI.
 * Version: 1.0.0
 * Author: MetaPix AI Team
 * Author URI: https://metapix.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: metapix-ai
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('METAPIX_AI_VERSION', '1.0.0');
define('METAPIX_AI_PLUGIN_FILE', __FILE__);
define('METAPIX_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('METAPIX_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('METAPIX_AI_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'MetaPixAI\\';
    $base_dir = METAPIX_AI_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main MetaPix AI Plugin Class
 */
class MetaPixAI {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        add_action('plugins_loaded', [$this, 'init']);
        add_action('init', [$this, 'load_textdomain']);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', [$this, 'wp_version_notice']);
            return;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', [$this, 'php_version_notice']);
            return;
        }
        
        // Initialize core components
        $this->init_components();
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Core
        new MetaPixAI\Core\Database();
        new MetaPixAI\Core\Settings();
        new MetaPixAI\Core\License();
        
        // Admin
        if (is_admin()) {
            new MetaPixAI\Admin\Admin();
            new MetaPixAI\Admin\Dashboard();
            new MetaPixAI\Admin\Settings();
        }
        
        // API
        new MetaPixAI\API\RestAPI();
        
        // Modules
        new MetaPixAI\Modules\ImageSEO();
        new MetaPixAI\Modules\ImageMetadataSEO();
        new MetaPixAI\Modules\ImageModeration();
        new MetaPixAI\Modules\PromptHistory();
        new MetaPixAI\Modules\ContentOptimizer();
        new MetaPixAI\Modules\LinkingStructure();
        new MetaPixAI\Modules\PerformanceAnalyzer();
        new MetaPixAI\Modules\CompetitorIntelligence();
        new MetaPixAI\Modules\Analytics();
        
        // Services
        new MetaPixAI\Services\OpenAI();
        new MetaPixAI\Services\PageSpeed();
        new MetaPixAI\Services\GoogleAnalytics();
        
        // Scheduler
        new MetaPixAI\Core\Scheduler();
        
        // Notifications
        new MetaPixAI\Core\Notifications();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'metapix-ai',
            false,
            dirname(METAPIX_AI_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        MetaPixAI\Core\Database::create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('metapix_ai_activated', true);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('metapix_ai_daily_scan');
        wp_clear_scheduled_hook('metapix_ai_weekly_report');
        wp_clear_scheduled_hook('metapix_ai_performance_check');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = [
            'metapix_ai_mode' => 'manual', // autonomous, manual
            'metapix_ai_auto_optimize_images' => false,
            'metapix_ai_auto_optimize_content' => false,
            'metapix_ai_auto_schema' => false,
            'metapix_ai_performance_monitoring' => true,
            'metapix_ai_weekly_reports' => true,
            'metapix_ai_license_key' => '',
            'metapix_ai_plan' => 'free',
            'metapix_ai_openai_api_key' => '',
            'metapix_ai_pagespeed_api_key' => '',
            'metapix_ai_ga4_property_id' => '',
        ];
        
        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                update_option($option, $value);
            }
        }
    }
    
    /**
     * Schedule cron jobs
     */
    private function schedule_cron_jobs() {
        if (!wp_next_scheduled('metapix_ai_daily_scan')) {
            wp_schedule_event(time(), 'daily', 'metapix_ai_daily_scan');
        }
        
        if (!wp_next_scheduled('metapix_ai_weekly_report')) {
            wp_schedule_event(time(), 'weekly', 'metapix_ai_weekly_report');
        }
        
        if (!wp_next_scheduled('metapix_ai_performance_check')) {
            wp_schedule_event(time(), 'twicedaily', 'metapix_ai_performance_check');
        }
    }
    
    /**
     * WordPress version notice
     */
    public function wp_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo sprintf(
            __('MetaPix AI requires WordPress version 5.0 or higher. You are running version %s.', 'metapix-ai'),
            get_bloginfo('version')
        );
        echo '</p></div>';
    }
    
    /**
     * PHP version notice
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo sprintf(
            __('MetaPix AI requires PHP version 7.4 or higher. You are running version %s.', 'metapix-ai'),
            PHP_VERSION
        );
        echo '</p></div>';
    }
}

// Initialize plugin
MetaPixAI::get_instance();