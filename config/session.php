<?php
// config/session.php
class Session {
    
    /**
     * Start the session if not already started
     */
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            // Set secure session parameters
            if (session_status() == PHP_SESSION_DISABLED) {
                throw new Exception("Sessions are disabled");
            }
            
            // Set session cookie parameters for better security
            if (php_sapi_name() !== 'cli') {
                session_set_cookie_params([
                    'lifetime' => 0, // Session cookie (until browser closes)
                    'path' => '/',
                    'domain' => '',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            
            session_start();
        }
        
        // Regenerate session ID periodically to prevent fixation
        if (!isset($_SESSION['last_regeneration'])) {
            self::regenerateSessionId();
        } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
            self::regenerateSessionId();
        }
    }
    
    /**
     * Regenerate session ID
     */
    private static function regenerateSessionId() {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Set a session value
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get a session value
     */
    public static function get($key, $default = null) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    /**
     * Delete a session key
     */
    public static function delete($key) {
        unset($_SESSION[$key]);
    }
    
    /**
     * Destroy the entire session
     */
    public static function destroy() {
        if (session_status() == PHP_SESSION_ACTIVE) {
            // Clear all session variables
            $_SESSION = array();
            
            // Delete the session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            
            // Destroy the session
            session_destroy();
        }
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    /**
     * Check if user is adviser
     */
    public static function isAdviser() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'adviser';
    }
    
    /**
     * Check if user is student
     */
    public static function isStudent() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
    }
    
    /**
     * Get user ID
     */
    public static function getUserId() {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
    
    /**
     * Get ID number
     */
    public static function getIdNumber() {
        return isset($_SESSION['id_number']) ? $_SESSION['id_number'] : null;
    }
    
    /**
     * Get full name
     */
    public static function getFullName() {
        return isset($_SESSION['full_name']) ? $_SESSION['full_name'] : null;
    }
    
    /**
     * Get role
     */
    public static function getRole() {
        return isset($_SESSION['role']) ? $_SESSION['role'] : null;
    }
    
    /**
     * Get all session data
     */
    public static function getAll() {
        return $_SESSION;
    }
    
    /**
     * Flash message - set a message that will be available on the next request only
     */
    public static function setFlash($key, $message) {
        $_SESSION['_flash'][$key] = $message;
    }
    
    /**
     * Get flash message and delete it
     */
    public static function getFlash($key) {
        if (isset($_SESSION['_flash'][$key])) {
            $message = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $message;
        }
        return null;
    }
    
    /**
     * Check if flash message exists
     */
    public static function hasFlash($key) {
        return isset($_SESSION['_flash'][$key]);
    }
}
?>