<?php
// auth/check_handle.php
require_once '../config/db.php';

// Set header for JSON output
header('Content-Type: application/json');
// Prevent caching so the check is always live
header('Cache-Control: no-cache, must-revalidate');

$handle = $_GET['handle'] ?? '';
// Sanitize input to match your DB logic (lowercase, alphanumeric only)
$handle = strtolower(preg_replace('/[^a-z0-9]/', '', $handle));

if (empty($handle)) {
    echo json_encode(['status' => 'empty']);
    exit;
}

try {
    // Check if the handle already exists in the users table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE handle = ?");
    $stmt->execute([$handle]);
    $exists = $stmt->fetchColumn() > 0;

    echo json_encode([
        'status' => $exists ? 'taken' : 'available',
        'handle' => $handle
    ]);

} catch (PDOException $e) {
    // Return error if DB fails
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
