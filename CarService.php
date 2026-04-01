<?php require_once 'config/db.php'; ?>
<?php include 'includes/header.php'; ?>
  <div class="container">
    <!-- Header -->
    <header class="header">
      <h1 class="title">Garage Dashboard</h1>
      <button class="btn-logout">Logout</button>
    </header>

    <!-- Summary Cards -->
    <section class="cards">
      <div class="card">
        <div class="card-content">
          <i class="icon-wrench"></i>
          <div>
            <h2 class="card-title">Vehicles Serviced</h2>
            <p class="card-text">18 This Month</p>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-content">
          <i class="icon-clipboard"></i>
          <div>
            <h2 class="card-title">Pending Requests</h2>
            <p class="card-text">4 New Jobs</p>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-content">
          <i class="icon-clock"></i>
          <div>
            <h2 class="card-title">Upcoming Appointments</h2>
            <p class="card-text">6 Scheduled</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Action Section -->
    <section class="action-section">
      <h3 class="action-title">Log New Service</h3>
      <form class="service-form">
        <input type="text" placeholder="Vehicle Reg No." class="form-input">
        <input type="text" placeholder="Service Type (e.g. Oil Change)" class="form-input">
        <input type="date" class="form-input">
        <button class="btn-submit">Log Service</button>
      </form>
    </section>
  </div>
  
  <?php include 'includes/footer.php'; ?>
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Core theme JS-->
        <script src="js/scripts.js"></script>
  
  
</body>
</html>
