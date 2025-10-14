<?php
/*
Plugin Name: Student Fee Manager PRO
Description: Professional Student Fee Management System with customizable installments and email notifications.
Version: 1.0
Author: Your Name
Text Domain: sfm-pro
*/

// Security: Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin directories
define('SFM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SFM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SFM_PLUGIN_VERSION', '1.0');

// Include required files
require_once SFM_PLUGIN_DIR . 'includes/database.php';
require_once SFM_PLUGIN_DIR . 'includes/settings.php';
require_once SFM_PLUGIN_DIR . 'includes/email-system.php';
require_once SFM_PLUGIN_DIR . 'includes/reports.php';
require_once SFM_PLUGIN_DIR . 'includes/helpers.php';
require_once SFM_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once SFM_PLUGIN_DIR . 'includes/admin-pages.php';

// Activation and security checks
register_activation_hook(__FILE__, 'sfm_pro_activation_checks');
function sfm_pro_activation_checks() {
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Student Fee Manager PRO requires PHP 7.4 or higher. Please upgrade your PHP version.');
    }
    
    // Check WordPress version
    if (version_compare(get_bloginfo('version'), '5.6', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Student Fee Manager PRO requires WordPress 5.6 or higher. Please upgrade WordPress.');
    }
    
    // Create tables
    sfm_pro_create_tables();
    
    // Setup passwords for existing students
    sfm_pro_setup_passwords();
}

// Enhanced error logging
function sfm_pro_log_error($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SFM PRO ERROR: ' . $message);
        if ($data) {
            error_log('SFM PRO DATA: ' . print_r($data, true));
        }
    }
}

// Handle password setup for existing students - ENHANCED
function sfm_pro_setup_passwords() {
    global $wpdb;
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    
    // Check if password column exists, if not, add it
    $columns = $wpdb->get_col("DESC $students_table", 0);
    
    if (!in_array('password', $columns)) {
        $wpdb->query("ALTER TABLE $students_table ADD COLUMN password VARCHAR(255) DEFAULT NULL AFTER email");
        sfm_pro_log_error('Added password column to students table');
    }
}

// Safe uninstall - preserve all data
register_uninstall_hook(__FILE__, 'sfm_pro_safe_uninstall');
function sfm_pro_safe_uninstall() {
    // DO NOTHING - this prevents WordPress from deleting our database tables
    // All data will remain in the database tables
    sfm_pro_log_error('Student Fee Manager PRO: Plugin uninstalled safely - data preserved in database');
}

// Check and restore data on activation if tables exist
function sfm_pro_check_existing_data() {
    global $wpdb;
    
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    $installments_table = $wpdb->prefix . 'sfm_pro_installments';
    $settings_table = $wpdb->prefix . 'sfm_pro_settings';
    
    // Check if our tables already exist (from previous installation)
    $students_exists = $wpdb->get_var("SHOW TABLES LIKE '$students_table'") === $students_table;
    $installments_exists = $wpdb->get_var("SHOW TABLES LIKE '$installments_table'") === $installments_table;
    $settings_exists = $wpdb->get_var("SHOW TABLES LIKE '$settings_table'") === $settings_table;
    
    // If tables exist, we don't need to create them - data is preserved
    if ($students_exists && $installments_exists && $settings_exists) {
        sfm_pro_log_error('Existing tables found - data preserved');
        return; // Tables already exist with data
    }
    
    // If tables don't exist, create them (fresh installation)
    sfm_pro_create_tables();
}

// Initialize the plugin
class StudentFeeManagerPRO {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        // Add direct report download handler
        add_action('admin_post_sfm_download_report', array($this, 'handle_direct_report_download'));
        add_action('admin_post_nopriv_sfm_download_report', array($this, 'handle_no_permission'));
    }
    
    public function init() {
        // Register activation hook
        register_activation_hook(__FILE__, 'sfm_pro_check_existing_data');
        
        // Add admin menu
        add_action('admin_menu', 'sfm_pro_add_menu');
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Report download handler
        add_action('init', array($this, 'handle_report_download'));
        
        // Handle logo upload
        add_action('admin_post_sfm_pro_upload_logo', array($this, 'handle_logo_upload'));
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'sfm-pro') !== false) {
            wp_enqueue_style('sfm-admin-styles', SFM_PLUGIN_URL . 'assets/admin-styles.css', array(), SFM_PLUGIN_VERSION);
            wp_enqueue_script('sfm-admin-scripts', SFM_PLUGIN_URL . 'assets/admin-scripts.js', array('jquery'), SFM_PLUGIN_VERSION, true);
            
            // Localize script for AJAX
            wp_localize_script('sfm-admin-scripts', 'sfm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sfm_pro_nonce')
            ));
        }
    }
    
    public function handle_report_download() {
        if (isset($_GET['sfm_report_download']) && $_GET['sfm_report_download'] == '1') {
            sfm_pro_download_report();
        }
    }
    
    public function handle_direct_report_download() {
        // Verify capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        sfm_pro_download_report();
    }
    
    public function handle_no_permission() {
        wp_die('You do not have permission to access this page.');
    }
    
    public function handle_logo_upload() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'sfm_pro_upload_logo')) {
            wp_die('Security verification failed');
        }
        
        if (!empty($_FILES['company_logo']['name'])) {
            $upload = wp_handle_upload($_FILES['company_logo'], array(
                'test_form' => false,
                'mimes' => array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'png' => 'image/png',
                    'svg' => 'image/svg+xml'
                )
            ));
            
            if (isset($upload['url']) && !isset($upload['error'])) {
                sfm_pro_update_setting('company_logo_url', $upload['url']);
                wp_redirect(admin_url('admin.php?page=sfm-pro-settings&logo_uploaded=1'));
                exit;
            } else {
                $error_message = isset($upload['error']) ? $upload['error'] : 'Unknown error';
                sfm_pro_log_error('Logo upload failed: ' . $error_message);
                wp_redirect(admin_url('admin.php?page=sfm-pro-settings&logo_error=1'));
                exit;
            }
        }
        
        wp_redirect(admin_url('admin.php?page=sfm-pro-settings'));
        exit;
    }
}

new StudentFeeManagerPRO();

/**
 * Get company logo URL
 */
function sfm_pro_get_company_logo() {
    $logo_url = sfm_pro_get_setting('company_logo_url');
    if (!$logo_url) {
        // Default to website logo or placeholder
        $logo_url = get_theme_mod('custom_logo') ? wp_get_attachment_url(get_theme_mod('custom_logo')) : '';
    }
    return $logo_url;
}
?>