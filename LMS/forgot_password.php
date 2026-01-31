<?php
session_start();
include 'config.php';

$message = '';
$message_type = '';

// Generate CAPTCHA if not exists
if (!isset($_SESSION['captcha_answer'])) {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['captcha_num1'] = $num1;
    $_SESSION['captcha_num2'] = $num2;
    $_SESSION['captcha_answer'] = $num1 + $num2;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $captcha_input = trim($_POST['captcha']);

    // Validate CAPTCHA first
    if (empty($captcha_input)) {
        $message = 'Please solve the math problem';
        $message_type = 'error';
        // Generate new CAPTCHA
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $_SESSION['captcha_num1'] = $num1;
        $_SESSION['captcha_num2'] = $num2;
        $_SESSION['captcha_answer'] = $num1 + $num2;
    } elseif (!isset($_SESSION['captcha_answer']) || intval($captcha_input) !== intval($_SESSION['captcha_answer'])) {
        $message = 'Incorrect answer. Please try again.';
        $message_type = 'error';
        // Generate new CAPTCHA
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $_SESSION['captcha_num1'] = $num1;
        $_SESSION['captcha_num2'] = $num2;
        $_SESSION['captcha_answer'] = $num1 + $num2;
    } elseif (empty($email)) {
        $message = 'Please enter your email address';
        $message_type = 'error';
    } else {
        // Check if email exists and is an admin
        try {
            $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ? AND role = 'admin'");
            
            if (!$stmt) {
                $message = 'Database error: ' . $conn->error;
                $message_type = 'error';
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $token);
                    $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
                    
                    // Store token in session (in production, store in database)
                    $_SESSION['reset_token'] = $token_hash;
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_expiry'] = $expiry;
                    $_SESSION['reset_user_id'] = $user['id'];
                    
                    // Clear CAPTCHA
                    unset($_SESSION['captcha_answer']);
                    unset($_SESSION['captcha_num1']);
                    unset($_SESSION['captcha_num2']);
                    
                    // In production, send email with reset link
                    // For now, redirect to reset password page with token
                    header('Location: reset_password.php?token=' . $token);
                    exit();
                } else {
                    $message = 'No administrator account found with this email address';
                    $message_type = 'error';
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - I-Acadsikatayo: Learning Management System</title>
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
            background: linear-gradient(135deg, #daa520 0%, #b8860b 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(218, 165, 32, 0.3);
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

        /* CAPTCHA Styling */
        .captcha-group {
            margin: 1.5rem 0;
        }

        .captcha-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #1b5e20;
            font-size: 1rem;
        }

        .captcha-label i {
            color: #daa520;
            margin-right: 0.5rem;
        }

        .captcha-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
            padding: 1.25rem;
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.3);
        }

        .captcha-question {
            flex: 1;
            font-size: 1.75rem;
            font-weight: bold;
            color: white;
            text-align: center;
            font-family: 'Courier New', monospace;
            letter-spacing: 4px;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.3);
        }

        .captcha-refresh {
            background: rgba(218, 165, 32, 0.9);
            color: white;
            border: 2px solid white;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .captcha-refresh:hover {
            background: #daa520;
        }

        .captcha-refresh.rotating {
            animation: rotate 0.5s ease-in-out;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .captcha-input {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #2e7d32;
            border-radius: 10px;
            font-size: 1.1rem;
            text-align: center;
            font-weight: bold;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        .captcha-input:focus {
            outline: none;
            border-color: #daa520;
            box-shadow: 0 0 0 4px rgba(218, 165, 32, 0.2);
            background: white;
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
            background: linear-gradient(135deg, #daa520 0%, #b8860b 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #b8860b 0%, #daa520 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(218, 165, 32, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        /* Info Box */
        .info-box {
            background: rgba(218, 165, 32, 0.1);
            border-left: 4px solid #daa520;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .info-box i {
            color: #daa520;
            margin-right: 0.5rem;
        }

        .info-box p {
            color: #666;
            font-size: 0.95rem;
            margin: 0;
            line-height: 1.5;
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

            .captcha-question {
                font-size: 1.4rem;
                letter-spacing: 2px;
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
                        <i class="fas fa-key"></i>
                    </div>
                    <h2>Forgot Password</h2>
                    <p>Enter your email address and we'll help you reset your password</p>
                </div>

                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type == 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    <p>
                        <i class="fas fa-info-circle"></i>
                        For security purposes, password reset is only available for administrator accounts.
                    </p>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Administrator Email Address
                        </label>
                        <input type="email" id="email" name="email" required
                               placeholder="Enter your registered email address"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <!-- CAPTCHA Verification -->
                    <div class="captcha-group">
                        <label class="captcha-label">
                            <i class="fas fa-calculator"></i> Verify You're Human
                        </label>
                        <div class="captcha-container">
                            <div class="captcha-question" id="captcha-question">
                                <?php echo $_SESSION['captcha_num1'] . ' + ' . $_SESSION['captcha_num2'] . ' = ?'; ?>
                            </div>
                            <button type="button" class="captcha-refresh" onclick="refreshCaptcha()" title="New Question">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <input type="number" name="captcha" class="captcha-input" id="captcha-input"
                               placeholder="Enter your answer" required min="0" max="20">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Request Password Reset
                    </button>
                </form>
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
        function refreshCaptcha() {
            const btn = document.querySelector('.captcha-refresh');
            const questionEl = document.getElementById('captcha-question');
            const inputEl = document.getElementById('captcha-input');
            
            // Add rotating animation
            btn.classList.add('rotating');
            
            // Clear input field
            inputEl.value = '';
            
            // Fetch new CAPTCHA
            fetch('refresh_captcha.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    questionEl.textContent = data.question;
                    // Remove animation after it completes
                    setTimeout(() => {
                        btn.classList.remove('rotating');
                    }, 500);
                })
                .catch(error => {
                    console.error('Error refreshing CAPTCHA:', error);
                    // Fallback: reload the page if AJAX fails
                    location.reload();
                });
        }
    </script>
</body>
</html>