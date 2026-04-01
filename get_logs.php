<?php
require_once 'config/db.php';
$stmt = $pdo->query("
  SELECT l.id, l.action, l.details, l.created_at, u.name AS user
  FROM activity_logs l
  LEFT JOIN users u ON u.id = l.user_id
  ORDER BY l.id DESC LIMIT 200
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if(!$logs) { echo "<p class='text-muted'>No logs yet.</p>"; exit; }
echo "<table class='table table-sm'><thead><tr><th>#</th><th>User</th><th>Action</th><th>Details</th><th>Time</th></tr></thead><tbody>";
foreach($logs as $r){
  echo "<tr><td>{$r['id']}</td><td>".htmlspecialchars($r['user']??'System')."</td><td>".htmlspecialchars($r['action'])."</td><td>".htmlspecialchars($r['details'])."</td><td>{$r['created_at']}</td></tr>";
}
echo "</tbody></table>";