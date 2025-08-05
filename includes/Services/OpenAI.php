<?php

namespace MetaPixAI\Services;

/**
 * OpenAI API Service
 */
class OpenAI {
    
    /**
     * API base URL
     */
    const API_BASE_URL = 'https://api.openai.com/v1';
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('metapix_ai_openai_api_key', '');
    }
    
    /**
     * Check if OpenAI is configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * Analyze image with GPT-4 Vision
     */
    public function analyze_image($image_url, $prompt) {
        if (!$this->is_configured()) {
            throw new \Exception('OpenAI API key not configured');
        }
        
        $messages = [
            [
                'role' => 'system',
                'content' => $prompt['system']
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt['user']
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $image_url,
                            'detail' => 'high'
                        ]
                    ]
                ]
            ]
        ];
        
        $response = $this->make_request('/chat/completions', [
            'model' => 'gpt-4-vision-preview',
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.3
        ]);
        
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
            
            // Try to parse JSON response
            $json_data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json_data;
            }
            
            // If not JSON, return as plain text
            return ['alt_text' => $content, 'confidence' => 0.8];
        }
        
        throw new \Exception('Invalid response from OpenAI API');
    }
    
    /**
     * Optimize content with GPT-4
     */
    public function optimize_content($content, $optimization_type, $context = []) {
        if (!$this->is_configured()) {
            throw new \Exception('OpenAI API key not configured');
        }
        
        $prompt = $this->get_content_optimization_prompt($optimization_type, $content, $context);
        
        $response = $this->make_request('/chat/completions', [
            'model' => 'gpt-4-turbo-preview',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $prompt['system']
                ],
                [
                    'role' => 'user',
                    'content' => $prompt['user']
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.4
        ]);
        
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
            
            // Try to parse JSON response
            $json_data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json_data;
            }
            
            return ['optimized_content' => $content, 'confidence' => 0.8];
        }
        
        throw new \Exception('Invalid response from OpenAI API');
    }
    
    /**
     * Generate meta title and description
     */
    public function generate_meta_tags($title, $content, $keywords = []) {
        if (!$this->is_configured()) {
            throw new \Exception('OpenAI API key not configured');
        }
        
        $prompt = $this->get_meta_tags_prompt($title, $content, $keywords);
        
        $response = $this->make_request('/chat/completions', [
            'model' => 'gpt-4-turbo-preview',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $prompt['system']
                ],
                [
                    'role' => 'user',
                    'content' => $prompt['user']
                ]
            ],
            'max_tokens' => 300,
            'temperature' => 0.3
        ]);
        
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
            
            $json_data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json_data;
            }
        }
        
        throw new \Exception('Invalid response from OpenAI API');
    }
    
    /**
     * Analyze content for SEO improvements
     */
    public function analyze_content_seo($content, $target_keywords = []) {
        if (!$this->is_configured()) {
            throw new \Exception('OpenAI API key not configured');
        }
        
        $prompt = $this->get_seo_analysis_prompt($content, $target_keywords);
        
        $response = $this->make_request('/chat/completions', [
            'model' => 'gpt-4-turbo-preview',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $prompt['system']
                ],
                [
                    'role' => 'user',
                    'content' => $prompt['user']
                ]
            ],
            'max_tokens' => 800,
            'temperature' => 0.2
        ]);
        
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
            
            $json_data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json_data;
            }
        }
        
        throw new \Exception('Invalid response from OpenAI API');
    }
    
    /**
     * Generate schema markup suggestions
     */
    public function generate_schema_markup($content_type, $content, $metadata = []) {
        if (!$this->is_configured()) {
            throw new \Exception('OpenAI API key not configured');
        }
        
        $prompt = $this->get_schema_generation_prompt($content_type, $content, $metadata);
        
        $response = $this->make_request('/chat/completions', [
            'model' => 'gpt-4-turbo-preview',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $prompt['system']
                ],
                [
                    'role' => 'user',
                    'content' => $prompt['user']
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.1
        ]);
        
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
            
            $json_data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json_data;
            }
        }
        
        throw new \Exception('Invalid response from OpenAI API');
    }
    
    /**
     * Make API request to OpenAI
     */
    private function make_request($endpoint, $data) {
        $url = self::API_BASE_URL . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'User-Agent: MetaPix-AI-Plugin/1.0.0'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }
        
        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : 'HTTP error ' . $http_code;
            throw new \Exception('OpenAI API error: ' . $error_message);
        }
        
        $decoded_response = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from OpenAI API');
        }
        
        // Track API usage
        $this->track_api_usage($endpoint, $data);
        
        return $decoded_response;
    }
    
    /**
     * Get content optimization prompt
     */
    private function get_content_optimization_prompt($type, $content, $context) {
        $prompts = [
            'meta_title' => [
                'system' => 'You are an expert SEO specialist. Create compelling, SEO-optimized meta titles that drive clicks and rank well.',
                'user' => "Generate an optimized meta title for this content:\n\nContent: {$content}\n\nTarget Keywords: " . implode(', ', $context['keywords'] ?? []) . "\n\nGuidelines:\n- 50-60 characters\n- Include primary keyword naturally\n- Make it compelling and click-worthy\n- Avoid keyword stuffing\n\nRespond with JSON: {\"meta_title\": \"your title\", \"character_count\": 55, \"keywords_used\": [\"keyword1\"], \"reasoning\": \"explanation\"}"
            ],
            'meta_description' => [
                'system' => 'You are an expert SEO specialist. Create compelling meta descriptions that improve click-through rates.',
                'user' => "Generate an optimized meta description for this content:\n\nTitle: {$context['title']}\nContent: {$content}\n\nTarget Keywords: " . implode(', ', $context['keywords'] ?? []) . "\n\nGuidelines:\n- 150-160 characters\n- Include primary keyword\n- Include a call-to-action\n- Be descriptive and enticing\n\nRespond with JSON: {\"meta_description\": \"your description\", \"character_count\": 155, \"keywords_used\": [\"keyword1\"], \"cta_included\": true}"
            ],
            'content_improvement' => [
                'system' => 'You are an expert content strategist focused on SEO optimization and readability.',
                'user' => "Analyze this content and suggest improvements:\n\nContent: {$content}\n\nTarget Keywords: " . implode(', ', $context['keywords'] ?? []) . "\n\nAnalyze for:\n- Keyword density and placement\n- Content structure and headings\n- Readability score\n- Missing elements (CTAs, internal links)\n- Content gaps\n\nRespond with JSON format with specific suggestions."
            ]
        ];
        
        return $prompts[$type] ?? $prompts['content_improvement'];
    }
    
    /**
     * Get meta tags generation prompt
     */
    private function get_meta_tags_prompt($title, $content, $keywords) {
        return [
            'system' => 'You are an expert SEO specialist. Generate optimized meta title and description that improve search rankings and click-through rates.',
            'user' => "Generate SEO-optimized meta tags for this content:\n\nCurrent Title: {$title}\nContent Preview: " . substr(strip_tags($content), 0, 500) . "\nTarget Keywords: " . implode(', ', $keywords) . "\n\nRequirements:\n- Meta title: 50-60 characters, include primary keyword\n- Meta description: 150-160 characters, compelling with CTA\n- Natural keyword integration\n- Click-worthy and descriptive\n\nRespond with JSON:\n{\n  \"meta_title\": \"optimized title\",\n  \"meta_description\": \"optimized description\",\n  \"title_length\": 55,\n  \"description_length\": 155,\n  \"keywords_used\": [\"keyword1\", \"keyword2\"],\n  \"confidence\": 0.9\n}"
        ];
    }
    
    /**
     * Get SEO analysis prompt
     */
    private function get_seo_analysis_prompt($content, $keywords) {
        return [
            'system' => 'You are an expert SEO analyst. Provide detailed analysis and actionable recommendations for content optimization.',
            'user' => "Analyze this content for SEO optimization:\n\nContent: {$content}\n\nTarget Keywords: " . implode(', ', $keywords) . "\n\nProvide analysis on:\n1. Keyword usage and density\n2. Content structure (headings, paragraphs)\n3. Readability and user experience\n4. Missing SEO elements\n5. Internal linking opportunities\n6. Content length and depth\n7. Call-to-action presence\n\nRespond with JSON format:\n{\n  \"overall_score\": 75,\n  \"keyword_analysis\": {\n    \"density\": 2.5,\n    \"placement\": \"good\",\n    \"suggestions\": [\"suggestion1\"]\n  },\n  \"content_structure\": {\n    \"headings_score\": 80,\n    \"paragraph_length\": \"good\",\n    \"suggestions\": []\n  },\n  \"readability\": {\n    \"score\": 85,\n    \"grade_level\": \"8th grade\",\n    \"suggestions\": []\n  },\n  \"missing_elements\": [\"internal_links\", \"cta\"],\n  \"recommendations\": [\n    {\n      \"priority\": \"high\",\n      \"element\": \"meta_description\",\n      \"suggestion\": \"Add compelling meta description\",\n      \"impact\": \"Improve CTR by 15-25%\"\n    }\n  ]\n}"
        ];
    }
    
    /**
     * Get schema generation prompt
     */
    private function get_schema_generation_prompt($content_type, $content, $metadata) {
        return [
            'system' => 'You are an expert in structured data and schema markup. Generate valid JSON-LD schema markup that enhances search engine understanding.',
            'user' => "Generate appropriate schema markup for this content:\n\nContent Type: {$content_type}\nContent: " . substr(strip_tags($content), 0, 800) . "\nMetadata: " . json_encode($metadata) . "\n\nGenerate valid JSON-LD schema markup. Consider these schema types based on content:\n- Article/BlogPosting\n- Product\n- Review\n- FAQ\n- HowTo\n- Organization\n- LocalBusiness\n\nRespond with JSON:\n{\n  \"schema_type\": \"Article\",\n  \"schema_markup\": {\n    \"@context\": \"https://schema.org\",\n    \"@type\": \"Article\",\n    \"headline\": \"title\",\n    \"description\": \"description\",\n    \"author\": {\n      \"@type\": \"Person\",\n      \"name\": \"Author Name\"\n    },\n    \"datePublished\": \"2024-01-01\",\n    \"image\": \"image-url\"\n  },\n  \"confidence\": 0.95,\n  \"additional_schemas\": [\"FAQ\", \"BreadcrumbList\"]\n}"
        ];
    }
    
    /**
     * Track API usage for licensing
     */
    private function track_api_usage($endpoint, $data) {
        $license_key = get_option('metapix_ai_license_key', '');
        
        if (empty($license_key)) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_license_usage';
        
        // Increment API calls used
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET api_calls_used = api_calls_used + 1 WHERE license_key = %s",
            $license_key
        ));
        
        // Check if limit exceeded
        $usage = $wpdb->get_row($wpdb->prepare(
            "SELECT api_calls_used, api_calls_limit FROM $table WHERE license_key = %s",
            $license_key
        ));
        
        if ($usage && $usage->api_calls_used >= $usage->api_calls_limit) {
            // Create notification about limit exceeded
            \MetaPixAI\Core\Database::create_notification([
                'type' => 'warning',
                'title' => 'API Limit Exceeded',
                'message' => 'Your monthly API call limit has been reached. Please upgrade your plan to continue using AI features.',
                'priority' => 'high'
            ]);
        }
    }
    
    /**
     * Get remaining API calls
     */
    public function get_remaining_api_calls() {
        $license_key = get_option('metapix_ai_license_key', '');
        
        if (empty($license_key)) {
            return 0;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_license_usage';
        
        $usage = $wpdb->get_row($wpdb->prepare(
            "SELECT api_calls_used, api_calls_limit FROM $table WHERE license_key = %s",
            $license_key
        ));
        
        if (!$usage) {
            return 0;
        }
        
        return max(0, $usage->api_calls_limit - $usage->api_calls_used);
    }
    
    /**
     * Check if API calls are available
     */
    public function has_api_calls_available() {
        return $this->get_remaining_api_calls() > 0;
    }
}