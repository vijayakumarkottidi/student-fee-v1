<?php
/**
 * Password Reset - Hiticx
 */

require_once __DIR__ . '/portal-bootstrap.php';

hiticx_portal_bootstrap();

$error = '';
$success = '';

// Handle password reset request
if (isset($_POST['request_reset']) && $_POST['request_reset'] == '1') {
    $student_code = sanitize_text_field($_POST['student_code'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    
    if (empty($student_code) || empty($email)) {
        $error = 'Please enter both Student ID and Email';
    } else {
        global $wpdb;
        $students_table = $wpdb->prefix . 'sfm_pro_students';
        
        $student = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $students_table WHERE student_code = %s AND email = %s",
            $student_code, $email
        ));
        
        if ($student) {
            // Generate reset token
            $reset_token = wp_generate_password(32, false);
            $reset_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token in database
            $wpdb->update(
                $students_table,
                [
                    'password' => $reset_token, // Temporarily store reset token
                    'last_update' => current_time('mysql')
                ],
                ['id' => $student->id]
            );
            
            // Send reset email
            $reset_link = "https://hiticx.co.uk/wp-content/plugins/student-fee-manager-pro/student-portal/set-password.php?token=" . $reset_token . "&student=" . $student->id;
            
            $subject = 'Password Reset - Hiticx Student Portal';
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .button { background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>Password Reset Request</h2>
                    <p>Hello {$student->student_name},</p>
                    <p>You requested to reset your password for the Hiticx Student Portal.</p>
                    <p>Click the button below to set a new password:</p>
                    <p><a href='{$reset_link}' class='button'>Set New Password</a></p>
                    <p><small>This link will expire in 1 hour. If you didn't request this reset, please ignore this email.</small></p>
                    <p>Best regards,<br>Hiticx Administration</p>
                </div>
            </body>
            </html>
            ";
            
            // Set HTML content type
            add_filter('wp_mail_content_type', function() { return 'text/html'; });
            
            $email_sent = wp_mail($email, $subject, $message);
            
            // Reset content type
            remove_filter('wp_mail_content_type', 'set_html_content_type');
            
            if ($email_sent) {
                $success = 'Password reset link has been sent to your email!';
            } else {
                $error = 'Failed to send reset email. Please try again.';
            }
        } else {
            $error = 'No student found with this Student ID and Email combination.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Hiticx</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reset-header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .reset-header p {
            color: #666;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            border-color: #0073aa;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
        }
        
        .reset-btn {
            width: 100%;
            padding: 15px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .reset-btn:hover {
            background: #005a87;
        }
        
        .back-btn {
            width: 100%;
            padding: 12px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 10px;
        }
        
        .back-btn:hover {
            background: #545b62;
        }
        
        .error-message {
            background: #ffe6e6;
            color: #d63031;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #d63031;
        }
        
        .success-message {
            background: #e6f4ea;
            color: #1b6b2b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #1b6b2b;
        }
        
        .help-text {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <h1>🔐 Reset Password</h1>
            <p>Enter your details to receive a reset link</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="student_code">Student ID</label>
                <input type="text" id="student_code" name="student_code" required 
                       placeholder="Enter your Student ID">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       placeholder="Enter your registered email">
            </div>
            
            <button type="submit" name="request_reset" value="1" class="reset-btn">
                Send Reset Link
            </button>
        </form>
        
        <a href="portal-login.php" class="back-btn">← Back to Login</a>
        
        <div class="help-text">
            <p>You will receive a reset link via email.</p>
        </div>
    </div>
</body>
</html>