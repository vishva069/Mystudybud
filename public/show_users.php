<?php
require_once __DIR__ . '/../config/database.php';

// Simple security check - delete this file after use
$allowed_ips = ['127.0.0.1', '::1', $_SERVER['REMOTE_ADDR']];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    die("Access denied");
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, username, email, role, is_active FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="m-0">User Accounts</h3>
                <div>
                    <a href="reset-password.php" class="btn btn-light btn-sm">Reset Password</a>
                    <a href="login.php" class="btn btn-light btn-sm ms-2">Login Page</a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <strong>Note:</strong> For security reasons, this page only shows usernames and emails. Passwords are securely hashed and cannot be retrieved. Use the password reset functionality if you need to regain access.
                </div>
                
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                    <?php echo htmlspecialchars($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="reset-password.php?email=<?php echo urlencode($user['email']); ?>" class="btn btn-sm btn-outline-primary">Reset Password</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No users found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted">
                <strong>Security Notice:</strong> Delete this file after use to prevent unauthorized access to user information.
            </div>
        </div>
    </div>
</body>
</html>
