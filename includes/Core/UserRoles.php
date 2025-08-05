<?php

namespace MetaPixAI\Core;

/**
 * User Role Management System
 */
class UserRoles {
    
    /**
     * Available roles
     */
    const ROLES = [
        'user' => [
            'label' => 'User',
            'capabilities' => [
                'view_dashboard',
                'generate_images',
                'view_own_images',
                'edit_own_prompts',
                'view_credits'
            ]
        ],
        'admin' => [
            'label' => 'Admin',
            'capabilities' => [
                'view_dashboard',
                'generate_images',
                'view_own_images',
                'view_all_images',
                'edit_own_prompts',
                'view_credits',
                'manage_users',
                'moderate_images',
                'view_reports',
                'manage_credits'
            ]
        ],
        'super_admin' => [
            'label' => 'Super Admin',
            'capabilities' => [
                'view_dashboard',
                'generate_images',
                'view_own_images',
                'view_all_images',
                'edit_own_prompts',
                'view_credits',
                'manage_users',
                'moderate_images',
                'view_reports',
                'manage_credits',
                'manage_settings',
                'manage_api_keys',
                'view_analytics',
                'manage_subscriptions'
            ]
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('user_register', [$this, 'assign_default_role']);
        add_action('wp_login', [$this, 'check_user_status'], 10, 2);
        add_action('init', [$this, 'init_role_capabilities']);
    }
    
    /**
     * Initialize role capabilities
     */
    public function init_role_capabilities() {
        // Add custom capabilities to WordPress roles
        $wp_admin = get_role('administrator');
        if ($wp_admin) {
            foreach (self::ROLES['super_admin']['capabilities'] as $cap) {
                $wp_admin->add_cap($cap);
            }
        }
        
        $wp_editor = get_role('editor');
        if ($wp_editor) {
            foreach (self::ROLES['admin']['capabilities'] as $cap) {
                $wp_editor->add_cap($cap);
            }
        }
    }
    
    /**
     * Assign default role to new users
     */
    public function assign_default_role($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_user_roles';
        
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'role' => 'user',
            'permissions' => json_encode(self::ROLES['user']['capabilities']),
            'status' => 'active'
        ]);
        
        // Initialize user credits
        $this->initialize_user_credits($user_id);
    }
    
    /**
     * Initialize user credits
     */
    private function initialize_user_credits($user_id) {
        global $wpdb;
        $credits_table = $wpdb->prefix . 'metapix_user_credits';
        
        $wpdb->insert($credits_table, [
            'user_id' => $user_id,
            'credits_balance' => 10, // Free credits for new users
            'subscription_plan' => 'free'
        ]);
    }
    
    /**
     * Check user status on login
     */
    public function check_user_status($user_login, $user) {
        $role_data = $this->get_user_role($user->ID);
        
        if (!$role_data || $role_data->status === 'banned') {
            wp_logout();
            wp_redirect(wp_login_url() . '?banned=1');
            exit;
        }
        
        if ($role_data->banned_until && strtotime($role_data->banned_until) > time()) {
            wp_logout();
            wp_redirect(wp_login_url() . '?temp_banned=1');
            exit;
        }
    }
    
    /**
     * Get user role data
     */
    public static function get_user_role($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_user_roles';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Update user role
     */
    public static function update_user_role($user_id, $role, $admin_id = null) {
        if (!array_key_exists($role, self::ROLES)) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_user_roles';
        
        $result = $wpdb->update(
            $table,
            [
                'role' => $role,
                'permissions' => json_encode(self::ROLES[$role]['capabilities'])
            ],
            ['user_id' => $user_id]
        );
        
        if ($result !== false && $admin_id) {
            // Log role change
            Database::create_notification([
                'user_id' => $user_id,
                'type' => 'info',
                'title' => 'Role Updated',
                'message' => 'Your role has been updated to ' . self::ROLES[$role]['label'],
                'data' => json_encode(['changed_by' => $admin_id])
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * Ban user
     */
    public static function ban_user($user_id, $reason = '', $duration = null, $admin_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_user_roles';
        
        $data = [
            'status' => $duration ? 'temp_banned' : 'banned',
            'banned_reason' => $reason
        ];
        
        if ($duration) {
            $data['banned_until'] = date('Y-m-d H:i:s', strtotime($duration));
        }
        
        $result = $wpdb->update($table, $data, ['user_id' => $user_id]);
        
        if ($result !== false) {
            // Create notification
            Database::create_notification([
                'user_id' => $user_id,
                'type' => 'warning',
                'title' => 'Account Suspended',
                'message' => $duration ? 
                    "Your account has been temporarily suspended until {$duration}. Reason: {$reason}" :
                    "Your account has been suspended. Reason: {$reason}",
                'priority' => 'high'
            ]);
            
            // Log admin action
            if ($admin_id) {
                Database::log_optimization([
                    'post_id' => $user_id,
                    'optimization_type' => 'user_ban',
                    'module' => 'UserRoles',
                    'old_value' => 'active',
                    'new_value' => $duration ? "temp_banned_until_{$duration}" : 'banned',
                    'status' => 'completed'
                ]);
            }
        }
        
        return $result !== false;
    }
    
    /**
     * Unban user
     */
    public static function unban_user($user_id, $admin_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_user_roles';
        
        $result = $wpdb->update(
            $table,
            [
                'status' => 'active',
                'banned_until' => null,
                'banned_reason' => null
            ],
            ['user_id' => $user_id]
        );
        
        if ($result !== false) {
            Database::create_notification([
                'user_id' => $user_id,
                'type' => 'success',
                'title' => 'Account Restored',
                'message' => 'Your account has been restored and is now active.',
                'priority' => 'normal'
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * Check if user has capability
     */
    public static function user_can($user_id, $capability) {
        $role_data = self::get_user_role($user_id);
        
        if (!$role_data || $role_data->status !== 'active') {
            return false;
        }
        
        $permissions = json_decode($role_data->permissions, true);
        return in_array($capability, $permissions);
    }
    
    /**
     * Get users by role
     */
    public static function get_users_by_role($role = null, $status = 'active', $limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_user_roles';
        $users_table = $wpdb->users;
        
        $where_conditions = ["ur.status = %s"];
        $where_values = [$status];
        
        if ($role) {
            $where_conditions[] = "ur.role = %s";
            $where_values[] = $role;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
                    ur.role, ur.status, ur.banned_until, ur.banned_reason, ur.created_at
             FROM $users_table u 
             INNER JOIN $table ur ON u.ID = ur.user_id 
             WHERE $where_clause 
             ORDER BY ur.created_at DESC 
             LIMIT %d OFFSET %d",
            ...$where_values
        ));
    }
    
    /**
     * Get user statistics
     */
    public static function get_user_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_user_roles';
        
        return $wpdb->get_results(
            "SELECT 
                role,
                status,
                COUNT(*) as count
             FROM $table 
             GROUP BY role, status"
        );
    }
    
    /**
     * Get role label
     */
    public static function get_role_label($role) {
        return self::ROLES[$role]['label'] ?? ucfirst($role);
    }
    
    /**
     * Get all roles
     */
    public static function get_all_roles() {
        return self::ROLES;
    }
    
    /**
     * Check if user is admin or super admin
     */
    public static function is_admin($user_id) {
        $role_data = self::get_user_role($user_id);
        return $role_data && in_array($role_data->role, ['admin', 'super_admin']);
    }
    
    /**
     * Check if user is super admin
     */
    public static function is_super_admin($user_id) {
        $role_data = self::get_user_role($user_id);
        return $role_data && $role_data->role === 'super_admin';
    }
    
    /**
     * Get dashboard URL based on role
     */
    public static function get_dashboard_url($user_id) {
        $role_data = self::get_user_role($user_id);
        
        if (!$role_data) {
            return admin_url('admin.php?page=metapix-ai');
        }
        
        switch ($role_data->role) {
            case 'super_admin':
                return admin_url('admin.php?page=metapix-ai-super-admin');
            case 'admin':
                return admin_url('admin.php?page=metapix-ai-admin');
            default:
                return admin_url('admin.php?page=metapix-ai-user');
        }
    }
}