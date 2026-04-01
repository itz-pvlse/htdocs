<?php
require_once 'config/db.php';
session_start();

$my_id = $_SESSION['user_id'] ?? null;
$profile_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$profile_id) { die("Profile ID missing."); }

/** * BUSINESS LOGIC: user_garages */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_driver'])) {
    $insert = $pdo->prepare("INSERT IGNORE INTO user_garages (user_id, garage_id, is_preferred) VALUES (?, ?, 0)");
    $insert->execute([$profile_id, $my_id]);
    header("Location: dealer.php?id=" . $profile_id);
    exit;
}

try {
    $u_stmt = $pdo->prepare("SELECT id, name, email, phone, profile_photo, avatar, role FROM users WHERE id = ?");
    $u_stmt->execute([$profile_id]);
    $user = $u_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) { die("User not found."); }

    $is_following = false;
    if ($my_id) {
        $f_check = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = ?");
        $f_check->execute([$my_id, $profile_id]);
        $is_following = $f_check->fetchColumn() > 0;
    }

    $f_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
    $f_count_stmt->execute([$profile_id]);
    $follower_count = $f_count_stmt->fetchColumn();

    $v_stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ?");
    $v_stmt->execute([$profile_id]);
    $vehicles = $v_stmt->fetchAll(PDO::FETCH_ASSOC);

    $feed_stmt = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC");
    $feed_stmt->execute([$profile_id]);
    $feed_items = $feed_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($user['name']) ?> | Passport</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap');
        
        :root {
            --accent: #6366f1;
            --glass: rgba(255, 255, 255, 0.7);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: radial-gradient(circle at top right, #f8fafc, #f1f5f9);
            color: #1e293b;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03);
        }

        .drawer-content { 
            max-height: 0; 
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
        }

        .drawer-active .drawer-content { 
            max-height: 1200px; 
            opacity: 1;
            transform: translateY(0);
            padding-bottom: 1.5rem;
        }

        .drawer-active .chevron-icon { transform: rotate(180deg); color: var(--accent); }

        .feed-grid { column-count: 1; column-gap: 1.5rem; }
        @media (min-width: 768px) { .feed-grid { column-count: 2; } }

        .profile-gradient {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        }

        .btn-follow-active { background: #fff; color: #64748b; border: 1px solid #e2e8f0; }
        .btn-follow-inactive { background: #0f172a; color: white; box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.3); }
        
        .hover-lift { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(0,0,0,0.06); }
    </style>
</head>
<body class="pb-24">

    <nav class="sticky top-0 z-50 glass-card border-b border-white/50 px-6 py-4 mb-10">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <button onclick="history.back()" class="group flex items-center gap-2 text-slate-400 hover:text-slate-900 transition-colors">
                <i class="fa-solid fa-arrow-left-long group-hover:-translate-x-1 transition-transform"></i>
                <span class="text-[10px] font-black uppercase tracking-widest">Back</span>
            </button>
            <div class="flex gap-4">
                <button id="followBtn" 
                        data-status="<?= $is_following ? 'following' : 'not_following' ?>" 
                        onclick="toggleFollow(<?= $profile_id ?>)" 
                        class="h-10 px-8 rounded-full text-[10px] font-black uppercase tracking-[0.2em] transition-all <?= $is_following ? 'btn-follow-active' : 'btn-follow-inactive' ?>">
                    <?= $is_following ? 'Following' : 'Follow' ?>
                </button>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-6">
        
        <div class="flex flex-col items-center mb-16">
            <div class="relative group cursor-pointer mb-6">
                <div class="absolute -inset-1 profile-gradient rounded-[2.5rem] blur opacity-25 group-hover:opacity-50 transition duration-1000 group-hover:duration-200"></div>
                <div class="relative w-32 h-32 rounded-[2.2rem] overflow-hidden p-1 bg-white border border-slate-100">
                    <?php $photo = !empty($user['profile_photo']) ? $user['profile_photo'] : $user['avatar']; ?>
                    <img src="../uploads/profiles/<?= e($photo) ?>" class="w-full h-full object-cover rounded-[2rem]" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=000&color=fff'">
                </div>
                <div class="absolute -bottom-2 -right-2 bg-white p-2 rounded-xl shadow-lg border border-slate-50">
                    <i class="fa-solid fa-shield-check text-indigo-500 text-sm"></i>
                </div>
            </div>

            <h1 class="text-4xl font-black italic tracking-tighter uppercase text-slate-900 leading-none"><?= e($user['name']) ?></h1>
            <div class="flex items-center gap-3 mt-3">
                <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-[8px] font-black uppercase tracking-widest"><?= e($user['role']) ?></span>
                <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">ID: #<?= $user['id'] ?></span>
            </div>
            
            <div class="flex gap-12 mt-10">
                <div class="text-center group">
                    <p id="followerCount" class="text-2xl font-black italic text-slate-900 group-hover:text-indigo-600 transition-colors"><?= number_format($follower_count) ?></p>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">Followers</p>
                </div>
                <div class="text-center group">
                    <p class="text-2xl font-black italic text-slate-900 group-hover:text-indigo-600 transition-colors"><?= count($vehicles) ?></p>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">Garage Items</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-20">
            
            <div id="hangarDrawer" class="glass-card rounded-[2rem] overflow-hidden transition-all duration-300 border border-white/60">
                <button onclick="toggleDrawer('hangarDrawer')" class="w-full p-6 flex items-center justify-between hover:bg-white/40 transition-colors">
                    <div class="flex items-center gap-5">
                        <div class="w-12 h-12 rounded-2xl bg-white shadow-sm flex items-center justify-center text-indigo-500 border border-slate-50">
                            <i class="fa-solid fa-layer-group"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-[11px] font-black uppercase tracking-widest text-slate-900">The Hangar</p>
                            <p class="text-[9px] text-slate-400 font-bold italic"><?= count($vehicles) ?> VERIFIED ASSETS</p>
                        </div>
                    </div>
                    <div class="w-8 h-8 rounded-full border border-slate-100 flex items-center justify-center">
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-300 chevron-icon transition-transform duration-500"></i>
                    </div>
                </button>
                <div class="drawer-content px-6">
                    <div class="space-y-3">
                        <?php foreach($vehicles as $v): ?>
                            <div class="flex items-center justify-between p-4 rounded-2xl bg-white/50 border border-white hover:border-indigo-100 hover:bg-white transition-all group">
                                <div class="flex items-center gap-4">
                                    <div class="relative">
                                        <img src="/<?= e($v['image_path']) ?>" class="w-14 h-14 rounded-xl object-cover shadow-sm group-hover:scale-105 transition-transform">
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase text-slate-900 leading-tight"><?= e($v['year']) ?> <?= e($v['make']) ?></p>
                                        <p class="text-[9px] font-mono text-slate-400 mt-1"><?= e($v['plate_no']) ?></p>
                                    </div>
                                </div>
                                <a href="/v.php?id=<?= $v['id'] ?>" class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-300 group-hover:bg-slate-900 group-hover:text-white transition-all">
                                    <i class="fa-solid fa-arrow-right-long"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div id="contactDrawer" class="glass-card rounded-[2rem] overflow-hidden transition-all duration-300 border border-white/60">
                <button onclick="toggleDrawer('contactDrawer')" class="w-full p-6 flex items-center justify-between hover:bg-white/40 transition-colors">
                    <div class="flex items-center gap-5">
                        <div class="w-12 h-12 rounded-2xl bg-white shadow-sm flex items-center justify-center text-emerald-500 border border-slate-50">
                            <i class="fa-solid fa-paper-plane"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-[11px] font-black uppercase tracking-widest text-slate-900">Connections</p>
                            <p class="text-[9px] text-slate-400 font-bold italic">CONTACT CHANNELS</p>
                        </div>
                    </div>
                    <div class="w-8 h-8 rounded-full border border-slate-100 flex items-center justify-center">
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-300 chevron-icon transition-transform duration-500"></i>
                    </div>
                </button>
                <div class="drawer-content px-6">
                    <div class="space-y-4">
                        <div class="p-4 rounded-2xl bg-white/50 flex items-center gap-4 border border-white">
                            <i class="fa-solid fa-envelope-open text-slate-400 text-xs"></i>
                            <p class="text-[11px] font-bold text-slate-600 truncate"><?= e($user['email']) ?></p>
                        </div>
                        <div class="p-4 rounded-2xl bg-white/50 flex items-center gap-4 border border-white">
                            <i class="fa-solid fa-phone text-slate-400 text-xs"></i>
                            <p class="text-[11px] font-bold text-slate-600"><?= !empty($user['phone']) ? e($user['phone']) : 'Privacy Protected' ?></p>
                        </div>
                        <?php if(!$is_saved): ?>
                        <form method="POST" class="pt-2">
                            <button type="submit" name="save_driver" class="w-full py-4 bg-indigo-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all">
                                Establish Link
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between mb-10">
            <h2 class="text-[11px] font-black uppercase tracking-[0.5em] text-slate-400">Broadcasting Feed</h2>
            <div class="h-px flex-1 bg-slate-200 mx-6 opacity-50"></div>
            <div class="flex gap-2">
                <button class="w-8 h-8 rounded-lg bg-white border border-slate-100 flex items-center justify-center text-slate-400"><i class="fa-solid fa-grid-2"></i></button>
            </div>
        </div>

        <div class="feed-grid">
            <?php if (empty($feed_items)): ?>
                <div class="w-full py-32 text-center glass-card rounded-[2rem] border-dashed border-slate-300">
                    <i class="fa-solid fa-satellite-dish text-slate-200 text-4xl mb-4"></i>
                    <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest italic">Waiting for transmission...</p>
                </div>
            <?php else: ?>
                <?php foreach($feed_items as $post): ?>
                    <div class="feed-item glass-card rounded-[2.5rem] overflow-hidden hover-lift p-2">
                        <div class="bg-white rounded-[2rem] overflow-hidden">
                            <div class="p-5 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl bg-slate-100 overflow-hidden">
                                    <img src="../uploads/profiles/<?= e($photo) ?>" class="w-full h-full object-cover">
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-900"><?= e($user['name']) ?></span>
                                <span class="ml-auto text-[8px] font-bold text-slate-300 uppercase"><?= date('M d', strtotime($post['created_at'])) ?></span>
                            </div>

                            <?php if($post['type'] !== 'text'): ?>
                                <div class="px-2 pb-2">
                                    <div class="rounded-[1.5rem] overflow-hidden bg-slate-100">
                                        <?php if($post['type'] === 'video'): ?>
                                            <video src="../uploads/posts/<?= e($post['file_path']) ?>" controls class="w-full"></video>
                                        <?php else: ?>
                                            <img src="../uploads/posts/<?= e($post['file_path']) ?>" class="w-full hover:scale-105 transition-transform duration-1000">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="p-6 pt-4">
                                <p class="text-xs text-slate-600 leading-relaxed font-medium mb-6">
                                    <?= nl2br(e($post['content'])) ?>
                                </p>
                                <div class="flex gap-6 pt-4 border-t border-slate-50">
                                    <button class="flex items-center gap-2 text-slate-300 hover:text-rose-500 transition-colors">
                                        <i class="fa-solid fa-heart text-sm"></i>
                                        <span class="text-[10px] font-black uppercase">Like</span>
                                    </button>
                                    <button class="flex items-center gap-2 text-slate-300 hover:text-indigo-500 transition-colors">
                                        <i class="fa-solid fa-comment text-sm"></i>
                                        <span class="text-[10px] font-black uppercase">Reply</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
    function toggleDrawer(id) {
        const drawer = document.getElementById(id);
        const allDrawers = ['hangarDrawer', 'contactDrawer'];
        
        allDrawers.forEach(dId => {
            if(dId !== id) document.getElementById(dId).classList.remove('drawer-active');
        });
        
        drawer.classList.toggle('drawer-active');
    }

    async function toggleFollow(targetId) {
        const btn = document.getElementById('followBtn');
        const countDisplay = document.getElementById('followerCount');
        
        if (btn.getAttribute('data-status') === 'following') {
            if (!confirm("Terminate connection with <?= e($user['name']) ?>?")) return;
        }

        btn.style.opacity = '0.5';

        try {
            const formData = new FormData();
            formData.append('target_id', targetId);
            const res = await fetch('../socials/follow_manager.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                countDisplay.innerText = data.count;
                const isFollowed = data.action === 'followed';
                btn.innerText = isFollowed ? 'Following' : 'Follow';
                btn.setAttribute('data-status', isFollowed ? 'following' : 'not_following');
                btn.className = `h-10 px-8 rounded-full text-[10px] font-black uppercase tracking-[0.2em] transition-all ${isFollowed ? 'btn-follow-active' : 'btn-follow-inactive'}`;
            }
        } catch (e) { console.error(e); } finally {
            btn.style.opacity = '1';
        }
    }
    </script>
</body>
</html>
