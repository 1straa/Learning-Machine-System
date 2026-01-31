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

// Fetch upcoming online classes for enrolled courses
$online_classes = [];
$stmt = $conn->prepare("
    SELECT oc.id, oc.title, c.name as course_name,
           oc.scheduled_at, oc.meet_link,
           u.name as instructor_name,
           c.id as course_id,
           CASE
               WHEN oc.scheduled_at <= NOW() AND DATE_ADD(oc.scheduled_at, INTERVAL 60 MINUTE) >= NOW()
               THEN 'live'
               WHEN oc.scheduled_at > NOW()
               THEN 'upcoming'
               ELSE 'ended'
           END as status
    FROM online_classes oc
    JOIN courses c ON oc.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    JOIN users u ON c.instructor_id = u.id
    WHERE e.student_id = ?
    ORDER BY
        CASE
            WHEN oc.scheduled_at <= NOW() AND DATE_ADD(oc.scheduled_at, INTERVAL 60 MINUTE) >= NOW()
            THEN 1
            WHEN oc.scheduled_at > NOW()
            THEN 2
            ELSE 3
        END,
        oc.scheduled_at ASC
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

// Count classes by status
$live_classes = array_filter($online_classes, fn($c) => $c['status'] === 'live');
$upcoming_classes = array_filter($online_classes, fn($c) => $c['status'] === 'upcoming');
$past_classes = array_filter($online_classes, fn($c) => $c['status'] === 'ended');

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Online Classes - I-Acadsikatayo LMS</title>
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
            --live-color: #ef4444;
            --upcoming-color: #3b82f6;
            --ended-color: #6b7280;
        }

        body {
            background: var(--light-bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.95;
            margin: 0;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-card.live .stat-icon {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .stat-card.upcoming .stat-icon {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .stat-card.past .stat-icon {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .stat-card h3 {
            font-size: 2rem;
            margin: 0;
            color: var(--dark-bg);
            font-weight: 700;
        }

        .stat-card p {
            margin: 5px 0 0 0;
            color: #6b7280;
            font-size: 0.95rem;
        }

        .filter-tabs {
            background: white;
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            color: #6b7280;
        }

        .filter-tab:hover {
            background: #f3f4f6;
        }

        .filter-tab.active {
            background: var(--primary-color);
            color: white;
        }

        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .class-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            position: relative;
        }

        .class-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }

        .class-card.live {
            border: 2px solid var(--live-color);
            animation: pulse-border 2s infinite;
        }

        @keyframes pulse-border {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
        }

        .class-status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
            z-index: 10;
        }

        .class-status-badge.live {
            background: var(--live-color);
            color: white;
        }

        .class-status-badge.upcoming {
            background: var(--upcoming-color);
            color: white;
        }

        .class-status-badge.ended {
            background: var(--ended-color);
            color: white;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }

        .class-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            color: white;
            position: relative;
        }

        .class-header h3 {
            font-size: 1.4rem;
            margin: 0 0 8px 0;
            font-weight: 600;
            padding-right: 100px;
        }

        .class-header .course-name {
            font-size: 0.9rem;
            opacity: 0.95;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .class-body {
            padding: 25px;
        }

        .class-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #4b5563;
            font-size: 0.95rem;
        }

        .info-item i {
            width: 20px;
            color: var(--primary-color);
        }

        .class-description {
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
        }

        .class-actions {
            display: flex;
            gap: 10px;
        }

        .btn-join {
            flex: 1;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-join:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .btn-join.live {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            animation: pulse-button 2s infinite;
        }

        .btn-join.live:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        @keyframes pulse-button {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        }

        .btn-details {
            padding: 12px 20px;
            background: #f3f4f6;
            color: var(--dark-bg);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-details:hover {
            background: #e5e7eb;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--dark-bg);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #6b7280;
        }

        .time-remaining {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #fef3c7;
            color: #92400e;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .classes-grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .filter-tabs {
                flex-direction: column;
            }

            .filter-tab {
                width: 100%;
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
                        <a href="student_online_classes.php" class="active">
                            <i class="fas fa-video"></i>
                            <span>Online Classes</span>
                            <?php if (count($live_classes) > 0): ?>
                            <span class="badge bg-danger ms-2"><?php echo count($live_classes); ?></span>
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
                    <h1>Online Classes</h1>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search classes..." class="form-control" />
                    </div>
                </div>

                <div class="header-right">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <?php if (count($live_classes) > 0): ?>
                        <span class="notification-badge"><?php echo count($live_classes); ?></span>
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
                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-video me-3"></i>Online Classes</h1>
                    <p>Join your live classes or view upcoming sessions</p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-cards">
                    <div class="stat-card live">
                        <div class="stat-icon">
                            <i class="fas fa-circle"></i>
                        </div>
                        <h3><?php echo count($live_classes); ?></h3>
                        <p>Live Now</p>
                    </div>
                    <div class="stat-card upcoming">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3><?php echo count($upcoming_classes); ?></h3>
                        <p>Upcoming</p>
                    </div>
                    <div class="stat-card past">
                        <div class="stat-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3><?php echo count($past_classes); ?></h3>
                        <p>Past Classes</p>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="all">
                        <i class="fas fa-th me-2"></i>All Classes
                    </button>
                    <button class="filter-tab" data-filter="live">
                        <i class="fas fa-circle me-2"></i>Live Now
                    </button>
                    <button class="filter-tab" data-filter="upcoming">
                        <i class="fas fa-clock me-2"></i>Upcoming
                    </button>
                    <button class="filter-tab" data-filter="ended">
                        <i class="fas fa-history me-2"></i>Past Classes
                    </button>
                </div>

                <!-- Classes Grid -->
                <div class="classes-grid" id="classesGrid">
                    <?php if (empty($online_classes)): ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <i class="fas fa-video-slash"></i>
                        <h3>No Online Classes</h3>
                        <p>You don't have any online classes scheduled at the moment.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($online_classes as $class): ?>
                    <div class="class-card <?php echo $class['status']; ?>" data-status="<?php echo $class['status']; ?>" data-title="<?php echo strtolower(htmlspecialchars($class['title'])); ?>" data-course="<?php echo strtolower(htmlspecialchars($class['course_name'])); ?>">
                        <div class="class-status-badge <?php echo $class['status']; ?>">
                            <?php if ($class['status'] === 'live'): ?>
                            <span class="pulse-dot"></span>LIVE NOW
                            <?php elseif ($class['status'] === 'upcoming'): ?>
                            <i class="fas fa-clock"></i>UPCOMING
                            <?php else: ?>
                            <i class="fas fa-check"></i>ENDED
                            <?php endif; ?>
                        </div>

                        <div class="class-header">
                            <h3><?php echo htmlspecialchars($class['title']); ?></h3>
                            <div class="course-name">
                                <i class="fas fa-book"></i>
                                <?php echo htmlspecialchars($class['course_name']); ?>
                            </div>
                        </div>

                        <div class="class-body">
                            <div class="class-info">
                                <div class="info-item">
                                    <i class="fas fa-user"></i>
                                    <span><?php echo htmlspecialchars($class['instructor_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('l, F j, Y', strtotime($class['scheduled_at'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('g:i A', strtotime($class['scheduled_at'])); ?></span>
                                    <?php if ($class['status'] === 'upcoming'): ?>
                                    <?php
                                    $time_diff = strtotime($class['scheduled_at']) - time();
                                    $hours = floor($time_diff / 3600);
                                    $minutes = floor(($time_diff % 3600) / 60);
                                    if ($hours < 24) {
                                        echo '<span class="time-remaining">';
                                        if ($hours > 0) echo "Starts in {$hours}h {$minutes}m";
                                        else echo "Starts in {$minutes}m";
                                        echo '</span>';
                                    }
                                    ?>
                                    <?php endif; ?>
                                </div>
                                </div>
                            </div>

                            <div class="class-actions">
                                <?php if ($class['status'] === 'live' || $class['status'] === 'upcoming'): ?>
                                <a href="<?php echo htmlspecialchars($class['meet_link']); ?>" target="_blank" class="btn-join <?php echo $class['status']; ?>">
                                    <i class="fas fa-video"></i>
                                    <?php echo $class['status'] === 'live' ? 'Join Now' : 'Join Meeting'; ?>
                                </a>
                                <?php else: ?>
                                <button class="btn-join" disabled style="opacity: 0.6; cursor: not-allowed;">
                                    <i class="fas fa-check"></i>
                                    Class Ended
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functionality
        const filterTabs = document.querySelectorAll('.filter-tab');
        const classCards = document.querySelectorAll('.class-card');
        const searchInput = document.getElementById('searchInput');

        filterTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Update active tab
                filterTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');

                // Filter cards
                classCards.forEach(card => {
                    if (filter === 'all' || card.getAttribute('data-status') === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Check if empty
                checkEmptyState();
            });
        });

        // Search functionality
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();

            classCards.forEach(card => {
                const title = card.getAttribute('data-title');
                const course = card.getAttribute('data-course');

                if (title.includes(searchTerm) || course.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            checkEmptyState();
        });

        function checkEmptyState() {
            const visibleCards = Array.from(classCards).filter(card => card.style.display !== 'none');
            const grid = document.getElementById('classesGrid');

            // Remove existing empty state
            const existingEmpty = grid.querySelector('.empty-state');
            if (existingEmpty) {
                existingEmpty.remove();
            }

            // Add empty state if no cards visible
            if (visibleCards.length === 0 && classCards.length > 0) {
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                emptyState.style.gridColumn = '1 / -1';
                emptyState.innerHTML = `
                    <i class="fas fa-search"></i>
                    <h3>No Classes Found</h3>
                    <p>No classes match your current filter or search.</p>
                `;
                grid.appendChild(emptyState);
            }
        }

        // Auto-refresh for live status updates (every 30 seconds)
        setInterval(function() {
            location.reload();
        }, 30000);

        // Notification for upcoming classes
        window.addEventListener('load', function() {
            <?php if (count($live_classes) > 0): ?>
            if (Notification.permission === 'granted') {
                new Notification('Live Class Available!', {
                    body: 'You have <?php echo count($live_classes); ?> live class(es) happening now. Join now!',
                    icon: '/path/to/icon.png'
                });
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>