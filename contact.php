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
        /* Signature Paper Effect */
        .contact-paper {
            background: #ffffff;
            box-shadow: 20px 20px 0px rgba(0, 123, 255, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        body { background-color: #0a0a0a; margin: 0; }

        .input-underlined {
            border: none;
            border-bottom: 2px solid #e5e7eb;
            border-radius: 0;
            padding-left: 0;
            transition: all 0.3s ease;
        }

        .input-underlined:focus {
            border-bottom-color: #007bff;
            outline: none;
            box-shadow: none;
        }
    </style>
</head>

<body class="selection:bg-alblue selection:text-white">

<div class="min-h-screen py-16 md:py-24 px-4">
    <div class="max-w-5xl mx-auto">
        
        <div class="mb-12 text-center md:text-left">
            <h1 class="text-5xl md:text-7xl font-black text-white italic uppercase tracking-tighter leading-none mb-4">
                GET IN<br><span class="text-alblue">TOUCH.</span>
            </h1>
            <div class="flex items-center gap-4 justify-center md:justify-start">
                <div class="h-[2px] w-12 bg-alblue"></div>
                <p class="text-white/40 text-xs font-bold uppercase tracking-[0.3em]">Support / Partnerships / Inquiry</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
            
            <div class="lg:col-span-7 contact-paper rounded-3xl overflow-hidden">
                <div class="bg-darkness p-8 border-b border-gray-100">
                    <h3 class="text-white font-bold uppercase tracking-widest text-sm">Send an Official Message</h3>
                </div>
                
                <form id="contactForm" class="p-8 md:p-12 space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="flex flex-col">
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Full Name</label>
                            <input type="text" class="input-underlined text-darkness font-bold py-2" placeholder="e.g. John Doe" required>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Email Address</label>
                            <input type="email" class="input-underlined text-darkness font-bold py-2" placeholder="john@example.com" required>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Inquiry Type</label>
                        <select class="input-underlined text-darkness font-bold py-2 bg-transparent">
                            <option>General Support</option>
                            <option>Dealer Partnership</option>
                            <option>Vehicle History Dispute</option>
                            <option>Technical Issue</option>
                        </select>
                    </div>

                    <div class="flex flex-col">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Your Message</label>
                        <textarea rows="4" class="input-underlined text-darkness font-bold py-2 resize-none" placeholder="How can we assist you today?" required></textarea>
                    </div>

                    <button type="submit" class="w-full bg-darkness text-white font-black uppercase tracking-[0.2em] py-5 rounded-xl hover:bg-alblue transition-all transform hover:-translate-y-1 active:scale-95">
                        Dispatch Message
                    </button>
                </form>
            </div>

            <div class="lg:col-span-5 space-y-6">
                
                <div class="p-8 rounded-3xl border border-white/10 bg-white/5 group hover:border-alblue/50 transition-all">
                    <h4 class="text-alblue font-black uppercase tracking-widest text-xs mb-4">Direct Contact</h4>
                    <p class="text-white text-2xl font-black mb-1">(+254) 799-40-2030</p>
                    <p class="text-white/40 text-sm font-medium">Monday — Friday, 8am - 6pm</p>
                </div>

                <div class="p-8 rounded-3xl border border-white/10 bg-white/5">
                    <h4 class="text-alblue font-black uppercase tracking-widest text-xs mb-4">Our Location</h4>
                    <p class="text-white text-xl font-bold mb-1 italic">Nairobi Headquarters</p>
                    <p class="text-white/40 text-sm font-medium leading-relaxed">
                        Westlands Business District,<br>
                        Nairobi, Kenya
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <a href="#" class="p-6 rounded-2xl bg-white/5 border border-white/5 hover:border-alblue text-center transition-all">
                        <div class="text-alblue font-black text-xl mb-1">FAQ</div>
                        <div class="text-[9px] text-white/40 uppercase tracking-widest font-bold">Help Center</div>
                    </a>
                    <a href="#" class="p-6 rounded-2xl bg-white/5 border border-white/5 hover:border-alblue text-center transition-all">
                        <div class="text-alblue font-black text-xl mb-1">Chat</div>
                        <div class="text-[9px] text-white/40 uppercase tracking-widest font-bold">Live Support</div>
                    </a>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
