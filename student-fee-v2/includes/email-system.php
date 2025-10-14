<?php
/* ----------------------------
EMAIL SYSTEM
---------------------------- */

/**
 * Send welcome email
 */
function sfm_pro_send_welcome_email($student_id) {
    global $wpdb;
    
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    $installments_table = $wpdb->prefix . 'sfm_pro_installments';
    
    // Get student data
    $student = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $students_table WHERE id = %d", 
        $student_id
    ));
    
    if (!$student) {
        error_log("SFM PRO: Student not found for welcome email - ID: " . $student_id);
        return false;
    }
    
    // Get installments
    $installments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $installments_table WHERE student_id = %d ORDER BY installment_no ASC", 
        $student_id
    ));
    
    // Build installment rows
    $installment_rows = '';
    if (!empty($installments)) {
        foreach ($installments as $installment) {
            $status = 'Pending';
            if ($installment->paid > 0) {
                $status = $installment->paid >= $installment->amount ? 'Paid' : 'Partially Paid';
            }
            
            $installment_rows .= '<tr>';
            $installment_rows .= '<td>#' . $installment->installment_no . '</td>';
            $installment_rows .= '<td>' . sfm_format_date($installment->due_date) . '</td>';
            $installment_rows .= '<td>£' . number_format($installment->amount, 2) . '</td>';
            $installment_rows .= '<td>' . $status . '</td>';
            $installment_rows .= '</tr>';
        }
    } else {
        $installment_rows = '<tr><td colspan="4">No installments scheduled</td></tr>';
    }
    
    // Get company logo
    $company_logo = sfm_pro_get_company_logo();
    $logo_html = $company_logo ? '<img src="' . esc_url($company_logo) . '" alt="' . get_bloginfo('name') . '" style="max-height: 60px; max-width: 200px;">' : '';
    
    // Email subject
    $subject = 'Welcome to ' . get_bloginfo('name') . ' - Your Student Account Details';
    
    // Email body
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Welcome to ' . get_bloginfo('name') . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f6f6f6; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .header { background: #0073aa; color: white; padding: 30px 20px; text-align: center; }
            .content { padding: 30px; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e9ecef; }
            th { background-color: #f8f9fa; font-weight: 600; }
            .highlight { background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                ' . $logo_html . '
                <h1>Welcome to ' . get_bloginfo('name') . '</h1>
            </div>
            <div class="content">
                <p>Dear ' . $student->student_name . ',</p>
                <p>Welcome to our institution! Your student account has been successfully created.</p>
                
                <div class="highlight">
                    <h3 style="margin-top: 0;">Your Student Information</h3>
                    <p><strong>Student ID:</strong> ' . $student->student_code . '</p>
                    <p><strong>Course:</strong> ' . $student->course_name . '</p>
                    <p><strong>Join Date:</strong> ' . sfm_format_date($student->join_date) . '</p>
                </div>
                
                <h3>Fee Structure</h3>
                <table>
                    <tr>
                        <th>Description</th>
                        <th>Amount (£)</th>
                    </tr>
                    <tr>
                        <td>Total Course Fee</td>
                        <td>' . number_format($student->course_fee, 2) . '</td>
                    </tr>
                    <tr>
                        <td>Upfront Payment</td>
                        <td>' . number_format($student->upfront_payment, 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Remaining Balance</strong></td>
                        <td><strong>' . number_format($student->remaining, 2) . '</strong></td>
                    </tr>
                </table>
                
                <h3>Installment Schedule</h3>
                <table>
                    <tr>
                        <th>Installment</th>
                        <th>Due Date</th>
                        <th>Amount (£)</th>
                        <th>Status</th>
                    </tr>
                    ' . $installment_rows . '
                </table>
                
                <p><strong>Payment Instructions:</strong><br>
                Please ensure all payments are made before their due dates to avoid any late fees or service interruptions.</p>
                
                <p>If you have any questions about your fees or payment schedule, please don\'t hesitate to contact us.</p>
                
                <p>Best regards,<br>
                The ' . get_bloginfo('name') . ' Team</p>
            </div>
            <div class="footer">
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Set HTML content type
    add_filter('wp_mail_content_type', function() {
        return 'text/html';
    });
    
    // Send email
    $sent = wp_mail($student->email, $subject, $body);
    
    // Reset content type
    remove_filter('wp_mail_content_type', 'set_html_content_type');
    
    if (!$sent) {
        error_log("SFM PRO: Failed to send welcome email to: " . $student->email);
    }
    
    return $sent;
}

/**
 * Send payment confirmation email
 */
function sfm_pro_send_payment_email($installment_id, $payment_amount) {
    global $wpdb;
    
    $installments_table = $wpdb->prefix . 'sfm_pro_installments';
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    
    // Get installment and student data
    $installment = $wpdb->get_row($wpdb->prepare(
        "SELECT i.*, s.* FROM $installments_table i 
         JOIN $students_table s ON i.student_id = s.id 
         WHERE i.id = %d", 
        $installment_id
    ));
    
    if (!$installment) {
        error_log("SFM PRO: Installment not found for payment email - ID: " . $installment_id);
        return false;
    }
    
    // Get payment history
    $all_installments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $installments_table WHERE student_id = %d ORDER BY installment_no ASC", 
        $installment->student_id
    ));
    
    // Calculate totals
    $total_paid = floatval($installment->upfront_payment);
    $payment_history_rows = '';
    
    foreach ($all_installments as $inst) {
        $total_paid += floatval($inst->paid);
        
        if ($inst->paid > 0) {
            $status = $inst->paid >= $inst->amount ? 'Paid' : 'Partially Paid';
            $payment_history_rows .= '<tr>';
            $payment_history_rows .= '<td>Installment #' . $inst->installment_no . '</td>';
            $payment_history_rows .= '<td>' . sfm_format_date($inst->due_date) . '</td>';
            $payment_history_rows .= '<td>£' . number_format($inst->paid, 2) . '</td>';
            $payment_history_rows .= '<td>' . $status . '</td>';
            $payment_history_rows .= '</tr>';
        }
    }
    
    $remaining_balance = max(0, $installment->course_fee - $total_paid);
    
    // Next installment section
    $next_installment_section = '';
    foreach ($all_installments as $inst) {
        if ($inst->paid < $inst->amount) {
            $remaining_amount = $inst->amount - $inst->paid;
            $next_installment_section = '
            <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h3 style="margin-top: 0;">Next Installment Due</h3>
                <p><strong>Installment #' . $inst->installment_no . '</strong><br>
                <strong>Due Date:</strong> ' . sfm_format_date($inst->due_date) . '<br>
                <strong>Amount Due:</strong> £' . number_format($remaining_amount, 2) . '</p>
            </div>';
            break;
        }
    }
    
    // Get company logo
    $company_logo = sfm_pro_get_company_logo();
    $logo_html = $company_logo ? '<img src="' . esc_url($company_logo) . '" alt="' . get_bloginfo('name') . '" style="max-height: 60px; max-width: 200px;">' : '';
    
    // Email subject
    $subject = 'Payment Confirmation - ' . get_bloginfo('name');
    
    // Email body
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Payment Confirmation</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f6f6f6; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .header { background: #28a745; color: white; padding: 30px 20px; text-align: center; }
            .content { padding: 30px; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e9ecef; }
            th { background-color: #f8f9fa; font-weight: 600; }
            .success { background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                ' . $logo_html . '
                <h1>Payment Received - Thank You!</h1>
            </div>
            <div class="content">
                <p>Dear ' . $installment->student_name . ',</p>
                
                <div class="success">
                    <h3 style="margin-top: 0;">✅ Payment Confirmed</h3>
                    <p>We have successfully received your payment of <strong>£' . number_format($payment_amount, 2) . '</strong></p>
                    <p><strong>Date:</strong> ' . date('d-m-Y') . '</p>
                    <p><strong>For:</strong> Installment #' . $installment->installment_no . ' - ' . $installment->course_name . '</p>
                </div>
                
                <div class="info">
                    <h3>Current Balance Summary</h3>
                    <table>
                        <tr>
                            <td><strong>Student ID:</strong></td>
                            <td>' . $installment->student_code . '</td>
                        </tr>
                        <tr>
                            <td><strong>Total Course Fee:</strong></td>
                            <td>£' . number_format($installment->course_fee, 2) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Total Paid:</strong></td>
                            <td>£' . number_format($total_paid, 2) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Remaining Balance:</strong></td>
                            <td><strong>£' . number_format($remaining_balance, 2) . '</strong></td>
                        </tr>
                    </table>
                </div>
                
                ' . $next_installment_section . '
                
                <h3>Payment History</h3>
                <table>
                    <tr>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Amount (£)</th>
                        <th>Status</th>
                    </tr>
                    <tr>
                        <td>Upfront Payment</td>
                        <td>' . sfm_format_date($installment->join_date) . '</td>
                        <td>' . number_format($installment->upfront_payment, 2) . '</td>
                        <td>Paid</td>
                    </tr>
                    ' . $payment_history_rows . '
                </table>
                
                <p>If you have any questions about your payment or account balance, please contact our support team.</p>
                
                <p>Best regards,<br>
                The ' . get_bloginfo('name') . ' Team</p>
            </div>
            <div class="footer">
                <p>This is an automated payment confirmation. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Set HTML content type
    add_filter('wp_mail_content_type', function() {
        return 'text/html';
    });
    
    // Send email
    $sent = wp_mail($installment->email, $subject, $body);
    
    // Reset content type
    remove_filter('wp_mail_content_type', 'set_html_content_type');
    
    if (!$sent) {
        error_log("SFM PRO: Failed to send payment email to: " . $installment->email);
    }
    
    return $sent;
}

/**
 * Fix email from address and headers
 */
function sfm_pro_fix_email_headers($phpmailer) {
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    
    // Set from address to admin email instead of wordpress@domain
    $phpmailer->From = $admin_email;
    $phpmailer->FromName = $site_name;
    $phpmailer->Sender = $admin_email;
}

// Hook into WordPress email system
add_action('phpmailer_init', 'sfm_pro_fix_email_headers');
?>
