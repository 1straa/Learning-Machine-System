<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid message ID']);
    exit();
}

$message_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Fetch message details only if the logged-in user is the receiver
$stmt = $conn->prepare("
    SELECT m.subject, m.message, m.sent_at, u.name as sender_name, u.role as sender_role
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.id = ? AND m.receiver_id = ?
");
$stmt->bind_param("ii", $message_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $message = $result->fetch_assoc();

    // Mark message as read if not already
    $update_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
    $update_stmt->bind_param("i", $message_id);
    $update_stmt->execute();
    $update_stmt->close();

    echo json_encode([
        'success' => true,
        'subject' => $message['subject'],
        'message' => $message['message'],
        'sent_at' => date('M d, Y g:i A', strtotime($message['sent_at'])),
        'sender_name' => $message['sender_name'],
        'sender_role' => ucfirst($message['sender_role'])
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Message not found or access denied']);
}

$stmt->close();
$conn->close();
?>
