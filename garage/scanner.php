<?php
/**
 * scanner.php - GarageOS Terminal Lens (Bidirectional: Search & Register)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php"); exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Scanner | GarageOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #000; font-family: 'Plus Jakarta Sans', sans-serif; }
        #reader video { width: 100% !important; height: 100% !important; object-fit: cover !important; }
        .scan-overlay {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 280px; height: 180px; border: 2px solid rgba(255,255,255,0.5);
            border-radius: 1rem; box-shadow: 0 0 0 4000px rgba(0,0,0,0.5); z-index: 10;
        }
        .scan-line {
            position: absolute; top: 0; left: 0; width: 100%; height: 2px;
            background: #34d399; box-shadow: 0 0 8px #34d399; animation: scan 2s linear infinite;
        }
        @keyframes scan { 0% { top: 0; } 100% { top: 100%; } }
    </style>
</head>
<body class="flex flex-col h-screen overflow-hidden bg-black">

    <div class="fixed top-0 left-0 right-0 p-6 z-50 flex justify-between items-center bg-gradient-to-b from-black/80 to-transparent">
        <a href="admin_parts.php" class="w-10 h-10 bg-white/10 backdrop-blur-md rounded-xl flex items-center justify-center text-white">
            <i class="fa-solid fa-xmark"></i>
        </a>
        <div>
            <h1 class="text-white text-xs font-black uppercase tracking-widest italic text-center">
                <?= isset($_GET['mode']) && $_GET['mode'] === 'register' ? 'Registration Scan' : 'Inventory Scan' ?>
            </h1>
            <p class="text-[8px] text-emerald-400 font-bold uppercase text-center tracking-tighter">GarageOS Terminal</p>
        </div>
        <div class="w-10"></div>
    </div>

    <div class="relative flex-1 flex items-center justify-center overflow-hidden">
        <div id="reader" class="w-full h-full"></div>
        <div class="scan-overlay">
            <div class="scan-line" id="line"></div>
        </div>
    </div>

    <div class="bg-black p-8 pb-12 flex flex-col items-center gap-4 relative z-50">
        <div class="w-16 h-1.5 bg-white/20 rounded-full mb-2"></div>
        <p id="status-text" class="text-white/60 text-[10px] font-bold uppercase tracking-widest">Initialising Camera...</p>
        
        <div class="flex gap-4 w-full mt-4">
            <button onclick="switchCamera()" class="flex-1 bg-white/10 py-4 rounded-2xl text-white text-[10px] font-black uppercase tracking-widest">
                <i class="fa-solid fa-camera-rotate mr-2"></i> Flip Camera
            </button>
        </div>
    </div>

    <script>
        const html5QrCode = new Html5Qrcode("reader");
        const urlParams = new URLSearchParams(window.location.search);
        const mode = urlParams.get('mode') || 'search';
        
        let currentCameraId;
        let cameras = [];

        function onScanSuccess(decodedText, decodedResult) {
            if (navigator.vibrate) navigator.vibrate(100);
            document.getElementById('status-text').innerText = "CODE CAPTURED";
            document.getElementById('line').style.background = "#fbbf24"; // Turn amber on success
            
            // Redirect logic based on mode
            if (mode === 'register') {
                window.location.href = `admin_parts.php?new_barcode=${encodeURIComponent(decodedText)}`;
            } else {
                window.location.href = `admin_parts.php?search=${encodeURIComponent(decodedText)}&autofocus=true`;
            }
        }

        Html5Qrcode.getCameras().then(devices => {
            if (devices && devices.length) {
                cameras = devices;
                const backCamera = devices.find(device => device.label.toLowerCase().includes('back') || device.label.toLowerCase().includes('rear'));
                currentCameraId = backCamera ? backCamera.id : devices[0].id;
                startCamera(currentCameraId);
            }
        }).catch(err => {
            document.getElementById('status-text').innerText = "CAMERA ERROR: Check Permissions";
        });

        function startCamera(id) {
            html5QrCode.start(id, { fps: 15, qrbox: { width: 280, height: 180 } }, onScanSuccess)
            .then(() => { document.getElementById('status-text').innerText = `READY TO ${mode.toUpperCase()}`; });
        }

        function switchCamera() {
            html5QrCode.stop().then(() => {
                currentCameraId = (currentCameraId === cameras[0].id && cameras[1]) ? cameras[1].id : cameras[0].id;
                startCamera(currentCameraId);
            });
        }
    </script>
</body>
</html>
