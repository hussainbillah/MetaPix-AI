<?php

namespace MetaPixAI\Core;

/**
 * Credits/Token Management System
 */
class Credits {
    
    /**
     * Credit packages
     */
    const PACKAGES = [
        'starter' => [
            'credits' => 100,
            'price' => 9.99,
            'bonus' => 0,
            'label' => 'Starter Pack'
        ],
        'popular' => [
            'credits' => 500,
            'price' => 39.99,
            'bonus' => 50,
            'label' => 'Popular Pack'
        ],
        'professional' => [
            'credits' => 1000,
            'price' => 69.99,
            'bonus' => 150,
            'label' => 'Professional Pack'
        ],
        'enterprise' => [
            'credits' => 5000,
            'price' => 299.99,
            'bonus' => 1000,
            'label' => 'Enterprise Pack'
        ]
    ];
    
    /**
     * Subscription plans
     */
    const SUBSCRIPTION_PLANS = [
        'free' => [
            'label' => 'Free',
            'monthly_credits' => 10,
            'price' => 0,
            'features' => ['Basic image optimization', 'Limited generations']
        ],
        'pro' => [
            'label' => 'Pro',
            'monthly_credits' => 500,
            'price' => 19.99,
            'features' => ['Advanced AI features', 'Priority support', 'No watermarks']
        ],
        'agency' => [
            'label' => 'Agency',
            'monthly_credits' => 2000,
            'price' => 79.99,
            'features' => ['White-label options', 'Team management', 'API access', 'Custom branding']
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_metapix_topup_credits', [$this, 'ajax_topup_credits']);
        add_action('wp_ajax_metapix_get_credit_balance', [$this, 'ajax_get_credit_balance']);
        add_action('wp_ajax_metapix_get_credit_history', [$this, 'ajax_get_credit_history']);
        
        // Schedule monthly credit renewal for subscriptions
        add_action('metapix_monthly_credit_renewal', [$this, 'renew_monthly_credits']);
        if (!wp_next_scheduled('metapix_monthly_credit_renewal')) {
            wp_schedule_event(time(), 'monthly', 'metapix_monthly_credit_renewal');
        }
    }
    
    /**
     * Get user credit balance
     */
    public static function get_user_credits($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_user_credits';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if (!$result) {
            // Initialize credits for user if not exists
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'credits_balance' => 10,
                'subscription_plan' => 'free'
            ]);
            
            return [
                'credits_balance' => 10,
                'credits_used' => 0,
                'credits_purchased' => 0,
                'subscription_plan' => 'free',
                'subscription_expires' => null
            ];
        }
        
        return [
            'credits_balance' => (int)$result->credits_balance,
            'credits_used' => (int)$result->credits_used,
            'credits_purchased' => (int)$result->credits_purchased,
            'subscription_plan' => $result->subscription_plan,
            'subscription_expires' => $result->subscription_expires
        ];
    }
    
    /**
     * Add credits to user account
     */
    public static function add_credits($user_id, $credits, $transaction_type = 'purchase', $reference_id = null, $description = '') {
        global $wpdb;
        $credits_table = $wpdb->prefix . 'metapix_user_credits';
        $transactions_table = $wpdb->prefix . 'metapix_credit_transactions';
        
        // Get current balance
        $current = self::get_user_credits($user_id);
        $new_balance = $current['credits_balance'] + $credits;
        
        // Update credits
        $result = $wpdb->update(
            $credits_table,
            [
                'credits_balance' => $new_balance,
                'credits_purchased' => $current['credits_purchased'] + ($transaction_type === 'purchase' ? $credits : 0),
                'last_topup' => current_time('mysql')
            ],
            ['user_id' => $user_id]
        );
        
        if ($result !== false) {
            // Log transaction
            $wpdb->insert($transactions_table, [
                'user_id' => $user_id,
                'transaction_type' => $transaction_type,
                'credits_amount' => $credits,
                'balance_before' => $current['credits_balance'],
                'balance_after' => $new_balance,
                'reference_id' => $reference_id,
                'description' => $description
            ]);
            
            // Create notification
            Database::create_notification([
                'user_id' => $user_id,
                'type' => 'success',
                'title' => 'Credits Added',
                'message' => "Your account has been credited with {$credits} credits.",
                'data' => json_encode([
                    'credits_added' => $credits,
                    'new_balance' => $new_balance,
                    'transaction_type' => $transaction_type
                ])
            ]);
            
            return $new_balance;
        }
        
        return false;
    }
    
    /**
     * Deduct credits from user account
     */
    public static function deduct_credits($user_id, $credits, $transaction_type = 'usage', $reference_id = null, $description = '') {
        global $wpdb;
        $credits_table = $wpdb->prefix . 'metapix_user_credits';
        $transactions_table = $wpdb->prefix . 'metapix_credit_transactions';
        
        // Get current balance
        $current = self::get_user_credits($user_id);
        
        if ($current['credits_balance'] < $credits) {
            return false; // Insufficient credits
        }
        
        $new_balance = $current['credits_balance'] - $credits;
        
        // Update credits
        $result = $wpdb->update(
            $credits_table,
            [
                'credits_balance' => $new_balance,
                'credits_used' => $current['credits_used'] + $credits
            ],
            ['user_id' => $user_id]
        );
        
        if ($result !== false) {
            // Log transaction
            $wpdb->insert($transactions_table, [
                'user_id' => $user_id,
                'transaction_type' => $transaction_type,
                'credits_amount' => -$credits,
                'balance_before' => $current['credits_balance'],
                'balance_after' => $new_balance,
                'reference_id' => $reference_id,
                'description' => $description
            ]);
            
            // Check if credits are low
            if ($new_balance <= 5 && $new_balance > 0) {
                Database::create_notification([
                    'user_id' => $user_id,
                    'type' => 'warning',
                    'title' => 'Low Credits',
                    'message' => "You have {$new_balance} credits remaining. Consider topping up to continue using AI features.",
                    'priority' => 'normal'
                ]);
            } elseif ($new_balance === 0) {
                Database::create_notification([
                    'user_id' => $user_id,
                    'type' => 'error',
                    'title' => 'No Credits Remaining',
                    'message' => 'You have no credits left. Please top up to continue using AI features.',
                    'priority' => 'high'
                ]);
            }
            
            return $new_balance;
        }
        
        return false;
    }
    
    /**
     * Check if user has enough credits
     */
    public static function has_credits($user_id, $credits_needed = 1) {
        $current = self::get_user_credits($user_id);
        return $current['credits_balance'] >= $credits_needed;
    }
    
    /**
     * Get credit transaction history
     */
    public static function get_credit_history($user_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_credit_transactions';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        ));
    }
    
    /**
     * Update subscription plan
     */
    public static function update_subscription($user_id, $plan, $expires_at = null) {
        if (!array_key_exists($plan, self::SUBSCRIPTION_PLANS)) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_user_credits';
        
        $data = ['subscription_plan' => $plan];
        
        if ($expires_at) {
            $data['subscription_expires'] = $expires_at;
        } elseif ($plan !== 'free') {
            $data['subscription_expires'] = date('Y-m-d H:i:s', strtotime('+1 month'));
        }
        
        $result = $wpdb->update($table, $data, ['user_id' => $user_id]);
        
        if ($result !== false) {
            // Add monthly credits for new subscription
            $monthly_credits = self::SUBSCRIPTION_PLANS[$plan]['monthly_credits'];
            if ($monthly_credits > 0) {
                self::add_credits(
                    $user_id, 
                    $monthly_credits, 
                    'subscription', 
                    $plan, 
                    "Monthly credits for {$plan} plan"
                );
            }
            
            Database::create_notification([
                'user_id' => $user_id,
                'type' => 'success',
                'title' => 'Subscription Updated',
                'message' => "Your subscription has been updated to " . self::SUBSCRIPTION_PLANS[$plan]['label'],
                'data' => json_encode(['plan' => $plan, 'expires_at' => $expires_at])
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * Renew monthly credits for active subscriptions
     */
    public function renew_monthly_credits() {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_user_credits';
        
        // Get active subscriptions
        $active_subscriptions = $wpdb->get_results(
            "SELECT user_id, subscription_plan 
             FROM $table 
             WHERE subscription_plan != 'free' 
             AND (subscription_expires IS NULL OR subscription_expires > NOW())"
        );
        
        foreach ($active_subscriptions as $subscription) {
            $plan = $subscription->subscription_plan;
            $monthly_credits = self::SUBSCRIPTION_PLANS[$plan]['monthly_credits'] ?? 0;
            
            if ($monthly_credits > 0) {
                self::add_credits(
                    $subscription->user_id,
                    $monthly_credits,
                    'subscription_renewal',
                    $plan,
                    "Monthly credit renewal for {$plan} plan"
                );
            }
        }
    }
    
    /**
     * Get credit cost for operation
     */
    public static function get_operation_cost($operation_type) {
        $costs = [
            'alt_text_generation' => 1,
            'meta_tag_generation' => 2,
            'content_analysis' => 3,
            'seo_audit' => 5,
            'competitor_analysis' => 10
        ];
        
        return $costs[$operation_type] ?? 1;
    }
    
    /**
     * Process credit purchase
     */
    public static function process_credit_purchase($user_id, $package, $payment_data) {
        if (!array_key_exists($package, self::PACKAGES)) {
            return false;
        }
        
        $package_data = self::PACKAGES[$package];
        $total_credits = $package_data['credits'] + $package_data['bonus'];
        
        // Record payment
        global $wpdb;
        $payments_table = $wpdb->prefix . 'metapix_payments';
        
        $payment_id = $wpdb->insert($payments_table, [
            'user_id' => $user_id,
            'payment_method' => $payment_data['method'],
            'payment_gateway' => $payment_data['gateway'],
            'transaction_id' => $payment_data['transaction_id'],
            'amount' => $package_data['price'],
            'currency' => $payment_data['currency'] ?? 'USD',
            'credits_purchased' => $total_credits,
            'status' => $payment_data['status'] ?? 'completed',
            'gateway_response' => json_encode($payment_data)
        ]);
        
        if ($payment_id) {
            // Add credits to user account
            $new_balance = self::add_credits(
                $user_id,
                $total_credits,
                'purchase',
                $payment_id,
                "Purchased {$package_data['label']} - {$package_data['credits']} credits + {$package_data['bonus']} bonus"
            );
            
            return $new_balance;
        }
        
        return false;
    }
    
    /**
     * Get user's subscription status
     */
    public static function get_subscription_status($user_id) {
        $credits = self::get_user_credits($user_id);
        $plan = $credits['subscription_plan'];
        $expires = $credits['subscription_expires'];
        
        $is_active = true;
        if ($expires && strtotime($expires) < time()) {
            $is_active = false;
        }
        
        return [
            'plan' => $plan,
            'label' => self::SUBSCRIPTION_PLANS[$plan]['label'] ?? ucfirst($plan),
            'expires_at' => $expires,
            'is_active' => $is_active,
            'monthly_credits' => self::SUBSCRIPTION_PLANS[$plan]['monthly_credits'] ?? 0,
            'features' => self::SUBSCRIPTION_PLANS[$plan]['features'] ?? []
        ];
    }
    
    /**
     * AJAX: Top up credits
     */
    public function ajax_topup_credits() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $package = sanitize_text_field($_POST['package']);
        
        if (!array_key_exists($package, self::PACKAGES)) {
            wp_send_json_error(['message' => 'Invalid package selected']);
        }
        
        $package_data = self::PACKAGES[$package];
        
        wp_send_json_success([
            'package' => $package_data,
            'redirect_url' => admin_url('admin.php?page=metapix-ai-payment&package=' . $package)
        ]);
    }
    
    /**
     * AJAX: Get credit balance
     */
    public function ajax_get_credit_balance() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $credits = self::get_user_credits($user_id);
        $subscription = self::get_subscription_status($user_id);
        
        wp_send_json_success([
            'credits' => $credits,
            'subscription' => $subscription
        ]);
    }
    
    /**
     * AJAX: Get credit history
     */
    public function ajax_get_credit_history() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $limit = intval($_POST['limit'] ?? 20);
        $offset = intval($_POST['offset'] ?? 0);
        
        $history = self::get_credit_history($user_id, $limit, $offset);
        
        wp_send_json_success(['history' => $history]);
    }
    
    /**
     * Get all packages
     */
    public static function get_packages() {
        return self::PACKAGES;
    }
    
    /**
     * Get all subscription plans
     */
    public static function get_subscription_plans() {
        return self::SUBSCRIPTION_PLANS;
    }
}