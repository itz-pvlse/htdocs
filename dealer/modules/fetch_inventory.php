<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    echo '<tr><td colspan="6">Unauthorized</td></tr>';
    exit;
}

$dealerId = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Base query
$sql = "SELECT * FROM dealer_listings WHERE dealer_id = ?";
$params = [$dealerId];

if ($search !== '') {
    $sql .= " AND (make LIKE ? OR model LIKE ? OR year LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter !== '') {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$cars) {
    echo '<tr><td colspan="6">No cars found</td></tr>';
    exit;
}

foreach ($cars as $car) {
    $mainImage = $car['main_image'] ?? 'uploads/dealer_listings/default_car.png';
    if (!file_exists(__DIR__ . '/../../' . $mainImage)) {
        $mainImage = 'uploads/dealer_listings/default_car.png';
    }

    // Fetch extra images
    $stmtImgs = $pdo->prepare("SELECT image_path FROM dealer_listing_images WHERE listing_id = ?");
    $stmtImgs->execute([$car['id']]);
    $extraImages = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);

    echo "<tr>";
    echo "<td>";
    echo "<div style='display:flex;flex-direction:column;gap:5px;'>";

    // Main image clickable
    echo "<img src='../" . htmlspecialchars($mainImage) . "' 
              alt='Main Image' 
              class='lightbox-img' 
              data-group='car-" . $car['id'] . "'
              style='width:80px;height:50px;object-fit:cover;border-radius:4px;cursor:pointer;'>";

    // Extra images thumbnails
    if ($extraImages) {
        echo "<div style='display:flex;gap:3px;flex-wrap:wrap;'>";
        foreach ($extraImages as $img) {
            echo "<img src='../" . htmlspecialchars($img['image_path']) . "' 
                      alt='Extra Image' 
                      class='lightbox-img' 
                      data-group='car-" . $car['id'] . "'
                      style='width:35px;height:35px;object-fit:cover;border-radius:3px;cursor:pointer;'>";
        }
        echo "</div>";
    }

    echo "</div>";
    echo "</td>";

    echo "<td>" . htmlspecialchars($car['make'] . ' ' . $car['model']) . "</td>";
    echo "<td>" . htmlspecialchars($car['year']) . "</td>";
    echo "<td>KES " . htmlspecialchars(number_format($car['price'])) . "</td>";
    echo "<td>" . ($car['status'] === 'active'
        ? "<span style='color:green;font-weight:bold;'>Available</span>"
        : "<span style='color:red;font-weight:bold;'>Sold</span>") . "</td>";
    echo "<td>
            <a href='edit_car.php?id=" . $car['id'] . "' class='btn-edit'>✏️ Edit</a>
            <a href='delete_car.php?id=" . $car['id'] . "' class='btn-delete' onclick='return confirm(\"Are you sure?\");'>🗑️ Delete</a>
          </td>";
    echo "</tr>";
}
?>

<!-- Lightbox HTML -->
<div id="lightbox-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
background:rgba(0,0,0,0.8);justify-content:center;align-items:center;z-index:9999;flex-direction:column;">
    <span id="lightbox-close" style="position:absolute;top:15px;right:25px;font-size:30px;color:white;cursor:pointer;">&times;</span>
    <img id="lightbox-image" src="" style="max-width:90%;max-height:80%;border-radius:8px;">
    <div style="position:absolute;bottom:20px;width:100%;display:flex;justify-content:space-between;padding:0 30px;">
        <button id="lightbox-prev" style="padding:10px 15px;font-size:18px;">⬅ Prev</button>
        <button id="lightbox-next" style="padding:10px 15px;font-size:18px;">Next ➡</button>
    </div>
</div>

<script>
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('lightbox-img')) {
        const group = e.target.getAttribute('data-group');
        const imgs = [...document.querySelectorAll(`.lightbox-img[data-group="${group}"]`)].map(img => img.src);
        let currentIndex = imgs.indexOf(e.target.src);

        const overlay = document.getElementById('lightbox-overlay');
        const lightboxImage = document.getElementById('lightbox-image');
        overlay.style.display = 'flex';
        lightboxImage.src = imgs[currentIndex];

        const showImage = (index) => {
            currentIndex = (index + imgs.length) % imgs.length;
            lightboxImage.src = imgs[currentIndex];
        };

        document.getElementById('lightbox-next').onclick = () => showImage(currentIndex + 1);
        document.getElementById('lightbox-prev').onclick = () => showImage(currentIndex - 1);

        document.getElementById('lightbox-close').onclick = function() {
            overlay.style.display = 'none';
        };
    }
});
</script>