<?php
/* ----------------------------
REPORT DOWNLOAD FUNCTIONS
---------------------------- */

/**
 * Generate and download reports
 */
function sfm_pro_download_report() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    
    // Verify nonce
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'sfm_download_report')) {
        wp_die('Invalid request - Security verification failed');
    }
    
    global $wpdb;
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    $installments_table = $wpdb->prefix . 'sfm_pro_installments';
    
    $report_type = sanitize_text_field($_GET['report_type'] ?? 'students_list');
    $format = sanitize_text_field($_GET['format'] ?? 'csv');
    
    // Get all students with their installments
    $students = $wpdb->get_results("
        SELECT s.*, 
               GROUP_CONCAT(CONCAT(i.installment_no, '|', i.amount, '|', i.paid, '|', i.due_date, '|', i.status) SEPARATOR ';') as installments
        FROM $students_table s
        LEFT JOIN $installments_table i ON s.id = i.student_id
        GROUP BY s.id
        ORDER BY s.created_at DESC
    ");
    
    switch ($report_type) {
        case 'students_list':
            $filename = 'students-list-' . date('Y-m-d');
            $headers = ['Student ID', 'Name', 'Email', 'Course', 'Total Fee', 'Upfront Paid', 'Remaining', 'Installment Period', 'Status', 'Join Date', 'Next Payment Date'];
            break;
            
        case 'payment_summary':
            $filename = 'payment-summary-' . date('Y-m-d');
            $headers = ['Student ID', 'Name', 'Course', 'Total Fee', 'Total Paid', 'Remaining', 'Payment Status'];
            break;
            
        case 'overdue_report':
            $filename = 'overdue-payments-' . date('Y-m-d');
            $headers = ['Student ID', 'Name', 'Course', 'Overdue Installment', 'Due Date', 'Amount Due', 'Days Overdue'];
            break;
            
        default:
            $filename = 'student-report-' . date('Y-m-d');
            $headers = ['Student ID', 'Name', 'Email', 'Course', 'Total Fee', 'Upfront Paid', 'Remaining', 'Status'];
    }
    
    if ($format === 'csv') {
        sfm_pro_generate_csv($filename, $headers, $students, $report_type);
    } else {
        sfm_pro_generate_excel($filename, $headers, $students, $report_type);
    }
    
    exit;
}

/**
 * Generate CSV report
 */
function sfm_pro_generate_csv($filename, $headers, $students, $report_type) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    fputcsv($output, $headers);
    
    foreach ($students as $student) {
        $total_paid = floatval($student->upfront_payment);
        $installments = [];
        
        if ($student->installments) {
            $installment_data = explode(';', $student->installments);
            foreach ($installment_data as $inst) {
                $parts = explode('|', $inst);
                if (count($parts) >= 5) {
                    $installments[] = [
                        'no' => $parts[0],
                        'amount' => floatval($parts[1]),
                        'paid' => floatval($parts[2]),
                        'due_date' => $parts[3],
                        'status' => $parts[4]
                    ];
                    $total_paid += floatval($parts[2]);
                }
            }
        }
        
        $remaining = max(0, $student->course_fee - $total_paid);
        
        switch ($report_type) {
            case 'students_list':
                $row = [
                    $student->student_code,
                    $student->student_name,
                    $student->email,
                    $student->course_name,
                    number_format($student->course_fee, 2),
                    number_format($student->upfront_payment, 2),
                    number_format($remaining, 2),
                    $student->installment_period . ' months',
                    $student->status,
                    sfm_format_date($student->join_date),
                    sfm_format_date($student->next_payment_date)
                ];
                fputcsv($output, $row);
                break;
                
            case 'payment_summary':
                $payment_status = $remaining <= 0 ? 'Paid in Full' : ($total_paid > 0 ? 'Partially Paid' : 'Not Paid');
                $row = [
                    $student->student_code,
                    $student->student_name,
                    $student->course_name,
                    number_format($student->course_fee, 2),
                    number_format($total_paid, 2),
                    number_format($remaining, 2),
                    $payment_status
                ];
                fputcsv($output, $row);
                break;
                
            case 'overdue_report':
                $today = time();
                $has_overdue = false;
                
                foreach ($installments as $installment) {
                    if ($installment['paid'] < $installment['amount'] && strtotime($installment['due_date']) < $today) {
                        $days_overdue = floor(($today - strtotime($installment['due_date'])) / (60 * 60 * 24));
                        $amount_due = $installment['amount'] - $installment['paid'];
                        
                        $row = [
                            $student->student_code,
                            $student->student_name,
                            $student->course_name,
                            'Installment #' . $installment['no'],
                            sfm_format_date($installment['due_date']),
                            number_format($amount_due, 2),
                            $days_overdue
                        ];
                        fputcsv($output, $row);
                        $has_overdue = true;
                    }
                }
                
                // If no overdue installments for this student, skip
                if (!$has_overdue) {
                    continue;
                }
                break;
                
            default:
                $row = [
                    $student->student_code,
                    $student->student_name,
                    $student->email,
                    $student->course_name,
                    number_format($student->course_fee, 2),
                    number_format($student->upfront_payment, 2),
                    number_format($remaining, 2),
                    $student->status
                ];
                fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

/**
 * Generate Excel report (HTML table that Excel can open)
 */
function sfm_pro_generate_excel($filename, $headers, $students, $report_type) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>td, th { border: 1px solid #ddd; padding: 8px; }</style>';
    echo '</head>';
    echo '<body>';
    echo '<table>';
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th>' . esc_html($header) . '</th>';
    }
    echo '</tr>';
    
    foreach ($students as $student) {
        $total_paid = floatval($student->upfront_payment);
        $installments = [];
        
        if ($student->installments) {
            $installment_data = explode(';', $student->installments);
            foreach ($installment_data as $inst) {
                $parts = explode('|', $inst);
                if (count($parts) >= 5) {
                    $installments[] = [
                        'no' => $parts[0],
                        'amount' => floatval($parts[1]),
                        'paid' => floatval($parts[2]),
                        'due_date' => $parts[3],
                        'status' => $parts[4]
                    ];
                    $total_paid += floatval($parts[2]);
                }
            }
        }
        
        $remaining = max(0, $student->course_fee - $total_paid);
        
        switch ($report_type) {
            case 'students_list':
                echo '<tr>';
                echo '<td>' . esc_html($student->student_code) . '</td>';
                echo '<td>' . esc_html($student->student_name) . '</td>';
                echo '<td>' . esc_html($student->email) . '</td>';
                echo '<td>' . esc_html($student->course_name) . '</td>';
                echo '<td>' . number_format($student->course_fee, 2) . '</td>';
                echo '<td>' . number_format($student->upfront_payment, 2) . '</td>';
                echo '<td>' . number_format($remaining, 2) . '</td>';
                echo '<td>' . $student->installment_period . ' months</td>';
                echo '<td>' . esc_html($student->status) . '</td>';
                echo '<td>' . sfm_format_date($student->join_date) . '</td>';
                echo '<td>' . sfm_format_date($student->next_payment_date) . '</td>';
                echo '</tr>';
                break;
                
            case 'payment_summary':
                $payment_status = $remaining <= 0 ? 'Paid in Full' : ($total_paid > 0 ? 'Partially Paid' : 'Not Paid');
                echo '<tr>';
                echo '<td>' . esc_html($student->student_code) . '</td>';
                echo '<td>' . esc_html($student->student_name) . '</td>';
                echo '<td>' . esc_html($student->course_name) . '</td>';
                echo '<td>' . number_format($student->course_fee, 2) . '</td>';
                echo '<td>' . number_format($total_paid, 2) . '</td>';
                echo '<td>' . number_format($remaining, 2) . '</td>';
                echo '<td>' . $payment_status . '</td>';
                echo '</tr>';
                break;
                
            case 'overdue_report':
                $today = time();
                $has_overdue = false;
                
                foreach ($installments as $installment) {
                    if ($installment['paid'] < $installment['amount'] && strtotime($installment['due_date']) < $today) {
                        $days_overdue = floor(($today - strtotime($installment['due_date'])) / (60 * 60 * 24));
                        $amount_due = $installment['amount'] - $installment['paid'];
                        
                        echo '<tr>';
                        echo '<td>' . esc_html($student->student_code) . '</td>';
                        echo '<td>' . esc_html($student->student_name) . '</td>';
                        echo '<td>' . esc_html($student->course_name) . '</td>';
                        echo '<td>Installment #' . $installment['no'] . '</td>';
                        echo '<td>' . sfm_format_date($installment['due_date']) . '</td>';
                        echo '<td>' . number_format($amount_due, 2) . '</td>';
                        echo '<td>' . $days_overdue . '</td>';
                        echo '</tr>';
                        $has_overdue = true;
                    }
                }
                
                // If no overdue installments for this student, skip
                if (!$has_overdue) {
                    continue;
                }
                break;
                
            default:
                echo '<tr>';
                echo '<td>' . esc_html($student->student_code) . '</td>';
                echo '<td>' . esc_html($student->student_name) . '</td>';
                echo '<td>' . esc_html($student->email) . '</td>';
                echo '<td>' . esc_html($student->course_name) . '</td>';
                echo '<td>' . number_format($student->course_fee, 2) . '</td>';
                echo '<td>' . number_format($student->upfront_payment, 2) . '</td>';
                echo '<td>' . number_format($remaining, 2) . '</td>';
                echo '<td>' . esc_html($student->status) . '</td>';
                echo '</tr>';
        }
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
}

/**
 * Render reports download section
 */
function sfm_pro_render_reports_section() {
    $report_nonce = wp_create_nonce('sfm_download_report');
    ?>
    <div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;margin-bottom:20px;">
        <h3 style="margin-top:0;">📊 Download Reports</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:15px;">
            
            <div style="border:1px solid #e1e1e1;padding:15px;border-radius:6px;">
                <h4 style="margin-top:0;">📋 Students List</h4>
                <p style="color:#666;margin-bottom:15px;">Complete list of all students with their details</p>
                <div style="display:flex;gap:10px;">
                    <a href="<?php echo admin_url('admin-post.php?action=sfm_download_report&report_type=students_list&format=csv&nonce=' . $report_nonce); ?>" class="button button-primary">
                        📥 Download CSV
                    </a>
                    <a href="<?php echo admin_url('admin-post.php?action=sfm_download_report&report_type=students_list&format=excel&nonce=' . $report_nonce); ?>" class="button button-primary">
                        📥 Download Excel
                    </a>
                </div>
            </div>
            
            <div style="border:1px solid #e1e1e1;padding:15px;border-radius:6px;">
                <h4 style="margin-top:0;">💰 Payment Summary</h4>
                <p style="color:#666;margin-bottom:15px;">Overview of payments received and pending amounts</p>
                <div style="display:flex;gap:10px;">
                    <a href="<?php echo admin_url('admin-post.php?action=sfm_download_report&report_type=payment_summary&format=csv&nonce=' . $report_nonce); ?>" class="button button-primary">
                        📥 Download CSV
                    </a>
                    <a href="<?php echo admin_url('admin-post.php?action=sfm_download_report&report_type=payment_summary&format=excel&nonce=' . $report_nonce); ?>" class="button button-primary">
                        📥 Download Excel
                    </a>
                </div>
            </div>
            
            <div style="border:1px solid #e1e1e1;padding:15px;border-radius:6px;">
                <h4 style="margin-top:0;">⏰ Overdue Payments</h4>
                <p style="color:#666;margin-bottom:15px;">List of all overdue installments with due dates</p>
                <div style="display:flex;gap:10px;">
                    <a href="<?php echo admin_url('admin-post.php?action=sfm_download_report&report_type=overdue_report&format=csv&nonce=' . $report_nonce); ?>" class="button button-primary">
                        📥 Download CSV
                    </a>
                    <a href="<?php echo admin_url('admin-post.php?action=sfm_download_report&report_type=overdue_report&format=excel&nonce=' . $report_nonce); ?>" class="button button-primary">
                        📥 Download Excel
                    </a>
                </div>
            </div>
            
        </div>
        
        <div style="margin-top:15px;padding:12px;background:#f8f9fa;border-radius:4px;border-left:4px solid #0073aa;">
            <strong>💡 Tip:</strong> Reports are generated based on current data. Use CSV for data analysis and Excel for printing.
        </div>
    </div>
    <?php
}
?>