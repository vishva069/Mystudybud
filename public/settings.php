<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['user_id'];
$auth = new Auth();
$user = $auth->getCurrentUser();

if (!$user) {
    header('Location: error.php?message=User not found');
    exit;
}

$db = Database::getInstance()->getConnection();

// Process account settings update
$success = '';
$error = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'account';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All password fields are required';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        // Verify current password
        if ($auth->verifyPassword($userId, $currentPassword)) {
            // Update password
            if ($auth->updatePassword($userId, $newPassword)) {
                $success = 'Password updated successfully';
            } else {
                $error = 'Failed to update password';
            }
        } else {
            $error = 'Current password is incorrect';
        }
    }
}

// Handle email preferences update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email_preferences'])) {
    $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
    $marketingEmails = isset($_POST['marketing_emails']) ? 1 : 0;
    
    try {
        $stmt = $db->prepare("
            UPDATE user_preferences 
            SET email_notifications = :email_notifications, 
                marketing_emails = :marketing_emails
            WHERE user_id = :user_id
        ");
        
        // Check if preferences exist
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM user_preferences WHERE user_id = :user_id");
        $checkStmt->execute([':user_id' => $userId]);
        $preferencesExist = $checkStmt->fetchColumn() > 0;
        
        if (!$preferencesExist) {
            // Create preferences if they don't exist
            $db->prepare("
                INSERT INTO user_preferences (user_id, email_notifications, marketing_emails)
                VALUES (:user_id, :email_notifications, :marketing_emails)
            ")->execute([
                ':user_id' => $userId,
                ':email_notifications' => $emailNotifications,
                ':marketing_emails' => $marketingEmails
            ]);
            $success = 'Email preferences updated successfully';
        } else if ($stmt->execute([
            ':email_notifications' => $emailNotifications,
            ':marketing_emails' => $marketingEmails,
            ':user_id' => $userId
        ])) {
            $success = 'Email preferences updated successfully';
        } else {
            $error = 'Failed to update email preferences';
        }
    } catch (PDOException $e) {
        // If table doesn't exist, create it
        if (strpos($e->getMessage(), "no such table: user_preferences") !== false) {
            $db->exec("CREATE TABLE user_preferences (
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
            
            // Try again
            $db->prepare("
                INSERT INTO user_preferences (user_id, email_notifications, marketing_emails)
                VALUES (:user_id, :email_notifications, :marketing_emails)
            ")->execute([
                ':user_id' => $userId,
                ':email_notifications' => $emailNotifications,
                ':marketing_emails' => $marketingEmails
            ]);
            $success = 'Email preferences updated successfully';
        } else {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get user preferences
try {
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
    
    $stmt = $db->prepare("SELECT * FROM user_preferences WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$preferences) {
        // Create default preferences
        $db->prepare("
            INSERT INTO user_preferences (user_id, email_notifications, marketing_emails)
            VALUES (:user_id, 1, 1)
        ")->execute([':user_id' => $userId]);
        
        $preferences = [
            'email_notifications' => 1,
            'marketing_emails' => 1,
            'theme' => 'light',
            'language' => 'en'
        ];
    }
} catch (PDOException $e) {
    $preferences = [
        'email_notifications' => 1,
        'marketing_emails' => 1,
        'theme' => 'light',
        'language' => 'en'
    ];
}

// Get login activity
try {
    $stmt = $db->prepare("
        SELECT * FROM login_activity
        WHERE user_id = :user_id
        ORDER BY login_time DESC
        LIMIT 5
    ");
    $stmt->execute([':user_id' => $userId]);
    $loginActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $loginActivity = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row">
            <!-- Settings Sidebar -->
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Settings</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="?tab=account" class="list-group-item list-group-item-action <?php echo $activeTab === 'account' ? 'active' : ''; ?>">
                            <i class="bi bi-person me-2"></i>Account
                        </a>
                        <a href="?tab=security" class="list-group-item list-group-item-action <?php echo $activeTab === 'security' ? 'active' : ''; ?>">
                            <i class="bi bi-shield-lock me-2"></i>Security
                        </a>
                        <a href="?tab=notifications" class="list-group-item list-group-item-action <?php echo $activeTab === 'notifications' ? 'active' : ''; ?>">
                            <i class="bi bi-bell me-2"></i>Notifications
                        </a>
                        <a href="?tab=billing" class="list-group-item list-group-item-action <?php echo $activeTab === 'billing' ? 'active' : ''; ?>">
                            <i class="bi bi-credit-card me-2"></i>Billing
                        </a>
                        <a href="profile.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-arrow-left me-2"></i>Back to Profile
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Settings Content -->
            <div class="col-md-9">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($activeTab === 'account'): ?>
                    <!-- Account Settings -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-person me-2"></i>Account Information</h5>
                        </div>
                        <div class="card-body">
                            <form>
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                    <div class="form-text">Your username cannot be changed</div>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                    <div class="form-text">Contact support to change your email address</div>
                                </div>
                                <div class="mb-3">
                                    <label for="account_created" class="form-label">Account Created</label>
                                    <input type="text" class="form-control" id="account_created" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="last_login" class="form-label">Last Login</label>
                                    <input type="text" class="form-control" id="last_login" value="<?php echo $user['last_login'] ? date('F j, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>" readonly>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Delete Account -->
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h5>
                        </div>
                        <div class="card-body">
                            <h6>Delete Account</h6>
                            <p class="text-muted">Once you delete your account, there is no going back. Please be certain.</p>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                <i class="bi bi-trash me-2"></i>Delete My Account
                            </button>
                        </div>
                    </div>
                
                <?php elseif ($activeTab === 'security'): ?>
                    <!-- Security Settings -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text">Password must be at least 8 characters long</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Login Activity -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-door-open me-2"></i>Recent Login Activity</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($loginActivity)): ?>
                                <div class="p-4 text-center">
                                    <p class="text-muted">No login activity found</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>IP Address</th>
                                                <th>Device</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($loginActivity as $login): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y H:i', strtotime($login['login_time'])); ?></td>
                                                    <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                                                    <td><?php echo htmlspecialchars(substr($login['user_agent'], 0, 50) . '...'); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $login['status'] === 'success' ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($login['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                
                <?php elseif ($activeTab === 'notifications'): ?>
                    <!-- Notification Settings -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Email Preferences</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="email_notifications" name="email_notifications" <?php echo ($preferences['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">Course updates and announcements</label>
                                    <div class="form-text">Receive emails about updates to courses you're enrolled in</div>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="marketing_emails" name="marketing_emails" <?php echo ($preferences['marketing_emails'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="marketing_emails">Marketing emails</label>
                                    <div class="form-text">Receive emails about new courses and special offers</div>
                                </div>
                                <button type="submit" name="update_email_preferences" class="btn btn-primary">Save Preferences</button>
                            </form>
                        </div>
                    </div>
                
                <?php elseif ($activeTab === 'billing'): ?>
                    <!-- Billing Information -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-credit-card me-2"></i>Billing Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>You don't have any payment methods or subscriptions yet.
                            </div>
                            
                            <div class="mb-4">
                                <h6>Payment Methods</h6>
                                <p class="text-muted">No payment methods found</p>
                                <button class="btn btn-outline-primary" disabled>
                                    <i class="bi bi-plus-circle me-2"></i>Add Payment Method
                                </button>
                            </div>
                            
                            <div class="mb-4">
                                <h6>Subscriptions</h6>
                                <p class="text-muted">You don't have any active subscriptions</p>
                                <a href="courses.php" class="btn btn-primary">
                                    <i class="bi bi-book me-2"></i>Browse Premium Courses
                                </a>
                            </div>
                            
                            <div>
                                <h6>Purchase History</h6>
                                <p class="text-muted">No purchases found</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>Warning: This action cannot be undone!
                    </div>
                    <p>Are you absolutely sure you want to delete your account? This will permanently erase all your data including:</p>
                    <ul>
                        <li>Your profile information</li>
                        <li>Course enrollments and progress</li>
                        <li>Saved videos and bookmarks</li>
                        <li>All other account data</li>
                    </ul>
                    <div class="mb-3">
                        <label for="delete_confirmation" class="form-label">Type "DELETE" to confirm</label>
                        <input type="text" class="form-control" id="delete_confirmation" placeholder="DELETE">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>Delete Account</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete account confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const deleteConfirmationInput = document.getElementById('delete_confirmation');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            
            if (deleteConfirmationInput && confirmDeleteBtn) {
                deleteConfirmationInput.addEventListener('input', function() {
                    confirmDeleteBtn.disabled = this.value !== 'DELETE';
                });
                
                confirmDeleteBtn.addEventListener('click', function() {
                    if (deleteConfirmationInput.value === 'DELETE') {
                        alert('This is a demo. Account deletion is disabled.');
                    }
                });
            }
        });
    </script>
</body>
</html>
