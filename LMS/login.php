<?php
session_start();
require_once __DIR__ . '/api/db.php';

if (isset($_SESSION['user_id'])) {
  if ($_SESSION['user_role'] === 'admin') header("Location: admin.php");
  elseif ($_SESSION['user_role'] === 'teacher') header("Location: teacher.php");
  elseif ($_SESSION['user_role'] === 'student') header("Location: student.php");
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  $stmt = $pdo->prepare("SELECT id, name, email, role, password_hash FROM users WHERE email = :email LIMIT 1");
  $stmt->execute([':email' => $email]);
  $user = $stmt->fetch();

  if ($user && password_verify($password, $user['password_hash'])) {
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id")->execute([':id' => $user['id']]);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];

    if ($user['role'] === 'admin') header("Location: admin.php");
    elseif ($user['role'] === 'teacher') header("Location: teacher.php");
    elseif ($user['role'] === 'student') header("Location: student.php");
    exit;
  } else {
    $error = "❌ Invalid email or password.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | LMS</title>
  <link rel="stylesheet" href="Login.css">
</head>
<body>
  <main class="login-bg">
    <section class="login-container">
      <div class="login-icon">
        <svg width="36" height="36" fill="none" viewBox="0 0 24 24">
          <rect x="2" y="7" width="20" height="10" rx="5" fill="#fff"/>
          <path d="M2 7l10 6 10-6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h1 class="login-title">Welcome Back</h1>
      <p class="login-subtitle">Login to your account</p>

      <form id="loginForm" class="login-form" method="POST">
        <div class="form-group">
          <label>Email</label>
          <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div style="position:relative;">
            <input type="password" id="password" name="password" required>
          </div>
        </div>
        <button type="submit" class="login-btn">Login</button>
      </form>

      <?php if ($error): ?>
        <div class="error" style="color:red; margin-top:10px;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="login-footer">
        <p>Don't have an account? <a href="contact_admin.php">Contact Admin</a></p>
      </div>
    </section>
  </main>
  <script src="login.js"></script>
</body>
</html>