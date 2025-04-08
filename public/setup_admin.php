<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Admin credentials - CHANGE THESE AS NEEDED
$adminEmail = 'admin@studybud.com';
$adminUsername = 'superadmin';
$adminPassword = 'Password123!';
$adminFullName = 'System Administrator';

// Get database connection
$db = Database::getInstance()->getConnection();

try {
    // Check if we're using SQLite or MySQL
    $dbType = Database::getInstance()->getDatabaseType();
    
    // Create users table if it doesn't exist (for SQLite)
    if ($dbType === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            full_name TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            is_active INTEGER DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        )");
    }
    
    // Check if admin user already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email OR username = :username");
    $stmt->execute([
        ':email' => $adminEmail,
        ':username' => $adminUsername
    ]);
    
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        // Update existing admin user
        $hashedPassword = password_hash($adminPassword . PASSWORD_PEPPER, PASSWORD_ARGON2ID);
        
        $stmt = $db->prepare("UPDATE users SET 
            email = :email,
            username = :username,
            password_hash = :password,
            full_name = :fullName,
            role = 'admin',
            is_active = 1
            WHERE id = :id");
            
        $stmt->execute([
            ':email' => $adminEmail,
            ':username' => $adminUsername,
            ':password' => $hashedPassword,
            ':fullName' => $adminFullName,
            ':id' => $existingUser['id']
        ]);
        
        $message = "Admin user updated successfully!";
    } else {
        // Create new admin user
        $hashedPassword = password_hash($adminPassword . PASSWORD_PEPPER, PASSWORD_ARGON2ID);
        
        $stmt = $db->prepare("INSERT INTO users 
            (email, username, password_hash, full_name, role, is_active) 
            VALUES (:email, :username, :password, :fullName, 'admin', 1)");
            
        $stmt->execute([
            ':email' => $adminEmail,
            ':username' => $adminUsername,
            ':password' => $hashedPassword,
            ':fullName' => $adminFullName
        ]);
        
        $message = "Admin user created successfully!";
    }
    
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 50px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">StudyBud Admin Setup</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                        
                        <div class="alert alert-info">
                            <h5>Admin Credentials:</h5>
                            <ul class="mb-0">
                                <li><strong>Email:</strong> <?php echo htmlspecialchars($adminEmail); ?></li>
                                <li><strong>Username:</strong> <?php echo htmlspecialchars($adminUsername); ?></li>
                                <li><strong>Password:</strong> <?php echo htmlspecialchars($adminPassword); ?></li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>Important:</strong> For security reasons, please delete this file after use.
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="login.php" class="btn btn-primary">Go to Login</a>
                            <a href="index.php" class="btn btn-outline-secondary">Go to Homepage</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
