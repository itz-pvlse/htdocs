<?php
// profile_settings.php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Updated query to include handle
$stmt = $pdo->prepare("SELECT name, email, phone, profile_photo, handle FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | AutoLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="bg-slate-50">

    <div class="max-w-md mx-auto min-h-screen flex flex-col">
        
        <div class="px-6 pt-10 pb-6 flex items-center gap-4">
            <a href="dashboard.php" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 shadow-sm active:scale-95 transition-all">
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </a>
            <h1 class="text-xl font-black uppercase italic tracking-tighter text-slate-900">Profile <span class="text-indigo-600">Settings</span></h1>
        </div>

        <div class="px-6 flex-1">
            <form action="actions/update_profile.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <div class="flex flex-col items-center mb-8">
                    <div class="relative group">
                        <div class="w-28 h-28 rounded-[2.5rem] bg-indigo-600 overflow-hidden flex items-center justify-center text-white text-4xl font-black shadow-2xl shadow-indigo-200">
                            <?php if(!empty($user['profile_photo'])): ?>
                                <img src="uploads/profiles/<?= e($user['profile_photo']) ?>" class="w-full h-full object-cover" id="preview-img">
                            <?php else: ?>
                                <span id="initials"><?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?></span>
                                <img src="" class="hidden w-full h-full object-cover" id="preview-img">
                            <?php endif; ?>
                        </div>
                        
                        <label for="photo-upload" class="absolute -bottom-2 -right-2 w-10 h-10 bg-white rounded-2xl shadow-lg border border-slate-100 flex items-center justify-center text-indigo-600 cursor-pointer hover:scale-110 transition-all">
                            <i class="fa-solid fa-camera text-xs"></i>
                            <input type="file" id="photo-upload" name="profile_photo" class="hidden" accept="image/*" onchange="previewImage(this)">
                        </label>
                    </div>
                    <div class="mt-4 px-4 py-1.5 bg-indigo-50 rounded-full border border-indigo-100">
                        <p class="text-[11px] font-black text-indigo-600 tracking-tight uppercase">@<?= e($user['handle']) ?></p>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase tracking-widest border border-emerald-100">
                        <i class="fa-solid fa-circle-check mr-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="p-4 rounded-2xl bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest border border-rose-100">
                        <i class="fa-solid fa-circle-exclamation mr-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Unique Handle</label>
                    <div class="relative opacity-60">
                        <i class="fa-solid fa-at absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" value="<?= e($user['handle']) ?>" readonly
                               class="w-full bg-slate-100 border border-slate-200 rounded-[1.5rem] py-4 pl-12 pr-6 text-xs font-bold text-slate-500 cursor-not-allowed outline-none shadow-inner">
                    </div>
                </div>

                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Full Name</label>
                    <div class="relative">
                        <i class="fa-solid fa-user absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 text-xs"></i>
                        <input type="text" name="name" value="<?= e($user['name']) ?>" required
                               class="w-full bg-white border border-slate-100 rounded-[1.5rem] py-4 pl-12 pr-6 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all shadow-sm">
                    </div>
                </div>

                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Email Address</label>
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 text-xs"></i>
                        <input type="email" name="email" value="<?= e($user['email']) ?>" required
                               class="w-full bg-white border border-slate-100 rounded-[1.5rem] py-4 pl-12 pr-6 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all shadow-sm">
                    </div>
                </div>

                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Phone Number</label>
                    <div class="relative">
                        <i class="fa-solid fa-phone absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 text-xs"></i>
                        <input type="text" name="phone" value="<?= e($user['phone']) ?>"
                               class="w-full bg-white border border-slate-100 rounded-[1.5rem] py-4 pl-12 pr-6 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all shadow-sm">
                    </div>
                </div>

                <div class="pt-6">
                    <button type="submit" 
                            class="w-full bg-slate-900 text-white py-5 rounded-[2rem] text-xs font-black uppercase tracking-[0.2em] shadow-xl shadow-slate-200 active:scale-[0.98] transition-all">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        <div class="p-6 mb-10">
            <div class="bg-rose-50 rounded-[2.5rem] p-6 border border-rose-100">
                <h4 class="text-[10px] font-black text-rose-600 uppercase tracking-widest mb-2">Security</h4>
                <p class="text-[10px] text-rose-400 font-medium mb-4">Keep your account safe by updating your password regularly.</p>
                <a href="security.php" class="inline-block text-[10px] font-black text-rose-600 uppercase underline underline-offset-4">Change Password</a>
            </div>
        </div>

    </div>

    <script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('preview-img');
                const initials = document.getElementById('initials');
                
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                if(initials) initials.classList.add('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</body>
</html>
