<div class="dashboard-page">
    <!-- Dashboard Statistics -->
    <style>
        .stat-cards-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
        }
        .stat-cards-grid .card {
            min-width: 0; /* Prevents flex/grid blowouts */
            overflow: hidden !important;
        }
        .stat-cards-grid .card-body {
            overflow: hidden !important;
            padding: 0 !important;
        }
        @media (max-width: 1400px) {
            .stat-cards-grid .stat-icon {
                margin-right: 0.5rem !important;
            }
            .stat-cards-grid .stat-number {
                font-size: 1.5rem !important;
            }
            .stat-cards-grid .stat-label {
                font-size: 0.75rem !important;
            }
        }
        @media (max-width: 1200px) {
            .stat-cards-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (max-width: 768px) {
            .stat-cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 576px) {
            .stat-cards-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div class="stat-cards-grid mb-4">
        <div class="card stat-card total-resources h-100" onclick="navigateToResources()" style="cursor: pointer; border-left: 4px solid var(--primary-color);">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon me-3">
                    <span class="material-icons text-primary fs-2">inventory_2</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number h3 mb-1" id="totalResources">0</div>
                    <div class="stat-label text-muted">Total Resources</div>
                </div>
            </div>
        </div>
        <div class="card stat-card pending-requests h-100" onclick="navigateToRequests()" style="cursor: pointer; border-left: 4px solid var(--warning-color);">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon me-3">
                    <span class="material-icons text-warning fs-2">pending_actions</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number h3 mb-1" id="pendingRequests">0</div>
                    <div class="stat-label text-muted">Pending Requests</div>
                </div>
            </div>
        </div>
        <div class="card stat-card approved-requests h-100" onclick="navigateToRequests()" style="cursor: pointer; border-left: 4px solid var(--success-color);">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon me-3">
                    <span class="material-icons text-success fs-2">check_circle</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number h3 mb-1" id="approvedRequests">0</div>
                    <div class="stat-label text-muted">Approved Requests</div>
                </div>
            </div>
        </div>
        <div class="card stat-card low-stock h-100" onclick="navigateToResources(); return false;" style="cursor: pointer; border-left: 4px solid var(--danger-color);">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon me-3">
                    <span class="material-icons text-danger fs-2">warning</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number h3 mb-1" id="lowStockItems">0</div>
                    <div class="stat-label text-muted">Low Stock Alert</div>
                </div>
            </div>
        </div>
        <div class="card stat-card active-hazards h-100" onclick="window.location.href='?page=hazard'; return false;" style="cursor: pointer; border-left: 4px solid #dc3545;">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon me-3">
                    <span class="material-icons fs-2" style="color: #dc3545;">emergency</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number h3 mb-1" id="activeHazards">0</div>
                    <div class="stat-label text-muted">Active Hazards</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Section -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <span class="material-icons me-2">map</span>
                        Zamboanga del Sur Municipalities Map
                    </h5>
                    <div class="btn-group" role="group">
                        <button class="btn btn-primary btn-sm" id="toggleMapView" title="Toggle Map View">
                            <span class="material-icons me-1">visibility</span>
                            <span id="toggleMapText">Show Map</span>
                        </button>
                    </div>
                </div>
                <div class="dashboard-map-container">
                    <div id="municipalityMap" class="municipality-map"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resources Section -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <!-- Resource Overview -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Resource Overview</h5>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary btn-sm" onclick="loadRealData()" title="Load Real Data">
                            <span class="material-icons" style="font-size: 16px;">refresh</span>
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="window.location.href='?page=resources'">View All</button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="resourceOverviewContent">
                        <div class="row g-3" id="resourceStats">
                            <div class="col-4">
                                <div class="p-3 rounded bg-light text-center">
                                    <div class="text-muted small">Availability</div>
                                    <div class="h5 mb-0">--%</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 rounded bg-light text-center">
                                    <div class="text-muted small">Low Stock</div>
                                    <div class="h5 mb-0">--</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 rounded bg-light text-center">
                                    <div class="text-muted small">Total Value</div>
                                    <div class="h5 mb-0">--</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <h6 class="text-muted mb-2">Category Distribution</h6>
                            <div id="resourceDistribution">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading resource data...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics (Chart.js) -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <span class="material-icons me-2">insights</span>
                        Analytics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <h6 class="mb-2">Stock Health</h6>
                            <div class="chart-container" style="height:240px;">
                                <canvas id="dashStockHealthChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <h6 class="mb-2">Top Requested Resources (30 days)</h6>
                            <div class="chart-container" style="height:240px;">
                                <canvas id="dashTopRequestedChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-12">
                            <h6 class="mb-2">Approval Lead Time (weeks)</h6>
                            <div class="chart-container" style="height:240px;">
                                <canvas id="dashResponseTimeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Leaflet CSS/JS for map interactions -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<!-- Leaflet MarkerCluster for decluttering markers -->
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<!-- Chart.js for analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard functionality
    initializeDashboard();
});

function initializeDashboard() {
    // Load dashboard data
    loadDashboardData();
    
    // Load resource overview automatically
    loadResourceOverview();
}

function loadDashboardData() {
    // Dashboard data loading code will be added here
    console.log('Dashboard data loaded');
}

async function loadResourceOverview() {
    const contentDiv = document.getElementById('resourceOverviewContent');
    if (!contentDiv) return;
    
    try {
        // Try different possible paths (new resilient endpoint first)
        const possiblePaths = [
            'config/resources_overview.php',
            '../../config/resources_overview.php',
            '/ConnectDRRM/config/resources_overview.php',
            'config/get_resource_overview.php',
            '../../config/get_resource_overview.php',
            '/ConnectDRRM/config/get_resource_overview.php'
        ];
        
        let response = null;
        let data = null;
        
        for (const path of possiblePaths) {
            try {
                console.log('Trying path:', path);
                response = await fetch(path);
                if (response.ok) {
                    data = await response.json();
                    console.log('Success with path:', path, data);
                    break;
                }
            } catch (e) {
                console.log('Failed with path:', path, e.message);
                continue;
            }
        }
        
        if (data && data.success) {
            // The resilient endpoint returns {data: { groupBy, items }}
            if (data.data && data.data.items && Array.isArray(data.data.items)) {
                displayResourceOverviewFromItems(data.data);
            } else if (data.data && data.data.categories) {
                displayResourceOverview(data.data);
            } else {
                throw new Error('Unexpected payload');
            }
        } else {
            throw new Error(data?.error || 'No data received');
        }
    } catch (error) {
        console.error('Error loading resource overview:', error);
        showResourceOverviewError();
    }
}

async function loadRealData() {
    await loadResourceOverview();
}

function displayResourceOverview(data) {
    const { categories = [], stats = {} } = data || {};
    const container = document.getElementById('resourceOverviewContent');
    if (!container) return;

    // Derive totals
    const totalResources = Number(stats.totalResources ?? 0);
    const totalStock = Number(stats.totalStock ?? 0);
    const lowStock = Number(stats.lowStockCount ?? 0);
    const availability = Number(stats.availabilityPercentage ?? 0);

    // Prepare top categories (compact view)
    const cats = [...categories];
    cats.sort((a,b) => (Number(b.totalStock||0) - Number(a.totalStock||0)));
    const topN = 6;
    const top = cats.slice(0, topN);
    const others = cats.slice(topN);
    const othersItemCount = others.reduce((s,c)=> s + Number(c.resourceCount||0), 0);
    const othersStock = others.reduce((s,c)=> s + Number(c.totalStock||0), 0);

    const maxStock = Math.max(1, ...top.map(c => Number(c.totalStock||0)), othersStock);
    const pct = (v) => Math.round((Number(v||0) / maxStock) * 100);
    const num = (v) => Number(v||0).toLocaleString();

    container.innerHTML = `
        <div class="row g-2" id="resourceStats">
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center p-2 bg-light rounded border">
                    <span class="material-icons text-success me-2">check_circle</span>
                    <div>
                        <div class="text-muted small">Availability</div>
                        <div class="fw-bold">${availability}%</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center p-2 bg-light rounded border">
                    <span class="material-icons text-danger me-2">warning</span>
                    <div>
                        <div class="text-muted small">Low Stock</div>
                        <div class="fw-bold">${num(lowStock)}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center p-2 bg-light rounded border">
                    <span class="material-icons text-primary me-2">inventory_2</span>
                    <div>
                        <div class="text-muted small">Total Items</div>
                        <div class="fw-bold">${num(totalResources)}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center p-2 bg-light rounded border">
                    <span class="material-icons text-info me-2">countertops</span>
                    <div>
                        <div class="text-muted small">Total Stock</div>
                        <div class="fw-bold">${num(totalStock)}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category</th>
                            <th class="text-end">Items</th>
                            <th class="text-end">Stock</th>
                            <th style="width:140px;">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${top.map(c => `
                            <tr>
                                <td>${(c.categoryName || 'Uncategorized')}</td>
                                <td class="text-end">${num(c.resourceCount)}</td>
                                <td class="text-end">${num(c.totalStock)}</td>
                                <td>
                                    <div class="progress" style="height:6px;">
                                        <div class="progress-bar bg-primary" style="width:${pct(c.totalStock)}%"></div>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                        ${othersItemCount + othersStock > 0 ? `
                            <tr class="table-active">
                                <td>Others</td>
                                <td class="text-end">${num(othersItemCount)}</td>
                                <td class="text-end">${num(othersStock)}</td>
                                <td>
                                    <div class="progress" style="height:6px;">
                                        <div class="progress-bar bg-secondary" style="width:${pct(othersStock)}%"></div>
                                    </div>
                                </td>
                            </tr>
                        ` : ''}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

// Render when API provides { groupBy, items: [{label, itemCount, totalStock}] }
function displayResourceOverviewFromItems(payload) {
    const { items = [] } = payload || {};
    const container = document.getElementById('resourceOverviewContent');
    if (!container) return;

    // Compute simple totals
    const totalResources = items.reduce((s,i)=> s + Number(i.itemCount||0), 0);
    const totalStock = items.reduce((s,i)=> s + Number(i.totalStock||0), 0);

    const topN = 6;
    const sorted = [...items].sort((a,b)=> Number(b.totalStock||0) - Number(a.totalStock||0));
    const top = sorted.slice(0, topN);
    const others = sorted.slice(topN);
    const othersItemCount = others.reduce((s,i)=> s + Number(i.itemCount||0), 0);
    const othersStock = others.reduce((s,i)=> s + Number(i.totalStock||0), 0);
    const maxStock = Math.max(1, ...top.map(i=> Number(i.totalStock||0)), othersStock);
    const pct = (v) => Math.round((Number(v||0) / maxStock) * 100);
    const num = (v) => Number(v||0).toLocaleString();

    container.innerHTML = `
        <div class="row g-2" id="resourceStats">
            <div class="col-6 col-md-4">
                <div class="d-flex align-items-center p-2 bg-light rounded border">
                    <span class="material-icons text-primary me-2">inventory_2</span>
                    <div>
                        <div class="text-muted small">Total Items</div>
                        <div class="fw-bold">${num(totalResources)}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="d-flex align-items-center p-2 bg-light rounded border">
                    <span class="material-icons text-info me-2">countertops</span>
                    <div>
                        <div class="text-muted small">Total Stock</div>
                        <div class="fw-bold">${num(totalStock)}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>${payload.groupBy || 'Category'}</th>
                            <th class="text-end">Items</th>
                            <th class="text-end">Stock</th>
                            <th style="width:140px;">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${top.map(i => `
                            <tr>
                                <td>${i.label || 'Unspecified'}</td>
                                <td class="text-end">${num(i.itemCount)}</td>
                                <td class="text-end">${num(i.totalStock)}</td>
                                <td>
                                    <div class="progress" style="height:6px;">
                                        <div class="progress-bar bg-primary" style="width:${pct(i.totalStock)}%"></div>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                        ${othersItemCount + othersStock > 0 ? `
                            <tr class="table-active">
                                <td>Others</td>
                                <td class="text-end">${num(othersItemCount)}</td>
                                <td class="text-end">${num(othersStock)}</td>
                                <td>
                                    <div class="progress" style="height:6px;">
                                        <div class="progress-bar bg-secondary" style="width:${pct(othersStock)}%"></div>
                                    </div>
                                </td>
                            </tr>
                        ` : ''}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

function getCategoryIcon(categoryName) {
    const iconMap = {
        'Emergency Supplies': '🏥',
        'Medical Equipment': '⚕️',
        'Rescue Equipment': '🚑',
        'Food Supplies': '🍚',
        'Relief Supplies': '📦',
        'Medical': '⚕️',
        'Emergency': '🚨',
        'Food': '🍚',
        'Rescue': '🚑',
        'Medical Supplies': '⚕️',
        'Emergency Equipment': '🚨',
        'Communication Equipment': '📡',
        'Food & Relief': '🍚',
        'Water & Sanitation': '💧',
        'General': '📦'
    };
    
    return iconMap[categoryName] || '📦';
}

function getCategoryColor(categoryName) {
    const colorMap = {
        'Emergency Supplies': 'bg-danger',
        'Medical Equipment': 'bg-primary',
        'Rescue Equipment': 'bg-warning',
        'Food Supplies': 'bg-success',
        'Relief Supplies': 'bg-info',
        'Medical': 'bg-primary',
        'Emergency': 'bg-danger',
        'Food': 'bg-success',
        'Rescue': 'bg-warning',
        'Medical Supplies': 'bg-primary',
        'Emergency Equipment': 'bg-danger',
        'Communication Equipment': 'bg-info',
        'Food & Relief': 'bg-success',
        'Water & Sanitation': 'bg-info',
        'General': 'bg-secondary'
    };
    
    return colorMap[categoryName] || 'bg-secondary';
}

function formatCurrency(amount) {
    if (amount >= 1000000) {
        return '₱' + (amount / 1000000).toFixed(1) + 'M';
    } else if (amount >= 1000) {
        return '₱' + (amount / 1000).toFixed(0) + 'K';
    } else {
        return '₱' + amount.toFixed(0);
    }
}

function showResourceOverviewError() {
    const contentDiv = document.getElementById('resourceOverviewContent');
    if (!contentDiv) return;
    contentDiv.innerHTML = `
        <div class="text-center py-4">
            <span class="material-icons text-danger" style="font-size: 32px;">error</span>
            <p class="mt-2 text-muted mb-0">Failed to load resource overview. Please try again.</p>
        </div>
    `;
}
</script>

