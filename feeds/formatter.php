<?php
// feeds/formatter.php

function linkify_tags($text) {
    // 1. Sanitize input to prevent XSS
    $text = htmlspecialchars($text);

    // 2. Mentions: @handle -> profile.php?u=handle
    // (\w+) matches a-z, A-Z, 0-9, and _
    $text = preg_replace(
        '/@(\w+)/', 
        '<a href="profile.php?u=$1" class="text-blue-400 font-bold hover:underline">@$1</a>', 
        $text
    );

    // 3. Hashtags: #topic -> index.php?tag=topic
    $text = preg_replace(
        '/#(\w+)/', 
        '<a href="index.php?tag=$1" class="text-orange-400 font-bold hover:underline">#$1</a>', 
        $text
    );

    // 4. Preserve line breaks
    return nl2br($text);
}
