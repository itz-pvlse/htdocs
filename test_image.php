<?php
$imgPath = 'assets/not_allowed.png';

// Check if file exists on server
if (file_exists($imgPath)) {
    echo "<p style='color:green;'>Image file exists on the server: $imgPath</p>";
} else {
    echo "<p style='color:red;'>Image file NOT found on the server: $imgPath</p>";
}

// Show full URL the browser tries to load
$fullUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
    . "://" . $_SERVER['HTTP_HOST'] . "/" . $imgPath;

echo "<p>Full URL being used: <a href='$fullUrl' target='_blank'>$fullUrl</a></p>";

// Optional: display the image
echo "<img src='$imgPath' alt='Debug Image' style='max-width:200px;'>";
?>
