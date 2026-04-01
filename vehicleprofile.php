<?php require_once 'config/db.php'; ?>
<?php include 'includes/header.php'; ?>
<head>
  <title>Vehicle Profile | AutoLog</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f5f6fa;
      margin: 0;
      padding: 0;
      color: #333;
    }
    header {
      background-color: #222f3e;
      color: white;
      padding: 1rem 2rem;
      text-align: center;
      font-size: 1.5rem;
    }
    .container {
      max-width: 1000px;
      margin: auto;
      padding: 2rem;
    }
    .vehicle-card {
      background-color: white;
      border-radius: 10px;
      padding: 1.5rem;
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
      margin-bottom: 2rem;
    }
    .vehicle-card h2 {
      margin-top: 0;
      font-size: 1.8rem;
      color: #2f3640;
    }
    .vehicle-info {
      display: flex;
      flex-wrap: wrap;
      gap: 2rem;
    }
    .info-block {
      flex: 1 1 200px;
      font-size: 1rem;
    }
    .section-title {
      font-size: 1.3rem;
      color: #273c75;
      margin-bottom: 0.5rem;
      margin-top: 2rem;
    }
    .history, .appointments {
      background-color: #f1f2f6;
      border-radius: 8px;
      padding: 1rem;
    }
    .gallery {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 10px;
    }
    .gallery img {
      width: 100px;
      height: 70px;
      object-fit: cover;
      border-radius: 6px;
    }
    .btn-group {
      margin-top: 1.5rem;
      display: flex;
      gap: 1rem;
    }
    .btn {
      padding: 0.6rem 1.2rem;
      border: none;
      border-radius: 6px;
      background-color: #40739e;
      color: white;
      cursor: pointer;
      transition: background 0.3s ease;
    }
    .btn:hover {
      background-color: #273c75;
    }
  </style>
</head>
<body>

<header>Vehicle Profile</header>

<div class="container">

  <!-- Vehicle Overview -->
  <div class="vehicle-card">
    <h2>Toyota Corolla 2018</h2>
    <div class="vehicle-info">
      <div class="info-block"><strong>Registration:</strong> KDA 456L</div>
      <div class="info-block"><strong>Make:</strong> Toyota</div>
      <div class="info-block"><strong>Model:</strong> Corolla</div>
      <div class="info-block"><strong>Year:</strong> 2018</div>
      <div class="info-block"><strong>Owner:</strong> Kassim Bakari</div>
    </div>

    <div class="btn-group">
      <button class="btn">Edit Profile</button>
      <button class="btn">Add Service Log</button>
    </div>
  </div>

  <!-- Maintenance History -->
  <div class="vehicle-card">
    <div class="section-title">Maintenance History</div>
    <div class="history">
      <ul>
        <li>12 Feb 2024 - Oil Change at Coast Garage</li>
        <li>10 Jan 2024 - Brake Pads Replacement</li>
        <li>23 Dec 2023 - Full Service Checkup</li>
      </ul>
    </div>
  </div>

  <!-- Upcoming Appointments -->
  <div class="vehicle-card">
    <div class="section-title">Upcoming Appointments</div>
    <div class="appointments">
      <ul>
        <li>25 July 2025 - Engine Diagnosis @ Mombasa AutoTech</li>
      </ul>
    </div>
  </div>

  <!-- Vehicle Gallery -->
  <div class="vehicle-card">
    <div class="section-title">Vehicle Photos</div>
    <div class="gallery">
      <img src="https://via.placeholder.com/100" alt="Vehicle 1">
      <img src="https://via.placeholder.com/100" alt="Vehicle 2">
      <img src="https://via.placeholder.com/100" alt="Vehicle 3">
    </div>
  </div>

</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
