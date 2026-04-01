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
        
        /* Premium Paper Card */
        .story-card {
            background: #ffffff;
            box-shadow: 30px 30px 0px rgba(0, 123, 255, 0.1);
            border: 1px solid #e5e7eb;
        }

        .text-outline {
            -webkit-text-stroke: 1px rgba(255,255,255,0.2);
            color: transparent;
        }

        .image-overlay-card {
            position: relative;
            overflow: hidden;
            border-radius: 2rem;
        }

        .image-overlay-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, #0a0a0a 0%, transparent 100%);
        }
    </style>
</head>

<body class="selection:bg-alblue selection:text-white">

<header class="relative pt-32 pb-20 px-6 overflow-hidden">
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-20 pointer-events-none"></div>
    
    <div class="max-w-6xl mx-auto text-center relative z-10">
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-alblue/10 border border-alblue/20 mb-8">
            <span class="text-[10px] font-black uppercase tracking-[0.4em] text-alblue">The AutoLog Manifesto</span>
        </div>
        
        <h1 class="text-4xl md:text-7xl font-black text-white italic uppercase tracking-tighter leading-[0.9] mb-12">
            Eliminating the <span class="text-outline">Mystery</span> <br>
            Behind every <span class="text-alblue">Kenyan</span> Road.
        </h1>
        
        <p class="text-white/40 text-lg md:text-xl max-w-3xl mx-auto font-medium leading-relaxed mb-10">
            Empowering every car buyer, seller, and owner with transparent, verified vehicle information — making every decision <span class="text-white">smarter, safer, and more trustworthy.</span>
        </p>
        
        <a href="#story" class="inline-block px-10 py-5 bg-white text-darkness font-black uppercase tracking-widest rounded-full hover:bg-alblue hover:text-white transition-all transform hover:scale-105">
            Read Our Story
        </a>
    </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-20" id="story">
    
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-center mb-40">
        <div class="lg:col-span-6">
            <div class="image-overlay-card shadow-2xl transform -rotate-2">
                <img src="assets/founding.jpeg" alt="Founding" class="w-full h-[500px] object-cover" onerror="this.src='https://images.unsplash.com/photo-1511919884226-fd3cad34687c?auto=format&fit=crop&q=80'">
            </div>
        </div>
        <div class="lg:col-span-6">
            <h2 class="text-5xl font-black text-white italic uppercase tracking-tighter mb-8">
                Born from <br><span class="text-alblue">Frustration.</span>
            </h2>
            <div class="space-y-6 text-white/50 text-lg leading-relaxed">
                <p>
                    AutoLog was born out of a simple yet powerful idea — <strong class="text-white">car history should never be a mystery.</strong> Frustrated by the lack of accessible and trustworthy vehicle information, our founders set out to create a platform that brings full transparency to Kenya’s automotive market.
                </p>
                <p>
                    From humble beginnings, we aimed to give every car owner, buyer, and mechanic the tools to verify a vehicle’s background with confidence — from ownership and mileage to service and accident history.
                </p>
            </div>
        </div>
    </div>

    <div class="story-card rounded-[3rem] p-8 md:p-20 mb-40">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-black text-darkness uppercase tracking-tight">Our Core Values</h2>
            <div class="w-20 h-1 bg-alblue mx-auto mt-4"></div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
            <div class="space-y-4">
                <div class="text-5xl font-black text-alblue/20">01</div>
                <h4 class="text-xl font-black text-darkness uppercase">Transparency</h4>
                <p class="text-gray-500 leading-relaxed">We believe in truth over assumptions. Every report we deliver is backed by verified, traceable data sources.</p>
            </div>
            <div class="space-y-4">
                <div class="text-5xl font-black text-alblue/20">02</div>
                <h4 class="text-xl font-black text-darkness uppercase">Trust</h4>
                <p class="text-gray-500 leading-relaxed">We partner only with credible garages and institutions to ensure every record is authentic and reliable.</p>
            </div>
            <div class="space-y-4">
                <div class="text-5xl font-black text-alblue/20">03</div>
                <h4 class="text-xl font-black text-darkness uppercase">Innovation</h4>
                <p class="text-gray-500 leading-relaxed">We continuously improve with the latest technologies — including blockchain integrity and automated verification.</p>
            </div>
        </div>
    </div>

    <div class="mb-40">
        <div class="text-center mb-20">
            <h2 class="text-5xl font-black text-white italic uppercase tracking-tighter mb-4">Meet the <span class="text-alblue">Architects.</span></h2>
            <p class="text-white/40 uppercase tracking-[0.3em] text-xs font-bold">The minds driving automotive transparency</p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
            <?php 
            $team = [
                ['name' => 'Kassim Bakari', 'role' => 'Founder & CEO'],
                ['name' => 'Arden Vasek', 'role' => 'CFO'],
                ['name' => 'Toribio Nerthus', 'role' => 'Operations'],
                ['name' => 'Malvina Cilla', 'role' => 'CTO']
            ];
            foreach($team as $member): ?>
            <div class="group text-center">
                <div class="relative mb-6 inline-block">
                    <div class="absolute inset-0 bg-alblue rounded-2xl rotate-6 group-hover:rotate-0 transition-transform duration-300"></div>
                    <img src="https://dummyimage.com/300x350/1a1a1a/ffffff&text=<?= $member['name'][0] ?>" alt="<?= $member['name'] ?>" class="relative z-10 w-full rounded-2xl grayscale group-hover:grayscale-0 transition-all border-2 border-white/10">
                </div>
                <h5 class="text-white font-black uppercase tracking-tight text-lg"><?= $member['name'] ?></h5>
                <p class="text-alblue font-bold text-[10px] uppercase tracking-widest mt-1"><?= $member['role'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-alblue rounded-[3rem] p-12 md:p-24 text-center overflow-hidden relative">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full blur-3xl -mr-32 -mt-32"></div>
        <div class="relative z-10">
            <h2 class="text-4xl md:text-6xl font-black text-white italic uppercase tracking-tighter mb-8">
                Ready to own <br>your vehicle's <span class="text-darkness">truth?</span>
            </h2>
            <div class="flex flex-col md:flex-row gap-4 justify-center">
                <a href="register.php" class="px-10 py-5 bg-darkness text-white font-black uppercase tracking-widest rounded-full hover:bg-white hover:text-darkness transition-all">Start Free Account</a>
                <a href="contact.php" class="px-10 py-5 bg-white/20 border border-white/30 text-white font-black uppercase tracking-widest rounded-full hover:bg-white/40 transition-all">Partner With Us</a>
            </div>
        </div>
    </div>

</main>

<?php include 'includes/footer.php'; ?>

</body>
</html>
