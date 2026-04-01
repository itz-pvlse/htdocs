<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    echo '<tr><td colspan="7">Unauthorized access.</td></tr>';
    exit;
}

$dealerId = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$contacted = $_GET['contacted'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "
    SELECT l.id, l.user_id, l.name, l.email, l.phone, l.message, l.created_at, l.contacted,
           u.name AS registered_name
    FROM leads l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.dealer_id = ?
";
$params = [$dealerId];

if ($search !== '') {
    $sql .= " AND (l.name LIKE ? OR l.email LIKE ? OR l.phone LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($contacted === '0' || $contacted === '1') {
    $sql .= " AND l.contacted = ?";
    $params[] = (int)$contacted;
}
if ($status === 'registered') { $sql .= " AND l.user_id IS NOT NULL"; }
elseif ($status === 'guest') { $sql .= " AND l.user_id IS NULL"; }

$sql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total count for pagination
$countSql = "SELECT COUNT(*) FROM leads l WHERE l.dealer_id = ?";
$countParams = [$dealerId];
if ($search !== '') {
    $countSql .= " AND (l.name LIKE ? OR l.email LIKE ? OR l.phone LIKE ?)";
    $countParams[] = $like; $countParams[] = $like; $countParams[] = $like;
}
if ($contacted === '0' || $contacted === '1') { $countSql .= " AND l.contacted = ?"; $countParams[] = (int)$contacted; }
if ($status === 'registered') { $countSql .= " AND l.user_id IS NOT NULL"; }
elseif ($status === 'guest') { $countSql .= " AND l.user_id IS NULL"; }

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalLeads = $countStmt->fetchColumn();
$totalPages = ceil($totalLeads / $limit);

// Render table rows
if (!$leads) { echo '<tr><td colspan="7" style="text-align:center;">No leads found-when new leads are created they will appear here.</td></tr>'; }
else {
    foreach ($leads as $lead) {
        echo '<tr class="'.($lead['contacted'] ? 'contacted-row' : '').'">';
        echo '<td>'.htmlspecialchars($lead['name']).'</td>';
        echo '<td>'.htmlspecialchars($lead['email']).'</td>';
        echo '<td>'.htmlspecialchars($lead['phone']).'</td>';
        echo '<td>'.nl2br(htmlspecialchars($lead['message'])).'</td>';
        echo '<td>'.date('M d, Y H:i', strtotime($lead['created_at'])).'</td>';
        echo '<td>'.($lead['user_id'] ? '✅ '.htmlspecialchars($lead['registered_name']) : '❌ Guest').'</td>';
        echo '<td class="actions">';
        if (!$lead['contacted']) {
            echo '<form method="POST" action="mark_contacted_ajax.php" style="display:inline;">';
            echo '<input type="hidden" name="lead_id" value="'.$lead['id'].'">';
            echo '<button type="submit" class="contacted">Mark as Contacted</button>';
            echo '</form>';
        } else {
            echo '<span style="color:#28a745;font-weight:bold;">Contacted</span>';
        }
        echo '<form method="POST" action="delete_lead_ajax.php" style="display:inline;">';
        echo '<input type="hidden" name="lead_id" value="'.$lead['id'].'">';
        echo '<button type="submit" class="delete">Delete</button>';
        echo '</form>';
        echo '</td></tr>';
    }

    // Render pagination buttons
    if ($totalPages > 1) {
        echo '<tr><td colspan="7" style="text-align:center; padding:10px 0;">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $activeStyle = ($i == $page) ? 'background:#007bff;color:#fff;' : '';
            echo '<button class="page-btn" data-page="'.$i.'" style="margin:0 3px; padding:5px 10px; border-radius:4px; border:1px solid #007bff; cursor:pointer; '.$activeStyle.'">'.$i.'</button>';
        }
        echo '</td></tr>';
    }
}
?>