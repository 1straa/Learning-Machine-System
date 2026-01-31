<?php
session_start();
include 'config.php';

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch student's enrolled courses with progress
$courses = [];
$stmt = $conn->prepare("
    SELECT c.id, c.name, u.name as instructor, e.progress,
           CASE
               WHEN DAYOFWEEK(CURDATE()) = 1 THEN 'Next Week, 10:00 AM'
               WHEN DAYOFWEEK(CURDATE()) = 2 THEN 'Next Week, 10:00 AM'
               WHEN DAYOFWEEK(CURDATE()) = 3 THEN 'Next Week, 10:00 AM'
               WHEN DAYOFWEEK(CURDATE()) = 4 THEN 'Next Week, 10:00 AM'
               WHEN DAYOFWEEK(CURDATE()) = 5 THEN 'Next Week, 10:00 AM'
               ELSE 'Next Week, 10:00 AM'
           END as next_class
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    JOIN users u ON c.instructor_id = u.id
    WHERE e.student_id = ?
    ORDER BY c.created_at DESC
");
if (!$stmt) {
    die("Prepare failed (courses): " . $conn->error);
}
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$course_result = $stmt->get_result();
if ($course_result && $course_result->num_rows > 0) {
    $colors = ['blue', 'emerald', 'purple', 'orange', 'indigo'];
    $color_index = 0;
    while ($row = $course_result->fetch_assoc()) {
        $row['color'] = $colors[$color_index % count($colors)];
        $courses[] = $row;
        $color_index++;
    }
}
$stmt->close();

// Fetch assignments from enrolled courses
$assignments = [];
$stmt = $conn->prepare("
    SELECT a.id, a.title, c.name as course_name, a.due_date,
           CASE
               WHEN s.id IS NULL THEN 'pending'
               WHEN s.grade IS NOT NULL THEN 'completed'
               ELSE 'in-progress'
           END as status,
           CASE
               WHEN DATEDIFF(a.due_date, CURDATE()) <= 2 THEN 'high'
               WHEN DATEDIFF(a.due_date, CURDATE()) <= 7 THEN 'medium'
               ELSE 'low'
           END as priority
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE e.student_id = ?
    ORDER BY a.due_date ASC
    LIMIT 5
");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
if (!$stmt) {
    die("Prepare failed (assignments): " . $conn->error);
}
$stmt->execute();
$assignment_result = $stmt->get_result();
if ($assignment_result && $assignment_result->num_rows > 0) {
    while ($row = $assignment_result->fetch_assoc()) {
        $row['course'] = $row['course_name'];
        $due_days = max(0, (strtotime($row['due_date']) - time()) / (60*60*24));
        $row['due_date'] = $due_days <= 0 ? 'Overdue' : 'Due in ' . round($due_days) . ' days';
        unset($row['course_name']);
        $assignments[] = $row;
    }
}
$stmt->close();

// Fetch stats
$active_courses = count($courses);

// Fetch upcoming online classes for enrolled courses
$online_classes = [];
$stmt = $conn->prepare("
    SELECT oc.id, oc.title, c.name as course_name, oc.scheduled_at, oc.meet_link,
           u.name as instructor_name,
           c.id as course_id
    FROM online_classes oc
    JOIN courses c ON oc.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    JOIN users u ON c.instructor_id = u.id
    WHERE e.student_id = ? AND oc.scheduled_at > NOW()
    ORDER BY oc.scheduled_at ASC
    LIMIT 5
");
if (!$stmt) {
    die("Prepare failed (online_classes): " . $conn->error);
}
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$online_result = $stmt->get_result();
if ($online_result && $online_result->num_rows > 0) {
    while ($row = $online_result->fetch_assoc()) {
        $online_classes[] = $row;
    }
}
$stmt->close();

// Count assignments due this week
$due_this_week = 0;
foreach ($assignments as $assignment) {
    if (strpos($assignment['due_date'], 'Due in') !== false) {
        $days = (int)explode(' ', $assignment['due_date'])[2];
        if ($days <= 7) {
            $due_this_week++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Student Dashboard - I-Acadsikatayo LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/admin_dashboard.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <div>
                        <h3>Student Portal</h3>
                        <p>I-Acadsikatayo: LMS</p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="student_dashboard.php" class="active">
                            <i class="fas fa-book"></i>
                            <span>My Subjects</span>
                        </a>
                    </li>
                    <li>
                        <a href="student_assignments.php">
                            <i class="fas fa-file-alt"></i>
                            <span>Assignments</span>
                        </a>
                    </li>
                    <li>
                        <a href="student_schedule.php">
                            <i class="fas fa-calendar"></i>
                            <span>Schedule</span>
                        </a>
                    </li>
                    <li>
                        <a href="student_messages.php">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                        </a>
                    </li>
                    <li>
                        <a href="student_online_classes.php">
                            <i class="fas fa-video"></i>
                            <span>Online Classes</span>
                            <?php if (!empty($online_classes)): ?>
                            <span class="badge bg-danger ms-2"><?php echo count($online_classes); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="btn btn-success">
                    <i class="fas fa-sign-out-alt"></i>
                    Sign Out
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Student Dashboard</h1>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search courses, assignments..." class="form-control" />
                    </div>
                </div>

                <div class="header-right">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <?php if (!empty($online_classes)): ?>
                        <span class="notification-badge"><?php echo count($online_classes); ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="user-role">Student</span>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-content">
                        <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h2>
                        <p>You have <?php echo $active_courses; ?> courses in progress and <?php echo $due_this_week; ?> assignments due this week.</p>
                        <button class="btn-primary" onclick="window.location.href='student_schedule.php'">View Today's Schedule</button>
                    </div>
                </div>

                <!-- Upcoming Online Classes -->
                <?php if (!empty($online_classes)): ?>
                <div class="online-classes-section" style="margin-bottom: 2rem;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2>
                            <span class="pulse-dot"></span>
                            Upcoming Online Classes
                        </h2>
                        <a href="student_online_classes.php" class="btn btn-sm btn-outline-primary">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="row">
                        <?php foreach ($online_classes as $class): ?>
                        <div class="col-md-6 mb-3">
                            <div class="online-class-card">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h4 class="mb-1"><?php echo htmlspecialchars($class['title']); ?></h4>
                                        <div class="online-class-time">
                                            <i class="fas fa-calendar me-2"></i>
                                            <?php echo date('l, F j, Y', strtotime($class['scheduled_at'])); ?>
                                        </div>
                                        <div class="online-class-time">
                                            <i class="fas fa-clock me-2"></i>
                                            <?php echo date('g:i A', strtotime($class['scheduled_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="mb-2">
                                    <i class="fas fa-book me-2"></i><?php echo htmlspecialchars($class['course_name']); ?>
                                </p>
                                <p class="mb-3">
                                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($class['instructor_name']); ?>
                                </p>
                                <a href="<?php echo htmlspecialchars($class['meet_link']); ?>" target="_blank" class="join-btn d-inline-block text-decoration-none">
                                    <i class="fas fa-video me-2"></i>Join Meeting
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Courses Grid -->
                <div class="courses-section">
                    <h2>My Subjects</h2>
                    <div class="courses-grid">
                        <?php if (empty($courses)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>You are not enrolled in any courses yet.
                        </div>
                        <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-header <?php echo $course['color']; ?>">
                                <div class="course-info">
                                    <h3><?php echo htmlspecialchars($course['name']); ?></h3>
                                    <p><?php echo htmlspecialchars($course['instructor']); ?></p>
                                </div>
                            </div>
                            <div class="course-body">
                                <div class="progress-section">
                                    <div class="progress-info">
                                        <span>Progress</span>
                                        <span><?php echo $course['progress']; ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $course['progress']; ?>%"></div>
                                    </div>
                                </div>
                                <div class="course-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo htmlspecialchars($course['next_class']); ?></span>
                                    </div>
                                </div>
                                <button class="btn-primary">
                                    <i class="fas fa-play-circle"></i>
                                    Continue Learning
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Assignments -->
                <div class="assignments-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2>Upcoming Assignments</h2>
                        <a href="student_assignments.php" class="btn btn-sm btn-outline-primary">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="assignments-list">
                        <?php if (empty($assignments)): ?>
                        <div class="assignment-item">
                            <div class="assignment-content">
                                <p style="text-align: center; padding: 1rem; color: #6b7280;">No assignments at the moment</p>
                            </div>
                        </div>
                        <?php else: ?>
                        <?php foreach ($assignments as $assignment): ?>
                        <div class="assignment-item">
                            <div class="assignment-icon">
                                <?php
                                $icon_class = '';
                                switch ($assignment['status']) {
                                    case 'completed':
                                        $icon_class = 'fas fa-check-circle completed';
                                        break;
                                    case 'in-progress':
                                        $icon_class = 'fas fa-clock in-progress';
                                        break;
                                    default:
                                        $icon_class = 'fas fa-exclamation-circle pending';
                                }
                                ?>
                                <i class="<?php echo $icon_class; ?>"></i>
                            </div>
                            <div class="assignment-content">
                                <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                <p><?php echo htmlspecialchars($assignment['course']); ?> • <?php echo htmlspecialchars($assignment['due_date']); ?></p>
                            </div>
                            <div class="assignment-actions">
                                <span class="priority-badge <?php echo $assignment['priority']; ?>">
                                    <?php echo htmlspecialchars($assignment['priority']); ?>
                                </span>
                                <button class="btn-link">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="stats-section">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $active_courses; ?></h3>
                            <p>Active Subjects</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon emerald">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($assignments); ?></h3>
                            <p>Pending Assignments</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-video"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($online_classes); ?></h3>
                            <p>Upcoming Classes</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/student_dashboard.js"></script>
</body>
</html>
<?php
$conn->close();
?>