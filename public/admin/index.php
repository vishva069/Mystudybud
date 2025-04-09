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

// Get users with pagination
$users = $admin->getAllUsers($page, $limit);
$totalUsers = $admin->getUserCount();
$totalPages = ceil($totalUsers / $limit);

// Get current tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
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
                        <a class="nav-link" href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Admin</a>
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
                    <a href="?tab=users" class="list-group-item list-group-item-action <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
                        <i class="bi bi-people me-2"></i> Users
                    </a>
                    <a href="?tab=login_activity" class="list-group-item list-group-item-action <?php echo $activeTab === 'login_activity' ? 'active' : ''; ?>">
                        <i class="bi bi-door-open me-2"></i> Login Activity
                    </a>
                    <a href="?tab=create_admin" class="list-group-item list-group-item-action <?php echo $activeTab === 'create_admin' ? 'active' : ''; ?>">
                        <i class="bi bi-person-plus me-2"></i> Create Admin
                    </a>
                </div>
            </div>
            
            <!-- Main content -->
            <div class="col-md-10 p-4">
                <?php if ($activeTab === 'users'): ?>
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
