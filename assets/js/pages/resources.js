/**
 * Resources Page JavaScript
 * Manages municipality resources overview and detailed views
 */

class ResourcesPage {
    constructor() {
        this.currentMunicipality = null;
        this.currentMunicipalityId = null;
        this.userMunicipalityId = window.userMunicipalityId || null; // Get from PHP session
        this.editingResourceId = null;
        this.municipalities = [];
        this.filteredMunicipalities = null;
        this.municipalitiesPerPage = 6; // two rows per page (3 cols on lg)
        this.currentMunicipalitiesPage = 1;
        this.resources = [];
        // All Resources view state
        this.viewMode = 'all';
        this.allResources = window.allResources || [];
        this.allResPage = 1;
        this.allResPageSize = parseInt(localStorage.getItem('allResPageSize') || '20', 10);
        this.allResSearch = '';
        this.allResType = '';
        this.allResStatus = '';
        this.allResSort = localStorage.getItem('allResSort') || '';
        this._resourceSnapshotById = new Map();
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.setupFilters();
        // Set initial counter to prevent 0 jump
        this.setInitialCounter();
        
        // Defer municipality loading until after initial render (non-blocking)
        // This improves navigation speed since loadMunicipalities uses static data from PHP
        requestAnimationFrame(() => {
            setTimeout(() => {
                this.loadMunicipalities();
            }, 100);
        });
        
        // Add direct event listener to manage resources button as backup
        this.setupManageResourcesButton();
        
        // Update subcategory dropdown - defer to avoid blocking (uses static data)
        requestAnimationFrame(() => {
            this.updateSubcategoryDropdown();
        });
        
        // Initialize view mode
        this.renderView();
        
        // Force sync after everything is loaded
        setTimeout(() => {
            this.syncHeaderControls();
        }, 100);
    }
    
    setInitialCounter() {
        const counter = document.getElementById('totalResourcesCount');
        if (counter) {
            counter.textContent = '--';
            counter.style.transition = 'none';
        }
    }
    
    setupManageResourcesButton() {
        // Add direct event listener as backup to onclick
        const manageButton = document.querySelector('button[onclick="manageMyResources()"]');
        if (manageButton) {
            manageButton.addEventListener('click', function(e) {
                e.preventDefault();
                manageMyResources();
            });
        }
    }
    
    bindEvents() {
        // Search functionality
        const municipalitySearch = document.getElementById('municipalitySearch');
        const resourceSearch = document.getElementById('resourceSearch');
        const viewSelect = document.getElementById('resourcesViewSelect');
        const allResSearch = document.getElementById('allResSearch');
        const allResTypeFilter = document.getElementById('allResTypeFilter');
        const allResStatusFilter = document.getElementById('allResStatusFilter');
        const allResSort = document.getElementById('allResSort');
        const allResPageSize = document.getElementById('allResPageSize');
        
        if (municipalitySearch) {
            municipalitySearch.addEventListener('input', this.filterMunicipalities.bind(this));
        }
        
        if (resourceSearch) {
            resourceSearch.addEventListener('input', this.filterResources.bind(this));
        }
        
        // Filter functionality
        const typeFilter = document.getElementById('resourceTypeFilter');
        const statusFilter = document.getElementById('statusFilter');
        const resourceSort = document.getElementById('resourceSort');
        
        if (typeFilter) {
            typeFilter.addEventListener('change', this.filterResources.bind(this));
        }
        
        if (statusFilter) {
            statusFilter.addEventListener('change', this.filterResources.bind(this));
        }
        if (resourceSort) {
            resourceSort.addEventListener('change', () => {
                this.renderResources(this.getFilteredResources());
            });
        }
        // Removed quick filter chips for a simpler UI
        
        // All Resources view controls
        if (viewSelect) {
            viewSelect.addEventListener('change', (e) => {
                this.viewMode = e.target.value;
                this.syncHeaderControls();
                this.renderView();
            });
            viewSelect.value = 'all';
        }
        if (allResSearch) {
            // Debounce search input for better performance
            let searchTimeout;
            allResSearch.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.allResSearch = e.target.value.toLowerCase();
                    this.allResPage = 1;
                    this.renderAllResources();
                }, 300); // 300ms delay
            });
        }
        if (allResTypeFilter) {
            allResTypeFilter.addEventListener('change', (e) => {
                this.allResType = e.target.value;
                this.allResPage = 1;
                this.renderAllResources();
            });
        }
        if (allResStatusFilter) {
            allResStatusFilter.addEventListener('change', (e) => {
                this.allResStatus = e.target.value;
                this.allResPage = 1;
                this.renderAllResources();
            });
        }
        if (allResSort) {
            // Set initial value from state/localStorage
            allResSort.value = this.allResSort || '';
            allResSort.addEventListener('change', (e) => {
                this.allResSort = e.target.value;
                localStorage.setItem('allResSort', this.allResSort || '');
                this.allResPage = 1;
                this.renderAllResources();
            });
        }
        // If page size selector is removed from UI, keep internal state as default and ignore
        
        // Modal backdrop close
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeAllModals();
            }
        });

        // Real-time status badge update in Modal
        const avStock = document.getElementById('availableStock');
        const dmgStock = document.getElementById('damagedStock');
        const minStock = document.getElementById('minimumStock');
        const totalStock = document.getElementById('totalStock');
        const resName = document.getElementById('resourceName');
        if (avStock) avStock.addEventListener('input', () => this.updateModalStatusBadge());
        if (dmgStock) dmgStock.addEventListener('input', () => this.updateModalStatusBadge());
        if (minStock) minStock.addEventListener('input', () => this.updateModalStatusBadge());
        if (totalStock) totalStock.addEventListener('input', () => this.updateItemizedUnitsForm());
        if (resName) resName.addEventListener('input', () => this.updateItemizedUnitsForm());
    }
    
    setupFilters() {
        // Initialize filter states
        this.currentFilters = {
            search: '',
            type: '',
            status: ''
        };
    }
    
    loadMunicipalities() {
        // Use static data from PHP - no API calls needed for municipality overview
        this.municipalities = (window.resourcesData || []).map(municipality => ({
            ...municipality,
            isOwn: municipality.id == this.userMunicipalityId
        }));
        this.renderMunicipalities();
        
        // Update total resources count
        const counter = document.getElementById('totalResourcesCount');
        if (counter) {
            counter.textContent = window.totalResources || 0;
        }
    }
    
    loadMockData() {
        // Use real data from PHP
        this.municipalities = window.resourcesData || [];
        this.renderMunicipalities();
        
        const counter = document.getElementById('totalResourcesCount');
        if (counter) {
            counter.textContent = window.totalResources || '0';
            counter.style.transition = 'none';
        }
    }
    
    renderMunicipalities(filteredMunicipalities = null) {
        const container = document.getElementById('municipalitiesGrid');
        this.filteredMunicipalities = filteredMunicipalities || this.municipalities;
        const total = this.filteredMunicipalities.length;
        const totalPages = Math.max(1, Math.ceil(total / this.municipalitiesPerPage));
        if (this.currentMunicipalitiesPage > totalPages) this.currentMunicipalitiesPage = totalPages;
        const startIdx = (this.currentMunicipalitiesPage - 1) * this.municipalitiesPerPage;
        const endIdx = startIdx + this.municipalitiesPerPage;
        const municipalitiesToRender = this.filteredMunicipalities.slice(startIdx, endIdx);
        
        if (municipalitiesToRender.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <span class="material-icons text-muted" style="font-size: 48px;">location_off</span>
                    <p class="text-muted mt-2">No municipalities found</p>
                </div>
            `;
            return;
        }
        
        // Function to shorten long municipality names
        const shortenName = (name) => {
            if (name.includes('Zamboanga del Sur')) {
                return name.replace('Zamboanga del Sur', 'ZDS');
            }
            if (name.includes('PDRRMO')) {
                return name.replace('PDRRMO', 'PDRRMO');
            }
            if (name.length > 20) {
                return name.substring(0, 17) + '...';
            }
            return name;
        };
        

        container.innerHTML = `
            <div class="row g-3">
                ${municipalitiesToRender.map(municipality => `
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <!-- Municipality Header -->
                                <div class="d-flex align-items-start mb-3">
                                    <div class="me-3">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <span class="material-icons text-primary">location_on</span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="card-title mb-1 fw-bold">${shortenName(municipality.name)}</h6>
                                        <small class="text-muted">${municipality.isOwn ? 'Your DRRMO' : 'External DRRMO'}</small>
                                    </div>
                                </div>
                                
                                <!-- Municipality Stats -->
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded border">
                                            <div class="fw-bold text-primary fs-5">${municipality.totalItems}</div>
                                            <small class="text-muted">Resources</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded border">
                                            <div class="fw-bold text-success fs-5">${municipality.resourceTypes}</div>
                                            <small class="text-muted">Types</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Last Updated -->
                                <div class="mb-3">
                                    <small class="text-muted d-flex align-items-center">
                                        <span class="material-icons me-1" style="font-size: 16px;">schedule</span>
                                        Updated: ${this.formatDate(municipality.lastUpdated)}
                                    </small>
                                </div>
                                
                                <!-- Action Button -->
                                <div class="mt-auto">
                                    <button class="btn btn-primary w-100" onclick="resourcesPage.viewMunicipalityResources(${municipality.id}, '${municipality.name}')">
                                        <span class="btn-content">
                                            <span class="material-icons">visibility</span>
                                            <span class="btn-text">View Resources</span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;

        // Render pagination controls
        this.renderMunicipalitiesPagination(this.currentMunicipalitiesPage, totalPages);
    }
    
    updateTotalResourcesCount() {
        const total = this.municipalities.reduce((sum, municipality) => sum + municipality.totalItems, 0);
        const counter = document.getElementById('totalResourcesCount');
        if (counter) {
            // Set the number directly without animation to prevent twitching
            counter.textContent = total;
        }
    }
    
    animateNumber(element, start, end, duration) {
        const startTime = performance.now();
        
        const update = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const current = Math.floor(start + (end - start) * progress);
            element.textContent = current;
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        };
        
        requestAnimationFrame(update);
    }
    
    filterMunicipalities() {
        const searchTerm = document.getElementById('municipalitySearch').value.toLowerCase();
        const filteredMunicipalities = this.municipalities.filter(municipality =>
            municipality.name.toLowerCase().includes(searchTerm)
        );
        this.currentMunicipalitiesPage = 1;
        this.renderMunicipalities(filteredMunicipalities);
    }

    renderMunicipalitiesPagination(current, total) {
        const container = document.getElementById('municipalitiesPagination');
        if (!container) return;
        container.innerHTML = '';
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
            if (!disabled && !active) btn.addEventListener('click', () => {
                this.currentMunicipalitiesPage = page;
                this.renderMunicipalities(this.filteredMunicipalities);
                const grid = document.getElementById('municipalitiesGrid');
                if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            li.appendChild(btn);
            return li;
        };

        const safeTotal = Math.max(1, total || 1);
        const safeCurrent = Math.min(Math.max(1, current || 1), safeTotal);

        ul.appendChild(create('Prev', safeCurrent - 1, safeCurrent === 1));
        for (let p = 1; p <= safeTotal; p++) {
            ul.appendChild(create(String(p), p, false, p === safeCurrent));
        }
        ul.appendChild(create('Next', safeCurrent + 1, safeCurrent === safeTotal));

        nav.appendChild(ul);
        container.appendChild(nav);
        container.style.display = safeTotal > 1 ? 'block' : 'none';
    }
    
    viewMunicipalityResources(municipalityId, municipalityName) {
        this.currentMunicipalityId = municipalityId;
        this.currentMunicipality = municipalityName;
        
        // Hide overview and show detail
        document.getElementById('resourcesOverview').style.display = 'none';
        document.getElementById('resourcesDetail').style.display = 'block';
        
        // Update detail header
        document.getElementById('municipalityName').textContent = municipalityName;
        document.getElementById('municipalityDescription').textContent = 'Resource management and inventory';
        
        // Setup actions based on permissions
        this.setupDetailActions(municipalityId);
        
        // Load resources for this municipality - no async
        this.loadMunicipalityResources(municipalityId);
    }
    
    setupDetailActions(municipalityId) {
        const actionsContainer = document.getElementById('detailActions');
        const isOwnMunicipality = municipalityId === this.userMunicipalityId;
        
        if (isOwnMunicipality) {
            actionsContainer.innerHTML = `
                <button class="btn btn-detail-add" onclick="resourcesPage.openAddResourceModal()">
                    <span class="material-icons">add</span>
                    Add Resource
                </button>
                <button class="btn btn-detail-export" onclick="resourcesPage.exportResources()">
                    <span class="material-icons">download</span>
                    Export
                </button>
            `;
        } else {
            actionsContainer.innerHTML = `
                <button class="btn btn-detail-export" onclick="resourcesPage.exportResources()">
                    <span class="material-icons">download</span>
                    Export
                </button>
            `;
        }
    }
    
    loadMunicipalityResources(municipalityId) {
        console.log('Loading resources for municipality ID:', municipalityId);
        console.log('Available static resources:', window.allResources?.length || 0);
        
        // For viewing a specific municipality's resources, use static data
        // The API get_resources.php excludes the current user's municipality
        // So we need to use the static data that includes all municipalities
        
        if (window.allResources && window.allResources.length > 0) {
            // Filter resources by the selected municipality from static data
            this.resources = window.allResources.filter(resource => 
                resource.drrmoID == municipalityId
            );
            console.log('Filtered resources for municipality:', this.resources.length);
            console.log('Sample resource:', this.resources[0]);
            this.renderResources();
        } else {
            console.log('No static resources available, trying API...');
            // Fallback: try to fetch from API (but this might not work for own municipality)
            fetch('config/get_resources.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.resources) {
                        // Filter resources by the selected municipality
                        this.resources = data.resources.filter(resource => 
                            resource.drrmoID == municipalityId
                        );
                        console.log('API resources for municipality:', this.resources.length);
                        this.renderResources();
                    } else {
                        console.error('Error loading resources:', data.message);
                        // Show empty state
                        this.resources = [];
                        this.renderResources();
                    }
                })
                .catch(error => {
                    console.error('Error fetching resources:', error);
                    // Show empty state
                    this.resources = [];
                    this.renderResources();
                });
        }
    }
    
    refreshMunicipalityResourcesFromAPI(municipalityId, deletedResourceId = null) {
        console.log('Refreshing resources from API for municipality ID:', municipalityId);
        
        // Use provided deletedResourceId or fall back to editingResourceId
        const resourceIdToRemove = deletedResourceId || this.editingResourceId;
        
        // Fetch fresh data from API using POST with JSON body (as expected by the API)
        fetch('config/get_resources_by_municipality.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ municipalityId: municipalityId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.resources) {
                    this.resources = data.data.resources;
                    console.log('Refreshed resources from API:', this.resources.length);
                    
                    // Also update the static window.allResources to keep it in sync
                    if (window.allResources && Array.isArray(window.allResources)) {
                        // Remove deleted resources if specified
                        if (resourceIdToRemove) {
                            window.allResources = window.allResources.filter(r => r.id !== resourceIdToRemove);
                        }
                        // Add/update resources from the fresh API response
                        data.data.resources.forEach(resource => {
                            const existingIndex = window.allResources.findIndex(r => r.id === resource.id);
                            if (existingIndex >= 0) {
                                window.allResources[existingIndex] = resource;
                            } else {
                                window.allResources.push(resource);
                            }
                        });
                    }
                    
                    // Update this.allResources as well for the "All Resources" view
                    if (this.allResources && Array.isArray(this.allResources)) {
                        if (resourceIdToRemove) {
                            this.allResources = this.allResources.filter(r => r.id !== resourceIdToRemove);
                        }
                        data.data.resources.forEach(resource => {
                            const existingIndex = this.allResources.findIndex(r => r.id === resource.id);
                            if (existingIndex >= 0) {
                                this.allResources[existingIndex] = resource;
                            } else {
                                this.allResources.push(resource);
                            }
                        });
                    }
                    
                    this.renderResources();
                } else {
                    console.error('Error refreshing resources:', data.error?.message || data.message);
                    // Don't clear resources on error - keep what we have
                    // Only remove the deleted one if specified
                    if (resourceIdToRemove) {
                        this.resources = this.resources.filter(r => r.id !== resourceIdToRemove);
                        this.renderResources();
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching resources from API:', error);
                // Don't clear resources on error - keep what we have
                // Only remove the deleted one if specified
                if (resourceIdToRemove) {
                    this.resources = this.resources.filter(r => r.id !== resourceIdToRemove);
                    this.renderResources();
                }
            });
    }
    
    loadMockResources(municipalityId) {
        // Load mock resources to prevent jittering
        this.resources = [
            {
                id: 1,
                name: 'Emergency Food Rations',
                type: 'food',
                quantity: 150,
                minQuantity: 10,
                description: 'Ready-to-eat emergency food supplies',
                lastUpdated: new Date().toISOString()
            },
            {
                id: 2,
                name: 'Medical Kits',
                type: 'medical',
                quantity: 25,
                minQuantity: 5,
                description: 'Complete medical emergency kits',
                lastUpdated: new Date().toISOString()
            },
            {
                id: 3,
                name: 'Water Purification Tablets',
                type: 'water',
                quantity: 200,
                minQuantity: 20,
                description: 'Water treatment tablets for emergency use',
                lastUpdated: new Date().toISOString()
            }
        ];
        this.renderResources();
    }
    
    renderResources(filteredResources = null) {
        const tableBody = document.getElementById('resourcesTableBody');
        const resourcesToRender = filteredResources || this.resources;
        const isOwnMunicipality = this.currentMunicipalityId === this.userMunicipalityId;
        
        if (resourcesToRender.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: var(--spacing-xl); color: var(--text-muted);">
                        No resources found matching your criteria.
                    </td>
                </tr>
            `;
            return;
        }
        
        tableBody.innerHTML = resourcesToRender.map(resource => `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${resource.items && resource.items.length > 0 ? `
                            <button type="button" class="btn btn-link btn-sm p-0 toggle-items-btn" onclick="resourcesPage.toggleItems(this, ${resource.id})" style="color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border: none; background: none;">
                                <span class="material-icons" style="font-size: 20px; transition: transform 0.2s; color: var(--primary-color);">keyboard_arrow_right</span>
                            </button>
                        ` : ''}
                        <div>
                            <div style="font-weight: 600; color: var(--text-dark);">${resource.name}</div>
                            ${resource.plateNumber ? `
                                <div class="text-muted" style="font-size: 0.72rem; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0; font-family: monospace;">
                                    <span class="material-icons" style="font-size: 12px;">badge</span>
                                    ${resource.plateNumber}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </td>
                <td>${resource.subcategory || resource.category || 'N/A'}</td>
                <td>
                    <span style="font-weight: 600;">${resource.quantity}</span>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                        Min: ${resource.minQuantity || 0}
                    </div>
                </td>
                <td>
                    ${(resource.damagedStock > 0)
                        ? `<span class="badge bg-danger">${resource.damagedStock} Damaged</span>`
                        : '<span class="text-muted small">None</span>'}
                </td>
                <td>
                    <span class="status-badge ${this.getResourceStatus(resource) === 'Available' ? 'badge-available' : this.getResourceStatus(resource) === 'Low Stock' ? 'badge-low' : this.getResourceStatus(resource) === 'Damaged / Repairing' ? 'badge-repairing' : 'badge-out'}">
                        ${this.getResourceStatus(resource)}
                    </span>
                    ${((this.getResourceStatus(resource) === 'Out of Stock' || this.getResourceStatus(resource) === 'Unavailable') && resource.nextAvailableDate) ? `
                        <div class="text-muted mt-1" style="font-size: 0.72rem; line-height: 1.1; font-weight: 500;">
                            Available: ${this.formatDate(resource.nextAvailableDate)}
                        </div>
                    ` : ''}
                </td>
                <td>${this.formatDate(resource.lastUpdated)}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        ${isOwnMunicipality ? `
                            <button class="btn btn-outline-primary btn-sm" onclick="resourcesPage.editResource(${resource.id})" title="Edit">
                                <span class="material-icons" style="font-size: 16px;">edit</span>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="resourcesPage.deleteResource(${resource.id})" title="Delete">
                                <span class="material-icons" style="font-size: 16px;">delete</span>
                            </button>
                        ` : `
                            <span class="text-muted small">View Only</span>
                        `}
                    </div>
                </td>
            </tr>
            ${resource.items && resource.items.length > 0 ? `
                <tr id="items-row-${resource.id}" class="resource-items-row" style="display: none; background-color: #fafbfc;">
                    <td colspan="7" style="padding: 12px 24px;">
                        <div style="border-left: 3px solid #cbd5e1; padding-left: 16px; margin: 4px 0;">
                            <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                                <span>Itemized Units (${resource.items.length})</span>
                                ${isOwnMunicipality ? `
                                    <button class="btn btn-link btn-sm p-0" onclick="resourcesPage.manageItems(${resource.id})" style="font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; font-weight: 500; border: none; background: none; color: var(--primary-color);">
                                        <span class="material-icons" style="font-size: 14px;">settings</span> Manage Units
                                    </button>
                                ` : ''}
                            </div>
                            <div class="table-responsive" style="border: 1px solid #e2e8f0; border-radius: 6px; background: #ffffff; overflow: hidden;">
                                <table class="table table-sm table-borderless m-0" style="font-size: 0.85rem; width: 100%;">
                                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                        <tr>
                                            <th style="padding: 6px 12px; color: #475569; font-weight: 600; text-align: left;">Plate No. / Serial No. / ID</th>
                                            <th style="padding: 6px 12px; color: #475569; font-weight: 600; text-align: left;">Status</th>
                                            <th style="padding: 6px 12px; color: #475569; font-weight: 600; text-align: left;">Storage Location</th>
                                            <th style="padding: 6px 12px; color: #475569; font-weight: 600; text-align: left;">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${resource.items.map(item => `
                                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                                <td style="padding: 8px 12px; font-family: monospace; font-weight: 500; text-align: left;">
                                                    <span class="material-icons" style="font-size: 14px; vertical-align: middle; margin-right: 4px; color: #64748b;">badge</span>
                                                    ${item.uniqueIdentifier}
                                                </td>
                                                <td style="padding: 8px 12px; text-align: left;">
                                                    <span class="badge ${item.status === 'Available' ? 'bg-success' : item.status === 'Damaged / Repairing' ? 'bg-warning text-dark' : 'bg-secondary'}" style="font-size: 0.72rem; padding: 2px 6px;">
                                                        ${item.status}
                                                    </span>
                                                </td>
                                                <td style="padding: 8px 12px; color: #334155; text-align: left;">
                                                    ${item.storageLocation || '<span class="text-muted italic">Not specified</span>'}
                                                </td>
                                                <td style="padding: 8px 12px; color: #64748b; font-style: italic; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: left;" title="${item.conditionNotes || ''}">
                                                    ${item.conditionNotes || '-'}
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
            ` : ''}
        `).join('');
    }
    
    getFilteredResources() {
        const searchTerm = document.getElementById('resourceSearch')?.value.toLowerCase() || '';
        
        let filteredResources = this.resources.filter(resource => {
            const matchesSearch = (resource.name||'').toLowerCase().includes(searchTerm) ||
                                (resource.description && resource.description.toLowerCase().includes(searchTerm));
            
            return matchesSearch;
        });

        // Sort
        const sortKey = document.getElementById('resourceSort')?.value || '';
        if (sortKey) {
            const compareText = (a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' });
            const compareNum = (a, b) => a - b;
            filteredResources.sort((a, b) => {
                switch (sortKey) {
                    case 'name_asc':
                        return compareText((a.name||''), (b.name||''));
                    case 'name_desc':
                        return compareText((b.name||''), (a.name||''));
                    case 'qty_desc':
                        return compareNum((b.quantity||0), (a.quantity||0));
                    case 'qty_asc':
                        return compareNum((a.quantity||0), (b.quantity||0));
                    case 'status': {
                        const va = this.getResourceStatus(a) === 'Available' ? 0 : (this.getResourceStatus(a) === 'Low Stock' ? 1 : this.getResourceStatus(a) === 'Damaged / Repairing' ? 2 : 3);
                        const vb = this.getResourceStatus(b) === 'Available' ? 0 : (this.getResourceStatus(b) === 'Low Stock' ? 1 : this.getResourceStatus(b) === 'Damaged / Repairing' ? 2 : 3);
                        if (va !== vb) return va - vb;
                        return compareText((a.name||''), (b.name||''));
                    }
                    case 'updated_desc': {
                        const toTs = (d) => { const iso = String(d||'').replace(' ', 'T'); const t = Date.parse(iso); return isNaN(t) ? 0 : t; };
                        return toTs(b.lastUpdated) - toTs(a.lastUpdated);
                    }
                    default:
                        return 0;
                }
            });
        }

        return filteredResources;
    }

    filterResources() {
        this.renderResources(this.getFilteredResources());
    }
    
    getResourceStatus(resource) {
        if (resource.damagedStock > 0 && resource.quantity === 0) return 'Damaged / Repairing';
        if (resource.quantity === 1 && resource.damagedStock === 1) return 'Damaged / Repairing';
        if (resource.quantity === 0) return 'Out of Stock';
        if (resource.quantity <= resource.minQuantity) return 'Low Stock';
        return 'Available';
    }
    
    getStatusClass(resource) {
        const status = this.getResourceStatus(resource);
        if (status === 'Available') return 'status-available';
        if (status === 'Low Stock') return 'status-low';
        if (status === 'Damaged / Repairing') return 'status-repairing';
        return 'status-out';
    }
    
    cleanMunicipalityName(name) {
        // Remove CDRRMO/MDRRMO prefixes
        return name.replace(/^(CDRRMO|MDRRMO)\s+/i, '');
    }
    
    updateSubcategoryDropdown() {
        const dropdown = document.getElementById('allResTypeFilter');
        if (!dropdown || !window.availableSubcategories) return;
        
        // Clear existing options except "All Subcategories"
        dropdown.innerHTML = '<option value="">All Subcategories</option>';
        
        // Add actual subcategories from database, excluding "Unmapped" entries
        window.availableSubcategories.forEach(subcat => {
            if (!subcat.startsWith('Unmapped')) {
                dropdown.innerHTML += `<option value="${subcat}">${subcat}</option>`;
            }
        });
    }
    
    formatResourceType(type) {
        // Handle the actual data format from database
        const typeMap = {
            'medical-supplies': 'Medical Supplies',
            'emergency-equipment': 'Emergency Equipment',
            'communication-equipment': 'Communication Equipment',
            'food-supplies': 'Food Supplies',
            'rescue-equipment': 'Rescue Equipment',
            'shelter-materials': 'Shelter Materials',
            'transportation': 'Transportation',
            'other': 'Other'
        };
        
        // If exact match found, use it
        if (typeMap[type]) {
            return typeMap[type];
        }
        
        // Fallback: try to match by prefix
        if (type.includes('medical')) return 'Medical Equipment';
        if (type.includes('emergency')) return 'Emergency Equipment';
        if (type.includes('communication')) return 'Communication Equipment';
        if (type.includes('food')) return 'Food Supplies';
        if (type.includes('rescue')) return 'Rescue Equipment';
        if (type.includes('shelter')) return 'Shelter Materials';
        if (type.includes('transport')) return 'Transportation';
        
        // Final fallback: capitalize and clean up
        return type.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
    
    // Modal Functions
    openAddResourceModal() {
        this.editingResourceId = null;
        this.updateModalContent('Add New Resource', 'Fill in the details below to add a new resource to your inventory', 'Save Resource');
        document.getElementById('resourceForm').reset();
        const statusBadge = document.getElementById('modalStatusBadge');
        if (statusBadge) {
            statusBadge.style.display = 'none';
        }
        this.updateItemizedUnitsForm();
        document.getElementById('resourceModal').classList.add('active');
        document.getElementById('resourceModal').style.display = 'flex';
    }
    
    editResource(resourceId) {
        const resource = this.resources.find(r => r.id === resourceId);
        if (!resource) {
            console.error('Resource not found:', resourceId);
            return;
        }
        
        console.log('Editing resource:', resource);
        
        this.editingResourceId = resourceId;
        this.updateModalContent('Edit Resource', 'Update the details for this resource', 'Update Resource');
        
        // Wait for modal to be visible before populating fields
        setTimeout(() => {
            console.log('Populating form fields...');
            
            // Populate form with all database fields
            const nameField = document.getElementById('resourceName');
            const categoryField = document.getElementById('resourceCategory');
            const subcategoryField = document.getElementById('resourceSubcategory');
            const unitField = document.getElementById('resourceUnit');
            const descriptionField = document.getElementById('resourceDescription');
            const totalStockField = document.getElementById('totalStock');
            const availableStockField = document.getElementById('availableStock');
            const damagedStockField = document.getElementById('damagedStock');
            const minimumStockField = document.getElementById('minimumStock');
            const storageLocationField = document.getElementById('storageLocation');
            const plateNumberField = document.getElementById('plateNumber');
            
            // Handle both field naming conventions (quantity vs availableStock, minQuantity vs minimumStock)
            const resourceName = resource.name || resource.resourceName || '';
            const resourceCategory = resource.category || '';
            const resourceSubcategory = resource.subcategory || '';
            const resourceUnit = resource.unit || '';
            const resourceDescription = resource.description || '';
            const resourceTotalStock = resource.totalStock ?? (resource.quantity ?? 0);
            const resourceAvailableStock = resource.availableStock ?? (resource.quantity ?? 0);
            const resourceDamagedStock = resource.damagedStock ?? 0;
            const resourceMinimumStock = resource.minimumStock ?? (resource.minQuantity ?? 0);
            const resourceStorageLocation = resource.storageLocation || '';
            const resourcePlateNumber = resource.plateNumber || '';
            
            if (nameField) nameField.value = resourceName;
            if (categoryField) categoryField.value = resourceCategory;
            if (subcategoryField) subcategoryField.value = resourceSubcategory;
            if (unitField) unitField.value = resourceUnit;
            if (descriptionField) descriptionField.value = resourceDescription;
            if (totalStockField) totalStockField.value = resourceTotalStock;
            if (availableStockField) availableStockField.value = resourceAvailableStock;
            if (damagedStockField) damagedStockField.value = resourceDamagedStock;
            if (minimumStockField) minimumStockField.value = resourceMinimumStock;
            if (storageLocationField) storageLocationField.value = resourceStorageLocation;
            if (plateNumberField) plateNumberField.value = resourcePlateNumber;
            
            console.log('Form fields populated:', {
                name: nameField?.value,
                totalStock: totalStockField?.value,
                availableStock: availableStockField?.value,
                minimumStock: minimumStockField?.value,
                storageLocation: storageLocationField?.value
            });

            this.updateModalStatusBadge();
            this.updateItemizedUnitsForm(resource);
        }, 100);
        
        document.getElementById('resourceModal').classList.add('active');
        document.getElementById('resourceModal').style.display = 'flex';
    }
    
    updateModalContent(title, subtitle, buttonText) {
        const titleTextEl = document.getElementById('modalTitleText');
        if (titleTextEl) {
            titleTextEl.textContent = title;
        } else {
            document.getElementById('modalTitle').textContent = title;
        }
        document.getElementById('modalSubtitle').textContent = subtitle;
        document.getElementById('saveButtonText').textContent = buttonText;
    }

    updateModalStatusBadge() {
        const availableStockVal = parseInt(document.getElementById('availableStock')?.value) || 0;
        const damagedStockVal = parseInt(document.getElementById('damagedStock')?.value) || 0;
        const minimumStockVal = parseInt(document.getElementById('minimumStock')?.value) || 0;
        
        const resourceStatus = this.getResourceStatus({
            quantity: availableStockVal,
            minQuantity: minimumStockVal,
            damagedStock: damagedStockVal
        });
        
        const statusBadge = document.getElementById('modalStatusBadge');
        if (statusBadge) {
            statusBadge.textContent = resourceStatus;
            statusBadge.style.display = 'inline-block';
            statusBadge.className = 'status-badge ' + (
                resourceStatus === 'Available' ? 'badge-available' :
                resourceStatus === 'Low Stock' ? 'badge-low' :
                resourceStatus === 'Damaged / Repairing' ? 'badge-repairing' : 'badge-out'
            );
        }
    }

    toggleItems(btn, id) {
        const row = document.getElementById(`items-row-${id}`);
        const icon = btn.querySelector('.material-icons');
        if (row) {
            if (row.style.display === 'none') {
                row.style.display = 'table-row';
                if (icon) icon.style.transform = 'rotate(90deg)';
            } else {
                row.style.display = 'none';
                if (icon) icon.style.transform = 'rotate(0deg)';
            }
        }
    }

    manageItems(resourceId) {
        this.editResource(resourceId);
    }

    updateItemizedUnitsForm(resource = null) {
        const totalStockField = document.getElementById('totalStock');
        const count = parseInt(totalStockField?.value) || 0;
        const section = document.getElementById('itemizedUnitsSection');
        const container = document.getElementById('itemizedUnitsList');
        
        if (!container || !section) return;
        
        if (count <= 0) {
            section.style.display = 'none';
            container.innerHTML = '';
            return;
        }
        
        section.style.display = 'block';
        
        const existingData = [];
        const items = container.querySelectorAll('.item-unit-row');
        items.forEach((row) => {
            existingData.push({
                id: row.dataset.id || null,
                uniqueIdentifier: row.querySelector('.item-identifier')?.value || '',
                status: row.querySelector('.item-status')?.value || 'Available',
                storageLocation: row.querySelector('.item-location')?.value || '',
                conditionNotes: row.querySelector('.item-notes')?.value || ''
            });
        });
        
        const sourceItems = (existingData.length === 0 && resource && resource.items) ? resource.items : existingData;
        
        let html = '';
        const resourceName = document.getElementById('resourceName')?.value || 'Unit';
        
        for (let i = 0; i < count; i++) {
            const item = sourceItems[i] || {};
            const itemIndex = i + 1;
            const itemId = item.id || '';
            const identifier = item.uniqueIdentifier || (resourceName + ' Unit #' + itemIndex);
            const status = item.status || 'Available';
            const location = item.storageLocation || document.getElementById('storageLocation')?.value || '';
            const notes = item.conditionNotes || '';
            
            html += `
                <div class="item-unit-row" data-index="${i}" data-id="${itemId}" style="border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; background: var(--bg-light); text-align: left; margin-bottom: 8px;">
                    <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-dark); margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                        <span>Unit #${itemIndex}</span>
                        ${itemId ? `<span style="font-size: 0.72rem; color: var(--text-muted);">Database ID: #${itemId}</span>` : '<span style="font-size: 0.72rem; color: var(--primary-color);">New Unit</span>'}
                    </div>
                    <div class="form-row-three" style="margin-bottom: 8px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.75rem; margin-bottom: 4px; display: block; text-align: left; font-weight: 500;">Plate / Serial / ID</label>
                            <input type="text" class="item-identifier" value="${identifier}" placeholder="e.g. Plate No." style="width: 100%; padding: 6px 10px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: 4px;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.75rem; margin-bottom: 4px; display: block; text-align: left; font-weight: 500;">Status</label>
                            <select class="item-status" style="width: 100%; padding: 6px 10px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: 4px; background: #fff;" onchange="resourcesPage.syncStocksFromItemStatuses()">
                                <option value="Available" ${status === 'Available' ? 'selected' : ''}>Available</option>
                                <option value="Damaged / Repairing" ${status === 'Damaged / Repairing' ? 'selected' : ''}>Damaged / Repairing</option>
                                <option value="Decommissioned" ${status === 'Decommissioned' ? 'selected' : ''}>Decommissioned</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.75rem; margin-bottom: 4px; display: block; text-align: left; font-weight: 500;">Storage Location</label>
                            <input type="text" class="item-location" value="${location}" placeholder="Storage Location" style="width: 100%; padding: 6px 10px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: 4px;">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem; margin-bottom: 4px; display: block; text-align: left; font-weight: 500;">Condition Notes / Issues</label>
                        <input type="text" class="item-notes" value="${notes}" placeholder="Specify any issues or details" style="width: 100%; padding: 6px 10px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: 4px;">
                    </div>
                </div>
            `;
        }
        
        container.innerHTML = html;
        this.syncStocksFromItemStatuses();
    }

    syncStocksFromItemStatuses() {
        const container = document.getElementById('itemizedUnitsList');
        if (!container) return;
        
        const rows = container.querySelectorAll('.item-unit-row');
        let availableCount = 0;
        let damagedCount = 0;
        
        rows.forEach(row => {
            const status = row.querySelector('.item-status')?.value;
            if (status === 'Available') {
                availableCount++;
            } else if (status === 'Damaged / Repairing') {
                damagedCount++;
            }
        });
        
        const avStockField = document.getElementById('availableStock');
        const dmgStockField = document.getElementById('damagedStock');
        
        if (avStockField) avStockField.value = availableCount;
        if (dmgStockField) dmgStockField.value = damagedCount;
        
        this.updateModalStatusBadge();
    }
    
    saveResource() {
        const form = document.getElementById('resourceForm');
        const formData = new FormData(form);
        
        const resourceData = {
            name: formData.get('name'),
            category: formData.get('category'),
            subcategory: formData.get('subcategory') || null,
            unit: formData.get('unit'),
            description: formData.get('description'),
            totalStock: parseInt(formData.get('totalStock')) || 0,
            availableStock: parseInt(formData.get('availableStock')) || 0,
            damagedStock: parseInt(formData.get('damagedStock')) || 0,
            minimumStock: parseInt(formData.get('minimumStock')) || 0,
            storageLocation: formData.get('storageLocation') || null,
            plateNumber: formData.get('plateNumber') || null
        };

        const items = [];
        const itemRows = document.querySelectorAll('#itemizedUnitsList .item-unit-row');
        itemRows.forEach(row => {
            items.push({
                id: row.dataset.id ? parseInt(row.dataset.id) : null,
                uniqueIdentifier: row.querySelector('.item-identifier')?.value || '',
                status: row.querySelector('.item-status')?.value || 'Available',
                storageLocation: row.querySelector('.item-location')?.value || '',
                conditionNotes: row.querySelector('.item-notes')?.value || ''
            });
        });
        resourceData.items = items;
        
        if (this.editingResourceId) {
            resourceData.id = this.editingResourceId;
        }
        
        // Validate form
        if (!resourceData.name || !resourceData.category || !resourceData.unit || 
            resourceData.totalStock < 0 || resourceData.availableStock < 0 || resourceData.damagedStock < 0) {
            this.showError('Please fill in all required fields correctly.');
            return;
        }
        
        if (resourceData.availableStock > resourceData.totalStock) {
            this.showError('Available stock cannot be greater than total stock.');
            return;
        }

        if ((resourceData.availableStock + resourceData.damagedStock) > resourceData.totalStock) {
            this.showError('Available stock + Damaged stock cannot be greater than total stock.');
            return;
        }
        
        // Optimistic UI: update table immediately for edits
        let didOptimisticallyUpdate = false;
        if (this.editingResourceId) {
            const idx = this.resources.findIndex(r => r.id === this.editingResourceId);
            if (idx !== -1) {
                const original = { ...this.resources[idx] };
                this._resourceSnapshotById.set(this.editingResourceId, original);
                this.resources[idx] = {
                    ...this.resources[idx],
                    name: resourceData.name,
                    resourceName: resourceData.name,
                    category: resourceData.category,
                    subcategory: resourceData.subcategory,
                    quantity: resourceData.availableStock, // Update display quantity
                    totalStock: resourceData.totalStock,
                    availableStock: resourceData.availableStock,
                    damagedStock: resourceData.damagedStock,
                    minimumStock: resourceData.minimumStock,
                    minQuantity: resourceData.minimumStock, // Update display minQuantity
                    unit: resourceData.unit,
                    description: resourceData.description,
                    storageLocation: resourceData.storageLocation,
                    plateNumber: resourceData.plateNumber,
                    lastUpdated: new Date().toISOString()
                };
                this.renderResources();
                didOptimisticallyUpdate = true;
            }
        }
        
        // Make API call
        const url = this.editingResourceId ? 'config/update_resource.php' : 'config/add_resource.php';
        const method = this.editingResourceId ? 'PUT' : 'POST';
        
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(resourceData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSuccess(this.editingResourceId ? 'Resource updated successfully!' : 'Resource added successfully!');
                this.closeResourceModal();
                
                // For new resources, add optimistically with proper structure
                if (!this.editingResourceId && data.resourceId) {
                    const newResource = {
                        id: data.resourceId,
                        name: resourceData.name,
                        resourceName: resourceData.name,
                        category: resourceData.category,
                        subcategory: resourceData.subcategory,
                        quantity: resourceData.availableStock,
                        totalStock: resourceData.totalStock,
                        availableStock: resourceData.availableStock,
                        damagedStock: resourceData.damagedStock,
                        minimumStock: resourceData.minimumStock,
                        minQuantity: resourceData.minimumStock,
                        unit: resourceData.unit,
                        description: resourceData.description,
                        storageLocation: resourceData.storageLocation,
                        plateNumber: resourceData.plateNumber,
                        lastUpdated: new Date().toISOString()
                    };
                    this.resources.push(newResource);
                    this.renderResources();
                }
                
                // Ensure real-time consistency: refresh from server after a short delay
                if (this.currentMunicipalityId) {
                    setTimeout(() => {
                        fetch('config/get_resources_by_municipality.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ municipalityId: this.currentMunicipalityId })
                        })
                        .then(r => r.json())
                        .then(j => {
                            if (j.success && j.data && Array.isArray(j.data.resources)) {
                                this.resources = j.data.resources;
                                this.renderResources();
                            }
                        })
                        .catch(() => {});
                        // Also refresh header notifications badge/list
                        try {
                            if (window.headerComponent) {
                                const badge = document.getElementById('notifBadge');
                                // only badge when dropdown closed
                                window.headerComponent.loadNotifications(null, badge);
                            }
                        } catch (e) {}
                    }, 1000);
                }
                
                // Also refresh the municipalities overview to update counts (with small delay for smooth UX)
                setTimeout(() => {
                    this.refreshMunicipalitiesOverview();
                }, 500);
            } else {
                this.showError(data.message || 'An error occurred');
                // Revert optimistic update on failure
                if (didOptimisticallyUpdate && this.editingResourceId && this._resourceSnapshotById.has(this.editingResourceId)) {
                    const snapshot = this._resourceSnapshotById.get(this.editingResourceId);
                    const idx = this.resources.findIndex(r => r.id === this.editingResourceId);
                    if (idx !== -1) {
                        this.resources[idx] = snapshot;
                        this.renderResources();
                    }
                    this._resourceSnapshotById.delete(this.editingResourceId);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showError('An error occurred while saving the resource');
            // Revert optimistic update on network error
            if (didOptimisticallyUpdate && this.editingResourceId && this._resourceSnapshotById.has(this.editingResourceId)) {
                const snapshot = this._resourceSnapshotById.get(this.editingResourceId);
                const idx = this.resources.findIndex(r => r.id === this.editingResourceId);
                if (idx !== -1) {
                    this.resources[idx] = snapshot;
                    this.renderResources();
                }
                this._resourceSnapshotById.delete(this.editingResourceId);
            }
        });
    }
    
    deleteResource(resourceId) {
        const resource = this.resources.find(r => r.id === resourceId);
        if (!resource) return;
        
        this.editingResourceId = resourceId;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    
    confirmDelete() {
        if (!this.editingResourceId) {
            this.showError('No resource selected for deletion');
            this.closeDeleteModal();
            return;
        }
        
        const resourceIdToDelete = this.editingResourceId;
        console.log('Attempting to delete resource ID:', resourceIdToDelete);
        
        // Make API call to delete resource
        // Use POST instead of DELETE for better compatibility (XAMPP and some servers don't handle DELETE properly)
        fetch('config/delete_resource.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: resourceIdToDelete })
        })
        .then(response => {
            console.log('Delete response status:', response.status, response.statusText);
            return response.json().then(data => {
                return { ok: response.ok, status: response.status, data: data };
            }).catch(err => {
                // If JSON parsing fails, return error info
                console.error('Failed to parse JSON:', err);
                return { ok: false, status: response.status, data: { success: false, error: { message: 'Invalid server response' } } };
            });
        })
        .then(result => {
            console.log('Delete result:', result);
            
            if (!result.ok || !result.data.success) {
                // Extract error message from various possible structures
                let errorMessage = 'An error occurred while deleting the resource';
                
                if (result.data) {
                    if (result.data.error && result.data.error.message) {
                        errorMessage = result.data.error.message;
                    } else if (result.data.message) {
                        errorMessage = result.data.message;
                    } else if (typeof result.data.error === 'string') {
                        errorMessage = result.data.error;
                    }
                }
                
                console.error('Delete failed:', errorMessage, result.data);
                this.showError(errorMessage);
                return;
            }
            
            // Success - immediately remove from local array for instant UI update
            const deletedResourceId = resourceIdToDelete;
            this.resources = this.resources.filter(r => r.id !== deletedResourceId);
            
            // Also update static arrays
            if (window.allResources && Array.isArray(window.allResources)) {
                window.allResources = window.allResources.filter(r => r.id !== deletedResourceId);
            }
            if (this.allResources && Array.isArray(this.allResources)) {
                this.allResources = this.allResources.filter(r => r.id !== deletedResourceId);
            }
            
            // Re-render immediately with updated data
            this.renderResources();
            
            // If viewing "All Resources" view, re-render it as well
            if (this.viewMode === 'all') {
                this.renderAllResources();
            }
            
            this.showSuccess('Resource deleted successfully!');
            
            // Reload resources for current municipality with fresh API data to ensure consistency
            if (this.currentMunicipalityId) {
                this.refreshMunicipalityResourcesFromAPI(this.currentMunicipalityId, deletedResourceId);
            }
            
            // Also refresh the municipalities overview to update counts (with small delay for smooth UX)
            setTimeout(() => {
                this.refreshMunicipalitiesOverview();
            }, 500);
        })
        .catch(error => {
            console.error('Error deleting resource:', error);
            // Show the actual error message or a generic one
            const errorMessage = error.message || 'Network error: Could not connect to server';
            this.showError(errorMessage);
        })
        .finally(() => {
            // Close modal after operation completes (success or error)
            this.closeDeleteModal();
        });
    }
    
    closeResourceModal() {
        document.getElementById('resourceModal').classList.remove('active');
        document.getElementById('resourceModal').style.display = 'none';
        this.editingResourceId = null;
    }
    
    closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
        this.editingResourceId = null;
    }
    
    refreshMunicipalitiesOverview() {
        // Only refresh if we're currently viewing the overview
        const overview = document.getElementById('resourcesOverview');
        if (overview && overview.style.display !== 'none') {
            // Show subtle loading indicator
            const counter = document.getElementById('totalResourcesCount');
            if (counter) {
                counter.textContent = '...';
            }
            this.loadMunicipalities();
        }
    }
    
    closeAllModals() {
        this.closeResourceModal();
        this.closeDeleteModal();
    }
    
    exportResources() {
        // Export current resources list as CSV (openable in Excel)
        const headers = [
            'Resource Name',
            'Subcategory',
            'Category',
            'Quantity',
            'Minimum',
            'Status',
            'Last Updated'
        ];

        const escapeCsv = (val) => {
            const s = String(val ?? '');
            if (/[",\n]/.test(s)) {
                return '"' + s.replace(/"/g, '""') + '"';
            }
            return s;
        };

        const rows = this.resources.map(r => [
            escapeCsv(r.name),
            escapeCsv(r.subcategory || ''),
            escapeCsv(r.category || ''),
            escapeCsv(r.quantity ?? ''),
            escapeCsv(r.minQuantity ?? ''),
            escapeCsv(this.getResourceStatus(r)),
            escapeCsv(this.formatDate(r.lastUpdated))
        ]);

        const csv = [headers.map(escapeCsv).join(','), ...rows.map(row => row.join(','))].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);

        const safeMunicipality = (this.currentMunicipality || 'municipality').replace(/\s+/g, '_').toLowerCase();
        const link = document.createElement('a');
        link.href = url;
        link.download = `${safeMunicipality}_resources.csv`;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        setTimeout(function () {
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }, 150);
        
        this.showSuccess('CSV exported successfully.');
    }
    
    // Utility functions
    showSuccess(message) {
        if (window.municipalityDashboard) {
            window.municipalityDashboard.showNotification(message, 'success');
        }
    }
    
    showError(message) {
        if (window.municipalityDashboard) {
            window.municipalityDashboard.showNotification(message, 'error');
        }
    }

    syncHeaderControls() {
        // Hide/show the input group containing the municipality search
        const muniSearch = document.getElementById('municipalitySearch');
        if (muniSearch && muniSearch.closest('.input-group')) {
            muniSearch.closest('.input-group').style.display = this.viewMode === 'overview' ? 'flex' : 'none';
        }
        
        // Also hide/show the entire page section header
        const pageSectionHeader = document.querySelector('.page-section-header');
        if (pageSectionHeader) {
            pageSectionHeader.style.display = this.viewMode === 'overview' ? 'flex' : 'none';
        }
        
        // Hide/show the summary container that shows "Total Resources"
        const summary = document.querySelector('.resources-summary');
        if (summary) {
            summary.style.display = this.viewMode === 'overview' ? 'block' : 'none';
        }
    }

    renderView() {
        const overviewCardGrid = document.getElementById('municipalitiesGrid');
        const overviewPaginate = document.getElementById('municipalitiesPagination');
        const allContainer = document.getElementById('allResourcesContainer');
        const resourcesDetail = document.getElementById('resourcesDetail');
        
        if (!overviewCardGrid || !allContainer) {
            console.error('Missing elements for renderView');
            return;
        };
        
        if (this.viewMode === 'overview') {
            // Show overview grid inside the card body
            overviewCardGrid.style.display = 'block';
            if (overviewPaginate) {
                overviewPaginate.style.display = 'flex';
                overviewPaginate.style.visibility = 'visible';
            }
            allContainer.style.display = 'none';
            // Ensure outer detail section stays hidden unless explicitly opened
            if (resourcesDetail) {
                resourcesDetail.style.display = 'none';
            }
            this.renderMunicipalities();
        } else {
            // Show All Resources table
            overviewCardGrid.style.display = 'none';
            if (overviewPaginate) {
                overviewPaginate.style.display = 'none';
                overviewPaginate.style.visibility = 'hidden';
            }
            allContainer.style.display = 'block';
            if (resourcesDetail) {
                resourcesDetail.style.display = 'none';
            }
            this.renderAllResources();
        }
    }

    getResourceStatusForRow(resource) {
        if (resource.damagedStock > 0 && resource.quantity === 0) return 'Damaged / Repairing';
        if (resource.quantity === 1 && resource.damagedStock === 1) return 'Damaged / Repairing';
        return resource.quantity > 0 ? 'Available' : 'Unavailable';
    }

    renderAllResources() {
        const tbody = document.getElementById('allResourcesTableBody');
        const pagination = document.getElementById('allResourcesPagination');
        if (!tbody || !pagination) {
            console.error('Missing required elements for renderAllResources');
            return;
        }

        // Show loading state
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="d-flex justify-content-center align-items-center"><div class="spinner-border spinner-border-sm text-primary me-2" role="status"><span class="visually-hidden">Loading...</span></div><span>Loading resources...</span></div></td></tr>';

        // Validate data
        if (!this.allResources || !Array.isArray(this.allResources)) {
            console.error('Invalid allResources data');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Error loading resources data</td></tr>';
            return;
        }

        // Filter
        let filtered = this.allResources.filter(r => {
            const matchSearch = !this.allResSearch || (
                (r.name || '').toLowerCase().includes(this.allResSearch) ||
                this.cleanMunicipalityName(r.drrmoName || '').toLowerCase().includes(this.allResSearch)
            );
            // Improved type matching - check both subcategory and category
            const matchType = !this.allResType || 
                (r.subcategory === this.allResType) || 
                (r.category === this.allResType) ||
                (r.type === this.allResType);
            const status = this.getResourceStatusForRow(r).toLowerCase();
            const matchStatus = !this.allResStatus || status.includes(this.allResStatus);
            return matchSearch && matchType && matchStatus;
        });

        // Sorting
        const sortKey = this.allResSort || '';
        if (sortKey) {
            const compareText = (a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' });
            const compareNum = (a, b) => a - b;
            filtered.sort((a, b) => {
                switch (sortKey) {
                    case 'name_asc':
                        return compareText((a.name||''), (b.name||''));
                    case 'name_desc':
                        return compareText((b.name||''), (a.name||''));
                    case 'municipality_asc':
                        return compareText(this.cleanMunicipalityName(a.drrmoName||''), this.cleanMunicipalityName(b.drrmoName||''));
                    case 'category_asc':
                        return compareText((a.subcategory||a.category||''), (b.subcategory||b.category||''));
                    case 'qty_desc':
                        return compareNum((b.quantity||0), (a.quantity||0));
                    case 'qty_asc':
                        return compareNum((a.quantity||0), (b.quantity||0));
                    case 'status': {
                        // Available first, then Damaged / Repairing, then Unavailable
                        const va = this.getResourceStatusForRow(a) === 'Available' ? 0 : (this.getResourceStatusForRow(a) === 'Damaged / Repairing' ? 1 : 2);
                        const vb = this.getResourceStatusForRow(b) === 'Available' ? 0 : (this.getResourceStatusForRow(b) === 'Damaged / Repairing' ? 1 : 2);
                        if (va !== vb) return va - vb;
                        return compareText((a.name||''), (b.name||''));
                    }
                    case 'updated_desc': {
                        // Normalize space-separated datetime to ISO format for reliable parsing
                        const toTs = (d) => {
                            if (!d) return 0;
                            const iso = String(d).replace(' ', 'T');
                            const t = Date.parse(iso);
                            return isNaN(t) ? 0 : t;
                        };
                        const da = toTs(a.lastUpdated);
                        const db = toTs(b.lastUpdated);
                        return db - da;
                    }
                    default:
                        return 0;
                }
            });
        }

        // Pagination
        const total = filtered.length;
        const totalPages = Math.max(1, Math.ceil(total / this.allResPageSize));
        if (this.allResPage > totalPages) this.allResPage = totalPages;
        const start = (this.allResPage - 1) * this.allResPageSize;
        const end = start + this.allResPageSize;
        const pageItems = filtered.slice(start, end);

        // Render rows
        if (pageItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No resources found</td></tr>';
        } else {
            tbody.innerHTML = pageItems.map(r => `
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            ${r.items && r.items.length > 0 ? `
                                <button type="button" class="btn btn-link btn-sm p-0 toggle-items-btn" onclick="resourcesPage.toggleItems(this, 'all-${r.id}')" style="color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border: none; background: none;">
                                    <span class="material-icons" style="font-size: 20px; transition: transform 0.2s; color: var(--primary-color);">keyboard_arrow_right</span>
                                </button>
                            ` : ''}
                            <div>
                                <div style="font-weight: 600; color: var(--text-dark); text-align: left;">${r.name}</div>
                                ${r.plateNumber ? `
                                    <div class="text-muted" style="font-size: 0.72rem; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0; font-family: monospace;">
                                        <span class="material-icons" style="font-size: 12px;">badge</span>
                                        ${r.plateNumber}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </td>
                    <td>${this.cleanMunicipalityName(r.drrmoName)}</td>
                    <td>${r.subcategory || r.category || 'N/A'}</td>
                    <td>${r.quantity}</td>
                    <td>${(r.damagedStock > 0) ? `<span class="badge bg-danger">${r.damagedStock}</span>` : '<span class="text-muted small">-</span>'}</td>
                    <td>
                        <span class="badge ${this.getResourceStatusForRow(r) === 'Available' ? 'bg-success' : this.getResourceStatusForRow(r) === 'Damaged / Repairing' ? 'bg-warning text-dark' : 'bg-danger'}">${this.getResourceStatusForRow(r)}</span>
                        ${((this.getResourceStatusForRow(r) === 'Unavailable' || this.getResourceStatusForRow(r) === 'Out of Stock') && r.nextAvailableDate) ? `
                            <div class="text-muted mt-1" style="font-size: 0.72rem; line-height: 1.1; font-weight: 500;">
                                Available: ${this.formatDate(r.nextAvailableDate)}
                            </div>
                        ` : ''}
                    </td>
                    <td>${this.formatDate(r.lastUpdated)}</td>
                </tr>
                ${r.items && r.items.length > 0 ? `
                    <tr id="items-row-all-${r.id}" class="resource-items-row" style="display: none; background-color: #fafbfc;">
                        <td colspan="7" style="padding: 12px 24px;">
                            <div style="border-left: 3px solid #cbd5e1; padding-left: 16px; margin: 4px 0;">
                                <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 8px; text-align: left;">
                                    Itemized Units (${r.items.length})
                                </div>
                                <div class="table-responsive" style="border: 1px solid #e2e8f0; border-radius: 6px; background: #ffffff; overflow: hidden;">
                                    <table class="table table-sm table-borderless m-0" style="font-size: 0.85rem; width: 100%;">
                                        <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                            <tr>
                                                <th style="padding: 6px 12px; color: #475569; font-weight: 600; text-align: left;">Plate No. / Serial No. / ID</th>
                                                <th style="padding: 6px 12px; color: #475569; font-weight: 600; text-align: left;">Status</th>
                                                <th style="padding: 6px 12px; color: #475569; font-weight: 600; text-align: left;">Storage Location</th>
                                                <th style="padding: 6px 12px; color: #475569; font-weight: 600; text-align: left;">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${r.items.map(item => `
                                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                                    <td style="padding: 8px 12px; font-family: monospace; font-weight: 500; text-align: left;">
                                                        <span class="material-icons" style="font-size: 14px; vertical-align: middle; margin-right: 4px; color: #64748b;">badge</span>
                                                        ${item.uniqueIdentifier}
                                                    </td>
                                                    <td style="padding: 8px 12px; text-align: left;">
                                                        <span class="badge ${item.status === 'Available' ? 'bg-success' : item.status === 'Damaged / Repairing' ? 'bg-warning text-dark' : 'bg-secondary'}" style="font-size: 0.72rem; padding: 2px 6px;">
                                                            ${item.status}
                                                        </span>
                                                    </td>
                                                    <td style="padding: 8px 12px; color: #334155; text-align: left;">
                                                        ${item.storageLocation || '<span class="text-muted italic">Not specified</span>'}
                                                    </td>
                                                    <td style="padding: 8px 12px; color: #64748b; font-style: italic; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: left;" title="${item.conditionNotes || ''}">
                                                        ${item.conditionNotes || '-'}
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                ` : ''}
            `).join('');
        }

        // Render pagination (5-window)
        pagination.innerHTML = '';
        const create = (label, page, disabled = false, active = false) => {
            const li = document.createElement('li');
            li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'page-link';
            btn.textContent = label;
            if (!disabled && !active) btn.addEventListener('click', () => { this.allResPage = page; this.renderAllResources(); });
            li.appendChild(btn);
            return li;
        };
        const totalP = totalPages;
        const curr = this.allResPage;
        const maxVisible = 5;
        const half = Math.floor(maxVisible / 2);
        let startP = Math.max(1, curr - half);
        let endP = Math.min(totalP, startP + maxVisible - 1);
        startP = Math.max(1, endP - maxVisible + 1);
        pagination.appendChild(create('First', 1, curr === 1));
        pagination.appendChild(create('«', Math.max(1, curr - 1), curr === 1));
        for (let p = startP; p <= endP; p++) pagination.appendChild(create(String(p), p, false, p === curr));
        pagination.appendChild(create('»', Math.min(totalP, curr + 1), curr === totalP));
        pagination.appendChild(create('Last', totalP, curr === totalP));
    }
}

// Global functions for onclick handlers
function manageMyResources() {
    // Simple direct approach - use the user's municipality ID
    const userMunicipalityId = window.userMunicipalityId;
    
    if (!userMunicipalityId) {
        alert('Unable to determine your municipality. Please refresh the page.');
        return;
    }
    
    // Get municipality name from the static data
    const municipalityData = window.resourcesData?.find(m => m.id == userMunicipalityId);
    const municipalityName = municipalityData?.name || 'Your Municipality';
    
    // Directly call the view function
    if (window.resourcesPage) {
        window.resourcesPage.viewMunicipalityResources(userMunicipalityId, municipalityName);
    } else {
        alert('Page not ready. Please wait a moment and try again.');
    }
}

function backToOverview() {
    document.getElementById('resourcesDetail').style.display = 'none';
    document.getElementById('resourcesOverview').style.display = 'block';
    resourcesPage.currentMunicipalityId = null;
    resourcesPage.currentMunicipality = null;
}

function closeResourceModal() {
    resourcesPage.closeResourceModal();
}

function closeDeleteModal() {
    resourcesPage.closeDeleteModal();
}

function saveResource() {
    resourcesPage.saveResource();
}

function confirmDelete() {
    resourcesPage.confirmDelete();
}

function handleCategoryChange() {
    const categorySelect = document.getElementById('resourceCategory');
    if (categorySelect && categorySelect.value === 'add_new_category') {
        openAddCategoryModal();
        categorySelect.value = ''; // Reset selection
    }
}

function handleSubcategoryChange() {
    const subcategorySelect = document.getElementById('resourceSubcategory');
    if (subcategorySelect && subcategorySelect.value === 'add_new_subcategory') {
        openAddSubcategoryModal();
        subcategorySelect.value = ''; // Reset selection
    }
}

function openAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.add('active');
    document.getElementById('addCategoryModal').style.display = 'flex';
    document.getElementById('newCategoryName').value = '';
    document.getElementById('newCategoryName').focus();
}

function closeAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.remove('active');
    document.getElementById('addCategoryModal').style.display = 'none';
}

function openAddSubcategoryModal() {
    document.getElementById('addSubcategoryModal').classList.add('active');
    document.getElementById('addSubcategoryModal').style.display = 'flex';
    document.getElementById('newSubcategoryName').value = '';
    document.getElementById('newSubcategoryName').focus();
}

function closeAddSubcategoryModal() {
    document.getElementById('addSubcategoryModal').classList.remove('active');
    document.getElementById('addSubcategoryModal').style.display = 'none';
}

function saveNewCategory() {
    const categoryName = document.getElementById('newCategoryName').value.trim();
    
    if (!categoryName) {
        alert('Please enter a category name');
        return;
    }
    
    // Add the new category to the dropdown
    const categorySelect = document.getElementById('resourceCategory');
    const newOption = document.createElement('option');
    newOption.value = categoryName;
    newOption.textContent = categoryName;
    
    // Insert before the "Add New Category" option
    const addNewOption = categorySelect.querySelector('option[value="add_new_category"]');
    categorySelect.insertBefore(newOption, addNewOption);
    
    // Select the new category
    categorySelect.value = categoryName;
    
    // Close modal
    closeAddCategoryModal();
    
    alert('Category added successfully!');
}

function saveNewSubcategory() {
    const subcategoryName = document.getElementById('newSubcategoryName').value.trim();
    
    if (!subcategoryName) {
        alert('Please enter a subcategory name');
        return;
    }
    
    // Add the new subcategory to the dropdown
    const subcategorySelect = document.getElementById('resourceSubcategory');
    const newOption = document.createElement('option');
    newOption.value = subcategoryName;
    newOption.textContent = subcategoryName;
    
    // Insert before the "Add New Subcategory" option
    const addNewOption = subcategorySelect.querySelector('option[value="add_new_subcategory"]');
    subcategorySelect.insertBefore(newOption, addNewOption);
    
    // Select the new subcategory
    subcategorySelect.value = subcategoryName;
    
    // Close modal
    closeAddSubcategoryModal();
    
    alert('Subcategory added successfully!');
}

// Initialize page immediately
document.addEventListener('DOMContentLoaded', () => {
    if (!window.resourcesPage) {
    window.resourcesPage = new ResourcesPage();
}
});