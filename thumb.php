<?php

// Basic configuration
$thumbsDir = __DIR__ . '/thumbs';
$uploadsDir = __DIR__ . '/uploads';

// Ensure thumbs directory exists
if (!is_dir($thumbsDir)) {
    if (!mkdir($thumbsDir, 0755, true)) {
        header('HTTP/1.1 500 Internal Server Error');
        die('Could not create thumbs directory.');
    }
}

// Get parameters
$src = $_GET['src'] ?? '';
$w = (int)($_GET['w'] ?? 0);
$h = (int)($_GET['h'] ?? 0);
$fit = $_GET['fit'] ?? 'cover';

// Validate path
$cleanSrc = ltrim($src, '/');
if (strpos($cleanSrc, 'uploads/') === 0) {
    $cleanSrc = substr($cleanSrc, 8); // Remove 'uploads/' prefix
}
$srcPath = realpath($uploadsDir . '/' . $cleanSrc);

if (!$srcPath || strpos($srcPath, realpath($uploadsDir)) !== 0 || !file_exists($srcPath)) {
    header('HTTP/1.1 404 Not Found');
    die('Image not found or invalid path.');
}

// Generate cache filename
$ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
$hash = md5($src . $w . $h . $fit . filemtime($srcPath));
$cacheFile = $thumbsDir . '/' . $hash . '.' . $ext;

// Browser Caching
$etag = '"' . $hash . '"';
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=31536000'); // 1 year
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

// Check cache
if (file_exists($cacheFile)) {
    $mime = mime_content_type($cacheFile);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($cacheFile));
    readfile($cacheFile);
    exit;
}

// Check if GD is available
if (!extension_loaded('gd')) {
    header('HTTP/1.1 501 Not Implemented');
    die('GD library is missing.');
}

// Load image
$sourceImage = null;
switch ($ext) {
    case 'jpg':
    case 'jpeg':
        $sourceImage = @imagecreatefromjpeg($srcPath);
        break;
    case 'png':
        $sourceImage = @imagecreatefrompng($srcPath);
        break;
    case 'gif':
        $sourceImage = @imagecreatefromgif($srcPath);
        break;
    case 'webp':
        $sourceImage = @imagecreatefromwebp($srcPath);
        break;
}

if (!$sourceImage) {
    header('HTTP/1.1 415 Unsupported Media Type');
    die('Unsupported image type or corrupted file.');
}

$origW = imagesx($sourceImage);
$origH = imagesy($sourceImage);

// Calculate new dimensions
if ($w <= 0 && $h <= 0) {
    $newW = $origW;
    $newH = $origH;
} elseif ($w > 0 && $h <= 0) {
    $newW = $w;
    $newH = (int)($origH * ($w / $origW));
} elseif ($w <= 0 && $h > 0) {
    $newW = (int)($origW * ($h / $origH));
    $newH = $h;
} else {
    // Both w and h defined
    if ($fit === 'cover') {
        // Crop to fit
        $srcRatio = $origW / $origH;
        $dstRatio = $w / $h;

        if ($srcRatio > $dstRatio) {
            // Source is wider than dest
            $tempH = $h;
            $tempW = (int)($h * $srcRatio);
        } else {
            // Source is taller than dest
            $tempW = $w;
            $tempH = (int)($w / $srcRatio);
        }
    } else {
        // Contain
         $ratio = min($w / $origW, $h / $origH);
         $newW = (int)($origW * $ratio);
         $newH = (int)($origH * $ratio);
    }
}

// Create new image
if ($fit === 'cover' && $w > 0 && $h > 0) {
    // For cover, we resize then crop
    // Wait, the logic above for cover needs to be mapped to crop params
    // Let's redo cover logic simply

    $destImage = imagecreatetruecolor($w, $h);

    // Preserve transparency
    if ($ext == 'png' || $ext == 'webp' || $ext == 'gif') {
        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
        $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
        imagefilledrectangle($destImage, 0, 0, $w, $h, $transparent);
    }

    $srcRatio = $origW / $origH;
    $dstRatio = $w / $h;

    $srcX = 0;
    $srcY = 0;
    $srcW = $origW;
    $srcH = $origH;

    if ($srcRatio > $dstRatio) {
        // Source is wider, crop left/right
        $srcW = (int)($origH * $dstRatio);
        $srcX = (int)(($origW - $srcW) / 2);
    } else {
        // Source is taller, crop top/bottom
        $srcH = (int)($origW / $dstRatio);
        $srcY = (int)(($origH - $srcH) / 2);
    }

    imagecopyresampled($destImage, $sourceImage, 0, 0, $srcX, $srcY, $w, $h, $srcW, $srcH);

} else {
    // Contain or only one dimension
    $destImage = imagecreatetruecolor($newW, $newH);

    // Preserve transparency
    if ($ext == 'png' || $ext == 'webp' || $ext == 'gif') {
        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
        $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
        imagefilledrectangle($destImage, 0, 0, $newW, $newH, $transparent);
    }

    imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
}

// Save to cache
switch ($ext) {
    case 'jpg':
    case 'jpeg':
        imagejpeg($destImage, $cacheFile, 85);
        break;
    case 'png':
        imagepng($destImage, $cacheFile);
        break;
    case 'gif':
        imagegif($destImage, $cacheFile);
        break;
    case 'webp':
        imagewebp($destImage, $cacheFile, 85);
        break;
}

// Cleanup
imagedestroy($sourceImage);
imagedestroy($destImage);

// Output
$mime = mime_content_type($cacheFile);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($cacheFile));
readfile($cacheFile);
