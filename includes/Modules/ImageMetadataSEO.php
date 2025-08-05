<?php

namespace MetaPixAI\Modules;

use MetaPixAI\Core\Database;
use MetaPixAI\Core\Credits;
use MetaPixAI\Core\UserRoles;
use MetaPixAI\Services\OpenAI;

/**
 * Enhanced Image Metadata SEO Module
 */
class ImageMetadataSEO {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    /**
     * Initialize hooks
     */
    public function init() {
        // Image processing hooks
        add_action('add_attachment', [$this, 'process_new_image']);
        add_action('edit_attachment', [$this, 'process_updated_image']);
        
        // AJAX handlers
        add_action('wp_ajax_metapix_generate_smart_alt', [$this, 'ajax_generate_smart_alt']);
        add_action('wp_ajax_metapix_optimize_filename', [$this, 'ajax_optimize_filename']);
        add_action('wp_ajax_metapix_generate_og_tags', [$this, 'ajax_generate_og_tags']);
        add_action('wp_ajax_metapix_bulk_optimize_metadata', [$this, 'ajax_bulk_optimize_metadata']);
        add_action('wp_ajax_metapix_analyze_image_seo', [$this, 'ajax_analyze_image_seo']);
        
        // Admin interface hooks
        add_action('add_meta_boxes', [$this, 'add_image_seo_meta_boxes']);
        add_action('save_post', [$this, 'save_image_metadata']);
        add_filter('attachment_fields_to_edit', [$this, 'add_advanced_seo_fields'], 10, 2);
        add_filter('attachment_fields_to_save', [$this, 'save_advanced_seo_fields'], 10, 2);
        
        // Frontend optimization
        add_filter('wp_get_attachment_image_attributes', [$this, 'enhance_image_attributes'], 10, 3);
        add_filter('the_content', [$this, 'optimize_content_images']);
        
        // SEO enhancements
        add_action('wp_head', [$this, 'add_og_image_tags']);
        add_action('wp_head', [$this, 'add_structured_data']);
    }
    
    /**
     * Process new image upload
     */
    public function process_new_image($attachment_id) {
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Check if user has credits for auto-optimization
        if (!Credits::has_credits($user_id, 1)) {
            return;
        }
        
        // Get auto-optimization setting
        $auto_optimize = get_option('metapix_ai_auto_optimize_images', false);
        
        if ($auto_optimize) {
            $this->auto_optimize_image($attachment_id, $user_id);
        } else {
            // Just analyze and store recommendations
            $this->analyze_image_metadata($attachment_id);
        }
    }
    
    /**
     * Auto-optimize image metadata
     */
    private function auto_optimize_image($attachment_id, $user_id) {
        $analysis = $this->analyze_image_metadata($attachment_id);
        
        if (!$analysis['needs_optimization']) {
            return;
        }
        
        $credits_used = 0;
        $optimizations = [];
        
        // Generate ALT text if missing
        if (empty($analysis['alt_text']) || $analysis['alt_quality_score'] < 50) {
            $alt_text = $this->generate_smart_alt_text($attachment_id);
            if ($alt_text) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                $optimizations[] = 'alt_text_generated';
                $credits_used++;
            }
        }
        
        // Generate title if poor quality
        if ($analysis['title_quality_score'] < 50) {
            $title = $this->generate_seo_title($attachment_id);
            if ($title) {
                wp_update_post([
                    'ID' => $attachment_id,
                    'post_title' => $title
                ]);
                $optimizations[] = 'title_optimized';
                $credits_used++;
            }
        }
        
        // Generate caption if missing
        if (empty($analysis['caption'])) {
            $caption = $this->generate_seo_caption($attachment_id);
            if ($caption) {
                wp_update_post([
                    'ID' => $attachment_id,
                    'post_excerpt' => $caption
                ]);
                $optimizations[] = 'caption_generated';
                $credits_used++;
            }
        }
        
        // Optimize filename if poor
        if ($analysis['filename_score'] < 50) {
            $new_filename = $this->suggest_seo_filename($attachment_id);
            if ($new_filename) {
                update_post_meta($attachment_id, '_metapix_suggested_filename', $new_filename);
                $optimizations[] = 'filename_suggested';
            }
        }
        
        // Deduct credits
        if ($credits_used > 0) {
            Credits::deduct_credits($user_id, $credits_used, 'auto_image_optimization', $attachment_id);
        }
        
        // Log optimization
        Database::log_optimization([
            'post_id' => $attachment_id,
            'optimization_type' => 'image_metadata_auto',
            'module' => 'ImageMetadataSEO',
            'old_value' => json_encode($analysis),
            'new_value' => json_encode($optimizations),
            'status' => 'completed'
        ]);
        
        // Record generation
        $this->record_image_generation($attachment_id, $user_id, $optimizations, $credits_used);
    }
    
    /**
     * Analyze image metadata quality
     */
    public function analyze_image_metadata($attachment_id) {
        $attachment = get_post($attachment_id);
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        $analysis = [
            'attachment_id' => $attachment_id,
            'filename' => basename($attachment->guid),
            'title' => $attachment->post_title,
            'alt_text' => $alt_text,
            'caption' => $attachment->post_excerpt,
            'description' => $attachment->post_content,
            'file_size' => $metadata['filesize'] ?? 0,
            'dimensions' => [
                'width' => $metadata['width'] ?? 0,
                'height' => $metadata['height'] ?? 0
            ],
            'needs_optimization' => false
        ];
        
        // Analyze filename quality
        $analysis['filename_score'] = $this->analyze_filename_quality($analysis['filename']);
        
        // Analyze ALT text quality
        $analysis['alt_quality_score'] = $this->analyze_alt_text_quality($alt_text);
        
        // Analyze title quality
        $analysis['title_quality_score'] = $this->analyze_title_quality($analysis['title']);
        
        // Check if optimization is needed
        $analysis['needs_optimization'] = (
            $analysis['filename_score'] < 70 ||
            $analysis['alt_quality_score'] < 70 ||
            $analysis['title_quality_score'] < 70 ||
            empty($analysis['caption'])
        );
        
        // SEO recommendations
        $analysis['recommendations'] = $this->generate_seo_recommendations($analysis);
        
        // Overall SEO score
        $analysis['overall_seo_score'] = $this->calculate_overall_seo_score($analysis);
        
        return $analysis;
    }
    
    /**
     * Generate smart ALT text using AI
     */
    public function generate_smart_alt_text($attachment_id) {
        $openai = new OpenAI();
        
        if (!$openai->is_configured()) {
            return false;
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        $context = $this->get_image_context($attachment_id);
        
        $prompt = $this->build_alt_text_prompt($context);
        
        try {
            $response = $openai->analyze_image($image_url, $prompt);
            
            if ($response && isset($response['alt_text'])) {
                $alt_text = $this->clean_and_validate_alt_text($response['alt_text']);
                return $alt_text;
            }
        } catch (Exception $e) {
            error_log('MetaPix AI: ALT text generation failed - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Generate SEO-optimized title
     */
    public function generate_seo_title($attachment_id) {
        $openai = new OpenAI();
        
        if (!$openai->is_configured()) {
            return false;
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        $context = $this->get_image_context($attachment_id);
        
        $prompt = "Analyze this image and generate a concise, SEO-friendly title (max 60 characters) that describes the main subject. Context: {$context['post_title']} - {$context['post_excerpt']}. Focus on keywords that would help this image rank in search results.";
        
        try {
            $response = $openai->analyze_image($image_url, $prompt);
            
            if ($response && isset($response['title'])) {
                return sanitize_text_field($response['title']);
            }
        } catch (Exception $e) {
            error_log('MetaPix AI: Title generation failed - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Generate SEO caption
     */
    public function generate_seo_caption($attachment_id) {
        $openai = new OpenAI();
        
        if (!$openai->is_configured()) {
            return false;
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        $context = $this->get_image_context($attachment_id);
        
        $prompt = "Create an engaging, SEO-friendly caption (100-150 characters) for this image that would work well on social media and search results. Include relevant keywords from context: {$context['post_title']}";
        
        try {
            $response = $openai->analyze_image($image_url, $prompt);
            
            if ($response && isset($response['caption'])) {
                return sanitize_textarea_field($response['caption']);
            }
        } catch (Exception $e) {
            error_log('MetaPix AI: Caption generation failed - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Suggest SEO-friendly filename
     */
    public function suggest_seo_filename($attachment_id) {
        $attachment = get_post($attachment_id);
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $context = $this->get_image_context($attachment_id);
        
        // Use ALT text as base for filename
        $base_text = $alt_text ?: $attachment->post_title;
        
        if (empty($base_text)) {
            $base_text = $context['post_title'] ?: 'image';
        }
        
        // Clean and optimize filename
        $filename = strtolower($base_text);
        $filename = preg_replace('/[^a-z0-9\s-]/', '', $filename);
        $filename = preg_replace('/[\s-]+/', '-', $filename);
        $filename = trim($filename, '-');
        $filename = substr($filename, 0, 50); // Limit length
        
        // Add file extension
        $file_info = pathinfo($attachment->guid);
        $extension = $file_info['extension'] ?? 'jpg';
        
        return $filename . '.' . $extension;
    }
    
    /**
     * Get image context from parent post
     */
    private function get_image_context($attachment_id) {
        $attachment = get_post($attachment_id);
        $context = [
            'post_title' => '',
            'post_content' => '',
            'post_excerpt' => '',
            'keywords' => []
        ];
        
        if ($attachment->post_parent) {
            $parent = get_post($attachment->post_parent);
            if ($parent) {
                $context['post_title'] = $parent->post_title;
                $context['post_content'] = wp_strip_all_tags($parent->post_content);
                $context['post_excerpt'] = $parent->post_excerpt;
                
                // Extract keywords from content
                $context['keywords'] = $this->extract_keywords_from_content($context['post_content']);
            }
        }
        
        return $context;
    }
    
    /**
     * Build ALT text generation prompt
     */
    private function build_alt_text_prompt($context) {
        $prompt = "Analyze this image and generate descriptive, SEO-friendly ALT text (50-125 characters) that accurately describes what you see. ";
        
        if (!empty($context['post_title'])) {
            $prompt .= "This image is from a post titled: '{$context['post_title']}'. ";
        }
        
        if (!empty($context['keywords'])) {
            $keywords = implode(', ', array_slice($context['keywords'], 0, 5));
            $prompt .= "Try to naturally incorporate these relevant keywords if they match the image content: {$keywords}. ";
        }
        
        $prompt .= "Focus on accessibility and SEO. Avoid starting with 'Image of' or 'Picture of'. Be specific and descriptive.";
        
        return $prompt;
    }
    
    /**
     * Clean and validate ALT text
     */
    private function clean_and_validate_alt_text($alt_text) {
        $alt_text = sanitize_text_field($alt_text);
        $alt_text = trim($alt_text);
        
        // Remove common prefixes
        $prefixes = ['Image of ', 'Picture of ', 'Photo of ', 'A photo of ', 'An image of '];
        foreach ($prefixes as $prefix) {
            if (stripos($alt_text, $prefix) === 0) {
                $alt_text = substr($alt_text, strlen($prefix));
                break;
            }
        }
        
        // Ensure proper length
        if (strlen($alt_text) > 125) {
            $alt_text = substr($alt_text, 0, 122) . '...';
        }
        
        return $alt_text;
    }
    
    /**
     * Analyze filename quality
     */
    private function analyze_filename_quality($filename) {
        $score = 100;
        
        // Check for generic names
        $generic_patterns = ['/^img_\d+/', '/^image\d*/', '/^photo\d*/', '/^dsc\d+/', '/^screenshot/i'];
        foreach ($generic_patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                $score -= 40;
                break;
            }
        }
        
        // Check for underscores (prefer hyphens)
        if (strpos($filename, '_') !== false) {
            $score -= 10;
        }
        
        // Check for spaces
        if (strpos($filename, ' ') !== false) {
            $score -= 15;
        }
        
        // Check for special characters
        if (preg_match('/[^a-zA-Z0-9.-]/', $filename)) {
            $score -= 10;
        }
        
        // Check length (too short or too long)
        $name_without_ext = pathinfo($filename, PATHINFO_FILENAME);
        if (strlen($name_without_ext) < 3) {
            $score -= 20;
        } elseif (strlen($name_without_ext) > 50) {
            $score -= 10;
        }
        
        return max(0, $score);
    }
    
    /**
     * Analyze ALT text quality
     */
    private function analyze_alt_text_quality($alt_text) {
        if (empty($alt_text)) {
            return 0;
        }
        
        $score = 100;
        
        // Check length
        $length = strlen($alt_text);
        if ($length < 10) {
            $score -= 30;
        } elseif ($length > 125) {
            $score -= 20;
        }
        
        // Check for generic text
        $generic_terms = ['image', 'picture', 'photo', 'img'];
        foreach ($generic_terms as $term) {
            if (stripos($alt_text, $term) === 0) {
                $score -= 25;
                break;
            }
        }
        
        // Check for descriptiveness (basic heuristics)
        $descriptive_words = ['showing', 'featuring', 'displaying', 'containing', 'with', 'in', 'on', 'at'];
        $has_descriptive = false;
        foreach ($descriptive_words as $word) {
            if (stripos($alt_text, $word) !== false) {
                $has_descriptive = true;
                break;
            }
        }
        
        if (!$has_descriptive && $length > 20) {
            $score -= 10;
        }
        
        return max(0, $score);
    }
    
    /**
     * Analyze title quality
     */
    private function analyze_title_quality($title) {
        if (empty($title)) {
            return 0;
        }
        
        $score = 100;
        
        // Check for generic titles
        $generic_patterns = ['/^img_\d+/', '/^image\d*/', '/^untitled/i', '/^new image/i'];
        foreach ($generic_patterns as $pattern) {
            if (preg_match($pattern, $title)) {
                $score -= 50;
                break;
            }
        }
        
        // Check length
        $length = strlen($title);
        if ($length < 5) {
            $score -= 30;
        } elseif ($length > 60) {
            $score -= 15;
        }
        
        return max(0, $score);
    }
    
    /**
     * Generate SEO recommendations
     */
    private function generate_seo_recommendations($analysis) {
        $recommendations = [];
        
        if ($analysis['filename_score'] < 70) {
            $recommendations[] = [
                'type' => 'filename',
                'priority' => 'high',
                'message' => 'Optimize filename for SEO with descriptive keywords',
                'action' => 'optimize_filename'
            ];
        }
        
        if ($analysis['alt_quality_score'] < 70) {
            $recommendations[] = [
                'type' => 'alt_text',
                'priority' => 'high',
                'message' => 'Generate or improve ALT text for accessibility and SEO',
                'action' => 'generate_alt_text'
            ];
        }
        
        if ($analysis['title_quality_score'] < 70) {
            $recommendations[] = [
                'type' => 'title',
                'priority' => 'medium',
                'message' => 'Improve image title with SEO-friendly description',
                'action' => 'optimize_title'
            ];
        }
        
        if (empty($analysis['caption'])) {
            $recommendations[] = [
                'type' => 'caption',
                'priority' => 'medium',
                'message' => 'Add engaging caption for social media optimization',
                'action' => 'generate_caption'
            ];
        }
        
        if ($analysis['file_size'] > 500000) { // 500KB
            $recommendations[] = [
                'type' => 'compression',
                'priority' => 'medium',
                'message' => 'Consider compressing image for better performance',
                'action' => 'compress_image'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Calculate overall SEO score
     */
    private function calculate_overall_seo_score($analysis) {
        $weights = [
            'filename_score' => 0.25,
            'alt_quality_score' => 0.35,
            'title_quality_score' => 0.20,
            'has_caption' => 0.20
        ];
        
        $score = 0;
        $score += $analysis['filename_score'] * $weights['filename_score'];
        $score += $analysis['alt_quality_score'] * $weights['alt_quality_score'];
        $score += $analysis['title_quality_score'] * $weights['title_quality_score'];
        $score += (empty($analysis['caption']) ? 0 : 100) * $weights['has_caption'];
        
        return round($score, 2);
    }
    
    /**
     * Record image generation
     */
    private function record_image_generation($attachment_id, $user_id, $optimizations, $credits_used) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_image_generations';
        
        $attachment = get_post($attachment_id);
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'attachment_id' => $attachment_id,
            'prompt' => 'Auto-optimization: ' . implode(', ', $optimizations),
            'alt_text' => $alt_text,
            'title' => $attachment->post_title,
            'caption' => $attachment->post_excerpt,
            'filename_original' => basename($attachment->guid),
            'credits_used' => $credits_used,
            'status' => 'completed',
            'moderation_status' => 'approved'
        ]);
    }
    
    /**
     * Extract keywords from content
     */
    private function extract_keywords_from_content($content) {
        // Simple keyword extraction (can be enhanced with NLP)
        $words = str_word_count(strtolower($content), 1);
        $word_count = array_count_values($words);
        
        // Filter out common words
        $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'];
        
        foreach ($stop_words as $stop_word) {
            unset($word_count[$stop_word]);
        }
        
        // Filter words that are too short
        $word_count = array_filter($word_count, function($word) {
            return strlen($word) > 3;
        }, ARRAY_FILTER_USE_KEY);
        
        // Sort by frequency and return top keywords
        arsort($word_count);
        return array_keys(array_slice($word_count, 0, 10));
    }
    
    /**
     * AJAX: Generate smart ALT text
     */
    public function ajax_generate_smart_alt() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $attachment_id = intval($_POST['attachment_id']);
        
        if (!UserRoles::user_can($user_id, 'generate_images')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        if (!Credits::has_credits($user_id, 1)) {
            wp_send_json_error(['message' => 'Insufficient credits']);
        }
        
        $alt_text = $this->generate_smart_alt_text($attachment_id);
        
        if ($alt_text) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
            Credits::deduct_credits($user_id, 1, 'alt_text_generation', $attachment_id);
            
            wp_send_json_success([
                'alt_text' => $alt_text,
                'message' => 'ALT text generated successfully'
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to generate ALT text']);
        }
    }
    
    /**
     * AJAX: Analyze image SEO
     */
    public function ajax_analyze_image_seo() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $attachment_id = intval($_POST['attachment_id']);
        $analysis = $this->analyze_image_metadata($attachment_id);
        
        wp_send_json_success(['analysis' => $analysis]);
    }
    
    /**
     * Add image SEO meta boxes
     */
    public function add_image_seo_meta_boxes() {
        add_meta_box(
            'metapix-image-seo',
            'MetaPix AI - Image SEO',
            [$this, 'render_image_seo_meta_box'],
            'attachment',
            'normal',
            'high'
        );
    }
    
    /**
     * Render image SEO meta box
     */
    public function render_image_seo_meta_box($post) {
        if (!wp_attachment_is_image($post->ID)) {
            return;
        }
        
        $analysis = $this->analyze_image_metadata($post->ID);
        $user_id = get_current_user_id();
        $credits = Credits::get_user_credits($user_id);
        
        wp_nonce_field('metapix_image_seo_nonce', 'metapix_image_seo_nonce');
        
        echo '<div id="metapix-image-seo-dashboard">';
        echo '<div class="metapix-seo-score">';
        echo '<h4>SEO Score: <span class="score-badge score-' . $this->get_score_class($analysis['overall_seo_score']) . '">' . $analysis['overall_seo_score'] . '/100</span></h4>';
        echo '</div>';
        
        echo '<div class="metapix-analysis-grid">';
        
        // Filename analysis
        echo '<div class="analysis-item">';
        echo '<h5>Filename <span class="score">' . $analysis['filename_score'] . '/100</span></h5>';
        echo '<p>Current: <code>' . $analysis['filename'] . '</code></p>';
        if ($analysis['filename_score'] < 70) {
            $suggested = $this->suggest_seo_filename($post->ID);
            echo '<p>Suggested: <code>' . $suggested . '</code></p>';
            echo '<button type="button" class="button" onclick="optimizeFilename(' . $post->ID . ')">Optimize Filename</button>';
        }
        echo '</div>';
        
        // ALT text analysis
        echo '<div class="analysis-item">';
        echo '<h5>ALT Text <span class="score">' . $analysis['alt_quality_score'] . '/100</span></h5>';
        echo '<p>Current: ' . ($analysis['alt_text'] ?: '<em>Not set</em>') . '</p>';
        if ($analysis['alt_quality_score'] < 70) {
            echo '<button type="button" class="button button-primary" onclick="generateSmartAlt(' . $post->ID . ')" ' . ($credits['credits_balance'] < 1 ? 'disabled' : '') . '>';
            echo 'Generate Smart ALT Text (1 credit)';
            echo '</button>';
        }
        echo '</div>';
        
        // Recommendations
        if (!empty($analysis['recommendations'])) {
            echo '<div class="metapix-recommendations">';
            echo '<h4>Recommendations</h4>';
            echo '<ul>';
            foreach ($analysis['recommendations'] as $rec) {
                echo '<li class="priority-' . $rec['priority'] . '">';
                echo '<strong>' . ucfirst($rec['type']) . ':</strong> ' . $rec['message'];
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Add JavaScript
        ?>
        <script>
        function generateSmartAlt(attachmentId) {
            const button = event.target;
            button.disabled = true;
            button.textContent = 'Generating...';
            
            jQuery.post(ajaxurl, {
                action: 'metapix_generate_smart_alt',
                attachment_id: attachmentId,
                nonce: '<?php echo wp_create_nonce('metapix_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    button.disabled = false;
                    button.textContent = 'Generate Smart ALT Text (1 credit)';
                }
            });
        }
        </script>
        
        <style>
        .metapix-analysis-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .analysis-item {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .score-badge {
            padding: 5px 10px;
            border-radius: 3px;
            color: white;
            font-weight: bold;
        }
        .score-excellent { background: #28a745; }
        .score-good { background: #17a2b8; }
        .score-fair { background: #ffc107; color: #333; }
        .score-poor { background: #dc3545; }
        .priority-high { color: #dc3545; }
        .priority-medium { color: #ffc107; }
        .priority-low { color: #28a745; }
        </style>
        <?php
    }
    
    /**
     * Get score CSS class
     */
    private function get_score_class($score) {
        if ($score >= 90) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'fair';
        return 'poor';
    }
}