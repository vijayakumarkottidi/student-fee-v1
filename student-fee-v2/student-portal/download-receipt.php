<?php
/**
 * Download Payment Receipt - Hiticx
 */

require_once __DIR__ . '/portal-bootstrap.php';

hiticx_portal_bootstrap();

if (!isset($_SESSION['sfm_student_id'])) {
    header('Location: portal-login.php');
    exit;
}

$student_id = $_SESSION['sfm_student_id'];
$installment_id = intval($_GET['installment_id'] ?? 0);

// Get student and installment data
global $wpdb;
$students_table = $wpdb->prefix . 'sfm_pro_students';
$installments_table = $wpdb->prefix . 'sfm_pro_installments';

$student = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $students_table WHERE id = %d", 
    $student_id
));

if (!$student) {
    die('Student not found.');
}

// Get installment data
$installment = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $installments_table WHERE id = %d AND student_id = %d", 
    $installment_id, $student_id
));

if (!$installment || $installment->paid <= 0) {
    die('No payment found for this installment.');
}

// Get company logo
$company_logo = sfm_pro_get_company_logo();

// Generate receipt PDF (HTML that can be printed as PDF)
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?php echo $student->student_code; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: white;
            padding: 20px;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #0073aa;
            border-radius: 10px;
            padding: 30px;
            background: white;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #0073aa;
        }
        
        .logo {
            max-width: 200px;
            max-height: 80px;
            margin-bottom: 15px;
        }
        
        .receipt-title {
            font-size: 28px;
            color: #0073aa;
            margin-bottom: 10px;
        }
        
        .receipt-subtitle {
            color: #666;
            font-size: 16px;
        }
        
        .receipt-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .info-section h3 {
            color: #0073aa;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            color: #333;
        }
        
        .payment-details {
            background: #e6f4ea;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .payment-amount {
            font-size: 32px;
            font-weight: bold;
            color: #1b6b2b;
            margin: 10px 0;
        }
        
        .payment-description {
            color: #666;
            font-size: 16px;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 14px;
        }
        
        .print-btn {
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .print-btn:hover {
            background: #005a87;
        }
        
        @media print {
            .print-btn {
                display: none;
            }
            
            body {
                padding: 0;
            }
            
            .receipt-container {
                border: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <button class="print-btn" onclick="window.print()">🖨️ Print Receipt</button>
        
        <div class="receipt-header">
            <?php if ($company_logo): ?>
                <img src="<?php echo $company_logo; ?>" alt="Hiticx" class="logo">
            <?php endif; ?>
            <h1 class="receipt-title">PAYMENT RECEIPT</h1>
            <p class="receipt-subtitle">Official Payment Confirmation</p>
        </div>
        
        <div class="receipt-info">
            <div class="info-section">
                <h3>Student Information</h3>
                <div class="info-row">
                    <span class="info-label">Student ID:</span>
                    <span class="info-value"><?php echo $student->student_code; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Student Name:</span>
                    <span class="info-value"><?php echo $student->student_name; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo $student->email; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Course:</span>
                    <span class="info-value"><?php echo $student->course_name; ?></span>
                </div>
            </div>
            
            <div class="info-section">
                <h3>Receipt Details</h3>
                <div class="info-row">
                    <span class="info-label">Receipt No:</span>
                    <span class="info-value">RCP<?php echo date('Ymd') . $installment_id; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date Issued:</span>
                    <span class="info-value"><?php echo date('F j, Y'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Academic Year:</span>
                    <span class="info-value"><?php echo date('Y'); ?>-<?php echo date('Y') + 1; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value" style="color: #27ae60; font-weight: bold;">PAID</span>
                </div>
            </div>
        </div>
        
        <div class="payment-details">
            <h3>Payment Confirmation</h3>
            <div class="payment-amount">£<?php echo number_format($installment->paid, 2); ?></div>
            <p class="payment-description">
                Installment #<?php echo $installment->installment_no; ?> - <?php echo $student->course_name; ?>
            </p>
            <p><strong>Due Date:</strong> <?php echo date('F j, Y', strtotime($installment->due_date)); ?></p>
            <p><strong>Payment Date:</strong> <?php echo date('F j, Y'); ?></p>
        </div>
        
        <div class="receipt-info">
            <div class="info-section">
                <h3>Payment Breakdown</h3>
                <div class="info-row">
                    <span class="info-label">Installment Amount:</span>
                    <span class="info-value">£<?php echo number_format($installment->amount, 2); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Amount Paid:</span>
                    <span class="info-value">£<?php echo number_format($installment->paid, 2); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Remaining:</span>
                    <span class="info-value">£<?php echo number_format($installment->amount - $installment->paid, 2); ?></span>
                </div>
            </div>
            
            <div class="info-section">
                <h3>Course Summary</h3>
                <div class="info-row">
                    <span class="info-label">Total Course Fee:</span>
                    <span class="info-value">£<?php echo number_format($student->course_fee, 2); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Paid to Date:</span>
                    <span class="info-value">
                        £<?php 
                        $total_paid = $student->upfront_payment;
                        $all_installments = $wpdb->get_results($wpdb->prepare(
                            "SELECT paid FROM $installments_table WHERE student_id = %d", 
                            $student_id
                        ));
                        foreach ($all_installments as $inst) {
                            $total_paid += $inst->paid;
                        }
                        echo number_format($total_paid, 2); 
                        ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Balance Due:</span>
                    <span class="info-value">£<?php echo number_format($student->course_fee - $total_paid, 2); ?></span>
                </div>
            </div>
        </div>
        
        <div class="receipt-footer">
            <p><strong>Hiticx - Student Fee Management System</strong></p>
            <p>This is an official payment receipt. Please keep this for your records.</p>
            <p>Generated on: <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p>Receipt ID: RCP<?php echo date('Ymd') . $installment_id . '-' . $student->student_code; ?></p>
        </div>
    </div>
    
    <script>
        // Auto-print option
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === '1') {
            window.print();
        }
        
        // Add download as PDF functionality
        function downloadPDF() {
            window.print();
        }
    </script>
</body>
</html>