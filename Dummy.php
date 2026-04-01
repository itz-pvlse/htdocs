<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/db.php';

// Get garage ID from query string
$garage_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT 
        u.email,
        u.role,
        g.id,
        g.name,
        g.logo,
        g.phone,
        g.location,
        g.latitude,
        g.longitude,
        g.description,
        g.created_at
    FROM users AS u
    JOIN garages AS g ON u.id = g.id
    WHERE u.id = ?
");
$stmt->execute([$garage_id]);
$garage = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$garage) {
  echo "<p>Garage not found.</p>";
  include 'includes/footer.php';
  exit;
}

// Fetch services
$specialty_name = $pdo->prepare("SELECT * FROM garage_specialties WHERE garage_id = ?");
$specialty_name->execute([$garage_id]);

// Fetch gallery images
$gallery = $pdo->prepare("SELECT * FROM garage_gallery WHERE garage_id = ?");
$gallery->execute([$garage_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($garage['garage_name']) ?> - AutoLog</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
body { background: #f6f8fa; color: #333; }
.container { max-width: 1100px; margin: 20px auto; background: #fff; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }

/* Header Section */
.header { position: relative; height: 250px; background: #001f3f; display: flex; align-items: center; justify-content: center; }
.header img { width: 140px; height: 140px; border-radius: 50%; border: 5px solid #fff; object-fit: cover; position: absolute; bottom: -70px; }
.header h1 { position: absolute; bottom: 10px; color: #fff; font-size: 1.6rem; text-align: center; }

/* Info Section */
.info { text-align: center; padding: 90px 20px 20px; }
.info p { margin: 5px 0; font-size: 0.95rem; }
.info i { color: #001f3f; margin-right: 8px; }

/* Section Titles */
.section-title { margin: 20px 0 10px; font-weight: 600; color: #001f3f; text-transform: uppercase; font-size: 1rem; text-align: left; }

/* Specialties */
.specialties { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-bottom: 20px; }
.specialty { background: #eaf1ff; color: #001f3f; padding: 6px 14px; border-radius: 25px; font-size: 0.9rem; }

/* Gallery */
.gallery { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 10px; }
.gallery img { width: 100%; height: 120px; object-fit: cover; border-radius: 10px; }

/* Reviews */
.review-card { background: #f9f9f9; border-radius: 10px; padding: 15px; margin: 10px 0; }
.review-card h4 { margin-bottom: 5px; font-size: 1rem; }
.review-card p { font-size: 0.9rem; color: #555; }
.stars { color: #f6b01a; }

/* Buttons */
.actions { display: flex; justify-content: center; gap: 15px; margin: 20px 0; flex-wrap: wrap; }
.btn { background: #001f3f; color: white; border: none; padding: 10px 20px; border-radius: 25px; cursor: pointer; transition: 0.3s; }
.btn:hover { background: #003366; }

/* Modals */
.modal { display: none; position: fixed; z-index: 999; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
.modal-content { background: #fff; padding: 20px; border-radius: 10px; width: 90%; max-width: 400px; }
.modal-content h3 { margin-bottom: 10px; }
.modal-content input, .modal-content textarea { width: 100%; padding: 8px; margin-bottom: 10px; border-radius: 5px; border: 1px solid #ccc; }
.modal-content .btn { width: 100%; }

/* Responsive */
@media(max-width: 768px){
    .header { height: 180px; }
    .header img { width: 100px; height: 100px; bottom: -50px; }
    .info { padding-top: 70px; }
}
</style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <img src="<?= htmlspecialchars($garage['logo']) ?>" alt="Garage Logo">
        <h1><?= htmlspecialchars($garage['garage_name']) ?></h1>
    </div>

    <!-- Info -->
    <div class="info">
        <p><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($garage['phone']) ?></p>
        <p><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($garage['email']) ?></p>
        <p><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($garage['location']) ?></p>
        <p><?= nl2br(htmlspecialchars($garage['description'])) ?></p>
    </div>

    <!-- Specialties -->
    <div class="section-title">Specialties</div>
    <div class="specialties">
        <?php foreach ($specialties as $s): ?>
            <span class="specialty"><?= htmlspecialchars($s['specialty_name']) ?></span>
        <?php endforeach; ?>
    </div>

    <!-- Gallery -->
    <div class="section-title">Gallery</div>
    <div class="gallery">
        <?php foreach ($gallery as $img): ?>
            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Garage Image">
        <?php endforeach; ?>
    </div>

    <!-- Reviews -->
    <div class="section-title">Reviews</div>
    <?php foreach ($reviews as $r): ?>
        <div class="review-card">
            <h4><?= htmlspecialchars($r['user_name']) ?></h4>
            <div class="stars"><?= str_repeat('★', $r['rating']) ?></div>
            <p><?= htmlspecialchars($r['comment']) ?></p>
            <small><?= htmlspecialchars(date('F j, Y', strtotime($r['created_at']))) ?></small>
        </div>
    <?php endforeach; ?>

    <!-- Actions -->
    <div class="actions">
        <button class="btn" onclick="openModal('bookModal')">Book Appointment</button>
        <button class="btn" onclick="openModal('reviewModal')">Leave a Review</button>
    </div>
</div>

<!-- Book Modal -->
<div class="modal" id="bookModal">
    <div class="modal-content">
        <h3>Book Appointment</h3>
        <form method="post" action="book_appointment.php">
            <input type="hidden" name="garage_id" value="<?= $garage_id ?>">
            <input type="text" name="user_name" placeholder="Your Name" required>
            <input type="date" name="date" required>
            <input type="time" name="time" required>
            <textarea name="message" placeholder="Message" rows="3"></textarea>
            <button type="submit" class="btn">Submit</button>
        </form>
        <button class="btn" onclick="closeModal('bookModal')" style="background:#888;margin-top:10px;">Cancel</button>
    </div>
</div>

<!-- Review Modal -->
<div class="modal" id="reviewModal">
    <div class="modal-content">
        <h3>Leave a Review</h3>
        <form method="post" action="submit_review.php">
            <input type="hidden" name="garage_id" value="<?= $garage_id ?>">
            <input type="text" name="user_name" placeholder="Your Name" required>
            <input type="number" name="rating" placeholder="Rating (1-5)" min="1" max="5" required>
            <textarea name="comment" placeholder="Your review" rows="3"></textarea>
            <button type="submit" class="btn">Submit</button>
        </form>
        <button class="btn" onclick="closeModal('reviewModal')" style="background:#888;margin-top:10px;">Cancel</button>
    </div>
</div>

<script>
function openModal(id){ document.getElementById(id).style.display='flex'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }
</script>

</body>
</html>