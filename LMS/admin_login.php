<?php
session_start();
include 'config.php';

// If user is already logged in, redirect
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

$error = '';

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
    $password = $_POST['password'];
    $captcha_input = trim($_POST['captcha']);

    // Validate CAPTCHA first
    if (empty($captcha_input)) {
        $error = 'Please solve the math problem';
        // Generate new CAPTCHA
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $_SESSION['captcha_num1'] = $num1;
        $_SESSION['captcha_num2'] = $num2;
        $_SESSION['captcha_answer'] = $num1 + $num2;
    } elseif (!isset($_SESSION['captcha_answer']) || intval($captcha_input) !== intval($_SESSION['captcha_answer'])) {
        $error = 'Incorrect answer. Please try again.';
        // Generate new CAPTCHA
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $_SESSION['captcha_num1'] = $num1;
        $_SESSION['captcha_num2'] = $num2;
        $_SESSION['captcha_answer'] = $num1 + $num2;
    } elseif (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        // Check user in database
        try {
            $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ? AND role = 'admin'");

            if (!$stmt) {
                $error = 'Database error: ' . $conn->error;
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();

                    // Verify password (supports both hashed and plain text for testing)
                    $password_valid = false;

                    // Check if password is hashed
                    if (password_verify($password, $user['password'])) {
                        $password_valid = true;
                    }
                    // Fallback for plain text passwords (for testing only - remove in production)
                    elseif ($password === $user['password']) {
                        $password_valid = true;
                    }

                    if ($password_valid) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['email'] = $email;

                        // Clear CAPTCHA
                        unset($_SESSION['captcha_answer']);
                        unset($_SESSION['captcha_num1']);
                        unset($_SESSION['captcha_num2']);

                        // Redirect to admin dashboard
                        header('Location: admin_dashboard.php');
                        exit();
                    } else {
                        $error = 'Invalid password';
                    }
                } else {
                    $error = 'No administrator account found with this email';
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Login - I-Acadsikatayo: Learning Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin_login.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="LMS logo.jpg" alt="I-Acadsikatayo logo" />
                    <div>
                        <h1>I-Acadsikatayo: Learning Management System</h1>
                        <p>Metro Dagupan Colleges</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-form">
            <!-- Back Button -->
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to selection
            </a>

            <!-- Login Form -->
            <div class="form-card">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h2>Administrator Login</h2>
                    <p>Secure access to administrative controls</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="email" name="email" required
                               placeholder="Enter your email address"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" id="password" name="password" required
                               placeholder="Enter your password">
                    </div>

                    <!-- CAPTCHA Verification -->
                    <div class="captcha-group">
                        <label class="captcha-label">
                            <i class="fas fa-calculator"></i> Verify You're Human
                        </label>
                        <div class="captcha-container">
                            <div class="captcha-question" id="captcha-question">
                                <?php echo $_SESSION['captcha_num1'] . ' + ' . $_SESSION['captcha_num2'] . ' = '; ?>
                                <input type="number" name="captcha" class="captcha-input-inline" id="captcha-input"
                                       placeholder="" required min="0" max="20">
                            </div>
                            <button type="button" class="captcha-refresh" onclick="refreshCaptcha()" title="New Question">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Sign In as Administrator
                    </button>
                </form>

                <div class="form-footer">
                    <a href="forgot_password.php" class="forgot-link">
                        <i class="fas fa-key"></i> Forgot your password?
                    </a>
                </div>
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
                    questionEl.innerHTML = data.question;
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
