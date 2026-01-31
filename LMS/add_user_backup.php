<?php
session_start();
include 'config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (!$name) {
        $errors[] = "Name is required.";
    }
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (!$password || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if (!in_array($role, ['admin', 'faculty', 'student'])) {
        $errors[] = "Invalid role selected.";
    }

    if (empty($errors)) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            $errors[] = "Database connection failed: " . $conn->connect_error;
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "Email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
                if ($insert_stmt->execute()) {
                    $success = "User added successfully.";
                } else {
                    $errors[] = "Failed to add user: " . $conn->error;
                }
                $insert_stmt->close();
            }
            $stmt->close();
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Add New User - Admin Panel</title>
    <link rel="stylesheet" href="admin_dashboard.css" />
</head>
<body>
    <div class="dashboard">
        <main class="main-content" style="padding: 2rem;">
            <h1>Add New User</h1>
            <?php if ($errors): ?>
                <div style="color: red; margin-bottom: 1rem;">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif ($success): ?>
                <div style="color: green; margin-bottom: 1rem;"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <form method="POST" action="add_user.php" style="max-width: 400px;">
                <label for="name">Name:</label><br />
                <input type="text" id="name" name="name" required /><br /><br />

                <label for="email">Email:</label><br />
                <input type="email" id="email" name="email" required /><br /><br />

                <label for="password">Password:</label><br />
                <input type="password" id="password" name="password" required /><br /><br />

                <label for="role">Role:</label><br />
                <select id="role" name="role" required>
                    <option value="">Select role</option>
                    <option value="admin">Admin</option>
                    <option value="faculty">Faculty</option>
                    <option value="student">Student</option>
                </select><br /><br />

                <button type="submit">Add User</button>
            </form>
            <br />
            <a href="admin_dashboard.php">Back to Dashboard</a>
        </main>
    </div>
</body>
</html>
