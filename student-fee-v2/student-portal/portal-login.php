<?php
/**
 * Student Portal Login - Hiticx
 */

require_once __DIR__ . '/portal-bootstrap.php';

hiticx_portal_bootstrap();

$error = '';

// Handle login
if (isset($_POST['login']) && $_POST['login'] == '1') {
    $student_code = sanitize_text_field($_POST['student_code'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($student_code) || empty($password)) {
        $error = 'Please enter both Student ID and Password';
    } else {
        global $wpdb;
        $students_table = $wpdb->prefix . 'sfm_pro_students';
        
        $student = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $students_table WHERE student_code = %s",
            $student_code
        ));
        
        if ($student) {
            // Check if student has set a password
            if (empty($student->password)) {
                // First time login - use email as password
                if ($password === $student->email) {
                    $_SESSION['sfm_student_id'] = $student->id;
                    $_SESSION['sfm_student_code'] = $student->student_code;
                    $_SESSION['sfm_student_name'] = $student->student_name;
                    $_SESSION['sfm_last_login'] = time();
                    
                    header('Location: student-dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid password. Use your registered email as password for first login.';
                }
            } else {
                // Check password
                if (wp_check_password($password, $student->password)) {
                    $_SESSION['sfm_student_id'] = $student->id;
                    $_SESSION['sfm_student_code'] = $student->student_code;
                    $_SESSION['sfm_student_name'] = $student->student_name;
                    $_SESSION['sfm_last_login'] = time();
                    
                    header('Location: student-dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid password.';
                }
            }
        } else {
            $error = 'Invalid Student ID.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Login - Hiticx</title>
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
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .login-header p {
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
        
        .login-btn {
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
        
        .login-btn:hover {
            background: #005a87;
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
        
        .help-text {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 14px;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 15px;
        }
        
        .forgot-password a {
            color: #0073aa;
            text-decoration: none;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .institution-info {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🎓 Student Portal</h1>
            <p>Hiticx - Fee Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="student_code">Student ID</label>
                <input type="text" id="student_code" name="student_code" required 
                       placeholder="Enter your Student ID (e.g., SFM0001)">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Enter your password">
            </div>
            
            <button type="submit" name="login" value="1" class="login-btn">
                Login to Portal
            </button>
        </form>
        
        <div class="forgot-password">
            <a href="password-reset.php">Forgot Password?</a>
        </div>
        
        <div class="help-text">
            <p>First time? Use your registered email as password.</p>
            <p>Need help? Contact your institution's administrator.</p>
        </div>
        
        <div class="institution-info">
            <p><strong>Hiticx Student Portal</strong><br>
            Secure access to your fee information</p>
        </div>
    </div>
</body>
</html>