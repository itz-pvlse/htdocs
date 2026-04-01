<?php
/**
 * DYNAMIC NOTIFICATION SYSTEM
 * Place this in includes/notifications.php
 */

// 1. DATABASE FETCH: Get latest 5 activity updates for the logged-in user
$user_id = $_SESSION['user_id'] ?? 0;
$notifications = [];

if ($user_id > 0) {
    try {
        $notif_stmt = $pdo->prepare("
            SELECT 
                sr.requested_service, 
                sr.status, 
                sr.updated_at, 
                v.plate_no, 
                v.make,
                g.name as garage_name
            FROM service_requests sr
            JOIN vehicles v ON sr.vehicle_id = v.id
            JOIN garages g ON sr.garage_id = g.id
            WHERE sr.user_id = ? 
            ORDER BY sr.updated_at DESC 
            LIMIT 5
        ");
        $notif_stmt->execute([$user_id]);
        $notifications = $notif_stmt->fetchAll();
    } catch (PDOException $e) {
        // Silently fail to prevent breaking the UI if table structure differs
        $notifications = [];
    }
}

// Count items for the red dot indicator
$has_alerts = count($notifications) > 0;
?>

<button onclick="openNotificationModal()" 
        id="notifToggleButton"
        class="fixed bottom-28 right-6 w-14 h-14 bg-slate-900 text-white rounded-full shadow-[0_15px_35px_rgba(0,0,0,0.3)] flex items-center justify-center z-[45] active:scale-90 transition-all hover:bg-indigo-600 group sm:bottom-10">
    <div class="relative">
        <i class="fas fa-bell text-xl group-hover:rotate-12 transition-transform"></i>
        <?php if ($has_alerts): ?>
            <span class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-rose-500 rounded-full border-2 border-slate-900"></span>
        <?php endif; ?>
    </div>
</button>

<div id="notificationModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300 opacity-0" id="modalBackdrop" onclick="closeNotificationModal()"></div>
    
    <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-[3rem] p-8 shadow-2xl transform translate-y-full transition-transform duration-500 ease-in-out sm:max-w-md sm:right-6 sm:left-auto sm:bottom-6 sm:rounded-[2.5rem]" id="modalContainer">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h3 class="text-2xl font-black text-slate-900 uppercase italic leading-none">Activity</h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-2">Latest Updates</p>
            </div>
            <button onclick="closeNotificationModal()" class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center hover:bg-rose-50 hover:text-rose-500 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="space-y-4 max-h-[50vh] overflow-y-auto pb-8 pr-2 custom-scrollbar">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-12">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-bell-slash text-slate-200 text-2xl"></i>
                    </div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">No recent activity found</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $n): 
                    $isDone = ($n['status'] == 'completed');
                    $accentColor = $isDone ? 'emerald' : 'indigo';
                ?>
                    <div class="p-5 bg-slate-50 rounded-[2rem] border border-slate-100 flex gap-4 items-start relative overflow-hidden group hover:border-indigo-100 transition-colors">
                        <div class="w-11 h-11 bg-white rounded-2xl flex items-center justify-center text-<?= $accentColor ?>-500 shrink-0 shadow-sm border border-slate-100 group-hover:scale-110 transition-transform">
                            <i class="fas <?= $isDone ? 'fa-check-double' : 'fa-screwdriver-wrench' ?> text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between items-start mb-1">
                                <p class="text-[10px] font-black text-slate-900 uppercase italic"><?= htmlspecialchars($n['requested_service']) ?></p>
                                <span class="text-[8px] font-bold text-slate-400 uppercase"><?= date('H:i', strtotime($n['updated_at'])) ?></span>
                            </div>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter leading-tight">
                                Your <span class="text-slate-800"><?= $n['make'] ?> (<?= $n['plate_no'] ?>)</span> 
                                is currently <span class="text-<?= $accentColor ?>-600"><?= $n['status'] ?></span> 
                                at <?= htmlspecialchars($n['garage_name'] ?? 'Garage') ?>.
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>

<script>
function openNotificationModal() {
    const modal = document.getElementById('notificationModal');
    const container = document.getElementById('modalContainer');
    const backdrop = document.getElementById('modalBackdrop');
    const toggle = document.getElementById('notifToggleButton');
    
    // Hide toggle button
    toggle.classList.add('opacity-0', 'scale-0');
    
    // Display Modal
    modal.classList.remove('hidden');
    
    // Start Animation
    setTimeout(() => {
        backdrop.classList.add('opacity-100');
        container.classList.remove('translate-y-full');
    }, 10);
}

function closeNotificationModal() {
    const modal = document.getElementById('notificationModal');
    const container = document.getElementById('modalContainer');
    const backdrop = document.getElementById('modalBackdrop');
    const toggle = document.getElementById('notifToggleButton');
    
    // Animate out
    backdrop.classList.remove('opacity-100');
    container.classList.add('translate-y-full');
    
    // Wait for transition, then hide
    setTimeout(() => {
        modal.classList.add('hidden');
        toggle.classList.remove('opacity-0', 'scale-0');
    }, 500);
}
</script>