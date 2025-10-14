<?php
/* ----------------------------
ADMIN MENU & PAGES
---------------------------- */

/**
 * Add admin menu
 */
function sfm_pro_add_menu(){
    add_menu_page(
        'Student Dashboard',
        'Student Fees PRO',
        'manage_options',
        'sfm-pro-dashboard',
        'sfm_pro_dashboard_page',
        'dashicons-welcome-learn-more',
        6
    );
    
    add_submenu_page(
        'sfm-pro-dashboard',
        'Add Student',
        'Add Student',
        'manage_options',
        'sfm-pro-add',
        'sfm_pro_add_student_page'
    );
    
    add_submenu_page(
        'sfm-pro-dashboard',
        'Settings',
        'Settings',
        'manage_options',
        'sfm-pro-settings',
        'sfm_pro_settings_page'
    );
}

/**
 * Dashboard Page
 */
function sfm_pro_dashboard_page(){
    if (!current_user_can('manage_options')) wp_die('Access denied');
    
    echo '<div class="wrap">';
    echo '<h1>📊 Student Dashboard</h1>';
    
    echo '<div style="margin-bottom:20px;">';
    echo '<a href="' . admin_url('admin.php?page=sfm-pro-add') . '" class="button button-primary">➕ Add New Student</a>';
    echo '<a href="' . admin_url('admin.php?page=sfm-pro-settings') . '" class="button" style="margin-left:10px;">⚙️ Settings</a>';
    echo '</div>';
    
    // Reports download section
    sfm_pro_render_reports_section();
    
    echo '<div id="sfm-metrics" style="margin-bottom:20px;"></div>';
    
    echo '<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;margin-bottom:20px;">';
    echo '<h3 style="margin-top:0;">🔍 Search Students</h3>';
    echo '<div style="display:flex;gap:10px;align-items:center;">';
    echo '<input type="text" id="sfm-search" placeholder="Search by Student ID, Name, or Email" style="padding:8px;width:300px;border:1px solid #ddd;border-radius:4px;">';
    echo '<button id="sfm-search-btn" class="button button-secondary">Search</button>';
    echo '<button id="sfm-clear-search" class="button">Clear</button>';
    echo '</div>';
    echo '</div>';
    
    echo '<div id="sfm-table"></div>';
    echo '</div>';
}

/**
 * Add Student Page
 */
function sfm_pro_add_student_page(){
    if (!current_user_can('manage_options')) wp_die('Access denied');
    
    echo '<div class="wrap">';
    echo '<h1>➕ Add New Student</h1>';
    
    sfm_pro_handle_add();
    sfm_pro_render_add_form();
    
    echo '</div>';
}

/**
 * Settings Page
 */
function sfm_pro_settings_page() {
    if (!current_user_can('manage_options')) wp_die('Access denied');
    
    // Handle success/error messages
    if (isset($_GET['logo_uploaded'])) {
        echo '<div class="notice notice-success"><p>✅ Logo uploaded successfully!</p></div>';
    }
    if (isset($_GET['logo_error'])) {
        echo '<div class="notice notice-error"><p>❌ Failed to upload logo. Please try again.</p></div>';
    }
    
    $current_logo = sfm_pro_get_company_logo();
    ?>
    <div class="wrap">
        <h1>⚙️ Plugin Settings</h1>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
            
            <!-- Logo Settings -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                <h2 style="margin-top: 0;">🏢 Company Logo</h2>
                <p>Upload your company logo to be used in email communications.</p>
                
                <?php if ($current_logo): ?>
                <div style="text-align: center; margin: 20px 0;">
                    <h3>Current Logo:</h3>
                    <img src="<?php echo esc_url($current_logo); ?>" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="sfm_pro_upload_logo">
                    <?php wp_nonce_field('sfm_pro_upload_logo'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="company_logo">Upload New Logo</label></th>
                            <td>
                                <input type="file" id="company_logo" name="company_logo" accept="image/jpeg,image/png,image/gif,image/svg+xml">
                                <p class="description">Recommended: PNG or JPG, max 500x200 pixels</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">📤 Upload Logo</button>
                        <?php if ($current_logo): ?>
                        <button type="button" onclick="sfmProRemoveLogo()" class="button button-secondary">🗑️ Remove Logo</button>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
            
            <!-- Email Settings -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                <h2 style="margin-top: 0;">📧 Email Settings</h2>
                <p>Configure email preferences and test the email system.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Email System Status</th>
                        <td>
                            <span style="color: #46b450; font-weight: bold;">✅ Active</span>
                            <p class="description">Emails are automatically sent for new students and payments</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Test Email</th>
                        <td>
                            <button type="button" onclick="sfmProTestEmail()" class="button">🧪 Send Test Email</button>
                            <p class="description">Send a test email to verify the system is working</p>
                        </td>
                    </tr>
                </table>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 20px;">
                    <h3 style="margin-top: 0;">📋 Automated Emails</h3>
                    <ul style="margin-bottom: 0;">
                        <li><strong>Welcome Email:</strong> Sent when a new student is added</li>
                        <li><strong>Payment Confirmation:</strong> Sent when a payment is recorded</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- System Information -->
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px; margin-top: 20px;">
            <h2 style="margin-top: 0;">🔧 System Information</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div>
                    <h4>Database Status</h4>
                    <ul>
                        <li>✅ Tables: Installed</li>
                        <li>✅ Settings: Configured</li>
                        <li>✅ Email System: Ready</li>
                    </ul>
                </div>
                <div>
                    <h4>Plugin Info</h4>
                    <ul>
                        <li><strong>Version:</strong> 1.0</li>
                        <li><strong>Students:</strong> <span id="sfm-student-count">Loading...</span></li>
                        <li><strong>Last Updated:</strong> <?php echo date('Y-m-d'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function sfmProRemoveLogo() {
        if (confirm('Are you sure you want to remove the company logo?')) {
            jQuery.post(ajaxurl, {
                action: 'sfm_pro_remove_logo',
                nonce: '<?php echo wp_create_nonce('sfm_pro_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to remove logo: ' + response.data);
                }
            });
        }
    }
    
    function sfmProTestEmail() {
        if (confirm('This will send a test email to your admin email address. Continue?')) {
            var button = document.querySelector('button[onclick="sfmProTestEmail()"]');
            button.disabled = true;
            button.innerHTML = 'Sending Test...';
            
            jQuery.post(ajaxurl, {
                action: 'sfm_pro_test_email_system',
                nonce: '<?php echo wp_create_nonce('sfm_pro_test_email'); ?>'
            }, function(response) {
                button.disabled = false;
                button.innerHTML = '🧪 Send Test Email';
                
                if (response.success) {
                    alert('✅ Test email sent successfully! Check your inbox.');
                } else {
                    alert('❌ Test email failed: ' + response.data);
                }
            }).fail(function() {
                button.disabled = false;
                button.innerHTML = '🧪 Send Test Email';
                alert('❌ Request failed. Please try again.');
            });
        }
    }
    
    // Load student count
    jQuery(document).ready(function($) {
        $.post(ajaxurl, {
            action: 'sfm_pro_get_student_count',
            nonce: '<?php echo wp_create_nonce('sfm_pro_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#sfm-student-count').text(response.data.count);
            }
        });
    });
    </script>
    <?php
}

/**
 * Handle student addition
 */
function sfm_pro_handle_add(){
    if (!isset($_POST['sfm_pro_add_nonce'])) return;
    if (!wp_verify_nonce($_POST['sfm_pro_add_nonce'],'sfm_pro_add')) return;
    if (!current_user_can('manage_options')) return;

    global $wpdb;
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    $installments_table = $wpdb->prefix . 'sfm_pro_installments';

    $name = sanitize_text_field($_POST['student_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $course = sanitize_text_field($_POST['course_name'] ?? '');
    $fee = floatval($_POST['course_fee'] ?? 0);
    $upfront = floatval($_POST['upfront_payment'] ?? 0);
    $installment_period = intval($_POST['installment_period'] ?? 5);
    $join_date = sfm_format_date_for_db($_POST['join_date'] ?? '');
    $next_payment_date = sfm_format_date_for_db($_POST['next_payment_date'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($course) || $fee <= 0 || empty($join_date) || empty($next_payment_date)) {
        echo '<div class="notice notice-error"><p>All fields are required.</p></div>'; 
        return;
    }
    if (!is_email($email)) { 
        echo '<div class="notice notice-error"><p>Invalid email address.</p></div>'; 
        return; 
    }
    if ($upfront > $fee) { 
        echo '<div class="notice notice-error"><p>Upfront payment cannot exceed total fee.</p></div>'; 
        return; 
    }
    if ($installment_period < 1 || $installment_period > 24) {
        echo '<div class="notice notice-error"><p>Installment period must be between 1 and 24 months.</p></div>';
        return;
    }

    // Check for duplicate email
    $existing_email = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $students_table WHERE email = %s",
        $email
    ));
    if ($existing_email > 0) {
        echo '<div class="notice notice-error"><p>Email already exists in the system.</p></div>';
        return;
    }

    $remaining = $fee - $upfront;
    $res = $wpdb->insert($students_table, [
        'student_name' => $name, 
        'email' => $email, 
        'course_name' => $course,
        'course_fee' => $fee,
        'upfront_payment' => $upfront,
        'remaining' => $remaining,
        'installment_period' => $installment_period,
        'join_date' => $join_date,
        'next_payment_date' => $next_payment_date,
        'status' => ($remaining <= 0 ? 'Cleared' : 'Ongoing')
    ]);
    
    if (!$res) { 
        echo '<div class="notice notice-error"><p>Failed to add student to database.</p></div>'; 
        return; 
    }

    $student_id = $wpdb->insert_id;
    $code = 'SFM' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
    $wpdb->update($students_table, ['student_code' => $code], ['id' => $student_id]);

    // Create installments based on installment period
    if ($remaining > 0 && $installment_period > 0) {
        $installment_amount = round($remaining / $installment_period, 2);
        $total_allocated = 0;
        
        for ($i = 1; $i <= $installment_period; $i++) {
            $amount = ($i == $installment_period) ? $remaining - $total_allocated : $installment_amount;
            // Calculate due date: next_payment_date + (i-1) months
            $due_date = date('Y-m-d', strtotime("+" . ($i - 1) . " months", strtotime($next_payment_date)));
            $wpdb->insert($installments_table, [
                'student_id' => $student_id,
                'installment_no' => $i,
                'amount' => $amount,
                'due_date' => $due_date,
                'paid' => 0,
                'status' => 'Pending'
            ]);
            $total_allocated += $amount;
        }
    }
    
    // Send welcome email
    $email_sent = sfm_pro_send_welcome_email($student_id);
    
    echo '<div class="notice notice-success"><p>✅ Student added successfully with ID: ' . esc_html($code) . '</p>';
    if ($email_sent) {
        echo '<p>📧 Welcome email sent to student.</p>';
    } else {
        echo '<p>⚠️ Welcome email could not be sent.</p>';
    }
    echo '</div>';
}

/**
 * Render add student form
 */
function sfm_pro_render_add_form(){
    $default_join_date = date('d-m-Y');
    $default_next_payment = date('d-m-Y', strtotime('+1 month'));
    ?>
    <div style="max-width:600px;">
        <div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;">
            <form method="post">
                <?php wp_nonce_field('sfm_pro_add','sfm_pro_add_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="student_name">Student Name *</label></th>
                        <td><input type="text" id="student_name" name="student_name" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="email">Email Address *</label></th>
                        <td><input type="email" id="email" name="email" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="course_name">Course Name *</label></th>
                        <td><input type="text" id="course_name" name="course_name" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="course_fee">Total Course Fee (£) *</label></th>
                        <td><input type="number" id="course_fee" name="course_fee" step="0.01" min="0" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="upfront_payment">Upfront Payment (£) *</label></th>
                        <td><input type="number" id="upfront_payment" name="upfront_payment" step="0.01" min="0" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="installment_period">Installment Period (Months) *</label></th>
                        <td>
                            <select id="installment_period" name="installment_period" required>
                                <?php for ($i = 1; $i <= 24; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($i, 5); ?>>
                                        <?php echo $i . ' month' . ($i > 1 ? 's' : ''); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="join_date">Join Date *</label></th>
                        <td><input type="text" id="join_date" name="join_date" required value="<?php echo $default_join_date; ?>" placeholder="dd-mm-yyyy"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="next_payment_date">Next Payment Start Date *</label></th>
                        <td><input type="text" id="next_payment_date" name="next_payment_date" required value="<?php echo $default_next_payment; ?>" placeholder="dd-mm-yyyy"></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Add Student</button>
                    <a href="<?php echo admin_url('admin.php?page=sfm-pro-dashboard'); ?>" class="button">View Dashboard</a>
                </p>
            </form>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        // Add date picker or validation for dd-mm-yyyy format
        $('#join_date, #next_payment_date').on('blur', function() {
            var date = $(this).val();
            var pattern = /^(\d{2})-(\d{2})-(\d{4})$/;
            if (date && !pattern.test(date)) {
                alert('Please enter date in dd-mm-yyyy format');
                $(this).focus();
            }
        });
    });
    </script>
    <?php
}
?>
