<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';

try {
    $auth = new Auth();
    
    // Redirect to dashboard if already logged in
    if ($auth->isLoggedIn()) {
        header('Location: dashboard.php');
        exit;
    }
} catch (Exception $e) {
    // Redirect to error page with the error message
    header('Location: error.php?message=' . urlencode($e->getMessage()) . '&type=database');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">StudyBud</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6">
                <h1 class="display-4">Welcome to StudyBud</h1>
                <p class="lead">Your personal learning journey starts here.</p>
                <div class="mt-4">
                    <a href="register.php" class="btn btn-primary btn-lg me-2">Get Started</a>
                    <a href="login.php" class="btn btn-outline-primary btn-lg">Login</a>
                </div>
            </div>
            <div class="col-md-6">
                <img src="assets/images/learning.svg" alt="Learning illustration" class="img-fluid">
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Learn at Your Pace</h5>
                        <p class="card-text">Access courses anytime, anywhere. Learn at your own convenience.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Track Progress</h5>
                        <p class="card-text">Monitor your learning progress and pick up where you left off.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Save for Later</h5>
                        <p class="card-text">Bookmark videos and courses to watch them later.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-light mt-5">
        <div class="container py-4">
            <div class="row">
                <div class="col-md-6">
                    <h5>StudyBud</h5>
                    <p>Making learning accessible to everyone.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; <?php echo date('Y'); ?> StudyBud. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
