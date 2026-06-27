<div class="user-management-page">
    <link rel="stylesheet" href="assets/css/pages/user_management.css">
    <style>
        /* Bypass theme overrides for tabs and radios */
        .user-management-page .nav-pills .nav-link:not(.active) { color: #6c757d !important; }
        .user-management-page .nav-pills .nav-link:not(.active):hover { color: #495057 !important; background-color: #f8f9fa; }
        .custom-radio-card { cursor: pointer; background-color: #fff; border-color: #dee2e6; color: #212529; }
        .custom-radio-card:hover { border-color: #babbbc; background-color: #f8f9fa; }
        .btn-check:checked + .custom-radio-card.admin-card { border-color: #0d6efd !important; background-color: #f0f7ff !important; box-shadow: 0 0 0 0.25rem rgba(13,110,253,.15); }
        .btn-check:checked + .custom-radio-card.approving-card { border-color: #198754 !important; background-color: #f0fdf4 !important; box-shadow: 0 0 0 0.25rem rgba(25,135,84,.15); }
    </style>

        <!-- Modern Tabs Navigation -->
        <div class="bg-white p-1 border rounded-pill shadow-sm d-inline-flex mb-4">
            <ul class="nav nav-pills" id="userManagementTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill px-4 fw-semibold d-flex align-items-center transition-all" id="create-account-tab" data-bs-toggle="tab" data-bs-target="#create-account" type="button" role="tab" aria-controls="create-account" aria-selected="true">
                        <span class="material-icons me-2" style="font-size: 18px;">person_add</span> Create Account
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-4 fw-semibold d-flex align-items-center transition-all" id="municipalities-overview-tab" data-bs-toggle="tab" data-bs-target="#municipalities-overview" type="button" role="tab" aria-controls="municipalities-overview" aria-selected="false">
                        <span class="material-icons me-2" style="font-size: 18px;">corporate_fare</span> Municipalities Overview
                    </button>
                </li>
            </ul>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="userManagementTabContent">
            <!-- Create Account Tab -->
            <div class="tab-pane fade show active" id="create-account" role="tabpanel" aria-labelledby="create-account-tab">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom pt-3 pb-2 px-3">
                        <h5 class="mb-0 fw-bold d-flex align-items-center text-primary">
                            <span class="material-icons me-2">account_circle</span>
                            Provision New Account
                        </h5>
                        <p class="text-muted small mb-0 mt-1">Select a municipality and assign an account type to generate login credentials.</p>
                    </div>
                    
                    <div class="card-body p-3 bg-white">
                        <form id="createUserForm" autocomplete="off" data-lpignore="true">
                            <div class="row g-3">
                                <!-- Municipality Selection -->
                                <div class="col-12">
                                    <div class="card border shadow-sm rounded-4">
                                        <div class="card-body p-2">
                                            <label for="userMunicipality" class="form-label text-uppercase text-muted fw-bold mb-2 ps-1" style="font-size: 0.75rem; letter-spacing: 1px;">
                                                Select Municipality <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3">
                                                    <span class="material-icons text-muted" style="font-size: 18px;">location_city</span>
                                                </span>
                                                <select class="form-select border-start-0 rounded-end-pill py-2 px-3" id="userMunicipality" name="drrmo_id" required style="cursor: pointer; font-weight: 500;">
                                                    <option value="">Choose a municipality...</option>
                                                    <!-- Options will be populated dynamically -->
                                                </select>
                                            </div>
                                            <div class="mt-2 text-muted small d-flex align-items-start ps-1">
                                                <span class="material-icons me-1 text-info" style="font-size: 16px;">info</span>
                                                <span>Municipalities with 2 existing accounts will be disabled.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Account Type Selection -->
                                <div class="col-12" id="accountTypeSelection" style="display: none;">
                                    <div class="card border shadow-sm rounded-4">
                                        <div class="card-body p-2">
                                            <label class="form-label text-uppercase text-muted fw-bold mb-2 ps-1" style="font-size: 0.75rem; letter-spacing: 1px;">
                                                Account Type <span class="text-danger">*</span>
                                            </label>
                                            <div class="account-type-cards d-flex flex-column flex-md-row gap-2">
                                                <div class="account-type-card flex-fill" data-type="admin">
                                                    <input type="radio" name="account_type" id="accountTypeAdmin" value="admin" required class="btn-check">
                                                    <label for="accountTypeAdmin" class="border border-2 w-100 h-100 text-start p-2 rounded-4 d-flex align-items-center transition-all custom-radio-card admin-card">
                                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 36px; height: 36px;">
                                                            <span class="material-icons text-primary" style="font-size: 18px;">admin_panel_settings</span>
                                                        </div>
                                                        <div>
                                                            <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.9rem;">Admin / DRRMO Officer</h6>
                                                            <p class="small text-muted mb-0" style="white-space: normal; font-size: 0.8rem;">Full access to manage resources, requests, and data.</p>
                                                        </div>
                                                    </label>
                                                </div>
                                                <div class="account-type-card flex-fill" data-type="approving_authority">
                                                    <input type="radio" name="account_type" id="accountTypeApproving" value="approving_authority" required class="btn-check">
                                                    <label for="accountTypeApproving" class="border border-2 w-100 h-100 text-start p-2 rounded-4 d-flex align-items-center transition-all custom-radio-card approving-card">
                                                        <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 36px; height: 36px;">
                                                            <span class="material-icons text-success" style="font-size: 18px;">verified_user</span>
                                                        </div>
                                                        <div>
                                                            <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.9rem;">Head of DRRMO</h6>
                                                            <p class="small text-muted mb-0" style="white-space: normal; font-size: 0.8rem;">Authority to approve resource requests for orderly conduct.</p>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            <div id="accountTypeWarning" class="alert alert-warning mt-2 mb-0 py-2 border-0 rounded-4 d-flex align-items-center" style="display: none;">
                                                <span class="material-icons me-2" style="font-size: 18px;">warning</span>
                                                <span id="accountTypeWarningText" class="small"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Generated Credentials Display -->
                                <div class="col-12" id="generatedCredentials" style="display: none;">
                                    <div class="card border border-info border-opacity-25 shadow-sm rounded-4 overflow-hidden bg-info bg-opacity-10">
                                        <div class="card-body p-3">
                                            <h6 class="fw-bold mb-2 d-flex align-items-center text-primary" style="font-size: 0.95rem;">
                                                <span class="material-icons me-2" style="font-size: 20px;">vpn_key</span> Generated Account Credentials
                                            </h6>
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label class="form-label text-uppercase text-muted fw-bold mb-1 ps-1" style="font-size: 0.65rem; letter-spacing: 1px;">
                                                        Email Address <span id="emailAccountTypeBadge" class="badge bg-primary ms-2 rounded-pill" style="display: none;"></span>
                                                    </label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-white border-end-0 rounded-start-3"><span class="material-icons text-muted" style="font-size: 16px;">email</span></span>
                                                        <input type="email" class="form-control border-start-0 bg-white" id="generatedEmail" readonly autocomplete="off" data-lpignore="true">
                                                        <button type="button" class="btn btn-white border bg-white rounded-end-3 text-primary" onclick="copyToClipboard('generatedEmail')" title="Copy Email">
                                                            <span class="material-icons" style="font-size: 16px;">content_copy</span>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-uppercase text-muted fw-bold mb-1 ps-1" style="font-size: 0.65rem; letter-spacing: 1px;">Password</label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-white border-end-0 rounded-start-3"><span class="material-icons text-muted" style="font-size: 16px;">lock</span></span>
                                                        <input type="text" class="form-control border-start-0 bg-white" id="generatedPassword" readonly autocomplete="off" data-lpignore="true">
                                                        <button type="button" class="btn btn-white border bg-white text-primary" onclick="copyToClipboard('generatedPassword')" title="Copy Password">
                                                            <span class="material-icons" style="font-size: 16px;">content_copy</span>
                                                        </button>
                                                        <button type="button" class="btn btn-primary rounded-end-3" onclick="regeneratePassword()" title="Regenerate Password">
                                                            <span class="material-icons" style="font-size: 16px;">autorenew</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-muted d-flex align-items-start ps-1" style="font-size: 0.75rem;">
                                                <span class="material-icons text-warning me-1" style="font-size: 14px; margin-top: 2px;">error_outline</span>
                                                <span><strong>Important:</strong> Please securely copy and share these credentials with the user. They cannot be retrieved later.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Hidden fields for form submission -->
                                <input type="hidden" id="userEmail" name="email">
                                <input type="hidden" id="userPassword" name="password">
                            </div>
                            
                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <button type="reset" class="btn btn-light px-4 rounded-pill fw-semibold" onclick="resetForm()">
                                    Cancel
                                </button>
                                <button type="submit" class="btn btn-primary px-4 rounded-pill fw-semibold d-flex align-items-center" id="submitBtn" disabled>
                                    <span class="material-icons me-2" style="font-size: 18px;">check_circle</span> Create Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Municipalities Overview Tab -->
            <div class="tab-pane fade" id="municipalities-overview" role="tabpanel" aria-labelledby="municipalities-overview-tab">
                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                    <div class="card-header bg-white border-bottom pt-3 pb-2 px-3">
                        <h5 class="mb-0 fw-bold d-flex align-items-center text-primary">
                            <span class="material-icons me-2">location_city</span>
                            Registered Municipalities
                        </h5>
                        <p class="text-muted small mb-0 mt-1">Overview of all active municipalities and their registered accounts.</p>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <!-- Loading State -->
                        <div id="loadingState" class="text-center py-5 my-5">
                            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted fw-semibold">Loading municipalities data...</p>
                        </div>

                        <!-- Error State -->
                        <div id="errorState" class="alert alert-danger border-0 rounded-4 shadow-sm" style="display: none;">
                            <span class="material-icons align-middle me-2">error</span>
                            <span id="errorMessage"></span>
                        </div>

                        <!-- Municipalities Grid -->
                        <div id="municipalitiesGrid" style="display: none;" class="row g-4">
                            <!-- Municipalities will be rendered here via JS -->
                        </div>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-5" id="municipalitiesPagination"></div>
                    </div>
                </div>
            </div>
        </div>

</div>

<!-- Page JS is already included by pdrrmo.php (page-specific JS loader) -->

