<?php
require_once '../auth/auth_check.php';
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];

    // Collect posted data
    $make               = $_POST['make'];
    $model              = $_POST['model'];
    $plate_no           = $_POST['plate_no'];
    $chassis_number     = $_POST['chassis_number'];
    $customs_entry      = $_POST['customs_entry_number'];
    $body_type          = $_POST['body_type'];
    $manufacture_year   = $_POST['manufacture_year'];
    $color              = $_POST['color'];
    $fuel_type          = $_POST['fuel_type'];
    $engine_number      = $_POST['engine_number'];
    $rating_cc          = $_POST['rating_cc'];
    $load_capacity      = $_POST['load_capacity'];
    $passengers         = $_POST['passengers'];
    $condition          = $_POST['condition'];
    $driver_side        = $_POST['driver_side'];
    $under_caveat       = $_POST['under_caveat'];
    $reg_cert_no        = $_POST['reg_cert_no'];
    $reg_cert_serial    = $_POST['reg_cert_serial'];
    $transmission_type  = $_POST['transmission_type'];
	$insurance_expiry_date = $_POST['insurance_expiry_date'];
$last_service_date     = $_POST['last_service_date'];
$service_interval_km   = $_POST['service_interval_km'];

    // 🔒 Check for duplicates excluding current ID
    $stmt = $pdo->prepare("
        SELECT id FROM vehicles 
        WHERE (
            reg_cert_no = :reg_cert_no OR 
            chassis_number = :chassis_number OR 
            engine_number = :engine_number OR 
            reg_cert_serial = :reg_cert_serial
        ) AND id != :id
    ");
    $stmt->execute([
        ':reg_cert_no'     => $reg_cert_no,
        ':chassis_number'  => $chassis_number,
        ':engine_number'   => $engine_number,
        ':reg_cert_serial' => $reg_cert_serial,
        ':id'              => $id
    ]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = "❌ Duplicate detected: Reg Cert No, Chassis No, Engine No, or Serial No already exists.";
        header("Location: ../vehicle_profile.php?id=" . $id);
        exit;
    }

    // ✅ Proceed with update
    $update = $pdo->prepare("
        UPDATE vehicles SET
            make = :make,
            model = :model,
            plate_no = :plate_no,
            chassis_number = :chassis_number,
            customs_entry_number = :customs_entry_number,
            body_type = :body_type,
            manufacture_year = :manufacture_year,
            color = :color,
            fuel_type = :fuel_type,
            engine_number = :engine_number,
            rating_cc = :rating_cc,
            load_capacity = :load_capacity,
            passengers = :passengers,
            `condition` = :condition,
            driver_side = :driver_side,
            under_caveat = :under_caveat,
            reg_cert_no = :reg_cert_no,
            reg_cert_serial = :reg_cert_serial,
            transmission_type = :transmission_type,
			insurance_expiry_date = :insurance_expiry_date,
			last_service_date = :last_service_date,
			service_interval_km = :service_interval_km
        WHERE id = :id
    ");

    $update->execute([
        ':make'                => $make,
        ':model'               => $model,
        ':plate_no'            => $plate_no,
        ':chassis_number'      => $chassis_number,
        ':customs_entry_number'=> $customs_entry,
        ':body_type'           => $body_type,
        ':manufacture_year'    => $manufacture_year,
        ':color'               => $color,
        ':fuel_type'           => $fuel_type,
        ':engine_number'       => $engine_number,
        ':rating_cc'           => $rating_cc,
        ':load_capacity'       => $load_capacity,
        ':passengers'          => $passengers,
        ':condition'           => $condition,
        ':driver_side'         => $driver_side,
        ':under_caveat'        => $under_caveat,
        ':reg_cert_no'         => $reg_cert_no,
        ':reg_cert_serial'     => $reg_cert_serial,
        ':transmission_type'   => $transmission_type,
		':insurance_expiry_date' => $insurance_expiry_date,
		':last_service_date'     => $last_service_date,
		':service_interval_km'   => $service_interval_km,
        ':id'                  => $id
    ]);

    // ✅ Feedback
    if ($update->rowCount() > 0) {
        $_SESSION['success'] = "✅ Vehicle updated successfully.";
    } else {
        $_SESSION['warning'] = "⚠️ No changes were made or vehicle not found.";
    }

    header("Location: ../vehicle_profile.php?id=" . $id);
    exit;
} else {
    die("Invalid request method.");
}
?>