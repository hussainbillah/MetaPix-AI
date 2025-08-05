<?php

namespace MetaPixAI\API;

use MetaPixAI\Core\Database;
use MetaPixAI\Services\OpenAI;
use MetaPixAI\Modules\ImageSEO;
use MetaPixAI\Modules\ContentOptimizer;

/**
 * REST API Endpoints
 */
class RestAPI {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'metapix-ai/v1';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Image SEO endpoints
        register_rest_route(self::NAMESPACE, '/images/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'analyze_image'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'attachment_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Attachment ID to analyze'
                ],
                'context' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Context for better analysis'
                ]
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/images/generate-alt', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_alt_text'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'attachment_id' => [
                    'required' => true,
                    'type' => 'integer'
                ],
                'context' => [
                    'required' => false,
                    'type' => 'string'
                ]
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/images/bulk-optimize', [
            'methods' => 'POST',
            'callback' => [$this, 'bulk_optimize_images'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_ids' => [
                    'required' => false,
                    'type' => 'array',
                    'description' => 'Array of post IDs to optimize'
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50
                ]
            ]
        ]);
        
        // Content optimization endpoints
        register_rest_route(self::NAMESPACE, '/content/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'analyze_content'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer'
                ]
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/content/generate-meta', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_meta_tags'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer'
                ],
                'keywords' => [
                    'required' => false,
                    'type' => 'array'
                ]
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/content/optimize', [
            'methods' => 'POST',
            'callback' => [$this, 'optimize_content'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer'
                ],
                'optimization_type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['meta_title', 'meta_description', 'content_improvement']
                ]
            ]
        ]);
        
        // SEO scores and analytics
        register_rest_route(self::NAMESPACE, '/scores/(?P<post_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_seo_scores'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer'
                ]
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/scores/bulk', [
            'methods' => 'GET',
            'callback' => [$this, 'get_bulk_seo_scores'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_ids' => [
                    'required' => false,
                    'type' => 'array'
                ],
                'post_type' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'post'
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 20
                ]
            ]
        ]);
        
        // Performance endpoints
        register_rest_route(self::NAMESPACE, '/performance/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'analyze_performance'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'url' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'uri'
                ],
                'device' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['desktop', 'mobile'],
                    'default' => 'desktop'
                ]
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/performance/(?P<post_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_performance_metrics'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        // Competitor analysis
        register_rest_route(self::NAMESPACE, '/competitors/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'analyze_competitors'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'keyword' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 5
                ]
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/competitors/(?P<keyword>[^/]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_competitor_data'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        // Optimization history
        register_rest_route(self::NAMESPACE, '/history', [
            'methods' => 'GET',
            'callback' => [$this, 'get_optimization_history'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id' => [
                    'required' => false,
                    'type' => 'integer'
                ],
                'module' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50
                ],
                'offset' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0
                ]
            ]
        ]);
        
        // Settings and configuration
        register_rest_route(self::NAMESPACE, '/settings', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_settings'],
            'permission_callback' => [$this, 'check_admin_permissions']
        ]);
        
        // License and usage
        register_rest_route(self::NAMESPACE, '/license/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_license_status'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        register_rest_route(self::NAMESPACE, '/license/usage', [
            'methods' => 'GET',
            'callback' => [$this, 'get_usage_stats'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        // Notifications
        register_rest_route(self::NAMESPACE, '/notifications', [
            'methods' => 'GET',
            'callback' => [$this, 'get_notifications'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'unread_only' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false
                ]
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/notifications/(?P<id>\d+)/read', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_notification_read'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        // Bulk operations
        register_rest_route(self::NAMESPACE, '/bulk/scan', [
            'methods' => 'POST',
            'callback' => [$this, 'bulk_scan_posts'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_type' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'post'
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10
                ],
                'modules' => [
                    'required' => false,
                    'type' => 'array',
                    'default' => ['content', 'images']
                ]
            ]
        ]);
        
        // Schema markup
        register_rest_route(self::NAMESPACE, '/schema/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_schema'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer'
                ],
                'schema_type' => [
                    'required' => false,
                    'type' => 'string'
                ]
            ]
        ]);
    }
    
    /**
     * Check permissions for API access
     */
    public function check_permissions($request) {
        // Check if user can edit posts
        if (!current_user_can('edit_posts')) {
            return new \WP_Error('forbidden', 'You do not have permission to access this endpoint.', ['status' => 403]);
        }
        
        // Check license status
        $license_key = get_option('metapix_ai_license_key', '');
        if (empty($license_key)) {
            return new \WP_Error('no_license', 'No valid license found.', ['status' => 401]);
        }
        
        // Check API rate limits
        if (!$this->check_rate_limits()) {
            return new \WP_Error('rate_limit_exceeded', 'API rate limit exceeded.', ['status' => 429]);
        }
        
        return true;
    }
    
    /**
     * Check admin permissions
     */
    public function check_admin_permissions($request) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('forbidden', 'Administrator access required.', ['status' => 403]);
        }
        
        return true;
    }
    
    /**
     * Check API rate limits
     */
    private function check_rate_limits() {
        $openai = new OpenAI();
        return $openai->has_api_calls_available();
    }
    
    /**
     * Analyze image endpoint
     */
    public function analyze_image($request) {
        $attachment_id = $request->get_param('attachment_id');
        $context = $request->get_param('context') ?? '';
        
        $image_seo = new ImageSEO();
        $analysis = $image_seo->analyze_image(['id' => $attachment_id, 'context' => $context]);
        
        if ($analysis) {
            return rest_ensure_response([
                'success' => true,
                'data' => $analysis,
                'message' => 'Image analysis completed successfully'
            ]);
        }
        
        return new \WP_Error('analysis_failed', 'Failed to analyze image', ['status' => 500]);
    }
    
    /**
     * Generate ALT text endpoint
     */
    public function generate_alt_text($request) {
        $attachment_id = $request->get_param('attachment_id');
        $context = $request->get_param('context') ?? '';
        
        $image_seo = new ImageSEO();
        $alt_text = $image_seo->generate_alt_text($attachment_id, $context);
        
        if ($alt_text) {
            return rest_ensure_response([
                'success' => true,
                'data' => ['alt_text' => $alt_text],
                'message' => 'ALT text generated successfully'
            ]);
        }
        
        return new \WP_Error('generation_failed', 'Failed to generate ALT text', ['status' => 500]);
    }
    
    /**
     * Bulk optimize images endpoint
     */
    public function bulk_optimize_images($request) {
        $post_ids = $request->get_param('post_ids') ?? [];
        $limit = $request->get_param('limit') ?? 50;
        
        if (empty($post_ids)) {
            // Get recent posts if no specific IDs provided
            $posts = get_posts([
                'numberposts' => $limit,
                'post_status' => 'publish',
                'fields' => 'ids'
            ]);
            $post_ids = $posts;
        }
        
        $image_seo = new ImageSEO();
        $results = [];
        $processed = 0;
        
        foreach ($post_ids as $post_id) {
            if ($processed >= $limit) break;
            
            $post = get_post($post_id);
            if (!$post) continue;
            
            $images = $image_seo->extract_images_from_post($post);
            foreach ($images as $image) {
                $analysis = $image_seo->analyze_image($image, $post_id);
                $results[] = [
                    'post_id' => $post_id,
                    'attachment_id' => $image['id'],
                    'analysis' => $analysis
                ];
                $processed++;
                
                if ($processed >= $limit) break;
            }
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => [
                'processed' => $processed,
                'results' => $results
            ],
            'message' => "Processed {$processed} images"
        ]);
    }
    
    /**
     * Analyze content endpoint
     */
    public function analyze_content($request) {
        $post_id = $request->get_param('post_id');
        
        $content_optimizer = new ContentOptimizer();
        $analysis = $content_optimizer->perform_content_analysis($post_id);
        
        if ($analysis) {
            return rest_ensure_response([
                'success' => true,
                'data' => $analysis,
                'message' => 'Content analysis completed successfully'
            ]);
        }
        
        return new \WP_Error('analysis_failed', 'Failed to analyze content', ['status' => 500]);
    }
    
    /**
     * Generate meta tags endpoint
     */
    public function generate_meta_tags($request) {
        $post_id = $request->get_param('post_id');
        $keywords = $request->get_param('keywords') ?? [];
        
        $post = get_post($post_id);
        if (!$post) {
            return new \WP_Error('post_not_found', 'Post not found', ['status' => 404]);
        }
        
        $openai = new OpenAI();
        
        try {
            $meta_tags = $openai->generate_meta_tags($post->post_title, $post->post_content, $keywords);
            
            return rest_ensure_response([
                'success' => true,
                'data' => $meta_tags,
                'message' => 'Meta tags generated successfully'
            ]);
            
        } catch (\Exception $e) {
            return new \WP_Error('generation_failed', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Optimize content endpoint
     */
    public function optimize_content($request) {
        $post_id = $request->get_param('post_id');
        $optimization_type = $request->get_param('optimization_type');
        
        $post = get_post($post_id);
        if (!$post) {
            return new \WP_Error('post_not_found', 'Post not found', ['status' => 404]);
        }
        
        $openai = new OpenAI();
        
        try {
            $context = [
                'title' => $post->post_title,
                'keywords' => []
            ];
            
            // Get keywords from SEO plugins
            $yoast_keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            $rankmath_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            
            if ($yoast_keyword) $context['keywords'][] = $yoast_keyword;
            if ($rankmath_keyword) $context['keywords'][] = $rankmath_keyword;
            
            $optimization = $openai->optimize_content($post->post_content, $optimization_type, $context);
            
            return rest_ensure_response([
                'success' => true,
                'data' => $optimization,
                'message' => 'Content optimization completed'
            ]);
            
        } catch (\Exception $e) {
            return new \WP_Error('optimization_failed', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Get SEO scores endpoint
     */
    public function get_seo_scores($request) {
        $post_id = $request->get_param('post_id');
        
        $scores = Database::get_seo_score($post_id);
        
        if ($scores) {
            return rest_ensure_response([
                'success' => true,
                'data' => $scores,
                'message' => 'SEO scores retrieved successfully'
            ]);
        }
        
        return new \WP_Error('no_scores', 'No SEO scores found for this post', ['status' => 404]);
    }
    
    /**
     * Get bulk SEO scores endpoint
     */
    public function get_bulk_seo_scores($request) {
        $post_ids = $request->get_param('post_ids');
        $post_type = $request->get_param('post_type');
        $limit = $request->get_param('limit');
        
        global $wpdb;
        $scores_table = $wpdb->prefix . 'metapix_seo_scores';
        
        if (!empty($post_ids)) {
            $post_ids_str = implode(',', array_map('intval', $post_ids));
            $results = $wpdb->get_results(
                "SELECT * FROM $scores_table WHERE post_id IN ($post_ids_str)"
            );
        } else {
            // Get recent posts of specified type
            $posts_query = new \WP_Query([
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'fields' => 'ids'
            ]);
            
            if ($posts_query->have_posts()) {
                $post_ids_str = implode(',', $posts_query->posts);
                $results = $wpdb->get_results(
                    "SELECT * FROM $scores_table WHERE post_id IN ($post_ids_str)"
                );
            } else {
                $results = [];
            }
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $results,
            'message' => 'Bulk SEO scores retrieved successfully'
        ]);
    }
    
    /**
     * Get optimization history endpoint
     */
    public function get_optimization_history($request) {
        $post_id = $request->get_param('post_id');
        $module = $request->get_param('module');
        $limit = $request->get_param('limit');
        $offset = $request->get_param('offset');
        
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_optimization_history';
        
        $where_conditions = [];
        $where_values = [];
        
        if ($post_id) {
            $where_conditions[] = 'post_id = %d';
            $where_values[] = $post_id;
        }
        
        if ($module) {
            $where_conditions[] = 'module = %s';
            $where_values[] = $module;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
            ...$where_values
        ));
        
        return rest_ensure_response([
            'success' => true,
            'data' => $results,
            'message' => 'Optimization history retrieved successfully'
        ]);
    }
    
    /**
     * Handle settings endpoint
     */
    public function handle_settings($request) {
        if ($request->get_method() === 'GET') {
            // Get settings
            $settings = [
                'mode' => get_option('metapix_ai_mode', 'manual'),
                'auto_optimize_images' => get_option('metapix_ai_auto_optimize_images', false),
                'auto_optimize_content' => get_option('metapix_ai_auto_optimize_content', false),
                'auto_schema' => get_option('metapix_ai_auto_schema', false),
                'performance_monitoring' => get_option('metapix_ai_performance_monitoring', true),
                'weekly_reports' => get_option('metapix_ai_weekly_reports', true),
                'openai_configured' => !empty(get_option('metapix_ai_openai_api_key', '')),
                'pagespeed_configured' => !empty(get_option('metapix_ai_pagespeed_api_key', '')),
                'ga4_configured' => !empty(get_option('metapix_ai_ga4_property_id', ''))
            ];
            
            return rest_ensure_response([
                'success' => true,
                'data' => $settings,
                'message' => 'Settings retrieved successfully'
            ]);
            
        } else {
            // Update settings
            $params = $request->get_json_params();
            
            $allowed_settings = [
                'metapix_ai_mode',
                'metapix_ai_auto_optimize_images',
                'metapix_ai_auto_optimize_content',
                'metapix_ai_auto_schema',
                'metapix_ai_performance_monitoring',
                'metapix_ai_weekly_reports'
            ];
            
            foreach ($params as $key => $value) {
                if (in_array($key, $allowed_settings)) {
                    update_option($key, $value);
                }
            }
            
            return rest_ensure_response([
                'success' => true,
                'message' => 'Settings updated successfully'
            ]);
        }
    }
    
    /**
     * Get license status endpoint
     */
    public function get_license_status($request) {
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
        
        $status = [
            'plan' => $plan,
            'has_license' => !empty($license_key),
            'license_valid' => $usage ? $usage->status === 'active' : false,
            'sites_used' => $usage ? $usage->sites_used : 0,
            'sites_limit' => $usage ? $usage->sites_limit : 1,
            'api_calls_used' => $usage ? $usage->api_calls_used : 0,
            'api_calls_limit' => $usage ? $usage->api_calls_limit : 1000,
            'expires_at' => $usage ? $usage->expires_at : null
        ];
        
        return rest_ensure_response([
            'success' => true,
            'data' => $status,
            'message' => 'License status retrieved successfully'
        ]);
    }
    
    /**
     * Get usage statistics endpoint
     */
    public function get_usage_stats($request) {
        global $wpdb;
        
        // Get optimization counts
        $optimization_table = $wpdb->prefix . 'metapix_optimization_history';
        $optimization_stats = $wpdb->get_results(
            "SELECT module, COUNT(*) as count FROM $optimization_table 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
             GROUP BY module"
        );
        
        // Get SEO score averages
        $scores_table = $wpdb->prefix . 'metapix_seo_scores';
        $score_stats = $wpdb->get_row(
            "SELECT 
                AVG(overall_score) as avg_overall,
                AVG(content_score) as avg_content,
                AVG(image_score) as avg_image,
                AVG(performance_score) as avg_performance,
                COUNT(*) as total_analyzed
             FROM $scores_table"
        );
        
        return rest_ensure_response([
            'success' => true,
            'data' => [
                'optimization_stats' => $optimization_stats,
                'score_stats' => $score_stats,
                'period' => '30 days'
            ],
            'message' => 'Usage statistics retrieved successfully'
        ]);
    }
    
    /**
     * Get notifications endpoint
     */
    public function get_notifications($request) {
        $unread_only = $request->get_param('unread_only');
        $user_id = get_current_user_id();
        
        $notifications = Database::get_notifications($user_id, $unread_only);
        
        return rest_ensure_response([
            'success' => true,
            'data' => $notifications,
            'message' => 'Notifications retrieved successfully'
        ]);
    }
    
    /**
     * Mark notification as read endpoint
     */
    public function mark_notification_read($request) {
        $notification_id = $request->get_param('id');
        
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_notifications';
        
        $result = $wpdb->update(
            $table,
            ['is_read' => 1],
            ['id' => $notification_id],
            ['%d'],
            ['%d']
        );
        
        if ($result !== false) {
            return rest_ensure_response([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        }
        
        return new \WP_Error('update_failed', 'Failed to update notification', ['status' => 500]);
    }
    
    /**
     * Bulk scan posts endpoint
     */
    public function bulk_scan_posts($request) {
        $post_type = $request->get_param('post_type');
        $limit = $request->get_param('limit');
        $modules = $request->get_param('modules');
        
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => $limit,
            'meta_query' => [
                [
                    'key' => '_metapix_content_analysis',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
        
        $results = [];
        $image_seo = new ImageSEO();
        $content_optimizer = new ContentOptimizer();
        
        foreach ($posts as $post) {
            $post_results = ['post_id' => $post->ID];
            
            if (in_array('content', $modules)) {
                $content_analysis = $content_optimizer->perform_content_analysis($post->ID);
                $post_results['content_analysis'] = $content_analysis;
            }
            
            if (in_array('images', $modules)) {
                $images = $image_seo->extract_images_from_post($post);
                $image_results = [];
                
                foreach ($images as $image) {
                    $image_analysis = $image_seo->analyze_image($image, $post->ID);
                    $image_results[] = $image_analysis;
                }
                
                $post_results['image_analysis'] = $image_results;
            }
            
            $results[] = $post_results;
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => [
                'processed' => count($results),
                'results' => $results
            ],
            'message' => 'Bulk scan completed successfully'
        ]);
    }
    
    /**
     * Generate schema markup endpoint
     */
    public function generate_schema($request) {
        $post_id = $request->get_param('post_id');
        $schema_type = $request->get_param('schema_type');
        
        $post = get_post($post_id);
        if (!$post) {
            return new \WP_Error('post_not_found', 'Post not found', ['status' => 404]);
        }
        
        $openai = new OpenAI();
        
        try {
            $metadata = [
                'title' => $post->post_title,
                'date_published' => $post->post_date,
                'date_modified' => $post->post_modified,
                'author' => get_the_author_meta('display_name', $post->post_author),
                'featured_image' => get_the_post_thumbnail_url($post_id, 'full')
            ];
            
            $content_type = $schema_type ?: $post->post_type;
            $schema = $openai->generate_schema_markup($content_type, $post->post_content, $metadata);
            
            return rest_ensure_response([
                'success' => true,
                'data' => $schema,
                'message' => 'Schema markup generated successfully'
            ]);
            
        } catch (\Exception $e) {
            return new \WP_Error('generation_failed', $e->getMessage(), ['status' => 500]);
        }
    }
}