<?php
session_start();
require_once '../config/db.php'; 
require_once 'db_functions.php';
require_once 'formatter.php';
require_once 'trending_logic.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$current_user_id = $_SESSION['user_id'];

// Get existing filters
$filter = isset($_GET['view']) && $_GET['view'] === 'following' ? 'following' : 'all';
$tag_filter = $_GET['tag'] ?? null;
$search_query = $_GET['search'] ?? null;

/**
 * 1. UNIFIED QUERY
 * Merges the feed filters with the Dealer Listing data and interaction counts
 */
$params = [$current_user_id, $current_user_id];

$query = "SELECT p.*, u.name, u.handle, u.role, u.profile_photo, u.is_verified,
          dl.make, dl.model, dl.price, dl.year, dl.main_image as listing_image, dl.vehicle_condition,
          (SELECT COUNT(*) FROM likes WHERE content_id = p.id AND content_type = 'post') as like_count,
          (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
          (SELECT COUNT(*) FROM likes WHERE content_id = p.id AND content_type = 'post' AND user_id = ?) as user_liked,
          f.id AS following_indicator
          FROM posts p 
          JOIN users u ON p.user_id = u.id 
          LEFT JOIN follows f ON f.follower_id = ? AND f.following_id = p.user_id 
          LEFT JOIN dealer_listings dl ON p.listing_id = dl.id
          WHERE 1=1"; 

if ($filter === 'following') {
    $query .= " AND f.id IS NOT NULL";
}

if ($tag_filter) {
    $query .= " AND p.content LIKE ?";
    $params[] = "%#$tag_filter%";
}

if ($search_query) {
    $query .= " AND (p.content LIKE ? OR u.name LIKE ? OR u.handle LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$trendingTags = getTrendingTags($pdo);

/* ============================================================
   EMPTY STATE WITH TRENDING CAROUSEL (If no posts match filters)
   ============================================================ */
if (empty($posts)) {
    $trendingStmt = $pdo->prepare("
        SELECT p.*, u.name as author_name, u.handle as author_handle, u.is_verified,
        dl.main_image as listing_image,
        (SELECT COUNT(*) FROM likes WHERE content_id = p.id AND content_type = 'post') as likes
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN dealer_listings dl ON p.listing_id = dl.id
        ORDER BY likes DESC LIMIT 5
    ");
    $trendingStmt->execute();
    $trending = $trendingStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <div class='col-span-full flex flex-col items-center justify-center py-20 px-6 text-center'>
        <div class='relative mb-6'>
            <div class='absolute inset-0 bg-blue-500/10 blur-3xl rounded-full'></div>
            <div class='relative w-20 h-20 bg-[#161617] border border-[#2D2D2E] rounded-3xl flex items-center justify-center shadow-xl'>
                <i data-lucide='layers' class='w-8 h-8 text-slate-500'></i>
            </div>
        </div>
        <h3 class='text-white font-black text-sm uppercase tracking-widest mb-2'>No Posts Found</h3>
        <p class='text-slate-400 text-[11px] font-medium max-w-[240px] leading-relaxed mb-8'>
            We couldn't find any posts matching your criteria. Try adjusting your filters or explore trending topics.
        </p>
        
        <div class='w-full max-w-6xl mx-auto mt-10'>
            <div class='flex items-center justify-between mb-8 px-2'>
                <h4 class='text-[10px] font-black uppercase tracking-[0.3em] text-slate-500'>Trending Discussions</h4>
                <div class='h-px flex-1 bg-[#2D2D2E] ml-6'></div>
            </div>
            <div class='flex gap-5 overflow-x-auto pb-10 no-scrollbar snap-x touch-pan-x'>
                <?php foreach ($trending as $t): 
                    $preview = htmlspecialchars(substr(strip_tags($t['content']), 0, 70)) . '...';
                    $tMedia = !empty($t['listing_image']) ? "../" . $t['listing_image'] : (!empty($t['media_path']) ? "../uploads/media/" . $t['media_path'] : "");
                ?>
                    <div class='min-w-[280px] md:min-w-[320px] snap-start bg-[#161617] border border-[#2D2D2E] p-4 rounded-[2.5rem] text-left shadow-sm transition-all duration-300 flex flex-col h-full'>
                        <div class='w-full h-32 rounded-[1.8rem] overflow-hidden mb-4 bg-black shrink-0'>
                            <?= $tMedia ? "<img src='{$tMedia}' class='w-full h-full object-cover'>" : "<div class='w-full h-full flex items-center justify-center opacity-20'><i data-lucide='message-circle' class='w-8 h-8 text-slate-400'></i></div>" ?>
                        </div>
                        <div class='flex-grow px-2'>
                            <div class='flex items-center gap-3 mb-3'>
                                <div class='flex flex-col min-w-0'>
                                    <h4 class='text-[10px] font-black text-white uppercase tracking-tight flex items-center gap-1'>
                                        <span class='truncate'><?= $t['author_name'] ?></span>
                                        <?= ($t['is_verified'] == 1 ? "<i data-lucide='badge-check' class='w-3 h-3 text-blue-500 fill-blue-500/10'></i>" : "") ?>
                                    </h4>
                                    <span class='text-[8px] font-bold text-slate-500 truncate'>@<?= $t['author_handle'] ?></span>
                                </div>
                            </div>
                            <p class='text-[11px] text-slate-400 leading-relaxed mb-6 line-clamp-2 min-h-[2.5rem]'><?= $preview ?></p>
                        </div>
                        <div class='mt-auto flex items-center justify-between pt-4 border-t border-[#2D2D2E] px-2 shrink-0'>
                            <div class='flex items-center gap-1.5'>
                                <i data-lucide='arrow-big-up' class='w-3.5 h-3.5 text-orange-500 fill-orange-500'></i>
                                <span class='text-[10px] font-black text-white'><?= $t['likes'] ?></span>
                            </div>
                            <button onclick="window.location.href='index.php?post_id=<?= $t['id'] ?>'" class='text-[9px] font-black text-blue-500 uppercase tracking-widest py-1.5 px-4 bg-blue-500/10 rounded-xl hover:bg-blue-600 hover:text-white transition-colors'>Join Thread</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    exit;
}

/* ============================================================
   MAIN POST LOOP
   ============================================================ */
foreach ($posts as $post): 
    $avatar = get_user_avatar($post['profile_photo'], $post['role'], $post['name']);
    $postId = $post['id'];

    // FETCH GALLERY MEDIA
    $mediaStmt = $pdo->prepare("SELECT media_path, media_type FROM post_media WHERE post_id = ? ORDER BY id ASC");
    $mediaStmt->execute([$postId]);
    $galleryMedia = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

    $sliderMedia = [];
    if (!empty($post['media_path'])) {
        $sliderMedia[] = ['path' => $post['media_path'], 'type' => $post['media_type']];
    }
    foreach ($galleryMedia as $gm) {
        if ($gm['media_path'] !== $post['media_path']) {
            $sliderMedia[] = ['path' => $gm['media_path'], 'type' => $gm['media_type']];
        }
    }
    $mediaCount = count($sliderMedia);
?>
<?php endforeach; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AutoLog | Community</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');
        
        :root {
            --reddit-bg: #030303;
            --reddit-card: #1A1A1B;
            --reddit-border: #2D2D2E;
            --reddit-text: #D7DADC;
            --reddit-text-dim: #818384;
            --reddit-hover: #272729;
        }

        body { font-family: 'IBM Plex Sans', sans-serif; background-color: var(--reddit-bg); color: var(--reddit-text); overflow-x: hidden; }

        .post-card { 
            background-color: var(--reddit-card); 
            border: 1px solid var(--reddit-border);
            border-radius: 20px;
            overflow: hidden;
        }
        
        .media-container { 
            width: 94%; margin: 0 auto; aspect-ratio: 4 / 5; 
            background: #000; overflow: hidden; border-radius: 14px; position: relative;
        }
        
        .media-container video, .media-container img { width: 100%; height: 100%; object-fit: cover; }

        .bottom-nav {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            width: 90%; max-width: 400px;
            background: rgba(26, 26, 27, 0.9);
            backdrop-filter: blur(15px);
            border: 1px solid var(--reddit-border);
            border-radius: 30px;
            display: flex; justify-content: space-around; align-items: center;
            padding: 10px; z-index: 1000;
        }

        .nav-item { color: var(--reddit-text-dim); padding: 10px; border-radius: 20px; }
        .nav-item.active { color: #fff; background: var(--reddit-hover); }
        .plus-btn { background: #3b82f6; color: white !important; border-radius: 50%; padding: 12px; }

        .comment-section-container { max-height: 500px; display: flex; flex-direction: column; }
        .comment-list { overflow-y: auto; flex-grow: 1; scroll-behavior: smooth; }
        .comment-list::-webkit-scrollbar { width: 4px; }
        .comment-list::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }

        .modal-overlay { 
            display: none; position: fixed; inset: 0; z-index: 2000; 
            background: rgba(0,0,0,0.9); align-items: center; justify-content: center; padding: 20px;
        }
    </style>
</head>

<body class="pb-32">

<div class="bottom-nav">
    <a href="index.php" class="nav-item <?= $filter === 'all' ? 'active' : '' ?>"><i data-lucide="home"></i></a>
    <a href="index.php?view=following" class="nav-item <?= $filter === 'following' ? 'active' : '' ?>"><i data-lucide="users"></i></a>
    <button onclick="togglePostModal()" class="nav-item plus-btn"><i data-lucide="plus"></i></button>
    <a href="#" class="nav-item"><i data-lucide="search"></i></a>
    <a href="profile.php?u=<?= $_SESSION['user_handle'] ?? '' ?>" class="nav-item"><i data-lucide="user"></i></a>
</div>

<div id="fullscreen-modal" class="modal-overlay" onclick="closeFullscreen()">
    <div id="fullscreen-content" class="w-full h-full flex items-center justify-center" onclick="event.stopPropagation()"></div>
</div>

<!DOCTYPE html>
<html lang="en">
<head>
    </head>
<body class="bg-[#0a0a0b] text-white">

<div id="post-modal" class="modal-overlay" style="display: none;" onclick="togglePostModal()">
    <div class="post-card w-full max-w-lg p-0 relative overflow-hidden bg-[#0a0a0b] border border-white/10 rounded-3xl shadow-2xl" onclick="event.stopPropagation()">
        
        <div class="p-4 border-b border-white/5 flex justify-between items-center bg-white/5">
            <h2 class="text-[11px] font-black uppercase tracking-[0.4em] text-blue-500">Create New Post</h2>
            <button onclick="togglePostModal()" class="text-gray-400 hover:text-white transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form action="process_new_post.php" method="POST" enctype="multipart/form-data">
            
            <div id="preview-grid-container" class="hidden grid grid-cols-3 gap-0.5 bg-black max-h-[300px] overflow-y-auto border-b border-white/5">
                </div>

            <div class="p-5">
                <textarea name="content" 
                    class="w-full bg-transparent border-none text-white text-sm focus:ring-0 placeholder:text-white/20 resize-none mb-4" 
                    placeholder="Share your update with the community..." rows="4" required></textarea>
                
                <div class="flex justify-between items-center pt-2">
                    <div class="flex items-center gap-4">
                        <label class="group cursor-pointer flex items-center gap-2 bg-white/5 px-4 py-2 rounded-xl border border-white/5 hover:bg-white/10 transition-all">
                            <input type="file" id="multi-media-input" name="post_media[]" class="hidden" multiple accept="image/*,video/*" onchange="previewFiles(event)">
                            <i data-lucide="image" class="w-5 h-5 text-blue-500 group-hover:scale-110 transition-transform"></i>
                            <span class="text-[9px] font-black uppercase tracking-widest text-white/70">Gallery</span>
                        </label>
                    </div>

                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-10 py-2.5 rounded-2xl text-[11px] font-black uppercase tracking-widest shadow-lg transition-all active:scale-95">
                        Post
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="max-w-xl mx-auto px-4 mt-6">
    <div class="mb-6 relative">
        <form action="index.php" method="GET" class="relative">
            <input type="hidden" name="view" value="<?= htmlspecialchars($filter) ?>">
            <i data-lucide="search" class="w-4 h-4 text-gray-500 absolute left-4 top-1/2 -translate-y-1/2"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search_query ?? '') ?>" 
                   placeholder="Search posts, users, or handles..." 
                   class="w-full bg-[#1A1A1B] border border-[#2D2D2E] rounded-2xl py-3 pl-11 pr-4 text-sm text-[#D7DADC] focus:ring-1 focus:ring-blue-500 outline-none transition shadow-lg">
            <?php if ($search_query): ?>
                <a href="index.php?view=<?= $filter ?>" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white">
                    <i data-lucide="circle-x" class="w-4 h-4"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="mb-8">
        <div class="flex items-center gap-2 mb-4 px-1">
            <i data-lucide="trending-up" class="w-4 h-4 text-orange-500"></i>
            <h3 class="text-[11px] font-black text-[#818384] uppercase tracking-widest">Trending Now</h3>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if (!empty($trendingTags)): ?>
                <?php foreach ($trendingTags as $tag => $count): ?>
                    <a href="index.php?tag=<?= urlencode($tag) ?>&view=<?= $filter ?>" 
                       class="flex items-center gap-2 bg-[#1A1A1B] border border-[#2D2D2E] hover:border-blue-500/50 px-3 py-1.5 rounded-full transition group">
                        <span class="text-xs text-gray-400 group-hover:text-blue-400 transition">#<?= htmlspecialchars($tag) ?></span>
                        <span class="text-[10px] bg-[#272729] text-gray-500 px-1.5 py-0.5 rounded-md"><?= $count ?></span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-[11px] text-gray-600 italic px-1">No recent hashtags found.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($posts)): ?>
        <div class="text-center py-20 bg-[#161617] rounded-3xl border border-[#2D2D2E]">
            <i data-lucide="search-x" class="w-12 h-12 text-gray-600 mx-auto mb-4"></i>
            <p class="text-gray-400 text-sm font-medium">No results found.</p>
            <a href="index.php?view=<?= $filter ?>" class="text-blue-500 text-xs mt-2 inline-block font-bold">Clear all filters</a>
        </div>
    <?php else: ?>
       <?php foreach ($posts as $post): 
    // Reuse your existing Smart Avatar logic
    $rawPath = $post['profile_photo'];
    $role = $post['role'];
    $userName = $post['name'];
    $finalAvatar = get_user_avatar($rawPath, $role, $userName); // Assuming this helper exists or use your previous inline logic
    
    $postId = $post['id'];

    // 1. FETCH GALLERY MEDIA (The "Multi-media" part)
    $mediaStmt = $pdo->prepare("SELECT media_path, media_type FROM post_media WHERE post_id = ? ORDER BY id ASC");
    $mediaStmt->execute([$postId]);
    $galleryMedia = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

    $sliderMedia = [];
    // Include main post media if it exists
    if (!empty($post['media_path'])) {
        $sliderMedia[] = ['path' => $post['media_path'], 'type' => $post['media_type']];
    }
    // Include additional gallery items
    foreach ($galleryMedia as $gm) {
        if ($gm['media_path'] !== $post['media_path']) {
            $sliderMedia[] = ['path' => $gm['media_path'], 'type' => $gm['media_type']];
        }
    }
    $mediaCount = count($sliderMedia);
?>
    <div class="post-card mb-8 flex flex-col" data-post-id="<?= $postId ?>">
        <div class="p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="profile.php?u=<?= htmlspecialchars($post['handle']) ?>">
                    <img src="<?= $finalAvatar ?>" class="w-10 h-10 rounded-full bg-[#272729] object-cover border border-[#2D2D2E]">
                </a>
                <div>
                    <a href="profile.php?u=<?= htmlspecialchars($post['handle']) ?>" class="group">
                        <p class="font-bold text-sm text-[#D7DADC] group-hover:underline"><?= htmlspecialchars($post['name']) ?></p>
                        <div class="flex items-center gap-2">
                            <p class="text-[10px] text-blue-500 font-black uppercase tracking-wider"><?= strtoupper($post['role']) ?></p>
                            <span class="text-[10px] text-gray-500 font-medium italic lowercase">@<?= htmlspecialchars($post['handle']) ?></span>
                        </div>
                    </a>
                </div>
            </div>
            
            <div class="relative">
                <button onclick="togglePostOptions(<?= $postId ?>)" class="p-2 text-gray-500 hover:text-white rounded-full">
                    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                </button>
                <div id="options-<?= $postId ?>" class="hidden absolute right-0 mt-2 w-48 bg-[#1A1A1B] border border-[#2D2D2E] rounded-xl shadow-2xl z-50 overflow-hidden">
                    <?php if ($post['user_id'] == $current_user_id): ?>
                        <button onclick="deletePost(<?= $postId ?>)" class="w-full flex items-center gap-3 px-4 py-3 text-red-500 hover:bg-red-500/10 text-xs font-bold transition">
                            <i data-lucide="trash-2" class="w-4 h-4"></i> DELETE
                        </button>
                    <?php endif; ?>
                    <button class="w-full flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-white/5 text-xs font-bold transition">
                        <i data-lucide="share" class="w-4 h-4"></i> SHARE
                    </button>
                </div>
            </div>
        </div>

        <div class="px-5 pb-4">
            <p class="text-[15px] text-[#D7DADC]">
                <?= nl2br(linkify_tags(htmlspecialchars($post['content']))) ?>
            </p>
        </div>

        <?php if ($mediaCount > 0): ?>
            <div class="relative w-full overflow-hidden bg-black">
                <div class="flex w-full overflow-x-auto snap-x snap-mandatory no-scrollbar scroll-smooth" 
                     id="slider-<?= $postId ?>"
                     onscroll="handleSliderScroll(this, <?= $postId ?>)">
                    
                    <?php foreach ($sliderMedia as $idx => $m): 
                        $m_url = "../uploads/media/" . $m['path'];
                    ?>
                        <div class="min-w-full snap-center relative flex items-center justify-center aspect-[4/5] bg-black">
                            <?php if ($m['type'] === 'image'): ?>
                                <img src="<?= $m_url ?>" class="w-full h-full object-cover" onclick="openFullscreen('<?= $m_url ?>', 'image')">
                            <?php else: ?>
                                <video src="<?= $m_url ?>" class="w-full h-full object-cover feed-video" loop muted playsinline autoplay onclick="openFullscreen('<?= $m_url ?>', 'video')"></video>
                                <button onclick="toggleMute(this, event)" class="absolute bottom-4 right-4 bg-black/60 p-2 rounded-full text-white z-10">
                                    <i data-lucide="volume-x" class="w-4 h-4 mute-icon"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($mediaCount > 1): ?>
                    <div class="absolute top-4 right-4 z-10 bg-black/50 backdrop-blur-md px-2.5 py-1 rounded-full border border-white/10">
                        <span class="text-[10px] font-black text-white tracking-widest" id="counter-<?= $postId ?>">1 / <?= $mediaCount ?></span>
                    </div>

                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5 z-10" id="dots-<?= $postId ?>">
                        <?php for($i=0; $i < $mediaCount; $i++): ?>
                            <div class="w-1.5 h-1.5 rounded-full transition-all duration-300 <?= $i === 0 ? 'bg-blue-600 w-3' : 'bg-white/30' ?>"></div>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($post['post_type'] === 'listing_share' && !empty($post['listing_id'])): ?>
            <div class="mx-4 my-2 rounded-2xl overflow-hidden border border-[#2D2D2E] bg-[#1A1A1B] cursor-pointer" 
                 onclick="window.location.href='../dealer/car_details.php?id=<?= $post['listing_id'] ?>'">
                <img src="../<?= htmlspecialchars($post['listing_image']) ?>" class="w-full aspect-video object-cover">
                <div class="p-3">
                    <h5 class="text-xs font-black text-white uppercase"><?= $post['year'] ?> <?= htmlspecialchars($post['make']) ?></h5>
                    <p class="text-blue-500 font-black text-sm">Ksh <?= number_format($post['price']) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="px-4 py-4 flex items-center gap-4">
            <div class="flex items-center bg-[#272729] rounded-full px-3 py-1 gap-2">
                <button onclick="handleLike(this, <?= $postId ?>, 'post')" class="<?= $post['user_liked'] ? 'text-orange-500' : 'text-gray-400' ?>">
                    <i data-lucide="arrow-big-up" class="w-5 h-5"></i>
                </button>
                <span class="text-sm font-bold count"><?= $post['like_count'] ?></span>
            </div>
          <button onclick="toggleComments(<?= $postId ?>)" class="flex items-center gap-2 text-sm text-gray-400 hover:text-white transition">
    <i data-lucide="message-circle" class="w-5 h-5"></i>
    <span><?= $post['comment_count'] ?? 0 ?> Discuss</span>
</button>



        </div>
        <div id="comments-container-<?= $postId ?>" class="hidden bg-[#161617] mx-4 mb-4 rounded-2xl border border-[#2D2D2E] overflow-hidden">
    <div class="comment-list p-4 space-y-4" id="list-<?= $postId ?>">
        </div>
    
    <div class="border-t border-[#2D2D2E] p-3 flex gap-2">
        <input id="input-<?= $postId ?>" type="text" 
               class="w-full bg-[#272729] border-none rounded-xl px-4 py-2 text-sm text-white focus:ring-1 focus:ring-blue-500" 
               placeholder="Write a comment...">
        <button onclick="submitComment(<?= $postId ?>)" class="bg-blue-600 text-white p-2 rounded-xl">
            <i data-lucide="send" class="w-4 h-4"></i>
        </button>
    </div>
</div>
    </div>
<?php endforeach; ?>

    <?php endif; ?>
</div>

<script src="autocomplete.js"></script>
<script>
/* ============================================================
   AUTOLOG COMMUNITY & DASHBOARD GLOBAL SCRIPT
   ============================================================ */

let activeReplyIds = {};
let feedLoaded = false;

document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    if (window.initAutocomplete) window.initAutocomplete();
    
    // Initialize observers for any videos already on the page
    initMediaObservers();
});

/**
 * MEDIA INITIALIZATION & AUTO-PLAY
 * Uses IntersectionObserver to play/pause videos as they enter/leave the viewport
 */
function initMediaObservers() {
    const feedVideos = document.querySelectorAll('.feed-video, .feed-video-player');
    
    const videoObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const video = entry.target;
            if (entry.isIntersecting) {
                video.muted = true; 
                const playPromise = video.play();
                if (playPromise !== undefined) {
                    playPromise.catch(() => { /* Autoplay was prevented */ });
                }
            } else {
                video.pause();
            }
        });
    }, { threshold: 0.4 });

    feedVideos.forEach(vid => videoObserver.observe(vid));

    // Mobile Autoplay Unlocker
    const unlockAutoplay = () => {
        feedVideos.forEach(video => { if (video.paused) video.play().catch(() => {}); });
        document.removeEventListener('touchstart', unlockAutoplay);
        document.removeEventListener('click', unlockAutoplay);
    };
    document.addEventListener('touchstart', unlockAutoplay, { passive: true });
    document.addEventListener('click', unlockAutoplay, { passive: true });
}

/**
 * SLIDER & CAROUSEL SYNCING
 * Handles numerical counters (1/4) and pagination dots for multi-media posts
 */
function handleSliderScroll(el, postId) {
    const scrollLeft = el.scrollLeft;
    const width = el.clientWidth;
    const index = Math.round(scrollLeft / width);
    const total = el.children.length;

    // Update Numerical Counter
    const counter = document.getElementById(`counter-${postId}`);
    if (counter) counter.innerText = `${index + 1} / ${total}`;

    // Update Dots
    const dotsContainer = document.getElementById(`dots-${postId}`);
    if (dotsContainer) {
        const dots = dotsContainer.querySelectorAll('div');
        dots.forEach((dot, i) => {
            if (i === index) {
                dot.className = 'w-3 h-1.5 rounded-full transition-all duration-300 bg-blue-600';
            } else {
                dot.className = 'w-1.5 h-1.5 rounded-full transition-all duration-300 bg-slate-200/40';
            }
        });
    }
}

/**
 * POST CREATION MODAL
 */
function togglePostModal() {
    const m = document.getElementById('post-modal');
    const grid = document.getElementById('preview-grid-container');
    const fileInput = document.getElementById('multi-media-input');

    const isOpening = m.style.display !== 'flex';
    m.style.display = isOpening ? 'flex' : 'none';

    if (isOpening) {
        if (window.initAutocomplete) setTimeout(window.initAutocomplete, 100);
    } else {
        grid.innerHTML = '';
        grid.classList.add('hidden');
        fileInput.value = '';
    }
}

/**
 * MULTI-FILE PREVIEW (Instagram Grid Style)
 */
function previewFiles(e) {
    const files = e.target.files;
    const gridContainer = document.getElementById('preview-grid-container');
    gridContainer.innerHTML = '';
    
    if (files.length > 0) {
        gridContainer.classList.remove('hidden');
        gridContainer.className = "grid grid-cols-3 gap-0.5 bg-black max-h-[300px] overflow-y-auto border-b border-white/5";
    } else {
        gridContainer.classList.add('hidden');
        return;
    }

    Array.from(files).forEach((file) => {
        const wrapper = document.createElement('div');
        wrapper.className = "relative aspect-square overflow-hidden group border-[0.5px] border-white/5 animate-in zoom-in-95 duration-300";
        const url = URL.createObjectURL(file);

        if (file.type.startsWith('image/')) {
            wrapper.innerHTML = `<img src="${url}" class="w-full h-full object-cover">`;
        } else {
            wrapper.innerHTML = `<video src="${url}" class="w-full h-full object-cover" muted loop onmouseover="this.play()" onmouseout="this.pause()"></video>
                                 <div class="absolute inset-0 flex items-center justify-center bg-black/20 pointer-events-none">
                                     <i data-lucide="play" class="w-5 h-5 text-white opacity-80"></i>
                                 </div>`;
        }
        gridContainer.appendChild(wrapper);
    });
    lucide.createIcons();
}

/**
 * LIKES & SOCIAL INTERACTIONS
 */
async function handleLike(btn, id, type) {
    const res = await fetch('ajax_like.php', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ content_id: id, type: type }) 
    });
    const data = await res.json();
    if (data.success) {
        const countEl = btn.parentElement.querySelector('.count') || btn.querySelector('span');
        if(countEl) countEl.innerText = data.new_count;
        
        const icon = btn.querySelector('i, svg');
        if (data.action === 'liked') {
            icon.classList.add('fill-orange-500', 'text-orange-500');
            icon.classList.remove('text-gray-400', 'text-slate-300');
        } else {
            icon.classList.remove('fill-orange-500', 'text-orange-500');
            icon.classList.add('text-gray-400', 'text-slate-300');
        }
        if (navigator.vibrate) navigator.vibrate(10);
    }
}

/**
 * FULLSCREEN MEDIA PREVIEW (With Drag-to-Dismiss)
 */
function openFullscreen(src, type) {
    const modal = document.createElement('div');
    modal.id = 'media-preview-modal';
    modal.className = 'fixed inset-0 z-[10000] bg-black flex flex-col items-center justify-center select-none touch-none animate-in fade-in duration-200';
    
    const mediaContent = type === 'image' 
        ? `<img src="${src}" id="preview-media" class="max-w-full max-h-full object-contain shadow-2xl transition-transform duration-200 ease-out">`
        : `<video src="${src}" id="preview-media" class="max-w-full max-h-full" controls autoplay playsinline></video>`;

    modal.innerHTML = `
        <button class="absolute top-6 right-6 z-[10001] bg-white/10 p-2 rounded-full text-white backdrop-blur-md" onclick="closeFullscreen()">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>
        <div class="w-full h-full flex items-center justify-center overflow-hidden" id="preview-drag-area">${mediaContent}</div>`;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden'; 
    lucide.createIcons();

    const mediaObj = document.getElementById('preview-media');
    let startX = 0, startY = 0, currentX = 0, currentY = 0;

    modal.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        mediaObj.style.transition = 'none';
    }, {passive: true});

    modal.addEventListener('touchmove', (e) => {
        currentX = e.touches[0].clientX - startX;
        currentY = e.touches[0].clientY - startY;
        const distance = Math.sqrt(currentX * currentX + currentY * currentY);
        const opacity = Math.max(0, 1 - (distance / 400));
        modal.style.backgroundColor = `rgba(0,0,0, ${opacity})`;
        mediaObj.style.transform = `translate(${currentX}px, ${currentY}px) scale(${1 - (distance / 2000)})`;
    }, {passive: true});

    modal.addEventListener('touchend', () => {
        if (Math.sqrt(currentX * currentX + currentY * currentY) > 100) {
            closeFullscreen();
        } else {
            mediaObj.style.transition = 'transform 0.3s cubic-bezier(0.2, 0, 0.2, 1)';
            modal.style.backgroundColor = 'black';
            mediaObj.style.transform = 'translate(0, 0) scale(1)';
        }
    });
}

function closeFullscreen() { 
    const modal = document.getElementById('media-preview-modal');
    if (modal) {
        modal.classList.replace('fade-in', 'fade-out');
        setTimeout(() => { modal.remove(); document.body.style.overflow = ''; }, 200);
    }
}

/**
 * COMMENTS & OPTIONS DROPDOWNS
 */
function togglePostOptions(postId) {
    document.querySelectorAll('[id^="options-"]').forEach(el => {
        if (el.id !== `options-${postId}`) el.classList.add('hidden');
    });
    const menu = document.getElementById(`options-${postId}`);
    menu.classList.toggle('hidden');
}

/**
 * Toggles the visibility of the comment section and loads content
 */
async function toggleComments(postId) {
    // The ID must match the HTML container
    const container = document.getElementById(`comments-container-${postId}`);
    
    if (!container) {
        console.error(`Comment container not found for ID: comments-container-${postId}`);
        return;
    }

    // Toggle visibility
    const isHidden = container.classList.contains('hidden');
    
    if (isHidden) {
        container.classList.remove('hidden');
        // Show a small loader while fetching
        const list = document.getElementById(`list-${postId}`);
        if (list && list.innerHTML.trim() === "") {
            list.innerHTML = '<div class="py-4 text-center opacity-50 text-xs">Loading discussion...</div>';
            await loadComments(postId);
        }
    } else {
        container.classList.add('hidden');
    }
}

/**
 * Fetches comments from the server
 */
async function loadComments(postId, showAll = false) {
    const list = document.getElementById(`list-${postId}`);
    if (!list) return;

    try {
        const response = await fetch(`get_comments.php?post_id=${postId}${showAll ? '&view_all=true' : ''}`);
        const html = await response.text();
        list.innerHTML = html;
        
        // Re-initialize icons for the newly loaded comments
        if (window.lucide) lucide.createIcons();
    } catch (error) {
        console.error("Error loading comments:", error);
        list.innerHTML = '<div class="py-4 text-center text-red-500 text-xs">Failed to load comments.</div>';
    }
}

function submitComment(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const postId = formData.get('post_id');

    fetch('feeds/process_comment_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            form.reset();
            cancelReply();
            fetchComments(postId);
            if(typeof loadProfilePosts === "function") loadProfilePosts(<?= $dealer_user_id ?>);
        }
    });
}

function prepareReply(handle, commentId) {
    const indicator = document.getElementById('replyIndicator');
    const input = document.getElementById('modalParentId');
    const text = document.getElementById('commentText');
    const handleSpan = indicator.querySelector('span');
    input.value = commentId;
    handleSpan.innerText = `Replying to @${handle}`;
    indicator.classList.remove('hidden');
    text.placeholder = `Reply to @${handle}...`;
    text.focus();
}

function cancelReply() {
    const indicator = document.getElementById('replyIndicator');
    if(indicator) {
        document.getElementById('modalParentId').value = "";
        indicator.classList.add('hidden');
        document.getElementById('commentText').placeholder = "What are your thoughts?";
    }
}

/**
 * MENU & OVERLAY LOGIC
 */
function toggleCommentMenu(e, id) {
    e.stopPropagation();
    document.querySelectorAll('[id^="dropdown-"]').forEach(menu => {
        if (menu.id !== `dropdown-${id}`) menu.classList.add('hidden');
    });
    const menu = document.getElementById(`dropdown-${id}`);
    if (menu) menu.classList.toggle('hidden');
}

function processCommentDelete(commentId) {
    const formData = new FormData();
    formData.append('comment_id', commentId);
    fetch('../feeds/comment_delete.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const el = document.getElementById(`comment-wrapper-${commentId}`);
            if (el) {
                el.style.opacity = '0';
                el.style.transform = 'scale(0.95)';
                setTimeout(() => el.remove(), 300);
            }
        }
    });
}
/**
 * GLOBAL EVENT LISTENERS
 */
document.addEventListener('click', (e) => {
    if (!e.target.closest('.relative')) {
        document.querySelectorAll('[id^="options-"], [id^="dropdown-"]').forEach(menu => menu.classList.add('hidden'));
    }
});

document.addEventListener('keydown', (e) => { 
    if (e.key === "Escape") closeFullscreen(); 
});
</script>

</body>
</html>
