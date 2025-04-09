<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Admin.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function register($email, $username, $password, $fullName) {
        try {
            // Validate input
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            
            if (strlen($password) < 8) {
                throw new Exception("Password must be at least 8 characters long");
            }
            
            // Hash password with pepper
            $hashedPassword = password_hash($password . PASSWORD_PEPPER, PASSWORD_ARGON2ID);
            
            $stmt = $this->db->prepare(
                "INSERT INTO users (email, username, password_hash, full_name) 
                 VALUES (:email, :username, :password, :fullName)"
            );
            
            return $stmt->execute([
                ':email' => $email,
                ':username' => $username,
                ':password' => $hashedPassword,
                ':fullName' => $fullName
            ]);
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            throw new Exception("Registration failed");
        }
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, email, password_hash, username, role 
                 FROM users 
                 WHERE email = :email AND is_active = TRUE"
            );
            
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password . PASSWORD_PEPPER, $user['password_hash'])) {
                // Update last login
                $this->updateLastLogin($user['id']);
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Log successful login
                $admin = new Admin();
                $admin->logLoginActivity($user['id'], $user['email'], $user['username'], 'success');
                
                return true;
            }
            
            // Log failed login attempt if user exists
            if ($user) {
                $admin = new Admin();
                $admin->logLoginActivity($user['id'], $user['email'], $user['username'], 'failed');
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            throw new Exception("Login failed");
        }
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare(
            "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id"
        );
        return $stmt->execute([':id' => $userId]);
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function logout() {
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, email, username, full_name, role, created_at 
                 FROM users 
                 WHERE id = :id"
            );
            
            $stmt->execute([':id' => $_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
}
