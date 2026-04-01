<?php
require_once '../config/db.php';
session_start();

// Ensure the garage is logged in
if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$garage_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'saved';

// 1. Updated query to include u.id so we can link to their profile
$query = "SELECT u.id, u.name, u.avatar 
          FROM users u 
          JOIN user_garages ug ON u.id = ug.user_id 
          WHERE ug.garage_id = ?";

if($type === 'preferred') { 
    $query .= " AND ug.is_preferred = 1"; 
}

$stmt = $pdo->prepare($query);
$stmt->execute([$garage_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if(empty($users)) {
    echo '<div class="flex flex-col items-center justify-center py-12 text-slate-300">
            <i class="fa-solid fa-face-meh text-3xl mb-3"></i>
            <p class="text-[10px] font-black uppercase italic tracking-widest">No users found</p>
          </div>';
} else {
    foreach($users as $user) {
        // Use the relative path logic you provided earlier
        $avatar = !empty($user['avatar']) ? "../" . htmlspecialchars($user['avatar']) : 'https://ui-avatars.com/api/?name='.urlencode($user['name']).'&background=F1F5F9&color=64748b';
        
        // 2. Changed <div> to <a> and added the profile link
        echo '
        <a href="user_profile.php?id='.$user['id'].'" class="flex items-center gap-4 p-3 hover:bg-slate-50 rounded-2xl transition-all border border-transparent hover:border-slate-100 group">
            <div class="relative">
                <img src="'.$avatar.'" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm group-hover:scale-105 transition-transform">
                '.($type === 'preferred' ? '<div class="absolute -bottom-1 -right-1 w-4 h-4 bg-amber-500 border-2 border-white rounded-full flex items-center justify-center text-[6px] text-white"><i class="fa-solid fa-star"></i></div>' : '').'
            </div>
            
            <div class="flex flex-col">
                <span class="text-[11px] font-black uppercase tracking-tight text-slate-700 group-hover:text-indigo-600 transition-colors">'.htmlspecialchars($user['name']).'</span>
                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">View Driver Passport</span>
            </div>
            
            <div class="ml-auto flex items-center gap-2">
                <i class="fa-solid fa-chevron-right text-slate-200 group-hover:text-slate-400 text-[10px] group-hover:translate-x-1 transition-all"></i>
            </div>
        </a>';
    }
}
