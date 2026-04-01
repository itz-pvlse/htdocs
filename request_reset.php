<?php
require_once 'config/db.php';
include 'includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password - AUTOLOG</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
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
            margin: 20px 0; 
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
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .btn-neural:hover {
            background: #007aff;
            transform: translateY(-1px);
        }

        @keyframes micro-slide {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-micro { animation: micro-slide 0.4s ease forwards; }
    </style>
</head>

<body class="antialiased">
    <main class="flex-grow flex items-center justify-center px-4 py-10">
        <div class="white-card w-full max-w-[400px] rounded-[32px] p-6 md:p-8 animate-micro">
            
            <div class="flex items-end justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-black tracking-tighter text-slate-900 leading-none uppercase text-nowrap">Recovery</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-2">Reset Password</p>
                </div>
                <div class="text-right">
                    <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-1 rounded-md uppercase">Sec-Key</span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-[11px] font-bold flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-4 p-3 rounded-xl bg-green-50 border border-green-100 text-green-600 text-[11px] font-bold flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="auth/send_reset.php" class="space-y-4">
                <div class="input-group-focus flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-50">
                    <i data-lucide="mail" class="w-4 h-4 text-slate-400"></i>
                    <input type="email" id="email" name="email" required 
                        class="bg-transparent w-full text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400" 
                        placeholder="Registered Email">
                </div>

                <button type="submit" class="btn-neural w-full py-4 rounded-2xl font-black uppercase tracking-widest text-[11px] text-white mt-2 active:scale-95 flex items-center justify-center gap-2">
                    <span>Request Reset Link</span>
                    <i data-lucide="send" class="w-3.5 h-3.5"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-50">
                <a href="login.php" class="flex items-center justify-center gap-2 group">
                    <i data-lucide="arrow-left" class="w-3 h-3 text-slate-300 group-hover:text-blue-600 transition"></i>
                    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest group-hover:text-slate-900 transition">Return to Terminal</span>
                </a>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
