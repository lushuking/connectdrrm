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
        const dispatchField = document.getElementById('dispatchItemsField');
        const dispatchList = document.getElementById('dispatchItemsList');
        const dispatchValidation = document.getElementById('dispatchItemsValidation');

        // Hide dispatch items by default
        if (dispatchField) dispatchField.style.display = 'none';
        if (dispatchList) dispatchList.innerHTML = '';
        if (dispatchValidation) dispatchValidation.style.display = 'none';

        if (message) message.textContent = kind === 'accept'
            ? 'Are you sure you want to ACCEPT this resource request?'
            : 'Are you sure you want to REJECT this resource request?';
        if (btn) {
            btn.textContent = kind === 'accept' ? 'Accept Request' : 'Reject Request';
            btn.className = 'btn ' + (kind === 'accept' ? 'btn-success' : 'btn-danger') + ' btn-sm';
            btn.disabled = false; // Enable initially
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

        // Fetch available items if accepting
        if (kind === 'accept' && requestId) {
            // Disable button during loading
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';
            }
            fetch(`config/get_request_details.php?requestId=${requestId}`)
                .then(r => r.json())
                .then(res => {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = 'Accept Request';
                    }
                    if (res && res.success && res.data) {
                        const req = res.data;
                        const qty = parseInt(req.quantity, 10) || 0;
                        if (req.availableItems && Array.isArray(req.availableItems)) {
                            // This is an itemized resource
                            if (dispatchField && dispatchList) {
                                dispatchField.style.display = 'block';
                                document.getElementById('dispatchItemsQty').textContent = qty;
                                
                                if (req.availableItems.length === 0) {
                                    dispatchList.innerHTML = '<div class="text-danger small p-2 text-center">No available units in inventory! Can not approve request.</div>';
                                    if (btn) btn.disabled = true;
                                    return;
                                }

                                dispatchList.innerHTML = req.availableItems.map(item => `
                                    <div class="form-check p-2 rounded border mb-1 d-flex align-items-center justify-content-between bg-white shadow-xs" style="border: 1px solid #e9ecef!important; padding-left: 2.5em!important;">
                                        <div>
                                            <input class="form-check-input dispatch-item-chk" type="checkbox" value="${item.itemID}" id="chk_dispatch_${item.itemID}" style="cursor: pointer; width: 16px; height: 16px; margin-top: 0.15em;">
                                            <label class="form-check-label fw-bold text-dark ms-1" for="chk_dispatch_${item.itemID}" style="cursor: pointer;">
                                                ${item.uniqueIdentifier}
                                            </label>
                                            <span class="text-muted small ms-2">Loc: ${item.storageLocation || 'N/A'}</span>
                                        </div>
                                        <div>
                                            <span class="badge bg-success">${item.status}</span>
                                        </div>
                                    </div>
                                `).join('');

                                // Add change listener to checkboxes
                                const checkboxes = dispatchList.querySelectorAll('.dispatch-item-chk');
                                function validateSelection() {
                                    const checked = Array.from(checkboxes).filter(c => c.checked);
                                    if (checked.length === qty) {
                                        if (dispatchValidation) dispatchValidation.style.display = 'none';
                                        if (btn) btn.disabled = false;
                                    } else {
                                        if (dispatchValidation) {
                                            dispatchValidation.style.display = 'block';
                                            dispatchValidation.textContent = `Must select exactly ${qty} unit(s). Currently selected: ${checked.length}.`;
                                        }
                                        if (btn) btn.disabled = true;
                                    }
                                }
                                checkboxes.forEach(c => c.addEventListener('change', validateSelection));
                                validateSelection(); // Initial validation
                            }
                        }
                    }
                })
                .catch(err => {
                    console.error('Failed to load item availability', err);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = 'Accept Request';
                    }
                });
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

        // Collect selected dispatch item IDs if accepting an itemized request
        if (action === 'accept') {
            const checkedBoxes = document.querySelectorAll('#dispatchItemsList .dispatch-item-chk:checked');
            if (checkedBoxes.length > 0) {
                requestBody.dispatchedItems = Array.from(checkedBoxes).map(c => parseInt(c.value, 10));
            }
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
    // Provider-side confirm return (with damage assessment)
    if (typeof window.confirmReturn !== 'function') {
        window.confirmReturn = function(requestId){

            // ── Helpers ──────────────────────────────────────────────────
            // Bind live validation so Good + Damaged must equal total qty
            let _totalReturnQty = 0;
            function bindAssessmentValidation(total) {
                _totalReturnQty = total;
                const goodEl    = document.getElementById('retGoodQty');
                const damagedEl = document.getElementById('retDamagedQty');
                const validEl   = document.getElementById('retQtyValidation');
                const btn       = document.getElementById('confirmReturnBtn');
                // Enforce the browser-level max so the spinner arrows stop at the total
                if (goodEl)    goodEl.max    = total;
                if (damagedEl) damagedEl.max = total;
                function validate() {
                    const good    = parseInt(goodEl?.value    || '0', 10) || 0;
                    const damaged = parseInt(damagedEl?.value || '0', 10) || 0;
                    const sum = good + damaged;
                    if (!validEl) return;
                    if (sum === total) {
                        validEl.style.display = 'none';
                        if (btn) btn.disabled = false;
                    } else {
                        validEl.style.display = 'block';
                        const diff = total - sum;
                        if (diff > 0) {
                            validEl.innerHTML = `<span class="text-warning"><span class="material-icons" style="font-size:13px;vertical-align:middle;">warning</span> ${diff} item(s) unaccounted for. Total must be ${total}.</span>`;
                        } else {
                            validEl.innerHTML = `<span class="text-danger"><span class="material-icons" style="font-size:13px;vertical-align:middle;">error</span> Total exceeds returned qty (${total}). Reduce good or damaged count.</span>`;
                        }
                        if (btn) btn.disabled = true;
                    }
                }
                if (goodEl)    { goodEl.addEventListener('input',    validate); goodEl.addEventListener('change',    validate); }
                if (damagedEl) { damagedEl.addEventListener('input', validate); damagedEl.addEventListener('change', validate); }
                validate(); // run immediately
            }

            // ── Perform the actual HTTP call ──────────────────────────────
            const performConfirmReturn = function() {
                const btn = document.getElementById('confirmReturnBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Confirming...';
                }

                const effectiveRequestId = (btn && btn.dataset && btn.dataset.currentRequestId)
                    ? btn.dataset.currentRequestId
                    : requestId;

                // Collect assessment values
                const goodQty    = parseInt(document.getElementById('retGoodQty')?.value    || '0', 10) || 0;
                const damagedQty = parseInt(document.getElementById('retDamagedQty')?.value || '0', 10) || 0;
                const damageNotes = document.getElementById('retDamageNotes')?.value.trim() || '';

                // Collect per-unit item conditions if itemized return checklist is visible
                const itemConditions = [];
                const returnItemsField = document.getElementById('returnItemsField');
                if (returnItemsField && returnItemsField.style.display !== 'none') {
                    const selects = document.querySelectorAll('#returnItemsList .return-item-condition');
                    selects.forEach(sel => {
                        itemConditions.push({
                            itemID: parseInt(sel.dataset.itemId, 10),
                            condition: sel.value  // 'Available' or 'Damaged / Repairing'
                        });
                    });
                }

                const payload = {
                    requestId:    effectiveRequestId,
                    goodQty:      goodQty,
                    damagedQty:   damagedQty,
                    damageNotes:  damageNotes
                };
                if (itemConditions.length > 0) {
                    payload.itemConditions = itemConditions;
                }

                fetch('config/confirm_return.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
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
                        let msg = 'Return confirmed.';
                        if (j.data && j.data.hasDamage) {
                            msg = `Return confirmed: ${j.data.goodQty} restocked, ${j.data.damagedQty} flagged as damaged.`;
                        } else {
                            msg = `Return confirmed and restocked (${j.data?.goodQty ?? goodQty} item(s)).`;
                        }
                        if (typeof showNotification==='function') showNotification(msg, 'success');

                        // Trigger real-time updates for all request tables
                        if (typeof triggerRequestDataRefresh === 'function') {
                            triggerRequestDataRefresh();
                        }
                    }

                    // Close modal
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
                        btn.innerHTML = '<span class="material-icons me-1" style="font-size:15px;vertical-align:middle;">task_alt</span>Confirm Return';
                    }
                });
            };

            // Check if modal exists, if not, use fallback
            const modalEl = document.getElementById('confirmReturnModal');
            const btn     = document.getElementById('confirmReturnBtn');

            if (!modalEl || !btn) {
                if (!window.confirm('Confirm the return and restock the resource?')) return;
                performConfirmReturn();
                return;
            }

            // Ensure requestsData is available, fetch if needed
            if (!window.requestsData || !Array.isArray(window.requestsData)) {
                fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.success && d.data && Array.isArray(d.data.requests)) {
                            window.requestsData = d.data.requests;
                        }
                        populateAndShowModal();
                    })
                    .catch(() => { populateAndShowModal(); });
            } else {
                populateAndShowModal();
            }

            function populateAndShowModal() {
                // Fetch fresh data from the API (includes dispatchedItems)
                fetch(`config/get_request_details.php?requestId=${requestId}`)
                    .then(r => r.json())
                    .then(res => {
                        try {
                            const req = (res && res.success && res.data) ? res.data : null;

                            // Fallback to local data for basic fields
                            const localReq = (window.requestsData || []).find(r => String(r.id) === String(requestId));

                            const set = (id, html) => {
                                const el = document.getElementById(id);
                                if (el) { try { el.innerHTML = html; } catch(e) { console.warn('set failed:', id, e); } }
                            };

                            const totalQty = req ? (parseInt(req.quantity) || 0) : (localReq ? parseInt(localReq.quantity) || 0 : 0);

                            set('retModalId',       `REQ-${requestId}`);
                            set('retModalResource', req ? (req.resourceName || '—') : (localReq?.name || '—'));
                            set('retModalBorrower', req ? (req.fromMunicipality || '—') : (localReq?.municipality || '—'));
                            set('retModalQty',      `${totalQty} ${req ? (req.unit || '') : (localReq?.unit || '')}`.trim() || '—');

                            const statusVal = req ? req.status : (localReq?.status || 'return pending');
                            const statusClass = (typeof window.getRequestStatusClass === 'function')
                                ? window.getRequestStatusClass(statusVal)
                                : 'secondary';
                            set('retModalStatus', `<span class="badge bg-${statusClass}">${statusVal}</span>`);

                            // Reset assessment fields
                            const goodEl    = document.getElementById('retGoodQty');
                            const damagedEl = document.getElementById('retDamagedQty');
                            const notesEl   = document.getElementById('retDamageNotes');
                            if (goodEl) {
                                goodEl.value = totalQty;
                                goodEl.max   = totalQty;
                            }
                            if (damagedEl) {
                                damagedEl.value = 0;
                                damagedEl.max   = totalQty;
                            }
                            if (notesEl) notesEl.value = '';

                            // Show per-unit return checklist if dispatched items exist
                            const returnItemsField = document.getElementById('returnItemsField');
                            const returnItemsList  = document.getElementById('returnItemsList');
                            const dispatched = req ? (req.dispatchedItems || []) : [];

                            if (returnItemsField && returnItemsList) {
                                if (dispatched.length > 0) {
                                    returnItemsField.style.display = 'block';
                                    returnItemsList.innerHTML = dispatched.map(item => `
                                        <div class="d-flex align-items-center justify-content-between p-2 rounded border mb-1 bg-white" style="border: 1px solid #e9ecef!important;">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="material-icons text-primary" style="font-size: 15px;">tag</span>
                                                <span class="fw-bold text-dark">${item.uniqueIdentifier}</span>
                                                <span class="text-muted small">${item.storageLocation || ''}</span>
                                            </div>
                                            <select class="form-select form-select-sm return-item-condition" data-item-id="${item.itemID}" style="width: auto; min-width: 170px;">
                                                <option value="Available">✅ Good / Available</option>
                                                <option value="Damaged / Repairing">⚠️ Damaged / Repairing</option>
                                            </select>
                                        </div>
                                    `).join('');

                                    // Auto-calculate good/damaged counts from dropdowns
                                    function syncCountsFromSelects() {
                                        const sels = document.querySelectorAll('#returnItemsList .return-item-condition');
                                        let good = 0, damaged = 0;
                                        sels.forEach(s => {
                                            if (s.value === 'Available') good++;
                                            else damaged++;
                                        });
                                        if (goodEl)    goodEl.value    = good;
                                        if (damagedEl) damagedEl.value = damaged;
                                        bindAssessmentValidation(totalQty);
                                    }
                                    document.querySelectorAll('#returnItemsList .return-item-condition').forEach(s => {
                                        s.addEventListener('change', syncCountsFromSelects);
                                    });
                                    syncCountsFromSelects();
                                } else {
                                    returnItemsField.style.display = 'none';
                                    returnItemsList.innerHTML = '';
                                    // Wire up manual validation
                                    bindAssessmentValidation(totalQty);
                                }
                            } else {
                                bindAssessmentValidation(totalQty);
                            }

                            // Ensure button is ready
                            if (btn) {
                                btn.disabled = false;
                                btn.innerHTML = '<span class="material-icons me-1" style="font-size:15px;vertical-align:middle;">task_alt</span>Confirm Return';
                                btn.dataset.currentRequestId = String(requestId);

                                // Ensure single binding
                                if (!btn.dataset.bound) {
                                    btn.dataset.bound = '1';
                                    btn.addEventListener('click', performConfirmReturn);
                                }
                            }

                            // Show modal
                            if (typeof bootstrap !== 'undefined' && modalEl) {
                                const modal = new bootstrap.Modal(modalEl);
                                modal.show();
                            } else if (modalEl) {
                                modalEl.style.display = 'block';
                                modalEl.classList.add('show');
                                document.body.classList.add('modal-open');
                            }
                        } catch (err) {
                            console.error('Error populating confirm return modal:', err);
                            if (!window.confirm('Confirm the return and restock the resource?')) return;
                            performConfirmReturn();
                        }
                    })
                    .catch(() => {
                        // Fallback: just use local data
                        const localReq = (window.requestsData || []).find(r => String(r.id) === String(requestId));
                        const totalQty = localReq ? parseInt(localReq.quantity) || 0 : 0;
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<span class="material-icons me-1" style="font-size:15px;vertical-align:middle;">task_alt</span>Confirm Return';
                            btn.dataset.currentRequestId = String(requestId);
                            if (!btn.dataset.bound) {
                                btn.dataset.bound = '1';
                                btn.addEventListener('click', performConfirmReturn);
                            }
                        }
                        bindAssessmentValidation(totalQty);
                        if (typeof bootstrap !== 'undefined' && modalEl) {
                            new bootstrap.Modal(modalEl).show();
                        }
                    });
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


