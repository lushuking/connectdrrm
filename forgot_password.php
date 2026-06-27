<?php
session_start();
require_once 'config/db.php';
require_once 'config/auth.php';

$error_message = '';
$success_message = '';

// SECURITY: Self-service password reset is disabled.
// Password resets must be handled by PDRRMO administrators.
$is_pdrrmo = isLoggedIn() && (($_SESSION['user_type'] ?? '') === 'emergency_coordinator' || ($_SESSION['user_type'] ?? '') === 'admin');
if ($is_pdrrmo) {
    // Redirect PDRRMO to the correct tool
    header('Location: pdrrmo.php?page=user_management');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ConnectDRRM - Forgot Password</title>
  <link rel="icon" type="image/png" href="assets/logos/LoginLogo.png">
  <link rel="stylesheet" href="assets/css/login.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="container" id="container">
    <div class="forms-box login">
      <form method="POST" action="" class="login-form">
        <h1>Forgot Password</h1>
        <p class="login-subtitle">Password reset is handled by PDRRMO</p>
        
        <?php if (!empty($error_message)): ?>
          <div class="error-message">
            <i class="fa-solid fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
          </div>
        <?php endif; ?>
        
        <div class="success-message">
          <i class="fa-solid fa-info-circle"></i>
          For security, users cannot reset passwords directly. Please contact your PDRRMO administrator to reset your account password.
        </div>
        
        <div class="account-info">
          <p>
            <a href="login.php" style="color: #667eea; text-decoration: none; font-weight: 500;">
              <i class="fa-solid fa-arrow-left"></i> Back to Login
            </a>
          </p>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Remove any URL parameters
    document.addEventListener('DOMContentLoaded', function() {
      if (window.location.search) {
        const url = new URL(window.location);
        url.search = '';
        window.history.replaceState({}, document.title, url.pathname);
      }
    });
  </script>
</body>
</html>


