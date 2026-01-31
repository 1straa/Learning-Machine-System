<?php
session_start();
include 'config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$success = '';

if ($course_id <= 0) {
    header('Location: admin_dashboard.php?tab=courses');
    exit();
}

// Fetch course data
$stmt = $conn->prepare("SELECT id, name, description, instructor_id FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
$course = $result->fetch_assoc();
$stmt->close();

if (!$course) {
    header('Location: admin_dashboard.php?tab=courses');
    exit();
}

// Fetch faculty members
$faculty_result = $conn->query("SELECT id, name FROM users WHERE role = 'faculty' ORDER BY name ASC");
$faculty_members = [];
if ($faculty_result) {
    while ($row = $faculty_result->fetch_assoc()) {
        $faculty_members[] = $row;
    }
}

// Handle form submission
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
        $update_stmt = $conn->prepare("UPDATE courses SET name = ?, description = ?, instructor_id = ? WHERE id = ?");
        $update_stmt->bind_param("ssii", $name, $description, $instructor_id, $course_id);
        if ($update_stmt->execute()) {
            $success = "Course updated successfully.";
            // Refresh course data
            $course['name'] = $name;
            $course['description'] = $description;
            $course['instructor_id'] = $instructor_id;
        } else {
            $errors[] = "Failed to update course: " . $conn->error;
        }
        $update_stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Edit Course - Admin Dashboard</title>
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
                    <h1>Edit Course</h1>
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
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($course['name']) ?>" required />
                        </div>
                        <div class="form-group">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" rows="4"><?= htmlspecialchars($course['description']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="instructor_id">Instructor:</label>
                            <select id="instructor_id" name="instructor_id" required>
                                <option value="">Select Instructor</option>
                                <?php foreach ($faculty_members as $faculty): ?>
                                    <option value="<?= $faculty['id'] ?>" <?= $course['instructor_id'] == $faculty['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($faculty['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary">Update Course</button>
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
