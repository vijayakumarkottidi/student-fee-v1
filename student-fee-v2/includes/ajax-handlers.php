<?php
/* ----------------------------
AJAX HANDLERS
---------------------------- */

// Remove logo
add_action('wp_ajax_sfm_pro_remove_logo', 'sfm_pro_remove_logo');
function sfm_pro_remove_logo() {
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    if (!check_ajax_referer('sfm_pro_nonce', 'nonce', false)) wp_send_json_error('Invalid nonce');
    
    $removed = sfm_pro_update_setting('company_logo_url', '');
    
    if ($removed) {
        wp_send_json_success('Logo removed successfully');
    } else {
        wp_send_json_error('Failed to remove logo');
    }
}

// Get student count
add_action('wp_ajax_sfm_pro_get_student_count', 'sfm_pro_get_student_count');
function sfm_pro_get_student_count() {
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    if (!check_ajax_referer('sfm_pro_nonce', 'nonce', false)) wp_send_json_error('Invalid nonce');
    
    global $wpdb;
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $students_table");
    
    wp_send_json_success(['count' => intval($count)]);
}

// Load students table and metrics
add_action('wp_ajax_sfm_pro_load', 'sfm_pro_load_students_table');
function sfm_pro_load_students_table() {
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    if (!check_ajax_referer('sfm_pro_nonce', 'nonce', false)) wp_send_json_error('Invalid nonce');
    
    $search = sanitize_text_field($_POST['search'] ?? '');
    
    $table_html = sfm_pro_generate_students_table($search);
    $metrics_html = sfm_pro_generate_metrics($search);
    
    wp_send_json_success([
        'table' => $table_html,
        'metrics' => $metrics_html
    ]);
}

// Pay installment
add_action('wp_ajax_sfm_pro_pay', 'sfm_pro_pay_installment');
function sfm_pro_pay_installment() {
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    if (!check_ajax_referer('sfm_pro_nonce', 'nonce', false)) wp_send_json_error('Invalid nonce');
    
    global $wpdb;
    $installments_table = $wpdb->prefix . 'sfm_pro_installments';
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    
    $installment_id = intval($_POST['installment_id']);
    $amount = floatval($_POST['amount']);
    
    // Get current installment data
    $installment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $installments_table WHERE id = %d", 
        $installment_id
    ));
    
    if (!$installment) {
        wp_send_json_error('Installment not found');
    }
    
    // Calculate remaining amount for this installment
    $remaining_for_installment = floatval($installment->amount) - floatval($installment->paid);
    
    // If payment is more than remaining, cap it and handle excess
    $actual_payment = min($amount, $remaining_for_installment);
    $excess_amount = $amount - $actual_payment;
    
    $new_paid = floatval($installment->paid) + $actual_payment;
    $status = $new_paid >= $installment->amount ? 'Paid' : 'Partially Paid';
    
    // Update installment
    $updated = $wpdb->update(
        $installments_table,
        [
            'paid' => $new_paid,
            'status' => $status
        ],
        ['id' => $installment_id]
    );
    
    if ($updated) {
        // Update student remaining balance
        $student = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $students_table WHERE id = %d", 
            $installment->student_id
        ));
        
        if ($student) {
            $new_remaining = max(0, floatval($student->remaining) - $actual_payment);
            $student_status = $new_remaining <= 0 ? 'Cleared' : 'Ongoing';
            
            $wpdb->update(
                $students_table,
                [
                    'remaining' => $new_remaining,
                    'status' => $student_status,
                    'last_update' => current_time('mysql')
                ],
                ['id' => $installment->student_id]
            );
        }
        
        // Handle excess payment - apply to next unpaid installment
        if ($excess_amount > 0) {
            $next_installment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $installments_table 
                 WHERE student_id = %d AND paid < amount 
                 ORDER BY installment_no ASC 
                 LIMIT 1",
                $installment->student_id
            ));
            
            if ($next_installment) {
                $next_remaining = floatval($next_installment->amount) - floatval($next_installment->paid);
                $excess_to_apply = min($excess_amount, $next_remaining);
                
                $next_new_paid = floatval($next_installment->paid) + $excess_to_apply;
                $next_status = $next_new_paid >= $next_installment->amount ? 'Paid' : 'Partially Paid';
                
                $wpdb->update(
                    $installments_table,
                    [
                        'paid' => $next_new_paid,
                        'status' => $next_status
                    ],
                    ['id' => $next_installment->id]
                );
                
                // Update student remaining balance again for excess
                if ($student) {
                    $final_remaining = max(0, $new_remaining - $excess_to_apply);
                    $final_student_status = $final_remaining <= 0 ? 'Cleared' : 'Ongoing';
                    
                    $wpdb->update(
                        $students_table,
                        [
                            'remaining' => $final_remaining,
                            'status' => $final_student_status,
                            'last_update' => current_time('mysql')
                        ],
                        ['id' => $installment->student_id]
                    );
                }
                
                // Send payment confirmation email for both payments
                sfm_pro_send_payment_email($installment_id, $actual_payment);
                if ($excess_to_apply > 0) {
                    sfm_pro_send_payment_email($next_installment->id, $excess_to_apply);
                }
                
                wp_send_json_success('Payment recorded successfully. £' . number_format($actual_payment, 2) . ' applied to installment #' . $installment->installment_no . ' and £' . number_format($excess_to_apply, 2) . ' applied to installment #' . $next_installment->installment_no);
            } else {
                // No next installment found, just use the capped amount
                sfm_pro_send_payment_email($installment_id, $actual_payment);
                wp_send_json_success('Payment recorded successfully. £' . number_format($actual_payment, 2) . ' applied (excess amount ignored - no more installments)');
            }
        } else {
            // No excess, normal payment
            sfm_pro_send_payment_email($installment_id, $actual_payment);
            wp_send_json_success('Payment recorded successfully');
        }
    } else {
        wp_send_json_error('Failed to record payment');
    }
}

// Unpay installment
add_action('wp_ajax_sfm_pro_unpay', 'sfm_pro_unpay_installment');
function sfm_pro_unpay_installment() {
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    if (!check_ajax_referer('sfm_pro_nonce', 'nonce', false)) wp_send_json_error('Invalid nonce');
    
    global $wpdb;
    $installments_table = $wpdb->prefix . 'sfm_pro_installments';
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    
    $installment_id = intval($_POST['installment_id']);
    
    // Get current installment data
    $installment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $installments_table WHERE id = %d", 
        $installment_id
    ));
    
    if (!$installment) {
        wp_send_json_error('Installment not found');
    }
    
    $amount_paid = floatval($installment->paid);
    
    // Update installment
    $updated = $wpdb->update(
        $installments_table,
        [
            'paid' => 0,
            'status' => 'Pending'
        ],
        ['id' => $installment_id]
    );
    
    if ($updated) {
        // Update student remaining balance
        $student = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $students_table WHERE id = %d", 
            $installment->student_id
        ));
        
        if ($student) {
            $new_remaining = floatval($student->remaining) + $amount_paid;
            $student_status = $new_remaining <= 0 ? 'Cleared' : 'Ongoing';
            
            $wpdb->update(
                $students_table,
                [
                    'remaining' => $new_remaining,
                    'status' => $student_status,
                    'last_update' => current_time('mysql')
                ],
                ['id' => $installment->student_id]
            );
        }
        
        wp_send_json_success('Installment marked as unpaid');
    } else {
        wp_send_json_error('Failed to update installment');
    }
}

// Delete student
add_action('wp_ajax_sfm_pro_delete_student', 'sfm_pro_delete_student');
function sfm_pro_delete_student() {
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    if (!check_ajax_referer('sfm_pro_nonce', 'nonce', false)) wp_send_json_error('Invalid nonce');
    
    global $wpdb;
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    $installments_table = $wpdb->prefix . 'sfm_pro_installments';
    
    $student_id = intval($_POST['student_id']);
    
    // Delete installments first
    $wpdb->delete($installments_table, ['student_id' => $student_id]);
    
    // Delete student
    $deleted = $wpdb->delete($students_table, ['id' => $student_id]);
    
    if ($deleted) {
        wp_send_json_success('Student deleted successfully');
    } else {
        wp_send_json_error('Failed to delete student');
    }
}

// Update student name
add_action('wp_ajax_sfm_pro_update_name', 'sfm_pro_update_student_name');
function sfm_pro_update_student_name() {
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    if (!check_ajax_referer('sfm_pro_nonce', 'nonce', false)) wp_send_json_error('Invalid nonce');
    
    global $wpdb;
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    
    $student_id = intval($_POST['student_id']);
    $student_name = sanitize_text_field($_POST['student_name']);
    
    if (empty($student_name)) {
        wp_send_json_error('Student name cannot be empty');
    }
    
    $updated = $wpdb->update(
        $students_table,
        [
            'student_name' => $student_name,
            'last_update' => current_time('mysql')
        ],
        ['id' => $student_id]
    );
    
    if ($updated !== false) {
        wp_send_json_success('Student name updated successfully');
    } else {
        wp_send_json_error('Failed to update student name');
    }
}

// Test email system
add_action('wp_ajax_sfm_pro_test_email_system', 'sfm_pro_test_email_system');
function sfm_pro_test_email_system() {
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    if (!check_ajax_referer('sfm_pro_test_email', 'nonce', false)) wp_send_json_error('Invalid nonce');
    
    $admin_email = get_option('admin_email');
    $subject = 'SFM PRO - Test Email';
    $message = 'This is a test email from Student Fee Manager PRO plugin. If you received this, your email system is working correctly.';
    
    $sent = wp_mail($admin_email, $subject, $message);
    
    if ($sent) {
        wp_send_json_success('Test email sent successfully');
    } else {
        wp_send_json_error('Failed to send test email');
    }
}

/**
 * Generate students table HTML
 */
function sfm_pro_generate_students_table($search = '') {
    global $wpdb;
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    $installments_table = $wpdb->prefix . 'sfm_pro_installments';
    
    // Build query with search
    $query = "SELECT s.*, 
                     GROUP_CONCAT(CONCAT(i.id, '|', i.installment_no, '|', i.amount, '|', i.paid, '|', i.due_date, '|', i.status) SEPARATOR ';') as installments
              FROM $students_table s
              LEFT JOIN $installments_table i ON s.id = i.student_id";
    
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(s.student_code LIKE %s OR s.student_name LIKE %s OR s.email LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($where)) {
        $query .= " WHERE " . implode(' AND ', $where);
    }
    
    $query .= " GROUP BY s.id ORDER BY s.created_at DESC";
    
    if (!empty($params)) {
        $students = $wpdb->get_results($wpdb->prepare($query, $params));
    } else {
        $students = $wpdb->get_results($query);
    }
    
    if (empty($students)) {
        return '
        <div class="sfm-empty-state">
            <h3>📝 No Students Found</h3>
            <p>' . (empty($search) ? 'Get started by adding your first student!' : 'No students match your search criteria.') . '</p>
            <a href="' . admin_url('admin.php?page=sfm-pro-add') . '" class="button button-primary">➕ Add New Student</a>
        </div>';
    }
    
    ob_start();
    ?>
    <table class="sfm-table">
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Course</th>
                <th>Total Fee</th>
                <th>Paid</th>
                <th>Remaining</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $student): 
                $total_paid = floatval($student->upfront_payment);
                $installments = [];
                $has_overdue = false;
                
                if ($student->installments) {
                    $installment_data = explode(';', $student->installments);
                    foreach ($installment_data as $inst) {
                        $parts = explode('|', $inst);
                        if (count($parts) >= 6) {
                            $installments[] = [
                                'id' => $parts[0],
                                'no' => $parts[1],
                                'amount' => floatval($parts[2]),
                                'paid' => floatval($parts[3]),
                                'due_date' => $parts[4],
                                'status' => $parts[5]
                            ];
                            $total_paid += floatval($parts[3]);
                            
                            // Check if this installment is overdue
                            if ($parts[3] < $parts[2] && strtotime($parts[4]) < time()) {
                                $has_overdue = true;
                            }
                        }
                    }
                }
                
                $remaining = max(0, $student->course_fee - $total_paid);
                $status_class = '';
                $row_class = $has_overdue ? 'sfm-overdue-student' : '';
                
                switch ($student->status) {
                    case 'Cleared': $status_class = 'badge-paid'; break;
                    case 'Ongoing': $status_class = 'badge-ongoing'; break;
                    default: $status_class = 'badge-ongoing';
                }
            ?>
            <tr class="<?php echo $row_class; ?>">
                <td>
                    <a href="#" class="sfm-student-id" data-student-id="<?php echo $student->id; ?>">
                        <?php echo esc_html($student->student_code); ?>
                    </a>
                </td>
                <td>
                    <span class="sfm-student-name <?php echo $has_overdue ? 'sfm-overdue-name' : ''; ?>">
                        <?php echo esc_html($student->student_name); ?>
                    </span>
                    <?php if ($has_overdue): ?>
                        <span class="sfm-overdue-badge" title="This student has overdue payments">⚠️</span>
                    <?php endif; ?>
                    <button class="sfm-edit-name" data-student-id="<?php echo $student->id; ?>" title="Edit name">✏️</button>
                </td>
                <td><?php echo esc_html($student->email); ?></td>
                <td><?php echo esc_html($student->course_name); ?></td>
                <td>£<?php echo number_format($student->course_fee, 2); ?></td>
                <td>£<?php echo number_format($total_paid, 2); ?></td>
                <td>£<?php echo number_format($remaining, 2); ?></td>
                <td>
                    <span class="sfm-badge <?php echo $status_class; ?>">
                        <?php echo esc_html($student->status); ?>
                        <?php if ($has_overdue): ?>
                            (Overdue)
                        <?php endif; ?>
                    </span>
                </td>
                <td>
                    <button class="sfm-delete-btn" data-student-id="<?php echo $student->id; ?>">Delete</button>
                </td>
            </tr>
            <tr id="sfm-expand-<?php echo $student->id; ?>" class="sfm-expandable-row">
                <td colspan="9">
                    <div class="sfm-expandable-content">
                        <h4>📅 Installments for <?php echo esc_html($student->student_name); ?></h4>
                        <?php if ($has_overdue): ?>
                            <div style="background:#fff2f2;color:#a12929;padding:10px;border-radius:4px;margin-bottom:15px;border-left:4px solid #e74c3c;">
                                <strong>⚠️ Attention:</strong> This student has overdue payments!
                            </div>
                        <?php endif; ?>
                        <div class="sfm-installments">
                            <?php if (!empty($installments)): ?>
                                <?php foreach ($installments as $installment): 
                                    $due_class = '';
                                    $due_date = strtotime($installment['due_date']);
                                    $today = time();
                                    $is_overdue = false;
                                    
                                    if ($installment['paid'] >= $installment['amount']) {
                                        $due_class = 'badge-paid';
                                    } elseif ($due_date < $today) {
                                        $due_class = 'badge-overdue';
                                        $is_overdue = true;
                                    } else {
                                        $due_class = 'badge-due';
                                    }
                                ?>
                                <div class="sfm-installment-item <?php echo $is_overdue ? 'sfm-overdue-installment' : ''; ?>">
                                    <div class="sfm-installment-info">
                                        <strong>Installment #<?php echo $installment['no']; ?></strong>
                                        <span class="sfm-badge <?php echo $due_class; ?>">
                                            Due: <?php echo sfm_format_date($installment['due_date']); ?>
                                            <?php if ($is_overdue): ?>
                                                ⚠️ OVERDUE
                                            <?php endif; ?>
                                        </span>
                                        <span>Amount: £<?php echo number_format($installment['amount'], 2); ?></span>
                                        <span>Paid: £<?php echo number_format($installment['paid'], 2); ?></span>
                                        <span>Remaining: £<?php echo number_format($installment['amount'] - $installment['paid'], 2); ?></span>
                                    </div>
                                    <div class="sfm-installment-actions">
                                        <?php if ($installment['paid'] < $installment['amount']): ?>
                                            <input type="number" class="sfm-install-input" 
                                                   placeholder="Amount" step="0.01" min="0.01" 
                                                   max="<?php echo $installment['amount'] - $installment['paid']; ?>"
                                                   title="Maximum: £<?php echo number_format($installment['amount'] - $installment['paid'], 2); ?>">
                                            <button class="button sfm-pay-btn" data-id="<?php echo $installment['id']; ?>">
                                                Pay
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($installment['paid'] > 0): ?>
                                            <button class="button sfm-unpay-btn" data-id="<?php echo $installment['id']; ?>">
                                                Unpay
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No installments scheduled.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

/**
 * Generate metrics HTML
 */
function sfm_pro_generate_metrics($search = '') {
    global $wpdb;
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    $installments_table = $wpdb->prefix . 'sfm_pro_installments';
    
    // Build query with search
    $query = "SELECT 
                COUNT(*) as total_students,
                SUM(course_fee) as total_fees,
                SUM(upfront_payment) as total_upfront,
                SUM(remaining) as total_remaining,
                AVG(course_fee) as avg_fee
              FROM $students_table";
    
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(student_code LIKE %s OR student_name LIKE %s OR email LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($where)) {
        $query .= " WHERE " . implode(' AND ', $where);
    }
    
    if (!empty($params)) {
        $metrics = $wpdb->get_row($wpdb->prepare($query, $params));
    } else {
        $metrics = $wpdb->get_row($query);
    }
    
    // Count overdue installments
    $overdue_query = "SELECT COUNT(*) as overdue_count 
                      FROM $installments_table 
                      WHERE due_date < CURDATE() AND paid < amount";
    
    if (!empty($search)) {
        $overdue_query .= " AND student_id IN (
            SELECT id FROM $students_table 
            WHERE (student_code LIKE %s OR student_name LIKE %s OR email LIKE %s)
        )";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $overdue_params = [$search_term, $search_term, $search_term];
        $overdue_count = $wpdb->get_var($wpdb->prepare($overdue_query, $overdue_params));
    } else {
        $overdue_count = $wpdb->get_var($overdue_query);
    }
    
    ob_start();
    ?>
    <div class="sfm-metrics-grid">
        <div class="sfm-metric-card">
            <div class="sfm-metric-value"><?php echo intval($metrics->total_students ?? 0); ?></div>
            <div class="sfm-metric-label">Total Students</div>
        </div>
        <div class="sfm-metric-card">
            <div class="sfm-metric-value">£<?php echo number_format($metrics->total_fees ?? 0, 2); ?></div>
            <div class="sfm-metric-label">Total Fees</div>
        </div>
        <div class="sfm-metric-card">
            <div class="sfm-metric-value">£<?php echo number_format($metrics->total_upfront ?? 0, 2); ?></div>
            <div class="sfm-metric-label">Total Paid</div>
        </div>
        <div class="sfm-metric-card">
            <div class="sfm-metric-value">£<?php echo number_format($metrics->total_remaining ?? 0, 2); ?></div>
            <div class="sfm-metric-label">Pending Balance</div>
        </div>
        <div class="sfm-metric-card">
            <div class="sfm-metric-value"><?php echo intval($overdue_count); ?></div>
            <div class="sfm-metric-label">Overdue Payments</div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>