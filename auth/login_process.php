<?php

include '../config/db.php'; 
session_start();
// 1. COLLECT DATA
$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$redirect = $_REQUEST['redirect'] ?? ''; 

try {
    // We select * so we definitely get the 'handle' column
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        
        // ✅ SESSION SETUP
        session_regenerate_id(true); 
        $user_id = $user['id'];
        $_SESSION['user_id']     = $user_id;
        $_SESSION['name']        = $user['name'];
        $_SESSION['role']        = $user['role'];
        $_SESSION['user_email']  = $user['email'];
        
        // ✅ HANDLE UPDATE: This stops the profile.php redirects
        $_SESSION['user_handle'] = $user['handle']; 

        if (!empty($user['garage_id'])) {
            $_SESSION['garage_id'] = $user['garage_id'];
        }

        // --- DEVICE & BROWSER DETECTION ---
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $current_ip = $_SERVER['REMOTE_ADDR'];
        
        // Browser Detection
        $browser = 'Firefox';
        if (strpos($user_agent, 'Edge') !== false) $browser = 'Edge';
        elseif (strpos($user_agent, 'Chrome') !== false) $browser = 'Chrome';
        elseif (strpos($user_agent, 'Safari') !== false) $browser = 'Safari';

        // OS Detection
        $os = 'Other';
        if (strpos($user_agent, 'Windows') !== false) $os = 'Windows';
        elseif (strpos($user_agent, 'Macintosh') !== false) $os = 'macOS';
        elseif (strpos($user_agent, 'Android') !== false) $os = 'Android';
        elseif (strpos($user_agent, 'iPhone') !== false) $os = 'iOS';

        // --- PERSISTENT DEVICE RECOGNITION ---
        $device_cookie = $_COOKIE['device_auth'] ?? null;
        $is_recognized = false;

        // Step A: Check if the browser provides a cookie we already know
        if ($device_cookie) {
            $stmtCheck = $pdo->prepare("SELECT device_token FROM user_sessions WHERE user_id = ? AND device_token = ? LIMIT 1");
            $stmtCheck->execute([$user_id, $device_cookie]);
            if ($stmtCheck->fetch()) {
                $is_recognized = true;
            }
        }

        // Step B: Backup Check (IP + Browser)
        if (!$is_recognized) {
            $stmtIPCheck = $pdo->prepare("SELECT device_token FROM user_sessions WHERE user_id = ? AND ip_address = ? AND browser_name = ? LIMIT 1");
            $stmtIPCheck->execute([$user_id, $current_ip, $browser]);
            $backup = $stmtIPCheck->fetch();
            if ($backup) {
                $is_recognized = true;
                $device_cookie = $backup['device_token']; 
            }
        }

        // --- SECURITY ALERT & TOKEN MANAGEMENT ---
        if (!$is_recognized) {
            $updateAlert = $pdo->prepare("UPDATE users SET security_alert = 1 WHERE id = ?");
            $updateAlert->execute([$user_id]);

            $token_to_save = bin2hex(random_bytes(32));
            
            $sessionStmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, device_token, device_type, browser_name, platform_name, ip_address) VALUES (?, ?, ?, 'Desktop', ?, ?, ?)");
            $sessionStmt->execute([$user_id, session_id(), $token_to_save, $browser, $os, $current_ip]);

            setcookie('device_auth', $token_to_save, time() + (86400 * 30), "/", "", false, true); 
        } else {
            $sessionStmt = $pdo->prepare("UPDATE user_sessions SET session_id = ?, last_activity = NOW(), ip_address = ? WHERE user_id = ? AND device_token = ?");
            $sessionStmt->execute([session_id(), $current_ip, $user_id, $device_cookie]);
            
            setcookie('device_auth', $device_cookie, time() + (86400 * 30), "/", "", false, true);
        }

        // ✅ UPDATE LOGS
        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user_id]);

        // ✅ REDIRECTION
        $role_paths = [
            'owner'    => '../dashboard.php',
            'garage'   => '../garage.php',
            'dealer'   => '../dealer/dealer.php',
            'mechanic' => '../mechanic/mechanic_dashboard.php'
        ];
        $location = $role_paths[$user['role']] ?? '../login.php';
        header("Location: " . $location);
        exit;

    } else {
        $_SESSION['error'] = "Invalid email or password";
        header("Location: ../login.php");
        exit;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: ../login.php?err=1");
    exit;
}
