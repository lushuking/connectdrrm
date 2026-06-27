// Requests page actions: Accept/Reject/View
(function(){
    // Single source of truth for request status badge class
    function getRequestStatusClass(status) {
        const map = {
            'pending': 'warning',
            'pending_head_approval': 'warning',
            'accepted': 'success',
            'approved': 'success',
            'rejected': 'danger',
            'in progress': 'info',
            'completed': 'primary',
            'cancelled': 'secondary'
        };
        return map[String(status || '').toLowerCase()] || 'secondary';
    }

    // Expose for templates that call it
    window.getRequestStatusClass = window.getRequestStatusClass || getRequestStatusClass;
    
    // Global function to trigger request data refresh
    window.triggerRequestDataRefresh = function() {
        // Dispatch custom event to refresh all request tables
        document.dispatchEvent(new CustomEvent('requests:refresh'));
        
        // Also refresh notifications
        if (window.refreshHeaderNotifications) {
            window.refreshHeaderNotifications();
        }
    };

    window.__pendingActionRequestId = window.__pendingActionRequestId || null;
    window.__pendingActionType = window.__pendingActionType || null;

    // Expose a normalized status->class mapper globally to avoid duplicates
    window.getRequestStatusClass = function(status){
        const s = String(status || '').toLowerCase();
        const statusMap = {
            'pending': 'warning',
            'pending_head_approval': 'warning',
            'accepted': 'success',
            'approved': 'success',
            'rejected': 'danger',
            'in progress': 'info',
            'completed': 'primary',
            'fulfilled': 'primary',
            'cancelled': 'secondary'
        };
        if (!s) return 'warning'; // treat empty/unknown as pending visually
        return statusMap[s] || 'secondary';
    };

    // Mark as received (fulfilled)
    window.markRequestReceived = function(requestId){
        if (!requestId) return;
        
        // Get request details for confirmation message
        const request = (window.requestsData || []).find(r => String(r.id) === String(requestId));
        const requestName = request ? (request.name || 'this resource') : 'this resource';
        const requestQuantity = request ? (request.quantity || '') : '';
        const requestUnit = request ? (request.unit || '') : '';
        const quantityText = requestQuantity ? ` (Qty: ${requestQuantity}${requestUnit ? ' ' + requestUnit : ''})` : '';
        
        // Show confirmation modal
        if (window.confirmationModal) {
            window.confirmationModal.show({
                title: 'Confirm Receipt',
                message: `Are you sure you have received <strong>${requestName}${quantityText}</strong>?<br><br>This action will mark the request as fulfilled and notify the provider.`,
                type: 'warning',
                confirmText: 'Yes, Mark as Received',
                cancelText: 'Cancel',
                showCancel: true,
                onConfirm: () => {
                    // User confirmed - proceed with marking as received
                    performMarkAsReceived(requestId);
                },
                onCancel: () => {
                    // User cancelled - do nothing
                }
            });
        } else {
            // Fallback to direct confirmation if modal not available
            if (window.confirm(`Are you sure you have received ${requestName}${quantityText}?`)) {
                performMarkAsReceived(requestId);
            }
        }
    };
    
    // Perform the actual mark as received action
    function performMarkAsReceived(requestId) {
        if (!requestId) return;
        
        // Optimistic UI
        window.requestStatusOverride = window.requestStatusOverride || {};
        window.requestStatusOverride[requestId] = 'fulfilled';
        try {
            const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
            if (row) {
                const badge = row.querySelector('td:nth-child(6) .badge');
                if (badge) { badge.className = `badge bg-${window.getRequestStatusClass('fulfilled')}`; badge.textContent = 'fulfilled'; }
                const btnGroup = row.querySelector('td:last-child .btn-group');
                if (btnGroup) {
                    btnGroup.querySelectorAll('button[data-action="received"]').forEach(b => b.remove());
                }
            }
        } catch(_){}
        
        fetch('config/mark_request_received.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ requestId: requestId })
        }).then(r=>r.json()).then(j=>{
            if (!(j && j.success)) {
                // Revert optimistic UI
                delete window.requestStatusOverride[requestId];
                persistOverrides();
                
                if (typeof showNotification==='function') showNotification('Failed to mark as received', 'error');
                
                // Refresh to restore correct state
                if (typeof loadUserRequests==='function') loadUserRequests();
                if (typeof loadRequests==='function') loadRequests(window.requestsCurrentPage||1);
            } else {
                if (typeof showNotification==='function') showNotification('Request marked as received successfully', 'success');
                
                // Trigger real-time updates for all request tables
                triggerRequestDataRefresh();
                
                // Refresh to show updated state
                if (typeof loadUserRequests==='function') loadUserRequests();
                if (typeof loadRequests==='function') loadRequests(window.requestsCurrentPage||1);
            }
        }).catch(()=>{
            // Revert optimistic UI on error
            delete window.requestStatusOverride[requestId];
            persistOverrides();
            
            if (typeof showNotification==='function') showNotification('Failed to mark as received', 'error');
            
            // Refresh to restore correct state
            if (typeof loadUserRequests==='function') loadUserRequests();
            if (typeof loadRequests==='function') loadRequests(window.requestsCurrentPage||1);
        });
    }
    // Track immediate client-side status overrides until fresh data is fetched; persist to localStorage
    try {
        const saved = localStorage.getItem('requestStatusOverride');
        window.requestStatusOverride = saved ? JSON.parse(saved) : (window.requestStatusOverride || {});
    } catch(_) {
        window.requestStatusOverride = window.requestStatusOverride || {};
    }
    function persistOverrides() {
        try { localStorage.setItem('requestStatusOverride', JSON.stringify(window.requestStatusOverride)); } catch(_) {}
    }

    function openConfirmModal(kind, requestId) {
        const modalEl = document.getElementById('confirmActionModal');
        const message = document.getElementById('confirmActionMessage');
        const btn = document.getElementById('confirmActionBtn');
        const reasonField = document.getElementById('rejectionReasonField');
        const reasonTextarea = document.getElementById('rejectionReason');
        
        if (message) message.textContent = kind === 'accept'
            ? 'Are you sure you want to ACCEPT this resource request?'
            : 'Are you sure you want to REJECT this resource request?';
        if (btn) {
            btn.textContent = kind === 'accept' ? 'Accept Request' : 'Reject Request';
            btn.className = 'btn ' + (kind === 'accept' ? 'btn-success' : 'btn-danger') + ' btn-sm';
            if (requestId) {
                btn.dataset.requestId = requestId;
                btn.dataset.action = kind;
            }
        }
        
        // Show/hide rejection reason field
        if (reasonField) {
            if (kind === 'reject') {
                reasonField.style.display = 'block';
                if (reasonTextarea) {
                    reasonTextarea.value = ''; // Clear previous reason
                    reasonTextarea.focus();
                }
            } else {
                reasonField.style.display = 'none';
            }
        }
        
        try {
            if (typeof bootstrap !== 'undefined' && modalEl) {
                new bootstrap.Modal(modalEl).show();
                return true;
            }
        } catch (e) {
            console.error('Bootstrap modal open failed:', e);
        }
        return false;
    }

    function acceptRequest(requestId) {
        window.__pendingActionRequestId = requestId;
        window.__pendingActionType = 'accept';
        if (!openConfirmModal('accept', requestId)) {
            if (window.confirm('Are you sure you want to ACCEPT this resource request?')) {
                updateRequestStatus(requestId, 'accept');
                window.__pendingActionRequestId = null;
                window.__pendingActionType = null;
            }
        }
    }

    function showAcceptModal(requestId) {
        acceptRequest(requestId);
    }

    function showRejectModal(requestId) {
        window.__pendingActionRequestId = requestId;
        window.__pendingActionType = 'reject';
        if (!openConfirmModal('reject', requestId)) {
            if (window.confirm('Are you sure you want to REJECT this resource request?')) {
                updateRequestStatus(requestId, 'reject');
                window.__pendingActionRequestId = null;
                window.__pendingActionType = null;
            }
        }
    }

    // Handle confirmation button click via robust event delegation (guarded against multiple registrations)
    if (!window.confirmActionBtnListenerRegistered) {
        document.addEventListener('click', function(e) {
            const confirmBtn = e.target.closest('#confirmActionBtn');
            if (confirmBtn) {
                e.preventDefault();
                const requestId = confirmBtn.dataset.requestId || window.__pendingActionRequestId;
                const action = confirmBtn.dataset.action || window.__pendingActionType;
                if (requestId && action) {
                    updateRequestStatus(requestId, action);
                    const modalEl = document.getElementById('confirmActionModal');
                    if (modalEl && typeof bootstrap !== 'undefined') {
                        const modal = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
                        if (modal) modal.hide();
                    }
                    window.__pendingActionRequestId = null;
                    window.__pendingActionType = null;
                    delete confirmBtn.dataset.requestId;
                    delete confirmBtn.dataset.action;
                }
            }
        });
        window.confirmActionBtnListenerRegistered = true;
    }


    function updateRequestStatus(requestId, action) {
        const btn = document.getElementById('confirmActionBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...'; }
        
        // Get rejection reason if it's a rejection
        const reasonTextarea = document.getElementById('rejectionReason');
        const rejectionReason = (action === 'reject' && reasonTextarea) ? reasonTextarea.value.trim() : null;
        
        const requestBody = { requestId, action };
        if (rejectionReason) {
            requestBody.rejectionReason = rejectionReason;
        }
        
        fetch('config/update_request_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestBody)
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                const actionText = action === 'accept' ? 'accepted' : 'rejected';
                if (typeof showNotification === 'function') {
                    showNotification(`Request ${actionText} successfully! The requesting municipality has been notified.`, 'success');
                }
                // Optimistic UI: update the row status and hide action buttons
                try {
                    const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                    if (row) {
                        // Status cell is the 6th td in the row (0-indexed 5)
                        const cells = row.querySelectorAll('td');
                        // Update badge
                        const normalized = action === 'accept' ? 'accepted' : 'rejected';
                        const badgeClass = getRequestStatusClass(normalized);
                        if (cells && cells.length >= 7) {
                            cells[5].innerHTML = `<span class="badge bg-${badgeClass}">${normalized.charAt(0).toUpperCase() + normalized.slice(1)}</span>`;
                        }
                        // Remove accept/reject buttons, keep View
                        const actionsCell = cells[7];
                        if (actionsCell) {
                            const btnGroup = actionsCell.querySelector('.btn-group');
                            if (btnGroup) {
                                // Remove all buttons except the last (View Details)
                                const buttons = btnGroup.querySelectorAll('button');
                                buttons.forEach((b, idx) => {
                                    const title = (b.getAttribute('title') || '').toLowerCase();
                                    if (title.includes('accept') || title.includes('reject')) {
                                        b.remove();
                                    }
                                });
                            }
                        }
                    }
                } catch (_) {
                    // ignore DOM update errors
                }
                // Record override so other tables (e.g., My Requests) reflect the change immediately
                window.requestStatusOverride[requestId] = action === 'accept' ? 'approved' : 'rejected';
                persistOverrides();
                // Keep group expanded after approving one item (group stays visible like in Head approval)
                if (data.requestGroupId) {
                    if (!window.expandedResourceRequestGroups) window.expandedResourceRequestGroups = new Set();
                    window.expandedResourceRequestGroups.add(data.requestGroupId);
                }
                // Trigger real-time updates for all request tables
                triggerRequestDataRefresh();
                // Pull fresh requests from server to ensure persisted statuses show correctly
                fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(j => {
                        if (j && j.success && j.data && Array.isArray(j.data.requests)) {
                            window.requestsData = j.data.requests;
                            // Clear overrides for items that now have a persisted status
                            try {
                                j.data.requests.forEach(function(r){
                                    const s = String(r.status||'').toLowerCase();
                                    if (s === 'approved' || s === 'rejected') {
                                        delete window.requestStatusOverride[r.id];
                                    }
                                });
                                persistOverrides();
                            } catch(_){}
                        }
                        if (typeof loadRequests === 'function') {
                            if (window.requestsCurrentPage) {
                                loadRequests(window.requestsCurrentPage);
                            } else {
                                loadRequests(1);
                            }
                        }
                        if (typeof loadUserRequests === 'function') {
                            loadUserRequests();
                        }
                    })
                    .catch(() => {
                        if (typeof loadRequests === 'function') loadRequests(1);
                        if (typeof loadUserRequests === 'function') loadUserRequests();
                    });
                if (typeof loadAvailableResources === 'function') {
                    loadAvailableResources();
                }
            } else {
                const msg = data && data.error && data.error.message ? data.error.message : 'Unknown error';
                // If backend says invalid state (e.g., Only pending requests can be updated),
                // clear any optimistic override and refresh from server so UI matches reality
                const isInvalidState = /only\s+pending\s+requests\s+can\s+be\s+updated/i.test(msg) || (data && data.error && data.error.code === 'invalid_state');
                if (isInvalidState) {
                    try {
                        if (window.requestStatusOverride) delete window.requestStatusOverride[requestId];
                        persistOverrides();
                    } catch(_) {}
                    fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                        .then(r => r.json()).then(j => {
                            if (j && j.success && j.data && Array.isArray(j.data.requests)) {
                                window.requestsData = j.data.requests;
                            }
                            if (typeof loadRequests === 'function') loadRequests(1);
                            if (typeof loadUserRequests === 'function') loadUserRequests();
                            if (typeof loadBorrowedRequests === 'function') loadBorrowedRequests();
                        }).catch(()=>{
                            if (typeof loadRequests === 'function') loadRequests(1);
                        });
                }
                if (typeof showNotification === 'function') {
                    showNotification('Error updating request: ' + msg, 'error');
                } else {
                    alert('Error updating request: ' + msg);
                }
            }
        })
        .catch(err => {
            console.error('updateRequestStatus failed:', err);
            if (typeof showNotification === 'function') {
                showNotification('Error updating request. Please try again.', 'error');
            } else {
                alert('Error updating request. Please try again.');
            }
        })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = action === 'accept' ? 'Accept Request' : 'Reject Request';
            }
        });
    }

    // Expose to global for onclick handlers in table
    window.acceptRequest = acceptRequest;
    window.showAcceptModal = showAcceptModal;
    window.showRejectModal = showRejectModal;
    window.updateRequestStatus = updateRequestStatus;

    window.__pendingDeleteRequestId = null;
    function openDeleteRequestModal(requestId) {
        window.__pendingDeleteRequestId = requestId;
        const txt = document.getElementById('deleteRequestConfirmText');
        if (txt) txt.textContent = `Are you sure you want to delete request #${requestId}? This cannot be undone.`;
        const el = document.getElementById('deleteRequestConfirmModal');
        try {
            if (el && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(el).show();
                return true;
            }
        } catch (_) {}
        return false;
    }

    function performDeleteRequest(requestId) {
        return fetch('config/delete_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ requestId })
        }).then(r=>r.json());
    }
    window.__pendingBypassRequestId = null;
    window.bypassHeadApproval = function(requestId) {
        window.__pendingBypassRequestId = requestId;
        const modalEl = document.getElementById('bypassApprovalConfirmModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            try {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } catch(e) {
                console.error('[bypassHeadApproval] modal error:', e);
                if (window.confirm("Are you sure you want to bypass the Head's approval and forward this request?")) {
                    performBypassApproval(requestId);
                }
            }
        } else {
            if (window.confirm("Are you sure you want to bypass the Head's approval and forward this request?")) {
                performBypassApproval(requestId);
            }
        }
    };

    function performBypassApproval(requestId) {
        return fetch('config/update_request_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ requestId: requestId, action: 'accept' })
        })
        .then(r => r.json())
        .then(j => {
            if (!j.success) throw new Error(j.error?.message || 'Failed');
            if (typeof showNotification === 'function') showNotification('Request bypassed and forwarded to provider!', 'success');
            
            // Ensure status badge updates without needing a full reload
            if (!window.requestStatusOverride) window.requestStatusOverride = {};
            window.requestStatusOverride[requestId] = 'pending';
            
            // Refresh data
            if (typeof triggerRequestDataRefresh === 'function') {
                triggerRequestDataRefresh();
            } else {
                fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                    .then(r=>r.json()).then(d=>{
                        if (d && d.success && d.data && Array.isArray(d.data.requests)) {
                            window.requestsData = d.data.requests;
                        }
                        if (typeof loadUserRequests === 'function') loadUserRequests();
                    });
            }
        })
        .catch(e => {
            if (typeof showNotification === 'function') showNotification('Failed to bypass request: ' + (e.message || 'Error'), 'error');
            else alert('Failed to bypass request: ' + e.message);
        });
    }

    // Wire confirm button for bypass request modal
    const initBypassBtn = function() {
        const bypassBtn = document.getElementById('confirmBypassApprovalBtn');
        if (bypassBtn && !bypassBtn.dataset.bound) {
            bypassBtn.dataset.bound = '1';
            bypassBtn.addEventListener('click', function(){
                if (!window.__pendingBypassRequestId) return;
                const el = document.getElementById('bypassApprovalConfirmModal');
                const origHtml = bypassBtn.innerHTML;
                bypassBtn.disabled = true; 
                bypassBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Forwarding...';
                
                performBypassApproval(window.__pendingBypassRequestId).finally(() => {
                    bypassBtn.disabled = false;
                    bypassBtn.innerHTML = origHtml;
                    if (el && typeof bootstrap !== 'undefined') {
                        const modalInstance = bootstrap.Modal.getInstance(el);
                        if (modalInstance) modalInstance.hide();
                    }
                    window.__pendingBypassRequestId = null;
                });
            });
        }
    };
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBypassBtn);
    } else {
        initBypassBtn();
    }


    window.deleteMyRequest = function(requestId) {
        if (openDeleteRequestModal(requestId)) return;
        // Fallback to native confirm if modal not available
        if (!window.confirm('Delete this request? This cannot be undone.')) return;
        performDeleteRequest(requestId).then(j=>{
            if (!j.success) throw new Error(j.error?.message || 'Failed');
            if (typeof showNotification === 'function') showNotification('Request deleted.', 'success');
            // Refresh both lists
            fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                .then(r=>r.json()).then(d=>{
                    if (d && d.success && d.data && Array.isArray(d.data.requests)) {
                        window.requestsData = d.data.requests;
                    }
                    if (typeof loadUserRequests === 'function') loadUserRequests();
                    if (typeof loadRequests === 'function') loadRequests(1);
                }).catch(()=>{
                    if (typeof loadUserRequests === 'function') loadUserRequests();
                    if (typeof loadRequests === 'function') loadRequests(1);
                });
        }).catch(e=>{
            if (typeof showNotification === 'function') showNotification('Failed to delete request: ' + (e.message || 'Error'), 'error');
            else alert('Failed to delete request');
        });
    };

    // Wire confirm button for delete request modal
    const initDeleteBtn = function(){
        const btn = document.getElementById('confirmDeleteRequestBtn');
        if (btn && !btn.dataset.bound) {
            btn.dataset.bound = '1';
            btn.addEventListener('click', function(){
                if (!window.__pendingDeleteRequestId) return;
                const el = document.getElementById('deleteRequestConfirmModal');
                btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';
                performDeleteRequest(window.__pendingDeleteRequestId).then(j=>{
                    if (!j.success) throw new Error(j.error?.message || 'Failed');
                    if (typeof showNotification === 'function') showNotification('Request deleted.', 'success');
                    if (el && typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(el).hide();
                    window.__pendingDeleteRequestId = null;
                    // Refresh lists
                    fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                        .then(r=>r.json()).then(d=>{
                            if (d && d.success && d.data && Array.isArray(d.data.requests)) {
                                window.requestsData = d.data.requests;
                            }
                            if (typeof loadUserRequests === 'function') loadUserRequests();
                            if (typeof loadRequests === 'function') loadRequests(1);
                        }).catch(()=>{
                            if (typeof loadUserRequests === 'function') loadUserRequests();
                            if (typeof loadRequests === 'function') loadRequests(1);
                        });
                }).catch(e=>{
                    if (typeof showNotification === 'function') showNotification('Failed to delete request: ' + (e.message || 'Error'), 'error');
                }).finally(()=>{
                    btn.disabled = false; btn.textContent = 'Delete';
                });
            });
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDeleteBtn);
    } else {
        initDeleteBtn();
    }

    // Request return (borrower initiates return)
    window.requestReturn = function(requestId){
        if (!requestId) return;
        
        // Get request details for confirmation message
        const request = (window.requestsData || []).find(r => String(r.id) === String(requestId));
        const requestName = request ? (request.name || 'this resource') : 'this resource';
        const requestQuantity = request ? (request.quantity || '') : '';
        const requestUnit = request ? (request.unit || '') : '';
        const quantityText = requestQuantity ? ` (Qty: ${requestQuantity}${requestUnit ? ' ' + requestUnit : ''})` : '';
        const toMunicipality = request ? (request.toMunicipality || 'the provider') : 'the provider';
        
        // Show confirmation modal
        if (window.confirmationModal) {
            window.confirmationModal.show({
                title: 'Confirm Return Request',
                message: `Are you sure you want to return <strong>${requestName}${quantityText}</strong> to ${toMunicipality}?<br><br>This action will request the provider to confirm the return. The resource will be restocked once confirmed.`,
                type: 'warning',
                confirmText: 'Yes, Request Return',
                cancelText: 'Cancel',
                showCancel: true,
                onConfirm: () => {
                    // User confirmed - proceed with return request
                    performRequestReturn(requestId);
                },
                onCancel: () => {
                    // User cancelled - do nothing
                }
            });
        } else {
            // Fallback to direct confirmation if modal not available
            if (window.confirm(`Are you sure you want to return ${requestName}${quantityText} to ${toMunicipality}?`)) {
                performRequestReturn(requestId);
            }
        }
    };
    
    // Perform the actual return request action
    function performRequestReturn(requestId) {
        if (!requestId) return;
        
        // Optimistic UI to return pending (full quantity)
        window.requestStatusOverride = window.requestStatusOverride || {};
        window.requestStatusOverride[requestId] = 'return pending';
        try {
            const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
            if (row) {
                const badge = row.querySelector('td:nth-child(5) .badge, td:nth-child(6) .badge');
                if (badge) { badge.className = `badge bg-${window.getRequestStatusClass('return pending')}`; badge.textContent = 'return pending'; }
            }
        } catch(_){}
        
        fetch('config/request_return.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ requestId: requestId })
        }).then(r=>r.json()).then(j=>{
            if (!(j && j.success)) {
                // Revert optimistic UI
                delete window.requestStatusOverride[requestId];
                persistOverrides();
                
                if (typeof showNotification==='function') showNotification('Failed to request return', 'error');
                
                // Refresh to restore correct state
                fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                    .then(r=>r.json()).then(d=>{
                        if (d && d.success && d.data && Array.isArray(d.data.requests)) {
                            window.requestsData = d.data.requests;
                        }
                        if (typeof loadBorrowedRequests==='function') loadBorrowedRequests();
                        if (typeof loadRequests==='function') loadRequests(1);
                        if (typeof loadUserRequests==='function') loadUserRequests();
                    }).catch(()=>{
                        if (typeof loadBorrowedRequests==='function') loadBorrowedRequests();
                    });
            } else {
                if (typeof showNotification==='function') showNotification('Return requested. Awaiting provider confirmation.', 'success');
                
                try {
                    if (typeof window.refreshHeaderNotifications === 'function') {
                        window.refreshHeaderNotifications();
                    } else {
                        document.dispatchEvent(new CustomEvent('notifications:refresh'));
                    }
                } catch(_) {}
                
                // Refresh both tables
                fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                    .then(r=>r.json()).then(d=>{
                        if (d && d.success && d.data && Array.isArray(d.data.requests)) {
                            window.requestsData = d.data.requests;
                        }
                        if (typeof loadBorrowedRequests==='function') loadBorrowedRequests();
                        if (typeof loadRequests==='function') loadRequests(1);
                        if (typeof loadUserRequests==='function') loadUserRequests();
                    }).catch(()=>{
                        if (typeof loadBorrowedRequests==='function') loadBorrowedRequests();
                    });
            }
        }).catch(()=>{
            // Revert optimistic UI on error
            delete window.requestStatusOverride[requestId];
            persistOverrides();
            
            if (typeof showNotification==='function') showNotification('Failed to request return', 'error');
            
            // Refresh to restore correct state
            fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                .then(r=>r.json()).then(d=>{
                    if (d && d.success && d.data && Array.isArray(d.data.requests)) {
                        window.requestsData = d.data.requests;
                    }
                    if (typeof loadBorrowedRequests==='function') loadBorrowedRequests();
                    if (typeof loadRequests==='function') loadRequests(1);
                    if (typeof loadUserRequests==='function') loadUserRequests();
                }).catch(()=>{
                    if (typeof loadBorrowedRequests==='function') loadBorrowedRequests();
                });
        });
    }
    // Provider-side confirm return
    if (typeof window.confirmReturn !== 'function') {
        window.confirmReturn = function(requestId){
            // Helper function to perform the actual confirmation
            const performConfirmReturn = function() {
                const btn = document.getElementById('confirmReturnBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Confirming...';
                }

                // Use the latest request id assigned when opening the modal.
                // This avoids stale closure values when the button is bound only once.
                const effectiveRequestId = (btn && btn.dataset && btn.dataset.currentRequestId)
                    ? btn.dataset.currentRequestId
                    : requestId;
                
                fetch('config/confirm_return.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ requestId: effectiveRequestId })
                }).then(r => {
                    if (!r.ok) {
                        return r.json().then(j => {
                            throw new Error(j?.error?.message || 'Failed to confirm return');
                        });
                    }
                    return r.json();
                }).then(j => {
                    if (!(j && j.success)) {
                        const errorMsg = j?.error?.message || 'Failed to confirm return';
                        if (typeof showNotification==='function') showNotification(errorMsg, 'error');
                    } else {
                        if (typeof showNotification==='function') showNotification('Return confirmed and restocked.', 'success');
                        
                        // Trigger real-time updates for all request tables
                        if (typeof triggerRequestDataRefresh === 'function') {
                            triggerRequestDataRefresh();
                        }
                    }
                    
                    // Close modal if it exists
                    const modalEl = document.getElementById('confirmReturnModal');
                    if (modalEl) {
                        const modal = (typeof bootstrap !== 'undefined') ? bootstrap.Modal.getInstance(modalEl) : null;
                        if (modal) {
                            modal.hide();
                        } else {
                            modalEl.style.display = 'none';
                            modalEl.classList.remove('show');
                            document.body.classList.remove('modal-open');
                            const backdrop = document.querySelector('.modal-backdrop');
                            if (backdrop) backdrop.remove();
                        }
                    }
                    
                    // Refresh request data
                    fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(d => {
                            if (d && d.success && d.data && Array.isArray(d.data.requests)) {
                                window.requestsData = d.data.requests;
                            }
                            if (typeof loadBorrowedRequests === 'function') loadBorrowedRequests();
                            if (typeof loadRequests === 'function') loadRequests(1);
                        })
                        .catch(() => {
                            if (typeof loadRequests === 'function') loadRequests(1);
                        });
                }).catch(err => {
                    const errorMsg = err.message || 'Failed to confirm return. Please try again.';
                    if (typeof showNotification === 'function') {
                        showNotification(errorMsg, 'error');
                    } else {
                        alert(errorMsg);
                    }
                }).finally(() => {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Confirm Return';
                    }
                });
            };

            // Check if modal exists, if not, use fallback
            const modalEl = document.getElementById('confirmReturnModal');
            const btn = document.getElementById('confirmReturnBtn');
            
            if (!modalEl || !btn) {
                // Fallback: use simple confirmation dialog
                if (!window.confirm('Confirm the return and restock the resource?')) return;
                performConfirmReturn();
                return;
            }

            // Ensure requestsData is available, fetch if needed
            if (!window.requestsData || !Array.isArray(window.requestsData)) {
                // Try to fetch request data first
                fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.success && d.data && Array.isArray(d.data.requests)) {
                            window.requestsData = d.data.requests;
                        }
                        // Now populate and show modal
                        populateAndShowModal();
                    })
                    .catch(() => {
                        // If fetch fails, still try to show modal with minimal data
                        populateAndShowModal();
                    });
            } else {
                populateAndShowModal();
            }

            function populateAndShowModal() {
                try {
                    const req = (window.requestsData || []).find(r => String(r.id) === String(requestId));
                    
                    // Populate modal with defensive checks
                    const set = (id, html) => {
                        const el = document.getElementById(id);
                        if (el) {
                            try {
                                el.innerHTML = html;
                            } catch (e) {
                                console.warn('Failed to set content for element:', id, e);
                            }
                        }
                    };
                    
                    set('retModalId', `REQ-${requestId}`);
                    set('retModalResource', req && req.name ? req.name : '—');
                    set('retModalBorrower', req && req.municipality ? req.municipality : '—');
                    set('retModalQty', req ? `${req.quantity || 0} ${req.unit || ''}`.trim() : '—');
                    
                    // Safe status class getter
                    const statusClass = (typeof window.getRequestStatusClass === 'function') 
                        ? window.getRequestStatusClass((req && req.status) || 'return pending')
                        : 'secondary';
                    set('retModalStatus', `<span class="badge bg-${statusClass}">${(req && req.status) || 'return pending'}</span>`);

                    // Ensure button is ready
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Confirm Return';
                        // Track the "current" requestId intended for this modal instance
                        btn.dataset.currentRequestId = String(requestId);
                        
                        // Ensure single binding
                        if (!btn.dataset.bound) {
                            btn.dataset.bound = '1';
                            btn.dataset.boundRequestId = String(requestId);
                            btn.addEventListener('click', performConfirmReturn);
                        }
                    }

                    // Show modal
                    if (typeof bootstrap !== 'undefined' && modalEl) {
                        const modal = new bootstrap.Modal(modalEl);
                        modal.show();
                    } else if (modalEl) {
                        // Fallback if Bootstrap is not available
                        modalEl.style.display = 'block';
                        modalEl.classList.add('show');
                        document.body.classList.add('modal-open');
                    }
                } catch (err) {
                    console.error('Error populating confirm return modal:', err);
                    // Fallback to simple confirmation
                    if (!window.confirm('Confirm the return and restock the resource?')) return;
                    performConfirmReturn();
                }
            }
        };
    }
})();

// PDRRMO Requests page JS stub
// The PDRRMO requests page embeds its main inline script in the PHP file.
// This stub avoids 404s from automatic script includes.
document.addEventListener('DOMContentLoaded', function () {
  console.debug('[requests.js] Loaded. Inline logic is defined in dashboards/pages/pdrrmo/requests.php');
});


