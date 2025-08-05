<?php

namespace MetaPixAI\Modules;

use MetaPixAI\Core\Database;
use MetaPixAI\Core\UserRoles;

/**
 * Image Moderation & Reporting System
 */
class ImageModeration {
    
    /**
     * Report reasons
     */
    const REPORT_REASONS = [
        'inappropriate_content' => 'Inappropriate Content',
        'copyright_violation' => 'Copyright Violation',
        'spam' => 'Spam',
        'misleading_information' => 'Misleading Information',
        'poor_quality' => 'Poor Quality',
        'offensive_language' => 'Offensive Language',
        'other' => 'Other'
    ];
    
    /**
     * Moderation statuses
     */
    const MODERATION_STATUSES = [
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'flagged' => 'Flagged for Review'
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
        add_action('wp_ajax_metapix_report_image', [$this, 'ajax_report_image']);
        add_action('wp_ajax_metapix_moderate_image', [$this, 'ajax_moderate_image']);
        add_action('wp_ajax_metapix_get_reports', [$this, 'ajax_get_reports']);
        add_action('wp_ajax_metapix_bulk_moderate', [$this, 'ajax_bulk_moderate']);
        add_action('wp_ajax_metapix_get_moderation_queue', [$this, 'ajax_get_moderation_queue']);
        
        // Admin interface
        add_action('admin_menu', [$this, 'add_moderation_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_moderation_scripts']);
        
        // Auto-moderation hooks
        add_action('metapix_auto_moderate_images', [$this, 'auto_moderate_images']);
        
        // Schedule auto-moderation
        if (!wp_next_scheduled('metapix_auto_moderate_images')) {
            wp_schedule_event(time(), 'hourly', 'metapix_auto_moderate_images');
        }
        
        // Frontend display filters
        add_filter('metapix_display_image', [$this, 'filter_moderated_images'], 10, 2);
        add_action('wp_footer', [$this, 'add_report_modal']);
    }
    
    /**
     * Report an image
     */
    public function report_image($image_generation_id, $reported_by, $reason, $description = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'metapix_image_reports';
        
        // Check if user already reported this image
        $existing_report = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE image_generation_id = %d AND reported_by = %d",
            $image_generation_id,
            $reported_by
        ));
        
        if ($existing_report) {
            return false; // Already reported
        }
        
        // Insert report
        $result = $wpdb->insert($table, [
            'image_generation_id' => $image_generation_id,
            'reported_by' => $reported_by,
            'report_reason' => $reason,
            'report_description' => $description,
            'status' => 'pending'
        ]);
        
        if ($result) {
            // Update image generation status
            $generations_table = $wpdb->prefix . 'metapix_image_generations';
            $wpdb->update(
                $generations_table,
                ['is_reported' => 1, 'moderation_status' => 'flagged'],
                ['id' => $image_generation_id]
            );
            
            // Notify admins
            $this->notify_admins_of_report($image_generation_id, $reason);
            
            // Log the report
            Database::log_optimization([
                'post_id' => $image_generation_id,
                'optimization_type' => 'image_reported',
                'module' => 'ImageModeration',
                'old_value' => 'approved',
                'new_value' => 'flagged',
                'status' => 'completed'
            ]);
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Moderate an image
     */
    public function moderate_image($image_generation_id, $action, $moderator_id, $reason = '') {
        global $wpdb;
        $generations_table = $wpdb->prefix . 'metapix_image_generations';
        $reports_table = $wpdb->prefix . 'metapix_image_reports';
        
        $valid_actions = ['approve', 'reject', 'flag', 'ban_user'];
        
        if (!in_array($action, $valid_actions)) {
            return false;
        }
        
        // Get image generation data
        $generation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $generations_table WHERE id = %d",
            $image_generation_id
        ));
        
        if (!$generation) {
            return false;
        }
        
        $new_status = 'approved';
        $action_taken = $action;
        
        switch ($action) {
            case 'approve':
                $new_status = 'approved';
                break;
            case 'reject':
                $new_status = 'rejected';
                break;
            case 'flag':
                $new_status = 'flagged';
                break;
            case 'ban_user':
                $new_status = 'rejected';
                $this->ban_user_for_violation($generation->user_id, $reason, $moderator_id);
                break;
        }
        
        // Update image generation
        $result = $wpdb->update(
            $generations_table,
            ['moderation_status' => $new_status],
            ['id' => $image_generation_id]
        );
        
        if ($result !== false) {
            // Update related reports
            $wpdb->update(
                $reports_table,
                [
                    'status' => 'reviewed',
                    'reviewed_by' => $moderator_id,
                    'reviewed_at' => current_time('mysql'),
                    'action_taken' => $action_taken
                ],
                ['image_generation_id' => $image_generation_id]
            );
            
            // Notify user of moderation decision
            $this->notify_user_of_moderation($generation->user_id, $image_generation_id, $action, $reason);
            
            // Log moderation action
            Database::log_optimization([
                'post_id' => $image_generation_id,
                'optimization_type' => 'image_moderated',
                'module' => 'ImageModeration',
                'old_value' => $generation->moderation_status,
                'new_value' => $new_status,
                'status' => 'completed'
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Ban user for violation
     */
    private function ban_user_for_violation($user_id, $reason, $moderator_id) {
        // Check violation history
        $violation_count = $this->get_user_violation_count($user_id);
        
        $ban_duration = null;
        if ($violation_count >= 3) {
            // Permanent ban for repeat offenders
            $ban_duration = null;
        } elseif ($violation_count >= 2) {
            // 30-day ban
            $ban_duration = '+30 days';
        } else {
            // 7-day ban for first offense
            $ban_duration = '+7 days';
        }
        
        UserRoles::ban_user($user_id, $reason, $ban_duration, $moderator_id);
        
        // Create severe violation notification
        Database::create_notification([
            'user_id' => $user_id,
            'type' => 'error',
            'title' => 'Account Suspended - Policy Violation',
            'message' => "Your account has been suspended due to a policy violation. Reason: {$reason}",
            'priority' => 'high',
            'data' => json_encode([
                'violation_type' => 'image_content',
                'moderator_id' => $moderator_id,
                'ban_duration' => $ban_duration
            ])
        ]);
    }
    
    /**
     * Get user violation count
     */
    private function get_user_violation_count($user_id) {
        global $wpdb;
        $reports_table = $wpdb->prefix . 'metapix_image_reports';
        $generations_table = $wpdb->prefix . 'metapix_image_generations';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $reports_table r 
             INNER JOIN $generations_table g ON r.image_generation_id = g.id 
             WHERE g.user_id = %d AND r.action_taken = 'ban_user'",
            $user_id
        ));
    }
    
    /**
     * Get moderation queue
     */
    public function get_moderation_queue($limit = 20, $offset = 0, $status = 'pending') {
        global $wpdb;
        $generations_table = $wpdb->prefix . 'metapix_image_generations';
        $users_table = $wpdb->users;
        
        $where_clause = "WHERE g.moderation_status = %s";
        $params = [$status];
        
        if ($status === 'flagged') {
            $where_clause = "WHERE g.is_reported = 1 AND g.moderation_status IN ('flagged', 'pending')";
            $params = [];
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $sql = "SELECT g.*, u.user_login, u.display_name, u.user_email,
                       COUNT(r.id) as report_count
                FROM $generations_table g
                INNER JOIN $users_table u ON g.user_id = u.ID
                LEFT JOIN {$wpdb->prefix}metapix_image_reports r ON g.id = r.image_generation_id
                $where_clause
                GROUP BY g.id
                ORDER BY g.created_at ASC
                LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
    
    /**
     * Get image reports
     */
    public function get_image_reports($image_generation_id) {
        global $wpdb;
        $reports_table = $wpdb->prefix . 'metapix_image_reports';
        $users_table = $wpdb->users;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.user_login, u.display_name 
             FROM $reports_table r
             INNER JOIN $users_table u ON r.reported_by = u.ID
             WHERE r.image_generation_id = %d
             ORDER BY r.created_at DESC",
            $image_generation_id
        ));
    }
    
    /**
     * Auto-moderate images using AI
     */
    public function auto_moderate_images() {
        $queue = $this->get_moderation_queue(50, 0, 'pending');
        
        foreach ($queue as $item) {
            $moderation_result = $this->ai_moderate_image($item->attachment_id);
            
            if ($moderation_result) {
                if ($moderation_result['flagged']) {
                    $this->moderate_image($item->id, 'flag', 0, 'Auto-flagged: ' . $moderation_result['reason']);
                } else {
                    $this->moderate_image($item->id, 'approve', 0, 'Auto-approved');
                }
            }
        }
    }
    
    /**
     * AI-based image moderation
     */
    private function ai_moderate_image($attachment_id) {
        // This would integrate with content moderation APIs like Google Vision API
        // For now, return a simple heuristic-based result
        
        if (!$attachment_id) {
            return null;
        }
        
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return null;
        }
        
        // Basic filename-based flagging
        $filename = strtolower(basename($attachment->guid));
        $flagged_terms = ['xxx', 'porn', 'nude', 'sex', 'adult', 'explicit'];
        
        foreach ($flagged_terms as $term) {
            if (strpos($filename, $term) !== false) {
                return [
                    'flagged' => true,
                    'reason' => 'Inappropriate filename detected',
                    'confidence' => 0.8
                ];
            }
        }
        
        // Check file size (unusually large files might be problematic)
        $file_size = filesize(get_attached_file($attachment_id));
        if ($file_size > 10 * 1024 * 1024) { // 10MB
            return [
                'flagged' => true,
                'reason' => 'File size too large',
                'confidence' => 0.6
            ];
        }
        
        return [
            'flagged' => false,
            'reason' => 'No issues detected',
            'confidence' => 0.7
        ];
    }
    
    /**
     * Notify admins of new report
     */
    private function notify_admins_of_report($image_generation_id, $reason) {
        $admins = get_users(['role' => 'administrator']);
        
        foreach ($admins as $admin) {
            if (UserRoles::user_can($admin->ID, 'moderate_images')) {
                Database::create_notification([
                    'user_id' => $admin->ID,
                    'type' => 'warning',
                    'title' => 'New Image Report',
                    'message' => "An image has been reported for: " . self::REPORT_REASONS[$reason],
                    'priority' => 'high',
                    'data' => json_encode([
                        'image_generation_id' => $image_generation_id,
                        'report_reason' => $reason,
                        'action_url' => admin_url('admin.php?page=metapix-ai-moderation&image=' . $image_generation_id)
                    ])
                ]);
            }
        }
    }
    
    /**
     * Notify user of moderation decision
     */
    private function notify_user_of_moderation($user_id, $image_generation_id, $action, $reason) {
        $messages = [
            'approve' => 'Your image has been approved and is now public.',
            'reject' => 'Your image has been rejected. Reason: ' . $reason,
            'flag' => 'Your image has been flagged for review. Reason: ' . $reason,
            'ban_user' => 'Your account has been suspended due to policy violations.'
        ];
        
        $types = [
            'approve' => 'success',
            'reject' => 'warning',
            'flag' => 'warning',
            'ban_user' => 'error'
        ];
        
        Database::create_notification([
            'user_id' => $user_id,
            'type' => $types[$action],
            'title' => 'Image Moderation Update',
            'message' => $messages[$action],
            'priority' => $action === 'ban_user' ? 'high' : 'normal',
            'data' => json_encode([
                'image_generation_id' => $image_generation_id,
                'action' => $action,
                'reason' => $reason
            ])
        ]);
    }
    
    /**
     * Add moderation menu
     */
    public function add_moderation_menu() {
        $user_id = get_current_user_id();
        
        if (!UserRoles::user_can($user_id, 'moderate_images')) {
            return;
        }
        
        add_submenu_page(
            'metapix-ai',
            'Image Moderation',
            'Moderation',
            'manage_options',
            'metapix-ai-moderation',
            [$this, 'render_moderation_page']
        );
    }
    
    /**
     * Render moderation page
     */
    public function render_moderation_page() {
        $current_tab = $_GET['tab'] ?? 'queue';
        $queue = $this->get_moderation_queue(20);
        $flagged = $this->get_moderation_queue(20, 0, 'flagged');
        
        ?>
        <div class="wrap">
            <h1>Image Moderation</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=metapix-ai-moderation&tab=queue" 
                   class="nav-tab <?php echo $current_tab === 'queue' ? 'nav-tab-active' : ''; ?>">
                    Moderation Queue (<?php echo count($queue); ?>)
                </a>
                <a href="?page=metapix-ai-moderation&tab=flagged" 
                   class="nav-tab <?php echo $current_tab === 'flagged' ? 'nav-tab-active' : ''; ?>">
                    Flagged Images (<?php echo count($flagged); ?>)
                </a>
                <a href="?page=metapix-ai-moderation&tab=reports" 
                   class="nav-tab <?php echo $current_tab === 'reports' ? 'nav-tab-active' : ''; ?>">
                    Reports
                </a>
                <a href="?page=metapix-ai-moderation&tab=settings" 
                   class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    Settings
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'queue':
                        $this->render_moderation_queue($queue);
                        break;
                    case 'flagged':
                        $this->render_flagged_images($flagged);
                        break;
                    case 'reports':
                        $this->render_reports_tab();
                        break;
                    case 'settings':
                        $this->render_moderation_settings();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render moderation queue
     */
    private function render_moderation_queue($queue) {
        ?>
        <div class="metapix-moderation-queue">
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select id="bulk-action-selector-top">
                        <option value="-1">Bulk Actions</option>
                        <option value="approve">Approve</option>
                        <option value="reject">Reject</option>
                        <option value="flag">Flag for Review</option>
                    </select>
                    <input type="submit" class="button action" value="Apply" onclick="bulkModerate()">
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <th>Image</th>
                        <th>User</th>
                        <th>ALT Text</th>
                        <th>Reports</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queue as $item): ?>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" name="image_ids[]" value="<?php echo $item->id; ?>">
                        </th>
                        <td>
                            <?php if ($item->attachment_id): ?>
                                <?php echo wp_get_attachment_image($item->attachment_id, 'thumbnail'); ?>
                            <?php else: ?>
                                <div class="placeholder-image">No Image</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($item->display_name); ?></strong><br>
                            <small><?php echo esc_html($item->user_email); ?></small>
                        </td>
                        <td><?php echo esc_html($item->alt_text); ?></td>
                        <td>
                            <?php if ($item->report_count > 0): ?>
                                <span class="badge badge-warning"><?php echo $item->report_count; ?> reports</span>
                            <?php else: ?>
                                <span class="badge badge-info">No reports</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($item->created_at)); ?></td>
                        <td>
                            <button class="button button-small button-primary" 
                                    onclick="moderateImage(<?php echo $item->id; ?>, 'approve')">
                                Approve
                            </button>
                            <button class="button button-small" 
                                    onclick="moderateImage(<?php echo $item->id; ?>, 'reject')">
                                Reject
                            </button>
                            <button class="button button-small button-secondary" 
                                    onclick="viewImageDetails(<?php echo $item->id; ?>)">
                                Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .metapix-moderation-queue .placeholder-image {
            width: 50px;
            height: 50px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #666;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-info { background: #17a2b8; color: white; }
        </style>
        
        <script>
        function moderateImage(imageId, action) {
            if (!confirm('Are you sure you want to ' + action + ' this image?')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'metapix_moderate_image',
                image_id: imageId,
                moderation_action: action,
                nonce: '<?php echo wp_create_nonce('metapix_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            });
        }
        
        function bulkModerate() {
            const selectedIds = jQuery('input[name="image_ids[]"]:checked').map(function() {
                return this.value;
            }).get();
            
            const action = jQuery('#bulk-action-selector-top').val();
            
            if (selectedIds.length === 0 || action === '-1') {
                alert('Please select images and an action.');
                return;
            }
            
            if (!confirm('Apply ' + action + ' to ' + selectedIds.length + ' images?')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'metapix_bulk_moderate',
                image_ids: selectedIds,
                moderation_action: action,
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
     * AJAX: Report image
     */
    public function ajax_report_image() {
        check_ajax_referer('metapix_public_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $image_id = intval($_POST['image_id']);
        $reason = sanitize_text_field($_POST['reason']);
        $description = sanitize_textarea_field($_POST['description']);
        
        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to report images']);
        }
        
        $report_id = $this->report_image($image_id, $user_id, $reason, $description);
        
        if ($report_id) {
            wp_send_json_success(['message' => 'Image reported successfully. Thank you for helping keep our community safe.']);
        } else {
            wp_send_json_error(['message' => 'Failed to report image or you have already reported this image']);
        }
    }
    
    /**
     * AJAX: Moderate image
     */
    public function ajax_moderate_image() {
        check_ajax_referer('metapix_admin_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        
        if (!UserRoles::user_can($user_id, 'moderate_images')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $image_id = intval($_POST['image_id']);
        $action = sanitize_text_field($_POST['moderation_action']);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        $result = $this->moderate_image($image_id, $action, $user_id, $reason);
        
        if ($result) {
            wp_send_json_success(['message' => 'Image moderated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to moderate image']);
        }
    }
    
    /**
     * Add report modal to frontend
     */
    public function add_report_modal() {
        if (!is_user_logged_in()) {
            return;
        }
        
        ?>
        <div id="metapix-report-modal" class="metapix-modal" style="display: none;">
            <div class="metapix-modal-content">
                <div class="metapix-modal-header">
                    <h3>Report Image</h3>
                    <span class="metapix-modal-close">&times;</span>
                </div>
                <div class="metapix-modal-body">
                    <form id="metapix-report-form">
                        <input type="hidden" id="report-image-id" name="image_id">
                        
                        <label for="report-reason">Reason for reporting:</label>
                        <select id="report-reason" name="reason" required>
                            <option value="">Select a reason...</option>
                            <?php foreach (self::REPORT_REASONS as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label for="report-description">Additional details (optional):</label>
                        <textarea id="report-description" name="description" rows="4" 
                                  placeholder="Please provide more details about why you're reporting this image..."></textarea>
                        
                        <div class="metapix-modal-actions">
                            <button type="button" class="button" onclick="closeReportModal()">Cancel</button>
                            <button type="submit" class="button button-primary">Submit Report</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <style>
        .metapix-modal {
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .metapix-modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 0;
            border-radius: 5px;
            width: 90%;
            max-width: 500px;
        }
        .metapix-modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .metapix-modal-close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .metapix-modal-body {
            padding: 20px;
        }
        .metapix-modal-body label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .metapix-modal-body select,
        .metapix-modal-body textarea {
            width: 100%;
            margin-bottom: 15px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .metapix-modal-actions {
            text-align: right;
        }
        .metapix-modal-actions .button {
            margin-left: 10px;
        }
        </style>
        
        <script>
        function openReportModal(imageId) {
            document.getElementById('report-image-id').value = imageId;
            document.getElementById('metapix-report-modal').style.display = 'block';
        }
        
        function closeReportModal() {
            document.getElementById('metapix-report-modal').style.display = 'none';
            document.getElementById('metapix-report-form').reset();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('metapix-report-modal');
            if (event.target == modal) {
                closeReportModal();
            }
        }
        
        // Handle form submission
        jQuery(document).ready(function($) {
            $('#metapix-report-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = {
                    action: 'metapix_report_image',
                    image_id: $('#report-image-id').val(),
                    reason: $('#report-reason').val(),
                    description: $('#report-description').val(),
                    nonce: '<?php echo wp_create_nonce('metapix_public_nonce'); ?>'
                };
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', formData, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        closeReportModal();
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
     * Filter moderated images from display
     */
    public function filter_moderated_images($display, $image_generation) {
        if ($image_generation->moderation_status === 'rejected') {
            return false;
        }
        
        if ($image_generation->moderation_status === 'flagged' && !UserRoles::is_admin(get_current_user_id())) {
            return false;
        }
        
        return $display;
    }
    
    /**
     * Enqueue moderation scripts
     */
    public function enqueue_moderation_scripts($hook) {
        if (strpos($hook, 'metapix-ai-moderation') === false) {
            return;
        }
        
        wp_enqueue_script('metapix-moderation', METAPIX_AI_PLUGIN_URL . 'assets/js/moderation.js', ['jquery'], METAPIX_AI_VERSION, true);
        wp_enqueue_style('metapix-moderation', METAPIX_AI_PLUGIN_URL . 'assets/css/moderation.css', [], METAPIX_AI_VERSION);
        
        wp_localize_script('metapix-moderation', 'metapixModerationAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('metapix_admin_nonce')
        ]);
    }
}