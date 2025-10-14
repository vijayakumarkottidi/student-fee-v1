<?php
/**
 * Set New Password - Hiticx
 */

require_once __DIR__ . '/portal-bootstrap.php';

hiticx_portal_bootstrap();

$error = '';
$success = '';

// Verify reset token
$token = sanitize_text_field($_GET['token'] ?? '');
$student_id = intval($_GET['student'] ?? 0);

if (empty($token) || $student_id == 0) {
    $error = 'Invalid reset link.';
} else {
    global $wpdb;
    $students_table = $wpdb->prefix . 'sfm_pro_students';
    
    $student = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $students_table WHERE id = %d AND password = %s",
        $student_id, $token
    ));
    
    if (!$student) {
        $error = 'Invalid or expired reset link.';
    }
}

// Handle password set
if (isset($_POST['set_password']) && $_POST['set_password'] == '1') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please enter both password fields.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Hash the new password
        $hashed_password = wp_hash_password($new_password);
        
        // Update password in database
        $updated = $wpdb->update(
            $students_table,
            [
                'password' => $hashed_password,
                'last_update' => current_time('mysql')
            ],
            ['id' => $student_id]
        );
        
        if ($updated) {
            $success = 'Password updated successfully! You can now login with your new password.';
        } else {
            $error = 'Failed to update password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Hiticx</title>
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
        
        .password-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        
        .password-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .password-header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .password-header p {
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
        
        .password-btn {
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
        
        .password-btn:hover {
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
    <div class="password-container">
        <div class="password-header">
            <h1>🔐 Set New Password</h1>
            <p>Create your new password</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
                <p><a href="portal-login.php" style="color: #0073aa; text-decoration: underline;">Click here to login</a></p>
            </div>
        <?php endif; ?>
        
        <?php if (!$success && !$error && $student): ?>
        <form method="post">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required 
                       placeholder="Enter new password (min. 6 characters)" minlength="6">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required 
                       placeholder="Confirm your new password" minlength="6">
            </div>
            
            <button type="submit" name="set_password" value="1" class="password-btn">
                Set New Password
            </button>
        </form>
        <?php endif; ?>
        
        <a href="portal-login.php" class="back-btn">← Back to Login</a>
        
        <div class="help-text">
            <p>Password must be at least 6 characters long.</p>
        </div>
    </div>
</body>
</html>