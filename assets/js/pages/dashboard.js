/**
 * Dashboard Page JavaScript - Compatible with existing sidebar
 */

class DashboardPage {
    constructor() {
        this.map = null;
        this.mapInitialized = false;
        this.isMapHidden = true; // Start hidden by default with overlay
        this.clusterGroup = null;
        this.municipalityMarkers = [];
        this.init();
    }
    
    init() {
        console.log('DashboardPage init() called');
        
        // Load key data immediately for fast dashboard readiness
        this.loadRecentRequests();
        this.loadRecentNotifications();
        this.loadAndAnimateCounters();
        this.initializeAnalytics();
        
        // Initialize map after a short delay to ensure DOM is ready
        setTimeout(() => {
            console.log('Initializing map after timeout...');
            this.initializeMunicipalityMap();
            // Wait for map to be fully rendered before setting up controls
            setTimeout(() => {
                this.setupMapControls();
                // Set initial state - map hidden with overlay
                setTimeout(() => {
                    this.setMapHidden(true);
                }, 100);
            }, 200);
        }, 100);
    }
    
    async loadRecentRequests() {
        const container = document.getElementById('recentRequestsList');
        if (!container) return;
        
        try {
            // Removed artificial delay - load immediately when called
            
            const requests = [
                {
                    id: 1,
                    title: 'Emergency Medical Supplies',
                    requester: 'Barangay San Miguel',
                    status: 'pending',
                    date: '2024-03-15'
                },
                {
                    id: 2,
                    title: 'Relief Goods Distribution',
                    requester: 'Barangay Centro',
                    status: 'approved',
                    date: '2024-03-14'
                },
                {
                    id: 3,
                    title: 'Rescue Equipment Request',
                    requester: 'Barangay Riverside',
                    status: 'urgent',
                    date: '2024-03-13'
                }
            ];
            
            container.innerHTML = requests.map(request => `
                <div class="request-item">
                    <div class="request-info">
                        <h4 class="request-title">${request.title}</h4>
                        <p class="request-meta">${request.requester} • ${request.date}</p>
                    </div>
                    <div class="request-status ${request.status}">${request.status}</div>
                </div>
            `).join('');
            
        } catch (error) {
            container.innerHTML = '<div class="error">Failed to load recent requests</div>';
        }
    }
    
    async loadRecentNotifications() {
        const container = document.getElementById('recentNotificationsList');
        if (!container) return;
        
        try {
            // Removed artificial delay - load immediately when called
            
            const notifications = [
                {
                    title: 'Low Stock Alert',
                    message: 'Medical supplies are running low in inventory',
                    time: '2 hours ago',
                    type: 'warning',
                    unread: true
                },
                {
                    title: 'New Resource Request',
                    message: 'Barangay San Miguel submitted a new request',
                    time: '4 hours ago',
                    type: 'info',
                    unread: true
                },
                {
                    title: 'Inventory Updated',
                    message: 'Emergency supplies inventory has been updated',
                    time: '1 day ago',
                    type: 'success',
                    unread: false
                }
            ];
            
            container.innerHTML = notifications.map(notification => `
                <div class="notification-item ${notification.unread ? 'unread' : ''}">
                    <div class="notification-icon">
                        <span class="material-icons">${this.getNotificationIcon(notification.type)}</span>
                    </div>
                    <div class="notification-content">
                        <h5 class="notification-title">${notification.title}</h5>
                        <p class="notification-message">${notification.message}</p>
                        <span class="notification-time">${notification.time}</span>
                    </div>
                </div>
            `).join('');
            
        } catch (error) {
            container.innerHTML = '<div class="error">Failed to load notifications</div>';
        }
    }
    
    getNotificationIcon(type) {
        const icons = {
            warning: 'warning',
            info: 'info',
            success: 'check_circle',
            error: 'error'
        };
        return icons[type] || 'notifications';
    }
    
    async loadAndAnimateCounters() {
        const defaults = { 
            totalResources: 0, 
            pendingRequests: 0, 
            approvedRequests: 0,
            lowStockItems: 0,
            activeHazards: 0
        };
        
        try {
            // Use lazy loading utility with caching and deduplication
            const resOverview = await MunicipalityDashboard.lazyLoadAPI('config/get_resource_overview.php').catch(()=>null);
            const stats = resOverview && resOverview.success ? (resOverview.data?.stats || {}) : {};
            defaults.totalResources = Number(stats.totalResources || 0);
            defaults.lowStockItems = Number(stats.lowStockCount || 0);
        } catch (_) {}
        
        try {
            // Use lazy loading utility with caching and deduplication
            const overview = await MunicipalityDashboard.lazyLoadAPI('config/drrm_reports_api.php?action=overview').catch(()=>null);
            if (overview && overview.success && overview.data) {
                defaults.pendingRequests = Number(overview.data.myRequests?.pendingRequests || 0);
                defaults.approvedRequests = Number(overview.data.myRequests?.approvedRequests || 0);
                defaults.activeHazards = Number(overview.data.myHazards?.active || 0);
            }
        } catch (_) {}
        
        const counters = [
            { id: 'totalResources', target: defaults.totalResources },
            { id: 'pendingRequests', target: defaults.pendingRequests },
            { id: 'approvedRequests', target: defaults.approvedRequests },
            { id: 'lowStockItems', target: defaults.lowStockItems },
            { id: 'activeHazards', target: defaults.activeHazards }
        ];
        
        counters.forEach(counter => {
            const element = document.getElementById(counter.id);
            if (element) {
                this.animateNumber(element, 0, counter.target, 1200);
            }
        });
    }
    
    animateNumber(element, start, end, duration) {
        const startTime = performance.now();
        
        const update = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const current = Math.floor(start + (end - start) * progress);
            element.textContent = current;
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        };
        
        requestAnimationFrame(update);
    }

    initializeMunicipalityMap() {
        // Prevent double-initialization
        if (this.map) {
            console.warn('Map already initialized, skipping re-init');
            return;
        }

        const mapElement = document.getElementById('municipalityMap');
        if (!mapElement) {
            console.error('Map element not found: municipalityMap');
            return;
        }

        // Reset any previous Leaflet instance
        if (mapElement._leaflet_id) {
            try { mapElement._leaflet_id = null; } catch (_) {}
        }

        // Check if Leaflet is loaded
        if (typeof L === 'undefined') {
            console.error('Leaflet library not loaded');
            return;
        }

        console.log('Initializing map...');
        
        try {
            // Initialize map centered on Zamboanga del Sur
            this.map = L.map('municipalityMap', {
                zoomControl: true,
                attributionControl: true,
                dragging: true,
                scrollWheelZoom: true,
                doubleClickZoom: true,
                boxZoom: true,
                keyboard: true,
                tap: false
            }).setView([7.8333, 123.1667], 9);
        } catch (error) {
            console.error('Error initializing map:', error);
            return;
        }

        // Ensure the map container has proper styling
        if (mapElement) {
            if (mapElement.offsetHeight < 360) mapElement.style.minHeight = '420px';
            mapElement.style.pointerEvents = 'auto';
            mapElement.style.touchAction = 'none';
            mapElement.style.cursor = 'grab';
        }

        // Add OpenStreetMap tiles for detailed map
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(this.map);
        
        // Add scale control
        L.control.scale({ metric: true, imperial: false, position: 'bottomleft' }).addTo(this.map);

        // Set bounds to limit zoom to Zamboanga del Sur area
        const zamboangaDelSurBounds = L.latLngBounds(
            L.latLng(6.5, 122.0),
            L.latLng(8.5, 124.5)
        );
        this.map.setMaxBounds(zamboangaDelSurBounds);
        this.map.setMinZoom(7);

        // Add municipality markers
        this.addMunicipalityMarkers();

        this.mapInitialized = true;
        console.log('Map initialized successfully');
    }

    addMunicipalityMarkers() {
        const DATA_URL = 'config/data/zamboanga_del_sur_complete.json';

        const canCluster = (typeof L !== 'undefined' && typeof L.markerClusterGroup === 'function');
        if (canCluster) {
            if (!this.clusterGroup) {
                try {
                    this.clusterGroup = L.markerClusterGroup({
                        showCoverageOnHover: false,
                        spiderfyOnMaxZoom: true,
                        disableClusteringAtZoom: 12,
                        maxClusterRadius: function(z){ return z <= 8 ? 80 : 60; }
                    });
                    this.map.addLayer(this.clusterGroup);
                } catch (_) {
                    // fallback below
                }
            } else {
                try { this.clusterGroup.clearLayers(); } catch(_) {}
            }
        }

        // Fallback: clear previously added markers
        if (!canCluster) {
            if (Array.isArray(this.municipalityMarkers)) {
                this.municipalityMarkers.forEach(it => { try { this.map.removeLayer(it.marker); } catch(_){} });
            }
            this.municipalityMarkers = [];
        }

        // Load and add markers
        fetch(DATA_URL, { cache: 'no-store' })
            .then(r => r.json())
            .then(json => {
                const list = (json && Array.isArray(json.municipalities)) ? json.municipalities : [];
                // Filter to only show municipalities and Pagadian City
                const filtered = list.filter(m => (m && (m.type === 'municipality' || (m.type === 'city' && /pagadian/i.test(m.name)))));
                console.log(`Loaded ${filtered.length} municipalities from JSON`);
                
                if (!filtered.length) {
                    console.warn('Municipality dataset empty, using hardcoded fallback markers');
                    this.addHardcodedMunicipalityMarkers();
                    return;
                }
                
                let addedCount = 0;
                filtered.forEach(m => {
                    const coords = Array.isArray(m.coordinates) ? m.coordinates : [];
                    const lat = Number(coords[0]);
                    const lng = Number(coords[1]);
                    if (!isFinite(lat) || !isFinite(lng)) {
                        return;
                    }
                    
                    const marker = L.marker([lat, lng], { icon: this.makeMunicipalityIcon(m) });
                    marker.bindPopup(this.buildMunicipalityPopup(m), { className: 'custom-popup municipality-popup' });
                    if (this.clusterGroup && canCluster) {
                        this.clusterGroup.addLayer(marker);
                    } else {
                        marker.addTo(this.map);
                        this.municipalityMarkers.push({ marker, municipality: m });
                    }
                    addedCount++;
                });
                console.log(`Map markers: ${addedCount} added`);
            })
            .catch(err => { 
                console.error('Failed to load municipality dataset:', err); 
                this.addHardcodedMunicipalityMarkers(); 
            });
    }

    makeMunicipalityIcon(m) {
        const isCity = m.type === 'city';
        // Use indigo for cities instead of red to avoid confusion with hazards
        const color = isCity ? '#4f46e5' : '#2563eb';
        const size = isCity ? 22 : 18;
        const html = `
            <div class="marker-icon" style="width:${size}px;height:${size}px;border-radius:50%;background:${color};border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>
        `;
        return L.divIcon({ 
            className: 'municipality-marker', 
            html, 
            iconSize: [size, size], 
            iconAnchor: [size/2, size/2], 
            tooltipAnchor: [0, -size/2] 
        });
    }

    buildMunicipalityPopup(m) {
        const fmt = (n) => (Number(n)||0).toLocaleString();
        const pop = fmt(m.population);
        const area = m.area || 'N/A';
        const bgy = fmt(m.barangay_count);
        const postal = m.postal_code || 'N/A';
        const typeLabel = (m.type || 'municipality').toUpperCase();
        
        return `
            <div class="popup-header" style="-webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility;">
                <h4 class="popup-title" style="font-weight: 600; color: white; -webkit-text-stroke: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">${m.name}</h4>
                <span class="popup-type" style="font-weight: 500; -webkit-text-stroke: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">${typeLabel}</span>
            </div>
            <div class="popup-body" style="-webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility;">
                <div class="popup-stats">
                    <div class="stat-item">
                        <span class="stat-label" style="font-weight: 500; -webkit-text-stroke: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">Population</span>
                        <span class="stat-value" style="font-weight: 600; color: #1e293b; -webkit-text-stroke: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">${pop}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label" style="font-weight: 500; -webkit-text-stroke: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">Area</span>
                        <span class="stat-value" style="font-weight: 600; color: #1e293b; -webkit-text-stroke: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">${area}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label" style="font-weight: 500; -webkit-text-stroke: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">Barangays</span>
                        <span class="stat-value" style="font-weight: 600; color: #1e293b; -webkit-text-stroke: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">${bgy}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label" style="font-weight: 500; -webkit-text-stroke: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">Postal Code</span>
                        <span class="stat-value" style="font-weight: 600; color: #1e293b; -webkit-text-stroke: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">${postal}</span>
                    </div>
                </div>
                <p class="popup-description" style="font-weight: 400; color: #475569; -webkit-text-stroke: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">Click on Resources or Requests for detailed data by municipality.</p>
            </div>
        `;
    }

    addHardcodedMunicipalityMarkers() {
        const points = [
            { name: 'Pagadian City', type: 'city', coordinates: [7.8249, 123.4365], population: 210452, area: '378.80 km2', barangay_count: 54, postal_code: '7016' },
            { name: 'Aurora', type: 'municipality', coordinates: [7.9500, 123.5833], population: 52995, area: '180.95 km2', barangay_count: 44, postal_code: '7020' },
            { name: 'Bayog', type: 'municipality', coordinates: [7.8833, 123.0333], population: 34519, area: '354.64 km2', barangay_count: 28, postal_code: '7011' },
            { name: 'Dimataling', type: 'municipality', coordinates: [7.5333, 123.3667], population: 31340, area: '141.80 km2', barangay_count: 24, postal_code: '7032' },
            { name: 'Dinas', type: 'municipality', coordinates: [7.6167, 123.2833], population: 36236, area: '89.50 km2', barangay_count: 30, postal_code: '7030' },
            { name: 'Dumalinao', type: 'municipality', coordinates: [7.8167, 123.3667], population: 39823, area: '139.1 km2', barangay_count: 30, postal_code: '7015' },
            { name: 'Dumingag', type: 'municipality', coordinates: [8.1667, 123.3500], population: 48881, area: '297.17 km2', barangay_count: 44, postal_code: '7028' },
            { name: 'Guipos', type: 'municipality', coordinates: [7.7167, 123.3167], population: 9705, area: '66.6 km2', barangay_count: 17, postal_code: '7042' },
            { name: 'Josefina', type: 'municipality', coordinates: [7.7500, 123.2500], population: 11799, area: '59.90 km2', barangay_count: 14, postal_code: '7027' },
            { name: 'Kumalarang', type: 'municipality', coordinates: [7.7500, 123.1500], population: 29479, area: '151.49 km2', barangay_count: 18, postal_code: '7013' },
            { name: 'Labangan', type: 'municipality', coordinates: [7.8667, 123.1833], population: 44262, area: '163.05 km2', barangay_count: 25, postal_code: '7017' },
            { name: 'Lakewood', type: 'municipality', coordinates: [7.8833, 123.1833], population: 21559, area: '201.30 km2', barangay_count: 14, postal_code: '7014' },
            { name: 'Lapuyan', type: 'municipality', coordinates: [7.6333, 123.1833], population: 27737, area: '329.00 km2', barangay_count: 26, postal_code: '7037' },
            { name: 'Mahayag', type: 'municipality', coordinates: [8.1167, 123.4500], population: 48258, area: '194.90 km2', barangay_count: 29, postal_code: '7026' },
            { name: 'Margosatubig', type: 'municipality', coordinates: [7.5167, 123.3667], population: 37873, area: '244.30 km2', barangay_count: 17, postal_code: '7035' },
            { name: 'Midsalip', type: 'municipality', coordinates: [8.1833, 123.2167], population: 34889, area: '188.30 km2', barangay_count: 33, postal_code: '7021' },
            { name: 'Molave', type: 'municipality', coordinates: [8.0927, 123.4849], population: 53140, area: '251.50 km2', barangay_count: 25, postal_code: '7023' },
            { name: 'Pitogo', type: 'municipality', coordinates: [7.4517, 123.3133], population: 27516, area: '95.94 km2', barangay_count: 15, postal_code: '7033' },
            { name: 'Ramon Magsaysay', type: 'municipality', coordinates: [8.0040, 123.4856], population: 27280, area: '113.70 km2', barangay_count: 27, postal_code: '7024' },
            { name: 'San Miguel', type: 'municipality', coordinates: [7.6483, 123.2676], population: 19838, area: '181.59 km2', barangay_count: 18, postal_code: '7029' },
            { name: 'San Pablo', type: 'municipality', coordinates: [7.6559, 123.4610], population: 26648, area: '149.90 km2', barangay_count: 28, postal_code: '7031' },
            { name: 'Sominot', type: 'municipality', coordinates: [8.0412, 123.3821], population: 19061, area: '111.52 km2', barangay_count: 18, postal_code: '7022' },
            { name: 'Tabina', type: 'municipality', coordinates: [7.4654, 123.4101], population: 25734, area: '71.65 km2', barangay_count: 15, postal_code: '7034' },
            { name: 'Tambulig', type: 'municipality', coordinates: [8.0681, 123.5352], population: 37480, area: '130.65 km2', barangay_count: 31, postal_code: '7025' },
            { name: 'Tigbao', type: 'municipality', coordinates: [7.8211, 123.2266], population: 21675, area: '120.69 km2', barangay_count: 18, postal_code: '7043' },
            { name: 'Tukuran', type: 'municipality', coordinates: [7.8550, 123.5751], population: 42429, area: '144.91 km2', barangay_count: 25, postal_code: '7019' },
            { name: 'Vincenzo A. Sagun', type: 'municipality', coordinates: [7.5164, 123.1697], population: 24852, area: '63.00 km2', barangay_count: 14, postal_code: '7036' }
        ];
        points.forEach(m => {
            const lat = Number(m.coordinates[0]);
            const lng = Number(m.coordinates[1]);
            if (!isFinite(lat) || !isFinite(lng)) return;
            const marker = L.marker([lat, lng], { icon: this.makeMunicipalityIcon(m) });
            marker.bindPopup(this.buildMunicipalityPopup(m), { className: 'custom-popup municipality-popup' });
            marker.addTo(this.map);
            this.municipalityMarkers.push({ marker, municipality: m });
        });
    }

    setupMapControls() {
        const toggleBtn = document.getElementById('toggleMapView');
        const mapElement = document.getElementById('municipalityMap');

        if (toggleBtn) {
            // Inject CSS for map overlay
            if (!document.getElementById('mapToggleBtnStyles')) {
                const style = document.createElement('style');
                style.id = 'mapToggleBtnStyles';
                style.textContent = `
                    #municipalityMap { position: relative !important; }
                    #mapBlurOverlay { 
                        position: absolute !important; 
                        top: 0 !important; 
                        left: 0 !important; 
                        right: 0 !important; 
                        bottom: 0 !important; 
                        width: 100% !important; 
                        height: 100% !important; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        background: rgba(0,0,0,0.85) !important; 
                        backdrop-filter: blur(8px); 
                        -webkit-backdrop-filter: blur(8px); 
                        z-index: 9999 !important; 
                        pointer-events: auto !important; 
                    }
                    .map-overlay { 
                        position: absolute !important; 
                        inset: 0 !important; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        background: rgba(0,0,0,0.85) !important; 
                        backdrop-filter: blur(8px); 
                        -webkit-backdrop-filter: blur(8px); 
                        z-index: 9999 !important; 
                        pointer-events: auto !important; 
                    }
                    .map-overlay .overlay-toggle { 
                        background: rgba(37, 99, 235, 0.95) !important; 
                        color: #fff !important; 
                        border: none !important; 
                        border-radius: 12px; 
                        padding: 16px 32px; 
                        display: inline-flex; 
                        align-items: center; 
                        gap: 12px; 
                        cursor: pointer; 
                        box-shadow: 0 8px 24px rgba(0,0,0,0.4); 
                        transition: all 0.3s ease; 
                        font-size: 16px;
                        font-weight: 600;
                    }
                    .map-overlay .overlay-toggle:hover { 
                        background: rgba(37, 99, 235, 1) !important; 
                        transform: translateY(-2px);
                        box-shadow: 0 12px 32px rgba(0,0,0,0.5);
                    }
                    .map-overlay .overlay-toggle svg { 
                        width: 24px; 
                        height: 24px; 
                    }
                `;
                (document.head || document.documentElement).appendChild(style);
            }

            toggleBtn.addEventListener('click', () => {
                this.toggleMapVisibility();
            });
        }

        // Ensure map container is positioned for overlay and create overlay
        if (mapElement) {
            mapElement.style.position = 'relative';
            mapElement.style.width = '100%';
            mapElement.style.height = '100%';
            
            // Remove existing overlay if present
            const existingOverlay = document.getElementById('mapBlurOverlay');
            if (existingOverlay) {
                existingOverlay.remove();
            }
            
            // Create overlay
            const overlay = document.createElement('div');
            overlay.id = 'mapBlurOverlay';
            overlay.className = 'map-overlay';
            overlay.innerHTML = `
                <button class="overlay-toggle">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M1.5 12s4.5-6.5 10.5-6.5S22.5 12 22.5 12 18 18.5 12 18.5 1.5 12 1.5 12Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="3.5" fill="white"/>
                    </svg>
                    <span>Show Map</span>
                </button>
            `;
            const centerBtn = overlay.querySelector('.overlay-toggle');
            centerBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                this.toggleMapVisibility();
            });
            
            mapElement.appendChild(overlay);
        }
    }

    toggleMapVisibility() {
        this.isMapHidden = !this.isMapHidden;
        this.setMapHidden(this.isMapHidden);
    }

    setMapHidden(hidden) {
        const mapElement = document.getElementById('municipalityMap');
        if (!mapElement) {
            console.error('mapElement not found in setMapHidden');
            return;
        }
        
        const overlay = document.getElementById('mapBlurOverlay');
        const toggleBtn = document.getElementById('toggleMapView');
        const toggleText = document.getElementById('toggleMapText');
        
        if (overlay) {
            if (hidden) {
                overlay.style.display = 'flex';
                overlay.style.visibility = 'visible';
                overlay.style.opacity = '1';
                overlay.style.pointerEvents = 'auto';
                const span = overlay.querySelector('.overlay-toggle span');
                if (span) span.textContent = 'Show Map';
            } else {
                overlay.style.display = 'none';
                overlay.style.pointerEvents = 'none';
                const span = overlay.querySelector('.overlay-toggle span');
                if (span) span.textContent = 'Hide Map';
            }
        }
        
        if (toggleBtn && toggleText) {
            toggleText.textContent = hidden ? 'Show Map' : 'Hide Map';
            const icon = toggleBtn.querySelector('.material-icons');
            if (icon) {
                icon.textContent = hidden ? 'visibility' : 'visibility_off';
            }
        }
        
        // Disable/enable map interactions
        if (this.map) {
            if (hidden) {
                this.map.dragging.disable();
                this.map.scrollWheelZoom.disable();
                this.map.doubleClickZoom.disable();
                this.map.boxZoom.disable();
                this.map.keyboard.disable();
                if (this.map.touchZoom) this.map.touchZoom.disable();
            } else {
                this.map.dragging.enable();
                this.map.scrollWheelZoom.enable();
                this.map.doubleClickZoom.enable();
                this.map.boxZoom.enable();
                this.map.keyboard.enable();
                if (this.map.touchZoom) this.map.touchZoom.enable();
                setTimeout(() => {
                    if (this.map) {
                        this.map.invalidateSize();
                    }
                }, 120);
            }
        }
    }

    showNotification(message, type) {
        if (window.municipalityDashboard && typeof window.municipalityDashboard.showNotification === 'function') {
            window.municipalityDashboard.showNotification(message, type);
        }
    }
}

// Global functions
function createNewRequest() {
    if (window.municipalityDashboard) {
        window.municipalityDashboard.showNotification('Redirecting to create new request...', 'info');
    }
    setTimeout(() => {
        window.location.href = '?page=requests&action=create';
    }, 1000);
}

function updateInventory() {
    if (window.municipalityDashboard) {
        window.municipalityDashboard.showNotification('Redirecting to inventory management...', 'info');
    }
    setTimeout(() => {
        window.location.href = '?page=inventory';
    }, 1000);
}

function generateReport() {
    if (window.municipalityDashboard) {
        window.municipalityDashboard.showNotification('Generating report...', 'info');
    }
    setTimeout(() => {
        const reportData = {
            date: new Date().toISOString(),
            totalResources: 156,
            pendingRequests: 12,
            lowStockItems: 8,
            notifications: 5
        };
        
        const dataStr = JSON.stringify(reportData, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        const url = URL.createObjectURL(dataBlob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = 'dashboard-report.json';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        setTimeout(function () {
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }, 150);
        
        if (window.municipalityDashboard) {
            window.municipalityDashboard.showNotification('Report downloaded!', 'success');
        }
    }, 2000);
}

function viewAllResources() {
    window.location.href = '?page=resources';
}

function viewAllRequests() {
    window.location.href = '?page=requests';
}

function viewAllNotifications() {
    window.location.href = '?page=notifications';
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.dashboard-page')) {
        window.dashboardPage = new DashboardPage();
    }
});

// Analytics (Chart.js) helpers
DashboardPage.prototype.initializeAnalytics = async function() {
    if (typeof Chart === 'undefined') {
        try { await this.loadChartLibrary(); } catch (_) { return; }
    }
    await Promise.all([
        this.renderStockHealthChart(),
        this.renderTopRequestedResourcesChart(),
        this.renderResponseTimeTrendChart()
    ]);
};

DashboardPage.prototype.loadChartLibrary = function() {
    return new Promise((resolve, reject) => {
        if (typeof Chart !== 'undefined') { resolve(); return; }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        s.onload = resolve; s.onerror = reject; document.head.appendChild(s);
    });
};

DashboardPage.prototype.renderStockHealthChart = async function() {
    const el = document.getElementById('dashStockHealthChart');
    if (!el || typeof Chart === 'undefined') return;
    try {
        // Use lazy loading utility with caching and deduplication
        const json = await MunicipalityDashboard.lazyLoadAPI('config/get_resource_overview.php');
        if (!json || !json.success) throw new Error('no data');
        const stats = json.data?.stats || {};
        const total = Number(stats.totalResources || 0);
        const low = Number(stats.lowStockCount || 0);
        const available = Math.max(0, Math.round((Number(stats.availabilityPercentage || 0) / 100) * total));
        const unavailable = Math.max(0, total - available - low);
        const labels = ['Available', 'Low Stock', 'Unavailable'];
        const values = [available, low, unavailable];
        const colors = ['#22c55e','#f59e0b','#ef4444'];
        
        const fontFamily = "'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif";
        const baseFontSize = 13;
        
        new Chart(el, { 
            type: 'doughnut', 
            data: { 
                labels, 
                datasets: [{ 
                    data: values, 
                    backgroundColor: colors, 
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
                            font: {
                                family: fontFamily,
                                size: baseFontSize,
                                weight: '500'
                            },
                            padding: 12,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        titleFont: {
                            family: fontFamily,
                            size: baseFontSize + 1,
                            weight: '600'
                        },
                        bodyFont: {
                            family: fontFamily,
                            size: baseFontSize
                        },
                        padding: 12,
                        boxPadding: 6
                    }
                }
            } 
        });
    } catch (_) {}
};

DashboardPage.prototype.renderTopRequestedResourcesChart = async function() {
    const el = document.getElementById('dashTopRequestedChart');
    if (!el || typeof Chart === 'undefined') return;
    try {
        // Use lazy loading utility with caching and deduplication
        const [incoming, outgoing] = await Promise.all([
            MunicipalityDashboard.lazyLoadAPI('config/drrm_reports_api.php?action=borrowed_resources').catch(()=>null),
            MunicipalityDashboard.lazyLoadAPI('config/drrm_reports_api.php?action=my_requests').catch(()=>null)
        ]);
        const inList = (incoming && incoming.success && Array.isArray(incoming.data?.borrowedRequests)) ? incoming.data.borrowedRequests : [];
        const outList = (outgoing && outgoing.success && Array.isArray(outgoing.data?.myRequests)) ? outgoing.data.myRequests : [];
        const now = new Date();
        const cutoff = new Date(now.getTime() - 30*24*60*60*1000);
        const all = [...inList, ...outList].filter(x => x.requestDate && new Date(x.requestDate) >= cutoff);
        const map = new Map();
        all.forEach(x => {
            const key = x.resourceName || x.resourceType || 'Unspecified';
            map.set(key, (map.get(key) || 0) + 1);
        });
        const top = Array.from(map.entries()).sort((a,b)=> b[1]-a[1]).slice(0,5);
        const labels = top.map(t=>t[0]);
        const data = top.map(t=>t[1]);
        
        const fontFamily = "'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif";
        const baseFontSize = 13;
        
        new Chart(el, { 
            type: 'bar', 
            data: { 
                labels, 
                datasets: [{ 
                    label: 'Requests', 
                    data, 
                    backgroundColor: '#3b82f6',
                    borderRadius: 4
                }] 
            }, 
            options: { 
                indexAxis: 'y', 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        titleFont: {
                            family: fontFamily,
                            size: baseFontSize + 1,
                            weight: '600'
                        },
                        bodyFont: {
                            family: fontFamily,
                            size: baseFontSize
                        },
                        padding: 12,
                        boxPadding: 6
                    }
                },
                scales: { 
                    x: { 
                        beginAtZero: true, 
                        precision: 0,
                        ticks: {
                            font: {
                                family: fontFamily,
                                size: baseFontSize - 1
                            }
                        },
                        title: {
                            display: true,
                            text: 'Number of Requests',
                            font: {
                                family: fontFamily,
                                size: baseFontSize,
                                weight: '500'
                            }
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                family: fontFamily,
                                size: baseFontSize - 1,
                                weight: '500'
                            }
                        }
                    }
                } 
            } 
        });
    } catch (_) {}
};

DashboardPage.prototype.renderResponseTimeTrendChart = async function() {
    const el = document.getElementById('dashResponseTimeChart');
    if (!el || typeof Chart === 'undefined') return;
    try {
        // Use lazy loading utility with caching and deduplication
        const [bor, myr] = await Promise.all([
            MunicipalityDashboard.lazyLoadAPI('config/drrm_reports_api.php?action=borrowed_resources').catch(()=>null),
            MunicipalityDashboard.lazyLoadAPI('config/drrm_reports_api.php?action=my_requests').catch(()=>null)
        ]);
        const borrowed = (bor && bor.success && Array.isArray(bor.data?.borrowedRequests)) ? bor.data.borrowedRequests : [];
        const myReq = (myr && myr.success && Array.isArray(myr.data?.myRequests)) ? myr.data.myRequests : [];
        const weekKey = (d) => {
            const dt = new Date(d); if (isNaN(dt)) return null;
            const onejan = new Date(dt.getFullYear(),0,1);
            const week = Math.ceil((((dt - onejan) / 86400000) + onejan.getDay()+1) / 7);
            return `${dt.getFullYear()}-W${String(week).padStart(2,'0')}`;
        };
        const lastWeeks = (()=>{ const arr=[]; const now=new Date(); for(let i=5;i>=0;i--){ const dt=new Date(now.getFullYear(), now.getMonth(), now.getDate()-i*7); arr.push(weekKey(dt)); } return Array.from(new Set(arr)); })();
        const avgByWeek = (list) => lastWeeks.map(wk => {
            const vals = list.filter(x=> x.responseTime != null && weekKey(x.requestDate) === wk).map(x=> Number(x.responseTime));
            if (!vals.length) return 0; return Math.round(vals.reduce((a,b)=>a+b,0)/vals.length);
        });
        const a = avgByWeek(borrowed);
        const b = avgByWeek(myReq);
        
        const fontFamily = "'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif";
        const baseFontSize = 13;
        
        new Chart(el, { 
            type: 'line', 
            data: { 
                labels: lastWeeks.map(w=>w.replace(/.*-W/,'Wk ')), 
                datasets: [
                    { 
                        label: 'Incoming (to me)', 
                        data: a, 
                        borderColor: '#3b82f6', 
                        backgroundColor: 'rgba(59,130,246,0.2)', 
                        tension: 0.2,
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    { 
                        label: 'Outgoing (from me)', 
                        data: b, 
                        borderColor: '#10b981', 
                        backgroundColor: 'rgba(16,185,129,0.2)', 
                        tension: 0.2,
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ] 
            }, 
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                family: fontFamily,
                                size: baseFontSize,
                                weight: '500'
                            },
                            padding: 12,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        titleFont: {
                            family: fontFamily,
                            size: baseFontSize + 1,
                            weight: '600'
                        },
                        bodyFont: {
                            family: fontFamily,
                            size: baseFontSize
                        },
                        padding: 12,
                        boxPadding: 6
                    }
                },
                scales: { 
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            font: {
                                family: fontFamily,
                                size: baseFontSize - 1
                            }
                        },
                        title: {
                            display: true,
                            text: 'Hours',
                            font: {
                                family: fontFamily,
                                size: baseFontSize,
                                weight: '500'
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: fontFamily,
                                size: baseFontSize - 1,
                                weight: '500'
                            }
                        }
                    }
                } 
            } 
        });
    } catch (_) {}
};
