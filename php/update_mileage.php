<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId = $_POST['vehicle_id'];
    $newMileage = $_POST['mileage'];

    if (is_numeric($vehicleId) && is_numeric($newMileage)) {

        // 1. Get current mileage from the vehicles table
        $stmt = $pdo->prepare("SELECT current_mileage FROM vehicles WHERE id = :id");
        $stmt->execute([':id' => $vehicleId]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($vehicle) {
            $currentMileage = $vehicle['current_mileage'];

            // 2. Check if new mileage is greater than current mileage
            if ($newMileage > $currentMileage) {

                // 3. Check for duplicate in mileage_logs table
                $checkLog = $pdo->prepare("SELECT COUNT(*) FROM mileage_logs WHERE vehicle_id = :vehicle_id AND mileage = :mileage");
                $checkLog->execute([
                    ':vehicle_id' => $vehicleId,
                    ':mileage' => $newMileage
                ]);

                $duplicateCount = $checkLog->fetchColumn();

                if ($duplicateCount == 0) {
                    // 4. Update vehicles table
                    $updateStmt = $pdo->prepare("UPDATE vehicles SET current_mileage = :mileage WHERE id = :id");
                    $updateStmt->execute([
                        ':mileage' => $newMileage,
                        ':id' => $vehicleId
                    ]);

                    // 5. Insert into mileage_logs
                    $logStmt = $pdo->prepare("INSERT INTO mileage_logs (vehicle_id, mileage, updated_at) VALUES (:vehicle_id, :mileage, NOW())");
                    $logStmt->execute([
                        ':vehicle_id' => $vehicleId,
                        ':mileage' => $newMileage
                    ]);

                    // 6. Redirect with success
                    header("Location: ../vehicle_profile.php?id=" . $vehicleId . "&mileage_updated=1");
                    exit;
                } else {
                    // Mileage already logged before
                    header("Location: ../vehicle_profile.php?id=" . $vehicleId . "&mileage_duplicate=1");
                    exit;
                }
            } else {
                // New mileage must be higher
                header("Location: ../vehicle_profile.php?id=" . $vehicleId . "&mileage_updated=0");
                exit;
            }
        } else {
            echo "Vehicle not found.";
        }
    } else {
        echo "Invalid data submitted.";
    }
} else {
    echo "Invalid request method.";
}