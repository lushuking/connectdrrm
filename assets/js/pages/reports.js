// DRRM Reports Page JavaScript - Connected to Real Data
class DRRMReportsManager {
    constructor() {
        this.currentReport = null;
        this.reportData = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setDefaultDates();
        this.initializeCharts();
        this.loadDRRMOverview();
    }

    setupEventListeners() {
        // Form validation
        const reportForm = document.getElementById('reportForm');
        if (reportForm) {
            reportForm.addEventListener('submit', this.handleFormSubmit.bind(this));
        }

        // Modal close handlers
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeAllModals();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }

    setDefaultDates() {
        const today = new Date();
        const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
        
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        
        if (startDateInput) {
            startDateInput.value = lastMonth.toISOString().split('T')[0];
        }
        if (endDateInput) {
            endDateInput.value = today.toISOString().split('T')[0];
        }
    }

    async loadDRRMOverview() {
        try {
            const response = await fetch('config/drrm_reports_api.php?action=overview');
            const data = await response.json();
            
            if (data.success) {
                this.updateOverviewCards(data.data);
            }
        } catch (error) {
            console.error('Error loading DRRM overview:', error);
        }
    }

    updateOverviewCards(data) {
        // Update overview cards with real data using standard CSS selectors
        let municipalityCard = null;
        let resourceCard = null;
        let requestCard = null;
        let hazardCard = null;

        document.querySelectorAll('.card').forEach(card => {
            card.querySelectorAll('.material-icons').forEach(icon => {
                const text = icon.textContent.trim();
                if (text === 'location_city') {
                    municipalityCard = card;
                } else if (text === 'inventory') {
                    resourceCard = card;
                } else if (text === 'request_quote') {
                    requestCard = card;
                } else if (text === 'warning') {
                    hazardCard = card;
                }
            });
        });

        if (municipalityCard) {
            const h3 = municipalityCard.querySelector('h3');
            if (h3) h3.textContent = data.municipalities || '0';
        }
        if (resourceCard) {
            const h3 = resourceCard.querySelector('h3');
            if (h3) h3.textContent = (data.resources && data.resources.totalResources) || '0';
        }
        if (requestCard) {
            const h3 = requestCard.querySelector('h3');
            if (h3) h3.textContent = (data.requests && data.requests.pendingRequests) || '0';
        }
        if (hazardCard) {
            const h3 = hazardCard.querySelector('h3');
            if (h3) h3.textContent = (data.hazards && data.hazards.active) || '0';
        }
    }


    handleFormSubmit(e) {
        e.preventDefault();
        this.generateReport();
    }



    openReportPreviewModal() {
        const modal = document.getElementById('reportPreviewModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    closeReportPreviewModal() {
        const modal = document.getElementById('reportPreviewModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    closeAllModals() {
        this.closeReportPreviewModal();
        this.closeAllSpecificModals();
    }

    closeAllSpecificModals() {
        const modals = [
            'myResourcesModal',
            'borrowedResourcesModal', 
            'myRequestsModal',
            'myHazardsModal',
            'myPerformanceModal',
            'resourceUtilizationModal',
            'emergencyPreparednessModal',
            'monthlySummaryModal'
        ];

        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        });
        document.body.style.overflow = 'auto';
    }




    renderReportPreview() {
        const previewContent = document.getElementById('reportPreviewContent');
        if (!previewContent || !this.currentReport || !this.reportData) return;

        const reportHtml = this.generateReportHTML();
        previewContent.innerHTML = reportHtml;

        // Render charts inside the report preview
        if (this.currentReport.includeCharts) {
            // Defer to ensure DOM is painted
            setTimeout(() => {
                this.renderResourceSharingChartInto('reportResourceSharingChart');
            }, 0);
        }
    }

    generateReportHTML() {
        const report = this.currentReport;
        const data = this.reportData;
        const logoUrl = data && (data.logoUrl || data.logo_url) ? String(data.logoUrl || data.logo_url) : '';
        const logoHtml = logoUrl
            ? `<img src="${logoUrl}" alt="Municipality Logo" style="width:72px;height:72px;border-radius:50%;object-fit:contain;display:block;margin:0 auto 10px;" />`
            : '';

        return `
            <div class="report-header">
                ${logoHtml}
                <h1 class="report-title">${report.reportTitle}</h1>
                ${report.reportDescription ? `<p class="report-description">${report.reportDescription}</p>` : ''}
                <div class="report-meta">
                    <strong>Generated:</strong> ${new Date().toLocaleString()} | 
                    <strong>Period:</strong> ${new Date(report.startDate).toLocaleDateString()} - ${new Date(report.endDate).toLocaleDateString()}
                </div>
            </div>

            <div class="report-section">
                <h3>Executive Summary</h3>
                <div class="report-summary">
                    <h4>Key Findings</h4>
                    <p>${this.generateSummaryText(report.reportType, data)}</p>
                </div>
            </div>

            ${report.includeCharts ? `
            <div class="report-section">
                <h3>Data Visualizations</h3>
                <div class="report-chart" style="height:280px;">
                    <canvas id="reportResourceSharingChart"></canvas>
                </div>
            </div>
            ` : ''}

            ${report.includeDetails ? `
            <div class="report-section">
                <h3>Detailed Analysis</h3>
                ${this.generateDetailsContent(report.reportType, data)}
            </div>
            ` : ''}

            ${report.includeRecommendations ? `
            <div class="report-section">
                <h3>Recommendations</h3>
                <div class="report-summary">
                    <h4>Action Items</h4>
                    <ul>
                        ${this.generateRecommendations(report.reportType)}
                    </ul>
                </div>
            </div>
            ` : ''}

            <div class="report-section">
                <h3>Report Information</h3>
                <table class="report-table">
                    <tr>
                        <th>Generated By</th>
                        <td>ConnectDRRM System</td>
                    </tr>
                    <tr>
                        <th>Report ID</th>
                        <td>DRRM-${Date.now()}</td>
                    </tr>
                    <tr>
                        <th>Report Type</th>
                        <td>${report.reportType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</td>
                    </tr>
                    <tr>
                        <th>Export Format</th>
                        <td>${report.exportFormat.toUpperCase()}</td>
                    </tr>
                </table>
            </div>

            <div class="report-metadata">
                <div class="metadata-item">
                    <span class="metadata-label">Report Type:</span>
                    <span class="metadata-value">${report.reportType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">Date Range:</span>
                    <span class="metadata-value">${new Date(report.startDate).toLocaleDateString()} - ${new Date(report.endDate).toLocaleDateString()}</span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">Generated:</span>
                    <span class="metadata-value">${new Date().toLocaleString()}</span>
                </div>
            </div>
        `;
    }

    generateSummaryText(type, data) {
        switch (type) {
            case 'my_resources':
                const resourceCount = data.myResources?.length || 0;
                if (resourceCount === 0) {
                    return 'No resources are currently registered for your municipality.';
                }
                return `Your municipality has ${resourceCount} resources with varying stock levels.`;
            case 'borrowed_resources':
                const borrowedCount = data.borrowedRequests?.length || 0;
                if (borrowedCount === 0) {
                    return 'No resources have been borrowed from your municipality.';
                }
                return `Your municipality has received ${borrowedCount} resource requests from other municipalities.`;
            case 'my_requests':
                const requestCount = data.myRequests?.length || 0;
                if (requestCount === 0) {
                    return 'Your municipality has not made any resource requests to other municipalities.';
                }
                return `Your municipality has made ${requestCount} resource requests to other municipalities.`;
            case 'my_hazards':
                const hazardCount = data.myHazards?.length || 0;
                if (hazardCount === 0) {
                    return 'No hazard incidents have been reported for your municipality.';
                }
                return `Your municipality has reported ${hazardCount} hazard incidents.`;
            case 'resource_inventory':
                return `Resource inventory analysis shows ${data.municipalityResources?.length || 0} municipalities with varying resource levels. ${data.lowStockItems?.length || 0} items require immediate attention.`;
            case 'request_management':
                return `Request management analysis reveals ${data.statistics?.totalRequests || 0} total requests with ${data.statistics?.fulfilledRequests || 0} successfully fulfilled.`;
            case 'hazard_assessment':
                return `Hazard assessment shows ${data.statistics?.totalHazards || 0} total hazards with ${data.statistics?.activeHazards || 0} currently active.`;
            case 'municipality_performance':
                return `Municipality performance analysis covers ${data.length || 0} municipalities with varying DRRM capacity levels.`;
            default:
                return 'Comprehensive DRRM analysis completed successfully.';
        }
    }

    generateDetailsContent(type, data) {
        switch (type) {
            case 'my_resources':
                return this.generateMyResourcesDetails(data);
            case 'borrowed_resources':
                return this.generateBorrowedResourcesDetails(data);
            case 'my_requests':
                return this.generateMyRequestsDetails(data);
            case 'my_hazards':
                return this.generateMyHazardsDetails(data);
            case 'resource_inventory':
                return this.generateResourceInventoryDetails(data);
            case 'request_management':
                return this.generateRequestManagementDetails(data);
            case 'hazard_assessment':
                return this.generateHazardAssessmentDetails(data);
            default:
                return '<p>Detailed analysis content would be generated here based on the report type.</p>';
        }
    }

    generateResourceInventoryDetails(data) {
        if (!data.municipalityResources || data.municipalityResources.length === 0) {
            return '<p>No resource inventory data available.</p>';
        }

        const headers = ['Municipality', 'Total Resources', 'Total Stock', 'Low Stock Items', 'Well Stocked Items'];
        const headerRow = headers.map(header => `<th>${header}</th>`).join('');
        const dataRows = data.municipalityResources.slice(0, 10).map(municipality => 
            `<tr>
                <td>${municipality.municipality}</td>
                <td>${municipality.totalResources}</td>
                <td>${municipality.totalStock}</td>
                <td>${municipality.lowStockItems}</td>
                <td>${municipality.wellStockedItems}</td>
            </tr>`
        ).join('');

        return `
            <table class="report-table">
                <thead>
                    <tr>${headerRow}</tr>
                </thead>
                <tbody>
                    ${dataRows}
                </tbody>
            </table>
        `;
    }

    generateRequestManagementDetails(data) {
        if (!data.statistics) {
            return '<p>No request management data available.</p>';
        }

        return `
            <div class="report-summary">
                <h4>Request Statistics</h4>
                <p>Total Requests: ${data.statistics.totalRequests}</p>
                <p>Pending: ${data.statistics.pendingRequests}</p>
                <p>Approved: ${data.statistics.approvedRequests}</p>
                <p>Fulfilled: ${data.statistics.fulfilledRequests}</p>
                <p>Average Response Time: ${Math.round(data.statistics.avgResponseTime || 0)} hours</p>
            </div>
        `;
    }

    generateHazardAssessmentDetails(data) {
        if (!data.statistics) {
            return '<p>No hazard assessment data available.</p>';
        }

        return `
            <div class="report-summary">
                <h4>Hazard Statistics</h4>
                <p>Total Hazards: ${data.statistics.totalHazards}</p>
                <p>Active Hazards: ${data.statistics.activeHazards}</p>
                <p>High Risk: ${data.statistics.highRisk}</p>
                <p>Medium Risk: ${data.statistics.mediumRisk}</p>
                <p>Low Risk: ${data.statistics.lowRisk}</p>
                <p>Total People Affected: ${data.statistics.totalPeopleAffected || 0}</p>
            </div>
        `;
    }

    generateMyResourcesDetails(data) {
        if (!data.myResources || data.myResources.length === 0) {
            return '<p><strong>No data available:</strong> Your municipality has not registered any resources in the system.</p>';
        }

        const headers = ['Resource Name', 'Category', 'Available Stock', 'Unit', 'Status'];
        const headerRow = headers.map(header => `<th>${header}</th>`).join('');
        const dataRows = data.myResources.slice(0, 10).map(resource => 
            `<tr>
                <td>${resource.resourceName || 'N/A'}</td>
                <td>${resource.category || 'N/A'}</td>
                <td>${resource.availableStock || 0}</td>
                <td>${resource.unit || 'N/A'}</td>
                <td>${resource.stockStatus || 'Unknown'}</td>
            </tr>`
        ).join('');

        return `
            <div class="report-summary">
                <h4>Resource Inventory</h4>
                <p>Total Resources: ${data.myResources.length}</p>
                ${data.categoryDistribution ? `<p>Categories: ${data.categoryDistribution.length}</p>` : ''}
                ${data.lowStockItems ? `<p>Low Stock Items: ${data.lowStockItems.length}</p>` : ''}
            </div>
            <table class="report-table">
                <thead>
                    <tr>${headerRow}</tr>
                </thead>
                <tbody>
                    ${dataRows}
                </tbody>
            </table>
            ${data.myResources.length > 10 ? `<p><em>Showing first 10 resources. Total: ${data.myResources.length}</em></p>` : ''}
        `;
    }

    generateBorrowedResourcesDetails(data) {
        if (!data.borrowedRequests || data.borrowedRequests.length === 0) {
            return '<p><strong>No data available:</strong> No other municipalities have requested resources from your municipality.</p>';
        }

        const headers = ['Request ID', 'From Municipality', 'Resource', 'Quantity', 'Priority', 'Status', 'Request Date'];
        const headerRow = headers.map(header => `<th>${header}</th>`).join('');
        const dataRows = data.borrowedRequests.slice(0, 10).map(request => 
            `<tr>
                <td>${request.requestID || 'N/A'}</td>
                <td>${request.fromMunicipality || 'N/A'}</td>
                <td>${request.resourceName || 'N/A'}</td>
                <td>${request.quantity || 0}</td>
                <td>${request.priority || 'N/A'}</td>
                <td>${request.status || 'N/A'}</td>
                <td>${request.requestDate ? new Date(request.requestDate).toLocaleDateString() : 'N/A'}</td>
            </tr>`
        ).join('');

        return `
            <div class="report-summary">
                <h4>Resource Requests Received</h4>
                <p>Total Requests: ${data.borrowedRequests.length}</p>
                ${data.statistics ? `
                    <p>Pending: ${data.statistics.pendingBorrowed || 0}</p>
                    <p>Approved: ${data.statistics.approvedBorrowed || 0}</p>
                    <p>Fulfilled: ${data.statistics.fulfilledBorrowed || 0}</p>
                ` : ''}
            </div>
            <table class="report-table">
                <thead>
                    <tr>${headerRow}</tr>
                </thead>
                <tbody>
                    ${dataRows}
                </tbody>
            </table>
            ${data.borrowedRequests.length > 10 ? `<p><em>Showing first 10 requests. Total: ${data.borrowedRequests.length}</em></p>` : ''}
        `;
    }

    generateMyRequestsDetails(data) {
        if (!data.myRequests || data.myRequests.length === 0) {
            return '<p><strong>No data available:</strong> Your municipality has not made any resource requests to other municipalities.</p>';
        }

        const headers = ['Request ID', 'To Municipality', 'Resource', 'Quantity', 'Priority', 'Status', 'Request Date'];
        const headerRow = headers.map(header => `<th>${header}</th>`).join('');
        const dataRows = data.myRequests.slice(0, 10).map(request => 
            `<tr>
                <td>${request.requestID || 'N/A'}</td>
                <td>${request.toMunicipality || 'N/A'}</td>
                <td>${request.resourceName || 'N/A'}</td>
                <td>${request.quantity || 0}</td>
                <td>${request.priority || 'N/A'}</td>
                <td>${request.status || 'N/A'}</td>
                <td>${request.requestDate ? new Date(request.requestDate).toLocaleDateString() : 'N/A'}</td>
            </tr>`
        ).join('');

        return `
            <div class="report-summary">
                <h4>Resource Requests Made</h4>
                <p>Total Requests: ${data.myRequests.length}</p>
                ${data.statistics ? `
                    <p>Pending: ${data.statistics.pendingRequests || 0}</p>
                    <p>Approved: ${data.statistics.approvedRequests || 0}</p>
                    <p>Fulfilled: ${data.statistics.fulfilledRequests || 0}</p>
                ` : ''}
            </div>
            <table class="report-table">
                <thead>
                    <tr>${headerRow}</tr>
                </thead>
                <tbody>
                    ${dataRows}
                </tbody>
            </table>
            ${data.myRequests.length > 10 ? `<p><em>Showing first 10 requests. Total: ${data.myRequests.length}</em></p>` : ''}
        `;
    }

    generateMyHazardsDetails(data) {
        if (!data.myHazards || data.myHazards.length === 0) {
            return '<p><strong>No data available:</strong> No hazard incidents have been reported for your municipality.</p>';
        }

        const headers = ['Hazard Type', 'Intensity', 'Location', 'People Affected', 'Reported Date', 'Status'];
        const headerRow = headers.map(header => `<th>${header}</th>`).join('');
        const dataRows = data.myHazards.slice(0, 10).map(hazard => 
            `<tr>
                <td>${hazard.hazardType || 'N/A'}</td>
                <td>${hazard.intensity || 'N/A'}</td>
                <td>${hazard.location || 'N/A'}</td>
                <td>${hazard.affectedPopulation || 0}</td>
                <td>${hazard.reportedAt ? new Date(hazard.reportedAt).toLocaleDateString() : 'N/A'}</td>
                <td>${hazard.status || 'N/A'}</td>
            </tr>`
        ).join('');

        return `
            <div class="report-summary">
                <h4>Hazard Incidents</h4>
                <p>Total Hazards: ${data.myHazards.length}</p>
                ${data.statistics ? `
                    <p>Active: ${data.statistics.activeHazards || 0}</p>
                    <p>High Risk: ${data.statistics.highRisk || 0}</p>
                    <p>Medium Risk: ${data.statistics.mediumRisk || 0}</p>
                    <p>Low Risk: ${data.statistics.lowRisk || 0}</p>
                    <p>Total People Affected: ${data.statistics.totalPeopleAffected || 0}</p>
                ` : ''}
            </div>
            <table class="report-table">
                <thead>
                    <tr>${headerRow}</tr>
                </thead>
                <tbody>
                    ${dataRows}
                </tbody>
            </table>
            ${data.myHazards.length > 10 ? `<p><em>Showing first 10 hazards. Total: ${data.myHazards.length}</em></p>` : ''}
        `;
    }

    generateRecommendations(type) {
        const recommendations = {
            'resource_inventory': [
                'Review and restock low-inventory items immediately',
                'Implement automated inventory alerts for critical resources',
                'Develop resource sharing protocols between municipalities',
                'Conduct regular inventory audits'
            ],
            'request_management': [
                'Improve request processing times',
                'Implement automated request tracking system',
                'Develop standard operating procedures for request fulfillment',
                'Enhance inter-municipality communication protocols'
            ],
            'hazard_assessment': [
                'Strengthen early warning systems',
                'Improve hazard monitoring capabilities',
                'Develop comprehensive emergency response plans',
                'Enhance community preparedness programs'
            ],
            'municipality_performance': [
                'Provide additional training for DRRM personnel',
                'Improve resource allocation strategies',
                'Enhance coordination between municipalities',
                'Develop performance monitoring systems'
            ]
        };

        const typeRecommendations = recommendations[type] || [
            'Review current DRRM policies and procedures',
            'Enhance coordination between stakeholders',
            'Improve data collection and reporting systems',
            'Develop comprehensive training programs'
        ];

        return typeRecommendations.map(rec => `<li>${rec}</li>`).join('');
    }

    async initializeCharts() {
        // Ensure Chart.js is available, then render charts from real data
        if (typeof Chart === 'undefined') {
            try { await this.loadChartJS(); } catch (_) { /* ignore */ }
        }
        await this.renderMyResourceChart();
        await this.renderBorrowedFromUsChart();
        await this.renderMyRequestChart();
        await this.renderMyHazardChart();
        await this.renderResourceSharingChart();
    }

    loadChartJS() {
        return new Promise((resolve, reject) => {
            if (typeof Chart !== 'undefined') {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    // Deprecated: replaced by real-data renderers
    createSampleCharts() {}

    async renderMyResourceChart() {
        const el = document.getElementById('myResourceChart');
        if (!el || typeof Chart === 'undefined') return;
        try {
            const res = await fetch('config/drrm_reports_api.php?action=my_resources', { credentials: 'same-origin' });
            const json = await res.json();
            if (!json || !json.success) throw new Error(json && json.error ? json.error : 'Failed');
            const dist = Array.isArray(json.data?.categoryDistribution) ? json.data.categoryDistribution : [];
            const labels = dist.map(d => d.category || 'Unspecified');
            const counts = dist.map(d => Number(d.count || 0));
            const colors = labels.map((_, i) => {
                const palette = ['#ef4444','#3b82f6','#22c55e','#f59e0b','#a855f7','#06b6d4','#e11d48','#84cc16'];
                return palette[i % palette.length];
            });
            new Chart(el, {
                type: 'doughnut',
                data: { labels, datasets: [{ data: counts, backgroundColor: colors, borderWidth: 1 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { title: { display: true, text: 'My Resource Categories' }, legend: { position: 'bottom' } }
                }
            });
        } catch (e) {
            // silently ignore
        }
    }

    async renderBorrowedFromUsChart() {
        const el = document.getElementById('borrowedFromUsChart');
        if (!el || typeof Chart === 'undefined') return;
        try {
            const res = await fetch('config/drrm_reports_api.php?action=borrowed_resources', { credentials: 'same-origin' });
            const json = await res.json();
            if (!json || !json.success) throw new Error(json && json.error ? json.error : 'Failed');
            const items = Array.isArray(json.data?.borrowedRequests) ? json.data.borrowedRequests : [];
            
            // Count by status
            const counts = { 
                pending: 0, 
                approved: 0, 
                fulfilled: 0, 
                rejected: 0,
                'return pending': 0,
                returned: 0
            };
            items.forEach(r => { 
                const s = String(r.status || '').toLowerCase(); 
                if (counts.hasOwnProperty(s)) counts[s]++;
            });
            
            // Filter out zero values for cleaner chart
            const labels = [];
            const data = [];
            const bg = [];
            
            if (counts.pending > 0) {
                labels.push('Pending');
                data.push(counts.pending);
                bg.push('#f59e0b');
            }
            if (counts.approved > 0) {
                labels.push('Approved');
                data.push(counts.approved);
                bg.push('#3b82f6');
            }
            if (counts.fulfilled > 0) {
                labels.push('Fulfilled');
                data.push(counts.fulfilled);
                bg.push('#22c55e');
            }
            if (counts['return pending'] > 0) {
                labels.push('Return Pending');
                data.push(counts['return pending']);
                bg.push('#06b6d4');
            }
            if (counts.returned > 0) {
                labels.push('Returned');
                data.push(counts.returned);
                bg.push('#10b981');
            }
            if (counts.rejected > 0) {
                labels.push('Rejected');
                data.push(counts.rejected);
                bg.push('#ef4444');
            }
            
            // If no data, show empty state
            if (labels.length === 0) {
                labels.push('No Data');
                data.push(1);
                bg.push('#e5e7eb');
            }
            
            new Chart(el, {
                type: 'doughnut',
                data: { 
                    labels, 
                    datasets: [{ 
                        label: 'Resources Borrowed From Us', 
                        data, 
                        backgroundColor: bg, 
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }] 
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { 
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 8,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Error rendering borrowed from us chart:', e);
        }
    }

    async renderMyRequestChart() {
        const el = document.getElementById('myRequestChart');
        if (!el || typeof Chart === 'undefined') return;
        try {
            const res = await fetch('config/drrm_reports_api.php?action=my_requests', { credentials: 'same-origin' });
            const json = await res.json();
            if (!json || !json.success) throw new Error(json && json.error ? json.error : 'Failed');
            const items = Array.isArray(json.data?.myRequests) ? json.data.myRequests : [];
            
            // Count by status - only show requests made by this municipality
            const counts = { 
                pending: 0, 
                approved: 0, 
                fulfilled: 0, 
                rejected: 0,
                'return pending': 0,
                returned: 0
            };
            items.forEach(r => { 
                const s = String(r.status || '').toLowerCase(); 
                if (counts.hasOwnProperty(s)) counts[s]++;
            });
            
            const labels = [];
            const data = [];
            const bg = [];
            
            if (counts.pending > 0) {
                labels.push('Pending');
                data.push(counts.pending);
                bg.push('#f59e0b');
            }
            if (counts.approved > 0) {
                labels.push('Approved');
                data.push(counts.approved);
                bg.push('#3b82f6');
            }
            if (counts.fulfilled > 0) {
                labels.push('Fulfilled');
                data.push(counts.fulfilled);
                bg.push('#22c55e');
            }
            if (counts['return pending'] > 0) {
                labels.push('Return Pending');
                data.push(counts['return pending']);
                bg.push('#06b6d4');
            }
            if (counts.returned > 0) {
                labels.push('Returned');
                data.push(counts.returned);
                bg.push('#10b981');
            }
            if (counts.rejected > 0) {
                labels.push('Rejected');
                data.push(counts.rejected);
                bg.push('#ef4444');
            }
            
            if (labels.length === 0) {
                labels.push('No Data');
                data.push(1);
                bg.push('#e5e7eb');
            }
            
            new Chart(el, {
                type: 'bar',
                data: { 
                    labels, 
                    datasets: [{ 
                        label: 'My Resource Requests', 
                        data, 
                        backgroundColor: bg, 
                        borderWidth: 1,
                        borderRadius: 4
                    }] 
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { 
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed.x}`;
                                }
                            }
                        }
                    },
                    scales: { 
                        x: { 
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            }
                        },
                        y: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Error rendering my request chart:', e);
        }
    }

    async renderMyHazardChart() {
        const el = document.getElementById('myHazardChart');
        if (!el || typeof Chart === 'undefined') return;
        try {
            const res = await fetch('config/drrm_reports_api.php?action=my_hazards', { credentials: 'same-origin' });
            const json = await res.json();
            if (!json || !json.success) {
                // Show empty state if hazards table doesn't exist
                new Chart(el, {
                    type: 'doughnut',
                    data: {
                        labels: ['No Hazard Data'],
                        datasets: [{
                            data: [1],
                            backgroundColor: ['#e5e7eb'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { enabled: false }
                        }
                    }
                });
                return;
            }
            
            const items = Array.isArray(json.data?.myHazards) ? json.data.myHazards : [];
            const stats = json.data?.statistics || {};
            
            // Create visualization based on hazard types and intensities
            const typeCounts = {};
            const intensityCounts = { High: 0, Medium: 0, Low: 0 };
            
            items.forEach(h => {
                // Count by type
                const type = h.hazardType || 'Unspecified';
                typeCounts[type] = (typeCounts[type] || 0) + 1;
                
                // Count by intensity
                const intensity = h.intensity || 'Low';
                if (intensityCounts.hasOwnProperty(intensity)) {
                    intensityCounts[intensity]++;
                }
            });
            
            // Create chart showing hazard types (if available) or intensities
            let labels, data, bg;
            
            if (Object.keys(typeCounts).length > 0) {
                // Show top 5 hazard types
                const sortedTypes = Object.entries(typeCounts)
                    .sort((a, b) => b[1] - a[1])
                    .slice(0, 5);
                labels = sortedTypes.map(t => t[0]);
                data = sortedTypes.map(t => t[1]);
                bg = ['#ef4444', '#f59e0b', '#eab308', '#84cc16', '#06b6d4'].slice(0, labels.length);
            } else if (stats.totalHazards > 0) {
                // Fallback to intensity if no type data
                labels = [];
                data = [];
                bg = [];
                if (stats.highRisk > 0) {
                    labels.push('High Risk');
                    data.push(stats.highRisk);
                    bg.push('#ef4444');
                }
                if (stats.mediumRisk > 0) {
                    labels.push('Medium Risk');
                    data.push(stats.mediumRisk);
                    bg.push('#f59e0b');
                }
                if (stats.lowRisk > 0) {
                    labels.push('Low Risk');
                    data.push(stats.lowRisk);
                    bg.push('#eab308');
                }
            } else {
                // No data
                labels = ['No Hazards Reported'];
                data = [1];
                bg = ['#e5e7eb'];
            }
            
            new Chart(el, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Hazard Count',
                        data,
                        backgroundColor: bg,
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed.x} hazard(s)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            }
                        },
                        y: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Error rendering hazard chart:', e);
            // Show error state
            const ctx = el.getContext('2d');
            if (ctx) {
                ctx.fillStyle = '#9ca3af';
                ctx.font = '14px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('Error loading hazard data', el.width / 2, el.height / 2);
            }
        }
    }

    async renderResourceSharingChart() {
        const el = document.getElementById('resourceSharingChart');
        if (!el || typeof Chart === 'undefined') return;
        try {
            const [borRes, myReqRes] = await Promise.all([
                fetch('config/drrm_reports_api.php?action=borrowed_resources', { credentials: 'same-origin' }).then(r=>r.json()).catch(()=>null),
                fetch('config/drrm_reports_api.php?action=my_requests', { credentials: 'same-origin' }).then(r=>r.json()).catch(()=>null)
            ]);
            const borrowed = (borRes && borRes.success && Array.isArray(borRes.data?.borrowedRequests)) ? borRes.data.borrowedRequests : [];
            const myReq = (myReqRes && myReqRes.success && Array.isArray(myReqRes.data?.myRequests)) ? myReqRes.data.myRequests : [];
            const fmt = (d) => { const dt = new Date(d); return isNaN(dt) ? null : `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}`; };
            const months = (()=>{ const arr=[]; const now=new Date(); for(let i=5;i>=0;i--){ const dt=new Date(now.getFullYear(),now.getMonth()-i,1); arr.push(`${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}`);} return arr; })();
            const seriesA = months.map(m=>borrowed.filter(x=>fmt(x.requestDate)===m).length);
            const seriesB = months.map(m=>myReq.filter(x=>fmt(x.requestDate)===m).length);
            new Chart(el, {
                type: 'line',
                data: { labels: months.map(m=>{ const [y,mo]=m.split('-'); return new Date(Number(y), Number(mo)-1, 1).toLocaleString(undefined,{month:'short'}); }), datasets: [
                    { label: 'Resources Borrowed From Me', data: seriesA, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.2)', tension: 0.2 },
                    { label: 'Resources I Borrowed', data: seriesB, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.2)', tension: 0.2 }
                ]},
                options: { responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: 'Resource Sharing Activity (last 6 months)' } }, scales: { y: { beginAtZero: true, precision: 0 } } }
            });
        } catch (e) {
            // ignore
        }
    }

    async renderResourceSharingChartInto(elementId) {
        const el = document.getElementById(elementId);
        if (!el || typeof Chart === 'undefined') return;
        try {
            const [borRes, myReqRes] = await Promise.all([
                fetch('config/drrm_reports_api.php?action=borrowed_resources', { credentials: 'same-origin' }).then(r=>r.json()).catch(()=>null),
                fetch('config/drrm_reports_api.php?action=my_requests', { credentials: 'same-origin' }).then(r=>r.json()).catch(()=>null)
            ]);
            const borrowed = (borRes && borRes.success && Array.isArray(borRes.data?.borrowedRequests)) ? borRes.data.borrowedRequests : [];
            const myReq = (myReqRes && myReqRes.success && Array.isArray(myReqRes.data?.myRequests)) ? myReqRes.data.myRequests : [];
            const fmt = (d) => { const dt = new Date(d); return isNaN(dt) ? null : `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}`; };
            const months = (()=>{ const arr=[]; const now=new Date(); for(let i=5;i>=0;i--){ const dt=new Date(now.getFullYear(),now.getMonth()-i,1); arr.push(`${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}`);} return arr; })();
            const seriesA = months.map(m=>borrowed.filter(x=>fmt(x.requestDate)===m).length);
            const seriesB = months.map(m=>myReq.filter(x=>fmt(x.requestDate)===m).length);
            new Chart(el, {
                type: 'line',
                data: { labels: months.map(m=>{ const [y,mo]=m.split('-'); return new Date(Number(y), Number(mo)-1, 1).toLocaleString(undefined,{month:'short'}); }), datasets: [
                    { label: 'Resources Borrowed From Me', data: seriesA, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.2)', tension: 0.2 },
                    { label: 'Resources I Borrowed', data: seriesB, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.2)', tension: 0.2 }
                ]},
                options: { responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: 'Resource Sharing Activity (last 6 months)' } }, scales: { y: { beginAtZero: true, precision: 0 } } }
            });
        } catch (e) {
            // ignore
        }
    }

    printReport() {
        const printContent = document.getElementById('reportPreviewContent');
        if (!printContent) return;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>${this.currentReport.reportTitle}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .report-template{max-width:1000px;margin:0 auto;} @media print { .modal-header,.modal-footer,.modal-actions{display:none!important;} }
                    </style>
                </head>
                <body>
                    ${printContent.innerHTML}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }

    exportReport() {
        const format = this.currentReport.exportFormat;
        
        switch (format) {
            case 'pdf':
                this.exportToPDF();
                break;
            case 'excel':
                this.exportToExcel();
                break;
            case 'csv':
                this.exportToCSV();
                break;
            default:
                this.showError('Unsupported export format');
        }
    }

    exportToPDF() {
        // Use html2pdf when available; fallback to print-to-PDF
        if (typeof this.exportCurrentReport === 'function') {
            this.exportCurrentReport();
            return;
        }
        if (typeof window.exportCurrentReport === 'function') {
            window.exportCurrentReport();
            return;
        }
        this.printCurrentReport && this.printCurrentReport();
    }

    exportToExcel() {
        this.showMessage('Excel export functionality would be implemented here for DRRM reports.');
    }

    exportToCSV() {
        if (!this.reportData) {
            this.showError('No data available for CSV export');
            return;
        }

        // Generate CSV based on report type
        let csvContent = '';
        
        switch (this.currentReport.reportType) {
            case 'resource_inventory':
                if (this.reportData.municipalityResources) {
                    csvContent = this.generateResourceInventoryCSV(this.reportData.municipalityResources);
                }
                break;
            case 'request_management':
                if (this.reportData.recentRequests) {
                    csvContent = this.generateRequestManagementCSV(this.reportData.recentRequests);
                }
                break;
            default:
                csvContent = 'Report Type,Generated Date\n' + this.currentReport.reportType + ',' + new Date().toISOString();
        }

        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.currentReport.reportTitle.replace(/\s+/g, '_')}.csv`;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }, 150);
    }

    generateResourceInventoryCSV(data) {
        const headers = ['Municipality', 'Total Resources', 'Total Stock', 'Low Stock Items', 'Well Stocked Items'];
        const csvContent = [
            headers.join(','),
            ...data.map(municipality => 
                headers.map(header => `"${municipality[header.toLowerCase().replace(/\s+/g, '')] || ''}"`).join(',')
            )
        ].join('\n');
        return csvContent;
    }

    generateRequestManagementCSV(data) {
        const headers = ['Request ID', 'From Municipality', 'To Municipality', 'Resource Name', 'Quantity', 'Priority', 'Status', 'Request Date'];
        const csvContent = [
            headers.join(','),
            ...data.map(request => 
                headers.map(header => `"${request[header.toLowerCase().replace(/\s+/g, '')] || ''}"`).join(',')
            )
        ].join('\n');
        return csvContent;
    }


    async generateQuickReport(type) {
        console.log('DRRMReportsManager.generateQuickReport called with type:', type);
        // Show the specific modal for this report type
        this.openSpecificModal(type);
    }

    async generateUnifiedReport() {
        this.showLoading('Generating all-in-one report...');
        try {
            const res = await fetch('config/drrm_reports_api.php?action=unified', { credentials: 'same-origin' });
            let json;
            try {
                json = await res.json();
            } catch (parseErr) {
                const text = await res.text().catch(() => '');
                console.error('Unified report non-JSON response:', text);
                this.showError('Failed to generate unified report: server returned invalid response');
                return;
            }
            if (!json || !json.success) {
                console.error('Unified report error:', json);
                this.showError('Failed to generate unified report' + (json && json.error ? ': ' + json.error : ''));
                return;
            }
            this.currentReport = {
                reportType: 'unified',
                reportTitle: 'All-in-One Municipality Report',
                reportDescription: 'Resources, Borrowed Resources, My Requests, and Hazard entries in one simple report.',
                startDate: this.getDefaultStartDate(),
                endDate: this.getDefaultEndDate(),
                exportFormat: 'pdf'
            };
            this.reportData = json.data;
            this.renderUnifiedReportSimple(json.data);
            this.openReportPreviewModal();
        } catch (e) {
            console.error('Unified report fetch error:', e);
            this.showError('Error generating unified report: ' + e.message);
        }
    }

    renderUnifiedReportSimple(data) {
        const container = document.getElementById('reportPreviewContent');
        if (!container) return;
        const safe = (v) => (v === null || v === undefined ? '' : v);

        const section = (title, tableHtml) => `
            <div class="report-section">
                <h3>${title}</h3>
                ${tableHtml}
            </div>`;

        const table = (headers, rows) => `
            <table class="report-table">
                <thead><tr>${headers.map(h=>`<th>${h}</th>`).join('')}</tr></thead>
                <tbody>${rows.length ? rows.join('') : `<tr><td colspan="${headers.length}">No data</td></tr>`}</tbody>
            </table>`;

        // Resources
        const resHeaders = ['Resource', 'Category', 'Unit', 'Available', 'Status'];
        const resRows = (data.myResources || []).map(r => `
            <tr>
                <td>${safe(r.resourceName)}</td>
                <td>${safe(r.category)}</td>
                <td>${safe(r.unit)}</td>
                <td>${safe(r.availableStock)}</td>
                <td>${safe(r.stockStatus)}</td>
            </tr>`);

        // Borrowed from me
        const borHeaders = ['From Municipality', 'Resource', 'Qty', 'Unit', 'Priority', 'Status', 'Requested', 'Responded'];
        const borRows = (data.borrowedFromMe || []).map(x => `
            <tr>
                <td>${safe(x.fromMunicipality)}</td>
                <td>${safe(x.resourceName)}</td>
                <td>${safe(x.quantity)}</td>
                <td>${safe(x.unit)}</td>
                <td>${safe(x.priority)}</td>
                <td>${safe(x.status)}</td>
                <td>${x.requestDate ? new Date(x.requestDate).toLocaleString() : ''}</td>
                <td>${x.responseDate ? new Date(x.responseDate).toLocaleString() : ''}</td>
            </tr>`);

        // My requests
        const myReqHeaders = ['To Municipality', 'Resource', 'Qty', 'Unit', 'Priority', 'Status', 'Requested', 'Responded'];
        const myReqRows = (data.myRequests || []).map(x => `
            <tr>
                <td>${safe(x.toMunicipality)}</td>
                <td>${safe(x.resourceName)}</td>
                <td>${safe(x.quantity)}</td>
                <td>${safe(x.unit)}</td>
                <td>${safe(x.priority)}</td>
                <td>${safe(x.status)}</td>
                <td>${x.requestDate ? new Date(x.requestDate).toLocaleString() : ''}</td>
                <td>${x.responseDate ? new Date(x.responseDate).toLocaleString() : ''}</td>
            </tr>`);

        // Hazards
        const hazHeaders = ['Type', 'Intensity', 'Location', 'People Affected', 'Date', 'Status'];
        const hazRows = (data.myHazards || []).map(h => `
            <tr>
                <td>${safe(h.hazardType)}</td>
                <td>${safe(h.intensity)}</td>
                <td>${safe(h.location)}</td>
                <td>${safe(h.peopleAffected)}</td>
                <td>${h.hazardDate ? new Date(h.hazardDate).toLocaleDateString() : ''}</td>
                <td>${safe(h.status)}</td>
            </tr>`);

        container.innerHTML = `
            <div class="report-header">
                <h1 class="report-title">${this.currentReport.reportTitle}</h1>
                <div class="report-meta"><strong>Generated:</strong> ${new Date().toLocaleString()}</div>
            </div>
            ${section('Resources', table(resHeaders, resRows))}
            ${section('Resources Borrowed From Us', table(borHeaders, borRows))}
            ${section('My Resource Requests', table(myReqHeaders, myReqRows))}
            ${section('My Hazard Reports', table(hazHeaders, hazRows))}
        `;
    }

    openSpecificModal(type) {
        console.log('openSpecificModal called with type:', type);
        const modalMap = {
            'my_resources': 'myResourcesModal',
            'borrowed_resources': 'borrowedResourcesModal',
            'my_requests': 'myRequestsModal',
            'my_hazards': 'myHazardsModal',
            'my_performance': 'myPerformanceModal',
            'resource_utilization': 'resourceUtilizationModal',
            'emergency_preparedness': 'emergencyPreparednessModal',
            'monthly_summary': 'monthlySummaryModal'
        };

        const modalId = modalMap[type];
        console.log('Modal ID:', modalId);
        
        if (modalId) {
            const modal = document.getElementById(modalId);
            console.log('Modal element:', modal);
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                console.log('Modal opened successfully');
                if (modalId === 'myResourcesModal') {
                    this.loadMyResourcesData();
                }
            } else {
                console.error('Modal element not found:', modalId);
            }
        } else {
            console.error('No modal mapping found for type:', type);
        }
    }

    async loadMyResourcesData() {
        try {
            const res = await fetch('config/get_resource_overview.php');
            const json = await res.json();
            if (!json || !json.success) throw new Error('Failed to load resource overview');

            const data = json.data || {};
            const categories = Array.isArray(data.categories) ? data.categories : [];
            const stats = data.stats || { totalResources: 0, totalStock: 0, lowStockCount: 0, availabilityPercentage: 0 };

            const summaryEl = document.getElementById('myResourcesSummary');
            if (summaryEl) {
                summaryEl.textContent = `Total Resources: ${stats.totalResources} • Total Stock: ${stats.totalStock} • Low Stock Items: ${stats.lowStockCount} • Availability: ${stats.availabilityPercentage}%`;
            }

            const labels = categories.map(c => c.categoryName || 'Unspecified');
            const counts = categories.map(c => c.resourceCount || 0);
            const totals = categories.map(c => c.totalStock || 0);

            const catCtx = document.getElementById('myResourcesCategoryChart');
            if (catCtx && window.Chart) {
                new Chart(catCtx, {
                    type: 'bar',
                    data: { labels, datasets: [{ label: 'Items', data: counts, backgroundColor: '#3b82f6' }] },
                    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
                });
            }

            const availCtx = document.getElementById('myResourcesAvailabilityChart');
            if (availCtx && window.Chart) {
                const available = Math.round((stats.availabilityPercentage || 0) * (stats.totalResources || 0) / 100);
                const low = stats.lowStockCount || 0;
                const unavailable = Math.max(0, (stats.totalResources || 0) - available);
                new Chart(availCtx, {
                    type: 'doughnut',
                    data: { labels: ['Available', 'Low Stock', 'Unavailable'], datasets: [{ data: [available, low, unavailable], backgroundColor: ['#10b981', '#f59e0b', '#ef4444'] }] },
                    options: { responsive: true }
                });
            }

            const topCtx = document.getElementById('myResourcesTopChart');
            if (topCtx && window.Chart) {
                const top = labels.map((label, i) => ({ label, value: totals[i] || 0 })).sort((a, b) => b.value - a.value).slice(0, 8);
                new Chart(topCtx, {
                    type: 'bar',
                    data: { labels: top.map(t => t.label), datasets: [{ label: 'Total Stock', data: top.map(t => t.value), backgroundColor: '#6366f1' }] },
                    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
                });
            }
        } catch (e) {
            console.error('Failed to load My Resources data:', e);
            const summaryEl = document.getElementById('myResourcesSummary');
            if (summaryEl) summaryEl.textContent = 'Failed to load data.';
        }
    }

    async generateSpecificReport(type) {
        // Show loading state
        this.showLoading(`Generating ${type.replace(/_/g, ' ')} report...`);

        try {
            // Set default report configuration for quick generation
            const reportConfig = {
                reportType: type,
                reportTitle: this.getReportTitle(type),
                reportDescription: this.getReportDescription(type),
                startDate: this.getDefaultStartDate(),
                endDate: this.getDefaultEndDate(),
                municipalityScope: 'own',
                includeCharts: true,
                includeDetails: true,
                includeRecommendations: true,
                includeGeographic: false,
                exportFormat: 'pdf'
            };

            // Fetch real data from API
            const response = await fetch(`config/drrm_reports_api.php?action=${type}`);
            const data = await response.json();
            
            if (!data.success) {
                this.showError('Failed to generate report: ' + data.error);
                return;
            }

            this.currentReport = reportConfig;
            this.reportData = data.data;

            // If My Resources report, mirror template.html structure
            if (type === 'my_resources') {
                // Load resources of current municipality to power detailed table and comparison chart
                const resResp = await fetch('config/get_resources_by_municipality.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({})
                });
                let resources = [];
                try {
                    const resJson = await resResp.json();
                    if (resJson && resJson.success && resJson.data && Array.isArray(resJson.data.resources)) {
                        resources = resJson.data.resources;
                    }
                } catch (_) {}
                this.renderMyResourcesTemplate(this.reportData, resources);
            } else if (type === 'my_hazards') {
                this.renderMyHazardsTemplate(this.reportData);
            } else if (type === 'borrowed_resources') {
                this.renderBorrowedResourcesReport();
            } else {
                this.renderReportPreview();
            }

            this.closeAllSpecificModals();
            this.openReportPreviewModal();
        } catch (error) {
            this.showError('Error generating report: ' + error.message);
        }
    }

    getReportHeaderTitle() {
        let source = 'fallback';
        let muni = null;
        try {
            const fromResourcesPage = (window.resourcesPage && window.resourcesPage.currentMunicipality) || null;
            const fromDashboard =
                (window.municipalityDashboard && (window.municipalityDashboard.currentMunicipality || window.municipalityDashboard.currentMunicipalityName)) ||
                null;
            muni = fromResourcesPage || fromDashboard;
            if (muni) {
                source = fromResourcesPage ? 'resourcesPage' : 'municipalityDashboard';
                const title = 'MUNICIPALITY OF ' + String(muni).toUpperCase();

                return title;
            }
        } catch (_) {
            // fall through to default
        }

        const defaultTitle = 'PROVINCIAL DRRM OFFICE (PDRRMO)';

        return defaultTitle;
    }

    renderMyResourcesTemplate(overviewData, resources) {
        const previewContent = document.getElementById('reportPreviewContent');
        if (!previewContent) return;

        const stats = overviewData?.stats || { totalResources: 0, totalStock: 0, lowStockCount: 0, availabilityPercentage: 0 };
        const headerMunicipality = (overviewData && overviewData.municipalityName)
            ? 'MUNICIPALITY OF ' + String(overviewData.municipalityName).toUpperCase()
            : this.getReportHeaderTitle();
        const logoUrl = (overviewData && (overviewData.logoUrl || overviewData.logo_url)) ? String(overviewData.logoUrl || overviewData.logo_url) : '';
        const logoHtml = logoUrl
            ? `<img src="${logoUrl}" alt="Municipality Logo" style="width:80px;height:80px;margin:0 auto 15px;border-radius:50%;object-fit:contain;display:block;" />`
            : `<div class="logo-placeholder" style="width:80px; height:80px; margin:0 auto 15px; background:#ecf0f1; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; color:#7f8c8d;">LOGO</div>`;
        const totalResources = Number(stats.totalResources || (resources ? resources.length : 0) || 0);
        const availableCount = (resources || []).filter(r => Number(r.availableStock || 0) > 0).length;
        const criticalCount = (resources || []).filter(r => Number(r.availableStock || 0) <= 0 || Number(r.availableStock || 0) < Number(r.minimumStock || 0)).length;

        const today = new Date();
        const reportDate = today.toLocaleDateString();
        const period = `${today.toLocaleString('default', { month: 'short' })} ${today.getFullYear()}`;

        // Handle case when there are no resources
        if (totalResources === 0) {
            previewContent.innerHTML = `
                <div class="pdf-page">
                    <div class="document" style="max-width:100%; margin:0 auto; background:#fff; padding:0; box-shadow:none;">
                        <div class="header" style="text-align:center; border-bottom:3px solid #2c3e50; padding-bottom:20px; margin:20px 40px 30px 40px;">
                            ${logoHtml}
                            <h1 style="font-size:24px; color:#2c3e50; margin-bottom:5px;">${headerMunicipality}</h1>
                            <h2 style="font-size:18px; color:#34495e; font-weight:normal; margin-bottom:10px;">Resources Inventory Report</h2>
                            <p style="font-size:14px; margin-top:10px;">Comprehensive Resource Management and Availability Status</p>
                        </div>

                        <div class="report-info" style="display:flex; justify-content:space-between; margin:0 40px 30px 40px; padding:15px; background:#ecf0f1; border-radius:5px;">
                            <div><strong>Report Date:</strong> ${reportDate}</div>
                            <div><strong>Reporting Period:</strong> ${period}</div>
                            <div><strong>Prepared By:</strong> Resource Management Office</div>
                        </div>

                        <div class="section" style="margin:0 40px 30px 40px;">
                            <h2 class="section-title" style="font-size:18px; color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:8px; margin-bottom:15px; font-weight:bold;">Executive Summary</h2>
                            <div style="text-align:center; padding:40px; background:#f8f9fa; border-radius:8px; border:2px dashed #dee2e6;">
                                <h3 style="color:#6c757d; margin-bottom:15px;">No Data Available</h3>
                                <p style="color:#6c757d; font-size:16px; margin-bottom:20px;">Your municipality has not registered any resources in the system.</p>
                                <p style="color:#6c757d; font-size:14px;">To generate a meaningful report, please add resources to your inventory first.</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            return;
        }

        // Build HTML closely following template.html (without graphs)
        previewContent.innerHTML = `
            <div class="pdf-page">
                <div class="document" style="max-width:100%; margin:0 auto; background:#fff; padding:0; box-shadow:none;">
                    <div class="header" style="text-align:center; border-bottom:3px solid #2c3e50; padding-bottom:20px; margin:20px 40px 30px 40px;">
                        ${logoHtml}
                        <h1 style="font-size:24px; color:#2c3e50; margin-bottom:5px;">${headerMunicipality}</h1>
                        <h2 style="font-size:18px; color:#34495e; font-weight:normal; margin-bottom:10px;">Resources Inventory Report</h2>
                        <p style="font-size:14px; margin-top:10px;">Comprehensive Resource Management and Availability Status</p>
                    </div>

                    <div class="report-info" style="display:flex; justify-content:space-between; margin:0 40px 30px 40px; padding:15px; background:#ecf0f1; border-radius:5px;">
                        <div><strong>Report Date:</strong> ${reportDate}</div>
                        <div><strong>Reporting Period:</strong> ${period}</div>
                        <div><strong>Prepared By:</strong> Resource Management Office</div>
                    </div>

                    <div class="section" style="margin:0 40px 30px 40px;">
                        <h2 class="section-title" style="font-size:18px; color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:8px; margin-bottom:15px; font-weight:bold;">Executive Summary</h2>
                        <p style="margin-bottom:20px;">This report provides a comprehensive overview of the municipality's resource inventory, including equipment, supplies, vehicles, and facilities. The data reflects current stock levels, availability status, and resource allocation.</p>
                        <div class="summary-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:15px; margin-bottom:20px;">
                            <div class="summary-card" style="border:1px solid #ddd; padding:15px; border-radius:5px; text-align:center;">
                                <h3 style="font-size:14px; color:#7f8c8d; margin-bottom:10px;">Total Resources</h3>
                                <div class="value" style="font-size:28px; font-weight:bold; color:#2c3e50;">${totalResources}</div>
                            </div>
                            <div class="summary-card" style="border:1px solid #ddd; padding:15px; border-radius:5px; text-align:center;">
                                <h3 style="font-size:14px; color:#7f8c8d; margin-bottom:10px;">Available</h3>
                                <div class="value" style="font-size:28px; font-weight:bold; color:#27ae60;">${availableCount}</div>
                            </div>
                            <div class="summary-card" style="border:1px solid #ddd; padding:15px; border-radius:5px; text-align:center;">
                                <h3 style="font-size:14px; color:#7f8c8d; margin-bottom:10px;">Critical Stock</h3>
                                <div class="value" style="font-size:28px; font-weight:bold; color:#e74c3c;">${criticalCount}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pdf-page">
                <div class="document" style="max-width:100%; margin:0 auto; background:#fff; padding:0; box-shadow:none;">
                    <div class="section" style="margin:0 40px 30px 40px;">
                        <h2 class="section-title" style="font-size:18px; color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:8px; margin-bottom:15px; font-weight:bold;">Resources Inventory</h2>
                        <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:12px;">
                            <thead style="background:#34495e; color:#fff;">
                                <tr>
                                    <th style="padding:12px 8px; text-align:left; font-weight:bold;">Resource Name</th>
                                    <th style="padding:12px 8px; text-align:left; font-weight:bold;">Category</th>
                                    <th style="padding:12px 8px; text-align:left; font-weight:bold;">Unit</th>
                                    <th style="padding:12px 8px; text-align:left; font-weight:bold;">Current Stock</th>
                                    <th style="padding:12px 8px; text-align:left; font-weight:bold;">Minimum Required</th>
                                    <th style="padding:12px 8px; text-align:left; font-weight:bold;">Storage Location</th>
                                    <th style="padding:12px 8px; text-align:left; font-weight:bold;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(resources || []).map(r => {
                                    const a = Number(r.availableStock || 0);
                                    const m = Number(r.minimumStock || 0);
                                    const status = a <= 0 ? { cls: 'status-unavailable', label: 'Unavailable' } : (a < m ? { cls: 'status-low', label: 'Low Stock' } : { cls: 'status-available', label: 'Available' });
                                    return `<tr>
                                        <td style="padding:10px 8px; border-bottom:1px solid #ddd;">${r.resourceName || r.name || ''}</td>
                                        <td style="padding:10px 8px; border-bottom:1px solid #ddd;">${r.category || ''}</td>
                                        <td style="padding:10px 8px; border-bottom:1px solid #ddd;">${r.unit || ''}</td>
                                        <td style="padding:10px 8px; border-bottom:1px solid #ddd;">${a}</td>
                                        <td style="padding:10px 8px; border-bottom:1px solid #ddd;">${m}</td>
                                        <td style="padding:10px 8px; border-bottom:1px solid #ddd;">${r.storageLocation || ''}</td>
                                        <td style="padding:10px 8px; border-bottom:1px solid #ddd;"><span class="status-badge ${status.cls}" style="display:inline-block; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold;">${status.label}</span></td>
                                    </tr>`;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="pdf-page">
                <div class="document" style="max-width:100%; margin:0 auto; background:#fff; padding:0; box-shadow:none;">
                    <div class="notes" style="background:#fffbea; border-left:4px solid #f39c12; padding:15px; margin:20px 40px 0 40px;">
                        <h4 style="color:#f39c12; margin-bottom:8px;">Notes and Recommendations:</h4>
                        <p style="font-size:12px; line-height:1.5;">1) Review items marked Low Stock and schedule restocking. 2) Verify storage locations for accuracy. 3) Consider setting alerts based on minimum thresholds.</p>
                    </div>


                    <div class="footer" style="margin:0 40px 40px 40px; padding-top:20px; border-top:2px solid #ecf0f1; font-size:12px; color:#7f8c8d;">
                        <p><strong>Document Reference:</strong> MUN-RES-${today.getFullYear()}-${today.getMonth()+1}</p>
                        <p><strong>Classification:</strong> For Official Use Only</p>
                        <p><strong>Contact:</strong> resource.management@municipality.gov.ph | Tel: (02) 1234-5678</p>
                        <p style="margin-top:10px; text-align:center;">This is a computer-generated report. For inquiries, please contact the Resource Management Office.</p>
                    </div>
                </div>
            </div>
        `;

        // Charts intentionally omitted to keep export compact
        const container = document.getElementById('resourceCategorySections');
        if (container) {
            container.innerHTML = '';
        }
    }

    renderBorrowedResourcesReport() {
        const previewContent = document.getElementById('reportPreviewContent');
        if (!previewContent) return;

        const data = this.reportData || {};
        const borrowedRequests = Array.isArray(data.borrowedRequests) ? data.borrowedRequests : [];
        const stats = data.statistics || {};
        const headerMunicipality = (data && data.municipalityName)
            ? 'MUNICIPALITY OF ' + String(data.municipalityName).toUpperCase()
            : this.getReportHeaderTitle();
        const logoUrl = (data && (data.logoUrl || data.logo_url)) ? String(data.logoUrl || data.logo_url) : '';
        const logoHtml = logoUrl
            ? `<img src="${logoUrl}" alt="Municipality Logo" style="width:80px;height:80px;margin:0 auto 15px;border-radius:50%;object-fit:contain;display:block;" />`
            : `<div class="logo-placeholder" style="width:80px; height:80px; margin:0 auto 15px; background:#ecf0f1; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; color:#7f8c8d;">LOGO</div>`;

        const today = new Date();
        const reportDate = today.toLocaleDateString();
        const period = `${today.toLocaleString('default', { month: 'short' })} ${today.getFullYear()}`;

        if (borrowedRequests.length === 0) {
            previewContent.innerHTML = `
                <div class="pdf-page">
                    <div class="document" style="max-width:100%; margin:0 auto; background:#fff; padding:0; box-shadow:none;">
                        <div class="header" style="text-align:center; border-bottom:3px solid #2c3e50; padding-bottom:20px; margin:20px 40px 30px 40px;">
                            ${logoHtml}
                            <h1 style="font-size:24px; color:#2c3e50; margin-bottom:5px;">${headerMunicipality}</h1>
                            <h2 style="font-size:18px; color:#34495e; font-weight:normal; margin-bottom:10px;">Resources Borrowed From Us Report</h2>
                            <p style="font-size:14px; margin-top:10px;">Summary of resource requests received from other municipalities</p>
                        </div>
                        <div class="report-info" style="display:flex; justify-content:space-between; margin:0 40px 30px 40px; padding:15px; background:#ecf0f1; border-radius:5px;">
                            <div><strong>Report Date:</strong> ${reportDate}</div>
                            <div><strong>Reporting Period:</strong> ${period}</div>
                            <div><strong>Prepared By:</strong> DRRM Office</div>
                        </div>
                        <div class="section" style="margin:0 40px 30px 40px;">
                            <h2 class="section-title" style="font-size:18px; color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:8px; margin-bottom:15px; font-weight:bold;">Executive Summary</h2>
                            <div style="text-align:center; padding:40px; background:#f8f9fa; border-radius:8px; border:2px dashed #dee2e6;">
                                <h3 style="color:#6c757d; margin-bottom:15px;">No Data Available</h3>
                                <p style="color:#6c757d; font-size:16px; margin-bottom:20px;">No resources have been borrowed from your municipality.</p>
                            </div>
                        </div>
                    </div>
                </div>`;
            return;
        }

        const totalBorrowed = Number(stats.totalBorrowed || borrowedRequests.length || 0);
        const pendingBorrowed = Number(stats.pendingBorrowed || 0);
        const approvedBorrowed = Number(stats.approvedBorrowed || 0);
        const fulfilledBorrowed = Number(stats.fulfilledBorrowed || 0);
        const avgResponseTime = Math.round(Number(stats.avgResponseTime || 0));

        const headers = ['Request ID', 'From Municipality', 'Resource', 'Quantity', 'Unit', 'Priority', 'Status', 'Request Date', 'Response Date'];
        const headerRow = headers.map(h => `<th style="padding:10px 8px; text-align:left; font-weight:bold; background:#34495e; color:#fff;">${h}</th>`).join('');
        const rows = borrowedRequests.slice(0, 15).map(r => `
            <tr>
                <td style="padding:10px 8px; border-bottom:1px solid #ddd; font-size:11px;">${r.requestID || 'N/A'}</td>
                <td style="padding:10px 8px; border-bottom:1px solid #ddd; font-size:11px;">${r.fromMunicipality || 'N/A'}</td>
                <td style="padding:10px 8px; border-bottom:1px solid #ddd; font-size:11px;">${r.resourceName || 'N/A'}</td>
                <td style="padding:10px 8px; border-bottom:1px solid #ddd; font-size:11px;">${r.quantity || 0}</td>
                <td style="padding:10px 8px; border-bottom:1px solid #ddd; font-size:11px;">${r.unit || 'N/A'}</td>
                <td style="padding:10px 8px; border-bottom:1px solid #ddd; font-size:11px;">${r.priority || 'N/A'}</td>
                <td style="padding:10px 8px; border-bottom:1px solid #ddd; font-size:11px;">${r.status || 'N/A'}</td>
                <td style="padding:10px 8px; border-bottom:1px solid #ddd; font-size:11px;">${r.requestDate ? new Date(r.requestDate).toLocaleDateString() : 'N/A'}</td>
                <td style="padding:10px 8px; border-bottom:1px solid #ddd; font-size:11px;">${r.responseDate ? new Date(r.responseDate).toLocaleDateString() : 'N/A'}</td>
            </tr>`).join('');

        previewContent.innerHTML = `
            <div class="pdf-page">
                <div class="document" style="max-width:100%; margin:0 auto; background:#fff; padding:0; box-shadow:none;">
                    <div class="header" style="text-align:center; border-bottom:3px solid #2c3e50; padding-bottom:20px; margin:20px 40px 30px 40px;">
                        ${logoHtml}
                        <h1 style="font-size:24px; color:#2c3e50; margin-bottom:5px;">${headerMunicipality}</h1>
                        <h2 style="font-size:18px; color:#34495e; font-weight:normal; margin-bottom:10px;">Resources Borrowed From Us Report</h2>
                        <p style="font-size:14px; margin-top:10px;">Summary of resource requests received from other municipalities</p>
                    </div>
                    <div class="report-info" style="display:flex; justify-content:space-between; margin:0 40px 30px 40px; padding:15px; background:#ecf0f1; border-radius:5px; font-size:12px;">
                        <div><strong>Report Date:</strong> ${reportDate}</div>
                        <div><strong>Reporting Period:</strong> ${period}</div>
                        <div><strong>Prepared By:</strong> DRRM Office</div>
                    </div>
                    <div class="section" style="margin:0 40px 30px 40px;">
                        <h2 class="section-title" style="font-size:18px; color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:8px; margin-bottom:15px; font-weight:bold;">Executive Summary</h2>
                        <div class="summary-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom:20px;">
                            <div class="summary-card" style="border:1px solid #ddd; padding:15px; border-radius:5px; text-align:center;">
                                <h3 style="font-size:13px; color:#7f8c8d; margin-bottom:10px;">Total Requests</h3>
                                <div class="value" style="font-size:24px; font-weight:bold; color:#2c3e50;">${totalBorrowed}</div>
                            </div>
                            <div class="summary-card" style="border:1px solid #ddd; padding:15px; border-radius:5px; text-align:center;">
                                <h3 style="font-size:13px; color:#7f8c8d; margin-bottom:10px;">Pending</h3>
                                <div class="value" style="font-size:24px; font-weight:bold; color:#f59e0b;">${pendingBorrowed}</div>
                            </div>
                            <div class="summary-card" style="border:1px solid #ddd; padding:15px; border-radius:5px; text-align:center;">
                                <h3 style="font-size:13px; color:#7f8c8d; margin-bottom:10px;">Approved</h3>
                                <div class="value" style="font-size:24px; font-weight:bold; color:#3b82f6;">${approvedBorrowed}</div>
                            </div>
                            <div class="summary-card" style="border:1px solid #ddd; padding:15px; border-radius:5px; text-align:center;">
                                <h3 style="font-size:13px; color:#7f8c8d; margin-bottom:10px;">Fulfilled</h3>
                                <div class="value" style="font-size:24px; font-weight:bold; color:#22c55e;">${fulfilledBorrowed}</div>
                            </div>
                        </div>
                        ${avgResponseTime > 0 ? `<p style="font-size:13px; color:#7f8c8d; margin-bottom:15px;"><strong>Average Response Time:</strong> ${avgResponseTime} hours</p>` : ''}
                    </div>
                    <div class="section" style="margin:0 40px 30px 40px;">
                        <h2 class="section-title" style="font-size:18px; color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:8px; margin-bottom:15px; font-weight:bold;">Status Distribution</h2>
                        <div class="chart-container-print" style="width:100%; max-width:100%; height:280px; margin:20px 0; position:relative; page-break-inside:avoid;">
                            <canvas id="borrowedResourcesStatusChart" style="max-width:100% !important; max-height:100% !important; width:100% !important; height:auto !important;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pdf-page">
                <div class="document" style="max-width:100%; margin:0 auto; background:#fff; padding:0; box-shadow:none;">
                    <div class="section" style="margin:0 40px 30px 40px;">
                        <h2 class="section-title" style="font-size:18px; color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:8px; margin-bottom:15px; font-weight:bold;">Resource Requests Received</h2>
                        <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:11px;">
                            <thead>
                                <tr>${headerRow}</tr>
                            </thead>
                            <tbody>
                                ${rows}
                            </tbody>
                        </table>
                        ${borrowedRequests.length > 15 ? `<p style="font-size:11px; color:#7f8c8d;"><em>Showing first 15 requests. Total: ${borrowedRequests.length}</em></p>` : ''}
                    </div>
                    <div class="footer" style="margin:0 40px 40px 40px; padding-top:20px; border-top:2px solid #ecf0f1; font-size:11px; color:#7f8c8d;">
                        <p><strong>Document Reference:</strong> MUN-BOR-${today.getFullYear()}-${today.getMonth()+1}</p>
                        <p><strong>Classification:</strong> For Official Use Only</p>
                        <p style="margin-top:10px; text-align:center;">This is a computer-generated report. For inquiries, please contact the DRRM Office.</p>
                    </div>
                </div>
            </div>
        `;

        // Render the chart after a short delay to ensure DOM is ready
        setTimeout(() => {
            this.renderBorrowedResourcesStatusChart(borrowedRequests);
        }, 100);
    }

    renderBorrowedResourcesStatusChart(requests) {
        const el = document.getElementById('borrowedResourcesStatusChart');
        if (!el || typeof Chart === 'undefined') return;
        
        try {
            // Count by status
            const counts = { 
                pending: 0, 
                approved: 0, 
                fulfilled: 0, 
                rejected: 0,
                'return pending': 0,
                returned: 0
            };
            requests.forEach(r => { 
                const s = String(r.status || '').toLowerCase(); 
                if (counts.hasOwnProperty(s)) counts[s]++;
            });
            
            // Filter out zero values for cleaner chart
            const labels = [];
            const data = [];
            const bg = [];
            
            if (counts.pending > 0) {
                labels.push('Pending');
                data.push(counts.pending);
                bg.push('#f59e0b');
            }
            if (counts.approved > 0) {
                labels.push('Approved');
                data.push(counts.approved);
                bg.push('#3b82f6');
            }
            if (counts.fulfilled > 0) {
                labels.push('Fulfilled');
                data.push(counts.fulfilled);
                bg.push('#22c55e');
            }
            if (counts['return pending'] > 0) {
                labels.push('Return Pending');
                data.push(counts['return pending']);
                bg.push('#06b6d4');
            }
            if (counts.returned > 0) {
                labels.push('Returned');
                data.push(counts.returned);
                bg.push('#10b981');
            }
            if (counts.rejected > 0) {
                labels.push('Rejected');
                data.push(counts.rejected);
                bg.push('#ef4444');
            }
            
            // If no data, show empty state
            if (labels.length === 0) {
                labels.push('No Data');
                data.push(1);
                bg.push('#e5e7eb');
            }

            const fontFamily = "'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif";
            
            new Chart(el, {
                type: 'doughnut',
                data: { 
                    labels, 
                    datasets: [{ 
                        label: 'Resources Borrowed From Us', 
                        data, 
                        backgroundColor: bg, 
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }] 
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    layout: {
                        padding: {
                            top: 10,
                            bottom: 10,
                            left: 10,
                            right: 10
                        }
                    },
                    plugins: { 
                        legend: { 
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 8,
                                font: {
                                    size: 11,
                                    family: fontFamily
                                },
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            },
                            titleFont: {
                                family: fontFamily,
                                size: 12
                            },
                            bodyFont: {
                                family: fontFamily,
                                size: 11
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Error rendering borrowed resources status chart:', e);
        }
    }

    renderMyHazardsTemplate(hazardData) {
        const previewContent = document.getElementById('reportPreviewContent');
        if (!previewContent) return;

        const stats = hazardData?.statistics || { totalHazards: 0, activeHazards: 0, highRisk: 0, mediumRisk: 0, lowRisk: 0, totalPeopleAffected: 0 };
        const hazards = Array.isArray(hazardData?.myHazards) ? hazardData.myHazards : [];
        const headerMunicipality = (hazardData && hazardData.municipalityName)
            ? 'MUNICIPALITY OF ' + String(hazardData.municipalityName).toUpperCase()
            : this.getReportHeaderTitle();
        const logoUrl = (hazardData && (hazardData.logoUrl || hazardData.logo_url)) ? String(hazardData.logoUrl || hazardData.logo_url) : '';
        const logoHtml = logoUrl
            ? `<img src="${logoUrl}" alt="Municipality Logo" style="width:80px;height:80px;margin:0 auto 15px;border-radius:50%;object-fit:contain;display:block;" />`
            : `<div class="logo-placeholder" style="width:80px; height:80px; margin:0 auto 15px; background:#ecf0f1; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; color:#7f8c8d;">LOGO</div>`;

        const today = new Date();
        const reportDate = today.toLocaleDateString();
        const period = `${today.toLocaleString('default', { month: 'short' })} ${today.getFullYear()}`;

        if (hazards.length === 0) {
            previewContent.innerHTML = `
                <div class="pdf-page">
                    <div class="document" style="max-width:100%; margin:0 auto; background:#fff; padding:0; box-shadow:none;">
                        <div class="header" style="text-align:center; border-bottom:3px solid #2c3e50; padding-bottom:20px; margin:20px 40px 30px 40px;">
                            ${logoHtml}
                            <h1 style="font-size:24px; color:#2c3e50; margin-bottom:5px;">${headerMunicipality}</h1>
                            <h2 style="font-size:18px; color:#34495e; font-weight:normal; margin-bottom:10px;">Hazard Incidents Report</h2>
                            <p style="font-size:14px; margin-top:10px;">Summary of reported hazard events and affected population</p>
                        </div>
                        <div class="report-info" style="display:flex; justify-content:space-between; margin:0 40px 30px 40px; padding:15px; background:#ecf0f1; border-radius:5px;">
                            <div><strong>Report Date:</strong> ${reportDate}</div>
                            <div><strong>Reporting Period:</strong> ${period}</div>
                            <div><strong>Prepared By:</strong> DRRM Office</div>
                        </div>
                        <div class="section" style="margin:0 40px 30px 40px;">
                            <h2 class="section-title" style="font-size:18px; color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:8px; margin-bottom:15px; font-weight:bold;">Executive Summary</h2>
                            <div style="text-align:center; padding:40px; background:#f8f9fa; border-radius:8px; border:2px dashed #dee2e6;">
                                <h3 style="color:#6c757d; margin-bottom:15px;">No Data Available</h3>
                                <p style="color:#6c757d; font-size:16px; margin-bottom:20px;">No hazard incidents have been reported for your municipality.</p>
                            </div>
                        </div>
                    </div>
                </div>`;
            return;
        }

        const totalHazards = Number(stats.totalHazards || hazards.length || 0);
        const active = Number(stats.activeHazards || 0);
        const totalAffected = Number(stats.totalPeopleAffected || 0);

        const headers = ['Hazard Type', 'Intensity', 'Location', 'People Affected', 'Reported Date', 'Status'];
        const headerRow = headers.map(h => `<th>${h}</th>`).join('');
        const rows = hazards.slice(0, 12).map(h => `
            <tr>
                <td>${h.hazardType || 'N/A'}</td>
                <td>${h.intensity || 'N/A'}</td>
                <td>${h.location || 'N/A'}</td>
                <td>${Number(h.affectedPopulation || 0)}</td>
                <td>${h.reportedAt ? new Date(h.reportedAt).toLocaleDateString() : 'N/A'}</td>
                <td>${h.status || 'N/A'}</td>
            </tr>`).join('');

        previewContent.innerHTML = `
            <div class="pdf-page">
                <div class="document" style="max-width:100%; margin:0 auto; background:#fff; padding:0; box-shadow:none;">
                    <div class="header" style="text-align:center; border-bottom:3px solid #2c3e50; padding-bottom:20px; margin:20px 40px 30px 40px;">
                        ${logoHtml}
                        <h1 style="font-size:24px; color:#2c3e50; margin-bottom:5px;">${headerMunicipality}</h1>
                        <h2 style="font-size:18px; color:#34495e; font-weight:normal; margin-bottom:10px;">Hazard Incidents Report</h2>
                        <p style="font-size:14px; margin-top:10px;">Summary of reported hazard events and affected population</p>
                    </div>
                    <div class="report-info" style="display:flex; justify-content:space-between; margin:0 40px 30px 40px; padding:15px; background:#ecf0f1; border-radius:5px;">
                        <div><strong>Report Date:</strong> ${reportDate}</div>
                        <div><strong>Reporting Period:</strong> ${period}</div>
                        <div><strong>Prepared By:</strong> DRRM Office</div>
                    </div>
                    <div class="section" style="margin:0 40px 30px 40px;">
                        <h2 class="section-title" style="font-size:18px; color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:8px; margin-bottom:15px; font-weight:bold;">Executive Summary</h2>
                        <div class="summary-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:15px; margin-bottom:20px;">
                            <div class="summary-card" style="border:1px solid #ddd; padding:15px; border-radius:5px; text-align:center;">
                                <h3 style="font-size:14px; color:#7f8c8d; margin-bottom:10px;">Total Hazards</h3>
                                <div class="value" style="font-size:28px; font-weight:bold; color:#2c3e50;">${totalHazards}</div>
                            </div>
                            <div class="summary-card" style="border:1px solid #ddd; padding:15px; border-radius:5px; text-align:center;">
                                <h3 style="font-size:14px; color:#7f8c8d; margin-bottom:10px;">Active</h3>
                                <div class="value" style="font-size:28px; font-weight:bold; color:#e67e22;">${active}</div>
                            </div>
                            <div class="summary-card" style="border:1px solid #ddd; padding:15px; border-radius:5px; text-align:center;">
                                <h3 style="font-size:14px; color:#7f8c8d; margin-bottom:10px;">People Affected</h3>
                                <div class="value" style="font-size:28px; font-weight:bold; color:#e74c3c;">${totalAffected}</div>
                            </div>
                        </div>
                    </div>
                    <div class="section" style="margin:0 40px 30px 40px;">
                        <h2 class="section-title" style="font-size:18px; color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:8px; margin-bottom:15px; font-weight:bold;">Recent Hazard Incidents</h2>
                        <table class="report-table" style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr>${headerRow}</tr>
                            </thead>
                            <tbody>
                                ${rows}
                            </tbody>
                        </table>
                        ${hazards.length > 12 ? `<p><em>Showing first 12 hazards. Total: ${hazards.length}</em></p>` : ''}
                    </div>
                </div>
            </div>`;
    }

    printCurrentReport() {
        const container = document.getElementById('reportPreviewContent');
        if (!container) return;
        // Wait for charts to render before printing
        this.waitForChartsRendered(450).then(() => {
            const clone = this.getExportClone(container);
            const win = window.open('', '_blank');
            if (!win) return;
            const html = clone.innerHTML;
            const docHtml = `<!DOCTYPE html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><title>Report</title>
                <link rel=\"stylesheet\" href=\"assets/css/pages/reports.css\">\n
                <style>
                    body{padding:16px;background:#fff;color:#111827;} 
                    .report-template{max-width:1000px;margin:0 auto;} 
                    @media print { 
                        .modal-header,.modal-footer,.modal-actions{display:none!important;}
                        .chart-container-print, .chart-container {
                            width: 100% !important;
                            max-width: 100% !important;
                            height: auto !important;
                            max-height: 300px !important;
                            page-break-inside: avoid !important;
                            break-inside: avoid !important;
                        }
                        canvas {
                            max-width: 100% !important;
                            max-height: 280px !important;
                            width: 100% !important;
                            height: auto !important;
                            page-break-inside: avoid !important;
                        }
                    }
                </style>
            </head><body>${html}
            <script>(function(){setTimeout(function(){window.print();}, 350);})();<\\/script>
            </body></html>`;
            win.document.open();
            win.document.write(docHtml);
            win.document.close();
        });
    }

    async exportCurrentReport() {
        const container = document.getElementById('reportPreviewContent');
        if (!container) return;
        await this.waitForChartsRendered(600);
        await this.waitForImagesLoaded(container);
        if (document.fonts && document.fonts.ready) {
            try { await document.fonts.ready; } catch (_) {}
        }
        
        // Ensure chart containers have proper sizing before export
        const chartContainers = container.querySelectorAll('.chart-container-print, .chart-container');
        chartContainers.forEach(container => {
            container.style.width = '100%';
            container.style.maxWidth = '100%';
            container.style.height = '280px';
            container.style.maxHeight = '280px';
        });

        // 1) Try exporting directly from the live container (best for layout)
        const originalStyle = {
            maxHeight: container.style.maxHeight,
            overflow: container.style.overflow,
            height: container.style.height
        };
        container.style.maxHeight = 'none';
        container.style.overflow = 'visible';
        container.style.height = 'auto';

        // Generate appropriate filename based on report type
        const reportTitle = this.currentReport ? this.currentReport.reportTitle.replace(/\s+/g, '_') : 'Report';
        const filename = `${reportTitle}_${new Date().toISOString().slice(0,10)}.pdf`;
        
        const opt = {
            margin: 0.5,
            filename: filename,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' },
            pagebreak: { mode: ['css', 'legacy'], before: '.page-break' }
        };

        const runLive = async () => {
            const pdfBlob = await html2pdf().from(container).set(opt).output('blob');
            const pdfUrl = URL.createObjectURL(pdfBlob);
            const downloadLink = document.createElement('a');
            downloadLink.href = pdfUrl;
            downloadLink.download = filename;
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            setTimeout(function () {
                document.body.removeChild(downloadLink);
                URL.revokeObjectURL(pdfUrl);
            }, 150);
        };
        const restoreLiveStyles = () => {
            container.style.maxHeight = originalStyle.maxHeight;
            container.style.overflow = originalStyle.overflow;
            container.style.height = originalStyle.height;
        };

        try {
            await runLive();
            restoreLiveStyles();
            return;
        } catch (_) {
            // fall through to wrapper fallback
            restoreLiveStyles();
        }

        // 2) Fallback: render from offscreen wrapper (stable but may differ in layout)
        const exportNode = this.getExportClone(container);
        const wrapper = document.createElement('div');
        wrapper.style.position = 'absolute';
        wrapper.style.left = '-99999px';
        wrapper.style.top = '0';
        wrapper.style.width = '720px';
        wrapper.style.padding = '0';
        wrapper.style.margin = '0';
        wrapper.style.background = '#ffffff';
        wrapper.style.color = '#111827';
        wrapper.appendChild(exportNode);
        document.body.appendChild(wrapper);
        await this.waitForImagesLoaded(wrapper);
        try {
            const pdfBlob = await html2pdf().from(wrapper).set(opt).output('blob');
            const pdfUrl = URL.createObjectURL(pdfBlob);
            const downloadLink = document.createElement('a');
            downloadLink.href = pdfUrl;
            downloadLink.download = filename;
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            setTimeout(function () {
                document.body.removeChild(downloadLink);
                URL.revokeObjectURL(pdfUrl);
            }, 150);
        } finally {
            try { document.body.removeChild(wrapper); } catch (_) {}
        }
    }

    getReportTitle(type) {
        const titles = {
            'my_resources': 'My Resources Report',
            'borrowed_resources': 'Resources Borrowed From Us Report',
            'my_requests': 'My Resource Requests Report',
            'my_hazards': 'My Hazard Reports',
            'my_performance': 'My Performance Report',
            'resource_utilization': 'Resource Utilization Report',
            'emergency_preparedness': 'Emergency Preparedness Report',
            'monthly_summary': 'Monthly Summary Report'
        };
        return titles[type] || 'Municipality Report';
    }

    getReportDescription(type) {
        const descriptions = {
            'my_resources': 'Complete inventory of your municipality\'s resources, stock levels, and availability status.',
            'borrowed_resources': 'Detailed report of who borrowed what from your municipality, when, and current status.',
            'my_requests': 'Analysis of requests your municipality made to others, status, and response times.',
            'my_hazards': 'Hazard incidents in your municipality, risk levels, and response actions taken.',
            'my_performance': 'Your municipality\'s DRRM performance metrics and response efficiency.',
            'resource_utilization': 'How your resources are being used, borrowed, and returned by other municipalities.',
            'emergency_preparedness': 'Your municipality\'s emergency response readiness and capacity assessment.',
            'monthly_summary': 'Complete monthly overview of your municipality\'s DRRM activities and performance.'
        };
        return descriptions[type] || 'Municipality-specific DRRM analysis and reporting.';
    }

    getDefaultStartDate() {
        const lastMonth = new Date();
        lastMonth.setMonth(lastMonth.getMonth() - 1);
        return lastMonth.toISOString().split('T')[0];
    }

    getDefaultEndDate() {
        return new Date().toISOString().split('T')[0];
    }

    useTemplate(templateType) {
        const templates = {
            'emergency_response': {
                reportType: 'emergency_response',
                title: 'Emergency Response Report',
                description: 'Analysis of emergency response times, resource mobilization, and incident management effectiveness.',
                includeCharts: true,
                includeDetails: true,
                includeRecommendations: true,
                includeGeographic: false
            },
            'resource_sharing': {
                reportType: 'resource_sharing',
                title: 'Resource Sharing Analysis',
                description: 'Analysis of inter-municipality resource sharing patterns and collaboration effectiveness.',
                includeCharts: true,
                includeDetails: true,
                includeRecommendations: true,
                includeGeographic: false
            },
            'capacity_assessment': {
                reportType: 'capacity_assessment',
                title: 'DRRM Capacity Assessment',
                description: 'Comprehensive evaluation of municipal DRRM capacity and resource adequacy.',
                includeCharts: true,
                includeDetails: true,
                includeRecommendations: true,
                includeGeographic: true
            },
            'compliance_monitoring': {
                reportType: 'compliance_monitoring',
                title: 'Compliance Monitoring Report',
                description: 'Monitoring of DRRM standards compliance, reporting requirements, and regulatory adherence.',
                includeCharts: false,
                includeDetails: true,
                includeRecommendations: true,
                includeGeographic: false
            }
        };

        const template = templates[templateType];
        if (!template) return;

        // Fill form with template data
        const reportTypeSelect = document.getElementById('reportType');
        const titleInput = document.getElementById('reportTitle');
        const descriptionInput = document.getElementById('reportDescription');
        const chartsCheckbox = document.getElementById('includeCharts');
        const detailsCheckbox = document.getElementById('includeDetails');
        const recommendationsCheckbox = document.getElementById('includeRecommendations');
        const geographicCheckbox = document.getElementById('includeGeographic');

        if (reportTypeSelect) reportTypeSelect.value = template.reportType;
        if (titleInput) titleInput.value = template.title;
        if (descriptionInput) descriptionInput.value = template.description;
        if (chartsCheckbox) chartsCheckbox.checked = template.includeCharts;
        if (detailsCheckbox) detailsCheckbox.checked = template.includeDetails;
        if (recommendationsCheckbox) recommendationsCheckbox.checked = template.includeRecommendations;
        if (geographicCheckbox) geographicCheckbox.checked = template.includeGeographic;

        this.openReportModal();
    }

    viewReport(reportId) {
        this.showMessage(`Viewing report ${reportId}. In a real implementation, this would load the report from the server.`);
    }

    downloadReport(reportId) {
        this.showMessage(`Downloading report ${reportId}. In a real implementation, this would download the report file.`);
    }

    viewAllReports() {
        this.showMessage('Viewing all reports. In a real implementation, this would navigate to a reports archive page.');
    }

    showLoading(message) {
        console.log('Loading:', message);
    }

    showError(message) {
        alert('Error: ' + message);
    }

    showMessage(message) {
        alert(message);
    }

    waitForChartsRendered(delayMs) {
        return new Promise(resolve => setTimeout(resolve, delayMs || 250));
    }

    waitForImagesLoaded(root) {
        const images = Array.from(root.querySelectorAll('img'));
        if (images.length === 0) return Promise.resolve();
        return Promise.all(images.map(img => {
            if (img.complete && img.naturalWidth > 0) return Promise.resolve();
            return new Promise(res => {
                img.onload = () => res();
                img.onerror = () => res();
            });
        }));
    }

    getExportClone(container) {
        const clone = container.cloneNode(true);
        this.replaceCanvasesWithImagesFromSource(container, clone);
        // Remove any scrolling/max-height constraints inside the clone
        const adjust = (el) => { if (!el) return; el.style.maxHeight = 'none'; el.style.overflow = 'visible'; el.style.height = 'auto'; el.style.boxShadow = 'none'; el.style.background = '#ffffff'; };
        adjust(clone);
        const candidates = clone.querySelectorAll('.report-preview, .report-template');
        candidates.forEach(adjust);
        return clone;
    }

    // Copy canvas renderings from the live DOM (source) into the clone as images
    replaceCanvasesWithImagesFromSource(sourceRoot, targetRoot) {
        const sourceCanvases = Array.from(sourceRoot.querySelectorAll('canvas'));
        const targetCanvases = Array.from(targetRoot.querySelectorAll('canvas'));
        const count = Math.min(sourceCanvases.length, targetCanvases.length);
        for (let i = 0; i < count; i++) {
            const src = sourceCanvases[i];
            const tgt = targetCanvases[i];
            try {
                const dataUrl = src.toDataURL('image/png');
                const img = document.createElement('img');
                img.src = dataUrl;
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
                tgt.replaceWith(img);
            } catch (_) {
                // If toDataURL fails, leave canvas as-is
            }
        }
    }
}

// Global functions for HTML onclick handlers
function openReportModal() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.openReportModal();
    }
}

function closeReportModal() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.closeReportModal();
    }
}

function closeReportPreviewModal() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.closeReportPreviewModal();
    }
}

function generateReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.generateReport();
    }
}

// removed testClick notice

function generateQuickReport(type) {
    console.log('generateQuickReport called with type:', type);
    if (window.drrmReportsManager) {
        window.drrmReportsManager.generateQuickReport(type);
    } else {
        console.error('drrmReportsManager not found');
        alert('Reports manager not initialized. Please refresh the page.');
    }
}

function useTemplate(templateType) {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.useTemplate(templateType);
    }
}

function printReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.printReport();
    }
}

function exportReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.exportReport();
    }
}

function generateUnifiedReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.generateUnifiedReport();
    }
}

function viewAllReports() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.viewAllReports();
    }
}

// Specific modal functions
function closeMyResourcesModal() {
    const modal = document.getElementById('myResourcesModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function closeBorrowedResourcesModal() {
    const modal = document.getElementById('borrowedResourcesModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function closeMyRequestsModal() {
    const modal = document.getElementById('myRequestsModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function closeMyHazardsModal() {
    const modal = document.getElementById('myHazardsModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function closeMyPerformanceModal() {
    const modal = document.getElementById('myPerformanceModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function closeResourceUtilizationModal() {
    const modal = document.getElementById('resourceUtilizationModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function closeEmergencyPreparednessModal() {
    const modal = document.getElementById('emergencyPreparednessModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function closeMonthlySummaryModal() {
    const modal = document.getElementById('monthlySummaryModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Specific report generation functions
function generateMyResourcesReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.generateSpecificReport('my_resources');
    }
}

function generateBorrowedResourcesReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.generateSpecificReport('borrowed_resources');
    }
}

function generateMyRequestsReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.generateSpecificReport('my_requests');
    }
}

function generateMyHazardsReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.generateSpecificReport('my_hazards');
    }
}

function generateMyPerformanceReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.generateSpecificReport('my_performance');
    }
}

function generateResourceUtilizationReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.generateSpecificReport('resource_utilization');
    }
}

function generateEmergencyPreparednessReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.generateSpecificReport('emergency_preparedness');
    }
}

function generateMonthlySummaryReport() {
    if (window.drrmReportsManager) {
        window.drrmReportsManager.generateSpecificReport('monthly_summary');
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing DRRMReportsManager');
    window.drrmReportsManager = new DRRMReportsManager();
    console.log('DRRMReportsManager initialized:', window.drrmReportsManager);
});