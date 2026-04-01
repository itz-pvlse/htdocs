<?php
// feeds/autocomplete.php
require_once '../config/db.php';
header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'user'; 

if (strlen($query) < 1) { 
    echo json_encode([]); 
    exit; 
}

if ($type === 'user') {
    /**
     * Now searching by HANDLE. 
     * We return the handle as the 'label' so the JS inserts @handle 
     * which works perfectly with our unique profile links.
     */
    $stmt = $pdo->prepare("SELECT handle as label FROM users WHERE handle LIKE ? LIMIT 5");
    $stmt->execute(["$query%"]);
} else {
    /**
     * Search hashtags. 
     * Using a more reliable REGEXP to find words starting with #
     */
    $stmt = $pdo->prepare("SELECT DISTINCT 
        SUBSTRING_INDEX(SUBSTRING_INDEX(content, '#', -1), ' ', 1) as label 
        FROM posts 
        WHERE content REGEXP ? 
        LIMIT 5");
    $stmt->execute(['#' . $query]);
}

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Clean up results
foreach($results as &$row) {
    // We keep the logic to strip non-alphanumeric chars to ensure 
    // the mention/tag doesn't break the regex in formatter.php
    $row['label'] = preg_replace('/[^A-Za-z0-9_]/', '', $row['label']);
}

echo json_encode($results);
