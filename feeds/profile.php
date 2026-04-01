<?php
require_once '../config/db.php';
require_once 'db_functions.php'; // Updated: Required for get_user_avatar()
require_once 'formatter.php'; 
session_start();

// 1. Get handle from URL (?u=kassimbakari)
$handle = $_GET['u'] ?? null;

// 2. If no handle in URL, check if the user is looking at their own profile via session
if (!$handle && isset($_SESSION['user_handle'])) {
    $handle = $_SESSION['user_handle'];
}

// 3. If STILL no handle, try to get it from DB
if (!$handle && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT handle FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $handle = $stmt->fetchColumn();
    
    if ($handle) {
        $_SESSION['user_handle'] = $handle; 
    }
}

// 4. Final safety check
if (!$handle) {
    header("Location: index.php?error=no_handle_found");
    exit;
}

// 5. Fetch User Data by Handle (Updated: Added profile_photo)
$stmt = $pdo->prepare("SELECT id, name, handle, role, profile_photo, created_at FROM users WHERE handle = ?");
$stmt->execute([$handle]);
$user = $stmt->fetch();

if (!$user) {
    ?>
    <body style='background:#0f0f0f; color:white; font-family:sans-serif; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100vh;'>
        <h1 style='color:#ef4444'>Handle Not Found</h1>
        <p>Could not find user with handle: @<b><?= htmlspecialchars($handle) ?></b></p>
        <a href='index.php' style='color:#3b82f6; margin-top:20px;'>Return to Feed</a>
    </body>
    <?php
    exit;
}

// Get the actual profile photo using our Smart Logic
$profileAvatar = get_user_avatar($user['profile_photo'], $user['role'], $user['name']);

// 6. Fetch User's Posts
$postStmt = $pdo->prepare("
    SELECT p.*, u.handle, (SELECT COUNT(*) FROM likes WHERE content_id = p.id AND content_type = 'post') as like_count
    FROM posts p 
    JOIN users u ON p.user_id = u.id
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC
");
$postStmt->execute([$user['id']]);
$userPosts = $postStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($user['name']) ?> (@<?= htmlspecialchars($user['handle']) ?>)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .profile-gradient { background: linear-gradient(180deg, #2563eb 0%, #0f0f0f 100%); }
        .glass-card { background: rgba(26, 26, 27, 0.8); backdrop-filter: blur(10px); }
        .profile-image { object-fit: cover; }
    </style>
</head>
<body class="bg-[#0F0F0F] text-[#D7DADC]">

    <nav class="fixed top-0 w-full z-50 glass-card border-b border-[#2D2D2E] px-4 py-3">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2 text-gray-400 hover:text-white transition">
                <i data-lucide="chevron-left" class="w-5 h-5"></i> <span>Feed</span>
            </a>
            <h2 class="font-bold">@<?= htmlspecialchars($user['handle']) ?></h2>
            <div class="w-10"></div>
        </div>
    </nav>

    <div class="pt-16 max-w-4xl mx-auto">
        <div class="relative h-64 profile-gradient rounded-b-[3rem] border-b border-[#2D2D2E]">
            <div class="absolute -bottom-16 left-8 flex items-end gap-6">
                <div class="relative">
                    <img src="<?= $profileAvatar ?>" 
                         class="w-32 h-32 rounded-3xl bg-[#1A1A1B] border-4 border-[#0F0F0F] shadow-2xl profile-image">
                </div>
                <div class="mb-4">
                    <h1 class="text-4xl font-black text-white leading-tight"><?= htmlspecialchars($user['name']) ?></h1>
                    <p class="text-blue-400 font-bold flex items-center gap-1">
                        @<?= htmlspecialchars($user['handle']) ?>
                        <i data-lucide="badge-check" class="w-4 h-4"></i>
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mt-20 px-8">
            <div class="bg-[#1A1A1B] p-4 rounded-2xl border border-[#2D2D2E] text-center">
                <p class="text-2xl font-black text-white"><?= count($userPosts) ?></p>
                <p class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Posts</p>
            </div>
            <div class="bg-[#1A1A1B] p-4 rounded-2xl border border-[#2D2D2E] text-center text-blue-500">
                <p class="text-xl font-black uppercase"><?= htmlspecialchars($user['role'] ?? 'Member') ?></p>
                <p class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Status</p>
            </div>
            <div class="bg-[#1A1A1B] p-4 rounded-2xl border border-[#2D2D2E] text-center">
                <p class="text-lg font-black text-white"><?= date('M Y', strtotime($user['created_at'])) ?></p>
                <p class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Joined</p>
            </div>
        </div>

        <div class="mt-12 px-8 pb-20">
            <h3 class="text-sm font-bold text-gray-400 mb-6 uppercase tracking-wider">User Activity</h3>
            
            <div class="space-y-4">
                <?php if (empty($userPosts)): ?>
                    <div class="text-center py-12 bg-[#1A1A1B] rounded-3xl border border-dashed border-[#2D2D2E]">
                        <p class="text-gray-600 italic">No posts yet...</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($userPosts as $post): ?>
                        <div class="bg-[#1A1A1B] p-6 rounded-3xl border border-[#2D2D2E] hover:border-[#3b82f644] transition-colors">
                            <p class="text-[#D7DADC] text-lg leading-relaxed mb-4">
                                <?php echo nl2br(linkify_tags(htmlspecialchars($post['content']))); ?>
                            </p>
                            
                            <?php if (!empty($post['media_path'])): ?>
                                <div class="mb-4 rounded-xl overflow-hidden border border-[#2D2D2E] max-w-sm">
                                    <?php if ($post['media_type'] === 'image'): ?>
                                        <img src="../uploads/media/<?= $post['media_path'] ?>" class="w-full h-auto">
                                    <?php else: ?>
                                        <video src="../uploads/media/<?= $post['media_path'] ?>" controls class="w-full h-auto"></video>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="flex items-center gap-4 text-xs text-gray-500">
                                <span class="flex items-center gap-1"><i data-lucide="heart" class="w-3 h-3 text-red-500"></i> <?= $post['like_count'] ?></span>
                                <span>•</span>
                                <span><?= date('F j, Y', strtotime($post['created_at'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
