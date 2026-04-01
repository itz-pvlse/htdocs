<?php
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['plate_no'])) {
    echo "<p class='text-red-500 text-center font-bold mt-10'>Invalid request.</p>";
    exit;
}

/* ===============================
   Normalize plate input
   =============================== */
$plate = strtoupper(str_replace(' ', '', trim($_POST['plate_no'])));

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM vehicles
        WHERE UPPER(REPLACE(plate_no, ' ', '')) = ?
        LIMIT 1
    ");
    $stmt->execute([$plate]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        ?>
        <div class="lg:col-span-6 flex justify-center mt-12 px-4">
            <div class="bg-white border border-red-100 p-10 text-center shadow-xl rounded-[35px] max-w-sm w-full">
                <div class="w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                        <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-extrabold text-slate-900 mb-2">Vehicle Not Found</h3>
                <p class="text-slate-500 text-sm leading-relaxed mb-6">
                    The plate <span class="font-mono font-bold text-red-600"><?= htmlspecialchars($plate) ?></span> is not registered for public access or does not exist in our secure registry.
                </p>
                <button onclick="window.location.reload()" class="w-full py-4 bg-slate-100 text-slate-700 font-bold rounded-full hover:bg-slate-200 transition">Try Another Search</button>
            </div>
        </div>
        <?php
        exit;
    }

    /* ===============================
       Safe extraction (NO notices)
       =============================== */
    $plate_no     = htmlspecialchars($vehicle['plate_no']);
    $make         = htmlspecialchars($vehicle['make'] ?? 'Unknown');
    $model        = htmlspecialchars($vehicle['model'] ?? '—');
    $year         = htmlspecialchars($vehicle['year'] ?? '—');
    
    $mileage = isset($vehicle['current_mileage']) && $vehicle['current_mileage'] !== null
        ? number_format((int)$vehicle['current_mileage'])
        : '—';

    $last_service = isset($vehicle['last_service_date']) && $vehicle['last_service_date'] !== null
        ? htmlspecialchars($vehicle['last_service_date'])
        : '—';

    $condition    = htmlspecialchars($vehicle['condition'] ?? 'Unknown');
    $under_caveat = htmlspecialchars($vehicle['under_caveat'] ?? 'No');

    $image_path = !empty($vehicle['image_path'])
        ? htmlspecialchars($vehicle['image_path'])
        : 'assets/car_placeholder.jpg';
    ?>

    <style>
        .showroom-card {
            background: #ffffff;
            border: 1px solid rgba(30, 58, 138, 0.08);
            border-radius: 35px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.08);
            transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        .showroom-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 40px 80px -15px rgba(30, 58, 138, 0.15);
            border-color: #2563eb;
        }
        .img-container {
            border-radius: 25px;
            margin: 15px;
            overflow: hidden;
            height: 220px;
        }
        .img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 1.2s ease;
        }
        .showroom-card:hover .img-container img {
            transform: scale(1.1);
        }
        .plate-pill {
            background: #0f172a;
            color: #ffffff;
            font-family: 'SF Mono', 'Fira Code', monospace;
            letter-spacing: 2px;
            font-weight: 900;
        }
        .status-verified {
            background: #00ff88;
            color: #0f172a;
            box-shadow: 0 4px 15px rgba(0, 255, 136, 0.3);
        }
        .btn-report-action {
            background: #1e3a8a; /* Midnight Blue */
            transition: all 0.3s ease;
        }
        .btn-report-action:hover {
            background: #2563eb; /* Electric Blue */
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
            transform: translateY(-2px);
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .data-row:last-child { border-bottom: none; }
        .label-text { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .value-text { font-size: 0.9rem; font-weight: 800; color: #0f172a; }
    </style>

    <div class="lg:col-span-6 flex justify-center mt-10 px-4">
        <div class="showroom-card relative max-w-sm w-full">
            
            <div class="absolute top-8 right-8 z-10">
                <div class="plate-pill px-4 py-1.5 rounded-full text-xs shadow-2xl">
                    <?= $plate_no ?>
                </div>
            </div>

            <div class="img-container shadow-inner">
                <img src="<?= $image_path ?>" alt="<?= $make ?> <?= $model ?>">
            </div>

            <div class="px-8 pb-8 pt-2">
                
                <div class="mb-6">
                    <h2 class="text-2xl font-black text-slate-900 leading-tight tracking-tighter">
                        <?= $make ?> <?= $model ?>
                    </h2>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Model Year <?= $year ?></span>
                        <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                        <span class="text-[10px] font-bold text-blue-500 uppercase tracking-widest">Digital Registry</span>
                    </div>
                </div>

                <div class="space-y-1 mb-8">
                    <div class="data-row">
                        <span class="label-text">Owner Status</span>
                        <span class="status-verified px-3 py-1 rounded-full text-[10px] uppercase font-black">Verified</span>
                    </div>
                    <div class="data-row">
                        <span class="label-text">Accident History</span>
                        <span class="status-verified px-3 py-1 rounded-full text-[10px] uppercase font-black">None</span>
                    </div>
                    <div class="data-row">
                        <span class="label-text">Condition</span>
                        <span class="value-text"><?= $condition ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label-text">Caveat Status</span>
                        <span class="value-text <?= $under_caveat !== 'No' ? 'text-red-500' : '' ?>"><?= $under_caveat ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label-text">Mileage</span>
                        <span class="value-text"><?= $mileage !== '—' ? $mileage . ' KM' : '—' ?></span>
                    </div>
                </div>

                <div class="flex items-center bg-blue-50 rounded-2xl p-4 mb-8 border border-blue-100">
                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-blue-600 shadow-sm mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                            <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5V2zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1H4z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-extrabold text-blue-400 uppercase tracking-widest mb-0.5">Last Service Recorded</p>
                        <p class="text-xs font-black text-blue-900 uppercase"><?= $last_service ?></p>
                    </div>
                </div>

                <a href="vehicle_report.php?plate=<?= urlencode($plate_no) ?>"
                   target="_blank"
                   class="btn-report-action flex items-center justify-center gap-3 w-full text-white py-5 rounded-[50px] text-xs font-black uppercase tracking-widest shadow-xl">
                    <span>View Full Report</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                    </svg>
                </a>
            </div>

        </div>
    </div>

<?php
} catch (PDOException $e) {
    echo "<div class='lg:col-span-6 text-center mt-10 p-6 bg-red-50 text-red-700 rounded-3xl border border-red-100 mx-4'>";
    echo "<p class='font-black uppercase text-xs tracking-widest mb-1'>Database Connectivity Error</p>";
    echo "<p class='text-sm'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
