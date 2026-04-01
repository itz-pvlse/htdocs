<?php

require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    header('Location: dealerlogin.php');
    exit;
}
?>
<style>
.inventory-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.search-filters {
    display: flex;
    gap: 10px;
}

.btn-add-car {
    background-color: #27ae60;
    color: white;
    padding: 6px 12px;
    text-decoration: none;
    border-radius: 5px;
    transition: 0.3s;
}

.btn-add-car:hover {
    background-color: #219150;
}

.inventory-wrapper {
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid #ddd;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
}

.inventory-table th, .inventory-table td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}

.inventory-table th {
    background-color: #f4f4f4;
}

.btn-edit { color: #3498db; text-decoration: none; margin-right: 5px; }
.btn-delete { color: #e74c3c; text-decoration: none; }

/* Lightbox styling */
#lightbox-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.8);
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

#lightbox-image {
    max-width: 90%;
    max-height: 90%;
    border-radius: 8px;
}

.lightbox-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.8);
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 10px;
    border-radius: 50%;
}

#lightbox-prev { left: 30px; }
#lightbox-next { right: 30px; }

#lightbox-close {
    position: absolute;
    top: 15px; right: 25px;
    font-size: 30px;
    color: white;
    cursor: pointer;
}
</style>

<div class="inventory-header">
    <h2>My Inventory</h2>
    <a href="add_car.php" class="btn-add-car">➕ Add New Car</a>
</div>

<div class="search-filters">
    <input type="text" id="search" placeholder="Search by make/model..." style="padding:5px; flex:1;">
    <select id="status" style="padding:5px;">
        <option value="">All Statuses</option>
        <option value="active">Available</option>
        <option value="sold">Sold</option>
    </select>
</div>

<div class="inventory-wrapper">
<table class="inventory-table">
    <thead>
        <tr>
            <th>Image</th>
            <th>Make & Model</th>
            <th>Year</th>
            <th>Price</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="inventory-body">
        <!-- AJAX results go here -->
    </tbody>
</table>
</div>

<!-- Lightbox HTML -->
<div id="lightbox-overlay">
    <span id="lightbox-close">&times;</span>
    <button id="lightbox-prev" class="lightbox-btn">&#10094;</button>
    <img id="lightbox-image" src="">
    <button id="lightbox-next" class="lightbox-btn">&#10095;</button>
</div>

<script>
function fetchInventory() {
    const search = document.getElementById('search').value;
    const status = document.getElementById('status').value;

    fetch(`modules/fetch_inventory.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`)
        .then(res => res.text())
        .then(data => {
            document.getElementById('inventory-body').innerHTML = data;
            bindLightboxEvents(); // bind after loading
        });
}

function bindLightboxEvents() {
    const overlay = document.getElementById('lightbox-overlay');
    const lightboxImage = document.getElementById('lightbox-image');
    let imgs = [], currentIndex = 0;

    document.querySelectorAll('.lightbox-img').forEach(img => {
        img.addEventListener('click', () => {
            const group = img.getAttribute('data-group');
            imgs = [...document.querySelectorAll(`.lightbox-img[data-group="${group}"]`)].map(i => i.src);
            currentIndex = imgs.indexOf(img.src);
            overlay.style.display = 'flex';
            lightboxImage.src = imgs[currentIndex];
        });
    });

    document.getElementById('lightbox-next').onclick = () => {
        currentIndex = (currentIndex + 1) % imgs.length;
        lightboxImage.src = imgs[currentIndex];
    };
    document.getElementById('lightbox-prev').onclick = () => {
        currentIndex = (currentIndex - 1 + imgs.length) % imgs.length;
        lightboxImage.src = imgs[currentIndex];
    };
    document.getElementById('lightbox-close').onclick = () => overlay.style.display = 'none';
}

// Initial fetch
fetchInventory();

// Search + filter events
document.getElementById('search').addEventListener('input', fetchInventory);
document.getElementById('status').addEventListener('change', fetchInventory);
</script>