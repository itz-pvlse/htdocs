<?php
session_start();
require_once '../config/db.php';
require_once 'db_functions.php'; 
require_once 'formatter.php'; 

$post_id = $_GET['post_id'] ?? 0;
$current_user_id = $_SESSION['user_id'] ?? 0;
$view_all = isset($_GET['view_all']) && $_GET['view_all'] === 'true';

// 1. Unified Query
$query = "SELECT c.*, u.name, u.handle, u.role, u.profile_photo, u.id as commenter_id,
          (SELECT COUNT(*) FROM likes WHERE content_id = c.id AND content_type = 'comment') as like_count,
          (SELECT COUNT(*) FROM likes WHERE content_id = c.id AND content_type = 'comment' AND user_id = ?) as user_liked
          FROM comments c
          JOIN users u ON c.user_id = u.id
          WHERE c.post_id = ?
          ORDER BY c.created_at ASC";

if (!$view_all) { $query .= " LIMIT 20"; } 

$stmt = $pdo->prepare($query);
$stmt->execute([$current_user_id, $post_id]);
$all_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$commentMap = [];
foreach ($all_comments as $c) {
    $c['replies'] = [];
    $commentMap[$c['id']] = $c;
}

$tree = [];
foreach ($commentMap as $id => &$c) {
    if ($c['parent_id'] && isset($commentMap[$c['parent_id']])) {
        $commentMap[$c['parent_id']]['replies'][] = &$c;
    } else {
        $tree[] = &$c;
    }
}

/**
 * 2. Recursive Function - Fixed Click Targets
 */
function renderComments($comments, $postId, $current_user_id, $depth = 0, $parentHandle = null) {
    foreach ($comments as $comment) {
        $avatarUrl = get_user_avatar($comment['profile_photo'], $comment['role'], $comment['name']);
        $isOwner = ($comment['commenter_id'] == $current_user_id);
        
        $profilePath = ($comment['role'] === 'dealer') ? '../dealer/dealer.php' : '../user/profile.php';
        $profileLink = $profilePath . "?id=" . $comment['commenter_id'];

        $indentClass = "";
        if ($depth > 0) {
            $margin = ($depth === 1) ? "ml-6" : "ml-0"; 
            $indentClass = "$margin border-l border-[#2D2D2E] pl-4 mt-4";
        } else {
            $indentClass = "py-6 border-b border-white/5";
        }
        ?>
        
        <div id="comment-wrapper-<?= $comment['id'] ?>" 
             class="comment-item relative group <?= $indentClass ?> transition-opacity duration-300">
            
            <div class="absolute top-4 right-2 z-50">
                <button onclick="console.log('Btn Clicked ID: <?= $comment['id'] ?>'); toggleCommentMenu(event, <?= $comment['id'] ?>)" 
                        class="p-2 text-[#818384] hover:text-white transition-colors cursor-pointer block">
                    <i data-lucide="more-horizontal" class="w-5 h-5 pointer-events-none"></i>
                </button>
                
                <div id="dropdown-<?= $comment['id'] ?>" 
                     class="hidden absolute right-0 mt-2 w-36 bg-[#1A1A1B] border border-[#2D2D2E] rounded-xl shadow-2xl overflow-hidden">
                    <?php if ($isOwner): ?>
                        <button onclick="showDeleteOverlay(<?= $comment['id'] ?>)" 
                                class="w-full text-left px-4 py-3 text-[10px] font-black text-rose-500 hover:bg-white/5 uppercase tracking-widest">
                            Delete Comment
                        </button>
                    <?php else: ?>
                        <button onclick="reportComment(<?= $comment['id'] ?>)" 
                                class="w-full text-left px-4 py-3 text-[10px] font-black text-[#818384] hover:bg-white/5 uppercase tracking-widest">
                            Report
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isOwner): ?>
                <div id="delete-overlay-<?= $comment['id'] ?>" 
                     class="hidden absolute inset-0 bg-black/95 backdrop-blur-md flex items-center justify-center z-[60]">
                    <div class="flex flex-col items-center gap-3 text-center px-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-white/50">Remove this comment?</p>
                        <div class="flex gap-2">
                            <button onclick="processCommentDelete(<?= $comment['id'] ?>)" 
                                    class="bg-rose-600 text-white px-6 py-2 rounded-full text-[10px] font-black uppercase tracking-widest shadow-lg active:scale-95 transition-transform">
                                Delete
                            </button>
                            <button onclick="hideDeleteOverlay(<?= $comment['id'] ?>)" 
                                    class="bg-[#2D2D2E] text-white px-6 py-2 rounded-full text-[10px] font-black uppercase tracking-widest active:scale-95 transition-transform">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex gap-3">
                <a href="<?= $profileLink ?>" class="flex-shrink-0 z-10">
                    <img src="<?= $avatarUrl ?>" class="w-8 h-8 rounded-full border border-[#2D2D2E] object-cover hover:opacity-80 transition-opacity">
                </a>
                
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <a href="<?= $profileLink ?>" class="hover:underline decoration-blue-500/40 z-10">
                            <span class="text-[11px] font-bold text-[#D7DADC]">@<?= htmlspecialchars($comment['handle']) ?></span>
                        </a>

                        <?php if($comment['role'] === 'dealer'): ?>
                            <span class="text-[8px] bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded font-black uppercase">Dealer</span>
                        <?php endif; ?>
                        <span class="text-[9px] text-[#818384]"><?= date('H:i', strtotime($comment['created_at'])) ?></span>
                    </div>

                    <p class="text-[12px] text-[#D7DADC] leading-relaxed break-words pr-10">
                        <?php if ($depth > 0 && $parentHandle): ?>
                            <span class="text-blue-500 font-bold mr-1">@<?= htmlspecialchars($parentHandle) ?></span>
                        <?php endif; ?>
                        <?= nl2br(linkify_tags(htmlspecialchars($comment['comment_text']))) ?>
                    </p>

                    <div class="flex items-center gap-6 mt-3">
                        <div class="flex items-center gap-1">
                            <button onclick="handleLike(this, <?= $comment['id'] ?>, 'comment')" class="group p-1 -ml-1">
                                <i data-lucide="arrow-big-up" class="w-4 h-4 transition-all <?= $comment['user_liked'] ? 'fill-orange-500 text-orange-500' : 'text-[#818384] group-hover:text-orange-500' ?>"></i>
                            </button>
                            <span class="count-num text-[10px] font-bold text-[#818384]">
                                <?= $comment['like_count'] ?>
                            </span>
                        </div>

                        <button onclick="prepareReply('<?= htmlspecialchars($comment['handle']) ?>', '<?= $comment['id'] ?>')" 
                                class="text-[10px] font-black text-[#818384] hover:text-white uppercase tracking-widest transition-colors">
                            Reply
                        </button>
                    </div>
                </div>
            </div>

            <?php if (!empty($comment['replies'])): ?>
                <div class="replies-container">
                    <?php renderComments($comment['replies'], $postId, $current_user_id, $depth + 1, $comment['handle']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Render
if (empty($tree)) {
    echo '<div class="flex flex-col items-center py-10 opacity-30">
            <i data-lucide="message-square" class="w-8 h-8 text-gray-500 mb-2"></i>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">No comments yet</p>
          </div>';
} else {
    renderComments($tree, $post_id, $current_user_id);
}
