<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_unread_count':
        // Get count of unread messages
        $admin_id = $_SESSION['user_id'];
        $result = $conn->query("
            SELECT COUNT(*) as count 
            FROM messages 
            WHERE receiver_id = $admin_id AND is_read = 0
        ");
        $row = $result->fetch_assoc();
        echo json_encode(['success' => true, 'count' => $row['count']]);
        break;

    case 'get_recent_messages':
        // Get recent unread messages for notification dropdown
        $admin_id = $_SESSION['user_id'];
        $result = $conn->query("
            SELECT m.id, m.subject, m.message, m.sent_at, m.is_read,
                   u.name as sender_name, u.role as sender_role
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = $admin_id
            ORDER BY m.sent_at DESC
            LIMIT 5
        ");
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        break;

    case 'get_all_messages':
        // Get all messages with filters
        $admin_id = $_SESSION['user_id'];
        $filter = $_GET['filter'] ?? 'all';
        $search = $_GET['search'] ?? '';
        
        $where = "WHERE m.receiver_id = $admin_id";
        
        if ($filter === 'unread') {
            $where .= " AND m.is_read = 0";
        } elseif ($filter === 'read') {
            $where .= " AND m.is_read = 1";
        } elseif ($filter === 'student') {
            $where .= " AND u.role = 'student'";
        } elseif ($filter === 'faculty') {
            $where .= " AND u.role = 'faculty'";
        }
        
        if ($search) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (u.name LIKE '%$search%' OR m.subject LIKE '%$search%' OR m.message LIKE '%$search%')";
        }
        
        $result = $conn->query("
            SELECT m.id, m.subject, m.message, m.sent_at, m.is_read,
                   u.id as sender_id, u.name as sender_name, u.role as sender_role, u.email as sender_email
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            $where
            ORDER BY m.sent_at DESC
        ");
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        break;

    case 'get_message':
        // Get single message details with replies
        $message_id = intval($_GET['id'] ?? 0);
        $admin_id = $_SESSION['user_id'];
        
        $result = $conn->query("
            SELECT m.*, u.name as sender_name, u.role as sender_role, u.email as sender_email
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.id = $message_id AND m.receiver_id = $admin_id
        ");
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Message not found']);
            break;
        }
        
        $message = $result->fetch_assoc();
        
        // Get replies
        $replies_result = $conn->query("
            SELECT r.*, u.name as sender_name, u.role as sender_role
            FROM message_replies r
            JOIN users u ON r.sender_id = u.id
            WHERE r.message_id = $message_id
            ORDER BY r.sent_at ASC
        ");
        
        $replies = [];
        while ($row = $replies_result->fetch_assoc()) {
            $replies[] = $row;
        }
        
        $message['replies'] = $replies;
        
        // Mark as read
        $conn->query("UPDATE messages SET is_read = 1 WHERE id = $message_id");
        
        echo json_encode(['success' => true, 'message' => $message]);
        break;

    case 'send_reply':
        // Send reply to a message
        $message_id = intval($_POST['message_id'] ?? 0);
        $reply_text = trim($_POST['reply'] ?? '');
        $admin_id = $_SESSION['user_id'];
        
        if (!$reply_text) {
            echo json_encode(['success' => false, 'message' => 'Reply cannot be empty']);
            break;
        }
        
        // Get original message sender
        $result = $conn->query("SELECT sender_id FROM messages WHERE id = $message_id");
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Message not found']);
            break;
        }
        
        $original_message = $result->fetch_assoc();
        $receiver_id = $original_message['sender_id'];
        
        // Insert reply
        $stmt = $conn->prepare("
            INSERT INTO message_replies (message_id, sender_id, receiver_id, reply, sent_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iiis", $message_id, $admin_id, $receiver_id, $reply_text);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send reply']);
        }
        $stmt->close();
        break;

    case 'send_message':
        // Send new message to student or faculty
        $receiver_id = intval($_POST['receiver_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $message_text = trim($_POST['message'] ?? '');
        $admin_id = $_SESSION['user_id'];
        
        if (!$receiver_id || !$subject || !$message_text) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            break;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, subject, message, sent_at, is_read)
            VALUES (?, ?, ?, ?, NOW(), 0)
        ");
        $stmt->bind_param("iiss", $admin_id, $receiver_id, $subject, $message_text);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send message']);
        }
        $stmt->close();
        break;

    case 'mark_as_read':
        // Mark message as read
        $message_id = intval($_POST['id'] ?? 0);
        $admin_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("
            UPDATE messages SET is_read = 1 
            WHERE id = ? AND receiver_id = ?
        ");
        $stmt->bind_param("ii", $message_id, $admin_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark as read']);
        }
        $stmt->close();
        break;

    case 'delete_message':
        // Delete message
        $message_id = intval($_POST['id'] ?? 0);
        $admin_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("
            DELETE FROM messages WHERE id = ? AND receiver_id = ?
        ");
        $stmt->bind_param("ii", $message_id, $admin_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Message deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete message']);
        }
        $stmt->close();
        break;

    case 'get_users':
        // Get list of students and faculty for new message
        $result = $conn->query("
            SELECT id, name, email, role 
            FROM users 
            WHERE role IN ('student', 'faculty')
            ORDER BY role, name
        ");
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        echo json_encode(['success' => true, 'users' => $users]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
$conn->close();?>