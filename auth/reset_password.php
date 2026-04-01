<?php
// 1. Logic First
require('../config/db.php');

// Start session if not already started in header
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$token = $_GET['token'] ?? '';
$isValid = false;
$user_data = null;

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $user_data = $stmt->fetch();

    if ($user_data && strtotime($user_data['token_expiry']) > time()) {
        $isValid = true;
    }
}

// 2. Include Header (This likely has your opening <html> and <head>)
include '../includes/header.php'; 
?>

<style>
    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(180deg, #001F3F 0%, #000000 100%) !important;
        background-attachment: fixed !important;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }
    .white-card {
        background: #ffffff;
        box-shadow: 0 50px 100px -20px rgba(0, 0, 0, 0.6);
        position: relative;
        overflow: hidden;
    }
    .white-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: linear-gradient(90deg, #007aff, #ff3b30);
    }
    .input-group-focus {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid #e2e8f0;
    }
    .input-group-focus:focus-within {
        border-color: #007aff;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.05);
    }
    .btn-neural {
        background: #000;
        transition: all 0.3s ease;
    }
    .btn-neural:hover {
        background: #007aff;
        transform: translateY(-1px);
    }
</style>

<main class="flex-grow flex items-center justify-center px-4 py-10">
    <?php if ($isValid): ?>
        <div class="white-card w-full max-w-[400px] rounded-[32px] p-6 md:p-8">
            <div class="flex items-end justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-black tracking-tighter text-slate-900 leading-none uppercase">Security</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-2">Update Credentials</p>
                </div>
                <div class="text-right">
                    <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-1 rounded-md uppercase">Verified</span>
                </div>
            </div>

            <form id="resetForm" action="reset_process.php" method="post" class="space-y-3">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="input-group-focus flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-50">
                    <i data-lucide="shield-check" class="w-4 h-4 text-slate-400"></i>
                    <input type="password" id="new_password" name="new_password" required 
                        class="bg-transparent w-full text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400" 
                        placeholder="New Password">
                </div>

                <div class="input-group-focus flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-50">
                    <i data-lucide="lock" class="w-4 h-4 text-slate-400"></i>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                        class="bg-transparent w-full text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400" 
                        placeholder="Confirm New Password">
                </div>

                <button type="submit" class="btn-neural w-full py-4 rounded-2xl font-black uppercase tracking-widest text-[11px] text-white mt-2 active:scale-95">
                    Sync Credentials
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-50 text-center">
                <a href="../login.php" class="text-[10px] font-black text-slate-300 uppercase tracking-widest hover:text-slate-900 transition">
                    Cancel Operation
                </a>
            </div>
        </div>

    <?php else: ?>
        <div class="bg-white p-10 rounded-[32px] text-center max-w-[400px] shadow-2xl">
            <h2 class="font-900 text-xl uppercase tracking-tighter mb-2">Access Denied</h2>
            <p class="text-slate-400 text-[10px] uppercase font-bold tracking-widest mb-6">Token Expired or Invalid</p>
            <a href="../request_reset.php" class="block bg-black text-white p-4 rounded-2xl font-black text-[10px] uppercase tracking-widest">
                Request New Token
            </a>
        </div>
    <?php endif; ?>
</main>

<script>
    // Lucide check: Only run if lucide is defined
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }
    
    const resetForm = document.getElementById('resetForm');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            const pass = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            if (pass !== confirm) {
                e.preventDefault();
                alert("Passwords do not match!");
            }
        });
    }
</script>

<?php include '../includes/footer.php'; ?>
