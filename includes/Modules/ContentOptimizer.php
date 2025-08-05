<?php

namespace MetaPixAI\Modules;

use MetaPixAI\Core\Database;
use MetaPixAI\Services\OpenAI;

/**
 * Content Optimizer Module
 */
class ContentOptimizer {
    
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
        // Hook into post save to analyze content
        add_action('save_post', [$this, 'analyze_post_content'], 10, 2);
        
        // Add meta boxes for content optimization
        add_action('add_meta_boxes', [$this, 'add_content_optimizer_meta_box']);
        
        // Save meta box data
        add_action('save_post', [$this, 'save_content_optimizer_meta_box']);
        
        // AJAX handlers
        add_action('wp_ajax_metapix_analyze_content', [$this, 'ajax_analyze_content']);
        add_action('wp_ajax_metapix_generate_meta_tags', [$this, 'ajax_generate_meta_tags']);
        add_action('wp_ajax_metapix_optimize_content', [$this, 'ajax_optimize_content']);
        add_action('wp_ajax_metapix_check_readability', [$this, 'ajax_check_readability']);
        
        // Add content analysis to post list
        add_filter('manage_posts_columns', [$this, 'add_seo_score_column']);
        add_filter('manage_pages_columns', [$this, 'add_seo_score_column']);
        add_action('manage_posts_custom_column', [$this, 'display_seo_score_column'], 10, 2);
        add_action('manage_pages_custom_column', [$this, 'display_seo_score_column'], 10, 2);
    }
    
    /**
     * Analyze post content
     */
    public function analyze_post_content($post_id, $post) {
        // Skip if not in autonomous mode
        if (get_option('metapix_ai_mode') !== 'autonomous') {
            return;
        }
        
        // Skip for revisions and auto-saves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Only analyze published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        $this->perform_content_analysis($post_id);
    }
    
    /**
     * Perform comprehensive content analysis
     */
    public function perform_content_analysis($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return false;
        }
        
        $analysis = [
            'post_id' => $post_id,
            'content_length' => str_word_count(strip_tags($post->post_content)),
            'title_analysis' => $this->analyze_title($post->post_title),
            'meta_analysis' => $this->analyze_meta_tags($post_id),
            'content_structure' => $this->analyze_content_structure($post->post_content),
            'keyword_analysis' => $this->analyze_keywords($post),
            'readability' => $this->calculate_readability($post->post_content),
            'seo_elements' => $this->check_seo_elements($post),
            'overall_score' => 0,
            'recommendations' => [],
            'analyzed_at' => current_time('mysql')
        ];
        
        // Calculate overall score
        $analysis['overall_score'] = $this->calculate_overall_score($analysis);
        
        // Generate recommendations
        $analysis['recommendations'] = $this->generate_recommendations($analysis);
        
        // Save analysis
        update_post_meta($post_id, '_metapix_content_analysis', $analysis);
        
        // Update SEO scores in database
        Database::update_seo_score($post_id, [
            'content_score' => $analysis['overall_score'],
            'overall_score' => $this->calculate_combined_seo_score($post_id, $analysis['overall_score'])
        ]);
        
        // Log optimization opportunities
        if ($analysis['overall_score'] < 80) {
            Database::log_optimization([
                'post_id' => $post_id,
                'optimization_type' => 'content_seo',
                'module' => 'ContentOptimizer',
                'old_value' => json_encode(['score' => $analysis['overall_score']]),
                'new_value' => json_encode($analysis['recommendations']),
                'status' => 'pending',
                'score_before' => $analysis['overall_score'],
                'ai_confidence' => 90
            ]);
        }
        
        return $analysis;
    }
    
    /**
     * Analyze title
     */
    private function analyze_title($title) {
        $analysis = [
            'length' => strlen($title),
            'word_count' => str_word_count($title),
            'issues' => [],
            'score' => 100
        ];
        
        // Check title length
        if ($analysis['length'] < 30) {
            $analysis['issues'][] = 'title_too_short';
            $analysis['score'] -= 20;
        } elseif ($analysis['length'] > 60) {
            $analysis['issues'][] = 'title_too_long';
            $analysis['score'] -= 15;
        }
        
        // Check word count
        if ($analysis['word_count'] < 5) {
            $analysis['issues'][] = 'too_few_words';
            $analysis['score'] -= 10;
        }
        
        // Check for power words
        $power_words = ['ultimate', 'complete', 'guide', 'best', 'top', 'how', 'why', 'what', 'when', 'where'];
        $has_power_word = false;
        foreach ($power_words as $word) {
            if (stripos($title, $word) !== false) {
                $has_power_word = true;
                break;
            }
        }
        
        if (!$has_power_word) {
            $analysis['issues'][] = 'no_power_words';
            $analysis['score'] -= 5;
        }
        
        return $analysis;
    }
    
    /**
     * Analyze meta tags
     */
    private function analyze_meta_tags($post_id) {
        $analysis = [
            'meta_title' => '',
            'meta_description' => '',
            'issues' => [],
            'score' => 100
        ];
        
        // Check for Yoast SEO
        $yoast_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        $yoast_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        
        // Check for RankMath
        $rankmath_title = get_post_meta($post_id, 'rank_math_title', true);
        $rankmath_description = get_post_meta($post_id, 'rank_math_description', true);
        
        // Check for All in One SEO
        $aioseo_title = get_post_meta($post_id, '_aioseo_title', true);
        $aioseo_description = get_post_meta($post_id, '_aioseo_description', true);
        
        $analysis['meta_title'] = $yoast_title ?: $rankmath_title ?: $aioseo_title ?: '';
        $analysis['meta_description'] = $yoast_description ?: $rankmath_description ?: $aioseo_description ?: '';
        
        // Analyze meta title
        if (empty($analysis['meta_title'])) {
            $analysis['issues'][] = 'missing_meta_title';
            $analysis['score'] -= 25;
        } else {
            $title_length = strlen($analysis['meta_title']);
            if ($title_length < 30) {
                $analysis['issues'][] = 'meta_title_too_short';
                $analysis['score'] -= 15;
            } elseif ($title_length > 60) {
                $analysis['issues'][] = 'meta_title_too_long';
                $analysis['score'] -= 10;
            }
        }
        
        // Analyze meta description
        if (empty($analysis['meta_description'])) {
            $analysis['issues'][] = 'missing_meta_description';
            $analysis['score'] -= 25;
        } else {
            $desc_length = strlen($analysis['meta_description']);
            if ($desc_length < 120) {
                $analysis['issues'][] = 'meta_description_too_short';
                $analysis['score'] -= 15;
            } elseif ($desc_length > 160) {
                $analysis['issues'][] = 'meta_description_too_long';
                $analysis['score'] -= 10;
            }
        }
        
        return $analysis;
    }
    
    /**
     * Analyze content structure
     */
    private function analyze_content_structure($content) {
        $analysis = [
            'headings' => [],
            'paragraphs' => 0,
            'issues' => [],
            'score' => 100
        ];
        
        // Extract headings
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/i', $content, $heading_matches);
        
        if (!empty($heading_matches[1])) {
            foreach ($heading_matches[1] as $index => $level) {
                $analysis['headings'][] = [
                    'level' => intval($level),
                    'text' => strip_tags($heading_matches[2][$index])
                ];
            }
        }
        
        // Check H1 tag
        $h1_count = 0;
        foreach ($analysis['headings'] as $heading) {
            if ($heading['level'] === 1) {
                $h1_count++;
            }
        }
        
        if ($h1_count === 0) {
            $analysis['issues'][] = 'missing_h1';
            $analysis['score'] -= 20;
        } elseif ($h1_count > 1) {
            $analysis['issues'][] = 'multiple_h1';
            $analysis['score'] -= 15;
        }
        
        // Check heading hierarchy
        $previous_level = 0;
        foreach ($analysis['headings'] as $heading) {
            if ($previous_level > 0 && $heading['level'] > $previous_level + 1) {
                $analysis['issues'][] = 'heading_hierarchy_issue';
                $analysis['score'] -= 10;
                break;
            }
            $previous_level = $heading['level'];
        }
        
        // Count paragraphs
        $paragraphs = explode('</p>', $content);
        $analysis['paragraphs'] = count($paragraphs) - 1;
        
        // Check paragraph length
        $long_paragraphs = 0;
        foreach ($paragraphs as $paragraph) {
            $word_count = str_word_count(strip_tags($paragraph));
            if ($word_count > 150) {
                $long_paragraphs++;
            }
        }
        
        if ($long_paragraphs > $analysis['paragraphs'] * 0.3) {
            $analysis['issues'][] = 'paragraphs_too_long';
            $analysis['score'] -= 10;
        }
        
        return $analysis;
    }
    
    /**
     * Analyze keywords
     */
    private function analyze_keywords($post) {
        $analysis = [
            'primary_keyword' => '',
            'keyword_density' => 0,
            'lsi_keywords' => [],
            'issues' => [],
            'score' => 100
        ];
        
        // Get target keyword from SEO plugins
        $post_id = $post->ID;
        $yoast_keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        $rankmath_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        
        $analysis['primary_keyword'] = $yoast_keyword ?: $rankmath_keyword ?: '';
        
        if (empty($analysis['primary_keyword'])) {
            $analysis['issues'][] = 'no_target_keyword';
            $analysis['score'] -= 30;
            return $analysis;
        }
        
        // Calculate keyword density
        $content_text = strip_tags($post->post_content . ' ' . $post->post_title);
        $total_words = str_word_count($content_text);
        $keyword_count = substr_count(strtolower($content_text), strtolower($analysis['primary_keyword']));
        
        $analysis['keyword_density'] = $total_words > 0 ? ($keyword_count / $total_words) * 100 : 0;
        
        // Check keyword density
        if ($analysis['keyword_density'] < 0.5) {
            $analysis['issues'][] = 'keyword_density_too_low';
            $analysis['score'] -= 20;
        } elseif ($analysis['keyword_density'] > 3) {
            $analysis['issues'][] = 'keyword_density_too_high';
            $analysis['score'] -= 25;
        }
        
        // Check keyword in title
        if (stripos($post->post_title, $analysis['primary_keyword']) === false) {
            $analysis['issues'][] = 'keyword_not_in_title';
            $analysis['score'] -= 15;
        }
        
        // Check keyword in first paragraph
        $first_paragraph = $this->get_first_paragraph($post->post_content);
        if (stripos($first_paragraph, $analysis['primary_keyword']) === false) {
            $analysis['issues'][] = 'keyword_not_in_first_paragraph';
            $analysis['score'] -= 10;
        }
        
        return $analysis;
    }
    
    /**
     * Calculate readability score
     */
    private function calculate_readability($content) {
        $text = strip_tags($content);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = str_word_count($text);
        $syllables = $this->count_syllables($text);
        
        $sentence_count = count($sentences);
        
        if ($sentence_count === 0 || $words === 0) {
            return [
                'score' => 0,
                'grade_level' => 'Unknown',
                'issues' => ['insufficient_content']
            ];
        }
        
        // Flesch Reading Ease Score
        $avg_sentence_length = $words / $sentence_count;
        $avg_syllables_per_word = $syllables / $words;
        
        $flesch_score = 206.835 - (1.015 * $avg_sentence_length) - (84.6 * $avg_syllables_per_word);
        $flesch_score = max(0, min(100, $flesch_score));
        
        // Grade level
        $grade_level = $this->get_grade_level($flesch_score);
        
        $analysis = [
            'score' => round($flesch_score, 1),
            'grade_level' => $grade_level,
            'avg_sentence_length' => round($avg_sentence_length, 1),
            'avg_syllables_per_word' => round($avg_syllables_per_word, 2),
            'issues' => []
        ];
        
        // Check for readability issues
        if ($flesch_score < 30) {
            $analysis['issues'][] = 'very_difficult_to_read';
        } elseif ($flesch_score < 50) {
            $analysis['issues'][] = 'difficult_to_read';
        }
        
        if ($avg_sentence_length > 20) {
            $analysis['issues'][] = 'sentences_too_long';
        }
        
        return $analysis;
    }
    
    /**
     * Count syllables in text
     */
    private function count_syllables($text) {
        $words = str_word_count(strtolower($text), 1);
        $syllable_count = 0;
        
        foreach ($words as $word) {
            $syllable_count += $this->count_word_syllables($word);
        }
        
        return $syllable_count;
    }
    
    /**
     * Count syllables in a word
     */
    private function count_word_syllables($word) {
        $word = strtolower($word);
        $vowels = 'aeiouy';
        $syllables = 0;
        $prev_was_vowel = false;
        
        for ($i = 0; $i < strlen($word); $i++) {
            $is_vowel = strpos($vowels, $word[$i]) !== false;
            
            if ($is_vowel && !$prev_was_vowel) {
                $syllables++;
            }
            
            $prev_was_vowel = $is_vowel;
        }
        
        // Handle silent 'e'
        if (substr($word, -1) === 'e' && $syllables > 1) {
            $syllables--;
        }
        
        return max(1, $syllables);
    }
    
    /**
     * Get grade level from Flesch score
     */
    private function get_grade_level($score) {
        if ($score >= 90) return '5th grade';
        if ($score >= 80) return '6th grade';
        if ($score >= 70) return '7th grade';
        if ($score >= 60) return '8th-9th grade';
        if ($score >= 50) return '10th-12th grade';
        if ($score >= 30) return 'College level';
        return 'Graduate level';
    }
    
    /**
     * Check SEO elements
     */
    private function check_seo_elements($post) {
        $analysis = [
            'has_featured_image' => has_post_thumbnail($post->ID),
            'has_categories' => !empty(get_the_category($post->ID)),
            'has_tags' => !empty(get_the_tags($post->ID)),
            'internal_links' => $this->count_internal_links($post->post_content),
            'external_links' => $this->count_external_links($post->post_content),
            'images_with_alt' => $this->count_images_with_alt($post->post_content),
            'total_images' => $this->count_total_images($post->post_content),
            'has_cta' => $this->has_call_to_action($post->post_content),
            'issues' => [],
            'score' => 100
        ];
        
        // Check issues
        if (!$analysis['has_featured_image']) {
            $analysis['issues'][] = 'missing_featured_image';
            $analysis['score'] -= 10;
        }
        
        if ($analysis['internal_links'] === 0) {
            $analysis['issues'][] = 'no_internal_links';
            $analysis['score'] -= 15;
        }
        
        if ($analysis['total_images'] > 0 && $analysis['images_with_alt'] < $analysis['total_images']) {
            $analysis['issues'][] = 'images_missing_alt_text';
            $analysis['score'] -= 20;
        }
        
        if (!$analysis['has_cta']) {
            $analysis['issues'][] = 'missing_call_to_action';
            $analysis['score'] -= 10;
        }
        
        return $analysis;
    }
    
    /**
     * Get first paragraph
     */
    private function get_first_paragraph($content) {
        $paragraphs = explode('</p>', $content);
        return strip_tags($paragraphs[0] ?? '');
    }
    
    /**
     * Count internal links
     */
    private function count_internal_links($content) {
        $site_url = get_site_url();
        $domain = parse_url($site_url, PHP_URL_HOST);
        
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
        
        $internal_count = 0;
        foreach ($matches[1] as $url) {
            $url_domain = parse_url($url, PHP_URL_HOST);
            if ($url_domain === $domain || strpos($url, '/') === 0) {
                $internal_count++;
            }
        }
        
        return $internal_count;
    }
    
    /**
     * Count external links
     */
    private function count_external_links($content) {
        $site_url = get_site_url();
        $domain = parse_url($site_url, PHP_URL_HOST);
        
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
        
        $external_count = 0;
        foreach ($matches[1] as $url) {
            $url_domain = parse_url($url, PHP_URL_HOST);
            if ($url_domain && $url_domain !== $domain && strpos($url, '/') !== 0) {
                $external_count++;
            }
        }
        
        return $external_count;
    }
    
    /**
     * Count images with ALT text
     */
    private function count_images_with_alt($content) {
        preg_match_all('/<img[^>]+alt=["\'][^"\']*["\'][^>]*>/i', $content, $matches);
        return count($matches[0]);
    }
    
    /**
     * Count total images
     */
    private function count_total_images($content) {
        preg_match_all('/<img[^>]+>/i', $content, $matches);
        return count($matches[0]);
    }
    
    /**
     * Check for call-to-action
     */
    private function has_call_to_action($content) {
        $cta_patterns = [
            '/\b(click here|learn more|read more|get started|sign up|subscribe|download|buy now|contact us|call now)\b/i',
            '/\b(try|start|join|discover|explore|find out|check out)\b/i'
        ];
        
        foreach ($cta_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate overall content score
     */
    private function calculate_overall_score($analysis) {
        $scores = [
            'title' => $analysis['title_analysis']['score'] * 0.15,
            'meta' => $analysis['meta_analysis']['score'] * 0.20,
            'structure' => $analysis['content_structure']['score'] * 0.15,
            'keywords' => $analysis['keyword_analysis']['score'] * 0.25,
            'readability' => min(100, $analysis['readability']['score']) * 0.15,
            'seo_elements' => $analysis['seo_elements']['score'] * 0.10
        ];
        
        return round(array_sum($scores), 1);
    }
    
    /**
     * Calculate combined SEO score
     */
    private function calculate_combined_seo_score($post_id, $content_score) {
        $existing_scores = Database::get_seo_score($post_id);
        
        if (!$existing_scores) {
            return $content_score;
        }
        
        // Weighted average of all module scores
        $weights = [
            'content_score' => 0.30,
            'image_score' => 0.20,
            'technical_score' => 0.20,
            'performance_score' => 0.15,
            'schema_score' => 0.15
        ];
        
        $total_score = $content_score * $weights['content_score'];
        $total_score += ($existing_scores->image_score ?? 0) * $weights['image_score'];
        $total_score += ($existing_scores->technical_score ?? 0) * $weights['technical_score'];
        $total_score += ($existing_scores->performance_score ?? 0) * $weights['performance_score'];
        $total_score += ($existing_scores->schema_score ?? 0) * $weights['schema_score'];
        
        return round($total_score, 1);
    }
    
    /**
     * Generate recommendations
     */
    private function generate_recommendations($analysis) {
        $recommendations = [];
        
        // Title recommendations
        foreach ($analysis['title_analysis']['issues'] as $issue) {
            switch ($issue) {
                case 'title_too_short':
                    $recommendations[] = [
                        'priority' => 'high',
                        'type' => 'title',
                        'message' => 'Title is too short. Aim for 30-60 characters.',
                        'impact' => 'Improve click-through rate by 10-15%'
                    ];
                    break;
                case 'title_too_long':
                    $recommendations[] = [
                        'priority' => 'medium',
                        'type' => 'title',
                        'message' => 'Title may be truncated in search results. Keep it under 60 characters.',
                        'impact' => 'Prevent title truncation in SERPs'
                    ];
                    break;
            }
        }
        
        // Meta tag recommendations
        foreach ($analysis['meta_analysis']['issues'] as $issue) {
            switch ($issue) {
                case 'missing_meta_description':
                    $recommendations[] = [
                        'priority' => 'high',
                        'type' => 'meta',
                        'message' => 'Add a compelling meta description (150-160 characters).',
                        'impact' => 'Improve click-through rate by 20-30%'
                    ];
                    break;
                case 'missing_meta_title':
                    $recommendations[] = [
                        'priority' => 'high',
                        'type' => 'meta',
                        'message' => 'Add a custom meta title with your target keyword.',
                        'impact' => 'Better control over search result appearance'
                    ];
                    break;
            }
        }
        
        // Content structure recommendations
        foreach ($analysis['content_structure']['issues'] as $issue) {
            switch ($issue) {
                case 'missing_h1':
                    $recommendations[] = [
                        'priority' => 'high',
                        'type' => 'structure',
                        'message' => 'Add an H1 tag to your content.',
                        'impact' => 'Improve content hierarchy and SEO'
                    ];
                    break;
                case 'multiple_h1':
                    $recommendations[] = [
                        'priority' => 'medium',
                        'type' => 'structure',
                        'message' => 'Use only one H1 tag per page.',
                        'impact' => 'Better content structure for search engines'
                    ];
                    break;
            }
        }
        
        // Keyword recommendations
        foreach ($analysis['keyword_analysis']['issues'] as $issue) {
            switch ($issue) {
                case 'no_target_keyword':
                    $recommendations[] = [
                        'priority' => 'high',
                        'type' => 'keywords',
                        'message' => 'Set a target keyword for this content.',
                        'impact' => 'Focus optimization efforts on specific terms'
                    ];
                    break;
                case 'keyword_density_too_low':
                    $recommendations[] = [
                        'priority' => 'medium',
                        'type' => 'keywords',
                        'message' => 'Increase keyword density to 0.5-2% by naturally including your target keyword.',
                        'impact' => 'Better keyword relevance signals'
                    ];
                    break;
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Add content optimizer meta box
     */
    public function add_content_optimizer_meta_box() {
        $post_types = ['post', 'page', 'product'];
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'metapix-content-optimizer',
                __('MetaPix AI - Content Optimizer', 'metapix-ai'),
                [$this, 'render_content_optimizer_meta_box'],
                $post_type,
                'normal',
                'default'
            );
        }
    }
    
    /**
     * Render content optimizer meta box
     */
    public function render_content_optimizer_meta_box($post) {
        wp_nonce_field('metapix_content_optimizer_nonce', 'metapix_content_optimizer_nonce');
        
        $analysis = get_post_meta($post->ID, '_metapix_content_analysis', true);
        
        echo '<div id="metapix-content-optimizer-container">';
        
        if ($analysis) {
            $this->render_content_analysis_dashboard($analysis);
        } else {
            echo '<p>' . __('Click "Analyze Content" to get SEO recommendations.', 'metapix-ai') . '</p>';
        }
        
        // Action buttons
        echo '<div class="metapix-actions">';
        echo '<button type="button" class="button button-primary" id="analyze-content" data-post-id="' . $post->ID . '">' . __('Analyze Content', 'metapix-ai') . '</button>';
        echo '<button type="button" class="button" id="generate-meta-tags" data-post-id="' . $post->ID . '">' . __('Generate Meta Tags', 'metapix-ai') . '</button>';
        echo '<button type="button" class="button" id="optimize-content" data-post-id="' . $post->ID . '">' . __('AI Optimize', 'metapix-ai') . '</button>';
        echo '</div>';
        
        echo '</div>';
        
        // Enqueue scripts
        $this->enqueue_content_optimizer_scripts();
    }
    
    /**
     * Render content analysis dashboard
     */
    private function render_content_analysis_dashboard($analysis) {
        echo '<div class="metapix-analysis-dashboard">';
        
        // Overall score
        echo '<div class="metapix-score-card">';
        echo '<h3>' . __('Overall Content Score', 'metapix-ai') . '</h3>';
        echo '<div class="score-circle" data-score="' . $analysis['overall_score'] . '">';
        echo '<span class="score-number">' . $analysis['overall_score'] . '</span>';
        echo '<span class="score-label">/100</span>';
        echo '</div>';
        echo '</div>';
        
        // Detailed scores
        echo '<div class="metapix-detailed-scores">';
        
        $score_items = [
            'title_analysis' => ['label' => 'Title', 'score' => $analysis['title_analysis']['score']],
            'meta_analysis' => ['label' => 'Meta Tags', 'score' => $analysis['meta_analysis']['score']],
            'content_structure' => ['label' => 'Structure', 'score' => $analysis['content_structure']['score']],
            'keyword_analysis' => ['label' => 'Keywords', 'score' => $analysis['keyword_analysis']['score']],
            'readability' => ['label' => 'Readability', 'score' => min(100, $analysis['readability']['score'])],
            'seo_elements' => ['label' => 'SEO Elements', 'score' => $analysis['seo_elements']['score']]
        ];
        
        foreach ($score_items as $key => $item) {
            echo '<div class="score-item">';
            echo '<span class="score-label">' . $item['label'] . '</span>';
            echo '<div class="score-bar">';
            echo '<div class="score-fill" style="width: ' . $item['score'] . '%"></div>';
            echo '</div>';
            echo '<span class="score-value">' . round($item['score']) . '</span>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Recommendations
        if (!empty($analysis['recommendations'])) {
            echo '<div class="metapix-recommendations">';
            echo '<h4>' . __('Recommendations', 'metapix-ai') . '</h4>';
            
            foreach ($analysis['recommendations'] as $rec) {
                $priority_class = 'priority-' . $rec['priority'];
                echo '<div class="recommendation-item ' . $priority_class . '">';
                echo '<div class="rec-priority">' . ucfirst($rec['priority']) . '</div>';
                echo '<div class="rec-content">';
                echo '<p class="rec-message">' . $rec['message'] . '</p>';
                echo '<p class="rec-impact"><em>' . $rec['impact'] . '</em></p>';
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Enqueue content optimizer scripts
     */
    private function enqueue_content_optimizer_scripts() {
        wp_enqueue_script('metapix-content-optimizer', METAPIX_AI_PLUGIN_URL . 'assets/js/content-optimizer.js', ['jquery'], METAPIX_AI_VERSION, true);
        wp_enqueue_style('metapix-content-optimizer', METAPIX_AI_PLUGIN_URL . 'assets/css/content-optimizer.css', [], METAPIX_AI_VERSION);
        
        wp_localize_script('metapix-content-optimizer', 'metapixContentOptimizer', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('metapix_content_optimizer_nonce'),
            'strings' => [
                'analyzing' => __('Analyzing...', 'metapix-ai'),
                'generating' => __('Generating...', 'metapix-ai'),
                'optimizing' => __('Optimizing...', 'metapix-ai'),
                'success' => __('Success!', 'metapix-ai'),
                'error' => __('Error occurred', 'metapix-ai')
            ]
        ]);
    }
    
    /**
     * AJAX: Analyze content
     */
    public function ajax_analyze_content() {
        check_ajax_referer('metapix_content_optimizer_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_die('Invalid post ID');
        }
        
        $analysis = $this->perform_content_analysis($post_id);
        
        if ($analysis) {
            wp_send_json_success([
                'analysis' => $analysis,
                'message' => __('Content analysis completed!', 'metapix-ai')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to analyze content.', 'metapix-ai')
            ]);
        }
    }
    
    /**
     * AJAX: Generate meta tags
     */
    public function ajax_generate_meta_tags() {
        check_ajax_referer('metapix_content_optimizer_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_die('Invalid post ID');
        }
        
        $post = get_post($post_id);
        $openai = new OpenAI();
        
        if (!$openai->is_configured()) {
            wp_send_json_error([
                'message' => __('OpenAI API not configured.', 'metapix-ai')
            ]);
            return;
        }
        
        try {
            // Get target keywords
            $keywords = [];
            $yoast_keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            $rankmath_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            
            if ($yoast_keyword) $keywords[] = $yoast_keyword;
            if ($rankmath_keyword) $keywords[] = $rankmath_keyword;
            
            $meta_tags = $openai->generate_meta_tags($post->post_title, $post->post_content, $keywords);
            
            wp_send_json_success([
                'meta_tags' => $meta_tags,
                'message' => __('Meta tags generated successfully!', 'metapix-ai')
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to generate meta tags: ', 'metapix-ai') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Save content optimizer meta box
     */
    public function save_content_optimizer_meta_box($post_id) {
        if (!isset($_POST['metapix_content_optimizer_nonce']) || 
            !wp_verify_nonce($_POST['metapix_content_optimizer_nonce'], 'metapix_content_optimizer_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Handle any meta box saves here
    }
    
    /**
     * Add SEO score column to post list
     */
    public function add_seo_score_column($columns) {
        $columns['metapix_seo_score'] = __('SEO Score', 'metapix-ai');
        return $columns;
    }
    
    /**
     * Display SEO score in post list
     */
    public function display_seo_score_column($column, $post_id) {
        if ($column === 'metapix_seo_score') {
            $score_data = Database::get_seo_score($post_id);
            
            if ($score_data) {
                $score = $score_data->overall_score;
                $class = $score >= 80 ? 'good' : ($score >= 60 ? 'ok' : 'poor');
                echo '<span class="metapix-score-badge ' . $class . '">' . $score . '</span>';
            } else {
                echo '<span class="metapix-score-badge not-analyzed">—</span>';
            }
        }
    }
}