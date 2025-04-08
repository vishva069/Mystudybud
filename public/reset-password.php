<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';

$message = '';
$messageType = '';
$tokenVerified = false;
$email = '';

try {
    $auth = new Auth();
    
    // Redirect if already logged in
    if ($auth->isLoggedIn()) {
        header('Location: dashboard.php');
        exit;
    }
    
    // Check if token is provided and valid
    $token = $_GET['token'] ?? '';
    if ($token) {
        $tokenData = $auth->verifyPasswordResetToken($token);
        if ($tokenData) {
            $tokenVerified = true;
            $email = $tokenData['email'];
        } else {
            $message = 'Invalid or expired password reset link. Please request a new one.';
            $messageType = 'danger';
        }
    } else {
        header('Location: forgot-password.php');
        exit;
    }
} catch (Exception $e) {
    // Redirect to error page with the error message
    header('Location: error.php?message=' . urlencode($e->getMessage()) . '&type=database');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenVerified) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request';
        $messageType = 'danger';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate password
        if (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters long';
            $messageType = 'danger';
        } elseif ($password !== $confirmPassword) {
            $message = 'Passwords do not match';
            $messageType = 'danger';
        } else {
            try {
                $result = $auth->resetPassword($token, $password);
                
                if ($result) {
                    $message = 'Your password has been reset successfully. You can now login with your new password.';
                    $messageType = 'success';
                    
                    // Redirect to login after 3 seconds
                    header('Refresh: 3; URL=login.php');
                } else {
                    $message = 'Failed to reset password. Please try again.';
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                $message = $e->getMessage();
                $messageType = 'danger';
                error_log('Password reset error: ' . $e->getMessage());
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
    <title>Reset Password - StudyBud</title>
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
                            <h2>Reset Password</h2>
                            <?php if ($tokenVerified): ?>
                                <p class="text-muted">Create a new password for <?php echo h($email); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo h($messageType); ?>"><?php echo h($message); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($tokenVerified && $messageType !== 'success'): ?>
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           minlength="8"
                                           required 
                                           autofocus>
                                    <div class="invalid-feedback">
                                        Password must be at least 8 characters long.
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           required>
                                    <div class="invalid-feedback">
                                        Please confirm your password.
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    Reset Password
                                </button>
                            </form>
                        <?php elseif ($messageType === 'success'): ?>
                            <div class="text-center">
                                <p>Redirecting to login page...</p>
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center">
                                <a href="forgot-password.php" class="btn btn-primary">Request New Reset Link</a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($messageType !== 'success'): ?>
                            <div class="text-center mt-3">
                                <a href="login.php" class="text-decoration-none">
                                    Back to Login
                                </a>
                            </div>
                        <?php endif; ?>
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
        
        // Check if passwords match
        const password = document.getElementById('password')
        const confirmPassword = document.getElementById('confirm_password')
        
        if (password && confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match')
                } else {
                    confirmPassword.setCustomValidity('')
                }
            })
            
            password.addEventListener('input', function() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match')
                } else {
                    confirmPassword.setCustomValidity('')
                }
            })
        }
    })()
    </script>
</body>
</html>
