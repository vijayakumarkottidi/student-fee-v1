<?php
/* ----------------------------
HELPER FUNCTIONS
---------------------------- */

/**
 * Format date from YYYY-MM-DD to DD-MM-YYYY
 */
function sfm_format_date($date) {
    if (empty($date) || $date == '0000-00-00') {
        return '';
    }
    return date('d-m-Y', strtotime($date));
}

/**
 * Format date from DD-MM-YYYY to YYYY-MM-DD for database
 */
function sfm_format_date_for_db($date) {
    if (empty($date)) {
        return '';
    }
    $parts = explode('-', $date);
    if (count($parts) === 3) {
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return $date;
}

/**
 * Generate student code
 */
function sfm_generate_student_code($student_id) {
    return 'SFM' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
}

/**
 * Validate email address
 */
function sfm_validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitize monetary value
 */
function sfm_sanitize_amount($amount) {
    return floatval($amount);
}

/**
 * Check if student email already exists
 */
function sfm_email_exists($email, $exclude_student_id = 0) {
    global $wpdb;
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    
    $query = "SELECT COUNT(*) FROM $students_table WHERE email = %s";
    $params = [$email];
    
    if ($exclude_student_id > 0) {
        $query .= " AND id != %d";
        $params[] = $exclude_student_id;
    }
    
    return $wpdb->get_var($wpdb->prepare($query, $params)) > 0;
}
?>
