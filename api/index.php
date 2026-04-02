<?php
// api/index.php
// Forward all requests to the appropriate PHP file

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove leading slash
$path = ltrim($path, '/');

// Handle root path
if ($path === '' || $path === 'index.php') {
    include __DIR__ . '/../index.php';
    exit;
}

// Handle static files
$static_extensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg'];
$ext = pathinfo($path, PATHINFO_EXTENSION);

if (in_array($ext, $static_extensions)) {
    $file = __DIR__ . '/../' . $path;
    if (file_exists($file)) {
        $mime = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml'
        ];
        header('Content-Type: ' . ($mime[$ext] ?? 'text/plain'));
        readfile($file);
        exit;
    }
}

// Handle PHP files
$php_file = __DIR__ . '/../' . $path;
if (file_exists($php_file) && pathinfo($php_file, PATHINFO_EXTENSION) === 'php') {
    include $php_file;
    exit;
}

// Handle .php extension missing
$php_file_ext = __DIR__ . '/../' . $path . '.php';
if (file_exists($php_file_ext)) {
    include $php_file_ext;
    exit;
}

// Handle subdirectories
$subdirs = ['admin', 'adviser', 'auth', 'titles'];
foreach ($subdirs as $subdir) {
    $sub_file = __DIR__ . '/../' . $subdir . '/' . $path;
    if (file_exists($sub_file)) {
        include $sub_file;
        exit;
    }
    $sub_file_php = __DIR__ . '/../' . $subdir . '/' . $path . '.php';
    if (file_exists($sub_file_php)) {
        include $sub_file_php;
        exit;
    }
}

// Fallback to index.php
include __DIR__ . '/../index.php';