<?php
session_start();

// Redirect to login if not logged in
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Restrict access by role (e.g., owner or garage)
function requireRole($required_role) {
    requireLogin();

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        // Set flash message
        $_SESSION['flash_message'] = "❌ Access Denied: You’re not allowed to view that page.";

        // Redirect back to previous page if possible
        if (!empty($_SERVER['HTTP_REFERER'])) {
            header("Location: " . $_SERVER['HTTP_REFERER']);
        } else {
            header("Location: index.php");
        }
        exit();
    }
}
