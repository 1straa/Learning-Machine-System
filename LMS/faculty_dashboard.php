<?php
session_start();
include 'config.php';

// Check if user is logged in as faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Active tab handling
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

$addOnlineClassErrors = [];
$addOnlineClassSuccess = '';

// Handle add online class form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_online_class'])) {
    $title = trim($_POST['class_title'] ?? '');
    $course_id = intval($_POST['course_id'] ?? 0);
    $meet_link = trim($_POST['meet_link'] ?? '');
    $scheduled_at = $_POST['scheduled_at'] ?? '';

    if (!$title || !$course_id || !$meet_link || !$scheduled_at) {
        $addOnlineClassErrors[] = "All fields are required.";
    }

    // Check if course belongs to instructor
    $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
    if ($stmt === false) {
        $addOnlineClassErrors[] = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
        $stmt->execute();
        $course_check = $stmt->get_result();
        if ($course_check->num_rows === 0) {
            $addOnlineClassErrors[] = "Invalid course selected.";
        }
        $stmt->close();
    }

    if (empty($addOnlineClassErrors)) {
        $stmt = $conn->prepare("INSERT INTO online_classes (title, course_id, meet_link, scheduled_at, instructor_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt === false) {
            $addOnlineClassErrors[] = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("sissi", $title, $course_id, $meet_link, $scheduled_at, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $addOnlineClassSuccess = "Online class created successfully.";
                header("Location: faculty_dashboard.php?tab=schedule&class_created=1");
                exit();
            } else {
                $addOnlineClassErrors[] = "Failed to create online class: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle add assignment form submission
$addAssignmentErrors = [];
$addAssignmentSuccess = '';

if (isset($_GET['assignment_created']) && $_GET['assignment_created'] == '1') {
    $addAssignmentSuccess = "Assignment created successfully.";
}
if (isset($_GET['assignment_updated']) && $_GET['assignment_updated'] == '1') {
    $addAssignmentSuccess = "Assignment updated successfully.";
}
if (isset($_GET['assignment_deleted']) && $_GET['assignment_deleted'] == '1') {
    $addAssignmentSuccess = "Assignment deleted successfully.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignment'])) {
    $title = trim($_POST['assignment_title'] ?? '');
    $description = trim($_POST['assignment_description'] ?? '');
    $course_id = intval($_POST['assignment_course_id'] ?? 0);
    $due_date = $_POST['assignment_due_date'] ?? '';
    $type = $_POST['assignment_type'] ?? '';

    if (!$title || !$course_id || !$due_date || !$type) {
        $addAssignmentErrors[] = "Title, type, course, and due date are required.";
    }

    // Check if course belongs to instructor
    $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
    if ($stmt === false) {
        $addAssignmentErrors[] = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
        $stmt->execute();
        $course_check = $stmt->get_result();
        if ($course_check->num_rows === 0) {
            $addAssignmentErrors[] = "Invalid course selected.";
        }
        $stmt->close();
    }

    if (empty($addAssignmentErrors)) {
        // Handle file upload if present
        $file_path = null;
        if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/assignments/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = time() . '_' . basename($_FILES['assignment_file']['name']);
            $file_path = $upload_dir . $file_name;
            move_uploaded_file($_FILES['assignment_file']['tmp_name'], $file_path);
        }

        $stmt = $conn->prepare("INSERT INTO assignments (title, description, course_id, due_date, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt === false) {
            $addAssignmentErrors[] = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ssis", $title, $description, $course_id, $due_date);
            if ($stmt->execute()) {
                header("Location: faculty_dashboard.php?tab=activity&assignment_created=1");
                exit();
            } else {
                $addAssignmentErrors[] = "Failed to create assignment: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle edit assignment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_assignment'])) {
    $assignment_id = intval($_POST['edit_assignment_id'] ?? 0);
    $title = trim($_POST['edit_assignment_title'] ?? '');
    $description = trim($_POST['edit_assignment_description'] ?? '');
    $course_id = intval($_POST['edit_assignment_course_id'] ?? 0);
    $due_date = $_POST['edit_assignment_due_date'] ?? '';

    if (!$assignment_id || !$title || !$course_id || !$due_date) {
        $addAssignmentErrors[] = "All fields are required.";
    }

    // Check if assignment belongs to instructor's course
    $stmt = $conn->prepare("SELECT a.id FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.id = ? AND c.instructor_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
        $stmt->execute();
        $check_result = $stmt->get_result();
        if ($check_result->num_rows === 0) {
            $addAssignmentErrors[] = "Invalid assignment.";
        }
        $stmt->close();
    }

    if (empty($addAssignmentErrors)) {
        $stmt = $conn->prepare("UPDATE assignments SET title = ?, description = ?, course_id = ?, due_date = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssisi", $title, $description, $course_id, $due_date, $assignment_id);
            if ($stmt->execute()) {
                header("Location: faculty_dashboard.php?tab=activity&assignment_updated=1");
                exit();
            } else {
                $addAssignmentErrors[] = "Failed to update assignment.";
            }
            $stmt->close();
        }
    }
}

// Handle delete assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignment'])) {
    $assignment_id = intval($_POST['assignment_id'] ?? 0);
    
    // Check if assignment belongs to instructor's course
    $stmt = $conn->prepare("SELECT a.id FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.id = ? AND c.instructor_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
        $stmt->execute();
        $check_result = $stmt->get_result();
        if ($check_result->num_rows > 0) {
            $stmt->close();
            
            // Delete the assignment
            $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $assignment_id);
                if ($stmt->execute()) {
                    header("Location: faculty_dashboard.php?tab=activity&assignment_deleted=1");
                    exit();
                }
                $stmt->close();
            }
        } else {
            $stmt->close();
        }
    }
}

// Handle send message
$sendMessageSuccess = '';
$sendMessageErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$receiver_id || !$subject || !$message) {
        $sendMessageErrors[] = "All fields are required.";
    }

    if (empty($sendMessageErrors)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, sent_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $_SESSION['user_id'], $receiver_id, $subject, $message);
        if ($stmt->execute()) {
            $sendMessageSuccess = "Message sent successfully.";
            header("Location: faculty_dashboard.php?tab=messages&sent=1");
            exit();
        } else {
            $sendMessageErrors[] = "Failed to send message.";
        }
        $stmt->close();
    }
}

// Define colors globally
$colors = ['blue', 'emerald', 'purple', 'orange', 'indigo'];

// Fetch faculty's courses (VIEW ONLY - no add/edit/delete)
$courses = [];
$stmt = $conn->prepare("
    SELECT c.id, c.name, c.description,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as students,
           (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) as assignments
    FROM courses c
    WHERE c.instructor_id = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$course_result = $stmt->get_result();
if ($course_result && $course_result->num_rows > 0) {
    $color_index = 0;
    while ($row = $course_result->fetch_assoc()) {
        $row['color'] = $colors[$color_index % count($colors)];
        $courses[] = $row;
        $color_index++;
    }
}
$stmt->close();

// Fetch recent activity
$recent_activity = [];
$stmt = $conn->prepare("
    SELECT s.submitted_at, u.name as student_name, a.title, c.name as course_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN courses c ON a.course_id = c.id
    JOIN users u ON s.student_id = u.id
    WHERE c.instructor_id = ?
    ORDER BY s.submitted_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$activity_result = $stmt->get_result();
if ($activity_result && $activity_result->num_rows > 0) {
    while ($row = $activity_result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
}
$stmt->close();

// Fetch students for students tab - grouped by student
$students = [];
if ($activeTab === 'students') {
    // First get unique students
    $stmt = $conn->prepare("
        SELECT DISTINCT u.id, u.name, u.email,
               (SELECT COUNT(DISTINCT e2.course_id) 
                FROM enrollments e2 
                JOIN courses c2 ON e2.course_id = c2.id 
                WHERE e2.student_id = u.id AND c2.instructor_id = ?) as course_count,
               (SELECT AVG(e3.progress) 
                FROM enrollments e3 
                JOIN courses c3 ON e3.course_id = c3.id 
                WHERE e3.student_id = u.id AND c3.instructor_id = ?) as avg_progress
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        JOIN courses c ON e.course_id = c.id
        WHERE c.instructor_id = ?
        GROUP BY u.id
        ORDER BY u.name
    ");
    $stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute();
    $students_result = $stmt->get_result();
    
    while ($row = $students_result->fetch_assoc()) {
        // Get all courses for this student
        $stmt2 = $conn->prepare("
            SELECT c.name, e.progress, e.enrolled_at
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = ? AND c.instructor_id = ?
            ORDER BY c.name
        ");
        $stmt2->bind_param("ii", $row['id'], $_SESSION['user_id']);
        $stmt2->execute();
        $courses_result = $stmt2->get_result();
        $row['courses'] = [];
        while ($course_row = $courses_result->fetch_assoc()) {
            $row['courses'][] = $course_row;
        }
        $stmt2->close();
        
        $students[] = $row;
    }
    $stmt->close();
}

// Fetch assignments for activity tab
$assignments = [];
if ($activeTab === 'activity') {
    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.description, a.due_date, a.course_id, c.name as course_name,
               (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submissions
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        WHERE c.instructor_id = ?
        ORDER BY a.due_date DESC
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $assign_result = $stmt->get_result();
    while ($row = $assign_result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
}

// Fetch online classes for schedule tab
$online_classes = [];
if ($activeTab === 'schedule') {
    // Check if online_classes table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'online_classes'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT oc.id, oc.title, oc.scheduled_at, oc.meet_link, c.name as course_name
            FROM online_classes oc
            JOIN courses c ON oc.course_id = c.id
            WHERE oc.instructor_id = ?
            ORDER BY oc.scheduled_at ASC
        ");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $class_result = $stmt->get_result();
            while ($row = $class_result->fetch_assoc()) {
                $online_classes[] = $row;
            }
            $stmt->close();
        }
    }
}

// Fetch messages
$messages = [];
$contacts = [];
if ($activeTab === 'messages') {
    // Fetch all messages
    $stmt = $conn->prepare("
        SELECT m.id, m.subject, m.message, m.sent_at, m.is_read,
               u1.name as sender_name, u2.name as receiver_name,
               m.sender_id, m.receiver_id
        FROM messages m
        JOIN users u1 ON m.sender_id = u1.id
        JOIN users u2 ON m.receiver_id = u2.id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.sent_at DESC
    ");
    $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute();
    $msg_result = $stmt->get_result();
    while ($row = $msg_result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();

    // Fetch all students and admin for contacts
    $stmt = $conn->prepare("
        SELECT DISTINCT u.id, u.name, u.email, u.role
        FROM users u
        WHERE (u.role = 'admin' OR u.id IN (
            SELECT DISTINCT e.student_id
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE c.instructor_id = ?
        )) AND u.id != ?
        ORDER BY u.role, u.name
    ");
    $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute();
    $contact_result = $stmt->get_result();
    while ($row = $contact_result->fetch_assoc()) {
        $contacts[] = $row;
    }
    $stmt->close();
}

// Analytics data
$analytics_data = [
    'total_courses' => count($courses),
    'total_students' => array_sum(array_column($courses, 'students')),
    'total_assignments' => array_sum(array_column($courses, 'assignments')),
    'courses_breakdown' => [],
    'monthly_submissions' => []
];

if ($activeTab === 'analytics') {
    // Course breakdown
    foreach ($courses as $course) {
        $analytics_data['courses_breakdown'][] = [
            'name' => $course['name'],
            'students' => $course['students']
        ];
    }

    // Monthly submissions (last 6 months)
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(s.submitted_at, '%Y-%m') as month, COUNT(*) as count
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN courses c ON a.course_id = c.id
        WHERE c.instructor_id = ? AND s.submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $monthly_result = $stmt->get_result();
    while ($row = $monthly_result->fetch_assoc()) {
        $analytics_data['monthly_submissions'][] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - I-Acadsikatayo: Learning Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/faculty_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="modal_optimization.js"></script>
    <script src="responsive_dashboard.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <div>
                        <h3>Faculty Dashboard</h3>
                        <p>I-Acadsikatayo: LMS</p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="<?= $activeTab === 'overview' ? 'active' : '' ?>">
                        <a href="?tab=overview">
                            <i class="fas fa-chart-bar"></i>
                            <span>Overview</span>
                        </a>
                    </li>
                    <li class="<?= $activeTab === 'courses' ? 'active' : '' ?>">
                        <a href="?tab=courses">
                            <i class="fas fa-book"></i>
                            <span>My Subject</span>
                        </a>
                    </li>
                    <li class="<?= $activeTab === 'students' ? 'active' : '' ?>">
                        <a href="?tab=students">
                            <i class="fas fa-users"></i>
                            <span>Students</span>
                        </a>
                    </li>
                    <li class="<?= $activeTab === 'activity' ? 'active' : '' ?>">
                        <a href="?tab=activity">
                            <i class="fas fa-file-text"></i>
                            <span>Activity</span>
                        </a>
                    </li>
                    <li class="<?= $activeTab === 'schedule' ? 'active' : '' ?>">
                        <a href="?tab=schedule">
                            <i class="fas fa-calendar"></i>
                            <span>Schedule</span>
                        </a>
                    </li>
                    <li class="<?= $activeTab === 'messages' ? 'active' : '' ?>">
                        <a href="?tab=messages">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                        </a>
                    </li>
                    <li class="<?= $activeTab === 'analytics' ? 'active' : '' ?>">
                        <a href="?tab=analytics">
                            <i class="fas fa-chart-line"></i>
                            <span>Analytics</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sign Out</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Faculty Dashboard</h1>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search courses, students...">
                    </div>
                </div>

                <div class="header-right">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                    </button>
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="user-role">Faculty Member</span>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <?php if ($activeTab === 'overview'): ?>
                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon blue">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo count($courses); ?></h3>
                                <p>Total Courses</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon emerald">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo array_sum(array_column($courses, 'students')); ?></h3>
                                <p>Total Students</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon purple">
                                <i class="fas fa-file-text"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo array_sum(array_column($courses, 'assignments')); ?></h3>
                                <p>Total Assignments</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <h2>Quick Actions</h2>
                        <div class="actions-grid">
                            <button class="action-btn" data-bs-toggle="modal" data-bs-target="#addOnlineClassModal">
                                <i class="fas fa-video"></i>
                                <span>Create Online Class</span>
                            </button>
                            <button class="action-btn" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                                <i class="fas fa-file-text"></i>
                                <span>Create Assignment/Quiz</span>
                            </button>
                            <button class="action-btn" onclick="window.location.href='?tab=students'">
                                <i class="fas fa-users"></i>
                                <span>View Students</span>
                            </button>
                            <button class="action-btn" onclick="window.location.href='?tab=analytics'">
                                <i class="fas fa-chart-bar"></i>
                                <span>View Analytics</span>
                            </button>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="recent-activity">
                        <h2>Recent Activity</h2>
                        <div class="activity-list">
                            <?php if (empty($recent_activity)): ?>
                                <p class="text-muted">No recent activity.</p>
                            <?php else: ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon blue">
                                        <i class="fas fa-file-text"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p>Assignment submitted: <?php echo htmlspecialchars($activity['title']); ?></p>
                                        <span><?php echo htmlspecialchars($activity['student_name']); ?> • <?php echo htmlspecialchars($activity['course_name']); ?> • <?php echo date('M d, Y', strtotime($activity['submitted_at'])); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($activeTab === 'courses'): ?>
                    <div class="content-section">
                        <h2 class="mb-4"><i class="fas fa-book me-2"></i>My Subject</h2>
                        <p class="text-muted mb-4">Subject management is handled by administrators. You can view your assigned courses below.</p>
                        
                        <div class="row">
                            <?php if (empty($courses)): ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>No courses assigned yet.
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($courses as $course): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="course-card card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <h5 class="card-title"><?php echo htmlspecialchars($course['name']); ?></h5>
                                                <span class="badge bg-primary"><?php echo $course['students']; ?> Students</span>
                                            </div>
                                            <p class="card-text text-muted"><?php echo htmlspecialchars($course['description']); ?></p>
                                            <div class="d-flex justify-content-between mt-3">
                                                <span class="text-muted"><i class="fas fa-tasks me-1"></i><?php echo $course['assignments']; ?> Assignments</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($activeTab === 'students'): ?>
                    <div class="content-section">
                        <h2 class="mb-4"><i class="fas fa-users me-2"></i>My Students</h2>
                        
                        <?php if (empty($students)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>No students enrolled in your courses yet.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($students as $student): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100 shadow-sm border-0" style="border-left: 4px solid #3b82f6 !important;">
                                        <div class="card-body">
                                            <!-- Student Header -->
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                                    <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($student['name']); ?></h5>
                                                    <small class="text-muted"><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($student['email']); ?></small>
                                                </div>
                                            </div>
                                            
                                            <!-- Statistics -->
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <div class="text-center p-2" style="background: #f0f9ff; border-radius: 8px;">
                                                        <h4 class="mb-0 text-primary"><?php echo $student['course_count']; ?></h4>
                                                        <small class="text-muted">Courses</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-center p-2" style="background: #f0fdf4; border-radius: 8px;">
                                                        <h4 class="mb-0 text-success"><?php echo round($student['avg_progress']); ?>%</h4>
                                                        <small class="text-muted">Avg Progress</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Enrolled Courses -->
                                            <div class="mb-3">
                                                <h6 class="mb-2"><i class="fas fa-book me-2"></i>Enrolled Courses:</h6>
                                                <div class="courses-list" style="max-height: 200px; overflow-y: auto;">
                                                    <?php foreach ($student['courses'] as $course): ?>
                                                    <div class="mb-2 p-2" style="background: #f9fafb; border-radius: 6px; border-left: 3px solid #6366f1;">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <strong style="font-size: 0.9rem;"><?php echo htmlspecialchars($course['name']); ?></strong>
                                                            <span class="badge bg-primary"><?php echo $course['progress']; ?>%</span>
                                                        </div>
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $course['progress']; ?>%;" aria-valuenow="<?php echo $course['progress']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                        <small class="text-muted"><i class="fas fa-calendar-alt me-1"></i>Enrolled: <?php echo date('M d, Y', strtotime($course['enrolled_at'])); ?></small>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Actions -->
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-sm btn-outline-primary" onclick="alert('Message feature coming soon!')">
                                                    <i class="fas fa-envelope me-1"></i>Send Message
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($activeTab === 'activity'): ?>
                    <div class="content-section">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-clipboard-list me-2"></i>Assignments & Quizzes</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                                <i class="fas fa-plus me-2"></i>Create New
                            </button>
                        </div>
                        
                        <?php if ($addAssignmentSuccess): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($addAssignmentSuccess); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($addAssignmentErrors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <ul class="mb-0">
                                    <?php foreach ($addAssignmentErrors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($assignments)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>No assignments or quizzes created yet.
                            </div>
                        <?php else: ?>
                            <?php foreach ($assignments as $assignment): ?>
                            <div class="assignment-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h5><?php echo htmlspecialchars($assignment['title']); ?></h5>
                                        <p class="text-muted mb-2"><?php echo htmlspecialchars($assignment['description']); ?></p>
                                        <div class="d-flex gap-3 flex-wrap">
                                            <span class="text-muted"><i class="fas fa-book me-1"></i><?php echo htmlspecialchars($assignment['course_name']); ?></span>
                                            <span class="text-muted"><i class="fas fa-calendar me-1"></i>Due: <?php echo date('M d, Y g:i A', strtotime($assignment['due_date'])); ?></span>
                                            <span class="badge bg-info"><?php echo $assignment['submissions']; ?> Submissions</span>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 ms-3">
                                        <button class="btn btn-sm btn-outline-primary edit-assignment-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editAssignmentModal"
                                                data-id="<?php echo $assignment['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($assignment['title']); ?>"
                                                data-description="<?php echo htmlspecialchars($assignment['description']); ?>"
                                                data-course-id="<?php echo $assignment['course_id'] ?? ''; ?>"
                                                data-due-date="<?php echo date('Y-m-d\TH:i', strtotime($assignment['due_date'])); ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this assignment? All submissions will be lost.');">
                                            <input type="hidden" name="delete_assignment" value="1">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                <?php elseif ($activeTab === 'schedule'): ?>
                    <div class="content-section">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-calendar-alt me-2"></i>Class Schedule</h2>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addOnlineClassModal">
                                <i class="fas fa-plus me-2"></i>Schedule Online Class
                            </button>
                        </div>

                        <?php if (isset($_GET['class_created']) && $_GET['class_created'] == '1'): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i>Online class scheduled successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($online_classes)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>No online classes scheduled yet. Click the button above to schedule your first class!
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($online_classes as $class): ?>
                                    <?php
                                    $scheduled_time = strtotime($class['scheduled_at']);
                                    $is_upcoming = $scheduled_time > time();
                                    $is_today = date('Y-m-d', $scheduled_time) === date('Y-m-d');
                                    ?>
                                <div class="col-md-6 mb-3">
                                    <div class="schedule-item <?php echo $is_today ? 'border-warning' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($class['title']); ?></h5>
                                            <?php if ($is_today): ?>
                                                <span class="badge bg-warning text-dark">Today</span>
                                            <?php elseif ($is_upcoming): ?>
                                                <span class="badge bg-success">Upcoming</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Past</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-2"><strong>Course:</strong> <?php echo htmlspecialchars($class['course_name']); ?></p>
                                        <p class="mb-2">
                                            <i class="fas fa-clock me-2"></i>
                                            <?php echo date('l, F j, Y', $scheduled_time); ?> at <?php echo date('g:i A', $scheduled_time); ?>
                                        </p>
                                        <a href="<?php echo htmlspecialchars($class['meet_link']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="fas fa-video me-1"></i>Join Meeting
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($activeTab === 'messages'): ?>
                    <div class="content-section">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-envelope me-2"></i>Messages</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeMessageModal">
                                <i class="fas fa-pen me-2"></i>Compose Message
                            </button>
                        </div>

                        <?php if (isset($_GET['sent']) && $_GET['sent'] == '1'): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i>Message sent successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="fas fa-address-book me-2"></i>Contacts</h6>
                                    </div>
                                    <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                                        <?php foreach ($contacts as $contact): ?>
                                        <a href="#" class="list-group-item list-group-item-action contact-item" data-contact-id="<?php echo $contact['id']; ?>" data-contact-name="<?php echo htmlspecialchars($contact['name']); ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px; font-size: 0.9em;">
                                                    <?php echo strtoupper(substr($contact['name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($contact['name']); ?></div>
                                                    <small class="text-muted"><?php echo ucfirst($contact['role']); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-inbox me-2"></i>Message History</h6>
                                    </div>
                                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                        <?php if (empty($messages)): ?>
                                            <p class="text-muted text-center">No messages yet. Start a conversation!</p>
                                        <?php else: ?>
                                            <?php foreach ($messages as $message): ?>
                                            <div class="message-item <?php echo ($message['receiver_id'] == $_SESSION['user_id'] && !$message['is_read']) ? 'unread' : ''; ?>">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <strong>
                                                        <?php if ($message['sender_id'] == $_SESSION['user_id']): ?>
                                                            To: <?php echo htmlspecialchars($message['receiver_name']); ?>
                                                        <?php else: ?>
                                                            From: <?php echo htmlspecialchars($message['sender_name']); ?>
                                                        <?php endif; ?>
                                                    </strong>
                                                    <small class="text-muted"><?php echo date('M d, Y g:i A', strtotime($message['sent_at'])); ?></small>
                                                </div>
                                                <div class="mb-1"><strong>Subject:</strong> <?php echo htmlspecialchars($message['subject']); ?></div>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($activeTab === 'analytics'): ?>
                    <div class="content-section">
                        <h2 class="mb-4"><i class="fas fa-chart-line me-2"></i>Analytics Dashboard</h2>
                        
                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card text-white bg-primary">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Courses</h5>
                                        <h2><?php echo $analytics_data['total_courses']; ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-white bg-success">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Students</h5>
                                        <h2><?php echo $analytics_data['total_students']; ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-white bg-info">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Assignments</h5>
                                        <h2><?php echo $analytics_data['total_assignments']; ?></h2>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Students per Course</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="courseStudentsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Submission Trends (Last 6 Months)</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="submissionTrendsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Online Class Modal -->
    <div class="modal fade" id="addOnlineClassModal" tabindex="-1" aria-labelledby="addOnlineClassModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addOnlineClassModalLabel">Schedule Online Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <?php if (!empty($addOnlineClassErrors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($addOnlineClassErrors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="add_online_class" value="1">
                        <div class="mb-3">
                            <label for="class_title" class="form-label">Class Title</label>
                            <input type="text" class="form-control" id="class_title" name="class_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Course</label>
                            <select class="form-control" id="course_id" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="meet_link" class="form-label">Google Meet Link</label>
                            <input type="url" class="form-control" id="meet_link" name="meet_link" placeholder="https://meet.google.com/..." required>
                        </div>
                        <div class="mb-3">
                            <label for="scheduled_at" class="form-label">Scheduled Time</label>
                            <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Schedule Class</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Assignment/Quiz Modal -->
    <div class="modal fade" id="createAssignmentModal" tabindex="-1" aria-labelledby="createAssignmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createAssignmentModalLabel">Create Assignment/Quiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <?php if (!empty($addAssignmentErrors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($addAssignmentErrors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="add_assignment" value="1">
                        
                        <div class="mb-3">
                            <label for="assignment_type" class="form-label">Type *</label>
                            <select class="form-select" id="assignment_type" name="assignment_type" required>
                                <option value="">Select Type</option>
                                <option value="assignment">Assignment</option>
                                <option value="quiz">Quiz</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="assignment_title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="assignment_title" name="assignment_title" placeholder="Enter assignment/quiz title" required>
                        </div>

                        <div class="mb-3">
                            <label for="assignment_description" class="form-label">Description</label>
                            <textarea class="form-control" id="assignment_description" name="assignment_description" rows="4" placeholder="Enter detailed instructions..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="assignment_course_id" class="form-label">Course *</label>
                            <select class="form-select" id="assignment_course_id" name="assignment_course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="assignment_due_date" class="form-label">Due Date & Time *</label>
                            <input type="datetime-local" class="form-control" id="assignment_due_date" name="assignment_due_date" required>
                        </div>

                        <div class="mb-3">
                            <label for="assignment_file" class="form-label">Attach File (Optional)</label>
                            <input type="file" class="form-control" id="assignment_file" name="assignment_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt">
                            <small class="text-muted">Supported formats: PDF, DOC, DOCX, PPT, PPTX, TXT (Max 10MB)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Compose Message Modal -->
    <div class="modal fade" id="composeMessageModal" tabindex="-1" aria-labelledby="composeMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="composeMessageModalLabel">Compose Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <?php if (!empty($sendMessageErrors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($sendMessageErrors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="send_message" value="1">
                        
                        <div class="mb-3">
                            <label for="receiver_id" class="form-label">To *</label>
                            <select class="form-select" id="receiver_id" name="receiver_id" required>
                                <option value="">Select Recipient</option>
                                <?php foreach ($contacts as $contact): ?>
                                    <option value="<?php echo $contact['id']; ?>"><?php echo htmlspecialchars($contact['name']); ?> (<?php echo ucfirst($contact['role']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject *</label>
                            <input type="text" class="form-control" id="subject" name="subject" placeholder="Enter subject" required>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="6" placeholder="Type your message here..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Assignment Modal -->
    <div class="modal fade" id="editAssignmentModal" tabindex="-1" aria-labelledby="editAssignmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAssignmentModalLabel">Edit Assignment/Quiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="edit_assignment" value="1">
                        <input type="hidden" id="edit_assignment_id" name="edit_assignment_id" value="">
                        
                        <div class="mb-3">
                            <label for="edit_assignment_title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="edit_assignment_title" name="edit_assignment_title" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_assignment_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_assignment_description" name="edit_assignment_description" rows="4"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="edit_assignment_course_id" class="form-label">Course *</label>
                            <select class="form-select" id="edit_assignment_course_id" name="edit_assignment_course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_assignment_due_date" class="form-label">Due Date & Time *</label>
                            <input type="datetime-local" class="form-control" id="edit_assignment_due_date" name="edit_assignment_due_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Analytics Charts
        <?php if ($activeTab === 'analytics'): ?>
        // Course Students Chart
        const courseData = <?php echo json_encode($analytics_data['courses_breakdown']); ?>;
        const courseLabels = courseData.map(c => c.name);
        const courseValues = courseData.map(c => c.students);

        const ctx1 = document.getElementById('courseStudentsChart');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: courseLabels,
                datasets: [{
                    label: 'Number of Students',
                    data: courseValues,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
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

        // Submission Trends Chart
        const submissionData = <?php echo json_encode($analytics_data['monthly_submissions']); ?>;
        const submissionLabels = submissionData.map(s => {
            const date = new Date(s.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        const submissionValues = submissionData.map(s => s.count);

        const ctx2 = document.getElementById('submissionTrendsChart');
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: submissionLabels,
                datasets: [{
                    label: 'Submissions',
                    data: submissionValues,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
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
        <?php endif; ?>

        // Contact item click handler for messages
        document.querySelectorAll('.contact-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const contactId = this.getAttribute('data-contact-id');
                const contactName = this.getAttribute('data-contact-name');
                
                // Open compose modal with pre-filled recipient
                const modal = new bootstrap.Modal(document.getElementById('composeMessageModal'));
                document.getElementById('receiver_id').value = contactId;
                modal.show();
            });
        });

        // Edit assignment button handler
        document.querySelectorAll('.edit-assignment-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const title = this.getAttribute('data-title');
                const description = this.getAttribute('data-description');
                const courseId = this.getAttribute('data-course-id');
                const dueDate = this.getAttribute('data-due-date');
                
                document.getElementById('edit_assignment_id').value = id;
                document.getElementById('edit_assignment_title').value = title;
                document.getElementById('edit_assignment_description').value = description;
                document.getElementById('edit_assignment_course_id').value = courseId;
                document.getElementById('edit_assignment_due_date').value = dueDate;
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
