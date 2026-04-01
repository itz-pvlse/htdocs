<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base = '/'; // adjust base path
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dealer Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* --- Navbar Styles --- */
        .navbar-nav .nav-link {
            color: #fff;
            margin-right: 1rem;
            transition: color 0.2s;
        }
        .navbar-nav .nav-link:hover {
            color: #0d6efd;
        }
        .logout-btn {
            padding: 6px 14px;
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            font-family: sans-serif;
            transition: background-color 0.3s;
        }
        .logout-btn:hover {
            background-color: #c0392b;
        }
        .logo-circle {
            display: flex;
            align-items: center;
        }

        /* Mobile menu styles */
        @media (max-width: 992px) {
            .navbar-collapse {
                background-color: #343a40;
                padding: 1rem;
            }
            .navbar-nav {
                flex-direction: column;
            }
            .navbar-nav .nav-item {
                margin-bottom: 0.5rem;
            }
            .navbar-nav .nav-link {
                display: block;
                width: 100%;
                padding: 0.75rem 1rem;
                border-radius: 4px;
            }
            .navbar-nav .nav-link:hover {
                background-color: #495057;
                color: #fff;
            }
            .logout-btn {
                display: block;
                width: 100%;
                text-align: center;
                margin: 0.5rem 0 0 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container px-3">
            <!-- Logo -->
            <div class="logo-circle">
                <a class="navbar-brand" href="<?= $base ?>index.php">
                    <img src="<?= $base ?>assets/autolog2.png" alt="AUTOLOG Logo" height="40">
                </a>
            </div>

            <!-- Hamburger -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu" aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu Links -->
            <div class="collapse navbar-collapse" id="navbarMenu">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>garages.php">Garages</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>pricing.php">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>dashboard.php">Car Owner Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>garage.php">Garage Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>dealer/index.php">CarDealer Dashboard</a></li>
                    
                    <!-- Logout -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a href="<?= $base ?>auth/logout.php" class="logout-btn">Logout</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Welcome Message -->
    <div class="container mt-3">
        <h2>Welcome, <?= htmlspecialchars($dealer['name'] ?? 'User') ?></h2>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
