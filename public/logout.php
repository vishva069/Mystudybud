<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';

$auth = new Auth();
$auth->logout();

// Redirect to login page
header('Location: login.php');
exit;
?>
