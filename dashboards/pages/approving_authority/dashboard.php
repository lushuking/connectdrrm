<?php
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/db.php';

$drrmoID = $_SESSION['municipality_id'] ?? null;
$userName = htmlspecialchars($_SESSION['full_name'] ?? 'Head of DRRMO');

// Fetch approval stats
$pendingCount = 0;
$approvedToday = 0;
$rejectedToday = 0;
$totalReviewed = 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE toDRRMO = ? AND LOWER(status) = 'pending_head_approval'");
    $stmt->execute([$drrmoID]);
    $pendingCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE toDRRMO = ? AND LOWER(status) = 'approved' AND DATE(updatedAt) = CURDATE()");
    $stmt->execute([$drrmoID]);
    $approvedToday = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE toDRRMO = ? AND LOWER(status) = 'rejected' AND DATE(updatedAt) = CURDATE()");
    $stmt->execute([$drrmoID]);
    $rejectedToday = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE toDRRMO = ? AND LOWER(status) IN ('approved','rejected')");
    $stmt->execute([$drrmoID]);
    $totalReviewed = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    error_log('Dashboard stats error: ' . $e->getMessage());
}
?>

<div class="approving-dashboard-page">
<style>
    .approving-dashboard-page { padding: 0; }

    /* Hero banner */
    .aa-hero {
        background: linear-gradient(135deg, #1A3D63 0%, #2d6a9f 60%, #4A7FA7 100%);
        border-radius: 1rem;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    /* Stat cards */
    .aa-stat-card {
        border: none;
        border-radius: 0.875rem;
        box-shadow: 0 2px 12px rgba(0,0,0,0.07);
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
    }
    .aa-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    .aa-stat-icon {
        width: 52px; height: 52px;
        border-radius: 0.75rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .aa-stat-number {
        font-size: 2rem; font-weight: 800; line-height: 1;
        letter-spacing: -1px;
    }

    /* Quick action card */
    .aa-action-btn {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 0.875rem 1rem;
        border-radius: 0.625rem;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s;
        border: 1.5px solid transparent;
    }
    .aa-action-btn:hover { transform: translateX(4px); }
    .aa-action-btn.primary { background: #f0f7ff; color: #1A3D63; border-color: #bcd4f0; }
    .aa-action-btn.primary:hover { background: #daeaf9; }
    .aa-action-btn.success { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
    .aa-action-btn.success:hover { background: #dcfce7; }
    .aa-action-btn .aa-btn-icon {
        width: 36px; height: 36px; border-radius: 0.5rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }

    /* Checklist */
    .aa-checklist li {
        padding: 0.5rem 0;
        border-bottom: 1px solid #f1f5f9;
        display: flex; align-items: center; gap: 0.625rem;
        font-size: 0.875rem; color: #475569;
    }
    .aa-checklist li:last-child { border-bottom: none; }
    .aa-check-dot {
        width: 22px; height: 22px; border-radius: 50%;
        background: #dcfce7; display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
</style>

    <!-- Hero Banner -->
    <div class="aa-hero p-4 mb-4">
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;">
                        <span class="material-icons text-white" style="font-size:28px;">verified_user</span>
                    </div>
                    <div>
                        <p class="text-white text-opacity-75 small mb-0" style="letter-spacing:0.5px;">HEAD OF DRRMO</p>
                        <h5 class="text-white fw-bold mb-0">Welcome back, <?= $userName ?>!</h5>
                    </div>
                </div>
                <p class="text-white text-opacity-75 small mb-0 ms-1">
                    You are responsible for reviewing and approving resource requests from municipalities, ensuring transparency and orderly conduct in resource sharing.
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <?php if ($pendingCount > 0): ?>
                <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-10 rounded-3 px-3 py-2">
                    <span class="material-icons text-warning" style="font-size:20px;">notifications_active</span>
                    <div class="text-start">
                        <div class="text-white fw-bold fs-5 lh-1"><?= $pendingCount ?></div>
                        <div class="text-white text-opacity-75" style="font-size:0.72rem;">Pending Reviews</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-10 rounded-3 px-3 py-2">
                    <span class="material-icons text-white" style="font-size:20px;">check_circle</span>
                    <div class="text-white text-opacity-75 small">All caught up!</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stat Cards Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card aa-stat-card h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="aa-stat-icon" style="background:#fff3cd;">
                            <span class="material-icons" style="color:#b45309;font-size:24px;">pending_actions</span>
                        </div>
                        <div>
                            <div class="aa-stat-number text-dark"><?= $pendingCount ?></div>
                            <div class="text-muted small fw-semibold">Pending</div>
                        </div>
                    </div>
                    <a href="?page=approvals" class="btn btn-sm btn-warning w-100 mt-3 rounded-pill fw-semibold" style="font-size:0.8rem;">
                        Review Now
                    </a>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card aa-stat-card h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="aa-stat-icon" style="background:#dcfce7;">
                            <span class="material-icons" style="color:#166534;font-size:24px;">check_circle</span>
                        </div>
                        <div>
                            <div class="aa-stat-number" style="color:#166534;"><?= $approvedToday ?></div>
                            <div class="text-muted small fw-semibold">Approved Today</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card aa-stat-card h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="aa-stat-icon" style="background:#fee2e2;">
                            <span class="material-icons" style="color:#991b1b;font-size:24px;">cancel</span>
                        </div>
                        <div>
                            <div class="aa-stat-number" style="color:#991b1b;"><?= $rejectedToday ?></div>
                            <div class="text-muted small fw-semibold">Rejected Today</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card aa-stat-card h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="aa-stat-icon" style="background:#ede9fe;">
                            <span class="material-icons" style="color:#5b21b6;font-size:24px;">fact_check</span>
                        </div>
                        <div>
                            <div class="aa-stat-number" style="color:#5b21b6;"><?= $totalReviewed ?></div>
                            <div class="text-muted small fw-semibold">Total Reviewed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row: Quick Actions + Role Info -->
    <div class="row g-3">
        <!-- Quick Actions -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom px-4 pt-3 pb-2">
                    <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
                        <span class="material-icons text-primary" style="font-size:18px;">flash_on</span>
                        Quick Actions
                    </h6>
                </div>
                <div class="card-body p-3 d-flex flex-column gap-2">
                    <a href="?page=approvals" class="aa-action-btn primary">
                        <div class="aa-btn-icon" style="background:#dbeafe;">
                            <span class="material-icons" style="color:#1d4ed8;font-size:18px;">assignment_turned_in</span>
                        </div>
                        <div>
                            <div>View Pending Approvals</div>
                            <div class="fw-normal text-muted" style="font-size:0.78rem;"><?= $pendingCount ?> request<?= $pendingCount !== 1 ? 's' : '' ?> awaiting your review</div>
                        </div>
                        <span class="material-icons ms-auto text-muted" style="font-size:16px;">chevron_right</span>
                    </a>
                    <a href="?page=reports" class="aa-action-btn success">
                        <div class="aa-btn-icon" style="background:#dcfce7;">
                            <span class="material-icons" style="color:#166534;font-size:18px;">bar_chart</span>
                        </div>
                        <div>
                            <div>View Reports</div>
                            <div class="fw-normal text-muted" style="font-size:0.78rem;">Approval history and statistics</div>
                        </div>
                        <span class="material-icons ms-auto text-muted" style="font-size:16px;">chevron_right</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Role Info -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom px-4 pt-3 pb-2">
                    <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
                        <span class="material-icons text-success" style="font-size:18px;">info</span>
                        Your Responsibilities
                    </h6>
                </div>
                <div class="card-body p-3">
                    <p class="text-muted small mb-3">
                        As the <strong>Head of DRRMO</strong>, you oversee all inter-municipality resource sharing requests and ensure they meet operational standards.
                    </p>
                    <ul class="list-unstyled aa-checklist mb-0">
                        <li>
                            <div class="aa-check-dot"><span class="material-icons" style="font-size:13px;color:#16a34a;">check</span></div>
                            Review and evaluate pending resource requests
                        </li>
                        <li>
                            <div class="aa-check-dot"><span class="material-icons" style="font-size:13px;color:#16a34a;">check</span></div>
                            Approve or reject with full audit trail
                        </li>
                        <li>
                            <div class="aa-check-dot"><span class="material-icons" style="font-size:13px;color:#16a34a;">check</span></div>
                            Monitor grouped request batch processing
                        </li>
                        <li>
                            <div class="aa-check-dot"><span class="material-icons" style="font-size:13px;color:#16a34a;">check</span></div>
                            Track historical approval performance
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
