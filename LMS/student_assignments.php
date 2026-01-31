<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];

// Fetch assignments from enrolled courses
$assignments = [];
$stmt = $conn->prepare("
    SELECT a.id, a.title, COALESCE(a.description, 'No description provided') as description, 
           c.name as course_name, a.due_date,
           CASE
               WHEN s.id IS NULL THEN 'pending'
               WHEN s.grade IS NOT NULL THEN 'completed'
               ELSE 'submitted'
           END as status,
           s.grade, s.submitted_at, s.file_path, s.submission_text,
           CASE
               WHEN DATEDIFF(a.due_date, CURDATE()) < 0 THEN 'overdue'
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
");

if ($stmt) {
    $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute();
    $assignment_result = $stmt->get_result();
    if ($assignment_result && $assignment_result->num_rows > 0) {
        while ($row = $assignment_result->fetch_assoc()) {
            $row['course'] = $row['course_name'];
            $due_days = (strtotime($row['due_date']) - time()) / (60*60*24);
            if ($due_days < 0) {
                $row['due_text'] = 'Overdue by ' . abs(round($due_days)) . ' days';
            } else {
                $row['due_text'] = 'Due in ' . round($due_days) . ' days';
            }
            $row['due_date_formatted'] = date('M j, Y g:i A', strtotime($row['due_date']));
            $row['total_points'] = 100;
            unset($row['course_name']);
            $assignments[] = $row;
        }
    }
    $stmt->close();
}

$pending_assignments = array_filter($assignments, function($a) { return $a['status'] === 'pending'; });
$submitted_assignments = array_filter($assignments, function($a) { return $a['status'] === 'submitted'; });
$completed_assignments = array_filter($assignments, function($a) { return $a['status'] === 'completed'; });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Assignments - Student Dashboard</title>
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

        /* Assignments Specific Styles */
        .assignments-section h2 {
            margin-bottom: 20px;
            color: var(--dark-bg);
        }

        .assignments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .assignment-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .assignment-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }

        .assignment-card-header {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .assignment-card-course {
            font-weight: 600;
            font-size: 0.9rem;
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
        .priority-badge.overdue { background: #fee2e2; color: #dc2626; }

        .assignment-card-body {
            padding: 20px;
        }

        .assignment-card-body h3 {
            margin: 0 0 10px 0;
            color: var(--dark-bg);
            font-size: 1.2rem;
        }

        .assignment-card-body p {
            margin: 0 0 15px 0;
            color: #6b7280;
            line-height: 1.5;
        }

        .assignment-card-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .meta-item i {
            margin-right: 5px;
        }

        .assignment-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .due-text {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .due-text.high { color: #dc2626; }
        .due-text.medium { color: #d97706; }
        .due-text.low { color: #16a34a; }
        .due-text.overdue { color: #dc2626; }

        .assignment-card .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .assignment-card .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-2px);
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

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.submitted { background: #dbeafe; color: #2563eb; }

        .grade-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
        }

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

        .filter-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .filter-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .assignments-grid {
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

            .assignment-card-footer {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .filter-buttons {
                flex-wrap: wrap;
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
                    <li><a href="student_dashboard.php"><i class="fas fa-book"></i><span>My Courses</span></a></li>
                    <li><a href="student_assignments.php" class="active"><i class="fas fa-file-alt"></i><span>Assignments</span></a></li>
                    <li><a href="student_schedule.php"><i class="fas fa-calendar"></i><span>Schedule</span></a></li>
                    <li><a href="student_messages.php"><i class="fas fa-envelope"></i><span>Messages</span></a></li>
                    <li><a href="student_online_classes.php"><i class="fas fa-video"></i><span>Online Classes</span></a></li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="btn btn-success"><i class="fas fa-sign-out-alt"></i>Sign Out</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Assignments</h1>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search assignments..." class="form-control" id="searchInput" />
                    </div>
                </div>

                <div class="header-right">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge"></span>
                    </button>
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="user-role">Student</span>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <!-- Stats Overview -->
                <div class="stats-section">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($pending_assignments); ?></h3>
                            <p>Pending Assignments</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon emerald">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($submitted_assignments); ?></h3>
                            <p>Submitted</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($completed_assignments); ?></h3>
                            <p>Graded</p>
                        </div>
                    </div>
                </div>

                <!-- Pending Assignments -->
                <div class="assignments-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2 style="margin: 0;">Pending Assignments</h2>
                        <div class="filter-buttons">
                            <button class="filter-btn active" data-filter="all">All</button>
                            <button class="filter-btn" data-filter="high">High Priority</button>
                            <button class="filter-btn" data-filter="week">This Week</button>
                        </div>
                    </div>
                    <div class="assignments-grid" id="pendingAssignments">
                        <?php if (empty($pending_assignments)): ?>
                        <div class="assignment-card">
                            <div class="assignment-card-body">
                                <p style="text-align: center; color: #6b7280; padding: 2rem;">No pending assignments</p>
                            </div>
                        </div>
                        <?php else: ?>
                        <?php foreach ($pending_assignments as $assignment): ?>
                        <div class="assignment-card" data-priority="<?php echo $assignment['priority']; ?>">
                            <div class="assignment-card-header">
                                <div class="assignment-card-course"><?php echo htmlspecialchars($assignment['course']); ?></div>
                                <span class="priority-badge <?php echo $assignment['priority']; ?>">
                                    <?php echo ucfirst($assignment['priority']); ?>
                                </span>
                            </div>
                            <div class="assignment-card-body">
                                <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($assignment['description'], 0, 100)); ?>...</p>
                                <div class="assignment-card-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo $assignment['due_date_formatted']; ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-star"></i>
                                        <span><?php echo $assignment['total_points']; ?> points</span>
                                    </div>
                                </div>
                                <div class="assignment-card-footer">
                                    <span class="due-text <?php echo $assignment['priority']; ?>"><?php echo $assignment['due_text']; ?></span>
                                    <button class="btn-primary submit-btn" data-assignment-id="<?php echo $assignment['id']; ?>" data-assignment-title="<?php echo htmlspecialchars($assignment['title']); ?>">
                                        <i class="fas fa-upload"></i>
                                        Submit Assignment
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Submitted Assignments -->
                <?php if (!empty($submitted_assignments)): ?>
                <div class="assignments-section">
                    <h2>Submitted Assignments</h2>
                    <div class="assignments-list">
                        <?php foreach ($submitted_assignments as $assignment): ?>
                        <div class="assignment-item">
                            <div class="assignment-icon">
                                <i class="fas fa-clock" style="color: #3b82f6; background-color: #dbeafe; padding: 0.625rem; border-radius: 50%;"></i>
                            </div>
                            <div class="assignment-content">
                                <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                <p><?php echo htmlspecialchars($assignment['course']); ?> • Submitted on <?php echo date('M j, Y', strtotime($assignment['submitted_at'])); ?></p>
                            </div>
                            <div class="assignment-actions">
                                <span class="status-badge submitted">Awaiting Grade</span>
                                <button class="btn-link view-submission-btn" data-assignment='<?php echo json_encode($assignment); ?>'>
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Graded Assignments -->
                <?php if (!empty($completed_assignments)): ?>
                <div class="assignments-section">
                    <h2>Graded Assignments</h2>
                    <div class="assignments-list">
                        <?php foreach ($completed_assignments as $assignment): ?>
                        <div class="assignment-item">
                            <div class="assignment-icon">
                                <i class="fas fa-check-circle" style="color: #10b981; background-color: #d1fae5; padding: 0.625rem; border-radius: 50%;"></i>
                            </div>
                            <div class="assignment-content">
                                <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                <p><?php echo htmlspecialchars($assignment['course']); ?> • Grade: <?php echo $assignment['grade']; ?>/<?php echo $assignment['total_points']; ?></p>
                            </div>
                            <div class="assignment-actions">
                                <span class="grade-badge"><?php echo round(($assignment['grade'] / $assignment['total_points']) * 100); ?>%</span>
                                <button class="btn-link view-submission-btn" data-assignment='<?php echo json_encode($assignment); ?>'>
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Submit Assignment Modal -->
    <div class="modal fade" id="submitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="submitForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="assignment_id" id="assignmentId">
                        <h6 id="assignmentTitle" class="mb-3"></h6>
                        
                        <div class="mb-3">
                            <label class="form-label">Submission Text</label>
                            <textarea class="form-control" name="submission_text" rows="5" placeholder="Enter your submission text here..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Upload File (Optional)</label>
                            <input type="file" class="form-control" name="submission_file" accept=".pdf,.doc,.docx,.txt,.zip">
                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX, TXT, ZIP (Max 10MB)</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Make sure to review your submission before submitting. You cannot edit after submission.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Submission Modal -->
    <div class="modal fade" id="viewSubmissionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">View Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="submissionContent">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Submit button handlers
        document.querySelectorAll('.submit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const assignmentId = this.dataset.assignmentId;
                const assignmentTitle = this.dataset.assignmentTitle;
                
                document.getElementById('assignmentId').value = assignmentId;
                document.getElementById('assignmentTitle').textContent = assignmentTitle;
                
                const modal = new bootstrap.Modal(document.getElementById('submitModal'));
                modal.show();
            });
        });

        // Submit form handler
        document.getElementById('submitForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            
            try {
                const response = await fetch('submit_assignment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Assignment';
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Assignment';
            }
        });

        // View submission handlers
        document.querySelectorAll('.view-submission-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const assignment = JSON.parse(this.dataset.assignment);
                let content = `
                    <h6><strong>Assignment:</strong> ${assignment.title}</h6>
                    <p><strong>Course:</strong> ${assignment.course}</p>
                    <p><strong>Submitted:</strong> ${new Date(assignment.submitted_at).toLocaleString()}</p>
                    <hr>
                `;
                
                if (assignment.submission_text) {
                    content += `<h6>Submission Text:</h6><p>${assignment.submission_text}</p>`;
                }
                
                if (assignment.file_path) {
                    content += `<h6>Attached File:</h6><p><a href="${assignment.file_path}" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-download"></i> Download File</a></p>`;
                }
                
                if (assignment.grade) {
                    content += `<hr><h6>Grade:</h6><p class="text-success"><strong>${assignment.grade}/${assignment.total_points}</strong> (${Math.round((assignment.grade / assignment.total_points) * 100)}%)</p>`;
                    
                    if (assignment.feedback) {
                        content += `<h6>Feedback:</h6><p>${assignment.feedback}</p>`;
                    }
                }
                
                document.getElementById('submissionContent').innerHTML = content;
                const modal = new bootstrap.Modal(document.getElementById('viewSubmissionModal'));
                modal.show();
            });
        });

        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const cards = document.querySelectorAll('#pendingAssignments .assignment-card');
                
                cards.forEach(card => {
                    if (filter === 'all') {
                        card.style.display = 'block';
                    } else if (filter === 'high') {
                        card.style.display = card.dataset.priority === 'high' || card.dataset.priority === 'overdue' ? 'block' : 'none';
                    } else if (filter === 'week') {
                        const dueText = card.querySelector('.due-text').textContent;
                        const days = parseInt(dueText.match(/\d+/));
                        card.style.display = days <= 7 ? 'block' : 'none';
                    }
                });
            });
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.assignment-card');
            
            cards.forEach(card => {
                const title = card.querySelector('h3').textContent.toLowerCase();
                const course = card.querySelector('.assignment-card-course').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || course.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>