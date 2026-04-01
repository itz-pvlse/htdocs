<?php require_once 'config/db.php'; ?>
<?php include 'includes/header.php'; ?>
     <?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'config/db.php';
$error = '';
?>
    <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - AUTOLOG</title>
  <link href="<?= $base ?>assets/css/register.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
<body>


<br><br><br>
  <div class="form-container">
    <form action="auth/login_process.php" method="POST" class="form-box">

      <label for="email">Garage Email:</label>
      <input type="email" id="email" name="email" required />

      <label for="password">Password:</label>
      <div style="position: relative;">
  <input type="password" id="password" name="password" required>
  <span id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">👁️</span>
</div>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['error'])) {
    echo "<p style='color: red; font-weight: bold;'>".$_SESSION['error']."</p>";
    unset($_SESSION['error']);
}
?>

	  
      <button type="submit">Login</button>
	  
	  <br>
	  <p>Don't have an account? <a href="registergarage.php">Register here</a></p></br>
	  <p><a href="request_reset.php">Forgot your password?</a></p>
<?php if (!empty($error)): ?>
<div class="alert alert-danger mt-3" role="alert">
  <?php echo $error; ?>
</div>
<?php endif; ?>

    </form>
    
 
			
			
        </main>
   
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Core theme JS-->
        <script src="js/scripts.js"></script>
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

		
    </body>
	<?php if (!empty($error)): ?>
    <script>
        alert("<?php echo $error; ?>");
    </script>
<?php endif; ?>


</html>
