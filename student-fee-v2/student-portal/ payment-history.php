<?php
/**
 * Payment History - Hiticx
 * Complete implementation
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

// Get all payments (installments with payments made)
$payments = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $installments_table 
     WHERE student_id = %d AND paid > 0 
     ORDER BY due_date DESC", 
    $student_id
));

// Calculate totals
$total_paid = floatval($student->upfront_payment);
foreach ($payments as $payment) {
    $total_paid += floatval($payment->paid);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Hiticx</title>
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
        
        .payment-history {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .history-header h2 {
            color: #333;
            font-size: 24px;
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
        
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .payments-table th {
            background: #0073aa;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .payments-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .payments-table tr:hover {
            background: #f8f9fa;
        }
        
        .receipt-link {
            color: #0073aa;
            text-decoration: none;
            font-weight: 600;
        }
        
        .receipt-link:hover {
            text-decoration: underline;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .summary-card {
            background: #eef7ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
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
            
            .header, .payment-history {
                padding: 20px;
            }
            
            .payments-table {
                font-size: 14px;
            }
            
            .payments-table th,
            .payments-table td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Payment History</h1>
            <p>Complete record of all your payments</p>
        </div>
        
        <div class="payment-history">
            <div class="history-header">
                <h2>💰 Payment Transactions</h2>
                <a href="student-dashboard.php" class="back-btn">← Back to Dashboard</a>
            </div>
            
            <!-- Summary Section -->
            <div class="summary-card">
                <h3>Payment Summary</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <h4>Total Course Fee</h4>
                        <p>£<?php echo number_format($student->course_fee, 2); ?></p>
                    </div>
                    <div class="summary-item">
                        <h4>Upfront Payment</h4>
                        <p>£<?php echo number_format($student->upfront_payment, 2); ?></p>
                    </div>
                    <div class="summary-item">
                        <h4>Installments Paid</h4>
                        <p>£<?php echo number_format($total_paid - $student->upfront_payment, 2); ?></p>
                    </div>
                    <div class="summary-item">
                        <h4>Total Paid</h4>
                        <p>£<?php echo number_format($total_paid, 2); ?></p>
                    </div>
                </div>
            </div>
            
            <?php if (empty($payments)): ?>
                <div class="empty-state">
                    <h3>No Payment History</h3>
                    <p>You haven't made any installment payments yet.</p>
                    <p>Your payment history will appear here once you start making payments.</p>
                </div>
            <?php else: ?>
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Installment</th>
                            <th>Due Date</th>
                            <th>Amount Due</th>
                            <th>Amount Paid</th>
                            <th>Payment Date</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><strong>#<?php echo $payment->installment_no; ?></strong></td>
                            <td><?php echo date('d M, Y', strtotime($payment->due_date)); ?></td>
                            <td>£<?php echo number_format($payment->amount, 2); ?></td>
                            <td style="color: #28a745; font-weight: bold;">
                                £<?php echo number_format($payment->paid, 2); ?>
                            </td>
                            <td>
                                <?php 
                                // Use last update date as payment date, or due date if not available
                                $payment_date = !empty($payment->last_update) ? $payment->last_update : $payment->due_date;
                                echo date('d M, Y', strtotime($payment_date));
                                ?>
                            </td>
                            <td>
                                <a href="download-receipt.php?installment_id=<?php echo $payment->id; ?>" 
                                   class="receipt-link" target="_blank">
                                    📄 Download
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Include upfront payment in history -->
                <?php if ($student->upfront_payment > 0): ?>
                <div style="margin-top: 30px;">
                    <h3>Upfront Payment</h3>
                    <table class="payments-table">
                        <tr>
                            <td><strong>Upfront Payment</strong></td>
                            <td><?php echo date('d M, Y', strtotime($student->join_date)); ?></td>
                            <td>£<?php echo number_format($student->course_fee, 2); ?></td>
                            <td style="color: #28a745; font-weight: bold;">
                                £<?php echo number_format($student->upfront_payment, 2); ?>
                            </td>
                            <td><?php echo date('d M, Y', strtotime($student->join_date)); ?></td>
                            <td>
                                <span style="color: #666; font-style: italic;">
                                    Included in enrollment
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>