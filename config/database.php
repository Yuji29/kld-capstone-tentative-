<?php
// config/database.php - For PostgreSQL on Render

class Database {
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        // Try different ways to get the database URL
        $database_url = getenv('DATABASE_URL');
        
        // If not found, try Render's specific variable
        if (!$database_url) {
            $database_url = getenv('RENDER_DATABASE_URL');
        }
        
        // For debugging - remove after it works
        error_log("DATABASE_URL exists: " . ($database_url ? "YES" : "NO"));
        
        if ($database_url) {
            try {
                $this->conn = new PDO($database_url);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                error_log("Database connected successfully!");
            } catch(PDOException $e) {
                error_log("Connection error: " . $e->getMessage());
                die("Database connection failed: " . $e->getMessage());
            }
        } else {
            error_log("DATABASE_URL environment variable not set!");
            die("Database configuration error. Please check logs.");
        }
        
        return $this->conn;
    }
}
?>