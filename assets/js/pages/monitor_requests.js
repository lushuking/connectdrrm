// Monitor Requests JavaScript functionality
let allRequests = [];
let filteredRequests = [];
let realTimeUpdateInterval;
let currentPage = 1;
let pageSize = 10;

// Load requests on page load
document.addEventListener('DOMContentLoaded', function() {
    // Defer initial load until after page renders (non-blocking)
    requestAnimationFrame(() => {
        setTimeout(() => {
            loadRequests(); // Load data after initial render
        }, 100);
    });
    
    initializeEventListeners();
    initializeModalEvents();
    
    // Start real-time updates right after the first load kicks off
    startRealTimeUpdates();
});

// Initialize modal event listeners
function initializeModalEvents() {
    const modalElement = document.getElementById('requestDetailsModal');
    
    // Handle backdrop click
    modalElement.addEventListener('click', function(e) {
        if (e.target === modalElement) {
            closeRequestDetailsModal();
        }
    });
    
    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && (modalElement.style.display === 'block' || modalElement.style.display === 'flex')) {
            closeRequestDetailsModal();
        }
    });
}

// Start real-time updates
function startRealTimeUpdates() {
    // Don't start if already running
    if (realTimeUpdateInterval) {
        return;
    }
    
    // Pause updates when tab is hidden to save resources
    realTimeUpdateInterval = setInterval(() => {
        if (!document.hidden) {
            loadRequests(); // Load data directly for real-time updates too
        }
    }, 10000); // Update every 10 seconds
    
    // Resume when tab becomes visible
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !realTimeUpdateInterval) {
            startRealTimeUpdates();
        }
    });
}

// Stop real-time updates when page is unloaded
window.addEventListener('beforeunload', function() {
    if (realTimeUpdateInterval) {
        clearInterval(realTimeUpdateInterval);
    }
});

function initializeEventListeners() {
    // Add event listeners for filters
    document.getElementById('statusFilter').addEventListener('change', filterRequests);
    document.getElementById('municipalityFilter').addEventListener('change', filterRequests);
    document.getElementById('priorityFilter').addEventListener('change', filterRequests);
    document.getElementById('searchInput').addEventListener('input', searchRequests);
    
    // Add event delegation for view details buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.view-details-btn')) {
            e.preventDefault();
            e.stopPropagation();
            const button = e.target.closest('.view-details-btn');
            const requestId = button.getAttribute('data-request-id');
            viewRequestDetails(requestId);
        }
    });
}

async function loadRequests() {
    try {
        const response = await fetch('config/monitor_requests_api.php?action=list');
        const data = await response.json();
        
        if (data.success) {
            allRequests = data.requests;
            // Preserving filter state and page number during background refresh
            applyFilters(true);
        } else {
            console.error('Failed to load requests:', data.error);
            allRequests = [];
            filteredRequests = [];
            currentPage = 1;
            populateRequestsTable(filteredRequests);
        }
    } catch (error) {
        console.error('Error loading requests:', error);
        allRequests = [];
        filteredRequests = [];
        currentPage = 1;
        populateRequestsTable(filteredRequests);
    }
    
    loadMunicipalities();
}

function populateRequestsTable(requests) {
    const tbody = document.getElementById('requestsTableBody');
    tbody.innerHTML = '';
    
    if (requests.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <div class="text-muted">
                        <span class="material-icons mb-2 d-block" style="font-size: 48px; opacity: 0.5;">inbox</span>
                        No requests found matching your criteria.
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // Group requests by requestGroupId, but only if there are multiple requests in the group
    const groupedRequests = {};
    const ungroupedRequests = [];
    
    requests.forEach(request => {
        if (request.requestGroupId) {
            if (!groupedRequests[request.requestGroupId]) {
                groupedRequests[request.requestGroupId] = [];
            }
            groupedRequests[request.requestGroupId].push(request);
        } else {
            ungroupedRequests.push(request);
        }
    });
    
    // Move groups with only one request to ungrouped requests
    Object.keys(groupedRequests).forEach(groupId => {
        if (groupedRequests[groupId].length <= 1) {
            ungroupedRequests.push(...groupedRequests[groupId]);
            delete groupedRequests[groupId];
        }
    });
    
    // Use globally stored expanded groups for Monitor Requests
    if (!window.expandedMonitorRequestGroups) {
        window.expandedMonitorRequestGroups = new Set();
    }
    const expandedGroups = window.expandedMonitorRequestGroups;
    
    // Combine grouped and ungrouped requests for pagination
    const allItems = [];
    
    // Add grouped requests (header + items)
    Object.keys(groupedRequests).forEach(groupId => {
        allItems.push({ type: 'group-header', groupId: groupId, group: groupedRequests[groupId] });
        groupedRequests[groupId].forEach(request => {
            allItems.push({ type: 'group-item', groupId: groupId, request: request });
        });
    });
    
    // Add ungrouped requests
    ungroupedRequests.forEach(request => {
        allItems.push({ type: 'ungrouped', request: request });
    });
    
    const total = allItems.length;
    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = Math.min(startIndex + pageSize, total);
    const pageItems = allItems.slice(startIndex, endIndex);

    const statusClass = (s) => {
        const v = String(s || '').toLowerCase();
        if (v === 'pending') return 'status-badge status-pending';
        if (v === 'approved') return 'status-badge status-approved';
        if (v === 'rejected') return 'status-badge status-rejected';
        if (v === 'fulfilled' || v === 'received' || v === 'returned') return 'status-badge status-fulfilled';
        return 'status-badge status-pending';
    };
    const priorityClass = (p) => {
        const v = String(p || '').toLowerCase();
        if (v === 'high') return 'priority-badge priority-high';
        if (v === 'medium') return 'priority-badge priority-medium';
        return 'priority-badge priority-low';
    };

    let htmlContent = '';
    
    pageItems.forEach(item => {
        if (item.type === 'group-header') {
            const groupId = item.groupId;
            const group = item.group;
            const groupCount = group.length;
            const wasExpanded = expandedGroups.has(groupId);
            
            htmlContent += `
                <tr class="table-group-header">
                    <td colspan="8" class="py-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="material-icons me-2" style="vertical-align: middle; font-size: 18px;">group</span>
                                <strong>Request Group: ${groupId}</strong>
                                <span class="badge bg-info ms-2">${groupCount} resource${groupCount > 1 ? 's' : ''}</span>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="toggleMonitorRequestGroup('${groupId}')" type="button">
                                <span class="material-icons" id="monitor-req-icon-${groupId}" style="font-size: 18px;">${wasExpanded ? 'expand_less' : 'expand_more'}</span>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        } else if (item.type === 'group-item') {
            const request = item.request;
            const groupId = item.groupId;
            const wasExpanded = expandedGroups.has(groupId);
            const fromTo = (request.fromMunicipality || request.municipality || '') + ' → ' + (request.toMunicipality || '');
            
            htmlContent += `
                <tr data-request-id="${request.id}" data-group-id="${groupId}" class="monitor-group-${groupId}" style="display: ${wasExpanded ? '' : 'none'};">
                    <td><strong class="text-primary">${request.id}</strong><br><small class="text-muted">Part of ${groupId}</small></td>
                    <td>${fromTo}</td>
                    <td>${request.resourceType}</td>
                    <td><span class="badge bg-primary">${request.quantity}</span></td>
                    <td><span class="${priorityClass(request.priority)}">${String(request.priority || '').toUpperCase()}</span></td>
                    <td><span class="${statusClass(request.status)}">${String(request.status || '').toLowerCase() === 'fulfilled' ? 'RECEIVED' : String(request.status || '').toUpperCase()}</span></td>
                    <td><small class="text-muted">${formatDate(request.requestDate)}</small></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary view-details-btn" data-request-id="${request.id}" title="View Details" style="pointer-events: auto; z-index: 1;">
                            <span class="material-icons" style="font-size: 18px;">visibility</span>
                        </button>
                    </td>
                </tr>
            `;
        } else {
            // Ungrouped request
            const request = item.request;
            const fromTo = (request.fromMunicipality || request.municipality || '') + ' → ' + (request.toMunicipality || '');
            
            htmlContent += `
                <tr data-request-id="${request.id}">
                    <td><strong class="text-primary">${request.id}</strong></td>
                    <td>${fromTo}</td>
                    <td>${request.resourceType}</td>
                    <td><span class="badge bg-primary">${request.quantity}</span></td>
                    <td><span class="${priorityClass(request.priority)}">${String(request.priority || '').toUpperCase()}</span></td>
                    <td><span class="${statusClass(request.status)}">${String(request.status || '').toLowerCase() === 'fulfilled' ? 'RECEIVED' : String(request.status || '').toUpperCase()}</span></td>
                    <td><small class="text-muted">${formatDate(request.requestDate)}</small></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary view-details-btn" data-request-id="${request.id}" title="View Details" style="pointer-events: auto; z-index: 1;">
                            <span class="material-icons" style="font-size: 18px;">visibility</span>
                        </button>
                    </td>
                </tr>
            `;
        }
    });
    
    tbody.innerHTML = htmlContent;
    
    // Count actual requests (not group headers) for pagination display
    const actualRequestCount = requests.length;
    // Calculate showing range based on actual requests, not items (which include headers)
    const showingStart = Math.min(startIndex + 1, actualRequestCount);
    const showingEnd = Math.min(endIndex, actualRequestCount);
    updatePaginationUI(actualRequestCount, showingStart, showingEnd);
}

function loadMunicipalities() {
    const municipalityFilter = document.getElementById('municipalityFilter');
    if (!municipalityFilter) return;
    
    // Preserve current selection
    const currentValue = municipalityFilter.value;
    
    const municipalities = [...new Set(allRequests.map(r => r.municipality || r.fromMunicipality || r.toMunicipality).filter(Boolean))];
    
    municipalityFilter.innerHTML = '<option value="">All Municipalities</option>';
    municipalities.forEach(municipality => {
        const option = document.createElement('option');
        option.value = municipality;
        option.textContent = municipality;
        municipalityFilter.appendChild(option);
    });
    
    // Restore selection
    if (currentValue && municipalities.includes(currentValue)) {
        municipalityFilter.value = currentValue;
    }
}

function applyFilters(preservePage = false) {
    const statusFilterEl = document.getElementById('statusFilter');
    const municipalityFilterEl = document.getElementById('municipalityFilter');
    const priorityFilterEl = document.getElementById('priorityFilter');
    const searchInputEl = document.getElementById('searchInput');

    const statusFilter = statusFilterEl ? statusFilterEl.value : '';
    const municipalityFilter = municipalityFilterEl ? municipalityFilterEl.value : '';
    const priorityFilter = priorityFilterEl ? priorityFilterEl.value : '';
    const searchTerm = searchInputEl ? searchInputEl.value.toLowerCase() : '';
    
    filteredRequests = allRequests.filter(request => {
        const matchesStatus = !statusFilter || String(request.status).toLowerCase() === statusFilter.toLowerCase();
        
        // Match either fromMunicipality, toMunicipality, or the general municipality property
        const matchesMunicipality = !municipalityFilter || 
            String(request.municipality || '').toLowerCase() === municipalityFilter.toLowerCase() ||
            String(request.fromMunicipality || '').toLowerCase() === municipalityFilter.toLowerCase() ||
            String(request.toMunicipality || '').toLowerCase() === municipalityFilter.toLowerCase();
            
        const matchesPriority = !priorityFilter || String(request.priority).toLowerCase() === priorityFilter.toLowerCase();
        
        const matchesSearch = !searchTerm || 
            String(request.id).toLowerCase().includes(searchTerm) ||
            String(request.municipality || '').toLowerCase().includes(searchTerm) ||
            String(request.fromMunicipality || '').toLowerCase().includes(searchTerm) ||
            String(request.toMunicipality || '').toLowerCase().includes(searchTerm) ||
            String(request.resourceType || '').toLowerCase().includes(searchTerm) ||
            String(request.description || '').toLowerCase().includes(searchTerm);
        
        return matchesStatus && matchesMunicipality && matchesPriority && matchesSearch;
    });
    
    if (!preservePage) {
        currentPage = 1;
    } else {
        // Clamp currentPage to max pages for the new filtered set
        const totalPages = Math.max(1, Math.ceil(filteredRequests.length / pageSize));
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
    }
    
    populateRequestsTable(filteredRequests);
}

function filterRequests() {
    applyFilters(false);
}

function searchRequests() {
    applyFilters(false);
}

function refreshRequests() {
    loadRequests();
}

function updatePaginationUI(total, showingStart, showingEnd) {
    const totalRecordsEl = document.getElementById('totalRecords');
    const showingStartEl = document.getElementById('showingStart');
    const showingEndEl = document.getElementById('showingEnd');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const pageNumbers = document.getElementById('pageNumbers');

    if (totalRecordsEl) totalRecordsEl.textContent = String(total);
    if (showingStartEl) showingStartEl.textContent = total === 0 ? '0' : String(showingStart);
    if (showingEndEl) showingEndEl.textContent = total === 0 ? '0' : String(showingEnd);

    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    if (prevBtn) prevBtn.disabled = currentPage <= 1;
    if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

    if (pageNumbers) {
        pageNumbers.innerHTML = '';

        const addPageButton = (pageIndex) => {
            const a = document.createElement('a');
            a.href = '#';
            a.className = 'page-number' + (pageIndex === currentPage ? ' active' : '');
            a.textContent = String(pageIndex);
            a.addEventListener('click', (e) => {
                e.preventDefault();
                goToPage(pageIndex);
            });
            pageNumbers.appendChild(a);
        };

        const addEllipsis = () => {
            const span = document.createElement('span');
            span.className = 'page-number-ellipsis';
            span.textContent = '...';
            pageNumbers.appendChild(span);
        };

        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }

        // Always show page 1
        if (startPage > 1) {
            addPageButton(1);
            if (startPage > 2) {
                addEllipsis();
            }
        }

        // Show page range
        for (let i = startPage; i <= endPage; i++) {
            addPageButton(i);
        }

        // Always show last page
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                addEllipsis();
            }
            addPageButton(totalPages);
        }
    }
}

function previousPage() {
    if (currentPage > 1) {
        currentPage--;
        populateRequestsTable(filteredRequests);
    }
}

function nextPage() {
    const totalPages = Math.max(1, Math.ceil(filteredRequests.length / pageSize));
    if (currentPage < totalPages) {
        currentPage++;
        populateRequestsTable(filteredRequests);
    }
}

function goToPage(page) {
    const totalPages = Math.max(1, Math.ceil(filteredRequests.length / pageSize));
    currentPage = Math.min(Math.max(1, page), totalPages);
    populateRequestsTable(filteredRequests);
}

function exportRequests() {
    const csvContent = [
        ['Request ID', 'Municipality', 'Resource Type', 'Quantity', 'Priority', 'Status', 'Request Date', 'Description'],
        ...filteredRequests.map(request => [
            request.id,
            request.municipality,
            request.resourceType,
            request.quantity,
            request.priority,
            request.status,
            request.requestDate,
            request.description
        ])
    ].map(row => row.map(field => `"${field}"`).join(',')).join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `requests_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () {
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }, 150);
}

function viewRequestDetails(requestId) {
    const request = allRequests.find(r => r.id === requestId);
    if (!request) return;
    
    const modalContent = document.getElementById('requestDetailsContent');
    modalContent.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="card-title text-primary mb-3">
                            <span class="material-icons me-2">assignment</span>
                            Request Information
                        </h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Request ID</label>
                                <p class="mb-0 fw-semibold">${request.id}</p>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">From Municipality</label>
                                <p class="mb-0">${request.fromMunicipality}</p>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">To Municipality</label>
                                <p class="mb-0">${request.toMunicipality}</p>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Resource Type</label>
                                <p class="mb-0">${request.resourceType} (${request.category || ''})</p>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Quantity</label>
                                <p class="mb-0 fw-semibold text-primary">${request.quantity} ${request.unit || 'units'}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="card-title text-primary mb-3">
                            <span class="material-icons me-2">info</span>
                            Status & Priority
                        </h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Priority</label>
                                <div class="mb-2">
                                    <span class="badge bg-${request.priority === 'high' ? 'danger' : request.priority === 'medium' ? 'warning' : 'success'} fs-6">
                                        ${request.priority.toUpperCase()}
                                    </span>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Status</label>
                                <div class="mb-2">
                                    <span class="badge bg-${request.status === 'pending' ? 'warning' : request.status === 'approved' ? 'success' : request.status === 'rejected' ? 'danger' : 'info'} fs-6">
                                        ${request.status === 'pending_head_approval' ? 'PENDING' : request.status.toUpperCase()}
                                    </span>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Request Date</label>
                                <p class="mb-0">
                                    <span class="material-icons me-1" style="font-size: 16px;">calendar_today</span>
                                    ${formatDate(request.requestDate)}
                                </p>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Approve Date</label>
                                <p class="mb-0">
                                    <span class="material-icons me-1" style="font-size: 16px;">check_circle</span>
                                    ${request.responseDate ? formatDate(request.responseDate) : 'Not approved yet'}
                                </p>
                            </div>
                            ${request.deliveryDate ? `
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Requested Delivery Date</label>
                                <p class="mb-0">${formatDate(request.deliveryDate)}</p>
                            </div>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="card-title text-primary mb-3">
                            <span class="material-icons me-2">map</span>
                            Delivery & Transport
                        </h6>
                        <div class="row g-3">
                            ${request.deliveryLocation ? `
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Delivery Location</label>
                                <p class="mb-0">${request.deliveryLocation}</p>
                            </div>` : ''}
                            ${request.transportationMethod ? `
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Transportation Method</label>
                                <p class="mb-0">${request.transportationMethod}</p>
                            </div>` : ''}
                            ${request.expectedDuration ? `
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Expected Duration</label>
                                <p class="mb-0">${request.expectedDuration}</p>
                            </div>` : ''}
                            ${request.returnDate ? `
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Expected Return Date</label>
                                <p class="mb-0">${formatDate(request.returnDate)}</p>
                            </div>` : ''}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="card-title text-primary mb-3">
                            <span class="material-icons me-2">contact_phone</span>
                            Contact & Purpose
                        </h6>
                        <div class="row g-3">
                            ${request.requestorName ? `
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Requestor</label>
                                <p class="mb-0">${request.requestorName}</p>
                            </div>` : ''}
                            ${request.contactPhone ? `
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Phone</label>
                                <p class="mb-0">${request.contactPhone}</p>
                            </div>` : ''}
                            ${request.contactEmail ? `
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Email</label>
                                <p class="mb-0">${request.contactEmail}</p>
                            </div>` : ''}
                            ${request.purposeOfRequest ? `
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Purpose</label>
                                <p class="mb-0">${request.purposeOfRequest}</p>
                            </div>` : ''}
                            ${request.specialHandling ? `
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Special Handling</label>
                                <p class="mb-0">${request.specialHandling}</p>
                            </div>` : ''}
                            ${request.notes ? `
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted">Notes</label>
                                <p class="mb-0">${request.notes}</p>
                            </div>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 bg-light" style="border-radius: 12px; border-left: 4px solid ${request.headApprovalStatus === 'approved' ? '#198754' : (request.headApprovalStatus === 'bypassed' ? '#ffc107' : '#0d6efd')} !important;">
                    <div class="card-body">
                        <h6 class="card-title text-primary mb-3 d-flex align-items-center gap-2">
                            <span class="material-icons">verified_user</span>
                            Authorization & Audit Details
                        </h6>
                        <div class="row g-3 align-items-center">
                            <div class="col-md-7">
                                <div class="row g-3">
                                    ${request.headApprovalStatus ? `
                                    <div class="col-12">
                                        <label class="form-label fw-bold text-muted d-block mb-1">Approval Method</label>
                                        <span class="badge ${request.headApprovalStatus === 'approved' ? 'bg-success' : 'bg-warning text-dark'} d-inline-flex align-items-center gap-1">
                                            <span class="material-icons" style="font-size: 14px;">
                                                ${request.headApprovalStatus === 'approved' ? 'verified' : 'fast_forward'}
                                            </span>
                                            ${request.headApprovalStatus === 'approved' ? 'Approved by Head' : 'Bypassed'}
                                        </span>
                                    </div>` : ''}
                                    <div class="col-12">
                                        <label class="form-label fw-bold text-muted d-block mb-1">Authorized By</label>
                                        <p class="mb-0 fw-bold text-dark fs-6">${request.headApprovedBy || request.approvingAuthority || 'N/A'}</p>
                                        ${request.approverTitle ? `<small class="text-muted d-block">${request.approverTitle}</small>` : ''}
                                    </div>
                                    ${request.budgetCode ? `
                                    <div class="col-12">
                                        <label class="form-label fw-bold text-muted d-block mb-1">Budget Code</label>
                                        <p class="mb-0 fw-semibold text-secondary">${request.budgetCode}</p>
                                    </div>` : ''}
                                </div>
                            </div>
                            <div class="col-md-5 text-md-end text-center">
                                ${request.approverSignature ? `
                                <div class="d-inline-block p-2 bg-white border rounded shadow-xs">
                                    <div class="text-muted small text-center" style="font-size: 8px; margin-bottom: 3px; font-weight: 700; text-transform: uppercase;">E-Signature</div>
                                    <img src="${request.approverSignature}" alt="Digital Signature" style="max-height: 55px; max-width: 140px; object-fit: contain; display: block;">
                                </div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const modalElement = document.getElementById('requestDetailsModal');
    
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
        modal.show();
    } else {
        modalElement.style.display = 'flex';
        modalElement.style.alignItems = 'center';
        modalElement.style.justifyContent = 'center';
        modalElement.style.position = 'fixed';
        modalElement.style.top = '0';
        modalElement.style.left = '0';
        modalElement.style.width = '100%';
        modalElement.style.height = '100%';
        modalElement.classList.add('show');
        document.body.classList.add('modal-open');
        
        const modalDialog = modalElement.querySelector('.modal-dialog');
        if (modalDialog) {
            modalDialog.style.maxWidth = '98%';
            modalDialog.style.width = '1400px';
        }
        
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modalBackdrop';
        document.body.appendChild(backdrop);
    }
}

function closeRequestDetailsModal() {
    const modalElement = document.getElementById('requestDetailsModal');
    
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }
    } else {
        modalElement.style.display = 'none';
        modalElement.classList.remove('show');
        document.body.classList.remove('modal-open');
        
        const backdrop = document.getElementById('modalBackdrop');
        if (backdrop) {
            backdrop.remove();
        }
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Toggle monitor request group visibility
function toggleMonitorRequestGroup(groupId) {
    const rows = document.querySelectorAll(`tr.monitor-group-${groupId}`);
    const icon = document.getElementById(`monitor-req-icon-${groupId}`);
    
    if (!rows.length) return;
    
    // Check if group is expanded (first row is visible)
    const isExpanded = rows[0].style.display !== 'none';
    
    rows.forEach(row => {
        // Skip header row
        if (!row.classList.contains('table-group-header')) {
            row.style.display = isExpanded ? 'none' : '';
        }
    });
    
    // Update icon
    if (icon) {
        icon.textContent = isExpanded ? 'expand_more' : 'expand_less';
    }
    
    // Update global expanded groups set for Monitor Requests
    if (!window.expandedMonitorRequestGroups) {
        window.expandedMonitorRequestGroups = new Set();
    }
    
    if (isExpanded) {
        window.expandedMonitorRequestGroups.delete(groupId);
    } else {
        window.expandedMonitorRequestGroups.add(groupId);
    }
}
