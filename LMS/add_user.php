<?php
session_start();
include 'config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all courses for enrollment selection
$courses_result = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
$all_courses = [];
if ($courses_result) {
    while ($row = $courses_result->fetch_assoc()) {
        $all_courses[] = $row;
    }
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $course_enrollments_post = $_POST['courses'] ?? [];

    $errors = [];
    if (!$name) $errors[] = "Name is required.";
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (!$password || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if (!in_array($role, ['admin', 'faculty', 'student'])) $errors[] = "Invalid role selected.";

    if (empty($errors)) {
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
                $new_user_id = $insert_stmt->insert_id();

                if ($role === 'student' && !empty($course_enrollments_post)) {
                    $enroll_stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, progress) VALUES (?, ?, 0)");
                    foreach ($course_enrollments_post as $course_id) {
                        $course_id = intval($course_id);
                        $enroll_stmt->bind_param("ii", $new_user_id, $course_id);
                        $enroll_stmt->execute();
                    }
                    $enroll_stmt->close();
                }

                $success = "User added successfully.";
                // Clear form data
                $name = $email = $password = $role = '';
                $course_enrollments_post = [];
            } else {
                $errors[] = "Failed to add user: " . $conn->error;
            }
            $insert_stmt->close();
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
    <title>Add New User - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin_dashboard.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark-bg: #1f2937;
            --light-bg: #f9fafb;
        }

        .form-row-horizontal {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 20px;
        }

        .form-row-horizontal label {
            min-width: 150px;
            margin-bottom: 0;
            text-align: right;
            font-weight: 500;
        }

        .form-row-horizontal .form-control,
        .form-row-horizontal .form-select {
            flex: 1;
        }

        .form-row-horizontal small {
            margin-left: 170px;
            display: block;
        }

        .enrollment-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            margin-left: 170px;
        }

        .course-checkbox {
            padding: 8px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 8px;
            background: white;
        }

        .course-checkbox:hover {
            background: #f0f0f0;
        }

        .success-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .main-content {
            margin-left: 0;
            padding: 2rem;
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show success-message" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <script>
        setTimeout(function() {
            document.querySelector('.success-message')?.remove();
        }, 3000);
    </script>
    <?php endif; ?>

    <div class="dashboard">
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-user-plus me-2 text-primary"></i>Add New User</h1>
                    <p class="text-muted mb-0">Create a new user account for the system</p>
                </div>
                <a href="admin_dashboard.php?tab=users" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to User Management
                </a>
            </div>

            <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Please fix the following errors:</h6>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="">
                    <div class="form-row-horizontal">
                        <label for="name">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name ?? '') ?>" required>
                    </div>

                    <div class="form-row-horizontal">
                        <label for="email">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
                    </div>

                    <div class="form-row-horizontal">
                        <label for="password">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <small class="text-muted">Minimum 6 characters</small>

                    <div class="form-row-horizontal">
                        <label for="role">Role *</label>
                        <select class="form-select" id="role" name="role" required onchange="toggleCourseSelection(this.value)">
                            <option value="">Select role</option>
                            <option value="admin" <?= ($role ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="faculty" <?= ($role ?? '') === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                            <option value="student" <?= ($role ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                        </select>
                    </div>

                    <div id="courseEnrollmentSection" class="enrollment-section" style="display: <?= ($role ?? '') === 'student' ? 'block' : 'none' ?>;">
                        <h6><i class="fas fa-book me-2"></i>Enroll in Courses (Optional)</h6>
                        <p class="text-muted small">Select courses to enroll this student</p>
                        <div id="courseCheckboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; padding: 10px;">
                            <?php foreach ($all_courses as $course): ?>
                            <div class="form-check course-checkbox">
                                <input class="form-check-input" type="checkbox" name="courses[]" value="<?= $course['id'] ?>" id="course_<?= $course['id'] ?>" <?= in_array($course['id'], $course_enrollments_post ?? []) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="course_<?= $course['id'] ?>">
                                    <?= htmlspecialchars($course['name']) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <a href="admin_dashboard.php?tab=users" class="btn btn-secondary me-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add User
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCourseSelection(role) {
            const section = document.getElementById('courseEnrollmentSection');
            section.style.display = role === 'student' ? 'block' : 'none';
        }
    </script>
</body>
</html>
