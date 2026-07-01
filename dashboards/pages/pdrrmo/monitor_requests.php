<div class="monitor-requests-page">
    <link rel="stylesheet" href="assets/css/pages/monitor_requests.css">


    <!-- Filters and Search -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="row g-4 align-items-end">
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label text-uppercase text-muted fw-bold mb-2">Status</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3"><span class="material-icons text-muted" style="font-size: 18px;">assignment_turned_in</span></span>
                        <select class="form-select border-start-0 rounded-end-pill py-2" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="fulfilled">Received</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label for="municipalityFilter" class="form-label text-uppercase text-muted fw-bold mb-2">Municipality</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3"><span class="material-icons text-muted" style="font-size: 18px;">location_city</span></span>
                        <select class="form-select border-start-0 rounded-end-pill py-2" id="municipalityFilter">
                            <option value="">All Municipalities</option>
                            <!-- Options will be populated dynamically -->
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label for="priorityFilter" class="form-label text-uppercase text-muted fw-bold mb-2">Priority</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3"><span class="material-icons text-muted" style="font-size: 18px;">flag</span></span>
                        <select class="form-select border-start-0 rounded-end-pill py-2" id="priorityFilter">
                            <option value="">All Priorities</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label for="searchInput" class="form-label text-uppercase text-muted fw-bold mb-2">Search</label>
                    <div class="input-group">
                        <input type="text" class="form-control rounded-start-pill py-2 ps-3" id="searchInput" placeholder="Search requests...">
                        <span class="input-group-text bg-white rounded-end-pill pe-3">
                            <span class="material-icons text-primary">search</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="requestsTable">
                <thead class="table-light text-muted">
                    <tr>
                        <th scope="col" class="ps-4 fw-semibold py-3 border-bottom-0">Request ID</th>
                        <th scope="col" class="fw-semibold py-3 border-bottom-0">From → To</th>
                        <th scope="col" class="fw-semibold py-3 border-bottom-0">Resource Type</th>
                        <th scope="col" class="fw-semibold py-3 border-bottom-0">Quantity</th>
                        <th scope="col" class="fw-semibold py-3 border-bottom-0">Priority</th>
                        <th scope="col" class="fw-semibold py-3 border-bottom-0">Status</th>
                        <th scope="col" class="fw-semibold py-3 border-bottom-0">Request Date</th>
                        <th scope="col" class="pe-4 fw-semibold py-3 border-bottom-0 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody" class="border-top-0">
                    <!-- Data will be loaded directly without loading state -->
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="card-footer bg-white border-top py-3 px-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div class="text-muted small">
                Showing <span id="showingStart" class="fw-bold">0</span> to <span id="showingEnd" class="fw-bold">0</span> of <span id="totalRecords" class="fw-bold text-primary">0</span> requests
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary rounded-pill btn-sm d-flex align-items-center px-3" onclick="previousPage()" id="prevBtn" disabled>
                    <span class="material-icons me-1" style="font-size: 16px;">chevron_left</span> Previous
                </button>
                <div class="d-flex gap-1" id="pageNumbers"></div>
                <button class="btn btn-outline-secondary rounded-pill btn-sm d-flex align-items-center px-3" onclick="nextPage()" id="nextBtn" disabled>
                    Next <span class="material-icons ms-1" style="font-size: 16px;">chevron_right</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Request Details Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestDetailsModalLabel">
                    <span class="material-icons me-2">info</span>
                    Request Details
                </h5>
                <button type="button" class="btn-close" onclick="closeRequestDetailsModal()" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                <!-- Request details will be populated here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRequestDetailsModal()">Close</button>
            </div>
        </div>
    </div>
</div>