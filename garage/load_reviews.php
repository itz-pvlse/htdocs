<?php
require_once '../config/db.php';

$garage_id = $_GET['garage_id'] ?? 0;
$offset = $_GET['offset'] ?? 0;
$limit = 5;

$stmt = $pdo->prepare("
  SELECT gr.*, u.name
  FROM garage_reviews gr
  JOIN users u ON gr.user_id = u.id
  WHERE gr.garage_id = ?
  ORDER BY gr.created_at DESC
  LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $garage_id, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();

$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($reviews) {
  foreach ($reviews as $r) {
    echo '<div class="review-card">';
    echo '<div class="review-meta">';
    echo '<div><strong>' . htmlspecialchars($r['name']) . '</strong></div>';
    echo '<div class="star">' . str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) . '</div>';
    echo '</div>';
    echo '<div class="review-text small">' . nl2br(htmlspecialchars($r['review_text'])) . '</div>';
    echo '<div class="review-date small">' . date("F j, Y", strtotime($r['created_at'])) . '</div>';
    echo '</div>';
  }
}
?>