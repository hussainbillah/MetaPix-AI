<?php

namespace MetaPixAI\Admin;

use MetaPixAI\Core\Database;
use MetaPixAI\Services\OpenAI;

/**
 * Admin Dashboard
 */
class Dashboard {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_metapix_dashboard_stats', [$this, 'ajax_get_dashboard_stats']);
        add_action('wp_ajax_metapix_recent_activity', [$this, 'ajax_get_recent_activity']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('MetaPix AI', 'metapix-ai'),
            __('MetaPix AI', 'metapix-ai'),
            'manage_options',
            'metapix-ai',
            [$this, 'render_dashboard'],
            'data:image/svg+xml;base64,' . base64_encode($this->get_menu_icon()),
            30
        );
        
        // Dashboard submenu (same as main page)
        add_submenu_page(
            'metapix-ai',
            __('Dashboard', 'metapix-ai'),
            __('Dashboard', 'metapix-ai'),
            'manage_options',
            'metapix-ai',
            [$this, 'render_dashboard']
        );
        
        // Settings submenu
        add_submenu_page(
            'metapix-ai',
            __('Settings', 'metapix-ai'),
            __('Settings', 'metapix-ai'),
            'manage_options',
            'metapix-ai-settings',
            [$this, 'render_settings']
        );
        
        // Analytics submenu
        add_submenu_page(
            'metapix-ai',
            __('Analytics', 'metapix-ai'),
            __('Analytics', 'metapix-ai'),
            'manage_options',
            'metapix-ai-analytics',
            [$this, 'render_analytics']
        );
        
        // Optimization History submenu
        add_submenu_page(
            'metapix-ai',
            __('Optimization History', 'metapix-ai'),
            __('History', 'metapix-ai'),
            'manage_options',
            'metapix-ai-history',
            [$this, 'render_history']
        );
        
        // Bulk Tools submenu
        add_submenu_page(
            'metapix-ai',
            __('Bulk Tools', 'metapix-ai'),
            __('Bulk Tools', 'metapix-ai'),
            'manage_options',
            'metapix-ai-bulk',
            [$this, 'render_bulk_tools']
        );
    }
    
    /**
     * Get menu icon SVG
     */
    private function get_menu_icon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
        </svg>';
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'metapix-ai') === false) {
            return;
        }
        
        // Enqueue main admin styles and scripts
        wp_enqueue_style('metapix-ai-admin', METAPIX_AI_PLUGIN_URL . 'assets/css/admin.css', [], METAPIX_AI_VERSION);
        wp_enqueue_script('metapix-ai-admin', METAPIX_AI_PLUGIN_URL . 'assets/js/admin.js', ['jquery', 'wp-api'], METAPIX_AI_VERSION, true);
        
        // Enqueue Chart.js for analytics
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true);
        
        // Localize script
        wp_localize_script('metapix-ai-admin', 'metapixAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'resturl' => rest_url('metapix-ai/v1/'),
            'nonce' => wp_create_nonce('metapix_admin_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'metapix-ai'),
                'error' => __('An error occurred', 'metapix-ai'),
                'success' => __('Success!', 'metapix-ai'),
                'confirm' => __('Are you sure?', 'metapix-ai'),
                'processing' => __('Processing...', 'metapix-ai')
            ]
        ]);
    }
    
    /**
     * Render main dashboard
     */
    public function render_dashboard() {
        $license_status = $this->get_license_status();
        $dashboard_stats = $this->get_dashboard_stats();
        
        ?>
        <div class="wrap metapix-admin-wrap">
            <h1 class="metapix-page-title">
                <?php _e('MetaPix AI Dashboard', 'metapix-ai'); ?>
                <span class="metapix-version">v<?php echo METAPIX_AI_VERSION; ?></span>
            </h1>
            
            <?php $this->render_license_notice($license_status); ?>
            
            <div class="metapix-dashboard-grid">
                <!-- Overview Cards -->
                <div class="metapix-overview-cards">
                    <?php $this->render_overview_cards($dashboard_stats); ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="metapix-quick-actions">
                    <div class="metapix-card">
                        <h3><?php _e('Quick Actions', 'metapix-ai'); ?></h3>
                        <div class="metapix-actions-grid">
                            <button class="metapix-action-btn" id="bulk-scan-posts">
                                <span class="dashicons dashicons-search"></span>
                                <?php _e('Scan All Posts', 'metapix-ai'); ?>
                            </button>
                            <button class="metapix-action-btn" id="optimize-images">
                                <span class="dashicons dashicons-format-image"></span>
                                <?php _e('Optimize Images', 'metapix-ai'); ?>
                            </button>
                            <button class="metapix-action-btn" id="generate-meta-tags">
                                <span class="dashicons dashicons-tag"></span>
                                <?php _e('Generate Meta Tags', 'metapix-ai'); ?>
                            </button>
                            <button class="metapix-action-btn" id="performance-check">
                                <span class="dashicons dashicons-performance"></span>
                                <?php _e('Performance Check', 'metapix-ai'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- SEO Score Chart -->
                <div class="metapix-score-chart">
                    <div class="metapix-card">
                        <h3><?php _e('SEO Score Trends', 'metapix-ai'); ?></h3>
                        <canvas id="seo-score-chart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="metapix-recent-activity">
                    <div class="metapix-card">
                        <h3><?php _e('Recent Activity', 'metapix-ai'); ?></h3>
                        <div id="recent-activity-list">
                            <?php $this->render_recent_activity(); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Top Performing Posts -->
                <div class="metapix-top-posts">
                    <div class="metapix-card">
                        <h3><?php _e('Top Performing Posts', 'metapix-ai'); ?></h3>
                        <div id="top-posts-list">
                            <?php $this->render_top_posts(); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications -->
                <div class="metapix-notifications">
                    <div class="metapix-card">
                        <h3><?php _e('Notifications', 'metapix-ai'); ?></h3>
                        <div id="notifications-list">
                            <?php $this->render_notifications(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render license notice
     */
    private function render_license_notice($license_status) {
        if (!$license_status['has_license']) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('MetaPix AI License Required', 'metapix-ai'); ?></strong>
                    <?php _e('Please enter your license key to access all features.', 'metapix-ai'); ?>
                    <a href="<?php echo admin_url('admin.php?page=metapix-ai-settings'); ?>" class="button button-primary">
                        <?php _e('Enter License Key', 'metapix-ai'); ?>
                    </a>
                </p>
            </div>
            <?php
        } elseif (!$license_status['license_valid']) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('Invalid License', 'metapix-ai'); ?></strong>
                    <?php _e('Your license key is invalid or expired.', 'metapix-ai'); ?>
                    <a href="<?php echo admin_url('admin.php?page=metapix-ai-settings'); ?>" class="button">
                        <?php _e('Update License', 'metapix-ai'); ?>
                    </a>
                </p>
            </div>
            <?php
        } elseif ($license_status['api_calls_used'] >= $license_status['api_calls_limit']) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('API Limit Reached', 'metapix-ai'); ?></strong>
                    <?php _e('You have reached your monthly API call limit.', 'metapix-ai'); ?>
                    <a href="#" class="button button-primary">
                        <?php _e('Upgrade Plan', 'metapix-ai'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Render overview cards
     */
    private function render_overview_cards($stats) {
        ?>
        <div class="metapix-card metapix-stat-card">
            <div class="metapix-stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="metapix-stat-content">
                <h3><?php echo number_format($stats['avg_seo_score'], 1); ?></h3>
                <p><?php _e('Average SEO Score', 'metapix-ai'); ?></p>
            </div>
        </div>
        
        <div class="metapix-card metapix-stat-card">
            <div class="metapix-stat-icon">
                <span class="dashicons dashicons-admin-post"></span>
            </div>
            <div class="metapix-stat-content">
                <h3><?php echo number_format($stats['total_analyzed']); ?></h3>
                <p><?php _e('Posts Analyzed', 'metapix-ai'); ?></p>
            </div>
        </div>
        
        <div class="metapix-card metapix-stat-card">
            <div class="metapix-stat-icon">
                <span class="dashicons dashicons-format-image"></span>
            </div>
            <div class="metapix-stat-content">
                <h3><?php echo number_format($stats['images_optimized']); ?></h3>
                <p><?php _e('Images Optimized', 'metapix-ai'); ?></p>
            </div>
        </div>
        
        <div class="metapix-card metapix-stat-card">
            <div class="metapix-stat-icon">
                <span class="dashicons dashicons-performance"></span>
            </div>
            <div class="metapix-stat-content">
                <h3><?php echo number_format($stats['avg_performance'], 1); ?></h3>
                <p><?php _e('Avg Performance Score', 'metapix-ai'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        $activities = Database::get_optimization_history(null, 10);
        
        if (empty($activities)) {
            echo '<p>' . __('No recent activity found.', 'metapix-ai') . '</p>';
            return;
        }
        
        echo '<ul class="metapix-activity-list">';
        foreach ($activities as $activity) {
            $post_title = get_the_title($activity->post_id) ?: __('Unknown Post', 'metapix-ai');
            $time_ago = human_time_diff(strtotime($activity->created_at), current_time('timestamp'));
            
            echo '<li class="metapix-activity-item">';
            echo '<div class="activity-icon">';
            echo '<span class="dashicons dashicons-' . $this->get_activity_icon($activity->module) . '"></span>';
            echo '</div>';
            echo '<div class="activity-content">';
            echo '<p><strong>' . $this->get_activity_title($activity) . '</strong></p>';
            echo '<p class="activity-meta">' . $post_title . ' • ' . sprintf(__('%s ago', 'metapix-ai'), $time_ago) . '</p>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Render top performing posts
     */
    private function render_top_posts() {
        global $wpdb;
        $scores_table = $wpdb->prefix . 'metapix_seo_scores';
        
        $top_posts = $wpdb->get_results(
            "SELECT post_id, overall_score FROM $scores_table 
             ORDER BY overall_score DESC 
             LIMIT 5"
        );
        
        if (empty($top_posts)) {
            echo '<p>' . __('No analyzed posts found.', 'metapix-ai') . '</p>';
            return;
        }
        
        echo '<ul class="metapix-posts-list">';
        foreach ($top_posts as $post_data) {
            $post = get_post($post_data->post_id);
            if (!$post) continue;
            
            $score_class = $post_data->overall_score >= 80 ? 'good' : ($post_data->overall_score >= 60 ? 'ok' : 'poor');
            
            echo '<li class="metapix-post-item">';
            echo '<div class="post-score score-' . $score_class . '">' . round($post_data->overall_score) . '</div>';
            echo '<div class="post-content">';
            echo '<h4><a href="' . get_edit_post_link($post->ID) . '">' . $post->post_title . '</a></h4>';
            echo '<p class="post-meta">' . get_post_type_object($post->post_type)->labels->singular_name . '</p>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Render notifications
     */
    private function render_notifications() {
        $notifications = Database::get_notifications(null, false, 5);
        
        if (empty($notifications)) {
            echo '<p>' . __('No notifications.', 'metapix-ai') . '</p>';
            return;
        }
        
        echo '<ul class="metapix-notifications-list">';
        foreach ($notifications as $notification) {
            $priority_class = 'priority-' . $notification->priority;
            $read_class = $notification->is_read ? 'read' : 'unread';
            
            echo '<li class="metapix-notification-item ' . $priority_class . ' ' . $read_class . '">';
            echo '<div class="notification-content">';
            echo '<h4>' . $notification->title . '</h4>';
            echo '<p>' . $notification->message . '</p>';
            echo '<span class="notification-time">' . 
                 human_time_diff(strtotime($notification->created_at), current_time('timestamp')) . 
                 ' ' . __('ago', 'metapix-ai') . '</span>';
            echo '</div>';
            if (!$notification->is_read) {
                echo '<button class="mark-read-btn" data-id="' . $notification->id . '">×</button>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Get activity icon
     */
    private function get_activity_icon($module) {
        $icons = [
            'ImageSEO' => 'format-image',
            'ContentOptimizer' => 'edit',
            'PerformanceAnalyzer' => 'performance',
            'CompetitorIntelligence' => 'chart-bar',
            'LinkingStructure' => 'admin-links'
        ];
        
        return $icons[$module] ?? 'admin-generic';
    }
    
    /**
     * Get activity title
     */
    private function get_activity_title($activity) {
        $titles = [
            'ImageSEO' => __('Image SEO optimized', 'metapix-ai'),
            'ContentOptimizer' => __('Content analyzed', 'metapix-ai'),
            'PerformanceAnalyzer' => __('Performance checked', 'metapix-ai'),
            'CompetitorIntelligence' => __('Competitor analysis completed', 'metapix-ai'),
            'LinkingStructure' => __('Schema markup generated', 'metapix-ai')
        ];
        
        return $titles[$activity->module] ?? __('Optimization completed', 'metapix-ai');
    }
    
    /**
     * Get license status
     */
    private function get_license_status() {
        $license_key = get_option('metapix_ai_license_key', '');
        $plan = get_option('metapix_ai_plan', 'free');
        
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_license_usage';
        
        $usage = null;
        if (!empty($license_key)) {
            $usage = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE license_key = %s",
                $license_key
            ));
        }
        
        return [
            'plan' => $plan,
            'has_license' => !empty($license_key),
            'license_valid' => $usage ? $usage->status === 'active' : false,
            'sites_used' => $usage ? $usage->sites_used : 0,
            'sites_limit' => $usage ? $usage->sites_limit : 1,
            'api_calls_used' => $usage ? $usage->api_calls_used : 0,
            'api_calls_limit' => $usage ? $usage->api_calls_limit : 1000,
            'expires_at' => $usage ? $usage->expires_at : null
        ];
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_stats() {
        global $wpdb;
        
        // Get SEO score averages
        $scores_table = $wpdb->prefix . 'metapix_seo_scores';
        $score_stats = $wpdb->get_row(
            "SELECT 
                AVG(overall_score) as avg_overall,
                AVG(performance_score) as avg_performance,
                COUNT(*) as total_analyzed
             FROM $scores_table"
        );
        
        // Get optimization counts
        $optimization_table = $wpdb->prefix . 'metapix_optimization_history';
        $optimization_stats = $wpdb->get_row(
            "SELECT COUNT(*) as total_optimizations FROM $optimization_table 
             WHERE module = 'ImageSEO' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return [
            'avg_seo_score' => $score_stats ? round($score_stats->avg_overall, 1) : 0,
            'avg_performance' => $score_stats ? round($score_stats->avg_performance, 1) : 0,
            'total_analyzed' => $score_stats ? $score_stats->total_analyzed : 0,
            'images_optimized' => $optimization_stats ? $optimization_stats->total_optimizations : 0
        ];
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        ?>
        <div class="wrap metapix-admin-wrap">
            <h1><?php _e('MetaPix AI Settings', 'metapix-ai'); ?></h1>
            
            <form method="post" action="options.php" id="metapix-settings-form">
                <?php
                settings_fields('metapix_ai_settings');
                do_settings_sections('metapix_ai_settings');
                ?>
                
                <div class="metapix-settings-grid">
                    <!-- License Settings -->
                    <div class="metapix-card">
                        <h3><?php _e('License', 'metapix-ai'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('License Key', 'metapix-ai'); ?></th>
                                <td>
                                    <input type="password" name="metapix_ai_license_key" 
                                           value="<?php echo esc_attr(get_option('metapix_ai_license_key', '')); ?>" 
                                           class="regular-text" />
                                    <p class="description"><?php _e('Enter your MetaPix AI license key.', 'metapix-ai'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- AI Configuration -->
                    <div class="metapix-card">
                        <h3><?php _e('AI Configuration', 'metapix-ai'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('OpenAI API Key', 'metapix-ai'); ?></th>
                                <td>
                                    <input type="password" name="metapix_ai_openai_api_key" 
                                           value="<?php echo esc_attr(get_option('metapix_ai_openai_api_key', '')); ?>" 
                                           class="regular-text" />
                                    <p class="description"><?php _e('Required for AI-powered features like ALT text generation.', 'metapix-ai'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Operation Mode', 'metapix-ai'); ?></th>
                                <td>
                                    <select name="metapix_ai_mode">
                                        <option value="manual" <?php selected(get_option('metapix_ai_mode'), 'manual'); ?>>
                                            <?php _e('Manual', 'metapix-ai'); ?>
                                        </option>
                                        <option value="autonomous" <?php selected(get_option('metapix_ai_mode'), 'autonomous'); ?>>
                                            <?php _e('Autonomous', 'metapix-ai'); ?>
                                        </option>
                                    </select>
                                    <p class="description"><?php _e('Choose between manual approval or automatic optimization.', 'metapix-ai'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Optimization Settings -->
                    <div class="metapix-card">
                        <h3><?php _e('Optimization Settings', 'metapix-ai'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Auto-optimize Images', 'metapix-ai'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="metapix_ai_auto_optimize_images" value="1" 
                                               <?php checked(get_option('metapix_ai_auto_optimize_images'), 1); ?> />
                                        <?php _e('Automatically optimize images on upload', 'metapix-ai'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Auto-optimize Content', 'metapix-ai'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="metapix_ai_auto_optimize_content" value="1" 
                                               <?php checked(get_option('metapix_ai_auto_optimize_content'), 1); ?> />
                                        <?php _e('Automatically analyze content on publish', 'metapix-ai'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Auto Schema Markup', 'metapix-ai'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="metapix_ai_auto_schema" value="1" 
                                               <?php checked(get_option('metapix_ai_auto_schema'), 1); ?> />
                                        <?php _e('Automatically generate schema markup', 'metapix-ai'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Performance Monitoring -->
                    <div class="metapix-card">
                        <h3><?php _e('Performance Monitoring', 'metapix-ai'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('PageSpeed API Key', 'metapix-ai'); ?></th>
                                <td>
                                    <input type="text" name="metapix_ai_pagespeed_api_key" 
                                           value="<?php echo esc_attr(get_option('metapix_ai_pagespeed_api_key', '')); ?>" 
                                           class="regular-text" />
                                    <p class="description"><?php _e('Google PageSpeed Insights API key for performance monitoring.', 'metapix-ai'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Enable Monitoring', 'metapix-ai'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="metapix_ai_performance_monitoring" value="1" 
                                               <?php checked(get_option('metapix_ai_performance_monitoring'), 1); ?> />
                                        <?php _e('Monitor website performance automatically', 'metapix-ai'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Analytics Integration -->
                    <div class="metapix-card">
                        <h3><?php _e('Analytics Integration', 'metapix-ai'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Google Analytics 4 Property ID', 'metapix-ai'); ?></th>
                                <td>
                                    <input type="text" name="metapix_ai_ga4_property_id" 
                                           value="<?php echo esc_attr(get_option('metapix_ai_ga4_property_id', '')); ?>" 
                                           class="regular-text" placeholder="G-XXXXXXXXXX" />
                                    <p class="description"><?php _e('Connect Google Analytics 4 for enhanced reporting.', 'metapix-ai'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Reporting Settings -->
                    <div class="metapix-card">
                        <h3><?php _e('Reporting', 'metapix-ai'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Weekly Reports', 'metapix-ai'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="metapix_ai_weekly_reports" value="1" 
                                               <?php checked(get_option('metapix_ai_weekly_reports'), 1); ?> />
                                        <?php _e('Send weekly SEO optimization reports', 'metapix-ai'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics() {
        ?>
        <div class="wrap metapix-admin-wrap">
            <h1><?php _e('MetaPix AI Analytics', 'metapix-ai'); ?></h1>
            
            <div class="metapix-analytics-grid">
                <!-- Performance Overview -->
                <div class="metapix-card">
                    <h3><?php _e('Performance Overview', 'metapix-ai'); ?></h3>
                    <canvas id="performance-chart" width="400" height="200"></canvas>
                </div>
                
                <!-- SEO Score Distribution -->
                <div class="metapix-card">
                    <h3><?php _e('SEO Score Distribution', 'metapix-ai'); ?></h3>
                    <canvas id="score-distribution-chart" width="400" height="200"></canvas>
                </div>
                
                <!-- Module Performance -->
                <div class="metapix-card">
                    <h3><?php _e('Module Performance', 'metapix-ai'); ?></h3>
                    <div id="module-performance-stats">
                        <?php $this->render_module_performance(); ?>
                    </div>
                </div>
                
                <!-- Optimization Trends -->
                <div class="metapix-card">
                    <h3><?php _e('Optimization Trends', 'metapix-ai'); ?></h3>
                    <canvas id="optimization-trends-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render module performance stats
     */
    private function render_module_performance() {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_optimization_history';
        
        $module_stats = $wpdb->get_results(
            "SELECT 
                module,
                COUNT(*) as total_optimizations,
                AVG(score_after - score_before) as avg_improvement,
                AVG(ai_confidence) as avg_confidence
             FROM $table 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY module"
        );
        
        if (empty($module_stats)) {
            echo '<p>' . __('No optimization data available.', 'metapix-ai') . '</p>';
            return;
        }
        
        echo '<div class="module-stats-grid">';
        foreach ($module_stats as $stat) {
            $module_name = $this->get_module_display_name($stat->module);
            
            echo '<div class="module-stat-card">';
            echo '<h4>' . $module_name . '</h4>';
            echo '<div class="stat-row">';
            echo '<span class="stat-label">' . __('Optimizations:', 'metapix-ai') . '</span>';
            echo '<span class="stat-value">' . number_format($stat->total_optimizations) . '</span>';
            echo '</div>';
            echo '<div class="stat-row">';
            echo '<span class="stat-label">' . __('Avg Improvement:', 'metapix-ai') . '</span>';
            echo '<span class="stat-value">+' . number_format($stat->avg_improvement, 1) . '</span>';
            echo '</div>';
            echo '<div class="stat-row">';
            echo '<span class="stat-label">' . __('AI Confidence:', 'metapix-ai') . '</span>';
            echo '<span class="stat-value">' . number_format($stat->avg_confidence, 1) . '%</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    /**
     * Get module display name
     */
    private function get_module_display_name($module) {
        $names = [
            'ImageSEO' => __('Image SEO', 'metapix-ai'),
            'ContentOptimizer' => __('Content Optimizer', 'metapix-ai'),
            'PerformanceAnalyzer' => __('Performance Analyzer', 'metapix-ai'),
            'CompetitorIntelligence' => __('Competitor Intelligence', 'metapix-ai'),
            'LinkingStructure' => __('Linking & Structure', 'metapix-ai')
        ];
        
        return $names[$module] ?? $module;
    }
    
    /**
     * Render optimization history page
     */
    public function render_history() {
        ?>
        <div class="wrap metapix-admin-wrap">
            <h1><?php _e('Optimization History', 'metapix-ai'); ?></h1>
            
            <div class="metapix-history-filters">
                <select id="module-filter">
                    <option value=""><?php _e('All Modules', 'metapix-ai'); ?></option>
                    <option value="ImageSEO"><?php _e('Image SEO', 'metapix-ai'); ?></option>
                    <option value="ContentOptimizer"><?php _e('Content Optimizer', 'metapix-ai'); ?></option>
                    <option value="PerformanceAnalyzer"><?php _e('Performance Analyzer', 'metapix-ai'); ?></option>
                    <option value="CompetitorIntelligence"><?php _e('Competitor Intelligence', 'metapix-ai'); ?></option>
                </select>
                
                <select id="status-filter">
                    <option value=""><?php _e('All Statuses', 'metapix-ai'); ?></option>
                    <option value="pending"><?php _e('Pending', 'metapix-ai'); ?></option>
                    <option value="completed"><?php _e('Completed', 'metapix-ai'); ?></option>
                    <option value="failed"><?php _e('Failed', 'metapix-ai'); ?></option>
                </select>
                
                <button class="button" id="filter-history"><?php _e('Filter', 'metapix-ai'); ?></button>
            </div>
            
            <div id="optimization-history-table">
                <?php $this->render_history_table(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render history table
     */
    private function render_history_table() {
        $history = Database::get_optimization_history(null, 50);
        
        if (empty($history)) {
            echo '<p>' . __('No optimization history found.', 'metapix-ai') . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'metapix-ai'); ?></th>
                    <th><?php _e('Post', 'metapix-ai'); ?></th>
                    <th><?php _e('Module', 'metapix-ai'); ?></th>
                    <th><?php _e('Type', 'metapix-ai'); ?></th>
                    <th><?php _e('Status', 'metapix-ai'); ?></th>
                    <th><?php _e('Score Change', 'metapix-ai'); ?></th>
                    <th><?php _e('Confidence', 'metapix-ai'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $item): ?>
                    <tr>
                        <td><?php echo date('M j, Y H:i', strtotime($item->created_at)); ?></td>
                        <td>
                            <?php 
                            $post_title = get_the_title($item->post_id);
                            if ($post_title) {
                                echo '<a href="' . get_edit_post_link($item->post_id) . '">' . $post_title . '</a>';
                            } else {
                                echo __('Unknown Post', 'metapix-ai');
                            }
                            ?>
                        </td>
                        <td><?php echo $this->get_module_display_name($item->module); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $item->optimization_type)); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $item->status; ?>">
                                <?php echo ucfirst($item->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($item->score_after > $item->score_before): ?>
                                <span class="score-improvement">+<?php echo number_format($item->score_after - $item->score_before, 1); ?></span>
                            <?php else: ?>
                                <span class="score-neutral">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($item->ai_confidence, 1); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render bulk tools page
     */
    public function render_bulk_tools() {
        ?>
        <div class="wrap metapix-admin-wrap">
            <h1><?php _e('Bulk Tools', 'metapix-ai'); ?></h1>
            
            <div class="metapix-bulk-tools-grid">
                <!-- Bulk Content Analysis -->
                <div class="metapix-card">
                    <h3><?php _e('Bulk Content Analysis', 'metapix-ai'); ?></h3>
                    <p><?php _e('Analyze multiple posts for SEO optimization opportunities.', 'metapix-ai'); ?></p>
                    <div class="bulk-tool-options">
                        <label>
                            <input type="checkbox" name="post_types[]" value="post" checked> <?php _e('Posts', 'metapix-ai'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="post_types[]" value="page"> <?php _e('Pages', 'metapix-ai'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="post_types[]" value="product"> <?php _e('Products', 'metapix-ai'); ?>
                        </label>
                    </div>
                    <button class="button button-primary" id="bulk-analyze-content">
                        <?php _e('Start Analysis', 'metapix-ai'); ?>
                    </button>
                </div>
                
                <!-- Bulk Image Optimization -->
                <div class="metapix-card">
                    <h3><?php _e('Bulk Image Optimization', 'metapix-ai'); ?></h3>
                    <p><?php _e('Generate ALT text and optimize images across your site.', 'metapix-ai'); ?></p>
                    <div class="bulk-tool-options">
                        <label>
                            <input type="number" name="image_limit" value="50" min="1" max="500"> 
                            <?php _e('Images to process', 'metapix-ai'); ?>
                        </label>
                    </div>
                    <button class="button button-primary" id="bulk-optimize-images">
                        <?php _e('Start Optimization', 'metapix-ai'); ?>
                    </button>
                </div>
                
                <!-- Bulk Meta Tag Generation -->
                <div class="metapix-card">
                    <h3><?php _e('Bulk Meta Tag Generation', 'metapix-ai'); ?></h3>
                    <p><?php _e('Generate meta titles and descriptions for posts missing them.', 'metapix-ai'); ?></p>
                    <div class="bulk-tool-options">
                        <label>
                            <input type="checkbox" name="generate_titles" checked> <?php _e('Generate Meta Titles', 'metapix-ai'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="generate_descriptions" checked> <?php _e('Generate Meta Descriptions', 'metapix-ai'); ?>
                        </label>
                    </div>
                    <button class="button button-primary" id="bulk-generate-meta">
                        <?php _e('Start Generation', 'metapix-ai'); ?>
                    </button>
                </div>
                
                <!-- Bulk Performance Check -->
                <div class="metapix-card">
                    <h3><?php _e('Bulk Performance Check', 'metapix-ai'); ?></h3>
                    <p><?php _e('Check Core Web Vitals and performance metrics for multiple pages.', 'metapix-ai'); ?></p>
                    <div class="bulk-tool-options">
                        <label>
                            <select name="device_type">
                                <option value="desktop"><?php _e('Desktop', 'metapix-ai'); ?></option>
                                <option value="mobile"><?php _e('Mobile', 'metapix-ai'); ?></option>
                            </select>
                        </label>
                    </div>
                    <button class="button button-primary" id="bulk-performance-check">
                        <?php _e('Start Check', 'metapix-ai'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Progress Display -->
            <div id="bulk-progress" class="metapix-card" style="display: none;">
                <h3><?php _e('Processing...', 'metapix-ai'); ?></h3>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <p class="progress-status"></p>
            </div>
            
            <!-- Results Display -->
            <div id="bulk-results" class="metapix-card" style="display: none;">
                <h3><?php _e('Results', 'metapix-ai'); ?></h3>
                <div class="results-content"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Get dashboard stats
     */
    public function ajax_get_dashboard_stats() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $stats = $this->get_dashboard_stats();
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Get recent activity
     */
    public function ajax_get_recent_activity() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $activities = Database::get_optimization_history(null, 10);
        wp_send_json_success($activities);
    }
}