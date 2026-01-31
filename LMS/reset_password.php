<?php
session_start();
include '../config.php';

$error = '';
$success = '';
$valid_token = false;

// Check if token is provided and valid
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $token_hash = hash('sha256', $token);
    
    // Verify token
    if (isset($_SESSION['reset_token']) && 
        isset($_SESSION['reset_expiry']) && 
        isset($_SESSION['reset_email']) &&
        $_SESSION['reset_token'] === $token_hash) {
        
        // Check if token has expired
        if (strtotime($_SESSION['reset_expiry']) > time()) {
            $valid_token = true;
        } else {
            $error = 'Reset link has expired. Please request a new password reset.';
        }
    } else {
        $error = 'Invalid reset link. Please request a new password reset.';
    }
} else {
    $error = 'No reset token provided. Please use the link from your email.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Update password in database
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if (!$stmt) {
                $error = 'Database error: ' . $conn->error;
            } else {
                $stmt->bind_param("si", $hashed_password, $_SESSION['reset_user_id']);
                
                if ($stmt->execute()) {
                    // Clear reset session data
                    unset($_SESSION['reset_token']);
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_expiry']);
                    unset($_SESSION['reset_user_id']);
                    
                    $success = 'Password successfully reset! Redirecting to login...';
                    
                    // Redirect after 3 seconds
                    header("refresh:3;url=admin_login.php");
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - I-Acadsikatayo: Learning Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Background with blur effect */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect fill="%23f5f5dc" width="100" height="100"/></svg>');
            background-size: cover;
            background-position: center;
            filter: blur(8px);
            opacity: 0.3;
            z-index: -2;
        }

        /* Gradient overlay */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                rgba(245, 245, 220, 0.95) 0%, 
                rgba(255, 255, 255, 0.92) 35%,
                rgba(218, 165, 32, 0.15) 65%,
                rgba(46, 125, 50, 0.25) 100%);
            z-index: -1;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            padding: 1.5rem 0;
            box-shadow: 0 4px 20px rgba(46, 125, 50, 0.3);
            position: relative;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }

        .logo i {
            font-size: 3rem;
            color: #daa520;
        }

        .logo h1 {
            font-size: 1.8rem;
            margin-bottom: 0.3rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .logo p {
            font-size: 1rem;
            opacity: 0.95;
            font-weight: 500;
        }

        /* Back Button */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #2e7d32;
            text-decoration: none;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.2);
            margin-bottom: 2rem;
        }

        .back-link:hover {
            background: white;
            transform: translateX(-5px);
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.3);
        }

        .back-link i {
            font-size: 1.1rem;
        }

        /* Main Container */
        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 20px;
        }

        .form-wrapper {
            width: 100%;
            max-width: 500px;
        }

        /* Form Card */
        .form-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(46, 125, 50, 0.25);
            backdrop-filter: blur(10px);
            border: 3px solid rgba(218, 165, 32, 0.3);
            transition: all 0.3s ease;
        }

        .form-card:hover {
            box-shadow: 0 20px 60px rgba(46, 125, 50, 0.35);
            border-color: #daa520;
        }

        /* Form Header */
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(46, 125, 50, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .form-icon i {
            font-size: 3rem;
            color: white;
        }

        .form-header h2 {
            font-size: 2rem;
            color: #1b5e20;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: #666;
            font-size: 1rem;
            line-height: 1.6;
        }

        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.5s ease-in-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.error {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            animation: shake 0.5s ease-in-out;
        }

        .message.success {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #1b5e20;
            font-size: 1rem;
        }

        .form-group label i {
            margin-right: 0.5rem;
            color: #daa520;
        }

        .form-group input {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid rgba(46, 125, 50, 0.3);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus {
            outline: none;
            border-color: #2e7d32;
            box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.1);
            background: white;
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }

        .strength-weak {
            width: 33%;
            background: #dc3545;
        }

        .strength-medium {
            width: 66%;
            background: #ffc107;
        }

        .strength-strong {
            width: 100%;
            background: #28a745;
        }

        .password-strength-text {
            font-size: 0.85rem;
            margin-top: 0.25rem;
            font-weight: 500;
        }

        /* Password Requirements */
        .password-requirements {
            background: rgba(46, 125, 50, 0.05);
            border-left: 3px solid #2e7d32;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 0.5rem;
        }

        .password-requirements p {
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
            color: #666;
            font-weight: 600;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .password-requirements li {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.25rem;
        }

        /* Submit Button */
        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            margin-top: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(46, 125, 50, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            text-align: center;
            padding: 1.5rem 0;
            box-shadow: 0 -4px 20px rgba(46, 125, 50, 0.2);
            margin-top: auto;
        }

        .footer p {
            margin: 0;
            opacity: 0.95;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .logo h1 {
                font-size: 1.3rem;
            }

            .logo i {
                font-size: 2rem;
            }

            .form-card {
                padding: 2rem 1.5rem;
            }

            .form-header h2 {
                font-size: 1.5rem;
            }

            .form-icon {
                width: 80px;
                height: 80px;
            }

            .form-icon i {
                font-size: 2.5rem;
            }

            .main-container {
                padding: 2rem 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    <div>
                        <h1>I-Acadsikatayo: Learning Management System</h1>
                        <p>Metro Dagupan Colleges</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <div class="form-wrapper">
            <!-- Back Button -->
            <a href="admin_login.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Login
            </a>

            <!-- Form Card -->
            <div class="form-card">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h2>Reset Your Password</h2>
                    <p>Create a new secure password for your account</p>
                </div>

                <?php if ($error): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($valid_token && !$success): ?>
                    <form method="POST" action="" id="resetForm">
                        <div class="form-group">
                            <label for="new_password">
                                <i class="fas fa-key"></i> New Password
                            </label>
                            <input type="password" id="new_password" name="new_password" required
                                   placeholder="Enter your new password"
                                   minlength="8">
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="password-strength-text" id="strengthText"></div>
                            
                            <div class="password-requirements">
                                <p><i class="fas fa-info-circle"></i> Password Requirements:</p>
                                <ul>
                                    <li>At least 8 characters long</li>
                                    <li>Mix of uppercase and lowercase letters</li>
                                    <li>Include at least one number</li>
                                    <li>Include at least one special character</li>
                                </ul>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-check-double"></i> Confirm New Password
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password" required
                                   placeholder="Re-enter your new password"
                                   minlength="8">
                        </div>

                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Reset Password
                        </button>
                    </form>
                <?php elseif (!$valid_token): ?>
                    <div style="text-align: center; padding: 2rem 0;">
                        <a href="forgot_password.php" class="btn btn-primary">
                            <i class="fas fa-redo"></i> Request New Reset Link
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 I-Acadsikatayo: Learning Management System. All rights reserved.</p>
        </div>
    </footer>

    <script>
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('resetForm');

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            return strength;
        }

        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);
                
                strengthBar.className = 'password-strength-bar';
                
                if (password.length === 0) {
                    strengthBar.style.width = '0%';
                    strengthText.textContent = '';
                } else if (strength <= 2) {
                    strengthBar.classList.add('strength-weak');
                    strengthText.textContent = 'Weak password';
                    strengthText.style.color = '#dc3545';
                } else if (strength <= 4) {
                    strengthBar.classList.add('strength-medium');
                    strengthText.textContent = 'Medium password';
                    strengthText.style.color = '#ffc107';
                } else {
                    strengthBar.classList.add('strength-strong');
                    strengthText.textContent = 'Strong password';
                    strengthText.style.color = '#28a745';
                }
            });
        }

        // Form validation
        if (form) {
            form.addEventListener('submit', function(e) {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    confirmPasswordInput.focus();
                    return false;
                }
                
                if (newPassword.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long!');
                    newPasswordInput.focus();
                    return false;
                }
            });
        }
    </script>
</body>
</html>