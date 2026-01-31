<?php
session_start();
include 'config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$success = '';

if ($user_id <= 0) {
    header('Location: admin_dashboard.php?tab=users');
    exit();
}

// Fetch user data
$stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: admin_dashboard.php?tab=users');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';

    if (!$name) {
        $errors[] = "Name is required.";
    }
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (!in_array($role, ['admin', 'faculty', 'student'])) {
        $errors[] = "Invalid role selected.";
    }

    if (empty($errors)) {
        // Check if email already exists for another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Email already exists.";
        } else {
            $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
            $update_stmt->bind_param("sssi", $name, $email, $role, $user_id);
            if ($update_stmt->execute()) {
                $success = "User updated successfully.";
                // Refresh user data
                $user['name'] = $name;
                $user['email'] = $email;
                $user['role'] = $role;
            } else {
                $errors[] = "Failed to update user: " . $conn->error;
            }
            $update_stmt->close();
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit User - Admin Dashboard</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <h3>Admin Panel</h3>
                        <p>I-Acadsikatayo: Learning Management System</p>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin_dashboard.php?tab=overview"><i class="fas fa-chart-bar"></i><span>Overview</span></a></li>
                    <li class="active"><a href="admin_dashboard.php?tab=users"><i class="fas fa-users"></i><span>User Management</span></a></li>
                    <li><a href="admin_dashboard.php?tab=courses"><i class="fas fa-book"></i><span>Course Management</span></a></li>
                    <li><a href="admin_dashboard.php?tab=reports"><i class="fas fa-file-text"></i><span>Reports</span></a></li>
                    <li><a href="admin_dashboard.php?tab=settings"><i class="fas fa-cog"></i><span>System Settings</span></a></li>
                </ul>
            </nav>
        </aside>
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Edit User</h1>
                </div>
                <div class="header-right">
                    <a href="admin_dashboard.php?tab=users" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Users</span>
                    </a>
                </div>
            </header>
            <div class="content">
                <div class="content-section">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Name:</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="role">Role:</label>
                            <select id="role" name="role" required>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="faculty" <?= $user['role'] === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                                <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary">Update User</button>
                    </form>
                    <?php if ($errors): ?>
                        <div class="error-messages">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="success-message">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <style>
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
        }
        .btn-primary, .btn-secondary {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background-color: #dc2626;
            color: white;
        }
        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }
        .error-messages {
            color: red;
            margin-top: 1rem;
        }
        .success-message {
            color: green;
            margin-top: 1rem;
        }
    </style>
</body>
</html>
