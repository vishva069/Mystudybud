<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';

$message = '';
$messageType = '';

try {
    $auth = new Auth();
    
    // Redirect if already logged in
    if ($auth->isLoggedIn()) {
        header('Location: dashboard.php');
        exit;
    }
} catch (Exception $e) {
    // Redirect to error page with the error message
    header('Location: error.php?message=' . urlencode($e->getMessage()) . '&type=database');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request';
        $messageType = 'danger';
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address';
            $messageType = 'danger';
        } else {
            try {
                $result = $auth->createPasswordResetToken($email);
                
                if ($result) {
                    // In a real application, you would send an email with the reset link
                    // For this implementation, we'll just display the link (not secure for production)
                    $resetLink = APP_URL . '/public/reset-password.php?token=' . $result['token'];
                    
                    $message = 'A password reset link has been sent to your email address. Please check your inbox.';
                    $messageType = 'success';
                    
                    // For development/testing purposes only
                    if (error_reporting() === E_ALL) {
                        $message .= '<br><small class="text-muted">Development mode: <a href="' . h($resetLink) . '">Reset link</a></small>';
                    }
                } else {
                    // Don't reveal if the email exists or not for security
                    $message = 'If your email is registered, you will receive a password reset link shortly.';
                    $messageType = 'info';
                }
            } catch (Exception $e) {
                $message = 'An error occurred. Please try again later.';
                $messageType = 'danger';
                error_log('Password reset request error: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2>Forgot Password</h2>
                            <p class="text-muted">Enter your email to reset your password</p>
                        </div>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo h($messageType); ?>"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="forgot-password.php" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       required 
                                       autofocus>
                                <div class="invalid-feedback">
                                    Please enter a valid email address.
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                Send Reset Link
                            </button>
                            
                            <div class="text-center mt-3">
                                <a href="login.php" class="text-decoration-none">
                                    Back to Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
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
