<?php
/**
 * Payment Schedule - Hiticx
 * Shows all installments and payment status
 */

// Simple error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Correct WordPress path for your server
$wp_load_path = '/home/hitiypom/public_html/wp-load.php';

if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    die('
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h1>🚧 Configuration Error</h1>
        <p>WordPress not found at: ' . $wp_load_path . '</p>
        <p>Please contact the administrator.</p>
    </div>
    ');
}

// Start session
if (!session_id()) {
    session_start();
}

// Check if student is logged in
if (!isset($_SESSION['sfm_student_id'])) {
    header('Location: portal-login.php');
    exit;
}

$student_id = $_SESSION['sfm_student_id'];

// Get student data from database
global $wpdb;
$students_table = $wpdb->prefix . 'sfm_pro_students';
$installments_table = $wpdb->prefix . 'sfm_pro_installments';

// Get student main data
$student = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $students_table WHERE id = %d", 
    $student_id
));

if (!$student) {
    die('Student not found.');
}

// Get all installments for this student
$installments = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $installments_table 
     WHERE student_id = %d 
     ORDER BY installment_no ASC", 
    $student_id
));

// Calculate totals
$total_paid = floatval($student->upfront_payment);
$total_installment_amount = 0;
$total_installment_paid = 0;

foreach ($installments as $installment) {
    $total_paid += floatval($installment->paid);
    $total_installment_amount += floatval($installment->amount);
    $total_installment_paid += floatval($installment->paid);
}

$remaining = max(0, $student->course_fee - $total_paid);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Schedule - Hiticx</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7f9fc;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #0073aa;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .student-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #0073aa;
        }
        
        .info-card h3 {
            color: #333;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .info-card p {
            color: #0073aa;
            font-size: 18px;
            font-weight: bold;
        }
        
        .payment-schedule {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .schedule-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .schedule-header h2 {
            color: #333;
            font-size: 24px;
        }
        
        .print-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .print-btn:hover {
            background: #218838;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .back-btn:hover {
            background: #545b62;
        }
        
        .installments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .installments-table th {
            background: #0073aa;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .installments-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .installments-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-paid {
            background: #e6f4ea;
            color: #1b6b2b;
        }
        
        .status-pending {
            background: #fff8e6;
            color: #8a5a00;
        }
        
        .status-overdue {
            background: #fff2f2;
            color: #a12929;
        }
        
        .status-partial {
            background: #eef7ff;
            color: #0b4a7a;
        }
        
        .amount-paid {
            color: #1b6b2b;
            font-weight: 600;
        }
        
        .amount-due {
            color: #a12929;
            font-weight: 600;
        }
        
        .summary-card {
            background: #eef7ff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
            border-left: 4px solid #0073aa;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .summary-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 6px;
        }
        
        .summary-item h4 {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .summary-item p {
            color: #0073aa;
            font-size: 18px;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header, .payment-schedule {
                padding: 20px;
            }
            
            .installments-table {
                font-size: 14px;
            }
            
            .installments-table th,
            .installments-table td {
                padding: 10px 8px;
            }
        }
        
        @media print {
            .print-btn, .back-btn {
                display: none;
            }
            
            body {
                background: white;
                padding: 0;
            }
            
            .header, .payment-schedule {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📅 Payment Schedule</h1>
            <p>Complete overview of your payment installments</p>
            
            <div class="student-info">
                <div class="info-card">
                    <h3>Student ID</h3>
                    <p><?php echo esc_html($student->student_code); ?></p>
                </div>
                <div class="info-card">
                    <h3>Student Name</h3>
                    <p><?php echo esc_html($student->student_name); ?></p>
                </div>
                <div class="info-card">
                    <h3>Course</h3>
                    <p><?php echo esc_html($student->course_name); ?></p>
                </div>
                <div class="info-card">
                    <h3>Total Course Fee</h3>
                    <p>£<?php echo number_format($student->course_fee, 2); ?></p>
                </div>
            </div>
        </div>
        
        <div class="payment-schedule">
            <div class="schedule-header">
                <h2>📋 Installment Schedule</h2>
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.print()" class="print-btn">🖨️ Print Schedule</button>
                    <a href="student-dashboard.php" class="back-btn">← Back to Dashboard</a>
                </div>
            </div>
            
            <?php if (empty($installments)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No installments scheduled</h3>
                    <p>Your payment schedule will appear here once installments are created.</p>
                </div>
            <?php else: ?>
                <div class="installments-table">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Installment #</th>
                                <th>Due Date</th>
                                <th>Amount Due</th>
                                <th>Amount Paid</th>
                                <th>Remaining</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $today = time();
                            foreach ($installments as $installment): 
                                $due_date = strtotime($installment->due_date);
                                $remaining_amount = $installment->amount - $installment->paid;
                                
                                // Determine status
                                if ($installment->paid >= $installment->amount) {
                                    $status = 'Paid';
                                    $status_class = 'status-paid';
                                } elseif ($installment->paid > 0) {
                                    $status = 'Partially Paid';
                                    $status_class = 'status-partial';
                                } elseif ($due_date < $today) {
                                    $status = 'Overdue';
                                    $status_class = 'status-overdue';
                                } else {
                                    $status = 'Pending';
                                    $status_class = 'status-pending';
                                }
                            ?>
                            <tr>
                                <td><strong>#<?php echo $installment->installment_no; ?></strong></td>
                                <td><?php echo date('d M, Y', $due_date); ?></td>
                                <td>£<?php echo number_format($installment->amount, 2); ?></td>
                                <td class="amount-paid">£<?php echo number_format($installment->paid, 2); ?></td>
                                <td class="amount-due">£<?php echo number_format($remaining_amount, 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status; ?>
                                        <?php if ($status === 'Overdue'): ?>⚠️<?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($installment->paid > 0): ?>
                                        <a href="download-receipt.php?installment_id=<?php echo $installment->id; ?>" 
                                           style="color: #0073aa; text-decoration: none; font-size: 12px;">
                                            📄 Receipt
                                        </a>
                                    <?php elseif ($status === 'Overdue' || $status === 'Pending'): ?>
                                        <span style="color: #666; font-size: 12px;">Pay on Due Date</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary Section -->
                <div class="summary-card">
                    <h3>💰 Payment Summary</h3>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <h4>Upfront Payment</h4>
                            <p>£<?php echo number_format($student->upfront_payment, 2); ?></p>
                        </div>
                        <div class="summary-item">
                            <h4>Installment Total</h4>
                            <p>£<?php echo number_format($total_installment_amount, 2); ?></p>
                        </div>
                        <div class="summary-item">
                            <h4>Installments Paid</h4>
                            <p>£<?php echo number_format($total_installment_paid, 2); ?></p>
                        </div>
                        <div class="summary-item">
                            <h4>Remaining Balance</h4>
                            <p>£<?php echo number_format($remaining, 2); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Legend -->
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                    <h4 style="margin-bottom: 10px;">Status Legend:</h4>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span class="status-badge status-paid">Paid</span>
                            <small>Payment completed</small>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span class="status-badge status-partial">Partially Paid</span>
                            <small>Partial payment made</small>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span class="status-badge status-pending">Pending</span>
                            <small>Payment not yet due</small>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span class="status-badge status-overdue">Overdue</span>
                            <small>Payment past due date</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-print if print parameter is set
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === '1') {
            window.print();
        }
    </script>
</body>
</html>