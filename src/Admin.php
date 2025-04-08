<?php
require_once __DIR__ . '/../config/database.php';

class Admin {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Check if a user has admin privileges
     */
    public function isAdmin($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT role FROM users WHERE id = :id"
            );
            
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();
            
            return $user && $user['role'] === 'admin';
        } catch (PDOException $e) {
            error_log("Admin check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all users with pagination
     */
    public function getAllUsers($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, email, username, full_name, created_at, last_login, is_active, role 
                 FROM users 
                 ORDER BY id DESC 
                 LIMIT :limit OFFSET :offset"
            );
            
            // Using bindValue because PDO doesn't support binding to LIMIT/OFFSET directly
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get users error: " . $e->getMessage());
            throw new Exception("Failed to retrieve users");
        }
    }
    
    /**
     * Get total user count
     */
    public function getUserCount() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM users");
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Get user count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get login activity with pagination
     */
    public function getLoginActivity($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        try {
            // Create login_activity table if using SQLite and table doesn't exist
            if (Database::getInstance()->getDatabaseType() === 'sqlite') {
                $this->db->exec("CREATE TABLE IF NOT EXISTS login_activity (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    email TEXT NOT NULL,
                    username TEXT NOT NULL,
                    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ip_address TEXT,
                    user_agent TEXT,
                    status TEXT,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )");
            }
            
            $stmt = $this->db->prepare(
                "SELECT login_activity.*, users.full_name 
                 FROM login_activity 
                 JOIN users ON login_activity.user_id = users.id
                 ORDER BY login_time DESC 
                 LIMIT :limit OFFSET :offset"
            );
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get login activity error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log login activity
     */
    public function logLoginActivity($userId, $email, $username, $status = 'success') {
        try {
            // Create login_activity table if using SQLite and table doesn't exist
            if (Database::getInstance()->getDatabaseType() === 'sqlite') {
                $this->db->exec("CREATE TABLE IF NOT EXISTS login_activity (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    email TEXT NOT NULL,
                    username TEXT NOT NULL,
                    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ip_address TEXT,
                    user_agent TEXT,
                    status TEXT,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )");
            }
            
            $stmt = $this->db->prepare(
                "INSERT INTO login_activity (user_id, email, username, ip_address, user_agent, status) 
                 VALUES (:user_id, :email, :username, :ip_address, :user_agent, :status)"
            );
            
            return $stmt->execute([
                ':user_id' => $userId,
                ':email' => $email,
                ':username' => $username,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                ':status' => $status
            ]);
        } catch (PDOException $e) {
            error_log("Log login activity error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a new admin user
     */
    public function createAdminUser($email, $username, $password, $fullName) {
        try {
            // Hash password with pepper
            $hashedPassword = password_hash($password . PASSWORD_PEPPER, PASSWORD_ARGON2ID);
            
            $stmt = $this->db->prepare(
                "INSERT INTO users (email, username, password_hash, full_name, role) 
                 VALUES (:email, :username, :password, :fullName, 'admin')"
            );
            
            return $stmt->execute([
                ':email' => $email,
                ':username' => $username,
                ':password' => $hashedPassword,
                ':fullName' => $fullName
            ]);
        } catch (PDOException $e) {
            error_log("Create admin error: " . $e->getMessage());
            throw new Exception("Failed to create admin user");
        }
    }
}
