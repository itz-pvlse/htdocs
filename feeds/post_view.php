<?php
// feeds/post_view.php
session_start();
require_once '../config/db.php';
require_once 'db_functions.php';
require_once 'formatter.php'; 

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: index.php"); 
    exit;
}

$postId = (int)$_GET['id'];
$current_user_id = $_SESSION['user_id'];

try {
    // 1. Fetch post details - Using 'profile_photo' to match your DB schema
    $stmt = $pdo->prepare("SELECT p.*, u.name, u.handle, u.role, u.profile_photo, 
                          (SELECT COUNT(*) FROM likes WHERE content_id = p.id AND content_type = 'post') as like_count,
                          (SELECT COUNT(*) FROM likes WHERE content_id = p.id AND content_type = 'post' AND user_id = ?) as user_liked
                          FROM posts p 
                          JOIN users u ON p.user_id = u.id 
                          WHERE p.id = ?");
    $stmt->execute([$current_user_id, $postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        die("<p class='text-center py-20 font-bold uppercase tracking-widest text-slate-400'>Post not found.</p>");
    }

    // 2. Fetch comments manually to ensure stability
    $cStmt = $pdo->prepare("SELECT c.*, u.name, u.handle, u.role, u.profile_photo 
                            FROM comments c 
                            JOIN users u ON c.user_id = u.id 
                            WHERE c.post_id = ? 
                            ORDER BY c.created_at ASC");
    $cStmt->execute([$postId]);
    $comments = $cStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion | @<?= htmlspecialchars($post['handle']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-900">

<div class="max-w-2xl mx-auto px-4 py-8">
    <button onclick="window.history.back()" class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-blue-600 transition-colors mb-6">
        <i data-lucide="arrow-left" class="w-3 h-3"></i> Back to Feed
    </button>

    <div class="bg-white rounded-[32px] p-6 shadow-sm border border-slate-100 mb-8">
        <div class="flex items-center gap-3 mb-6">
            <?php $avatar = !empty($post['profile_photo']) ? '../uploads/avatars/'.$post['profile_photo'] : '../assets/img/default-avatar.png'; ?>
            <img src="<?= $avatar ?>" class="w-12 h-12 rounded-2xl object-cover border border-slate-50 shadow-sm">
            <div class="flex-1">
                <h4 class="text-sm font-black uppercase tracking-tight text-slate-900 flex items-center gap-1">
                    <?= htmlspecialchars($post['name']) ?>
                    <?php if($post['role'] === 'dealer'): ?>
                        <i data-lucide="badge-check" class="w-3.5 h-3.5 text-blue-500 fill-blue-500/10"></i>
                    <?php endif; ?>
                </h4>
                <p class="text-[10px] text-slate-400 font-bold italic">@<?= htmlspecialchars($post['handle']) ?></p>
            </div>
            <span class="text-[10px] font-black text-slate-300 uppercase"><?= date('M j, Y', strtotime($post['created_at'])) ?></span>
        </div>

        <div class="text-sm md:text-base text-slate-600 leading-relaxed mb-6 px-1">
            <?= linkify_tags(nl2br(htmlspecialchars($post['content']))) ?>
        </div>

        <?php if (!empty($post['media_path'])): ?>
            <div class="rounded-2xl overflow-hidden border border-slate-50 mb-6 bg-slate-50 shadow-inner">
                <img src="../uploads/media/<?= $post['media_path'] ?>" class="w-full h-auto max-h-[500px] object-cover">
            </div>
        <?php endif; ?>

        <div class="flex items-center pt-4 border-t border-slate-50">
            <button onclick="handleUpvote(<?= $post['id'] ?>, this)" class="flex items-center gap-2 group transition-transform active:scale-90">
                <i data-lucide="arrow-big-up" class="w-5 h-5 <?= $post['user_liked'] ? 'fill-orange-500 text-orange-500' : 'text-slate-300' ?> transition-all"></i>
                <span class="text-xs font-black <?= $post['user_liked'] ? 'text-orange-500' : 'text-slate-400' ?>"><?= $post['like_count'] ?></span>
            </button>
        </div>
    </div>

    <div class="bg-white rounded-[24px] p-4 shadow-sm border border-slate-100 mb-8">
        <form action="process_comment.php" method="POST" class="flex flex-col gap-3">
            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
            <textarea name="comment_text" 
                      class="w-full text-sm p-4 bg-slate-50 rounded-2xl border-none focus:ring-2 focus:ring-blue-500/20 resize-none transition-all" 
                      placeholder="Add to the discussion..." required rows="3"></textarea>
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest px-8 py-3 rounded-xl hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/20 active:scale-95">
                    Post Comment
                </button>
            </div>
        </form>
    </div>

    <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 mb-6 ml-2">Comments (<?= count($comments) ?>)</h3>
    <div class="space-y-4">
        <?php if(empty($comments)): ?>
            <p class="text-center py-10 text-[10px] font-bold text-slate-300 uppercase tracking-widest">No comments yet. Be the first!</p>
        <?php endif; ?>
        
        <?php foreach ($comments as $comment): ?>
            <div class="bg-white rounded-[24px] p-5 border border-slate-100 shadow-sm ml-4 relative">
                <div class="flex items-center gap-3 mb-3">
                    <?php $cAvatar = !empty($comment['profile_photo']) ? '../uploads/avatars/'.$comment['profile_photo'] : '../assets/img/default-avatar.png'; ?>
                    <img src="<?= $cAvatar ?>" class="w-8 h-8 rounded-xl object-cover border border-slate-50">
                    <div class="flex-1">
                        <span class="text-[10px] font-black text-slate-900 uppercase tracking-tight"><?= htmlspecialchars($comment['name']) ?></span>
                        <span class="text-[9px] text-slate-300 font-bold ml-2 uppercase italic"><?= date('M j, H:i', strtotime($comment['created_at'])) ?></span>
                    </div>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed px-1">
                    <?= nl2br(htmlspecialchars($comment['comment_text'])) ?>
                </p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    // Initialize Lucide
    lucide.createIcons();

    /**
     * SYNCED UPVOTE LOGIC
     * Exact same logic as your Dashboard and Community Index
     */
    function handleUpvote(postId, btn) {
        const icon = btn.querySelector('svg, i');
        const span = btn.querySelector('span');

        fetch('ajax_like.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ content_id: postId, type: 'post' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.action === 'liked') {
                    icon.setAttribute('class', 'lucide lucide-arrow-big-up w-5 h-5 fill-orange-500 text-orange-500 transition-all');
                    span.className = 'text-xs font-black text-orange-500';
                } else {
                    icon.setAttribute('class', 'lucide lucide-arrow-big-up w-5 h-5 text-slate-300 transition-all');
                    span.className = 'text-xs font-black text-slate-400';
                }
                span.innerText = data.new_count;
                // Re-init lucide to ensure the SVG is rendered correctly
                lucide.createIcons();
            }
        })
        .catch(err => console.error("Error upvoting:", err));
    }
</script>

</body>
</html>
