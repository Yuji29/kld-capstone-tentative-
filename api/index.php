<?php
// api/index.php - Entry point for Vercel

// Get the requested path
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Remove query string if present
if (strpos($request_uri, '?') !== false) {
    $request_uri = substr($request_uri, 0, strpos($request_uri, '?'));
}

// Route to the appropriate file
if ($request_uri == '/' || $request_uri == '/index.php') {
    include __DIR__ . '/../index.php';
}
elseif (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/', $request_uri)) {
    // Serve static files
    $file = __DIR__ . '/..' . $request_uri;
    if (file_exists($file)) {
        $mime_types = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml'
        ];
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (isset($mime_types[$ext])) {
            header('Content-Type: ' . $mime_types[$ext]);
        }
        readfile($file);
        exit;
    }
}
elseif (strpos($request_uri, '/admin/') === 0) {
    $file = __DIR__ . '/../admin/' . basename($request_uri);
    if (file_exists($file)) {
        include $file;
    } else {
        http_response_code(404);
        echo "Page not found";
    }
}
elseif (strpos($request_uri, '/adviser/') === 0) {
    $file = __DIR__ . '/../adviser/' . basename($request_uri);
    if (file_exists($file)) {
        include $file;
    } else {
        http_response_code(404);
        echo "Page not found";
    }
}
elseif (strpos($request_uri, '/auth/') === 0) {
    $file = __DIR__ . '/../auth/' . basename($request_uri);
    if (file_exists($file)) {
        include $file;
    } else {
        http_response_code(404);
        echo "Page not found";
    }
}
elseif (strpos($request_uri, '/titles/') === 0) {
    $file = __DIR__ . '/../titles/' . basename($request_uri);
    if (file_exists($file)) {
        include $file;
    } else {
        http_response_code(404);
        echo "Page not found";
    }
}
else {
    // Try to serve as PHP file
    $file = __DIR__ . '/..' . $request_uri . '.php';
    if (file_exists($file)) {
        include $file;
    } else {
        include __DIR__ . '/../index.php';
    }
}
?>