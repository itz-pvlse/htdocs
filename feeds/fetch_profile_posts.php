<?php
// feeds/fetch_profile_posts.php
require_once '../config/db.php'; 
require_once 'formatter.php';    
require_once 'db_functions.php'; 
session_start();

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$current_session_id = $_SESSION['user_id'] ?? 0;

if ($user_id <= 0) {
    echo "<p class='text-center text-slate-500 py-10 text-[10px] font-black uppercase tracking-widest'>Invalid profile ID.</p>";
    exit;
}

try {
    // FETCH MAIN POSTS
    $stmt = $pdo->prepare("
        SELECT p.*, u.handle, u.name, u.role, u.profile_photo, u.is_verified,
        dl.make, dl.model, dl.price, dl.year, dl.main_image as listing_image, dl.vehicle_condition,
        (SELECT COUNT(*) FROM likes WHERE content_id = p.id AND content_type = 'post') as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
        (SELECT COUNT(*) FROM likes WHERE content_id = p.id AND content_type = 'post' AND user_id = ?) as user_liked
        FROM posts p 
        JOIN users u ON p.user_id = u.id
        LEFT JOIN dealer_listings dl ON p.listing_id = dl.id
        WHERE p.user_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$current_session_id, $user_id]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='p-4 bg-red-50 text-red-600 text-[10px] font-mono'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

/* ============================================================
   EMPTY STATE WITH TRENDING CAROUSEL
   ============================================================ */
if (empty($posts)) {
    $trendingStmt = $pdo->prepare("
        SELECT p.*, u.name as author_name, u.handle as author_handle, u.is_verified,
        dl.main_image as listing_image,
        (SELECT COUNT(*) FROM likes WHERE content_id = p.id AND content_type = 'post') as likes
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN dealer_listings dl ON p.listing_id = dl.id
        WHERE p.user_id != ?
        ORDER BY likes DESC LIMIT 5
    ");
    $trendingStmt->execute([$user_id]);
    $trending = $trendingStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "
    <div class='col-span-full flex flex-col items-center justify-center py-20 px-6 text-center'>
        <div class='relative mb-6'>
            <div class='absolute inset-0 bg-blue-500/10 blur-3xl rounded-full'></div>
            <div class='relative w-20 h-20 bg-white border border-slate-100 rounded-3xl flex items-center justify-center shadow-xl'>
                <i data-lucide='layers' class='w-8 h-8 text-slate-200'></i>
            </div>
        </div>
        <h3 class='text-slate-900 font-black text-sm uppercase tracking-widest mb-2'>No Posts Yet</h3>
        <p class='text-slate-400 text-[11px] font-medium max-w-[240px] leading-relaxed mb-8'>
            This dealer hasn't shared any updates. Explore what's happening in the community.
        </p>
        <a href='../feeds/index.php' class='group flex items-center gap-3 bg-slate-900 hover:bg-blue-600 text-white px-8 py-3.5 rounded-2xl transition-all duration-300 shadow-lg mb-20'>
            <span class='text-[10px] font-black uppercase tracking-[0.2em]'>Explore Community</span>
            <i data-lucide='arrow-right' class='w-4 h-4 group-hover:translate-x-1 transition-transform'></i>
        </a>
        <div class='w-full max-w-6xl mx-auto'>
            <div class='flex items-center justify-between mb-8 px-2'>
                <h4 class='text-[10px] font-black uppercase tracking-[0.3em] text-slate-400'>Trending Discussions</h4>
                <div class='h-px flex-1 bg-slate-100 ml-6'></div>
            </div>
            <div class='flex gap-5 overflow-x-auto pb-10 no-scrollbar snap-x touch-pan-x'>";
    foreach ($trending as $t) {
        $preview = htmlspecialchars(substr(strip_tags($t['content']), 0, 70)) . '...';
        $tMedia = !empty($t['listing_image']) ? "../" . $t['listing_image'] : (!empty($t['media_path']) ? "../uploads/media/" . $t['media_path'] : "");
        echo "
                <div class='min-w-[280px] md:min-w-[320px] snap-start bg-white border border-slate-100 p-4 rounded-[2.5rem] text-left shadow-sm hover:border-blue-200 transition-all duration-300 flex flex-col h-full'>
                    <div class='w-full h-32 rounded-[1.8rem] overflow-hidden mb-4 bg-slate-50 shrink-0'>
                        " . ($tMedia ? "<img src='{$tMedia}' class='w-full h-full object-cover'>" : "<div class='w-full h-full flex items-center justify-center opacity-20'><i data-lucide='message-circle' class='w-8 h-8 text-slate-400'></i></div>") . "
                    </div>
                    <div class='flex-grow px-2'>
                        <div class='flex items-center gap-3 mb-3'>
                            <div class='flex flex-col min-w-0'>
                                <h4 class='text-[10px] font-black text-slate-900 uppercase tracking-tight flex items-center gap-1'>
                                    <span class='truncate'>{$t['author_name']}</span>
                                    " . ($t['is_verified'] == 1 ? "<i data-lucide='badge-check' class='w-3 h-3 text-blue-500 fill-blue-500/10'></i>" : "") . "
                                </h4>
                                <span class='text-[8px] font-bold text-slate-400 truncate'>@{$t['author_handle']}</span>
                            </div>
                        </div>
                        <p class='text-[11px] text-slate-500 leading-relaxed mb-6 line-clamp-2 min-h-[2.5rem]'>{$preview}</p>
                    </div>
                    <div class='mt-auto flex items-center justify-between pt-4 border-t border-slate-50 px-2 shrink-0'>
                        <div class='flex items-center gap-1.5'>
                            <i data-lucide='arrow-big-up' class='w-3.5 h-3.5 text-orange-500 fill-orange-500'></i>
                            <span class='text-[10px] font-black text-slate-900'>{$t['likes']}</span>
                        </div>
                        <button onclick=\"window.location.href='../feeds/index.php?post_id={$t['id']}'\" class='text-[9px] font-black text-blue-600 uppercase tracking-widest py-1.5 px-4 bg-blue-50 rounded-xl hover:bg-blue-600 hover:text-white transition-colors'>Join Thread</button>
                    </div>
                </div>";
    }
    echo "</div></div></div>";
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
    <div class="bg-white rounded-[2.5rem] p-5 border border-slate-100 shadow-sm transition-all duration-300 post-card flex flex-col mb-6 w-full h-fit" data-post-id="<?= $postId ?>">
        
        <div class="flex items-center gap-3 mb-4 shrink-0">
            <img src="<?= $avatar ?>" class="w-10 h-10 rounded-2xl object-cover border border-slate-50 shadow-sm">
            <div class="flex-1 min-w-0">
                <h4 class="text-[11px] font-black text-slate-900 uppercase tracking-tight flex items-center gap-1">
                    <span class="truncate"><?= htmlspecialchars($post['name']) ?></span>
                    <?php if(isset($post['is_verified']) && $post['is_verified'] == 1): ?>
                        <i data-lucide="badge-check" class="w-3.5 h-3.5 text-blue-500 fill-blue-500/10"></i>
                    <?php endif; ?>
                </h4>
                <p class="text-[9px] text-slate-400 font-bold italic truncate">@<?= htmlspecialchars($post['handle']) ?></p>
            </div>
            <div class="text-right shrink-0">
                <span class="block text-[9px] font-black text-slate-300 uppercase tracking-tighter">
                    <?= date('M j, Y', strtotime($post['created_at'])) ?>
                </span>
            </div>
        </div>

        <div class="text-xs text-slate-600 leading-relaxed mb-4 px-1 line-clamp-3">
            <?= linkify_tags(nl2br(htmlspecialchars($post['content']))) ?>
        </div>

        <?php if ($mediaCount > 0): ?>
            <div class="relative w-full overflow-hidden mb-4 rounded-[1.8rem]">
                <div class="relative group -mx-5 sm:mx-0"> 
                    <div class="bg-[#0a0a0b] relative overflow-hidden flex items-center justify-center w-full" 
                         style="display: grid; place-items: center; aspect-ratio: auto; min-height: 200px; max-height: 500px;">
                        
                        <div class="flex w-full h-full overflow-x-auto snap-x snap-mandatory no-scrollbar scroll-smooth" 
                             onscroll="handleSliderScroll(this, <?= $postId ?>)">
                            <?php foreach ($sliderMedia as $idx => $m): 
                                $m_url = "../uploads/media/" . $m['path'];
                            ?>
                                <div class="min-w-full snap-center relative flex items-center justify-center cursor-zoom-in group/media overflow-hidden" 
                                     style="max-height: 500px;">
                                    <?php if ($m['type'] === 'image'): ?>
                                        <img src="<?= $m_url ?>" 
                                             class="w-full h-auto block object-cover" 
                                             style="max-height: 500px;"
                                             onclick="openMediaPreview('<?= $m_url ?>', 'image')">
                                    <?php else: ?>
                                        <video class="w-full h-auto block object-cover feed-video-player" 
                                               style="max-height: 500px;"
                                               muted loop playsinline preload="metadata" 
                                               onclick="openMediaPreview('<?= $m_url ?>', 'video')">
                                            <source src="<?= $m_url ?>" type="video/mp4">
                                        </video>
                                        <button onclick="toggleFeedVolume(this, event)" class="absolute bottom-3 right-3 z-20 bg-black/60 backdrop-blur-md p-2 rounded-full border border-white/10 text-white">
                                            <i data-lucide="volume-x" class="w-3.5 h-3.5 volume-icon"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($mediaCount > 1): ?>
                            <div class="absolute top-4 right-4 z-10 bg-black/50 backdrop-blur-md px-2.5 py-1 rounded-full border border-white/10">
                                <span class="text-[9px] font-black text-white tracking-widest" id="counter-<?= $postId ?>">1 / <?= $mediaCount ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($mediaCount > 1): ?>
                        <div class="flex justify-center gap-1.5 mt-3 slider-dots" id="dots-<?= $postId ?>">
                            <?php for($i=0; $i < $mediaCount; $i++): ?>
                                <div class="w-1.5 h-1.5 rounded-full transition-all duration-300 <?= $i === 0 ? 'bg-blue-600 w-3' : 'bg-slate-200' ?>"></div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($post['post_type'] === 'listing_share' && !empty($post['listing_id']) && $post['make']): ?>
            <div class="rounded-2xl overflow-hidden border border-slate-100 mb-4 bg-slate-50 shadow-sm group cursor-pointer" onclick="window.location.href='../dealer/car_details.php?id=<?= $post['listing_id'] ?>'">
                <div class="relative aspect-video overflow-hidden bg-slate-200">
                    <img src="../<?= htmlspecialchars($post['listing_image']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute top-3 left-3">
                        <span class="bg-white/90 backdrop-blur px-2 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest text-slate-900 shadow-sm border border-white/20">
                            Listing Repost
                        </span>
                    </div>
                </div>
                <div class="p-4">
                    <h5 class="text-[11px] font-black text-slate-900 uppercase truncate"><?= $post['year'] ?> <?= htmlspecialchars($post['make'] . ' ' . $post['model']) ?></h5>
                    <div class="flex items-center justify-between mt-1">
                        <p class="text-[13px] font-black text-blue-600">Ksh <?= number_format($post['price']) ?></p>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"><?= htmlspecialchars($post['vehicle_condition'] ?? 'Used') ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-between pt-4 border-t border-slate-50 mt-auto shrink-0 px-1">
            <div class="flex items-center gap-5">
                <button onclick="handleUpvote(<?= $postId ?>, this)" class="flex items-center gap-1.5 group transition-transform active:scale-95">
                    <i data-lucide="arrow-big-up" class="w-4 h-4 <?= $post['user_liked'] ? 'fill-orange-500 text-orange-500' : 'text-slate-300' ?>"></i>
                    <span class="text-[10px] font-black <?= $post['user_liked'] ? 'text-orange-500' : 'text-slate-400' ?>">
                        <?= $post['like_count'] ?>
                    </span>
                </button>
                <button onclick="openComments(<?= $postId ?>, '<?= htmlspecialchars(addslashes($post['name'])) ?>')" class="flex items-center gap-1.5 group active:scale-95">
                    <i data-lucide="message-square" class="w-4 h-4 text-slate-300"></i>
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">
                        <?= $post['comment_count'] ?: '0' ?> <span class="hidden sm:inline">Discuss</span>
                    </span>
                </button>
            </div>
            <button onclick="sharePost(<?= $postId ?>, 'Share', '<?= $post['handle'] ?>', <?= $user_id ?>)" class="flex items-center gap-2 text-slate-300 hover:text-blue-600">
                <i data-lucide="share-2" class="w-4 h-4"></i>
            </button>
        </div>
    </div>
<?php endforeach; ?>

<script>
function handleSliderScroll(el, postId) {
    const scrollLeft = el.scrollLeft;
    const width = el.clientWidth;
    const index = Math.round(scrollLeft / width);
    const total = el.children.length;

    const counter = document.getElementById(`counter-${postId}`);
    if (counter) counter.innerText = `${index + 1} / ${total}`;

    const dotsContainer = document.getElementById(`dots-${postId}`);
    if (dotsContainer) {
        const dots = dotsContainer.querySelectorAll('div');
        dots.forEach((dot, i) => {
            if (i === index) {
                dot.className = 'w-3 h-1.5 rounded-full transition-all duration-300 bg-blue-600';
            } else {
                dot.className = 'w-1.5 h-1.5 rounded-full transition-all duration-300 bg-slate-200';
            }
        });
    }
}
if (window.lucide) lucide.createIcons();
</script>
