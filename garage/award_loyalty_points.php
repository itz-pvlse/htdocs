<?php
// ✅ Require DB connection (should already exist if included)
if (!isset($pdo)) {
    exit("Database connection not found.");
}

// ✅ Ensure $job_id and $user_id are set
if (!isset($job_id) || !isset($user_id)) {
    exit("Missing job_id or user_id.");
}

$points_awarded = 10; // default reward

try {
    // Check if user is already a loyalty member
    $check = $pdo->prepare("SELECT id, points FROM loyalty_members WHERE user_id = ?");
    $check->execute([$user_id]);
    $member = $check->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        // Update points
        $update = $pdo->prepare("UPDATE loyalty_members SET points = points + ?, joined_at = NOW() WHERE user_id = ?");
        $update->execute([$points_awarded, $user_id]);
    } else {
        // Insert new member
        $insert = $pdo->prepare("INSERT INTO loyalty_members (user_id, points, joined_at) VALUES (?, ?, NOW())");
        $insert->execute([$user_id, $points_awarded]);
    }

    // Optional: log points
    $log = $pdo->prepare("INSERT INTO loyalty_points_log (user_id, job_id, points_awarded, created_at) VALUES (?, ?, ?, NOW())");
    $log->execute([$user_id, $job_id, $points_awarded]);

} catch (Exception $e) {
    error_log("Loyalty points error: " . $e->getMessage());
}