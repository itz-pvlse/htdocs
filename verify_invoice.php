<?php
require_once 'config/db.php';

$invoice_id = $_GET['id'] ?? 0;

/**
 * FETCH INVOICE VERIFICATION DATA
 */
$query = "SELECT 
            i.id as invoice_id,
            i.grand_total,
            i.created_at,
            sr.requested_service,
            v.plate_no,
            g.name as garage_name,
            u.name as owner_name
          FROM invoices i 
          JOIN jobs j ON i.job_id = j.id 
          JOIN service_requests sr ON j.request_id = sr.id 
          JOIN garages g ON sr.garage_id = g.id 
          JOIN users u ON sr.user_id = u.id
          LEFT JOIN vehicles v ON sr.vehicle_id = v.id
          WHERE i.id = ? 
          LIMIT 1";

$stmt = $pdo->prepare($query);
$stmt->execute([$invoice_id]);
$inv = $stmt->fetch();

$is_valid = (bool)$inv;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Verification | AutoLog</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { 
            font-family: 'Inter', sans-serif;
            background: #000000;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .neural-card {
            background: linear-gradient(145deg, #111827, #000000);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .check-glow {
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.4);
        }
    </style>
</head>
<body class="text-white p-6">

    <div class="w-full max-w-sm mx-auto">
        
        <div class="flex flex-col items-center mb-8">
            <div class="bg-indigo-600 text-white px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest mb-2">
                System Verified
            </div>
            <h1 class="text-xl font-black italic uppercase tracking-tighter">Auto<span class="text-indigo-500">Log</span></h1>
        </div>

        <?php if ($is_valid): ?>
            <div class="neural-card rounded-[2.5rem] p-8 text-center relative overflow-hidden shadow-2xl">
                
                <div class="w-20 h-20 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 check-glow border-4 border-black">
                    <i class="fas fa-check text-3xl text-black"></i>
                </div>

                <p class="text-[10px] font-black uppercase tracking-[0.3em] text-emerald-400 mb-1">Authentic Record</p>
                <h2 class="text-2xl font-black uppercase italic mb-8">Valid <span class="text-indigo-500">Invoice</span></h2>
                
                <div class="space-y-5 text-left mb-8">
                    <div class="border-b border-white/5 pb-3">
                        <p class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mb-1">Garage</p>
                        <p class="text-sm font-black text-slate-200 uppercase"><?= htmlspecialchars($inv['garage_name']) ?></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="border-b border-white/5 pb-3">
                            <p class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mb-1">Vehicle</p>
                            <p class="text-sm font-black text-slate-200 uppercase"><?= htmlspecialchars($inv['plate_no'] ?? 'N/A') ?></p>
                        </div>
                        <div class="border-b border-white/5 pb-3 text-right">
                            <p class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mb-1">Total</p>
                            <p class="text-sm font-black text-indigo-400">KES <?= number_format($inv['grand_total'], 0) ?></p>
                        </div>
                    </div>

                    <div class="border-b border-white/5 pb-3">
                        <p class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mb-1">Date Issued</p>
                        <p class="text-sm font-black text-slate-200 uppercase"><?= date('d M Y', strtotime($inv['created_at'])) ?></p>
                    </div>
                </div>

                <div class="bg-black/50 p-4 rounded-2xl border border-white/5 mb-8">
                    <p class="text-[9px] text-slate-400 font-medium leading-relaxed">
                        This receipt was generated through the <span class="text-white font-bold">AutoLog Network</span>. Identity and transaction data are verified against our secure ledger.
                    </p>
                </div>

                <button onclick="window.close()" class="w-full bg-white text-black py-4 rounded-2xl font-black uppercase tracking-widest text-xs active:scale-95 transition-all">
                    Done
                </button>
            </div>
        <?php else: ?>
            <div class="neural-card rounded-[2.5rem] p-10 text-center border-rose-500/20">
                <i class="fas fa-circle-xmark text-5xl text-rose-500 mb-6"></i>
                <h2 class="text-xl font-black uppercase italic mb-2">Record <span class="text-rose-500">Not Found</span></h2>
                <p class="text-xs text-slate-500 mb-8 leading-relaxed">This invoice ID could not be verified in the AutoLog system.</p>
                <a href="/" class="block w-full bg-white/5 py-4 rounded-2xl font-black uppercase tracking-widest text-xs text-white">Back</a>
            </div>
        <?php endif; ?>

        <p class="mt-8 text-center text-[8px] font-bold text-white/20 uppercase tracking-[0.6em]">
            Verified by AutoLog Systems
        </p>
    </div>

</body>
</html>
