<?php
$error_message = $_GET['message'] ?? 'An unknown error occurred.';
$error_type = $_GET['type'] ?? 'general';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .error-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
        }
        .steps-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="error-container">
            <div class="text-center mb-4">
                <i class="bi bi-exclamation-triangle-fill error-icon"></i>
                <h1 class="mt-3">Oops! Something went wrong</h1>
                <p class="lead text-muted"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
            
            <?php if (strpos($error_message, 'MySQL') !== false): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">How to Fix MySQL Connection Issues</h5>
                </div>
                <div class="card-body">
                    <div class="steps-container">
                        <h6><i class="bi bi-1-circle-fill me-2"></i>Start XAMPP Control Panel</h6>
                        <p>Open XAMPP Control Panel from your Start Menu or Desktop.</p>
                        
                        <h6><i class="bi bi-2-circle-fill me-2"></i>Start MySQL Service</h6>
                        <p>Click the "Start" button next to MySQL in the XAMPP Control Panel.</p>
                        <div class="text-center mb-3">
                            <img src="https://i.imgur.com/IMQCL1F.png" alt="XAMPP Control Panel" class="img-fluid border rounded" style="max-width: 500px;">
                        </div>
                        
                        <h6><i class="bi bi-3-circle-fill me-2"></i>Verify MySQL is Running</h6>
                        <p>The status should change to "Running" with a green background.</p>
                        
                        <h6><i class="bi bi-4-circle-fill me-2"></i>Refresh StudyBud</h6>
                        <p>Once MySQL is running, refresh this page to try again.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="text-center">
                <a href="index.php" class="btn btn-primary me-2">
                    <i class="bi bi-house-door me-1"></i> Go to Homepage
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Go Back
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
