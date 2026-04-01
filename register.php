<?php 
require_once 'config/db.php'; 
include 'includes/header.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Account - AUTOLOG</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, #001F3F 0%, #000000 100%) !important;
            background-attachment: fixed !important;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .white-card {
            background: #ffffff;
            box-shadow: 0 50px 100px -20px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
            margin: 20px 0;
        }

        .white-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007aff, #ff3b30);
        }

        .input-group-focus {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e2e8f0;
        }

        .input-group-focus:focus-within {
            border-color: #007aff;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.05);
        }

        .btn-neural {
            background: #000;
            transition: all 0.3s ease;
        }

        .btn-neural:hover {
            background: #007aff;
            transform: translateY(-1px);
        }

        @keyframes micro-slide {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-micro { animation: micro-slide 0.4s ease forwards; }
    </style>
</head>
<body class="antialiased">

<main class="flex-grow flex items-center justify-center px-4 py-8">
    <div class="white-card w-full max-w-[440px] rounded-[32px] p-6 md:p-8 animate-micro">
        
        <div class="flex items-end justify-between mb-6">
            <div>
                <h2 class="text-2xl font-black tracking-tighter text-slate-900 leading-none uppercase">Join Us</h2>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-2">Create Account</p>
            </div>
            <div class="text-right">
                <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-1 rounded-md uppercase">Ecosystem Access</span>
            </div>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-[11px] font-bold flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4"></i>
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <form id="registerForm" action="auth/register_process.php" method="POST" class="space-y-4">
            
            <div class="input-group-focus flex items-center gap-3 px-4 py-2.5 rounded-2xl bg-slate-50">
                <i data-lucide="users" class="w-4 h-4 text-slate-400"></i>
                <select id="role" name="role" required onchange="updateDynamicLabel()" 
                    class="bg-transparent w-full text-sm font-semibold text-slate-900 outline-none cursor-pointer">
                    <option value="" disabled selected>I am a...</option>
                    <option value="owner">Individual Car Owner</option>
                    <option value="garage">Garage / Business</option>
                    <option value="dealer">Car Dealership</option>
                </select>
            </div>

            <div class="space-y-3">
                <div class="input-group-focus flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-50">
                    <i data-lucide="user" class="w-4 h-4 text-slate-400"></i>
                    <input type="text" id="name" name="name" required 
                        class="bg-transparent w-full text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400" 
                        placeholder="Full Name"
                        oninput="handleNameInput(this.value)">
                </div>
                
                <div id="preview-box" class="hidden p-4 rounded-2xl bg-blue-50/50 border border-blue-100 space-y-3 animate-micro">
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black text-blue-600 uppercase tracking-widest mb-1">Public Display Preview</span>
                            <div class="flex items-center gap-2">
                                <div id="avatar-preview" class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-bold uppercase">?</div>
                                <div>
                                    <h4 id="display-name" class="text-sm font-black text-slate-900 leading-none">Name</h4>
                                   <div class="flex items-center justify-between group">
    <div class="flex items-center gap-1.5 bg-blue-100/50 px-3 py-1.5 rounded-full border border-blue-200 transition-all focus-within:border-blue-400">
        <i data-lucide="at-sign" class="w-3 h-3 text-blue-500"></i>
        <input type="text" id="custom-handle" name="custom_handle" 
            class="bg-transparent text-[11px] font-bold text-blue-600 outline-none w-28 placeholder:text-blue-300" 
            readonly
            placeholder="yourhandle"
            oninput="handleManualEntry(this.value)">
        
        <button type="button" onclick="unlockHandle()" id="edit-handle-btn" title="Customize Handle" class="hover:scale-110 transition-transform">
            <i data-lucide="edit-3" class="w-3 h-3 text-slate-400 hover:text-blue-600"></i>
        </button>
    </div>
    
    <div id="handle-status" class="text-[9px] font-black uppercase px-2 py-1 rounded-md hidden"></div>
</div>

                                </div>
                            </div>
                        </div>
                        <div id="handle-status" class="text-[9px] font-black uppercase px-2 py-1 rounded-md hidden"></div>
                    </div>
                    
                    <div class="pt-2 border-t border-blue-100">
                        <p class="text-[9px] font-bold text-slate-500 leading-relaxed uppercase">
                            <i data-lucide="shield-check" class="w-3 h-3 inline mr-1 text-green-600"></i>
                            Official transactions use your Full Name.
                        </p>
                    </div>
                </div>
            </div>

            <div class="input-group-focus flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-50">
                <i data-lucide="mail" class="w-4 h-4 text-slate-400"></i>
                <input type="email" id="email" name="email" required 
                    class="bg-transparent w-full text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400" 
                    placeholder="Email Address">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="input-group-focus flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-50">
                    <input type="password" id="password" name="password" required 
                        class="bg-transparent w-full text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400" 
                        placeholder="Password">
                </div>
                <div class="input-group-focus flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-50">
                    <input type="password" id="confirm_password" name="confirm_password" required 
                        class="bg-transparent w-full text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400" 
                        placeholder="Confirm">
                </div>
            </div>

            <div class="flex items-start gap-3 px-2 py-2">
                <input type="checkbox" id="terms" name="terms" required class="mt-1 w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <label for="terms" class="text-[10px] font-bold text-slate-400 leading-tight uppercase tracking-tight">
                    I agree to the <a href="terms.php" class="text-blue-600">Terms</a> and <a href="privacy.php" class="text-blue-600">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" id="submitBtn" class="btn-neural w-full py-4 rounded-2xl font-black uppercase tracking-widest text-[11px] text-white mt-2 active:scale-95">
                Create Account
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-50 text-center">
            <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest">
                Already have an account? 
                <a href="login.php" class="text-slate-900 hover:underline ml-1">Log in</a>
            </p>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

<script>
    lucide.createIcons();
    
    let debounceTimer;
    let handleLocked = true;
    let isHandleAvailable = false;

    // Triggered by Full Name input
    function handleNameInput(val) {
        const displayName = document.getElementById('display-name');
        const customHandle = document.getElementById('custom-handle');
        const avatarPreview = document.getElementById('avatar-preview');
        const previewBox = document.getElementById('preview-box');
        
        if (val.trim().length > 0) {
            previewBox.classList.remove('hidden');
            displayName.innerText = val;
            avatarPreview.innerText = val.charAt(0);
            
            if (handleLocked) {
                let handle = val.toLowerCase().replace(/[^a-z0-9]/g, '');
                if (handle.length > 20) handle = handle.substring(0, 20);
                customHandle.value = handle;
                handleManualEntry(handle);
            }
        } else {
            previewBox.classList.add('hidden');
        }
    }

    // Triggered by manual handle input
    function handleManualEntry(val) {
        const sanitized = val.toLowerCase().replace(/[^a-z0-9]/g, '');
        document.getElementById('custom-handle').value = sanitized;
        
        const statusEl = document.getElementById('handle-status');
        clearTimeout(debounceTimer);

        // 1. Check for empty
        if (sanitized.length === 0) {
            statusEl.innerText = "Required";
            statusEl.className = "text-[9px] font-black uppercase px-2 py-1 rounded-md bg-amber-100 text-amber-600";
            statusEl.classList.remove('hidden');
            isHandleAvailable = false;
            return;
        }

        // 2. Check for length
        if (sanitized.length < 3) {
            statusEl.innerText = "Too Short";
            statusEl.className = "text-[9px] font-black uppercase px-2 py-1 rounded-md bg-slate-100 text-slate-400";
            statusEl.classList.remove('hidden');
            isHandleAvailable = false;
            return;
        }

        // 3. Debounce DB Check
        statusEl.innerText = "Checking...";
        statusEl.className = "text-[9px] font-black uppercase px-2 py-1 rounded-md bg-slate-100 text-slate-400";
        statusEl.classList.remove('hidden');

        debounceTimer = setTimeout(() => {
            checkHandleAvailability(sanitized);
        }, 500);
    }

    function unlockHandle() {
        handleLocked = false;
        const input = document.getElementById('custom-handle');
        input.removeAttribute('readonly');
        input.focus();
        document.getElementById('edit-handle-btn').classList.add('hidden');
    }

    async function checkHandleAvailability(handle) {
        const statusEl = document.getElementById('handle-status');
        try {
            const response = await fetch(`auth/check_handle.php?handle=${handle}`);
            const data = await response.json();
            
            if (data.status === 'taken') {
                statusEl.innerText = "Taken";
                statusEl.className = "text-[9px] font-black uppercase px-2 py-1 rounded-md bg-red-100 text-red-600";
                isHandleAvailable = false;
            } else {
                statusEl.innerText = "Available";
                statusEl.className = "text-[9px] font-black uppercase px-2 py-1 rounded-md bg-green-100 text-green-600";
                isHandleAvailable = true;
            }
        } catch (error) {
            console.error("Error:", error);
        }
    }

    function updateDynamicLabel() {
        const role = document.getElementById('role').value;
        const input = document.getElementById('name');
        input.placeholder = (role === 'garage') ? "Garage Name" : (role === 'dealer' ? "Dealership Name" : "Full Name");
    }

    document.getElementById("registerForm").addEventListener("submit", (e) => {
        const password = document.getElementById("password").value;
        const confirm = document.getElementById("confirm_password").value;

        if (password !== confirm) {
            e.preventDefault();
            alert("Passwords do not match!");
            return;
        }

        if (!isHandleAvailable) {
            e.preventDefault();
            alert("Please provide a valid, available handle.");
        }
    });
</script>
</body>
</html>
