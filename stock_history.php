<?php
require_once 'config/db.php';
$part_id = (int)($_GET['part_id'] ?? 0);
if(!$part_id){ echo "<p class='text-muted'>No part selected</p>"; exit; }
$st = $pdo->prepare("SELECT h.*, u.name AS user, p.part_name FROM stock_history h LEFT JOIN users u ON u.id=h.user_id LEFT JOIN parts_inventory p ON p.id=h.part_id WHERE h.part_id=? ORDER BY h.created_at DESC LIMIT 200");
$st->execute([$part_id]);
$hist = $st->fetchAll(PDO::FETCH_ASSOC);
if(!$hist){ echo "<p class='text-muted'>No history yet.</p>"; exit; }
echo "<table class='table table-sm'><thead><tr><th>#</th><th>Type</th><th>Qty change</th><th>Old</th><th>New</th><th>User</th><th>Note</th><th>Time</th></tr></thead><tbody>";
foreach($hist as $h){
  echo "<tr><td>{$h['id']}</td><td>{$h['change_type']}</td><td>{$h['qty_changed']}</td><td>{$h['old_qty']}</td><td>{$h['new_qty']}</td><td>".htmlspecialchars($h['user']??'')."</td><td>".htmlspecialchars($h['note']??'')."</td><td>{$h['created_at']}</td></tr>";
}
echo "</tbody></table>";