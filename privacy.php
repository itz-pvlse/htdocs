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
        /* High-End Paper Effect */
        .policy-card {
            background: #ffffff;
            box-shadow: 20px 20px 0px rgba(0, 123, 255, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        body {
            background-color: #0a0a0a; /* Matches your site background */
            margin: 0;
        }

        .section-number {
            -webkit-text-stroke: 1px #007bff;
            color: transparent;
            font-family: serif;
        }
    </style>
</head>

<body class="selection:bg-alblue selection:text-white">

<div class="min-h-screen py-16 md:py-24 px-4">
    
    <div class="max-w-4xl mx-auto">
        
        <div class="mb-12">
            <h1 class="text-5xl md:text-7xl font-black text-white italic uppercase tracking-tighter leading-none mb-4">
                PRIVACY<br><span class="text-alblue">POLICY.</span>
            </h1>
            <div class="flex items-center gap-4">
                <div class="h-[2px] w-12 bg-alblue"></div>
                <p class="text-white/40 text-xs font-bold uppercase tracking-[0.3em]">AutoLog Kenya / Internal Protocol</p>
            </div>
        </div>

        <div class="policy-card rounded-3xl overflow-hidden">
            
            <div class="bg-darkness p-8 md:p-12 border-b border-gray-100">
                <p class="text-xl md:text-2xl text-white font-medium leading-tight">
                    We process vehicle data with <span class="text-alblue">absolute precision</span> and user data with <span class="text-alblue">total privacy.</span>
                </p>
            </div>

            <div class="p-8 md:p-16 space-y-16">
                
               <div class="space-y-24"> <section class="relative pl-6 md:pl-12">
        <span class="absolute -left-2 md:-left-6 -top-6 text-8xl font-black opacity-[0.06] section-number pointer-events-none select-none">
            01
        </span>
        <div class="relative z-10">
            <h2 class="text-2xl font-black text-darkness uppercase tracking-tight mb-6">Data Acquisition</h2>
            <p class="text-gray-600 leading-relaxed mb-8">To maintain the integrity of Kenya's vehicle history, we collect the following datasets:</p>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php 
                $items = [
                    "Owner Identification", "Chassis & VIN Details", 
                    "Service Logs", "Geographic Coordinates", 
                    "Network Protocols", "Financial Metadata"
                ];
                foreach($items as $item): ?>
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100">
                    <div class="w-2 h-2 bg-alblue rounded-full"></div>
                    <span class="text-sm font-bold text-gray-800 uppercase tracking-wide"><?= $item ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="relative pl-6 md:pl-12">
        <span class="absolute -left-2 md:-left-6 -top-6 text-8xl font-black opacity-[0.06] section-number pointer-events-none select-none">
            02
        </span>
        <div class="relative z-10">
            <h2 class="text-2xl font-black text-darkness uppercase tracking-tight mb-6">Encryption Standards</h2>
            <div class="border-l-4 border-alblue pl-6 py-2">
                <p class="text-gray-600 text-lg leading-relaxed">
                    Every bit of data is protected by <span class="font-bold text-darkness">AES-256 bit encryption</span>. We treat vehicle history as sensitive financial data, ensuring no unauthorized mechanic or dealer can access your private logs without explicit permission.
                </p>
            </div>
        </div>
    </section>

    <section class="relative pl-6 md:pl-12">
        <span class="absolute -left-2 md:-left-6 -top-6 text-8xl font-black opacity-[0.06] section-number pointer-events-none select-none">
            03
        </span>
        <div class="relative z-10">
            <h2 class="text-2xl font-black text-darkness uppercase tracking-tight mb-6">User Autonomy</h2>
            <p class="text-gray-600 leading-relaxed">
                You own your data. Users have the right to request a full export of their vehicle logs or the permanent deletion of their account at any time via the support portal.
            </p>
        </div>
    </section>

</div>


                <div class="pt-12 border-t border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-8">
                    <div>
                        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2">Support Channel</h4>
                        <p class="text-xl font-black text-darkness">legal@autolog.co.ke</p>
                    </div>
                    <div class="px-6 py-3 bg-darkness text-white rounded-full text-xs font-bold uppercase tracking-widest">
                        Nairobi HQ / Verified
                    </div>
                </div>

            </div>
        </div>

        <div class="mt-16 text-center">
            <a href="index.php" class="group inline-flex items-center gap-3 text-white/40 hover:text-white transition-all">
                <span class="text-xs font-black uppercase tracking-widest">Exit to Dashboard</span>
                <div class="w-8 h-[1px] bg-white/20 group-hover:w-12 group-hover:bg-alblue transition-all"></div>
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
