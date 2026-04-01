<?php require_once 'config/db.php'; ?>
<?php include 'includes/header.php'; ?>
<head>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: #f9f9f9;
      color: #222;
    }

    header {
      background-color: #101820;
      color: #fff;
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    header h1 {
      margin: 0;
      font-size: 1.5rem;
    }

    .vehicle-container {
      max-width: 1000px;
      margin: 2rem auto;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,0,0,0.05);
      padding: 2rem;
    }

    .vehicle-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #ddd;
      padding-bottom: 1rem;
      margin-bottom: 1.5rem;
    }

    .vehicle-header img {
      width: 220px;
      height: auto;
      border-radius: 6px;
      object-fit: cover;
    }

    .vehicle-header .info {
      flex: 1;
      margin-left: 2rem;
    }

    .info h2 {
      margin: 0;
      font-size: 2rem;
      color: #333;
    }

    .info p {
      margin: 0.3rem 0;
      color: #555;
    }

    .section {
      margin-bottom: 2rem;
    }

    .section h3 {
      margin-bottom: 0.8rem;
      color: #444;
    }

    .section ul {
      list-style: none;
      padding: 0;
    }

    .section ul li {
      padding: 0.5rem 0;
      border-bottom: 1px solid #eee;
    }

    .status-badge {
      display: inline-block;
      background-color: #1c7430;
      color: white;
      padding: 0.25rem 0.7rem;
      font-size: 0.85rem;
      border-radius: 12px;
    }

    .report-box {
      background: #f2f2f2;
      padding: 1rem;
      border-left: 4px solid #007bff;
      margin-bottom: 1rem;
      border-radius: 4px;
    }
	
	
  .qr-section {
    margin-top: 2rem;
    text-align: center;
  }

  #vehicleQr {
    margin: 1rem auto;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 8px;
    background-color: #fff;
  }


	
  </style>
</head>
<body>

  <header>
    
    <span>Vehicle History Report</span>
  </header>

  <div class="vehicle-container">
    <div class="vehicle-header">
      <img src="https://www.carlogos.org/car-logos/toyota-logo-2019-640.png" alt="Vehicle Image" />
      <div class="info">
        <h2>Toyota Axio 2015</h2>
        <p>Chassis Number: KDH2015123456</p>
        <p>Registration: KDB 567R</p>
        <p>Status: <span class="status-badge">Clean</span></p>
      </div>
    </div>

<div class="qr-section">
  <h2>Scan QR Code</h2>
  <canvas id="vehicleQr"></canvas>
</div>


    <div class="section">
      <h3>Ownership History</h3>
      <ul>
        <li>Imported: Jan 2016</li>
        <li>Owner 1: John Doe (2016 - 2020)</li>
        <li>Owner 2: Sarah Kimani (2020 - Present)</li>
      </ul>
    </div>

    <div class="section">
      <h3>Maintenance Logs</h3>
      <ul>
        <li>March 2024 - Full service @ AutoFix Garage</li>
        <li>August 2023 - Brake pad replacement</li>
        <li>Jan 2023 - Oil & filter change</li>
      </ul>
    </div>

    <div class="section">
      <h3>Accident History</h3>
      <ul>
        <li>No major accidents reported.</li>
      </ul>
    </div>

    <div class="section">
      <h3>Vehicle Reports</h3>
      <div class="report-box">
        🚗 Odometer verified: 92,000 km<br>
        ✅ Logbook ownership verified with NTSA<br>
        ✅ Service intervals maintained
      </div>
    </div>
  </div>
  
   <?php include 'includes/footer.php'; ?>
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Core theme JS-->
        <script src="js/scripts.js"></script>
  

<script>
  // Replace with your vehicle profile page's unique URL
  const vehicleProfileURL = window.location.href;

  const qr = new QRious({
    element: document.getElementById('vehicleQr'),
    value: vehicleProfileURL,
    size: 150,
    level: 'H'
  });
</script>


</body>
</html>
