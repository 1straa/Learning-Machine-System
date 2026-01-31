<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];

// Fetch online classes
$online_classes = [];
$stmt = $conn->prepare("
    SELECT oc.id, oc.title, oc.description, c.name as course_name, oc.scheduled_at, oc.meet_link, u.name as instructor_name
    FROM online_classes oc
    JOIN courses c ON oc.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    LEFT JOIN users u ON c.instructor_id = u.id
    WHERE e.student_id = ? AND oc.scheduled_at >= CURDATE()
    ORDER BY oc.scheduled_at ASC
");

if ($stmt) {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $online_classes[] = $row;
    }
    $stmt->close();
}

// Fetch assignments due this month
$assignments_due = [];
$stmt = $conn->prepare("
    SELECT a.id, a.title, c.name as course_name, a.due_date
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE e.student_id = ? AND s.id IS NULL AND a.due_date >= CURDATE()
    AND MONTH(a.due_date) = MONTH(CURDATE())
    ORDER BY a.due_date ASC
");

if ($stmt) {
    $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignments_due[] = $row;
    }
    $stmt->close();
}

// Get today's schedule
$today_schedule = array_filter($online_classes, function($class) {
    return date('Y-m-d', strtotime($class['scheduled_at'])) === date('Y-m-d');
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Schedule - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin_dashboard.css" />
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

        /* Schedule Specific Styles */
        .schedule-section h2 {
            margin-bottom: 20px;
            color: var(--dark-bg);
        }

        .calendar-grid {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: var(--primary-color);
            color: white;
        }

        .calendar-day-name {
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }

        .calendar-day {
            min-height: 100px;
            padding: 10px;
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            position: relative;
            cursor: pointer;
            transition: background 0.2s;
        }

        .calendar-day:hover {
            background: #f9fafb;
        }

        .calendar-day.other-month {
            background: #f9fafb;
            color: #9ca3af;
        }

        .calendar-day.today {
            background: #fef3c7;
        }

        .calendar-day-number {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .calendar-events {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .calendar-event-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .calendar-event-class { background: #3b82f6; }
        .calendar-event-assignment { background: #ef4444; }

        .event-tooltip {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 200px;
            top: 100%;
            left: 0;
        }

        .calendar-day:hover .event-tooltip {
            display: block;
        }

        .schedule-list-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .schedule-item {
            border-left: 4px solid;
            padding: 20px;
            margin-bottom: 15px;
            background: #f9fafb;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .schedule-item:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .schedule-item.class {
            border-left-color: #3b82f6;
        }

        .schedule-item.assignment {
            border-left-color: #ef4444;
        }

        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .schedule-nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .schedule-nav button {
            background: var(--primary-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .schedule-nav button:hover {
            background: #4338ca;
            transform: scale(1.1);
        }

        .schedule-nav h3 {
            margin: 0;
            color: var(--dark-bg);
        }

        .view-toggle {
            display: flex;
            gap: 10px;
        }

        .view-toggle button {
            padding: 8px 16px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .view-toggle button.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .view-toggle button:hover {
            background: #f3f4f6;
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

            .calendar-grid {
                overflow-x: auto;
            }

            .calendar-days {
                min-width: 700px;
            }

            .schedule-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .view-toggle {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
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
                        <a href="student_dashboard.php">
                            <i class="fas fa-book"></i>
                            <span>My Courses</span>
                        </a>
                    </li>
                    <li>
                        <a href="student_assignments.php">
                            <i class="fas fa-file-alt"></i>
                            <span>Assignments</span>
                        </a>
                    </li>
                    <li>
                        <a href="student_schedule.php" class="active">
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
                <a href="logout.php" class="btn btn-success"><i class="fas fa-sign-out-alt"></i>Sign Out</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Schedule</h1>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search schedule..." class="form-control" />
                    </div>
                </div>
                <div class="header-right">
                    <button class="notification-btn"><i class="fas fa-bell"></i></button>
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="user-role">Student</span>
                    </div>
                </div>
            </header>

            <div class="content">
                <div class="schedule-header">
                    <div class="schedule-nav">
                        <button onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                        <h3 id="currentMonth"><?php echo date('F Y'); ?></h3>
                        <button onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="view-toggle">
                        <button class="active">Month</button>
                        <button onclick="location.href='student_online_classes.php'">Classes</button>
                        <button onclick="location.href='student_assignments.php'">Assignments</button>
                    </div>
                </div>

                <div class="calendar-grid">
                    <div class="calendar-header">
                        <div class="calendar-day-name">Sun</div>
                        <div class="calendar-day-name">Mon</div>
                        <div class="calendar-day-name">Tue</div>
                        <div class="calendar-day-name">Wed</div>
                        <div class="calendar-day-name">Thu</div>
                        <div class="calendar-day-name">Fri</div>
                        <div class="calendar-day-name">Sat</div>
                    </div>
                    <div class="calendar-days" id="calendarDays">
                        <?php
                        $currentMonth = date('n');
                        $currentYear = date('Y');
                        $firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
                        $daysInMonth = date('t', $firstDay);
                        $dayOfWeek = date('w', $firstDay);
                        $today = date('j');

                        // Previous month days
                        for ($i = 0; $i < $dayOfWeek; $i++) {
                            echo '<div class="calendar-day other-month"></div>';
                        }

                        // Current month days
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            $isToday = ($day == $today) ? 'today' : '';
                            $currentDate = date('Y-m-d', mktime(0, 0, 0, $currentMonth, $day, $currentYear));
                            
                            // Check for events
                            $hasClass = false;
                            $hasAssignment = false;
                            $events = [];
                            
                            foreach ($online_classes as $class) {
                                if (date('Y-m-d', strtotime($class['scheduled_at'])) === $currentDate) {
                                    $hasClass = true;
                                    $events[] = ['type' => 'class', 'data' => $class];
                                }
                            }
                            
                            foreach ($assignments_due as $assignment) {
                                if (date('Y-m-d', strtotime($assignment['due_date'])) === $currentDate) {
                                    $hasAssignment = true;
                                    $events[] = ['type' => 'assignment', 'data' => $assignment];
                                }
                            }
                            
                            echo '<div class="calendar-day ' . $isToday . '" data-date="' . $currentDate . '">';
                            echo '<div class="calendar-day-number">' . $day . '</div>';
                            echo '<div class="calendar-events">';
                            if ($hasClass) echo '<div class="calendar-event-dot calendar-event-class"></div>';
                            if ($hasAssignment) echo '<div class="calendar-event-dot calendar-event-assignment"></div>';
                            echo '</div>';
                            
                            if (!empty($events)) {
                                echo '<div class="event-tooltip">';
                                foreach ($events as $event) {
                                    if ($event['type'] === 'class') {
                                        echo '<div class="mb-2"><strong><i class="fas fa-video text-primary"></i> ' . htmlspecialchars($event['data']['title']) . '</strong><br>';
                                        echo '<small>' . date('g:i A', strtotime($event['data']['scheduled_at'])) . '</small></div>';
                                    } else {
                                        echo '<div class="mb-2"><strong><i class="fas fa-tasks text-danger"></i> ' . htmlspecialchars($event['data']['title']) . '</strong><br>';
                                        echo '<small>Due: ' . date('g:i A', strtotime($event['data']['due_date'])) . '</small></div>';
                                    }
                                }
                                echo '</div>';
                            }
                            echo '</div>';
                        }

                        // Next month days
                        $remainingDays = 42 - ($daysInMonth + $dayOfWeek);
                        for ($i = 1; $i <= $remainingDays; $i++) {
                            echo '<div class="calendar-day other-month"></div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="schedule-list-section">
                    <h2>Today's Schedule - <?php echo date('l, F j, Y'); ?></h2>
                    <div class="schedule-day">
                        <div class="schedule-items">
                            <?php if (empty($today_schedule) && empty(array_filter($assignments_due, function($a) {
                                return date('Y-m-d', strtotime($a['due_date'])) === date('Y-m-d');
                            }))): ?>
                            <p style="text-align: center; color: #6b7280; padding: 2rem;">No schedule for today</p>
                            <?php else: ?>
                                <?php foreach ($today_schedule as $class): ?>
                                <div class="schedule-item class">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h5 class="mb-1"><i class="fas fa-video text-primary me-2"></i><?php echo htmlspecialchars($class['title']); ?></h5>
                                            <p class="text-muted mb-1"><?php echo htmlspecialchars($class['course_name']); ?> • <?php echo htmlspecialchars($class['instructor_name']); ?></p>
                                        </div>
                                        <span class="badge bg-primary"><?php echo date('g:i A', strtotime($class['scheduled_at'])); ?></span>
                                    </div>
                                    <?php if ($class['description']): ?>
                                    <p class="mb-2"><?php echo htmlspecialchars($class['description']); ?></p>
                                    <?php endif; ?>
                                    <a href="<?php echo htmlspecialchars($class['meet_link']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="fas fa-video me-1"></i>Join Meeting
                                    </a>
                                </div>
                                <?php endforeach; ?>

                                <?php foreach ($assignments_due as $assignment): ?>
                                    <?php if (date('Y-m-d', strtotime($assignment['due_date'])) === date('Y-m-d')): ?>
                                    <div class="schedule-item assignment">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-1"><i class="fas fa-tasks text-danger me-2"></i><?php echo htmlspecialchars($assignment['title']); ?></h5>
                                                <p class="text-muted mb-0"><?php echo htmlspecialchars($assignment['course_name']); ?> • Due: <?php echo date('g:i A', strtotime($assignment['due_date'])); ?></p>
                                            </div>
                                            <a href="student_assignments.php" class="btn btn-sm btn-danger">
                                                <i class="fas fa-arrow-right me-1"></i>View
                                            </a>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Upcoming This Week -->
                <div class="schedule-list-section">
                    <h2>Upcoming This Week</h2>
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3"><i class="fas fa-video text-primary me-2"></i>Online Classes</h5>
                            <?php
                            $week_classes = array_filter($online_classes, function($class) {
                                $class_date = strtotime($class['scheduled_at']);
                                $week_end = strtotime('+7 days');
                                return $class_date >= time() && $class_date <= $week_end;
                            });
                            ?>
                            <?php if (empty($week_classes)): ?>
                            <p class="text-muted">No classes scheduled this week</p>
                            <?php else: ?>
                            <?php foreach ($week_classes as $class): ?>
                            <div class="schedule-item class mb-2">
                                <strong><?php echo htmlspecialchars($class['title']); ?></strong><br>
                                <small><?php echo date('D, M j - g:i A', strtotime($class['scheduled_at'])); ?></small>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3"><i class="fas fa-tasks text-danger me-2"></i>Assignment Deadlines</h5>
                            <?php
                            $week_assignments = array_filter($assignments_due, function($assignment) {
                                $due_date = strtotime($assignment['due_date']);
                                $week_end = strtotime('+7 days');
                                return $due_date >= time() && $due_date <= $week_end;
                            });
                            ?>
                            <?php if (empty($week_assignments)): ?>
                            <p class="text-muted">No assignments due this week</p>
                            <?php else: ?>
                            <?php foreach ($week_assignments as $assignment): ?>
                            <div class="schedule-item assignment mb-2">
                                <strong><?php echo htmlspecialchars($assignment['title']); ?></strong><br>
                                <small><?php echo date('D, M j - g:i A', strtotime($assignment['due_date'])); ?></small>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();

        function changeMonth(direction) {
            currentMonth += direction;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            } else if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }

            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            document.getElementById('currentMonth').textContent = monthNames[currentMonth] + ' ' + currentYear;

            // Reload calendar (you'd need to implement AJAX to reload the calendar dynamically)
            location.href = `student_schedule.php?month=${currentMonth + 1}&year=${currentYear}`;
        }

        // Click on calendar day to see events
        document.querySelectorAll('.calendar-day').forEach(day => {
            day.addEventListener('click', function() {
                const date = this.dataset.date;
                if (date) {
                    // You can implement a modal or scroll to that date's schedule
                    console.log('Clicked date:', date);
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>