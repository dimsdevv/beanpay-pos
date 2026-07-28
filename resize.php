<?php
$source = imagecreatefromjpeg(__DIR__ . '/assets/images/logo.jpeg');
$width = imagesx($source);
$height = imagesy($source);

// 512x512
$img512 = imagecreatetruecolor(512, 512);
imagecopyresampled($img512, $source, 0, 0, 0, 0, 512, 512, $width, $height);
imagepng($img512, __DIR__ . '/assets/images/logo-512.png');

// 192x192
$img192 = imagecreatetruecolor(192, 192);
imagecopyresampled($img192, $source, 0, 0, 0, 0, 192, 192, $width, $height);
imagepng($img192, __DIR__ . '/assets/images/logo-192.png');

echo "Images resized successfully.\n";
?>
