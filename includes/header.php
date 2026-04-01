<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base = "/";
$isLoggedIn = isset($_SESSION['user_id']);

/**
 * Intelligent Dashboard Routing
 */
$dashboard_url = $base . "login.php";
if ($isLoggedIn && isset($_SESSION['role'])) {
    switch($_SESSION['role']) {
        case 'dealer':   $dashboard_url = $base . 'dealer/dealer.php'; break;
        case 'garage':   $dashboard_url = $base . 'garage.php'; break;
        case 'mechanic': $dashboard_url = $base . 'mechanic/mechanic_dashboard.php'; break;
        case 'owner':    $dashboard_url = $base . 'dashboard.php'; break;
        default:         $dashboard_url = $base . 'index.php';
    }
}
?>
<?php include $_SERVER['DOCUMENT_ROOT'].'/includes/ai_bot_ui.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>AUTOLOG | Ecosystem</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        alblue: '#007aff',
                        alred: '#ff3b30',
                    }
                }
            }
        }
    </script>

    <style>
        html, body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(180deg, #001F3F 0%, #000000 100%) !important; 
            background-attachment: fixed !important;
            color: #fff; 
            margin: 0; 
            min-height: 100vh;
        }
        
        #page-container, #content-wrap, #content-area { background: transparent !important; }
        a { text-decoration: none !important; }

        #mainNav { 
            width: 100%; position: fixed; top: 0; z-index: 1050;
            background: rgba(0, 0, 0, 0.4); 
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        @keyframes menu-reveal {
            0% { opacity: 0; transform: translateY(-10px) scale(0.98); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        .animate-menu-reveal { animation: menu-reveal 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

        #page-container { display: flex; flex-direction: column; min-height: 100vh; }
        #content-wrap { flex: 1; padding-top: 88px; }
        #mobile-menu { max-height: 85vh; overflow-y: auto; }

        .alva-pulse-ring {
            position: absolute; inset: 0; border-radius: 9999px;
            border: 2px solid #007aff; opacity: 0;
            animation: alva-ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
        }
        @keyframes alva-ping { 0% { transform: scale(1); opacity: 0.8; } 100% { transform: scale(1.5); opacity: 0; } }
    </style>
</head>

<body>

<div id="page-container">
    <nav id="mainNav">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-4 flex items-center justify-between relative z-50">
            <div class="flex items-center gap-10">
                <a href="<?= $base ?>index.php" class="flex items-center group">
                    <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center shadow-lg transition-all duration-300 group-hover:scale-105 group-hover:rotate-[-5deg]">
                        <img src="<?= $base ?>assets/toplogo.png" alt="AL" class="h-8 w-8 object-contain">
                    </div>
                    <div class="ml-3 flex flex-col justify-center">
                        <h1 class="text-2xl font-black italic tracking-tighter leading-none">
                            <span class="text-white uppercase">AUTO</span><span class="text-alred uppercase">LOG</span>
                        </h1>
                        <span class="text-[8px] font-bold text-white/40 uppercase tracking-[0.4em] mt-1 ml-0.5">ECOSYSTEM</span>
                    </div>
                </a>

                <div class="hidden lg:flex items-center gap-6">
                    <a href="<?= $base ?>index.php" class="text-xs font-bold text-white/50 hover:text-white transition uppercase tracking-widest">Home</a>
                    <a href="<?= $base ?>dealer/cars.php" class="text-xs font-bold text-white/50 hover:text-white transition uppercase tracking-widest">Cars</a>
                    <a href="<?= $base ?>garages.php" class="text-xs font-bold text-white/50 hover:text-white transition uppercase tracking-widest">Service</a>
                </div>
            </div>

            <div class="flex items-center gap-3">
                
                <button id="aiToggleTrigger" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 hover:bg-white/10 transition-all group relative">
                    <div class="alva-pulse-ring"></div>
                    <img src="<?= $base ?>allo_avatar.png" alt="ALVA" class="w-6 h-6 rounded-full object-cover border border-blue-500/30">
                    <span class="hidden md:inline-block text-[10px] font-black text-white uppercase tracking-tighter">ALVA AI</span>
                </button>

                <?php if ($isLoggedIn): ?>
                    <a href="<?= $dashboard_url ?>" class="hidden md:inline-flex px-5 py-2 rounded-full bg-alblue text-white text-[11px] font-black uppercase tracking-tighter hover:bg-blue-600 transition shadow-lg shadow-alblue/20">
                        Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= $base ?>login.php" class="hidden md:block text-sm font-bold text-white/70 hover:text-white transition px-2">Log in</a>
                <?php endif; ?>
                
                <button id="menu-btn" class="text-white p-2 hover:bg-white/5 rounded-xl transition">
                    <i data-lucide="menu"></i>
                </button>
            </div>
        </div>

        <div id="mobile-menu" class="hidden absolute top-24 left-6 right-6 bg-[#0a0c10] border border-white/10 rounded-[40px] z-50 shadow-[0_20px_50px_rgba(0,0,0,0.6)] animate-menu-reveal flex flex-col overflow-hidden" style="max-height: calc(100vh - 120px);">
    <div class="flex-1 overflow-y-auto p-5 pb-2 custom-scrollbar">
        
        <p class="text-[10px] font-black text-white/20 uppercase tracking-[0.2em] ml-5 mb-3">Navigation</p>
        <div class="flex flex-col gap-1.5">
            <a href="<?= $base ?>index.php" class="flex items-center gap-4 p-3.5 rounded-[24px] bg-white/[0.02] border border-white/5 hover:bg-white/[0.05] transition group">
                <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white/40 group-hover:text-alblue transition"><i data-lucide="home" class="w-5 h-5"></i></div>
                <span class="text-white/90 font-bold text-sm">Home</span>
            </a>

            <a href="<?= $base ?>dealer/cars.php" class="flex items-center gap-4 p-3.5 rounded-[24px] bg-white/[0.02] border border-white/5 hover:bg-white/[0.05] transition group">
                <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white/40 group-hover:text-alblue transition"><i data-lucide="car" class="w-5 h-5"></i></div>
                <span class="text-white/90 font-bold text-sm">Explore Cars</span>
            </a>

            <a href="<?= $base ?>garages.php" class="flex items-center gap-4 p-3.5 rounded-[24px] bg-white/[0.02] border border-white/5 hover:bg-white/[0.05] transition group">
                <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white/40 group-hover:text-alblue transition"><i data-lucide="wrench" class="w-5 h-5"></i></div>
                <span class="text-white/90 font-bold text-sm">Service & Repairs</span>
            </a>

            <a href="<?= $base ?>dealer/dealers.php" class="flex items-center gap-4 p-3.5 rounded-[24px] bg-white/[0.02] border border-white/5 hover:bg-white/[0.05] transition group">
                <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white/40 group-hover:text-alblue transition"><i data-lucide="store" class="w-5 h-5"></i></div>
                <span class="text-white/90 font-bold text-sm">Partner Showrooms</span>
            </a>

            <a href="<?= $base ?>pricing.php" class="flex items-center gap-4 p-3.5 rounded-[24px] bg-white/[0.02] border border-white/5 hover:bg-white/[0.05] transition group">
                <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white/40 group-hover:text-alblue transition"><i data-lucide="credit-card" class="w-5 h-5"></i></div>
                <span class="text-white/90 font-bold text-sm">Pricing Plans</span>
            </a>

            <div class="grid grid-cols-2 gap-1.5">
                <a href="<?= $base ?>about.php" class="flex items-center gap-3 p-3.5 rounded-[24px] bg-white/[0.02] border border-white/5 hover:bg-white/10 transition group">
                    <i data-lucide="info" class="w-4 h-4 text-white/20 group-hover:text-alblue"></i>
                    <span class="text-white/70 font-bold text-xs">About</span>
                </a>
                <a href="<?= $base ?>contact.php" class="flex items-center gap-3 p-3.5 rounded-[24px] bg-white/[0.02] border border-white/5 hover:bg-white/10 transition group">
                    <i data-lucide="mail" class="w-4 h-4 text-white/20 group-hover:text-alblue"></i>
                    <span class="text-white/70 font-bold text-xs">Contact</span>
                </a>
            </div>
        </div>
    </div>



            <div class="p-5 bg-white/[0.02] border-t border-white/5 rounded-b-[40px]">
                <a href="<?= $dashboard_url ?>" class="relative block group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-alblue to-blue-600 rounded-[30px] blur opacity-20 group-hover:opacity-40 transition"></div>
                    <div class="relative flex items-center justify-between p-4 bg-alblue rounded-[25px] transition group-active:scale-95">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-black/10 rounded-xl flex items-center justify-center text-black">
                                <i data-lucide="<?= $isLoggedIn ? 'layout-grid' : 'log-in' ?>" class="w-5 h-5"></i>
                            </div>
                            <span class="text-black font-black uppercase text-xs"><?= $isLoggedIn ? 'Dashboard' : 'Login' ?></span>
                        </div>
                        <i data-lucide="arrow-up-right" class="w-4 h-4 text-black"></i>
                    </div>
                </a>
                <?php if ($isLoggedIn): ?>
                    <div class="mt-4 flex justify-center">
                        <a href="<?= $base ?>auth/logout.php" class="text-[10px] font-black text-red-500/60 hover:text-red-500 uppercase tracking-widest flex items-center gap-2">
                            <i data-lucide="log-out" class="w-3 h-3"></i> Sign out
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div id="content-wrap">
        <div id="content-area" class="min-h-[80vh]">
            <script>
        if (window.lucide) { lucide.createIcons(); }

        const menuBtn = document.getElementById('menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const aiToggleTrigger = document.getElementById('aiToggleTrigger');

        // Toggle Mobile Menu
        menuBtn.addEventListener('click', (e) => {
            e.stopPropagation(); 
            const isHidden = mobileMenu.classList.toggle('hidden');
            menuBtn.innerHTML = isHidden ? '<i data-lucide="menu"></i>' : '<i data-lucide="x"></i>';
            lucide.createIcons();
        });

        // Close menu on outside click
        document.addEventListener('click', (e) => {
            if (!mobileMenu.classList.contains('hidden') && !mobileMenu.contains(e.target)) {
                mobileMenu.classList.add('hidden');
                menuBtn.innerHTML = '<i data-lucide="menu"></i>';
                lucide.createIcons();
            }
        });

        // Combined ALVA Trigger
        aiToggleTrigger.onclick = (e) => {
            e.preventDefault();
            // Close mobile menu if open
            if (!mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.add('hidden');
                menuBtn.innerHTML = '<i data-lucide="menu"></i>';
                lucide.createIcons();
            }
            if (typeof openChat === "function") openChat();
        };
    </script>
