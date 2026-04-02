<?php
// ============================================
// AUTO-CREATE DATABASE CONFIGURATION
// ============================================

$config_file = __DIR__ . '/../config/database.php';
if (!file_exists($config_file)) {
    $db_config = '<?php
class Database {
    private $host = getenv("DB_HOST") ?: "localhost";
    private $db_name = getenv("DB_NAME") ?: "defaultdb";
    private $username = getenv("DB_USER") ?: "avnadmin";
    private $password = getenv("DB_PASSWORD") ?: "";
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch(PDOException $e) {
            error_log("Connection error: " . $e->getMessage());
        }
        return $this->conn;
    }
}
?>';
    file_put_contents($config_file, $db_config);
}

// ============================================
// VERIFY ENVIRONMENT VARIABLES ARE SET
// ============================================

$required_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
$missing_vars = [];
foreach ($required_vars as $var) {
    if (!getenv($var)) {
        $missing_vars[] = $var;
    }
}

if (!empty($missing_vars)) {
    error_log("Missing environment variables: " . implode(', ', $missing_vars));
}

// ============================================
// ROUTING - SERVE STATIC FILES AND PHP PAGES
// ============================================

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

// ============================================
// SERVE STATIC FILES (CSS, JS, IMAGES)
// ============================================

$static_extensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot'];
$path_parts = pathinfo($path);
$ext = isset($path_parts['extension']) ? strtolower($path_parts['extension']) : '';

if (in_array($ext, $static_extensions)) {
    $static_file = __DIR__ . '/../' . $path;
    if (file_exists($static_file) && !is_dir($static_file)) {
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
        readfile($static_file);
        exit;
    }
}

// ============================================
// SERVE PHP FILES FROM ROOT
// ============================================

$root_file = __DIR__ . '/../' . $path;
if (file_exists($root_file) && !is_dir($root_file) && $ext == 'php') {
    include $root_file;
    exit;
}

// Try with .php extension
$root_file_php = __DIR__ . '/../' . $path . '.php';
if (file_exists($root_file_php) && !is_dir($root_file_php)) {
    include $root_file_php;
    exit;
}

// ============================================
// SERVE PHP FILES FROM SUBDIRECTORIES
// ============================================

$subdirs = ['admin', 'adviser', 'auth', 'titles'];
foreach ($subdirs as $subdir) {
    $sub_file = __DIR__ . '/../' . $subdir . '/' . basename($path);
    if (file_exists($sub_file) && !is_dir($sub_file)) {
        include $sub_file;
        exit;
    }
    
    // Try with .php extension
    $sub_file_php = __DIR__ . '/../' . $subdir . '/' . basename($path) . '.php';
    if (file_exists($sub_file_php) && !is_dir($sub_file_php)) {
        include $sub_file_php;
        exit;
    }
}

// ============================================
// FALLBACK - SERVE INDEX.PHP (SPA MODE)
// ============================================

include __DIR__ . '/../index.php';
?>