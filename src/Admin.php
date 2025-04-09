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
     * Get admin dashboard statistics
     */
    public function getDashboardStats() {
        try {
            $stats = [
                'total_users' => 0,
                'active_users' => 0,
                'total_courses' => 0,
                'total_videos' => 0,
                'recent_registrations' => [],
                'recent_logins' => []
            ];
            
            // Get user counts
            $stmt = $this->db->query("SELECT COUNT(*) FROM users");
            $stats['total_users'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE is_active = TRUE");
            $stats['active_users'] = $stmt->fetchColumn();
            
            // Get course count
            $stmt = $this->db->query("SELECT COUNT(*) FROM courses");
            $stats['total_courses'] = $stmt->fetchColumn();
            
            // Get video count
            $stmt = $this->db->query("SELECT COUNT(*) FROM videos");
            $stats['total_videos'] = $stmt->fetchColumn();
            
            // Get recent registrations
            $stmt = $this->db->query(
                "SELECT id, username, email, full_name, created_at 
                 FROM users 
                 ORDER BY created_at DESC 
                 LIMIT 5"
            );
            $stats['recent_registrations'] = $stmt->fetchAll();
            
            // Get recent logins
            $stmt = $this->db->query(
                "SELECT login_activity.*, users.full_name 
                 FROM login_activity 
                 JOIN users ON login_activity.user_id = users.id
                 ORDER BY login_time DESC 
                 LIMIT 5"
            );
            $stats['recent_logins'] = $stmt->fetchAll();
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Get dashboard stats error: " . $e->getMessage());
            return [];
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
    
    /**
     * Get user details by ID
     */
    public function getUserById($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, email, username, full_name, created_at, last_login, is_active, role 
                 FROM users 
                 WHERE id = :id"
            );
            
            $stmt->execute([':id' => $userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update user details
     */
    public function updateUser($userId, $data) {
        try {
            $allowedFields = ['email', 'username', 'full_name', 'is_active', 'role'];
            $updates = [];
            $params = [':id' => $userId];
            
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $updates[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
            }
            
            if (empty($updates)) {
                return false;
            }
            
            $updateStr = implode(', ', $updates);
            $stmt = $this->db->prepare("UPDATE users SET $updateStr WHERE id = :id");
            
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update user error: " . $e->getMessage());
            throw new Exception("Failed to update user");
        }
    }
    
    /**
     * Update user password
     */
    public function updateUserPassword($userId, $newPassword) {
        try {
            $hashedPassword = password_hash($newPassword . PASSWORD_PEPPER, PASSWORD_ARGON2ID);
            
            $stmt = $this->db->prepare(
                "UPDATE users 
                 SET password_hash = :password 
                 WHERE id = :id"
            );
            
            return $stmt->execute([
                ':password' => $hashedPassword,
                ':id' => $userId
            ]);
        } catch (PDOException $e) {
            error_log("Update password error: " . $e->getMessage());
            throw new Exception("Failed to update password");
        }
    }
    
    /**
     * Delete user
     */
    public function deleteUser($userId) {
        try {
            // First check if user is an admin
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();
            
            if ($user && $user['role'] === 'admin') {
                // Count number of admins
                $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                $adminCount = $stmt->fetchColumn();
                
                if ($adminCount <= 1) {
                    throw new Exception("Cannot delete the last admin user");
                }
            }
            
            // Delete user
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
            return $stmt->execute([':id' => $userId]);
        } catch (PDOException $e) {
            error_log("Delete user error: " . $e->getMessage());
            throw new Exception("Failed to delete user");
        }
    }
    
    /**
     * Get all courses with pagination
     */
    public function getAllCourses($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        try {
            $stmt = $this->db->prepare(
                "SELECT c.*, u.username as instructor_name 
                 FROM courses c
                 JOIN users u ON c.instructor_id = u.id
                 ORDER BY c.created_at DESC 
                 LIMIT :limit OFFSET :offset"
            );
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get courses error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total course count
     */
    public function getCourseCount() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM courses");
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Get course count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get all admin users
     */
    public function getAdminUsers() {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, email, username, full_name, created_at, last_login, is_active 
                 FROM users 
                 WHERE role = 'admin' 
                 ORDER BY id DESC"
            );
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get admin users error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Count admin users
     */
    public function countAdminUsers() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Count admin users error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get system settings
     */
    public function getSystemSettings() {
        try {
            // Create settings table if it doesn't exist
            $this->createSettingsTableIfNotExists();
            
            $stmt = $this->db->query("SELECT * FROM settings");
            $settings = [];
            
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            return $settings;
        } catch (PDOException $e) {
            error_log("Get settings error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update system setting
     */
    public function updateSystemSetting($key, $value) {
        try {
            // Create settings table if it doesn't exist
            $this->createSettingsTableIfNotExists();
            
            // Check if setting exists
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = :key");
            $stmt->execute([':key' => $key]);
            $exists = $stmt->fetchColumn() > 0;
            
            if ($exists) {
                $stmt = $this->db->prepare(
                    "UPDATE settings 
                     SET setting_value = :value, updated_at = CURRENT_TIMESTAMP 
                     WHERE setting_key = :key"
                );
            } else {
                $stmt = $this->db->prepare(
                    "INSERT INTO settings (setting_key, setting_value) 
                     VALUES (:key, :value)"
                );
            }
            
            return $stmt->execute([
                ':key' => $key,
                ':value' => $value
            ]);
        } catch (PDOException $e) {
            error_log("Update setting error: " . $e->getMessage());
            throw new Exception("Failed to update setting");
        }
    }
    
    /**
     * Create settings table if it doesn't exist
     */
    private function createSettingsTableIfNotExists() {
        try {
            // Check if table exists
            if (Database::getInstance()->getDatabaseType() === 'mysql') {
                $stmt = $this->db->query("SHOW TABLES LIKE 'settings'");
                $tableExists = $stmt->rowCount() > 0;
            } else {
                // SQLite
                $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
                $tableExists = $stmt->fetchColumn() !== false;
            }
            
            if (!$tableExists) {
                if (Database::getInstance()->getDatabaseType() === 'mysql') {
                    $this->db->exec("CREATE TABLE settings (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        setting_key VARCHAR(255) NOT NULL UNIQUE,
                        setting_value TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_setting_key (setting_key)
                    )");
                } else {
                    // SQLite
                    $this->db->exec("CREATE TABLE settings (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        setting_key TEXT NOT NULL UNIQUE,
                        setting_value TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                }
                
                // Insert default settings
                $defaultSettings = [
                    'site_name' => 'StudyBud',
                    'site_description' => 'A modern e-learning platform',
                    'allow_registrations' => '1',
                    'max_login_attempts' => '5',
                    'maintenance_mode' => '0'
                ];
                
                foreach ($defaultSettings as $key => $value) {
                    $stmt = $this->db->prepare(
                        "INSERT INTO settings (setting_key, setting_value) 
                         VALUES (:key, :value)"
                    );
                    $stmt->execute([':key' => $key, ':value' => $value]);
                }
            }
        } catch (PDOException $e) {
            error_log("Create settings table error: " . $e->getMessage());
            throw new Exception("Failed to create settings table");
        }
    }
}
