<div class="reports-page pdrrmo-reports-page">
    <link rel="stylesheet" href="assets/css/pages/reports.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <!-- Tabs: Office Request Reports | Data Analytics -->
    <ul class="nav nav-tabs mb-4" id="pdrrmoReportsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="office-request-reports-tab" data-bs-toggle="tab" data-bs-target="#office-request-reports" type="button" role="tab" aria-controls="office-request-reports" aria-selected="true">Office Request Reports</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="data-analytics-tab" data-bs-toggle="tab" data-bs-target="#data-analytics" type="button" role="tab" aria-controls="data-analytics" aria-selected="false">Data Analytics</button>
        </li>
    </ul>

    <div class="tab-content" id="pdrrmoReportsTabContent">
        <!-- Office Request Reports (same layout/style as municipality) -->
        <div class="tab-pane fade show active" id="office-request-reports" role="tabpanel" aria-labelledby="office-request-reports-tab">
            <!-- Reports Header -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%); color: white;">
                <div class="card-body p-5 position-relative">
                    <span class="material-icons position-absolute text-white" style="font-size: 150px; opacity: 0.1; right: 5%; top: 50%; transform: translateY(-50%);">insert_chart_outlined</span>
                    <div class="position-relative z-1">
                        <h2 class="fw-bold mb-2 text-white">Office Reports & Analytics</h2>
                        <p class="mb-4 text-white opacity-75 fs-6" style="max-width: 600px;">Generate comprehensive reports based on your office's DRRM data, including resource inventory, requests, and hazard incidents.</p>
                        <button class="btn btn-outline-light btn-lg rounded-pill fw-bold shadow-sm d-inline-flex align-items-center px-4 py-2" onclick="generateUnifiedReport()">
                            <span class="material-icons me-2">table_view</span>
                            Generate All-in-One Report
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
                            <p class="text-muted small mb-4">Your office's resource inventory, stock levels, and availability status.</p>
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
                            <p class="text-muted small mb-4">Who borrowed what from your office, when, and the current status.</p>
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
                            <p class="text-muted small mb-4">Requests your office made to others, current status, and response times.</p>
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
                    My Office Analytics
                </h4>
            </div>

            <!-- Charts Row 1 -->
            <div class="row g-4 mb-4">
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

        <!-- Data Analytics (province-wide) -->
        <div class="tab-pane fade" id="data-analytics" role="tabpanel" aria-labelledby="data-analytics-tab">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h2 class="h3 mb-1 text-gray-800 d-flex align-items-center" style="font-weight: 700; letter-spacing: -0.5px;">
                        <span class="material-icons text-primary me-2" style="font-size: 28px;">analytics</span>
                        Data Analytics Hub
                    </h2>
                    <p class="text-muted mb-0">Gain actionable insights from province-wide DRRM request patterns and hazard data.</p>
                </div>
                
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle shadow-sm rounded-pill px-4" type="button" id="exportMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="material-icons align-middle me-1" style="font-size: 18px;">download</span> Export Data
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="exportMenu">
                        <li><a class="dropdown-item" href="#" id="pdrrmoExportMunicipalityTopCsv"><span class="material-icons align-middle text-muted me-2" style="font-size: 18px;">table_view</span>Top by Municipality (CSV)</a></li>
                        <li><a class="dropdown-item" href="#" id="pdrrmoExportPatternsCsv"><span class="material-icons align-middle text-muted me-2" style="font-size: 18px;">table_view</span>Municipalities (CSV)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-primary" href="#" id="pdrrmoExportAnalyticsPdf"><span class="material-icons align-middle me-2" style="font-size: 18px;">picture_as_pdf</span>Full Analytics (PDF)</a></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);">
                <div class="card-body p-4">
                    <div class="row align-items-center gy-4">
                        <div class="col-lg-5 col-xl-4 position-relative">
                            <label class="form-label text-uppercase text-muted fw-bold mb-2" style="font-size: 0.75rem; letter-spacing: 1px;">Time Period</label>
                            <div class="d-flex align-items-center gap-2">
                                <select id="pdrrmoPeriodMonths" class="form-select border shadow-sm bg-white rounded-pill px-3 py-2" style="cursor:pointer; font-weight: 500; min-width: 160px;">
                                    <option value="6">Last 6 Months</option>
                                    <option value="12" selected>Last 12 Months</option>
                                    <option value="24">Last 24 Months</option>
                                </select>
                                <button type="button" class="btn btn-primary rounded-circle shadow-sm flex-shrink-0" id="pdrrmoRefreshAnalytics" style="width: 42px; height: 42px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Refresh Data">
                                    <span class="material-icons">refresh</span>
                                </button>
                            </div>
                            <div class="d-none d-lg-block position-absolute top-0 bottom-0 end-0 border-end"></div>
                        </div>
                        
                        <div class="col-lg-7 col-xl-8 ps-lg-4">
                            <label class="form-label text-uppercase text-muted fw-bold mb-2" style="font-size: 0.75rem; letter-spacing: 1px;">Dashboard View</label>
                            <div class="d-flex flex-wrap gap-2" id="pdrrmoSectionFilterGroup">
                                <input type="radio" class="btn-check" name="sectionFilter" id="filterAll" value="all" autocomplete="off" checked>
                                <label class="btn btn-outline-secondary rounded-pill px-3 shadow-sm d-flex align-items-center" for="filterAll">
                                    <span class="material-icons me-1" style="font-size: 18px;">dashboard</span> All
                                </label>

                                <input type="radio" class="btn-check" name="sectionFilter" id="filterHazards" value="section-hazards" autocomplete="off">
                                <label class="btn btn-outline-danger rounded-pill px-3 shadow-sm d-flex align-items-center" for="filterHazards">
                                    <span class="material-icons me-1" style="font-size: 18px;">warning</span> Hazards
                                </label>

                                <input type="radio" class="btn-check" name="sectionFilter" id="filterFairness" value="section-fairness" autocomplete="off">
                                <label class="btn btn-outline-success rounded-pill px-3 shadow-sm d-flex align-items-center" for="filterFairness">
                                    <span class="material-icons me-1" style="font-size: 18px;">balance</span> Fairness
                                </label>

                                <input type="radio" class="btn-check" name="sectionFilter" id="filterTrends" value="section-trends" autocomplete="off">
                                <label class="btn btn-outline-primary rounded-pill px-3 shadow-sm d-flex align-items-center" for="filterTrends">
                                    <span class="material-icons me-1" style="font-size: 18px;">trending_up</span> Trends
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    <!-- Hazard Hotspots + Fairness Audit (responsive command-center blocks) -->

    <!-- Hazard Hotspots (province-wide) -->
    <div id="section-hazards" class="pdrrmo-analytics-section">
    <div class="pdrrmo-cc-section mb-4">
        <div class="pdrrmo-cc-head">
            <span class="pdrrmo-cc-chip chip-danger">
                <span class="pdrrmo-cc-dot"></span>
                Hazard Hotspots
            </span>
            <div class="pdrrmo-cc-line"></div>
            <span class="pdrrmo-cc-note">hazards · affected population · active hotspots</span>
        </div>

        <div class="row g-4">
            <div class="col-xl-5 col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <span class="material-icons me-2 text-danger">warning</span>
                            Hazard Frequency by Type
                        </h6>
                        <span class="text-muted small">Last <span data-pdrrmo-months></span> months</span>
                    </div>
                    <div class="card-body">
                        <div id="pdrrmoHazardTypeFrequencyWrap" class="pdrrmo-cc-list">
                            <p class="text-muted text-center py-4 mb-0">Loading…</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-7 col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <span class="material-icons me-2 text-danger">place</span>
                            Hotspot Municipalities
                        </h6>
                        <span class="text-muted small">Active hazards + affected population</span>
                    </div>
                    <div class="card-body overflow-auto pdrrmo-cc-scroll">
                        <div id="pdrrmoHazardHotspotsWrap">
                            <p class="text-muted text-center py-4 mb-0">Loading…</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Request Fairness Audit -->
    <div id="section-fairness" class="pdrrmo-analytics-section">
    <div class="pdrrmo-cc-section mb-4">
        <div class="pdrrmo-cc-head">
            <span class="pdrrmo-cc-chip chip-success">
                <span class="pdrrmo-cc-dot"></span>
                Request Fairness Audit
            </span>
            <div class="pdrrmo-cc-line"></div>
            <span class="pdrrmo-cc-note">requests · fromDRRMO/toDRRMO · status · priority · response time · returns</span>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <span class="material-icons me-2 text-success">balance</span>
                            Municipality Dependency Score
                        </h6>
                        <span class="text-muted small">Borrow vs provide balance</span>
                    </div>
                    <div class="card-body overflow-auto pdrrmo-cc-scroll">
                        <div id="pdrrmoDependencyWrap">
                            <p class="text-muted text-center py-4 mb-0">Loading…</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <span class="material-icons me-2 text-success">task_alt</span>
                            Fulfillment Rate by Municipality
                        </h6>
                        <span class="text-muted small">Approved or better ÷ total sent</span>
                    </div>
                    <div class="card-body overflow-auto pdrrmo-cc-scroll">
                        <div id="pdrrmoFulfillmentWrap">
                            <p class="text-muted text-center py-4 mb-0">Loading…</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <span class="material-icons me-2 text-success">donut_small</span>
                            Request Priority Distribution
                        </h6>
                        <span class="text-muted small">Priority + urgency combined</span>
                    </div>
                    <div class="card-body">
                        <div class="chart-container pdrrmo-cc-chart-md">
                            <canvas id="pdrrmoPriorityDonutChart"></canvas>
                        </div>
                        <div id="pdrrmoPriorityLegend" class="mt-3"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <span class="material-icons me-2 text-success">schedule</span>
                            Avg. Processing Time (Corridors)
                        </h6>
                        <span class="text-muted small">responseDate − requestDate</span>
                    </div>
                    <div class="card-body overflow-auto pdrrmo-cc-scroll">
                        <div id="pdrrmoProcessingWrap">
                            <p class="text-muted text-center py-4 mb-0">Loading…</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <span class="material-icons me-2 text-success">assignment_turned_in</span>
                            Return Compliance
                        </h6>
                        <span class="text-muted small">On-time returns vs return date</span>
                    </div>
                    <div class="card-body overflow-auto pdrrmo-cc-scroll">
                        <div id="pdrrmoReturnComplianceWrap">
                            <p class="text-muted text-center py-4 mb-0">Loading…</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Data Analytics Dashboard -->
    <div id="section-trends" class="pdrrmo-analytics-section">
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <span class="material-icons me-2">analytics</span>
                Data Analytics Dashboard
            </h5>
            <p class="text-muted small mb-0 mt-1">Request trends, high-demand equipment, and reporting patterns across municipalities</p>
        </div>
    </div>

    <!-- Row 1: Request trends + High-demand equipment -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <span class="material-icons me-2">trending_up</span>
                        Request Trends
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container pdrrmo-cc-chart-lg">
                        <canvas id="pdrrmoRequestTrendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <span class="material-icons me-2">priority_high</span>
                        High-Demand Equipment
                    </h6>
                    <span class="text-muted small">Most requested by count</span>
                </div>
                <div class="card-body">
                    <div class="chart-container pdrrmo-cc-chart-lg">
                        <canvas id="pdrrmoHighDemandChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Most requested resources by municipality + Reporting patterns by municipality -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <span class="material-icons me-2">format_list_numbered</span>
                        Most Requested Resources by Municipality
                    </h6>
                    <span class="text-muted small">Top requested items per municipality (as requester)</span>
                </div>
                <div class="card-body overflow-auto pdrrmo-cc-scroll">
                    <div id="pdrrmoMunicipalityTopResourcesWrap">
                        <p class="text-muted text-center py-4">Loading…</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <span class="material-icons me-2">location_city</span>
                        Reporting Patterns by Municipality
                    </h6>
                    <span class="text-muted small">As requester vs as provider</span>
                </div>
                <div class="card-body overflow-auto pdrrmo-cc-scroll">
                    <div class="chart-container pdrrmo-cc-chart-lg">
                        <canvas id="pdrrmoMunicipalityPatternsChart"></canvas>
                    </div>
                    <div id="pdrrmoMunicipalityTableWrap" class="mt-3">
                        <table class="table table-sm table-striped align-middle mb-0" id="pdrrmoMunicipalityTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Municipality</th>
                                    <th class="text-end">As Requester</th>
                                    <th class="text-end">As Provider</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody id="pdrrmoMunicipalityTableBody">
                                <tr><td colspan="4" class="text-center text-muted">Loading…</td></tr>
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
</div>

<!-- Report modals (same structure as municipality for generateMyResourcesReport, etc.) -->
<!-- My Resources Report Modal -->
<div id="myResourcesModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title"><span class="material-icons text-primary">inventory</span> My Resources Report</h2>
            <button class="modal-close" onclick="closeMyResourcesModal()"><span class="material-icons">close</span></button>
        </div>
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <div id="myResourcesSummary" class="mb-3 small text-muted">Loading real data…</div>
                <div class="row g-3">
                    <div class="col-md-6"><div class="card"><div class="card-header py-2"><strong>Category Breakdown</strong></div><div class="card-body"><div class="chart-container" style="height: 260px;"><canvas id="myResourcesCategoryChart"></canvas></div></div></div></div>
                    <div class="col-md-6"><div class="card"><div class="card-header py-2"><strong>Availability</strong></div><div class="card-body"><div class="chart-container" style="height: 260px;"><canvas id="myResourcesAvailabilityChart"></canvas></div></div></div></div>
                </div>
                <div class="row g-3 mt-1"><div class="col-md-12"><div class="card"><div class="card-header py-2"><strong>Top Categories by Stock</strong></div><div class="card-body"><div class="chart-container" style="height: 220px;"><canvas id="myResourcesTopChart"></canvas></div></div></div></div></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeMyResourcesModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateMyResourcesReport()"><span class="material-icons">assessment</span> Generate My Resources Report</button>
        </div>
    </div>
</div>
<!-- Borrowed Resources Report Modal -->
<div id="borrowedResourcesModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title"><span class="material-icons text-info">swap_horiz</span> Resources Borrowed From Us Report</h2>
            <button class="modal-close" onclick="closeBorrowedResourcesModal()"><span class="material-icons">close</span></button>
        </div>
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <p>This report will include: Who borrowed what from your office, when, and current status; response times for your approvals; statistics on requests made to you.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeBorrowedResourcesModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateBorrowedResourcesReport()"><span class="material-icons">assessment</span> Generate Borrowed Resources Report</button>
        </div>
    </div>
</div>
<!-- My Requests Report Modal -->
<div id="myRequestsModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title"><span class="material-icons text-warning">request_quote</span> My Resource Requests Report</h2>
            <button class="modal-close" onclick="closeMyRequestsModal()"><span class="material-icons">close</span></button>
        </div>
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <p>This report will include: Requests your office made to others; status and response times; which offices you requested from; success rate of your requests.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeMyRequestsModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateMyRequestsReport()"><span class="material-icons">assessment</span> Generate My Requests Report</button>
        </div>
    </div>
</div>
<!-- My Hazards Report Modal -->
<div id="myHazardsModal" class="modal">
    <div class="modal-content report-modal">
        <div class="modal-header">
            <h2 class="modal-title"><span class="material-icons text-danger">warning</span> My Hazard Reports</h2>
            <button class="modal-close" onclick="closeMyHazardsModal()"><span class="material-icons">close</span></button>
        </div>
        <div class="modal-body">
            <div class="report-preview-section">
                <h4>Report Preview</h4>
                <p>This report will include: Hazard incidents in your office; risk levels and affected populations; response actions taken; hazard statistics for your area.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeMyHazardsModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="generateMyHazardsReport()"><span class="material-icons">assessment</span> Generate My Hazards Report</button>
        </div>
    </div>
</div>
<!-- Report Preview Modal -->
<div id="reportPreviewModal" class="modal">
    <div class="modal-content report-preview-modal">
        <div class="modal-header">
            <h2 class="modal-title"><span class="material-icons">preview</span> Report Preview</h2>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="printReport()"><span class="material-icons">print</span> Print</button>
                <button class="btn btn-primary" onclick="exportReport()"><span class="material-icons">download</span> Export</button>
                <button class="modal-close" onclick="closeReportPreviewModal()"><span class="material-icons">close</span></button>
            </div>
        </div>
        <div class="modal-body">
            <div class="report-preview" id="reportPreviewContent"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Check URL parameters to determine which tab to show
    var urlParams = new URLSearchParams(window.location.search);
    var activeTab = urlParams.get('tab');
    
    var officeBtn = document.getElementById('office-request-reports-tab');
    var analyticsBtn = document.getElementById('data-analytics-tab');
    var officePane = document.getElementById('office-request-reports');
    var analyticsPane = document.getElementById('data-analytics');

    function showOffice() {
        if (officeBtn) {
            officeBtn.classList.add('active');
            officeBtn.setAttribute('aria-selected', 'true');
        }
        if (analyticsBtn) {
            analyticsBtn.classList.remove('active');
            analyticsBtn.setAttribute('aria-selected', 'false');
        }
        if (officePane) officePane.classList.add('show', 'active');
        if (analyticsPane) analyticsPane.classList.remove('show', 'active');
        // Keep user at top; prevents "tab click scroll illusion"
        window.scrollTo({ top: 0, behavior: 'auto' });
    }

    function showAnalytics() {
        if (analyticsBtn) {
            analyticsBtn.classList.add('active');
            analyticsBtn.setAttribute('aria-selected', 'true');
        }
        if (officeBtn) {
            officeBtn.classList.remove('active');
            officeBtn.setAttribute('aria-selected', 'false');
        }
        if (analyticsPane) analyticsPane.classList.add('show', 'active');
        if (officePane) officePane.classList.remove('show', 'active');
        // Keep user at top; prevents "tab click scroll illusion"
        window.scrollTo({ top: 0, behavior: 'auto' });
    }

    // Show the appropriate tab based on URL parameter
    if (activeTab === 'data-analytics') {
        showAnalytics();
    } else {
        // Default to Office Request Reports
        showOffice();
    }

    // Enhanced tab switching with proper cleanup
    var tabButtons = document.querySelectorAll('#pdrrmoReportsTabs button[data-bs-toggle="tab"]');
    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get target pane
            var targetId = btn.getAttribute('data-bs-target');
            var targetPane = document.querySelector(targetId);
            
            // Remove active/show from ALL tabs and panes first
            var allTabs = document.querySelectorAll('#pdrrmoReportsTabs .nav-link');
            var allPanes = document.querySelectorAll('#pdrrmoReportsTabContent .tab-pane');
            
            allTabs.forEach(function (t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            
            allPanes.forEach(function (p) {
                p.classList.remove('show', 'active');
            });
            
            // Add active/show to selected tab and pane
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
            if (targetPane) {
                targetPane.classList.add('show', 'active');
            }
            
            // Update URL without reloading
            var newTab = btn.id.replace('-tab', '');
            var newUrl = new URL(window.location);
            newUrl.searchParams.set('tab', newTab);
            window.history.pushState({}, '', newUrl);
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'auto' });
        });
    });

    // Handle section filtering in Data Analytics
    var sectionFilters = document.querySelectorAll('input[name="sectionFilter"]');
    if (sectionFilters.length > 0) {
        sectionFilters.forEach(function(radio) {
            radio.addEventListener('change', function() {
                var selectedVal = this.value;
                var allSections = document.querySelectorAll('.pdrrmo-analytics-section');
                
                allSections.forEach(function(section) {
                    if (selectedVal === 'all') {
                        section.style.display = 'block';
                    } else {
                        if (section.id === selectedVal) {
                            section.style.display = 'block';
                        } else {
                            section.style.display = 'none';
                        }
                    }
                });
            });
        });
    }
});
</script>
