<?
$stmt = $pdo->query("SELECT * FROM mileage_settings WHERE auto_update = 'yes'");
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($vehicles as $v) {
    $lastDriveDate = new DateTime($v['last_drive_date']);
    $today = new DateTime();
    $daysElapsed = $lastDriveDate->diff($today)->days;

    $estimated_km = $daysElapsed * $v['days_per_week'] / 7 * $v['km_per_day'];

    // Get current mileage
    $stmt2 = $pdo->prepare("SELECT current_mileage FROM vehicles WHERE id = ?");
    $stmt2->execute([$v['vehicle_id']]);
    $current = $stmt2->fetchColumn();

    $new_mileage = round($current + $estimated_km);

    // Update
    $update = $pdo->prepare("UPDATE vehicles SET current_mileage = ? WHERE id = ?");
    $update->execute([$new_mileage, $v['vehicle_id']]);

    // Log
    $log = $pdo->prepare("INSERT INTO mileage_logs (vehicle_id, mileage, created_at) VALUES (?, ?, NOW())");
    $log->execute([$v['vehicle_id'], $new_mileage]);

    // Update last_drive_date to today
    $pdo->prepare("UPDATE mileage_settings SET last_drive_date = ? WHERE vehicle_id = ?")
        ->execute([$today->format('Y-m-d'), $v['vehicle_id']]);
}
?>