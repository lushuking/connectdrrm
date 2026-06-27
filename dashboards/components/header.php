<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
// Determine user type and set appropriate titles
$user_type = $_SESSION['user_type'] ?? 'guest';
$is_pdrrmo = ($user_type === 'emergency_coordinator' || $user_type === 'admin');

$is_approving_authority = ($user_type === 'approving_authority');

$page_titles = [
    'dashboard' => $is_pdrrmo ? 'PDRRMO Dashboard' : ($is_approving_authority ? 'Head of DRRMO Dashboard' : 'Municipality Dashboard'),
    'approvals' => 'Pending Approvals',
    'resources' => 'Resource Management',
    'requests' => 'Resource Requests',
    'hazard' => 'Hazard Information System',
    'reports' => 'Reports',
    'notifications' => 'Notifications',
    'monitor_requests' => 'Monitor All Requests',
    'user_management' => 'User Management'
];
$current_title = $page_titles[$current_page] ?? 'Dashboard';

// Fetch municipality name for welcome message if available
$welcomeMunicipality = null;
try {
    require_once __DIR__ . '/../../config/db.php';
    // Ensure we have municipality_id in session; if not, fetch via users table
    if (!isset($_SESSION['municipality_id']) && isset($_SESSION['user_id'])) {
        $q = $pdo->prepare('SELECT municipalityID FROM users WHERE userID = ? LIMIT 1');
        $q->execute([$_SESSION['user_id']]);
        $u = $q->fetch();
        if ($u && isset($u['municipalityID'])) {
            $_SESSION['municipality_id'] = $u['municipalityID'];
        }
    }
    if (isset($_SESSION['municipality_id'])) {
        // Our schema uses the drrmo table for municipality names and logos
        $stmt = $pdo->prepare('SELECT name, logo_url FROM drrmo WHERE drrmoID = ? LIMIT 1');
        $stmt->execute([$_SESSION['municipality_id']]);
        $row = $stmt->fetch();
        if ($row) {
            $welcomeMunicipality = $row['name'] ?? null;
            $municipalityLogo = $row['logo_url'] ?? null;
        }
    }
} catch (Exception $e) {
    // ignore
}
?>

<header class="page-header">
    <div class="header-content">
        <div class="header-left d-flex align-items-center">
            <button class="mobile-sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar" style="margin-right: var(--spacing-sm);">
                <span class="material-icons">menu</span>
            </button>
            <div>
                <h1 class="page-title"><?php echo htmlspecialchars($current_title); ?></h1>
                <p class="page-description">
                            <?php if ($is_pdrrmo): ?>
                                PDRRMO
                            <?php elseif ($welcomeMunicipality): ?>
                                Welcome, <?php echo htmlspecialchars($welcomeMunicipality); ?>
                            <?php else: ?>
                                Municipality Disaster Risk Reduction Management
                            <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="header-right">
            <div class="header-actions">

                <button class="btn btn-primary" id="quickActionBtn">Quick Action</button>
                <div class="notifications dropdown" id="notificationsDropdown">
                    <button class="icon-btn" id="notificationsBtn" title="Notifications">
                        <span class="material-icons">notifications</span>
                        <span class="badge" id="notifBadge" style="display:none;">0</span>
                    </button>
                    <div class="dropdown-menu notifications-menu">
                        <div class="dropdown-header">Notifications</div>
                        <div class="notifications-list" id="notificationsList">
                            <div class="empty">No notifications</div>
                        </div>
                        <div class="dropdown-footer">
                            <a href="?page=notifications" class="view-all-link">View all</a>
                            <button class="mark-all-read-btn" onclick="markAllNotificationsAsSeen()" title="Mark all as read">Mark all as read</button>
                        </div>
                    </div>
                </div>
                <div class="user-profile dropdown">
                    <div class="user-avatar" style="overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($municipalityLogo)): ?>
                            <img src="<?php echo htmlspecialchars($municipalityLogo); ?>" alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fa-solid fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($is_pdrrmo ? 'ZDS - Admin' : (($welcomeMunicipality ?: 'Municipality') . ' - Admin')); ?></span>
                    <span class="dropdown-arrow">▼</span>
                    <div class="dropdown-menu">
                        <a href="#" class="dropdown-item" onclick="openViewProfileModal(); return false;">
                            <span class="material-icons">person</span>
                            Profile
                        </a>
                        <a href="#" class="dropdown-item" onclick="openEditProfileModal(); return false;">
                            <span class="material-icons">edit</span>
                            Edit Profile
                        </a>
                        <a href="<?php echo $is_pdrrmo ? 'pdrrmo.php?page=settings' : 'municipality.php?page=settings'; ?>" class="dropdown-item">
                            <span class="material-icons">settings</span>
                            Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item logout" onclick="confirmLogout(event);">
                            <span class="material-icons">logout</span>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- View Profile Modal (Read-Only) -->
<div class="modal fade" id="viewProfileModal" tabindex="-1" aria-labelledby="viewProfileLabel" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog modal-dialog-centered" style="width: 60vw !important; max-width: 60vw !important; margin: 0 auto !important;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewProfileLabel">
                    <span class="material-icons me-2">person</span>
                    Municipality Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Logo Section -->
                <div class="card mb-4 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0 d-flex align-items-center">
                            <span class="material-icons me-2">image</span>
                            Municipality Logo
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <div id="viewLogoPreview" style="min-height: 100px;">
                            <img id="viewLogoImage" src="" alt="Municipality Logo" style="display: none; max-width: 150px; max-height: 150px; width: auto; height: auto; border-radius: 8px; margin: 0 auto;">
                            <div id="viewLogoPlaceholder" class="logo-placeholder">
                                <span class="material-icons">image</span>
                                <p>No logo uploaded</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 d-flex align-items-center">
                            <span class="material-icons me-2 text-primary">contact_mail</span>
                            Contact Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- DRRMO Head Section -->
                        <div class="mb-4 pb-3 border-bottom">
                            <h6 class="mb-3 d-flex align-items-center">
                                <span class="material-icons me-2" style="font-size: 20px; color: #667eea;">supervisor_account</span>
                                DRRMO Head (Approving Authority)
                            </h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted">Name</label>
                                    <div class="view-field-value" id="viewDrrmoHead">—</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted">Title/Position</label>
                                    <div class="view-field-value" id="viewDrrmoHeadTitle">—</div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-0">
                                    <label class="form-label fw-bold text-muted">
                                        <span class="material-icons me-1" style="font-size: 18px; vertical-align: middle;">draw</span>
                                        E-Signature
                                    </label>
                                    <div id="viewDrrmoHeadSignaturePreview" style="display: none; text-align: center; margin-top: 10px;">
                                        <img id="viewDrrmoHeadSignatureImg" class="img-fluid border rounded" style="max-height: 80px; max-width: 100%;">
                                    </div>
                                    <div id="viewDrrmoHeadSignaturePlaceholder" class="text-muted text-center" style="padding: 20px;">
                                        No signature uploaded
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Operator Section -->
                        <div class="mb-0">
                            <h6 class="mb-3 d-flex align-items-center">
                                <span class="material-icons me-2" style="font-size: 20px; color: #667eea;">person</span>
                                Operator/Requestor
                            </h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted">Name</label>
                                    <div class="view-field-value" id="viewOperatorName">—</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted">Title/Position</label>
                                    <div class="view-field-value" id="viewOperatorTitle">—</div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-0">
                                    <label class="form-label fw-bold text-muted">
                                        <span class="material-icons me-1" style="font-size: 18px; vertical-align: middle;">draw</span>
                                        E-Signature
                                    </label>
                                    <div id="viewOperatorSignaturePreview" style="display: none; text-align: center; margin-top: 10px;">
                                        <img id="viewOperatorSignatureImg" class="img-fluid border rounded" style="max-height: 80px; max-width: 100%;">
                                    </div>
                                    <div id="viewOperatorSignaturePlaceholder" class="text-muted text-center" style="padding: 20px;">
                                        No signature uploaded
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="closeViewProfileAndEdit();">
                    <span class="material-icons me-1">edit</span>
                    Edit Profile
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileLabel" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog modal-dialog-centered" style="width: 60vw !important; max-width: 60vw !important; margin: 0 auto !important;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileLabel">
                    <span class="material-icons me-2">edit</span>
                    Edit Municipality Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editProfileForm">
                    <!-- Logo Section -->
                    <div class="card mb-4 border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0 d-flex align-items-center">
                                <span class="material-icons me-2">image</span>
                                Municipality Logo
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row justify-content-center">
                                <div class="col-12 col-sm-8 col-md-6 col-lg-4">
                                    <div class="logo-upload-container">
                                        <div class="logo-preview" id="logoPreview" style="min-height: 100px;">
                                            <img id="logoImage" src="" alt="Municipality Logo" style="display: none; max-width: 150px; max-height: 150px; width: auto; height: auto; border-radius: 8px; margin: 0 auto;">
                                            <div id="logoPlaceholder" class="logo-placeholder">
                                                <span class="material-icons">image</span>
                                                <p>No logo uploaded</p>
                                            </div>
                                        </div>
                                        <input type="file" id="logoInput" accept="image/*" class="form-control mt-3" onchange="handleLogoUpload(event)">
                                        <small class="form-text text-muted d-block mt-2 text-center">Recommended: 200x200px, PNG or JPG format</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0 d-flex align-items-center">
                                <span class="material-icons me-2 text-primary">contact_mail</span>
                                Contact Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- DRRMO Head Section -->
                            <div class="mb-4 pb-3 border-bottom">
                                <h6 class="mb-3 d-flex align-items-center">
                                    <span class="material-icons me-2" style="font-size: 20px; color: #667eea;">supervisor_account</span>
                                    DRRMO Head (Approving Authority)
                                </h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="drrmoHead" class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="drrmoHead" placeholder="e.g., Director Jane Smith" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="drrmoHeadTitle" class="form-label fw-bold">Title/Position <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="drrmoHeadTitle" placeholder="e.g., Municipal DRRMO" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 mb-0">
                                        <label for="drrmoHeadSignature" class="form-label fw-bold">
                                            <span class="material-icons me-1" style="font-size: 18px; vertical-align: middle;">draw</span>
                                            E-Signature
                                            <small class="text-muted fw-normal">(Auto-filled in resource requests)</small>
                                        </label>
                                        <div class="signature-upload-container border rounded p-3" style="background-color: #f8f9fa; min-height: 120px;">
                                            <div id="drrmoHeadSignaturePreview" style="display: none; margin-bottom: 15px; text-align: center;">
                                                <img id="drrmoHeadSignatureImg" class="img-fluid border rounded" style="max-height: 80px; max-width: 100%;">
                                            </div>
                                            <div class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="drrmoHeadSignatureBtn">
                                                    <span class="material-icons me-1" style="font-size: 18px;">upload_file</span>
                                                    <span id="drrmoHeadSignatureBtnText">Upload Signature</span>
                                                </button>
                                                <input type="file" id="drrmoHeadSignatureFile" accept="image/*" style="display: none;">
                                                <button type="button" class="btn btn-sm btn-danger ms-2" id="drrmoHeadSignatureRemove" style="display: none;" onclick="clearProfileSignature('drrmoHead')">
                                                    <span class="material-icons me-1" style="font-size: 18px;">delete</span>
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Operator Section -->
                            <div class="mb-0" id="operatorSection" style="<?php echo ($is_approving_authority ? 'display: none;' : ''); ?>">
                                <h6 class="mb-3 d-flex align-items-center">
                                    <span class="material-icons me-2" style="font-size: 20px; color: #667eea;">person</span>
                                    Operator/Requestor
                                </h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="operatorName" class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="operatorName" placeholder="e.g., Juan Dela Cruz" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="operatorTitle" class="form-label fw-bold">Title/Position <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="operatorTitle" placeholder="e.g., DRRM Officer II" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 mb-0">
                                        <label for="operatorSignature" class="form-label fw-bold">
                                            <span class="material-icons me-1" style="font-size: 18px; vertical-align: middle;">draw</span>
                                            E-Signature
                                            <small class="text-muted fw-normal">(Auto-filled in resource requests)</small>
                                        </label>
                                        <div class="signature-upload-container border rounded p-3" style="background-color: #f8f9fa; min-height: 120px;">
                                            <div id="operatorSignaturePreview" style="display: none; margin-bottom: 15px; text-align: center;">
                                                <img id="operatorSignatureImg" class="img-fluid border rounded" style="max-height: 80px; max-width: 100%;">
                                            </div>
                                            <div class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="operatorSignatureBtn">
                                                    <span class="material-icons me-1" style="font-size: 18px;">upload_file</span>
                                                    <span id="operatorSignatureBtnText">Upload Signature</span>
                                                </button>
                                                <input type="file" id="operatorSignatureFile" accept="image/*" style="display: none;">
                                                <button type="button" class="btn btn-sm btn-danger ms-2" id="operatorSignatureRemove" style="display: none;" onclick="clearProfileSignature('operator')">
                                                    <span class="material-icons me-1" style="font-size: 18px;">delete</span>
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Alert -->
                    <div class="alert alert-success d-flex align-items-start">
                        <span class="material-icons me-2">check_circle</span>
                        <div>
                            <strong>Profile Information:</strong>
                            <ul class="mb-0 mt-2 small">
                                <li>All information saved here will be auto-filled in resource requests</li>
                                <li>E-signatures will automatically appear in request documents</li>
                                <li>You can update any field anytime by editing your profile</li>
                            </ul>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="showProfileConfirmationModal()">
                    <span class="material-icons me-1">save</span>
                    Save Profile
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Profile Confirmation Modal -->
<div class="modal fade" id="profileConfirmationModal" tabindex="-1" aria-labelledby="profileConfirmationLabel" aria-hidden="true" style="z-index: 10051;">
    <div class="modal-dialog modal-dialog-centered" style="width: 90vw !important; max-width: 500px !important;">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileConfirmationLabel">
                    <span class="material-icons me-2" style="vertical-align: middle;">check_circle</span>
                    Confirm Profile Save
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-flex align-items-start mb-4">
                    <span class="material-icons me-3" style="color: #0d6efd; flex-shrink: 0;">info</span>
                    <div>
                        <strong>Profile Update</strong>
                        <p class="mb-0 mt-2 small">Your profile information will be saved and used for all future resource requests.</p>
                    </div>
                </div>
                
                <div class="profile-confirmation-details">
                    <h6 class="mb-3 text-muted">Review your information:</h6>
                    
                    <!-- DRRMO Head Section -->
                    <div class="card mb-3 border-0 bg-light">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-start">
                                <span class="material-icons me-2" style="color: #667eea; flex-shrink: 0;">supervisor_account</span>
                                <div class="flex-grow-1">
                                    <h6 class="mb-2">DRRMO Head (Approving Authority)</h6>
                                    <p class="mb-1 small"><strong>Name:</strong> <span id="confirmDrrmoHead">-</span></p>
                                    <p class="mb-0 small"><strong>Title:</strong> <span id="confirmDrrmoHeadTitle">-</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Operator Section -->
                    <div class="card mb-0 border-0 bg-light">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-start">
                                <span class="material-icons me-2" style="color: #667eea; flex-shrink: 0;">person</span>
                                <div class="flex-grow-1">
                                    <h6 class="mb-2">Operator/Requestor</h6>
                                    <p class="mb-1 small"><strong>Name:</strong> <span id="confirmOperatorName">-</span></p>
                                    <p class="mb-0 small"><strong>Title:</strong> <span id="confirmOperatorTitle">-</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <span class="material-icons me-1" style="font-size: 18px; vertical-align: middle;">close</span>
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmAndSaveProfile()">
                    <span class="material-icons me-1" style="font-size: 18px; vertical-align: middle;">check</span>
                    Confirm & Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" style="z-index: 10052;">
    <div class="modal-dialog modal-dialog-centered" style="width: 90vw !important; max-width: 400px !important;">
        <div class="modal-content">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title" id="successModalLabel">
                    <span class="material-icons me-2" style="vertical-align: middle;">check_circle</span>
                    Success
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <span class="material-icons" style="font-size: 64px; color: #198754;">check_circle</span>
                </div>
                <p id="successMessage" class="mb-0" style="font-size: 1.1rem; color: #495057;"></p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success w-100" data-bs-dismiss="modal">
                                    <span class="material-icons me-1" style="font-size: 18px; vertical-align: middle;">done</span>
                                    OK
                                </button>
                            </div>
                        </div>
                    </div>
                </div>



                <style>
/* Edit Profile Modal Styling */
#editProfileModal {
    z-index: 10050 !important;
}

#editProfileModal .modal-backdrop {
    z-index: 10049 !important;
}

#editProfileModal .modal-dialog {
    width: 60vw !important;
    max-width: 60vw !important;
    margin: 0 auto !important;
}

/* Force modal width */
#editProfileModal.modal.show .modal-dialog {
    width: 60vw !important;
    max-width: 60vw !important;
}

#editProfileModal .modal-content {
    height: auto !important;
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    max-width: none !important;
    max-height: 92vh !important;
    border-radius: 12px !important;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important;
    border: none !important;
}

#editProfileModal .modal-body {
    flex: 1 1 auto !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    padding: 2rem !important;
    background: #f8f9fa !important;
}

/* Hide modal body scrollbar but keep scrolling */
#editProfileModal .modal-body::-webkit-scrollbar { display: none; }
#editProfileModal .modal-body { scrollbar-width: none; -ms-overflow-style: none; }

/* Explicitly override any global modal content constraints for this modal */
#editProfileModal .modal-dialog .modal-content,
#editProfileModal.modal .modal-content {
    width: 100% !important;
    max-width: none !important;
}

#editProfileModal .modal-header,
#editProfileModal .modal-footer {
    flex-shrink: 0 !important;
}

/* Prevent page scrollbar when modal is open */
body.modal-open {
    overflow: hidden !important;
    padding-right: 0 !important;
}

/* Also prevent root html from scrolling when modal is open */
html.modal-open {
    overflow: hidden !important;
    padding-right: 0 !important;
}

#editProfileModal .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    border-radius: 12px 12px 0 0 !important;
    padding: 1.5rem !important;
}

#editProfileModal .modal-title {
    font-weight: 600 !important;
    font-size: 1.25rem !important;
}

#editProfileModal .btn-close {
    filter: invert(1) !important;
    opacity: 0.8 !important;
}

#editProfileModal .btn-close:hover {
    opacity: 1 !important;
}

#editProfileModal .modal-body {
    padding: 2rem !important;
    background: #f8f9fa !important;
}

#editProfileModal .modal-footer {
    background: white !important;
    border-radius: 0 0 12px 12px !important;
    padding: 1.5rem !important;
    border-top: 1px solid #e9ecef !important;
}

.logo-upload-container {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    background-color: #ffffff;
    transition: all 0.3s ease;
}

.logo-upload-container:hover {
    border-color: #667eea;
    background-color: #f8f9ff;
}

.logo-placeholder {
    color: #6c757d;
}

.logo-placeholder .material-icons {
    font-size: 48px;
    margin-bottom: 10px;
}

.logo-placeholder p {
    margin: 0;
    font-size: 14px;
}

.logo-preview {
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.logo-preview img {
    max-width: 150px !important;
    max-height: 150px !important;
    width: auto !important;
    height: auto !important;
}

/* Form styling */
#editProfileModal .form-label {
    font-weight: 600 !important;
    color: #495057 !important;
    margin-bottom: 0.5rem !important;
}

#editProfileModal .form-control {
    border-radius: 8px !important;
    border: 2px solid #e9ecef !important;
    padding: 0.75rem !important;
    transition: all 0.3s ease !important;
}

#editProfileModal .form-control:focus {
    border-color: #667eea !important;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
}

#editProfileModal .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 0.75rem 2rem !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
}

#editProfileModal .btn-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3) !important;
}

#editProfileModal .btn-secondary {
    border-radius: 8px !important;
    padding: 0.75rem 2rem !important;
    font-weight: 600 !important;
}

/* View Profile Modal Styling */
#viewProfileModal {
    z-index: 10050 !important;
}

#viewProfileModal .modal-backdrop {
    z-index: 10049 !important;
}

#viewProfileModal .modal-dialog {
    width: 60vw !important;
    max-width: 60vw !important;
    margin: 0 auto !important;
}

#viewProfileModal.modal.show .modal-dialog {
    width: 60vw !important;
    max-width: 60vw !important;
}

#viewProfileModal .modal-content {
    height: auto !important;
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    max-width: none !important;
    max-height: 92vh !important;
    border-radius: 12px !important;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important;
    border: none !important;
}

#viewProfileModal .modal-body {
    flex: 1 1 auto !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    padding: 2rem !important;
    background: #f8f9fa !important;
}

#viewProfileModal .modal-body::-webkit-scrollbar { display: none; }
#viewProfileModal .modal-body { scrollbar-width: none; -ms-overflow-style: none; }

#viewProfileModal .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    border-radius: 12px 12px 0 0 !important;
    padding: 1.5rem !important;
}

#viewProfileModal .modal-title {
    font-weight: 600 !important;
    font-size: 1.25rem !important;
}

#viewProfileModal .btn-close {
    filter: invert(1) !important;
    opacity: 0.8 !important;
}

#viewProfileModal .view-field-value {
    padding: 0.5rem;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    min-height: 38px;
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    color: #495057;
}

#viewProfileModal .view-field-value:empty::before {
    content: '—';
    color: #999;
}

/* Profile Confirmation Modal Styling */
#profileConfirmationModal {
    z-index: 10051 !important;
}

#profileConfirmationModal .modal-dialog {
    width: 90vw !important;
    max-width: 500px !important;
}

#profileConfirmationModal .modal-content {
    border-radius: 12px !important;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important;
    border: none !important;
}

#profileConfirmationModal .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    border-radius: 12px 12px 0 0 !important;
    padding: 1.5rem !important;
    border: none !important;
}

#profileConfirmationModal .modal-title {
    font-weight: 600 !important;
    font-size: 1.1rem !important;
    display: flex !important;
    align-items: center !important;
}

#profileConfirmationModal .btn-close-white {
    filter: invert(1) !important;
    opacity: 0.8 !important;
}

#profileConfirmationModal .btn-close-white:hover {
    opacity: 1 !important;
}

#profileConfirmationModal .modal-body {
    padding: 2rem !important;
    background: #f8f9fa !important;
}

#profileConfirmationModal .modal-footer {
    background: white !important;
    border-radius: 0 0 12px 12px !important;
    padding: 1.5rem !important;
    border-top: 1px solid #e9ecef !important;
}

#profileConfirmationModal .card {
    border-radius: 8px !important;
    transition: all 0.3s ease !important;
}

#profileConfirmationModal .card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
}

#profileConfirmationModal .alert {
    border-radius: 8px !important;
    border: none !important;
    background: #e7f3ff !important;
}

#profileConfirmationModal .profile-confirmation-details h6 {
    font-weight: 600 !important;
    color: #495057 !important;
}

#profileConfirmationModal .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: none !important;
    padding: 0.6rem 1.5rem !important;
    font-weight: 500 !important;
    transition: all 0.3s ease !important;
}

#profileConfirmationModal .btn-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4) !important;
}

#profileConfirmationModal .btn-secondary {
    border-radius: 8px !important;
    padding: 0.6rem 1.5rem !important;
    font-weight: 500 !important;
}

/* Success Modal Styling */
#successModal {
    z-index: 10052 !important;
}

#successModal .modal-dialog {
    width: 90vw !important;
    max-width: 400px !important;
}

#successModal .modal-content {
    border-radius: 12px !important;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important;
    border: none !important;
}

#successModal .modal-header {
    background: linear-gradient(135deg, #198754 0%, #157347 100%) !important;
    color: white !important;
    border-radius: 12px 12px 0 0 !important;
    padding: 1.5rem !important;
    border: none !important;
}

#successModal .modal-title {
    font-weight: 600 !important;
    font-size: 1.1rem !important;
    display: flex !important;
    align-items: center !important;
}

#successModal .btn-close-white {
    filter: invert(1) !important;
    opacity: 0.8 !important;
}

#successModal .btn-close-white:hover {
    opacity: 1 !important;
}

#successModal .btn-success {
    background: linear-gradient(135deg, #198754 0%, #157347 100%) !important;
    border: none !important;
    padding: 0.75rem 1.5rem !important;
    font-weight: 500 !important;
    transition: all 0.3s ease !important;
}

#successModal .btn-success:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 16px rgba(25, 135, 84, 0.4) !important;
}
</style>

<script>
// Municipality Profile Management
let municipalityProfile = {};

// Current municipality name from server (used as key for mappings)
const CURRENT_MUNICIPALITY = <?php echo json_encode($welcomeMunicipality ?: ''); ?>;
const CURRENT_MUNICIPALITY_ID = <?php echo json_encode($_SESSION['municipality_id'] ?? null); ?>;
const normalizeMunicipality = (s)=>String(s||'')
  .replace(/^(Municipality of|City of)\s+/i,'')
  .replace(/\s+City$/i,'')
  .replace(/\b[A-Z]{0,3}DRRMO\b/ig,'')
  .replace(/\s{2,}/g,' ')
  .trim()
  .toLowerCase();

// Load profile from localStorage on page load
document.addEventListener('DOMContentLoaded', function() {
    loadMunicipalityProfile();
    // Also load from server to ensure persistence across devices
    try { loadMunicipalityProfileFromServer(); } catch(_) {}
});

// Open view-only profile modal
function openViewProfileModal() {
    loadMunicipalityProfile();
    
    // Populate view fields
    const profile = getMunicipalityProfile();
    
    // Logo
    const viewLogoImg = document.getElementById('viewLogoImage');
    const viewLogoPlaceholder = document.getElementById('viewLogoPlaceholder');
    if (profile.logo) {
        viewLogoImg.src = profile.logo;
        viewLogoImg.style.display = 'block';
        viewLogoPlaceholder.style.display = 'none';
    } else {
        viewLogoImg.style.display = 'none';
        viewLogoPlaceholder.style.display = 'block';
    }
    
    // DRRMO Head
    document.getElementById('viewDrrmoHead').textContent = profile.drrmoHead || '—';
    document.getElementById('viewDrrmoHeadTitle').textContent = profile.drrmoHeadTitle || '—';
    if (profile.drrmoHeadSignature) {
        document.getElementById('viewDrrmoHeadSignatureImg').src = profile.drrmoHeadSignature;
        document.getElementById('viewDrrmoHeadSignaturePreview').style.display = 'block';
        document.getElementById('viewDrrmoHeadSignaturePlaceholder').style.display = 'none';
    } else {
        document.getElementById('viewDrrmoHeadSignaturePreview').style.display = 'none';
        document.getElementById('viewDrrmoHeadSignaturePlaceholder').style.display = 'block';
    }
    
    // Operator
    document.getElementById('viewOperatorName').textContent = profile.operatorName || '—';
    document.getElementById('viewOperatorTitle').textContent = profile.operatorTitle || '—';
    if (profile.operatorSignature) {
        document.getElementById('viewOperatorSignatureImg').src = profile.operatorSignature;
        document.getElementById('viewOperatorSignaturePreview').style.display = 'block';
        document.getElementById('viewOperatorSignaturePlaceholder').style.display = 'none';
    } else {
        document.getElementById('viewOperatorSignaturePreview').style.display = 'none';
        document.getElementById('viewOperatorSignaturePlaceholder').style.display = 'block';
    }
    
    // Try to load from server if not in local storage (update view fields after loading)
    setTimeout(() => {
        try {
            loadMunicipalityProfileFromServer();
            setTimeout(() => {
                const updatedProfile = getMunicipalityProfile();
                if (updatedProfile.drrmoHead) document.getElementById('viewDrrmoHead').textContent = updatedProfile.drrmoHead || '—';
                if (updatedProfile.drrmoHeadTitle) document.getElementById('viewDrrmoHeadTitle').textContent = updatedProfile.drrmoHeadTitle || '—';
                if (updatedProfile.operatorName) document.getElementById('viewOperatorName').textContent = updatedProfile.operatorName || '—';
                if (updatedProfile.operatorTitle) document.getElementById('viewOperatorTitle').textContent = updatedProfile.operatorTitle || '—';
                // Update logo if loaded from server
                if (updatedProfile.logo && !profile.logo) {
                    const viewLogoImg = document.getElementById('viewLogoImage');
                    const viewLogoPlaceholder = document.getElementById('viewLogoPlaceholder');
                    viewLogoImg.src = updatedProfile.logo;
                    viewLogoImg.style.display = 'block';
                    viewLogoPlaceholder.style.display = 'none';
                }
                // Update e-signatures if loaded from server (from profile completion)
                if (updatedProfile.drrmoHeadSignature) {
                    const el = document.getElementById('viewDrrmoHeadSignatureImg');
                    const prev = document.getElementById('viewDrrmoHeadSignaturePreview');
                    const ph = document.getElementById('viewDrrmoHeadSignaturePlaceholder');
                    if (el && prev && ph) {
                        el.src = updatedProfile.drrmoHeadSignature;
                        prev.style.display = 'block';
                        ph.style.display = 'none';
                    }
                }
                if (updatedProfile.operatorSignature) {
                    const el = document.getElementById('viewOperatorSignatureImg');
                    const prev = document.getElementById('viewOperatorSignaturePreview');
                    const ph = document.getElementById('viewOperatorSignaturePlaceholder');
                    if (el && prev && ph) {
                        el.src = updatedProfile.operatorSignature;
                        prev.style.display = 'block';
                        ph.style.display = 'none';
                    }
                }
            }, 500);
        } catch(e) {}
    }, 100);
    
    // Ensure modal appears at the front
    const modalElement = document.getElementById('viewProfileModal');
    if (modalElement) {
        modalElement.style.zIndex = '10050';
        
        // Create and configure backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.style.zIndex = '10049';
        document.body.appendChild(backdrop);
        
        // Show modal
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: false, // We're handling backdrop manually
            keyboard: true,
            focus: true
        });
        modal.show();
        
        // Clean up backdrop when modal is hidden
        modalElement.addEventListener('hidden.bs.modal', function() {
            if (backdrop && backdrop.parentNode) {
                backdrop.parentNode.removeChild(backdrop);
            }
        });
    }
}

// Close view modal and open edit modal
function closeViewProfileAndEdit() {
    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewProfileModal'));
    if (viewModal) viewModal.hide();
    
    setTimeout(() => {
        openEditProfileModal();
    }, 300);
}

function openEditProfileModal() {
    loadMunicipalityProfile();
    
    // Ensure modal appears at the front
    const modalElement = document.getElementById('editProfileModal');
    if (modalElement) {
        modalElement.style.zIndex = '10050';
        
        // Check if user is approving_authority and handle operator fields
        const operatorSection = document.getElementById('operatorSection');
        const isApprovingAuthority = operatorSection && operatorSection.style.display === 'none';
        
        if (isApprovingAuthority) {
            // Remove required attribute from operator fields for approving_authority
            const operatorNameEl = document.getElementById('operatorName');
            const operatorTitleEl = document.getElementById('operatorTitle');
            if (operatorNameEl) operatorNameEl.removeAttribute('required');
            if (operatorTitleEl) operatorTitleEl.removeAttribute('required');
        }
        
        // Create and configure backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.style.zIndex = '10049';
        document.body.appendChild(backdrop);
        
        // Show modal
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: false, // We're handling backdrop manually
            keyboard: true,
            focus: true
        });
        modal.show();
        
        // Clean up backdrop when modal is hidden
        modalElement.addEventListener('hidden.bs.modal', function() {
            if (backdrop && backdrop.parentNode) {
                backdrop.parentNode.removeChild(backdrop);
            }
        });
    }
}

function loadMunicipalityProfile() {
    const saved = localStorage.getItem('municipalityProfile');
    if (saved) {
        municipalityProfile = JSON.parse(saved);
        
        // Populate form fields
        document.getElementById('drrmoHead').value = municipalityProfile.drrmoHead || '';
        document.getElementById('drrmoHeadTitle').value = municipalityProfile.drrmoHeadTitle || '';
        const operatorNameEl = document.getElementById('operatorName');
        if (operatorNameEl) operatorNameEl.value = municipalityProfile.operatorName || '';
        const operatorTitleEl = document.getElementById('operatorTitle');
        if (operatorTitleEl) operatorTitleEl.value = municipalityProfile.operatorTitle || '';
        
        // Show logo if exists
        if (municipalityProfile.logo) {
            document.getElementById('logoImage').src = municipalityProfile.logo;
            document.getElementById('logoImage').style.display = 'block';
            document.getElementById('logoPlaceholder').style.display = 'none';
        } else {
            document.getElementById('logoImage').style.display = 'none';
            document.getElementById('logoPlaceholder').style.display = 'block';
        }
        
        // Load signatures
        if (municipalityProfile.operatorSignature) {
            showProfileSignaturePreview(municipalityProfile.operatorSignature, 'operator');
        }
        if (municipalityProfile.drrmoHeadSignature) {
            showProfileSignaturePreview(municipalityProfile.drrmoHeadSignature, 'drrmoHead');
        }
    }
    
    // Initialize signature upload handlers
    initializeProfileSignatureUploads();
}

async function loadMunicipalityProfileFromServer() {
    try {
        const res = await fetch('config/get_municipality_profile.php', { credentials: 'same-origin' });
        const j = await res.json();
        if (j && j.success && j.data && j.data.profile) {
            const p = j.data.profile;
            // Update form fields from server
            const dHead = document.getElementById('drrmoHead');
            if (dHead) dHead.value = p.drrmo_head || '';
            const dHeadTitle = document.getElementById('drrmoHeadTitle');
            if (dHeadTitle) dHeadTitle.value = p.drrmo_head_title || '';
            const opName = document.getElementById('operatorName');
            if (opName) opName.value = p.operator_name || '';
            const opTitle = document.getElementById('operatorTitle');
            if (opTitle) opTitle.value = p.operator_title || '';

            // Update logo preview from server if present and no local override
            if (p.logo_url) {
                const img = document.getElementById('logoImage');
                const ph = document.getElementById('logoPlaceholder');
                if (img && ph) {
                    img.src = p.logo_url + '?t=' + Date.now();
                    img.style.display = 'block';
                    ph.style.display = 'none';
                }
            }

            // Sync to localStorage for quicker UX (include e-signatures from profile completion so they show inside the system)
            try {
                municipalityProfile = Object.assign({}, municipalityProfile, {
                    drrmoHead: p.drrmo_head || '',
                    drrmoHeadTitle: p.drrmo_head_title || '',
                    operatorName: p.operator_name || '',
                    operatorTitle: p.operator_title || '',
                    logo: p.logo_url || municipalityProfile.logo || '',
                    operatorSignature: p.operator_signature || municipalityProfile.operatorSignature || '',
                    drrmoHeadSignature: p.drrmo_head_signature || municipalityProfile.drrmoHeadSignature || ''
                });
                localStorage.setItem('municipalityProfile', JSON.stringify(municipalityProfile));
                // Update signature previews in edit form if present
                if (municipalityProfile.operatorSignature && typeof showProfileSignaturePreview === 'function') {
                    showProfileSignaturePreview(municipalityProfile.operatorSignature, 'operator');
                }
                if (municipalityProfile.drrmoHeadSignature && typeof showProfileSignaturePreview === 'function') {
                    showProfileSignaturePreview(municipalityProfile.drrmoHeadSignature, 'drrmoHead');
                }
            } catch(_) {}
        }
    } catch(_) {}
}

function handleLogoUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Preview immediately
    try {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logoImage').src = e.target.result;
            document.getElementById('logoImage').style.display = 'block';
            document.getElementById('logoPlaceholder').style.display = 'none';
        };
        reader.readAsDataURL(file);
    } catch(_) {}

    // Upload to server and persist in DB
    const fd = new FormData();
    fd.append('logo', file);
    fetch('config/upload_municipality_logo.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    }).then(r => r.json()).then(j => {
        if (j && j.success && j.url) {
            // Store URL reference in local profile for convenience
            municipalityProfile.logo = j.url;
            try { localStorage.setItem('municipalityProfile', JSON.stringify(municipalityProfile)); } catch(_) {}
            // Ensure preview uses server URL
            const img = document.getElementById('logoImage');
            if (img) img.src = j.url + '?t=' + Date.now();
        } else {
            alert('Logo upload failed' + (j && j.error && j.error.message ? ': ' + j.error.message : ''));
        }
    }).catch(() => alert('Logo upload failed'));
}

function showProfileConfirmationModal() {
    // Collect current form data
    const drrmoHead = document.getElementById('drrmoHead').value || '-';
    const drrmoHeadTitle = document.getElementById('drrmoHeadTitle').value || '-';
    const operatorName = (document.getElementById('operatorName') || {}).value || '-';
    const operatorTitle = (document.getElementById('operatorTitle') || {}).value || '-';
    
    // Populate confirmation modal with the data
    document.getElementById('confirmDrrmoHead').textContent = drrmoHead;
    document.getElementById('confirmDrrmoHeadTitle').textContent = drrmoHeadTitle;
    document.getElementById('confirmOperatorName').textContent = operatorName;
    document.getElementById('confirmOperatorTitle').textContent = operatorTitle;
    
    // Close edit modal and show confirmation modal
    const editModal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
    if (editModal) {
        editModal.hide();
    }
    
    // Show confirmation modal
    setTimeout(() => {
        const confirmModal = new bootstrap.Modal(document.getElementById('profileConfirmationModal'), {
            backdrop: 'static',
            keyboard: false,
            focus: true
        });
        confirmModal.show();
    }, 300);
}

function confirmAndSaveProfile() {
    // Close confirmation modal
    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('profileConfirmationModal'));
    if (confirmModal) {
        confirmModal.hide();
    }
    
    // Proceed with saving
    setTimeout(() => {
        saveMunicipalityProfile();
    }, 300);
}

function showSuccessModal(message) {
    // Set the success message
    document.getElementById('successMessage').textContent = message;
    
    // Show the success modal
    const successModal = new bootstrap.Modal(document.getElementById('successModal'), {
        backdrop: 'static',
        keyboard: false,
        focus: true
    });
    successModal.show();
}

function saveMunicipalityProfile() {
    // Collect form data
    municipalityProfile.drrmoHead = document.getElementById('drrmoHead').value;
    municipalityProfile.drrmoHeadTitle = document.getElementById('drrmoHeadTitle').value;
    
    // Only collect operator fields if user is not approving_authority
    const operatorSection = document.getElementById('operatorSection');
    const isApprovingAuthority = operatorSection && operatorSection.style.display === 'none';
    
    if (!isApprovingAuthority) {
        municipalityProfile.operatorName = (document.getElementById('operatorName')||{}).value || '';
        municipalityProfile.operatorTitle = (document.getElementById('operatorTitle')||{}).value || '';
    }
    
    // Save signatures (they are stored in municipalityProfile object when uploaded)
    // These are already in the object from handleProfileSignatureUpload
    
    // Save to localStorage
    localStorage.setItem('municipalityProfile', JSON.stringify(municipalityProfile));

    // Persist to server
    try {
        const updateData = {
            drrmoHead: municipalityProfile.drrmoHead || '',
            drrmoHeadTitle: municipalityProfile.drrmoHeadTitle || ''
        };
        
        // Only include operator fields if user is not approving_authority
        if (!isApprovingAuthority) {
            updateData.operatorName = municipalityProfile.operatorName || '';
            updateData.operatorTitle = municipalityProfile.operatorTitle || '';
        }
        
        fetch('config/update_municipality_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(updateData)
        }).then(r => r.json()).then(j => {
            if (!j || !j.success) {
                console.warn('Profile save server warning:', j && j.error ? j.error.message : 'unknown');
            }
        }).catch(() => {});
    } catch(_) {}

    // Also persist into a municipality-specific officials map (like logos)
    try {
        const key = normalizeMunicipality(CURRENT_MUNICIPALITY);
        let map = {};
        try { map = JSON.parse(localStorage.getItem('municipalityOfficials') || '{}') || {}; } catch(_) {}
        if (key) {
            map[key] = {
                operatorName: municipalityProfile.operatorName || '',
                operatorTitle: municipalityProfile.operatorTitle || '',
                drrmoHead: municipalityProfile.drrmoHead || '',
                drrmoHeadTitle: municipalityProfile.drrmoHeadTitle || ''
            };
        }
        localStorage.setItem('municipalityOfficials', JSON.stringify(map));

        // Also index by municipality ID if available
        if (CURRENT_MUNICIPALITY_ID) {
            let byId = {};
            try { byId = JSON.parse(localStorage.getItem('municipalityOfficialsById') || '{}') || {}; } catch(_) {}
            byId[String(CURRENT_MUNICIPALITY_ID)] = {
                operatorName: municipalityProfile.operatorName || '',
                operatorTitle: municipalityProfile.operatorTitle || '',
                drrmoHead: municipalityProfile.drrmoHead || '',
                drrmoHeadTitle: municipalityProfile.drrmoHeadTitle || ''
            };
            localStorage.setItem('municipalityOfficialsById', JSON.stringify(byId));
        }
    } catch(_) {}
    
    // Show success message in a styled modal
    showSuccessModal('Municipality profile saved successfully!');
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
    modal.hide();
    
    // Update header if needed
    updateHeaderWithProfile();
}

function updateHeaderWithProfile() {
    // Header will continue to use database values
    // This function can be used for other profile-related updates if needed
}

// Function to get municipality profile data (for use in other parts of the app)
function getMunicipalityProfile() {
    const saved = localStorage.getItem('municipalityProfile');
    return saved ? JSON.parse(saved) : {};
}

// Profile Signature Upload Functions
function initializeProfileSignatureUploads() {
    // Operator signature
    const operatorBtn = document.getElementById('operatorSignatureBtn');
    const operatorFile = document.getElementById('operatorSignatureFile');
    if (operatorBtn && operatorFile) {
        operatorBtn.addEventListener('click', () => operatorFile.click());
        operatorFile.addEventListener('change', (e) => handleProfileSignatureUpload(e, 'operator'));
    }
    
    // DRRMO Head signature
    const drrmoHeadBtn = document.getElementById('drrmoHeadSignatureBtn');
    const drrmoHeadFile = document.getElementById('drrmoHeadSignatureFile');
    if (drrmoHeadBtn && drrmoHeadFile) {
        drrmoHeadBtn.addEventListener('click', () => drrmoHeadFile.click());
        drrmoHeadFile.addEventListener('change', (e) => handleProfileSignatureUpload(e, 'drrmoHead'));
    }
}

function handleProfileSignatureUpload(event, type) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validate file size (2MB max)
    if (file.size > 2 * 1024 * 1024) {
        alert('File size must be less than 2MB');
        return;
    }
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = (e) => {
        const imageData = e.target.result;
        showProfileSignaturePreview(imageData, type);
        // Store in profile object
        if (type === 'operator') {
            municipalityProfile.operatorSignature = imageData;
        } else if (type === 'drrmoHead') {
            municipalityProfile.drrmoHeadSignature = imageData;
        }
        // Auto-save to localStorage
        try {
            localStorage.setItem('municipalityProfile', JSON.stringify(municipalityProfile));
        } catch(_) {}
    };
    reader.readAsDataURL(file);
}

function showProfileSignaturePreview(imageData, type) {
    const preview = document.getElementById(`${type}SignaturePreview`);
    const img = document.getElementById(`${type}SignatureImg`);
    const btn = document.getElementById(`${type}SignatureBtn`);
    const btnText = document.getElementById(`${type}SignatureBtnText`);
    const removeBtn = document.getElementById(`${type}SignatureRemove`);
    
    if (preview && img && btn) {
        img.src = imageData;
        preview.style.display = 'block';
        if (btnText) btnText.textContent = 'Change Signature';
        if (removeBtn) removeBtn.style.display = 'inline-block';
    }
}

function clearProfileSignature(type) {
    const preview = document.getElementById(`${type}SignaturePreview`);
    const fileInput = document.getElementById(`${type}SignatureFile`);
    const btn = document.getElementById(`${type}SignatureBtn`);
    const btnText = document.getElementById(`${type}SignatureBtnText`);
    const removeBtn = document.getElementById(`${type}SignatureRemove`);
    
    if (preview && fileInput && btn) {
        preview.style.display = 'none';
        fileInput.value = '';
        if (btnText) btnText.textContent = 'Upload Signature';
        if (removeBtn) removeBtn.style.display = 'none';
        
        // Remove from profile
        if (type === 'operator') {
            delete municipalityProfile.operatorSignature;
        } else if (type === 'drrmoHead') {
            delete municipalityProfile.drrmoHeadSignature;
        }
        
        // Save to localStorage
        try {
            localStorage.setItem('municipalityProfile', JSON.stringify(municipalityProfile));
        } catch(_) {}
    }
}

</script>