<?php
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/db.php';

// Get user's DRRMO ID
$drrmoID = $_SESSION['municipality_id'] ?? null;
?>

<style>
/* Approvals Page — aligned with system design */
.approvals-page {
    padding: 0;
    min-height: 100vh;
}

.approvals-container .card {
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: none;
    background: #ffffff;
}

.approvals-container .card-header {
    background: linear-gradient(135deg, #1A3D63 0%, #2d6a9f 100%);
    color: white;
    padding: 1.25rem 1.5rem;
    border-bottom: none;
}

.approvals-container .card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.15rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.approvals-container .card-header .badge {
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(10px);
    padding: 0.4rem 0.8rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.approvals-container .card-body {
    padding: 0;
}

#approvalsTable {
    font-size: 0.9375rem;
    margin: 0;
}

#approvalsTable thead {
    background: linear-gradient(to bottom, #f8f9fa 0%, #e9ecef 100%);
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    position: sticky;
    top: 0;
    z-index: 100;
}

#approvalsTable thead th {
    font-weight: 700;
    color: #2d3748;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-size: 0.75rem;
    padding: 1.25rem 1rem;
    background-color: transparent;
    border-bottom: 3px solid #667eea;
    white-space: nowrap;
}

.table-group-header {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%) !important;
    border-left: 5px solid #667eea !important;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
    position: relative;
}

.table-group-header::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
}

.table-group-header td {
    padding: 1.25rem 1.5rem !important;
}

.table-group-header h6 {
    font-size: 1rem;
    margin-bottom: 0.35rem;
    font-weight: 700;
    color: #667eea;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.table-group-header h6 i {
    font-size: 1.1rem;
}

.table-group-header .badge {
    font-size: 0.8125rem;
    padding: 0.5em 0.85em;
    font-weight: 600;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#approvalsTable tbody tr {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-bottom: 1px solid #e9ecef;
}

#approvalsTable tbody tr:hover {
    background: linear-gradient(90deg, rgba(102, 126, 234, 0.08) 0%, rgba(102, 126, 234, 0.02) 100%);
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

#approvalsTable tbody tr:last-child {
    border-bottom: none;
}

#approvalsTable td {
    vertical-align: middle;
    padding: 1.25rem 1rem;
}

.btn-group {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
}

.btn-group .btn {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.2s ease;
    border: none;
}

.btn-group .btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.btn-group .btn.btn-outline-success {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: white;
}

.btn-group .btn.btn-outline-success:hover:not(:disabled) {
    background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
}

.btn-group .btn.btn-outline-danger {
    background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
    color: white;
}

.btn-group .btn.btn-outline-danger:hover:not(:disabled) {
    background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
}

.btn-group .btn.btn-outline-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-group .btn.btn-outline-primary:hover {
    background: linear-gradient(135deg, #5568d3 0%, #663b9c 100%);
}

.badge {
    font-weight: 600;
    padding: 0.5em 0.85em;
    border-radius: 6px;
    font-size: 0.8125rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: none;
}

.badge.bg-success-subtle,
.badge.bg-success {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%) !important;
    color: white !important;
}

.badge.bg-danger-subtle,
.badge.bg-danger {
    background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%) !important;
    color: white !important;
}

.badge.bg-warning-subtle,
.badge.bg-warning {
    background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%) !important;
    color: white !important;
}

.badge.bg-info-subtle,
.badge.bg-info {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%) !important;
    color: white !important;
}

.text-primary {
    color: #667eea !important;
}

.fw-semibold {
    font-weight: 600;
}

.border-start.border-4 {
    border-left-width: 5px !important;
}

/* Request ID styling */
#approvalsTable td:first-child {
    font-weight: 700;
}

#approvalsTable td:first-child .fw-bold {
    color: #667eea;
    font-size: 0.9375rem;
}

/* Priority badges */
.badge.bg-danger-subtle {
    background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%) !important;
}

.badge.bg-warning-subtle {
    background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%) !important;
}

.badge.bg-info-subtle {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%) !important;
}

/* Evaluated row styling */
tbody tr.table-secondary {
    background: rgba(108, 117, 125, 0.06) !important;
    opacity: 0.85;
}

tbody tr.table-secondary:hover {
    background: rgba(108, 117, 125, 0.12) !important;
    transform: none;
}

/* Loading state */
.spinner-border {
    width: 3rem;
    height: 3rem;
    border-width: 0.3em;
}

/* Empty state */
.empty-state {
    padding: 4rem 2rem;
}

.empty-state i {
    font-size: 5rem;
    opacity: 0.3;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    #approvalsTable {
        font-size: 0.875rem;
    }
}

@media (max-width: 768px) {
    .approvals-page {
        padding: 1rem;
        background: #f8f9fa;
    }
    
    .approvals-container .card-header {
        padding: 1.25rem 1.5rem;
    }
    
    .approvals-container .card-header h5 {
        font-size: 1.25rem;
    }
    
    #approvalsTable thead th {
        font-size: 0.6875rem;
        padding: 1rem 0.75rem;
    }
    
    #approvalsTable td {
        padding: 1rem 0.75rem;
    }
    
    .btn-group .btn {
        padding: 0.4rem 0.6rem;
        font-size: 0.8125rem;
    }
    
    .table-group-header td {
        padding: 1rem !important;
    }
}

/* Smooth scrollbar */
.table-responsive::-webkit-scrollbar {
    height: 8px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #5568d3 0%, #663b9c 100%);
}
</style>

<div class="approvals-page">
    <div class="approvals-container">
        <div class="card shadow-sm">
            <div class="card-header">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5>
                        <span class="material-icons" style="font-size:22px;">assignment_turned_in</span>
                        Pending Approvals
                        <span class="badge ms-2 bg-white bg-opacity-20 text-white rounded-pill" id="pendingCountBadge" style="font-size:0.8rem;">-</span>
                    </h5>
                    <small class="text-white text-opacity-75 d-flex align-items-center gap-1">
                        <span class="material-icons" style="font-size:15px;">info</span>
                        Review and approve resource requests from municipalities
                    </small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="approvalsTable">
                        <thead class="table-light">
                            <tr>
                                <th width="12%">Request ID</th>
                                <th width="20%">Resource</th>
                                <th width="15%">Provider</th>
                                <th width="8%" class="text-center">Qty</th>
                                <th width="10%" class="text-center">Priority</th>
                                <th width="15%">Request Date</th>
                                <th width="12%" class="text-center">Stock Status</th>
                                <th width="8%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="approvalsTableBody" class="border-top-0">
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="text-muted mt-3 mb-0">Loading pending approvals...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load pending approvals
async function loadPendingApprovals() {
    try {
        const apiUrl = 'config/get_pending_approvals.php';
        const response = await fetch(apiUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let result;
        
        try {
            result = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('Failed to parse JSON response:', jsonError);
            console.error('Response text:', responseText);
            showError('Invalid response from server. Please refresh the page.');
            return;
        }
        
        const tbody = document.getElementById('approvalsTableBody');
        
        if (!result.success) {
            showError(result.error?.message || 'Failed to load approvals');
            return;
        }
        
        const requests = result.data?.requests || [];
        
        // Update pending count badge
        const pendingCountBadge = document.getElementById('pendingCountBadge');
        if (pendingCountBadge) {
            pendingCountBadge.textContent = requests.length;
        }
        
        if (requests.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-check-circle text-success mb-3"></i>
                            <h5 class="text-success mb-2 fw-bold">All Caught Up!</h5>
                            <p class="text-muted mb-1 fs-5">No pending approvals</p>
                            <small class="text-muted">All requests have been reviewed</small>
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
        
        // Keep all groups visible, even with single items (per plan requirement)
        // Groups must remain intact throughout the approval process
        
        // Build HTML with grouped and ungrouped requests
        let html = '';
        
        // Render grouped requests
        Object.keys(groupedRequests).forEach(groupId => {
            const group = groupedRequests[groupId];
            const groupCount = group.length;
            
            // Calculate evaluation progress
            let evaluatedCount = 0;
            let approvedCount = 0;
            let rejectedCount = 0;
            group.forEach(request => {
                if (request.isEvaluated) {
                    evaluatedCount++;
                    if (request.evaluationStatus === 'approved') {
                        approvedCount++;
                    } else if (request.evaluationStatus === 'rejected') {
                        rejectedCount++;
                    }
                }
            });
            
            // Determine group status badge
            let groupStatusBadge = '';
            if (evaluatedCount === groupCount) {
                // All evaluated
                if (rejectedCount > 0) {
                    // At least one rejected - entire group will be rejected
                    groupStatusBadge = '<span class="badge bg-danger ms-2">All Evaluated - Will Reject</span>';
                } else {
                    // All approved - group will forward
                    groupStatusBadge = '<span class="badge bg-success ms-2">All Evaluated - Ready to Forward</span>';
                }
            } else {
                // Partial evaluation
                groupStatusBadge = `<span class="badge bg-warning ms-2">${evaluatedCount} of ${groupCount} evaluated</span>`;
            }
            
            // Group header row - enhanced styling
            html += `
                <tr class="table-group-header">
                    <td colspan="8">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div class="d-flex align-items-center">
                                <div>
                                    <h6 class="mb-0">
                                        <i class="fas fa-layer-group"></i>
                                        Request Group: ${groupId}
                                    </h6>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-boxes me-1"></i>
                                        ${groupCount} resource${groupCount > 1 ? 's' : ''} in this group
                                    </small>
                                </div>
                            </div>
                            <div class="mt-2 mt-md-0">
                                ${groupStatusBadge}
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            
            // Group request rows - always show (always expanded)
            group.forEach((request, index) => {
                html += renderRequestRow(request, groupId, true);
            });
        });
        
        // Render ungrouped requests
        ungroupedRequests.forEach(request => {
            html += renderRequestRow(request, null, true);
        });
        
        tbody.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading approvals:', error);
        showError('Error loading approvals. Please refresh the page.');
    }
}

function showError(message) {
    const tbody = document.getElementById('approvalsTableBody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 4rem; opacity: 0.7;"></i>
                        <div class="alert alert-danger d-inline-block mb-0 px-4 py-3" role="alert" style="border-radius: 10px; box-shadow: 0 4px 12px rgba(245, 101, 101, 0.2);">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Error:</strong> ${escapeHtml(message)}
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }
}

// Toggle functionality removed - groups are always visible

function renderRequestRow(request, groupId, isExpanded) {
    const groupClass = groupId ? `group-${groupId}` : '';
    // Always show rows (no collapsing)
    const displayStyle = '';
    
    const priorityClass = {
        'high': 'danger',
        'medium': 'warning',
        'low': 'info'
    }[request.priority?.toLowerCase()] || 'secondary';
    
    const stockStatus = request.availableStock >= request.quantity 
        ? `<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle"><i class="fas fa-check-circle me-1"></i>Available</span>` 
        : `<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle"><i class="fas fa-exclamation-circle me-1"></i>Low Stock</span>`;
    
    // Check if item is evaluated (read-only)
    const isEvaluated = request.isEvaluated === true;
    const evaluationStatus = request.evaluationStatus || 'pending';
    
    // Determine row styling for evaluated items
    const rowClass = isEvaluated ? 'table-secondary bg-opacity-25' : '';
    const rowStyle = isEvaluated ? 'background-color: rgba(108, 117, 125, 0.05);' : '';
    
    // Evaluation status badge/indicator
    let evaluationBadge = '';
    if (isEvaluated) {
        if (evaluationStatus === 'approved') {
            evaluationBadge = '<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle"><i class="fas fa-check-circle me-1"></i>Approved</span>';
        } else if (evaluationStatus === 'rejected') {
            evaluationBadge = '<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle"><i class="fas fa-times-circle me-1"></i>Rejected</span>';
        }
    } else {
        evaluationBadge = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="fas fa-clock me-1"></i>Pending</span>';
    }
    
    // Action buttons - disabled if evaluated
    const approveDisabled = isEvaluated || request.availableStock < request.quantity;
    const rejectDisabled = isEvaluated;
    const approveTitle = isEvaluated ? 'Already evaluated' : (request.availableStock < request.quantity ? 'Insufficient stock' : 'Approve');
    const rejectTitle = isEvaluated ? 'Already evaluated' : 'Reject';
    
    return `
        <tr data-request-id="${request.id}" data-group-id="${groupId || ''}" class="${groupClass} ${rowClass}" style="${rowStyle}" ${displayStyle}>
            <td>
                <div class="d-flex flex-column">
                    <span class="fw-bold">
                        <i class="fas fa-hashtag me-1"></i>REQ-${request.id}
                    </span>
                    ${groupId ? `<small class="text-muted mt-1"><i class="fas fa-layer-group me-1"></i>Group ${groupId}</small>` : ''}
                    <div class="mt-2">${evaluationBadge}</div>
                </div>
            </td>
            <td>
                <div class="d-flex flex-column">
                    <span class="fw-semibold text-dark">${escapeHtml(request.name)}</span>
                    ${request.description ? `<small class="text-muted mt-1"><i class="fas fa-align-left me-1"></i>${escapeHtml(request.description.substring(0, 60))}${request.description.length > 60 ? '...' : ''}</small>` : ''}
                </div>
            </td>
            <td>
                ${request.originalToMunicipality 
                    ? `<span class="badge bg-info">
                         <i class="fas fa-building me-1"></i>${escapeHtml(request.originalToMunicipality)}
                       </span>` 
                    : '<span class="text-muted fst-italic"><i class="fas fa-minus-circle me-1"></i>N/A</span>'}
            </td>
            <td class="text-center">
                <div class="d-flex flex-column align-items-center">
                    <span class="fw-bold fs-5 text-dark">${request.quantity}</span>
                    ${request.unit ? `<small class="text-muted"><i class="fas fa-ruler me-1"></i>${request.unit}</small>` : ''}
                </div>
            </td>
            <td class="text-center">
                <span class="badge bg-${priorityClass}-subtle">
                    <i class="fas fa-${priorityClass === 'danger' ? 'exclamation-triangle' : priorityClass === 'warning' ? 'exclamation-circle' : 'info-circle'} me-1"></i>
                    ${(request.priority || 'N/A').toUpperCase()}
                </span>
            </td>
            <td>
                <div class="d-flex align-items-center text-muted">
                    <i class="far fa-calendar-alt me-2"></i>
                    <div class="d-flex flex-column">
                        <span class="small">${formatDate(request.requestDate)}</span>
                    </div>
                </div>
            </td>
            <td class="text-center">
                <div class="d-flex flex-column align-items-center">
                    ${stockStatus}
                    <small class="text-muted mt-1"><i class="fas fa-boxes me-1"></i>${request.availableStock} available</small>
                </div>
            </td>
            <td class="text-center">
                <div class="btn-group" role="group" aria-label="Actions">
                    <button class="btn btn-outline-success btn-sm ${approveDisabled ? 'opacity-50' : ''}" 
                            onclick="approveRequest(${request.id})" 
                            ${approveDisabled ? 'disabled' : ''}
                            title="${approveTitle}">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm ${rejectDisabled ? 'opacity-50' : ''}" 
                            onclick="rejectRequest(${request.id})" 
                            ${rejectDisabled ? 'disabled' : ''}
                            title="${rejectTitle}">
                        <i class="fas fa-times"></i>
                    </button>
                    <button class="btn btn-outline-primary btn-sm" 
                            onclick="viewRequestDetails(${request.id})" 
                            title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </td>
        </tr>
    `;
}

async function approveRequest(requestId) {
    const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
    if (!row) {
        alert('Request not found');
        return;
    }
    
    // Extract request details from the row
    const resourceName = row.cells[1].querySelector('strong')?.textContent || 'N/A';
    const provider = row.cells[2].querySelector('.badge')?.textContent || 'N/A';
    const quantity = row.cells[3].textContent.trim();
    const priority = row.cells[4].querySelector('.badge')?.textContent || 'N/A';
    const stock = row.cells[6].querySelector('small')?.textContent.replace('Stock: ', '') || 'N/A';
    
    // Show confirmation modal
    if (window.confirmationModal) {
        window.confirmationModal.show({
            title: 'Approve Request',
            message: `Are you sure you want to approve this resource request?`,
            type: 'success',
            confirmText: 'Approve',
            cancelText: 'Cancel',
            showCancel: true,
            onConfirm: async () => {
                await processApproval(requestId);
            }
        });
        
        // Add request details to the modal body
        setTimeout(() => {
            const modalBody = document.querySelector('.confirmation-modal-body');
            if (modalBody) {
                const detailsHTML = `
                    <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 6px; font-size: 0.875rem;">
                        <div style="margin-bottom: 0.5rem;"><strong>Resource:</strong> ${escapeHtml(resourceName)}</div>
                        <div style="margin-bottom: 0.5rem;"><strong>Provider:</strong> ${escapeHtml(provider)}</div>
                        <div style="margin-bottom: 0.5rem;"><strong>Quantity:</strong> ${escapeHtml(quantity)}</div>
                        <div style="margin-bottom: 0.5rem;"><strong>Priority:</strong> ${escapeHtml(priority)}</div>
                        <div><strong>Available Stock:</strong> ${escapeHtml(stock)}</div>
                    </div>
                `;
                modalBody.insertAdjacentHTML('beforeend', detailsHTML);
            }
        }, 100);
    } else {
        if (confirm('Are you sure you want to approve this request?')) {
            await processApproval(requestId);
        }
    }
}

async function processApproval(requestId) {
    try {
        const response = await fetch('config/update_request_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                requestId: requestId,
                action: 'accept'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const data = result.data || {};
            const isGroupRequest = data.isGroupRequest === true;
            const wasForwarded = data.wasForwarded === true;
            const groupStatus = data.groupStatus;
            
            let message = '';
            if (isGroupRequest && groupStatus === 'evaluated_but_waiting') {
                // Item evaluated but group still waiting for other items
                message = 'Request approved successfully! This item is now evaluated. The group will remain in the dashboard until all items are evaluated.';
            } else if (isGroupRequest && groupStatus === 'forwarded') {
                // Entire group was forwarded
                message = 'All items in the group have been evaluated. The entire group has been forwarded to the provider municipality.';
            } else if (wasForwarded) {
                // Single request forwarded
                message = 'Request approved successfully! The request has been forwarded to the provider municipality.';
            } else {
                // Default
                message = 'Request approved successfully!';
            }
            
            setTimeout(() => {
                if (window.confirmationModal) {
                    window.confirmationModal.alert(
                        'Success',
                        message,
                        'success'
                    );
                } else {
                    alert(message);
                }
            }, 250);
            loadPendingApprovals();
        } else {
            setTimeout(() => {
                if (window.confirmationModal) {
                    window.confirmationModal.alert(
                        'Error',
                        result.error?.message || 'Failed to approve request',
                        'error'
                    );
                } else {
                    alert('Error: ' + (result.error?.message || 'Failed to approve request'));
                }
            }, 250);
        }
    } catch (error) {
        console.error('Error approving request:', error);
        setTimeout(() => {
            if (window.confirmationModal) {
                window.confirmationModal.alert(
                    'Error',
                    'Error approving request. Please try again.',
                    'error'
                );
            } else {
                alert('Error approving request. Please try again.');
            }
        }, 250);
    }
}

async function rejectRequest(requestId) {
    const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
    if (!row) {
        alert('Request not found');
        return;
    }
    
    // Extract request details from the row
    const resourceName = row.cells[1].querySelector('strong')?.textContent || 'N/A';
    const provider = row.cells[2].querySelector('.badge')?.textContent || 'N/A';
    const quantity = row.cells[3].textContent.trim();
    
    // Show confirmation modal with input for rejection reason
    if (window.confirmationModal) {
        window.confirmationModal.show({
            title: 'Reject Request',
            message: `Are you sure you want to reject this resource request? Please provide a reason for rejection.`,
            type: 'warning',
            confirmText: 'Reject',
            cancelText: 'Cancel',
            showCancel: true,
            dangerAction: true,
            input: {
                type: 'textarea',
                label: 'Rejection Reason',
                placeholder: 'Enter the reason for rejecting this request...',
                required: true,
                value: ''
            },
            onConfirm: async (reason) => {
                if (!reason || !reason.trim()) {
                    if (window.confirmationModal) {
                        window.confirmationModal.alert(
                            'Required Field',
                            'Please provide a reason for rejection.',
                            'error'
                        );
                    } else {
                        alert('Please provide a reason for rejection.');
                    }
                    return;
                }
                await processRejection(requestId, reason);
            }
        });
        
        // Add request details to the modal body
        setTimeout(() => {
            const modalBody = document.querySelector('.confirmation-modal-body');
            if (modalBody) {
                const detailsHTML = `
                    <div style="margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: 6px; font-size: 0.875rem; border-left: 3px solid #ffc107;">
                        <div style="margin-bottom: 0.5rem;"><strong>Resource:</strong> ${escapeHtml(resourceName)}</div>
                        <div style="margin-bottom: 0.5rem;"><strong>Provider:</strong> ${escapeHtml(provider)}</div>
                        <div><strong>Quantity:</strong> ${escapeHtml(quantity)}</div>
                    </div>
                `;
                const messageElement = modalBody.querySelector('.confirmation-modal-message');
                if (messageElement) {
                    messageElement.insertAdjacentHTML('afterend', detailsHTML);
                }
            }
        }, 100);
    } else {
        const reason = prompt('Please provide a reason for rejection:');
        if (reason === null || !reason.trim()) {
            return;
        }
        await processRejection(requestId, reason);
    }
}

async function processRejection(requestId, reason) {
    try {
        const response = await fetch('config/update_request_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                requestId: requestId,
                action: 'reject',
                rejectionReason: reason
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const data = result.data || {};
            const isGroupRequest = data.isGroupRequest === true;
            const groupStatus = data.groupStatus;
            
            let message = '';
            if (isGroupRequest && groupStatus === 'evaluated_but_waiting') {
                // Item rejected but group still waiting for other items
                message = 'Request rejected successfully! This item is now evaluated. The group will remain in the dashboard until all items are evaluated.';
            } else if (isGroupRequest && groupStatus === 'forwarded') {
                // Entire group was rejected (shouldn't happen, but handle it)
                message = 'All items in the group have been evaluated. The entire group has been rejected.';
            } else {
                // Default
                message = 'The request has been rejected successfully.';
            }
            
            setTimeout(() => {
                if (window.confirmationModal) {
                    window.confirmationModal.alert(
                        'Request Rejected',
                        message,
                        'success'
                    );
                } else {
                    alert(message);
                }
            }, 250);
            loadPendingApprovals();
        } else {
            setTimeout(() => {
                if (window.confirmationModal) {
                    window.confirmationModal.alert(
                        'Error',
                        result.error?.message || 'Failed to reject request',
                        'error'
                    );
                } else {
                    alert('Error: ' + (result.error?.message || 'Failed to reject request'));
                }
            }, 250);
        }
    } catch (error) {
        console.error('Error rejecting request:', error);
        setTimeout(() => {
            if (window.confirmationModal) {
                window.confirmationModal.alert(
                    'Error',
                    'Error rejecting request. Please try again.',
                    'error'
                );
            } else {
                alert('Error rejecting request. Please try again.');
            }
        }, 250);
    }
}

function viewRequestDetails(requestId) {
    window.location.href = `?page=approvals&view=${requestId}`;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const month = date.getMonth() + 1;
    const day = date.getDate();
    const year = date.getFullYear();
    const hours = date.getHours();
    const minutes = date.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const displayHours = hours % 12 || 12;
    const displayMinutes = minutes.toString().padStart(2, '0');
    return `${month}/${day}/${year} ${displayHours}:${displayMinutes} ${ampm}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load approvals on page load
document.addEventListener('DOMContentLoaded', function() {
    loadPendingApprovals();
    
    // Refresh every 30 seconds
    setInterval(loadPendingApprovals, 30000);
});
</script>
