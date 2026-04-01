<?php
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']); 
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>AutoLog — Track. Service. Sell.</title>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#001F3F">

    <meta name="description" content="AutoLog — Kenya’s trusted car history and maintenance platform...">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:title" content="AutoLog — Track. Service. Sell.">
<meta property="og:description" content="Kenya’s trusted car history and maintenance platform. Track service records, verify vehicle history, and sell smarter.">
<meta property="og:url" content="https://autolog.xo.je/">
<meta property="og:site_name" content="AutoLog">
<meta property="og:image" content="https://autolog.xo.je/autolog-preview.jpg">
<meta property="og:image:width" content="672">
<meta property="og:image:height" content="405">
<meta property="og:image:alt" content="AutoLog — Track. Service. Sell.">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="AutoLog — Track. Service. Sell.">
<meta name="twitter:description" content="Kenya’s trusted car history and maintenance platform. Track service records, verify vehicle history, and sell smarter.">
<meta name="twitter:image" content="https://autolog.xo.je/autolog-preview.jpg">
    <script src="https://unpkg.com/lucide@latest"></script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        alblue: '#2563eb',
                        bgdeep: '#0b0b0b',
                        card: 'rgba(255, 255, 255, 0.03)',
                        muted: '#9ca3af'
                    }
                }
            }
        }
    </script>

    <style>
        body { background: #0b0b0b; color: #fff; font-family: 'Inter', sans-serif; }
        
        .hero-bg {
            background: radial-gradient(circle at top right, rgba(37, 99, 235, 0.1), transparent),
                        linear-gradient(180deg, #001F3F 0%, #0b0b0b 100%);
        }

        .glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .search-glow:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
        }

        /* Smooth scroll for horizontal sections */
        .h-scroll::-webkit-scrollbar { height: 4px; }
        .h-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade { animation: fadeIn 0.5s ease-out forwards; }
        @keyframes pulse-green {
    0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
    70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
    100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}
.health-pulse {
    animation: pulse-green 2s infinite;
}
    </style>
</head>
<?php include 'includes/ai_bot_ui.php'; ?>
<body class="antialiased">
    
<div class="hero-bg min-h-screen relative border-b border-white/5">
    <nav class="max-w-7xl mx-auto px-6 lg:px-8 py-6 flex items-center justify-between relative z-50">
        <div class="flex items-center gap-8">
            <a href="index.php" class="flex items-center group">
                <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center shadow-lg transition-all duration-300 group-hover:scale-105">
                    <img src="assets/toplogo.png" alt="AL" class="h-8 w-8 object-contain">
                </div>
                
                <div class="ml-3 flex flex-col justify-center">
                    <h1 class="text-2xl font-black italic tracking-tighter leading-none">
                        <span class="text-white uppercase">AUTO</span><span class="text-[#ff3b30] uppercase">LOG</span>
                    </h1>
                    <span class="text-[8px] font-bold text-white/40 uppercase tracking-[0.4em] mt-1 ml-0.5">
                        ECOSYSTEM
                    </span>
                </div>
            </a>

            <div class="hidden md:flex items-center gap-6">
                <a href="dealer/cars.php" class="text-sm font-medium text-white/70 hover:text-white transition">Explore Cars</a>
                <a href="garages.php" class="text-sm font-medium text-white/70 hover:text-white transition">Service & Repairs</a>
                <a href="dealer/dealers.php" class="text-sm font-medium text-white/70 hover:text-white transition">Trusted Dealers</a>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button id="aiToggleTrigger" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 hover:bg-white/10 transition-all group relative">
                <div class="absolute inset-0 rounded-full border border-alblue opacity-0 group-hover:animate-ping"></div>
                
                <img src="allo_avatar.png" alt="ALVA" class="w-6 h-6 rounded-full object-cover border border-blue-500/30">
                <span class="hidden md:inline-block text-[10px] font-black text-white uppercase tracking-tighter">ALVA AI</span>
            </button>

            <?php if ($isLoggedIn): ?>
                <div class="hidden md:block">
                    <a href="<?php 
                        if (!isset($_SESSION['role'])) {
                            echo 'login.php';
                        } else {
                            switch($_SESSION['role']) {
                                case 'dealer':   echo 'dealer/dealer.php'; break;
                                case 'garage':   echo 'garage.php'; break;
                                case 'mechanic': echo 'mechanic/mechanic_dashboard.php'; break;
                                case 'owner':    echo 'dashboard.php'; break;
                                default:         echo 'index.php';
                            }
                        }
                    ?>" class="text-sm font-semibold text-white/80 hover:text-white transition-colors">
                        Dashboard
                    </a>
                </div>

                <a href="auth/logout.php" class="hidden md:inline-flex px-5 py-2 rounded-full bg-red-500/10 text-red-500 border border-red-500/20 text-sm font-bold hover:bg-red-500 hover:text-white transition items-center">
                    Logout
                </a>

            <?php else: ?>
                <div class="hidden md:flex items-center gap-4">
                    <a href="login.php" class="text-sm font-semibold text-white/80 hover:text-white transition">Log in</a>
                    <a href="register.php" class="px-5 py-2.5 rounded-full bg-alblue text-white text-sm font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-500/20">
                        Join AutoLog
                    </a>
                </div>
            <?php endif; ?>
            
            <button id="menu-btn" class="md:hidden text-white p-2 hover:bg-white/5 rounded-xl transition">
                <i data-lucide="menu"></i>
            </button>
        </div>
    </nav>

    <script>
        document.getElementById('aiToggleTrigger').onclick = function(e) {
            e.preventDefault();
            // This assumes openChat() is defined in your ai_bot_ui.php
            if (typeof openChat === "function") {
                openChat();
            } else {
                console.error("ALVA AI UI not loaded.");
            }
        };
    </script>


<div id="mobile-menu" class="hidden absolute top-24 left-6 right-6 bg-[#0a0c10] border border-white/10 p-5 rounded-[40px] z-50 shadow-[0_20px_50px_rgba(0,0,0,0.6)] animate-menu-reveal">
    
    <div class="flex flex-col gap-2">
        <p class="text-[10px] font-black text-white/20 uppercase tracking-[0.2em] ml-5 mb-1">Explore AutoLog</p>
        
        <a href="dealer/cars.php" class="flex items-center justify-between p-4 rounded-[28px] bg-white/[0.02] hover:bg-white/[0.05] border border-white/5 transition-all duration-300 group">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-alblue/10 flex items-center justify-center text-alblue group-hover:bg-alblue group-hover:text-black transition-all duration-500">
                    <i data-lucide="car" class="w-5 h-5"></i>
                </div>
                <span class="text-white/90 font-bold text-sm tracking-tight">Vehicle Marketplace</span>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4 text-white/10 group-hover:text-white transition-all"></i>
        </a>

        <a href="garages.php" class="flex items-center justify-between p-4 rounded-[28px] bg-white/[0.02] hover:bg-white/[0.05] border border-white/5 transition-all duration-300 group">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-alblue/10 flex items-center justify-center text-alblue group-hover:bg-alblue group-hover:text-black transition-all duration-500">
                    <i data-lucide="wrench" class="w-5 h-5"></i>
                </div>
                <span class="text-white/90 font-bold text-sm tracking-tight">Service & Repairs</span>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4 text-white/10 group-hover:text-white transition-all"></i>
        </a>

        <a href="dealer/dealers.php" class="flex items-center justify-between p-4 rounded-[28px] bg-white/[0.02] hover:bg-white/[0.05] border border-white/5 transition-all duration-300 group">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-alblue/10 flex items-center justify-center text-alblue group-hover:bg-alblue group-hover:text-black transition-all duration-500">
                    <i data-lucide="store" class="w-5 h-5"></i>
                </div>
                <span class="text-white/90 font-bold text-sm tracking-tight">Trusted Dealers</span>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4 text-white/10 group-hover:text-white transition-all"></i>
        </a>
    </div>

    <div class="mt-8">
        <a href="<?php 
            if (!$isLoggedIn || !isset($_SESSION['role'])) {
                echo 'login.php';
            } else {
                switch($_SESSION['role']) {
                    case 'dealer':   echo 'dealer/dealer.php'; break;
                    case 'garage':   echo 'garage.php'; break;
                    case 'mechanic': echo 'mechanic/mechanic_dashboard.php'; break;
                    case 'owner':    echo 'dashboard.php'; break;
                    default:         echo 'index.php';
                }
            }
        ?>" class="relative block group">
            <div class="absolute -inset-1 bg-gradient-to-r from-alblue to-blue-600 rounded-[30px] blur opacity-20 group-hover:opacity-50 transition duration-500"></div>
            
            <div class="relative flex items-center justify-between p-5 bg-alblue rounded-[30px] transition-transform duration-300 group-active:scale-95">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-black/10 rounded-2xl flex items-center justify-center text-black">
                        <i data-lucide="<?= $isLoggedIn ? 'layout-grid' : 'log-in' ?>" class="w-6 h-6"></i>
                    </div>
                    <div class="text-left">
                        <span class="block text-black font-black uppercase text-[13px] leading-none tracking-tight">
                            <?= $isLoggedIn ? 'Access Dashboard' : 'Member Login' ?>
                        </span>
                        <span class="text-black/60 text-[10px] font-bold uppercase tracking-widest">
                            <?= $isLoggedIn ? 'Management Suite' : 'Secure Entry' ?>
                        </span>
                    </div>
                </div>
                <div class="w-10 h-10 bg-black rounded-full flex items-center justify-center text-alblue shadow-lg transition-transform group-hover:rotate-45">
                    <i data-lucide="arrow-up-right" class="w-5 h-5"></i>
                </div>
            </div>
        </a>
    </div>

    <?php if ($isLoggedIn): ?>
        <div class="mt-6 flex justify-center">
            <a href="auth/logout.php" class="text-[10px] font-black text-red-500/40 hover:text-red-500 uppercase tracking-[0.3em] py-2 transition-colors">
                Sign out of account
            </a>
        </div>
    <?php endif; ?>

    <p class="text-center text-[9px] text-white/20 font-bold uppercase tracking-[0.3em] mt-6 mb-2">
        AutoLog Ecosystem &copy; 2026
    </p>
</div>

<script>
    const menuBtn = document.getElementById('menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    // Use stopPropagation to prevent the "click outside" from firing instantly
    menuBtn.addEventListener('click', (e) => {
        e.stopPropagation(); 
        const isHidden = mobileMenu.classList.toggle('hidden');
        
        if (!isHidden && window.lucide) {
            lucide.createIcons();
        }
    });

    // Only close if the menu is actually open
    document.addEventListener('click', (e) => {
        if (!mobileMenu.classList.contains('hidden')) {
            if (!mobileMenu.contains(e.target)) {
                mobileMenu.classList.add('hidden');
            }
        }
    });
</script>


<style>
@keyframes menu-reveal {
    0% { opacity: 0; transform: translateY(-15px) scale(0.97); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-menu-reveal {
    animation: menu-reveal 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>




<main class="max-w-7xl mx-auto px-6 lg:px-8 pt-8 lg:pt-12 pb-24 grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
    
    <div class="lg:col-span-7 animate-fade text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white shadow-lg border border-slate-100 text-slate-600 text-[10px] lg:text-[11px] font-bold mb-6 mx-auto">
            <div class="flex items-center justify-center bg-blue-500/10 p-1 rounded-full">
                <span class="relative flex h-1.5 w-1.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-blue-600"></span>
                </span>
            </div>
            <span class="tracking-tight uppercase">Verified Car Data <span class="lowercase font-normal opacity-60">in</span> Kenya</span>
        </div>
        
        <h1 class="text-4xl sm:text-6xl font-black tracking-tight text-white leading-[1.1] mb-6">
            Track. Service.<br><span class="text-alblue">Sell with Trust.</span>
        </h1>
        
        <p class="text-base lg:text-lg text-white/60 max-w-xl leading-relaxed mb-8 mx-auto">
            The all-in-one automotive platform. Browse verified vehicles, track service history, and list your car to a trusted audience.
        </p>

        <div class="glass p-2 rounded-2xl max-w-2xl border-white/10 shadow-2xl mx-auto">
            <form id="heroSearchForm" onsubmit="searchVehicle(event); return false;" class="flex flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 text-white/40 w-5 h-5"></i>
                    <input id="heroPlate" type="text" placeholder="Enter plate or car model" 
                        class="w-full pl-12 pr-4 py-4 rounded-xl bg-white/5 text-white placeholder:text-white/30 border border-transparent focus:border-alblue/50 focus:bg-white/10 transition outline-none">
                </div>
                <button type="submit" class="px-8 py-4 rounded-xl bg-white text-black font-bold hover:bg-alblue hover:text-white transition flex items-center justify-center gap-2">
                    Get Insights <i data-lucide="sparkles" size="18"></i>
                </button>
            </form>
        </div>

        <p class="mt-4 text-xs text-white/40 flex items-center justify-center gap-2">
            <i data-lucide="shield-check" size="14"></i> Powered by AI and General Autolog Data.
        </p>
    </div>

    <div class="lg:col-span-5 mt-12 lg:mt-0 overflow-x-clip px-6 py-10">
        <div class="hidden md:flex justify-center lg:justify-end w-full">
            <div class="relative w-full max-w-[300px] lg:max-w-[360px]">
                <div class="absolute inset-0 transform -rotate-[12deg] -translate-x-8 translate-y-2 pointer-events-none">
                    <div class="w-full h-full rounded-[2.5rem] bg-slate-100 border border-slate-200 shadow-md opacity-50"></div>
                </div>
                <div class="absolute inset-0 transform rotate-[12deg] translate-x-8 translate-y-2 pointer-events-none">
                    <div class="w-full h-full rounded-[2.5rem] bg-slate-200 border border-slate-300 shadow-md opacity-30"></div>
                </div>

                <div class="relative z-30 transform hover:scale-[1.03] transition-transform duration-300">
                    <div class="w-full rounded-[2.5rem] bg-white p-5 shadow-[0_20px_50px_rgba(0,0,0,0.3)] border border-slate-200">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="text-sm lg:text-md text-slate-900 font-bold tracking-tight">Toyota Vitz <span class="text-slate-400 font-normal text-xs">• 2016</span></h3>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-green-500"></span>
                                    </span>
                                    <span class="text-[9px] uppercase tracking-wider text-slate-500 font-bold">Health: Excellent</span>
                                </div>
                            </div>
                            <div class="bg-blue-50 border border-blue-100 px-2 py-1 rounded-md">
                                <span class="text-[10px] text-blue-600 font-black tracking-widest uppercase">KDA 123X</span>
                            </div>
                        </div>

                        <div class="relative overflow-hidden rounded-2xl">
                            <img src="uploads/dealer_listings/1755086582_22_1754556002_687fac6508454_2017_Toyota_Yaris_L_5-door__front_right__08-25-2024_1_.jpg"
                                alt="Vehicle"
                                class="w-full h-36 lg:h-44 object-cover" />
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                                <p class="text-slate-400 text-[8px] uppercase font-bold mb-0.5">Ownership</p>
                                <p class="text-green-600 font-bold text-[10px] flex items-center gap-1">
                                    <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> Verified
                                </p>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                                <p class="text-slate-400 text-[8px] uppercase font-bold mb-0.5">Accidents</p>
                                <p class="text-slate-700 font-bold text-[10px]">None Reported</p>
                            </div>
                        </div>

                        <div class="mt-5 pt-4 border-t border-slate-100 flex justify-between items-center">
                            <span class="text-[10px] text-slate-400">Updated Jan 2026</span>
                            <button class="text-xs font-bold text-blue-600 hover:text-blue-700 transition-colors">View History →</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="resultModal" class="fixed inset-0 bg-black/80 hidden items-center justify-center z-[100] backdrop-blur-sm">
        <div class="glass p-8 rounded-3xl max-w-xl w-full mx-4 relative border-white/20 animate-fade">
            <button onclick="closeModal()" class="absolute top-4 right-4 text-white/40 hover:text-white transition">
                <i data-lucide="x"></i>
            </button>
            <div id="modalContent" class="mt-2 max-h-[70vh] overflow-y-auto">
                <p class="text-center text-muted">Loading...</p>
            </div>
        </div>
    </div>
</main>

<script>
    // Initialize Icons
    lucide.createIcons();

    function openModal() {
        const modal = document.getElementById('resultModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        const modal = document.getElementById('resultModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function isKenyanPlate(text) {
        return /^K[A-Z]{2}\s?\d{3}[A-Z]$/i.test(text.trim());
    }

    // --- YOUR ORIGINAL DATABASE LOGIC (Updated for the new UI) ---
    async function searchAutoLog(plate) {
        const content = document.getElementById('modalContent');
        
        content.innerHTML = `
            <div class="text-center py-10">
                <i data-lucide="loader-2" class="animate-spin mx-auto mb-4 text-blue-500" size="40"></i>
                <p class="text-white/50 text-[10px] font-black uppercase tracking-widest">Accessing AutoLog Database...</p>
            </div>`;
        lucide.createIcons();

        try {
            const response = await fetch('search_vehicle.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `plate_no=${encodeURIComponent(plate)}`
            });
            
            const html = await response.text();
            content.innerHTML = html;
            lucide.createIcons();
        } catch (error) {
            content.innerHTML = `<p class="text-red-500 text-center">Failed to fetch vehicle record from database.</p>`;
        }
    }

    // --- THE MAIN SEARCH ROUTER ---
    async function searchVehicle(event) {
        if (event) event.preventDefault();
        
        const inputField = document.getElementById('heroPlate');
        const content = document.getElementById('modalContent');

        if (!inputField || !inputField.value.trim()) return;
        const input = inputField.value.trim();

        openModal();

        // 1. DATABASE ROUTE (For Plate Numbers)
        if (isKenyanPlate(input)) {
            content.innerHTML = `
                <div class="text-center">
                    <h3 class="text-2xl font-bold mb-2 text-white italic uppercase tracking-tighter">${input}</h3>
                    <p class="text-white/40 mb-8 text-sm uppercase font-bold tracking-widest">Validated Kenyan Plate</p>
                    <div class="flex flex-col gap-3">
                        <button type="button" onclick="searchAutoLog('${input}')" class="w-full py-4 rounded-xl bg-blue-600 text-white font-bold flex items-center justify-center gap-2">
                           <i data-lucide="database" size="18"></i> View AutoLog History
                        </button>
                        <button type="button" onclick="window.open('https://serviceportal.ntsa.go.ke/services/41/apply', '_blank')" class="w-full py-4 rounded-xl bg-white/5 text-white font-bold flex items-center justify-center gap-2 border border-white/10">
                           <i data-lucide="external-link" size="18"></i> Official NTSA Check
                        </button>
                    </div>
                </div>`;
            lucide.createIcons();
            return;
        }

        // 2. AI ROUTE (For Car Models like "Toyota Vitz")
        content.innerHTML = `
            <div class="text-center py-10">
                <i data-lucide="loader-2" class="animate-spin mx-auto mb-4 text-blue-500" size="40"></i>
                <p class="text-white/50 text-[10px] font-black uppercase tracking-widest">Consulting AutoLog AI...</p>
            </div>`;
        lucide.createIcons();

        try {
            const response = await fetch("includes/car_expert.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ car: input })
            });

            const data = await response.json();
            
            if (data.choices && data.choices[0]) {
                const carInfo = data.choices[0].message.content;
                content.innerHTML = `
                    <div class="max-h-[60vh] overflow-y-auto pr-2 text-left">
                        <h3 class="text-2xl font-black mb-4 text-blue-500 italic uppercase tracking-tighter">${input}</h3>
                        <div class="prose prose-invert text-sm text-white/80 leading-relaxed whitespace-pre-wrap">${carInfo}</div>
                        <button type="button" onclick="closeModal()" class="w-full mt-8 py-4 bg-white/5 rounded-xl text-white font-bold border border-white/10">Close</button>
                    </div>`;
            }
        } catch (err) {
            content.innerHTML = `<p class="text-red-400 text-center">AI Connection Failed.</p>`;
        }
        lucide.createIcons();
    }
</script>

  </div>
 

    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

try {
    $stmt = $pdo->prepare("
        WITH RankedCars AS (
            SELECT 
                dl.id, dl.make, dl.model, dl.year, dl.main_image, dl.price, 
                dl.is_featured, dl.created_at,
                d.verified AS dealer_verified,
                -- 1. Identify each dealer's newest car
                ROW_NUMBER() OVER (
                    PARTITION BY dl.dealer_id 
                    ORDER BY dl.is_featured DESC, dl.created_at DESC
                ) as dealer_rank
            FROM dealer_listings dl
            INNER JOIN dealers d ON dl.dealer_id = d.user_id
            WHERE dl.status = 'active'
        )
        SELECT * FROM RankedCars
        ORDER BY 
            is_featured DESC,      -- 1. All Spotlight units at the front
            dealer_verified DESC,  -- 2. Verified partners next
            dealer_rank ASC,       -- 3. THE ROUND ROBIN (Prevents flooding)
            created_at DESC        -- 4. NEWEST LATEST (The freshness factor)
        LIMIT 10
    ");
    $stmt->execute();
    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ... error handling ...
}
 ?>
    
<main class="relative z-10 -mt-6 sm:-mt-12 pb-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    
    
    <?php
$stmt = $pdo->query("SELECT id, name, logo FROM garages ORDER BY name ASC LIMIT 12");
$garages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<br><br>

<section class="mb-12 lg:mb-16 overflow-hidden border-b border-blue-600/10 pb-12 lg:pb-16">

    <div class="relative z-10 flex items-center justify-between py-6 px-6 sm:px-8">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="w-1 h-3 bg-blue-600 rounded-full"></span>
                <p class="text-[10px] text-white/50 uppercase tracking-[0.3em] font-black">Professional Service Centers</p>
            </div>
            <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                Top Rated <span class="text-blue-600 italic font-light">Garages</span>
            </h2>
        </div>

        <a href="garages.php" class="group flex items-center gap-3 px-5 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl transition-all duration-300">
            <span class="text-[10px] font-black text-white tracking-widest uppercase group-hover:text-blue-600">EXPLORE</span>
            <div class="w-5 h-5 rounded-lg bg-white/10 flex items-center justify-center group-hover:bg-blue-600 transition-colors">
                <i data-lucide="chevron-right" class="w-3 h-3 text-white"></i>
            </div>
        </a>
    </div>

    <div class="flex gap-6 sm:gap-8 overflow-x-auto pb-8 px-6 no-scrollbar snap-x snap-mandatory overscroll-x-contain">
    <?php if (!empty($garages)): ?>
        <?php foreach ($garages as $garage): 
            $logoPath = !empty($garage['logo']) && file_exists(__DIR__ . "/uploads/logo/" . $garage['logo'])
              ? "uploads/logo/" . $garage['logo']
              : "assets/default_garage.jpeg";

            // CHECK: Adjust 'is_verified' to match your actual database column name
            $isVerified = isset($garage['is_verified']) && $garage['is_verified'] == 1;
        ?>
            <a href="garageprofile.php?id=<?= $garage['id'] ?>" 
               class="snap-start flex-none group flex flex-col items-center w-28 sm:w-32">
              
              <div class="relative w-24 h-24 sm:w-28 sm:h-28 p-1 rounded-full bg-gradient-to-b from-white/20 to-transparent group-hover:from-blue-600 group-hover:to-blue-400 transition-all duration-500 shadow-2xl">
                <div class="w-full h-full rounded-full bg-[#020617] overflow-hidden border-2 border-[#020617] relative">
                  <img src="<?= $logoPath ?>" 
                       alt="<?= htmlspecialchars($garage['name']) ?>" 
                       class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-125">
                </div>

                <?php if ($isVerified): ?>
                    <div class="absolute bottom-1 right-1 bg-blue-600 text-white rounded-full p-1 border-2 border-[#020617] shadow-lg z-20 transition-transform duration-500 group-hover:scale-110">
                      <i data-lucide="badge-check" class="w-3.5 h-3.5 fill-white/20"></i>
                    </div>
                <?php endif; ?>
              </div>

              <div class="mt-4 text-center w-full px-1">
                <span class="text-[11px] sm:text-xs font-black text-gray-300 group-hover:text-white transition-colors block truncate uppercase tracking-tight">
                  <?= htmlspecialchars($garage['name']) ?>
                </span>
                <div class="flex items-center justify-center gap-1 mt-1 bg-white/5 py-0.5 px-2 rounded-full w-fit mx-auto border border-white/5">
                   <i data-lucide="star" class="w-2.5 h-2.5 fill-yellow-500 text-yellow-500"></i>
                   <span class="text-[9px] text-white font-bold">4.8</span>
                </div>
              </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>


<div class="w-full mt-8 pb-20 relative overflow-hidden">
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-4xl h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>

    <div class="max-w-7xl mx-auto px-6 py-12">
        <div class="flex flex-col md:flex-row items-center justify-between gap-10">
            
            <div class="flex flex-col items-center md:items-start text-center md:text-left space-y-4">
                <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-blue-600/5 border border-blue-600/10">
                    <span class="text-[9px] font-black uppercase tracking-[0.3em] text-blue-500/80">List Your Garage</span>
                </div>
                
                
                
                <p class="text-white/40 text-[11px] font-bold uppercase tracking-widest max-w-sm leading-loose">
                    Join Kenya's fastest growing digital <br class="hidden md:block"> automotive service network.
                </p>
            </div>

            <div class="w-full md:w-auto flex flex-col items-center md:items-end gap-4">
                <a href="register.php" 
                   class="group relative flex items-center justify-center gap-6 bg-white/[0.02] hover:bg-blue-600 border border-white/5 hover:border-blue-500 px-10 py-5 rounded-[2rem] transition-all duration-500 shadow-2xl">
                    
                    <div class="text-right">
                        <p class="text-[9px] font-black text-blue-500 group-hover:text-blue-100 uppercase tracking-[0.2em] mb-1 transition-colors">Onboarding</p>
                        <p class="text-sm font-black text-white uppercase tracking-wider">Get Started Now</p>
                    </div>

                    <div class="w-10 h-10 rounded-full bg-blue-600/20 group-hover:bg-white flex items-center justify-center transition-all duration-500 shadow-inner">
                        <i data-lucide="arrow-right" class="w-5 h-5 text-blue-600 group-hover:scale-110"></i>
                    </div>
                </a>

                <div class="flex items-center gap-6 px-2">
                    <div class="flex items-center gap-1.5">
                        <i data-lucide="check-circle" class="w-3 h-3 text-blue-600"></i>
                        <span class="text-[9px] font-bold text-white/20 uppercase tracking-widest">Free Listing</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <i data-lucide="check-circle" class="w-3 h-3 text-blue-600"></i>
                        <span class="text-[9px] font-bold text-white/20 uppercase tracking-widest">Expert Support</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>




</section>





        
    <section class="mb-12 lg:mb-16 pb-12 lg:pb-16 overflow-x-hidden w-full border-b border-blue-600/10">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 px-4 sm:px-6 gap-4 relative z-20">
        <div class="relative">
            <div class="flex items-center gap-3 mb-2 sm:mb-3">
                <span class="w-1 h-5 bg-blue-600 rounded-full"></span>
                <span class="text-blue-600 text-[10px] font-black tracking-[0.4em] uppercase">Premium Inventory</span>
            </div>
            <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-none">
                Available <span class="text-blue-600/40">/</span> <span class="text-slate-400 font-light italic text-xl sm:text-3xl">Vehicles</span>
            </h2>
        </div>

        <a href="dealer/cars.php" class="group relative inline-flex items-center gap-3 px-5 py-3 bg-white rounded-2xl border border-slate-100 hover:border-blue-200 hover:shadow-xl hover:shadow-blue-500/5 transition-all duration-300 w-fit">
            <span class="text-[10px] sm:text-[11px] font-black text-slate-600 tracking-widest uppercase group-hover:text-blue-600">Explore Collection</span>
            <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-xl bg-slate-50 flex items-center justify-center group-hover:bg-blue-600 transition-colors">
                <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-400 group-hover:text-white"></i>
            </div>
        </a>
    </div>

   <div class="flex gap-4 sm:gap-8 overflow-x-auto pb-10 pt-2 pl-4 sm:pl-6 no-scrollbar snap-x snap-mandatory touch-auto">

        <?php if (!empty($cars)): 
            foreach ($cars as $car): 
                $imgPath = !empty($car['main_image']) ? $car['main_image'] : "uploads/dealer_listings/default_car.png";
                $fullName = htmlspecialchars($car['make'] . ' ' . $car['model']);
                $year = !empty($car['year']) ? $car['year'] : '';
                $isFeatured = (int)$car['is_featured'] === 1;
                $isVerified = (int)$car['dealer_verified'] === 1;
        ?>
            <a href="dealer/car_details.php?id=<?= urlencode($car['id']) ?>" 
               class="snap-start flex-none w-[82%] sm:w-80 group no-underline">
                
                <div class="relative bg-white rounded-[28px] sm:rounded-[32px] p-3 sm:p-4 transition-all duration-500 
                            shadow-[0_8px_30px_rgb(0,0,0,0.02)] 
                            hover:shadow-[0_40px_80px_-15px_rgba(0,0,0,0.1)] 
                            lg:hover:-translate-y-2">
                    
                    <div class="relative h-40 sm:h-52 w-full overflow-hidden rounded-[22px] sm:rounded-[24px] z-10 bg-slate-100">
                        <img src="<?= htmlspecialchars($imgPath) ?>" 
                             alt="<?= $fullName ?>" 
                             class="w-full h-full object-cover transition-transform duration-[1.5s] group-hover:scale-110">
                        
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent opacity-40 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity duration-500"></div>

                        <div class="absolute top-3 left-3 flex gap-2 z-20">
                            <?php if($year): ?>
                                <div class="bg-white/95 backdrop-blur-sm text-[10px] text-slate-900 font-black px-3 py-1.5 rounded-xl shadow-sm border border-slate-100">
                                    <?= $year ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if($isFeatured): ?>
                            <div class="absolute top-3 right-3 z-20">
                                <div class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-900/90 backdrop-blur-md border border-white/20 shadow-2xl">
                                    <i data-lucide="sparkles" class="w-4 h-4 text-yellow-400"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-5 px-1 sm:px-2 pb-2">
                        <div class="flex items-center justify-between mb-3 gap-2">
                            <h3 class="text-base sm:text-lg font-black text-slate-800 tracking-tight leading-tight group-hover:text-blue-600 transition-colors truncate">
                                <?= $fullName ?>
                            </h3>
                            
                            <?php if($isVerified): ?>
                                <div class="flex-none relative flex items-center justify-center w-6 h-6" title="Verified Listing">
                                    <i data-lucide="badge-check" class="w-6 h-6 text-blue-600 fill-blue-50"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex items-end justify-between border-t border-slate-50 pt-4">
                            <?php if (!empty($car['price'])): ?>
                                <div class="flex flex-col">
                                    <span class="text-[8px] sm:text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Price Guide</span>
                                    <span class="text-lg sm:text-xl font-black text-slate-900 tracking-tighter">
                                        <span class="text-blue-600 text-xs font-bold mr-0.5">KES</span><?= number_format($car['price']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="flex items-center gap-1 text-[9px] sm:text-[10px] font-black text-slate-300 group-hover:text-blue-600 transition-colors uppercase tracking-widest">
                                Details <i data-lucide="chevron-right" class="w-3 h-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        
        <div class="flex-none w-6 sm:w-10 h-full"></div>

        <?php endif; ?>
    </div>
</section>



 

<?php
// Prioritization: 1. Verified, 2. Highest Rated, 3. Alphabetical
$stmt = $pdo->query("
    SELECT 
        user_id,
        name,
        logo,
        location,
        rating,
        verified
    FROM dealers
    ORDER BY 
        verified DESC, -- 1. Verified showrooms first
        rating DESC,   -- 2. Best rated next
        name ASC       -- 3. Tie-breaker
    LIMIT 10
");

$dealers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="relative mb-12 lg:mb-16 pb-12 lg:pb-16 border-b border-blue-600/10">

    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 px-6 gap-4 relative z-20">
        <div class="relative">
            <div class="flex items-center gap-3 mb-2">
                <span class="w-1 h-5 bg-blue-600 rounded-full"></span>
                <span class="text-blue-600 text-[10px] font-black tracking-[0.4em] uppercase">Partner Network</span>
            </div>
            <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-none">
                Official <span class="text-blue-600/40">/</span> <span class="text-slate-400 font-light italic text-xl sm:text-3xl">Showrooms</span>
            </h2>
        </div>

        <a href="dealer/dealers.php" class="group relative inline-flex items-center gap-3 px-5 py-3 bg-white/5 hover:bg-white/10 border border-white/10 rounded-2xl transition-all duration-300 w-fit">
            <span class="text-[10px] sm:text-[11px] font-black text-white tracking-widest uppercase">Explore All</span>
            <div class="w-6 h-6 rounded-xl bg-white/10 flex items-center justify-center group-hover:bg-blue-600 transition-colors">
                <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-white"></i>
            </div>
        </a>
    </div>

    <div class="flex gap-5 overflow-x-auto pb-10 pl-6 pr-12 no-scrollbar snap-x snap-mandatory touch-auto">
        <?php if (!empty($dealers)): ?>
            <?php foreach ($dealers as $dealer): 
                $logoPath = !empty($dealer['logo']) ? htmlspecialchars($dealer['logo']) : 'assets/default_dealer.jpeg';
                $rating = !empty($dealer['rating']) ? (float)$dealer['rating'] : 0;
                $isVerified = ((int)$dealer['verified'] === 1);
            ?>
                <a href="dealer/dealer-profile.php?id=<?= $dealer['user_id'] ?>" 
                   class="snap-start flex-none group no-underline w-[80%] sm:w-64">
                    
                    <div class="relative h-full bg-white/5 backdrop-blur-xl border <?= $isVerified ? 'border-blue-500/30' : 'border-white/5' ?> rounded-[32px] p-6 hover:bg-white/10 hover:border-blue-500/50 transition-all duration-500 flex flex-col items-center">
                        
                        <?php if($isVerified): ?>
                        <div class="absolute top-5 right-5 z-10">
                            <i data-lucide="badge-check" class="w-5 h-5 text-blue-500 fill-blue-500/10"></i>
                        </div>
                        <?php endif; ?>

                        <div class="relative w-28 h-28 p-1 rounded-[28px] bg-gradient-to-br from-white/10 to-transparent border border-white/10 overflow-hidden group-hover:scale-105 transition-transform duration-500">
                            <img src="<?= $logoPath ?>" 
                                 alt="<?= htmlspecialchars($dealer['name']) ?>" 
                                 class="w-full h-full object-cover rounded-[24px]">
                        </div>

                        <div class="mt-6 text-center w-full flex-grow">
                            <h3 class="text-sm font-black text-white truncate px-1 tracking-tight uppercase">
                                <?= htmlspecialchars($dealer['name']) ?>
                            </h3>
                            
                            <?php if (!empty($dealer['location'])): ?>
                                <div class="mt-2 flex items-center justify-center gap-1.5 opacity-60">
                                    <i data-lucide="map-pin" class="w-3 h-3 text-blue-400"></i>
                                    <span class="text-[10px] text-white font-medium truncate uppercase tracking-wider">
                                        <?= htmlspecialchars($dealer['location']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="flex items-center justify-center gap-1 mt-4">
                                <?php for($i=0; $i<5; $i++): ?>
                                    <i data-lucide="star" class="w-2.5 h-2.5 <?= ($i < floor($rating)) ? 'text-yellow-500 fill-yellow-500' : 'text-white/20' ?>"></i>
                                <?php endfor; ?>
                                <span class="ml-1 text-[11px] text-white font-black"><?= number_format($rating, 1) ?></span>
                            </div>
                        </div>

                        <div class="mt-8 w-full">
                            <div class="w-full py-3.5 <?= $isVerified ? 'bg-blue-600 shadow-lg shadow-blue-600/20' : 'bg-white/10' ?> rounded-2xl text-[10px] font-black text-white text-center uppercase tracking-[0.2em] group-hover:bg-white group-hover:text-black transition-all duration-300">
                                View Stock
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    
    <div class="w-full mt-4 pb-16 relative">
    <div class="absolute top-0 left-1/4 w-1/2 h-px bg-gradient-to-r from-transparent via-blue-400/10 to-transparent"></div>

    <div class="max-w-7xl mx-auto px-6 pt-12">
        <div class="flex flex-col lg:flex-row items-center justify-between gap-12 bg-[#020617]/40 rounded-[2.5rem] border border-white/5 p-8 lg:p-12 shadow-2xl overflow-hidden relative group">
            
            <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-blue-600/5 blur-[100px] rounded-full group-hover:bg-blue-600/10 transition-all duration-700"></div>

            <div class="relative z-10 flex-1 space-y-5 text-center lg:text-left">
                <div class="flex items-center justify-center lg:justify-start gap-3">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    <span class="text-[10px] font-black uppercase tracking-[0.4em] text-white/30">Dealer Solutions</span>
                </div>
                
                <h2 class="text-3xl lg:text-5xl font-black text-white italic uppercase tracking-tighter leading-[0.9]">
                    Move your <br> <span class="text-blue-600">Inventory.</span>
                </h2>
                
                <p class="text-white/40 text-xs font-bold uppercase tracking-widest max-w-md leading-relaxed">
                    The ultimate dashboard for Kenyan car dealers. Track leads, manage stock, and verify histories in one click.
                </p>

                <div class="flex items-center justify-center lg:justify-start gap-8 pt-2">
                    <div>
                        <p class="text-white font-black text-lg">500+</p>
                        <p class="text-[8px] text-white/20 font-bold uppercase tracking-tighter">Dealers Active</p>
                    </div>
                    <div class="w-px h-8 bg-white/10"></div>
                    <div>
                        <p class="text-white font-black text-lg">24/7</p>
                        <p class="text-[8px] text-white/20 font-bold uppercase tracking-tighter">Live Support</p>
                    </div>
                </div>
            </div>

            <div class="relative z-10 w-full lg:w-auto">
                <a href="register.php" 
                   class="flex flex-col items-center gap-4 bg-blue-600 hover:bg-blue-500 text-white px-10 py-6 rounded-[2rem] transition-all duration-300 shadow-[0_20px_40px_rgba(37,99,235,0.3)] hover:-translate-y-1">
                    <div class="flex items-center gap-3">
                        <span class="text-[11px] font-black uppercase tracking-[0.2em]">Open Dealer Account</span>
                        <i data-lucide="external-link" class="w-4 h-4 text-white/50"></i>
                    </div>
                </a>
                
                <p class="text-center mt-4 text-[9px] text-white/20 font-medium uppercase tracking-[0.2em]">
                    No Credit Card Required • Instant Setup
                </p>
            </div>

        </div>
    </div>
</div>

    
</section>


        
<section class="mb-16 py-12 overflow-x-hidden">
    <div class="px-6 mb-10 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="px-3 py-1 bg-blue-600 text-[9px] font-black text-white uppercase tracking-widest rounded-full">Expert Advice</span>
            </div>
            <h2 class="text-3xl font-black text-white tracking-tight uppercase italic">
                Pro <span class="text-blue-600">Car Care</span>
            </h2>
        </div>
        <p class="text-[11px] text-white/40 uppercase tracking-[0.3em] font-bold border-l-2 border-white/10 pl-4">
            The Maintenance Manual
        </p>
    </div>

    <div class="relative overflow-hidden w-full group">
        <div class="absolute inset-y-0 left-0 w-24 bg-gradient-to-r from-black/20 to-transparent z-10 pointer-events-none"></div>
        <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-black/20 to-transparent z-10 pointer-events-none"></div>

        <div class="scroll-container flex flex-nowrap gap-8 py-4 animate-scroll-slow hover:pause">
            <?php
            $tips = [
                ['title' => 'Battery Health', 'desc' => 'Drive regularly or use a tender if parking for long periods.', 'icon' => 'zap', 'color' => 'bg-amber-500', 'shadow' => 'shadow-amber-500/20'],
                ['title' => 'Tire Pressure', 'desc' => 'Proper PSI saves fuel and prevents dangerous blowouts.', 'icon' => 'gauge', 'color' => 'bg-blue-600', 'shadow' => 'shadow-blue-600/20'],
                ['title' => 'Regular Oil', 'desc' => 'Change engine oil every 5k–7k km for engine longevity.', 'icon' => 'droplets', 'color' => 'bg-emerald-600', 'shadow' => 'shadow-emerald-600/20'],
                ['title' => 'Coolant Levels', 'desc' => 'Avoid engine overheating by checking levels bi-weekly.', 'icon' => 'thermometer', 'color' => 'bg-rose-600', 'shadow' => 'shadow-rose-600/20'],
                ['title' => 'Safety Lights', 'desc' => 'Check brake and signal lights to stay visible on the road.', 'icon' => 'lightbulb', 'color' => 'bg-indigo-600', 'shadow' => 'shadow-indigo-600/20'],
                ['title' => 'Body Wash', 'desc' => 'Remove road salt and debris to prevent paint corrosion.', 'icon' => 'waves', 'color' => 'bg-cyan-600', 'shadow' => 'shadow-cyan-600/20'],
            ];

            for ($i = 0; $i < 2; $i++):
                foreach ($tips as $index => $tip): ?>
                    <div class="tip-card flex-none w-[320px] sm:w-[350px] group/card">
                        <div class="h-full bg-white border border-white/10 p-10 rounded-[48px] shadow-[0_8px_30px_rgba(0,0,0,0.1)] group-hover/card:shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)] group-hover/card:-translate-y-2 transition-all duration-500">
                            
                            <div class="flex justify-between items-start mb-8">
                                <div class="w-14 h-14 rounded-2xl <?= $tip['color'] ?> <?= $tip['shadow'] ?> flex items-center justify-center text-white transition-transform duration-500 group-hover/card:rotate-12 scale-110">
                                    <i data-lucide="<?= $tip['icon'] ?>" class="w-7 h-7"></i>
                                </div>
                                <span class="text-5xl font-black text-slate-100 select-none group-hover/card:text-blue-50 transition-colors">
                                    0<?= ($index + 1) ?>
                                </span>
                            </div>
                            
                            <h3 class="font-black text-slate-900 text-2xl mb-4 tracking-tight group-hover/card:text-blue-600 transition-colors">
                                <?= $tip['title'] ?>
                            </h3>
                            
                            <p class="text-[14px] text-slate-500 leading-relaxed font-medium mb-8">
                                <?= $tip['desc'] ?>
                            </p>

                            <div class="flex items-center gap-2 pt-6 border-t border-slate-50">
                                <span class="w-2 h-2 rounded-full <?= $tip['color'] ?> animate-pulse"></span>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Service Tip</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; 
            endfor; ?>
        </div>
    </div>
</section>


<style>
/* Container width must be set to max-content to allow flex items to maintain width */
.scroll-container {
    display: flex;
    width: max-content;
    flex-wrap: nowrap;
}

.animate-scroll-slow {
    /* Use 40-60s for a premium, calm feel */
    animation: scrollX 50s linear infinite;
    will-change: transform;
}

@keyframes scrollX {
    0% { transform: translateX(0); }
    /* 100% moves by half the width of the total content (since we duplicated) */
    /* -16px offset accounts for half of the gap-8 between the two sets */
    100% { transform: translateX(calc(-50% - 16px)); }
}

.hover\:pause:hover {
    animation-play-state: paused;
}

/* Slightly faster on small mobile screens to keep engagement */
@media (max-width: 640px) {
    .animate-scroll-slow {
        animation-duration: 35s;
    }
}
</style>




<section class="mb-16 px-6 max-w-7xl mx-auto">
    <div class="relative overflow-hidden bg-white/[0.03] backdrop-blur-md border border-white/10 rounded-[48px] p-8 md:p-16 shadow-2xl">
        
        <div class="absolute -top-24 -left-24 w-80 h-80 bg-alblue/20 rounded-full blur-[120px]"></div>
        <div class="absolute -bottom-24 -right-24 w-80 h-80 bg-purple-600/10 rounded-full blur-[120px]"></div>

        <div class="relative flex flex-col gap-10">
            
            <div class="text-center md:text-left">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-alblue/10 border border-alblue/20 mb-4">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5 text-alblue"></i>
                    <span class="text-[10px] font-black text-alblue uppercase tracking-[0.2em]">Community Voice</span>
                </div>
                <h2 class="text-3xl md:text-5xl font-black text-white tracking-tight italic">Trust the <span class="text-alblue">Process.</span></h2>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
                
                <div class="lg:col-span-12 xl:col-span-9">
                    <div class="flex flex-row overflow-x-auto snap-x snap-mandatory gap-6 no-scrollbar pb-4 md:grid md:grid-cols-3 md:overflow-visible md:pb-0">
                        
                        <div class="min-w-[85%] md:min-w-0 snap-center bg-white/5 p-8 rounded-[32px] border border-white/5 md:bg-transparent md:border-none md:p-0">
                            <div class="text-alblue/40 mb-4">
                                <i data-lucide="quote" class="w-8 h-8 opacity-50"></i>
                            </div>
                            <p class="text-white/70 italic text-base md:text-lg leading-relaxed mb-6">
                                “AutoLog helped me make the right choice before buying I felt confident and in control.” 
                            </p>
                            <div>
                                <span class="block text-sm font-black text-white uppercase tracking-wider">Jane M.</span>
                                <span class="text-[9px] font-bold text-white/30 uppercase tracking-widest">Car Owner</span>
                            </div>
                        </div>

                        <div class="min-w-[85%] md:min-w-0 snap-center bg-white/5 p-8 rounded-[32px] border border-white/5 md:bg-transparent md:border-none md:p-0 md:border-l md:border-white/10 md:pl-10">
                            <div class="text-alblue/40 mb-4">
                                <i data-lucide="quote" class="w-8 h-8 opacity-50"></i>
                            </div>
                            <p class="text-white/70 italic text-base md:text-lg leading-relaxed mb-6">
                                “Selling my car took just a few days verified records gave buyers instant confidence.” 
                            </p>
                            <div>
                                <span class="block text-sm font-black text-white uppercase tracking-wider">Kelvin O.</span>
                                <span class="text-[9px] font-bold text-white/30 uppercase tracking-widest">Verified Seller</span>
                            </div>
                        </div>

                        <div class="min-w-[85%] md:min-w-0 snap-center bg-white/5 p-8 rounded-[32px] border border-white/5 md:bg-transparent md:border-none md:p-0 md:border-l md:border-white/10 md:pl-10">
                            <div class="text-alblue/40 mb-4">
                                <i data-lucide="quote" class="w-8 h-8 opacity-50"></i>
                            </div>
                            <p class="text-white/70 italic text-base md:text-lg leading-relaxed mb-6">
                                “The digital service history has transformed how we build trust with new customers.” 
                            </p>
                            <div>
                                <span class="block text-sm font-black text-white uppercase tracking-wider">Mike T.</span>
                                <span class="text-[9px] font-bold text-white/30 uppercase tracking-widest">Garage Owner</span>
                            </div>
                        </div>

                    </div>
                    <div class="flex gap-1.5 justify-center mt-6 md:hidden">
                        <div class="w-4 h-1 rounded-full bg-alblue"></div>
                        <div class="w-1.5 h-1 rounded-full bg-white/20"></div>
                        <div class="w-1.5 h-1 rounded-full bg-white/20"></div>
                    </div>
                </div>

                <div class="lg:col-span-12 xl:col-span-3">
                    <div class="relative group">
                        <div class="absolute -inset-1 bg-alblue/40 rounded-[40px] blur-2xl opacity-20 group-hover:opacity-40 transition duration-500"></div>
                        <div class="relative bg-alblue p-8 rounded-[40px] text-center shadow-2xl">
                            <h4 class="text-black font-black text-2xl mb-2">Ready?</h4>
                            <p class="text-black/70 text-sm mb-6 font-bold">Join 5,000+ drivers.</p>
                            <a href="login.php" class="block w-full py-4 bg-black text-white rounded-[20px] font-black uppercase text-[10px] tracking-widest hover:scale-[1.02] transition-all duration-300">
                                Try AutoLog Free
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>


<style>
/* Hide scrollbar for Chrome, Safari and Opera */
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
/* Hide scrollbar for IE, Edge and Firefox */
.no-scrollbar {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}
</style>




<div id="quickBar"
     class="fixed inset-x-0 bottom-8 flex justify-center pointer-events-none z-50
            transition-all duration-500 ease-in-out">
  
  <div class="pointer-events-auto flex items-center bg-[#0a0c10]/95 backdrop-blur-2xl border border-white/10 p-1 rounded-full shadow-2xl whitespace-nowrap">
    
    <div class="flex items-center">
      <a href="garages.php" class="flex items-center gap-1.5 px-2.5 py-2 rounded-full hover:bg-white/5 transition-colors group">
        <i data-lucide="wrench" class="w-3.5 h-3.5 text-blue-500"></i>
        <span class="text-[9px] font-black uppercase tracking-widest text-white/70 group-hover:text-white">Garages</span>
      </a>
      
      <a href="dealer/cars.php" class="flex items-center gap-1.5 px-2.5 py-2 rounded-full hover:bg-white/5 transition-colors group">
        <i data-lucide="car" class="w-3.5 h-3.5 text-blue-500"></i>
        <span class="text-[9px] font-black uppercase tracking-widest text-white/70 group-hover:text-white">Inventory</span>
      </a>
    </div>

    <div class="w-px h-3 bg-white/10 mx-0.5"></div>

    <?php if (!$isLoggedIn): ?>
      <a href="register.php" 
         class="flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-full transition-all active:scale-95 shadow-lg shadow-blue-600/20 ml-1">
         <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
         <span class="text-[9px] font-black uppercase tracking-wider">Join</span>
      </a>
    <?php else: ?>
      <a href="<?php 
    if (!isset($_SESSION['role'])) {
        echo 'login.php';
    } else {
        switch($_SESSION['role']) {
            case 'dealer':   echo 'dealer/dealer.php'; break;
            case 'garage':   echo 'garage.php'; break;
            case 'mechanic': echo 'mechanic/mechanic_dashboard.php'; break;
            case 'owner':    echo 'dashboard.php'; break;
            default:         echo 'index.php';
        }
    }
?>" class="flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-full transition-all ml-1">
    <i data-lucide="layout-dashboard" class="w-3.5 h-3.5 text-blue-400"></i>
    <span class="text-[9px] font-black uppercase tracking-wider">Portal</span>
</a>

    <?php endif; ?>
  </div>
</div>

<script>
const quickBar = document.getElementById('quickBar');
let lastScrollY = window.scrollY;

window.addEventListener('scroll', () => {
  if (window.scrollY > lastScrollY && window.scrollY > 100) {
    // Hide on scroll down
    quickBar.style.opacity = '0';
    quickBar.style.transform = 'translateY(20px)';
  } else {
    // Show on scroll up
    quickBar.style.opacity = '1';
    quickBar.style.transform = 'translateY(0)';
  }
  lastScrollY = window.scrollY;
});
</script>


  
  <!-- FOOTER INCLUDE -->
  <?php include 'includes/footer.php'; ?>

</div>

<!-- Optional smooth scroll + UX polish -->
<script>
  // Smooth scroll for internal links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      document.querySelector(this.getAttribute('href')).scrollIntoView({
        behavior: 'smooth'
      });
    });
  });
</script>

    
 
     
    
<script src="https://unpkg.com/lucide@latest"></script>
<script>
  lucide.createIcons();
</script>
 
<script defer src="js/main.js"></script>

</body>
</html> 