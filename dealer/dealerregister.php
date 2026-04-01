<?php require_once '../config/db.php'; ?>
<?php include '../includes/header.php'; ?>
     
    <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register - AUTOLOG</title>
  <link href="<?= $base ?>assets/css/register.css" rel="stylesheet" />
</head>
<body>

  <div class="form-container">
    <form action="../auth/register_process.php" method="POST" class="form-box">

      <h2>Create Account</h2>

      <?php
        if (isset($_GET['error'])) {
          echo "<p style='color:red;'>".htmlspecialchars($_GET['error'])."</p>";
        }
      ?>

      <label for="name">Dealership Name:</label>
      <input type="text" id="name" name="name" required />

      <label for="email">Dealership Email:</label>
      <input type="email" id="email" name="email" required />

      <label for="password">Password:</label>
     <div style="position: relative; margin-bottom: 1rem;">
  <input type="password" id="password" name="password" required placeholder="Enter Password" style="padding-right: 40px;">
  <span id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">👁️</span>
</div>


      <label for="role">Account Type:</label>
      <select id="role" name="role" required>
        <option value="">Select Role</option>
        <option value="owner">Car Owner</option>
        <option value="garage">Garage/Mechanic</option>
		<option value="dealer">Car Dealership</option>
      </select>

      <button type="submit">Register</button>

      <p>Already have an account? <a href="dealerlogin.php">Login here</a></p>
    </form>
  </div>
<script>
  const passwordInput = document.getElementById("password");
  const togglePassword = document.getElementById("togglePassword");

  togglePassword.addEventListener("click", function () {
    const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
    passwordInput.setAttribute("type", type);

    // Optional: Change icon when toggled (if you use emojis or icons)
    this.textContent = type === "password" ? "👁️" : "🙈";
  });
</script>

			
        </main>
  
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Core theme JS-->
        <script src="js/scripts.js"></script>
    </body>
</html>
