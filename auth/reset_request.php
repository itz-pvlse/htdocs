<?php
include '../includes/header.php';
require('../config/db.php'); // Uses $pdo

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Find user by token using PDO
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // Check if token is expired
        if (strtotime($user['token_expiry']) > time()) {
            // Token valid - Render Styled Form
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>New Password - AUTOLOG</title>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
                <script src="https://unpkg.com/lucide@latest"></script>
                <style>
                    :root {
                        --primary-color: #2563eb;
                        --primary-hover: #1d4ed8;
                        --bg-gradient: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                        --card-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                        --text-main: #111827;
                        --text-muted: #6b7280;
                    }

                    body {
                        margin: 0;
                        font-family: 'Inter', sans-serif;
                        background: var(--bg-gradient);
                        min-height: 100vh;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        padding: 20px;
                    }

                    .form-box {
                        background: rgba(255, 255, 255, 0.98);
                        backdrop-filter: blur(10px);
                        padding: 40px;
                        border-radius: 20px;
                        box-shadow: var(--card-shadow);
                        max-width: 420px;
                        width: 100%;
                        border: 1px solid rgba(255, 255, 255, 0.3);
                        box-sizing: border-box;
                    }

                    .header-section {
                        text-align: center;
                        margin-bottom: 30px;
                    }

                    .header-section h2 {
                        margin: 0 0 8px 0;
                        font-weight: 800;
                        font-size: 1.8rem;
                        letter-spacing: -0.025em;
                    }

                    .header-section p {
                        color: var(--text-muted);
                        font-size: 0.95rem;
                        margin: 0;
                    }

                    .input-group {
                        margin-bottom: 20px;
                    }

                    label {
                        display: block;
                        margin-bottom: 8px;
                        font-weight: 600;
                        font-size: 0.875rem;
                        color: #374151;
                    }

                    .password-wrapper {
                        position: relative;
                    }

                    input {
                        width: 100%;
                        padding: 12px 16px;
                        border-radius: 10px;
                        border: 1px solid #d1d5db;
                        font-size: 1rem;
                        transition: all 0.2s ease;
                        box-sizing: border-box;
                    }

                    input:focus {
                        outline: none;
                        border-color: var(--primary-color);
                        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
                    }

                    .toggle-btn {
                        position: absolute;
                        right: 12px;
                        top: 50%;
                        transform: translateY(-50%);
                        background: none;
                        border: none;
                        color: var(--text-muted);
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                    }

                    .reset-btn {
                        width: 100%;
                        padding: 14px;
                        background: var(--primary-color);
                        color: white;
                        border: none;
                        border-radius: 12px;
                        font-weight: 700;
                        font-size: 1rem;
                        cursor: pointer;
                        transition: all 0.2s;
                        margin-top: 10px;
                    }

                    .reset-btn:hover {
                        background: var(--primary-hover);
                        transform: translateY(-1px);
                    }

                    .links {
                        text-align: center;
                        margin-top: 25px;
                        font-size: 0.9rem;
                    }

                    .links a {
                        color: var(--primary-color);
                        text-decoration: none;
                        font-weight: 600;
                        display: inline-flex;
                        align-items: center;
                        gap: 5px;
                    }
                </style>
            </head>
            <body>
                <div class="form-box">
                    <div class="header-section">
                        <h2>Set New Password</h2>
                        <p>Your identity is verified. Please choose a strong new password.</p>
                    </div>

                    <form id="resetForm" action="reset_process.php" method="post">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="input-group">
                            <label>New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="new_password" name="new_password" placeholder="••••••••" required>
                                <button type="button" class="toggle-btn" onclick="togglePass('new_password', 'eye1')">
                                    <i data-lucide="eye" id="eye1" size="18"></i>
                                </button>
                            </div>
                        </div>

                        <div class="input-group">
                            <label>Confirm Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                                <button type="button" class="toggle-btn" onclick="togglePass('confirm_password', 'eye2')">
                                    <i data-lucide="eye" id="eye2" size="18"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="reset-btn">Update Password</button>
                    </form>

                    <div class="links">
                        <a href="../login.php">
                            <i data-lucide="arrow-left" size="16"></i> Back to Login
                        </a>
                    </div>
                </div>

                <script>
                    lucide.createIcons();

                    function togglePass(inputId, iconId) {
                        const input = document.getElementById(inputId);
                        const icon = document.getElementById(iconId);
                        const isPassword = input.type === "password";
                        input.type = isPassword ? "text" : "password";
                        icon.setAttribute("data-lucide", isPassword ? "eye-off" : "eye");
                        lucide.createIcons();
                    }

                    document.getElementById('resetForm').addEventListener('submit', function(e) {
                        const pass = document.getElementById('new_password').value;
                        const confirm = document.getElementById('confirm_password').value;
                        if (pass !== confirm) {
                            e.preventDefault();
                            alert("Passwords do not match!");
                        }
                    });
                </script>
            </body>
            </html>
            <?php
        } else {
            renderMessage("Token has expired.", "Please request a new password reset.");
        }
    } else {
        renderMessage("Invalid token.", "The reset link is incorrect or has already been used.");
    }
} else {
    renderMessage("No token provided.", "Please use the link sent to your email.");
}

// Helper function to show errors in the same style
function renderMessage($title, $sub) {
    echo "
    <div style='font-family:Inter,sans-serif; background:linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height:100vh; display:flex; justify-content:center; align-items:center;'>
        <div style='background:white; padding:40px; border-radius:20px; box-shadow:0 20px 25px rgba(0,0,0,0.1); text-align:center; max-width:400px;'>
            <h2 style='margin:0 0 10px 0;'>$title</h2>
            <p style='color:#6b7280; margin-bottom:20px;'>$sub</p>
            <a href='reset_request.php' style='color:#2563eb; text-decoration:none; font-weight:600;'>Try Again</a>
        </div>
    </div>";
}
?>
