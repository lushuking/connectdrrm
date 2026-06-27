class UserManagementPage {
    constructor() {
        this.municipalities = [];
        this.municipalitiesPerPage = 6;
        this.currentMunicipalitiesPage = 1;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadMunicipalitiesForDropdown();
        this.setupTabHandlers();
    }

    setupTabHandlers() {
        // Load municipalities overview when that tab is shown
        const municipalitiesTab = document.getElementById('municipalities-overview-tab');
        if (municipalitiesTab) {
            municipalitiesTab.addEventListener('shown.bs.tab', () => {
                // Only load if not already loaded
                if (this.municipalities.length === 0) {
                    this.loadMunicipalities();
                }
            });
        }

        // Ensure proper tab display on page load
        const activeTab = document.querySelector('.nav-link.active');
        if (activeTab) {
            const targetId = activeTab.getAttribute('data-bs-target');
            if (targetId) {
                const targetPane = document.querySelector(targetId);
                if (targetPane) {
                    // Hide all panes first
                    document.querySelectorAll('.tab-pane').forEach(pane => {
                        pane.classList.remove('active', 'show');
                    });
                    // Show active pane
                    targetPane.classList.add('active', 'show');
                }
            }
        }
    }

    setupEventListeners() {
        // Create user form submission
        const createForm = document.getElementById('createUserForm');
        if (createForm) {
            createForm.addEventListener('submit', (e) => this.handleCreateUser(e));
        }

        // Municipality selection change handler
        const municipalitySelect = document.getElementById('userMunicipality');
        if (municipalitySelect) {
            municipalitySelect.addEventListener('change', (e) => this.handleMunicipalityChange(e));
        }
    }

    async handleMunicipalityChange(e) {
        const municipalityId = e.target.value;
        const municipalityName = e.target.options[e.target.selectedIndex].text;
        
        if (!municipalityId) {
            // Hide everything if no municipality selected
            document.getElementById('accountTypeSelection').style.display = 'none';
            document.getElementById('generatedCredentials').style.display = 'none';
            document.getElementById('submitBtn').disabled = true;
            return;
        }

        // Fetch municipality account status
        try {
            const response = await fetch(`config/user_management_api.php?action=get_municipality&drrmo_id=${municipalityId}`);
            const result = await response.json();

            if (result.success && result.data.account_status) {
                const status = result.data.account_status;
                
                // Show account type selection
                this.showAccountTypeSelection(status);
            } else {
                // Default: show both options if status unavailable
                this.showAccountTypeSelection({
                    can_create_admin: true,
                    can_create_approving_authority: true,
                    total_accounts: 0
                });
            }
        } catch (error) {
            console.error('Error fetching municipality status:', error);
            // Show both options as fallback
            this.showAccountTypeSelection({
                can_create_admin: true,
                can_create_approving_authority: true,
                total_accounts: 0
            });
        }
    }

    showAccountTypeSelection(status) {
        const accountTypeSelection = document.getElementById('accountTypeSelection');
        const warningDiv = document.getElementById('accountTypeWarning');
        const warningText = document.getElementById('accountTypeWarningText');
        
        // Show account type selection
        accountTypeSelection.style.display = 'block';
        
        // Enable/disable account type options
        const adminCard = document.querySelector('[data-type="admin"]');
        const approvingCard = document.querySelector('[data-type="approving_authority"]');
        const adminRadio = document.getElementById('accountTypeAdmin');
        const approvingRadio = document.getElementById('accountTypeApproving');
        
        // Reset selections
        adminRadio.checked = false;
        approvingRadio.checked = false;
        
        // Update card states
        if (status.can_create_admin) {
            adminCard.classList.remove('disabled');
            adminRadio.disabled = false;
        } else {
            adminCard.classList.add('disabled');
            adminRadio.disabled = true;
        }
        
        if (status.can_create_approving_authority) {
            approvingCard.classList.remove('disabled');
            approvingRadio.disabled = false;
        } else {
            approvingCard.classList.add('disabled');
            approvingRadio.disabled = true;
        }
        
        // Show warning if municipality has accounts
        if (status.total_accounts > 0) {
            let warningMsg = '';
            if (status.total_accounts === 2) {
                warningMsg = 'This municipality already has 2 accounts (maximum limit reached).';
                warningDiv.className = 'alert alert-danger mt-3';
            } else if (status.total_accounts === 1) {
                if (status.has_admin) {
                    warningMsg = 'This municipality already has an Admin/DRRMO Officer account. You can only create a Head of DRRMO account.';
                } else {
                    warningMsg = 'This municipality already has a Head of DRRMO account. You can only create an Admin/DRRMO Officer account.';
                }
                warningDiv.className = 'alert alert-warning mt-3';
            }
            
            if (warningMsg) {
                warningText.textContent = warningMsg;
                warningDiv.style.display = 'block';
            } else {
                warningDiv.style.display = 'none';
            }
        } else {
            warningDiv.style.display = 'none';
        }
        
        // Hide credentials until account type is selected
        document.getElementById('generatedCredentials').style.display = 'none';
        document.getElementById('submitBtn').disabled = true;
        
        // Add event listeners for account type selection
        const handleRadioChange = () => {
            const selectedRadio = document.querySelector('input[name="account_type"]:checked');
            if (selectedRadio) {
                // Update card visual state
                document.querySelectorAll('.account-type-card').forEach(card => {
                    card.classList.remove('selected');
                });
                const selectedCard = selectedRadio.closest('.account-type-card');
                if (selectedCard) {
                    selectedCard.classList.add('selected');
                }
            }
            this.handleAccountTypeChange();
        };
        
        adminRadio.addEventListener('change', handleRadioChange);
        approvingRadio.addEventListener('change', handleRadioChange);
        
        // Also handle card clicks
        adminCard.addEventListener('click', (e) => {
            if (!adminCard.classList.contains('disabled') && e.target.tagName !== 'INPUT') {
                adminRadio.checked = true;
                adminRadio.dispatchEvent(new Event('change'));
            }
        });
        
        approvingCard.addEventListener('click', (e) => {
            if (!approvingCard.classList.contains('disabled') && e.target.tagName !== 'INPUT') {
                approvingRadio.checked = true;
                approvingRadio.dispatchEvent(new Event('change'));
            }
        });
    }

    handleAccountTypeChange() {
        const municipalitySelect = document.getElementById('userMunicipality');
        const municipalityId = municipalitySelect.value;
        const municipalityName = municipalitySelect.options[municipalitySelect.selectedIndex].text;
        const accountType = document.querySelector('input[name="account_type"]:checked')?.value;
        
        if (!municipalityId || !accountType) {
            document.getElementById('generatedCredentials').style.display = 'none';
            document.getElementById('submitBtn').disabled = true;
            return;
        }

        // Generate email from municipality name with account type indicator
        const email = this.generateEmail(municipalityName, accountType);
        const password = this.generatePassword();

        // Display generated credentials
        document.getElementById('generatedEmail').value = email;
        document.getElementById('generatedPassword').value = password;
        document.getElementById('userEmail').value = email;
        document.getElementById('userPassword').value = password;
        
        // Show account type badge for email
        const emailBadge = document.getElementById('emailAccountTypeBadge');
        if (accountType === 'approving_authority') {
            emailBadge.textContent = 'Head of DRRMO';
            emailBadge.className = 'badge bg-success ms-2';
            emailBadge.style.display = 'inline-block';
        } else {
            emailBadge.textContent = 'Admin / DRRMO Officer';
            emailBadge.className = 'badge bg-primary ms-2';
            emailBadge.style.display = 'inline-block';
        }
        
        document.getElementById('generatedCredentials').style.display = 'block';
        document.getElementById('submitBtn').disabled = false;
    }

    generateEmail(municipalityName, accountType = 'admin') {
        if (!municipalityName) return '';
        
        // Remove prefixes (CDRRMO, MDRRMO, PDRRMO)
        let cleanName = municipalityName.replace(/^(?:[A-Z]{0,3}DRRMO\s+)/i, '');
        
        // Remove "City of", "Municipality of"
        cleanName = cleanName.replace(/^(City of|Municipality of)\s+/i, '');
        
        // Remove trailing "City" or any DRRMO suffix (CDRRMO, MDRRMO, PDRRMO, or just DRRMO)
        cleanName = cleanName.replace(/\s+(City|[A-Z]{0,3}DRRMO|DRRMO)$/i, '');
        
        // Convert to lowercase
        cleanName = cleanName.toLowerCase().trim();
        
        // Replace spaces and special characters with nothing (keep only alphanumeric)
        cleanName = cleanName.replace(/[^a-z0-9]/g, '');
        
        // Ensure we have something
        if (!cleanName) {
            cleanName = 'municipality';
        }
        
        // Generate email with account type indicator for head of DRRMO
        if (accountType === 'approving_authority') {
            // Format: [municipalityname].head.drrmo@zds.gov.ph
            return cleanName + '.head.drrmo@zds.gov.ph';
        } else {
            // Format: [municipalityname].drrmo@zds.gov.ph (admin/DRRMO officer)
            return cleanName + '.drrmo@zds.gov.ph';
        }
    }

    generatePassword() {
        // Generate a secure random password
        const length = 12;
        const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
        let password = '';
        
        // Ensure at least one of each type
        password += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)]; // Uppercase
        password += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)]; // Lowercase
        password += '0123456789'[Math.floor(Math.random() * 10)]; // Number
        password += '!@#$%&*'[Math.floor(Math.random() * 7)]; // Special char
        
        // Fill the rest randomly
        for (let i = password.length; i < length; i++) {
            password += charset[Math.floor(Math.random() * charset.length)];
        }
        
        // Shuffle the password
        return password.split('').sort(() => Math.random() - 0.5).join('');
    }

    async loadMunicipalitiesForDropdown() {
        try {
            const response = await fetch('config/user_management_api.php?action=get_municipalities');
            const result = await response.json();

            if (result.success) {
                const select = document.getElementById('userMunicipality');
                if (select) {
                    select.innerHTML = '<option value="">Select Municipality</option>';
                    
                    result.data.forEach(mun => {
                        const option = document.createElement('option');
                        option.value = mun.drrmoID;
                        
                        // If municipality has 2 accounts, disable it and add note
                        if (!mun.can_create_account || mun.account_count >= 2) {
                            option.disabled = true;
                            option.textContent = `${mun.name} (2 accounts - Limit reached)`;
                            option.style.color = '#6c757d';
                            option.style.fontStyle = 'italic';
                        } else {
                            option.textContent = mun.name;
                        }
                        
                        select.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Error loading municipalities for dropdown:', error);
        }
    }

    async loadMunicipalities() {
        try {
            const response = await fetch('config/user_management_api.php?action=list');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();

            if (result.success) {
                this.municipalities = result.data;
                this.renderMunicipalities();
                document.getElementById('loadingState').style.display = 'none';
                document.getElementById('municipalitiesGrid').style.display = 'block';
                document.getElementById('errorState').style.display = 'none';
            } else {
                this.showError(result.error || 'Failed to load user data');
            }
        } catch (error) {
            console.error('Error loading municipalities:', error);
            this.showError('Failed to load user data: ' + error.message);
        }
    }

    // Helper to shorten long municipality names
    shortenName(name) {
        if (!name) return '';
        if (name.includes('Zamboanga del Sur')) {
            return name.replace('Zamboanga del Sur', 'ZDS');
        }
        if (name.length > 20) {
            return name.substring(0, 17) + '...';
        }
        return name;
    }

    renderMunicipalities() {
        const container = document.getElementById('municipalitiesGrid');
        if (!container) return;

        const total = this.municipalities.length;
        const totalPages = Math.max(1, Math.ceil(total / this.municipalitiesPerPage));
        if (this.currentMunicipalitiesPage > totalPages) this.currentMunicipalitiesPage = totalPages;
        const startIdx = (this.currentMunicipalitiesPage - 1) * this.municipalitiesPerPage;
        const endIdx = startIdx + this.municipalitiesPerPage;
        const municipalitiesToRender = this.municipalities.slice(startIdx, endIdx);
        
        if (municipalitiesToRender.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <span class="material-icons text-muted" style="font-size: 48px;">location_off</span>
                    <p class="text-muted mt-2">No municipalities found</p>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div class="row g-3">
                ${municipalitiesToRender.map(municipality => {
                    const totalUsers = municipality.users.length;
                    const completedProfiles = municipality.users.filter(u => u.profileCompleted).length;
                    const incompleteProfiles = totalUsers - completedProfiles;

                    let profileStatus = 'Complete';
                    let statusBg = '#dcfce7'; let statusColor = '#166534'; let statusIcon = 'check_circle';
                    if (totalUsers === 0) {
                        profileStatus = 'No Users';
                        statusBg = '#f1f5f9'; statusColor = '#64748b'; statusIcon = 'person_off';
                    } else if (incompleteProfiles > 0) {
                        profileStatus = 'Incomplete';
                        statusBg = '#fff7ed'; statusColor = '#c2410c'; statusIcon = 'pending';
                    }

                    const logoUrl = municipality.logo_url || null;
                    const initials = this.shortenName(municipality.name).split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();

                    return `
                        <div class="col-md-6 col-lg-4">
                            <div class="muni-card h-100">
                                <!-- Card Header Strip -->
                                <div class="muni-card-header">
                                    <div class="muni-avatar" id="avatar-${municipality.drrmoID}">
                                        ${logoUrl
                                            ? `<img src="${this.escapeHtml(logoUrl)}" alt="logo" 
                                                    style="width:100%;height:100%;object-fit:cover;border-radius:50%;border:2px solid #fff;"
                                                    onerror="this.style.display='none'; this.parentElement.innerHTML='<span>${initials}</span>';">`
                                            : `<span>${initials}</span>`
                                        }
                                    </div>
                                    <div class="muni-header-info">
                                        <div class="muni-name">${this.shortenName(municipality.name)}</div>
                                        <div class="muni-type">Municipal DRRMO</div>
                                    </div>
                                    <div class="muni-status-pill" style="background:${statusBg};color:${statusColor};">
                                        <span class="material-icons" style="font-size:13px;">${statusIcon}</span>
                                        ${profileStatus}
                                    </div>
                                </div>

                                <!-- Stats Row -->
                                <div class="muni-stats">
                                    <div class="muni-stat">
                                        <div class="muni-stat-value">${totalUsers}</div>
                                        <div class="muni-stat-label">Total Users</div>
                                    </div>
                                    <div class="muni-stat-divider"></div>
                                    <div class="muni-stat">
                                        <div class="muni-stat-value" style="color:#16a34a;">${completedProfiles}</div>
                                        <div class="muni-stat-label">Completed</div>
                                    </div>
                                    <div class="muni-stat-divider"></div>
                                    <div class="muni-stat">
                                        <div class="muni-stat-value" style="color:${incompleteProfiles > 0 ? '#c2410c' : '#94a3b8'};">${incompleteProfiles}</div>
                                        <div class="muni-stat-label">Incomplete</div>
                                    </div>
                                </div>


                                <!-- Action -->
                                <div class="muni-card-footer">
                                    <button class="muni-view-btn" onclick="userManagementPage.viewMunicipalityUsers(${municipality.drrmoID}, '${this.escapeHtml(municipality.name)}')">
                                        <span class="material-icons" style="font-size:18px;">group</span>
                                        View Users
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;

        // Render pagination
        this.renderMunicipalitiesPagination(this.currentMunicipalitiesPage, totalPages);
    }

    renderMunicipalitiesPagination(current, total) {
        const container = document.getElementById('municipalitiesPagination');
        if (!container) return;
        container.innerHTML = '';
        
        if (total <= 1) return;
        
        const nav = document.createElement('nav');
        const ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm mb-0 justify-content-center';

        const create = (label, page, disabled = false, active = false) => {
            const li = document.createElement('li');
            li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'page-link';
            btn.textContent = label;
            if (!disabled && !active) {
                btn.addEventListener('click', () => {
                    this.currentMunicipalitiesPage = page;
                    this.renderMunicipalities();
                });
            }
            li.appendChild(btn);
            return li;
        };

        ul.appendChild(create('Previous', current - 1, current === 1));
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
                ul.appendChild(create(i.toString(), i, false, i === current));
            } else if (i === current - 2 || i === current + 2) {
                ul.appendChild(create('...', i, true));
            }
        }
        ul.appendChild(create('Next', current + 1, current === total));
        
        nav.appendChild(ul);
        container.appendChild(nav);
    }



    viewMunicipalityUsers(drrmoId, municipalityName) {
        const municipality = this.municipalities.find(m => m.drrmoID == drrmoId);
        if (!municipality) return;

        let usersHtml = '';
        const totalUsers = municipality.users.length;
        const completedProfiles = municipality.users.filter(u => u.profileCompleted).length;
        const incompleteProfiles = totalUsers - completedProfiles;

        const logoUrl = municipality.logo_url || null;
        const initials = this.shortenName(municipalityName).split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();

        const summaryHtml = `
            <div class="card border-0 shadow-sm mb-4 rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 64px; height: 64px; border: 2px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                                ${logoUrl 
                                    ? `<img src="${this.escapeHtml(logoUrl)}" alt="logo" style="width:100%; height:100%; object-fit:cover;" onerror="this.parentElement.style.display='none'">`
                                    : `<span class="fw-bold text-primary fs-5">${initials}</span>`
                                }
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-1">${this.escapeHtml(municipalityName)}</h4>
                                <span class="text-muted small bg-light px-2 py-1 rounded fw-semibold">${this.escapeHtml(municipality.type || 'External DRRMO')}</span>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <div class="text-center px-4 py-2 border rounded-3 bg-white">
                                <h4 class="fw-bold text-primary mb-0">${totalUsers}</h4>
                                <span class="small text-muted text-uppercase fw-semibold" style="font-size:0.65rem; letter-spacing:0.5px;">Total Users</span>
                            </div>
                            <div class="text-center px-4 py-2 border rounded-3 bg-white">
                                <h4 class="fw-bold text-success mb-0">${completedProfiles}</h4>
                                <span class="small text-muted text-uppercase fw-semibold" style="font-size:0.65rem; letter-spacing:0.5px;">Complete</span>
                            </div>
                            <div class="text-center px-4 py-2 border rounded-3 bg-white">
                                <h4 class="fw-bold text-warning mb-0">${incompleteProfiles}</h4>
                                <span class="small text-muted text-uppercase fw-semibold" style="font-size:0.65rem; letter-spacing:0.5px;">Incomplete</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        if (municipality.users.length === 0) {
            usersHtml = `
                ${summaryHtml}
                <div class="text-center py-5 bg-white border rounded shadow-sm">
                    <h6 class="fw-bold text-dark">No User Accounts Found</h6>
                    <p class="text-muted small mb-0 px-4">No user accounts have been created for this municipality yet.</p>
                </div>
            `;
        } else {
            usersHtml = `
                ${summaryHtml}
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="width: 100%;">
                    <div class="card-header bg-primary text-white py-3 border-0">
                        <h6 class="mb-0 fw-bold d-flex align-items-center">
                            <span class="material-icons me-2" style="font-size: 18px;">people</span>
                            User Accounts List
                        </h6>
                    </div>
                    <div class="table-responsive" style="width: 100%;">
                        <table class="table table-hover align-middle mb-0 bg-white" style="width: 100%; white-space: nowrap;">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 border-0 text-uppercase text-muted py-3" style="font-size: 0.75rem; letter-spacing: 0.5px;">User Profile</th>
                                    <th class="border-0 text-uppercase text-muted py-3" style="font-size: 0.75rem; letter-spacing: 0.5px;">Assigned Role</th>
                                    <th class="border-0 text-uppercase text-muted py-3" style="font-size: 0.75rem; letter-spacing: 0.5px;">Status</th>
                                    <th class="pe-4 border-0 text-uppercase text-muted py-3 text-end" style="font-size: 0.75rem; letter-spacing: 0.5px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${municipality.users.map(user => {
                                    const statusBadge = user.profileCompleted 
                                        ? '<span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i>Completed</span>'
                                        : '<span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-2"><i class="fas fa-exclamation-circle me-1"></i>Incomplete</span>';
                                    
                                    let accountTypeDisplay = 'Unknown';
                                    if (user.accountTypeDisplay) {
                                        accountTypeDisplay = user.accountTypeDisplay;
                                    } else if (user.role === 'drrmo_staff') {
                                        accountTypeDisplay = 'Admin / DRRMO Officer';
                                    } else if (user.role === 'approving_authority') {
                                        accountTypeDisplay = 'Head of DRRMO';
                                    }
                                    
                                    return `
                                        <tr>
                                            <td class="ps-4 py-3 border-bottom border-light">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width:42px; height:42px; font-weight:bold; font-size: 14px;">
                                                        ${initials}
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark fs-6">${this.escapeHtml(user.fullName || 'No Name Set')}</div>
                                                        <div class="text-muted small">${this.escapeHtml(user.email)}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3 border-bottom border-light">
                                                <span class="badge bg-light text-dark border px-3 py-2 fw-semibold" style="font-size: 0.85rem;">
                                                    ${this.escapeHtml(accountTypeDisplay)}
                                                </span>
                                            </td>
                                            <td class="py-3 border-bottom border-light">${statusBadge}</td>
                                            <td class="pe-4 py-3 border-bottom border-light text-end">
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-bold"
                                                        onclick='userManagementPage.resetUserPassword(${user.userID}, ${JSON.stringify(user.email)})'>
                                                    Reset Password
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        const customModalHtml = `
            <div id="customViewUsersOverlay" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.6); z-index: 100000; display: flex; align-items: center; justify-content: center; padding: 20px;">
                <div id="customViewUsersModal" style="background: #f8f9fa; width: 90vw; max-width: 1400px; max-height: 95vh; border-radius: 12px; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); overflow: hidden; animation: modalFadeIn 0.2s ease-out;">
                    
                    <!-- Modal Header -->
                    <div style="background-color: #0d6efd; color: white; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span class="material-icons" style="font-size: 24px;">manage_accounts</span>
                            <h4 style="margin: 0; font-weight: 700; font-size: 1.25rem;">${this.escapeHtml(municipalityName)} User Accounts Overview</h4>
                        </div>
                        <button id="closeCustomModalBtn" style="background: transparent; border: none; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 4px; border-radius: 4px;">
                            <span class="material-icons" style="font-size: 28px;">close</span>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div style="padding: 24px; overflow-y: auto; flex-grow: 1;">
                        ${usersHtml}
                    </div>

                </div>
            </div>
            <style>
                @keyframes modalFadeIn {
                    from { opacity: 0; transform: scale(0.95) translateY(-20px); }
                    to { opacity: 1; transform: scale(1) translateY(0); }
                }
                #closeCustomModalBtn:hover { background: rgba(255,255,255,0.1) !important; }
            </style>
        `;
            
        const existingOverlay = document.getElementById('customViewUsersOverlay');
        if (existingOverlay) existingOverlay.remove();
        
        document.body.insertAdjacentHTML('beforeend', customModalHtml);
        
        // Add event listeners to close the modal
        const closeBtn = document.getElementById('closeCustomModalBtn');
        const overlay = document.getElementById('customViewUsersOverlay');
        const modalBox = document.getElementById('customViewUsersModal');
        
        const closeModal = () => {
            overlay.remove();
        };

        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal();
        });
    }

    async resetUserPassword(userId, email) {
        if (!userId) return;
        const ok = confirm(`Reset password for ${email}?\n\nThis will generate a new temporary password and immediately replace the existing one.`);
        if (!ok) return;

        try {
            const formData = new FormData();
            formData.append('user_id', String(userId));

            const response = await fetch('config/user_management_api.php?action=reset_user_password', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                this.showSuccessWithCredentials('Password reset successfully!', result.email, result.temporary_password);
            } else {
                this.showError(result.error || 'Failed to reset password');
            }
        } catch (error) {
            console.error('Error resetting password:', error);
            this.showError('Failed to reset password. Please try again.');
        }
    }

    async handleCreateUser(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        
        // Get generated credentials for display
        const email = document.getElementById('generatedEmail').value;
        const password = document.getElementById('generatedPassword').value;
        
        // Disable submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';

        try {
            const response = await fetch('config/user_management_api.php?action=create_user', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Show success message with credentials
                this.showSuccessWithCredentials('User account created successfully!', email, password);
                
                // Reset form
                this.resetForm();
                
                // Reload municipalities dropdown
                this.loadMunicipalitiesForDropdown();
                
                // If on municipalities overview tab, reload data
                const municipalitiesTab = document.getElementById('municipalities-overview');
                if (municipalitiesTab && municipalitiesTab.classList.contains('active')) {
                    this.loadMunicipalities();
                }
            } else {
                this.showError(result.error || 'Failed to create user account');
            }
        } catch (error) {
            console.error('Error creating user:', error);
            this.showError('Failed to create user account. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    resetForm() {
        const form = document.getElementById('createUserForm');
        if (form) {
            form.reset();
            document.getElementById('accountTypeSelection').style.display = 'none';
            document.getElementById('generatedCredentials').style.display = 'none';
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('userEmail').value = '';
            document.getElementById('userPassword').value = '';
            document.getElementById('accountTypeWarning').style.display = 'none';
            
            // Hide email badge
            document.getElementById('emailAccountTypeBadge').style.display = 'none';
            
            // Reset card states
            document.querySelectorAll('.account-type-card').forEach(card => {
                card.classList.remove('disabled', 'selected');
            });
        }
    }

    showSuccessWithCredentials(message, email, password) {
        // Create a detailed success alert with credentials
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show shadow-lg';
        alert.style.position = 'fixed';
        alert.style.top = '20px';
        alert.style.left = '50%';
        alert.style.transform = 'translateX(-50%)';
        alert.style.zIndex = '100010';
        alert.style.minWidth = '400px';
        alert.style.maxWidth = '90vw';
        
        // Store credentials in data attributes for copying
        alert.setAttribute('data-email', email);
        alert.setAttribute('data-password', password);
        
        alert.innerHTML = `
            <h6 class="alert-heading mb-3 fw-bold">
                <i class="fa-solid fa-check-circle"></i> ${message}
            </h6>
            <div class="credentials-display mb-3">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <strong>Email:</strong><br>
                        <code style="display: inline-block; margin-top: 5px;">${this.escapeHtml(email)}</code>
                        <button type="button" class="btn btn-sm btn-outline-success ms-2" onclick="copyToClipboard('${this.escapeHtml(email)}')">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </div>
                    <div class="col-md-6 mb-2">
                        <strong>Password:</strong><br>
                        <code style="display: inline-block; margin-top: 5px;">${this.escapeHtml(password)}</code>
                        <button type="button" class="btn btn-sm btn-outline-success ms-2" onclick="copyToClipboard('${this.escapeHtml(password)}')">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
            <p class="mb-0 text-dark"><strong>Please save these credentials!</strong> Share them with the user for their first login.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Auto-remove after 30 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 30000);
    }

    showError(message) {
        // Try the page container first for backwards compatibility
        const errorState = document.getElementById('errorState');
        const errorMessage = document.getElementById('errorMessage');
        if (errorState && errorMessage) {
            errorMessage.textContent = message;
            errorState.style.display = 'block';
            const loader = document.getElementById('loadingState');
            if (loader) loader.style.display = 'none';
        }

        // Always show a floating error alert so it works over modals
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show shadow-lg';
        alert.style.position = 'fixed';
        alert.style.top = '20px';
        alert.style.left = '50%';
        alert.style.transform = 'translateX(-50%)';
        alert.style.zIndex = '100010';
        alert.style.minWidth = '300px';
        
        alert.innerHTML = `
            <i class="fa-solid fa-exclamation-triangle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 8000);
    }

    showSuccess(message) {
        // Create a temporary success alert
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show shadow';
        alert.style.position = 'fixed';
        alert.style.top = '20px';
        alert.style.left = '50%';
        alert.style.transform = 'translateX(-50%)';
        alert.style.zIndex = '100010';
        alert.style.minWidth = '300px';
        
        alert.innerHTML = `
            <i class="fa-solid fa-check-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Global functions for form interactions
function copyToClipboard(elementIdOrValue) {
    let textToCopy = '';
    
    // Check if it's an element ID or direct value
    const element = document.getElementById(elementIdOrValue);
    if (element) {
        textToCopy = element.value;
        element.select();
        element.setSelectionRange(0, 99999); // For mobile devices
    } else {
        // It's a direct value
        textToCopy = elementIdOrValue;
    }
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(textToCopy).then(() => {
            // Show temporary feedback
            const btn = event?.target?.closest('button');
            if (btn) {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-outline-secondary', 'btn-outline-light');
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('btn-success');
                    if (btn.classList.contains('btn-outline-light')) {
                        btn.classList.add('btn-outline-light');
                    } else {
                        btn.classList.add('btn-outline-secondary');
                    }
                }, 2000);
            } else {
                // Fallback notification
                const tempAlert = document.createElement('div');
                tempAlert.className = 'alert alert-success position-fixed top-0 start-50 translate-middle-x mt-3';
                tempAlert.style.zIndex = '9999';
                tempAlert.innerHTML = '<i class="fa-solid fa-check"></i> Copied to clipboard!';
                document.body.appendChild(tempAlert);
                setTimeout(() => tempAlert.remove(), 2000);
            }
        }).catch(err => {
            // Fallback for older browsers
            if (element) {
                document.execCommand('copy');
            }
            alert('Copied to clipboard!');
        });
    } else {
        // Fallback for older browsers
        if (element) {
            document.execCommand('copy');
            alert('Copied to clipboard!');
        } else {
            // Create temporary textarea for copying
            const textarea = document.createElement('textarea');
            textarea.value = textToCopy;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('Copied to clipboard!');
        }
    }
}

function regeneratePassword() {
    if (window.userManagementPage) {
        const newPassword = window.userManagementPage.generatePassword();
        document.getElementById('generatedPassword').value = newPassword;
        document.getElementById('userPassword').value = newPassword;
    }
}

function resetForm() {
    if (window.userManagementPage) {
        window.userManagementPage.resetForm();
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.userManagementPage = new UserManagementPage();
        initializeTabs();
    });
} else {
    window.userManagementPage = new UserManagementPage();
    initializeTabs();
}

// Ensure tabs are properly initialized
function initializeTabs() {
    // Wait for Bootstrap to be available
    if (typeof bootstrap === 'undefined') {
        setTimeout(initializeTabs, 100);
        return;
    }

    // Get all tab buttons
    const tabButtons = document.querySelectorAll('#userManagementTabs .nav-link');
    const tabPanes = document.querySelectorAll('.tab-pane');

    // Ensure only the active tab is shown
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs and panes
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => {
                pane.classList.remove('active', 'show');
            });

            // Add active class to clicked tab
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            
            // Show corresponding pane
            const targetId = this.getAttribute('data-bs-target');
            if (targetId) {
                const targetPane = document.querySelector(targetId);
                if (targetPane) {
                    targetPane.classList.add('active', 'show');
                }
            }

            // Update aria-selected for other tabs
            tabButtons.forEach(btn => {
                if (btn !== this) {
                    btn.setAttribute('aria-selected', 'false');
                }
            });

            // Trigger Bootstrap tab event manually if needed
            if (bootstrap.Tab) {
                const tab = new bootstrap.Tab(this);
                tab.show();
            }
        });
    });

    // Ensure initial state is correct
    const activeTab = document.querySelector('#userManagementTabs .nav-link.active');
    if (activeTab) {
        const targetId = activeTab.getAttribute('data-bs-target');
        if (targetId) {
            const targetPane = document.querySelector(targetId);
            if (targetPane) {
                // Hide all panes
                tabPanes.forEach(pane => {
                    pane.classList.remove('active', 'show');
                });
                // Show active pane
                targetPane.classList.add('active', 'show');
            }
        }
    }
}
