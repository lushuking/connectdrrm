<div class="reports-page">
    <link rel="stylesheet" href="assets/css/pages/reports.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <!-- Reports Header -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%); color: white;">
        <div class="card-body p-5 position-relative">
            <span class="material-icons position-absolute text-white" style="font-size: 150px; opacity: 0.1; right: 5%; top: 50%; transform: translateY(-50%);">insert_chart_outlined</span>
            <div class="position-relative z-1">
                <h2 class="fw-bold mb-2 text-white">DRRM Reports & Analytics</h2>
                <p class="mb-4 text-white opacity-75 fs-6" style="max-width: 600px;">Generate comprehensive reports based on your municipality's actual DRRM data, including resource inventory, requests, and hazard incidents.</p>
                <button class="btn btn-outline-light btn-lg rounded-pill fw-bold shadow-sm d-inline-flex align-items-center px-4 py-2" onclick="generateUnifiedReport()">
                    <span class="material-icons me-2">table_chart</span>
                    Generate All-in-One Report (Table)
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Generate Report Cards -->
    <div class="row g-4 mb-5 align-items-stretch">
        <div class="col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden" onclick="generateMyResourcesReport()" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div class="card-body text-center p-4">
                    <div class="mb-3 mt-2">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                            <span class="material-icons text-primary" style="font-size: 32px;">inventory</span>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2 text-dark">My Resources</h5>
                    <p class="text-muted small mb-4">Your municipality's resource inventory, stock levels, and availability status.</p>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-semibold border border-primary border-opacity-25">Quick Generate</span>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden" onclick="generateBorrowedResourcesReport()" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div class="card-body text-center p-4">
                    <div class="mb-3 mt-2">
                        <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                            <span class="material-icons text-info" style="font-size: 32px;">swap_horiz</span>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2 text-dark">Borrowed From Us</h5>
                    <p class="text-muted small mb-4">Who borrowed what from your municipality, when, and the current status.</p>
                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-2 fw-semibold border border-info border-opacity-25">Quick Generate</span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden" onclick="generateMyRequestsReport()" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div class="card-body text-center p-4">
                    <div class="mb-3 mt-2">
                        <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                            <span class="material-icons text-warning" style="font-size: 32px;">request_quote</span>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2 text-dark">My Requests</h5>
                    <p class="text-muted small mb-4">Requests your municipality made to others, current status, and response times.</p>
                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-2 fw-semibold border border-warning border-opacity-25">Quick Generate</span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden" onclick="generateMyHazardsReport()" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div class="card-body text-center p-4">
                    <div class="mb-3 mt-2">
                        <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                            <span class="material-icons text-danger" style="font-size: 32px;">warning</span>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2 text-dark">My Hazards</h5>
                    <p class="text-muted small mb-4">Hazard incidents in your area, risk levels, and corresponding response actions.</p>
                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2 fw-semibold border border-danger border-opacity-25">Quick Generate</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Title -->
    <div class="d-flex align-items-center mb-4 mt-2">
        <h4 class="fw-bold text-gray-800 mb-0 d-flex align-items-center" style="letter-spacing: -0.5px;">
            <span class="material-icons text-primary me-2" style="font-size: 26px;">bar_chart</span>
            My Municipality Analytics
        </h4>
    </div>

    <!-- Charts Row 1 -->
    <div class="row g-4 mb-4">
        <!-- My Resource Categories -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0">
                    <h6 class="fw-bold text-uppercase text-muted mb-0" style="letter-spacing: 0.5px;">
                        <span class="material-icons align-middle me-1 text-primary" style="font-size: 18px;">inventory</span>
                        My Resource Categories
                    </h6>
                </div>
                <div class="card-body pt-2">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="myResourceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Resources Borrowed From Us -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0">
                    <h6 class="fw-bold text-uppercase text-muted mb-0" style="letter-spacing: 0.5px;">
                        <span class="material-icons align-middle me-1 text-info" style="font-size: 18px;">swap_horiz</span>
                        Resources Borrowed From Us
                    </h6>
                </div>
                <div class="card-body pt-2">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="borrowedFromUsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- My Resource Requests -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0">
                    <h6 class="fw-bold text-uppercase text-muted mb-0" style="letter-spacing: 0.5px;">
                        <span class="material-icons align-middle me-1 text-warning" style="font-size: 18px;">request_quote</span>
                        My Resource Requests
                    </h6>
                </div>
                <div class="card-body pt-2">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="myRequestChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row g-4 mb-4">
        <!-- My Hazard Reports -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0">
                    <h6 class="fw-bold text-uppercase text-muted mb-0" style="letter-spacing: 0.5px;">
                        <span class="material-icons align-middle me-1 text-danger" style="font-size: 18px;">warning</span>
                        My Hazard Reports
                    </h6>
                </div>
                <div class="card-body pt-2">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="myHazardChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Resource Sharing Activity -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0">
                    <h6 class="fw-bold text-uppercase text-muted mb-0" style="letter-spacing: 0.5px;">
                        <span class="material-icons align-middle me-1 text-success" style="font-size: 18px;">trending_up</span>
                        Resource Sharing Activity
                    </h6>
                </div>
                <div class="card-body pt-2">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="resourceSharingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- My Resources Report Modal -->
<div id="myResourcesModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title">
                <span class="material-icons text-primary">inventory</span>
                My Resources Report
            </h2>
            <button class="modal-close" onclick="closeMyResourcesModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <div id="myResourcesSummary" class="mb-3 small text-muted">Loading real data…</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header py-2"><strong>Category Breakdown</strong></div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 260px;">
                                    <canvas id="myResourcesCategoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header py-2"><strong>Availability</strong></div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 260px;">
                                    <canvas id="myResourcesAvailabilityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header py-2"><strong>Top Categories by Stock</strong></div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 220px;">
                                    <canvas id="myResourcesTopChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeMyResourcesModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateMyResourcesReport()">
                <span class="material-icons">assessment</span>
                Generate My Resources Report
            </button>
        </div>
    </div>
</div>

<!-- Borrowed Resources Report Modal -->
<div id="borrowedResourcesModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title">
                <span class="material-icons text-info">swap_horiz</span>
                Resources Borrowed From Us Report
            </h2>
            <button class="modal-close" onclick="closeBorrowedResourcesModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <p>This report will include:</p>
                <ul>
                    <li>Who borrowed what from your municipality</li>
                    <li>When they borrowed it and current status</li>
                    <li>Response times for your approvals</li>
                    <li>Statistics on requests made to you</li>
                </ul>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeBorrowedResourcesModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateBorrowedResourcesReport()">
                <span class="material-icons">assessment</span>
                Generate Borrowed Resources Report
            </button>
        </div>
    </div>
</div>

<!-- My Requests Report Modal -->
<div id="myRequestsModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title">
                <span class="material-icons text-warning">request_quote</span>
                My Resource Requests Report
            </h2>
            <button class="modal-close" onclick="closeMyRequestsModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <p>This report will include:</p>
                <ul>
                    <li>Requests your municipality made to others</li>
                    <li>Status and response times</li>
                    <li>Which municipalities you requested from</li>
                    <li>Success rate of your requests</li>
                </ul>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeMyRequestsModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateMyRequestsReport()">
                <span class="material-icons">assessment</span>
                Generate My Requests Report
            </button>
        </div>
    </div>
</div>

<!-- My Hazards Report Modal -->
<div id="myHazardsModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title">
                <span class="material-icons text-danger">warning</span>
                My Hazard Reports
            </h2>
            <button class="modal-close" onclick="closeMyHazardsModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <p>This report will include:</p>
                <ul>
                    <li>Hazard incidents in your municipality</li>
                    <li>Risk levels and affected populations</li>
                    <li>Response actions taken</li>
                    <li>Hazard statistics for your area</li>
                </ul>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeMyHazardsModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateMyHazardsReport()">
                <span class="material-icons">assessment</span>
                Generate My Hazards Report
            </button>
        </div>
    </div>
</div>

<!-- My Performance Report Modal -->
<div id="myPerformanceModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title">
                <span class="material-icons text-success">trending_up</span>
                My Performance Report
            </h2>
            <button class="modal-close" onclick="closeMyPerformanceModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <p>This report will include:</p>
                <ul>
                    <li>Your municipality's DRRM performance metrics</li>
                    <li>Response efficiency and capacity</li>
                    <li>Resource management effectiveness</li>
                    <li>Request fulfillment rates</li>
                </ul>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeMyPerformanceModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateMyPerformanceReport()">
                <span class="material-icons">assessment</span>
                Generate My Performance Report
            </button>
        </div>
    </div>
</div>

<!-- Resource Utilization Report Modal -->
<div id="resourceUtilizationModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title">
                <span class="material-icons text-info">analytics</span>
                Resource Utilization Report
            </h2>
            <button class="modal-close" onclick="closeResourceUtilizationModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <p>This report will include:</p>
                <ul>
                    <li>How your resources are being used</li>
                    <li>Which resources are borrowed most</li>
                    <li>Return rates and utilization patterns</li>
                    <li>Resource sharing effectiveness</li>
                </ul>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeResourceUtilizationModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateResourceUtilizationReport()">
                <span class="material-icons">assessment</span>
                Generate Resource Utilization Report
            </button>
        </div>
    </div>
</div>

<!-- Emergency Preparedness Report Modal -->
<div id="emergencyPreparednessModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title">
                <span class="material-icons text-danger">emergency</span>
                Emergency Preparedness Report
            </h2>
            <button class="modal-close" onclick="closeEmergencyPreparednessModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <p>This report will include:</p>
                <ul>
                    <li>Your municipality's emergency readiness</li>
                    <li>Resource availability for emergencies</li>
                    <li>Response capability assessment</li>
                    <li>Preparedness scoring</li>
                </ul>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeEmergencyPreparednessModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateEmergencyPreparednessReport()">
                <span class="material-icons">assessment</span>
                Generate Emergency Preparedness Report
            </button>
        </div>
    </div>
</div>

<!-- Monthly Summary Report Modal -->
<div id="monthlySummaryModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title">
                <span class="material-icons text-warning">summarize</span>
                Monthly Summary Report
            </h2>
            <button class="modal-close" onclick="closeMonthlySummaryModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <p>This report will include:</p>
                <ul>
                    <li>Complete monthly overview of your activities</li>
                    <li>Resources, requests, and performance</li>
                    <li>Monthly trends and patterns</li>
                    <li>Comprehensive municipality report</li>
                </ul>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeMonthlySummaryModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateMonthlySummaryReport()">
                <span class="material-icons">assessment</span>
                Generate Monthly Summary Report
            </button>
        </div>
    </div>
</div>

<!-- Report Preview Modal -->
<div id="reportPreviewModal" class="modal">
    <div class="modal-content report-preview-modal">
        <div class="modal-header">
            <h2 class="modal-title">
                <span class="material-icons">preview</span>
                Report Preview
            </h2>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="printReport()">
                    <span class="material-icons">print</span>
                    Print
                </button>
                <button class="btn btn-primary" onclick="exportReport()">
                    <span class="material-icons">download</span>
                    Export
                </button>
                <button class="modal-close" onclick="closeReportPreviewModal()">
                    <span class="material-icons">close</span>
                </button>
            </div>
        </div>
        
        <div class="modal-body">
            <div class="report-preview" id="reportPreviewContent">
                <!-- Report content will be generated here -->
            </div>
        </div>
    </div>
</div>