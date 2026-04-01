<?php
require_once __DIR__ . '/../../config/db.php';

$search = trim($_GET['search'] ?? '');
$year = trim($_GET['year'] ?? '');
$price = trim($_GET['price'] ?? '');

// Base query: only active cars
$sql = "SELECT * FROM dealer_listings WHERE status = 'active'";
$params = [];

// Search filter
if ($search !== '') {
    $sql .= " AND (make LIKE ? OR model LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
}

// Year filter
if ($year !== '') {
    $sql .= " AND year = ?";
    $params[] = $year;
}

// Price filter
if ($price !== '') {
    [$min, $max] = explode('-', $price) + [null, null];
    if ($min !== null && $max !== null) {
        $sql .= " AND price BETWEEN ? AND ?";
        $params[] = $min;
        $params[] = $max;
    } elseif ($min !== null && $max === null) {
        $sql .= " AND price >= ?";
        $params[] = $min;
    } elseif ($max !== null && $min === null) {
        $sql .= " AND price <= ?";
        $params[] = $max;
    }
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$cars) {
    echo "<p>No cars found.</p>";
    exit;
}

// Start list container
echo "<div class='car-list'>";

foreach ($cars as $car) {
    $mainImage = $car['main_image'] ?? 'uploads/dealer_listings/default_car.png';
    if (!file_exists(__DIR__ . '/../../' . $mainImage)) {
        $mainImage = 'uploads/dealer_listings/default_car.png';
    }

    echo "<div class='car-item'>";

    // Image section
    echo "<div class='car-image'>";
    echo "<img src='../" . htmlspecialchars($mainImage) . "' alt='Car Image'>";
    echo "</div>";

    // Info section
    echo "<div class='car-info'>";
    echo "<h3>" . htmlspecialchars($car['make'] . ' ' . $car['model']) . "</h3>";
    echo "<p>Year: " . htmlspecialchars($car['year']) . "</p>";
    echo "<p class='price'>Price: KES " . number_format($car['price']) . "</p>";

    // Optional badges for features (if your table has features column)
    if (!empty($car['features'])) {
        $features = explode(',', $car['features']); // assuming comma-separated
        echo "<div class='badges'>";
        foreach ($features as $f) {
            echo "<span class='badge'>" . htmlspecialchars(trim($f)) . "</span>";
        }
        echo "</div>";
    }

    echo "</div>"; // end car-info

    // View button
    echo "<a href='car_details.php?id=" . $car['id'] . "' class='view-btn'>View Details</a>";

    echo "</div>"; // end car-item
}

echo "</div>"; // end car-list
?>