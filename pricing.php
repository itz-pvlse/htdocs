<?php require_once 'config/db.php'; ?>
<?php include 'includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
          theme: {
            extend: {
              colors: {
                alblue: '#007bff',
                darkness: '#0a0a0a',
              }
            }
          }
        }
    </script>
    <style>
        body { background-color: #0a0a0a; margin: 0; }
        
        /* The Signature White Paper Card */
        .premium-card {
            background: #ffffff;
            box-shadow: 20px 20px 0px rgba(0, 123, 255, 1);
            border: 1px solid #ffffff;
        }

        /* Glass cards for secondary plans */
        .glass-pricing {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .text-decoration-none { text-decoration: none !important; }
    </style>
</head>

<body class="selection:bg-alblue selection:text-white">

<div class="min-h-screen py-20 px-6">
    
    <div class="max-w-4xl mx-auto text-center mb-20">
        <h1 class="text-5xl md:text-7xl font-black text-white italic uppercase tracking-tighter mb-4">
            PRICING <span class="text-alblue">MODELS.</span>
        </h1>
        <p class="text-white/40 text-xs font-bold uppercase tracking-[0.3em]">Scalable intelligence for owners & enterprises</p>
    </div>

    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
        
        <div class="glass-pricing rounded-[2.5rem] p-10 flex flex-col">
            <div class="mb-8">
                <h3 class="text-white/50 font-black uppercase tracking-widest text-xs mb-2">Tier 01</h3>
                <h2 class="text-3xl font-black text-white italic uppercase">Basic</h2>
                <div class="mt-4 flex items-baseline text-white">
                    <span class="text-5xl font-black tracking-tighter">FREE</span>
                </div>
            </div>
            
            <ul class="space-y-4 mb-10 flex-grow">
                <li class="flex items-center gap-3 text-sm text-white/70">
                    <svg class="w-5 h-5 text-alblue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    1 Vehicle History Report
                </li>
                <li class="flex items-center gap-3 text-sm text-white/70">
                    <svg class="w-5 h-5 text-alblue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    Basic Garage Listings
                </li>
                <li class="flex items-center gap-3 text-sm text-white/30">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    No Accident Records
                </li>
            </ul>

            <a href="#" class="text-decoration-none block w-full py-4 text-center border border-white/20 text-white font-black uppercase tracking-widest rounded-xl hover:bg-white/10 transition-all">
                Get Started
            </a>
        </div>

        <div class="premium-card rounded-[2.5rem] p-10 flex flex-col transform md:-translate-y-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 bg-alblue text-white px-6 py-2 font-black text-[10px] uppercase tracking-widest rounded-bl-2xl">
                Most Popular
            </div>
            
            <div class="mb-8">
                <h3 class="text-alblue font-black uppercase tracking-widest text-xs mb-2">Tier 02</h3>
                <h2 class="text-3xl font-black text-darkness italic uppercase">Pro Agent</h2>
                <div class="mt-4 flex items-baseline text-darkness">
                    <span class="text-sm font-bold mr-1">KSH</span>
                    <span class="text-6xl font-black tracking-tighter">499</span>
                    <span class="text-gray-400 font-bold ml-2">/MO</span>
                </div>
            </div>
            
            <ul class="space-y-4 mb-10 flex-grow">
                <?php $pro = ["10 Premium Reports", "Full Accident History", "Verified Garage Access", "Maintenance Tracking", "Priority Support"]; 
                foreach($pro as $item): ?>
                <li class="flex items-center gap-3 text-sm text-gray-700 font-bold">
                    <svg class="w-5 h-5 text-alblue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    <?= $item ?>
                </li>
                <?php endforeach; ?>
            </ul>

            <a href="#" class="text-decoration-none block w-full py-5 text-center bg-darkness text-white font-black uppercase tracking-widest rounded-xl hover:bg-alblue transition-all shadow-xl">
                Choose Plan
            </a>
        </div>

        <div class="glass-pricing rounded-[2.5rem] p-10 flex flex-col">
            <div class="mb-8">
                <h3 class="text-white/50 font-black uppercase tracking-widest text-xs mb-2">Tier 03</h3>
                <h2 class="text-3xl font-black text-white italic uppercase">Enterprise</h2>
                <div class="mt-4 flex items-baseline text-white">
                    <span class="text-sm font-bold mr-1">KSH</span>
                    <span class="text-5xl font-black tracking-tighter">2,499</span>
                    <span class="text-white/30 font-bold ml-2">/MO</span>
                </div>
            </div>
            
            <ul class="space-y-4 mb-10 flex-grow">
                <li class="flex items-center gap-3 text-sm text-white/70">
                    <svg class="w-5 h-5 text-alblue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    Unlimited Reports
                </li>
                <li class="flex items-center gap-3 text-sm text-white/70">
                    <svg class="w-5 h-5 text-alblue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    API Integration
                </li>
                <li class="flex items-center gap-3 text-sm text-white/70">
                    <svg class="w-5 h-5 text-alblue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    Dedicated Manager
                </li>
            </ul>

            <a href="#" class="text-decoration-none block w-full py-4 text-center border border-white/20 text-white font-black uppercase tracking-widest rounded-xl hover:bg-white/10 transition-all">
                Contact Sales
            </a>
        </div>

    </div>

    <p class="text-center text-white/20 text-[10px] font-bold uppercase tracking-[0.4em] mt-20">
        Prices inclusive of all taxes. Secure payments powered by M-PESA & Card.
    </p>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
