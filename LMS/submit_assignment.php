<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit();
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignment_id = intval($_POST['assignment_id'] ?? 0);
    $student_id = $_SESSION['user_id'];
    
    // Check if assignment exists and student is enrolled
    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.due_date, c.id as course_id
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        WHERE a.id = ? AND e.student_id = ?
    ");
    $stmt->bind_param("ii", $assignment_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $response['message'] = 'Invalid assignment or you are not enrolled in this course.';
        echo json_encode($response);
        exit();
    }
    
    $assignment = $result->fetch_assoc();
    $stmt->close();
    
    // Check if already submitted
    $stmt = $conn->prepare("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $assignment_id, $student_id);
    $stmt->execute();
    $existing = $stmt->get_result();
    
    if ($existing->num_rows > 0) {
        $response['message'] = 'You have already submitted this assignment.';
        echo json_encode($response);
        exit();
    }
    $stmt->close();
    
    // Handle file upload
    $file_path = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/submissions/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = $student_id . '_' . $assignment_id . '_' . time() . '_' . basename($_FILES['submission_file']['name']);
        $file_path = $upload_dir . $file_name;
        
        if (!move_uploaded_file($_FILES['submission_file']['tmp_name'], $file_path)) {
            $response['message'] = 'Failed to upload file.';
            echo json_encode($response);
            exit();
        }
    }
    
    $submission_text = trim($_POST['submission_text'] ?? '');
    
    // Insert submission
    $stmt = $conn->prepare("
        INSERT INTO submissions (assignment_id, student_id, submission_text, file_path, submitted_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiss", $assignment_id, $student_id, $submission_text, $file_path);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Assignment submitted successfully!';
    } else {
        $response['message'] = 'Failed to submit assignment: ' . $conn->error;
    }
    $stmt->close();
}

echo json_encode($response);
$conn->close();
?>