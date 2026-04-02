<?php
// config/database.php - For PostgreSQL on Render

class Database {
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        $database_url = getenv('DATABASE_URL');
        
        if ($database_url) {
            try {
                $this->conn = new PDO($database_url);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                error_log("Connection error: " . $e->getMessage());
            }
        }
        
        return $this->conn;
    }
}
?>