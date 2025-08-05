# MetaPix AI - WordPress SEO Plugin

MetaPix AI is a fully autonomous WordPress SEO assistant that uses artificial intelligence to optimize every aspect of your website's on-page SEO, including images, content structure, metadata, linking, schema markup, and performance.

## 🚀 Features

### Core Modules

#### 1. **Image SEO Engine**
- **GPT-4 Vision Integration**: Automatically generates descriptive, SEO-friendly ALT text using AI image analysis
- **Smart Context Analysis**: Uses surrounding content to create relevant ALT text
- **Image Optimization**: Detects poor filenames, suggests WebP format, compression optimization
- **Lazy Loading Detection**: Identifies images missing lazy loading attributes
- **Bulk Processing**: Optimize hundreds of images with one click

#### 2. **Content Optimizer**
- **AI-Powered Analysis**: Comprehensive content analysis using GPT-4
- **SEO Score Calculation**: Real-time scoring across multiple factors
- **Meta Tag Generation**: AI-generated meta titles and descriptions
- **Readability Analysis**: Flesch Reading Ease scoring and grade level detection
- **Keyword Optimization**: Density analysis and placement recommendations
- **Content Structure**: H1-H6 hierarchy validation and paragraph length analysis

#### 3. **Linking & Structure Module**
- **Internal Linking Suggestions**: AI-powered internal link recommendations
- **Schema Markup Generation**: Automatic JSON-LD schema for Articles, Products, FAQs, etc.
- **Heading Structure Validation**: Proper H1-H6 hierarchy checking
- **Broken Link Detection**: Identifies and reports broken internal/external links
- **Anchor Text Optimization**: Suggests improvements for link anchor text

#### 4. **Performance Analyzer**
- **Google PageSpeed Integration**: Real-time Core Web Vitals monitoring
- **LCP, CLS, FID Tracking**: Complete Core Web Vitals analysis
- **Mobile/Desktop Testing**: Separate performance metrics for different devices
- **Actionable Recommendations**: AI-generated performance improvement suggestions

#### 5. **Competitor Intelligence**
- **SERP Analysis**: Analyzes top 5 competitors for target keywords
- **Content Gap Analysis**: Identifies missing content opportunities
- **Backlink Insights**: Domain Authority and Page Authority tracking
- **Strategy Recommendations**: AI-powered competitive analysis

#### 6. **Analytics Integration**
- **Google Analytics 4**: Seamless GA4 integration for enhanced reporting
- **Performance Tracking**: Bounce rate, session duration, organic traffic
- **ROI Measurement**: Track SEO improvements over time

### 🎯 Operation Modes

- **Autonomous Mode**: Automatic background optimization on publish/update
- **Manual Mode**: User approval required for all optimizations
- **Hybrid Mode**: Admin-configurable automation rules

## 📁 Plugin Structure

```
metapix-ai/
├── metapix-ai.php                 # Main plugin file
├── includes/
│   ├── Core/
│   │   ├── Database.php           # Database operations & schema
│   │   ├── Settings.php           # Plugin settings management
│   │   ├── License.php            # License validation & management
│   │   ├── Scheduler.php          # Cron job management
│   │   └── Notifications.php     # User notification system
│   ├── Modules/
│   │   ├── ImageSEO.php           # Image optimization & ALT text generation
│   │   ├── ContentOptimizer.php   # Content analysis & optimization
│   │   ├── LinkingStructure.php   # Internal linking & schema
│   │   ├── PerformanceAnalyzer.php # PageSpeed & Core Web Vitals
│   │   ├── CompetitorIntelligence.php # Competitor analysis
│   │   └── Analytics.php          # GA4 integration & reporting
│   ├── Services/
│   │   ├── OpenAI.php            # GPT-4 & GPT-4 Vision API
│   │   ├── PageSpeed.php         # Google PageSpeed Insights API
│   │   └── GoogleAnalytics.php   # Google Analytics 4 API
│   ├── Admin/
│   │   ├── Admin.php             # Admin interface controller
│   │   ├── Dashboard.php         # Main dashboard & analytics
│   │   └── Settings.php          # Settings page & configuration
│   └── API/
│       └── RestAPI.php           # WordPress REST API endpoints
├── assets/
│   ├── css/
│   │   ├── admin.css             # Admin dashboard styles
│   │   ├── content-optimizer.css # Content optimizer styles
│   │   └── image-seo.css         # Image SEO styles
│   └── js/
│       ├── admin.js              # Admin dashboard functionality
│       ├── content-optimizer.js  # Content optimizer scripts
│       └── image-seo.js          # Image SEO scripts
└── languages/                    # Translation files
```

## 🛠 Installation & Setup

### Requirements
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+
- OpenAI API Key (for AI features)
- Google PageSpeed API Key (optional, for performance monitoring)
- Google Analytics 4 Property (optional, for enhanced analytics)

### Installation Steps

1. **Download & Install**
   ```bash
   # Upload plugin files to wp-content/plugins/metapix-ai/
   # Or install via WordPress admin
   ```

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "MetaPix AI" and click "Activate"

3. **Configure License**
   - Navigate to MetaPix AI → Settings
   - Enter your license key
   - Select your plan (Free, Pro, Business, Agency)

4. **API Configuration**
   ```php
   // Required: OpenAI API Key
   Settings → AI Configuration → OpenAI API Key
   
   // Optional: Google PageSpeed API Key
   Settings → Performance Monitoring → PageSpeed API Key
   
   // Optional: Google Analytics 4
   Settings → Analytics Integration → GA4 Property ID
   ```

5. **Choose Operation Mode**
   - **Manual**: Requires approval for all optimizations
   - **Autonomous**: Automatic optimization on publish/update

## 🔧 API Integration

### OpenAI GPT-4 Vision Setup
```php
// Image analysis prompt structure
$prompt = [
    'system' => 'You are an expert SEO specialist focused on creating optimal ALT text for images.',
    'user' => 'Analyze this image and generate SEO-friendly ALT text...'
];

// API call example
$response = $openai->analyze_image($image_url, $prompt);
```

### Google PageSpeed Integration
```php
// Performance analysis
$performance_data = $pagespeed->analyze_url($url, $device);
// Returns: LCP, FID, CLS, Performance Score, Opportunities
```

### REST API Endpoints

#### Image SEO
- `POST /wp-json/metapix-ai/v1/images/analyze` - Analyze single image
- `POST /wp-json/metapix-ai/v1/images/generate-alt` - Generate ALT text
- `POST /wp-json/metapix-ai/v1/images/bulk-optimize` - Bulk image optimization

#### Content Optimization
- `POST /wp-json/metapix-ai/v1/content/analyze` - Analyze post content
- `POST /wp-json/metapix-ai/v1/content/generate-meta` - Generate meta tags
- `POST /wp-json/metapix-ai/v1/content/optimize` - AI content optimization

#### SEO Scores & Analytics
- `GET /wp-json/metapix-ai/v1/scores/{post_id}` - Get SEO scores
- `GET /wp-json/metapix-ai/v1/scores/bulk` - Bulk SEO scores
- `GET /wp-json/metapix-ai/v1/history` - Optimization history

## 💰 Pricing Plans

### Free Plan
- 1 website
- 100 AI API calls/month
- Basic image optimization
- Manual mode only

### Pro Plan - $49/year
- Up to 3 websites
- 1,000 AI API calls/month
- All optimization modules
- Autonomous mode
- Performance monitoring
- Email support

### Business Plan - $99/year
- Up to 10 websites
- 5,000 AI API calls/month
- Competitor intelligence
- Advanced analytics
- Priority support
- Custom schema types

### Agency Plan - $1,299/year
- Up to 300 websites
- 50,000 AI API calls/month
- White-label options
- Advanced reporting
- Dedicated support
- Custom integrations

## 🔐 Payment Methods

### Traditional Payments
- **Stripe**: Credit cards, Apple Pay, Google Pay
- **PayPal**: PayPal balance, credit cards, bank transfers

### Cryptocurrency Payments
- **CoinGate**: BTC, ETH, LTC, and 70+ cryptocurrencies
- **NOWPayments**: 300+ cryptocurrencies
- **Coinbase Commerce**: BTC, ETH, USDC, DAI
- **Custom Crypto**: Direct wallet payments

## 📊 Database Schema

### Core Tables

#### `wp_metapix_optimization_history`
```sql
CREATE TABLE wp_metapix_optimization_history (
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
    PRIMARY KEY (id)
);
```

#### `wp_metapix_seo_scores`
```sql
CREATE TABLE wp_metapix_seo_scores (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    post_id bigint(20) NOT NULL,
    overall_score decimal(5,2) DEFAULT 0,
    image_score decimal(5,2) DEFAULT 0,
    content_score decimal(5,2) DEFAULT 0,
    technical_score decimal(5,2) DEFAULT 0,
    performance_score decimal(5,2) DEFAULT 0,
    schema_score decimal(5,2) DEFAULT 0,
    last_analyzed datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
```

## 🤖 AI Prompt Examples

### ALT Text Generation
```php
$prompt = [
    'system' => 'You are an expert SEO specialist focused on creating optimal ALT text for images.',
    'user' => "Analyze this image and generate optimal ALT text following these guidelines:
    
    1. Be descriptive but concise (under 125 characters)
    2. Include relevant keywords naturally
    3. Focus on what's important for SEO and accessibility
    4. Don't start with 'Image of' or 'Picture of'
    5. Consider the context where this image appears
    
    Context Information:
    - Post Title: {$post_title}
    - Surrounding Content: {$context}
    
    Respond with JSON format:
    {
        \"alt_text\": \"your generated alt text here\",
        \"confidence\": 0.95,
        \"keywords_used\": [\"keyword1\", \"keyword2\"],
        \"reasoning\": \"brief explanation of your choice\"
    }"
];
```

### Meta Description Generation
```php
$prompt = [
    'system' => 'You are an expert SEO specialist. Create compelling meta descriptions that improve click-through rates.',
    'user' => "Generate an optimized meta description for this content:
    
    Title: {$title}
    Content: {$content_preview}
    Target Keywords: {$keywords}
    
    Guidelines:
    - 150-160 characters
    - Include primary keyword
    - Include a call-to-action
    - Be descriptive and enticing
    
    Respond with JSON format."
];
```

### Content SEO Analysis
```php
$prompt = [
    'system' => 'You are an expert SEO analyst. Provide detailed analysis and actionable recommendations.',
    'user' => "Analyze this content for SEO optimization:
    
    Content: {$content}
    Target Keywords: {$keywords}
    
    Provide analysis on:
    1. Keyword usage and density
    2. Content structure (headings, paragraphs)
    3. Readability and user experience
    4. Missing SEO elements
    5. Internal linking opportunities
    6. Content length and depth
    7. Call-to-action presence"
];
```

## 🔄 Cron Jobs & Automation

### Scheduled Tasks
```php
// Daily content scan
wp_schedule_event(time(), 'daily', 'metapix_ai_daily_scan');

// Weekly performance reports
wp_schedule_event(time(), 'weekly', 'metapix_ai_weekly_report');

// Bi-daily performance checks
wp_schedule_event(time(), 'twicedaily', 'metapix_ai_performance_check');
```

### Autonomous Mode Triggers
- `save_post` - Content analysis on post save
- `add_attachment` - Image optimization on upload
- `wp_insert_post` - New post SEO setup

## 📈 Performance Optimization

### Caching Strategy
- **Transient Cache**: API responses cached for 1 hour
- **Object Cache**: Database queries optimized with WP Object Cache
- **Rate Limiting**: API calls throttled to prevent overuse

### Database Optimization
- **Indexes**: Optimized database indexes for fast queries
- **Cleanup**: Automatic cleanup of old optimization history
- **Archiving**: Long-term data archival for performance

## 🛡️ Security Features

### API Security
- **Nonce Verification**: All AJAX requests verified
- **Capability Checks**: User permission validation
- **Input Sanitization**: All inputs sanitized and validated
- **Rate Limiting**: API abuse prevention

### License Protection
- **Domain Validation**: License tied to specific domains
- **Usage Tracking**: API call monitoring and limits
- **Encryption**: Sensitive data encrypted in database

## 🔧 Development & Customization

### Hooks & Filters
```php
// Customize ALT text generation
add_filter('metapix_ai_alt_text_prompt', function($prompt, $context) {
    // Modify prompt for specific use cases
    return $prompt;
}, 10, 2);

// Modify SEO scoring weights
add_filter('metapix_ai_seo_score_weights', function($weights) {
    $weights['content_score'] = 0.40; // Increase content weight
    return $weights;
});

// Custom optimization actions
add_action('metapix_ai_optimization_complete', function($post_id, $module, $results) {
    // Custom actions after optimization
}, 10, 3);
```

### Custom Modules
```php
// Register custom optimization module
class CustomSEOModule {
    public function __construct() {
        add_action('metapix_ai_modules_loaded', [$this, 'register_module']);
    }
    
    public function register_module() {
        // Register custom module logic
    }
}
```

## 📞 Support & Documentation

### Support Channels
- **Email Support**: support@metapix.ai
- **Documentation**: https://docs.metapix.ai
- **Community Forum**: https://community.metapix.ai
- **GitHub Issues**: https://github.com/metapix-ai/wordpress-plugin

### License & Legal
- **License**: GPL v2 or later
- **Privacy Policy**: Full GDPR compliance
- **Terms of Service**: Available at https://metapix.ai/terms
- **Data Processing**: All data processed securely with encryption

---

**MetaPix AI** - Transforming WordPress SEO with Artificial Intelligence

For more information, visit: https://metapix.ai