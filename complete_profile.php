<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if profile is already completed
if (isProfileCompleted()) {
    // Redirect to appropriate dashboard
    if ($_SESSION['user_type'] === 'drrmo_staff') {
        header('Location: municipality.php');
    } elseif ($_SESSION['user_type'] === 'approving_authority') {
        header('Location: approving_authority.php');
    } elseif ($_SESSION['user_type'] === 'emergency_coordinator' || $_SESSION['user_type'] === 'admin') {
        header('Location: pdrrmo.php');
    } else {
        header('Location: login.php');
    }
    exit();
}

$error_message = '';
$success_message = '';
$user_type = $_SESSION['user_type'] ?? '';
$is_drrmo_staff = ($user_type === 'drrmo_staff');
$is_approving_authority = ($user_type === 'approving_authority');

// Handle profile completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $signature_base64 = trim($_POST['signature_base64'] ?? '');
    $change_password = isset($_POST['change_password']) && $_POST['change_password'] === '1';
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Validation
    if (empty($full_name) || empty($position) || empty($contact_number)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $contact_number)) {
        $error_message = 'Please enter a valid contact number.';
    } elseif (empty($signature_base64)) {
        $error_message = 'Please upload your e-signature.';
    } elseif ($is_drrmo_staff && (!isset($_FILES['municipality_logo']) || $_FILES['municipality_logo']['error'] === UPLOAD_ERR_NO_FILE) && empty($_POST['existing_logo'])) {
        // Check if logo already exists in database
        $check_logo_sql = "SELECT logo_url FROM drrmo WHERE drrmoID = ? LIMIT 1";
        $check_logo_stmt = $pdo->prepare($check_logo_sql);
        $check_logo_stmt->execute([$current_user['drrmoID'] ?? null]);
        $existing_logo_check = $check_logo_stmt->fetch();
        
        if (empty($existing_logo_check['logo_url'])) {
            $error_message = 'Please upload the municipality logo.';
        }
    } elseif ($change_password) {
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'Please fill in all password fields.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
        } else {
            // Verify current password
            $user_id = $_SESSION['user_id'];
            $check_sql = "SELECT password FROM users WHERE userID = ? LIMIT 1";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$user_id]);
            $user = $check_stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                $error_message = 'Current password is incorrect.';
            } else {
                // Update profile with password change
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                try {
                    $user_id = $_SESSION['user_id'];
                    
                    // Ensure signature column exists
                    try {
                        $checkSigColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'signature'")->fetch();
                        if (!$checkSigColumn) {
                            $pdo->exec("ALTER TABLE users ADD COLUMN signature LONGTEXT NULL");
                        }
                    } catch (Exception $e) {
                        error_log('Could not create signature column: ' . $e->getMessage());
                    }
                    
                    // Check if profileCompleted column exists
                    $checkColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'profileCompleted'")->fetch();
                    
                    if ($checkColumn) {
                        $update_sql = "UPDATE users SET fullName = ?, position = ?, contactNumber = ?, signature = ?, password = ?, profileCompleted = 1 WHERE userID = ?";
                        $update_stmt = $pdo->prepare($update_sql);
                        $result = $update_stmt->execute([$full_name, $position, $contact_number, $signature_base64, $hashed_password, $user_id]);
                        
                        if (!$result || $update_stmt->rowCount() === 0) {
                            throw new Exception('Failed to update user profile in database');
                        }
                    } else {
                        try {
                            $pdo->exec("ALTER TABLE users ADD COLUMN profileCompleted TINYINT(1) DEFAULT 0");
                        } catch (Exception $e) {
                            error_log('Could not create profileCompleted column: ' . $e->getMessage());
                        }
                        
                        $update_sql = "UPDATE users SET fullName = ?, position = ?, contactNumber = ?, signature = ?, password = ? WHERE userID = ?";
                        $update_stmt = $pdo->prepare($update_sql);
                        $result = $update_stmt->execute([$full_name, $position, $contact_number, $signature_base64, $hashed_password, $user_id]);
                        
                        if (!$result || $update_stmt->rowCount() === 0) {
                            throw new Exception('Failed to update user profile in database');
                        }
                    }
                    
                    // Handle logo upload for drrmo_staff
                    if ($is_drrmo_staff && isset($_FILES['municipality_logo']) && $_FILES['municipality_logo']['error'] === UPLOAD_ERR_OK) {
                        $municipality_id = $_SESSION['municipality_id'] ?? null;
                        if ($municipality_id) {
                            // Use existing logo upload endpoint logic
                            $tmp = $_FILES['municipality_logo']['tmp_name'];
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mime = finfo_file($finfo, $tmp);
                            finfo_close($finfo);
                            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/svg+xml' => 'svg'];
                            
                            if (isset($allowed[$mime])) {
                                $ext = $allowed[$mime];
                                $root = realpath(__DIR__);
                                $destDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logos';
                                if (!is_dir($destDir)) {
                                    mkdir($destDir, 0775, true);
                                }
                                
                                $filename = $municipality_id . '.' . $ext;
                                $destPath = $destDir . DIRECTORY_SEPARATOR . $filename;
                                
                                foreach (glob($destDir . DIRECTORY_SEPARATOR . $municipality_id . '.*') as $old) {
                                    @unlink($old);
                                }
                                
                                if (move_uploaded_file($tmp, $destPath)) {
                                    $relativeUrl = 'assets/logos/' . $filename;
                                    try {
                                        $pdo->query("ALTER TABLE drrmo ADD COLUMN IF NOT EXISTS logo_url VARCHAR(255) NULL");
                                    } catch (Exception $e) {
                                        try {
                                            $cols = $pdo->query("SHOW COLUMNS FROM drrmo LIKE 'logo_url'")->fetchAll();
                                            if (!$cols) {
                                                $pdo->query("ALTER TABLE drrmo ADD COLUMN logo_url VARCHAR(255) NULL");
                                            }
                                        } catch (Exception $ignored) {}
                                    }
                                    $upd = $pdo->prepare('UPDATE drrmo SET logo_url = ? WHERE drrmoID = ?');
                                    $upd->execute([$relativeUrl, $municipality_id]);
                                }
                            }
                        }
                    }
                    
                    // Update session
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['profile_completed'] = true;
                    
                    // Redirect to dashboard
                    if ($_SESSION['user_type'] === 'drrmo_staff') {
                        header('Location: municipality.php');
                    } elseif ($_SESSION['user_type'] === 'approving_authority') {
                        header('Location: approving_authority.php');
                    } elseif ($_SESSION['user_type'] === 'emergency_coordinator' || $_SESSION['user_type'] === 'admin') {
                        header('Location: pdrrmo.php');
                    } else {
                        header('Location: login.php');
                    }
                    exit();
                } catch (Exception $e) {
                    error_log('Profile completion error: ' . $e->getMessage());
                    $error_message = 'Failed to update profile. Please try again.';
                }
            }
        }
    } else {
        // Update profile without password change
        try {
            $user_id = $_SESSION['user_id'];
            
            // Ensure signature column exists
            try {
                $checkSigColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'signature'")->fetch();
                if (!$checkSigColumn) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN signature LONGTEXT NULL");
                }
            } catch (Exception $e) {
                error_log('Could not create signature column: ' . $e->getMessage());
            }
            
            // Check if profileCompleted column exists
            $checkColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'profileCompleted'")->fetch();
            
            if ($checkColumn) {
                $update_sql = "UPDATE users SET fullName = ?, position = ?, contactNumber = ?, signature = ?, profileCompleted = 1 WHERE userID = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $result = $update_stmt->execute([$full_name, $position, $contact_number, $signature_base64, $user_id]);
                
                if (!$result || $update_stmt->rowCount() === 0) {
                    throw new Exception('Failed to update user profile in database');
                }
            } else {
                try {
                    $pdo->exec("ALTER TABLE users ADD COLUMN profileCompleted TINYINT(1) DEFAULT 0");
                } catch (Exception $e) {
                    error_log('Could not create profileCompleted column: ' . $e->getMessage());
                }
                
                $update_sql = "UPDATE users SET fullName = ?, position = ?, contactNumber = ?, signature = ? WHERE userID = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $result = $update_stmt->execute([$full_name, $position, $contact_number, $signature_base64, $user_id]);
                
                if (!$result || $update_stmt->rowCount() === 0) {
                    throw new Exception('Failed to update user profile in database');
                }
            }
            
            // Handle logo upload for drrmo_staff
            if ($is_drrmo_staff && isset($_FILES['municipality_logo']) && $_FILES['municipality_logo']['error'] === UPLOAD_ERR_OK) {
                $municipality_id = $_SESSION['municipality_id'] ?? null;
                if ($municipality_id) {
                    $tmp = $_FILES['municipality_logo']['tmp_name'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $tmp);
                    finfo_close($finfo);
                    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/svg+xml' => 'svg'];
                    
                    if (isset($allowed[$mime])) {
                        $ext = $allowed[$mime];
                        $root = realpath(__DIR__);
                        $destDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logos';
                        if (!is_dir($destDir)) {
                            mkdir($destDir, 0775, true);
                        }
                        
                        $filename = $municipality_id . '.' . $ext;
                        $destPath = $destDir . DIRECTORY_SEPARATOR . $filename;
                        
                        foreach (glob($destDir . DIRECTORY_SEPARATOR . $municipality_id . '.*') as $old) {
                            @unlink($old);
                        }
                        
                        if (move_uploaded_file($tmp, $destPath)) {
                            $relativeUrl = 'assets/logos/' . $filename;
                            try {
                                $pdo->query("ALTER TABLE drrmo ADD COLUMN IF NOT EXISTS logo_url VARCHAR(255) NULL");
                            } catch (Exception $e) {
                                try {
                                    $cols = $pdo->query("SHOW COLUMNS FROM drrmo LIKE 'logo_url'")->fetchAll();
                                    if (!$cols) {
                                        $pdo->query("ALTER TABLE drrmo ADD COLUMN logo_url VARCHAR(255) NULL");
                                    }
                                } catch (Exception $ignored) {}
                            }
                            $upd = $pdo->prepare('UPDATE drrmo SET logo_url = ? WHERE drrmoID = ?');
                            $upd->execute([$relativeUrl, $municipality_id]);
                        }
                    }
                }
            }
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            $_SESSION['profile_completed'] = true;
            
            // Redirect to dashboard
            if ($_SESSION['user_type'] === 'drrmo_staff') {
                header('Location: municipality.php');
            } elseif ($_SESSION['user_type'] === 'approving_authority') {
                header('Location: approving_authority.php');
            } elseif ($_SESSION['user_type'] === 'emergency_coordinator' || $_SESSION['user_type'] === 'admin') {
                header('Location: pdrrmo.php');
            } else {
                header('Location: login.php');
            }
            exit();
        } catch (Exception $e) {
            error_log('Profile completion error: ' . $e->getMessage());
            $error_message = 'Failed to update profile. Please try again.';
        }
    }
}

// Get current user info for pre-filling
$user_id = $_SESSION['user_id'];

// Check if signature column exists before querying
try {
    $checkSigColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'signature'")->fetch();
    if ($checkSigColumn) {
        $user_sql = "SELECT fullName, position, contactNumber, email, signature, drrmoID FROM users WHERE userID = ? LIMIT 1";
    } else {
        $user_sql = "SELECT fullName, position, contactNumber, email, drrmoID FROM users WHERE userID = ? LIMIT 1";
    }
} catch (Exception $e) {
    $user_sql = "SELECT fullName, position, contactNumber, email, drrmoID FROM users WHERE userID = ? LIMIT 1";
}

$user_stmt = $pdo->prepare($user_sql);
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch();

// Get existing logo for drrmo_staff
$existing_logo = null;
if ($is_drrmo_staff && !empty($current_user['drrmoID'])) {
    try {
        $logo_sql = "SELECT logo_url FROM drrmo WHERE drrmoID = ? LIMIT 1";
        $logo_stmt = $pdo->prepare($logo_sql);
        $logo_stmt->execute([$current_user['drrmoID']]);
        $logo_data = $logo_stmt->fetch();
        if ($logo_data && !empty($logo_data['logo_url'])) {
            $existing_logo = $logo_data['logo_url'];
        }
    } catch (Exception $e) {
        error_log('Error fetching logo: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Complete Your Profile - ConnectDRRM</title>
  <link rel="icon" type="image/png" href="assets/logos/LoginLogo.png">
  <link rel="stylesheet" href="assets/css/login.css">
  <link rel="stylesheet" href="assets/css/complete_profile.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .signature-upload-container {
      border: 2px dashed #ddd;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
      background: #f9f9f9;
      min-height: 150px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .signature-preview {
      max-width: 100%;
      max-height: 120px;
      margin-bottom: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    .logo-upload-container {
      border: 2px dashed #ddd;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
      background: #f9f9f9;
      min-height: 150px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .logo-preview {
      max-width: 150px;
      max-height: 150px;
      margin-bottom: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
  </style>
</head>
<body>
  <div class="container" id="container">
    <div class="forms-box login">
      <form method="POST" action="" class="login-form" id="profileForm" enctype="multipart/form-data">
        <div class="logo-container">
          <img src="assets/logos/LoginLogo.png" alt="ConnectDRRM Logo">
        </div>
        <h1>Complete Your Profile</h1>
        <p class="login-subtitle">Please complete your profile to continue</p>
        
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
        
        <div class="input-box">
          <input type="text" name="full_name" class="field" placeholder="<?php echo $is_approving_authority ? 'Head Name' : 'Operator Name'; ?>" required>
          <i class="fa-solid fa-user"></i>
        </div>
        
        <?php if ($is_drrmo_staff): ?>
        <!-- Municipality Logo Upload (Only for DRRMO Staff) -->
        <div class="form-section">
          <label class="form-label">Municipality Logo <span class="text-danger">*</span></label>
          <div class="logo-upload-container">
            <div id="logoPreview" style="display: <?php echo (!empty($existing_logo)) ? 'block' : 'none'; ?>; margin-bottom: 10px;">
              <img id="logoImage" class="logo-preview" alt="Logo Preview" src="<?php echo !empty($existing_logo) ? htmlspecialchars($existing_logo) : ''; ?>">
              <div class="mt-2">
                <button type="button" class="btn btn-sm btn-danger me-2" onclick="clearLogo()">Remove</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="changeLogo()">Change Logo</button>
              </div>
            </div>
            <div id="logoPlaceholder" style="display: <?php echo empty($existing_logo) ? 'block' : 'none'; ?>;">
              <i class="fa-solid fa-image" style="font-size: 48px; color: #999; margin-bottom: 10px;"></i>
              <p style="color: #666; margin: 0;">Click to upload municipality logo</p>
            </div>
            <input type="file" name="municipality_logo" id="municipalityLogo" accept="image/*" style="display: none;" onchange="handleLogoUpload(event)" <?php echo empty($existing_logo) ? 'required' : ''; ?>>
            <input type="hidden" name="existing_logo" id="existingLogo" value="<?php echo !empty($existing_logo) ? htmlspecialchars($existing_logo) : ''; ?>">
            <div id="uploadLogoButtonContainer" style="display: <?php echo empty($existing_logo) ? 'block' : 'none'; ?>;">
              <button type="button" class="btn btn-primary mt-3" onclick="document.getElementById('municipalityLogo').click()">
                <i class="fa-solid fa-upload"></i> Upload Logo
              </button>
            </div>
          </div>
        </div>
        <?php endif; ?>
        
        <div class="input-box">
          <input type="text" name="position" class="field" placeholder="Position / Job Title" required>
          <i class="fa-solid fa-briefcase"></i>
        </div>
        
        <div class="input-box">
          <input type="tel" name="contact_number" class="field" placeholder="Contact Number" required>
          <i class="fa-solid fa-phone"></i>
        </div>
        
        <!-- E-Signature Upload (Required for both) -->
        <div class="form-section">
          <label class="form-label">E-Signature <span class="text-danger">*</span></label>
          <div class="signature-upload-container">
            <div id="signaturePreview" style="display: <?php echo (!empty($current_user['signature'])) ? 'block' : 'none'; ?>; margin-bottom: 10px;">
              <img id="signatureImage" class="signature-preview" alt="Signature Preview" src="<?php echo !empty($current_user['signature']) ? htmlspecialchars($current_user['signature']) : ''; ?>">
              <div class="mt-2">
                <button type="button" class="btn btn-sm btn-danger me-2" onclick="clearSignature()">Remove</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="changeSignature()">Change Signature</button>
              </div>
            </div>
            <div id="signaturePlaceholder" style="display: <?php echo empty($current_user['signature']) ? 'block' : 'none'; ?>;">
              <i class="fa-solid fa-signature" style="font-size: 48px; color: #999; margin-bottom: 10px;"></i>
              <p style="color: #666; margin: 0;">Click to upload your e-signature</p>
            </div>
            <input type="file" id="signatureFile" accept="image/*" style="display: none;" onchange="handleSignatureUpload(event)">
            <input type="hidden" name="signature_base64" id="signatureBase64" value="<?php echo !empty($current_user['signature']) ? htmlspecialchars($current_user['signature']) : ''; ?>" required>
            <div id="uploadButtonContainer" style="display: <?php echo empty($current_user['signature']) ? 'block' : 'none'; ?>;">
              <button type="button" class="btn btn-primary mt-3" onclick="document.getElementById('signatureFile').click()">
                <i class="fa-solid fa-upload"></i> Upload Signature
              </button>
            </div>
          </div>
        </div>
        
        <div class="password-change-section">
          <label class="checkbox-label">
            <input type="checkbox" name="change_password" id="changePassword" value="1">
            <span>Change Password (Optional)</span>
          </label>
          
          <div id="passwordFields" style="display: none;">
            <div class="input-box">
              <input type="password" name="current_password" class="field" placeholder="Current Password">
              <i class="fa-solid fa-lock"></i>
            </div>
            
            <div class="input-box">
              <input type="password" name="new_password" class="field" placeholder="New Password">
              <i class="fa-solid fa-lock"></i>
            </div>
            
            <div class="input-box">
              <input type="password" name="confirm_password" class="field" placeholder="Confirm New Password">
              <i class="fa-solid fa-lock"></i>
            </div>
          </div>
        </div>
        
        <button type="submit" class="btn" name="complete_profile">Complete Profile</button>
      </form>
    </div>
  </div>

  <script>
    let signatureData = '<?php echo !empty($current_user['signature']) ? htmlspecialchars($current_user['signature'], ENT_QUOTES) : ''; ?>';
    
    function handleSignatureUpload(event) {
      const file = event.target.files[0];
      if (!file) return;
      
      if (file.size > 2 * 1024 * 1024) {
        alert('File size must be less than 2MB');
        return;
      }
      
      if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        return;
      }
      
      const reader = new FileReader();
      reader.onload = function(e) {
        signatureData = e.target.result;
        document.getElementById('signatureImage').src = signatureData;
        document.getElementById('signaturePreview').style.display = 'block';
        document.getElementById('signaturePlaceholder').style.display = 'none';
        document.getElementById('uploadButtonContainer').style.display = 'none';
        document.getElementById('signatureBase64').value = signatureData;
      };
      reader.readAsDataURL(file);
    }
    
    function clearSignature() {
      signatureData = '';
      document.getElementById('signaturePreview').style.display = 'none';
      document.getElementById('signaturePlaceholder').style.display = 'block';
      document.getElementById('uploadButtonContainer').style.display = 'block';
      document.getElementById('signatureFile').value = '';
      document.getElementById('signatureBase64').value = '';
    }
    
    function changeSignature() {
      document.getElementById('signatureFile').click();
    }
    
    <?php if ($is_drrmo_staff): ?>
    function handleLogoUpload(event) {
      const file = event.target.files[0];
      if (!file) return;
      
      if (file.size > 2 * 1024 * 1024) {
        alert('File size must be less than 2MB');
        document.getElementById('municipalityLogo').value = '';
        return;
      }
      
      if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        document.getElementById('municipalityLogo').value = '';
        return;
      }
      
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('logoImage').src = e.target.result;
        document.getElementById('logoPreview').style.display = 'block';
        document.getElementById('logoPlaceholder').style.display = 'none';
        document.getElementById('uploadLogoButtonContainer').style.display = 'none';
        document.getElementById('existingLogo').value = ''; // Clear existing logo when new one is uploaded
      };
      reader.readAsDataURL(file);
    }
    
    function clearLogo() {
      document.getElementById('logoPreview').style.display = 'none';
      document.getElementById('logoPlaceholder').style.display = 'block';
      document.getElementById('uploadLogoButtonContainer').style.display = 'block';
      document.getElementById('municipalityLogo').value = '';
      document.getElementById('existingLogo').value = ''; // Clear existing logo reference
    }

    function changeLogo() {
      document.getElementById('municipalityLogo').click();
    }
    <?php endif; ?>
    
    // Toggle password fields
    document.getElementById('changePassword').addEventListener('change', function() {
      const passwordFields = document.getElementById('passwordFields');
      if (this.checked) {
        passwordFields.style.display = 'block';
        passwordFields.querySelectorAll('input').forEach(input => {
          input.required = true;
        });
      } else {
        passwordFields.style.display = 'none';
        passwordFields.querySelectorAll('input').forEach(input => {
          input.required = false;
          input.value = '';
        });
      }
    });
    
    // Form validation
    document.getElementById('profileForm').addEventListener('submit', function(e) {
      if (!document.getElementById('signatureBase64').value) {
        e.preventDefault();
        alert('Please upload your e-signature.');
        return false;
      }
      
      <?php if ($is_drrmo_staff): ?>
      const hasNewLogo = document.getElementById('municipalityLogo').files.length > 0;
      const hasExistingLogo = (document.getElementById('existingLogo').value || '').trim() !== '';
      if (!hasNewLogo && !hasExistingLogo) {
        e.preventDefault();
        alert('Please upload the municipality logo.');
        return false;
      }
      <?php endif; ?>
      
      const changePassword = document.getElementById('changePassword').checked;
      if (changePassword) {
        const newPassword = document.querySelector('[name="new_password"]').value;
        const confirmPassword = document.querySelector('[name="confirm_password"]').value;
        
        if (newPassword !== confirmPassword) {
          e.preventDefault();
          alert('New passwords do not match.');
          return false;
        }
        
        if (newPassword.length < 6) {
          e.preventDefault();
          alert('Password must be at least 6 characters long.');
          return false;
        }
      }
    });
    
    // Clear messages after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
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
