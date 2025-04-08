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
    
    public function getUserById($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, email, username, full_name, bio, role, created_at, last_login, profile_image
                 FROM users 
                 WHERE id = :id"
            );
            
            $stmt->execute([':id' => $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user by ID error: " . $e->getMessage());
            return null;
        }
    }
    
    public function createPasswordResetToken($email) {
        try {
            // Check if user exists
            $stmt = $this->db->prepare("SELECT id, email FROM users WHERE email = :email AND is_active = TRUE");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return false; // User not found or inactive
            }
            
            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            
            // Check if password_resets table exists, create if not
            $this->createPasswordResetsTableIfNotExists();
            
            // Delete any existing tokens for this user
            $stmt = $this->db->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user['id']]);
            
            // Create a new token that expires in 1 hour
            $stmt = $this->db->prepare(
                "INSERT INTO password_resets (user_id, token, expires_at) 
                 VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
            );
            
            $stmt->execute([
                ':user_id' => $user['id'],
                ':token' => $token
            ]);
            
            return [
                'email' => $user['email'],
                'token' => $token
            ];
        } catch (PDOException $e) {
            error_log("Password reset token creation error: " . $e->getMessage());
            throw new Exception("Failed to create password reset token");
        }
    }
    
    private function createPasswordResetsTableIfNotExists() {
        try {
            // Check if table exists
            $stmt = $this->db->query("SHOW TABLES LIKE 'password_resets'");
            $tableExists = $stmt->rowCount() > 0;
            
            if (!$tableExists) {
                // Create the table
                $this->db->exec("CREATE TABLE password_resets (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    token VARCHAR(255) NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    INDEX idx_token (token)
                )");
            }
        } catch (PDOException $e) {
            error_log("Create password_resets table error: " . $e->getMessage());
            throw new Exception("Failed to create password_resets table");
        }
    }
    
    public function verifyPasswordResetToken($token) {
        try {
            $stmt = $this->db->prepare(
                "SELECT pr.id, pr.user_id, u.email 
                 FROM password_resets pr
                 JOIN users u ON pr.user_id = u.id
                 WHERE pr.token = :token AND pr.expires_at > NOW()"
            );
            
            $stmt->execute([':token' => $token]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Token verification error: " . $e->getMessage());
            return false;
        }
    }
    
    public function resetPassword($token, $newPassword) {
        try {
            // Verify token is valid
            $tokenData = $this->verifyPasswordResetToken($token);
            
            if (!$tokenData) {
                return false; // Invalid or expired token
            }
            
            // Validate password
            if (strlen($newPassword) < 8) {
                throw new Exception("Password must be at least 8 characters long");
            }
            
            // Hash new password
            $hashedPassword = password_hash($newPassword . PASSWORD_PEPPER, PASSWORD_ARGON2ID);
            
            // Update user's password
            $stmt = $this->db->prepare("UPDATE users SET password_hash = :password WHERE id = :id");
            $success = $stmt->execute([
                ':password' => $hashedPassword,
                ':id' => $tokenData['user_id']
            ]);
            
            if ($success) {
                // Delete the used token
                $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = :token");
                $stmt->execute([':token' => $token]);
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function verifyPassword($userId, $password) {
        try {
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }
            
            return password_verify($password . PASSWORD_PEPPER, $user['password_hash']);
        } catch (PDOException $e) {
            error_log("Password verification error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updatePassword($userId, $newPassword) {
        try {
            // Validate password
            if (strlen($newPassword) < 8) {
                throw new Exception("Password must be at least 8 characters long");
            }
            
            // Hash new password
            $hashedPassword = password_hash($newPassword . PASSWORD_PEPPER, PASSWORD_ARGON2ID);
            
            // Update user's password
            $stmt = $this->db->prepare("UPDATE users SET password_hash = :password WHERE id = :id");
            return $stmt->execute([
                ':password' => $hashedPassword,
                ':id' => $userId
            ]);
        } catch (Exception $e) {
            error_log("Password update error: " . $e->getMessage());
            throw $e;
        }
    }
}
