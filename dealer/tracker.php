<?php
// tracker.php
require_once __DIR__ . '/../config/db.php'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* ============================================================
   1. DEFINE FUNCTIONS FIRST (So they are always available)
   ============================================================ */

function log_to_db($dealer_id, $type, $related_id = null) {
    global $pdo;
    if (!$dealer_id) return;

    $session_id = session_id();
    $source = (isset($_GET['ref']) && $_GET['ref'] === 'tiktok') ? 'tiktok' : 'direct';

    try {
        $stmt = $pdo->prepare("INSERT INTO dealer_engagement (dealer_id, type, related_id, session_id, source_platform) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$dealer_id, $type, $related_id, $session_id, $source]);
    } catch (Exception $e) {
        // Fail silently
    }
}

function track_engagement($dealer_id, $car_id = null) {
    // Log the Page View immediately via PHP
    $type = $car_id ? 'car_view' : 'profile_view';
    log_to_db($dealer_id, $type, $car_id);

    // Output the JS to handle button clicks
    ?>
    <script>
    function logAction(type) {
        const data = new FormData();
        data.append('action_type', type);
        data.append('dealer_id', '<?= $dealer_id ?>');
        data.append('related_id', '<?= $car_id ?>');

        // Points to the tracker.php file in the same directory
        fetch('tracker.php', { method: 'POST', body: data });
    }

    window.addEventListener('load', () => {
        if (new URLSearchParams(window.location.search).get('ref') === 'tiktok') {
            logAction('tiktok_click');
        }
    });
    </script>
    <?php
}

/* ============================================================
   2. HANDLE AJAX REQUESTS (Runs only when a button is clicked)
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    log_to_db($_POST['dealer_id'], $_POST['action_type'], $_POST['related_id'] ?? null);
    exit; // Stop execution here for AJAX calls
}
