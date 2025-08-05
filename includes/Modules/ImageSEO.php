<?php

namespace MetaPixAI\Modules;

use MetaPixAI\Core\Database;
use MetaPixAI\Services\OpenAI;

/**
 * Image SEO Engine Module
 */
class ImageSEO {
    
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
        // Hook into post save to analyze images
        add_action('save_post', [$this, 'analyze_post_images'], 10, 2);
        
        // Hook into attachment upload
        add_action('add_attachment', [$this, 'analyze_new_attachment']);
        
        // Add meta boxes for image SEO
        add_action('add_meta_boxes', [$this, 'add_image_seo_meta_box']);
        
        // Save meta box data
        add_action('save_post', [$this, 'save_image_seo_meta_box']);
        
        // AJAX handlers
        add_action('wp_ajax_metapix_generate_alt_text', [$this, 'ajax_generate_alt_text']);
        add_action('wp_ajax_metapix_optimize_image', [$this, 'ajax_optimize_image']);
        add_action('wp_ajax_metapix_bulk_optimize_images', [$this, 'ajax_bulk_optimize_images']);
        
        // Add image optimization suggestions to media library
        add_filter('attachment_fields_to_edit', [$this, 'add_seo_fields_to_media'], 10, 2);
        add_filter('attachment_fields_to_save', [$this, 'save_seo_fields_from_media'], 10, 2);
    }
    
    /**
     * Analyze images in a post
     */
    public function analyze_post_images($post_id, $post) {
        // Skip if not in autonomous mode
        if (get_option('metapix_ai_mode') !== 'autonomous') {
            return;
        }
        
        // Skip for revisions and auto-saves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Get all images in the post
        $images = $this->extract_images_from_post($post);
        
        foreach ($images as $image) {
            $this->analyze_image($image, $post_id);
        }
    }
    
    /**
     * Analyze new attachment
     */
    public function analyze_new_attachment($attachment_id) {
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        $this->analyze_image(['id' => $attachment_id], null);
    }
    
    /**
     * Extract images from post content
     */
    private function extract_images_from_post($post) {
        $images = [];
        
        // Get featured image
        if (has_post_thumbnail($post->ID)) {
            $images[] = [
                'id' => get_post_thumbnail_id($post->ID),
                'type' => 'featured',
                'context' => 'Featured image for: ' . $post->post_title
            ];
        }
        
        // Extract images from content
        preg_match_all('/<img[^>]+>/i', $post->post_content, $img_tags);
        
        foreach ($img_tags[0] as $img_tag) {
            // Extract src and alt
            preg_match('/src="([^"]+)"/i', $img_tag, $src_match);
            preg_match('/alt="([^"]*)"/i', $img_tag, $alt_match);
            
            if (!empty($src_match[1])) {
                $attachment_id = attachment_url_to_postid($src_match[1]);
                
                if ($attachment_id) {
                    $images[] = [
                        'id' => $attachment_id,
                        'type' => 'content',
                        'current_alt' => isset($alt_match[1]) ? $alt_match[1] : '',
                        'context' => $this->extract_surrounding_text($post->post_content, $img_tag)
                    ];
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Extract surrounding text for context
     */
    private function extract_surrounding_text($content, $img_tag) {
        $position = strpos($content, $img_tag);
        $start = max(0, $position - 200);
        $end = min(strlen($content), $position + strlen($img_tag) + 200);
        
        $context = substr($content, $start, $end - $start);
        $context = strip_tags($context);
        $context = preg_replace('/\s+/', ' ', $context);
        
        return trim($context);
    }
    
    /**
     * Analyze individual image
     */
    public function analyze_image($image, $post_id = null) {
        $attachment_id = $image['id'];
        $attachment = get_post($attachment_id);
        
        if (!$attachment || !wp_attachment_is_image($attachment_id)) {
            return false;
        }
        
        $analysis = [
            'attachment_id' => $attachment_id,
            'post_id' => $post_id,
            'issues' => [],
            'suggestions' => [],
            'score' => 100
        ];
        
        // Check ALT text
        $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (empty($current_alt)) {
            $analysis['issues'][] = 'missing_alt_text';
            $analysis['score'] -= 30;
            
            // Generate ALT text suggestion
            $suggested_alt = $this->generate_alt_text($attachment_id, $image['context'] ?? '');
            if ($suggested_alt) {
                $analysis['suggestions']['alt_text'] = $suggested_alt;
            }
        } else {
            // Analyze ALT text quality
            $alt_score = $this->analyze_alt_text_quality($current_alt, $image['context'] ?? '');
            if ($alt_score < 70) {
                $analysis['issues'][] = 'poor_alt_text';
                $analysis['score'] -= 15;
                
                $suggested_alt = $this->generate_alt_text($attachment_id, $image['context'] ?? '');
                if ($suggested_alt) {
                    $analysis['suggestions']['alt_text'] = $suggested_alt;
                }
            }
        }
        
        // Check filename
        $filename = basename(get_attached_file($attachment_id));
        if ($this->is_poor_filename($filename)) {
            $analysis['issues'][] = 'poor_filename';
            $analysis['score'] -= 10;
            $analysis['suggestions']['filename'] = $this->suggest_filename($attachment_id, $current_alt);
        }
        
        // Check image format and compression
        $file_path = get_attached_file($attachment_id);
        $image_info = getimagesize($file_path);
        
        if ($image_info) {
            $mime_type = $image_info['mime'];
            $file_size = filesize($file_path);
            
            // Suggest WebP format
            if (!in_array($mime_type, ['image/webp'])) {
                $analysis['suggestions']['format'] = 'webp';
                $analysis['score'] -= 5;
            }
            
            // Check file size
            if ($file_size > 500000) { // 500KB
                $analysis['issues'][] = 'large_file_size';
                $analysis['suggestions']['compression'] = true;
                $analysis['score'] -= 15;
            }
        }
        
        // Check if lazy loading is enabled
        if (!$this->has_lazy_loading($attachment_id)) {
            $analysis['suggestions']['lazy_loading'] = true;
            $analysis['score'] -= 5;
        }
        
        // Save analysis results
        update_post_meta($attachment_id, '_metapix_image_analysis', $analysis);
        
        // Log optimization opportunity
        if (!empty($analysis['issues']) || !empty($analysis['suggestions'])) {
            Database::log_optimization([
                'post_id' => $post_id ?: $attachment_id,
                'optimization_type' => 'image_seo',
                'module' => 'ImageSEO',
                'old_value' => json_encode(['alt' => $current_alt, 'filename' => $filename]),
                'new_value' => json_encode($analysis['suggestions']),
                'status' => 'pending',
                'score_before' => $analysis['score'],
                'ai_confidence' => 85
            ]);
        }
        
        return $analysis;
    }
    
    /**
     * Generate ALT text using GPT-4 Vision
     */
    public function generate_alt_text($attachment_id, $context = '') {
        $openai = new OpenAI();
        
        if (!$openai->is_configured()) {
            return false;
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        $post_title = '';
        $post_content = '';
        
        // Get post context if available
        $attachment = get_post($attachment_id);
        if ($attachment->post_parent) {
            $parent_post = get_post($attachment->post_parent);
            if ($parent_post) {
                $post_title = $parent_post->post_title;
                $post_content = wp_strip_all_tags($parent_post->post_content);
                $post_content = substr($post_content, 0, 500); // Limit context
            }
        }
        
        $prompt = $this->get_alt_text_prompt($context, $post_title, $post_content);
        
        try {
            $response = $openai->analyze_image($image_url, $prompt);
            
            if ($response && isset($response['alt_text'])) {
                // Clean and validate the generated ALT text
                $alt_text = sanitize_text_field($response['alt_text']);
                $alt_text = $this->clean_alt_text($alt_text);
                
                return $alt_text;
            }
        } catch (Exception $e) {
            error_log('MetaPix AI: Error generating ALT text - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Get ALT text generation prompt
     */
    private function get_alt_text_prompt($context, $post_title, $post_content) {
        return [
            'system' => 'You are an expert SEO specialist focused on creating optimal ALT text for images. Your goal is to create descriptive, SEO-friendly ALT text that improves accessibility and search engine optimization.',
            'user' => "Analyze this image and generate optimal ALT text following these guidelines:

1. Be descriptive but concise (under 125 characters)
2. Include relevant keywords naturally
3. Focus on what's important for SEO and accessibility
4. Don't start with 'Image of' or 'Picture of'
5. Consider the context where this image appears

Context Information:
- Post Title: {$post_title}
- Surrounding Content: {$context}
- Post Content Preview: " . substr($post_content, 0, 200) . "

Respond with JSON format:
{
    \"alt_text\": \"your generated alt text here\",
    \"confidence\": 0.95,
    \"keywords_used\": [\"keyword1\", \"keyword2\"],
    \"reasoning\": \"brief explanation of your choice\"
}"
        ];
    }
    
    /**
     * Clean and validate ALT text
     */
    private function clean_alt_text($alt_text) {
        // Remove quotes and clean up
        $alt_text = trim($alt_text, '"\'');
        
        // Limit length
        if (strlen($alt_text) > 125) {
            $alt_text = substr($alt_text, 0, 122) . '...';
        }
        
        // Remove common prefixes
        $prefixes = ['Image of ', 'Picture of ', 'Photo of ', 'A picture of ', 'An image of '];
        foreach ($prefixes as $prefix) {
            if (stripos($alt_text, $prefix) === 0) {
                $alt_text = substr($alt_text, strlen($prefix));
                break;
            }
        }
        
        return ucfirst(trim($alt_text));
    }
    
    /**
     * Analyze ALT text quality
     */
    private function analyze_alt_text_quality($alt_text, $context) {
        $score = 50; // Base score
        
        // Length check
        $length = strlen($alt_text);
        if ($length >= 10 && $length <= 125) {
            $score += 20;
        } elseif ($length > 125) {
            $score -= 10;
        }
        
        // Check for generic text
        $generic_terms = ['image', 'picture', 'photo', 'img', 'untitled'];
        $is_generic = false;
        foreach ($generic_terms as $term) {
            if (stripos($alt_text, $term) !== false) {
                $is_generic = true;
                break;
            }
        }
        
        if (!$is_generic) {
            $score += 15;
        } else {
            $score -= 20;
        }
        
        // Check for descriptive words
        $descriptive_words = ['showing', 'featuring', 'displaying', 'containing', 'with', 'of'];
        foreach ($descriptive_words as $word) {
            if (stripos($alt_text, $word) !== false) {
                $score += 5;
                break;
            }
        }
        
        // Check context relevance (simple keyword matching)
        if (!empty($context)) {
            $context_words = str_word_count(strtolower($context), 1);
            $alt_words = str_word_count(strtolower($alt_text), 1);
            $common_words = array_intersect($context_words, $alt_words);
            
            if (count($common_words) > 0) {
                $score += min(15, count($common_words) * 3);
            }
        }
        
        return min(100, max(0, $score));
    }
    
    /**
     * Check if filename is poor
     */
    private function is_poor_filename($filename) {
        $poor_patterns = [
            '/^img\d+/i',
            '/^image\d+/i',
            '/^photo\d+/i',
            '/^picture\d+/i',
            '/^dsc\d+/i',
            '/^untitled/i',
            '/^\d{8,}/i' // Long numbers
        ];
        
        foreach ($poor_patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Suggest better filename
     */
    private function suggest_filename($attachment_id, $alt_text) {
        if (!empty($alt_text)) {
            // Create filename from ALT text
            $filename = sanitize_title($alt_text);
            $filename = substr($filename, 0, 50); // Limit length
        } else {
            // Use attachment title
            $attachment = get_post($attachment_id);
            $filename = sanitize_title($attachment->post_title);
        }
        
        // Add file extension
        $file_path = get_attached_file($attachment_id);
        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
        
        return $filename . '.' . $extension;
    }
    
    /**
     * Check if image has lazy loading
     */
    private function has_lazy_loading($attachment_id) {
        // This is a simplified check - in practice, you'd check the actual HTML output
        return get_option('wp_lazy_loading_enabled', true);
    }
    
    /**
     * Add image SEO meta box
     */
    public function add_image_seo_meta_box() {
        $post_types = ['post', 'page', 'product'];
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'metapix-image-seo',
                __('MetaPix AI - Image SEO', 'metapix-ai'),
                [$this, 'render_image_seo_meta_box'],
                $post_type,
                'normal',
                'default'
            );
        }
    }
    
    /**
     * Render image SEO meta box
     */
    public function render_image_seo_meta_box($post) {
        wp_nonce_field('metapix_image_seo_nonce', 'metapix_image_seo_nonce');
        
        $images = $this->extract_images_from_post($post);
        
        echo '<div id="metapix-image-seo-container">';
        
        if (empty($images)) {
            echo '<p>' . __('No images found in this post.', 'metapix-ai') . '</p>';
            return;
        }
        
        foreach ($images as $image) {
            $analysis = get_post_meta($image['id'], '_metapix_image_analysis', true);
            $this->render_image_analysis_card($image, $analysis);
        }
        
        echo '</div>';
        
        // Add JavaScript for AJAX functionality
        $this->enqueue_image_seo_scripts();
    }
    
    /**
     * Render individual image analysis card
     */
    private function render_image_analysis_card($image, $analysis) {
        $attachment_id = $image['id'];
        $thumbnail = wp_get_attachment_image($attachment_id, 'thumbnail');
        $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        
        echo '<div class="metapix-image-card" data-attachment-id="' . $attachment_id . '">';
        echo '<div class="metapix-image-preview">' . $thumbnail . '</div>';
        echo '<div class="metapix-image-details">';
        
        // Current ALT text
        echo '<div class="metapix-field">';
        echo '<label>' . __('Current ALT Text:', 'metapix-ai') . '</label>';
        echo '<input type="text" class="current-alt" value="' . esc_attr($current_alt) . '" />';
        echo '</div>';
        
        // Show analysis if available
        if ($analysis) {
            echo '<div class="metapix-analysis">';
            echo '<p><strong>' . __('SEO Score:', 'metapix-ai') . '</strong> ' . $analysis['score'] . '/100</p>';
            
            if (!empty($analysis['issues'])) {
                echo '<div class="metapix-issues">';
                echo '<strong>' . __('Issues Found:', 'metapix-ai') . '</strong>';
                echo '<ul>';
                foreach ($analysis['issues'] as $issue) {
                    echo '<li>' . $this->get_issue_description($issue) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
            if (!empty($analysis['suggestions'])) {
                echo '<div class="metapix-suggestions">';
                echo '<strong>' . __('Suggestions:', 'metapix-ai') . '</strong>';
                
                if (isset($analysis['suggestions']['alt_text'])) {
                    echo '<div class="suggested-alt">';
                    echo '<label>' . __('Suggested ALT Text:', 'metapix-ai') . '</label>';
                    echo '<input type="text" class="suggested-alt-text" value="' . esc_attr($analysis['suggestions']['alt_text']) . '" readonly />';
                    echo '<button type="button" class="button apply-suggestion" data-type="alt_text">' . __('Apply', 'metapix-ai') . '</button>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
        }
        
        // Action buttons
        echo '<div class="metapix-actions">';
        echo '<button type="button" class="button button-primary generate-alt-text">' . __('Generate ALT Text', 'metapix-ai') . '</button>';
        echo '<button type="button" class="button optimize-image">' . __('Optimize Image', 'metapix-ai') . '</button>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Get issue description
     */
    private function get_issue_description($issue) {
        $descriptions = [
            'missing_alt_text' => __('Missing ALT text', 'metapix-ai'),
            'poor_alt_text' => __('ALT text could be improved', 'metapix-ai'),
            'poor_filename' => __('Filename is not SEO-friendly', 'metapix-ai'),
            'large_file_size' => __('File size is too large', 'metapix-ai')
        ];
        
        return $descriptions[$issue] ?? $issue;
    }
    
    /**
     * Enqueue image SEO scripts
     */
    private function enqueue_image_seo_scripts() {
        wp_enqueue_script('metapix-image-seo', METAPIX_AI_PLUGIN_URL . 'assets/js/image-seo.js', ['jquery'], METAPIX_AI_VERSION, true);
        wp_enqueue_style('metapix-image-seo', METAPIX_AI_PLUGIN_URL . 'assets/css/image-seo.css', [], METAPIX_AI_VERSION);
        
        wp_localize_script('metapix-image-seo', 'metapixImageSEO', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('metapix_image_seo_nonce'),
            'strings' => [
                'generating' => __('Generating...', 'metapix-ai'),
                'optimizing' => __('Optimizing...', 'metapix-ai'),
                'success' => __('Success!', 'metapix-ai'),
                'error' => __('Error occurred', 'metapix-ai')
            ]
        ]);
    }
    
    /**
     * AJAX: Generate ALT text
     */
    public function ajax_generate_alt_text() {
        check_ajax_referer('metapix_image_seo_nonce', 'nonce');
        
        $attachment_id = intval($_POST['attachment_id']);
        $context = sanitize_text_field($_POST['context'] ?? '');
        
        if (!$attachment_id) {
            wp_die('Invalid attachment ID');
        }
        
        $alt_text = $this->generate_alt_text($attachment_id, $context);
        
        if ($alt_text) {
            wp_send_json_success([
                'alt_text' => $alt_text,
                'message' => __('ALT text generated successfully!', 'metapix-ai')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to generate ALT text. Please check your OpenAI API configuration.', 'metapix-ai')
            ]);
        }
    }
    
    /**
     * AJAX: Optimize image
     */
    public function ajax_optimize_image() {
        check_ajax_referer('metapix_image_seo_nonce', 'nonce');
        
        $attachment_id = intval($_POST['attachment_id']);
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$attachment_id) {
            wp_die('Invalid attachment ID');
        }
        
        $analysis = $this->analyze_image(['id' => $attachment_id], $post_id);
        
        wp_send_json_success([
            'analysis' => $analysis,
            'message' => __('Image analysis completed!', 'metapix-ai')
        ]);
    }
    
    /**
     * Save image SEO meta box
     */
    public function save_image_seo_meta_box($post_id) {
        if (!isset($_POST['metapix_image_seo_nonce']) || 
            !wp_verify_nonce($_POST['metapix_image_seo_nonce'], 'metapix_image_seo_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Process any image SEO updates here
        // This would handle bulk updates from the meta box
    }
    
    /**
     * Add SEO fields to media library
     */
    public function add_seo_fields_to_media($form_fields, $post) {
        if (!wp_attachment_is_image($post->ID)) {
            return $form_fields;
        }
        
        $analysis = get_post_meta($post->ID, '_metapix_image_analysis', true);
        
        if ($analysis) {
            $form_fields['metapix_seo_score'] = [
                'label' => __('SEO Score', 'metapix-ai'),
                'input' => 'html',
                'html' => '<div class="metapix-seo-score">' . $analysis['score'] . '/100</div>'
            ];
            
            if (!empty($analysis['suggestions']['alt_text'])) {
                $form_fields['metapix_suggested_alt'] = [
                    'label' => __('Suggested ALT Text', 'metapix-ai'),
                    'value' => $analysis['suggestions']['alt_text'],
                    'helps' => __('AI-generated ALT text suggestion', 'metapix-ai')
                ];
            }
        }
        
        return $form_fields;
    }
    
    /**
     * Save SEO fields from media library
     */
    public function save_seo_fields_from_media($post, $attachment) {
        if (isset($attachment['metapix_suggested_alt'])) {
            update_post_meta($post['ID'], '_wp_attachment_image_alt', sanitize_text_field($attachment['metapix_suggested_alt']));
        }
        
        return $post;
    }
}