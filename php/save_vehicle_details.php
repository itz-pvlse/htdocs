<?php
require_once '../auth/auth_check.php';
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id'])) {
    $id = $_POST['vehicle_id'];

    // Extract all relevant values
    $reg_cert_no     = $_POST['reg_cert_no'];
    $chassis_number  = $_POST['chassis_number'];
    $engine_number   = $_POST['engine_number'];
    $reg_cert_serial = $_POST['reg_cert_serial'];

    // Check for duplicates before update
    $check_sql = "SELECT id FROM vehicles 
                  WHERE (reg_cert_no = :reg_cert_no 
                      OR chassis_number = :chassis_number 
                      OR engine_number = :engine_number 
                      OR reg_cert_serial = :reg_cert_serial)
                      AND id != :id";

    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([
        ':reg_cert_no'    => $reg_cert_no,
        ':chassis_number' => $chassis_number,
        ':engine_number'  => $engine_number,
        ':reg_cert_serial'=> $reg_cert_serial,
        ':id'             => $id
    ]);

    if ($check_stmt->rowCount() > 0) {
        $_SESSION['error'] = "⚠️ Duplicate detected: Reg Cert No, Chassis No, Engine No, or Reg Cert Serial already exists.";
        header("Location: ../vehicle_profile.php?id=" . $id);
        exit();
    }

    // If no duplicates, proceed to update
    $update_stmt = $pdo->prepare("UPDATE vehicles SET
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
        transmission_type = :transmission_type
        WHERE id = :id");

    $update_stmt->execute([
        ':chassis_number'     => $_POST['chassis_number'],
        ':customs_entry_number'=> $_POST['customs_entry_number'],
        ':body_type'          => $_POST['body_type'],
        ':manufacture_year'   => $_POST['manufacture_year'],
        ':color'              => $_POST['color'],
        ':fuel_type'          => $_POST['fuel_type'],
        ':engine_number'      => $_POST['engine_number'],
        ':rating_cc'          => $_POST['rating_cc'],
        ':load_capacity'      => $_POST['load_capacity'],
        ':passengers'         => $_POST['passengers'],
        ':condition'          => $_POST['condition'],
        ':driver_side'        => $_POST['driver_side'],
        ':under_caveat'       => $_POST['under_caveat'],
        ':reg_cert_no'        => $_POST['reg_cert_no'],
        ':reg_cert_serial'    => $_POST['reg_cert_serial'],
        ':transmission_type'  => $_POST['transmission_type'],
        ':id'                 => $id
    ]);

    $_SESSION['success'] = "✅ Vehicle updated successfully.";
    header("Location: ../vehicle_profile.php?id=" . $id);
    exit();
}
?>
