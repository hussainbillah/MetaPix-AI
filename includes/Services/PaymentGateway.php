<?php

namespace MetaPixAI\Services;

use MetaPixAI\Core\Credits;
use MetaPixAI\Core\Database;

/**
 * Payment Gateway Service
 */
class PaymentGateway {
    
    /**
     * Available payment gateways
     */
    const GATEWAYS = [
        'stripe' => [
            'label' => 'Stripe',
            'methods' => ['card', 'apple_pay', 'google_pay'],
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
            'fee_percentage' => 2.9
        ],
        'paypal' => [
            'label' => 'PayPal',
            'methods' => ['paypal', 'card'],
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
            'fee_percentage' => 3.49
        ],
        'sslcommerz' => [
            'label' => 'SSLCommerz',
            'methods' => ['card', 'mobile_banking', 'internet_banking'],
            'currencies' => ['BDT', 'USD', 'EUR'],
            'fee_percentage' => 2.5
        ],
        'crypto' => [
            'label' => 'Cryptocurrency',
            'methods' => ['bitcoin', 'ethereum', 'usdt', 'usdc'],
            'currencies' => ['BTC', 'ETH', 'USDT', 'USDC'],
            'fee_percentage' => 1.0
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_metapix_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_metapix_verify_payment', [$this, 'ajax_verify_payment']);
        add_action('wp_ajax_nopriv_metapix_webhook_stripe', [$this, 'handle_stripe_webhook']);
        add_action('wp_ajax_nopriv_metapix_webhook_paypal', [$this, 'handle_paypal_webhook']);
        add_action('wp_ajax_nopriv_metapix_webhook_crypto', [$this, 'handle_crypto_webhook']);
    }
    
    /**
     * Create payment intent
     */
    public function create_payment($user_id, $package, $gateway, $method = 'card') {
        $packages = Credits::get_packages();
        
        if (!array_key_exists($package, $packages)) {
            return ['success' => false, 'message' => 'Invalid package'];
        }
        
        if (!array_key_exists($gateway, self::GATEWAYS)) {
            return ['success' => false, 'message' => 'Invalid payment gateway'];
        }
        
        $package_data = $packages[$package];
        $amount = $package_data['price'];
        
        switch ($gateway) {
            case 'stripe':
                return $this->create_stripe_payment($user_id, $package_data, $amount, $method);
            case 'paypal':
                return $this->create_paypal_payment($user_id, $package_data, $amount);
            case 'sslcommerz':
                return $this->create_sslcommerz_payment($user_id, $package_data, $amount);
            case 'crypto':
                return $this->create_crypto_payment($user_id, $package_data, $amount, $method);
            default:
                return ['success' => false, 'message' => 'Gateway not implemented'];
        }
    }
    
    /**
     * Create Stripe payment
     */
    private function create_stripe_payment($user_id, $package_data, $amount, $method) {
        $stripe_key = get_option('metapix_ai_stripe_secret_key', '');
        
        if (empty($stripe_key)) {
            return ['success' => false, 'message' => 'Stripe not configured'];
        }
        
        try {
            \Stripe\Stripe::setApiKey($stripe_key);
            
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => 'usd',
                'payment_method_types' => [$method === 'card' ? 'card' : $method],
                'metadata' => [
                    'user_id' => $user_id,
                    'package' => $package_data['label'],
                    'credits' => $package_data['credits'] + $package_data['bonus']
                ]
            ]);
            
            return [
                'success' => true,
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id,
                'amount' => $amount
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Create PayPal payment
     */
    private function create_paypal_payment($user_id, $package_data, $amount) {
        $paypal_client_id = get_option('metapix_ai_paypal_client_id', '');
        $paypal_secret = get_option('metapix_ai_paypal_secret', '');
        $is_sandbox = get_option('metapix_ai_paypal_sandbox', true);
        
        if (empty($paypal_client_id) || empty($paypal_secret)) {
            return ['success' => false, 'message' => 'PayPal not configured'];
        }
        
        $base_url = $is_sandbox ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
        
        try {
            // Get access token
            $token_response = wp_remote_post($base_url . '/v1/oauth2/token', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                    'Authorization' => 'Basic ' . base64_encode($paypal_client_id . ':' . $paypal_secret)
                ],
                'body' => 'grant_type=client_credentials'
            ]);
            
            if (is_wp_error($token_response)) {
                return ['success' => false, 'message' => 'PayPal connection failed'];
            }
            
            $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
            $access_token = $token_data['access_token'];
            
            // Create order
            $order_data = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => number_format($amount, 2, '.', '')
                    ],
                    'description' => $package_data['label'] . ' - ' . ($package_data['credits'] + $package_data['bonus']) . ' credits'
                ]],
                'application_context' => [
                    'return_url' => admin_url('admin.php?page=metapix-ai-payment-success'),
                    'cancel_url' => admin_url('admin.php?page=metapix-ai-payment-cancel')
                ]
            ];
            
            $order_response = wp_remote_post($base_url . '/v2/checkout/orders', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token
                ],
                'body' => json_encode($order_data)
            ]);
            
            if (is_wp_error($order_response)) {
                return ['success' => false, 'message' => 'PayPal order creation failed'];
            }
            
            $order = json_decode(wp_remote_retrieve_body($order_response), true);
            
            return [
                'success' => true,
                'order_id' => $order['id'],
                'approval_url' => $order['links'][1]['href'] ?? '',
                'amount' => $amount
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Create SSLCommerz payment
     */
    private function create_sslcommerz_payment($user_id, $package_data, $amount) {
        $store_id = get_option('metapix_ai_sslcommerz_store_id', '');
        $store_password = get_option('metapix_ai_sslcommerz_store_password', '');
        $is_sandbox = get_option('metapix_ai_sslcommerz_sandbox', true);
        
        if (empty($store_id) || empty($store_password)) {
            return ['success' => false, 'message' => 'SSLCommerz not configured'];
        }
        
        $base_url = $is_sandbox ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';
        
        $transaction_id = 'METAPIX_' . $user_id . '_' . time();
        
        $post_data = [
            'store_id' => $store_id,
            'store_passwd' => $store_password,
            'total_amount' => $amount,
            'currency' => 'USD',
            'tran_id' => $transaction_id,
            'success_url' => admin_url('admin.php?page=metapix-ai-payment-success'),
            'fail_url' => admin_url('admin.php?page=metapix-ai-payment-fail'),
            'cancel_url' => admin_url('admin.php?page=metapix-ai-payment-cancel'),
            'cus_name' => get_userdata($user_id)->display_name,
            'cus_email' => get_userdata($user_id)->user_email,
            'cus_phone' => '',
            'cus_add1' => '',
            'cus_city' => '',
            'cus_country' => '',
            'product_name' => $package_data['label'],
            'product_category' => 'Credits',
            'product_profile' => 'general'
        ];
        
        try {
            $response = wp_remote_post($base_url . '/gwprocess/v4/api.php', [
                'body' => $post_data,
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                return ['success' => false, 'message' => 'SSLCommerz connection failed'];
            }
            
            $response_data = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($response_data['status'] === 'SUCCESS') {
                return [
                    'success' => true,
                    'session_key' => $response_data['sessionkey'],
                    'gateway_url' => $response_data['GatewayPageURL'],
                    'transaction_id' => $transaction_id,
                    'amount' => $amount
                ];
            } else {
                return ['success' => false, 'message' => $response_data['failedreason'] ?? 'Payment failed'];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Create cryptocurrency payment
     */
    private function create_crypto_payment($user_id, $package_data, $amount, $currency) {
        $coinbase_api_key = get_option('metapix_ai_coinbase_api_key', '');
        $coinbase_webhook_secret = get_option('metapix_ai_coinbase_webhook_secret', '');
        
        if (empty($coinbase_api_key)) {
            return ['success' => false, 'message' => 'Cryptocurrency payments not configured'];
        }
        
        try {
            $charge_data = [
                'name' => $package_data['label'],
                'description' => $package_data['credits'] . ' credits + ' . $package_data['bonus'] . ' bonus credits',
                'pricing_type' => 'fixed_price',
                'local_price' => [
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => 'USD'
                ],
                'metadata' => [
                    'user_id' => $user_id,
                    'package' => $package_data['label'],
                    'credits' => $package_data['credits'] + $package_data['bonus']
                ],
                'redirect_url' => admin_url('admin.php?page=metapix-ai-payment-success'),
                'cancel_url' => admin_url('admin.php?page=metapix-ai-payment-cancel')
            ];
            
            $response = wp_remote_post('https://api.commerce.coinbase.com/charges', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-CC-Api-Key' => $coinbase_api_key,
                    'X-CC-Version' => '2018-03-22'
                ],
                'body' => json_encode($charge_data),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                return ['success' => false, 'message' => 'Cryptocurrency payment creation failed'];
            }
            
            $charge = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($charge['data'])) {
                return [
                    'success' => true,
                    'charge_id' => $charge['data']['id'],
                    'hosted_url' => $charge['data']['hosted_url'],
                    'amount' => $amount,
                    'currency' => $currency
                ];
            } else {
                return ['success' => false, 'message' => 'Charge creation failed'];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Verify payment completion
     */
    public function verify_payment($gateway, $payment_data) {
        switch ($gateway) {
            case 'stripe':
                return $this->verify_stripe_payment($payment_data);
            case 'paypal':
                return $this->verify_paypal_payment($payment_data);
            case 'sslcommerz':
                return $this->verify_sslcommerz_payment($payment_data);
            case 'crypto':
                return $this->verify_crypto_payment($payment_data);
            default:
                return false;
        }
    }
    
    /**
     * Verify Stripe payment
     */
    private function verify_stripe_payment($payment_data) {
        $stripe_key = get_option('metapix_ai_stripe_secret_key', '');
        
        try {
            \Stripe\Stripe::setApiKey($stripe_key);
            $intent = \Stripe\PaymentIntent::retrieve($payment_data['payment_intent_id']);
            
            return $intent->status === 'succeeded';
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Process successful payment
     */
    public function process_successful_payment($user_id, $package, $payment_data) {
        $result = Credits::process_credit_purchase($user_id, $package, $payment_data);
        
        if ($result) {
            // Send confirmation email
            $this->send_payment_confirmation($user_id, $package, $payment_data);
            
            // Log successful payment
            Database::log_optimization([
                'post_id' => $user_id,
                'optimization_type' => 'payment_success',
                'module' => 'PaymentGateway',
                'old_value' => '0',
                'new_value' => $payment_data['amount'],
                'status' => 'completed'
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Send payment confirmation email
     */
    private function send_payment_confirmation($user_id, $package, $payment_data) {
        $user = get_userdata($user_id);
        $package_data = Credits::get_packages()[$package];
        
        $subject = 'Payment Confirmation - MetaPix AI Credits';
        $message = "
        <h2>Payment Confirmation</h2>
        <p>Dear {$user->display_name},</p>
        <p>Thank you for your purchase! Your payment has been successfully processed.</p>
        
        <h3>Order Details:</h3>
        <ul>
            <li><strong>Package:</strong> {$package_data['label']}</li>
            <li><strong>Credits:</strong> {$package_data['credits']} + {$package_data['bonus']} bonus</li>
            <li><strong>Amount:</strong> $" . number_format($payment_data['amount'], 2) . "</li>
            <li><strong>Payment Method:</strong> {$payment_data['method']}</li>
            <li><strong>Transaction ID:</strong> {$payment_data['transaction_id']}</li>
        </ul>
        
        <p>Your credits have been added to your account and are ready to use!</p>
        <p><a href='" . admin_url('admin.php?page=metapix-ai-user') . "'>Visit your dashboard</a></p>
        
        <p>Best regards,<br>MetaPix AI Team</p>
        ";
        
        wp_mail($user->user_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_stripe_webhook() {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $endpoint_secret = get_option('metapix_ai_stripe_webhook_secret', '');
        
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
            
            if ($event['type'] === 'payment_intent.succeeded') {
                $payment_intent = $event['data']['object'];
                $user_id = $payment_intent['metadata']['user_id'];
                $package = $payment_intent['metadata']['package'];
                
                $this->process_successful_payment($user_id, $package, [
                    'method' => 'stripe',
                    'gateway' => 'stripe',
                    'transaction_id' => $payment_intent['id'],
                    'amount' => $payment_intent['amount'] / 100,
                    'currency' => strtoupper($payment_intent['currency']),
                    'status' => 'completed'
                ]);
            }
            
            http_response_code(200);
            
        } catch (\Exception $e) {
            http_response_code(400);
            exit();
        }
    }
    
    /**
     * Handle cryptocurrency webhook
     */
    public function handle_crypto_webhook() {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'];
        $webhook_secret = get_option('metapix_ai_coinbase_webhook_secret', '');
        
        // Verify webhook signature
        $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);
        
        if (!hash_equals($expected_signature, $sig_header)) {
            http_response_code(400);
            exit();
        }
        
        $event = json_decode($payload, true);
        
        if ($event['event']['type'] === 'charge:confirmed') {
            $charge = $event['event']['data'];
            $user_id = $charge['metadata']['user_id'];
            $package = $charge['metadata']['package'];
            
            $this->process_successful_payment($user_id, $package, [
                'method' => 'crypto',
                'gateway' => 'crypto',
                'transaction_id' => $charge['id'],
                'amount' => $charge['pricing']['local']['amount'],
                'currency' => $charge['pricing']['local']['currency'],
                'status' => 'completed'
            ]);
        }
        
        http_response_code(200);
    }
    
    /**
     * AJAX: Create payment
     */
    public function ajax_create_payment() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $package = sanitize_text_field($_POST['package']);
        $gateway = sanitize_text_field($_POST['gateway']);
        $method = sanitize_text_field($_POST['method'] ?? 'card');
        
        $result = $this->create_payment($user_id, $package, $gateway, $method);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Verify payment
     */
    public function ajax_verify_payment() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $gateway = sanitize_text_field($_POST['gateway']);
        $payment_data = $_POST['payment_data'];
        
        $verified = $this->verify_payment($gateway, $payment_data);
        
        if ($verified) {
            wp_send_json_success(['message' => 'Payment verified successfully']);
        } else {
            wp_send_json_error(['message' => 'Payment verification failed']);
        }
    }
    
    /**
     * Get available gateways
     */
    public static function get_available_gateways() {
        $available = [];
        
        foreach (self::GATEWAYS as $key => $gateway) {
            $is_configured = false;
            
            switch ($key) {
                case 'stripe':
                    $is_configured = !empty(get_option('metapix_ai_stripe_secret_key', ''));
                    break;
                case 'paypal':
                    $is_configured = !empty(get_option('metapix_ai_paypal_client_id', ''));
                    break;
                case 'sslcommerz':
                    $is_configured = !empty(get_option('metapix_ai_sslcommerz_store_id', ''));
                    break;
                case 'crypto':
                    $is_configured = !empty(get_option('metapix_ai_coinbase_api_key', ''));
                    break;
            }
            
            if ($is_configured) {
                $available[$key] = $gateway;
            }
        }
        
        return $available;
    }
}