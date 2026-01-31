<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];
$success_message = '';

// Handle send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    
    if ($receiver_id && $subject && $message_text) {
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? AND role = 'faculty'");
        $stmt->bind_param("i", $receiver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, sent_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiss", $_SESSION['user_id'], $receiver_id, $subject, $message_text);
            if ($stmt->execute()) {
                $success_message = "Message sent successfully!";
            }
            $stmt->close();
        }
    }
}

// Mark message as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $message_id = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
    $stmt->bind_param("ii", $message_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

// Fetch faculty members
$faculty_members = [];
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.email
    FROM users u
    JOIN courses c ON u.id = c.instructor_id
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ? AND u.role = 'faculty'
    ORDER BY u.name
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$faculty_result = $stmt->get_result();
while ($row = $faculty_result->fetch_assoc()) {
    $faculty_members[] = $row;
}
$stmt->close();

// Fetch all messages
$messages = [];
$stmt = $conn->prepare("
    SELECT m.id, m.subject, m.message, m.sent_at, m.is_read, m.sender_id, m.receiver_id,
           u1.name as sender_name, u2.name as receiver_name
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

// Get unread count
$unread_count = 0;
foreach ($messages as $msg) {
    if ($msg['receiver_id'] == $_SESSION['user_id'] && !$msg['is_read']) {
        $unread_count++;
    }
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Messages - Student Dashboard</title>
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

        .notification-btn {
            position: relative;
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

        /* Messages specific styles */
        .message-item { padding: 15px; border-left: 3px solid #007bff; background: #f8f9fa; border-radius: 8px; margin-bottom: 10px; cursor: pointer; }
        .message-item.unread { background: #e7f3ff; font-weight: 600; border-left-color: #28a745; }
        .message-item:hover { background: #e9ecef; }
        .faculty-list { max-height: 400px; overflow-y: auto; }
        .faculty-item { padding: 12px; border-bottom: 1px solid #dee2e6; cursor: pointer; transition: background 0.2s; }
        .faculty-item:hover { background: #f8f9fa; }
        .message-detail { background: white; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6; }

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
                    <li><a href="student_dashboard.php"><i class="fas fa-book"></i><span>My Courses</span></a></li>
                    <li><a href="student_assignments.php"><i class="fas fa-file-alt"></i><span>Assignments</span></a></li>
                    <li><a href="student_schedule.php"><i class="fas fa-calendar"></i><span>Schedule</span></a></li>
                    <li><a href="student_messages.php" class="active"><i class="fas fa-envelope"></i><span>Messages</span><?php if($unread_count > 0): ?><span class="badge bg-danger ms-2"><?php echo $unread_count; ?></span><?php endif; ?></a></li>
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
                    <h1>Messages</h1>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search messages..." class="form-control" id="searchMessages" />
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

            <div class="content">
                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Faculty Members</h6>
                                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#composeModal">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                            <div class="faculty-list">
                                <?php if (empty($faculty_members)): ?>
                                <p class="text-muted text-center p-3">No faculty members found</p>
                                <?php else: ?>
                                <?php foreach ($faculty_members as $faculty): ?>
                                <div class="faculty-item" onclick="filterMessagesByFaculty(<?php echo $faculty['id']; ?>)">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                            <?php echo strtoupper(substr($faculty['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($faculty['name']); ?></div>
                                            <small class="text-muted">Instructor</small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fas fa-inbox me-2"></i>Messages (<?php echo count($messages); ?>)</h6>
                                <button class="btn btn-sm btn-primary" onclick="showAllMessages()">Show All</button>
                            </div>
                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                <?php if (empty($messages)): ?>
                                <p class="text-muted text-center">No messages yet</p>
                                <?php else: ?>
                                <div id="messagesList">
                                    <?php foreach ($messages as $msg): ?>
                                    <div class="message-item <?php echo ($msg['receiver_id'] == $_SESSION['user_id'] && !$msg['is_read']) ? 'unread' : ''; ?>" 
                                         data-message-id="<?php echo $msg['id']; ?>"
                                         data-sender-id="<?php echo $msg['sender_id']; ?>"
                                         data-receiver-id="<?php echo $msg['receiver_id']; ?>"
                                         onclick="viewMessage(<?php echo $msg['id']; ?>)">
                                        <div class="d-flex justify-content-between mb-1">
                                            <strong>
                                                <?php if ($msg['sender_id'] == $_SESSION['user_id']): ?>
                                                    <i class="fas fa-paper-plane text-primary me-1"></i> To: <?php echo htmlspecialchars($msg['receiver_name']); ?>
                                                <?php else: ?>
                                                    <i class="fas fa-envelope text-success me-1"></i> From: <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                <?php endif; ?>
                                            </strong>
                                            <small class="text-muted"><?php echo date('M d, Y g:i A', strtotime($msg['sent_at'])); ?></small>
                                        </div>
                                        <div class="mb-1"><strong><?php echo htmlspecialchars($msg['subject']); ?></strong></div>
                                        <p class="mb-0 text-muted"><?php echo htmlspecialchars(substr($msg['message'], 0, 80)); ?>...</p>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Compose Message Modal -->
    <div class="modal fade" id="composeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Compose Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="send_message" value="1">
                        <div class="mb-3">
                            <label class="form-label">To (Faculty Member) *</label>
                            <select class="form-select" name="receiver_id" required>
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculty_members as $faculty): ?>
                                <option value="<?php echo $faculty['id']; ?>"><?php echo htmlspecialchars($faculty['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject *</label>
                            <input type="text" class="form-control" name="subject" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message *</label>
                            <textarea class="form-control" name="message" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Message Modal -->
    <div class="modal fade" id="viewMessageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewMessageTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewMessageContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="replyBtn"><i class="fas fa-reply"></i> Reply</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const messages = <?php echo json_encode($messages); ?>;
        let currentMessageId = null;

        function viewMessage(messageId) {
            const message = messages.find(m => m.id == messageId);
            if (!message) return;
            
            currentMessageId = messageId;
            document.getElementById('viewMessageTitle').textContent = message.subject;
            
            const isSent = message.sender_id == <?php echo $_SESSION['user_id']; ?>;
            const content = `
                <div class="message-detail">
                    <p><strong>${isSent ? 'To' : 'From'}:</strong> ${isSent ? message.receiver_name : message.sender_name}</p>
                    <p><strong>Date:</strong> ${new Date(message.sent_at).toLocaleString()}</p>
                    <hr>
                    <p style="white-space: pre-wrap;">${message.message}</p>
                </div>
            `;
            
            document.getElementById('viewMessageContent').innerHTML = content;
            
            const replyBtn = document.getElementById('replyBtn');
            if (isSent) {
                replyBtn.style.display = 'none';
            } else {
                replyBtn.style.display = 'block';
                replyBtn.onclick = () => replyToMessage(message);
            }
            
            const modal = new bootstrap.Modal(document.getElementById('viewMessageModal'));
            modal.show();
            
            if (!isSent && !message.is_read) {
                fetch(`student_messages.php?mark_read=${messageId}`).then(() => {
                    location.reload();
                });
            }
        }

        function replyToMessage(message) {
            const composeModal = new bootstrap.Modal(document.getElementById('composeModal'));
            document.querySelector('[name="receiver_id"]').value = message.sender_id;
            document.querySelector('[name="subject"]').value = 'Re: ' + message.subject;
            
            const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewMessageModal'));
            viewModal.hide();
            composeModal.show();
        }

        function filterMessagesByFaculty(facultyId) {
            const items = document.querySelectorAll('.message-item');
            items.forEach(item => {
                const senderId = parseInt(item.dataset.senderId);
                const receiverId = parseInt(item.dataset.receiverId);
                
                if (senderId === facultyId || receiverId === facultyId) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function showAllMessages() {
            document.querySelectorAll('.message-item').forEach(item => {
                item.style.display = 'block';
            });
        }

        document.getElementById('searchMessages').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            document.querySelectorAll('.message-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(search) ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>