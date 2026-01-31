<?php
session_start();
include '../config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$errors = [];
$success = '';

// Fetch faculty members for instructor selection
$faculty_result = $conn->query("SELECT id, name FROM users WHERE role = 'faculty' ORDER BY name ASC");
$faculty_members = [];
if ($faculty_result) {
    while ($row = $faculty_result->fetch_assoc()) {
        $faculty_members[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $instructor_id = isset($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : 0;

    if (!$name) {
        $errors[] = "Course name is required.";
    }
    if ($instructor_id <= 0) {
        $errors[] = "Please select an instructor.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO courses (name, description, instructor_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $description, $instructor_id);
        if ($stmt->execute()) {
            $success = "Course created successfully.";
            // Clear form values
            $_POST = [];
        } else {
            $errors[] = "Failed to create course: " . $conn->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Create Course - Admin Dashboard</title>
    <link rel="stylesheet" href="css/admin_dashboard.css" />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
    />
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
                    <li><a href="admin_dashboard.php?tab=users"><i class="fas fa-users"></i><span>User Management</span></a></li>
                    <li class="active"><a href="admin_dashboard.php?tab=courses"><i class="fas fa-book"></i><span>Course Management</span></a></li>
                    <li><a href="admin_dashboard.php?tab=reports"><i class="fas fa-file-text"></i><span>Reports</span></a></li>
                    <li><a href="admin_dashboard.php?tab=settings"><i class="fas fa-cog"></i><span>System Settings</span></a></li>
                </ul>
            </nav>
        </aside>
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Create Course</h1>
                </div>
                <div class="header-right">
                    <a href="admin_dashboard.php?tab=courses" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Courses</span>
                    </a>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </header>
            <div class="content">
                <div class="content-section">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Course Name:</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required />
                        </div>
                        <div class="form-group">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="instructor_id">Instructor:</label>
                            <select id="instructor_id" name="instructor_id" required>
                                <option value="">Select Instructor</option>
                                <?php foreach ($faculty_members as $faculty): ?>
                                    <option value="<?= $faculty['id'] ?>" <?= (isset($_POST['instructor_id']) && $_POST['instructor_id'] == $faculty['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($faculty['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary">Create Course</button>
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
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            box-sizing: border-box;
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
