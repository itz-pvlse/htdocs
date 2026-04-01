<?php
require_once '../auth/auth_check.php';
require_once '../config/db.php';

require_once __DIR__ . '/vendor/autoload.php'; // path to mPDF
include '../includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$garage_id = $_SESSION['garage_id'] ?? 1; // Adjust session logic if needed


$from_date = $_POST['from_date'] ?? '';
$to_date = $_POST['to_date'] ?? '';
$plate_no = $_POST['plate_no'] ?? '';
$search = $_POST['search'] ?? '';

$query = "SELECT sl.*, v.plate_no, v.make_model 
          FROM service_logs sl 
          JOIN vehicle_details v ON sl.vehicle_id = v.id 
          WHERE 1=1";

$params = [];

if (!empty($from_date) && !empty($to_date)) {
    $query .= " AND DATE(sl.service_date) BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
}

if (!empty($plate_no)) {
    $query .= " AND v.plate_no = ?";
    $params[] = $plate_no;
}

if (!empty($search)) {
    $query .= " AND (sl.description LIKE ? OR v.make_model LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY sl.service_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$html = '<h2 style="text-align:center;">Service Report</h2>';
$html .= '<table border="1" width="100%" cellspacing="0" cellpadding="5">
<tr>
    <th>Plate No</th>
    <th>Vehicle</th>
    <th>Description</th>
    <th>Date</th>
    <th>Cost</th>
</tr>';

foreach ($rows as $row) {
    $html .= "<tr>
        <td>{$row['plate_no']}</td>
        <td>{$row['make_model']}</td>
        <td>{$row['description']}</td>
        <td>{$row['service_date']}</td>
        <td>KES " . number_format($row['cost']) . "</td>
    </tr>";
}
$html .= '</table>';

$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML($html);
$mpdf->Output("Service_Report.pdf", "I"); // I = inline, D = download