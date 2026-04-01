<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../config/db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/src/SMTP.php';
require '../vendor/phpmailer/src/Exception.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

    $stmt = $pdo->prepare("SELECT name FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // SECURITY: Always show success to prevent email fishing/enumeration
    $_SESSION['success'] = "If an account exists for this email, a reset link has been dispatched.";

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?");
        $stmt->execute([$token, $expiry, $email]);

        // FIX: Pointing to the actual reset page, not the request page
        $reset_link = "https://autolog.xo.je/auth/reset_password.php?token=$token";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            
            // SECURITY: You should ideally move these to a separate config file 
            // that is NOT in your public_html folder.
            $mail->Username   = 'kasosarlin02@gmail.com'; 
            $mail->Password   = 'noutkvmorrfanqfh'; // CHANGE THIS IMMEDIATELY in Google Account
            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('kasosarlin02@gmail.com', 'AutoLog');
            $mail->addAddress($email, $user['name']);
            $mail->Subject = 'AutoLog Security: Password Reset Request';
            $mail->isHTML(true);
            
            // Styled email body
            $mail->Body = "
            <div style='font-family: sans-serif; color: #333; max-width: 600px;'>
                <h2>Password Reset Request</h2>
                <p>Hi " . htmlspecialchars($user['name']) . ",</p>
                <p>A password reset was requested for your AutoLog account. Click the button below to secure your identity with a new password:</p>
                <div style='margin: 30px 0;'>
                    <a href='$reset_link' style='background: #000; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Reset Password</a>
                </div>
                <p style='font-size: 12px; color: #777;'>If you did not request this, please ignore this email. This link expires in 1 hour.</p>
            </div>";

            $mail->send();
        } catch (Exception $e) {
            // Log error internally, don't show specific SMTP details to user
            error_log("Mailer Error: {$mail->ErrorInfo}");
        }
    }

    header("Location: ../request_reset.php");
    exit();
}
