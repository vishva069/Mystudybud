<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Admin.php';

$auth = new Auth();
$admin = new Admin();

// Check if user is logged in and is an admin
if (!$auth->isLoggedIn() || !$admin->isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php?error=unauthorized');
    exit;
}

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$limit = 10;

// Get current tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Get dashboard statistics
$stats = $admin->getDashboardStats();

// Handle different tab data
switch ($activeTab) {
    case 'users':
        $users = $admin->getAllUsers($page, $limit);
        $totalUsers = $admin->getUserCount();
        $totalPages = ceil($totalUsers / $limit);
        break;
        
    case 'courses':
        $courses = $admin->getAllCourses($page, $limit);
        $totalCourses = $admin->getCourseCount();
        $totalPages = ceil($totalCourses / $limit);
        break;
        
    case 'login_activity':
        $loginActivity = $admin->getLoginActivity($page, $limit);
        break;
        
    case 'settings':
        $systemSettings = $admin->getSystemSettings();
        
        // Handle settings update
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $settingsError = 'Invalid request';
            } else {
                try {
                    foreach ($_POST as $key => $value) {
                        if (strpos($key, 'setting_') === 0) {
                            $settingKey = substr($key, 8); // Remove 'setting_' prefix
                            $admin->updateSystemSetting($settingKey, $value);
                        }
                    }
                    $settingsSuccess = 'Settings updated successfully';
                    $systemSettings = $admin->getSystemSettings(); // Refresh settings
                } catch (Exception $e) {
                    $settingsError = $e->getMessage();
                }
            }
        }
        break;
        
    default:
        // Dashboard is default
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-sidebar {
            min-height: calc(100vh - 56px);
            background-color: #f8f9fa;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .status-badge {
            width: 80px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">StudyBud</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">Student Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Admin Panel</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-badge"></i> <?php echo h($_SESSION['username']); ?> (Admin)
                    </span>
                    <a href="../logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 admin-sidebar p-0">
                <div class="list-group list-group-flush">
                    <a href="?tab=dashboard" class="list-group-item list-group-item-action <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                    <div class="list-group-item bg-light fw-bold small text-muted">
                        USER MANAGEMENT
                    </div>
                    <a href="?tab=users" class="list-group-item list-group-item-action <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
                        <i class="bi bi-people me-2"></i> Users
                    </a>
                    <a class="nav-link <?php echo $activeTab === 'admin_users' ? 'active' : ''; ?>" href="?tab=admin_users">
                        <i class="bi bi-shield-lock"></i> Admin Users
                    </a>
                    <a href="?tab=login_activity" class="list-group-item list-group-item-action <?php echo $activeTab === 'login_activity' ? 'active' : ''; ?>">
                        <i class="bi bi-door-open me-2"></i> Login Activity
                    </a>
                    <div class="list-group-item bg-light fw-bold small text-muted">
                        CONTENT MANAGEMENT
                    </div>
                    <a href="?tab=courses" class="list-group-item list-group-item-action <?php echo $activeTab === 'courses' ? 'active' : ''; ?>">
                        <i class="bi bi-book me-2"></i> Courses
                    </a>
                    <a href="?tab=videos" class="list-group-item list-group-item-action <?php echo $activeTab === 'videos' ? 'active' : ''; ?>">
                        <i class="bi bi-play-circle me-2"></i> Videos
                    </a>
                    <div class="list-group-item bg-light fw-bold small text-muted">
                        SYSTEM
                    </div>
                    <a href="?tab=settings" class="list-group-item list-group-item-action <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
                        <i class="bi bi-gear me-2"></i> Settings
                    </a>
                    <a href="?tab=logs" class="list-group-item list-group-item-action <?php echo $activeTab === 'logs' ? 'active' : ''; ?>">
                        <i class="bi bi-file-text me-2"></i> System Logs
                    </a>
                </div>
            </div>
            
            <!-- Main content -->
            <div class="col-md-10 p-4">
                <?php if ($activeTab === 'dashboard'): ?>
                    <!-- Dashboard tab -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-speedometer2"></i> Admin Dashboard</h2>
                        <div>
                            <span class="text-muted">Last updated: <?php echo date('Y-m-d H:i:s'); ?></span>
                            <button class="btn btn-sm btn-outline-primary ms-2" onclick="window.location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">Total Users</h6>
                                            <h2 class="mt-2 mb-0"><?php echo $stats['total_users']; ?></h2>
                                        </div>
                                        <div class="fs-1 opacity-50">
                                            <i class="bi bi-people"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3 small">
                                        <span class="text-white-50"><?php echo $stats['active_users']; ?> active users</span>
                                    </div>
                                </div>
                                <div class="card-footer bg-primary-dark d-flex justify-content-between py-2">
                                    <span class="small">View Details</span>
                                    <i class="bi bi-chevron-right small"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">Total Courses</h6>
                                            <h2 class="mt-2 mb-0"><?php echo $stats['total_courses']; ?></h2>
                                        </div>
                                        <div class="fs-1 opacity-50">
                                            <i class="bi bi-book"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3 small">
                                        <span class="text-white-50">Educational content</span>
                                    </div>
                                </div>
                                <div class="card-footer bg-success-dark d-flex justify-content-between py-2">
                                    <span class="small">View Details</span>
                                    <i class="bi bi-chevron-right small"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">Total Videos</h6>
                                            <h2 class="mt-2 mb-0"><?php echo $stats['total_videos']; ?></h2>
                                        </div>
                                        <div class="fs-1 opacity-50">
                                            <i class="bi bi-play-circle"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3 small">
                                        <span class="text-white-50">Learning materials</span>
                                    </div>
                                </div>
                                <div class="card-footer bg-info-dark d-flex justify-content-between py-2">
                                    <span class="small">View Details</span>
                                    <i class="bi bi-chevron-right small"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">System Status</h6>
                                            <h2 class="mt-2 mb-0">Active</h2>
                                        </div>
                                        <div class="fs-1 opacity-50">
                                            <i class="bi bi-check-circle"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3 small">
                                        <span class="text-white-50">All systems operational</span>
                                    </div>
                                </div>
                                <div class="card-footer bg-warning-dark d-flex justify-content-between py-2">
                                    <span class="small">View Details</span>
                                    <i class="bi bi-chevron-right small"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Recent Registrations -->
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <strong><i class="bi bi-person-plus me-2"></i> Recent Registrations</strong>
                                    <a href="?tab=users" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>User</th>
                                                    <th>Email</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($stats['recent_registrations'] as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar bg-light text-primary rounded-circle p-2 me-2">
                                                                <i class="bi bi-person"></i>
                                                            </div>
                                                            <?php echo h($user['full_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo h($user['email']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                
                                                <?php if (empty($stats['recent_registrations'])): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center py-3">No recent registrations</td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Logins -->
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <strong><i class="bi bi-door-open me-2"></i> Recent Login Activity</strong>
                                    <a href="?tab=login_activity" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>User</th>
                                                    <th>Time</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($stats['recent_logins'] as $login): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar bg-light text-primary rounded-circle p-2 me-2">
                                                                <i class="bi bi-person"></i>
                                                            </div>
                                                            <?php echo h($login['full_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo date('M d, H:i', strtotime($login['login_time'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $login['status'] === 'success' ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($login['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                
                                                <?php if (empty($stats['recent_logins'])): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center py-3">No recent login activity</td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <strong><i class="bi bi-lightning-charge me-2"></i> Quick Actions</strong>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="?tab=create_admin" class="btn btn-outline-primary w-100 d-flex flex-column align-items-center py-3">
                                        <i class="bi bi-person-plus fs-3 mb-2"></i>
                                        <span>Create Admin</span>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="?tab=courses&action=new" class="btn btn-outline-success w-100 d-flex flex-column align-items-center py-3">
                                        <i class="bi bi-plus-circle fs-3 mb-2"></i>
                                        <span>Add Course</span>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="?tab=settings" class="btn btn-outline-info w-100 d-flex flex-column align-items-center py-3">
                                        <i class="bi bi-gear fs-3 mb-2"></i>
                                        <span>System Settings</span>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="?tab=logs" class="btn btn-outline-secondary w-100 d-flex flex-column align-items-center py-3">
                                        <i class="bi bi-file-text fs-3 mb-2"></i>
                                        <span>View Logs</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                
                <?php elseif ($activeTab === 'users'): ?>
                    <!-- Users tab -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-people"></i> User Management</h2>
                        <div class="d-flex">
                            <input type="text" id="userSearch" class="form-control me-2" placeholder="Search users...">
                            <button class="btn btn-outline-primary">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header bg-light">
                            <strong>Registered Users</strong>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Full Name</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                            <th>Last Login</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo h($user['id']); ?></td>
                                            <td><?php echo h($user['username']); ?></td>
                                            <td><?php echo h($user['email']); ?></td>
                                            <td><?php echo h($user['full_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                    <?php echo h(ucfirst($user['role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?> status-badge">
                                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                            <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-secondary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">No users found</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    Showing <?php echo count($users); ?> of <?php echo $totalUsers; ?> users
                                </div>
                                
                                <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination mb-0">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&tab=users">Previous</a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&tab=users"><?php echo $i; ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&tab=users">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                
                <?php elseif ($activeTab === 'login_activity'): ?>
                    <!-- Login Activity tab -->
                    <?php
                    // Get login activity with pagination
                    $loginActivity = $admin->getLoginActivity($page, $limit);
                    ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-door-open"></i> Login Activity</h2>
                        <div>
                            <button class="btn btn-outline-primary">
                                <i class="bi bi-download"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header bg-light">
                            <strong>Recent Login Activity</strong>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Email</th>
                                            <th>Username</th>
                                            <th>Login Time</th>
                                            <th>IP Address</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loginActivity as $activity): ?>
                                        <tr>
                                            <td><?php echo h($activity['id']); ?></td>
                                            <td><?php echo h($activity['full_name']); ?></td>
                                            <td><?php echo h($activity['email']); ?></td>
                                            <td><?php echo h($activity['username']); ?></td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($activity['login_time'])); ?></td>
                                            <td><?php echo h($activity['ip_address']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $activity['status'] === 'success' ? 'success' : 'danger'; ?> status-badge">
                                                    <?php echo ucfirst($activity['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($loginActivity)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No login activity found</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                
                <?php elseif ($activeTab === 'settings'): ?>
                    <!-- Settings tab -->
                    <div class="mb-4">
                        <h2><i class="bi bi-gear"></i> System Settings</h2>
                        <p class="text-muted">Configure system-wide settings and preferences</p>
                    </div>
                    
                    <?php if (isset($settingsError)): ?>
                        <div class="alert alert-danger"><?php echo h($settingsError); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($settingsSuccess)): ?>
                        <div class="alert alert-success"><?php echo h($settingsSuccess); ?></div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-header bg-light">
                            <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">General</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">Security</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">Email</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab" aria-controls="maintenance" aria-selected="false">Maintenance</button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="?tab=settings">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="update_settings" value="1">
                                
                                <div class="tab-content" id="settingsTabsContent">
                                    <!-- General Settings Tab -->
                                    <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                                        <div class="mb-3">
                                            <label for="setting_site_name" class="form-label">Site Name</label>
                                            <input type="text" class="form-control" id="setting_site_name" name="setting_site_name" value="<?php echo h($systemSettings['site_name'] ?? 'StudyBud'); ?>">
                                            <div class="form-text">The name of your learning platform</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="setting_site_description" class="form-label">Site Description</label>
                                            <textarea class="form-control" id="setting_site_description" name="setting_site_description" rows="2"><?php echo h($systemSettings['site_description'] ?? 'A modern e-learning platform'); ?></textarea>
                                            <div class="form-text">Brief description of your platform</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="setting_allow_registrations" class="form-label">User Registration</label>
                                            <select class="form-select" id="setting_allow_registrations" name="setting_allow_registrations">
                                                <option value="1" <?php echo ($systemSettings['allow_registrations'] ?? '1') == '1' ? 'selected' : ''; ?>>Enabled</option>
                                                <option value="0" <?php echo ($systemSettings['allow_registrations'] ?? '1') == '0' ? 'selected' : ''; ?>>Disabled</option>
                                            </select>
                                            <div class="form-text">Allow new users to register on the platform</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Security Settings Tab -->
                                    <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                                        <div class="mb-3">
                                            <label for="setting_max_login_attempts" class="form-label">Max Login Attempts</label>
                                            <input type="number" class="form-control" id="setting_max_login_attempts" name="setting_max_login_attempts" value="<?php echo h($systemSettings['max_login_attempts'] ?? '5'); ?>" min="1" max="10">
                                            <div class="form-text">Number of failed login attempts before temporary lockout</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="setting_password_expiry_days" class="form-label">Password Expiry (Days)</label>
                                            <input type="number" class="form-control" id="setting_password_expiry_days" name="setting_password_expiry_days" value="<?php echo h($systemSettings['password_expiry_days'] ?? '90'); ?>" min="0" max="365">
                                            <div class="form-text">Days until password expires (0 = never)</div>
                                        </div>
                                        
                                        <div class="mb-3 form-check">
                                            <input type="checkbox" class="form-check-input" id="setting_enforce_2fa" name="setting_enforce_2fa" value="1" <?php echo ($systemSettings['enforce_2fa'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="setting_enforce_2fa">Enforce Two-Factor Authentication for Admins</label>
                                            <div class="form-text">Require all administrators to use 2FA</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Email Settings Tab -->
                                    <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                                        <div class="mb-3">
                                            <label for="setting_email_from" class="form-label">From Email Address</label>
                                            <input type="email" class="form-control" id="setting_email_from" name="setting_email_from" value="<?php echo h($systemSettings['email_from'] ?? 'noreply@studybud.com'); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="setting_email_from_name" class="form-label">From Name</label>
                                            <input type="text" class="form-control" id="setting_email_from_name" name="setting_email_from_name" value="<?php echo h($systemSettings['email_from_name'] ?? 'StudyBud'); ?>">
                                        </div>
                                        
                                        <div class="mb-3 form-check">
                                            <input type="checkbox" class="form-check-input" id="setting_email_notifications" name="setting_email_notifications" value="1" <?php echo ($systemSettings['email_notifications'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="setting_email_notifications">Enable Email Notifications</label>
                                        </div>
                                    </div>
                                    
                                    <!-- Maintenance Settings Tab -->
                                    <div class="tab-pane fade" id="maintenance" role="tabpanel" aria-labelledby="maintenance-tab">
                                        <div class="mb-3">
                                            <label for="setting_maintenance_mode" class="form-label">Maintenance Mode</label>
                                            <select class="form-select" id="setting_maintenance_mode" name="setting_maintenance_mode">
                                                <option value="0" <?php echo ($systemSettings['maintenance_mode'] ?? '0') == '0' ? 'selected' : ''; ?>>Disabled</option>
                                                <option value="1" <?php echo ($systemSettings['maintenance_mode'] ?? '0') == '1' ? 'selected' : ''; ?>>Enabled</option>
                                            </select>
                                            <div class="form-text">When enabled, only administrators can access the site</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="setting_maintenance_message" class="form-label">Maintenance Message</label>
                                            <textarea class="form-control" id="setting_maintenance_message" name="setting_maintenance_message" rows="3"><?php echo h($systemSettings['maintenance_message'] ?? 'We are currently performing scheduled maintenance. Please check back soon.'); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="reset" class="btn btn-outline-secondary me-2">Reset</button>
                                    <button type="submit" class="btn btn-primary">Save Settings</button>
                                </div>
                            </form>
                        </div>
                    </div>
                
                <?php elseif ($activeTab === 'create_admin'): ?>
                    <!-- Create Admin tab -->
                    <?php
                    $error = '';
                    $success = '';
                    
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
                        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                            $error = 'Invalid request';
                        } else {
                            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
                            $fullName = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
                            $password = $_POST['password'] ?? '';
                            
                            try {
                                if ($admin->createAdminUser($email, $username, $password, $fullName)) {
                                    $success = 'Admin user created successfully';
                                } else {
                                    $error = 'Failed to create admin user';
                                }
                            } catch (Exception $e) {
                                $error = $e->getMessage();
                            }
                        }
                    }
                    ?>
                    
                    <div class="mb-4">
                        <h2><i class="bi bi-person-plus"></i> Create Admin User</h2>
                        <p class="text-muted">Create a new administrator account with full system access</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo h($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo h($success); ?></div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-header bg-light">
                            <strong>New Admin Details</strong>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="?tab=create_admin" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="create_admin" value="1">
                                
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                    <div class="invalid-feedback">
                                        Please enter a full name.
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                        <div class="invalid-feedback">
                                            Please enter a valid email.
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                        <div class="invalid-feedback">
                                            Please enter a username.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="invalid-feedback">
                                        Please enter a password.
                                    </div>
                                    <div class="form-text">
                                        Password must be at least 8 characters long.
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-person-plus me-2"></i> Create Admin User
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (function () {
        'use strict'
        const forms = document.querySelectorAll('.needs-validation')
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
    </script>
</body>
</html>
