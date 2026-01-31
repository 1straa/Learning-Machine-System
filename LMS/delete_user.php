<?php
session_start();
include 'config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header('Location: admin_dashboard.php?tab=users');
    exit();
}

// Prevent deleting self
if ($user_id == $_SESSION['user_id']) {
    header('Location: admin_dashboard.php?tab=users&error=cannot_delete_self');
    exit();
}

// Fetch user data to confirm
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: admin_dashboard.php?tab=users');
    exit();
}

// Handle deletion
if (isset($_POST['confirm_delete'])) {
    $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delete_stmt->bind_param("i", $user_id);
    if ($delete_stmt->execute()) {
        header('Location: admin_dashboard.php?tab=users&success=user_deleted');
    } else {
        header('Location: admin_dashboard.php?tab=users&error=delete_failed');
    }
    $delete_stmt->close();
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delete User - Admin Dashboard</title>
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
                    <h1>Delete User</h1>
                </div>
                <div class="header-right">
                    <a href="admin_dashboard.php?tab=users" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Users</span>
                    </a>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </header>
            <div class="content">
                <div class="content-section">
                    <p>Are you sure you want to delete the user <strong><?= htmlspecialchars($user['name']) ?></strong>? This action cannot be undone.</p>
                    <form method="POST" action="">
                        <button type="submit" name="confirm_delete" class="btn-danger">Yes, Delete User</button>
                        <a href="admin_dashboard.php?tab=users" class="btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <style>
        .btn-danger {
            background-color: #dc2626;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 1rem;
        }
        .btn-secondary {
            background-color: #6b7280;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</body>
</html>
