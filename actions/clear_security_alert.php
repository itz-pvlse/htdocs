<?php
session_start();
require_once '../config/db.php';
if(isset($_SESSION['user_id'])) {
    $pdo->prepare("UPDATE users SET security_alert = 0 WHERE id = ?")->execute([$_SESSION['user_id']]);
}