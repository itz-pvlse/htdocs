<?php 
// Standardize base - remove the dots in links below
$base = "/AUTOLOG/"; 
?>

    </div> </div> </div> 

<footer class="mt-24 pt-16 pb-8 relative overflow-hidden bg-transparent">
    
    <div class="absolute -bottom-24 left-1/2 -translate-x-1/2 w-full max-w-[800px] h-64 bg-blue-500/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-6 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-12 mb-16">
            
            <div class="md:col-span-5 flex flex-col items-center md:items-start text-center md:text-left">
                <a href="<?= $base ?>../index.php" class="inline-flex items-center gap-3 mb-4 group text-decoration-none">
                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center p-1.5 transition-transform group-hover:rotate-12">
                        <img src="<?= $base ?>../assets/toplogo.png" alt="AutoLog" class="w-full h-auto object-contain">
                    </div>
                    <span class="text-2xl font-black text-white italic uppercase tracking-tighter">AutoLog</span>
                </a>
                <p class="text-white/40 text-sm max-w-sm leading-relaxed mb-6">
                    Kenya's premier platform for verified vehicle history, trusted showrooms, and expert garage services. Own your ride with total confidence.
                </p>
                <div class="flex gap-4">
                    <a href="#" class="text-white/30 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                    </a>
                    <a href="#" class="text-white/30 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>
                    </a>
                </div>
            </div>

            <div class="md:col-span-3 flex flex-col items-center md:items-start">
                <h4 class="text-white/80 font-bold uppercase tracking-widest text-xs mb-6">Platform</h4>
                <ul class="list-unstyled space-y-4 text-center md:text-left">
                    <li><a href="<?= $base ?>../dealer/dealers.php" class="text-white/40 hover:text-white transition-colors text-sm text-decoration-none">Showrooms</a></li>
                    <li><a href="<?= $base ?>../register.php" class="text-white/40 hover:text-white transition-colors text-sm text-decoration-none">Join as Dealer</a></li>
                    <li><a href="<?= $base ?>../contact.php" class="text-white/40 hover:text-white transition-colors text-sm text-decoration-none">Contact Support</a></li>
                </ul>
            </div>

            <div class="md:col-span-4 flex flex-col items-center md:items-start">
                <h4 class="text-white/80 font-bold uppercase tracking-widest text-xs mb-6">Legal</h4>
                <ul class="list-unstyled space-y-4 text-center md:text-left">
                    <li><a href="<?= $base ?>../privacy.php" class="text-white/40 hover:text-white transition-colors text-sm text-decoration-none">Privacy Policy</a></li>
                    <li><a href="<?= $base ?>../terms.php" class="text-white/40 hover:text-white transition-colors text-sm text-decoration-none">Terms of Service</a></li>
                    <li>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 mt-2">
                             <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                             <span class="text-[10px] text-white/40 font-bold uppercase tracking-wider">System Operational</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-[11px] text-white/20 uppercase tracking-widest font-bold text-center">
                © <?= date('Y'); ?> AutoLog Kenya.
            </p>
            <p class="text-[10px] text-white/10 font-medium tracking-tighter">
                V1.2.0-STABLE
            </p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
