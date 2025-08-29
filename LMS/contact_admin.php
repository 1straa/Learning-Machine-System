<?php
session_start();
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $message = trim($_POST['message'] ?? '');

  if ($name && $email && $message) {
    // In production, use mail() or PHPMailer to send email to admin
    // mail('admin@example.com', 'LMS Contact', $message, "From: $email");
    $success = "✅ Your message has been sent. The admin will contact you soon.";
  } else {
    $error = "❌ Please fill in all fields.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Admin | LMS</title>
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
      <h1 class="login-title">Contact Admin</h1>
      <p class="login-subtitle">Send a message to the administrator</p>

      <form class="login-form" method="POST">
        <div class="form-group">
          <label>Name</label>
          <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea name="message" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="login-btn">Send Message</button>
      </form>

      <?php if ($success): ?>
        <div class="success" style="color:green; margin-top:10px;"><?= htmlspecialchars($success) ?></div>
      <?php elseif ($error): ?>
        <div class="error" style="color:red; margin-top:10px;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="login-footer">
        <p><a href="login.php">&larr; Back to Login</a></p>
      </div>
    </section>
  </main>
</body>
</html>