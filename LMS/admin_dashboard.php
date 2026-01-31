
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

// Get stats from database
$total_users = 0;
$active_courses = 0;
$faculty_members = 0;
$students = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM users");
if ($result) {
    $row = $result->fetch_assoc();
    $total_users = $row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM courses");
if ($result) {
    $row = $result->fetch_assoc();
    $active_courses = $row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'faculty'");
if ($result) {
    $row = $result->fetch_assoc();
    $faculty_members = $row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
if ($result) {
    $row = $result->fetch_assoc();
    $students = $row['count'];
}

$stats = [
    'total_users' => $total_users,
    'active_courses' => $active_courses,
    'faculty_members' => $faculty_members,
    'students' => $students
];

// Get chart data for reports - ensure we have data even if empty
$monthly_enrollments = [];
$enrollment_query = $conn->query("
    SELECT DATE_FORMAT(enrolled_at, '%Y-%m') as month, COUNT(*) as count 
    FROM enrollments 
    WHERE enrolled_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month 
    ORDER BY month
");
if ($enrollment_query) {
    while ($row = $enrollment_query->fetch_assoc()) {
        $monthly_enrollments[] = $row;
    }
}

// If no enrollment data, create sample data for last 6 months
if (empty($monthly_enrollments)) {
    for ($i = 5; $i >= 0; $i--) {
        $date = date('Y-m', strtotime("-$i months"));
        $monthly_enrollments[] = ['month' => $date, 'count' => rand(5, 20)];
    }
}

$course_enrollments = [];
$course_query = $conn->query("
    SELECT c.name, COUNT(e.id) as student_count 
    FROM courses c 
    LEFT JOIN enrollments e ON c.id = e.course_id 
    GROUP BY c.id 
    ORDER BY student_count DESC 
    LIMIT 5
");
if ($course_query) {
    while ($row = $course_query->fetch_assoc()) {
        $course_enrollments[] = $row;
    }
}

// Active tab handling
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// Handle add user form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
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
                $new_user_id = $insert_stmt->insert_id;
                
                if ($role === 'student' && !empty($course_enrollments_post)) {
                    $enroll_stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, progress) VALUES (?, ?, 0)");
                    foreach ($course_enrollments_post as $course_id) {
                        $course_id = intval($course_id);
                        $enroll_stmt->bind_param("ii", $new_user_id, $course_id);
                        $enroll_stmt->execute();
                    }
                    $enroll_stmt->close();
                }
                
                header("Location: admin_dashboard.php?tab=users&success=1");
                exit();
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
}

// Handle edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id = (int)$_POST['user_id'];
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = trim($_POST['password'] ?? '');
    $course_enrollments_edit = $_POST['edit_courses'] ?? [];

    if ($name && $email && in_array($role, ['admin', 'faculty', 'student'])) {
        if (!empty($password) && strlen($password) >= 6) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?");
            $update_stmt->bind_param("ssssi", $name, $email, $hashed_password, $role, $id);
        } else {
            $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
            $update_stmt->bind_param("sssi", $name, $email, $role, $id);
        }

        if ($update_stmt->execute()) {
            if ($role === 'student') {
                $conn->query("DELETE FROM enrollments WHERE student_id = $id");
                if (!empty($course_enrollments_edit)) {
                    $enroll_stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, progress) VALUES (?, ?, 0)");
                    foreach ($course_enrollments_edit as $course_id) {
                        $course_id = intval($course_id);
                        $enroll_stmt->bind_param("ii", $id, $course_id);
                        $enroll_stmt->execute();
                    }
                    $enroll_stmt->close();
                }
            }
            header("Location: admin_dashboard.php?tab=users&updated=1");
            exit();
        }
        $update_stmt->close();
    }
}

// Handle delete user
if (isset($_GET['delete_user'])) {
    $user_id = intval($_GET['delete_user']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        header("Location: admin_dashboard.php?tab=users&deleted=1");
        exit();
    }
    $stmt->close();
}

// Handle bulk user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_users = $_POST['selected_users'] ?? [];
    
    if ($action === 'delete' && !empty($selected_users)) {
        $placeholders = str_repeat('?,', count($selected_users) - 1) . '?';
        $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_users)), ...$selected_users);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_dashboard.php?tab=users&bulk_deleted=1");
        exit();
    }
}

// Handle add course
$faculty_result = $conn->query("SELECT id, name FROM users WHERE role = 'faculty' ORDER BY name ASC");
$faculty_members_list = [];
if ($faculty_result) {
    while ($row = $faculty_result->fetch_assoc()) {
        $faculty_members_list[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $name = trim($_POST['course_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $instructor_id = isset($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : 0;

    if ($name && $instructor_id > 0) {
        $stmt = $conn->prepare("INSERT INTO courses (name, description, instructor_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $description, $instructor_id);
        if ($stmt->execute()) {
            header("Location: admin_dashboard.php?tab=courses&created=1");
            exit();
        }
        $stmt->close();
    }
}

// Handle delete course
if (isset($_GET['delete_course'])) {
    $course_id = intval($_GET['delete_course']);
    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    if ($stmt->execute()) {
        header("Location: admin_dashboard.php?tab=courses&course_deleted=1");
        exit();
    }
    $stmt->close();
}

// Handle edit course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_course'])) {
    $course_id = (int)$_POST['course_id'];
    $name = trim($_POST['course_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $instructor_id = isset($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : 0;

    if ($name && $instructor_id > 0) {
        $update_stmt = $conn->prepare("UPDATE courses SET name = ?, description = ?, instructor_id = ? WHERE id = ?");
        $update_stmt->bind_param("ssii", $name, $description, $instructor_id, $course_id);
        if ($update_stmt->execute()) {
            header("Location: admin_dashboard.php?tab=courses&course_updated=1");
            exit();
        }
        $update_stmt->close();
    }
}

// Handle system settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    header("Location: admin_dashboard.php?tab=settings&settings_updated=1");
    exit();
}

// Fetch all courses for enrollment selection
$courses_result = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
$all_courses = [];
if ($courses_result) {
    while ($row = $courses_result->fetch_assoc()) {
        $all_courses[] = $row;
    }
}

// Sidebar items
$sidebarItems = [
    ['id' => 'overview', 'label' => 'Overview', 'icon' => 'fa-chart-bar'],
    ['id' => 'users', 'label' => 'User Management', 'icon' => 'fa-users'],
    ['id' => 'courses', 'label' => 'Subject Management', 'icon' => 'fa-book'],
    ['id' => 'enrollments', 'label' => 'Enrollments', 'icon' => 'fa-user-plus'],
    ['id' => 'messages', 'label' => 'Messages', 'icon' => 'fa-envelope'],
    ['id' => 'reports', 'label' => 'Reports & Analytics', 'icon' => 'fa-chart-line'],
    ['id' => 'settings', 'label' => 'System Settings', 'icon' => 'fa-cog'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - I-Acadsikatayo: LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin_dashboard.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="responsive_dashboard.js"></script>
    <script src="modal_optimization.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php if (isset($_GET['success']) || isset($_GET['updated']) || isset($_GET['deleted']) || isset($_GET['created']) || isset($_GET['bulk_deleted']) || isset($_GET['course_deleted']) || isset($_GET['settings_updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show success-message" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php 
        if (isset($_GET['success'])) echo "User created successfully!";
        elseif (isset($_GET['updated'])) echo "User updated successfully!";
        elseif (isset($_GET['deleted'])) echo "User deleted successfully!";
        elseif (isset($_GET['bulk_deleted'])) echo "Selected users deleted successfully!";
        elseif (isset($_GET['created'])) echo "Course created successfully!";
        elseif (isset($_GET['course_deleted'])) echo "Course deleted successfully!";
        elseif (isset($_GET['settings_updated'])) echo "Settings updated successfully!";
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <script>
        setTimeout(function() {
            document.querySelector('.success-message')?.remove();
        }, 3000);
    </script>
    <?php endif; ?>

    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <h3>Admin Panel</h3>
                        <p>I-Acadsikatayo: LMS</p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <?php foreach ($sidebarItems as $item): ?>
                    <li class="<?= $activeTab === $item['id'] ? 'active' : '' ?>">
                        <a href="?tab=<?= $item['id'] ?>">
                            <i class="fas <?= $item['icon'] ?>"></i>
                            <span><?= htmlspecialchars($item['label']) ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sign Out</span>
                </a>
            </div>
        </aside>

        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Dashboard</h1>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search..." />
                    </div>
                </div>
                <div class="header-right">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-danger">3</span>
                    </button>
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                        <span class="user-role">Administrator</span>
                    </div>
                </div>
            </header>

            <div class="content">
                <?php if ($activeTab === 'overview'): ?>
                <div class="stats-grid">
                    <?php foreach ($stats as $key => $value): 
                        $colors = [
                            'total_users' => 'blue',
                            'active_courses' => 'emerald',
                            'faculty_members' => 'orange',
                            'students' => 'purple',
                        ];
                        $icons = [
                            'total_users' => 'fa-users',
                            'active_courses' => 'fa-book',
                            'faculty_members' => 'fa-chalkboard-teacher',
                            'students' => 'fa-graduation-cap',
                        ];
                    ?>
                    <div class="stat-card">
                        <div class="stat-icon <?= $colors[$key] ?>">
                            <i class="fas <?= $icons[$key] ?>"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= number_format($value) ?></h3>
                            <p><?= ucwords(str_replace('_', ' ', $key)) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="content-grid">
                    <div class="quick-actions">
                        <h2>Quick Actions</h2>
                        <div class="actions-grid">
                            <a href="add_user.php" class="action-btn">
                                <i class="fas fa-user-plus"></i>
                                <span>Add New User</span>
                            </a>
                            <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                                <i class="fas fa-book"></i>
                                <span>Create Subject</span>
                            </button>
                            <a href="?tab=enrollments" class="action-btn">
                                <i class="fas fa-user-graduate"></i>
                                <span>Manage Enrollments</span>
                            </a>
                            <a href="?tab=reports" class="action-btn">
                                <i class="fas fa-chart-bar"></i>
                                <span>View Reports</span>
                            </a>
                        </div>
                    </div>

                    <div class="recent-activity">
                        <h2>Recent Activity</h2>
                        <div class="activity-list">
                            <?php
                            $activity_query = "
                                SELECT 'user' as type, name, created_at FROM users 
                                UNION ALL
                                SELECT 'course' as type, name, created_at FROM courses
                                ORDER BY created_at DESC LIMIT 5
                            ";
                            $activity_result = $conn->query($activity_query);
                            if ($activity_result && $activity_result->num_rows > 0):
                                while ($activity = $activity_result->fetch_assoc()):
                            ?>
                            <div class="activity-item">
                                <div class="activity-icon <?= $activity['type'] === 'user' ? 'blue' : 'emerald' ?>">
                                    <i class="fas <?= $activity['type'] === 'user' ? 'fa-user' : 'fa-book' ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <p><?= $activity['type'] === 'user' ? 'New user registered' : 'Course created' ?></p>
                                    <span><?= htmlspecialchars($activity['name']) ?> • <?= date('M d, Y g:i A', strtotime($activity['created_at'])) ?></span>
                                </div>
                            </div>
                            <?php endwhile; else: ?>
                            <p class="text-muted">No recent activity</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php elseif ($activeTab === 'users'): ?>
                <div class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-users me-2"></i>User Management</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus me-2"></i>Add New User
                        </button>
                    </div>
                    
                    <div class="filter-bar">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <input type="text" id="userSearch" placeholder="Search users..." class="form-control">
                            </div>
                            <div class="col-md-3">
                                <select id="roleFilter" class="form-select">
                                    <option value="">All Roles</option>
                                    <option value="admin">Admin</option>
                                    <option value="faculty">Faculty</option>
                                    <option value="student">Student</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="sortBy" class="form-select">
                                    <option value="recent">Most Recent</option>
                                    <option value="name">Name (A-Z)</option>
                                    <option value="email">Email</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                                    <i class="fas fa-redo me-2"></i>Reset
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover" id="usersTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Courses</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $result = $conn->query("
                                    SELECT u.id, u.name, u.email, u.role, u.created_at,
                                           (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id) as course_count
                                    FROM users u 
                                    ORDER BY u.created_at DESC
                                ");
                                if ($result && $result->num_rows > 0) {
                                    while ($user = $result->fetch_assoc()) {
                                        $badge_color = ['admin' => 'danger', 'faculty' => 'primary', 'student' => 'success'];
                                        echo "<tr data-role='" . $user['role'] . "'>";
                                        echo "<td>" . $user['id'] . "</td>";
                                        echo "<td><strong>" . htmlspecialchars($user['name']) . "</strong></td>";
                                        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                                        echo "<td><span class='badge bg-" . $badge_color[$user['role']] . "'>" . ucfirst($user['role']) . "</span></td>";
                                        echo "<td>" . ($user['role'] === 'student' ? "<span class='badge bg-info'>" . $user['course_count'] . " courses</span>" : "N/A") . "</td>";
                                        echo "<td>" . date('M d, Y', strtotime($user['created_at'])) . "</td>";
                                        echo "<td><div class='table-actions'>";
                                        echo "<button type='button' class='btn btn-sm btn-outline-primary edit-user-btn' data-bs-toggle='modal' data-bs-target='#editUserModal' data-id='" . $user['id'] . "' data-name='" . htmlspecialchars($user['name']) . "' data-email='" . htmlspecialchars($user['email']) . "' data-role='" . $user['role'] . "'><i class='fas fa-edit'></i></button>";
                                        echo "<a href='?tab=users&delete_user=" . $user['id'] . "' class='btn btn-sm btn-outline-danger' onclick='return confirm(\"Delete this user?\")'><i class='fas fa-trash'></i></a>";
                                        echo "</div></td>";
                                        echo "</tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php elseif ($activeTab === 'courses'): ?>
<div class="content-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-book me-2"></i>Subject Management</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            <i class="fas fa-plus me-2"></i>Create Subject
        </button>
    </div>

    <div class="filter-bar">
        <input type="text" id="courseSearch" placeholder="Search courses..." class="form-control">
    </div>
    
    <div class="course-grid">
        <?php
        // Fixed query - added instructor_id to SELECT
        $result = $conn->query("
            SELECT c.id, c.name, c.description, c.instructor_id, u.name as instructor, c.created_at,
                   (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
            FROM courses c 
            LEFT JOIN users u ON c.instructor_id = u.id 
            ORDER BY c.created_at DESC
        ");
        
        if ($result && $result->num_rows > 0) {
            while ($course = $result->fetch_assoc()) {
                // Get the instructor_id, default to 0 if null
                $instructor_id = $course['instructor_id'] ?? 0;
        ?>
        <div class="course-card">
            <div class="course-header">
                <h5><?= htmlspecialchars($course['name']) ?></h5>
                <small><i class="fas fa-user me-1"></i><?= htmlspecialchars($course['instructor'] ?? 'N/A') ?></small>
            </div>
            <div class="course-body">
                <p class="text-muted"><?= htmlspecialchars(substr($course['description'], 0, 100)) ?><?= strlen($course['description']) > 100 ? '...' : '' ?></p>
                <div class="course-stats">
                    <div class="course-stat">
                        <div class="course-stat-value"><?= $course['student_count'] ?></div>
                        <div class="course-stat-label">Students</div>
                    </div>
                    <div class="course-stat">
                        <div class="course-stat-value"><?= date('M Y', strtotime($course['created_at'])) ?></div>
                        <div class="course-stat-label">Created</div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-primary flex-fill edit-course-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#editCourseModal"
                            data-id="<?= $course['id'] ?>"
                            data-name="<?= htmlspecialchars($course['name']) ?>"
                            data-description="<?= htmlspecialchars($course['description']) ?>"
                            data-instructor-id="<?= $instructor_id ?>">
                        <i class="fas fa-edit me-1"></i>Edit
                    </button>
                    <a href="admin_dashboard.php?tab=courses&delete_course=<?= $course['id'] ?>" 
                       class="btn btn-sm btn-outline-danger flex-fill" 
                       onclick="return confirm('Are you sure you want to delete this course? This will also remove all enrollments.')">
                        <i class="fas fa-trash me-1"></i>Delete
                    </a>
                </div>
            </div>
        </div>
        <?php 
            }
        } else {
            echo "<div class='col-12'><p class='text-muted text-center py-5'><i class='fas fa-inbox fa-3x mb-3 d-block'></i>No courses found.</p></div>";
        }
        ?>
    </div>
</div>


                <?php elseif ($activeTab === 'messages'): ?>
                <div class="content-section">
                    <h2 class="mb-4"><i class="fas fa-envelope me-2"></i>Faculty Messages</h2>
                    
                    <div class="filter-bar mb-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <input type="text" id="messageSearch" placeholder="Search messages..." class="form-control">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select">
                                    <option value="">All Messages</option>
                                    <option value="unread">Unread</option>
                                    <option value="read">Read</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select">
                                    <option value="">All Faculty</option>
                                    <?php 
                                    foreach ($faculty_members_list as $faculty) {
                                        echo "<option value='" . $faculty['id'] . "'>" . htmlspecialchars($faculty['name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="message-list">
                        <?php
                        $messages_query = $conn->query("
                            SELECT m.*, u.name as sender_name, u.role as sender_role
                            FROM messages m
                            JOIN users u ON m.sender_id = u.id
                            WHERE u.role = 'faculty' AND m.receiver_id = {$_SESSION['user_id']}
                            ORDER BY m.sent_at DESC
                            LIMIT 20
                        ");
                        
                        if ($messages_query && $messages_query->num_rows > 0) {
                            while ($msg = $messages_query->fetch_assoc()) {
                                $initials = strtoupper(substr($msg['sender_name'], 0, 1));
                                $unread_class = !$msg['is_read'] ? 'unread' : '';
                        ?>
                        <div class="message-item <?= $unread_class ?>" onclick="viewMessage(<?= $msg['id'] ?>)">
                            <div class="message-avatar"><?= $initials ?></div>
                            <div class="message-content">
                                <div class="message-header">
                                    <div>
                                        <strong><?= htmlspecialchars($msg['sender_name']) ?></strong>
                                        <span class="badge-custom bg-primary ms-2">Faculty</span>
                                    </div>
                                    <small class="text-muted"><?= date('M d, g:i A', strtotime($msg['sent_at'])) ?></small>
                                </div>
                                <div class="message-subject"><strong><?= htmlspecialchars($msg['subject']) ?></strong></div>
                                <p class="text-muted mb-0"><?= htmlspecialchars(substr($msg['message'], 0, 80)) ?>...</p>
                            </div>
                            <?php if (!$msg['is_read']): ?>
                            <div class="ms-auto">
                                <span class="badge bg-danger">New</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php 
                            }
                        } else {
                            echo "<div class='text-center py-5 text-muted'><i class='fas fa-inbox fa-3x mb-3'></i><p>No messages from faculty</p></div>";
                        }
                        ?>
                    </div>
                </div>

                <?php elseif ($activeTab === 'reports'): ?>
                <div class="content-section">
                    <h2 class="mb-4"><i class="fas fa-chart-line me-2"></i>Reports & Analytics</h2>
                    
                    <div class="stats-grid mb-4">
                        <div class="stat-card">
                            <div class="stat-icon blue">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $stats['total_users'] ?></h3>
                                <p>Total Users</p>
                                <small class="text-success"><i class="fas fa-arrow-up"></i> 12% this month</small>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon emerald">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $stats['active_courses'] ?></h3>
                                <p>Active Courses</p>
                                <small class="text-success"><i class="fas fa-arrow-up"></i> 8% this month</small>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon orange">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php 
                                $enroll_count = $conn->query("SELECT COUNT(*) as c FROM enrollments")->fetch_assoc()['c'];
                                echo $enroll_count;
                                ?></h3>
                                <p>Total Enrollments</p>
                                <small class="text-success"><i class="fas fa-arrow-up"></i> 15% this month</small>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon purple">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="stat-info">
                                <h3>78%</h3>
                                <p>Avg. Completion Rate</p>
                                <small class="text-danger"><i class="fas fa-arrow-down"></i> 3% this month</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="chart-container">
                                <h4><i class="fas fa-chart-line me-2"></i>Enrollment Trends (Last 6 Months)</h4>
                                <div class="chart-wrapper">
                                    <canvas id="enrollmentChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="chart-container">
                                <h4><i class="fas fa-chart-pie me-2"></i>User Distribution</h4>
                                <div class="chart-wrapper">
                                    <canvas id="userDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="chart-container">
                                <h4><i class="fas fa-chart-bar me-2"></i>Top 5 Courses by Enrollment</h4>
                                <div class="chart-wrapper">
                                    <canvas id="topCoursesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h4><i class="fas fa-trophy me-2"></i>Top Performing Students</h4>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Student</th>
                                                <th>Avg Progress</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $top_students = $conn->query("
                                                SELECT u.name, AVG(e.progress) as avg_progress
                                                FROM users u
                                                JOIN enrollments e ON u.id = e.student_id
                                                WHERE u.role = 'student'
                                                GROUP BY u.id
                                                ORDER BY avg_progress DESC
                                                LIMIT 5
                                            ");
                                            if ($top_students && $top_students->num_rows > 0) {
                                                $rank = 1;
                                                while ($student = $top_students->fetch_assoc()) {
                                                    echo "<tr>";
                                                    echo "<td><strong>#" . $rank++ . "</strong></td>";
                                                    echo "<td>" . htmlspecialchars($student['name']) . "</td>";
                                                    echo "<td><div class='progress'><div class='progress-bar bg-success' style='width:" . $student['avg_progress'] . "%'>" . round($student['avg_progress']) . "%</div></div></td>";
                                                    echo "</tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='3' class='text-center text-muted'>No student data available</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h4><i class="fas fa-calendar-check me-2"></i>Recent Activity Log</h4>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php
                                    $activity_log = $conn->query("
                                        SELECT 'enrollment' as type, CONCAT(u.name, ' enrolled in ', c.name) as activity, e.enrolled_at as time
                                        FROM enrollments e
                                        JOIN users u ON e.student_id = u.id
                                        JOIN courses c ON e.course_id = c.id
                                        ORDER BY e.enrolled_at DESC
                                        LIMIT 10
                                    ");
                                    if ($activity_log && $activity_log->num_rows > 0) {
                                        while ($log = $activity_log->fetch_assoc()) {
                                            echo "<div class='d-flex justify-content-between border-bottom py-2'>";
                                            echo "<small>" . htmlspecialchars($log['activity']) . "</small>";
                                            echo "<small class='text-muted'>" . date('M d, g:i A', strtotime($log['time'])) . "</small>";
                                            echo "</div>";
                                        }
                                    } else {
                                        echo "<p class='text-muted text-center py-3'>No activity logged yet</p>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php elseif ($activeTab === 'enrollments'): ?>
                <div class="content-section">
                    <h2 class="mb-4"><i class="fas fa-user-plus me-2"></i>Student Enrollments</h2>
                    
                    <div class="row">
                        <?php
                        $students_query = $conn->query("SELECT id, name, email FROM users WHERE role = 'student' ORDER BY name");
                        while ($student = $students_query->fetch_assoc()):
                            $enrolled_courses = $conn->query("
                                SELECT c.name, e.progress 
                                FROM enrollments e 
                                JOIN courses c ON e.course_id = c.id 
                                WHERE e.student_id = " . $student['id']
                            );
                        ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 user-card">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-user-graduate me-2 text-primary"></i><?= htmlspecialchars($student['name']) ?></h5>
                                    <p class="text-muted small"><?= htmlspecialchars($student['email']) ?></p>
                                    <hr>
                                    <h6 class="mb-3">Enrolled Subjects:</h6>
                                    <?php if ($enrolled_courses->num_rows > 0): ?>
                                        <ul class="list-group list-group-flush">
                                            <?php while ($course = $enrolled_courses->fetch_assoc()): ?>
                                            <li class="list-group-item px-0">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <small><strong><?= htmlspecialchars($course['name']) ?></strong></small>
                                                    <span class="badge bg-primary"><?= $course['progress'] ?>%</span>
                                                </div>
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar" style="width: <?= $course['progress'] ?>%"></div>
                                                </div>
                                            </li>
                                            <?php endwhile; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-muted small">No enrollments yet</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <?php elseif ($activeTab === 'settings'): ?>
                <div class="content-section">
                    <h2 class="mb-4"><i class="fas fa-cog me-2"></i>System Settings</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="update_settings" value="1">
                        
                        <div class="settings-panel">
                            <h4><i class="fas fa-building me-2"></i>General Settings</h4>
                            <div class="mb-3">
                                <label class="form-label">System Name</label>
                                <input type="text" class="form-control" value="I-Acadsikatayo: Learning Management System">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Admin Email</label>
                                <input type="email" class="form-control" value="admin@iacadsikatayo.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">System Timezone</label>
                                <select class="form-select">
                                    <option>Asia/Manila (UTC+8)</option>
                                    <option>Asia/Tokyo (UTC+9)</option>
                                    <option>America/New_York (UTC-5)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date Format</label>
                                <select class="form-select">
                                    <option>MM/DD/YYYY</option>
                                    <option>DD/MM/YYYY</option>
                                    <option>YYYY-MM-DD</option>
                                </select>
                            </div>
                        </div>

                        <div class="settings-panel">
                            <h4><i class="fas fa-bell me-2"></i>Notification Settings</h4>
                            <div class="setting-item">
                                <div>
                                    <strong>Email Notifications</strong>
                                    <p class="text-muted small mb-0">Send email notifications for system events</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div>
                                    <strong>New User Registration</strong>
                                    <p class="text-muted small mb-0">Notify admin when new users register</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div>
                                    <strong>Course Enrollment</strong>
                                    <p class="text-muted small mb-0">Notify when students enroll in courses</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div>
                                    <strong>Faculty Messages</strong>
                                    <p class="text-muted small mb-0">Notify admin of new faculty messages</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-panel">
                            <h4><i class="fas fa-lock me-2"></i>Security Settings</h4>
                            <div class="setting-item">
                                <div>
                                    <strong>Two-Factor Authentication</strong>
                                    <p class="text-muted small mb-0">Require 2FA for admin accounts</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div>
                                    <strong>Password Expiration</strong>
                                    <p class="text-muted small mb-0">Require password change every 90 days</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="mb-3 mt-3">
                                <label class="form-label">Minimum Password Length</label>
                                <input type="number" class="form-control" value="6" min="6" max="20">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Session Timeout (minutes)</label>
                                <input type="number" class="form-control" value="30" min="5" max="120">
                            </div>
                        </div>

                        <div class="settings-panel">
                            <h4><i class="fas fa-graduation-cap me-2"></i>Course Settings</h4>
                            <div class="setting-item">
                                <div>
                                    <strong>Auto-Enrollment</strong>
                                    <p class="text-muted small mb-0">Allow students to enroll without approval</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div>
                                    <strong>Course Completion Certificates</strong>
                                    <p class="text-muted small mb-0">Generate certificates on course completion</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="mb-3 mt-3">
                                <label class="form-label">Max Courses Per Student</label>
                                <input type="number" class="form-control" value="10" min="1" max="50">
                            </div>
                        </div>

                        <div class="settings-panel">
                            <h4><i class="fas fa-database me-2"></i>System Maintenance</h4>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary">
                                    <i class="fas fa-download me-2"></i>Backup Database
                                </button>
                                <button type="button" class="btn btn-outline-warning">
                                    <i class="fas fa-broom me-2"></i>Clear System Cache
                                </button>
                                <button type="button" class="btn btn-outline-info">
                                    <i class="fas fa-sync me-2"></i>Check for Updates
                                </button>
                                <button type="button" class="btn btn-outline-danger" onclick="return confirm('This will reset all settings to default. Continue?')">
                                    <i class="fas fa-redo me-2"></i>Reset to Defaults
                                </button>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save All Settings
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Add User Modal -->
                <div class="modal fade" id="addUserModal" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                                    <input type="hidden" name="add_user" value="1" />
                                    
                                    <div class="form-row-horizontal">
                                        <label for="name">Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    
                                    <div class="form-row-horizontal">
                                        <label for="email">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
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
                                            <option value="admin">Admin</option>
                                            <option value="faculty">Faculty</option>
                                            <option value="student">Student</option>
                                        </select>
                                    </div>
                                    
                                    <div id="courseEnrollmentSection" class="enrollment-section" style="display: none;">
                                        <h6><i class="fas fa-book me-2"></i>Enroll in Courses (Optional)</h6>
                                        <p class="text-muted small">Select courses to enroll this student</p>
                                        <div id="courseCheckboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; padding: 10px;">
                                            <?php foreach ($all_courses as $course): ?>
                                            <div class="form-check course-checkbox">
                                                <input class="form-check-input" type="checkbox" name="courses[]" value="<?= $course['id'] ?>" id="course_<?= $course['id'] ?>">
                                                <label class="form-check-label" for="course_<?= $course['id'] ?>">
                                                    <?= htmlspecialchars($course['name']) ?>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer" style="position: sticky; bottom: 0; background: white; z-index: 10; border-top: 2px solid #dee2e6;">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Add User
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="editUserModal" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                                    <input type="hidden" name="edit_user" value="1">
                                    <input type="hidden" id="edit_user_id" name="user_id">

                                    <div class="form-row-horizontal">
                                        <label for="edit_name">Full Name *</label>
                                        <input type="text" class="form-control" id="edit_name" name="name" required>
                                    </div>

                                    <div class="form-row-horizontal">
                                        <label for="edit_email">Email Address *</label>
                                        <input type="email" class="form-control" id="edit_email" name="email" required>
                                    </div>

                                    <div class="form-row-horizontal">
                                        <label for="edit_password">New Password</label>
                                        <input type="password" class="form-control" id="edit_password" name="password">
                                    </div>
                                    <small class="text-muted">Leave blank to keep current password</small>

                                    <div class="form-row-horizontal">
                                        <label for="edit_role">Role *</label>
                                        <select class="form-select" id="edit_role" name="role" required onchange="toggleEditCourseSelection(this.value)">
                                            <option value="admin">Admin</option>
                                            <option value="faculty">Faculty</option>
                                            <option value="student">Student</option>
                                        </select>
                                    </div>

                                    <div id="editCourseEnrollmentSection" class="enrollment-section" style="display: none;">
                                        <h6><i class="fas fa-book me-2"></i>Update Course Enrollments</h6>
                                        <div id="editCourseCheckboxes">
                                            <?php foreach ($all_courses as $course): ?>
                                            <div class="form-check course-checkbox">
                                                <input class="form-check-input edit-course-check" type="checkbox" name="edit_courses[]" value="<?= $course['id'] ?>" id="edit_course_<?= $course['id'] ?>">
                                                <label class="form-check-label" for="edit_course_<?= $course['id'] ?>">
                                                    <?= htmlspecialchars($course['name']) ?>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer" style="position: sticky; bottom: 0; background: white; z-index: 10; border-top: 2px solid #dee2e6;">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save me-2"></i>Update User
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Add Course Modal -->
                <div class="modal fade" id="addCourseModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New Subject</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <input type="hidden" name="add_course" value="1" />

                                    <div class="form-row-horizontal">
                                        <label for="course_name">Subject Name *</label>
                                        <input type="text" class="form-control" id="course_name" name="course_name" required>
                                    </div>

                                    <div class="form-row-horizontal">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>

                                    <div class="form-row-horizontal">
                                        <label for="instructor_id">Instructor *</label>
                                        <select class="form-select" id="instructor_id" name="instructor_id" required>
                                            <option value="">Select Instructor</option>
                                            <?php foreach ($faculty_members_list as $faculty): ?>
                                                <option value="<?= $faculty['id'] ?>">
                                                    <?= htmlspecialchars($faculty['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-plus me-2"></i>Create Subject
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Course Modal -->
                <div class="modal fade" id="editCourseModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Subject</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <input type="hidden" name="edit_course" value="1">
                                    <input type="hidden" id="edit_course_id" name="course_id">

                                    <div class="form-row-horizontal">
                                        <label for="edit_course_name">Subject Name *</label>
                                        <input type="text" class="form-control" id="edit_course_name" name="course_name" required>
                                    </div>

                                    <div class="form-row-horizontal">
                                        <label for="edit_course_description">Description</label>
                                        <textarea class="form-control" id="edit_course_description" name="description" rows="3"></textarea>
                                    </div>

                                    <div class="form-row-horizontal">
                                        <label for="edit_instructor_id">Instructor *</label>
                                        <select class="form-select" id="edit_instructor_id" name="instructor_id" required>
                                            <option value="">Select Instructor</option>
                                            <?php foreach ($faculty_members_list as $faculty): ?>
                                                <option value="<?= $faculty['id'] ?>">
                                                    <?= htmlspecialchars($faculty['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save me-2"></i>Update Subject
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle course selection based on role
        function toggleCourseSelection(role) {
            const section = document.getElementById('courseEnrollmentSection');
            section.style.display = role === 'student' ? 'block' : 'none';
        }

        function toggleEditCourseSelection(role) {
            const section = document.getElementById('editCourseEnrollmentSection');
            section.style.display = role === 'student' ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {

            // Handle Edit User Modal
            const editUserButtons = document.querySelectorAll('.edit-user-btn');

            editUserButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const email = this.getAttribute('data-email');
                    const role = this.getAttribute('data-role');

                    document.getElementById('edit_user_id').value = id;
                    document.getElementById('edit_name').value = name;
                    document.getElementById('edit_email').value = email;
                    document.getElementById('edit_role').value = role;
                    document.getElementById('edit_password').value = '';

                    toggleEditCourseSelection(role);

                    if (role === 'student') {
                        document.querySelectorAll('.edit-course-check').forEach(cb => cb.checked = false);

                        fetch(`get_student_courses.php?student_id=${id}`)
                            .then(response => response.json())
                            .then(data => {
                                data.forEach(courseId => {
                                    const checkbox = document.getElementById(`edit_course_${courseId}`);
                                    if (checkbox) checkbox.checked = true;
                                });
                            })
                            .catch(err => console.error('Error fetching courses:', err));
                    }
                });
            });

            // Handle Edit Course Modal
            const editCourseButtons = document.querySelectorAll('.edit-course-btn');

            editCourseButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const description = this.getAttribute('data-description');
                    const instructorId = this.getAttribute('data-instructor-id');

                    document.getElementById('edit_course_id').value = id;
                    document.getElementById('edit_course_name').value = name;
                    document.getElementById('edit_course_description').value = description;
                    document.getElementById('edit_instructor_id').value = instructorId;
                });
            });

            // User search and filters
            const userSearch = document.getElementById('userSearch');
            const roleFilter = document.getElementById('roleFilter');
            
            function filterUsers() {
                const searchTerm = userSearch ? userSearch.value.toLowerCase() : '';
                const roleValue = roleFilter ? roleFilter.value.toLowerCase() : '';
                const rows = document.querySelectorAll('#usersTable tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    const role = row.getAttribute('data-role');
                    const matchesSearch = text.includes(searchTerm);
                    const matchesRole = !roleValue || role === roleValue;
                    
                    row.style.display = matchesSearch && matchesRole ? '' : 'none';
                });
            }

            if (userSearch) userSearch.addEventListener('keyup', filterUsers);
            if (roleFilter) roleFilter.addEventListener('change', filterUsers);

            // Course search
            const courseSearch = document.getElementById('courseSearch');
            if (courseSearch) {
                courseSearch.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase();
                    const cards = document.querySelectorAll('.course-card');
                    
                    cards.forEach(card => {
                        const text = card.textContent.toLowerCase();
                        card.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });
            }

            // Select all checkboxes
            // Removed bulk action checkbox JS as checkboxes are removed

            // Initialize Charts
            <?php if ($activeTab === 'reports'): ?>
            
            // Enrollment Trends Chart
            const enrollmentCtx = document.getElementById('enrollmentChart');
            if (enrollmentCtx) {
                new Chart(enrollmentCtx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode(array_map(function($e) { return date('M Y', strtotime($e['month'] . '-01')); }, $monthly_enrollments)) ?>,
                        datasets: [{
                            label: 'New Enrollments',
                            data: <?= json_encode(array_column($monthly_enrollments, 'count')) ?>,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 5
                                }
                            }
                        }
                    }
                });
            }

            // User Distribution Chart
            const userDistCtx = document.getElementById('userDistributionChart');
            if (userDistCtx) {
                const adminCount = <?= $stats['total_users'] - $stats['students'] - $stats['faculty_members'] ?>;
                new Chart(userDistCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Students', 'Faculty', 'Admin'],
                        datasets: [{
                            data: [<?= $stats['students'] ?>, <?= $stats['faculty_members'] ?>, adminCount],
                            backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Top Courses Chart
            const topCoursesCtx = document.getElementById('topCoursesChart');
            if (topCoursesCtx) {
                new Chart(topCoursesCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_column($course_enrollments, 'name')) ?>,
                        datasets: [{
                            label: 'Students Enrolled',
                            data: <?= json_encode(array_column($course_enrollments, 'student_count')) ?>,
                            backgroundColor: [
                                '#4f46e5',
                                '#10b981',
                                '#f59e0b',
                                '#ef4444',
                                '#8b5cf6'
                            ],
                            borderRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
            <?php endif; ?>
        });

        function resetFilters() {
            document.getElementById('userSearch').value = '';
            document.getElementById('roleFilter').value = '';
            document.getElementById('sortBy').value = 'recent';
            const rows = document.querySelectorAll('#usersTable tbody tr');
            rows.forEach(row => row.style.display = '');
        }

        function viewMessage(messageId) {
            // Open message modal and fetch message details
            const modal = new bootstrap.Modal(document.getElementById('viewMessageModal'));
            const modalTitle = document.getElementById('viewMessageModalLabel');
            const modalBody = document.getElementById('viewMessageModalBody');
            modalTitle.textContent = 'Loading...';
            modalBody.textContent = 'Please wait while the message loads.';
            modal.show();

            fetch(`get_message.php?id=${messageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalTitle.textContent = data.subject;
                        modalBody.innerHTML = `
                            <p><strong>From:</strong> ${data.sender_name} (${data.sender_role})</p>
                            <p><strong>Sent:</strong> ${data.sent_at}</p>
                            <hr>
                            <p>${data.message.replace(/\n/g, '<br>')}</p>
                        `;
                    } else {
                        modalTitle.textContent = 'Error';
                        modalBody.textContent = 'Failed to load message.';
                    }
                })
                .catch(() => {
                    modalTitle.textContent = 'Error';
                    modalBody.textContent = 'Failed to load message.';
                });
        }
    </script>

    <!-- View Message Modal -->
    <div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewMessageModalLabel">Message</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewMessageModalBody">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>