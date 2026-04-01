<?php
session_start();
require_once '../config/db.php';

// PHPMailer Classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/src/SMTP.php';
require '../vendor/phpmailer/src/Exception.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') {
    header("Location: ../login.php"); exit;
}

$user_id = $_SESSION['user_id'];
// Added 'email' to the select so we can notify the dealer
$stmt = $pdo->prepare("SELECT verified, name, email FROM dealers WHERE user_id = ?");
$stmt->execute([$user_id]);
$dealer = $stmt->fetch();

// HANDLE NAMED UPLOADS
if (isset($_POST['submit_verification'])) {
    $uploaded_docs = [];
    $target_dir = "../uploads/verification/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $files_to_upload = ['permit', 'id_card'];
    
    foreach ($files_to_upload as $input_name) {
        if (!empty($_FILES[$input_name]['name'])) {
            $ext = pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
            $filename = $input_name . "_" . $user_id . "_" . time() . "." . $ext;
            
            if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target_dir . $filename)) {
                $uploaded_docs[$input_name] = "uploads/verification/" . $filename;
            }
        }
    }

    if (count($uploaded_docs) > 0) {
        $docs_json = json_encode($uploaded_docs);
        // Using column 18 (verified) = 2 for "Pending"
        $upd = $pdo->prepare("UPDATE dealers SET verified = 2, verification_documents = ? WHERE user_id = ?");
        $upd->execute([$docs_json, $user_id]);

        // --- EMAIL LOGIC START ---
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'kasosarlin02@gmail.com';   
            $mail->Password   = 'noutkvmorrfanqfh';  
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->setFrom('kasosarlin02@gmail.com', 'AutoLog');
            $mail->isHTML(true);

            // 1. Notify Admin (You)
            $mail->addAddress('kasosarlin02@gmail.com', 'AutoLog Admin');
            $mail->Subject = 'NEW VERIFICATION REQUEST: ' . $dealer['name'];
            $mail->Body    = "<h3>New Documents Uploaded</h3>
                              <p>Dealer: <b>{$dealer['name']}</b> (ID: $user_id)</p>
                              <p>Please log in to the admin panel to review the Business Permit and ID Card.</p>";
            $mail->send();

            // 2. Notify Dealer (Confirmation)
            $mail->clearAddresses();
            $mail->addAddress($dealer['email'], $dealer['name']);
            $mail->Subject = 'Verification Documents Received';
            $mail->Body    = "<h3>Hi {$dealer['name']},</h3>
                              <p>We have received your business documents. Our team will review them within 48 hours.</p>
                              <p>You will receive an email once your showroom status is updated.</p>";
            $mail->send();

        } catch (Exception $e) {
            // Silently fail or log error so it doesn't break the page redirect
            error_log("Mailer Error: {$mail->ErrorInfo}");
        }
        // --- EMAIL LOGIC END ---

        header("Location: verify.php?success=1"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Verification | Autolog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@700&family=Outfit:wght@300;400;700;900&display=swap');
        body { font-family: 'Outfit', sans-serif; background: #f0f4f8; }
        .heading-tech { font-family: 'Space Grotesk', sans-serif; }
    </style>
</head>
<body class="antialiased p-6 lg:p-12">

    <div class="max-w-xl mx-auto">
        <a href="settings.php" class="inline-flex items-center gap-2 text-slate-400 hover:text-indigo-600 font-bold text-[10px] uppercase mb-8 tracking-widest transition-all">
            <i class="fas fa-arrow-left"></i> Return to Settings
        </a>

        <div class="bg-white rounded-[2.5rem] p-8 lg:p-12 shadow-2xl shadow-slate-200/60 border border-white">
           <div class="w-20 h-20 bg-indigo-600 rounded-[2rem] flex items-center justify-center text-white text-3xl mb-8 shadow-xl shadow-indigo-200 rotate-3">
    <i class="fas fa-shield-halved"></i>
</div>

            
            <h1 class="text-4xl font-black text-slate-900 tracking-tighter heading-tech italic uppercase mb-2">Dealer <span class="text-indigo-600">Verification</span></h1>
            <p class="text-slate-500 font-medium mb-10 leading-relaxed">Secure your showroom badge by uploading legal business documentation. This builds trust with potential car buyers.</p>

            <?php if($dealer['verified'] == 2): ?>
                <div class="p-10 bg-slate-900 rounded-[2rem] text-center border border-slate-800">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-white/10 rounded-full mb-6 text-amber-400 animate-pulse">
                        <i class="fas fa-hourglass-half text-2xl"></i>
                    </div>
                    <h3 class="text-white font-black uppercase text-sm tracking-[0.3em] mb-2">Review in Progress</h3>
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Our compliance team is verifying your documents. This usually takes 48 hours.</p>
                </div>
            <?php elseif($dealer['verified'] == 1): ?>
                <div class="p-10 bg-emerald-500 rounded-[2rem] text-center shadow-xl shadow-emerald-200">
                    <i class="fas fa-certificate text-white text-5xl mb-4"></i>
                    <h3 class="text-white font-black uppercase text-lg tracking-widest">Showroom Verified</h3>
                    <p class="text-emerald-100 text-xs mt-2 font-bold uppercase">Your trust badge is now active on all listings.</p>
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div class="space-y-4">
                        <div class="group">
                            <label class="block p-8 border-2 border-dashed border-slate-200 rounded-[2rem] hover:border-indigo-600 hover:bg-indigo-50/30 transition-all cursor-pointer text-center">
                                <i class="fas fa-file-invoice text-slate-300 group-hover:text-indigo-600 text-3xl mb-4 transition-colors"></i>
                                <span class="block text-xs font-black uppercase text-slate-600 tracking-widest mb-1">Business Permit / License</span>
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tighter block mb-4">Legal business registration certificate</span>
                                <input type="file" name="permit" class="hidden" required onchange="updateLabel(this)">
                                <div class="inline-flex px-4 py-2 bg-slate-100 text-slate-500 rounded-lg text-[9px] font-black uppercase tracking-widest status-text">Choose File</div>
                            </label>
                        </div>

                        <div class="group">
                            <label class="block p-8 border-2 border-dashed border-slate-200 rounded-[2rem] hover:border-indigo-600 hover:bg-indigo-50/30 transition-all cursor-pointer text-center">
                                <i class="fas fa-id-badge text-slate-300 group-hover:text-indigo-600 text-3xl mb-4 transition-colors"></i>
                                <span class="block text-xs font-black uppercase text-slate-600 tracking-widest mb-1">Owner ID / Passport</span>
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tighter block mb-4">Scanned copy of National ID or Passport</span>
                                <input type="file" name="id_card" class="hidden" required onchange="updateLabel(this)">
                                <div class="inline-flex px-4 py-2 bg-slate-100 text-slate-500 rounded-lg text-[9px] font-black uppercase tracking-widest status-text">Choose File</div>
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="submit_verification" class="w-full py-5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-[1.5rem] font-black text-xs uppercase tracking-[0.3em] shadow-2xl shadow-indigo-200 transition-all active:scale-95">
                        Submit Documents
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="mt-12 text-center">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] flex items-center justify-center gap-2">
                <i class="fas fa-shield-halved text-indigo-400"></i> All documents are encrypted and stored securely
            </p>
        </div>
    </div>

<script>
function updateLabel(input) {
    const statusDiv = input.parentElement.querySelector('.status-text');
    if (input.files.length > 0) {
        statusDiv.innerText = "File Selected: " + input.files[0].name;
        statusDiv.classList.remove('bg-slate-100', 'text-slate-500');
        statusDiv.classList.add('bg-emerald-100', 'text-emerald-600');
    }
}
</script>
</body>
</html>
