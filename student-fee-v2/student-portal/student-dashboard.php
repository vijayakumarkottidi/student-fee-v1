<?php
/**
 * Student Dashboard - Hiticx
 * Fixed version that syncs with admin data
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

// Calculate total paid (upfront + all installment payments)
$total_paid = floatval($student->upfront_payment);
$installments = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $installments_table WHERE student_id = %d", 
    $student_id
));

foreach ($installments as $installment) {
    $total_paid += floatval($installment->paid);
}

$remaining = max(0, $student->course_fee - $total_paid);
$progress_percentage = $student->course_fee > 0 ? round(($total_paid / $student->course_fee) * 100) : 0;
$payment_progress = $student->course_fee > 0 ? round(($total_paid / $student->course_fee) * 100) : 0;

// Find next due installment
$next_due = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $installments_table 
     WHERE student_id = %d AND paid < amount 
     ORDER BY due_date ASC 
     LIMIT 1",
    $student_id
));

$next_due_amount = $next_due ? ($next_due->amount - $next_due->paid) : 0;
$next_due_date = $next_due ? date('M j, Y', strtotime($next_due->due_date)) : 'No pending payments';

// Count overdue installments
$overdue_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $installments_table 
     WHERE student_id = %d AND due_date < CURDATE() AND paid < amount",
    $student_id
));

// Get course progress (you might want to customize this based on your course structure)
$course_progress = 25; // Default - you can modify this based on your course completion logic
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard - Hiticx</title>
  <link rel="stylesheet" href="portal-styles.css">
  <link rel="stylesheet" href="dashboard-styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="container fade-in">
    <h1>📊 Welcome back, <?php echo esc_html($student->student_name); ?>!</h1>
    <p>Student ID: <?php echo esc_html($student->student_code); ?> | Course: <?php echo esc_html($student->course_name); ?></p>

    <?php if ($overdue_count > 0): ?>
    <div style="background: #fff2f2; color: #a12929; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #e74c3c;">
        <strong>⚠️ Attention:</strong> You have <?php echo $overdue_count; ?> overdue payment(s). Please contact administration.
    </div>
    <?php endif; ?>

    <div class="grid">
      <div class="card fade-in">
        <h2><i class="fas fa-wallet"></i> £<?php echo number_format($student->course_fee, 2); ?></h2>
        <p>Total Course Fee</p>
      </div>
      <div class="card fade-in">
        <h2><i class="fas fa-coins"></i> £<?php echo number_format($total_paid, 2); ?></h2>
        <p>Total Paid</p>
      </div>
      <div class="card fade-in">
        <h2><i class="fas fa-balance-scale"></i> £<?php echo number_format($remaining, 2); ?></h2>
        <p>Remaining Balance</p>
      </div>
      <div class="card fade-in">
        <h2><i class="fas fa-chart-line"></i> <?php echo $payment_progress; ?>%</h2>
        <p>Payment Progress</p>
        <div class="progress">
          <div class="progress-fill" style="width: <?php echo $payment_progress; ?>%"></div>
        </div>
      </div>
    </div>

    <div class="grid" style="margin-top: 30px;">
      <div class="card fade-in">
        <h3><i class="fas fa-book-open"></i> Your Course Progress</h3>
        <p>Status: <strong><?php echo esc_html($student->status); ?></strong></p>
        <p>Joined: <?php echo date('M j, Y', strtotime($student->join_date)); ?></p>
        <div class="progress">
          <div class="progress-fill" style="width: <?php echo $course_progress; ?>%"></div>
        </div>
        <p>Progress: <?php echo $course_progress; ?>% complete</p>
      </div>
      
      <div class="card fade-in">
        <h3><i class="fas fa-credit-card"></i> Payment Overview</h3>
        <p>£<?php echo number_format($total_paid, 2); ?> of £<?php echo number_format($student->course_fee, 2); ?> paid</p>
        <div class="progress">
          <div class="progress-fill" style="width: <?php echo $payment_progress; ?>%"></div>
        </div>
        <p>Installment Period: <?php echo $student->installment_period; ?> months</p>
        <a href="payment-schedule.php" class="button"><i class="fas fa-calendar-alt"></i> View Payment Schedule</a>
      </div>
      
      <div class="card fade-in">
        <h3><i class="fas fa-bell"></i> Next Payment</h3>
        <?php if ($next_due): ?>
        <p>Amount Due: <strong>£<?php echo number_format($next_due_amount, 2); ?></strong></p>
        <p>Due Date: <?php echo $next_due_date; ?></p>
        <p>Installment #<?php echo $next_due->installment_no; ?></p>
        <a href="make-payment.php?installment_id=<?php echo $next_due->id; ?>" class="button"><i class="fas fa-pound-sign"></i> Pay Now</a>
        <?php else: ?>
        <p>No pending payments</p>
        <p style="color: #27ae60;"><strong>All payments are complete! 🎉</strong></p>
        <?php endif; ?>
      </div>
      
      <div class="card fade-in">
        <h3><i class="fas fa-stream"></i> Quick Actions</h3>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <a href="payment-history.php" class="button" style="text-align: center;">
            <i class="fas fa-history"></i> Payment History
          </a>
          <a href="payment-schedule.php" class="button" style="text-align: center;">
            <i class="fas fa-file-invoice"></i> Payment Schedule
          </a>
          <a href="download-receipt.php" class="button" style="text-align: center;">
            <i class="fas fa-download"></i> Download Receipts
          </a>
          <a href="portal-logout.php" class="button" style="text-align: center; background: #dc3545;">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
        </div>
      </div>
    </div>

    <!-- Recent Activity Section -->
    <div style="margin-top: 30px;">
      <div class="card">
        <h3><i class="fas fa-clock"></i> Recent Activity</h3>
        <div style="max-height: 200px; overflow-y: auto;">
          <?php
          // Get recent payments
          $recent_payments = $wpdb->get_results($wpdb->prepare(
              "SELECT * FROM $installments_table 
               WHERE student_id = %d AND paid > 0 
               ORDER BY due_date DESC 
               LIMIT 5",
              $student_id
          ));
          
          if ($recent_payments): ?>
            <table style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr style="border-bottom: 1px solid #eee;">
                  <th style="padding: 10px; text-align: left;">Installment</th>
                  <th style="padding: 10px; text-align: left;">Date</th>
                  <th style="padding: 10px; text-align: right;">Amount</th>
                  <th style="padding: 10px; text-align: center;">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent_payments as $payment): ?>
                <tr style="border-bottom: 1px solid #f5f5f5;">
                  <td style="padding: 10px;">#<?php echo $payment->installment_no; ?></td>
                  <td style="padding: 10px;"><?php echo date('M j, Y', strtotime($payment->due_date)); ?></td>
                  <td style="padding: 10px; text-align: right;">£<?php echo number_format($payment->paid, 2); ?></td>
                  <td style="padding: 10px; text-align: center;">
                    <span style="background: #e6f4ea; color: #1b6b2b; padding: 4px 8px; border-radius: 12px; font-size: 12px;">
                      Paid
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p style="text-align: center; color: #666; padding: 20px;">
              No payment history found.
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>