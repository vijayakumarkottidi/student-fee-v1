<?php
/* ----------------------------
Database tables (activation)
---------------------------- */
function sfm_pro_create_tables(){
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $students = $wpdb->prefix . 'sfm_pro_students';
    $inst = $wpdb->prefix . 'sfm_pro_installments';
    $settings = $wpdb->prefix . 'sfm_pro_settings';
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql1 = "CREATE TABLE IF NOT EXISTS $students (
        id mediumint NOT NULL AUTO_INCREMENT,
        student_code varchar(20) DEFAULT NULL,
        student_name varchar(200) NOT NULL,
        email varchar(255) NOT NULL,
        password varchar(255) DEFAULT NULL,
        course_name varchar(200) NOT NULL,
        course_fee double NOT NULL,
        upfront_payment double NOT NULL,
        remaining double NOT NULL,
        installment_period tinyint DEFAULT 5,
        join_date date NOT NULL,
        next_payment_date date NOT NULL,
        status varchar(50) DEFAULT 'Ongoing',
        last_update datetime DEFAULT CURRENT_TIMESTAMP,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY student_code (student_code),
        KEY email (email)
    ) $charset;";

    $sql2 = "CREATE TABLE IF NOT EXISTS $inst (
        id mediumint NOT NULL AUTO_INCREMENT,
        student_id mediumint NOT NULL,
        installment_no tinyint NOT NULL,
        amount double NOT NULL,
        due_date date NOT NULL,
        paid double DEFAULT 0,
        status varchar(50) DEFAULT 'Pending',
        PRIMARY KEY(id),
        KEY student_id (student_id),
        KEY due_date (due_date)
    ) $charset;";

    $sql3 = "CREATE TABLE IF NOT EXISTS $settings (
        id mediumint NOT NULL AUTO_INCREMENT,
        setting_key varchar(100) NOT NULL,
        setting_value text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY setting_key (setting_key)
    ) $charset;";

    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
}
?>