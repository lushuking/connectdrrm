<?php
session_start();
require_once 'config/db.php';
require_once 'config/auth.php';

$error_message = '';
$success_message = '';

// SECURITY: Self-service password reset is disabled.
$error_message = 'For security, password resets are handled by PDRRMO administrators. Please contact PDRRMO to reset your password.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ConnectDRRM - Reset Password</title>
  <link rel="icon" type="image/png" href="assets/logos/LoginLogo.png">
  <link rel="stylesheet" href="assets/css/login.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="container" id="container">
    <div class="forms-box login">
      <form method="POST" action="" class="login-form">
        <h1>Reset Password</h1>
        <p class="login-subtitle">Password reset is handled by PDRRMO</p>
        
        <?php if (!empty($error_message)): ?>
          <div class="error-message">
            <i class="fa-solid fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
          </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
          <div class="success-message">
            <i class="fa-solid fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <p style="margin-top: 10px; font-size: 13px;">Redirecting to login page...</p>
          </div>
        <?php endif; ?>
        
        <div class="button-container" style="margin-top: 20px;">
          <a href="login.php" class="btn" style="text-decoration: none; display: inline-block;">
            Back to Login
          </a>
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
    // no-op
  </script>
</body>
</html>


