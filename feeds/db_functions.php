<?php
// feeds/db_functions.php

/**
 * Toggles a like/upvote. 
 * Reusable for posts and comments.
 */
function toggleLike($pdo, $userId, $contentId, $contentType) {
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND content_id = ? AND content_type = ?");
    $stmt->execute([$userId, $contentId, $contentType]);
    $like = $stmt->fetch();

    if ($like) {
        $del = $pdo->prepare("DELETE FROM likes WHERE id = ?");
        $del->execute([$like['id']]);
        return ['action' => 'unliked', 'count_change' => -1];
    } else {
        $ins = $pdo->prepare("INSERT INTO likes (user_id, content_id, content_type) VALUES (?, ?, ?)");
        $ins->execute([$userId, $contentId, $contentType]);
        return ['action' => 'liked', 'count_change' => 1];
    }
}

/**
 * Gets the total like count for a specific item
 */
function getLikeCount($pdo, $contentId, $contentType) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE content_id = ? AND content_type = ?");
    $stmt->execute([$contentId, $contentType]);
    return $stmt->fetchColumn();
}

/**
 * UPDATED: Fetches comments with Unified Avatar Logic
 * Flattens profile pictures from users, dealers, and garages tables.
 */
function getCommentsByPost($pdo, $postId, $currentUserId) {
    $query = "SELECT c.*, u.name, u.role,
              -- Unified Avatar Logic: Checks car owners, then dealers, then garages
              COALESCE(u.profile_photo, d.logo, g.logo, 'default.png') AS unified_avatar,
              (SELECT COUNT(*) FROM likes WHERE content_id = c.id AND content_type = 'comment') as like_count,
              (SELECT COUNT(*) FROM likes WHERE content_id = c.id AND content_type = 'comment' AND user_id = ?) as user_liked
              FROM comments c 
              JOIN users u ON c.user_id = u.id 
              LEFT JOIN dealers d ON u.id = d.user_id
              LEFT JOIN garages g ON u.id = g.user_id
              WHERE c.post_id = ? 
              ORDER BY COALESCE(c.parent_id, c.id) ASC, c.created_at ASC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$currentUserId, $postId]);
    return $stmt->fetchAll();
}

/**
 * Toggles a follow relationship between users
 */
function toggleFollow($pdo, $followerId, $followingId) {
    if ($followerId == $followingId) return ['status' => 'self'];

    $stmt = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$followerId, $followingId]);
    $exists = $stmt->fetch();

    if ($exists) {
        $pdo->prepare("DELETE FROM follows WHERE id = ?")->execute([$exists['id']]);
        return ['status' => 'unfollowed'];
    } else {
        $pdo->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)")->execute([$followerId, $followingId]);
        return ['status' => 'followed'];
    }
}

/**
 * Checks if a user is following another
 */
function isFollowing($pdo, $followerId, $followingId) {
    $stmt = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$followerId, $followingId]);
    return (bool)$stmt->fetch();
}

/**
 * Gets the total number of comments for a post (including replies)
 */
function getCommentCount($pdo, $postId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ?");
    $stmt->execute([$postId]);
    return $stmt->fetchColumn();
}

/**
 * GET UNIFIED PROFILE IMAGE
 * Returns the full relative path to the user's profile image based on their role.
 */
function getProfileImage($user) {
    $basePath = "../uploads/";
    $role = strtolower($user['role']);
    
    // 1. Check if the image exists in the data provided
    $img = $user['profile_pic'] ?? '';

    if (empty($img) || $img == 'default.png') {
        return $basePath . "avatars/default.png";
    }

    // 2. Route to the correct folder based on role
    switch ($role) {
        case 'dealer':
            return $basePath . "dealers/" . $img;
        case 'garage':
            return $basePath . "garages/" . $img; // Assuming logo column is here
        case 'owner':
        default:
            return $basePath . "profiles/" . $img;
    }
}


function get_user_avatar($profile_photo, $role, $name) {
    $finalAvatar = "";

    if (!empty($profile_photo)) {
        // If it's already a full path
        if (str_contains($profile_photo, 'uploads/')) {
            $testPath = "../" . $profile_photo;
        } else {
            // Determine folder by role
            $folder = match($role) {
                'garage' => 'uploads/logo/',
                'dealer' => 'uploads/dealers/',
                default  => 'uploads/profiles/',
            };
            $testPath = "../" . $folder . $profile_photo;
        }

        if (file_exists($testPath)) {
            $finalAvatar = $testPath;
        }
    }

    // Fallback if file is missing or path is empty
    if (empty($finalAvatar)) {
        $finalAvatar = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=6366f1&color=fff&bold=true";
    }

    return $finalAvatar;
}

?>
