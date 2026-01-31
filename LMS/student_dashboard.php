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
    <link rel="stylesheet" href="admin_dashboard.css" />
    <script src="modal_optimization.js"></script>
    <script src="responsive_dashboard.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

        /* Modal Fixes */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            outline: 0;
        }

        .modal.show {
            display: block !important;
        }

        .modal-dialog {
            position: relative;
            width: auto;
            margin: 1.75rem auto;
            max-width: 500px;
        }

        .modal-dialog-lg {
            max-width: 800px;
        }

        .modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0,0,0,.2);
            border-radius: 0.5rem;
            outline: 0;
        }

        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            width: 100vw;
            height: 100vh;
            background-color: #000;
        }

        .modal-backdrop.show {
            opacity: 0.5;
        }

        /* Form Layout - Labels on Left */
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

        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }

        .user-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }



        /* Student Dashboard Specific Styles */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.2);
        }

        .welcome-content h2 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .welcome-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .welcome-section .btn-primary {
            background: white;
            color: var(--primary-color);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .welcome-section .btn-primary:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        .online-classes-section {
            margin-bottom: 30px;
        }

        .online-class-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .online-class-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .online-class-time {
            font-size: 0.9em;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .join-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .join-btn:hover {
            background: #f0f0f0;
            transform: scale(1.05);
            color: #667eea;
        }

        .pulse-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
            margin-right: 8px;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.1); }
        }

        .courses-section h2 {
            margin-bottom: 20px;
            color: var(--dark-bg);
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .course-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .course-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }

        .course-header {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
            padding: 20px;
            position: relative;
        }

        .course-header.blue { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
        .course-header.emerald { background: linear-gradient(135deg, #10b981, #34d399); }
        .course-header.purple { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }
        .course-header.orange { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
        .course-header.indigo { background: linear-gradient(135deg, #6366f1, #8b5cf6); }

        .course-info h3 {
            margin: 0;
            font-size: 1.3rem;
        }

        .course-info p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .course-body {
            padding: 20px;
        }

        .progress-section {
            margin-bottom: 15px;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), #6366f1);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .course-meta {
            margin-bottom: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .meta-item i {
            margin-right: 8px;
            width: 16px;
        }

        .course-card .btn-primary {
            width: 100%;
            background: var(--primary-color);
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .course-card .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-2px);
        }

        .assignments-section h2 {
            margin-bottom: 20px;
            color: var(--dark-bg);
        }

        .assignments-list {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .assignment-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.2s;
        }

        .assignment-item:last-child {
            border-bottom: none;
        }

        .assignment-item:hover {
            background: #f9fafb;
        }

        .assignment-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }

        .assignment-icon .completed { color: #10b981; }
        .assignment-icon .in-progress { color: #f59e0b; }
        .assignment-icon .pending { color: #ef4444; }

        .assignment-content h4 {
            margin: 0 0 5px 0;
            color: var(--dark-bg);
        }

        .assignment-content p {
            margin: 0;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .assignment-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .priority-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-badge.high { background: #fef2f2; color: #dc2626; }
        .priority-badge.medium { background: #fef3c7; color: #d97706; }
        .priority-badge.low { background: #f0fdf4; color: #16a34a; }

        .btn-link {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .btn-link:hover {
            background: #f3f4f6;
        }

        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #60a5fa); color: white; }
        .stat-icon.emerald { background: linear-gradient(135deg, #10b981, #34d399); color: white; }
        .stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #a78bfa); color: white; }

        .stat-content h3 {
            margin: 0;
            font-size: 2rem;
            color: var(--dark-bg);
        }

        .stat-content p {
            margin: 5px 0 0 0;
            color: #6b7280;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .welcome-content h2 {
                font-size: 2rem;
            }

            .courses-grid {
                grid-template-columns: 1fr;
            }

            .stats-section {
                grid-template-columns: 1fr;
            }

            .assignment-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .assignment-actions {
                margin-left: 0;
                width: 100%;
                justify-content: space-between;
            }
        }
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