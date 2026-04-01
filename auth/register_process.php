<?php
// register_process.php
session_start();
include('../config/db.php');

// 1. COLLECT & SANITIZE DATA
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? 'owner';

// Get handle: use custom input if provided, otherwise derive from name
$requestedHandle = !empty($_POST['custom_handle']) ? $_POST['custom_handle'] : $name;
$handle = strtolower(preg_replace('/[^a-z0-9]/', '', $requestedHandle));

// Basic validation
if (empty($name) || empty($email) || empty($password) || empty($handle)) {
    echo "<script>alert('All fields are required.'); window.history.back();</script>";
    exit;
}

try {
    $pdo->beginTransaction();

    // 2. UNIQUE HANDLE CHECK (Safety Net)
    // Even with JS checks, we check one last time before DB insertion
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE handle = ?");
    $stmtCheck->execute([$handle]);
    
    if ($stmtCheck->fetchColumn() > 0) {
        // If someone grabbed the handle during the form fill, append random numbers
        $handle = $handle . rand(10, 99);
    }

    // 3. HASH PASSWORD
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 4. INSERT INTO USERS TABLE
    // Note: 'name' is no longer unique in your DB, so this won't trigger 1062 for duplicates
    $stmt = $pdo->prepare("INSERT INTO users (name, handle, email, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $handle, $email, $hashedPassword, $role]);

    $userId = $pdo->lastInsertId();

    // 5. ROLE-SPECIFIC TABLE INSERTIONS
    if ($role === 'garage') {
        $stmt2 = $pdo->prepare("INSERT INTO garages (id, name, email) VALUES (?, ?, ?)");
        $stmt2->execute([$userId, $name, $email]);
    }

    if ($role === 'dealer') {
        $stmt3 = $pdo->prepare("INSERT INTO dealers (user_id, name, email) VALUES (?, ?, ?)");
        $stmt3->execute([$userId, $name, $email]);
    }

    $pdo->commit();

    echo "<script>
        alert('Registration successful! Welcome, $name. Your handle is @$handle');
        window.location.href = '../login.php';
    </script>";
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();

    if ($e->errorInfo[1] == 1062) {
        // This will now only trigger for Email (or the rare Handle conflict)
        echo "<script>
            alert('Email or Handle already exists. Please use different credentials.');
            window.history.back();
        </script>";
    } else {
        error_log("Registration Error: " . $e->getMessage());
        echo "<script>
            alert('A system error occurred. Please try again later.');
            window.history.back();
        </script>";
    }
}
