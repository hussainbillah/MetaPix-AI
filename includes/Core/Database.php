<?php

namespace MetaPixAI\Core;

/**
 * Database management class
 */
class Database {
    
    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'maybe_upgrade_db']);
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Optimization history table
        $table_optimization_history = $wpdb->prefix . 'metapix_optimization_history';
        $sql_optimization_history = "CREATE TABLE $table_optimization_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            optimization_type varchar(50) NOT NULL,
            module varchar(50) NOT NULL,
            old_value longtext,
            new_value longtext,
            status varchar(20) DEFAULT 'pending',
            score_before decimal(5,2) DEFAULT 0,
            score_after decimal(5,2) DEFAULT 0,
            ai_confidence decimal(5,2) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY optimization_type (optimization_type),
            KEY module (module),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // SEO scores table
        $table_seo_scores = $wpdb->prefix . 'metapix_seo_scores';
        $sql_seo_scores = "CREATE TABLE $table_seo_scores (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            overall_score decimal(5,2) DEFAULT 0,
            image_score decimal(5,2) DEFAULT 0,
            content_score decimal(5,2) DEFAULT 0,
            technical_score decimal(5,2) DEFAULT 0,
            performance_score decimal(5,2) DEFAULT 0,
            schema_score decimal(5,2) DEFAULT 0,
            last_analyzed datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY overall_score (overall_score),
            KEY last_analyzed (last_analyzed)
        ) $charset_collate;";
        
        // Performance metrics table
        $table_performance = $wpdb->prefix . 'metapix_performance_metrics';
        $sql_performance = "CREATE TABLE $table_performance (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            url varchar(500) NOT NULL,
            device varchar(20) DEFAULT 'desktop',
            lcp_score decimal(8,2) DEFAULT NULL,
            fid_score decimal(8,2) DEFAULT NULL,
            cls_score decimal(8,4) DEFAULT NULL,
            performance_score int(3) DEFAULT NULL,
            accessibility_score int(3) DEFAULT NULL,
            best_practices_score int(3) DEFAULT NULL,
            seo_score int(3) DEFAULT NULL,
            opportunities longtext,
            diagnostics longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY url (url(191)),
            KEY device (device),
            KEY performance_score (performance_score),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Competitor analysis table
        $table_competitors = $wpdb->prefix . 'metapix_competitor_analysis';
        $sql_competitors = "CREATE TABLE $table_competitors (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            competitor_url varchar(500) NOT NULL,
            position int(3) NOT NULL,
            domain_authority int(3) DEFAULT NULL,
            page_authority int(3) DEFAULT NULL,
            content_length int(10) DEFAULT NULL,
            images_count int(5) DEFAULT NULL,
            internal_links int(5) DEFAULT NULL,
            external_links int(5) DEFAULT NULL,
            schema_types longtext,
            meta_title varchar(255),
            meta_description text,
            h1_tags text,
            analyzed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY keyword (keyword),
            KEY competitor_url (competitor_url(191)),
            KEY position (position),
            KEY analyzed_at (analyzed_at)
        ) $charset_collate;";
        
        // Analytics data table
        $table_analytics = $wpdb->prefix . 'metapix_analytics_data';
        $sql_analytics = "CREATE TABLE $table_analytics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            date date NOT NULL,
            pageviews int(10) DEFAULT 0,
            unique_pageviews int(10) DEFAULT 0,
            bounce_rate decimal(5,2) DEFAULT 0,
            avg_session_duration decimal(8,2) DEFAULT 0,
            organic_traffic int(10) DEFAULT 0,
            click_through_rate decimal(5,2) DEFAULT 0,
            impressions int(10) DEFAULT 0,
            clicks int(10) DEFAULT 0,
            avg_position decimal(5,2) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY post_date (post_id, date),
            KEY post_id (post_id),
            KEY date (date),
            KEY organic_traffic (organic_traffic)
        ) $charset_collate;";
        
        // License and usage tracking
        $table_license = $wpdb->prefix . 'metapix_license_usage';
        $sql_license = "CREATE TABLE $table_license (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            license_key varchar(255) NOT NULL,
            domain varchar(255) NOT NULL,
            plan varchar(50) NOT NULL,
            sites_limit int(5) DEFAULT 1,
            sites_used int(5) DEFAULT 0,
            api_calls_limit int(10) DEFAULT 1000,
            api_calls_used int(10) DEFAULT 0,
            reset_date date NOT NULL,
            status varchar(20) DEFAULT 'active',
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY license_key (license_key),
            KEY domain (domain),
            KEY plan (plan),
            KEY status (status),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Notifications table
        $table_notifications = $wpdb->prefix . 'metapix_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data longtext,
            is_read tinyint(1) DEFAULT 0,
            priority varchar(20) DEFAULT 'normal',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_read (is_read),
            KEY priority (priority),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_optimization_history);
        dbDelta($sql_seo_scores);
        dbDelta($sql_performance);
        dbDelta($sql_competitors);
        dbDelta($sql_analytics);
        dbDelta($sql_license);
        dbDelta($sql_notifications);
        
        // Update database version
        update_option('metapix_ai_db_version', self::DB_VERSION);
    }
    
    /**
     * Maybe upgrade database
     */
    public function maybe_upgrade_db() {
        $current_version = get_option('metapix_ai_db_version', '0.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::create_tables();
        }
    }
    
    /**
     * Get optimization history
     */
    public static function get_optimization_history($post_id = null, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'metapix_optimization_history';
        $where = $post_id ? $wpdb->prepare("WHERE post_id = %d", $post_id) : "";
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }
    
    /**
     * Log optimization
     */
    public static function log_optimization($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'metapix_optimization_history';
        
        $defaults = [
            'post_id' => 0,
            'optimization_type' => '',
            'module' => '',
            'old_value' => '',
            'new_value' => '',
            'status' => 'pending',
            'score_before' => 0,
            'score_after' => 0,
            'ai_confidence' => 0
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        return $wpdb->insert($table, $data);
    }
    
    /**
     * Update SEO score
     */
    public static function update_seo_score($post_id, $scores) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'metapix_seo_scores';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE post_id = %d",
            $post_id
        ));
        
        $data = array_merge($scores, ['last_analyzed' => current_time('mysql')]);
        
        if ($existing) {
            return $wpdb->update($table, $data, ['post_id' => $post_id]);
        } else {
            $data['post_id'] = $post_id;
            return $wpdb->insert($table, $data);
        }
    }
    
    /**
     * Get SEO score
     */
    public static function get_seo_score($post_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'metapix_seo_scores';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d",
            $post_id
        ));
    }
    
    /**
     * Save performance metrics
     */
    public static function save_performance_metrics($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'metapix_performance_metrics';
        
        return $wpdb->insert($table, $data);
    }
    
    /**
     * Get performance metrics
     */
    public static function get_performance_metrics($post_id = null, $limit = 10) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'metapix_performance_metrics';
        $where = $post_id ? $wpdb->prepare("WHERE post_id = %d", $post_id) : "";
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Save competitor analysis
     */
    public static function save_competitor_analysis($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'metapix_competitor_analysis';
        
        return $wpdb->insert($table, $data);
    }
    
    /**
     * Get competitor analysis
     */
    public static function get_competitor_analysis($keyword, $limit = 10) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'metapix_competitor_analysis';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE keyword = %s ORDER BY position ASC LIMIT %d",
            $keyword,
            $limit
        ));
    }
    
    /**
     * Save analytics data
     */
    public static function save_analytics_data($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'metapix_analytics_data';
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for upsert behavior
        $fields = implode(', ', array_keys($data));
        $values = implode(', ', array_fill(0, count($data), '%s'));
        $updates = implode(', ', array_map(function($key) {
            return "$key = VALUES($key)";
        }, array_keys($data)));
        
        return $wpdb->query($wpdb->prepare(
            "INSERT INTO $table ($fields) VALUES ($values) ON DUPLICATE KEY UPDATE $updates",
            array_values($data)
        ));
    }
    
    /**
     * Create notification
     */
    public static function create_notification($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'metapix_notifications';
        
        $defaults = [
            'user_id' => get_current_user_id(),
            'type' => 'info',
            'title' => '',
            'message' => '',
            'data' => '',
            'is_read' => 0,
            'priority' => 'normal'
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        if (is_array($data['data']) || is_object($data['data'])) {
            $data['data'] = json_encode($data['data']);
        }
        
        return $wpdb->insert($table, $data);
    }
    
    /**
     * Get notifications
     */
    public static function get_notifications($user_id = null, $unread_only = false, $limit = 20) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'metapix_notifications';
        $where = [];
        $values = [];
        
        if ($user_id) {
            $where[] = "user_id = %d";
            $values[] = $user_id;
        }
        
        if ($unread_only) {
            $where[] = "is_read = 0";
        }
        
        $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
        $values[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table $where_clause ORDER BY created_at DESC LIMIT %d",
            ...$values
        ));
    }
}