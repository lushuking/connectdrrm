<?php
session_start();

// Only redirect if not logging out
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true && !isset($_GET['logout'])) {
    $destination = null;
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'drrmo_staff') {
        $destination = 'municipality.php';
    } elseif (isset($_SESSION['user_type']) && ($_SESSION['user_type'] === 'emergency_coordinator' || $_SESSION['user_type'] === 'admin')) {
        $destination = 'pdrrmo.php';
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'approving_authority') {
        $destination = 'approving_authority.php';
    }

    if ($destination) {
        header('Location: ' . $destination);
        exit();
    } else {
        // Stale/unknown role: reset session so the login form shows instead of a blank page
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        session_destroy();
        session_start();
    }
}

$error_message = '';
$success_message = '';

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success_message = 'You have been successfully logged out.';
}

// Include database connection and auth functions
require_once 'config/db.php';
require_once 'config/auth.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        $result = authenticateUser($username, $password);
        
        if ($result['success']) {
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['username'] = $result['username'];
            $_SESSION['user_type'] = $result['user_type'];
            $_SESSION['full_name'] = $result['full_name'];
            if (isset($result['municipality_id'])) {
                $_SESSION['municipality_id'] = $result['municipality_id'];
            }
            
            // Check if profile is completed
            $profile_completed = $result['profile_completed'] ?? false;
            
            if (!$profile_completed) {
                // Redirect to profile completion page
                header('Location: complete_profile.php');
                exit();
            }
            
            // Redirect based on user type (auto-detected)
            $destination = null;
            if ($result['user_type'] === 'drrmo_staff') {
                $destination = 'municipality.php';
            } elseif ($result['user_type'] === 'emergency_coordinator' || $result['user_type'] === 'admin') {
                $destination = 'pdrrmo.php';
            } elseif ($result['user_type'] === 'approving_authority') {
                $destination = 'approving_authority.php';
            }

            if ($destination) {
                header('Location: ' . $destination);
                exit();
            }

            // Unknown role: do not exit to a blank page
            logoutUser();
            $error_message = 'Your account role is not recognized. Please contact your PDRRMO administrator.';
        } else {
            $error_message = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ConnectDRRM - Login</title>
  <link rel="icon" type="image/png" href="assets/logos/LoginLogo.png">
  <link rel="stylesheet" href="assets/css/login.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="container" id="container">
    <!-- Login Form -->
    <div class="forms-box login">
      <form method="POST" action="" class="login-form">
        <div class="logo-container">
          <img src="assets/logos/LoginLogo.png" alt="ConnectDRRM Logo">
        </div>
        <h1>Login</h1>
        <p class="login-subtitle">Welcome to ConnectDRRM</p>
        
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
          </div>
        <?php endif; ?>
        
        <div class="input-box login-email">
          <input type="email" name="username" class="field field-login-email" placeholder="Email" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
          <i class="fa-solid fa-user"></i>
        </div>
        
        <div class="input-box login-password">
          <input type="password" name="password" class="field field-login-password" placeholder="Password" required>
          <i class="fa-solid fa-lock"></i>
        </div>
        
        <div class="forgot-link">
          <a href="forgot_password.php">Forgot Password?</a>
        </div>
        
        <div class="button-container">
          <button type="submit" class="btn" name="login">Login</button>
        </div>
        
        <div class="account-info">
          <p>Need an account? Contact your PDRRMO administrator.</p>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Clear logout parameter from URL to prevent showing message on refresh
    document.addEventListener('DOMContentLoaded', function() {
      // Remove logout parameter from URL without page reload
      if (window.location.search.includes('logout=success')) {
        const url = new URL(window.location);
        url.searchParams.delete('logout');
        window.history.replaceState({}, document.title, url.pathname + url.search);
      }
      
      // Clear messages after 5 seconds
      setTimeout(() => {
        document.querySelectorAll('.error-message, .success-message').forEach(msg => {
          msg.style.transition = 'opacity 0.4s ease';
          msg.style.opacity = '0';
          setTimeout(() => {
            msg.style.display = 'none';
          }, 400);
        });
      }, 5000);
    });
  </script>
</body>
</html>