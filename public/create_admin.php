<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Admin.php';

// Set to false after creating admin to disable this script
$enableAdminCreation = true;

// Admin credentials
$adminEmail = 'admin@studybud.com';
$adminUsername = 'admin';
$adminPassword = 'Admin@123';
$adminFullName = 'System Administrator';

$message = '';

if (!$enableAdminCreation) {
    $message = 'Admin creation is disabled. Edit this file to enable it again.';
} else {
    try {
        $admin = new Admin();
        $admin->createAdminUser($adminEmail, $adminUsername, $adminPassword, $adminFullName);
        $message = 'Admin user created successfully!';
        
        // Disable the script after successful creation
        // Uncomment the following line to automatically disable after creation
        // file_put_contents(__FILE__, str_replace('$enableAdminCreation = true', '$enableAdminCreation = false', file_get_contents(__FILE__)));
    } catch (Exception $e) {
        $message = 'Error creating admin user: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Create Admin User</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                        
                        <?php if (strpos($message, 'successfully') !== false): ?>
                        <div class="alert alert-info">
                            <h5>Admin Credentials:</h5>
                            <ul>
                                <li><strong>Email:</strong> <?php echo htmlspecialchars($adminEmail); ?></li>
                                <li><strong>Username:</strong> <?php echo htmlspecialchars($adminUsername); ?></li>
                                <li><strong>Password:</strong> <?php echo htmlspecialchars($adminPassword); ?></li>
                            </ul>
                            <p class="mb-0"><strong>Important:</strong> Please delete this file after use for security reasons.</p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
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
