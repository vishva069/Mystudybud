<?php
require_once __DIR__ . '/../config/database.php';

// Simple security check - delete this file after use
$allowed_ips = ['127.0.0.1', '::1', $_SERVER['REMOTE_ADDR']];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    die("Access denied");
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if bio column exists
    $result = $db->query("PRAGMA table_info(users)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $bioExists = false;
    $profileImageExists = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'bio') {
            $bioExists = true;
        }
        if ($column['name'] === 'profile_image') {
            $profileImageExists = true;
        }
    }
    
    // Add bio column if it doesn't exist
    if (!$bioExists) {
        $db->exec("ALTER TABLE users ADD COLUMN bio TEXT");
        echo "<p>Added 'bio' column to users table</p>";
    }
    
    // Add profile_image column if it doesn't exist
    if (!$profileImageExists) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_image TEXT");
        echo "<p>Added 'profile_image' column to users table</p>";
    }
    
    // Update some users with bio and profile image for demonstration
    $db->exec("UPDATE users SET 
               bio = 'Web developer passionate about learning new technologies and sharing knowledge with others.',
               profile_image = 'assets/images/users/john.jpg'
               WHERE username = 'john_doe'");
               
    $db->exec("UPDATE users SET 
               bio = 'Frontend specialist with 5 years of experience in UI/UX design and implementation.',
               profile_image = 'assets/images/users/jane.jpg'
               WHERE username = 'jane_smith'");
    
    echo "<div class='alert alert-success'>Users table updated successfully!</div>";
    
    // Create user_preferences table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS user_preferences (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        email_notifications BOOLEAN DEFAULT 1,
        marketing_emails BOOLEAN DEFAULT 1,
        theme VARCHAR(20) DEFAULT 'light',
        language VARCHAR(10) DEFAULT 'en',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    echo "<p>Created user_preferences table if it didn't exist</p>";
    
    // Create login_activity table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS login_activity (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        status VARCHAR(20) NOT NULL,
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    echo "<p>Created login_activity table if it didn't exist</p>";
    
    // Insert some dummy login activity
    $db->exec("INSERT INTO login_activity (user_id, ip_address, user_agent, status)
              VALUES (2, '192.168.1.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'success')");
    
    $db->exec("INSERT INTO login_activity (user_id, ip_address, user_agent, status)
              VALUES (2, '192.168.1.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)', 'success')");
    
    $db->exec("INSERT INTO login_activity (user_id, ip_address, user_agent, status)
              VALUES (3, '192.168.1.2', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'success')");
    
    echo "<p>Added dummy login activity</p>";
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Database error: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Users Table - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Database Update</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <strong>Important:</strong> This file should be deleted after use for security reasons.
                </div>
                
                <div class="d-grid gap-2">
                    <a href="profile.php" class="btn btn-primary">Go to Profile Page</a>
                    <a href="settings.php" class="btn btn-secondary">Go to Settings Page</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
