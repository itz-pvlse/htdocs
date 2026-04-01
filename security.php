<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$user_id = $_SESSION['user_id'];

/** * NEW LOGIC: Clear alert on entry
 * This ensures the notification disappears from the Dashboard 
 * as soon as the user "addresses" it by opening this page.
 **/
$clearStmt = $pdo->prepare("UPDATE users SET security_alert = 0 WHERE id = ?");
$clearStmt->execute([$user_id]);

// Fetch the status AFTER clearing it so the pulse alert below doesn't show 
// on the same page load (unless you want it to stay until they leave).
$userStmt = $pdo->prepare("SELECT security_alert FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$userData = $userStmt->fetch(PDO::FETCH_ASSOC);

// Clean up very old sessions
$pdo->query("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings | AutoLog</title>
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
            <a href="profile_settings.php" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 shadow-sm active:scale-95 transition-all">
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </a>
            <h1 class="text-xl font-black uppercase italic tracking-tighter text-slate-900">Security <span class="text-rose-600">Shield</span></h1>
        </div>

        <div class="px-6 flex-1 pb-20">
            
            <?php if ($userData && $userData['security_alert'] == 1): ?>
                <div class="mb-6 p-5 rounded-[2rem] bg-amber-50 border border-amber-100 flex items-start gap-4 animate-pulse">
                    <div class="w-10 h-10 rounded-full bg-amber-500 text-white flex-shrink-0 flex items-center justify-center shadow-lg shadow-amber-200">
                        <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-amber-700">Security Alert</p>
                        <p class="text-[11px] font-bold text-amber-600/80 leading-tight mt-0.5">
                            A login was detected from a new location. If this wasn't you, sign out other devices immediately.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-slate-900 rounded-[2.5rem] p-6 mb-8 text-white relative overflow-hidden">
                <i class="fa-solid fa-shield-halved absolute -right-4 -bottom-4 text-7xl opacity-10"></i>
                <p class="text-[9px] font-black uppercase tracking-[0.2em] opacity-60 mb-1">Account Protection</p>
                <h3 class="text-lg font-black uppercase italic">Identity Verification</h3>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase tracking-widest border border-emerald-100">
                    <i class="fa-solid fa-circle-check mr-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form action="actions/update_security.php" method="POST" class="space-y-6">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Current Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock-open absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 text-xs"></i>
                        <input type="password" name="current_password" required placeholder="••••••••"
                               class="w-full bg-white border border-slate-100 rounded-[1.5rem] py-4 pl-12 pr-6 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 outline-none transition-all shadow-sm">
                    </div>
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">New Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-key absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 text-xs"></i>
                        <input type="password" name="new_password" required placeholder="Minimum 8 characters"
                               class="w-full bg-white border border-slate-100 rounded-[1.5rem] py-4 pl-12 pr-6 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all shadow-sm">
                    </div>
                </div>
                <button type="submit" class="w-full bg-slate-900 text-white py-5 rounded-[2rem] text-xs font-black uppercase tracking-[0.2em] shadow-xl shadow-slate-200 active:scale-[0.98] transition-all">
                    Update Security Keys
                </button>
            </form>

            <div class="mt-12">
                <h2 class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-4 block">Active Devices</h2>
                <div class="space-y-3">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $sessions = $stmt->fetchAll();

                    foreach ($sessions as $session):
                        $is_current = ($session['session_id'] === session_id());
                    ?>
                    <div class="bg-white border <?= $is_current ? 'border-indigo-100 ring-2 ring-indigo-50' : 'border-slate-100' ?> rounded-[2.5rem] p-5 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-2xl <?= $is_current ? 'bg-indigo-600 text-white' : 'bg-slate-50 text-slate-400' ?> flex items-center justify-center">
                                <i class="fa-solid <?= ($session['device_type'] === 'Mobile') ? 'fa-mobile-screen' : 'fa-desktop' ?> text-xs"></i>
                            </div>
                            <div>
                                <p class="text-[11px] font-black uppercase text-slate-700"><?= e($session['browser_name']) ?> on <?= e($session['platform_name']) ?></p>
                                <p class="text-[9px] font-bold uppercase tracking-tighter <?= $is_current ? 'text-indigo-500' : 'text-slate-400' ?>">
                                    <?= $is_current ? 'Current Device' : 'Active ' . date('M j, H:i', strtotime($session['last_activity'])) ?>
                                </p>
                            </div>
                        </div>

                        <?php if (!$is_current): ?>
                        <form action="actions/terminate_specific_session.php" method="POST">
                            <input type="hidden" name="sid" value="<?= e($session['session_id']) ?>">
                            <button type="submit" class="w-8 h-8 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center active:scale-90 transition-all">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <form action="actions/terminate_all_sessions.php" method="POST" onsubmit="return confirm('Sign out of all other devices?')">
                        <button type="submit" class="w-full mt-4 py-4 rounded-2xl bg-slate-50 border border-slate-100 text-rose-600 text-[10px] font-black uppercase tracking-widest hover:bg-rose-50 transition-all">
                            Sign out all other devices
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>