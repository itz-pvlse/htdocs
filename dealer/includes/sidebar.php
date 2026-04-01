<!-- Hamburger button for mobile -->
<button id="sidebarToggle">☰</button>
<div id="overlay"></div>

<div class="sidebar">
  <div class="sidebar-header">
    <img src="../<?= htmlspecialchars($dealer['logo'] ?? 'default-user.png') ?>" 
         class="dealer-logo" 
         alt="Dealer Logo"
         style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #ccc;">
    <h3><?= htmlspecialchars($dealer['name']) ?></h3>
  </div>

  <ul>
    <li><a href="index.php?page=dashboard">📊 Dashboard</a></li>
    <li><a href="index.php?page=inventory">🚗 Inventory</a></li>
    <li><a href="index.php?page=leads">📩 Leads</a></li>
    <li><a href="index.php?page=analytics">📈 Analytics</a></li>
    <li><a href="index.php?page=profile">👤 Profile</a></li>
    <li><a href="index.php?page=settings">⚙️ Settings</a></li>
    <li><a href="../auth/logout.php">🚪 Logout</a></li>
  </ul>
</div>

<style>
/* Sidebar styles */
.sidebar {
  width: 250px;
  background: #1e293b;
  color: white;
  height: 100vh;
  position: fixed;
  top: 0;
  left: 0;
  overflow-y: auto;
  transition: transform 0.3s ease;
  z-index: 1002;
}

.sidebar-header { text-align: center; padding: 20px; }
.sidebar-header h3 { margin-top: 10px; font-size: 18px; }

.sidebar ul { list-style: none; padding: 0; margin: 0; }
.sidebar ul li a {
  display: block; padding: 12px 16px; color: white; text-decoration: none;
}
.sidebar ul li a:hover { background: #334155; }

/* Hamburger button */
#sidebarToggle {
  display: none;
  position: fixed;
  top: 10px;
  left: 10px;
  z-index: 1003;
  font-size: 24px;
  background: none;
  border: none;
  color: #1e293b;
  cursor: pointer;
}

/* Overlay */
#overlay {
  display: none;
  position: fixed;
  top:0;
  left:0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.3);
  z-index: 1001;
}

/* Mobile responsive */
@media (max-width: 768px) {
  #sidebarToggle { display: block; }

  .sidebar {
    transform: translateX(-100%);
    width: 80%;
    max-width: 300px;
  }

  .sidebar.active { transform: translateX(0); }
  #overlay.active { display: block; }
}

</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.querySelector('.sidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  const overlay = document.getElementById('overlay');

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
  });

  overlay.addEventListener('click', () => {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
  });
});
</script>
