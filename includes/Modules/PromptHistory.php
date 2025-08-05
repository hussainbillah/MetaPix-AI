<?php

namespace MetaPixAI\Modules;

use MetaPixAI\Core\Database;
use MetaPixAI\Core\UserRoles;
use MetaPixAI\Services\OpenAI;

/**
 * Prompt History & Editor System
 */
class PromptHistory {
    
    /**
     * Prompt categories
     */
    const CATEGORIES = [
        'alt_text' => 'ALT Text Generation',
        'meta_tags' => 'Meta Tags',
        'content_optimization' => 'Content Optimization',
        'seo_analysis' => 'SEO Analysis',
        'image_description' => 'Image Description',
        'social_media' => 'Social Media',
        'custom' => 'Custom'
    ];
    
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
        // AJAX handlers
        add_action('wp_ajax_metapix_save_prompt', [$this, 'ajax_save_prompt']);
        add_action('wp_ajax_metapix_get_prompt_history', [$this, 'ajax_get_prompt_history']);
        add_action('wp_ajax_metapix_delete_prompt', [$this, 'ajax_delete_prompt']);
        add_action('wp_ajax_metapix_favorite_prompt', [$this, 'ajax_favorite_prompt']);
        add_action('wp_ajax_metapix_create_template', [$this, 'ajax_create_template']);
        add_action('wp_ajax_metapix_suggest_prompts', [$this, 'ajax_suggest_prompts']);
        add_action('wp_ajax_metapix_get_prompt_templates', [$this, 'ajax_get_prompt_templates']);
        
        // Admin interface
        add_action('admin_menu', [$this, 'add_prompt_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_prompt_scripts']);
        
        // Meta boxes
        add_action('add_meta_boxes', [$this, 'add_prompt_meta_boxes']);
        
        // Automatically save prompts when used
        add_action('metapix_prompt_used', [$this, 'record_prompt_usage'], 10, 3);
    }
    
    /**
     * Save a prompt to history
     */
    public function save_prompt($user_id, $prompt_text, $category = 'custom', $is_template = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_prompt_history';
        
        // Check if prompt already exists for user
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, usage_count FROM $table WHERE user_id = %d AND prompt_text = %s",
            $user_id,
            $prompt_text
        ));
        
        if ($existing) {
            // Update usage count and last used
            $wpdb->update(
                $table,
                [
                    'usage_count' => $existing->usage_count + 1,
                    'last_used' => current_time('mysql')
                ],
                ['id' => $existing->id]
            );
            
            return $existing->id;
        } else {
            // Insert new prompt
            $result = $wpdb->insert($table, [
                'user_id' => $user_id,
                'prompt_text' => $prompt_text,
                'prompt_category' => $category,
                'usage_count' => 1,
                'is_template' => $is_template ? 1 : 0,
                'last_used' => current_time('mysql')
            ]);
            
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Get user's prompt history
     */
    public function get_prompt_history($user_id, $category = null, $favorites_only = false, $templates_only = false, $limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_prompt_history';
        
        $where_conditions = ["user_id = %d"];
        $where_values = [$user_id];
        
        if ($category) {
            $where_conditions[] = "prompt_category = %s";
            $where_values[] = $category;
        }
        
        if ($favorites_only) {
            $where_conditions[] = "is_favorite = 1";
        }
        
        if ($templates_only) {
            $where_conditions[] = "is_template = 1";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE $where_clause 
             ORDER BY last_used DESC 
             LIMIT %d OFFSET %d",
            ...$where_values
        ));
    }
    
    /**
     * Get popular prompts (across all users)
     */
    public function get_popular_prompts($category = null, $limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_prompt_history';
        
        $where_clause = "";
        $where_values = [];
        
        if ($category) {
            $where_clause = "WHERE prompt_category = %s";
            $where_values[] = $category;
        }
        
        $where_values[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT prompt_text, prompt_category, 
                    SUM(usage_count) as total_usage,
                    COUNT(DISTINCT user_id) as user_count
             FROM $table 
             $where_clause
             GROUP BY prompt_text, prompt_category
             HAVING total_usage > 1
             ORDER BY total_usage DESC, user_count DESC
             LIMIT %d",
            ...$where_values
        ));
    }
    
    /**
     * Toggle favorite status
     */
    public function toggle_favorite($prompt_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_prompt_history';
        
        // Verify ownership
        $prompt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $prompt_id,
            $user_id
        ));
        
        if (!$prompt) {
            return false;
        }
        
        $new_status = $prompt->is_favorite ? 0 : 1;
        
        $result = $wpdb->update(
            $table,
            ['is_favorite' => $new_status],
            ['id' => $prompt_id]
        );
        
        return $result !== false ? $new_status : false;
    }
    
    /**
     * Create template from prompt
     */
    public function create_template($prompt_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_prompt_history';
        
        // Verify ownership
        $prompt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $prompt_id,
            $user_id
        ));
        
        if (!$prompt) {
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            ['is_template' => 1],
            ['id' => $prompt_id]
        );
        
        return $result !== false;
    }
    
    /**
     * Delete prompt
     */
    public function delete_prompt($prompt_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_prompt_history';
        
        // Verify ownership
        $result = $wpdb->delete(
            $table,
            [
                'id' => $prompt_id,
                'user_id' => $user_id
            ]
        );
        
        return $result !== false;
    }
    
    /**
     * Generate AI prompt suggestions
     */
    public function generate_prompt_suggestions($category, $context = '') {
        $openai = new OpenAI();
        
        if (!$openai->is_configured()) {
            return $this->get_default_suggestions($category);
        }
        
        $base_prompts = $this->get_category_base_prompts($category);
        $popular_prompts = $this->get_popular_prompts($category, 5);
        
        $prompt = "Based on the category '{$category}' and context '{$context}', suggest 5 improved prompt variations. ";
        $prompt .= "Here are some existing prompts in this category: ";
        
        foreach ($popular_prompts as $popular) {
            $prompt .= "- " . substr($popular->prompt_text, 0, 100) . "... ";
        }
        
        $prompt .= "Generate creative, effective prompts that would produce better results.";
        
        try {
            $response = $openai->optimize_content($prompt, 'prompt_suggestions', ['category' => $category]);
            
            if ($response && isset($response['suggestions'])) {
                return $response['suggestions'];
            }
        } catch (Exception $e) {
            error_log('MetaPix AI: Prompt suggestion failed - ' . $e->getMessage());
        }
        
        return $this->get_default_suggestions($category);
    }
    
    /**
     * Get default prompt suggestions
     */
    private function get_default_suggestions($category) {
        $suggestions = [
            'alt_text' => [
                'Describe this image in detail for accessibility, focusing on the main subject and important visual elements',
                'Generate concise ALT text that captures the essence of this image for screen readers',
                'Create descriptive ALT text that includes relevant keywords while maintaining accuracy',
                'Write accessible image description that conveys the mood and context of the scene',
                'Provide detailed visual description suitable for visually impaired users'
            ],
            'meta_tags' => [
                'Create compelling meta title and description that will improve click-through rates',
                'Generate SEO-optimized meta tags that include target keywords naturally',
                'Write engaging meta description that summarizes content and encourages clicks',
                'Create meta tags that balance SEO optimization with user appeal',
                'Generate meta title and description that stand out in search results'
            ],
            'content_optimization' => [
                'Analyze this content for SEO improvements and readability enhancements',
                'Suggest ways to improve content structure and keyword density',
                'Provide recommendations for better user engagement and SEO performance',
                'Identify opportunities to enhance content value and search visibility',
                'Review content for clarity, flow, and optimization opportunities'
            ],
            'image_description' => [
                'Provide a detailed, engaging description of this image for social media',
                'Create a compelling image caption that tells a story',
                'Write an image description that captures attention and encourages engagement',
                'Generate descriptive text that enhances the visual impact',
                'Create contextual description that adds value to the image'
            ]
        ];
        
        return $suggestions[$category] ?? $suggestions['alt_text'];
    }
    
    /**
     * Get category base prompts
     */
    private function get_category_base_prompts($category) {
        $base_prompts = [
            'alt_text' => 'Generate descriptive ALT text for this image',
            'meta_tags' => 'Create SEO-optimized meta title and description',
            'content_optimization' => 'Analyze and optimize this content for SEO',
            'image_description' => 'Write an engaging description for this image'
        ];
        
        return $base_prompts[$category] ?? $base_prompts['alt_text'];
    }
    
    /**
     * Record prompt usage
     */
    public function record_prompt_usage($user_id, $prompt_text, $category) {
        $this->save_prompt($user_id, $prompt_text, $category);
    }
    
    /**
     * Add prompt menu
     */
    public function add_prompt_menu() {
        add_submenu_page(
            'metapix-ai',
            'Prompt Library',
            'Prompts',
            'read',
            'metapix-ai-prompts',
            [$this, 'render_prompt_page']
        );
    }
    
    /**
     * Render prompt page
     */
    public function render_prompt_page() {
        $user_id = get_current_user_id();
        $current_tab = $_GET['tab'] ?? 'history';
        
        $history = $this->get_prompt_history($user_id, null, false, false, 20);
        $favorites = $this->get_prompt_history($user_id, null, true, false, 20);
        $templates = $this->get_prompt_history($user_id, null, false, true, 20);
        $popular = $this->get_popular_prompts(null, 20);
        
        ?>
        <div class="wrap">
            <h1>Prompt Library</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=metapix-ai-prompts&tab=history" 
                   class="nav-tab <?php echo $current_tab === 'history' ? 'nav-tab-active' : ''; ?>">
                    History (<?php echo count($history); ?>)
                </a>
                <a href="?page=metapix-ai-prompts&tab=favorites" 
                   class="nav-tab <?php echo $current_tab === 'favorites' ? 'nav-tab-active' : ''; ?>">
                    Favorites (<?php echo count($favorites); ?>)
                </a>
                <a href="?page=metapix-ai-prompts&tab=templates" 
                   class="nav-tab <?php echo $current_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
                    Templates (<?php echo count($templates); ?>)
                </a>
                <a href="?page=metapix-ai-prompts&tab=popular" 
                   class="nav-tab <?php echo $current_tab === 'popular' ? 'nav-tab-active' : ''; ?>">
                    Popular
                </a>
                <a href="?page=metapix-ai-prompts&tab=editor" 
                   class="nav-tab <?php echo $current_tab === 'editor' ? 'nav-tab-active' : ''; ?>">
                    Prompt Editor
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'history':
                        $this->render_prompt_history($history);
                        break;
                    case 'favorites':
                        $this->render_prompt_favorites($favorites);
                        break;
                    case 'templates':
                        $this->render_prompt_templates($templates);
                        break;
                    case 'popular':
                        $this->render_popular_prompts($popular);
                        break;
                    case 'editor':
                        $this->render_prompt_editor();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render prompt history
     */
    private function render_prompt_history($history) {
        ?>
        <div class="metapix-prompt-history">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="category-filter">
                        <option value="">All Categories</option>
                        <?php foreach (self::CATEGORIES as $key => $label): ?>
                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="submit" class="button" value="Filter" onclick="filterPrompts()">
                </div>
                <div class="alignright actions">
                    <button class="button button-primary" onclick="openPromptEditor()">New Prompt</button>
                </div>
            </div>
            
            <div class="metapix-prompts-grid">
                <?php foreach ($history as $prompt): ?>
                    <div class="prompt-card" data-category="<?php echo $prompt->prompt_category; ?>">
                        <div class="prompt-header">
                            <span class="prompt-category"><?php echo self::CATEGORIES[$prompt->prompt_category] ?? ucfirst($prompt->prompt_category); ?></span>
                            <div class="prompt-actions">
                                <button class="button-link favorite-btn <?php echo $prompt->is_favorite ? 'favorited' : ''; ?>" 
                                        onclick="toggleFavorite(<?php echo $prompt->id; ?>)" 
                                        title="<?php echo $prompt->is_favorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
                                    ★
                                </button>
                                <button class="button-link" onclick="editPrompt(<?php echo $prompt->id; ?>)" title="Edit">✏️</button>
                                <button class="button-link" onclick="deletePrompt(<?php echo $prompt->id; ?>)" title="Delete">🗑️</button>
                            </div>
                        </div>
                        <div class="prompt-content">
                            <p><?php echo esc_html(substr($prompt->prompt_text, 0, 200)); ?><?php echo strlen($prompt->prompt_text) > 200 ? '...' : ''; ?></p>
                        </div>
                        <div class="prompt-footer">
                            <span class="usage-count">Used <?php echo $prompt->usage_count; ?> times</span>
                            <span class="last-used">Last used: <?php echo human_time_diff(strtotime($prompt->last_used)); ?> ago</span>
                            <?php if ($prompt->is_template): ?>
                                <span class="template-badge">Template</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
        .metapix-prompts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .prompt-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .prompt-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .prompt-category {
            background: #0073aa;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .prompt-actions {
            display: flex;
            gap: 5px;
        }
        .favorite-btn.favorited {
            color: #ffc107;
        }
        .prompt-content p {
            margin: 0;
            line-height: 1.5;
        }
        .prompt-footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #666;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .template-badge {
            background: #28a745;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
        }
        </style>
        
        <script>
        function toggleFavorite(promptId) {
            jQuery.post(ajaxurl, {
                action: 'metapix_favorite_prompt',
                prompt_id: promptId,
                nonce: '<?php echo wp_create_nonce('metapix_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            });
        }
        
        function deletePrompt(promptId) {
            if (!confirm('Are you sure you want to delete this prompt?')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'metapix_delete_prompt',
                prompt_id: promptId,
                nonce: '<?php echo wp_create_nonce('metapix_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Render prompt editor
     */
    private function render_prompt_editor() {
        ?>
        <div class="metapix-prompt-editor">
            <div class="editor-container">
                <div class="editor-main">
                    <h3>Prompt Editor</h3>
                    
                    <form id="prompt-editor-form">
                        <div class="form-group">
                            <label for="prompt-category">Category:</label>
                            <select id="prompt-category" name="category" required>
                                <option value="">Select Category...</option>
                                <?php foreach (self::CATEGORIES as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="prompt-text">Prompt:</label>
                            <textarea id="prompt-text" name="prompt_text" rows="8" required 
                                      placeholder="Enter your prompt here..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="is-template" name="is_template" value="1">
                                Save as template
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="button" onclick="clearEditor()">Clear</button>
                            <button type="button" class="button" onclick="getSuggestions()">Get AI Suggestions</button>
                            <button type="submit" class="button button-primary">Save Prompt</button>
                        </div>
                    </form>
                </div>
                
                <div class="editor-sidebar">
                    <h4>AI Suggestions</h4>
                    <div id="ai-suggestions">
                        <p>Select a category and click "Get AI Suggestions" to see recommended prompts.</p>
                    </div>
                    
                    <h4>Quick Templates</h4>
                    <div id="quick-templates">
                        <?php
                        $user_id = get_current_user_id();
                        $templates = $this->get_prompt_history($user_id, null, false, true, 5);
                        foreach ($templates as $template):
                        ?>
                            <div class="template-item" onclick="loadTemplate('<?php echo esc_js($template->prompt_text); ?>', '<?php echo $template->prompt_category; ?>')">
                                <strong><?php echo self::CATEGORIES[$template->prompt_category] ?? ucfirst($template->prompt_category); ?></strong>
                                <p><?php echo esc_html(substr($template->prompt_text, 0, 100)); ?>...</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .editor-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-actions {
            text-align: right;
        }
        .form-actions .button {
            margin-left: 10px;
        }
        .editor-sidebar {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
        }
        .template-item {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .template-item:hover {
            background-color: #e9ecef;
        }
        .template-item strong {
            display: block;
            margin-bottom: 5px;
            color: #0073aa;
        }
        .template-item p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        #ai-suggestions {
            max-height: 300px;
            overflow-y: auto;
        }
        .suggestion-item {
            padding: 10px;
            border-left: 3px solid #0073aa;
            background: white;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .suggestion-item:hover {
            background-color: #f0f8ff;
        }
        </style>
        
        <script>
        function loadTemplate(promptText, category) {
            document.getElementById('prompt-text').value = promptText;
            document.getElementById('prompt-category').value = category;
        }
        
        function clearEditor() {
            document.getElementById('prompt-editor-form').reset();
        }
        
        function getSuggestions() {
            const category = document.getElementById('prompt-category').value;
            if (!category) {
                alert('Please select a category first.');
                return;
            }
            
            const suggestionsDiv = document.getElementById('ai-suggestions');
            suggestionsDiv.innerHTML = '<p>Loading suggestions...</p>';
            
            jQuery.post(ajaxurl, {
                action: 'metapix_suggest_prompts',
                category: category,
                nonce: '<?php echo wp_create_nonce('metapix_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    let html = '';
                    response.data.suggestions.forEach(function(suggestion) {
                        html += '<div class="suggestion-item" onclick="loadSuggestion(\'' + suggestion.replace(/'/g, "\\'") + '\')">';
                        html += suggestion;
                        html += '</div>';
                    });
                    suggestionsDiv.innerHTML = html;
                } else {
                    suggestionsDiv.innerHTML = '<p>Error loading suggestions.</p>';
                }
            });
        }
        
        function loadSuggestion(suggestion) {
            document.getElementById('prompt-text').value = suggestion;
        }
        
        // Handle form submission
        jQuery(document).ready(function($) {
            $('#prompt-editor-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = {
                    action: 'metapix_save_prompt',
                    category: $('#prompt-category').val(),
                    prompt_text: $('#prompt-text').val(),
                    is_template: $('#is-template').is(':checked') ? 1 : 0,
                    nonce: '<?php echo wp_create_nonce('metapix_admin_nonce'); ?>'
                };
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        alert('Prompt saved successfully!');
                        clearEditor();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Save prompt
     */
    public function ajax_save_prompt() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $prompt_text = sanitize_textarea_field($_POST['prompt_text']);
        $category = sanitize_text_field($_POST['category']);
        $is_template = intval($_POST['is_template']);
        
        if (empty($prompt_text) || empty($category)) {
            wp_send_json_error(['message' => 'Prompt text and category are required']);
        }
        
        $prompt_id = $this->save_prompt($user_id, $prompt_text, $category, $is_template);
        
        if ($prompt_id) {
            wp_send_json_success(['message' => 'Prompt saved successfully', 'prompt_id' => $prompt_id]);
        } else {
            wp_send_json_error(['message' => 'Failed to save prompt']);
        }
    }
    
    /**
     * AJAX: Get prompt history
     */
    public function ajax_get_prompt_history() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $category = sanitize_text_field($_POST['category'] ?? '');
        $favorites_only = intval($_POST['favorites_only'] ?? 0);
        $templates_only = intval($_POST['templates_only'] ?? 0);
        $limit = intval($_POST['limit'] ?? 20);
        $offset = intval($_POST['offset'] ?? 0);
        
        $history = $this->get_prompt_history($user_id, $category ?: null, $favorites_only, $templates_only, $limit, $offset);
        
        wp_send_json_success(['history' => $history]);
    }
    
    /**
     * AJAX: Delete prompt
     */
    public function ajax_delete_prompt() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $prompt_id = intval($_POST['prompt_id']);
        
        $result = $this->delete_prompt($prompt_id, $user_id);
        
        if ($result) {
            wp_send_json_success(['message' => 'Prompt deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete prompt']);
        }
    }
    
    /**
     * AJAX: Toggle favorite
     */
    public function ajax_favorite_prompt() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $prompt_id = intval($_POST['prompt_id']);
        
        $result = $this->toggle_favorite($prompt_id, $user_id);
        
        if ($result !== false) {
            wp_send_json_success(['message' => 'Favorite status updated', 'is_favorite' => $result]);
        } else {
            wp_send_json_error(['message' => 'Failed to update favorite status']);
        }
    }
    
    /**
     * AJAX: Suggest prompts
     */
    public function ajax_suggest_prompts() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $category = sanitize_text_field($_POST['category']);
        $context = sanitize_text_field($_POST['context'] ?? '');
        
        $suggestions = $this->generate_prompt_suggestions($category, $context);
        
        wp_send_json_success(['suggestions' => $suggestions]);
    }
    
    /**
     * Add prompt meta boxes
     */
    public function add_prompt_meta_boxes() {
        $screens = ['post', 'page', 'attachment'];
        
        foreach ($screens as $screen) {
            add_meta_box(
                'metapix-prompt-helper',
                'MetaPix AI - Prompt Helper',
                [$this, 'render_prompt_helper_meta_box'],
                $screen,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render prompt helper meta box
     */
    public function render_prompt_helper_meta_box($post) {
        $user_id = get_current_user_id();
        $recent_prompts = $this->get_prompt_history($user_id, null, false, false, 5);
        
        ?>
        <div id="metapix-prompt-helper">
            <h4>Recent Prompts</h4>
            <div class="recent-prompts">
                <?php foreach ($recent_prompts as $prompt): ?>
                    <div class="prompt-item" onclick="usePrompt('<?php echo esc_js($prompt->prompt_text); ?>')">
                        <small><?php echo self::CATEGORIES[$prompt->prompt_category] ?? ucfirst($prompt->prompt_category); ?></small>
                        <p><?php echo esc_html(substr($prompt->prompt_text, 0, 80)); ?>...</p>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" class="button button-small" onclick="openPromptLibrary()">
                View All Prompts
            </button>
        </div>
        
        <style>
        .prompt-item {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .prompt-item:hover {
            background-color: #f0f8ff;
        }
        .prompt-item small {
            color: #0073aa;
            font-weight: bold;
        }
        .prompt-item p {
            margin: 3px 0 0 0;
            font-size: 12px;
        }
        </style>
        
        <script>
        function usePrompt(promptText) {
            // This would integrate with whatever prompt input field is active
            alert('Prompt copied: ' + promptText.substring(0, 50) + '...');
            navigator.clipboard.writeText(promptText);
        }
        
        function openPromptLibrary() {
            window.open('<?php echo admin_url('admin.php?page=metapix-ai-prompts'); ?>', '_blank');
        }
        </script>
        <?php
    }
    
    /**
     * Enqueue prompt scripts
     */
    public function enqueue_prompt_scripts($hook) {
        if (strpos($hook, 'metapix-ai-prompts') !== false || in_array($hook, ['post.php', 'post-new.php'])) {
            wp_enqueue_script('metapix-prompts', METAPIX_AI_PLUGIN_URL . 'assets/js/prompts.js', ['jquery'], METAPIX_AI_VERSION, true);
            wp_enqueue_style('metapix-prompts', METAPIX_AI_PLUGIN_URL . 'assets/css/prompts.css', [], METAPIX_AI_VERSION);
            
            wp_localize_script('metapix-prompts', 'metapixPromptsAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('metapix_admin_nonce')
            ]);
        }
    }
}