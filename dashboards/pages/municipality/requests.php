<?php
// Requests page with real data
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/db.php';

// Get user's DRRMO ID
$drrmoID = $_SESSION['municipality_id'] ?? null;

// Get current user's DRRMO name
$currentUserDRRMOName = null;
if ($drrmoID) {
    try {
        $sql = "SELECT name FROM drrmo WHERE drrmoID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$drrmoID]);
        $result = $stmt->fetch();
        $currentUserDRRMOName = $result['name'] ?? null;
    } catch (Exception $e) {
        error_log('Error fetching current user DRRMO name: ' . $e->getMessage());
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo "<div class='alert alert-warning'>Please log in to view requests.</div>";
    return;
}

// Fetch real requests data
$requests = [];
$totalRequests = 0;

try {
    // Get requests relevant to current municipality (both outgoing and incoming)
    $sql = "
        SELECT 
            r.requestID,
            r.quantity,
            r.status,
            r.requestDate,
            r.responseDate,
            r.priority,
            r.notes,
            r.requestGroupId,
            r.head_approval_status,
            r.head_approved_by,
            r.approvingAuthority,
            r.approverTitle,
            r.approverSignature,
            from_drrmo.name as fromMunicipality,
            to_drrmo.name as toMunicipality,
            res.resourceName as resourceType,
            res.description,
            res.category,
            res.availableStock,
            res.unit,
            CASE 
                WHEN r.fromDRRMO = ? THEN 'outgoing'
                WHEN r.toDRRMO = ? THEN 'incoming'
                ELSE 'other'
            END as requestType
        FROM requests r
        JOIN drrmo from_drrmo ON r.fromDRRMO = from_drrmo.drrmoID
        JOIN drrmo to_drrmo ON r.toDRRMO = to_drrmo.drrmoID
        JOIN resources res ON r.resourceID = res.resourceID
        WHERE r.fromDRRMO = ? OR r.toDRRMO = ?
        ORDER BY r.requestGroupId IS NULL ASC, r.requestGroupId ASC, r.requestDate DESC
        LIMIT 1000
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$drrmoID, $drrmoID, $drrmoID, $drrmoID]);
    $rawRequests = $stmt->fetchAll();
    
    // Calculate total requests
    $totalRequests = count($rawRequests);
    
    // Transform requests to match JavaScript expectations
    $requests = [];
    foreach ($rawRequests as $request) {
        $isOwnRequest = ($request['fromMunicipality'] === $currentUserDRRMOName);
        $isIncomingRequest = ($request['requestType'] === 'incoming');
        
        // Debug logging
        error_log("Request Debug - fromMunicipality: " . $request['fromMunicipality'] . ", toMunicipality: " . $request['toMunicipality'] . ", requestType: " . $request['requestType'] . ", currentUserDRRMOName: " . $currentUserDRRMOName . ", isOwnRequest: " . ($isOwnRequest ? 'true' : 'false'));
        
        $status = isset($request['status']) && $request['status'] !== '' ? $request['status'] : 'pending';
        // Normalize municipality names (remove DRRMO/CDRRMO/MDRRMO suffixes)
        $strip = function($n) {
            $n = (string)$n;
            $n = preg_replace('/^(?:[A-Z]{0,3}DRRMO\s+)/', '', $n);
            $n = preg_replace('/\s+DRRMO$/', '', $n);
            $n = preg_replace('/^(City of\s+|Municipality of\s+)/i', '', $n);
            $n = preg_replace('/\s+City$/i', '', $n);
            return trim($n);
        };
        $fromName = isset($request['fromMunicipality']) ? $strip($request['fromMunicipality']) : '';
        $toName = isset($request['toMunicipality']) ? $strip($request['toMunicipality']) : '';
        $requests[] = [
            'id' => $request['requestID'],
            'name' => $request['resourceType'],
            'municipality' => $fromName,
            'toMunicipality' => $toName,
            'category' => $request['category'],
            'quantity' => $request['quantity'],
            'unit' => $request['unit'],
            'status' => $status,
            'description' => $request['description'],
            'requestDate' => $request['requestDate'],
            'responseDate' => isset($request['responseDate']) ? $request['responseDate'] : null,
            'priority' => $request['priority'],
            'notes' => $request['notes'],
            'requestGroupId' => $request['requestGroupId'] ?? null,
            'isOwnRequest' => $isOwnRequest,
            'requestType' => $request['requestType'],
            'isIncomingRequest' => $isIncomingRequest,
            'headApprovalStatus' => $request['head_approval_status'] ?? null,
            'headApprovedBy' => $request['head_approved_by'] ?? null,
            'approvingAuthority' => $request['approvingAuthority'] ?? null,
            'approverTitle' => $request['approverTitle'] ?? null,
            'approverSignature' => $request['approverSignature'] ?? null
        ];
    }
} catch (Exception $e) {
    error_log('Error fetching requests: ' . $e->getMessage());
}

// Fetch distinct categories from database for filters
$categoryOptions = [];
try {
    $catStmt = $pdo->query("SELECT DISTINCT category FROM resources WHERE category IS NOT NULL AND category <> '' ORDER BY category");
    $categoryOptions = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log('Error fetching categories: ' . $e->getMessage());
}
?>

<script>
// Pass requests data to JavaScript
window.requestsData = <?php echo json_encode($requests); ?>;
window.totalRequests = <?php echo $totalRequests; ?>;
window.currentUserDRRMOName = <?php echo json_encode($currentUserDRRMOName); ?>;

// Store expanded groups to preserve state during refresh
window.expandedUserRequestGroups = new Set();
window.expandedResourceRequestGroups = new Set();
window.expandedBorrowedRequestGroups = new Set();

document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('requestDetailsModal');
    if (!modalEl) {
        console.error('Modal element not found!');
    }
    
    // Initialize instructional banner (show on first visit unless dismissed)
    initializeInstructionBanner();
    
    // Update tab badges on initial load
    if (typeof updateTabBadges === 'function') {
        updateTabBadges();
    }
});

// Toggle How to Request Guide
function toggleHowToGuide() {
    const content = document.getElementById('howToGuideContent');
    const icon = document.getElementById('howToGuideIcon');
    
    if (content && icon) {
        const isHidden = content.style.display === 'none' || !content.style.display;
        content.style.display = isHidden ? 'block' : 'none';
        icon.textContent = isHidden ? 'expand_less' : 'expand_more';
        icon.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
    }
}

// Dismiss instructional banner
function dismissInstructionBanner() {
    const banner = document.getElementById('resourceRequestBanner');
    if (banner) {
        banner.style.display = 'none';
        // Store dismissal in localStorage
        try {
            localStorage.setItem('resourceRequestBannerDismissed', 'true');
        } catch (e) {
            console.warn('Failed to save banner dismissal state:', e);
        }
    }
}

// Initialize instructional banner (show on first visit)
function initializeInstructionBanner() {
    const banner = document.getElementById('resourceRequestBanner');
    if (!banner) return;
    
    try {
        const dismissed = localStorage.getItem('resourceRequestBannerDismissed');
        if (!dismissed) {
            // Show banner on first visit
            banner.style.display = 'block';
            // Auto-hide after 10 seconds if not manually dismissed
            setTimeout(() => {
                if (banner && banner.style.display !== 'none') {
                    dismissInstructionBanner();
                }
            }, 10000);
        }
    } catch (e) {
        console.warn('Failed to check banner dismissal state:', e);
        // Show banner if we can't check localStorage
        banner.style.display = 'block';
    }
}

</script>

<style>
    /* Scoped styles for Request Details modal - compact, scrollable layout */
    #requestDetailsModal .modal-dialog { max-width: 720px; }
    #requestDetailsModal .modal-content { border-radius: 12px; }
    #requestDetailsModal .modal-header { padding: 12px 16px; border-bottom: 1px solid #e9ecef; }
    #requestDetailsModal .modal-title { font-size: 16px; font-weight: 600; }
    #requestDetailsModal .modal-body { padding: 12px 16px; max-height: calc(100vh - 200px); overflow-y: auto; }
    #requestDetailsModal .modal-footer { padding: 10px 16px; border-top: 1px solid #e9ecef; }

    #requestDetailsModal .rdm-section { background: #fff; border: 1px solid #e9ecef; border-radius: 10px; padding: 10px 12px; margin-bottom: 10px; }
    #requestDetailsModal .rdm-row { display: flex; gap: 10px; align-items: center; justify-content: space-between; }
    #requestDetailsModal .rdm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    #requestDetailsModal .rdm-label { font-size: 11px; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
    #requestDetailsModal .rdm-value { font-size: 14px; font-weight: 600; color: #212529; }
    #requestDetailsModal .rdm-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; }
    #requestDetailsModal .rdm-priority { background: #fff3cd; color: #8a6d3b; }
    #requestDetailsModal .rdm-muted { color: #adb5bd; }
    #requestDetailsModal .rdm-transfer { display: grid; grid-template-columns: 1fr auto 1fr; gap: 8px; align-items: center; }
    #requestDetailsModal .rdm-transfer-box { background: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 10px; }
    #requestDetailsModal .rdm-arrow { font-size: 18px; color: #0d6efd; padding: 0 4px; }
    /* Ensure fits without page scroll on common desktop heights */
    @media (min-height: 640px) {
        #requestDetailsModal .modal-dialog { margin-top: 10px; margin-bottom: 10px; }
    }

    /* Compact Signature Upload Styles */
    .signature-preview img {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
    }

    /* Hide scrollbar in confirm return modal body (WebKit: Chrome, Edge, Safari) */
    #confirmReturnModal .modal-body::-webkit-scrollbar,
    #returnItemsList::-webkit-scrollbar,
    #dispatchItemsList::-webkit-scrollbar {
        display: none;
        width: 0;
    }

    /* Force confirm return modal to be large */
    .confirm-return-dialog,
    #confirmReturnModal .modal-dialog,
    #confirmReturnModal.modal .modal-dialog {
        width: 95vw !important;
        max-width: 95vw !important;
    }

    /* Multi-Resource Selection Styles */
    .resource-row {
        transition: all 0.2s ease-in-out;
        cursor: pointer;
    }

    .resource-row:hover {
        background: #f0f7ff !important;
        transform: translateX(2px);
        box-shadow: 0 2px 8px rgba(33, 150, 243, 0.1);
    }

    .resource-row-selected {
        background-color: #e3f2fd !important;
        border-left: 4px solid #2196f3;
        box-shadow: inset 4px 0 0 #2196f3, 0 2px 4px rgba(33, 150, 243, 0.15);
        transition: all 0.2s ease-in-out;
    }

    .resource-row-selected:hover {
        background-color: #bbdefb !important;
        border-left-color: #1976d2;
    }

    .resource-row-locked {
        background-color: #f5f5f5 !important;
        opacity: 0.5;
        cursor: not-allowed;
    }

    .resource-row-locked td {
        color: #6c757d;
    }

    .resource-checkbox:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }

    .resource-checkbox {
        cursor: pointer;
        width: 18px;
        height: 18px;
        margin: 0;
        transition: all 0.2s ease-in-out;
    }

    .resource-checkbox:hover {
        transform: scale(1.1);
    }

    .resource-checkbox:checked {
        accent-color: #2196f3;
    }

    /* Checkbox column with enhanced header */
    .resources-table th:last-child.select-column-header,
    .resources-table td:last-child {
        min-width: 140px;
        text-align: center;
        padding: 8px 12px;
    }

    /* Enhanced Select Column Header Styling */
    .select-column-header {
        background: linear-gradient(135deg, #667eea 0%, #5a67d8 100%) !important;
        color: white !important;
        font-weight: 600 !important;
        position: relative;
    }

    .select-column-header .fw-bold {
        color: white;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .select-column-header input[type="checkbox"] {
        cursor: pointer;
        filter: brightness(0) invert(1);
    }

    .select-column-header input[type="checkbox"]:hover {
        transform: scale(1.15);
        transition: transform 0.2s ease;
    }

    /* Sticky Action Bar Styles */
    #resourceSelectionActionBar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #ffffff 0%, #f0f7ff 100%);
        border-top: 3px solid #0d6efd;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
        z-index: 1050;
        padding: 16px 20px;
        display: none;
        transform: translateY(100%);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #resourceSelectionActionBar.show {
        display: block;
        transform: translateY(0);
        animation: slideUpActionBar 0.3s ease-out;
    }

    @keyframes slideUpActionBar {
        from {
            transform: translateY(100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Add bottom padding to table container when action bar is visible */
    body.action-bar-visible #available-resources .card-body.p-0 {
        padding-bottom: 75px !important;
    }

    /* Add space to pagination container */
    body.action-bar-visible #resourcesPagination {
        margin-bottom: 15px;
        padding-bottom: 15px;
    }

    /* Ensure the table footer row has space */
    body.action-bar-visible .resources-table tfoot td {
        padding-bottom: 20px;
    }

    #resourceSelectionActionBar .action-bar-content {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    #resourceSelectionActionBar .selection-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    #resourceSelectionActionBar .selection-count {
        font-weight: 600;
        color: #0d6efd;
    }

    #resourceSelectionActionBar .municipality-badge {
        background-color: #e3f2fd;
        color: #0d6efd;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    #resourceSelectionActionBar .action-buttons {
        display: flex;
        gap: 10px;
    }

    #resourceSelectionActionBar #requestSelectedResourcesBtn {
        transition: all 0.2s ease-in-out;
    }

    #resourceSelectionActionBar #requestSelectedResourcesBtn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4) !important;
    }

    #resourceSelectionActionBar #requestSelectedResourcesBtn:active {
        transform: translateY(0);
    }

    /* Instructional Guide Styles */
    #howToRequestGuide {
        border: 1px solid #e0e7ff;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
    }

    #howToRequestGuide .card-header {
        transition: all 0.3s ease;
        user-select: none;
    }

    #howToRequestGuide .card-header:hover {
        background-color: #f8f9fa !important;
    }

    #howToGuideIcon {
        transition: transform 0.3s ease;
        color: #667eea;
    }

    #howToGuideContent {
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Instructional Banner Styles */
    #resourceRequestBanner {
        border-left: 4px solid #0d6efd;
        background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        box-shadow: 0 2px 8px rgba(13, 110, 253, 0.15);
        animation: slideInBanner 0.4s ease-out;
    }

    @keyframes slideInBanner {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    #resourceRequestBanner .material-icons {
        flex-shrink: 0;
    }

</style>

<!-- Request Details Modal (Premium Styled) -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 650px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white;">
                <h6 class="modal-title d-flex align-items-center gap-2" id="requestDetailsLabel">
                    <span class="material-icons" style="font-size: 20px;">info</span>
                    Resource Request Details
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="background: #f8f9fa;">
                <!-- Top Overview Grid -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 10px;">
                            <div class="card-body p-3 text-center">
                                <div class="text-muted small text-uppercase mb-1" style="font-size: 10px; font-weight: 700; letter-spacing: 0.5px;">Request ID</div>
                                <div id="reqModalId" class="fw-bold text-dark fs-5">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 10px;">
                            <div class="card-body p-3 text-center d-flex flex-column align-items-center justify-content-center">
                                <div class="text-muted small text-uppercase mb-1" style="font-size: 10px; font-weight: 700; letter-spacing: 0.5px;">Status</div>
                                <div id="reqModalStatus" class="fw-semibold">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 10px;">
                            <div class="card-body p-3 text-center d-flex flex-column align-items-center justify-content-center">
                                <div class="text-muted small text-uppercase mb-1" style="font-size: 10px; font-weight: 700; letter-spacing: 0.5px;">Priority</div>
                                <div id="reqModalPriority" class="fw-semibold">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transfer Flow (From -> To) -->
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 10px;">
                    <div class="card-body p-3">
                        <div class="text-muted small text-uppercase mb-2" style="font-size: 10px; font-weight: 700; letter-spacing: 0.5px;">Route Flow</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="text-center flex-grow-1 p-2 bg-light rounded" style="max-width: 45%;">
                                <div class="small text-muted" style="font-size: 11px;">Requesting Office</div>
                                <div id="reqModalFrom" class="fw-bold text-truncate" style="font-size: 14px;">—</div>
                            </div>
                            <div class="text-center px-2">
                                <span class="material-icons text-primary" style="font-size: 24px;">arrow_forward</span>
                            </div>
                            <div class="text-center flex-grow-1 p-2 bg-light rounded" style="max-width: 45%;">
                                <div class="small text-muted" style="font-size: 11px;">Provider Office</div>
                                <div id="reqModalTo" class="fw-bold text-truncate" style="font-size: 14px;">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resource Details & Qty -->
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 10px;">
                    <div class="card-body p-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-8">
                                <div class="text-muted small text-uppercase mb-1" style="font-size: 10px; font-weight: 700; letter-spacing: 0.5px;">Requested Item</div>
                                <div id="reqModalResource" class="fw-bold text-dark fs-6" style="line-height: 1.2;">—</div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="text-muted small text-uppercase mb-1" style="font-size: 10px; font-weight: 700; letter-spacing: 0.5px;">Quantity</div>
                                <div id="reqModalQty" class="fw-bold text-primary fs-5">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes / Additional info -->
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 10px;">
                    <div class="card-body p-3">
                        <div class="text-muted small text-uppercase mb-1" style="font-size: 10px; font-weight: 700; letter-spacing: 0.5px;">Additional Notes</div>
                        <div id="reqModalNotes" class="text-dark small" style="white-space: pre-line; min-height: 38px;">—</div>
                    </div>
                </div>

                <!-- Dates Timeline -->
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 10px;">
                    <div class="card-body p-3">
                        <div class="row g-2 text-center">
                            <div class="col-6 border-end">
                                <div class="text-muted small text-uppercase mb-1" style="font-size: 9px; font-weight: 700; letter-spacing: 0.5px;">Submitted On</div>
                                <div id="reqModalRequestDate" class="small fw-semibold text-dark">—</div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small text-uppercase mb-1" style="font-size: 9px; font-weight: 700; letter-spacing: 0.5px;">Approved/Evaluated On</div>
                                <div id="reqModalApproveDate" class="small fw-semibold text-dark">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Damage Assessment Card (shown only for returned requests with damage) -->
                <div class="card border-0 shadow-sm mb-3 d-none" id="reqModalDamageSection" style="border-radius:10px;border-left:4px solid #dc3545!important;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="material-icons text-danger" style="font-size:18px;">warning</span>
                            <strong class="small text-danger">Damage Assessment Report</strong>
                        </div>
                        <div class="row g-2 text-center mb-2">
                            <div class="col-4 border-end">
                                <div class="text-muted small text-uppercase mb-1" style="font-size:9px;font-weight:700;letter-spacing:.5px;">Total Returned</div>
                                <div id="reqModalTotalQty" class="fw-bold text-dark">-</div>
                            </div>
                            <div class="col-4 border-end">
                                <div class="text-muted small text-uppercase mb-1" style="font-size:9px;font-weight:700;letter-spacing:.5px;">Good Condition</div>
                                <div id="reqModalGoodQty" class="fw-bold text-success">-</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small text-uppercase mb-1" style="font-size:9px;font-weight:700;letter-spacing:.5px;">Damaged</div>
                                <div id="reqModalDamagedQty" class="fw-bold text-danger">-</div>
                            </div>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase mb-1" style="font-size:9px;font-weight:700;letter-spacing:.5px;">Assessment Notes</div>
                            <div id="reqModalDamageNotes" class="small text-dark" style="white-space:pre-line;">-</div>
                        </div>
                    </div>
                </div>

                <!-- Dispatched Units Card -->
                <div class="card border-0 shadow-sm mb-3 d-none" id="reqModalDispatchedSection" style="border-radius:10px;border-left:4px solid #0d6efd!important;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="material-icons text-primary" style="font-size:18px;">local_shipping</span>
                            <strong class="small text-primary">Dispatched Units Details</strong>
                        </div>
                        <div id="reqModalDispatchedList" class="small text-dark d-flex flex-column gap-2">
                            <!-- List of dispatched units will be rendered here -->
                        </div>
                    </div>
                </div>

                <!-- Head Authorization & Audit Card -->
                <div class="card border-0 shadow-sm d-none" id="reqModalApprovalMethodSection" style="border-radius: 10px;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="text-muted small text-uppercase" style="font-size: 10px; font-weight: 700; letter-spacing: 0.5px;">Authorization Details</div>
                            <div id="reqModalApprovalBadge"></div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center justify-content-center bg-light rounded-circle text-secondary" style="width: 44px; height: 44px; flex-shrink: 0;">
                                    <span class="material-icons" style="font-size: 22px;">assignment_ind</span>
                                </div>
                                <div>
                                    <span class="text-muted small d-block mb-1" style="font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;" id="reqModalAuthorizedLabel">Authorized By</span>
                                    <span id="reqModalAuthorizedName" class="fw-bold text-dark d-block fs-6">—</span>
                                    <span id="reqModalAuthorizedTitle" class="text-muted small d-block" style="font-size: 11px;">—</span>
                                </div>
                            </div>
                            <div id="reqModalSignatureContainer" class="p-1 bg-white border rounded shadow-xs d-none" style="max-width: 130px; flex-shrink: 0;">
                                <div class="text-muted small text-center" style="font-size: 8px; margin-bottom: 2px;">E-Signature</div>
                                <img id="reqModalSignature" src="" alt="Digital Signature" style="max-height: 44px; max-width: 120px; object-fit: contain; display: block;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-3 px-4 bg-light border-top d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-warning btn-sm d-flex align-items-center gap-1 px-3 py-2 fw-semibold" id="generateDocBtn" onclick="generateDocumentForRequest()" style="border-radius: 8px;">
                    <span class="material-icons" style="font-size: 16px;">description</span>
                    Generate Document
                </button>
                <button type="button" class="btn btn-secondary btn-sm px-3 py-2 fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Accept/Reject Confirmation Modal -->
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="confirmActionLabel">Confirm Action</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <span class="material-icons text-warning" style="font-size: 48px;">warning</span>
                </div>
                <p class="text-center mb-3" id="confirmActionMessage">Are you sure you want to perform this action?</p>
                
                <!-- Rejection reason field (hidden by default) -->
                <div id="rejectionReasonField" style="display: none;" class="mb-3">
                    <label for="rejectionReason" class="form-label">
                        <span class="material-icons me-1" style="font-size: 18px;">comment</span>
                        Reason for rejection (optional)
                    </label>
                    <textarea 
                        class="form-control" 
                        id="rejectionReason" 
                        rows="3" 
                        placeholder="Please provide a reason for rejecting this request..."
                        maxlength="500"
                    ></textarea>
                    <div class="form-text">
                        <small class="text-muted">This reason will be included in the notification sent to the requesting municipality.</small>
                    </div>
                </div>
                
                <!-- Dispatch items field (hidden by default) -->
                <div id="dispatchItemsField" style="display: none;" class="mb-3">
                    <label class="form-label d-flex align-items-center gap-1 mb-1 fw-semibold">
                        <span class="material-icons text-primary" style="font-size: 18px;">fact_check</span>
                        Select Units to Dispatch
                    </label>
                    <p class="small text-muted mb-2">Please select exactly <strong class="text-primary" id="dispatchItemsQty">0</strong> unit(s) to dispatch for this request.</p>
                    <div id="dispatchItemsList" class="border rounded p-2 bg-white" style="max-height: 200px; overflow-y: auto; border: 1px solid #e9ecef!important;">
                        <!-- Checkboxes will be populated dynamically -->
                    </div>
                    <div id="dispatchItemsValidation" class="form-text mt-1 text-danger small" style="display: none;"></div>
                </div>
                
                <div class="alert alert-info">
                    <small>
                        <strong>Note:</strong> This action will notify the requesting municipality and cannot be undone.
                    </small>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="confirmActionBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Return Modal -->
<div class="modal fade" id="confirmReturnModal" tabindex="-1" aria-labelledby="confirmReturnLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable confirm-return-dialog" style="width:95vw!important;max-width:95vw!important;">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;">
            <div class="modal-header py-3 px-4" style="background:linear-gradient(135deg,#1e3c72 0%,#2a5298 100%);color:white;">
                <h6 class="modal-title d-flex align-items-center gap-2" id="confirmReturnLabel">
                    <span class="material-icons" style="font-size:20px;">assignment_return</span>
                    Confirm Return &amp; Assessment
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="background:#f8f9fa; overflow-y:auto; scrollbar-width:none; -ms-overflow-style:none;">
                <!-- Summary Cards -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="card border-0 shadow-sm h-100" style="border-radius:10px;">
                            <div class="card-body p-3 text-center">
                                <div class="text-muted small text-uppercase mb-1" style="font-size:10px;font-weight:700;letter-spacing:.5px;">Request ID</div>
                                <div id="retModalId" class="fw-bold text-dark">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card border-0 shadow-sm h-100" style="border-radius:10px;">
                            <div class="card-body p-3 text-center">
                                <div class="text-muted small text-uppercase mb-1" style="font-size:10px;font-weight:700;letter-spacing:.5px;">Status</div>
                                <div id="retModalStatus" class="fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm" style="border-radius:10px;">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-7">
                                        <div class="text-muted small text-uppercase mb-1" style="font-size:10px;font-weight:700;letter-spacing:.5px;">Resource</div>
                                        <div id="retModalResource" class="fw-bold text-dark">-</div>
                                    </div>
                                    <div class="col-5 text-end">
                                        <div class="text-muted small text-uppercase mb-1" style="font-size:10px;font-weight:700;letter-spacing:.5px;">Returned Qty</div>
                                        <div id="retModalQty" class="fw-bold text-primary fs-5">-</div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <div class="text-muted small text-uppercase mb-1" style="font-size:10px;font-weight:700;letter-spacing:.5px;">Borrower</div>
                                    <div id="retModalBorrower" class="fw-semibold">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Damage Assessment Section -->
                <div class="card border-0 shadow-sm mb-3" style="border-radius:10px;border-left:4px solid #fd7e14!important;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="material-icons text-warning" style="font-size:18px;">manage_search</span>
                            <strong class="small">Damage Assessment</strong>
                            <span class="badge bg-warning text-dark ms-auto" style="font-size:10px;">REQUIRED</span>
                        </div>
                        <p class="small text-muted mb-3">Inspect the returned items. Items in <strong class="text-success">Good Condition</strong> are restocked to available inventory. <strong class="text-danger">Damaged</strong> items are flagged and held for review &mdash; not restocked.</p>
                        
                        <!-- Itemized return checklist (shown only if dispatched items present) -->
                        <div id="returnItemsField" style="display: none;" class="mb-3">
                            <label class="form-label small fw-semibold mb-1 d-flex align-items-center gap-1">
                                <span class="material-icons text-primary" style="font-size: 16px;">fact_check</span>
                                Unit Check-in Checklist
                            </label>
                            <div id="returnItemsList" class="border rounded p-2 bg-white" style="max-height: 180px; overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none; border: 1px solid #e9ecef!important;">
                                <!-- List of itemized units with select dropdowns or toggles -->
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label for="retGoodQty" class="form-label small fw-semibold text-success d-flex align-items-center gap-1 mb-1">
                                    <span class="material-icons" style="font-size:15px;">check_circle</span> Good Condition
                                </label>
                                <input type="number" id="retGoodQty" class="form-control form-control-sm" min="0" max="0" value="0">
                                <div class="form-text" style="font-size:10px;">Goes back to available stock</div>
                            </div>
                            <div class="col-6">
                                <label for="retDamagedQty" class="form-label small fw-semibold text-danger d-flex align-items-center gap-1 mb-1">
                                    <span class="material-icons" style="font-size:15px;">warning</span> Damaged
                                </label>
                                <input type="number" id="retDamagedQty" class="form-control form-control-sm" min="0" max="0" value="0">
                                <div class="form-text" style="font-size:10px;">Flagged &mdash; NOT restocked</div>
                            </div>
                        </div>
                        <div id="retQtyValidation" class="mt-2 small" style="display:none;"></div>
                        <div class="mt-3">
                            <label for="retDamageNotes" class="form-label small fw-semibold mb-1">Damage Notes <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea id="retDamageNotes" class="form-control form-control-sm" rows="2" placeholder="Describe the condition or damage observed..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="small text-muted d-flex align-items-center gap-1">
                    <span class="material-icons" style="font-size:14px;">info</span>
                    Good + Damaged must equal the total returned quantity shown above.
                </div>
            </div>
            <div class="modal-footer py-2 px-4">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="confirmReturnBtn">
                    <span class="material-icons me-1" style="font-size:15px;vertical-align:middle;">task_alt</span>Confirm Return
                </button>
            </div>
        </div>
    </div>
    </div>

<div class="requests-page">

    <!-- Request Status Tabs -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs" id="requestTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="available-resources-tab" data-bs-toggle="tab" data-bs-target="#available-resources" type="button" role="tab">
                                <span class="material-icons me-2">inventory_2</span>
                                Available Resources
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="who-requested-tab" data-bs-toggle="tab" data-bs-target="#who-requested" type="button" role="tab">
                                <span class="material-icons me-2">people</span>
                                Resource Requests
                                <span class="badge rounded-pill bg-warning text-dark ms-2" id="resourceRequestsCount" style="display: none; font-size: 10px; vertical-align: middle;"></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="your-requests-tab" data-bs-toggle="tab" data-bs-target="#your-requests" type="button" role="tab">
                                <span class="material-icons me-2">assignment</span>
                                My Requests
                                <span class="badge rounded-pill bg-primary ms-2" id="myRequestsCount" style="display: none; font-size: 10px; vertical-align: middle;"></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="borrowed-resources-tab" data-bs-toggle="tab" data-bs-target="#borrowed-resources" type="button" role="tab">
                                <span class="material-icons me-2">assignment_return</span>
                                Borrowed Resources
                                <span class="badge rounded-pill bg-success ms-2" id="borrowedResourcesCount" style="display: none; font-size: 10px; vertical-align: middle;"></span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="requestTabsContent">
        <!-- Available Resources Tab -->
        <div class="tab-pane fade show active" id="available-resources" role="tabpanel">
            <!-- Resource Search and Filter Controls -->
            <div class="row resource-search-filters mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <span class="material-icons me-2">filter_list</span>
                                Search & Filter Resources
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="resourceSearch" class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <span class="material-icons">search</span>
                                        </span>
                                        <input 
                                            type="text" 
                                            id="resourceSearch" 
                                            class="form-control" 
                                            placeholder="Search by resource name or municipality..."
                                            onkeyup="filterResources()"
                                        >
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="categoryFilter" class="form-label">Category</label>
                                    <select id="categoryFilter" class="form-select" onchange="filterResources()">
                                        <option value="All">All Categories</option>
<?php foreach (($categoryOptions ?? []) as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
<?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="statusFilter" class="form-label">Status</label>
                                    <select id="statusFilter" class="form-select" onchange="filterResources()">
                                        <option value="All">All Status</option>
                                        <option value="Available">Available</option>
                                        <option value="Unavailable">Unavailable</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-muted">
                                            <span id="filterResultsCount">Use filters to search resources</span>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                                                <span class="material-icons me-1" style="font-size: 16px;">clear</span>
                                                Clear Filters
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instructional Guide and Banner -->
            <div class="row mb-3">
                <div class="col-12">
                    <!-- Collapsible How to Request Guide -->
                    <div class="card mb-3" id="howToRequestGuide">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="toggleHowToGuide()">
                            <h5 class="mb-0 d-flex align-items-center">
                                <span class="material-icons me-2" style="font-size: 24px; color: #667eea;">help_outline</span>
                                How to Request Resources
                            </h5>
                            <span class="material-icons" id="howToGuideIcon">expand_more</span>
                        </div>
                        <div class="card-body" id="howToGuideContent" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; min-width: 32px; font-weight: bold; margin-right: 12px;">1</div>
                                        <div>
                                            <h6 class="mb-1">Select Resources</h6>
                                            <p class="text-muted mb-0 small">Check the boxes in the "SELECT TO REQUEST" column to choose resources you need. You can click anywhere on a row to select it.</p>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; min-width: 32px; font-weight: bold; margin-right: 12px;">2</div>
                                        <div>
                                            <h6 class="mb-1">Review Selection</h6>
                                            <p class="text-muted mb-0 small">A blue action bar will appear at the bottom showing how many resources you've selected. All resources must be from the same municipality.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; min-width: 32px; font-weight: bold; margin-right: 12px;">3</div>
                                        <div>
                                            <h6 class="mb-1">Submit Request</h6>
                                            <p class="text-muted mb-0 small">Click the "Request Selected" button in the blue action bar at the bottom of the page to submit your request.</p>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; min-width: 32px; font-weight: bold; margin-right: 12px;">4</div>
                                        <div>
                                            <h6 class="mb-1">Fill Details</h6>
                                            <p class="text-muted mb-0 small">Complete the request form with quantity needed, priority, and other required information, then submit.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dismissible Instructional Banner -->
                    <div class="alert alert-info alert-dismissible fade show" role="alert" id="resourceRequestBanner" style="display: none;">
                        <div class="d-flex align-items-start">
                            <span class="material-icons me-3" style="font-size: 28px; color: #0d6efd;">info</span>
                            <div class="flex-grow-1">
                                <h6 class="alert-heading mb-2"><strong>How to Request Resources</strong></h6>
                                <p class="mb-1">
                                    <strong>Step 1:</strong> Check the boxes in the "SELECT TO REQUEST" column to select resources you need. 
                                    <strong>Step 2:</strong> A blue action bar will appear at the bottom of the page. 
                                    <strong>Step 3:</strong> Click "Request Selected" to submit your request.
                                </p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="dismissInstructionBanner()"></button>
                    </div>
                </div>
            </div>

            <!-- Resources Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0 resources-table">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Resources</th>
                                            <th class="text-center">Municipality</th>
                                            <th class="text-center">Category</th>
                                            <th class="text-center">Quantity</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center select-column-header" style="min-width: 140px; padding: 8px 12px;">
                                                <div class="d-flex flex-column align-items-center justify-content-center">
                                                    <div class="fw-bold mb-1" style="font-size: 0.85rem; line-height: 1.2;">☑ SELECT TO REQUEST</div>
                                                    <input type="checkbox" id="selectAllResources" title="Select all resources from the same municipality" style="width: 18px; height: 18px; margin: 0; cursor: pointer;">
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="availableResourcesTableBody">
                                        <!-- Data will be loaded dynamically from database -->
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="6">
                                                <div id="resourcesPagination" class="d-flex justify-content-center"></div>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Who Requested Tab -->
        <div class="tab-pane fade" id="who-requested" role="tabpanel">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="px-3 pt-3 pb-2">
                                <ul class="nav nav-tabs" id="resourceRequestsInnerTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="resource-requests-active-tab" data-bs-toggle="tab" data-bs-target="#who-requested-active" type="button" role="tab">
                                            Active
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="resource-requests-history-tab" data-bs-toggle="tab" data-bs-target="#who-requested-history" type="button" role="tab" title="Returned and rejected requests from other municipalities">
                                            History
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <div class="tab-content px-3 pb-3" id="resourceRequestsInnerTabsContent">
                                <div class="tab-pane fade show active" id="who-requested-active" role="tabpanel">
                                    <div class="request-table-container">
                                        <table class="request-table">
                                            <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Resource</th>
                                            <th>From → To</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Request Date</th>
                                            <th>Actions</th>
                                        </tr>
                                            </thead>
                                            <tbody id="resourceRequestsActiveBody">
                                                <!-- Active requests rendered here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="who-requested-history" role="tabpanel">
                                    <p class="text-muted small mb-2">History of resource requests: returned and rejected requests from other municipalities.</p>
                                    <div class="request-table-container">
                                        <table class="request-table">
                                            <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Resource</th>
                                            <th>From → To</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Request Date</th>
                                            <th>Actions</th>
                                        </tr>
                                            </thead>
                                            <tbody id="resourceRequestsHistoryBody">
                                                <!-- Historical requests rendered here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <nav aria-label="Requests pagination">
                                    <ul id="requestsPagination" class="pagination pagination-sm mb-0"></ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Your Request List Tab -->
        <div class="tab-pane fade" id="your-requests" role="tabpanel">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="d-flex justify-content-end align-items-center px-3 pt-3 pb-2">
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input" type="checkbox" value="1" id="myRequestsShowHistory">
                                    <label class="form-check-label" for="myRequestsShowHistory" style="font-size: 12px;">
                                        Show history (Received/Returned)
                                    </label>
                                </div>
                            </div>
                            <div class="request-table-container">
                                <table class="request-table">
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Resource</th>
                                            <th>To Municipality</th>
                                            <th>Quantity</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Request Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="yourRequestsTableBody">
                                        <!-- Data will be loaded dynamically from database -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Borrowed Resources Tab -->
        <div class="tab-pane fade" id="borrowed-resources" role="tabpanel">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="d-flex justify-content-end align-items-center px-3 pt-3 pb-2">
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input" type="checkbox" value="1" id="borrowedShowHistory">
                                    <label class="form-check-label" for="borrowedShowHistory" style="font-size: 12px;">
                                        Show history (Returned)
                                    </label>
                                </div>
                            </div>
                            <div class="request-table-container">
                                <table class="request-table">
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Resource</th>
                                            <th>Provider</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Request Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="borrowedRequestsTableBody">
                                        <!-- Rendered via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Request Resource Modal -->
<div class="modal fade" id="requestResourceModal" tabindex="-1" aria-labelledby="requestResourceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="width: 60vw !important; max-width: 60vw !important; margin: 0 auto !important;">
        <div class="modal-content" style="height: 100%; display: flex; flex-direction: column;">
            <div class="modal-header bg-primary text-white" style="flex-shrink: 0;">
                <div class="modal-title d-flex align-items-center">
                    <span class="material-icons me-2">inventory</span>
                    <h4 class="mb-0" id="requestResourceModalLabel">Request Resource</h4>
            </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
            <div class="modal-body p-4" style="flex: 1; overflow-y: auto; overflow-x: hidden;">
            <form id="requestResourceForm" onsubmit="event.preventDefault(); if(typeof window.submitRequest==='function'){window.submitRequest();} return false;">
                <!-- Single Resource Section (default, for backward compatibility) -->
                <div id="singleResourceSection" class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 d-flex align-items-center">
                            <span class="material-icons me-2 text-primary">inventory_2</span>
                            Resource Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Resource</label>
                                <div class="fw-bold" id="resourceNameDisplay">—</div>
                                <input type="hidden" id="resourceName">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Available Stock</label>
                                <div class="fw-bold" id="availableQuantityDisplay">—</div>
                                <input type="hidden" id="availableQuantity">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small">From Municipality</label>
                                <div class="fw-bold" id="providerMunicipalityDisplay">—</div>
                                <input type="hidden" id="providerMunicipality">
                                <input type="hidden" id="requestingMunicipality">
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label for="requestQuantity" class="form-label fw-bold">
                                    Quantity Needed <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control" id="requestQuantity" min="1" required placeholder="Enter quantity">
                                <small class="text-muted" id="maxQuantityHint"></small>
                            </div>
                            <div class="col-md-6">
                                <label for="requestPriority" class="form-label fw-bold">
                                    Priority Level <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="requestPriority" required>
                                    <option value="Low">🟢 Low Priority</option>
                                    <option value="Medium" selected>🟡 Medium Priority</option>
                                    <option value="High">🟠 High Priority</option>
                                    <option value="Critical">🔴 Critical Priority</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Multiple Resources Section (hidden by default) -->
                <div id="multipleResourcesSection" class="card mb-4" style="display: none;">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 d-flex align-items-center">
                            <span class="material-icons me-2 text-primary">inventory_2</span>
                            Selected Resources
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label class="form-label text-muted small">From Municipality</label>
                                <div class="fw-bold" id="providerMunicipalityDisplayMulti">—</div>
                                <input type="hidden" id="providerMunicipality">
                                <input type="hidden" id="requestingMunicipality">
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Resource Name</th>
                                        <th>Available Stock</th>
                                        <th style="width: 150px;">Quantity <span class="text-danger">*</span></th>
                                        <th style="width: 150px;">Priority <span class="text-danger">*</span></th>
                                    </tr>
                                </thead>
                                <tbody id="multipleResourcesTableBody">
                                    <!-- Populated dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Request Details Section -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 d-flex align-items-center">
                            <span class="material-icons me-2 text-primary">edit</span>
                            Request Details
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="deliveryDate" class="form-label fw-bold">
                                    Delivery Date & Time <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local" class="form-control" id="deliveryDate" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    Expected Duration <span class="text-danger">*</span>
                                </label>
                                <div class="d-flex gap-2">
                                    <input type="number" class="form-control" id="expectedDurationNumber" min="0" max="9" placeholder="0-9" required style="max-width: 100px;">
                                    <select class="form-select" id="expectedDurationUnit" required>
                                        <option value="">Select Unit</option>
                                        <option value="days">days</option>
                                        <option value="weeks">weeks</option>
                                        <option value="months">months</option>
                                    </select>
                                </div>
                                <input type="hidden" id="expectedDuration" value="">
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label for="returnDateRange" class="form-label fw-bold">
                                    Expected Return Date
                                </label>
                                <input type="text" class="form-control" id="returnDateRange" readonly style="background-color: #f8f9fa;" placeholder="Will be calculated...">
                                <input type="hidden" id="returnDate" value="">
                                <small class="text-muted">Auto-calculated from delivery date and duration</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Delivery Mode</label>
                                <div class="form-control" style="background-color: #f8f9fa;">
                                    Delivery (Please deliver)
                                </div>
                                <input type="hidden" id="transportationMethod" value="Delivery">
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label for="deliveryLocation" class="form-label fw-bold">
                                Delivery Location/Address <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="deliveryLocation" rows="2" placeholder="Enter complete delivery address including landmarks..." required></textarea>
                        </div>
                        
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <label for="contactPhone" class="form-label fw-bold">
                                    Contact Phone Number <span class="text-danger">*</span>
                                </label>
                                <input type="tel" class="form-control" id="contactPhone" required placeholder="e.g., 09123456789" maxlength="11" pattern="[0-9]{11}" title="Please enter exactly 11 digits">
                                <small class="text-muted">11 digits only (e.g., 09123456789)</small>
                            </div>
                            <div class="col-md-6">
                                <label for="contactEmail" class="form-label fw-bold">
                                    Contact Email <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" id="contactEmail" required placeholder="e.g., contact@municipality.gov.ph">
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label for="purposeOfRequest" class="form-label fw-bold">
                                Purpose of Request <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="purposeOfRequest" required onchange="toggleOtherPurpose()">
                                <option value="">Select Purpose</option>
                                <option value="Emergency Response">Emergency Response</option>
                                <option value="Disaster Relief">Disaster Relief</option>
                                <option value="Training Exercise">Training Exercise</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Preparedness">Preparedness</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mt-3" id="otherPurposeContainer" style="display: none;">
                            <label for="otherPurpose" class="form-label fw-bold">
                                Please specify <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="otherPurpose" placeholder="Describe the purpose of your request">
                        </div>
                    </div>
                </div>

                <!-- Additional Details (Always Visible) -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 d-flex align-items-center justify-content-between">
                            <span class="d-flex align-items-center">
                                <span class="material-icons me-2 text-primary">person</span>
                                Additional Details
                            </span>
                            <span class="badge bg-success">Pre-filled</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <!-- Requestor Information -->
                        <div class="mb-4">
                            <h6 class="mb-3">Requestor Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="requestorName" class="form-label">Requestor Name</label>
                                    <input type="text" class="form-control" id="requestorName" readonly style="background-color: #f8f9fa;">
                                </div>
                                <div class="col-md-6">
                                    <label for="requestorTitle" class="form-label">Title/Position</label>
                                    <input type="text" class="form-control" id="requestorTitle" placeholder="e.g., Operation &amp; Warning Division Chief">
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="form-label">E-Signature</label>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="requestorSignatureBtn">
                                    <span class="material-icons me-1" style="font-size: 16px;">draw</span>
                                    Upload E-Signature
                                </button>
                                <input type="file" id="requestorSignatureFile" accept="image/*" style="display: none;">
                                <div id="requestorSignaturePreview" class="mt-2" style="display: none;">
                                    <img id="requestorSignatureImg" class="img-fluid border rounded" style="max-height: 50px;">
                                    <button type="button" class="btn btn-sm btn-danger ms-2" onclick="clearSignature('requestor')">Remove</button>
                                </div>
                            </div>
                        </div>

                        <!-- Authorization -->
                        <div class="mb-0">
                            <h6 class="mb-3">Authorization &amp; Approval</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="approvingAuthority" class="form-label">Approving Authority <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="approvingAuthority" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="approverTitle" class="form-label">Title/Position</label>
                                    <input type="text" class="form-control" id="approverTitle" placeholder="e.g., MGDH/(LDRRMO)">
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="form-label">Approver E-Signature</label>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="approverSignatureBtn">
                                    <span class="material-icons me-1" style="font-size: 16px;">draw</span>
                                    Upload E-Signature
                                </button>
                                <input type="file" id="approverSignatureFile" accept="image/*" style="display: none;">
                                <div id="approverSignaturePreview" class="mt-2" style="display: none;">
                                    <img id="approverSignatureImg" class="img-fluid border rounded" style="max-height: 50px;">
                                    <button type="button" class="btn btn-sm btn-danger ms-2" onclick="clearSignature('approver')">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <span class="material-icons me-1">cancel</span>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="submitRequestBtn" onclick="console.log('Button clicked directly'); if(typeof window.submitRequest==='function'){console.log('Calling submitRequest'); window.submitRequest();}else{console.error('submitRequest function not found on window object'); alert('Submit function not found. Please refresh the page.');} return false;">
                        <span class="material-icons me-1">send</span>
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
            </form>
        </div>
            
        </div>
    </div>
</div>


<!-- Delete Request Confirm Modal -->
<div class="modal fade" id="deleteRequestConfirmModal" tabindex="-1" aria-labelledby="deleteRequestConfirmLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title d-flex align-items-center" id="deleteRequestConfirmLabel">
          <span class="material-icons text-danger me-2">warning</span>
          Delete Request
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="deleteRequestConfirmText" class="mb-0">Are you sure you want to delete this request?</p>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteRequestBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- Bypass Approval Confirm Modal -->
<div class="modal fade" id="bypassApprovalConfirmModal" tabindex="-1" aria-labelledby="bypassApprovalConfirmLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title d-flex align-items-center" id="bypassApprovalConfirmLabel">
          <span class="material-icons text-warning me-2">fast_forward</span>
          Bypass Head Approval
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Are you sure you want to bypass the Head's approval and forward this request directly to the provider?</p>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning btn-sm" id="confirmBypassApprovalBtn">Bypass & Forward</button>
      </div>
    </div>
  </div>
</div>

<!-- Multiple Resources Confirmation Modal -->
<div class="modal fade" id="confirmMultipleResourcesModal" tabindex="-1" aria-labelledby="confirmMultipleResourcesLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title d-flex align-items-center" id="confirmMultipleResourcesLabel">
          <span class="material-icons me-2">check_circle</span>
          Confirm Resource Requests
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Please review the resources you are about to request:</p>
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Resource Name</th>
                <th>Quantity</th>
                <th>Priority</th>
              </tr>
            </thead>
            <tbody id="confirmResourcesList">
              <!-- Populated dynamically -->
            </tbody>
          </table>
        </div>
        <div class="alert alert-info mt-3 mb-0">
          <strong>Note:</strong> All resources will be grouped together and a single formal letter will be generated listing all resources.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmSubmitMultipleResourcesBtn">
          <span class="material-icons me-1" style="font-size: 18px; vertical-align: middle;">send</span>
          Confirm & Submit
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Sticky Action Bar for Multi-Resource Selection -->
<div id="resourceSelectionActionBar" class="action-bar">
    <div class="action-bar-content">
        <div class="selection-info">
            <span class="material-icons" style="color: #0d6efd;">inventory</span>
            <span class="selection-count" id="selectedResourcesCount">0</span>
            <span>resources selected from</span>
            <span class="municipality-badge" id="selectedMunicipalityName">—</span>
        </div>
        <div class="action-buttons">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearResourceSelectionBtn">
                Clear Selection
            </button>
            <button type="button" class="btn btn-primary btn-sm fw-bold" id="requestSelectedResourcesBtn" style="min-width: 180px; box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);">
                <span class="material-icons me-1" style="font-size: 18px; vertical-align: middle;">send</span>
                Request Selected (<span id="requestSelectedCount">0</span>)
            </button>
        </div>
    </div>
</div>
<!-- requests.js is already included globally by municipality.php -->

<style>
/* Modal Override Styles - AGGRESSIVE OVERRIDE */
.modal-dialog {
    width: 60vw !important;
    max-width: 60vw !important;
    margin: 0 auto !important;
}

#requestResourceModal .modal-dialog {
    width: 60vw !important;
    max-width: 60vw !important;
    margin: 0 auto !important;
}

/* Force modal width */
.modal.show .modal-dialog {
    width: 60vw !important;
    max-width: 60vw !important;
}

#requestResourceModal .modal-content {
    height: auto !important;
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;           /* ensure content spans dialog width */
    max-width: none !important;       /* override global 500px limit */
    max-height: 92vh !important;      /* further reduced to avoid outer scrollbar */
}

#requestResourceModal .modal-body {
    flex: 1 1 auto !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
}

/* Hide modal body scrollbar but keep scrolling */
#requestResourceModal .modal-body::-webkit-scrollbar { display: none; }
#requestResourceModal .modal-body { scrollbar-width: none; -ms-overflow-style: none; }

/* Explicitly override any global modal content constraints for this modal */
#requestResourceModal .modal-dialog .modal-content,
#requestResourceModal.modal .modal-content {
    width: 100% !important;
    max-width: none !important;
}

#requestResourceModal .modal-header,
#requestResourceModal .modal-footer {
    flex-shrink: 0 !important;
}

/* Reduce header bottom padding and center title */
#requestResourceModal .modal-header {
    padding-bottom: 8px !important;
}
#requestResourceModal .modal-header .modal-title {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

/* Make section headers centered with white text */
#requestResourceModal .card-header {
    background-color: #0d6efd !important;
    color: #fff !important;
    text-align: center !important;
}
#requestResourceModal .card-header h5 {
    color: #fff !important;
    margin-bottom: 0 !important;
}

/* Auto-filled summary styling */
#requestResourceModal .alert-light {
    border-left: 4px solid #6c757d;
}

/* Collapse icon animation */
#requestResourceModal .card-header[data-bs-toggle="collapse"] .material-icons {
    transition: transform 0.3s ease;
}
#requestResourceModal .card-header[data-bs-toggle="collapse"][aria-expanded="true"] .material-icons {
    transform: rotate(90deg);
}

/* Read-only field styling */
#requestResourceModal input[readonly] {
    background-color: #f8f9fa !important;
    cursor: not-allowed;
}

/* Required field indicator */
#requestResourceModal .text-danger {
    font-size: 0.875rem;
}

/* Larger form controls for main fields */
#requestResourceModal .form-control-lg,
#requestResourceModal .form-select-lg {
    font-size: 1rem;
    padding: 0.625rem 0.75rem;
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

/* Tab content visibility */
.tab-content .tab-pane {
    display: none;
}

.tab-content .tab-pane.active {
    display: block;
}

.tab-content .tab-pane.show {
    display: block;
}

.tab-content .tab-pane.active.show {
    display: block;
}

/* Ensure the first tab is always visible and active */
#available-resources-tab.nav-link.active {
    color: #007bff;
    background-color: transparent;
    border-color: transparent;
    border-bottom-color: #007bff;
    font-weight: 600;
}

/* Ensure proper tab styling */
.nav-tabs .nav-link {
    border: 1px solid transparent;
    border-top-left-radius: 0.375rem;
    border-top-right-radius: 0.375rem;
}

.nav-tabs .nav-link:hover {
    border-color: #e9ecef #e9ecef #dee2e6;
}

.nav-tabs .nav-link.active {
    color: #495057;
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
}

/* Ensure footer pagination is visible and not overlapped */
.resources-pagination-bar {
    position: sticky;
    bottom: 0;
    z-index: 1;
    padding: 8px 12px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
}
.resources-pagination-bar .pagination .page-link {
    min-width: 34px;
    text-align: center;
}
.resources-pagination-bar nav {
    display: inline-block;
}

/* Ensure the pagination bar isn't clipped by the table wrapper */
.requests-page .table-responsive {
    overflow-y: visible;
}
</style>

<script>
// Initialize the requests page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing municipality requests page...');
    
    // Check if we're on the requests page and should show Resource Requests tab by default
    // Always default to Available Resources tab when clicking from sidebar
    let defaultTabId = 'available-resources-tab';
    
    // Try to restore from localStorage first (user's manual tab selection)
    try {
        const savedTabId = localStorage.getItem('municipalityRequests.activeTab');
        if (savedTabId && document.getElementById(savedTabId)) {
            defaultTabId = savedTabId;
        }
    } catch(_) {}
    
    // Only use URL parameter if no saved tab is found in localStorage
    // This ensures that after a notification brings user to a tab, 
    // and they manually switch to another tab, they stay on that tab
    if (!localStorage.getItem('municipalityRequests.activeTab')) {
        const urlParams = new URLSearchParams(window.location.search);
        const requestedTab = urlParams.get('tab');
        
        if (requestedTab === 'available-resources' || requestedTab === 'who-requested' || requestedTab === 'your-requests' || requestedTab === 'borrowed-resources') {
            // Map URL parameter to tab ID
            const tabMap = {
                'available-resources': 'available-resources-tab',
                'who-requested': 'who-requested-tab',
                'your-requests': 'your-requests-tab',
                'borrowed-resources': 'borrowed-resources-tab'
            };
            defaultTabId = tabMap[requestedTab] || 'available-resources-tab';
        }
    }
    
    // First, ensure the correct default tab is active
    const firstTab = document.getElementById(defaultTabId);
    const targetPaneSelector = firstTab ? firstTab.getAttribute('data-bs-target') : '#available-resources';
    const firstTabPane = document.querySelector(targetPaneSelector) || document.getElementById('available-resources');
    
    if (firstTab && firstTabPane) {
        console.log('Setting up', defaultTabId, 'tab as active...');
        
        // Remove active class from all tabs and panes
        document.querySelectorAll('#requestTabs .nav-link').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('active', 'show');
            pane.style.display = 'none';
        });
        
        // Add active class to first tab and pane
        firstTab.classList.add('active');
        firstTabPane.classList.add('active', 'show');
        firstTabPane.style.display = 'block';
        
        console.log('Available Resources tab is now active');
        
        // Load data for Available Resources tab if that's the default
        if (firstTab.id === 'available-resources-tab' && typeof loadAvailableResources === 'function') {
            loadAvailableResources();
        }
    }
    
    // Initialize Bootstrap tabs with proper visibility control
    const triggerTabList = [].slice.call(document.querySelectorAll('#requestTabs button'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            
            // Hide all tab panes first
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active', 'show');
                pane.style.display = 'none';
            });
            
            // Remove active from all tabs
            document.querySelectorAll('#requestTabs .nav-link').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show the clicked tab
            tabTrigger.show();
            
            // Force the target pane to be visible
            const targetPane = document.querySelector(triggerEl.getAttribute('data-bs-target'));
            if (targetPane) {
                targetPane.classList.add('active', 'show');
                targetPane.style.display = 'block';
            }
            
            // Add active to clicked tab
            triggerEl.classList.add('active');
            
            console.log('Tab clicked:', triggerEl.id, 'Target:', triggerEl.getAttribute('data-bs-target'));
            
            // Persist active tab id
            try { 
                localStorage.setItem('municipalityRequests.activeTab', triggerEl.id);
                // Also persist the active tab in a variable for immediate use
                window.currentActiveTab = triggerEl.id;
                
                // Update URL to remove tab parameter to avoid redirect on refresh
                const url = new URL(window.location.href);
                if (url.searchParams.has('tab')) {
                    url.searchParams.delete('tab');
                    // Don't trigger a page reload, just update the URL in browser history
                    window.history.replaceState({}, document.title, url.toString());
                }
            } catch(_) {}
            // Restart auto refresh when changing tabs
            try { if (typeof startAutoRefresh === 'function') startAutoRefresh(); } catch(_) {}
        });
    });
    
    // Initialize page helper if available (guarded)
    try {
        if (typeof RequestsPage !== 'undefined') {
            window.requestsPage = new RequestsPage();
        }
    } catch(_) {}
    
    // Add event listeners for filters
    const searchInput = document.getElementById('resourceSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            console.log('Search input changed:', searchInput.value);
            filterResources();
        });
    }
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            console.log('Category filter changed:', categoryFilter.value);
            filterResources();
        });
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            console.log('Status filter changed:', statusFilter.value);
            filterResources();
        });
    }
    
    // Setup form and button handlers will be done after submitRequest function is defined
    // (See code after submitRequest function definition)
    
    // Handle modal open: autofill profile data (but NOT setup button - that's done once above)
    document.addEventListener('shown.bs.modal', function(e) {
        if (e.target.id === 'requestResourceModal') {
            
            // Add event listener for the first resource quantity change to update max hint
            const quantityInput = document.getElementById('requestQuantity');
            if (quantityInput) {
                // Remove old listener if exists and add new one
                const newInput = quantityInput.cloneNode(true);
                quantityInput.parentNode.replaceChild(newInput, quantityInput);
                newInput.addEventListener('change', function() {
                    updateMaxQuantityHint();
                });
            }
            
            // Ensure submit button is enabled and clickable when modal opens
            const submitBtn = document.getElementById('submitRequestBtn');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.removeAttribute('disabled');
                submitBtn.classList.remove('submitting', 'disabled');
                submitBtn.style.pointerEvents = 'auto';
                submitBtn.style.cursor = 'pointer';
                submitBtn.style.opacity = '1';
                
                // Restore button text if it was changed
                if (submitBtn.innerHTML.includes('spinner-border')) {
                    submitBtn.innerHTML = '<span class="material-icons me-1">send</span>Submit Request';
                }
                
                // Ensure onclick handler is present
                if (!submitBtn.getAttribute('onclick') || submitBtn.getAttribute('onclick').indexOf('window.submitRequest') === -1) {
                    submitBtn.setAttribute('onclick', 'console.log("Button clicked directly"); if(typeof window.submitRequest==="function"){console.log("Calling submitRequest"); window.submitRequest();}else{console.error("submitRequest function not found on window object"); alert("Submit function not found. Please refresh the page.");} return false;');
                }
                
                console.log('Submit button enabled on modal open', {
                    disabled: submitBtn.disabled,
                    hasSubmitting: submitBtn.classList.contains('submitting'),
                    hasOnclick: !!submitBtn.getAttribute('onclick'),
                    windowSubmitRequest: typeof window.submitRequest
                });
            }
            
            // Auto-fill profile data into modal fields
            const autoFillFromProfile = () => {
                // Load from server (primary source)
                fetch('config/get_municipality_profile.php', { credentials: 'same-origin' })
                    .then(r => {
                        if (!r.ok) {
                            throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                        }
                        return r.json();
                    })
                    .then(data => {
                        if (data && data.success && data.data && data.data.profile) {
                            const profile = data.data.profile;
                            
                            // Update localStorage with server data
                            try {
                                const currentProfile = JSON.parse(localStorage.getItem('municipalityProfile') || '{}');
                                const updatedProfile = {
                                    ...currentProfile,
                                    drrmoHead: profile.drrmo_head || currentProfile.drrmoHead || '',
                                    drrmoHeadTitle: profile.drrmo_head_title || currentProfile.drrmoHeadTitle || '',
                                    operatorName: profile.operator_name || currentProfile.operatorName || '',
                                    operatorTitle: profile.operator_title || currentProfile.operatorTitle || '',
                                    logo: profile.logo_url || currentProfile.logo || '',
                                    municipalityName: profile.name || currentProfile.municipalityName || ''
                                };
                                localStorage.setItem('municipalityProfile', JSON.stringify(updatedProfile));
                            } catch (e) {
                                console.warn('Failed to update localStorage:', e);
                            }
                            
                            // Fill form fields
                            const approvingAuthorityField = document.getElementById('approvingAuthority');
                            if (approvingAuthorityField && profile.drrmo_head) {
                                approvingAuthorityField.value = profile.drrmo_head;
                            }
                            
                            const approverTitleField = document.getElementById('approverTitle');
                            if (approverTitleField && profile.drrmo_head_title) {
                                approverTitleField.value = profile.drrmo_head_title;
                            }
                            
                            const requestorNameField = document.getElementById('requestorName');
                            if (requestorNameField && profile.operator_name) {
                                requestorNameField.value = profile.operator_name;
                            }
                            
                            const requestorTitleField = document.getElementById('requestorTitle');
                            if (requestorTitleField && profile.operator_title) {
                                requestorTitleField.value = profile.operator_title;
                            }
                            
                            // Update municipality name fields if they exist
                            const requestingMunicipalityField = document.getElementById('requestingMunicipality');
                            if (requestingMunicipalityField && profile.name) {
                                requestingMunicipalityField.value = profile.name;
                            }
                            
                            const providerMunicipalityField = document.getElementById('providerMunicipality');
                            if (providerMunicipalityField && profile.name) {
                                // This will be set based on selected resource, but we can set default
                                if (!providerMunicipalityField.value) {
                                    providerMunicipalityField.value = profile.name;
                                }
                            }
                            
                            console.log('Municipality profile loaded successfully:', profile);
                        } else {
                            console.warn('Profile data structure unexpected:', data);
                        }
                    })
                    .catch(err => {
                        console.error('Failed to load profile from server:', err);
                        // Fallback to localStorage if server fails
                        try {
                            const savedProfile = localStorage.getItem('municipalityProfile');
                            if (savedProfile) {
                                const profile = JSON.parse(savedProfile);
                                const approvingAuthorityField = document.getElementById('approvingAuthority');
                                if (approvingAuthorityField && profile.drrmoHead) {
                                    approvingAuthorityField.value = profile.drrmoHead;
                                }
                                const approverTitleField = document.getElementById('approverTitle');
                                if (approverTitleField && profile.drrmoHeadTitle) {
                                    approverTitleField.value = profile.drrmoHeadTitle;
                                }
                                const requestorNameField = document.getElementById('requestorName');
                                if (requestorNameField && profile.operatorName) {
                                    requestorNameField.value = profile.operatorName;
                                }
                                const requestorTitleField = document.getElementById('requestorTitle');
                                if (requestorTitleField && profile.operatorTitle) {
                                    requestorTitleField.value = profile.operatorTitle;
                                }
                            }
                        } catch (e) {
                            console.error('Failed to load from localStorage fallback:', e);
                        }
                    });
                
                // Load signatures from localStorage profile
                try {
                    const savedProfile = localStorage.getItem('municipalityProfile');
                    if (savedProfile) {
                        const profile = JSON.parse(savedProfile);
                        
                        // Auto-fill operator/requestor signature
                        if (profile.operatorSignature) {
                            const requestorPreview = document.getElementById('requestorSignaturePreview');
                            const requestorImg = document.getElementById('requestorSignatureImg');
                            const requestorBtn = document.getElementById('requestorSignatureBtn');
                            if (requestorPreview && requestorImg && requestorBtn) {
                                requestorImg.src = profile.operatorSignature;
                                requestorPreview.style.display = 'block';
                                requestorBtn.textContent = 'Change E-Signature';
                            }
                        }
                        
                        // Auto-fill DRRMO Head/approver signature
                        if (profile.drrmoHeadSignature) {
                            const approverPreview = document.getElementById('approverSignaturePreview');
                            const approverImg = document.getElementById('approverSignatureImg');
                            const approverBtn = document.getElementById('approverSignatureBtn');
                            if (approverPreview && approverImg && approverBtn) {
                                approverImg.src = profile.drrmoHeadSignature;
                                approverPreview.style.display = 'block';
                                approverBtn.textContent = 'Change E-Signature';
                            }
                        }
                    }
                } catch(err) {
                    console.error('Failed to load signatures from profile:', err);
                }
            };
            
            // Try multiple times with delays to ensure fields are available
            setTimeout(autoFillFromProfile, 100);
            setTimeout(autoFillFromProfile, 300);
            setTimeout(autoFillFromProfile, 600);
            
            // Auto-calculate return date when deliveryDate or expectedDuration changes
            const calculateReturnDate = function() {
                const deliveryDate = document.getElementById('deliveryDate').value;
                const expectedDurationNumber = document.getElementById('expectedDurationNumber')?.value;
                const expectedDurationUnit = document.getElementById('expectedDurationUnit')?.value;
                const returnDateField = document.getElementById('returnDate');
                const returnDateRangeField = document.getElementById('returnDateRange');
                const expectedDurationHidden = document.getElementById('expectedDuration');
                
                if (!deliveryDate || !expectedDurationNumber || !expectedDurationUnit || !returnDateField) {
                    return;
                }
                
                // Update hidden field with combined value (format: "X days/weeks/months")
                if (expectedDurationHidden) {
                    const number = parseInt(expectedDurationNumber);
                    const unit = expectedDurationUnit;
                    expectedDurationHidden.value = `${number} ${unit}`;
                }
                
                const delivery = new Date(deliveryDate);
                if (isNaN(delivery.getTime())) {
                    return;
                }
                
                const number = parseInt(expectedDurationNumber);
                const unit = expectedDurationUnit.toLowerCase().trim();
                
                let daysToAdd = 7; // Default: 1 week
                
                if (unit === 'days') {
                    daysToAdd = number;
                } else if (unit === 'weeks') {
                    daysToAdd = number * 7;
                } else if (unit === 'months') {
                    daysToAdd = number * 30; // Approximate: 30 days per month
                }
                
                const returnDate = new Date(delivery);
                returnDate.setDate(returnDate.getDate() + daysToAdd);
                
                // Format as YYYY-MM-DD for date input
                const year = returnDate.getFullYear();
                const month = String(returnDate.getMonth() + 1).padStart(2, '0');
                const day = String(returnDate.getDate()).padStart(2, '0');
                const formattedDate = `${year}-${month}-${day}`;
                
                returnDateField.value = formattedDate;

                // Format range like "June 10 - June 13, 2026"
                const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                const deliveryMonth = months[delivery.getMonth()];
                const deliveryDay = delivery.getDate();
                const deliveryYear = delivery.getFullYear();

                const returnMonth = months[returnDate.getMonth()];
                const returnDay = returnDate.getDate();
                const returnYear = returnDate.getFullYear();

                let rangeString = "";
                if (deliveryYear === returnYear) {
                    rangeString = `${deliveryMonth} ${deliveryDay} - ${returnMonth} ${returnDay}, ${deliveryYear}`;
                } else {
                    rangeString = `${deliveryMonth} ${deliveryDay}, ${deliveryYear} - ${returnMonth} ${returnDay}, ${returnYear}`;
                }
                if (returnDateRangeField) {
                    returnDateRangeField.value = rangeString;
                }
            };
            
            // Add event listeners to auto-calculate return date
            const deliveryDateField = document.getElementById('deliveryDate');
            const expectedDurationNumberField = document.getElementById('expectedDurationNumber');
            const expectedDurationUnitField = document.getElementById('expectedDurationUnit');
            
            if (deliveryDateField) {
                deliveryDateField.addEventListener('change', calculateReturnDate);
                deliveryDateField.addEventListener('input', calculateReturnDate);
            }
            
            if (expectedDurationNumberField) {
                expectedDurationNumberField.addEventListener('change', calculateReturnDate);
                expectedDurationNumberField.addEventListener('input', calculateReturnDate);
            }
            
            if (expectedDurationUnitField) {
                expectedDurationUnitField.addEventListener('change', calculateReturnDate);
            }

            const requestResourceModal = document.getElementById('requestResourceModal');
            if (requestResourceModal) {
                requestResourceModal.addEventListener('shown.bs.modal', calculateReturnDate);
            }
            
            // Enforce 11-digit limit on phone number field
            const contactPhoneField = document.getElementById('contactPhone');
            if (contactPhoneField) {
                contactPhoneField.addEventListener('input', function(e) {
                    // Remove all non-numeric characters
                    let value = e.target.value.replace(/\D/g, '');
                    // Limit to 11 digits
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                    e.target.value = value;
                });
                
                contactPhoneField.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                    // Remove all non-numeric characters and limit to 11 digits
                    let value = pastedText.replace(/\D/g, '').substring(0, 11);
                    e.target.value = value;
                });
            }
            
            // Handle collapse icon rotation for additional details
            const additionalDetailsHeader = document.querySelector('[data-bs-target="#additionalDetailsCollapse"]');
            if (additionalDetailsHeader) {
                const collapseElement = document.getElementById('additionalDetailsCollapse');
                if (collapseElement) {
                    collapseElement.addEventListener('show.bs.collapse', function() {
                        additionalDetailsHeader.setAttribute('aria-expanded', 'true');
                        const icon = additionalDetailsHeader.querySelector('.material-icons');
                        if (icon) icon.style.transform = 'rotate(90deg)';
                    });
                    collapseElement.addEventListener('hide.bs.collapse', function() {
                        additionalDetailsHeader.setAttribute('aria-expanded', 'false');
                        const icon = additionalDetailsHeader.querySelector('.material-icons');
                        if (icon) icon.style.transform = 'rotate(0deg)';
                    });
                }
            }
        }
    });
    
    // Handle generate/view document buttons via data-action (event delegation)
    document.addEventListener('click', function(e) {
        const actionBtn = e.target.closest && e.target.closest('button[data-action]');
        if (actionBtn) {
            const action = actionBtn.getAttribute('data-action');
            const requestId = actionBtn.getAttribute('data-request-id');
            if (requestId && (action === 'generate-document' || action === 'view-document')) {
                e.preventDefault();
                e.stopPropagation();
                showPDFPreview(requestId);
            }
        }
    });
});

// Force the first tab to be active on page load
window.addEventListener('load', function() {
    console.log('Page fully loaded, ensuring first tab is active...');
    
    // Always default to Available Resources tab when coming from sidebar
    let defaultTabId = 'available-resources-tab';
    
    // Try to restore from localStorage first (user's manual tab selection)
    try {
        const savedTabId = localStorage.getItem('municipalityRequests.activeTab');
        if (savedTabId && document.getElementById(savedTabId)) {
            defaultTabId = savedTabId;
        }
    } catch(_) {}
    
    // Only use URL parameter if no saved tab is found in localStorage
    // This ensures that after a notification brings user to a tab, 
    // and they manually switch to another tab, they stay on that tab
    if (!localStorage.getItem('municipalityRequests.activeTab')) {
        const urlParams = new URLSearchParams(window.location.search);
        const requestedTab = urlParams.get('tab');
        
        if (requestedTab === 'available-resources' || requestedTab === 'who-requested' || requestedTab === 'your-requests' || requestedTab === 'borrowed-resources') {
            // Map URL parameter to tab ID
            const tabMap = {
                'available-resources': 'available-resources-tab',
                'who-requested': 'who-requested-tab',
                'your-requests': 'your-requests-tab',
                'borrowed-resources': 'borrowed-resources-tab'
            };
            defaultTabId = tabMap[requestedTab] || 'available-resources-tab';
        }
    }
    
    const firstTab = document.getElementById(defaultTabId) || document.getElementById('available-resources-tab');
    const targetPaneSelector = firstTab ? firstTab.getAttribute('data-bs-target') : '#available-resources';
    const firstTabPane = document.querySelector(targetPaneSelector) || document.getElementById('available-resources');
    
    if (firstTab && firstTabPane) {
        // Force hide ALL tab panes first
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('active', 'show');
            pane.style.display = 'none';
        });
        
        // Remove active from all tabs
        document.querySelectorAll('#requestTabs .nav-link').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Force add active state to first tab and pane
        firstTab.classList.add('active');
        firstTabPane.classList.add('active', 'show');
        firstTabPane.style.display = 'block';
        
        console.log('First tab forced to be active and visible:', firstTab.id);
        
        // Load the data for the selected tab
        if (firstTab.id === 'available-resources-tab') {
            loadAvailableResources();
        } else if (firstTab.id === 'who-requested-tab') {
            // Restore inner mode for Resource Requests
            try {
                const savedMode = localStorage.getItem('municipalityRequests.rrMode');
                if (savedMode === 'history') {
                    resourceRequestsMode = 'history';
                    const historyBtn = document.getElementById('resource-requests-history-tab');
                    const activeBtn = document.getElementById('resource-requests-active-tab');
                    const activePane = document.getElementById('who-requested-active');
                    const historyPane = document.getElementById('who-requested-history');
                    if (historyBtn && activeBtn) { historyBtn.classList.add('active'); activeBtn.classList.remove('active'); }
                    if (activePane && historyPane) {
                        [activePane, historyPane].forEach(p => { p.classList.remove('active','show'); p.style.display = 'none'; });
                        historyPane.classList.add('active','show');
                        historyPane.style.display = 'block';
                    }
                } else {
                    resourceRequestsMode = 'active';
                    // Ensure Active tab is selected
                    const activeBtn = document.getElementById('resource-requests-active-tab');
                    const historyBtn = document.getElementById('resource-requests-history-tab');
                    const activePane = document.getElementById('who-requested-active');
                    const historyPane = document.getElementById('who-requested-history');
                    if (activeBtn && historyBtn) { activeBtn.classList.add('active'); historyBtn.classList.remove('active'); }
                    if (activePane && historyPane) {
                        [activePane, historyPane].forEach(p => { p.classList.remove('active','show'); p.style.display = 'none'; });
                        activePane.classList.add('active','show');
                        activePane.style.display = 'block';
                    }
                }
            } catch(_) { 
                resourceRequestsMode = 'active';
                // Ensure Active tab is selected
                const activeBtn = document.getElementById('resource-requests-active-tab');
                const historyBtn = document.getElementById('resource-requests-history-tab');
                const activePane = document.getElementById('who-requested-active');
                const historyPane = document.getElementById('who-requested-history');
                if (activeBtn && historyBtn) { activeBtn.classList.add('active'); historyBtn.classList.remove('active'); }
                if (activePane && historyPane) {
                    [activePane, historyPane].forEach(p => { p.classList.remove('active','show'); p.style.display = 'none'; });
                    activePane.classList.add('active','show');
                    activePane.style.display = 'block';
                }
            }
            loadRequests(1);
        } else if (firstTab.id === 'your-requests-tab') {
            loadUserRequests();
        } else if (firstTab.id === 'borrowed-resources-tab') {
            loadBorrowedRequests();
        }
    }
    
    // Defer auto-refresh start until after initial data load completes (non-blocking)
    // This prevents auto-refresh from interfering with initial page load
    requestAnimationFrame(() => {
        setTimeout(() => {
            try { if (typeof startAutoRefresh === 'function') startAutoRefresh(); } catch(_) {}
        }, 1000); // Start auto-refresh 1 second after initial load
    });
    
    // Initialize signature uploads
    initializeSignatureUploads();
    
    // Preload data for tabs that need initial population when selected later
    // Show loading text only when actually loading
    const resultsCountEl = document.getElementById('resultsCount');
    if (resultsCountEl) resultsCountEl.textContent = '';
    
    // Add event listener for "My Requests" tab click
    const yourRequestsTab = document.getElementById('your-requests-tab');
    if (yourRequestsTab) {
        yourRequestsTab.addEventListener('click', function() {
            console.log('My Requests tab clicked, reloading user requests...');
            loadUserRequests();
        });
    }
    // Toggle history in My Requests
    const showHistoryToggle = document.getElementById('myRequestsShowHistory');
    if (showHistoryToggle) {
        showHistoryToggle.addEventListener('change', function(){
            console.log('Show history toggled:', showHistoryToggle.checked);
            loadUserRequests();
        });
    }
    
    // Add event listener for "Borrowed Resources" tab click
    const borrowedTab = document.getElementById('borrowed-resources-tab');
    if (borrowedTab) {
        borrowedTab.addEventListener('click', function() {
            console.log('Borrowed Resources tab clicked, loading borrowed requests...');
            loadBorrowedRequests();
        });
    }
    // Toggle history in Borrowed Resources
    const borrowedHistoryToggle = document.getElementById('borrowedShowHistory');
    if (borrowedHistoryToggle) {
        borrowedHistoryToggle.addEventListener('change', function(){
            console.log('Borrowed history toggled:', borrowedHistoryToggle.checked);
            loadBorrowedRequests();
        });
    }

    // Add event listener for "Resource Requests" tab click
    const whoRequestedTab = document.getElementById('who-requested-tab');
    if (whoRequestedTab) {
        whoRequestedTab.addEventListener('click', function() {
            console.log('Resource Requests tab clicked, reloading all requests...');
            resourceRequestsMode = 'active';
            // Force inner tabs state to Active
            try {
                const activeBtn = document.getElementById('resource-requests-active-tab');
                const historyBtn = document.getElementById('resource-requests-history-tab');
                const activePane = document.getElementById('who-requested-active');
                const historyPane = document.getElementById('who-requested-history');
                if (activeBtn && historyBtn) {
                    activeBtn.classList.add('active');
                    historyBtn.classList.remove('active');
                }
                if (activePane && historyPane) {
                    // Hide both first
                    [activePane, historyPane].forEach(p => { p.classList.remove('active','show'); p.style.display = 'none'; });
                    // Show active
                    activePane.classList.add('active','show');
                    activePane.style.display = 'block';
                }
                try { localStorage.setItem('municipalityRequests.rrMode', 'active'); } catch(_) {}
            } catch (_) {}
            // Always fetch fresh data when opening Who Requested so Active and History both have same data (fixes empty History)
            fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(j => {
                    if (j && j.success && j.data && Array.isArray(j.data.requests)) {
                        window.requestsData = j.data.requests;
                    }
                    loadRequests(1);
                })
                .catch(() => loadRequests(1));
            // Hide resources footer UI to avoid confusing "Loading resources..."
            const resultsCount = document.getElementById('resultsCount');
            const paginationControls = document.getElementById('paginationControls');
            if (resultsCount) resultsCount.textContent = '';
            if (paginationControls) paginationControls.classList.add('d-none');
        });
    }
    // Inner tabs: Active vs History for Resource Requests
    const rrActiveTab = document.getElementById('resource-requests-active-tab');
    const rrHistoryTab = document.getElementById('resource-requests-history-tab');
    if (rrActiveTab) {
        rrActiveTab.addEventListener('click', function() {
            resourceRequestsMode = 'active';
            console.log('Switched Resource Requests mode to active');
            try { localStorage.setItem('municipalityRequests.rrMode', 'active'); } catch(_) {}
            loadRequests(1);
        });
    }
    if (rrHistoryTab) {
        rrHistoryTab.addEventListener('click', function() {
            resourceRequestsMode = 'history';
            console.log('Switched Resource Requests mode to history');
            try { localStorage.setItem('municipalityRequests.rrMode', 'history'); } catch(_) {}
            // Show History pane so the table is visible
            const activeBtn = document.getElementById('resource-requests-active-tab');
            const historyBtn = document.getElementById('resource-requests-history-tab');
            const activePane = document.getElementById('who-requested-active');
            const historyPane = document.getElementById('who-requested-history');
            if (activeBtn && historyBtn) {
                activeBtn.classList.remove('active');
                historyBtn.classList.add('active');
            }
            if (activePane && historyPane) {
                activePane.classList.remove('active', 'show');
                activePane.style.display = 'none';
                historyPane.classList.add('active', 'show');
                historyPane.style.display = 'block';
            }
            // Fetch fresh data then render History so returned/rejected (and any stuck) requests are visible
            fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(j => {
                    if (j && j.success && j.data && Array.isArray(j.data.requests)) {
                        window.requestsData = j.data.requests;
                    }
                    loadRequests(1);
                })
                .catch(() => loadRequests(1));
        });
    }
    
    // Add event listener for "Available Resources" tab click
    const availableResourcesTab = document.getElementById('available-resources-tab');
    if (availableResourcesTab) {
        availableResourcesTab.addEventListener('click', function() {
            console.log('Available Resources tab clicked, reloading resources...');
            loadAvailableResources();
        });
    }
    
    
    // Listen for profile updates (when edit profile modal closes after saving)
    document.addEventListener('hidden.bs.modal', function(e) {
        if (e.target.id === 'editProfileModal') {
            // Profile was just saved, refresh button states
            setTimeout(updateRequestButtonStates, 300);
        }
        if (e.target.id === 'requestResourceModal') {
            // Reset modal to single resource mode when closed
            const singleResourceSection = document.getElementById('singleResourceSection');
            const multipleResourcesSection = document.getElementById('multipleResourcesSection');
            if (singleResourceSection) singleResourceSection.style.display = 'block';
            if (multipleResourcesSection) multipleResourcesSection.style.display = 'none';
            
            // Reset modal title
            const modalTitle = document.getElementById('requestResourceModalLabel');
            if (modalTitle) {
                modalTitle.textContent = 'Request Resource';
            }
            
            // Clear form data attributes
            const form = document.getElementById('requestResourceForm');
            if (form) {
                form.removeAttribute('data-multiple-resources');
                form.removeAttribute('data-resource-id');
            }
        }
    });
    
    
    // Cancel button event listener
    const cancelBtn = document.getElementById('cancelRequest');
    if (cancelBtn) {
        console.log('Cancel button found, adding event listener');
        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Cancel button clicked');
            const modal = bootstrap.Modal.getInstance(document.getElementById('requestResourceModal'));
            if (modal) {
                modal.hide();
            }
            // Reset form
            const form = document.getElementById('requestResourceForm');
            if (form) {
                form.reset();
            }
        });
    } else {
        console.log('Cancel button not found');
    }
    
    // Confirmation modal button event listener for multiple resources
    const confirmSubmitMultipleBtn = document.getElementById('confirmSubmitMultipleResourcesBtn');
    if (confirmSubmitMultipleBtn) {
        confirmSubmitMultipleBtn.addEventListener('click', function() {
            submitMultipleResourcesRequest();
        });
    }
    
    // Close modal button event listener
    const closeBtn = document.getElementById('closeRequestModal');
    if (closeBtn) {
        console.log('Close button found, adding event listener');
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Close button clicked');
            const modal = bootstrap.Modal.getInstance(document.getElementById('requestResourceModal'));
            if (modal) {
                modal.hide();
            }
            // Reset form
            const form = document.getElementById('requestResourceForm');
            if (form) {
                form.reset();
            }
        });
    } else {
        console.log('Close button not found');
    }
    
    // Test if buttons are clickable
    setTimeout(() => {
        const cancelBtn = document.getElementById('cancelRequest');
        const closeBtn = document.getElementById('closeRequestModal');
        const submitBtn = document.getElementById('submitRequestBtn');
        
        console.log('Button elements found:');
        console.log('Cancel button:', cancelBtn);
        console.log('Close button:', closeBtn);
        console.log('Submit button:', submitBtn);
        
        if (cancelBtn) {
            console.log('Cancel button style:', window.getComputedStyle(cancelBtn));
        }
        if (closeBtn) {
            console.log('Close button style:', window.getComputedStyle(closeBtn));
        }
    }, 1000);
});

// Search and filter functions
function filterResources() {
    console.log('Filter function called');
    
    const searchTerm = document.getElementById('resourceSearch').value.toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    console.log('Search term:', searchTerm);
    console.log('Category filter:', categoryFilter);
    console.log('Status filter:', statusFilter);
    
    // Instead of filtering the current page, reload the first page with filters
    // This ensures we get fresh data from the API
    loadResources(1);
}

// Clear all filters
function clearFilters() {
    console.log('Clearing filters');
    
    document.getElementById('resourceSearch').value = '';
    document.getElementById('categoryFilter').value = 'All';
    document.getElementById('statusFilter').value = 'All';
    
    // Reset filter results count
    const filterResultsCount = document.getElementById('filterResultsCount');
    if (filterResultsCount) {
        filterResultsCount.textContent = 'Use filters to search resources';
        filterResultsCount.className = 'text-muted';
    }
    
    // Reload the first page of resources to reset everything
    loadResources(1);
}

// Global functions for modal and actions
// Function to request multiple resources
function requestMultipleResources(resources) {
    console.log('Opening request modal for multiple resources:', resources);
    
    if (!resources || resources.length === 0) {
        showNotification('No resources selected', 'error');
        return;
    }
    
    // Store selected resources in form data attribute
    const form = document.getElementById('requestResourceForm');
    if (form) {
        form.setAttribute('data-multiple-resources', JSON.stringify(resources));
        form.removeAttribute('data-resource-id'); // Clear single resource ID
    }
    
    // Get municipality from first resource (all should be same)
    const municipality = resources[0].municipality;
    
    // Update modal title
    const modalTitle = document.getElementById('requestResourceModalLabel');
    if (modalTitle) {
        modalTitle.textContent = `Request ${resources.length} Resource${resources.length > 1 ? 's' : ''}`;
    }
    
    // Hide single resource display, show multiple resources display
    const singleResourceSection = document.getElementById('singleResourceSection');
    const multipleResourcesSection = document.getElementById('multipleResourcesSection');
    
    if (singleResourceSection) singleResourceSection.style.display = 'none';
    if (multipleResourcesSection) {
        multipleResourcesSection.style.display = 'block';
        
        // Populate multiple resources table
        const tbody = multipleResourcesSection.querySelector('#multipleResourcesTableBody');
        if (tbody) {
            tbody.innerHTML = resources.map((resource, index) => {
                const maxQty = resource.stock || 0;
                return `
                    <tr data-resource-id="${resource.resourceId}">
                        <td>${resource.resourceName || 'N/A'}</td>
                        <td>${maxQty} ${resource.unit || ''}</td>
                        <td>
                            <input type="number" 
                                   class="form-control form-control-sm resource-quantity-input" 
                                   data-resource-id="${resource.resourceId}"
                                   data-max="${maxQty}"
                                   min="1" 
                                   max="${maxQty}"
                                   value="1"
                                   required>
                            <small class="text-muted">Max: ${maxQty}</small>
                        </td>
                        <td>
                            <select class="form-select form-select-sm resource-priority-input" 
                                    data-resource-id="${resource.resourceId}">
                                <option value="Low">🟢 Low</option>
                                <option value="Medium" selected>🟡 Medium</option>
                                <option value="High">🟠 High</option>
                                <option value="Critical">🔴 Critical</option>
                            </select>
                        </td>
                    </tr>`;
            }).join('');
        }
    }
    
    // Set municipality display (for both sections)
    const providerDisplay = document.getElementById('providerMunicipalityDisplay');
    const providerDisplayMulti = document.getElementById('providerMunicipalityDisplayMulti');
    if (providerDisplay) providerDisplay.textContent = municipality;
    if (providerDisplayMulti) providerDisplayMulti.textContent = municipality;
    
    document.getElementById('providerMunicipality').value = municipality;
    document.getElementById('requestingMunicipality').value = window.currentUserDRRMOName || '';
    
    // Auto-populate form fields (same as single resource)
    const requestorNameField = document.getElementById('requestorName');
    if (requestorNameField) {
        requestorNameField.value = '<?php echo $_SESSION['full_name'] ?? 'N/A'; ?>';
    }
    
    document.getElementById('contactEmail').value = '<?php echo $_SESSION['username'] ?? ''; ?>';
    
    // Set default delivery date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('deliveryDate').value = tomorrow.toISOString().slice(0, 16);
    
    // Open modal
    const modalElement = document.getElementById('requestResourceModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
}

// Function to toggle Other Purpose input
function toggleOtherPurpose() {
    const purposeSelect = document.getElementById('purposeOfRequest');
    const otherPurposeContainer = document.getElementById('otherPurposeContainer');
    
    if (purposeSelect && otherPurposeContainer) {
        if (purposeSelect.value === 'Other') {
            otherPurposeContainer.style.display = 'block';
        } else {
            otherPurposeContainer.style.display = 'none';
        }
    }
}

// Function to update max quantity hint
function updateMaxQuantityHint() {
    const maxHint = document.getElementById('maxQuantityHint');
    const availableQuantity = document.getElementById('availableQuantity').value;
    
    if (maxHint && availableQuantity) {
        const available = parseInt(availableQuantity) || 0;
        maxHint.textContent = `Maximum available: ${available}`;
    }
}

// Function to highlight invalid field
function showFieldError(fieldId, message) {
    if (typeof showNotification === 'function') {
        showNotification(message, 'error');
    } else {
        alert(message);
    }
    
    const field = document.getElementById(fieldId);
    if (field) {
        field.classList.add('is-invalid');
        
        // Remove invalid class on input/change
        const removeInvalid = function() {
            this.classList.remove('is-invalid');
            this.removeEventListener('input', removeInvalid);
            this.removeEventListener('change', removeInvalid);
        };
        field.addEventListener('input', removeInvalid);
        field.addEventListener('change', removeInvalid);
        
        // Check if inside a collapsed element
        const collapseParent = field.closest('.collapse');
        if (collapseParent && !collapseParent.classList.contains('show')) {
            if (typeof bootstrap !== 'undefined') {
                const bsCollapse = new bootstrap.Collapse(collapseParent, { toggle: false });
                bsCollapse.show();
                // Wait for collapse to open before scrolling/focusing
                setTimeout(() => {
                    field.focus();
                    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 350);
            }
        } else {
            field.focus();
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

// Make submitRequest globally accessible - use simple function declaration
// Define submitRequest function - make it available immediately
function submitRequest() {
    console.log('=== submitRequest function called successfully ===');
    console.trace('submitRequest call stack');
    
    const form = document.getElementById('requestResourceForm');
    const submitBtn = document.getElementById('submitRequestBtn');
    
    if (!form) {
        console.error('Form not found!');
        showNotification('Form not found. Please refresh the page.', 'error');
        return;
    }
    
    if (!submitBtn) {
        console.error('Submit button not found!');
        showNotification('Submit button not found. Please refresh the page.', 'error');
        return;
    }
    
    // Prevent double submission (only if already submitting - not just disabled)
    if (submitBtn.classList.contains('submitting')) {
        console.log('Already submitting, ignoring duplicate submission');
        return;
    }
    
    // Get all form values with null checks
    const getFieldValue = (id) => {
        const field = document.getElementById(id);
        return field ? (field.value || '') : '';
    };
    
    const resourceName = getFieldValue('resourceName');
    const providerMunicipality = getFieldValue('providerMunicipality'); // The one providing/lending
    const requestingMunicipality = getFieldValue('requestingMunicipality'); // The one borrowing
    const requestQuantity = getFieldValue('requestQuantity');
    const requestPriority = getFieldValue('requestPriority');
    const deliveryDate = getFieldValue('deliveryDate');
    const deliveryLocation = getFieldValue('deliveryLocation');
    const requestorName = getFieldValue('requestorName');
    const requestorTitle = getFieldValue('requestorTitle');
    const requestorSignatureImg = document.getElementById('requestorSignatureImg');
    const requestorSignature = requestorSignatureImg ? requestorSignatureImg.src : '';
    const contactPhone = getFieldValue('contactPhone');
    const contactEmail = getFieldValue('contactEmail');
    let purposeOfRequest = getFieldValue('purposeOfRequest');
    
    // Combine expectedDurationNumber and expectedDurationUnit into hidden field
    const expectedDurationNumber = getFieldValue('expectedDurationNumber');
    const expectedDurationUnit = getFieldValue('expectedDurationUnit');
    let expectedDuration = '';
    if (expectedDurationNumber && expectedDurationUnit) {
        expectedDuration = `${expectedDurationNumber} ${expectedDurationUnit}`;
        const hiddenField = document.getElementById('expectedDuration');
        if (hiddenField) {
            hiddenField.value = expectedDuration;
        }
    } else {
        expectedDuration = getFieldValue('expectedDuration');
    }
    
    // Calculate return date from deliveryDate + expectedDuration if not manually set
    const calculateReturnDateFromDuration = function(deliveryDate, expectedDuration) {
        if (!deliveryDate || !expectedDuration) {
            return null;
        }
        
        const delivery = new Date(deliveryDate);
        if (isNaN(delivery.getTime())) {
            return null;
        }
        
        let daysToAdd = 7; // Default: 1 week
        
        const duration = expectedDuration.toLowerCase().trim();
        
        // Handle new format: "X days", "X weeks", "X months"
        const newFormatMatch = duration.match(/^(\d+)\s+(days?|weeks?|months?)$/);
        if (newFormatMatch) {
            const number = parseInt(newFormatMatch[1]);
            const unit = newFormatMatch[2];
            if (unit.startsWith('day')) {
                daysToAdd = number;
            } else if (unit.startsWith('week')) {
                daysToAdd = number * 7;
            } else if (unit.startsWith('month')) {
                daysToAdd = number * 30; // Approximate: 30 days per month
            }
        }
        // Handle old formats for backward compatibility
        else if (duration.includes('1-3 days')) {
            daysToAdd = 3;
        } else if (duration.includes('1 week') || (duration.includes('week') && !newFormatMatch)) {
            daysToAdd = 7;
        } else if (duration.includes('2-4 weeks')) {
            daysToAdd = 28; // 4 weeks
        } else if (duration.includes('1-3 months')) {
            daysToAdd = 90; // ~3 months
        } else if (duration.includes('3+ months')) {
            daysToAdd = 180; // ~6 months
        } else if (duration.includes('indefinite')) {
            daysToAdd = 365; // 1 year
        }
        
        const returnDate = new Date(delivery);
        returnDate.setDate(returnDate.getDate() + daysToAdd);
        
        // Format as YYYY-MM-DD for date input
        const year = returnDate.getFullYear();
        const month = String(returnDate.getMonth() + 1).padStart(2, '0');
        const day = String(returnDate.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    
    // Auto-calculate return date if not manually set
    let returnDate = getFieldValue('returnDate');
    if (!returnDate && deliveryDate && expectedDuration) {
        returnDate = calculateReturnDateFromDuration(deliveryDate, expectedDuration);
        // Auto-fill the field so user can see it
        const returnDateField = document.getElementById('returnDate');
        if (returnDateField && returnDate) {
            returnDateField.value = returnDate;
        }

        // Update range display field
        const returnDateRangeField = document.getElementById('returnDateRange');
        if (returnDateRangeField && deliveryDate && returnDate) {
            const delivery = new Date(deliveryDate);
            const returnD = new Date(returnDate);
            if (!isNaN(delivery.getTime()) && !isNaN(returnD.getTime())) {
                const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                const deliveryMonth = months[delivery.getMonth()];
                const deliveryDay = delivery.getDate();
                const deliveryYear = delivery.getFullYear();

                const returnMonth = months[returnD.getMonth()];
                const returnDay = returnD.getDate();
                const returnYear = returnD.getFullYear();

                let rangeString = "";
                if (deliveryYear === returnYear) {
                    rangeString = `${deliveryMonth} ${deliveryDay} - ${returnMonth} ${returnDay}, ${deliveryYear}`;
                } else {
                    rangeString = `${deliveryMonth} ${deliveryDay}, ${deliveryYear} - ${returnMonth} ${returnDay}, ${returnYear}`;
                }
                returnDateRangeField.value = rangeString;
            }
        }
    }
    
    const transportationMethod = getFieldValue('transportationMethod');
    const approvingAuthority = getFieldValue('approvingAuthority');
    const approverTitle = getFieldValue('approverTitle');
    const approverSignatureImg = document.getElementById('approverSignatureImg');
    const approverSignature = approverSignatureImg ? approverSignatureImg.src : '';
    const requestNotes = getFieldValue('requestNotes');
    const resourceId = form.getAttribute('data-resource-id');
    
    // Check if this is a multiple resource request FIRST (before single resource validation)
    const multipleResourcesData = form.getAttribute('data-multiple-resources');
    
    if (multipleResourcesData) {
        console.log('Processing multiple resource request...');
        // Handle multiple resources
        try {
            const resources = JSON.parse(multipleResourcesData);
            console.log('Parsed resources:', resources);
            
            if (!resources || !Array.isArray(resources) || resources.length === 0) {
                showNotification('No resources selected. Please select at least one resource.', 'error');
                return;
            }
            
            // Validate common required fields first
            if (!deliveryDate || deliveryDate.trim() === '') {
                showFieldError('deliveryDate', 'Please fill in Delivery Date');
                return;
            }
            
            if (!deliveryLocation || deliveryLocation.trim() === '') {
                showFieldError('deliveryLocation', 'Please fill in Delivery Location');
                return;
            }
            
            if (!contactPhone || contactPhone.trim() === '') {
                showFieldError('contactPhone', 'Please fill in Contact Phone');
                return;
            }
            
            // Validate phone number is exactly 11 digits
            const phoneDigits = contactPhone.replace(/\\D/g, '');
            if (phoneDigits.length !== 11) {
                showFieldError('contactPhone', 'Contact Phone Number must be exactly 11 digits');
                return;
            }
            
            if (!contactEmail || contactEmail.trim() === '') {
                showFieldError('contactEmail', 'Please fill in Contact Email');
                return;
            }
            
            if (!purposeOfRequest || purposeOfRequest.trim() === '') {
                showFieldError('purposeOfRequest', 'Please fill in Purpose of Request');
                return;
            }
            
            if (purposeOfRequest === 'Other') {
                const otherPurpose = getFieldValue('otherPurpose');
                if (!otherPurpose || otherPurpose.trim() === '') {
                    showFieldError('otherPurpose', 'Please specify the purpose of your request');
                    return;
                }
                purposeOfRequest = otherPurpose;
            }
            
            const expectedDurationNumber = getFieldValue('expectedDurationNumber');
            const expectedDurationUnit = getFieldValue('expectedDurationUnit');
            if (!expectedDurationNumber || !expectedDurationUnit || expectedDurationNumber.trim() === '' || expectedDurationUnit.trim() === '') {
                showFieldError(!expectedDurationNumber ? 'expectedDurationNumber' : 'expectedDurationUnit', 'Please fill in Expected Duration (both number and unit)');
                return;
            }
            // Update hidden field with combined value
            const expectedDurationHidden = document.getElementById('expectedDuration');
            if (expectedDurationHidden) {
                expectedDurationHidden.value = `${expectedDurationNumber} ${expectedDurationUnit}`;
            }
            const expectedDuration = `${expectedDurationNumber} ${expectedDurationUnit}`;
            
            if (!transportationMethod || transportationMethod.trim() === '') {
                // No specific ID for transportation method as it's a hidden field with a div. Defaulting to general alert or if we add ID to the visual div
                showNotification('Please fill in Transportation Method', 'error');
                return;
            }
            
            if (!approvingAuthority || approvingAuthority.trim() === '') {
                showFieldError('approvingAuthority', 'Please fill in Approving Authority');
                return;
            }
            
            // Validate all resources have quantities
            const resourcesWithQuantities = [];
            let allValid = true;
            
            resources.forEach((resource, index) => {
                const quantityInput = document.querySelector(`.resource-quantity-input[data-resource-id="${resource.resourceId}"]`);
                const priorityInput = document.querySelector(`.resource-priority-input[data-resource-id="${resource.resourceId}"]`);
                
                console.log(`Validating resource ${index + 1}:`, {
                    resourceId: resource.resourceId,
                    resourceName: resource.resourceName,
                    quantityInput: quantityInput ? quantityInput.value : 'NOT FOUND',
                    priorityInput: priorityInput ? priorityInput.value : 'NOT FOUND'
                });
                
                if (!quantityInput) {
                    allValid = false;
                    showNotification(`Quantity input not found for ${resource.resourceName}. Please refresh and try again.`, 'error');
                    return;
                }
                
                if (!quantityInput.value || parseInt(quantityInput.value) < 1) {
                    allValid = false;
                    showNotification(`Please enter a valid quantity for ${resource.resourceName}`, 'error');
                    quantityInput.classList.add('is-invalid');
                    quantityInput.focus();
                    return;
                }
                
                const qty = parseInt(quantityInput.value);
                if (qty > (resource.stock || 0)) {
                    allValid = false;
                    showNotification(`Quantity for ${resource.resourceName} exceeds available stock (${resource.stock})`, 'error');
                    quantityInput.classList.add('is-invalid');
                    quantityInput.focus();
                    return;
                }
                
                resourcesWithQuantities.push({
                    resourceId: resource.resourceId,
                    resourceName: resource.resourceName,
                    quantity: qty,
                    priority: priorityInput ? priorityInput.value : 'Medium',
                    unit: resource.unit || ''
                });
            });
            
            if (!allValid) {
                console.error('Validation failed for multiple resources');
                return;
            }
            
            if (resourcesWithQuantities.length === 0) {
                showNotification('No valid resources to submit. Please check your selections.', 'error');
                return;
            }
            
            console.log('All resources validated successfully:', resourcesWithQuantities);
            
            // Store resources with quantities for confirmation modal
            window.pendingMultipleResources = resourcesWithQuantities;
            
            // Show confirmation modal
            showMultipleResourcesConfirmation(resourcesWithQuantities);
            
            return; // Exit early - actual submission happens after confirmation
            
        } catch (error) {
            console.error('Error parsing multiple resources:', error);
            showNotification('Error processing selected resources: ' + error.message, 'error');
            return;
        }
    }
    
    // Single resource request validation (only if not multiple resources)
    console.log('Processing single resource request...');
    
    // Validate required fields for single resource
    if (!resourceName || resourceName.trim() === '') {
        showFieldError('resourceNameDisplay', 'Please select a resource');
        return;
    }
    
    if (!resourceId) {
        showNotification('Resource ID not found. Please try again.', 'error');
        return;
    }
    
    if (!requestQuantity || requestQuantity < 1) {
        showFieldError('requestQuantity', 'Quantity must be at least 1');
        return;
    }
    
    if (!requestPriority || requestPriority.trim() === '') {
        showFieldError('requestPriority', 'Please fill in Priority Level');
        return;
    }
    
    if (!deliveryDate || deliveryDate.trim() === '') {
        showFieldError('deliveryDate', 'Please fill in Delivery Date');
        return;
    }
    
    if (!deliveryLocation || deliveryLocation.trim() === '') {
        showFieldError('deliveryLocation', 'Please fill in Delivery Location');
        return;
    }
    
    // All validation passed - submit single resource request
    // Note: submitSingleResourceRequest() will perform complete validation including
    // contactPhone, contactEmail, purposeOfRequest, expectedDuration, transportationMethod, and approvingAuthority
    submitSingleResourceRequest();
}

// Function to show confirmation modal for multiple resources
function showMultipleResourcesConfirmation(resourcesWithQuantities) {
    const modal = new bootstrap.Modal(document.getElementById('confirmMultipleResourcesModal'));
    const tbody = document.getElementById('confirmResourcesList');
    
    if (!tbody) {
        console.error('Confirmation modal table body not found');
        return;
    }
    
    // Populate the table
    tbody.innerHTML = resourcesWithQuantities.map((resource, index) => {
        const priorityBadge = {
            'Low': '<span class="badge bg-success">Low</span>',
            'Medium': '<span class="badge bg-warning">Medium</span>',
            'High': '<span class="badge bg-danger">High</span>',
            'Critical': '<span class="badge bg-dark">Critical</span>'
        }[resource.priority] || '<span class="badge bg-secondary">' + resource.priority + '</span>';
        
        return `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${resource.resourceName || resource.name || 'N/A'}</strong></td>
                <td><strong>${resource.quantity || 0}</strong> ${resource.unit || ''}</td>
                <td>${priorityBadge}</td>
            </tr>
        `;
    }).join('');
    
    // Show modal
    modal.show();
}

// Function to submit multiple resources after confirmation
function submitMultipleResourcesRequest() {
    const resourcesWithQuantities = window.pendingMultipleResources;
    if (!resourcesWithQuantities || resourcesWithQuantities.length === 0) {
        showNotification('No resources to submit', 'error');
        return;
    }
    
    const form = document.getElementById('requestResourceForm');
    const submitBtn = document.getElementById('submitRequestBtn');
    
    if (!form || !submitBtn) {
        showNotification('Form not found. Please refresh the page.', 'error');
        return;
    }
    
    // Get all form values
    const getFieldValue = (id) => {
        const field = document.getElementById(id);
        return field ? (field.value || '') : '';
    };
    
    const providerMunicipality = getFieldValue('providerMunicipality');
    const requestingMunicipality = getFieldValue('requestingMunicipality');
    const deliveryDate = getFieldValue('deliveryDate');
    const deliveryLocation = getFieldValue('deliveryLocation');
    const requestorName = getFieldValue('requestorName');
    const requestorTitle = getFieldValue('requestorTitle');
    const requestorSignatureImg = document.getElementById('requestorSignatureImg');
    const requestorSignature = requestorSignatureImg ? requestorSignatureImg.src : '';
    const contactPhone = getFieldValue('contactPhone');
    const contactEmail = getFieldValue('contactEmail');
    let purposeOfRequest = getFieldValue('purposeOfRequest');
    if (purposeOfRequest === 'Other') {
        const otherPurpose = getFieldValue('otherPurpose');
        if (otherPurpose && otherPurpose.trim() !== '') {
            purposeOfRequest = otherPurpose;
        }
    }
    const expectedDuration = getFieldValue('expectedDuration');
    const returnDate = getFieldValue('returnDate');
    const transportationMethod = getFieldValue('transportationMethod');
    const approvingAuthority = getFieldValue('approvingAuthority');
    const approverTitle = getFieldValue('approverTitle');
    const approverSignatureImg = document.getElementById('approverSignatureImg');
    const approverSignature = approverSignatureImg ? approverSignatureImg.src : '';
    const sharedNotes = ''; // Removed from UI
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.classList.add('submitting');
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Submitting...';
    
    // Create request data with multiple resources
    const requestData = {
        resources: resourcesWithQuantities,
        providerMunicipality: providerMunicipality,
        requestingMunicipality: requestingMunicipality,
        deliveryDate: deliveryDate,
        deliveryLocation: deliveryLocation,
        requestorName: requestorName,
        requestorTitle: requestorTitle,
        requestorSignature: requestorSignature,
        contactPhone: contactPhone,
        contactEmail: contactEmail,
        purposeOfRequest: purposeOfRequest,
        expectedDuration: expectedDuration,
        returnDate: returnDate,
        transportationMethod: transportationMethod,
        approvingAuthority: approvingAuthority,
        approverTitle: approverTitle,
        approverSignature: approverSignature,
        requestNotes: sharedNotes
    };
    
    console.log('Submitting multiple resource requests:', requestData);
    
    // Submit request to server
    fetch('config/submit_request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestData)
    })
    .then(response => {
        console.log('Response received:', response);
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            console.log('Multiple requests submitted successfully!');
            
            // Close confirmation modal
            const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmMultipleResourcesModal'));
            if (confirmModal) confirmModal.hide();
            
            // Close request modal
            const requestModal = bootstrap.Modal.getInstance(document.getElementById('requestResourceModal'));
            if (requestModal) requestModal.hide();
            
            // Show success message
            showNotification(`Successfully submitted ${resourcesWithQuantities.length} resource request${resourcesWithQuantities.length > 1 ? 's' : ''}!`, 'success');
            
            // Clear selections
            clearResourceSelection();
            
            // Reset form
            form.reset();
            form.removeAttribute('data-multiple-resources');
            
            // Show/hide sections
            const singleResourceSection = document.getElementById('singleResourceSection');
            const multipleResourcesSection = document.getElementById('multipleResourcesSection');
            if (singleResourceSection) singleResourceSection.style.display = 'block';
            if (multipleResourcesSection) multipleResourcesSection.style.display = 'none';
            
            // Open PDF viewer with requestGroupId if available, otherwise use first requestID
            if (data.requestGroupId) {
                setTimeout(() => {
                    const pdfUrl = `dashboards/pages/pdf_viewer.php?groupId=${data.requestGroupId}`;
                    window.open(pdfUrl, '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');
                }, 500);
            } else if (data.requestIDs && data.requestIDs.length > 0) {
                // Fallback: use first request ID
                setTimeout(() => {
                    if (typeof showPDFPreview === 'function') {
                        showPDFPreview(data.requestIDs[0]);
                    } else {
                        const pdfUrl = `dashboards/pages/pdf_viewer.php?id=${data.requestIDs[0]}`;
                        window.open(pdfUrl, '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');
                    }
                }, 500);
            }
            
            // Refresh requests data
            fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.data && Array.isArray(d.data.requests)) {
                        window.requestsData = d.data.requests;
                        loadRequests(1);
                        loadUserRequests();
                        loadBorrowedRequests();
                    }
                })
                .catch(err => console.error('Error refreshing requests:', err));
            
            // Reload resources
            const currentPageEl = document.querySelector('#availableResourcesTableBody')?.closest('.card')?.querySelector('[data-page]');
            const currentPage = currentPageEl ? parseInt(currentPageEl.getAttribute('data-page')) || 1 : 1;
            loadResources(currentPage);
        } else {
            throw new Error(data.message || 'Failed to submit requests');
        }
    })
    .catch(error => {
        console.error('Error submitting requests:', error);
        showNotification('Error submitting requests: ' + error.message, 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.classList.remove('submitting');
        submitBtn.innerHTML = 'Submit Request';
    });
}

// Function to submit single resource request (called from submitRequest function)
function submitSingleResourceRequest() {
    const form = document.getElementById('requestResourceForm');
    const submitBtn = document.getElementById('submitRequestBtn');
    
    if (!form || !submitBtn) {
        showNotification('Form not found. Please refresh the page.', 'error');
        return;
    }
    
    // Get all form values with null checks
    const getFieldValue = (id) => {
        const field = document.getElementById(id);
        return field ? (field.value || '') : '';
    };
    
    const resourceName = getFieldValue('resourceName');
    const resourceId = getFieldValue('resourceId');
    const providerMunicipality = getFieldValue('providerMunicipality');
    const requestingMunicipality = getFieldValue('requestingMunicipality');
    const requestQuantity = getFieldValue('requestQuantity');
    const requestPriority = getFieldValue('requestPriority');
    const deliveryDate = getFieldValue('deliveryDate');
    const deliveryLocation = getFieldValue('deliveryLocation');
    const requestorName = getFieldValue('requestorName');
    const requestorTitle = getFieldValue('requestorTitle');
    const requestorSignatureImg = document.getElementById('requestorSignatureImg');
    const requestorSignature = requestorSignatureImg ? requestorSignatureImg.src : '';
    const contactPhone = getFieldValue('contactPhone');
    const contactEmail = getFieldValue('contactEmail');
    let purposeOfRequest = getFieldValue('purposeOfRequest');
    let expectedDuration = getFieldValue('expectedDuration');
    const returnDate = getFieldValue('returnDate');
    const transportationMethod = getFieldValue('transportationMethod');
    const approvingAuthority = getFieldValue('approvingAuthority');
    const approverTitle = getFieldValue('approverTitle');
    const approverSignatureImg = document.getElementById('approverSignatureImg');
    const approverSignature = approverSignatureImg ? approverSignatureImg.src : '';
    const requestNotes = ''; // Removed from UI
    
    if (!contactPhone || contactPhone.trim() === '') {
        showFieldError('contactPhone', 'Please fill in Contact Phone');
        return;
    }
    
    if (!contactEmail || contactEmail.trim() === '') {
        showFieldError('contactEmail', 'Please fill in Contact Email');
        return;
    }
    
    if (!purposeOfRequest || purposeOfRequest.trim() === '') {
        showFieldError('purposeOfRequest', 'Please fill in Purpose of Request');
        return;
    }
    
    if (purposeOfRequest === 'Other') {
        const otherPurpose = getFieldValue('otherPurpose');
        if (!otherPurpose || otherPurpose.trim() === '') {
            showFieldError('otherPurpose', 'Please specify the purpose of your request');
            return;
        }
        purposeOfRequest = otherPurpose;
    }
    
    // Validate expected duration fields
    const expectedDurationNumberCheck = getFieldValue('expectedDurationNumber');
    const expectedDurationUnitCheck = getFieldValue('expectedDurationUnit');
    if (!expectedDurationNumberCheck || !expectedDurationUnitCheck || expectedDurationNumberCheck.trim() === '' || expectedDurationUnitCheck.trim() === '') {
        showFieldError(!expectedDurationNumberCheck ? 'expectedDurationNumber' : 'expectedDurationUnit', 'Please fill in Expected Duration (both number and unit)');
        return;
    }
    // Update hidden field and expectedDuration variable
    const combinedDuration = `${expectedDurationNumberCheck} ${expectedDurationUnitCheck}`;
    const hiddenField = document.getElementById('expectedDuration');
    if (hiddenField) {
        hiddenField.value = combinedDuration;
    }
    expectedDuration = combinedDuration;
    
    if (!transportationMethod || transportationMethod.trim() === '') {
        showNotification('Please fill in Transportation Method', 'error');
        return;
    }
    
    if (!approvingAuthority || approvingAuthority.trim() === '') {
        showFieldError('approvingAuthority', 'Please fill in Approving Authority');
        return;
    }
    
    console.log('All required fields validated successfully for single resource');
    
    // All validation passed - now disable button and show loading
    submitBtn.disabled = true;
    submitBtn.classList.add('submitting');
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Submitting...';
    
    // Create request object
    const requestData = {
        resourceId: resourceId,
        resourceName: resourceName,
        providerMunicipality: providerMunicipality, // The one providing/lending
        requestingMunicipality: requestingMunicipality, // The one borrowing
        requestQuantity: parseInt(requestQuantity),
        requestPriority: requestPriority,
        deliveryDate: deliveryDate,
        deliveryLocation: deliveryLocation,
        requestorName: requestorName,
        requestorTitle: requestorTitle,
        requestorSignature: requestorSignature,
        contactPhone: contactPhone,
        contactEmail: contactEmail,
        purposeOfRequest: purposeOfRequest,
        expectedDuration: expectedDuration,
        returnDate: returnDate,
        transportationMethod: transportationMethod,
        approvingAuthority: approvingAuthority,
        approverTitle: approverTitle,
        approverSignature: approverSignature,
        requestNotes: requestNotes
    };
    console.log('Submitting resource request:', requestData);
    
    // Submit request to server
    console.log('Sending request to config/submit_request.php');
    fetch('config/submit_request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestData)
    })
    .then(response => {
        console.log('Response received:', response);
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            console.log('Request submitted successfully!');
            
            // Show success message
            showNotification('Resource request submitted successfully! You will receive a tracking ID shortly.', 'success');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('requestResourceModal'));
            modal.hide();
            
            // Generate and show PDF preview (formal letter)
            if (data.requestID) {
                console.log('Request ID received:', data.requestID, '- Opening PDF preview for formal letter');
                // Show notification about routing
                if (data.needsHeadApproval) {
                    showNotification('Request submitted! It has been sent to the Head of DRRMO for approval. The formal letter will be generated after approval.', 'info');
                } else {
                    showNotification('Request submitted! Opening formal letter preview...', 'success');
                    setTimeout(() => {
                        if (typeof showPDFPreview === 'function') {
                            showPDFPreview(data.requestID);
                        } else {
                            console.warn('showPDFPreview function not found, opening directly');
                            // Fallback: open PDF viewer directly
                            const pdfUrl = `dashboards/pages/pdf_viewer.php?id=${data.requestID}`;
                            window.open(pdfUrl, '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');
                        }
                    }, 500);
                }
            }
            
            // Reset form
            form.reset();
            form.removeAttribute('data-resource-id');
            
            // Refresh requests data from server first, then update tabs
            fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(j => {
                    if (j && j.success && j.data && Array.isArray(j.data.requests)) {
                        // Update the global requests data
                        window.requestsData = j.data.requests;
                        
                        // Refresh the current active tab
                        const activeTab = document.querySelector('#requestTabs .nav-link.active');
                        if (activeTab) {
                            if (activeTab.id === 'who-requested-tab') {
                                loadRequests(window.requestsCurrentPage || 1);
                            } else if (activeTab.id === 'your-requests-tab') {
                                loadUserRequests();
                            } else if (activeTab.id === 'borrowed-resources-tab') {
                                loadBorrowedRequests();
                            } else if (activeTab.id === 'available-resources-tab') {
                                loadAvailableResources();
                            }
                        }
                        
                        // Also refresh "Resource Requests" tab data silently (where new request would appear)
                        loadRequests(window.requestsCurrentPage || 1);
                        
                        // Also refresh "My Requests" tab silently (user's own requests)
                        loadUserRequests();
                    }
                })
                .catch(err => {
                    console.warn('Failed to refresh requests data:', err);
                });
            
            // Refresh notifications quietly
            if (window.refreshHeaderNotifications) {
                window.refreshHeaderNotifications();
            }
        } else {
            console.log('Request failed:', data.message);
            showNotification('Error submitting request: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting request:', error);
        showNotification('Error submitting request. Please try again.', 'error');
    })
    .finally(() => {
        // Re-enable submit button only if still disabled (not reloaded)
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('submitting');
            submitBtn.innerHTML = '<span class="material-icons me-1">send</span>Submit Request';
        }
    });
}

// Ensure function is globally accessible on window object - do this immediately
window.submitRequest = submitRequest;
console.log('submitRequest function assigned to window.submitRequest:', typeof window.submitRequest);

// Test button accessibility when modal opens
document.addEventListener('shown.bs.modal', function(e) {
    if (e.target.id === 'requestResourceModal') {
        setTimeout(function() {
            const testBtn = document.getElementById('submitRequestBtn');
            if (testBtn) {
                console.log('=== SUBMIT BUTTON TEST ===', {
                    id: testBtn.id,
                    disabled: testBtn.disabled,
                    onclick: testBtn.getAttribute('onclick'),
                    hasWindowSubmitRequest: typeof window.submitRequest,
                    buttonHTML: testBtn.outerHTML.substring(0, 200)
                });
                
                // Test if we can call the function directly
                if (typeof window.submitRequest === 'function') {
                    console.log('✓ window.submitRequest is available and callable');
                } else {
                    console.error('✗ window.submitRequest is NOT available!');
                }
            }
        }, 500);
    }
});

// Setup form and button handlers ONCE using event delegation (after submitRequest is defined)
// This prevents duplicate event listeners and works even if button is recreated
(function setupSubmitHandlers() {
    // Prevent form submission globally using event delegation
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form && form.id === 'requestResourceForm') {
            e.preventDefault();
            e.stopPropagation();
            console.log('Form submit prevented via delegation, calling submitRequest()');
            if (typeof window.submitRequest === 'function') {
                window.submitRequest();
            } else if (typeof submitRequest === 'function') {
                submitRequest();
            } else {
                console.error('submitRequest not found!');
            }
            return false;
        }
    }, true); // Use capture phase
    
    // Use event delegation for submit button - works even if button is recreated
    document.addEventListener('click', function(e) {
        // Check if clicked element is the submit button or a child of it
        let btn = e.target;
        
        // If clicked on a child element (like span or icon), find the button parent
        if (btn.id !== 'submitRequestBtn') {
            btn = btn.closest('#submitRequestBtn');
        }
        
        // Also check by ID directly
        if (!btn && e.target.id === 'submitRequestBtn') {
            btn = e.target;
        }
        
        if (btn && btn.id === 'submitRequestBtn') {
            e.preventDefault();
            e.stopPropagation();
            console.log('Submit button clicked via delegation', {
                target: e.target.tagName,
                buttonId: btn.id,
                disabled: btn.disabled,
                hasSubmitting: btn.classList.contains('submitting')
            });
            
            if (btn.disabled || btn.classList.contains('submitting')) {
                console.warn('Button is disabled or submitting, ignoring click');
                return false;
            }
            
            // Call submitRequest
            if (typeof window.submitRequest === 'function') {
                console.log('Calling window.submitRequest via delegation');
                window.submitRequest();
            } else if (typeof submitRequest === 'function') {
                console.log('Calling submitRequest (local) via delegation');
                submitRequest();
            } else {
                console.error('submitRequest function not found on window or local scope!');
            }
            return false;
        }
    }, true); // Use capture phase for better reliability
    
    console.log('Submit handlers setup complete (using event delegation)');
})();

// Notification function
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type} show`;
    notification.innerHTML = `
        <span class="material-icons">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Remove notification after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Global variables for pagination
let currentPage = 1;
let totalPages = 1;

// Load resources data
async function loadResources(page = 1) {
    try {
        console.log('Loading resources for page:', page);
        const resultsCount = document.getElementById('resultsCount');
        if (resultsCount) resultsCount.textContent = 'Loading resources...';
        
        // Get current filter values
        const searchTerm = document.getElementById('resourceSearch').value;
        const categoryFilter = document.getElementById('categoryFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        
        // Build query parameters
        const params = new URLSearchParams({
            page: page,
            limit: 10
        });
        
        if (searchTerm) params.append('search', searchTerm);
        if (categoryFilter && categoryFilter !== 'All') params.append('category', categoryFilter);
        if (statusFilter && statusFilter !== 'All') params.append('status', statusFilter);
        
        console.log('API request params:', params.toString());
        
        const response = await fetch(`config/get_resources.php?${params.toString()}`);
        const data = await response.json();
        
        console.log('Resources response:', data);
        
        if (data.success && data.resources) {
            displayResources(data.resources);
            const safePagination = data.pagination || {
                totalPages: 1,
                currentPage: 1,
                count: Array.isArray(data.resources) ? data.resources.length : 0,
                totalResources: Array.isArray(data.resources) ? data.resources.length : 0
            };
            updatePagination(safePagination);
        } else {
            console.error('Error loading resources:', data.message);
            document.getElementById('availableResourcesTableBody').innerHTML = 
                '<tr><td colspan="6" class="text-center text-muted">No resources available</td></tr>';
        }
    } catch (error) {
        console.error('Error fetching resources:', error);
        document.getElementById('availableResourcesTableBody').innerHTML = 
            '<tr><td colspan="6" class="text-center text-muted">Error loading resources</td></tr>';
    }
}

// Update pagination controls
function updatePagination(pagination) {
    currentPage = pagination.currentPage;
    totalPages = pagination.totalPages;
    
    const resultsCount = document.getElementById('resultsCount');
    const paginationContainer = document.getElementById('resourcesPagination');
    
    // Update results count (if present)
    if (resultsCount) {
        resultsCount.textContent = `Showing ${pagination.count} of ${pagination.totalResources} resources`;
    }
    
    // Build windowed pagination with First/Prev/Next/Last and up to 5 pages
    if (paginationContainer) {
        paginationContainer.innerHTML = '';
        const nav = document.createElement('nav');
        const ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm mb-0';
        const create = (label, page, disabled = false, active = false) => {
            const li = document.createElement('li');
            li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'page-link';
            btn.textContent = label;
            if (!disabled && !active) btn.addEventListener('click', () => loadResources(page));
            li.appendChild(btn);
            return li;
        };
        const total = Math.max(1, pagination.totalPages || 1);
        const current = Math.min(Math.max(1, pagination.currentPage || 1), total);

        const maxVisible = 5;
        const half = Math.floor(maxVisible / 2);
        let start = Math.max(1, current - half);
        let end = Math.min(total, start + maxVisible - 1);
        start = Math.max(1, end - maxVisible + 1);

        // First and Prev
        ul.appendChild(create('First', 1, current === 1));
        ul.appendChild(create('«', Math.max(1, current - 1), current === 1));

        // Numbered window
        for (let p = start; p <= end; p++) {
            ul.appendChild(create(String(p), p, false, p === current));
        }

        // Next and Last
        ul.appendChild(create('»', Math.min(total, current + 1), current === total));
        ul.appendChild(create('Last', total, current === total));

        nav.appendChild(ul);
        paginationContainer.appendChild(nav);
    }
}

// Build numbered pagination for Available Resources
// old renderResourcesPagination removed (simplified above)

// Load incoming requests to current municipality for Resource Requests tab
// Pagination state for Resource Requests tab
let requestsCurrentPage = 1;
const requestsPageSize = 10;
// Resource Requests inner-tab mode: 'active' | 'history'
let resourceRequestsMode = 'active';

function loadRequests(page = 1) {
    console.log('Loading incoming requests to current municipality...');
    const requests = window.requestsData || [];
    const currentUser = window.currentUserDRRMOName;
    
    console.log('All requests data:', requests);
    console.log('Current user municipality:', currentUser);
    console.log('Number of total requests:', requests.length);
    
    const includeHistory = (resourceRequestsMode === 'history');
    // Filter to show only incoming requests (requests TO current municipality)
    // Support both requestType and isIncomingRequest (API vs PHP payload)
    let incomingRequests = requests.filter(request => {
        const isIncoming = request.requestType === 'incoming' || request.isIncomingRequest === true;
        return isIncoming;
    });
    const activeStatuses = ['pending', 'approved', 'fulfilled', 'return pending'];
    if (includeHistory) {
        incomingRequests = incomingRequests.filter(r => {
            const override = window.requestStatusOverride && window.requestStatusOverride[r.id];
            const s = String(override || r.status || '').toLowerCase();
            return s === 'returned' || s === 'rejected' || !activeStatuses.includes(s);
        });
    } else {
        // Active: keep full groups together - if any member is active (e.g. return pending), include whole group
        // so confirming one return doesn't make the rest vanish
        const activeItems = incomingRequests.filter(r => {
            const override = window.requestStatusOverride && window.requestStatusOverride[r.id];
            const s = String(override || r.status || '').toLowerCase();
            return activeStatuses.includes(s);
        });
        const groupIdsWithActive = new Set(activeItems.map(r => r.requestGroupId).filter(Boolean));
        incomingRequests = incomingRequests.filter(r => {
            const override = window.requestStatusOverride && window.requestStatusOverride[r.id];
            const s = String(override || r.status || '').toLowerCase();
            if (activeStatuses.includes(s)) return true;
            if (r.requestGroupId && groupIdsWithActive.has(r.requestGroupId)) return true;
            return false;
        });
    }
    
    console.log('Incoming requests to current municipality:', incomingRequests);
    console.log('Number of incoming requests:', incomingRequests.length);

    // Pagination slice - keep full groups on the same page (don't split a group across pages)
    requestsCurrentPage = Math.max(1, page);
    const total = incomingRequests.length;
    const totalPagesLocal = Math.max(1, Math.ceil(total / requestsPageSize));
    if (requestsCurrentPage > totalPagesLocal) requestsCurrentPage = totalPagesLocal;
    const start = (requestsCurrentPage - 1) * requestsPageSize;
    const end = start + requestsPageSize;
    let pageItems = incomingRequests.slice(start, end);
    const groupIdsOnPage = new Set(pageItems.map(r => r.requestGroupId).filter(Boolean));
    if (groupIdsOnPage.size > 0) {
        const pageItemIds = new Set(pageItems.map(r => r.id));
        const extra = incomingRequests.filter(r => r.requestGroupId && groupIdsOnPage.has(r.requestGroupId) && !pageItemIds.has(r.id));
        if (extra.length) {
            pageItems = [...pageItems, ...extra];
            pageItems.sort((a, b) => incomingRequests.findIndex(x => x.id === a.id) - incomingRequests.findIndex(x => x.id === b.id));
        }
        // Keep groups expanded when they appear on this page (so group stays visible like in other parts)
        if (!window.expandedResourceRequestGroups) window.expandedResourceRequestGroups = new Set();
        groupIdsOnPage.forEach(gid => window.expandedResourceRequestGroups.add(gid));
    }

    // Choose target tbody based on current mode and clear the other
    const targetBodyId = resourceRequestsMode === 'history' ? 'resourceRequestsHistoryBody' : 'resourceRequestsActiveBody';
    const otherBodyId = resourceRequestsMode === 'history' ? 'resourceRequestsActiveBody' : 'resourceRequestsHistoryBody';
    const otherBody = document.getElementById(otherBodyId);
    if (otherBody) otherBody.innerHTML = '';

    displayRequests(pageItems, start, targetBodyId);
    renderRequestsPagination(requestsCurrentPage, totalPagesLocal);
    
    // Clean up global expanded groups to only include groups that still exist
    const currentGroupIds = new Set();
    incomingRequests.forEach(request => {
        if (request.requestGroupId) {
            currentGroupIds.add(request.requestGroupId);
        }
    });
    
    // Remove any stored expanded groups that no longer exist in the current data
    if (window.expandedResourceRequestGroups) {
        for (const groupId of [...window.expandedResourceRequestGroups]) {
            if (!currentGroupIds.has(groupId)) {
                window.expandedResourceRequestGroups.delete(groupId);
            }
        }
    }
}

// Display requests in Resource Requests tab
function displayRequests(requests, offsetStart = 0, targetBodyId = 'resourceRequestsActiveBody') {
    console.log('displayRequests called with:', requests);
    const tbody = document.getElementById(targetBodyId);
    console.log('Table body element:', tbody);
    
    if (!tbody) {
        console.log('Table body not found!');
        return;
    }
    
    if (requests.length === 0) {
        const isHistory = (targetBodyId === 'resourceRequestsHistoryBody');
        const emptyMsg = isHistory
            ? 'No history yet. Returned and rejected requests from other municipalities will appear here.'
            : 'No requests found';
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">' + emptyMsg + '</td></tr>';
        return;
    }
    
    console.log('Displaying', requests.length, 'requests');
    
    // Function to normalize municipality names (consistent with PHP formatting)
    function normalizeMunicipalityName(name) {
        let n = name || '';
        // Remove prefix variants like CDRRMO, MDRRMO, etc.
        n = n.replace(/^(?:[A-Z]{0,3}DRRMO\s+)/, '');
        // Remove suffix " DRRMO"
        n = n.replace(/\s+DRRMO$/, '');
        // Remove leading descriptors
        n = n.replace(/^(City of\s+|Municipality of\s+)/i, '');
        // Remove trailing " City"
        n = n.replace(/\s+City$/i, '');
        return n.trim();
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
    
    // Use globally stored expanded groups for Resource Requests tab
    if (!window.expandedResourceRequestGroups) {
        window.expandedResourceRequestGroups = new Set();
    }
    const expandedGroups = window.expandedResourceRequestGroups;
    
    // Build HTML with grouped and ungrouped requests
    let htmlContent = '';
    
    // Render grouped requests
    Object.keys(groupedRequests).forEach(groupId => {
        const group = groupedRequests[groupId];
        const groupCount = group.length;
        
        // Check if this group was previously expanded
        const wasExpanded = expandedGroups.has(groupId);
        
        // Group header row
        htmlContent += `
            <tr class="table-group-header" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left: 4px solid #1976d2; border-bottom: 2px solid #90caf9;">
                <td colspan="7" class="py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="material-icons me-2" style="vertical-align: middle; font-size: 20px; color: #1565c0;">folder_open</span>
                            <strong style="color: #1565c0;">Grouped Request</strong>
                            <span class="badge bg-primary ms-2" style="font-size: 11px;">${groupCount} resource${groupCount > 1 ? 's' : ''}</span>
                            <span class="text-muted ms-2" style="font-size: 11px;">${groupId}</span>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="toggleResourceRequestGroup('${groupId}')" type="button">
                            <span class="material-icons" id="resource-req-icon-${groupId}" style="font-size: 18px;">${wasExpanded ? 'expand_less' : 'expand_more'}</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        
        // Group request rows - show based on whether group was expanded
        group.forEach((request, index) => {
            const override = window.requestStatusOverride && window.requestStatusOverride[request.id];
            const effectiveStatus = override ? override : (request.status || '');
            const isPending = String(effectiveStatus || '').toLowerCase() === 'pending';
            const fromMunicipality = normalizeMunicipalityName(request.municipality || '');
            const toMunicipality = normalizeMunicipalityName(request.toMunicipality || '');
            
            htmlContent += `
                <tr data-request-id="${request.id}" data-group-id="${groupId}" class="group-${groupId}" style="display: ${wasExpanded ? '' : 'none'}; border-left: 4px solid #90caf9; background-color: #fafcff;">
                    <td class="text-center">REQ-${request.id}</td>
                    <td class="text-center">${request.name || 'N/A'}</td>
                    <td class="text-center">
                        <span class="text-primary">${fromMunicipality || 'N/A'} → ${toMunicipality || 'N/A'}</span>
                    </td>
                    <td class="text-center">${request.quantity || 0} ${request.unit || ''}</td>
                    <td class="text-center">
                        <span class="badge bg-${getRequestStatusClass(effectiveStatus)}">${(() => {
                            const s = String(effectiveStatus||'').toLowerCase();
                            if (s === 'fulfilled') return 'received';
                            if (s === 'pending_head_approval') return 'Awaiting Head';
                            return effectiveStatus || 'N/A';
                        })()}</span>
                    </td>
                    <td class="text-center">${formatDate(request.requestDate)}</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            ${isPending ? `
                                <button class="btn btn-xs btn-success" onclick="acceptRequest(${request.id})" title="Accept Request" style="padding: 2px 6px; font-size: 10px;">
                                    <span class="material-icons" style="font-size: 12px;">check</span>
                                </button>
                                <button class="btn btn-xs btn-danger" onclick="showRejectModal(${request.id})" title="Reject Request" style="padding: 2px 6px; font-size: 10px;">
                                    <span class="material-icons" style="font-size: 12px;">close</span>
                                </button>
                            ` : ''}
                            ${String(effectiveStatus||'').toLowerCase()==='return pending' ? `
                                <button class="btn btn-xs btn-primary" onclick="confirmReturn(${request.id})" title="Confirm Return" style="padding: 2px 6px; font-size: 10px;">
                                    <span class="material-icons" style="font-size: 12px;">assignment_turned_in</span>
                                </button>
                            ` : ''}
                            <button class="btn btn-xs btn-info btn-view-details" data-request-id="${request.id}" title="View Details" style="padding: 2px 6px; font-size: 10px;">
                                <span class="material-icons" style="font-size: 12px;">visibility</span>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        // Add separator row after each group
        htmlContent += `
            <tr class="group-separator group-${groupId}" style="display: ${wasExpanded ? '' : 'none'}; height: 0; border: none;">
                <td colspan="7" style="padding: 0; border-top: 3px solid #e0e0e0; background: #f5f5f5; height: 4px;"></td>
            </tr>
        `;
    });
    
    // Render ungrouped requests
    ungroupedRequests.forEach(request => {
        const override = window.requestStatusOverride && window.requestStatusOverride[request.id];
        const effectiveStatus = override ? override : (request.status || '');
        const isPending = String(effectiveStatus || '').toLowerCase() === 'pending';
        const fromMunicipality = normalizeMunicipalityName(request.municipality || '');
        const toMunicipality = normalizeMunicipalityName(request.toMunicipality || '');
        htmlContent += `
        <tr data-request-id="${request.id}">
            <td class="text-center">REQ-${request.id}</td>
            <td class="text-center">${request.name || 'N/A'}</td>
            <td class="text-center">
                <span class="text-primary">${fromMunicipality || 'N/A'} → ${toMunicipality || 'N/A'}</span>
            </td>
            <td class="text-center">${request.quantity || 0} ${request.unit || ''}</td>
            <td class="text-center">
                <span class="badge bg-${getRequestStatusClass(effectiveStatus)}">${(() => {
                    const s = String(effectiveStatus||'').toLowerCase();
                    if (s === 'fulfilled') return 'received';
                    if (s === 'pending_head_approval') return 'Awaiting Head';
                    return effectiveStatus || 'N/A';
                })()}</span>
            </td>
            <td class="text-center">${formatDate(request.requestDate)}</td>
            <td class="text-center">
                <div class="btn-group" role="group">
                    ${isPending ? `
                        <button class="btn btn-xs btn-success" onclick="acceptRequest(${request.id})" title="Accept Request" style="padding: 2px 6px; font-size: 10px;">
                            <span class="material-icons" style="font-size: 12px;">check</span>
                        </button>
                        <button class="btn btn-xs btn-danger" onclick="showRejectModal(${request.id})" title="Reject Request" style="padding: 2px 6px; font-size: 10px;">
                            <span class="material-icons" style="font-size: 12px;">close</span>
                        </button>
                    ` : ''}
                    ${String(effectiveStatus||'').toLowerCase()==='return pending' ? `
                        <button class="btn btn-xs btn-primary" onclick="confirmReturn(${request.id})" title="Confirm Return" style="padding: 2px 6px; font-size: 10px;">
                            <span class="material-icons" style="font-size: 12px;">assignment_turned_in</span>
                        </button>
                    ` : ''}
                    <button class="btn btn-xs btn-info btn-view-details" data-request-id="${request.id}" title="View Details" style="padding: 2px 6px; font-size: 10px;">
                        <span class="material-icons" style="font-size: 12px;">visibility</span>
                    </button>
                </div>
            </td>
        </tr>
        `;
    });
    
    tbody.innerHTML = htmlContent;
}

// Render paginator like: 1 2 3 4 5 … » Last »
function renderRequestsPagination(current, total) {
    const ul = document.getElementById('requestsPagination');
    if (!ul) return;
    ul.innerHTML = '';

    const createItem = (label, disabled, active, onClick) => {
        const li = document.createElement('li');
        li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
        const a = document.createElement('button');
        a.className = 'page-link';
        a.type = 'button';
        a.textContent = label;
        if (!disabled && !active && onClick) a.addEventListener('click', onClick);
        li.appendChild(a);
        return li;
    };

    // If only one page, hide
    if (total <= 1) {
        ul.style.display = 'none';
        return;
    }
    ul.style.display = '';

    // Show up to first 5 pages, then ellipsis, then next/last
    const maxVisible = 5;
    const visibleEnd = Math.min(total, maxVisible);

    for (let p = 1; p <= visibleEnd; p++) {
        ul.appendChild(createItem(String(p), false, p === current, () => loadRequests(p)));
    }

    if (total > maxVisible) {
        // Ellipsis
        const ellipsis = document.createElement('li');
        ellipsis.className = 'page-item disabled';
        const span = document.createElement('span');
        span.className = 'page-link';
        span.textContent = '…';
        ellipsis.appendChild(span);
        ul.appendChild(ellipsis);

        // Next »
        ul.appendChild(createItem('»', current >= total, false, () => loadRequests(Math.min(total, current + 1))));
        // Last »
        ul.appendChild(createItem('Last »', current >= total, false, () => loadRequests(total)));
    } else {
        // Next and Last still useful when not exceeding maxVisible
        ul.appendChild(createItem('»', current >= total, false, () => loadRequests(Math.min(total, current + 1))));
        ul.appendChild(createItem('Last »', current >= total, false, () => loadRequests(total)));
    }
}

// Load available resources
function loadAvailableResources() {
    console.log('Loading available resources (delegating to paginated loader)...');
    // Use the unified paginated loader so pagination renders on first load
    loadResources(1);
}

// Silent background auto-refresh - only when tab is visible, no visual indicators
let autoRefreshTimer = null;
let autoRefreshMs = 10000;

function startAutoRefresh() {
    // Stop existing timer
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }

    const computeInterval = () => {
        const activeMain = document.querySelector('#requestTabs .nav-link.active');
        const activeId = activeMain ? activeMain.id : '';
        if (activeId === 'who-requested-tab') return 5000;            // Resource Requests: 5s
        if (activeId === 'borrowed-resources-tab') return 5000;       // Borrowed Resources: 5s
        if (activeId === 'your-requests-tab') return 7000;            // My Requests: 7s
        if (activeId === 'available-resources-tab') return 15000;     // Available Resources: 15s
        return 10000; // fallback
    };

    const silentRefresh = async () => {
        // Don't refresh if tab is hidden (saves resources)
        if (document.hidden) {
            return;
        }

        try {
            // First, silently refresh the data source
            const response = await fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            if (data && data.success && data.data && Array.isArray(data.data.requests)) {
                window.requestsData = data.data.requests;
                
                // Update tab badges with new data
                if (typeof updateTabBadges === 'function') {
                    updateTabBadges();
                }
                // Then silently update the active tab (no visual indicators)
                const activeMain = document.querySelector('#requestTabs .nav-link.active');
                const activeId = activeMain ? activeMain.id : '';
                
                if (activeId === 'who-requested-tab') {
                    loadRequests(window.requestsCurrentPage || 1);
                } else if (activeId === 'your-requests-tab') {
                    loadUserRequests();
                } else if (activeId === 'borrowed-resources-tab') {
                    loadBorrowedRequests();
                } else if (activeId === 'available-resources-tab') {
                    loadAvailableResources();
                }
            }
        } catch(err) {
            // Silently fail - no console noise in production
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                console.warn('Silent refresh failed:', err);
            }
        }
    };

    // Compute interval based on active tab
    autoRefreshMs = computeInterval();
    
    // Start timer
    autoRefreshTimer = setInterval(silentRefresh, autoRefreshMs);
    
    // Also refresh immediately when tab becomes visible again
    document.addEventListener('visibilitychange', function visibilityHandler() {
        if (!document.hidden) {
            // Refresh immediately when tab becomes visible
            silentRefresh();
        }
    }, { once: false });
}

/**
 * ==================================================================================
 * MY REQUESTS - GROUP MULTIPLE RESOURCE REQUESTS WITH SINGLE LETTER
 * ==================================================================================
 * 
 * Plan Document: Group Multiple Resource Requests with Single Letter Generation
 * 
 * Overview:
 * When multiple resources are requested together, they should be:
 * 1. Grouped together with a shared requestGroupId
 * 2. Approved/rejected individually (each resource can be processed separately)
 * 3. Generate ONE formal letter that lists all resources in the group
 * 
 * Architecture Flow:
 * - User Submits Multiple Resources
 * - Generate Unique Group ID (e.g., GRP-{timestamp}-{random})
 * - Create Separate Request Records
 * - Assign Same requestGroupId to All
 * - Store Individual requestIDs
 * - Approval Stage: Group Displayed Together
 * - Individual Approve/Reject Buttons (Each Request Processed Separately)
 * - Generate Single Letter for Group (Letter Lists All Resources in Group)
 * 
 * Implementation Details:
 * 
 * 1. Database Schema:
 *    - requestGroupId column added to requests table (VARCHAR, nullable)
 *    - For single requests, requestGroupId will be NULL
 *    - For grouped requests, all requests share the same requestGroupId
 * 
 * 2. Request Submission (config/submit_request.php):
 *    - Generate unique requestGroupId for multiple resource requests
 *    - Format: GRP-{timestamp}-{random}
 *    - Assign same requestGroupId to all requests in the group
 *    - Return requestGroupId in success response along with requestIDs
 * 
 * 3. PDF Letter Generation (dashboards/pages/pdf_viewer.php):
 *    - Accepts both id (single request) and groupId (multiple requests) parameters
 *    - If groupId provided, fetches ALL requests with that requestGroupId
 *    - Generates single letter listing all resources in structured format:
 *      - Each resource shows: name, quantity, priority, unit
 *      - Shared details: delivery date, location, purpose, contact info, etc.
 * 
 * 4. Approval Interface (dashboards/pages/approving_authority/approvals.php):
 *    - Groups requests by requestGroupId in display
 *    - Shows group header: "Request Group: GRP-XXXXX (N resources)"
 *    - Expandable/collapsible sections
 *    - Individual request rows with separate approve/reject buttons
 * 
 * 5. Frontend Display (this file - requests.php):
 *    - displayUserRequests() groups requests by requestGroupId
 *    - Shows group headers with expand/collapse functionality
 *    - Uses window.expandedUserRequestGroups to preserve expanded state
 *    - Groups with only one request are displayed as ungrouped
 * 
 * 6. Document Generation:
 *    - When requestGroupId is present, use it to open letter
 *    - URL format: pdf_viewer.php?groupId={requestGroupId}
 *    - Falls back to first requestID if requestGroupId not available (backward compatibility)
 * 
 * Backward Compatibility:
 * - Single resource requests continue to work (requestGroupId = NULL)
 * - Existing requests without requestGroupId display correctly
 * - Letter generation handles both single and grouped requests
 * 
 * Benefits:
 * - Efficiency: One letter instead of multiple letters for grouped requests
 * - Clarity: Approving authority sees related requests together
 * - Flexibility: Still allows individual approval/rejection per resource
 * - Professional: Single formal letter for a coordinated request
 * 
 * Files Modified:
 * 1. config/submit_request.php - Add requestGroupId column, generate group ID
 * 2. dashboards/pages/pdf_viewer.php - Accept groupId, generate multi-resource letter
 * 3. dashboards/pages/approving_authority/approvals.php - Group display in approval interface
 * 4. dashboards/pages/municipality/requests.php - Group display in My Requests (this file)
 * 5. config/get_pending_approvals.php - Include requestGroupId in query and response
 * 
 * ==================================================================================
 */

// Update tab badge counts to show how many requests are in each tab
function updateTabBadges() {
    const requests = window.requestsData || [];
    const currentUser = window.currentUserDRRMOName;
    
    // Count "Resource Requests" (incoming pending requests)
    const incomingRequests = requests.filter(r => {
        const override = window.requestStatusOverride && window.requestStatusOverride[r.id];
        const status = String(override || r.status || '').toLowerCase();
        const isIncoming = r.requestType === 'incoming' || (!r.isOwnRequest && r.requestType !== 'other');
        const isPending = status === 'pending';
        return isIncoming && isPending;
    });
    
    // Count "My Requests" (own outgoing requests, active ones only)
    const myRequests = requests.filter(r => {
        const override = window.requestStatusOverride && window.requestStatusOverride[r.id];
        const status = String(override || r.status || '').toLowerCase();
        return r.isOwnRequest && 
               status !== 'group_approved_pending' && 
               status !== 'group_rejected_pending' &&
               (status === 'pending' || status === 'pending_head_approval' || status === 'approved' || status === 'fulfilled');
    });
    
    // Count "Borrowed Resources" (approved/fulfilled own requests needing action)
    const borrowedRequests = requests.filter(r => {
        const override = window.requestStatusOverride && window.requestStatusOverride[r.id];
        const status = String(override || r.status || '').toLowerCase();
        return r.isOwnRequest && (status === 'fulfilled' || status === 'approved' || status === 'return pending');
    });
    
    // Update badge elements
    const updateBadge = (id, count, highlightIfPending) => {
        const el = document.getElementById(id);
        if (!el) return;
        if (count > 0) {
            el.textContent = count;
            el.style.display = 'inline-block';
        } else {
            el.style.display = 'none';
        }
    };
    
    updateBadge('resourceRequestsCount', incomingRequests.length);
    updateBadge('myRequestsCount', myRequests.length);
    updateBadge('borrowedResourcesCount', borrowedRequests.length);
}

// Load user's requests
function loadUserRequests() {
    console.log('Loading user requests...');
    
    // Validate window.requestsData
    if (!window.requestsData) {
        console.warn('window.requestsData is not available, initializing empty array');
        window.requestsData = [];
    }
    
    if (!Array.isArray(window.requestsData)) {
        console.error('window.requestsData is not an array:', window.requestsData);
        window.requestsData = [];
    }
    
    const requests = window.requestsData || [];
    const currentUser = window.currentUserDRRMOName;
    
    console.log('All requests:', requests);
    console.log('Current user:', currentUser);
    console.log('Total requests count:', requests.length);
    
    // Debug each request
    requests.forEach((request, index) => {
        console.log(`Request ${index}:`, {
            id: request.id,
            municipality: request.municipality,
            toMunicipality: request.toMunicipality,
            isOwnRequest: request.isOwnRequest
        });
    });
    
    // Filter requests for current user
    let userRequests = requests.filter(request => request.isOwnRequest);
    
    // Keep pending_head_approval requests in 'Your Requests' so the user can see them
    userRequests = userRequests.filter(r => {
        const override = window.requestStatusOverride && window.requestStatusOverride[r.id];
        const status = String(override || r.status || '').toLowerCase();
        // Allow pending_head_approval, but maybe still filter out the group_ ones if they are internal
        return status !== 'group_approved_pending' && status !== 'group_rejected_pending';
    });
    
    // Hide received/returned by default unless history is toggled
    try {
        const showHistory = document.getElementById('myRequestsShowHistory');
        const includeHistory = !!(showHistory && showHistory.checked);
        if (!includeHistory) {
            userRequests = userRequests.filter(r => {
                const override = window.requestStatusOverride && window.requestStatusOverride[r.id];
                const status = String(override || r.status || '').toLowerCase();
                return status === 'pending' || status === 'pending_head_approval' || status === 'approved' || status === 'fulfilled';
            });
        }
    } catch(_) {}
    console.log('User requests:', userRequests);
    
    displayUserRequests(userRequests);
    
    // Clean up global expanded groups to only include groups that still exist
    const currentGroupIds = new Set();
    userRequests.forEach(request => {
        if (request.requestGroupId) {
            currentGroupIds.add(request.requestGroupId);
        }
    });
    
    // Remove any stored expanded groups that no longer exist in the current data
    for (const groupId of [...window.expandedUserRequestGroups]) {  // Create array copy to safely iterate
        if (!currentGroupIds.has(groupId)) {
            window.expandedUserRequestGroups.delete(groupId);
        }
    }
}

// Display user's requests in table
function displayUserRequests(requests) {
    console.log('displayUserRequests called with:', requests);
    
    // Validate inputs
    if (!Array.isArray(requests)) {
        console.error('displayUserRequests: requests is not an array:', requests);
        return;
    }
    
    const tbody = document.getElementById('yourRequestsTableBody');
    if (!tbody) {
        console.error('yourRequestsTableBody not found in DOM');
        // Show error to user
        const container = document.querySelector('.tab-pane#your-requests .request-table-container');
        if (container) {
            container.innerHTML = '<div class="alert alert-danger">Error: Unable to load table. Please refresh the page.</div>';
        }
        return;
    }
    
    console.log('Table body found:', tbody);
    console.log('Number of requests to display:', requests.length);
    
    if (requests.length === 0) {
        console.log('No requests to display, showing empty message');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <span class="material-icons text-muted" style="font-size: 48px;">assignment</span>
                    <p class="text-muted mt-2">No requests found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    // Function to normalize municipality names (consistent with PHP formatting)
    function normalizeMunicipalityName(name) {
        let n = name || '';
        // Remove prefix variants like CDRRMO, MDRRMO, etc.
        n = n.replace(/^(?:[A-Z]{0,3}DRRMO\s+)/, '');
        // Remove suffix " DRRMO"
        n = n.replace(/\s+DRRMO$/, '');
        // Remove leading descriptors
        n = n.replace(/^(City of\s+|Municipality of\s+)/i, '');
        // Remove trailing " City"
        n = n.replace(/\s+City$/i, '');
        return n.trim();
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
    
    // Use globally stored expanded groups
    const expandedGroups = window.expandedUserRequestGroups || new Set();
    
    // Build HTML with grouped and ungrouped requests
    let htmlContent = '';
    
    // Render grouped requests
    Object.keys(groupedRequests).forEach(groupId => {
        const group = groupedRequests[groupId];
        const groupCount = group.length;
        
        // Check if this group was previously expanded
        const wasExpanded = expandedGroups.has(groupId);
        
        // Group header row
        htmlContent += `
            <tr class="table-group-header" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left: 4px solid #1976d2; border-bottom: 2px solid #90caf9;">
                <td colspan="8" class="py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="material-icons me-2" style="vertical-align: middle; font-size: 20px; color: #1565c0;">folder_open</span>
                            <strong style="color: #1565c0;">Grouped Request</strong>
                            <span class="badge bg-primary ms-2" style="font-size: 11px;">${groupCount} resource${groupCount > 1 ? 's' : ''}</span>
                            <span class="text-muted ms-2" style="font-size: 11px;">${groupId}</span>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="toggleUserRequestGroup('${groupId}')" type="button">
                            <span class="material-icons" id="user-req-icon-${groupId}" style="font-size: 18px;">${wasExpanded ? 'expand_less' : 'expand_more'}</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        
        // Group request rows - show based on whether group was expanded
        group.forEach((request, index) => {
            const override = window.requestStatusOverride && window.requestStatusOverride[request.id];
            const effectiveStatus = override ? override : (request.status || '');
            const toMunicipality = normalizeMunicipalityName(request.toMunicipality || '');
            
            htmlContent += `
                <tr data-request-id="${request.id}" data-group-id="${groupId}" class="group-${groupId}" style="display: ${wasExpanded ? '' : 'none'}; border-left: 4px solid #90caf9; background-color: #fafcff;">
                    <td class="text-center">REQ-${request.id}</td>
                    <td class="text-center">${request.name || 'N/A'}</td>
                    <td class="text-center">${toMunicipality || 'N/A'}</td>
                    <td class="text-center">${request.quantity || 0} ${request.unit || ''}</td>
                    <td class="text-center">
                        <span class="badge bg-${getPriorityClass(request.priority)}">${request.priority || 'N/A'}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-${getRequestStatusClass(effectiveStatus)}">${(() => {
                            const s = String(effectiveStatus||'').toLowerCase();
                            if (s === 'fulfilled') return 'received';
                            if (s === 'pending_head_approval') return 'Awaiting Head';
                            return String(effectiveStatus||'') || 'N/A';
                        })()}</span>
                    </td>
                    <td class="text-center">${formatDate(request.requestDate)}</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            ${String(effectiveStatus||'').toLowerCase()==='pending_head_approval' ? `
                                <button class="btn btn-xs btn-warning" onclick="bypassHeadApproval(${request.id})" title="Bypass Approval & Forward" style="padding: 2px 6px; font-size: 10px;">
                                    <span class="material-icons" style="font-size: 12px;">fast_forward</span>
                                </button>
                            ` : ''}
                            ${String(effectiveStatus||'').toLowerCase()==='pending' ? `
                                <button class="btn btn-xs btn-danger" onclick="deleteMyRequest(${request.id})" title="Delete Request" style="padding: 2px 6px; font-size: 10px;">
                                    <span class="material-icons" style="font-size: 12px;">delete</span>
                                </button>
                            ` : ''}
                            ${String(effectiveStatus||'').toLowerCase()==='approved' ? `
                                <button class="btn btn-xs btn-success" data-action="received" onclick="markRequestReceived(${request.id})" title="Mark as Received" style="padding: 2px 6px; font-size: 10px;">
                                    <span class="material-icons" style="font-size: 12px;">done_all</span>
                                </button>
                            ` : ''}
                            <button class="btn btn-xs btn-info btn-view-details" data-request-id="${request.id}" title="View Details" style="padding: 2px 6px; font-size: 10px;">
                                <span class="material-icons" style="font-size: 12px;">visibility</span>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        // Add separator row after each group
        htmlContent += `
            <tr class="group-separator group-${groupId}" style="display: ${wasExpanded ? '' : 'none'}; height: 0; border: none;">
                <td colspan="8" style="padding: 0; border-top: 3px solid #e0e0e0; background: #f5f5f5; height: 4px;"></td>
            </tr>
        `;
    });
    
    // Render ungrouped requests
    ungroupedRequests.forEach(request => {
        const override = window.requestStatusOverride && window.requestStatusOverride[request.id];
        const effectiveStatus = override ? override : (request.status || '');
        const toMunicipality = normalizeMunicipalityName(request.toMunicipality || '');
        
        htmlContent += `
            <tr data-request-id="${request.id}">
                <td class="text-center">REQ-${request.id}</td>
                <td class="text-center">${request.name || 'N/A'}</td>
                <td class="text-center">${toMunicipality || 'N/A'}</td>
                <td class="text-center">${request.quantity || 0} ${request.unit || ''}</td>
                <td class="text-center">
                    <span class="badge bg-${getPriorityClass(request.priority)}">${request.priority || 'N/A'}</span>
                </td>
                <td class="text-center">
                    <span class="badge bg-${getRequestStatusClass(effectiveStatus)}">${(() => {
                        const s = String(effectiveStatus||'').toLowerCase();
                        if (s === 'fulfilled') return 'received';
                        if (s === 'pending_head_approval') return 'Awaiting Head';
                        return String(effectiveStatus||'') || 'N/A';
                    })()}</span>
                </td>
                <td class="text-center">${formatDate(request.requestDate)}</td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        ${String(effectiveStatus||'').toLowerCase()==='pending_head_approval' ? `
                            <button class="btn btn-xs btn-warning" onclick="bypassHeadApproval(${request.id})" title="Bypass Approval & Forward" style="padding: 2px 6px; font-size: 10px;">
                                <span class="material-icons" style="font-size: 12px;">fast_forward</span>
                            </button>
                        ` : ''}
                        ${String(effectiveStatus||'').toLowerCase()==='pending' ? `
                            <button class="btn btn-xs btn-danger" onclick="deleteMyRequest(${request.id})" title="Delete Request" style="padding: 2px 6px; font-size: 10px;">
                                <span class="material-icons" style="font-size: 12px;">delete</span>
                            </button>
                        ` : ''}
                        ${String(effectiveStatus||'').toLowerCase()==='approved' ? `
                            <button class="btn btn-xs btn-success" data-action="received" onclick="markRequestReceived(${request.id})" title="Mark as Received" style="padding: 2px 6px; font-size: 10px;">
                                <span class="material-icons" style="font-size: 12px;">done_all</span>
                            </button>
                        ` : ''}
                        <button class="btn btn-xs btn-info btn-view-details" data-request-id="${request.id}" title="View Details" style="padding: 2px 6px; font-size: 10px;">
                            <span class="material-icons" style="font-size: 12px;">visibility</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    console.log('Generated HTML:', htmlContent);
    tbody.innerHTML = htmlContent;
    console.log('Table body innerHTML set, current content:', tbody.innerHTML);
}

// Load borrowed (outgoing) approved/fulfilled requests into Borrowed Resources tab
function loadBorrowedRequests() {
    const requests = window.requestsData || [];
    // Normalize current user DRRMO name to plain municipality (strip prefixes/suffixes)
    const normalizeName = (n) => {
        try {
            let s = String(n || '');
            s = s.replace(/^(?:[A-Z]{0,3}DRRMO\s+)/, '');
            s = s.replace(/\s+DRRMO$/, '');
            s = s.replace(/^(City of\s+|Municipality of\s+)/i, '');
            s = s.replace(/\s+City$/i, '');
            return s.trim();
        } catch(_) { return String(n||''); }
    };
    const currentUser = normalizeName(window.currentUserDRRMOName);
    const borrowed = requests.filter(r => {
        const fromMe = r && normalizeName(r.municipality) === currentUser; // requester is current user
        const status = String((window.requestStatusOverride && window.requestStatusOverride[r.id]) || r.status || '').toLowerCase();
        const includeReturned = !!(document.getElementById('borrowedShowHistory') && document.getElementById('borrowedShowHistory').checked);
        if (!fromMe) return false;
        if (includeReturned) {
            return status === 'approved' || status === 'fulfilled' || status === 'received' || status === 'return pending' || status === 'returned';
        }
        return status === 'approved' || status === 'fulfilled' || status === 'received' || status === 'return pending';
    });
    displayBorrowedRequests(borrowed);
    
    // Clean up global expanded groups to only include groups that still exist
    const currentGroupIds = new Set();
    borrowed.forEach(request => {
        if (request.requestGroupId) {
            currentGroupIds.add(request.requestGroupId);
        }
    });
    
    // Remove any stored expanded groups that no longer exist in the current data
    if (window.expandedBorrowedRequestGroups) {
        for (const groupId of [...window.expandedBorrowedRequestGroups]) {
            if (!currentGroupIds.has(groupId)) {
                window.expandedBorrowedRequestGroups.delete(groupId);
            }
        }
    }
}

function displayBorrowedRequests(list) {
    const tbody = document.getElementById('borrowedRequestsTableBody');
    if (!tbody) return;
    if (!Array.isArray(list) || list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No borrowed resources</td></tr>';
        return;
    }
    
    // Function to normalize municipality names (consistent with PHP formatting)
    function normalizeMunicipalityName(name) {
        let n = name || '';
        // Remove prefix variants like CDRRMO, MDRRMO, etc.
        n = n.replace(/^(?:[A-Z]{0,3}DRRMO\s+)/, '');
        // Remove suffix " DRRMO"
        n = n.replace(/\s+DRRMO$/, '');
        // Remove leading descriptors
        n = n.replace(/^(City of\s+|Municipality of\s+)/i, '');
        // Remove trailing " City"
        n = n.replace(/\s+City$/i, '');
        return n.trim();
    }
    
    // Group requests by requestGroupId, but only if there are multiple requests in the group
    const groupedRequests = {};
    const ungroupedRequests = [];
    
    list.forEach(request => {
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
    
    // Use globally stored expanded groups for Borrowed Resources tab
    if (!window.expandedBorrowedRequestGroups) {
        window.expandedBorrowedRequestGroups = new Set();
    }
    const expandedGroups = window.expandedBorrowedRequestGroups;
    
    // Build HTML with grouped and ungrouped requests
    let htmlContent = '';
    
    // Render grouped requests
    Object.keys(groupedRequests).forEach(groupId => {
        const group = groupedRequests[groupId];
        const groupCount = group.length;
        
        // Check if this group was previously expanded
        const wasExpanded = expandedGroups.has(groupId);
        
        // Group header row
        htmlContent += `
            <tr class="table-group-header" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left: 4px solid #1976d2; border-bottom: 2px solid #90caf9;">
                <td colspan="7" class="py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="material-icons me-2" style="vertical-align: middle; font-size: 20px; color: #1565c0;">folder_open</span>
                            <strong style="color: #1565c0;">Grouped Request</strong>
                            <span class="badge bg-primary ms-2" style="font-size: 11px;">${groupCount} resource${groupCount > 1 ? 's' : ''}</span>
                            <span class="text-muted ms-2" style="font-size: 11px;">${groupId}</span>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="toggleBorrowedRequestGroup('${groupId}')" type="button">
                            <span class="material-icons" id="borrowed-req-icon-${groupId}" style="font-size: 18px;">${wasExpanded ? 'expand_less' : 'expand_more'}</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        
        // Group request rows - show based on whether group was expanded
        group.forEach((request, index) => {
            const override = (window.requestStatusOverride && window.requestStatusOverride[request.id]) || null;
            const status = String(override || request.status || '').toLowerCase();
            const canMarkReceived = status === 'approved';
            const canRequestReturn = status === 'fulfilled' || status === 'received';
            const waitingReturn = status === 'return pending';
            const isReturned = status === 'returned';
            const toMunicipality = normalizeMunicipalityName(request.toMunicipality || '');
            
            htmlContent += `
                <tr data-request-id="${request.id}" data-group-id="${groupId}" class="borrowed-group-${groupId}" style="display: ${wasExpanded ? '' : 'none'}; border-left: 4px solid #90caf9; background-color: #fafcff;">
                    <td class="text-center">REQ-${request.id}</td>
                    <td class="text-center">${request.name || 'N/A'}</td>
                    <td class="text-center">${toMunicipality || 'N/A'}</td>
                    <td class="text-center">${request.quantity || 0} ${request.unit || ''}</td>
                    <td class="text-center"><span class="badge bg-${getRequestStatusClass(status)}">${(() => {
                        const s = String(status||'').toLowerCase();
                        if (s === 'fulfilled') return 'received';
                        if (s === 'pending_head_approval') return 'Awaiting Head';
                        return status || 'pending';
                    })()}</span></td>
                    <td class="text-center">${formatDate(request.requestDate)}</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            ${canMarkReceived ? `
                                <button class="btn btn-xs btn-success" data-action="received" onclick="markRequestReceived(${request.id})" title="Mark as Received" style="padding: 2px 6px; font-size: 10px;">
                                    <span class="material-icons" style="font-size: 12px;">done_all</span>
                                </button>
                            ` : ''}
                            ${canRequestReturn ? `
                                <button class="btn btn-xs btn-warning" data-action="return" onclick="requestReturn(${request.id})" title="Return" style="padding: 2px 6px; font-size: 10px;">
                                    <span class="material-icons" style="font-size: 12px;">assignment_return</span>
                                </button>
                            ` : ''}
                            ${waitingReturn ? `
                                <span class="text-muted" style="font-size: 12px;">Waiting confirmation…</span>
                            ` : ''}
                            ${isReturned ? `
                                <span class="text-success" style="font-size: 12px;">Returned</span>
                            ` : ''}
                            <button class="btn btn-xs btn-info btn-view-details" data-request-id="${request.id}" title="View Details" style="padding: 2px 6px; font-size: 10px;">
                                <span class="material-icons" style="font-size: 12px;">visibility</span>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        // Add separator row after each group
        htmlContent += `
            <tr class="group-separator borrowed-group-${groupId}" style="display: ${wasExpanded ? '' : 'none'}; height: 0; border: none;">
                <td colspan="7" style="padding: 0; border-top: 3px solid #e0e0e0; background: #f5f5f5; height: 4px;"></td>
            </tr>
        `;
    });
    
    // Render ungrouped requests
    ungroupedRequests.forEach(r => {
        const override = (window.requestStatusOverride && window.requestStatusOverride[r.id]) || null;
        const status = String(override || r.status || '').toLowerCase();
        const canMarkReceived = status === 'approved';
        const canRequestReturn = status === 'fulfilled' || status === 'received';
        const waitingReturn = status === 'return pending';
        const isReturned = status === 'returned';
        const toMunicipality = normalizeMunicipalityName(r.toMunicipality || '');
        
        htmlContent += `
        <tr data-request-id="${r.id}">
            <td class="text-center">REQ-${r.id}</td>
            <td class="text-center">${r.name || 'N/A'}</td>
            <td class="text-center">${toMunicipality || 'N/A'}</td>
            <td class="text-center">${r.quantity || 0} ${r.unit || ''}</td>
            <td class="text-center"><span class="badge bg-${getRequestStatusClass(status)}">${(() => {
                const s = String(status||'').toLowerCase();
                if (s === 'fulfilled') return 'received';
                if (s === 'pending_head_approval') return 'Awaiting Head';
                return status || 'pending';
            })()}</span></td>
            <td class="text-center">${formatDate(r.requestDate)}</td>
            <td class="text-center">
                <div class="btn-group" role="group">
                    ${canMarkReceived ? `
                        <button class="btn btn-xs btn-success" data-action="received" onclick="markRequestReceived(${r.id})" title="Mark as Received" style="padding: 2px 6px; font-size: 10px;">
                            <span class="material-icons" style="font-size: 12px;">done_all</span>
                        </button>
                    ` : ''}
                    ${canRequestReturn ? `
                        <button class="btn btn-xs btn-warning" data-action="return" onclick="requestReturn(${r.id})" title="Return" style="padding: 2px 6px; font-size: 10px;">
                            <span class="material-icons" style="font-size: 12px;">assignment_return</span>
                        </button>
                    ` : ''}
                    ${waitingReturn ? `
                        <span class="text-muted" style="font-size: 12px;">Waiting confirmation…</span>
                    ` : ''}
                    ${isReturned ? `
                        <span class="text-success" style="font-size: 12px;">Returned</span>
                    ` : ''}
                    <button class="btn btn-xs btn-info btn-view-details" data-request-id="${r.id}" title="View Details" style="padding: 2px 6px; font-size: 10px;">
                        <span class="material-icons" style="font-size: 12px;">visibility</span>
                    </button>
                </div>
            </td>
        </tr>`;
    });
    
    tbody.innerHTML = htmlContent;
}

// Get priority class for badge
function getPriorityClass(priority) {
    const priorityMap = {
        'Low': 'success',
        'Medium': 'warning',
        'High': 'danger',
        'Critical': 'dark'
    };
    return priorityMap[priority] || 'secondary';
}

// Get request status class for badge (normalized)
// NOTE: Prefer the normalized mapper in assets/js/pages/requests.js.
// This remains only as a fallback if that file isn't loaded yet.
function getRequestStatusClass(status) {
    try {
        if (window && window.getRequestStatusClass && typeof window.getRequestStatusClass === 'function') {
            return window.getRequestStatusClass(status);
        }
    } catch (_) {}
    const s = String(status || '').toLowerCase();
    const statusMap = {
        'pending': 'warning',
        'pending_head_approval': 'warning',
        'accepted': 'success',
        'approved': 'success',
        'rejected': 'danger',
        'in progress': 'info',
        'completed': 'primary',
        'cancelled': 'secondary'
    };
    return statusMap[s] || 'secondary';
}

// Toggle user request group visibility (My Requests tab)
function toggleUserRequestGroup(groupId) {
    const rows = document.querySelectorAll(`tr.group-${groupId}`);
    const icon = document.getElementById(`user-req-icon-${groupId}`);
    
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
    
    // Update global expanded groups set
    if (isExpanded) {
        window.expandedUserRequestGroups.delete(groupId);
    } else {
        window.expandedUserRequestGroups.add(groupId);
    }
}

// Toggle resource request group visibility (Resource Requests tab)
function toggleResourceRequestGroup(groupId) {
    const rows = document.querySelectorAll(`tr.group-${groupId}`);
    const icon = document.getElementById(`resource-req-icon-${groupId}`);
    
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
    
    // Update global expanded groups set for Resource Requests
    if (!window.expandedResourceRequestGroups) {
        window.expandedResourceRequestGroups = new Set();
    }
    
    if (isExpanded) {
        window.expandedResourceRequestGroups.delete(groupId);
    } else {
        window.expandedResourceRequestGroups.add(groupId);
    }
}

// Toggle borrowed request group visibility (Borrowed Resources tab)
function toggleBorrowedRequestGroup(groupId) {
    const rows = document.querySelectorAll(`tr.borrowed-group-${groupId}`);
    const icon = document.getElementById(`borrowed-req-icon-${groupId}`);
    
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
    
    // Update global expanded groups set for Borrowed Resources
    if (!window.expandedBorrowedRequestGroups) {
        window.expandedBorrowedRequestGroups = new Set();
    }
    
    if (isExpanded) {
        window.expandedBorrowedRequestGroups.delete(groupId);
    } else {
        window.expandedBorrowedRequestGroups.add(groupId);
    }
}

// New: open Request Details modal by ID and populate
function openRequestDetailsById(rawId) {
    try {
        const modalEl = document.getElementById('requestDetailsModal');
        if (!modalEl) { console.error('Details modal not found'); return; }
        const id = String(rawId || '').replace(/^REQ-/i, '');
        if (!id) { console.error('No request ID provided'); return; }

        // Use absolute path to avoid relative path resolution issues
        const baseUrl = window.location.origin + '/ConnectDRRM';
        fetch(`${baseUrl}/config/get_request_details.php?requestId=${id}`, {
            credentials: 'same-origin'
        })
            .then(r => r.text())
            .then(text => {
                let res;
                try { res = JSON.parse(text); } catch(e) {
                    console.error('Non-JSON response from server:', text);
                    alert('Server returned an unexpected response. Check the browser console.');
                    return;
                }
                if (!res || !res.success || !res.data) {
                    console.error('Failed to fetch request details:', res);
                    alert('Error: ' + (res?.error?.message || 'Could not load request details.'));
                    return;
                }
                const req = res.data;

                // Store for PDF button
                try { window.currentRequestId = req.requestID; } catch(_) {}
                try { currentRequestId = req.requestID; } catch(_) {}
                // Store requestGroupId if available
                try { window.currentRequestGroupId = req.requestGroupId || null; } catch(_) {}
                
                // Populate fields
                const set = (elId, html) => { const el = document.getElementById(elId); if (el) el.innerHTML = html; };
                
                // Function to normalize municipality names (consistent with other functions)
                function normalizeMunicipalityName(name) {
                    let n = name || '';
                    n = n.replace(/^(?:[A-Z]{0,3}DRRMO\s+)/, '');
                    n = n.replace(/\s+DRRMO$/, '');
                    n = n.replace(/^(City of\s+|Municipality of\s+)/i, '');
                    n = n.replace(/\s+City$/i, '');
                    return n.trim();
                }
                
                set('reqModalId', `REQ-${req.requestID}`);
                set('reqModalResource', req.resourceName || 'N/A');
                set('reqModalFrom', normalizeMunicipalityName(req.fromMunicipality || ''));
                set('reqModalTo', normalizeMunicipalityName(req.toMunicipality || ''));
                set('reqModalQty', `${req.quantity || 0} ${req.unit || ''}`.trim());
                const cap = v => { const s = String(v||''); return s ? s.charAt(0).toUpperCase()+s.slice(1) : s; };
                const formatStatusDisplay = (status) => {
                    const s = String(status||'').toLowerCase();
                    if (s === 'pending_head_approval') return 'Awaiting Head';
                    return cap(status) || 'N/A';
                };
                set('reqModalStatus', `<span class="badge bg-${getRequestStatusClass(req.status)}">${formatStatusDisplay(req.status)}</span>`);
                set('reqModalPriority', `<span class="badge bg-${getPriorityClass(req.priority)}">${req.priority || 'N/A'}</span>`);
                set('reqModalRequestDate', req.requestDate ? formatDate(req.requestDate) : 'N/A');
                
                let approveDateText = 'Not approved yet';
                if (req.responseDate) {
                    approveDateText = formatDate(req.responseDate);
                } else {
                    const lowerStatus = String(req.status || '').toLowerCase();
                    if (lowerStatus !== 'pending' && lowerStatus !== 'pending_head_approval' && lowerStatus !== 'group_approved_pending' && lowerStatus !== 'group_rejected_pending') {
                        approveDateText = 'N/A';
                    }
                }
                set('reqModalApproveDate', approveDateText);
                set('reqModalNotes', req.notes || '—');

                // ── Dispatched Units Details ─────────────────────────────────
                const dispSec = document.getElementById('reqModalDispatchedSection');
                const dispList = document.getElementById('reqModalDispatchedList');
                if (dispSec && dispList) {
                    const dispatched = req.dispatchedItems || [];
                    if (dispatched.length > 0) {
                        dispSec.classList.remove('d-none');
                        dispList.innerHTML = dispatched.map(item => `
                            <div class="d-flex justify-content-between align-items-center p-2 rounded border bg-white shadow-sm" style="border: 1px solid #e9ecef!important;">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="material-icons text-primary" style="font-size: 16px;">tag</span>
                                    <span class="fw-bold text-dark">${item.uniqueIdentifier}</span>
                                    <span class="text-muted small ms-2">Loc: ${item.storageLocation || 'N/A'}</span>
                                </div>
                                <div>
                                    <span class="badge bg-${item.status === 'Available' ? 'success' : (item.status === 'In Use' ? 'info' : 'warning')}">${item.status}</span>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        dispSec.classList.add('d-none');
                        dispList.innerHTML = '';
                    }
                }

                // ── Damage Assessment card ─────────────────────────────────────
                const damageSec = document.getElementById('reqModalDamageSection');
                if (damageSec) {
                    const lowerSt    = String(req.status || '').toLowerCase();
                    const damagedQty = parseInt(req.damagedQty) || 0;
                    const hasDamage  = (lowerSt === 'returned');
                    if (hasDamage) {
                        damageSec.classList.remove('d-none');
                        const totalQty = parseInt(req.quantity) || 0;
                        const goodQty  = totalQty - damagedQty;
                        const setDmg = (id, html) => { const el = document.getElementById(id); if (el) el.innerHTML = html; };
                        setDmg('reqModalTotalQty',    totalQty);
                        setDmg('reqModalGoodQty',     goodQty);
                        setDmg('reqModalDamagedQty',  damagedQty);
                        setDmg('reqModalDamageNotes', req.damageAssessment || 'Good condition, no damage.');
                    } else {
                        damageSec.classList.add('d-none');
                    }
                }

                // Populate approval method if request has been approved, rejected, bypassed, or evaluated
                const methodSec = document.getElementById('reqModalApprovalMethodSection');
                if (methodSec) {
                    const lowerStatus = String(req.status || '').toLowerCase();
                    const hasHeadStatus = !!req.headApprovalStatus;
                    const isFinalEvaluated = (lowerStatus === 'approved' || lowerStatus === 'rejected' || lowerStatus === 'fulfilled' || lowerStatus === 'received' || lowerStatus === 'borrowed' || lowerStatus === 'returned');
                    
                    if (hasHeadStatus || isFinalEvaluated) {
                        methodSec.classList.remove('d-none');
                        methodSec.style.display = 'block';
                        
                        let label = 'Authorized';
                        let badgeClass = 'bg-success';
                        let icon = 'verified';
                        let borderColor = '#198754';
                        
                        let approverName = '';
                        let approverTitle = '';
                        let approverSignature = '';
                        
                        if (isFinalEvaluated) {
                            const isApproved = (lowerStatus !== 'rejected');
                            label = isApproved ? 'Approved by Admin' : 'Rejected by Admin';
                            badgeClass = isApproved ? 'bg-success' : 'bg-danger';
                            icon = isApproved ? 'verified' : 'cancel';
                            borderColor = isApproved ? '#198754' : '#dc3545';
                            
                            approverName = req.approvingAuthority || req.headApprovedBy || 'Authorized Officer';
                            approverTitle = req.approverTitle || 'DRRMO Staff';
                            approverSignature = req.approverSignature || '';
                        } else {
                            const isApproved = req.headApprovalStatus === 'approved';
                            label = isApproved ? 'Approved by Head' : 'Bypassed by Head';
                            badgeClass = isApproved ? 'bg-success' : 'bg-warning text-dark';
                            icon = isApproved ? 'verified' : 'fast_forward';
                            borderColor = isApproved ? '#198754' : '#ffc107';
                            
                            approverName = req.headApprovedBy || req.approvingAuthority || 'DRRMO Head';
                            approverTitle = req.approverTitle || (isApproved ? 'DRRMO Head' : 'DRRMO Staff');
                            approverSignature = req.approverSignature || '';
                        }
                        
                        methodSec.style.borderLeft = `4px solid ${borderColor}`;
                        
                        // Badge
                        const badgeEl = document.getElementById('reqModalApprovalBadge');
                        if (badgeEl) {
                            badgeEl.innerHTML = `<span class="badge ${badgeClass} d-flex align-items-center gap-1"><span class="material-icons" style="font-size: 12px;">${icon}</span> ${label}</span>`;
                        }
                        
                        // Authorized By Label
                        const labelEl = document.getElementById('reqModalAuthorizedLabel');
                        if (labelEl) {
                            labelEl.innerText = (lowerStatus === 'rejected') ? 'Rejected By' : 'Approved By';
                        }
                        
                        // Authorized By Name
                        const nameEl = document.getElementById('reqModalAuthorizedName');
                        if (nameEl) {
                            nameEl.innerText = approverName;
                        }
                        
                        // Authorized By Title
                        const titleEl = document.getElementById('reqModalAuthorizedTitle');
                        if (titleEl) {
                            titleEl.innerText = approverTitle;
                        }
                        
                        // Signature
                        const sigContainer = document.getElementById('reqModalSignatureContainer');
                        const sigImg = document.getElementById('reqModalSignature');
                        if (sigContainer && sigImg) {
                            if (approverSignature && approverSignature.trim() !== '') {
                                sigImg.src = approverSignature;
                                sigContainer.classList.remove('d-none');
                                sigContainer.classList.add('d-inline-block');
                            } else {
                                sigContainer.classList.add('d-none');
                                sigContainer.classList.remove('d-inline-block');
                                sigImg.src = '';
                            }
                        }
                    } else {
                        methodSec.classList.add('d-none');
                        methodSec.style.display = 'none';
                    }
                }
                
                // Show modal
                try {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    } else {
                        modalEl.style.display = 'flex';
                        modalEl.classList.add('show');
                        document.body.classList.add('modal-open');
                        const backdrop = document.createElement('div');
                        backdrop.className = 'modal-backdrop fade show';
                        backdrop.id = 'modalBackdrop';
                        document.body.appendChild(backdrop);
                    }
                } catch (e) { console.error('Failed to open modal', e); }
            })
            .catch(err => {
                console.error('Fetch request details error:', err);
                alert('Could not load request details. Please check your connection or try refreshing the page.');
            });
    } catch (e) { console.error('openRequestDetailsById error', e); }
}

// Delegate click for all dynamically rendered view buttons
if (window.__viewDetailsHandler) {
    document.removeEventListener('click', window.__viewDetailsHandler);
}
window.__viewDetailsHandler = function(e) {
    const btn = e.target && e.target.closest && e.target.closest('.btn-view-details');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    const id = btn.getAttribute('data-request-id');
    openRequestDetailsById(id);
};
document.addEventListener('click', window.__viewDetailsHandler);

// Format date for display
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Display resources in table
function displayResources(resources) {
    const tbody = document.getElementById('availableResourcesTableBody');
    if (!tbody) return;
    
    // Store all resources for filtering
    allLoadedResources = resources;
    
    // If a municipality is selected, filter to show only that municipality
    if (selectedMunicipality) {
        resources = resources.filter(resource => resource.municipality === selectedMunicipality);
    }
    
    if (resources.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No resources available' + 
            (selectedMunicipality ? ' from ' + selectedMunicipality : '') + '</td></tr>';
        return;
    }
    
    // Function to normalize municipality names (consistent with PHP formatting)
    function normalizeMunicipalityName(name) {
        let n = name || '';
        // Remove prefix variants like CDRRMO, MDRRMO, etc.
        n = n.replace(/^(?:[A-Z]{0,3}DRRMO\s+)/, '');
        // Remove suffix " DRRMO"
        n = n.replace(/\s+DRRMO$/, '');
        // Remove leading descriptors
        n = n.replace(/^(City of\s+|Municipality of\s+)/i, '');
        // Remove trailing " City"
        n = n.replace(/\s+City$/i, '');
        return n.trim();
    }
    
    tbody.innerHTML = resources.map((resource, index) => {
        const qty = Number(resource.availableStock || 0);
        const damaged = Number(resource.damagedStock || 0);
        const isAvailable = qty > 0 && !(qty === 1 && damaged === 1);
        const statusLabel = isAvailable ? 'Available' : (damaged > 0 ? 'Damaged / Repairing' : 'Unavailable');
        const statusClass = isAvailable ? 'success' : (damaged > 0 ? 'warning text-dark' : 'danger');
        const rawMunicipality = resource.municipality || '';
        const normalizedMunicipality = normalizeMunicipalityName(rawMunicipality);
        const municipality = rawMunicipality.replace(/"/g, '&quot;');
        const resourceId = resource.id || '';
        const resourceName = (resource.resourceName || '').replace(/"/g, '&quot;');
        const checkboxId = `resource-checkbox-${resourceId}-${index}`;
        
        return `
        <tr data-resource-id="${resourceId}" data-municipality="${municipality}" class="resource-row">
            <td title="${resource.resourceName || 'N/A'}">${resource.resourceName || 'N/A'}</td>
            <td title="${rawMunicipality || 'N/A'}">${normalizedMunicipality || 'N/A'}</td>
            <td title="${resource.category || 'N/A'}">${resource.category || 'N/A'}</td>
            <td title="${qty} ${resource.unit || ''}">${qty} ${resource.unit || ''}</td>
            <td>
                <span class="badge bg-${statusClass}" title="${statusLabel}">${statusLabel}</span>
                ${(!isAvailable && resource.nextAvailableDate) ? `
                    <div class="text-muted mt-1" style="font-size: 0.72rem; line-height: 1.1; font-weight: 500;">
                        Available: ${new Date(resource.nextAvailableDate).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'})}
                    </div>
                ` : ''}
            </td>
            <td class="text-center">
                <input type="checkbox" 
                       class="resource-checkbox" 
                       id="${checkboxId}"
                       data-resource-id="${resourceId}"
                       data-resource-name="${resourceName}"
                       data-municipality="${municipality}"
                       data-stock="${qty}"
                       data-unit="${(resource.unit || '').replace(/"/g, '&quot;')}"
                       ${!isAvailable ? 'disabled' : ''}
                       title="${isAvailable ? 'Select this resource' : 'Resource unavailable'}">
            </td>
        </tr>`;
    }).join('');
    
    // Re-bind checkbox event handlers after rendering
    bindResourceCheckboxes();
    
    // Restore selected checkboxes after rendering
    restoreSelectedCheckboxes();
}

function restoreSelectedCheckboxes() {
    // Restore checked state of previously selected resources
    selectedResourceIds.forEach(resourceId => {
        const checkbox = document.querySelector(`.resource-checkbox[data-resource-id="${resourceId}"]`);
        if (checkbox && !checkbox.disabled) {
            checkbox.checked = true;
            const row = checkbox.closest('tr.resource-row');
            if (row) {
                row.classList.add('resource-row-selected');
            }
        }
    });
    
    // Update select all checkbox state
    updateSelectAllCheckboxState();
    
    // Update action bar after restoring checkboxes
    updateActionBar();
}

// Multi-Resource Selection Functions
let selectedMunicipality = null;
let allLoadedResources = []; // Store all loaded resources for filtering
let selectedResourceIds = new Set(); // Store IDs of selected resources to persist across filtering

function bindResourceCheckboxes() {
    // Bind checkbox change handlers
    const checkboxes = document.querySelectorAll('.resource-checkbox');
    checkboxes.forEach(checkbox => {
        // Remove existing listeners by cloning (prevents duplicates)
        const newCheckbox = checkbox.cloneNode(true);
        checkbox.parentNode.replaceChild(newCheckbox, checkbox);
        newCheckbox.addEventListener('change', handleResourceCheckbox);
        
        // Prevent row click from firing when clicking checkbox directly
        newCheckbox.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    // Bind row click handlers - clicking anywhere on the row selects it
    const rows = document.querySelectorAll('tr.resource-row');
    rows.forEach(row => {
        // Check if row already has click handler (prevent duplicates)
        if (row.dataset.rowClickBound === 'true') {
            return;
        }
        row.dataset.rowClickBound = 'true';
        
        row.addEventListener('click', function(event) {
            // Don't trigger if clicking on the checkbox itself
            const target = event.target;
            const isCheckbox = target.type === 'checkbox' || target.closest('input[type="checkbox"]') !== null;
            
            // If clicking on checkbox, let the default behavior handle it
            if (isCheckbox) {
                return;
            }
            
            // Find the checkbox in this row
            const checkbox = row.querySelector('.resource-checkbox');
            if (checkbox && !checkbox.disabled) {
                // Toggle the checkbox
                checkbox.checked = !checkbox.checked;
                // Trigger the change event to update selection state
                checkbox.dispatchEvent(new Event('change'));
            }
        });
    });
    
    // Bind select all checkbox
    const selectAllCheckbox = document.getElementById('selectAllResources');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', handleSelectAllCheckbox);
    }
}

function handleResourceCheckbox(event) {
    const checkbox = event.target;
    const row = checkbox.closest('tr');
    const municipality = checkbox.getAttribute('data-municipality');
    const resourceId = checkbox.getAttribute('data-resource-id');
    const isChecked = checkbox.checked;
    
    if (isChecked) {
        // If this is the first selection, set the selected municipality and filter
        if (!selectedMunicipality) {
            selectedMunicipality = municipality;
            // Add to selected set first
            selectedResourceIds.add(resourceId);
            // Filter table to show only resources from this municipality
            filterResourcesByMunicipality(municipality);
            return; // Exit early, will be restored after reload
        }
        
        // If municipality doesn't match, prevent selection
        if (municipality !== selectedMunicipality) {
            checkbox.checked = false;
            showNotification('All selected resources must be from the same municipality. Clear selection to choose from ' + municipality, 'warning');
            return;
        }
        
        // Add to selected set
        selectedResourceIds.add(resourceId);
        
        // Add selected styling
        row.classList.add('resource-row-selected');
        
        // Update municipality lock
        updateMunicipalityLock();
    } else {
        // Remove from selected set
        selectedResourceIds.delete(resourceId);
        
        // Remove selected styling
        row.classList.remove('resource-row-selected');
        
        // Check if any checkboxes are still selected
        if (selectedResourceIds.size === 0) {
            // No selections left, clear municipality lock and show all resources
            selectedMunicipality = null;
            updateMunicipalityLock();
            // Show all resources again
            filterResourcesByMunicipality(null);
        }
    }
    
    updateActionBar();
}

function handleSelectAllCheckbox(event) {
    const selectAllCheckbox = event.target;
    const isChecked = selectAllCheckbox.checked;
    const tbody = document.getElementById('availableResourcesTableBody');
    if (!tbody) return;
    
    const rows = tbody.querySelectorAll('tr.resource-row');
    let firstMunicipality = null;
    
    // Find the first enabled row's municipality
    rows.forEach(row => {
        const checkbox = row.querySelector('.resource-checkbox:not([disabled])');
        if (checkbox && !firstMunicipality) {
            firstMunicipality = checkbox.getAttribute('data-municipality');
        }
    });
    
    if (!firstMunicipality) return;
    
    if (isChecked) {
        // Clear previous selections if switching municipalities
        if (selectedMunicipality && selectedMunicipality !== firstMunicipality) {
            selectedResourceIds.clear();
        }
        
        // If filtering to a new municipality, filter the table
        if (selectedMunicipality !== firstMunicipality) {
            selectedMunicipality = firstMunicipality;
            // Select all visible resources from this municipality
            rows.forEach(row => {
                const checkbox = row.querySelector('.resource-checkbox:not([disabled])');
                if (checkbox) {
                    const rowMunicipality = checkbox.getAttribute('data-municipality');
                    const resourceId = checkbox.getAttribute('data-resource-id');
                    if (rowMunicipality === firstMunicipality) {
                        selectedResourceIds.add(resourceId);
                    }
                }
            });
            filterResourcesByMunicipality(firstMunicipality);
            return; // Exit early, will be restored after reload
        }
        
        selectedMunicipality = firstMunicipality;
        // Select all visible resources from this municipality
        rows.forEach(row => {
            const checkbox = row.querySelector('.resource-checkbox:not([disabled])');
            if (checkbox) {
                const rowMunicipality = checkbox.getAttribute('data-municipality');
                const resourceId = checkbox.getAttribute('data-resource-id');
                if (rowMunicipality === firstMunicipality) {
                    selectedResourceIds.add(resourceId);
                    checkbox.checked = true;
                    row.classList.add('resource-row-selected');
                }
            }
        });
    } else {
        // Deselect all visible resources
        rows.forEach(row => {
            const checkbox = row.querySelector('.resource-checkbox');
            if (checkbox) {
                const resourceId = checkbox.getAttribute('data-resource-id');
                selectedResourceIds.delete(resourceId);
                checkbox.checked = false;
                row.classList.remove('resource-row-selected');
            }
        });
        
        if (selectedResourceIds.size === 0) {
            selectedMunicipality = null;
            // Show all resources again
            filterResourcesByMunicipality(null);
        }
    }
    
    updateMunicipalityLock();
    updateActionBar();
}

function updateMunicipalityLock() {
    const tbody = document.getElementById('availableResourcesTableBody');
    if (!tbody) return;
    
    const rows = tbody.querySelectorAll('tr.resource-row');
    
    if (selectedMunicipality) {
        // Lock resources from other municipalities
        rows.forEach(row => {
            const checkbox = row.querySelector('.resource-checkbox');
            if (!checkbox) return;
            
            const rowMunicipality = checkbox.getAttribute('data-municipality');
            
            if (rowMunicipality !== selectedMunicipality) {
                // Lock this row
                checkbox.disabled = true;
                row.classList.add('resource-row-locked');
                row.classList.remove('resource-row-selected');
                checkbox.checked = false;
                row.setAttribute('title', 'Resources must be from the same municipality. Clear selection to choose from ' + rowMunicipality);
            } else {
                // Unlock this row
                checkbox.disabled = false;
                row.classList.remove('resource-row-locked');
                row.removeAttribute('title');
            }
        });
    } else {
        // Unlock all rows
        rows.forEach(row => {
            const checkbox = row.querySelector('.resource-checkbox');
            if (!checkbox) return;
            
            // Only enable if resource is available
            const stock = parseInt(checkbox.getAttribute('data-stock') || '0');
            if (stock > 0) {
                checkbox.disabled = false;
            }
            row.classList.remove('resource-row-locked');
            row.classList.remove('resource-row-selected');
            row.removeAttribute('title');
        });
    }
    
    // Update select all checkbox state
    updateSelectAllCheckboxState();
}

function updateSelectAllCheckboxState() {
    const selectAllCheckbox = document.getElementById('selectAllResources');
    if (!selectAllCheckbox) return;
    
    const checkedBoxes = document.querySelectorAll('.resource-checkbox:checked:not([disabled])');
    const enabledBoxes = document.querySelectorAll('.resource-checkbox:not([disabled])');
    
    if (checkedBoxes.length === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    } else if (checkedBoxes.length === enabledBoxes.length) {
        selectAllCheckbox.checked = true;
        selectAllCheckbox.indeterminate = false;
    } else {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = true;
    }
}

function updateActionBar() {
    const actionBar = document.getElementById('resourceSelectionActionBar');
    const countElement = document.getElementById('selectedResourcesCount');
    const requestCountElement = document.getElementById('requestSelectedCount');
    const municipalityElement = document.getElementById('selectedMunicipalityName');
    
    // Use selectedResourceIds size instead of checking DOM (more reliable after filtering)
    const count = selectedResourceIds.size;
    
    if (count > 0 && actionBar) {
        actionBar.classList.add('show');
        document.body.classList.add('action-bar-visible');
        if (countElement) countElement.textContent = count;
        if (requestCountElement) requestCountElement.textContent = count;
        if (municipalityElement && selectedMunicipality) {
            municipalityElement.textContent = selectedMunicipality;
        }
    } else {
        if (actionBar) actionBar.classList.remove('show');
        document.body.classList.remove('action-bar-visible');
    }
}

function filterResourcesByMunicipality(municipality) {
    // Reload resources from server filtered by municipality
    if (municipality) {
        // Set search filter to municipality name and reload
        const searchInput = document.getElementById('resourceSearch');
        if (searchInput) {
            searchInput.value = municipality;
        }
        // Reset to page 1 and reload
        loadResources(1);
    } else {
        // Clear search filter and show all resources
        const searchInput = document.getElementById('resourceSearch');
        if (searchInput) {
            searchInput.value = '';
        }
        // Reset to page 1 and reload
        loadResources(1);
    }
}

function clearResourceSelection() {
    // Clear selected resource IDs
    selectedResourceIds.clear();
    
    const checkboxes = document.querySelectorAll('.resource-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    const rows = document.querySelectorAll('tr.resource-row');
    rows.forEach(row => {
        row.classList.remove('resource-row-selected');
        row.classList.remove('resource-row-locked');
        row.removeAttribute('title');
    });
    
    selectedMunicipality = null;
    updateMunicipalityLock();
    updateActionBar();
    
    // Show all resources again
    filterResourcesByMunicipality(null);
    
    const selectAllCheckbox = document.getElementById('selectAllResources');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    }
}

function getSelectedResources() {
    const checkedBoxes = document.querySelectorAll('.resource-checkbox:checked');
    const selectedResources = [];
    
    checkedBoxes.forEach(checkbox => {
        selectedResources.push({
            resourceId: checkbox.getAttribute('data-resource-id'),
            resourceName: checkbox.getAttribute('data-resource-name'),
            municipality: checkbox.getAttribute('data-municipality'),
            stock: parseInt(checkbox.getAttribute('data-stock') || '0'),
            unit: checkbox.getAttribute('data-unit') || ''
        });
    });
    
    return selectedResources;
}

// Bind action bar buttons
if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', function() {
        const clearBtn = document.getElementById('clearResourceSelectionBtn');
        const requestBtn = document.getElementById('requestSelectedResourcesBtn');
        
        if (clearBtn) {
            clearBtn.addEventListener('click', clearResourceSelection);
        }
        
        if (requestBtn) {
            requestBtn.addEventListener('click', function() {
                const selectedResources = getSelectedResources();
                if (selectedResources.length > 0) {
                    requestMultipleResources(selectedResources);
                } else {
                    showNotification('Please select at least one resource', 'warning');
                }
            });
        }
    });
}

// Get status class for badge (only Available / Unavailable)
function getStatusClass(status) {
    const normalized = (status || '').toLowerCase();
    if (normalized === 'available' || normalized === 'limited') return 'success';
    if (normalized === 'unavailable') return 'danger';
    return 'secondary';
}

// Get category class for badge
function getCategoryClass(category) {
    const categoryMap = {
        'Emergency Equipment': 'danger',
        'Water & Sanitation': 'info',
        'Rescue Equipment': 'warning',
        'Medical Supplies': 'success',
        'Food & Relief': 'primary',
        'Communication Equipment': 'secondary'
    };
    return categoryMap[category] || 'secondary';
}



// Pagination event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Previous page button
    const prevPage = document.getElementById('prevPage');
    if (prevPage) {
        prevPage.addEventListener('click', function() {
            if (currentPage > 1) {
                loadResources(currentPage - 1);
            }
        });
    }
    
    // Next page button
    const nextPage = document.getElementById('nextPage');
    if (nextPage) {
        nextPage.addEventListener('click', function() {
            if (currentPage < totalPages) {
                loadResources(currentPage + 1);
            }
        });
    }
});


// Accept/Reject functionality moved to assets/js/pages/requests.js

// Handle tab parameter from URL
const urlParams = new URLSearchParams(window.location.search);
const tab = urlParams.get('tab');
const requestId = urlParams.get('request');

if (tab) {
    // Activate the specific tab based on URL parameter
    if (tab === 'incoming-requests' || tab === 'who-requested') {
        const t = document.getElementById('who-requested-tab');
        if (t) t.click();
    } else if (tab === 'your-requests') {
        const yourTab = document.getElementById('your-requests-tab');
        if (yourTab) yourTab.click();
    } else if (tab === 'borrowed-resources') {
        const b = document.getElementById('borrowed-resources-tab');
        if (b) b.click();
    } else if (tab === 'available-resources') {
        const a = document.getElementById('available-resources-tab');
        if (a) a.click();
    }
    
    // After processing the tab parameter, update URL to remove it
    // This prevents redirection back to the original notification's tab on refresh
    try {
        const url = new URL(window.location.href);
        if (url.searchParams.has('tab')) {
            url.searchParams.delete('tab');
            // Don't trigger a page reload, just update the URL in browser history
            window.history.replaceState({}, document.title, url.toString());
        }
    } catch(e) {
        console.warn('Failed to update URL after tab parameter processing:', e);
    }
}

// Scroll to specific request if request parameter is provided
if (requestId) {
    function scrollToRequest() {
        // Wait for tables to load
        setTimeout(() => {
            const requestRow = document.querySelector(`tr[data-request-id="${requestId}"]`);
            if (requestRow) {
                // Highlight the row
                requestRow.style.backgroundColor = '#fff3cd';
                requestRow.style.transition = 'background-color 0.3s ease';
                requestRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Remove highlight after 3 seconds
                setTimeout(() => {
                    requestRow.style.backgroundColor = '';
                }, 3000);
                
                // Also try to open view details if available
                const viewBtn = requestRow.querySelector('.btn-view-details');
                if (viewBtn) {
                    // Add a visual indicator
                    viewBtn.style.boxShadow = '0 0 10px rgba(102, 126, 234, 0.5)';
                    setTimeout(() => {
                        viewBtn.style.boxShadow = '';
                    }, 2000);
                }
            } else {
                // Request not found in current view, try loading the correct tab and page
                const requests = window.requestsData || [];
                const targetRequest = requests.find(r => String(r.id) === String(requestId));
                
                if (targetRequest) {
                    // Determine which tab it should be in
                    const isIncoming = targetRequest.requestType === 'incoming';
                    const isOutgoing = targetRequest.requestType === 'outgoing';
                    const override = window.requestStatusOverride && window.requestStatusOverride[targetRequest.id];
                    const status = String(override || targetRequest.status || '').toLowerCase();
                    const isHistoryStatus = status === 'rejected' || status === 'returned';
                    
                    if (isIncoming) {
                        const t = document.getElementById('who-requested-tab');
                        if (t) {
                            t.click();
                            // If request is in history, switch to history mode
                            if (isHistoryStatus) {
                                window.resourceRequestsMode = 'history';
                                const historyBtn = document.getElementById('resource-requests-history-tab');
                                const activeBtn = document.getElementById('resource-requests-active-tab');
                                const activePane = document.getElementById('who-requested-active');
                                const historyPane = document.getElementById('who-requested-history');
                                if (historyBtn && activeBtn) {
                                    historyBtn.classList.add('active');
                                    activeBtn.classList.remove('active');
                                }
                                if (activePane && historyPane) {
                                    [activePane, historyPane].forEach(p => { p.classList.remove('active','show'); p.style.display = 'none'; });
                                    historyPane.classList.add('active','show');
                                    historyPane.style.display = 'block';
                                }
                                try { localStorage.setItem('municipalityRequests.rrMode', 'history'); } catch(_) {}
                            }
                            setTimeout(() => {
                                // Load requests and try again
                                if (typeof loadRequests === 'function') {
                                    loadRequests(1);
                                    setTimeout(scrollToRequest, 1000);
                                }
                            }, 500);
                        }
                    } else if (isOutgoing) {
                        const t = document.getElementById('your-requests-tab');
                        if (t) {
                            // If request is in history, enable history toggle BEFORE clicking tab
                            // (tab click handler calls loadUserRequests immediately)
                            if (isHistoryStatus) {
                                const historyToggle = document.getElementById('myRequestsShowHistory');
                                if (historyToggle && !historyToggle.checked) {
                                    historyToggle.checked = true;
                                }
                            }
                            t.click();
                            setTimeout(() => {
                                if (typeof loadUserRequests === 'function') {
                                    loadUserRequests();
                                    setTimeout(scrollToRequest, 1000);
                                }
                            }, 500);
                        }
                    }
                }
            }
        }, 1000);
    }
    
    // Wait for page to fully load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scrollToRequest);
    } else {
        scrollToRequest();
    }
    
    // After processing the request parameter, update URL to remove it
    // This prevents redirection back to the original notification's request on refresh
    try {
        const url = new URL(window.location.href);
        if (url.searchParams.has('request')) {
            url.searchParams.delete('request');
            // Don't trigger a page reload, just update the URL in browser history
            window.history.replaceState({}, document.title, url.toString());
        }
    } catch(e) {
        console.warn('Failed to update URL after request parameter processing:', e);
    }
}

// Refresh notifications when this page loads
document.addEventListener('DOMContentLoaded', function() {
    // Trigger notification refresh after a short delay to ensure header component is loaded
    setTimeout(() => {
        if (window.refreshHeaderNotifications) {
            window.refreshHeaderNotifications();
        }
        // Also dispatch the custom event
        document.dispatchEvent(new CustomEvent('notifications:refresh'));
    }, 500);
});

// Silent refresh function - updates data without visual indicators
async function silentRefreshAllTabs() {
    try {
        // Refresh the data source silently
        const response = await fetch('config/get_requests_for_municipality.php', { credentials: 'same-origin' });
        const data = await response.json();
        
        if (data && data.success && data.data && Array.isArray(data.data.requests)) {
            window.requestsData = data.data.requests;
            
            // Update all tabs silently (they'll show updated data when user switches to them)
            loadRequests(window.requestsCurrentPage || 1);
            loadUserRequests();
            loadBorrowedRequests();
            
            // Update current active tab
            const activeTab = document.querySelector('#requestTabs .nav-link.active');
            if (activeTab) {
                if (activeTab.id === 'available-resources-tab') {
                    loadAvailableResources();
                } else if (activeTab.id === 'who-requested-tab') {
                    loadRequests(window.requestsCurrentPage || 1);
                } else if (activeTab.id === 'your-requests-tab') {
                    loadUserRequests();
                } else if (activeTab.id === 'borrowed-resources-tab') {
                    loadBorrowedRequests();
                }
            }
        }
    } catch(err) {
        // Silent failure - no user-facing errors
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.warn('Silent refresh failed:', err);
        }
    }
}

// Listen for request refresh events - update silently
document.addEventListener('requests:refresh', function() {
    silentRefreshAllTabs();
    
    // Also refresh notifications quietly
    if (window.refreshHeaderNotifications) {
        window.refreshHeaderNotifications();
    }
});

// Unified auto-refresh system - handled by startAutoRefresh() function
// Removed duplicate timers to avoid conflicts

// Signature Upload Functions
function initializeSignatureUploads() {
    console.log('Initializing signature uploads...');
    
    // Requestor signature upload
    const requestorBtn = document.getElementById('requestorSignatureBtn');
    const requestorFile = document.getElementById('requestorSignatureFile');
    
    console.log('Requestor button found:', requestorBtn);
    console.log('Requestor file input found:', requestorFile);
    
    if (requestorBtn && requestorFile) {
        requestorBtn.addEventListener('click', () => {
            console.log('Requestor signature button clicked');
            requestorFile.click();
        });
        
        requestorFile.addEventListener('change', (e) => {
            console.log('Requestor signature file selected');
            handleSignatureUpload(e, 'requestor');
        });
    }
    
    // Approver signature upload
    const approverBtn = document.getElementById('approverSignatureBtn');
    const approverFile = document.getElementById('approverSignatureFile');
    
    console.log('Approver button found:', approverBtn);
    console.log('Approver file input found:', approverFile);
    
    if (approverBtn && approverFile) {
        approverBtn.addEventListener('click', () => {
            console.log('Approver signature button clicked');
            approverFile.click();
        });
        
        approverFile.addEventListener('change', (e) => {
            console.log('Approver signature file selected');
            handleSignatureUpload(e, 'approver');
        });
    }
}

function handleSignatureUpload(event, type) {
    console.log('handleSignatureUpload called for type:', type);
    const file = event.target.files[0];
    if (!file) {
        console.log('No file selected');
        return;
    }
    
    console.log('File selected:', file.name, 'Size:', file.size, 'Type:', file.type);
    
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
    
    console.log('File validation passed, reading file...');
    const reader = new FileReader();
    reader.onload = (e) => {
        console.log('File read successfully, showing preview...');
        showSignaturePreview(e.target.result, type);
    };
    reader.readAsDataURL(file);
}

function showSignaturePreview(imageData, type) {
    const preview = document.getElementById(`${type}SignaturePreview`);
    const img = document.getElementById(`${type}SignatureImg`);
    const btn = document.getElementById(`${type}SignatureBtn`);
    
    if (preview && img && btn) {
        img.src = imageData;
        preview.style.display = 'block';
        btn.textContent = 'Change E-Signature';
    }
}

function clearSignature(type) {
    const preview = document.getElementById(`${type}SignaturePreview`);
    const fileInput = document.getElementById(`${type}SignatureFile`);
    const btn = document.getElementById(`${type}SignatureBtn`);
    
    if (preview && fileInput && btn) {
        fileInput.value = '';
        preview.style.display = 'none';
        btn.textContent = 'Upload E-Signature';
    }
}

// PDF Preview Functions
function showPDFPreview(requestId) {
    console.log('Opening PDF preview for request ID:', requestId);
    const pdfUrl = `dashboards/pages/pdf_viewer.php?id=${requestId}`;
    window.open(pdfUrl, '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');
}

// Function to generate document for request (uses requestGroupId if available, otherwise single request ID)
function generateDocumentForRequest() {
    const requestGroupId = window.currentRequestGroupId;
    const requestId = window.currentRequestId || (typeof currentRequestId !== 'undefined' ? currentRequestId : null);
    
    if (requestGroupId) {
        // Use requestGroupId to generate document for the entire group
        console.log('Opening PDF preview for request group:', requestGroupId);
        const pdfUrl = `dashboards/pages/pdf_viewer.php?groupId=${requestGroupId}`;
        window.open(pdfUrl, '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');
    } else if (requestId) {
        // Fallback to single request ID
        console.log('Opening PDF preview for single request ID:', requestId);
        showPDFPreview(requestId);
    } else {
        showNotification('No request selected', 'error');
    }
}

// Store current request ID for PDF viewing
let currentRequestId = null;

function viewRequestPDF() {
    if (currentRequestId) {
        showPDFPreview(currentRequestId);
    } else {
        showNotification('No request selected', 'error');
    }
}


</script>
