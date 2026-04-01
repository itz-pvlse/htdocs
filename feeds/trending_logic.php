<?php
// feeds/trending_logic.php

function getTrendingTags($pdo, $limit = 5) {
    // This query finds all hashtags in posts, counts them, and returns the top ones
    // Note: For a very large database, you would ideally have a separate 'tags' table
    // but this regex approach works perfectly for starting out.
    
    $stmt = $pdo->query("SELECT content FROM posts ORDER BY created_at DESC LIMIT 100");
    $posts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $tags = [];
    foreach ($posts as $content) {
        preg_match_all('/#(\w+)/', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $tag) {
                $tag = strtolower($tag);
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }
        }
    }
    
    arsort($tags); // Sort by count descending
    return array_slice($tags, 0, $limit, true);
}
