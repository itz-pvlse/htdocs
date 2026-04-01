<?php include 'includes/header.php';?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - AUTOLOG</title>
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
                    <h2 class="text-2xl font-black tracking-tighter text-slate-900 leading-none uppercase">Sign In</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-2">Access Dashboard</p>
                </div>
                <div class="text-right">
                    <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-1 rounded-md uppercase">v3.0 Secure</span>
                </div>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-[11px] font-bold flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                    <?= htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form action="auth/login_process.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>" method="POST" class="space-y-3">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <div class="input-group-focus flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-50">
                    <i data-lucide="mail" class="w-4 h-4 text-slate-400"></i>
                    <input type="email" name="email" required 
                        class="bg-transparent w-full text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400" 
                        placeholder="Email Address">
                </div>

                <div class="input-group-focus flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-50">
                    <i data-lucide="lock" class="w-4 h-4 text-slate-400"></i>
                    <input type="password" id="password" name="password" required 
                        class="bg-transparent w-full text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400" 
                        placeholder="Password">
                    <button type="button" id="toggleBtn" class="text-slate-300 hover:text-slate-600">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                </div>

                <button type="submit" class="btn-neural w-full py-4 rounded-2xl font-black uppercase tracking-widest text-[11px] text-white mt-2 active:scale-95">
                    Login
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-50 grid grid-cols-2 gap-4">
                <a href="register.php" class="flex flex-col group">
                    <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest group-hover:text-blue-600 transition">New User?</span>
                    <span class="text-[11px] font-black text-slate-900 group-hover:underline">Register</span>
                </a>
                <a href="request_reset.php" class="flex flex-col group text-right">
                    <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest group-hover:text-red-500 transition">Lost Access?</span>
                    <span class="text-[11px] font-black text-slate-900 group-hover:underline">Recovery</span>
                </a>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        lucide.createIcons();
        const passwordInput = document.getElementById("password");
        const toggleBtn = document.getElementById("toggleBtn");
        toggleBtn.addEventListener("click", () => {
            const isPassword = passwordInput.type === "password";
            passwordInput.type = isPassword ? "text" : "password";
            toggleBtn.innerHTML = isPassword ? '<i data-lucide="eye-off" class="w-4 h-4"></i>' : '<i data-lucide="eye" class="w-4 h-4"></i>';
            lucide.createIcons();
        });
    </script>
</body>
</html>
