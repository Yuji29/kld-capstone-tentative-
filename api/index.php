<?php
// api/index.php - Entry point for Vercel

// Get the requested path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove leading slash
$path = ltrim($path, '/');

// If empty, serve index.php
if (empty($path) || $path == '') {
    include __DIR__ . '/../index.php';
    exit;
}

// Check if file exists in root directory
$root_file = __DIR__ . '/../' . $path;
if (file_exists($root_file) && !is_dir($root_file)) {
    $ext = pathinfo($root_file, PATHINFO_EXTENSION);
    
    // Serve PHP files directly
    if ($ext == 'php') {
        include $root_file;
        exit;
    }
    
    // Serve static files with correct mime types
    $mime_types = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2'
    ];
    
    if (isset($mime_types[$ext])) {
        header('Content-Type: ' . $mime_types[$ext]);
    }
    readfile($root_file);
    exit;
}

// Check subdirectories
$subdirs = ['admin', 'adviser', 'auth', 'titles'];
foreach ($subdirs as $subdir) {
    $sub_file = __DIR__ . '/../' . $subdir . '/' . basename($path);
    if (file_exists($sub_file) && !is_dir($sub_file)) {
        include $sub_file;
        exit;
    }
}

// Fallback to index.php
include __DIR__ . '/../index.php';
?>