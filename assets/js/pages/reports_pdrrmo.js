/**
 * PDRRMO Reports & Data Analytics
 * - Frequency of reports per request type (resource/equipment)
 * - Request trends, high-demand equipment, reporting patterns by municipality
 */

(function () {
    'use strict';

    let trendsChart = null;
    let highDemandChart = null;
    let municipalityPatternsChart = null;
    let priorityDonutChart = null;
    let lastData = null;

    function getMonths() {
        const el = document.getElementById('pdrrmoPeriodMonths');
        return el ? parseInt(el.value, 10) || 12 : 12;
    }

    async function fetchAnalytics() {
        const months = getMonths();
        const url = `config/drrm_reports_api.php?action=pdrrmo_analytics&months=${months}`;
        const res = await fetch(url);
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Failed to load analytics');
        return json.data;
    }

    function setMonthsLabels(data) {
        const months = parseInt(data && data.periodMonths, 10) || getMonths();
        document.querySelectorAll('[data-pdrrmo-months]').forEach(function (el) {
            el.textContent = String(months);
        });
    }

    function renderRequestTrends(data) {
        const ctx = document.getElementById('pdrrmoRequestTrendsChart');
        if (!ctx) return;
        if (trendsChart) { trendsChart.destroy(); trendsChart = null; }

        const trend = data.requestTrends || [];
        const labels = trend.map(function (t) { return t.period; });
        const values = trend.map(function (t) { return parseInt(t.requestCount, 10) || 0; });

        trendsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Requests',
                    data: values,
                    backgroundColor: 'rgba(33, 150, 243, 0.6)',
                    borderColor: 'rgb(33, 150, 243)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { title: { display: true, text: 'Month' } },
                    y: { beginAtZero: true, title: { display: true, text: 'Request count' } }
                }
            }
        });
    }

    function renderHighDemand(data) {
        const ctx = document.getElementById('pdrrmoHighDemandChart');
        if (!ctx) return;
        if (highDemandChart) { highDemandChart.destroy(); highDemandChart = null; }

        const top = (data.highDemandEquipment || []).slice(0, 10);
        const labels = top.map(function (r) {
            const n = (r.resourceName || 'Unknown').toString();
            return n.length > 20 ? n.substring(0, 18) + '…' : n;
        });
        const values = top.map(function (r) { return parseInt(r.requestCount, 10) || 0; });

        highDemandChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Request count',
                    data: values,
                    backgroundColor: 'rgba(76, 175, 80, 0.6)',
                    borderColor: 'rgb(76, 175, 80)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true },
                    y: { ticks: { maxRotation: 0, autoSkip: false } }
                }
            }
        });
    }

    function renderMostRequestedByMunicipality(data) {
        const wrap = document.getElementById('pdrrmoMunicipalityTopResourcesWrap');
        if (!wrap) return;

        const rows = data.mostRequestedByMunicipality || [];
        if (rows.length === 0) {
            wrap.innerHTML = '<p class="text-muted text-center py-4 mb-0">No request data in the selected period.</p>';
            return;
        }

        const accordionId = 'pdrrmoTopByMunicipalityAcc';
        wrap.innerHTML = `
            <div class="accordion accordion-flush" id="${accordionId}">
                ${rows.map(function (r, idx) {
                    const name = (r.municipalityNameDisplay || r.municipalityName || '—');
                    const tops = Array.isArray(r.topResources) ? r.topResources : [];

                    const top1 = tops[0] || null;
                    const top1Text = top1
                        ? `${escapeHtml(top1.resourceName || '—')} <span class="text-muted">(${(parseInt(top1.requestCount, 10) || 0).toLocaleString()} req)</span>`
                        : '<span class="text-muted">No requests</span>';

                    const itemId = `pdrrmoTopByMunItem_${idx}`;
                    const headId = `${itemId}_head`;
                    const collapseId = `${itemId}_collapse`;

                    const innerTable = tops.length ? `
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 52px;">#</th>
                                        <th>Resource / Equipment</th>
                                        <th class="text-end" style="width: 110px;">Requests</th>
                                        <th class="text-end" style="width: 140px;">Total Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tops.map(function (t, i) {
                                        const res = (t.resourceName || '—');
                                        const cnt = parseInt(t.requestCount, 10) || 0;
                                        const qty = parseInt(t.totalQuantity, 10) || 0;
                                        const unit = (t.unit || '').toString().trim();
                                        const qtyStr = unit ? (qty.toLocaleString() + ' ' + unit) : qty.toLocaleString();
                                        return `<tr>
                                            <td class="text-muted">${i + 1}</td>
                                            <td class="fw-semibold">${escapeHtml(res)}</td>
                                            <td class="text-end">${cnt.toLocaleString()}</td>
                                            <td class="text-end">${qtyStr}</td>
                                        </tr>`;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : `<p class="text-muted mb-0">No requests for this municipality in the selected period.</p>`;

                    return `
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="${headId}">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                                    <div class="d-flex flex-column flex-md-row gap-1 gap-md-3 w-100">
                                        <div class="fw-semibold">${escapeHtml(name)}</div>
                                        <div class="ms-md-auto">${top1Text}</div>
                                    </div>
                                </button>
                            </h2>
                            <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="${headId}" data-bs-parent="#${accordionId}">
                                <div class="accordion-body pt-2">
                                    ${innerTable}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function renderMunicipalityTable(data) {
        const tbody = document.getElementById('pdrrmoMunicipalityTableBody');
        if (!tbody) return;

        const rows = data.reportingPatternsByMunicipality || [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data in the selected period.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (r) {
            const name = (r.municipalityNameDisplay || r.municipalityName || '—');
            const req = parseInt(r.requestsAsRequester, 10) || 0;
            const prov = parseInt(r.requestsAsProvider, 10) || 0;
            const tot = req + prov;
            return `<tr><td>${escapeHtml(name)}</td><td class="text-end">${req.toLocaleString()}</td><td class="text-end">${prov.toLocaleString()}</td><td class="text-end fw-semibold">${tot.toLocaleString()}</td></tr>`;
        }).join('');
    }

    function renderMunicipalityChart(data) {
        const ctx = document.getElementById('pdrrmoMunicipalityPatternsChart');
        if (!ctx) return;
        if (municipalityPatternsChart) { municipalityPatternsChart.destroy(); municipalityPatternsChart = null; }

        const rows = (data.reportingPatternsByMunicipality || []).slice(0, 10);
        const labels = rows.map(function (r) {
            const n = (r.municipalityNameDisplay || r.municipalityName || '').toString();
            return n.length > 14 ? n.substring(0, 12) + '…' : n;
        });
        const asRequester = rows.map(function (r) { return parseInt(r.requestsAsRequester, 10) || 0; });
        const asProvider = rows.map(function (r) { return parseInt(r.requestsAsProvider, 10) || 0; });

        municipalityPatternsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'As Requester', data: asRequester, backgroundColor: 'rgba(255, 152, 0, 0.7)', borderColor: 'rgb(255, 152, 0)', borderWidth: 1 },
                    { label: 'As Provider', data: asProvider, backgroundColor: 'rgba(63, 81, 181, 0.7)', borderColor: 'rgb(63, 81, 181)', borderWidth: 1 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: false },
                    y: { stacked: false, beginAtZero: true }
                }
            }
        });
    }

    function severityBadge(activeCount, highCount, mediumCount) {
        const a = parseInt(activeCount, 10) || 0;
        const h = parseInt(highCount, 10) || 0;
        const m = parseInt(mediumCount, 10) || 0;
        if (a >= 8 || h >= 5) return { label: 'HIGH', cls: 'text-bg-danger' };
        if (a >= 4 || m >= 4) return { label: 'MED', cls: 'text-bg-warning' };
        return { label: 'LOW', cls: 'text-bg-success' };
    }

    function renderHazardTypeFrequency(data) {
        const wrap = document.getElementById('pdrrmoHazardTypeFrequencyWrap');
        if (!wrap) return;
        const rows = Array.isArray(data.hazardTypeFrequency) ? data.hazardTypeFrequency : [];
        if (!rows.length) {
            wrap.innerHTML = '<p class="text-muted text-center py-4 mb-0">No hazard data available for the selected period.</p>';
            return;
        }
        const max = Math.max.apply(null, rows.map(function (r) { return parseInt(r.hazardCount, 10) || 0; }).concat([1]));

        wrap.innerHTML = rows.map(function (r) {
            const name = (r.hazardType || '—').toString();
            const cnt = parseInt(r.hazardCount, 10) || 0;
            const active = parseInt(r.activeCount, 10) || 0;
            const sev = severityBadge(active, r.highCount, r.mediumCount);
            const pct = Math.round((cnt / max) * 100);
            return `
                <div class="pdrrmo-cc-item">
                    <div class="row-top">
                        <div class="name">${escapeHtml(name)}</div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="pdrrmo-cc-pill">${cnt.toLocaleString()} total</span>
                            <span class="pdrrmo-cc-pill">${active.toLocaleString()} active</span>
                            <span class="badge ${sev.cls}">${sev.label}</span>
                        </div>
                    </div>
                    <div class="pdrrmo-cc-bar" title="${cnt} hazards">
                        <span style="width:${pct}%;background:var(--bs-danger,#dc3545);opacity:.75"></span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderHazardHotspots(data) {
        const wrap = document.getElementById('pdrrmoHazardHotspotsWrap');
        if (!wrap) return;
        const rows = Array.isArray(data.hazardHotspots) ? data.hazardHotspots : [];
        if (!rows.length) {
            wrap.innerHTML = '<p class="text-muted text-center py-4 mb-0">No hotspot municipalities found in the selected period.</p>';
            return;
        }
        wrap.innerHTML = `
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Municipality</th>
                            <th class="text-end">Active</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Affected</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map(function (r) {
                            const name = r.municipalityNameDisplay || r.municipalityName || '—';
                            const active = parseInt(r.activeCount, 10) || 0;
                            const total = parseInt(r.hazardCount, 10) || 0;
                            const affected = parseInt(r.totalAffected, 10) || 0;
                            return `<tr>
                                <td class="fw-semibold">${escapeHtml(name)}</td>
                                <td class="text-end"><span class="badge text-bg-danger">${active.toLocaleString()}</span></td>
                                <td class="text-end">${total.toLocaleString()}</td>
                                <td class="text-end">${affected.toLocaleString()}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function ratioLabel(ratio) {
        const x = Number(ratio);
        if (!isFinite(x) || x >= 6) return { text: 'HEAVY BORROWER', cls: 'text-bg-danger' };
        if (x >= 2) return { text: 'MODERATE BORROWER', cls: 'text-bg-warning' };
        if (x >= 0.8 && x <= 1.25) return { text: 'BALANCED', cls: 'text-bg-info' };
        if (x < 0.8) return { text: 'NET PROVIDER', cls: 'text-bg-success' };
        return { text: 'MONITOR', cls: 'text-bg-secondary' };
    }

    function renderDependency(data) {
        const wrap = document.getElementById('pdrrmoDependencyWrap');
        if (!wrap) return;
        const rows = Array.isArray(data.dependencyByMunicipality) ? data.dependencyByMunicipality : [];
        if (!rows.length) {
            wrap.innerHTML = '<p class="text-muted text-center py-4 mb-0">No request data for dependency analysis.</p>';
            return;
        }
        // Show top 12 by activity
        const top = rows.slice(0, 12);
        wrap.innerHTML = `
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Municipality</th>
                            <th class="text-end">Sent</th>
                            <th class="text-end">Provided</th>
                            <th class="text-end">Ratio</th>
                            <th class="text-end">Tag</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${top.map(function (r) {
                            const name = r.municipalityNameDisplay || r.municipalityName || '—';
                            const sent = parseInt(r.requestsSent, 10) || 0;
                            const prov = parseInt(r.requestsProvided, 10) || 0;
                            const ratio = Number(r.dependencyRatio) || 0;
                            const tag = ratioLabel(ratio);
                            const ratioText = (ratio >= 999) ? '∞' : ratio.toFixed(1);
                            return `<tr>
                                <td class="fw-semibold">${escapeHtml(name)}</td>
                                <td class="text-end">${sent.toLocaleString()}</td>
                                <td class="text-end">${prov.toLocaleString()}</td>
                                <td class="text-end">${ratioText}</td>
                                <td class="text-end"><span class="badge ${tag.cls}">${tag.text}</span></td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderFulfillment(data) {
        const wrap = document.getElementById('pdrrmoFulfillmentWrap');
        if (!wrap) return;
        const rows = Array.isArray(data.fulfillmentRateByMunicipality) ? data.fulfillmentRateByMunicipality : [];
        if (!rows.length) {
            wrap.innerHTML = '<p class="text-muted text-center py-4 mb-0">No request data for fulfillment analysis.</p>';
            return;
        }
        const top = rows.slice(0, 12);
        wrap.innerHTML = top.map(function (r) {
            const name = r.municipalityNameDisplay || r.municipalityName || '—';
            const pct = Math.max(0, Math.min(100, Number(r.fulfillmentRate) || 0));
            const badgeCls = pct >= 85 ? 'text-bg-success' : (pct >= 60 ? 'text-bg-warning' : 'text-bg-danger');
            return `
                <div class="pdrrmo-cc-item" style="margin-bottom: 12px;">
                    <div class="row-top">
                        <div class="name">${escapeHtml(name)}</div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge ${badgeCls}">${pct.toFixed(1)}%</span>
                        </div>
                    </div>
                    <div class="pdrrmo-cc-bar">
                        <span style="width:${pct}%;background:var(--bs-success,#198754);opacity:.75"></span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderPriorityDonut(data) {
        const ctx = document.getElementById('pdrrmoPriorityDonutChart');
        const legend = document.getElementById('pdrrmoPriorityLegend');
        if (!ctx) return;
        const rows = Array.isArray(data.priorityDistribution) ? data.priorityDistribution : [];
        const labels = rows.map(function (r) { return r.label; });
        const values = rows.map(function (r) { return parseInt(r.count, 10) || 0; });

        if (priorityDonutChart) { priorityDonutChart.destroy(); priorityDonutChart = null; }

        priorityDonutChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.75)',  // danger
                        'rgba(255, 193, 7, 0.75)',  // warning
                        'rgba(13, 110, 253, 0.65)', // primary
                        'rgba(108, 117, 125, 0.55)' // secondary
                    ],
                    borderColor: [
                        'rgb(220, 53, 69)',
                        'rgb(255, 193, 7)',
                        'rgb(13, 110, 253)',
                        'rgb(108, 117, 125)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                cutout: '62%'
            }
        });

        if (legend) {
            legend.innerHTML = rows.map(function (r, idx) {
                const pct = Number(r.pct) || 0;
                const cnt = parseInt(r.count, 10) || 0;
                const dot = ['#dc3545', '#ffc107', '#0d6efd', '#6c757d'][idx] || '#6c757d';
                return `
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span style="width:10px;height:10px;border-radius:3px;background:${dot};display:inline-block"></span>
                            <span class="small fw-semibold">${escapeHtml(r.label)}</span>
                        </div>
                        <div class="small text-muted">${pct.toFixed(1)}% · ${cnt.toLocaleString()}</div>
                    </div>
                `;
            }).join('');
        }
    }

    function renderProcessingCorridors(data) {
        const wrap = document.getElementById('pdrrmoProcessingWrap');
        if (!wrap) return;
        const rows = Array.isArray(data.avgProcessingTimeCorridors) ? data.avgProcessingTimeCorridors : [];
        if (!rows.length) {
            wrap.innerHTML = '<p class="text-muted text-center py-4 mb-0">Not enough request data to compute corridor processing time.</p>';
            return;
        }
        const top = rows.slice(0, 12);
        wrap.innerHTML = top.map(function (r) {
            const from = r.fromNameDisplay || r.fromName || '—';
            const to = r.toNameDisplay || r.toName || '—';
            const avg = Number(r.avgHours) || 0;
            const badgeCls = avg <= 6 ? 'text-bg-success' : (avg <= 24 ? 'text-bg-warning' : 'text-bg-danger');
            return `
                <div class="pdrrmo-cc-item" style="margin-bottom: 12px;">
                    <div class="row-top" style="margin-bottom: 0;">
                        <div class="name" style="font-size: 0.9rem; font-weight: 600;">${escapeHtml(from)} → ${escapeHtml(to)}</div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge ${badgeCls}">${avg.toFixed(1)}h</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderReturnCompliance(data) {
        const wrap = document.getElementById('pdrrmoReturnComplianceWrap');
        if (!wrap) return;
        const rows = Array.isArray(data.returnComplianceByMunicipality) ? data.returnComplianceByMunicipality : [];
        if (!rows.length) {
            wrap.innerHTML = '<p class="text-muted text-center py-4 mb-0">No returned requests with return dates in the selected period.</p>';
            return;
        }
        const top = rows.slice(0, 12);
        wrap.innerHTML = top.map(function (r) {
            const name = r.municipalityNameDisplay || r.municipalityName || '—';
            const pct = Math.max(0, Math.min(100, Number(r.onTimePct) || 0));
            const badgeCls = pct >= 90 ? 'text-bg-success' : (pct >= 70 ? 'text-bg-warning' : 'text-bg-danger');
            return `
                <div class="pdrrmo-cc-item" style="margin-bottom: 12px;">
                    <div class="row-top">
                        <div class="name">${escapeHtml(name)}</div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge ${badgeCls}">${pct.toFixed(1)}%</span>
                        </div>
                    </div>
                    <div class="pdrrmo-cc-bar">
                        <span style="width:${pct}%;background:var(--bs-success,#198754);opacity:.75"></span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function exportMostRequestedByMunicipalityCsv() {
        const d = lastData;
        console.log('[PDRRMO Export] Top CSV - lastData:', d ? 'loaded' : 'null');
        if (!d) {
            alert('Data is still loading. Please wait a moment and try again.');
            return;
        }
        if (!(d.mostRequestedByMunicipality && d.mostRequestedByMunicipality.length)) {
            alert('No municipality top resources data to export. (Data is loaded but empty)');
            return;
        }
        const headers = ['Municipality', 'Rank', 'Resource/Equipment', 'Unit', 'Request Count', 'Total Quantity'];
        const rows = [];
        d.mostRequestedByMunicipality.forEach(function (m) {
            const muni = m.municipalityNameDisplay || m.municipalityName || '';
            const tops = Array.isArray(m.topResources) ? m.topResources : [];
            tops.slice(0, 5).forEach(function (t, idx) {
                rows.push([muni, idx + 1, t.resourceName || '', t.unit || '', (t.requestCount || 0), (t.totalQuantity || 0)]);
            });
            if (tops.length === 0) {
                rows.push([muni, '', '', '', 0, 0]);
            }
        });
        const csv = [headers.join(','), ...rows.map(function (r) { return r.map(function (c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(','); })].join('\n');
        downloadCsv(csv, 'pdrrmo_top_requested_by_municipality_' + new Date().toISOString().slice(0, 10) + '.csv');
    }

    function exportPatternsCsv() {
        const d = lastData;
        console.log('[PDRRMO Export] Patterns CSV - lastData:', d ? 'loaded' : 'null');
        if (!d) {
            alert('Data is still loading. Please wait a moment and try again.');
            return;
        }
        if (!(d.reportingPatternsByMunicipality && d.reportingPatternsByMunicipality.length)) {
            alert('No municipality patterns to export. (Data is loaded but empty)');
            return;
        }
        const headers = ['Municipality', 'As Requester', 'As Provider', 'Total'];
        const rows = d.reportingPatternsByMunicipality.map(function (r) {
            const req = parseInt(r.requestsAsRequester, 10) || 0;
            const prov = parseInt(r.requestsAsProvider, 10) || 0;
            return [r.municipalityNameDisplay || r.municipalityName || '', req, prov, req + prov];
        });
        const csv = [headers.join(','), ...rows.map(function (r) { return r.map(function (c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(','); })].join('\n');
        downloadCsv(csv, 'pdrrmo_reporting_patterns_' + new Date().toISOString().slice(0, 10) + '.csv');
    }

    function downloadCsv(csv, filename) {
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        const url = URL.createObjectURL(blob);
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }, 150);
    }

    function nextFrame() {
        return new Promise(function (resolve) {
            requestAnimationFrame(function () {
                requestAnimationFrame(resolve);
            });
        });
    }

    function formatDateTime(d) {
        try {
            return new Intl.DateTimeFormat(undefined, {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            }).format(d);
        } catch (_) {
            return d.toLocaleString();
        }
    }

    function canvasToImgDataUrl(canvas) {
        try {
            return canvas.toDataURL('image/png', 1.0);
        } catch (e) {
            return null;
        }
    }


    // ─── Helpers for building PDF table sections ─────────────────────────────
    function pdfCard(titleHtml, bodyHtml, fullWidth) {
        const w = fullWidth ? '100%' : '100%';
        return `
            <div class="pdf-card" style="background:#fff;border:1px solid #dee2e6;border-radius:6px;
                        margin-bottom:10px;overflow:hidden;width:${w};box-sizing:border-box; page-break-inside: avoid;">
                <div style="background:#f8f9fa;padding:7px 10px;border-bottom:1px solid #dee2e6;
                            font-size:11px;font-weight:600;color:#212529;display:flex;
                            align-items:center;gap:6px;">
                    ${titleHtml}
                </div>
                <div style="padding:8px 10px;font-size:10px;color:#212529;">
                    ${bodyHtml}
                </div>
            </div>`;
    }

    function pdfTable(headers, rows) {
        const ths = headers.map(function(h, i) {
            const align = i > 0 ? 'right' : 'left';
            return `<th style="background:#f1f3f5;padding:4px 6px;font-size:9px;font-weight:600;
                                border-bottom:2px solid #dee2e6;text-align:${align};">${h}</th>`;
        }).join('');
        const trs = rows.map(function(cells) {
            const tds = cells.map(function(c, i) {
                const align = i > 0 ? 'right' : 'left';
                return `<td style="padding:3px 6px;font-size:9px;border-bottom:1px solid #f0f0f0;
                               text-align:${align};overflow:hidden;
                               max-width:160px;text-overflow:ellipsis;">${c}</td>`;
            }).join('');
            return `<tr>${tds}</tr>`;
        }).join('');
        return `
            <table style="width:100%;border-collapse:collapse;font-size:9px;table-layout:fixed;">
                <thead><tr>${ths}</tr></thead>
                <tbody>${trs}</tbody>
            </table>`;
    }

    function pdfBarList(items) {
        // items: [{label, count, pct, badgeCls, badgeText, meta}]
        return items.map(function(item) {
            const pct = Math.max(0, Math.min(100, item.pct || 0));
            const badgeColor = {
                'text-bg-danger':   '#dc3545',
                'text-bg-warning':  '#ffc107',
                'text-bg-success':  '#198754',
                'text-bg-info':     '#0dcaf0',
                'text-bg-secondary':'#6c757d'
            }[item.badgeCls] || '#6c757d';
            const badgeTextColor = (item.badgeCls === 'text-bg-warning' || item.badgeCls === 'text-bg-info') ? '#000' : '#fff';
            return `
                <div style="margin-bottom:7px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
                        <span style="font-size:9px;font-weight:600;">${escapeHtml(String(item.label))}</span>
                        <div style="display:flex;align-items:center;gap:5px;">
                            ${item.pill ? `<span style="font-size:8px;color:#6c757d;background:#f0f0f0;padding:1px 5px;border-radius:3px;">${item.pill}</span>` : ''}
                            ${item.badgeText ? `<span style="font-size:8px;padding:2px 5px;border-radius:3px;background:${badgeColor};color:${badgeTextColor};">${item.badgeText}</span>` : ''}
                        </div>
                    </div>
                    <div style="background:#e9ecef;border-radius:2px;height:5px;">
                        <div style="width:${pct}%;height:5px;border-radius:2px;background:${item.barColor || '#0d6efd'};"></div>
                    </div>
                    ${item.meta ? `<div style="font-size:8px;color:#6c757d;margin-top:2px;">${item.meta}</div>` : ''}
                </div>`;
        }).join('');
    }

    function snapshotCanvases() {
        // Returns map of canvas id → dataURL
        const map = {};
        document.querySelectorAll('canvas[id]').forEach(function(c) {
            try { map[c.id] = { url: c.toDataURL('image/png', 1.0), w: c.width, h: c.height }; } catch(e) {}
        });
        return map;
    }

    function chartImg(canvasMap, id, heightPx) {
        const snap = canvasMap[id];
        if (!snap || !snap.url) return `<div style="height:${heightPx}px;background:#f8f9fa;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:9px;color:#6c757d;">No chart data</div>`;
        return `<img src="${snap.url}" style="width:100%;height:${heightPx}px;object-fit:contain;display:block;" />`;
    }

    async function exportAnalyticsPdf() {
        if (typeof window.html2pdf === 'undefined') {
            alert('PDF export library not loaded.');
            return;
        }
        if (!lastData) {
            alert('Analytics data not loaded yet. Please wait for data to load and try again.');
            return;
        }

        // Loading overlay
        const loadingEl = document.createElement('div');
        loadingEl.style.cssText = `
            position:fixed;inset:0;background:rgba(0,0,0,0.55);
            display:flex;flex-direction:column;align-items:center;
            justify-content:center;z-index:99999;color:#fff;
            font-size:1.1rem;gap:14px;
        `;
        loadingEl.innerHTML = `
            <div style="width:48px;height:48px;border:5px solid rgba(255,255,255,0.3);
                 border-top-color:#fff;border-radius:50%;animation:pdfspin 0.8s linear infinite"></div>
            <div>Generating PDF… please wait</div>
            <style>@keyframes pdfspin{to{transform:rotate(360deg)}}</style>
        `;
        document.body.appendChild(loadingEl);
        await new Promise(function(r){ return setTimeout(r, 80); });
        await nextFrame();

        // ── Snapshot all canvases BEFORE we build the stage ──
        const canvasMap = snapshotCanvases();

        // ── Stage width: letter portrait (8.5") minus 2×0.4" margins = 7.7" ──
        // At 96dpi: 7.7 × 96 ≈ 739px. Use 900px for better content fit.
        // html2canvas scale:2 captures at 1800px → jsPDF scales to fit 7.7" page.
        const STAGE_W  = 900;
        const PAD_H    = 38;                          // internal horizontal padding (each side)
        const INNER_W  = STAGE_W - PAD_H * 2;        // 824px — actual usable content width
        const GAP      = 12;                          // gap between columns
        const COL2_W   = Math.floor((INNER_W - GAP) / 2);   // ~406px each
        const COL3_W   = Math.floor((INNER_W - GAP * 2) / 3); // ~266px each

        // No DOM stage needed — html2pdf renders from the HTML string in its own
        // isolated off-screen context, avoiding sidebar coordinate-offset clipping.

        try {
            const d = lastData;
            const months = getMonths();
            const now = new Date();

            // ── Helper: section heading ──
            function sectionHead(label, color) {
                color = color || '#0d6efd';
                return `<div style="font-size:11px;font-weight:700;color:${color};padding:6px 0 4px;
                                    border-bottom:2px solid ${color};margin-bottom:8px;letter-spacing:.3px;">
                    ${escapeHtml(label)}
                </div>`;
            }

            // ═══════════════════════════════════════
            // PAGE 1: Cover header + Hazard Hotspots + Request Fairness (part 1)
            // ═══════════════════════════════════════

            // ─ Hazard type frequency list ─
            const hazTypeRows = Array.isArray(d.hazardTypeFrequency) ? d.hazardTypeFrequency : [];
            const hazTypeMax  = Math.max.apply(null, hazTypeRows.map(function(r){ return parseInt(r.hazardCount,10)||0; }).concat([1]));
            const hazTypeList = hazTypeRows.length ? pdfBarList(hazTypeRows.map(function(r) {
                const cnt  = parseInt(r.hazardCount, 10) || 0;
                const act  = parseInt(r.activeCount, 10) || 0;
                const sev  = severityBadge(act, r.highCount, r.mediumCount);
                return { label: r.hazardType || '—', pct: Math.round((cnt/hazTypeMax)*100),
                         pill: `${cnt} total · ${act} active`, badgeCls: sev.cls, badgeText: sev.label, barColor: '#dc3545' };
            })) : '<p style="color:#6c757d;font-size:9px;">No hazard data.</p>';

            // ─ Hazard hotspots table ─
            const hotspotRows = Array.isArray(d.hazardHotspots) ? d.hazardHotspots : [];
            const hotspotTable = hotspotRows.length ? pdfTable(
                ['Municipality', 'Active', 'Total', 'Affected'],
                hotspotRows.slice(0,12).map(function(r) {
                    const act = parseInt(r.activeCount,10)||0;
                    return [
                        escapeHtml(r.municipalityNameDisplay || r.municipalityName || '—'),
                        `<span style="background:#dc3545;color:#fff;padding:1px 5px;border-radius:3px;font-size:8px;">${act}</span>`,
                        (parseInt(r.hazardCount,10)||0).toLocaleString(),
                        (parseInt(r.totalAffected,10)||0).toLocaleString()
                    ];
                })
            ) : '<p style="color:#6c757d;font-size:9px;">No hotspot data.</p>';

            // ─ Dependency table ─
            const depRows = Array.isArray(d.dependencyByMunicipality) ? d.dependencyByMunicipality.slice(0,12) : [];
            const depTable = depRows.length ? pdfTable(
                ['Municipality', 'Sent', 'Provided', 'Ratio', 'Tag'],
                depRows.map(function(r) {
                    const tag   = ratioLabel(Number(r.dependencyRatio)||0);
                    const ratio = (Number(r.dependencyRatio)||0) >= 999 ? '∞' : (Number(r.dependencyRatio)||0).toFixed(1);
                    const tc    = { 'text-bg-danger':'#dc3545','text-bg-warning':'#ffc107',
                                    'text-bg-success':'#198754','text-bg-info':'#0dcaf0','text-bg-secondary':'#6c757d' }[tag.cls]||'#6c757d';
                    const ttc   = (tag.cls==='text-bg-warning'||tag.cls==='text-bg-info') ? '#000' : '#fff';
                    return [
                        escapeHtml(r.municipalityNameDisplay||r.municipalityName||'—'),
                        (parseInt(r.requestsSent,10)||0).toLocaleString(),
                        (parseInt(r.requestsProvided,10)||0).toLocaleString(),
                        ratio,
                        `<span style="background:${tc};color:${ttc};padding:1px 5px;border-radius:3px;font-size:8px;">${tag.text}</span>`
                    ];
                })
            ) : '<p style="color:#6c757d;font-size:9px;">No dependency data.</p>';

            // ─ Fulfillment rate list ─
            const ffRows = Array.isArray(d.fulfillmentRateByMunicipality) ? d.fulfillmentRateByMunicipality.slice(0,10) : [];
            const ffList = ffRows.length ? pdfBarList(ffRows.map(function(r) {
                const pct = Math.max(0,Math.min(100,Number(r.fulfillmentRate)||0));
                const bc  = pct>=85?'text-bg-success':(pct>=60?'text-bg-warning':'text-bg-danger');
                return { label: r.municipalityNameDisplay||r.municipalityName||'—', pct: pct,
                         badgeCls: bc, badgeText: pct.toFixed(1)+'%', barColor:'#198754' };
            })) : '<p style="color:#6c757d;font-size:9px;">No fulfillment data.</p>';

            // ─ Processing corridors table ─
            const procRows = Array.isArray(d.avgProcessingTimeCorridors) ? d.avgProcessingTimeCorridors : [];
            const procTable = procRows.length ? pdfTable(
                ['Corridor', 'Avg hrs', 'Requests'],
                procRows.slice(0,12).map(function(r) {
                    const avg = Number(r.avgHours)||0;
                    const bc  = avg<=6?'#198754':(avg<=24?'#ffc107':'#dc3545');
                    const tc  = avg<=24?'#000':'#fff';
                    return [
                        escapeHtml((r.fromNameDisplay||r.fromName||'—') + ' → ' + (r.toNameDisplay||r.toName||'—')),
                        `<span style="background:${bc};color:${tc};padding:1px 5px;border-radius:3px;font-size:8px;">${avg.toFixed(1)}h</span>`,
                        (parseInt(r.totalRequests,10)||0).toLocaleString()
                    ];
                })
            ) : '<p style="color:#6c757d;font-size:9px;">No corridor data.</p>';

            // ─ Return compliance list ─
            const retRows = Array.isArray(d.returnComplianceByMunicipality) ? d.returnComplianceByMunicipality.slice(0,10) : [];
            const retList = retRows.length ? pdfBarList(retRows.map(function(r) {
                const pct   = Math.max(0,Math.min(100,Number(r.onTimePct)||0));
                const onT   = parseInt(r.onTimeCount,10)||0;
                const tot   = parseInt(r.totalReturnedWithDue,10)||0;
                const bc    = pct>=90?'text-bg-success':(pct>=70?'text-bg-warning':'text-bg-danger');
                return { label: r.municipalityNameDisplay||r.municipalityName||'—', pct: pct,
                         pill: `${onT}/${tot}`, badgeCls: bc, badgeText: pct.toFixed(1)+'%', barColor:'#198754',
                         meta: `late ${parseInt(r.lateCount,10)||0}` };
            })) : '<p style="color:#6c757d;font-size:9px;">No return compliance data.</p>';

            // ─ Municipality top resources table ─
            const topRows = Array.isArray(d.mostRequestedByMunicipality) ? d.mostRequestedByMunicipality : [];
            let topResourcesHtml = '';
            if (topRows.length) {
                topResourcesHtml = topRows.slice(0, 15).map(function(m) {
                    const name  = escapeHtml(m.municipalityNameDisplay || m.municipalityName || '—');
                    const tops  = Array.isArray(m.topResources) ? m.topResources.slice(0,3) : [];
                    const items = tops.length
                        ? tops.map(function(t,i){
                            return `<div style="display:flex;justify-content:space-between;padding:1px 0;border-bottom:1px solid #f5f5f5;">
                                <span style="font-size:8px;">${i+1}. ${escapeHtml(t.resourceName||'—')}</span>
                                <span style="font-size:8px;color:#6c757d;">${(parseInt(t.requestCount,10)||0)} req</span>
                            </div>`;
                          }).join('')
                        : '<span style="font-size:8px;color:#6c757d;">No data</span>';
                    return `<div style="margin-bottom:6px;padding:5px 7px;background:#fcfcfc;border:1px solid #e9ecef;border-radius:4px;">
                        <div style="font-size:9px;font-weight:600;margin-bottom:3px;">${name}</div>
                        ${items}
                    </div>`;
                }).join('');
            } else {
                topResourcesHtml = '<p style="color:#6c757d;font-size:9px;">No data in the selected period.</p>';
            }

            // ─ Reporting patterns table ─
            const patRows = Array.isArray(d.reportingPatternsByMunicipality) ? d.reportingPatternsByMunicipality : [];
            const patTable = patRows.length ? pdfTable(
                ['Municipality', 'As Requester', 'As Provider', 'Total'],
                patRows.slice(0,15).map(function(r) {
                    const req  = parseInt(r.requestsAsRequester,10)||0;
                    const prov = parseInt(r.requestsAsProvider,10)||0;
                    return [
                        escapeHtml(r.municipalityNameDisplay||r.municipalityName||'—'),
                        req.toLocaleString(), prov.toLocaleString(), (req+prov).toLocaleString()
                    ];
                })
            ) : '<p style="color:#6c757d;font-size:9px;">No pattern data.</p>';

            // ═══════════════════════════════════
            // Assemble the full PDF document HTML
            // ═══════════════════════════════════

            // ── Cover header with table layout to prevent clipping ──
            const ACCENT = '#0d6efd';
            const coverHeader = `
                <table style="width:100%;border-collapse:collapse;margin-bottom:16px;
                              border-bottom:3px solid ${ACCENT};padding-bottom:10px;">
                    <tr>
                        <td style="vertical-align:bottom;">
                            <div style="font-size:16px;font-weight:700;color:${ACCENT};line-height:1.2;">
                                PDRRMO — Data Analytics Report
                            </div>
                            <div style="font-size:9px;color:#6c757d;margin-top:3px;">
                                Province-wide analytics · Period: last ${months} month${months!==1?'s':''}
                            </div>
                        </td>
                        <td style="vertical-align:bottom;text-align:right;">
                            <div style="font-size:8px;color:#6c757d;">
                                Generated: ${formatDateTime(now)}
                            </div>
                        </td>
                    </tr>
                </table>`;

            // ── Grid helpers — table-based layout for reliable html2canvas rendering ──
            function twoCol(left, right) {
                return `<div style="display:flex; width:100%; gap:12px; margin-bottom:0; page-break-inside: avoid;">
                    <div style="flex:1; width: calc(50% - 6px); box-sizing: border-box;">${left}</div>
                    <div style="flex:1; width: calc(50% - 6px); box-sizing: border-box;">${right}</div>
                </div>`;
            }
            function threeCol(a, b, c) {
                return `<div style="display:flex; width:100%; gap:12px; margin-bottom:0; page-break-inside: avoid;">
                    <div style="flex:1; width: calc(33.33% - 8px); box-sizing: border-box;">${a}</div>
                    <div style="flex:1; width: calc(33.33% - 8px); box-sizing: border-box;">${b}</div>
                    <div style="flex:1; width: calc(33.33% - 8px); box-sizing: border-box;">${c}</div>
                </div>`;
            }

            // Chart heights — tuned for portrait pages
            const CH_LG = 200;  // large chart full-width row
            const CH_MD = 160;  // medium chart in 2-col row
            const CH_SM = 140;  // small chart in 2-col row

            // ── Section: Hazard Hotspots ──
            const hazardSection = `
                ${sectionHead('🔴  Hazard Hotspots', '#dc3545')}
                ${twoCol(
                    pdfCard('Hazard Frequency by Type', hazTypeList),
                    pdfCard('Hotspot Municipalities', hotspotTable)
                )}`;

            // ── Section: Request Fairness Audit ──
            const fairnessSection = `
                ${sectionHead('🟢  Request Fairness Audit', '#198754')}
                ${twoCol(
                    pdfCard('Municipality Dependency Score', depTable),
                    pdfCard('Fulfillment Rate by Municipality', ffList)
                )}
                <div style="height:8px;"></div>
                ${twoCol(
                    pdfCard('Request Priority Distribution', chartImg(canvasMap, 'pdrrmoPriorityDonutChart', CH_MD)),
                    pdfCard('Avg. Processing Time (Corridors)', procTable)
                )}
                <div style="height:8px;"></div>
                ${pdfCard('Return Compliance by Municipality', retList)}`;

            // ── Section: Data Analytics Dashboard ──
            const analyticsSection = `
                ${sectionHead('📊  Data Analytics Dashboard', '#6610f2')}
                ${pdfCard('Request Trends Over Time', chartImg(canvasMap, 'pdrrmoRequestTrendsChart', CH_LG))}
                <div style="height:8px;"></div>
                ${pdfCard('High-Demand Equipment (Top 10)', chartImg(canvasMap, 'pdrrmoHighDemandChart', CH_LG))}
                <div style="height:8px;"></div>
                ${twoCol(
                    pdfCard('Most Requested Resources by Municipality', topResourcesHtml),
                    `<div>
                        ${pdfCard('Reporting Patterns (Chart)', chartImg(canvasMap, 'pdrrmoMunicipalityPatternsChart', CH_SM))}
                        <div style="height:6px;"></div>
                        ${pdfCard('Reporting Patterns (Table)', patTable)}
                    </div>`
                )}`;

            // page-break helper recognised by html2pdf
            const pageBreak = `<div style="page-break-before:always;break-before:page;height:0;"></div>`;

            // ── Assemble full document with zero padding (jsPDF margins handle spacing) ──
            const fullDoc = `
                <div style="width:100%;box-sizing:border-box;
                            font-family:Arial,Helvetica,sans-serif;font-size:10px;
                            color:#212529;background:#fff;overflow:hidden;">
                    ${coverHeader}
                    ${hazardSection}
                    <div style="height:8px;"></div>
                    ${fairnessSection}
                    ${pageBreak}
                    <div style="padding-top:6px;">${analyticsSection}</div>
                </div>`;

            // Small delay to let the loading spinner render before the heavy work
            await new Promise(function(r){ return setTimeout(r, 100); });

            const filename = 'pdrrmo_analytics_' + now.toISOString().slice(0, 10) + '.pdf';

            const opt = {
                margin:       [0.4, 0.4, 0.4, 0.4],
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.97 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' },
                pagebreak:    { mode: ['css', 'legacy'] }
            };

            // Render from the HTML string and get the PDF blob
            const pdfBlob = await window.html2pdf().set(opt).from(fullDoc, 'string').output('blob');
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

        } catch (e) {
            console.error('PDF export failed:', e);
            alert('Failed to export PDF: ' + (e && e.message ? e.message : String(e)));
        } finally {
            if (loadingEl && loadingEl.parentNode) { loadingEl.parentNode.removeChild(loadingEl); }
        }
    }

    function renderAll(data) {
        lastData = data;
        setMonthsLabels(data);
        renderRequestTrends(data);
        renderHighDemand(data);
        renderMostRequestedByMunicipality(data);
        renderMunicipalityTable(data);
        renderMunicipalityChart(data);

        renderHazardTypeFrequency(data);
        renderHazardHotspots(data);

        renderDependency(data);
        renderFulfillment(data);
        renderPriorityDonut(data);
        renderProcessingCorridors(data);
        renderReturnCompliance(data);
    }

    async function load() {
        const wrap = document.getElementById('pdrrmoMunicipalityTopResourcesWrap');
        const tbody = document.getElementById('pdrrmoMunicipalityTableBody');
        if (wrap) wrap.innerHTML = '<p class="text-muted text-center py-4 mb-0">Loading…</p>';
        if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Loading…</td></tr>';
        const hazardTypes = document.getElementById('pdrrmoHazardTypeFrequencyWrap');
        const hotspots = document.getElementById('pdrrmoHazardHotspotsWrap');
        const dep = document.getElementById('pdrrmoDependencyWrap');
        const ff = document.getElementById('pdrrmoFulfillmentWrap');
        const proc = document.getElementById('pdrrmoProcessingWrap');
        const ret = document.getElementById('pdrrmoReturnComplianceWrap');
        if (hazardTypes) hazardTypes.innerHTML = '<p class="text-muted text-center py-4 mb-0">Loading…</p>';
        if (hotspots) hotspots.innerHTML = '<p class="text-muted text-center py-4 mb-0">Loading…</p>';
        if (dep) dep.innerHTML = '<p class="text-muted text-center py-4 mb-0">Loading…</p>';
        if (ff) ff.innerHTML = '<p class="text-muted text-center py-4 mb-0">Loading…</p>';
        if (proc) proc.innerHTML = '<p class="text-muted text-center py-4 mb-0">Loading…</p>';
        if (ret) ret.innerHTML = '<p class="text-muted text-center py-4 mb-0">Loading…</p>';

        try {
            const data = await fetchAnalytics();
            renderAll(data);
        } catch (e) {
            console.error('PDRRMO analytics error:', e);
            if (wrap) wrap.innerHTML = '<p class="text-danger text-center py-4 mb-0">Failed to load analytics. (' + escapeHtml(String(e.message)) + ')</p>';
            if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Failed to load.</td></tr>';
            if (hazardTypes) hazardTypes.innerHTML = '<p class="text-danger text-center py-4 mb-0">Failed to load.</p>';
            if (hotspots) hotspots.innerHTML = '<p class="text-danger text-center py-4 mb-0">Failed to load.</p>';
            if (dep) dep.innerHTML = '<p class="text-danger text-center py-4 mb-0">Failed to load.</p>';
            if (ff) ff.innerHTML = '<p class="text-danger text-center py-4 mb-0">Failed to load.</p>';
            if (proc) proc.innerHTML = '<p class="text-danger text-center py-4 mb-0">Failed to load.</p>';
            if (ret) ret.innerHTML = '<p class="text-danger text-center py-4 mb-0">Failed to load.</p>';
        }
    }

    function bind() {
        const sel = document.getElementById('pdrrmoPeriodMonths');
        const refresh = document.getElementById('pdrrmoRefreshAnalytics');
        const expTop = document.getElementById('pdrrmoExportMunicipalityTopCsv');
        const expPat = document.getElementById('pdrrmoExportPatternsCsv');
        const expPdf = document.getElementById('pdrrmoExportAnalyticsPdf');

        if (sel) sel.addEventListener('change', load);
        if (refresh) refresh.addEventListener('click', function(e) { e.preventDefault(); load(); });
        if (expTop) expTop.addEventListener('click', function(e) { e.preventDefault(); exportMostRequestedByMunicipalityCsv(); });
        if (expPat) expPat.addEventListener('click', function(e) { e.preventDefault(); exportPatternsCsv(); });
        if (expPdf) expPdf.addEventListener('click', function(e) { e.preventDefault(); exportAnalyticsPdf(); });

        // Debug: confirm elements were found
        console.log('[PDRRMO Reports] Bound export buttons:', {
            expTop: !!expTop, expPat: !!expPat, expPdf: !!expPdf
        });

        // Ensure dropdown initializes correctly, even if loaded via AJAX
        const exportMenuBtn = document.getElementById('exportMenu');
        if (exportMenuBtn && typeof bootstrap !== 'undefined') {
            try {
                new bootstrap.Dropdown(exportMenuBtn);
            } catch (err) {
                console.warn('Bootstrap dropdown init failed:', err);
            }
        }
    }

    function init() {
        bind();
        load();
    }

    // The page is loaded via AJAX so DOMContentLoaded may have already fired
    // before these elements exist. Poll until the key element is present.
    function tryInit() {
        if (document.getElementById('pdrrmoPeriodMonths')) {
            init();
        } else if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                if (document.getElementById('pdrrmoPeriodMonths')) init();
            });
        } else {
            // Element not yet in DOM — retry after a short delay (AJAX inject)
            var attempts = 0;
            var poll = setInterval(function () {
                attempts++;
                if (document.getElementById('pdrrmoPeriodMonths')) {
                    clearInterval(poll);
                    init();
                } else if (attempts >= 20) {
                    clearInterval(poll); // give up after ~2s
                }
            }, 100);
        }
    }

    tryInit();
})();
