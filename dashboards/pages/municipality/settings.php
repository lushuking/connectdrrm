<div class="settings-page">
    <link rel="stylesheet" href="assets/css/pages/settings.css">

    <div class="settings-header mb-4">
        <h3 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <span class="material-icons text-primary">settings</span>
            Account Settings
        </h3>
        <p class="text-muted mb-0">Manage your account preferences, security, and profile information.</p>
    </div>

    <div class="row g-4">

        <!-- LEFT: Account Info -->
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body text-center p-4">
                    <div class="mx-auto mb-3 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow" style="width: 80px; height: 80px; font-size: 2rem; font-weight: 700;" id="settingsAvatarInitials">—</div>
                    <h5 class="fw-bold text-dark mb-1" id="settingsFullName">Loading...</h5>
                    <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-3 py-2 rounded-pill" id="settingsRoleDisplay">—</span>
                    <hr class="my-3">
                    <div class="text-start">
                        <div class="mb-2 d-flex align-items-center gap-2">
                            <span class="material-icons text-muted" style="font-size:18px;">email</span>
                            <span class="small text-dark" id="settingsEmail">—</span>
                        </div>
                        <div class="mb-2 d-flex align-items-center gap-2">
                            <span class="material-icons text-muted" style="font-size:18px;">work</span>
                            <span class="small text-dark" id="settingsPosition">—</span>
                        </div>
                        <div class="mb-2 d-flex align-items-center gap-2">
                            <span class="material-icons text-muted" style="font-size:18px;">phone</span>
                            <span class="small text-dark" id="settingsContact">—</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="material-icons text-muted" style="font-size:18px;">location_city</span>
                            <span class="small text-dark" id="settingsMunicipality">—</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Session Info Card -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom pt-3 pb-2 px-3">
                    <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
                        <span class="material-icons text-info" style="font-size:20px;">info</span>
                        Session Information
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <div class="text-muted small text-uppercase fw-semibold mb-1" style="letter-spacing:0.5px;">Account ID</div>
                        <div class="fw-semibold text-dark" id="settingsUserId">—</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small text-uppercase fw-semibold mb-1" style="letter-spacing:0.5px;">Account Role</div>
                        <div class="fw-semibold text-dark" id="settingsRoleRaw">—</div>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold mb-1" style="letter-spacing:0.5px;">System Version</div>
                        <div class="fw-semibold text-dark">ConnectDRRM v1.0.0</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Settings Panels -->
        <div class="col-lg-8">

            <!-- Update Profile -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom pt-3 pb-2 px-3">
                    <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
                        <span class="material-icons text-primary" style="font-size:20px;">manage_accounts</span>
                        Update Profile Information
                    </h6>
                    <p class="text-muted small mb-0 mt-1">Keep your contact information, signature, and organization logo up to date.</p>
                </div>
                <div class="card-body p-4">
                    <form id="updateProfileForm" autocomplete="off" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:0.5px;">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <span class="material-icons text-muted" style="font-size:18px;">person</span>
                                </span>
                                <input type="text" class="form-control border-start-0" id="profileFullName" name="full_name" placeholder="Enter your full name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:0.5px;">Position / Job Title <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <span class="material-icons text-muted" style="font-size:18px;">work</span>
                                </span>
                                <input type="text" class="form-control border-start-0" id="profilePosition" name="position" placeholder="Enter your position" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:0.5px;">Contact Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <span class="material-icons text-muted" style="font-size:18px;">phone</span>
                                </span>
                                <input type="tel" class="form-control border-start-0" id="profileContact" name="contact_number" placeholder="Enter your contact number" required>
                            </div>
                        </div>

                        <!-- E-Signature Section -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:0.5px;">E-Signature <span class="text-danger">*</span></label>
                            <div class="signature-upload-container p-3 mb-2" style="border: 2px dashed #ddd; border-radius: 8px; text-align: center; background: #f9f9f9; min-height: 150px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                <div id="settingsSignaturePreview" style="display: none; margin-bottom: 10px;">
                                    <img id="settingsSignatureImage" class="img-thumbnail" alt="Signature Preview" style="max-height: 120px;">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-danger me-2" onclick="clearSettingsSignature()">Remove</button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('settingsSignatureFile').click()">Change Signature</button>
                                    </div>
                                </div>
                                <div id="settingsSignaturePlaceholder">
                                    <span class="material-icons text-muted" style="font-size: 48px; margin-bottom: 10px;">signature</span>
                                    <p class="text-muted small mb-0">Click to upload your e-signature</p>
                                </div>
                                <input type="file" id="settingsSignatureFile" accept="image/*" style="display: none;" onchange="handleSettingsSignatureUpload(event)">
                                <input type="hidden" name="signature_base64" id="settingsSignatureBase64" required>
                                <div id="settingsUploadSigBtnContainer" class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('settingsSignatureFile').click()">
                                        Upload Signature Image
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted">Upload a clear PNG/JPG image of your signature. Preferably transparent background.</small>
                        </div>

                        <!-- Municipality Logo Section (Only for DRRMO Staff) -->
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'drrmo_staff'): ?>
                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:0.5px;">Municipality Logo <span class="text-danger">*</span></label>
                            <div class="logo-upload-container p-3 mb-2" style="border: 2px dashed #ddd; border-radius: 8px; text-align: center; background: #f9f9f9; min-height: 150px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                <div id="settingsLogoPreview" style="display: none; margin-bottom: 10px;">
                                    <img id="settingsLogoImage" class="img-thumbnail" alt="Logo Preview" style="max-height: 120px;">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-danger me-2" onclick="clearSettingsLogo()">Remove</button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('settingsLogoFile').click()">Change Logo</button>
                                    </div>
                                </div>
                                <div id="settingsLogoPlaceholder">
                                    <span class="material-icons text-muted" style="font-size: 48px; margin-bottom: 10px;">image</span>
                                    <p class="text-muted small mb-0">Click to upload municipality logo</p>
                                </div>
                                <input type="file" id="settingsLogoFile" name="municipality_logo" accept="image/*" style="display: none;" onchange="handleSettingsLogoUpload(event)">
                                <input type="hidden" name="existing_logo" id="settingsExistingLogo">
                                <div id="settingsUploadLogoBtnContainer" class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('settingsLogoFile').click()">
                                        Upload Logo Image
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted">Upload your official municipality seal/logo (PNG/JPG format).</small>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-5 fw-bold rounded-pill" id="updateProfileBtn">
                                <span class="material-icons me-2" style="font-size:18px;">save</span>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom pt-3 pb-2 px-3">
                    <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
                        <span class="material-icons text-warning" style="font-size:20px;">lock_reset</span>
                        Change Password
                    </h6>
                    <p class="text-muted small mb-0 mt-1">Update your login password. Use a strong combination of letters, numbers, and symbols.</p>
                </div>
                <div class="card-body p-4">
                    <form id="changePasswordForm" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:0.5px;">Current Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <span class="material-icons text-muted" style="font-size:18px;">lock</span>
                                </span>
                                <input type="password" class="form-control border-start-0" id="currentPassword" name="current_password" placeholder="Enter your current password" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary border-start-0" onclick="togglePasswordVisibility('currentPassword', this)">
                                    <span class="material-icons" style="font-size:18px;">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:0.5px;">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <span class="material-icons text-muted" style="font-size:18px;">vpn_key</span>
                                </span>
                                <input type="password" class="form-control border-start-0" id="newPassword" name="new_password" placeholder="At least 8 chars with uppercase, lowercase, number" autocomplete="new-password" oninput="checkPasswordStrength(this.value)">
                                <button type="button" class="btn btn-outline-secondary border-start-0" onclick="togglePasswordVisibility('newPassword', this)">
                                    <span class="material-icons" style="font-size:18px;">visibility</span>
                                </button>
                            </div>
                            <div class="mt-2" id="strengthBarWrap" style="display:none;">
                                <div class="progress" style="height:6px; border-radius:4px;">
                                    <div class="progress-bar" id="strengthBar" style="transition: width 0.3s, background-color 0.3s;"></div>
                                </div>
                                <small class="text-muted mt-1 d-block" id="strengthLabel"></small>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:0.5px;">Confirm New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <span class="material-icons text-muted" style="font-size:18px;">check_circle</span>
                                </span>
                                <input type="password" class="form-control border-start-0" id="confirmPassword" name="confirm_password" placeholder="Re-enter new password" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary border-start-0" onclick="togglePasswordVisibility('confirmPassword', this)">
                                    <span class="material-icons" style="font-size:18px;">visibility</span>
                                </button>
                            </div>
                            <div id="passwordMatchMsg" class="small mt-1" style="display:none;"></div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-5 fw-bold rounded-pill" id="changePasswordBtn">
                                <span class="material-icons me-2" style="font-size:18px;">save</span>
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Requirements Info -->
            <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #f0f7ff 0%, #e8f4fd 100%); border-left: 4px solid #0d6efd !important;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-primary mb-3 d-flex align-items-center gap-2">
                        <span class="material-icons" style="font-size:20px;">security</span>
                        Password Requirements
                    </h6>
                    <ul class="list-unstyled mb-0 row g-2">
                        <li class="col-md-6 d-flex align-items-center gap-2 small text-dark">
                            <span class="material-icons text-success" style="font-size:16px;">check_circle</span>
                            Minimum 8 characters
                        </li>
                        <li class="col-md-6 d-flex align-items-center gap-2 small text-dark">
                            <span class="material-icons text-success" style="font-size:16px;">check_circle</span>
                            At least one uppercase letter (A–Z)
                        </li>
                        <li class="col-md-6 d-flex align-items-center gap-2 small text-dark">
                            <span class="material-icons text-success" style="font-size:16px;">check_circle</span>
                            At least one lowercase letter (a–z)
                        </li>
                        <li class="col-md-6 d-flex align-items-center gap-2 small text-dark">
                            <span class="material-icons text-success" style="font-size:16px;">check_circle</span>
                            At least one number (0–9)
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Security Tips -->
            <div class="card border-0 shadow-sm rounded-4" style="background: linear-gradient(135deg, #fff9f0 0%, #fef3e2 100%); border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-warning mb-3 d-flex align-items-center gap-2">
                        <span class="material-icons" style="font-size:20px;">tips_and_updates</span>
                        Security Tips
                    </h6>
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex align-items-start gap-2 small text-dark mb-2">
                            <span class="material-icons text-warning mt-1" style="font-size:14px;">arrow_right</span>
                            Never share your password with anyone, including system administrators.
                        </li>
                        <li class="d-flex align-items-start gap-2 small text-dark mb-2">
                            <span class="material-icons text-warning mt-1" style="font-size:14px;">arrow_right</span>
                            Change your password regularly, at least every 3 months.
                        </li>
                        <li class="d-flex align-items-start gap-2 small text-dark mb-2">
                            <span class="material-icons text-warning mt-1" style="font-size:14px;">arrow_right</span>
                            Use a unique password that you don't use on other sites.
                        </li>
                        <li class="d-flex align-items-start gap-2 small text-dark">
                            <span class="material-icons text-warning mt-1" style="font-size:14px;">arrow_right</span>
                            Always log out when using a shared or public computer.
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
let settingsSignatureData = '';

document.addEventListener('DOMContentLoaded', function () {
    loadSettingsAccountInfo();

    const profileForm = document.getElementById('updateProfileForm');
    if (profileForm) profileForm.addEventListener('submit', handleUpdateProfile);

    const form = document.getElementById('changePasswordForm');
    if (form) form.addEventListener('submit', handleChangePassword);

    document.getElementById('confirmPassword').addEventListener('input', function () {
        const newPw = document.getElementById('newPassword').value;
        const msg = document.getElementById('passwordMatchMsg');
        if (this.value.length === 0) { msg.style.display = 'none'; return; }
        msg.style.display = 'block';
        if (this.value === newPw) {
            msg.className = 'small mt-1 text-success fw-semibold';
            msg.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;">check_circle</span> Passwords match';
        } else {
            msg.className = 'small mt-1 text-danger fw-semibold';
            msg.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;">cancel</span> Passwords do not match';
        }
    });
});

function handleSettingsSignatureUpload(event) {
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
        settingsSignatureData = e.target.result;
        document.getElementById('settingsSignatureImage').src = settingsSignatureData;
        document.getElementById('settingsSignaturePreview').style.display = 'block';
        document.getElementById('settingsSignaturePlaceholder').style.display = 'none';
        document.getElementById('settingsUploadSigBtnContainer').style.display = 'none';
        document.getElementById('settingsSignatureBase64').value = settingsSignatureData;
    };
    reader.readAsDataURL(file);
}

function clearSettingsSignature() {
    settingsSignatureData = '';
    document.getElementById('settingsSignaturePreview').style.display = 'none';
    document.getElementById('settingsSignaturePlaceholder').style.display = 'block';
    document.getElementById('settingsUploadSigBtnContainer').style.display = 'block';
    document.getElementById('settingsSignatureFile').value = '';
    document.getElementById('settingsSignatureBase64').value = '';
}

function handleSettingsLogoUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        alert('File size must be less than 2MB');
        document.getElementById('settingsLogoFile').value = '';
        return;
    }

    if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        document.getElementById('settingsLogoFile').value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('settingsLogoImage').src = e.target.result;
        document.getElementById('settingsLogoPreview').style.display = 'block';
        document.getElementById('settingsLogoPlaceholder').style.display = 'none';
        document.getElementById('settingsUploadLogoBtnContainer').style.display = 'none';
        document.getElementById('settingsExistingLogo').value = ''; // clear existing reference
    };
    reader.readAsDataURL(file);
}

function clearSettingsLogo() {
    document.getElementById('settingsLogoPreview').style.display = 'none';
    document.getElementById('settingsLogoPlaceholder').style.display = 'block';
    document.getElementById('settingsUploadLogoBtnContainer').style.display = 'block';
    document.getElementById('settingsLogoFile').value = '';
    document.getElementById('settingsExistingLogo').value = '';
}

async function loadSettingsAccountInfo() {
    try {
        const res = await fetch('config/settings_api.php?action=get_account_info');
        const data = await res.json();
        if (!data.success) return;
        const u = data.data;

        const initials = (u.fullName || u.email || 'U').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
        document.getElementById('settingsAvatarInitials').textContent = initials;
        document.getElementById('settingsFullName').textContent = u.fullName || 'No Name Set';
        document.getElementById('settingsRoleDisplay').textContent = u.roleDisplay;
        document.getElementById('settingsEmail').textContent = u.email;
        document.getElementById('settingsPosition').textContent = u.position || 'Not Set';
        document.getElementById('settingsContact').textContent = u.contactNumber || 'Not Set';
        document.getElementById('settingsMunicipality').textContent = u.municipality || 'N/A';
        document.getElementById('settingsUserId').textContent = '#' + u.userID;
        document.getElementById('settingsRoleRaw').textContent = u.roleDisplay;

        // Populate update form fields
        document.getElementById('profileFullName').value = u.fullName || '';
        document.getElementById('profilePosition').value = u.position || '';
        document.getElementById('profileContact').value = u.contactNumber || '';

        if (u.signature) {
            settingsSignatureData = u.signature;
            document.getElementById('settingsSignatureImage').src = u.signature;
            document.getElementById('settingsSignaturePreview').style.display = 'block';
            document.getElementById('settingsSignaturePlaceholder').style.display = 'none';
            document.getElementById('settingsUploadSigBtnContainer').style.display = 'none';
            document.getElementById('settingsSignatureBase64').value = u.signature;
        } else {
            clearSettingsSignature();
        }

        const logoPreviewEl = document.getElementById('settingsLogoPreview');
        if (logoPreviewEl) {
            if (u.logoUrl) {
                document.getElementById('settingsLogoImage').src = u.logoUrl;
                logoPreviewEl.style.display = 'block';
                document.getElementById('settingsLogoPlaceholder').style.display = 'none';
                document.getElementById('settingsUploadLogoBtnContainer').style.display = 'none';
                document.getElementById('settingsExistingLogo').value = u.logoUrl;
            } else {
                clearSettingsLogo();
            }
        }
    } catch (e) {
        console.error('Failed to load account info', e);
    }
}

async function handleUpdateProfile(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('updateProfileBtn');
    const orig = btn.innerHTML;

    if (!document.getElementById('settingsSignatureBase64').value) {
        showSettingsToast('Please upload your e-signature.', 'danger');
        return;
    }

    const logoFileEl = document.getElementById('settingsLogoFile');
    const existingLogoEl = document.getElementById('settingsExistingLogo');
    if (logoFileEl && existingLogoEl) {
        const hasNewLogo = logoFileEl.files.length > 0;
        const hasExistingLogo = (existingLogoEl.value || '').trim() !== '';
        if (!hasNewLogo && !hasExistingLogo) {
            showSettingsToast('Please upload the municipality logo.', 'danger');
            return;
        }
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons me-2" style="font-size:18px;">hourglass_top</span> Saving...';

    try {
        const res = await fetch('config/settings_api.php?action=update_profile', {
            method: 'POST',
            body: new FormData(form)
        });
        const data = await res.json();
        if (data.success) {
            showSettingsToast('Profile details, signature, and logo updated successfully!', 'success');
            await loadSettingsAccountInfo();

            // Dynamic header refresh
            const headerNameEl = document.querySelector('.user-info .user-name');
            if (headerNameEl) headerNameEl.textContent = document.getElementById('profileFullName').value;
        } else {
            showSettingsToast(data.error || 'Failed to update profile', 'danger');
        }
    } catch (err) {
        showSettingsToast('An error occurred. Please try again.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

async function handleChangePassword(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('changePasswordBtn');
    const orig = btn.innerHTML;

    const newPw = document.getElementById('newPassword').value;
    const confirmPw = document.getElementById('confirmPassword').value;
    if (newPw !== confirmPw) {
        showSettingsToast('New passwords do not match', 'danger');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons me-2" style="font-size:18px;">hourglass_top</span> Saving...';

    try {
        const res = await fetch('config/settings_api.php?action=change_password', {
            method: 'POST',
            body: new FormData(form)
        });
        const data = await res.json();
        if (data.success) {
            showSettingsToast('Password changed successfully! Please remember your new password.', 'success');
            form.reset();
            document.getElementById('strengthBarWrap').style.display = 'none';
            document.getElementById('passwordMatchMsg').style.display = 'none';
        } else {
            showSettingsToast(data.error || 'Failed to change password', 'danger');
        }
    } catch (err) {
        showSettingsToast('An error occurred. Please try again.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

function checkPasswordStrength(value) {
    const wrap = document.getElementById('strengthBarWrap');
    const bar = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    if (!value) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';

    let score = 0;
    if (value.length >= 8) score++;
    if (value.length >= 12) score++;
    if (/[A-Z]/.test(value)) score++;
    if (/[a-z]/.test(value)) score++;
    if (/[0-9]/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;

    const levels = [
        { max: 2, label: 'Weak',   color: '#ef4444', width: '25%'  },
        { max: 3, label: 'Fair',   color: '#f59e0b', width: '50%'  },
        { max: 5, label: 'Good',   color: '#3b82f6', width: '75%'  },
        { max: 6, label: 'Strong', color: '#10b981', width: '100%' },
    ];
    const level = levels.find(l => score <= l.max) || levels[levels.length - 1];
    bar.style.width = level.width;
    bar.style.backgroundColor = level.color;
    label.textContent = 'Strength: ' + level.label;
    label.style.color = level.color;
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('.material-icons');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility';
    }
}

function showSettingsToast(message, type) {
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show shadow-lg`;
    div.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:100010;min-width:350px;max-width:90vw;';
    const icon = type === 'success' ? 'check_circle' : 'error';
    div.innerHTML = `<span class="material-icons align-middle me-2" style="font-size:18px;">${icon}</span>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(div);
    setTimeout(() => { if (div.parentNode) div.remove(); }, type === 'success' ? 8000 : 6000);
}
</script>
