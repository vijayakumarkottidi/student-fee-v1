<?php
/**
 * Student Dashboard - Hiticx
 * Redesigned layout that syncs with admin data
 */

// Simple error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Correct WordPress path for your server
$wp_load_path = '/home/hitiypom/public_html/wp-load.php';

if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die('
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h1>🚧 Configuration Error</h1>
        <p>WordPress not found at: ' . $wp_load_path . '</p>
        <p>Please contact the administrator.</p>
    </div>
    ');
}

// Start PHP session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load the portal bootstrap file
require_once __DIR__ . '/portal-bootstrap.php';

// Safely call bootstrap function
if (function_exists('hiticx_portal_bootstrap')) {
    hiticx_portal_bootstrap();
} else {
    error_log('hiticx_portal_bootstrap() not found. Check portal-bootstrap.php');
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
$student = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM $students_table WHERE id = %d",
        $student_id
    )
);

if (!$student) {
    die('Student not found.');
}

// Calculate total paid (upfront + all installment payments)
$total_paid = floatval($student->upfront_payment);
$installments = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $installments_table WHERE student_id = %d",
        $student_id
    )
);

$completed_installments = 0;
foreach ($installments as $installment) {
    $total_paid += floatval($installment->paid);
    if (floatval($installment->paid) >= floatval($installment->amount)) {
        $completed_installments++;
    }
}

$total_installments = count($installments);
$remaining = max(0, $student->course_fee - $total_paid);
$payment_progress = $student->course_fee > 0 ? min(100, round(($total_paid / $student->course_fee) * 100)) : 0;

$course_progress_value = null;
if (isset($student->course_progress) && $student->course_progress !== '') {
    $course_progress_value = max(0, min(100, (int) round($student->course_progress)));
}
$course_progress = $course_progress_value !== null ? $course_progress_value : 25;

// Find next due installment
$next_due = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM $installments_table
         WHERE student_id = %d AND paid < amount
         ORDER BY due_date ASC
         LIMIT 1",
        $student_id
    )
);

$next_due_amount = $next_due ? ($next_due->amount - $next_due->paid) : 0;
$next_due_date = $next_due && $next_due->due_date ? date('M j, Y', strtotime($next_due->due_date)) : 'No pending payments';

// Count overdue installments
$overdue_count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM $installments_table
         WHERE student_id = %d AND due_date < CURDATE() AND paid < amount",
        $student_id
    )
);

$join_date_formatted = !empty($student->join_date) ? date('M j, Y', strtotime($student->join_date)) : 'TBC';
$installment_period = (int) $student->installment_period;
$completion_base_date = !empty($student->join_date) ? $student->join_date : 'now';
$estimated_completion_date = $installment_period > 0
    ? date('F Y', strtotime($completion_base_date . ' +' . $installment_period . ' months'))
    : 'To be confirmed';
$estimated_completion_label = $estimated_completion_date === 'To be confirmed'
    ? $estimated_completion_date
    : 'Est. ' . $estimated_completion_date;

// Build student initials
$student_initials = '';
if (!empty($student->student_name)) {
    $name_parts = preg_split('/\s+/', trim($student->student_name));
    foreach ($name_parts as $part) {
        $first_char = function_exists('mb_substr') ? mb_substr($part, 0, 1) : substr($part, 0, 1);
        $student_initials .= strtoupper($first_char);
        if (strlen($student_initials) >= 2) {
            break;
        }
    }
}
$student_initials = $student_initials ?: 'H';
$status_label = !empty($student->status) ? ucwords($student->status) : 'Active';

// Define course timeline steps
$timeline_steps = [
    [
        'label' => 'Enrollment',
        'subtitle' => $join_date_formatted,
        'state' => 'completed',
    ],
    [
        'label' => 'Course Start',
        'subtitle' => !empty($student->join_date)
            ? date('M j, Y', strtotime($student->join_date . ' +1 month'))
            : 'Scheduled',
        'state' => $course_progress >= 10 ? 'completed' : 'upcoming',
    ],
    [
        'label' => 'In Progress',
        'subtitle' => $course_progress . '% complete',
        'state' => ($course_progress >= 10 && $course_progress < 100)
            ? 'current'
            : ($course_progress >= 100 ? 'completed' : 'upcoming'),
    ],
    [
        'label' => 'Completion',
        'subtitle' => $course_progress >= 100 ? 'Completed' : $estimated_completion_label,
        'state' => $course_progress >= 100 ? 'completed' : 'upcoming',
    ],
];

// Get recent payments
$recent_payments = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $installments_table
         WHERE student_id = %d AND paid > 0
         ORDER BY due_date DESC
         LIMIT 5",
        $student_id
    )
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard - Hiticx</title>
  <link rel="stylesheet" href="portal-styles.css">
  <link rel="stylesheet" href="dashboard-styles.css">
</head>
<body>
  <div class="dashboard-layout">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-logo">H</div>
        <div>
          <div class="brand-name">HITICX</div>
          <div class="brand-subtitle">Student Portal</div>
        </div>
      </div>
      <nav class="sidebar-nav">
        <a class="nav-item active" href="<?php echo esc_url('student-dashboard.php'); ?>">
          <i class="fa-solid fa-gauge-high"></i>
          Dashboard
        </a>
        <a class="nav-item disabled" href="#" aria-disabled="true">
          <i class="fa-solid fa-user"></i>
          Personal Profile
        </a>
        <a class="nav-item disabled" href="#" aria-disabled="true">
          <i class="fa-solid fa-book"></i>
          Course Details
        </a>
        <a class="nav-item" href="<?php echo esc_url('payment-schedule.php'); ?>">
          <i class="fa-solid fa-credit-card"></i>
          Payments
        </a>
        <a class="nav-item disabled" href="#" aria-disabled="true">
          <i class="fa-solid fa-certificate"></i>
          Certificate
        </a>
        <a class="nav-item" href="<?php echo esc_url('payment-history.php'); ?>">
          <i class="fa-solid fa-clock-rotate-left"></i>
          Payment History
        </a>
        <a class="nav-item" href="<?php echo esc_url('download-receipt.php'); ?>">
          <i class="fa-solid fa-download"></i>
          Receipts
        </a>
        <a class="nav-item disabled" href="#" aria-disabled="true">
          <i class="fa-solid fa-life-ring"></i>
          Help & Support
        </a>
      </nav>
      <div class="sidebar-footer">
        Need assistance? Contact support@hiticx.com
      </div>
    </aside>

    <main class="main-content">
      <header class="topbar">
        <div class="topbar-left">
          <span class="eyebrow">Candidate Dashboard</span>
          <h1>Welcome back, <?php echo esc_html($student->student_name); ?>!</h1>
          <p>Here's your learning journey overview.</p>
        </div>
        <div>
          <div class="user-badge">
            <div class="user-avatar"><?php echo esc_html($student_initials); ?></div>
            <div class="user-meta">
              <strong><?php echo esc_html($student->student_name); ?></strong>
              <span>Candidate ID: <?php echo esc_html($student->student_code); ?></span>
              <span>Course: <?php echo esc_html($student->course_name); ?></span>
            </div>
          </div>
          <a class="logout-link" href="<?php echo esc_url('portal-logout.php'); ?>">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
          </a>
        </div>
      </header>

      <?php if ((int) $overdue_count > 0) : ?>
      <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i>
        You have <?php echo (int) $overdue_count; ?> overdue payment<?php echo (int) $overdue_count === 1 ? '' : 's'; ?>. Please contact administration for assistance.
      </div>
      <?php endif; ?>

      <section class="stats-grid">
        <article class="card stat-card">
          <div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div>
          <div class="stat-label">Total Course Fee</div>
          <div class="stat-value">£<?php echo number_format((float) $student->course_fee, 2); ?></div>
        </article>
        <article class="card stat-card">
          <div class="stat-icon"><i class="fa-solid fa-coins"></i></div>
          <div class="stat-label">Total Paid</div>
          <div class="stat-value">£<?php echo number_format((float) $total_paid, 2); ?></div>
        </article>
        <article class="card stat-card">
          <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
          <div class="stat-label">Remaining Balance</div>
          <div class="stat-value">£<?php echo number_format((float) $remaining, 2); ?></div>
        </article>
        <article class="card stat-card">
          <div class="stat-icon"><i class="fa-solid fa-chart-simple"></i></div>
          <div class="stat-label">Course Progress</div>
          <div class="stat-value"><?php echo $course_progress; ?>%</div>
        </article>
      </section>

      <section class="progress-grid">
        <article class="card progress-card">
          <div class="tag"><i class="fa-solid fa-graduation-cap"></i> Your Course Progress</div>
          <h3>Status: <?php echo esc_html($status_label); ?></h3>
          <p>Joined on <?php echo esc_html($join_date_formatted); ?> • <?php echo esc_html($estimated_completion_label); ?></p>
          <div class="progress-meter">
            <div class="progress-fill" style="width: <?php echo $course_progress; ?>%"></div>
          </div>
          <p><?php echo $course_progress; ?>% complete</p>
          <a class="button" href="#" aria-disabled="true" onclick="return false;">
            Continue Learning
          </a>
        </article>
        <article class="card progress-card">
          <div class="tag"><i class="fa-solid fa-piggy-bank"></i> Payment Progress</div>
          <h3><?php echo $payment_progress; ?>% of fees paid</h3>
          <p>£<?php echo number_format((float) $total_paid, 2); ?> of £<?php echo number_format((float) $student->course_fee, 2); ?> paid</p>
          <div class="progress-meter">
            <div class="progress-fill success" style="width: <?php echo $payment_progress; ?>%"></div>
          </div>
          <p>Installment period: <?php echo esc_html($installment_period > 0 ? $installment_period . ' month' . ($installment_period === 1 ? '' : 's') : 'Flexible'); ?></p>
          <a class="button secondary" href="<?php echo esc_url('payment-schedule.php'); ?>">
            <i class="fa-solid fa-calendar-days"></i> View Payment Schedule
          </a>
        </article>
      </section>

      <section class="split-grid">
        <article class="card next-step-card">
          <h3>Next Step</h3>
          <?php if ($next_due) : ?>
          <div class="next-step-details">
            <span>Pay installment <strong>#<?php echo (int) $next_due->installment_no; ?></strong></span>
            <span>Amount due: <strong>£<?php echo number_format((float) $next_due_amount, 2); ?></strong></span>
            <span>Due on <strong><?php echo esc_html($next_due_date); ?></strong></span>
          </div>
          <a class="button" href="<?php echo esc_url(add_query_arg('installment_id', (int) $next_due->id, 'make-payment.php')); ?>">
            <i class="fa-solid fa-sterling-sign"></i> Pay Now
          </a>
          <?php else : ?>
          <div class="next-step-details">
            <span>All payments are up to date.</span>
            <span class="status-pill"><i class="fa-solid fa-circle-check"></i> Paid in full</span>
          </div>
          <?php endif; ?>
          <div class="quick-links">
            <a class="quick-link" href="<?php echo esc_url('payment-history.php'); ?>"><i class="fa-solid fa-clock"></i> Payment History</a>
            <a class="quick-link" href="<?php echo esc_url('download-receipt.php'); ?>"><i class="fa-solid fa-receipt"></i> Receipts</a>
          </div>
        </article>
        <article class="card timeline-card">
          <h3>Course Timeline</h3>
          <div class="timeline-badges">
            <span class="badge"><i class="fa-solid fa-list-check"></i> <?php echo $completed_installments; ?> of <?php echo $total_installments; ?> installments completed</span>
            <span class="badge"><i class="fa-solid fa-chart-line"></i> <?php echo $payment_progress; ?>% fees paid</span>
          </div>
          <div class="timeline-steps">
            <?php foreach ($timeline_steps as $step) : ?>
            <div class="timeline-step <?php echo esc_attr($step['state']); ?>">
              <strong><?php echo esc_html($step['label']); ?></strong>
              <span><?php echo esc_html($step['subtitle']); ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </article>
      </section>

      <section class="card recent-activity">
        <h3>Recent Activity</h3>
        <div class="table-responsive">
          <?php if ($recent_payments) : ?>
          <table class="table">
            <thead>
              <tr>
                <th>Installment</th>
                <th>Date</th>
                <th style="text-align: right;">Amount</th>
                <th style="text-align: center;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_payments as $payment) : ?>
              <tr>
                <td>#<?php echo (int) $payment->installment_no; ?></td>
                <td><?php echo esc_html(date('M j, Y', strtotime($payment->due_date))); ?></td>
                <td style="text-align: right;">£<?php echo number_format((float) $payment->paid, 2); ?></td>
                <td style="text-align: center;"><span class="status-pill"><i class="fa-solid fa-circle-check"></i> Paid</span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else : ?>
          <div class="empty-state">No payment history found yet.</div>
          <?php endif; ?>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
