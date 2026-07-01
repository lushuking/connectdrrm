/**
 * Hazard Information System Dashboard JavaScript
 */

class HazardDashboard {
    constructor() {
        this.hazards = [];
        this.filteredHazards = [];
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.map = null;
        this.markers = [];
        this.isMinimized = true; // Start hidden
        this.mapInitialized = false;
        this._lastQuery = '';
        this._suggestAbortController = null;
        this._suggestCache = new Map(); // queryLower -> { ts, results }
        this.hazardLayers = {
            flood: null,
            landslide: null,
            stormSurge: null,
            earthquake: null,
            // GeoJSON-based layers (Zamboanga del Sur)
            flood50: null,
            rainInducedLandslide: null
        };
        this.layerControl = null; // Store layer control to avoid duplicates
        this.legendControl = null; // Store legend control so we can update it
        // Preload local instant index for zero-delay suggestions
        // Enhanced municipality centers and popular locations for Zamboanga del Sur
        this._municipalityCenters = [
            { name: 'Pagadian City', lat: 7.8258, lng: 123.4370 },
            { name: 'Aurora', lat: 7.9500, lng: 123.5833 },
            { name: 'Bayog', lat: 7.8500, lng: 123.0500 },
            { name: 'Dimataling', lat: 7.5333, lng: 123.3667 },
            { name: 'Dinas', lat: 7.6167, lng: 123.3167 },
            { name: 'Dumalinao', lat: 7.8167, lng: 123.3667 },
            { name: 'Dumingag', lat: 8.1833, lng: 123.3500 },
            { name: 'Guipos', lat: 7.7167, lng: 123.3167 },
            { name: 'Josefina', lat: 8.2000, lng: 123.5333 },
            { name: 'Kumalarang', lat: 7.7500, lng: 123.1500 },
            { name: 'Labangan', lat: 7.8667, lng: 123.5167 },
            { name: 'Lakewood', lat: 7.9500, lng: 123.1500 },
            { name: 'Lapuyan', lat: 7.6333, lng: 123.2000 },
            { name: 'Mahayag', lat: 8.1167, lng: 123.4500 },
            { name: 'Margosatubig', lat: 7.5667, lng: 123.1667 },
            { name: 'Midsalip', lat: 7.9833, lng: 123.2667 },
            { name: 'Molave', lat: 7.9167, lng: 123.4167 },
            { name: 'Pitogo', lat: 7.4500, lng: 123.2333 },
            { name: 'Ramon Magsaysay', lat: 8.0000, lng: 123.5000 },
            { name: 'San Miguel', lat: 7.6500, lng: 123.2667 },
            { name: 'San Pablo', lat: 7.1167, lng: 122.9000 },
            { name: 'Tabina', lat: 7.4667, lng: 123.4000 },
            { name: 'Tambulig', lat: 8.0667, lng: 123.5333 },
            { name: 'Tigbao', lat: 7.8333, lng: 123.1667 },
            { name: 'Tukuran', lat: 7.8500, lng: 123.5667 },
            { name: 'Vincenzo Sagun', lat: 7.5167, lng: 123.1833 },
            // Additional popular locations and barangays in Zamboanga del Sur
            { name: 'Balangasan', lat: 7.8200, lng: 123.4400 },
            { name: 'Pag-asa', lat: 7.8300, lng: 123.4500 },
            { name: 'Poblacion', lat: 7.8258, lng: 123.4370 },
            { name: 'Baliwasan', lat: 7.8100, lng: 123.4200 },
            { name: 'Tumaga', lat: 7.8000, lng: 123.4100 },
            { name: 'Mercedes', lat: 7.8400, lng: 123.4600 },
            { name: 'Sta. Lucia', lat: 7.8300, lng: 123.4300 },
            { name: 'San Jose', lat: 7.8150, lng: 123.4250 },
            { name: 'Baliwasan Chico', lat: 7.8050, lng: 123.4150 },
            { name: 'Baliwasan Grande', lat: 7.8150, lng: 123.4250 },
            // Zamboanga City locations (in Zamboanga del Sur)
            { name: 'Tetuan', lat: 6.9214, lng: 122.0790 },
            { name: 'Pasonanca', lat: 6.9000, lng: 122.1000 },
            { name: 'Ayala', lat: 6.9200, lng: 122.0800 },
            { name: 'Sta. Maria', lat: 6.9100, lng: 122.0700 },
            { name: 'Sta. Catalina', lat: 6.9300, lng: 122.0900 },
            { name: 'Sta. Barbara', lat: 6.9400, lng: 122.1100 }
        ];

        // Initialize barangay index for autocomplete
        this._barangayIndex = null;
        this._barangayIndexPromise = null;
        this.init();
    }

    formatLabel(value) {
        if (!value) return '';
        try {
            const str = String(value).trim().replace(/[-_]+/g, ' ');
            return str
                .split(' ')
                .filter(Boolean)
                .map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
                .join(' ');
        } catch (_) {
            return value;
        }
    }

    async loadBarangayIndex() {
        if (this._barangayIndex) {
            return this._barangayIndex;
        }
        
        if (this._barangayIndexPromise) {
            return this._barangayIndexPromise;
        }
        
        this._barangayIndexPromise = fetch(`${this.getBaseUrl()}config/data/barangay_index.json`)
            .then(response => response.json())
            .then(data => {
                this._barangayIndex = data;
                return data;
            })
            .catch(error => {
                console.error('Failed to load barangay index:', error);
                this._barangayIndex = { locations: [] };
                return this._barangayIndex;
            });
        
        return this._barangayIndexPromise;
    }

    getBaseUrl() {
        // Use global if provided
        if (window.APP_BASE) return window.APP_BASE;
        // Derive from current path (case-insensitive) to support /connectdrrm/
        const path = window.location.pathname;
        const lower = path.toLowerCase();
        const needle = '/connectdrrm/';
        const idx = lower.indexOf(needle);
        if (idx !== -1) {
            return path.substring(0, idx + needle.length);
        }
        // Fallback to root
        return '/connectdrrm/';
    }

    init() {
        this.loadHazards();
        this.initializeMap();
        this.setupMapControls();
        this.bindEvents();
        this.updateMetrics();
    }

    _nearestMunicipality(lat, lng) {
        try {
            let best = null;
            let bestD = Infinity;
            for (const m of this._municipalityCenters) {
                const dLat = (lat - m.lat);
                const dLng = (lng - m.lng);
                const d = dLat * dLat + dLng * dLng; // squared distance is enough for ranking
                if (d < bestD) { bestD = d; best = m; }
            }
            return best;
        } catch (_) { return null; }
    }

    loadHazards() {
        // Since we're now fetching data directly in PHP, we don't need to load it here
        // The data is already available in the PHP variables
        console.log('loadHazards called');
        console.log('window.hazardData:', window.hazardData);
        console.log('window.currentUserId:', window.currentUserId);
        
        const sevenDaysAgo = new Date();
        sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
        
        this.hazards = (window.hazardData || []).filter(hazard => {
            if (hazard.status === 'resolved' && hazard.resolvedAt) {
                const resolvedDate = new Date(hazard.resolvedAt);
                if (resolvedDate < sevenDaysAgo) {
                    return false; // older than 7 days, discard completely
                }
            }
            return true;
        });
        
        this.filteredHazards = this.hazards.filter(h => h.status !== 'resolved');
        console.log('this.hazards:', this.hazards);
        console.log('this.filteredHazards:', this.filteredHazards);
        console.log('Hazards with reportedBy field:', this.hazards.map(h => ({ id: id => id, reportedBy: h.reportedBy }))); // safe print
        this.populateHazardsList();
    }

    initializeMap() {
        // Initialize Leaflet map centered on Zamboanga del Sur
        const savedCenter = sessionStorage.getItem('hazardMapCenter');
        const savedZoom = sessionStorage.getItem('hazardMapZoom');
        // Track if we restored a previous view to avoid auto-fitting markers later
        this._restoredView = !!savedCenter && !!savedZoom;
        const defaultCenter = [7.8258, 123.4370];
        const center = savedCenter ? JSON.parse(savedCenter) : defaultCenter;
        const zoom = savedZoom ? parseInt(savedZoom, 10) : 8;
        this.map = L.map('hazardMap', { zoomControl: false }).setView(center, zoom);

        // Custom position zoom control to bottom right so it doesn't clash with floating header/pill
        L.control.zoom({
            position: 'bottomright'
        }).addTo(this.map);

        // Define base maps
        const streetMap = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18
        });

        const satelliteMap = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
            maxZoom: 18
        });

        const darkModeMap = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            maxZoom: 18
        });

        // Add streetMap by default
        streetMap.addTo(this.map);

        const baseMaps = {
            "<span class='map-control-layer-label'><span class='material-icons' style='font-size:14px;vertical-align:middle;'>map</span>Street View</span>": streetMap,
            "<span class='map-control-layer-label'><span class='material-icons' style='font-size:14px;vertical-align:middle;'>satellite</span>Satellite View</span>": satelliteMap,
            "<span class='map-control-layer-label'><span class='material-icons' style='font-size:14px;vertical-align:middle;'>dark_mode</span>Dark View</span>": darkModeMap
        };

        // Create the Layer Control with base maps
        this.layerControl = L.control.layers(baseMaps, null, {
            position: 'topright',
            collapsed: true
        }).addTo(this.map);

        // Set bounds to limit zoom to Zamboanga del Sur area
        const zamboangaDelSurBounds = L.latLngBounds(
            L.latLng(6.5, 122.0),  // Southwest corner
            L.latLng(8.5, 124.5)   // Northeast corner
        );
        this.map.setMaxBounds(zamboangaDelSurBounds);
        this.map.setMinZoom(8);

        // #region agent log
        this.map.on('layeradd', (e) => {
            const l = e.layer;
            let bounds = null;
            try { if (l.getBounds && l.getBounds()) bounds = l.getBounds().toBBoxString(); } catch (_) {}
        });
        this.map.on('layerremove', (e) => {
        });
        // #endregion

        // Persist map view to keep it stable across interactions
        const saveView = () => {
            const c = this.map.getCenter();
            sessionStorage.setItem('hazardMapCenter', JSON.stringify([c.lat, c.lng]));
            sessionStorage.setItem('hazardMapZoom', String(this.map.getZoom()));
        };
        this.map.on('moveend', saveView);
        this.map.on('zoomend', saveView);

        // Prevent scrolling outside bounds
        this.map.on('drag', () => {
            this.map.panInsideBounds(zamboangaDelSurBounds);
        });

        // Add Zamboanga del Sur boundary highlight
        this.addZamboangaDelSurBoundary();

        // Add hazard markers
        this.addHazardMarkers();
        
        // Load GeoJSON hazard layers (Flood 1:50k and Rain-Induced Landslide – Zamboanga del Sur)
        this.loadGeoJSONHazardLayers();

        // Add legend explaining colors and hazard types
        this.addMapLegend();
        
        this.mapInitialized = true;

        // Add coordinate tracker (Google Maps-style lat/lng display)
        this.addCoordinateTracker();
    }

    addCoordinateTracker() {
        // Create the floating coordinate pill
        const pill = document.createElement('div');
        pill.id = 'mapCoordPill';
        pill.style.cssText = `
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: #e2e8f0;
            font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
            font-size: 12px;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.12);
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
            pointer-events: auto; /* enable click on dismiss button */
            letter-spacing: 0.4px;
            white-space: nowrap;
            transition: opacity 0.2s ease;
            opacity: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        `;
        pill.innerHTML = `
            <span style="color:#60a5fa; font-size:10px;">📍</span>
            <span id="mapCoordLat" style="color:#86efac;">--°N</span>
            <span style="color:rgba(255,255,255,0.3);">|</span>
            <span id="mapCoordLng" style="color:#fbbf24;">--°E</span>
            <span id="mapCoordPinned" style="display:none; margin-left:6px; color:#f472b6; font-size:10px; font-family:sans-serif; cursor:pointer; font-weight:700;">📌 pinned <span id="unpinBtn" style="color:#ef4444; margin-left:4px; font-weight:bold; font-size:12px;">×</span></span>
        `;

        const mapEl = document.getElementById('hazardMap');
        if (mapEl) mapEl.appendChild(pill);

        // Prevent Leaflet map click/interaction when clicking on the coordinates pill itself
        L.DomEvent.disableClickPropagation(pill);

        let isPinned = false;
        this.pinnedLocationMarker = null;

        // Show coordinates on mousemove
        this.map.on('mousemove', (e) => {
            if (isPinned) return;
            const lat = e.latlng.lat.toFixed(6);
            const lng = e.latlng.lng.toFixed(6);
            document.getElementById('mapCoordLat').textContent = `${lat}°N`;
            document.getElementById('mapCoordLng').textContent = `${lng}°E`;
            pill.style.opacity = '1';
        });

        // Hide when mouse leaves
        this.map.on('mouseout', () => {
            if (!isPinned) pill.style.opacity = '0';
        });

        // Pin on click
        this.map.on('click', (e) => {
            // Check if we clicked on map and not on controls/markers
            if (e.originalEvent && e.originalEvent.target && e.originalEvent.target.closest('.leaflet-control, .hazard-marker-container, .marker-cluster')) {
                return;
            }

            const lat = e.latlng.lat.toFixed(6);
            const lng = e.latlng.lng.toFixed(6);
            document.getElementById('mapCoordLat').textContent = `${lat}°N`;
            document.getElementById('mapCoordLng').textContent = `${lng}°E`;
            pill.style.opacity = '1';

            isPinned = true;
            pill.style.background = 'rgba(30, 58, 138, 0.92)';
            pill.style.borderColor = 'rgba(96,165,250,0.5)';
            const pinnedLabel = document.getElementById('mapCoordPinned');
            if (pinnedLabel) pinnedLabel.style.display = 'inline';

            // Remove old marker if any
            if (this.pinnedLocationMarker) {
                this.map.removeLayer(this.pinnedLocationMarker);
            }

            // Create a beautiful pulsed pink pin to highlight the exact pinned spot
            const pinIcon = L.divIcon({
                className: 'pinned-location-marker',
                html: `
                    <div style="position: relative; display: flex; align-items: center; justify-content: center; width: 30px; height: 30px;">
                        <div style="
                            position: absolute;
                            width: 36px;
                            height: 36px;
                            background: rgba(244, 114, 182, 0.4);
                            border-radius: 50%;
                            animation: pinPulse 1.8s infinite ease-in-out;
                        "></div>
                        <div style="
                            background: #f472b6;
                            width: 12px;
                            height: 12px;
                            border-radius: 50%;
                            border: 2px solid white;
                            box-shadow: 0 0 8px rgba(0,0,0,0.4);
                            z-index: 2;
                        "></div>
                    </div>
                `,
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            
            // Add pinPulse animation keyframe styles if they don't exist yet
            if (!document.getElementById('pinPulseStyles')) {
                const style = document.createElement('style');
                style.id = 'pinPulseStyles';
                style.textContent = `
                    @keyframes pinPulse {
                        0% { transform: scale(0.5); opacity: 1; }
                        100% { transform: scale(1.6); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }

            this.pinnedLocationMarker = L.marker(e.latlng, { icon: pinIcon }).addTo(this.map);

            // Auto-populate Report Hazard form coordinates
            const reportLat = document.getElementById('hazardLatitude');
            const reportLng = document.getElementById('hazardLongitude');
            if (reportLat) reportLat.value = lat;
            if (reportLng) reportLng.value = lng;
        });

        // Wire up dismiss/unpin button listener
        const pinnedLabel = pill.querySelector('#mapCoordPinned');
        if (pinnedLabel) {
            pinnedLabel.addEventListener('click', (ev) => {
                ev.preventDefault();
                ev.stopPropagation(); // prevent map click triggering again
                isPinned = false;
                pill.style.background = 'rgba(15, 23, 42, 0.85)';
                pill.style.borderColor = 'rgba(255,255,255,0.12)';
                pinnedLabel.style.display = 'none';
                
                if (this.pinnedLocationMarker) {
                    this.map.removeLayer(this.pinnedLocationMarker);
                    this.pinnedLocationMarker = null;
                }
                
                // Clear form coordinates
                const reportLat = document.getElementById('hazardLatitude');
                const reportLng = document.getElementById('hazardLongitude');
                if (reportLat) reportLat.value = '';
                if (reportLng) reportLng.value = '';

                // Hide pill
                pill.style.opacity = '0';
            });
        }
    }

    setupMapControls() {
        const toggleBtn = document.getElementById('toggleMapView');
        const refreshBtn = document.getElementById('refreshMap');
        const mapElement = document.getElementById('hazardMap');

        if (toggleBtn) {
            // Inject CSS for map overlay only (using Bootstrap for button)
            if (!document.getElementById('hazardMapToggleBtnStyles')) {
                const style = document.createElement('style');
                style.id = 'hazardMapToggleBtnStyles';
                style.textContent = `
                    .map-overlay { position:absolute; inset:0; display:none; align-items:center; justify-content:center; background: rgba(255,255,255,0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 500; }
                    .map-overlay .overlay-toggle { background: rgba(17,24,39,0.8); color:#fff; border:none; border-radius:9999px; padding:10px 16px; display:inline-flex; align-items:center; gap:8px; cursor:pointer; box-shadow: var(--shadow-sm); }
                    .map-overlay .overlay-toggle:hover { background: rgba(17,24,39,0.95); }
                    .map-overlay .overlay-toggle svg { width:18px; height:18px; }
                `;
                (document.head || document.documentElement).appendChild(style);
            }

            // Use Bootstrap classes for the button
            toggleBtn.classList.add('btn', 'btn-primary', 'btn-sm');
            toggleBtn.textContent = this.isMinimized ? 'Show' : 'Hide';
            toggleBtn.addEventListener('click', () => {
                this.toggleMapSize();
                toggleBtn.textContent = this.isMinimized ? 'Show' : 'Hide';
            });
            
        }

        // Ensure map container is positioned for overlay and create overlay once
        if (mapElement) {
            if (!mapElement.style.position) mapElement.style.position = 'relative';
            if (!document.getElementById('hazardMapBlurOverlay')) {
                console.log('Creating hazardMapBlurOverlay...');
                const overlay = document.createElement('div');
                overlay.id = 'hazardMapBlurOverlay';
                overlay.className = 'map-overlay';
                overlay.innerHTML = `
                    <button class="overlay-toggle">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.5 12s4.5-6.5 10.5-6.5S22.5 12 22.5 12 18 18.5 12 18.5 1.5 12 1.5 12Z" stroke="white" stroke-width="1.6"/>
                            <circle cx="12" cy="12" r="3.2" fill="white"/>
                        </svg>
                        <span>Show</span>
                    </button>
                `;
                const centerBtn = overlay.querySelector('.overlay-toggle');
                centerBtn.addEventListener('click', () => {
                    this.toggleMapSize();
                });
                mapElement.appendChild(overlay);
                console.log('hazardMapBlurOverlay created and appended');
            } else {
                console.log('hazardMapBlurOverlay already exists');
            }
            
            // Set initial hidden state after a short delay to ensure map is rendered
            setTimeout(() => {
                this.setMapMinimized(this.isMinimized);
            }, 100);
        }

        // Remove height control if present from previous versions
        const oldHeightControl = document.getElementById('hazardMapHeightControl');
        if (oldHeightControl && oldHeightControl.parentElement) {
            oldHeightControl.parentElement.removeChild(oldHeightControl);
        }

        // Refresh button removed per request
    }

    toggleMapSize() {
        this.isMinimized = !this.isMinimized;
        this.setMapMinimized(this.isMinimized);
        this.showNotification(this.isMinimized ? 'Map hidden' : 'Map shown', 'info');
    }

    setMapMinimized(minimized) {
        const mapElement = document.getElementById('hazardMap');
        if (!mapElement) return;
        
        console.log('setMapMinimized called with:', minimized);
        
        // Map is always visible in the background for the blur effect
        mapElement.style.display = 'block';
        mapElement.style.visibility = 'visible';
        
        // Toggle overlay visibility and button labels
        const overlay = document.getElementById('hazardMapBlurOverlay');
        console.log('Overlay found:', !!overlay);
        if (overlay) {
            overlay.style.display = minimized ? 'flex' : 'none';
            console.log('Overlay display set to:', overlay.style.display);
            const span = overlay.querySelector('.overlay-toggle span');
            if (span) span.textContent = minimized ? 'Show' : 'Hide';
        } else {
            console.error('hazardMapBlurOverlay not found!');
        }
        const topBtn = document.getElementById('toggleMapView');
        if (topBtn) topBtn.textContent = minimized ? 'Show' : 'Hide';
        
        // Disable/enable map interactions
        if (this.map) {
            if (minimized) {
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
                setTimeout(() => this.map && this.map.invalidateSize(), 120);
            }
        }
    }

    ensureMapInitialized() {
        if (this.mapInitialized) return;
        this.initializeMap();
    }

    addZamboangaDelSurBoundary() {
        // Detailed land boundary for ZDS following coastline and borders
        const zamboangaDelSurLand = [
            // Zamboanga City Peninsula - West Coast
            [6.75, 122.05], [6.78, 122.08], [6.82, 122.12], [6.85, 122.15],
            [6.88, 122.18], [6.92, 122.20], [6.95, 122.22], [6.98, 122.24],
            [7.02, 122.26], [7.05, 122.28], [7.08, 122.30], [7.12, 122.32],
            [7.15, 122.34], [7.18, 122.36], [7.22, 122.38], [7.25, 122.40],
            
            // Connection to main landmass
            [7.28, 122.42], [7.30, 122.45], [7.32, 122.48], [7.34, 122.52],
            
            // Main province - North Coast
            [7.35, 122.55], [7.38, 122.60], [7.42, 122.65], [7.45, 122.70],
            [7.48, 122.75], [7.52, 122.80], [7.55, 122.85], [7.58, 122.90],
            [7.62, 122.95], [7.65, 123.00], [7.68, 123.05], [7.72, 123.10],
            [7.75, 123.15], [7.78, 123.20], [7.82, 123.25], [7.85, 123.30],
            [7.88, 123.35], [7.92, 123.40], [7.95, 123.45], [7.98, 123.50],
            [8.02, 123.55], [8.05, 123.60], [8.08, 123.65], [8.12, 123.70],
            [8.15, 123.75], [8.18, 123.80], [8.20, 123.82],
            
            // East Coast
            [8.18, 123.80], [8.15, 123.75], [8.12, 123.70], [8.08, 123.65],
            [8.05, 123.60], [8.02, 123.55], [7.98, 123.50], [7.95, 123.45],
            [7.92, 123.40], [7.88, 123.35], [7.85, 123.30], [7.82, 123.25],
            [7.78, 123.20], [7.75, 123.15], [7.72, 123.10], [7.68, 123.05],
            [7.65, 123.00], [7.62, 122.95], [7.58, 122.90], [7.55, 122.85],
            [7.52, 122.80], [7.48, 122.75], [7.45, 122.70], [7.42, 122.65],
            [7.38, 122.60], [7.35, 122.55], [7.32, 122.50], [7.30, 122.45],
            
            // South Coast back to peninsula
            [7.28, 122.42], [7.25, 122.40], [7.22, 122.38], [7.18, 122.36],
            [7.15, 122.34], [7.12, 122.32], [7.08, 122.30], [7.05, 122.28],
            [7.02, 122.26], [6.98, 122.24], [6.95, 122.22], [6.92, 122.20],
            [6.88, 122.18], [6.85, 122.15], [6.82, 122.12], [6.78, 122.08],
            [6.75, 122.05]
        ];

        // Create land area highlight with smooth curves
        const landArea = L.polygon(zamboangaDelSurLand, {
            color: 'transparent',
            weight: 0,
            opacity: 0,
            fillColor: '#3b82f6',
            fillOpacity: 0.15,
            smoothFactor: 1.0
        }).addTo(this.map);

        // Store references
        this.landArea = landArea;
    }

    async loadMarkerClusterLibrary() {
        if (window.L && window.L.markerClusterGroup) return;
        return new Promise((resolve, reject) => {
            // Load CSS
            if (!document.getElementById('markercluster-css')) {
                const link1 = document.createElement('link');
                link1.id = 'markercluster-css';
                link1.rel = 'stylesheet';
                link1.href = 'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css';
                document.head.appendChild(link1);
            }
            if (!document.getElementById('markercluster-default-css')) {
                const link2 = document.createElement('link');
                link2.id = 'markercluster-default-css';
                link2.rel = 'stylesheet';
                link2.href = 'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css';
                document.head.appendChild(link2);
            }
            // Custom premium marker cluster styles
            if (!document.getElementById('markercluster-premium-styles')) {
                const style = document.createElement('style');
                style.id = 'markercluster-premium-styles';
                style.textContent = `
                    .marker-cluster-premium {
                        background: rgba(15, 23, 42, 0.75);
                        backdrop-filter: blur(8px);
                        -webkit-backdrop-filter: blur(8px);
                        border: 2px solid rgba(255, 255, 255, 0.25);
                        border-radius: 50%;
                        color: #fff;
                        font-weight: 700;
                        font-size: 13px;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.4);
                        width: 40px !important;
                        height: 40px !important;
                        margin-left: -20px !important;
                        margin-top: -20px !important;
                    }
                    .marker-cluster-premium span {
                        line-height: 36px;
                    }
                `;
                document.head.appendChild(style);
            }
            // Load JS
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load MarkerCluster library'));
            document.head.appendChild(script);
        });
    }

    async addHazardMarkers() {
        // Load marker cluster library
        try {
            await this.loadMarkerClusterLibrary();
        } catch (e) {
            console.error("MarkerCluster load failed, falling back to basic markers:", e);
        }

        // Clear existing markers
        if (this.markerClusterGroup) {
            this.map.removeLayer(this.markerClusterGroup);
        }
        this.markers.forEach(marker => this.map.removeLayer(marker));
        this.markers = [];

        // Fetch ZDS coords if not cached
        if (!this._zdsCoordsCache) {
            try {
                // Ensure the path is correct relative to the current page
                const response = await fetch(`${this.getBaseUrl()}config/data/zamboanga_del_sur_complete.json`);
                if (response.ok) {
                    const data = await response.json();
                    let coords = [];
                    if (data.municipalities) {
                        data.municipalities.forEach(mun => {
                            if (mun.coordinates) coords.push(mun.coordinates);
                            if (mun.barangays) {
                                mun.barangays.forEach(brgy => {
                                    if (brgy.coordinates) coords.push(brgy.coordinates);
                                });
                            }
                        });
                    }
                    this._zdsCoordsCache = coords;
                } else {
                    this._zdsCoordsCache = [];
                }
            } catch (e) {
                console.error("Failed to load ZDS coords", e);
                this._zdsCoordsCache = [];
            }
        }

        // Zamboanga del Sur bounds
        const LAT_MIN = 6.5, LAT_MAX = 8.5, LNG_MIN = 122.0, LNG_MAX = 124.5;
        const inBounds = (lat, lng) =>
            isFinite(lat) && isFinite(lng) &&
            lat !== 0 && lng !== 0 &&
            lat >= LAT_MIN && lat <= LAT_MAX &&
            lng >= LNG_MIN && lng <= LNG_MAX;

        // Stable pseudo-random coord seeded from hazard ID — same spot every reload
        const seededRandom = (seed) => {
            let s = Math.abs(parseInt(seed, 10) || 1);
            s = (s * 1664525 + 1013904223) & 0xffffffff;
            return (s >>> 0) / 0xffffffff;
        };
        const getFallbackCoord = (id) => {
            if (this._zdsCoordsCache && this._zdsCoordsCache.length > 0) {
                const index = Math.floor(seededRandom(id) * this._zdsCoordsCache.length);
                const baseCoord = this._zdsCoordsCache[index];
                
                // Add jitter to scatter the points, breaking the artificial diagonal lines
                const jitterLat = (seededRandom(id + 100) - 0.5) * 0.1;
                const jitterLng = (seededRandom(id + 200) - 0.5) * 0.1;
                
                return [baseCoord[0] + jitterLat, baseCoord[1] + jitterLng];
            }
            // fallback if json fails
            const r1 = seededRandom(id);
            const r2 = seededRandom(id + 9999);
            return [
                LAT_MIN + r1 * (LAT_MAX - LAT_MIN),
                LNG_MIN + r2 * (LNG_MAX - LNG_MIN)
            ];
        };

        let fallbackCount = 0;

        // Initialize Marker Cluster Group if library loaded
        if (window.L && window.L.markerClusterGroup) {
            this.markerClusterGroup = L.markerClusterGroup({
                iconCreateFunction: function(cluster) {
                    const childCount = cluster.getChildCount();
                    return L.divIcon({
                        html: `<span>${childCount}</span>`,
                        className: 'marker-cluster-premium',
                        iconSize: L.point(40, 40)
                    });
                },
                maxClusterRadius: 40
            });
        } else {
            this.markerClusterGroup = null;
        }

        this.filteredHazards.forEach(hazard => {
            let [lat, lng] = hazard.coordinates || [0, 0];

            // If coords are missing/invalid, use a stable fallback inside ZDS
            if (!inBounds(lat, lng)) {
                [lat, lng] = getFallbackCoord(hazard.id);
                fallbackCount++;
            }

            const marker = L.marker([lat, lng], {
                icon: this.getHazardIcon(hazard.severity, hazard.type)
            });

            const isFallback = !inBounds(...(hazard.coordinates || [0, 0]));
            marker.bindPopup(`
                <div class="map-popup" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-width: 220px;">
                    <h3 style="font-weight: 600; color: #1e293b; font-size: 14px; margin-bottom: 8px;">${hazard.title}</h3>
                    ${isFallback ? `<p style="color:#f59e0b; font-size:0.75rem; margin-bottom:6px; font-weight: 500;">⚠️ Approximate location — no exact coordinates saved</p>` : ''}
                    <div style="font-size: 12px; display: grid; gap: 4px;">
                        <p style="color: #64748b; margin: 0;"><strong>Location:</strong> ${hazard.location}</p>
                        <p style="color: #64748b; margin: 0;"><strong>Severity:</strong> <span class="badge bg-${hazard.severity === 'critical' ? 'danger' : hazard.severity === 'high' ? 'warning' : 'info'}" style="font-size: 10px; padding: 2px 6px;">${hazard.severity.toUpperCase()}</span></p>
                        <p style="color: #64748b; margin: 0;"><strong>Status:</strong> <span style="text-transform: capitalize;">${hazard.status}</span></p>
                        <p style="color: #64748b; margin: 0;"><strong>Affected:</strong> ${(typeof hazard.affected === 'number' || (!isNaN(hazard.affected) && hazard.affected !== '')) ? Number(hazard.affected).toLocaleString() + ' people' : (hazard.affected || 'Not specified')}</p>
                    </div>
                    <div style="margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                        <button onclick="hazardDashboard.viewHazardDetails(${hazard.id})" class="btn btn-primary btn-sm w-100" style="font-size: 11px; padding: 4px 8px;">View Details</button>
                    </div>
                </div>
            `);

            this.markers.push(marker);

            if (this.markerClusterGroup) {
                this.markerClusterGroup.addLayer(marker);
            } else {
                marker.addTo(this.map);
            }
        });

        if (this.markerClusterGroup) {
            this.map.addLayer(this.markerClusterGroup);
        }

        if (fallbackCount > 0) {
            console.info(`[HazardMap] ${fallbackCount} hazard(s) placed at approximate locations (no exact coords in DB).`);
        }

        // Fit map to show all markers on first load
        if (this.markers.length > 0 && !this._restoredView) {
            const group = new L.featureGroup(this.markers);
            this.map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    getHazardIcon(severity, type) {
        const colors = {
            'low': '#10b981',      // Emerald Green
            'medium': '#f59e0b',   // Amber
            'high': '#ef4444',     // Red
            'critical': '#dc2626'  // Dark Red
        };

        const icons = {
            'flood': 'water',
            'flash-flood': 'water',
            'landslide': 'terrain',
            'earthquake': 'grid_view',
            'typhoon': 'cyclone',
            'storm': 'cyclone',
            'fire': 'whatshot',
            'other': 'warning'
        };

        // Normalize type
        let normType = String(type || 'other').toLowerCase().trim();
        if (normType.includes('flood')) normType = 'flood';
        else if (normType.includes('landslide')) normType = 'landslide';
        else if (normType.includes('earthquake')) normType = 'earthquake';
        else if (normType.includes('typhoon') || normType.includes('storm')) normType = 'storm';
        else if (normType.includes('fire')) normType = 'fire';
        else normType = 'other';

        const iconName = icons[normType] || 'warning';
        const color = colors[severity] || '#f59e0b';

        return L.divIcon({
            className: 'hazard-marker-container',
            html: `
                <div class="hazard-marker-pin" style="
                    background: ${color};
                    width: 30px;
                    height: 30px;
                    border-radius: 50% 50% 50% 0;
                    transform: rotate(-45deg);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
                    border: 2px solid white;
                ">
                    <span class="material-icons" style="
                        transform: rotate(45deg);
                        font-size: 15px;
                        color: white;
                    ">${iconName}</span>
                </div>
            `,
            iconSize: [30, 30],
            iconAnchor: [15, 30],
            popupAnchor: [0, -30]
        });
    }

    toggleFullscreenMap() {
        const mapSection = document.querySelector('.hazard-map-section');
        const fsBtn = document.getElementById('fullscreenMapBtn');
        if (!mapSection || !fsBtn) return;

        const icon = fsBtn.querySelector('.material-icons');

        if (mapSection.classList.contains('fullscreen-active')) {
            mapSection.classList.remove('fullscreen-active');
            if (icon) icon.textContent = 'fullscreen';
            fsBtn.title = 'Toggle Fullscreen Map';
            fsBtn.classList.remove('btn-secondary');
            fsBtn.classList.add('btn-outline-secondary');
        } else {
            // If map is currently blurred/hidden, show it first
            if (this.isMinimized) {
                this.toggleMapSize();
            }
            mapSection.classList.add('fullscreen-active');
            if (icon) icon.textContent = 'fullscreen_exit';
            fsBtn.title = 'Exit Fullscreen Map';
            fsBtn.classList.remove('btn-outline-secondary');
            fsBtn.classList.add('btn-secondary');
        }

        // Invalidate map size so it fits the new container dimensions
        setTimeout(() => {
            if (this.map) {
                this.map.invalidateSize();
            }
        }, 150);
    }

    bindEvents() {
        // Fullscreen map button
        const fsBtn = document.getElementById('fullscreenMapBtn');
        if (fsBtn) {
            fsBtn.addEventListener('click', () => {
                this.toggleFullscreenMap();
            });
        }

        // ESC key to exit fullscreen
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const mapSection = document.querySelector('.hazard-map-section');
                if (mapSection && mapSection.classList.contains('fullscreen-active')) {
                    this.toggleFullscreenMap();
                }
            }
        });

        // Report hazard button
        document.getElementById('reportHazardBtn').addEventListener('click', () => {
            this.showReportModal();
        });

        // Modal close buttons
        document.getElementById('closeReportModal').addEventListener('click', () => {
            this.hideReportModal();
        });

        document.getElementById('cancelReport').addEventListener('click', () => {
            this.hideReportModal();
        });

        // Modal backdrop click
        document.getElementById('reportHazardModal').addEventListener('click', (e) => {
            if (e.target.id === 'reportHazardModal') {
                this.hideReportModal();
            }
        });

        // Report form submission
        document.getElementById('reportHazardForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitHazardReport();
        });

        // Hazard images: validate + preview
        const hazardImagesInput = document.getElementById('hazardImages');
        if (hazardImagesInput) {
            hazardImagesInput.addEventListener('change', () => {
                const v = this.validateHazardImages(hazardImagesInput.files);
                if (!v.ok) {
                    hazardImagesInput.value = '';
                    this.renderHazardImagesPreview([]);
                    this.showNotification(v.message || 'Invalid image selection', 'error');
                    return;
                }
                this.renderHazardImagesPreview(v.files);
            });
        }

        /* 
        // Auto-calc people affected when location input loses focus (if user didn't pick a suggestion)
        // Disabled as per user request for manual input
        const locInput = document.getElementById('hazardLocation');
        if (locInput) {
            locInput.addEventListener('blur', async () => {
                const txt = (locInput.value || '').trim();
                const peopleField = document.getElementById('peopleAffected');
                if (!peopleField) return;
                // If already filled via chips, skip
                if (peopleField.value && Number(peopleField.value) > 0) return;
                if (!txt) return;
                const pop = await this.getLocationPopulation(txt);
                if (pop > 0) {
                    peopleField.value = Math.max(1, Math.round(pop));
                }
            });
        }
        */

        // Edit form save button
        const saveEditBtn = document.getElementById('saveEditHazardBtn');
        if (saveEditBtn) {
            saveEditBtn.addEventListener('click', () => this.submitEditHazard());
        }
        const cancelEditBtn = document.getElementById('cancelEditHazardBtn');
        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', () => this.hideEditHazard());
        }
        const closeEditBtn = document.getElementById('closeEditHazardModal');
        if (closeEditBtn) {
            closeEditBtn.addEventListener('click', () => this.hideEditHazard());
        }

        // Hazards list filters
        document.getElementById('hazardsSearch').addEventListener('input', () => {
            this.filterHazardsList();
        });

        document.getElementById('hazardsSeverityFilter').addEventListener('change', () => {
            this.filterHazardsList();
        });

        document.getElementById('hazardsStatusFilter').addEventListener('change', () => {
            this.filterHazardsList();
        });

        // Hazards list buttons
        document.getElementById('refreshHazardsList').addEventListener('click', () => {
            this.loadHazards();
            this.showNotification('Hazards list refreshed', 'success');
        });

        // Delete confirm modal wiring
        const delModal = document.getElementById('deleteConfirmModal');
        if (delModal) {
            const cancelBtn = document.getElementById('cancelDeleteBtn');
            const closeBtn = document.getElementById('closeDeleteConfirm');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            if (cancelBtn) cancelBtn.addEventListener('click', () => this.hideDeleteConfirm());
            if (closeBtn) closeBtn.addEventListener('click', () => this.hideDeleteConfirm());
            if (confirmBtn) confirmBtn.addEventListener('click', () => this.confirmDelete());
            delModal.addEventListener('click', (e) => { if (e.target === delModal) this.hideDeleteConfirm(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && delModal.classList.contains('active')) this.hideDeleteConfirm(); });
        }

        document.getElementById('reportHazardBtn').addEventListener('click', () => {
            this.showReportModal();
        });

        // Edit hazard form submission
        document.getElementById('editHazardForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitEditHazard();
        });

        // Location input with manual entry + map pin integration
        const locationInput = document.getElementById('hazardLocation');
        const locationDropdown = document.getElementById('locationDropdown');
        this._locationActiveIndex = -1;
        this._searchTimeout = null;
        
        // Add search icons to the input field if autocomplete is used
        if (locationInput && locationDropdown) {
            this.addSearchIcons(locationInput);
            
            locationInput.addEventListener('input', (e) => {
                this._locationActiveIndex = -1;
                
                // Clear previous timeout
                if (this._searchTimeout) {
                    clearTimeout(this._searchTimeout);
                }
                
                // Debounce search to prevent excessive API calls
                this._searchTimeout = setTimeout(() => {
                    this.showLocationSuggestions(e.target.value);
                }, 150); // 150ms delay
            });

            locationInput.addEventListener('focus', () => {
                if (locationInput.value.trim()) {
                    this._locationActiveIndex = -1;
                    this.showLocationSuggestions(locationInput.value);
                }
            });

            locationInput.addEventListener('keydown', (e) => {
                const items = Array.from(locationDropdown.querySelectorAll('.location-suggestion'));
                if (!items.length) return;
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this._locationActiveIndex = (this._locationActiveIndex + 1) % items.length;
                    this.updateActiveSuggestion(items);
                    this.scrollToActiveItem(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this._locationActiveIndex = (this._locationActiveIndex - 1 + items.length) % items.length;
                    this.updateActiveSuggestion(items);
                    this.scrollToActiveItem(items);
                } else if (e.key === 'Enter') {
                    if (this._locationActiveIndex >= 0) {
                        e.preventDefault();
                        items[this._locationActiveIndex].click();
                    }
                } else if (e.key === 'Escape') {
                    locationDropdown.classList.remove('show');
                    this._locationActiveIndex = -1;
                } else if (e.key === 'Tab') {
                    // Allow tab to work normally but close dropdown
                    setTimeout(() => {
                        locationDropdown.classList.remove('show');
                    }, 100);
                }
            });
        }

        // Clear coordinates button
        const clearCoordsBtn = document.getElementById('clearCoordinates');
        if (clearCoordsBtn) {
            clearCoordsBtn.addEventListener('click', () => {
                const latEl = document.getElementById('hazardLatitude');
                const lngEl = document.getElementById('hazardLongitude');
                if (latEl) latEl.value = '';
                if (lngEl) lngEl.value = '';
                this.updateCoordinateDisplay();
            });
        }

        // Hide dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (locationDropdown && !e.target.closest('.location-input-container')) {
                locationDropdown.classList.remove('show');
            }
        });

        // Manual pin by clicking the map disabled per request

        // Map search control disabled per request

        // Search functionality
        document.getElementById('hazardSearch').addEventListener('input', (e) => {
            this.filterHazards();
        });

        // Filter functionality
        document.getElementById('severityFilter').addEventListener('change', () => {
            this.filterHazards();
        });

        document.getElementById('typeFilter').addEventListener('change', () => {
            this.filterHazards();
        });

        document.getElementById('statusFilter').addEventListener('change', () => {
            this.filterHazards();
        });

        // HazardHunterPH toggle
        const toggleHazardHunterBtn = document.getElementById('toggleHazardHunter');
        if (toggleHazardHunterBtn) {
            toggleHazardHunterBtn.addEventListener('click', () => {
                this.toggleHazardHunterView();
            });
        }
        // Map control buttons are wired in setupMapControls()

        // Hazard Details modal close handlers (X, backdrop, ESC)
        const detailsModal = document.getElementById('hazardDetailsModal');
        if (detailsModal) {
            // X button(s)
            detailsModal.querySelectorAll('.modal-close').forEach(btn => {
                btn.addEventListener('click', () => this.hideHazardDetails());
            });
            // Backdrop click
            detailsModal.addEventListener('click', (e) => {
                if (e.target === detailsModal) this.hideHazardDetails();
            });
            // ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && detailsModal.classList.contains('active')) {
                    this.hideHazardDetails();
                }
            });
            // Event delegation as ultimate fallback
            document.addEventListener('click', (e) => {
                if (e.target && e.target.closest && e.target.closest('#hazardDetailsModal .modal-close')) {
                    this.hideHazardDetails();
                }
            });
        }
    }

    showReportModal() {
        document.getElementById('reportHazardModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        const statusGroup = document.getElementById('hazardStatusGroup');
        if (statusGroup) statusGroup.style.display = 'none';
    }

    hideReportModal() {
        document.getElementById('reportHazardModal').classList.remove('active');
        document.body.style.overflow = '';
        const form = document.getElementById('reportHazardForm');
        if (form) form.reset();
        // Clear images preview
        const preview = document.getElementById('hazardImagesPreview');
        if (preview) {
            preview.innerHTML = '';
            preview.style.display = 'none';
        }
        // Reset editing state and UI labels
        const editIdEl = document.getElementById('editHazardId');
        if (editIdEl) editIdEl.value = '';
        const headerEl = document.querySelector('#reportHazardModal .title-content h2');
        if (headerEl) headerEl.textContent = 'Report New Hazard';
        const submitBtn = document.querySelector('#reportHazardForm button[type="submit"]');
        if (submitBtn) submitBtn.textContent = 'Confirm';
        // Clear selected locations and coordinate display
        this.clearAllSelectedLocations();
        const latEl = document.getElementById('hazardLatitude');
        const lngEl = document.getElementById('hazardLongitude');
        if (latEl) latEl.value = '';
        if (lngEl) lngEl.value = '';
        this.updateCoordinateDisplay();
    }

    validateHazardImages(fileList) {
        const files = Array.from(fileList || []);
        if (files.length === 0) return { ok: true, files: [] };
        const maxFiles = 5;
        const maxSizeBytes = 5 * 1024 * 1024;
        const allowed = new Set(['image/jpeg', 'image/png', 'image/webp']);
        if (files.length > maxFiles) {
            return { ok: false, message: `Please upload up to ${maxFiles} images only.` };
        }
        for (const f of files) {
            if (!allowed.has(f.type)) {
                return { ok: false, message: 'Only JPG, PNG, or WebP images are allowed.' };
            }
            if (f.size > maxSizeBytes) {
                return { ok: false, message: 'Each image must be 5MB or smaller.' };
            }
        }
        return { ok: true, files };
    }

    renderHazardImagesPreview(files) {
        const preview = document.getElementById('hazardImagesPreview');
        if (!preview) return;
        preview.innerHTML = '';
        if (!files || files.length === 0) {
            preview.style.display = 'none';
            return;
        }
        preview.style.display = 'flex';
        for (const file of files) {
            const url = URL.createObjectURL(file);
            const wrapper = document.createElement('div');
            wrapper.style.width = '90px';
            wrapper.style.height = '90px';
            wrapper.style.border = '1px solid #e5e7eb';
            wrapper.style.borderRadius = '10px';
            wrapper.style.overflow = 'hidden';
            wrapper.style.background = '#f8fafc';
            wrapper.title = file.name;
            const img = document.createElement('img');
            img.src = url;
            img.alt = file.name;
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.objectFit = 'cover';
            img.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
            wrapper.appendChild(img);
            preview.appendChild(wrapper);
        }
    }

    async submitHazardReport() {
        const form = document.getElementById('reportHazardForm');
        if (!form) return;

        // Ensure coordinates are set (from chip coords, location text, or geocode fallback)
        const latEl = document.getElementById('hazardLatitude');
        const lngEl = document.getElementById('hazardLongitude');
        let lat = parseFloat(latEl?.value || '');
        let lng = parseFloat(lngEl?.value || '');
        const inBounds = (x, min, max) => isFinite(x) && x >= min && x <= max;
        if (!inBounds(lat, 6.5, 8.5) || !inBounds(lng, 122.0, 124.5)) {
            // Try from first selected chip
            const container = document.getElementById('selectedLocationsContainer');
            const firstChip = container?.querySelector('.selected-location-chip');
            const chipLat = parseFloat(firstChip?.getAttribute('data-lat') || '');
            const chipLng = parseFloat(firstChip?.getAttribute('data-lng') || '');
            if (inBounds(chipLat, 6.5, 8.5) && inBounds(chipLng, 122.0, 124.5)) {
                lat = chipLat; lng = chipLng;
                if (latEl) latEl.value = String(lat);
                if (lngEl) lngEl.value = String(lng);
            }
        }
        if (!inBounds(lat, 6.5, 8.5) || !inBounds(lng, 122.0, 124.5)) {
            // Geocode fallback from plain location name
            const txt2 = (document.getElementById('hazardLocation')?.value || '').trim();
            if (txt2) {
                try { await this.lookupLocationCoordinates(txt2); } catch (_) {}
                lat = parseFloat(latEl?.value || '');
                lng = parseFloat(lngEl?.value || '');
            }
        }

        // After attempts, if still invalid, block submit
        if (!inBounds(lat, 6.5, 8.5) || !inBounds(lng, 122.0, 124.5)) {
            this.showNotification('Please select a valid location within Zamboanga del Sur (lat/lng required).', 'error');
            return;
        }

        // Validate selected images before uploading
        const imagesInput = document.getElementById('hazardImages');
        const imagesValidation = this.validateHazardImages(imagesInput?.files);
        if (!imagesValidation.ok) {
            this.showNotification(imagesValidation.message || 'Invalid image selection', 'error');
            return;
        }

        const formData = new FormData(form);

        // Override 'other' fields
        if (formData.get('hazardType') === 'other') {
            const otherHazard = formData.get('otherHazardType');
            if (otherHazard) formData.set('hazardType', otherHazard);
        }
        if (formData.get('hazardSource') === 'other') {
            const otherSource = formData.get('otherHazardSource');
            if (otherSource) formData.set('hazardSource', otherSource);
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Submitting...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('config/submit_hazard.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Hazard report submitted successfully!', 'success');
                this.hideReportModal();
                
                // Refresh the page to show new data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                throw new Error(result.message || 'Failed to submit hazard report');
            }
            
        } catch (error) {
            console.error('Error submitting hazard report:', error);
            this.showNotification('Error: ' + error.message, 'error');
        } finally {
            // Reset button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }

    async submitEditHazard() {
        const form = document.getElementById('editHazardForm');
        if (!form) return;

        // Ensure coordinates exist in edit form as well
        const latEl = document.getElementById('edit_hazardLatitude') || document.getElementById('hazardLatitude');
        const lngEl = document.getElementById('edit_hazardLongitude') || document.getElementById('hazardLongitude');
        let lat = parseFloat(latEl?.value || '');
        let lng = parseFloat(lngEl?.value || '');
        const inBounds = (x, min, max) => isFinite(x) && x >= min && x <= max;
        if (!inBounds(lat, 6.5, 8.5) || !inBounds(lng, 122.0, 124.5)) {
            // Fallback to chip
            const container = document.getElementById('edit_selectedLocationsContainer');
            const firstChip = container?.querySelector('.selected-location-chip');
            const chipLat = parseFloat(firstChip?.getAttribute('data-lat') || '');
            const chipLng = parseFloat(firstChip?.getAttribute('data-lng') || '');
            if (inBounds(chipLat, 6.5, 8.5) && inBounds(chipLng, 122.0, 124.5)) {
                lat = chipLat; lng = chipLng;
                if (latEl) latEl.value = String(lat);
                if (lngEl) lngEl.value = String(lng);
            }
        }
        if (!inBounds(lat, 6.5, 8.5) || !inBounds(lng, 122.0, 124.5)) {
            // Geocode fallback from plain location name
            const txt2 = (document.getElementById('edit_hazardLocation')?.value || '').trim();
            if (txt2) {
                try { await this.lookupLocationCoordinates(txt2); } catch (_) {}
                lat = parseFloat(latEl?.value || '');
                lng = parseFloat(lngEl?.value || '');
            }
        }
        if (!inBounds(lat, 6.5, 8.5) || !inBounds(lng, 122.0, 124.5)) {
            this.showNotification('Please select a valid location within Zamboanga del Sur (lat/lng required).', 'error');
            return;
        }

        const data = new FormData(form);
        try {
            const response = await fetch('config/submit_hazard.php', {
                method: 'POST',
                body: data
            });
            const result = await response.json();
            if (result.success) {
                // Close modal
                const el = document.getElementById('editHazardModal');
                if (el && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(el).hide();
                }
                this.showNotification('Hazard updated successfully', 'success');
                // Reload to reflect changes
                setTimeout(() => window.location.reload(), 500);
            } else {
                this.showNotification(result.message || result.error?.message || 'Failed to update hazard', 'error');
            }
        } catch (e) {
            console.error(e);
            this.showNotification('Network error updating hazard', 'error');
        }
    }

    hideEditHazard() {
        const el = document.getElementById('editHazardModal');
        if (!el) return;
        el.classList.remove('active');
        el.style.display = 'none';
        document.body.style.overflow = '';
    }

    filterHazards() {
        const searchTerm = document.getElementById('hazardSearch').value.toLowerCase();
        const severityFilter = document.getElementById('severityFilter').value;
        const typeFilter = document.getElementById('typeFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;

        this.filteredHazards = this.hazards.filter(hazard => {
            const matchesSearch = hazard.title.toLowerCase().includes(searchTerm) ||
                                hazard.location.toLowerCase().includes(searchTerm) ||
                                hazard.description.toLowerCase().includes(searchTerm);
            
            const matchesSeverity = !severityFilter || hazard.severity === severityFilter;
            const matchesType = !typeFilter || hazard.type === typeFilter;
            const matchesStatus = statusFilter ? (hazard.status === statusFilter) : (hazard.status !== 'resolved');

            return matchesSearch && matchesSeverity && matchesType && matchesStatus;
        });

        this.currentPage = 1;
        this.addHazardMarkers();
        this.populateHazardsList();
    }

    filterHazardsList() {
        const searchTerm = document.getElementById('hazardsSearch')?.value.toLowerCase() || '';
        const severityFilter = document.getElementById('hazardsSeverityFilter')?.value || '';
        const statusFilter = document.getElementById('hazardsStatusFilter')?.value || '';

        this.filteredHazards = this.hazards.filter(hazard => {
            const matchesSearch = hazard.title.toLowerCase().includes(searchTerm) ||
                                hazard.location.toLowerCase().includes(searchTerm) ||
                                hazard.description.toLowerCase().includes(searchTerm);
            
            const matchesSeverity = !severityFilter || hazard.severity === severityFilter;
            const matchesStatus = statusFilter ? (hazard.status === statusFilter) : (hazard.status !== 'resolved');

            return matchesSearch && matchesSeverity && matchesStatus;
        });

        this.currentPage = 1;
        this.populateHazardsList();
    }



    updateMetrics() {
        const activeHazards = this.hazards.filter(h => h.status === 'active').length;
        const criticalAlerts = this.hazards.filter(h => h.severity === 'critical').length;
        const underMonitor = this.hazards.filter(h => h.status === 'monitoring').length;

        const affectedStrings = [];
        let totalAffected = 0;
        this.hazards.forEach(h => {
            if (!h.affected) return;
            const num = parseInt(h.affected, 10);
            if (!isNaN(num)) {
                totalAffected += num;
                const desc = String(h.affected).replace(/^\d+\s*/, '').trim();
                if (desc) {
                    affectedStrings.push(`${num} ${desc}`);
                }
            } else {
                affectedStrings.push(String(h.affected));
            }
        });

        document.getElementById('activeHazards').textContent = activeHazards;
        
        const countEl = document.getElementById('peopleAffectedCount');
        if (countEl) countEl.textContent = totalAffected.toLocaleString();

        const descEl = document.getElementById('peopleAffectedDesc');
        if (descEl) {
            if (affectedStrings.length > 0) {
                const uniqueStrings = [...new Set(affectedStrings)];
                const displayDesc = uniqueStrings.slice(0, 3).join(', ') + (uniqueStrings.length > 3 ? '...' : '');
                descEl.textContent = `(${displayDesc})`;
                descEl.style.display = 'block';
            } else {
                descEl.style.display = 'none';
            }
        }

        document.getElementById('criticalAlerts').textContent = criticalAlerts;
        document.getElementById('underMonitor').textContent = underMonitor;
    }


    toggleMapView() {
        this.toggleMapSize();
    }

    refreshMap() {
        // Clear existing markers
        if (this.markers) {
            this.markers.forEach(marker => {
                this.map.removeLayer(marker);
            });
            this.markers = [];
        }
        
        // Re-add all markers
        this.addHazardMarkers();
        this.showNotification('Map refreshed', 'info');
    }

    pinLocationOnMap() {
        const locationInput = document.getElementById('hazardLocation');
        const location = locationInput.value.trim();
        
        if (!location) return;

        // Clear existing location pin
        if (this.locationPin) {
            this.map.removeLayer(this.locationPin);
        }

        // Try to geocode the location
        this.geocodeLocation(location);
    }

    geocodeLocation(location) {
        // For demo purposes, we'll use a simple coordinate mapping
        // In production, you'd use a proper geocoding service like OpenStreetMap Nominatim
        const locationCoordinates = this.getLocationCoordinates(location);
        
        if (locationCoordinates) {
            this.addLocationPin(locationCoordinates, location);
            this.map.setView(locationCoordinates, 12);
            this.showNotification(`Location pinned: ${location}`, 'success');
        } else {
            this.showNotification(`Location not found: ${location}`, 'error');
        }
    }

    getLocationCoordinates(location) {
        // Simple coordinate mapping for common Zamboanga del Sur locations
        const locationMap = {
            'pagadian': [7.8258, 123.4370],
            'pagadian city': [7.8258, 123.4370],
            'zamboanga city': [6.9214, 122.0790],
            'dumingag': [8.1833, 123.3500],
            'molave': [7.9167, 123.4167],
            'aurora': [7.9500, 123.5833],
            'bayog': [7.8500, 123.0500],
            'dimataling': [7.5333, 123.3667],
            'dinas': [7.6167, 123.3167],
            'dumalinao': [7.8167, 123.3667],
            'guipos': [7.7167, 123.3167],
            'josefina': [8.2000, 123.5333],
            'kumalarang': [7.7500, 123.1500],
            'labangan': [7.8667, 123.5167],
            'lakewood': [7.9500, 123.1500],
            'lapuyan': [7.6333, 123.2000],
            'mahayag': [8.1167, 123.4500],
            'margosatubig': [7.5667, 123.1667],
            'midsalip': [7.9833, 123.2667],
            'pitogo': [7.4500, 123.2333],
            'ramon magsaysay': [8.0000, 123.5000],
            'san miguel': [7.6500, 123.2667],
            'san pablo': [7.1167, 122.9000],
            'tabina': [7.4667, 123.4000],
            'tambulig': [8.0667, 123.5333],
            'tigbao': [7.8333, 123.1667],
            'tukuran': [7.8500, 123.5667],
            'vincenzo sagun': [7.5167, 123.1833]
        };

        const normalizedLocation = location.toLowerCase();
        
        // Check for exact matches first
        if (locationMap[normalizedLocation]) {
            return locationMap[normalizedLocation];
        }

        // Check for partial matches
        for (const [key, coords] of Object.entries(locationMap)) {
            if (normalizedLocation.includes(key) || key.includes(normalizedLocation)) {
                return coords;
            }
        }

        return null;
    }

    addLocationPin(coordinates, location) {
        // Create a red pin for the location
        const locationIcon = L.divIcon({
            className: 'location-pin',
            html: `<div style="
                background: #ef4444;
                width: 24px;
                height: 24px;
                border-radius: 50% 50% 50% 0;
                border: 3px solid white;
                box-shadow: 0 3px 10px rgba(0,0,0,0.3);
                transform: rotate(-45deg);
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <div style="
                    color: white;
                    font-size: 12px;
                    font-weight: bold;
                    transform: rotate(45deg);
                ">📍</div>
            </div>`,
            iconSize: [24, 24],
            iconAnchor: [12, 24]
        });

        this.locationPin = L.marker(coordinates, { icon: locationIcon }).addTo(this.map);
        
        this.locationPin.bindPopup(`
            <div class="location-popup">
                <h4>📍 ${location}</h4>
                <p>Reported location for hazard</p>
                <button onclick="hazardDashboard.removeLocationPin()" class="btn btn-sm btn-secondary">Remove Pin</button>
            </div>
        `);
    }

    async addLocationToSelected(locationName, coords) {
        // Get population for this location
        const population = await this.getLocationPopulation(locationName);
        
        // Create location chip without showing population
        const chip = document.createElement('div');
        chip.className = 'selected-location-chip';
        chip.innerHTML = `
            <div class="location-chip-content">
                <span class="material-icons location-icon">place</span>
                <span class="location-name">${locationName}</span>
                <button type="button" class="remove-location-btn" onclick="hazardDashboard.removeLocationChip(this)">
                    <span class="material-icons">close</span>
                </button>
            </div>
        `;

        // Store population data as a data attribute for calculation
        chip.setAttribute('data-population', population);
        // Store coordinates if provided
        if (Array.isArray(coords) && coords.length >= 2) {
            const lat = parseFloat(coords[0]);
            const lng = parseFloat(coords[1]);
            if (isFinite(lat) && isFinite(lng)) {
                chip.setAttribute('data-lat', String(lat));
                chip.setAttribute('data-lng', String(lng));
            }
        }

        // Add to selected locations container
        const container = document.getElementById('selectedLocationsContainer');
        if (container) {
            container.style.display = 'block';
            const list = container.querySelector('.selected-locations-list');
            if (list) {
                list.appendChild(chip);
            }
        }

        // Update people affected calculation
        this.calculatePeopleAffected();
    }

    addLocationToSelectedWithPopulation(locationName, coords, population) {
        // Create location chip without showing population
        const chip = document.createElement('div');
        chip.className = 'selected-location-chip';
        chip.innerHTML = `
            <div class="location-chip-content">
                <span class="material-icons location-icon">place</span>
                <span class="location-name">${locationName}</span>
                <button type="button" class="remove-location-btn" onclick="hazardDashboard.removeLocationChip(this)">
                    <span class="material-icons">close</span>
                </button>
            </div>
        `;

        // Store population data as a data attribute for calculation
        chip.setAttribute('data-population', population);
        // Store coordinates if provided
        if (Array.isArray(coords) && coords.length >= 2) {
            const lat = parseFloat(coords[0]);
            const lng = parseFloat(coords[1]);
            if (isFinite(lat) && isFinite(lng)) {
                chip.setAttribute('data-lat', String(lat));
                chip.setAttribute('data-lng', String(lng));
            }
        }

        // Add to selected locations container
        const container = document.getElementById('selectedLocationsContainer');
        if (container) {
            container.style.display = 'block';
            const list = container.querySelector('.selected-locations-list');
            if (list) {
                list.appendChild(chip);
            }
        }

        // Update people affected calculation
        this.calculatePeopleAffected();
    }

    async getLocationPopulation(locationName) {
        try {
            const response = await fetch('config/data/barangay_index.json');
            const data = await response.json();
            
            // Search for the location in the data
            const location = data.locations.find(loc => 
                loc.name.toLowerCase() === locationName.toLowerCase() ||
                loc.display_name.toLowerCase() === locationName.toLowerCase()
            );
            
            return location ? (location.population || 0) : 0;
        } catch (error) {
            console.error('Error getting location population:', error);
            return 0;
        }
    }

    removeLocationChip(button) {
        const chip = button.closest('.selected-location-chip');
        if (chip) {
            chip.remove();
        }
        
        // Hide container if no locations
        const container = document.getElementById('selectedLocationsContainer');
        const list = container.querySelector('.selected-locations-list');
        if (list && list.children.length === 0) {
            container.style.display = 'none';
        }
        
        // Recalculate people affected
        this.calculatePeopleAffected();
    }

    async calculatePeopleAffected() {
        const container = document.getElementById('selectedLocationsContainer');
        if (!container) return;

        const chips = container.querySelectorAll('.selected-location-chip');
        let totalPopulation = 0;

        for (const chip of chips) {
            const population = parseInt(chip.getAttribute('data-population')) || 0;
            totalPopulation += population;
        }

        // Use full population sum; fallback to single location text
        let estimate = Math.round(totalPopulation);
        if (!estimate) {
            try {
                const txt = (document.getElementById('hazardLocation')?.value || '').trim();
                if (txt) {
                    const pop = await this.getLocationPopulation(txt);
                    if (pop > 0) estimate = Math.max(1, Math.round(pop));
                }
            } catch (_) {}
        }

        /* 
        // Update the people affected field - Disabled as per user request for manual input
        const peopleAffectedField = document.getElementById('peopleAffected');
        if (peopleAffectedField) {
            peopleAffectedField.value = estimate || 0;
        }
        */
    }

    clearAllSelectedLocations() {
        const container = document.getElementById('selectedLocationsContainer');
        if (!container) return;

        const list = container.querySelector('.selected-locations-list');
        if (list) {
            list.innerHTML = '';
        }
        container.style.display = 'none';
        
        // People affected field is now manual input - do not clear it automatically
    }

    addEditLocationToSelected(locationName, coords, population) {
        // Create location chip for edit modal without showing population
        const chip = document.createElement('div');
        chip.className = 'selected-location-chip';
        chip.innerHTML = `
            <div class="location-chip-content">
                <span class="material-icons location-icon">place</span>
                <span class="location-name">${locationName}</span>
                <button type="button" class="remove-location-btn" onclick="hazardDashboard.removeEditLocationChip(this)">
                    <span class="material-icons">close</span>
                </button>
            </div>
        `;

        // Store population data as a data attribute for calculation
        chip.setAttribute('data-population', population);

        // Add to edit selected locations container
        const container = document.getElementById('edit_selectedLocationsContainer');
        if (container) {
            container.style.display = 'block';
            const list = container.querySelector('.selected-locations-list');
            if (list) {
                list.appendChild(chip);
            }
        }

        // Update people affected calculation for edit modal
        this.calculateEditPeopleAffected();
    }

    removeEditLocationChip(button) {
        const chip = button.closest('.selected-location-chip');
        if (chip) {
            chip.remove();
        }
        
        // Hide container if no locations
        const container = document.getElementById('edit_selectedLocationsContainer');
        const list = container.querySelector('.selected-locations-list');
        if (list && list.children.length === 0) {
            container.style.display = 'none';
        }
        
        // Recalculate people affected for edit modal
        this.calculateEditPeopleAffected();
    }

    calculateEditPeopleAffected() {
        const container = document.getElementById('edit_selectedLocationsContainer');
        if (!container) return;

        const chips = container.querySelectorAll('.selected-location-chip');
        let totalPopulation = 0;

        for (const chip of chips) {
            const population = parseInt(chip.getAttribute('data-population')) || 0;
            totalPopulation += population;
        }

        // Use full population sum for edit
        const estimate = Math.round(totalPopulation);

        /*
        // Update the edit people affected field - Disabled as per user request for manual input
        const peopleAffectedField = document.getElementById('edit_peopleAffected');
        if (peopleAffectedField) {
            peopleAffectedField.value = estimate || 0;
        }
        */
    }

    clearAllEditSelectedLocations() {
        const container = document.getElementById('edit_selectedLocationsContainer');
        if (!container) return;

        const list = container.querySelector('.selected-locations-list');
        if (list) {
            list.innerHTML = '';
        }
        container.style.display = 'none';
        
        // People affected field is now manual input - do not clear it automatically
    }

    addManualPin(latlng) {
        const locationInput = document.getElementById('hazardLocation');
        if (locationInput.value.trim()) {
            // If there's already a location, ask for confirmation
            if (confirm('Replace current location pin with this position?')) {
                this.addLocationPin(latlng, 'Manual Pin');
                this.showNotification('Location pinned manually', 'info');
            }
        } else {
            this.addLocationPin(latlng, 'Manual Pin');
            this.showNotification('Location pinned manually', 'info');
        }
    }

    removeLocationPin() {
        if (this.locationPin) {
            this.map.removeLayer(this.locationPin);
            this.locationPin = null;
            this.showNotification('Location pin removed', 'info');
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span class="material-icons">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>
            <span>${message}</span>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Show notification
        setTimeout(() => notification.classList.add('show'), 100);

        // Remove notification after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Hazards List Methods
    populateHazardsList() {
        const tbody = document.getElementById('hazardsTableBody');
        console.log('populateHazardsList called, tbody:', tbody);
        console.log('filteredHazards:', this.filteredHazards);
        console.log('filteredHazards length:', this.filteredHazards.length);
        
        if (!tbody) {
            console.error('Table body not found!');
            return;
        }

        if (this.filteredHazards.length === 0) {
            console.log('No hazards to display');
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="hazards-empty-state">
                        <span class="material-icons">warning</span>
                        <h3>No Hazards Found</h3>
                        <p>No hazards have been reported yet.</p>
                    </td>
                </tr>
            `;
            this.renderPagination();
            return;
        }

        // Pagination Logic
        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        const paginatedHazards = this.filteredHazards.slice(startIndex, endIndex);

        console.log('Rendering hazards table with', paginatedHazards.length, 'hazards');
        const currentUserId = window.currentUserId || null;
        tbody.innerHTML = paginatedHazards.map(hazard => `
            <tr>
                <td>
                    <span class="hazard-type-badge ${hazard.type}">${this.formatLabel(hazard.type)}</span>
                </td>
                <td>
                    <span class="severity-badge ${hazard.severity}">${this.formatLabel(hazard.severity)}</span>
                </td>
                <td>
                    <span class="status-badge ${hazard.status}">${this.formatLabel(hazard.status)}</span>
                </td>
                <td>${this.formatLabel(hazard.location)}</td>
                <td>${hazard.municipality || 'Unknown Municipality'}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-xs btn-outline-primary" onclick="if(window.hazardDashboard) { window.hazardDashboard.viewHazardDetails('${hazard.id}'); } else { console.error('hazardDashboard not available'); }" title="View Details">
                            <span class="material-icons">visibility</span>
                        </button>
                        ${(
                            (hazard.reportedBy && currentUserId && String(hazard.reportedBy) === String(currentUserId)) ||
                            (hazard.drrmoID && window.userDrrmoId && String(hazard.drrmoID) === String(window.userDrrmoId)) ||
                            (window.currentUserType && ['admin', 'emergency_coordinator'].includes(window.currentUserType))
                        ) ? `
                        <button class="btn btn-xs btn-outline-secondary" onclick="if(window.hazardDashboard) { window.hazardDashboard.editHazard('${hazard.id}'); } else { console.error('hazardDashboard not available'); }" title="Edit">
                            <span class="material-icons">edit</span>
                        </button>
                        <button class="btn btn-xs btn-outline-danger" onclick="if(window.hazardDashboard) { window.hazardDashboard.requestDelete('${hazard.id}'); } else { console.error('hazardDashboard not available'); }" title="Delete">
                            <span class="material-icons">delete</span>
                        </button>
                        `: ''}
                    </div>
                </td>
            </tr>
        `).join('');
        console.log('Table rendered successfully');
        
        this.renderPagination();
    }

    renderPagination() {
        let container = document.getElementById('hazardsPagination');
        if (!container) {
            const tableContainer = document.querySelector('.hazards-table-container');
            if (tableContainer) {
                container = document.createElement('div');
                container.id = 'hazardsPagination';
                container.className = 'haz-pagination-wrap';
                tableContainer.parentNode.insertBefore(container, tableContainer.nextSibling);
            } else { return; }
        }

        const totalPages = Math.ceil(this.filteredHazards.length / this.itemsPerPage);
        const cp = this.currentPage;
        const go = (p) => `if(window.hazardDashboard) window.hazardDashboard.changePage(${p})`;

        if (totalPages <= 1) { container.innerHTML = ''; return; }

        // Sliding window: show up to 5 page numbers
        const windowSize = 5;
        let startPage = Math.max(1, cp - Math.floor(windowSize / 2));
        let endPage = startPage + windowSize - 1;
        if (endPage > totalPages) { endPage = totalPages; startPage = Math.max(1, endPage - windowSize + 1); }

        let items = '';

        // First
        items += `<button class="haz-page-btn ${cp === 1 ? 'disabled' : ''}" onclick="${go(1)}">First</button>`;
        // Prev «
        items += `<button class="haz-page-btn ${cp === 1 ? 'disabled' : ''}" onclick="${go(cp - 1)}">&laquo;</button>`;

        // Page numbers in window
        for (let i = startPage; i <= endPage; i++) {
            items += `<button class="haz-page-btn ${i === cp ? 'haz-page-active' : ''}" onclick="${go(i)}">${i}</button>`;
        }

        // Next »
        items += `<button class="haz-page-btn ${cp === totalPages ? 'disabled' : ''}" onclick="${go(cp + 1)}">&raquo;</button>`;
        // Last
        items += `<button class="haz-page-btn ${cp === totalPages ? 'disabled' : ''}" onclick="${go(totalPages)}">Last</button>`;

        // Info text
        const startItem = (cp - 1) * this.itemsPerPage + 1;
        const endItem = Math.min(cp * this.itemsPerPage, this.filteredHazards.length);
        const info = `<span class="haz-page-info">Showing ${startItem}–${endItem} of ${this.filteredHazards.length}</span>`;

        container.innerHTML = `<div class="haz-pagination">${info}<div class="haz-page-btns">${items}</div></div>`;
    }

    changePage(page) {
        const totalPages = Math.ceil(this.filteredHazards.length / this.itemsPerPage);
        if (page >= 1 && page <= totalPages) {
            this.currentPage = page;
            this.populateHazardsList();
        }
    }

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    viewHazardDetails(hazardId) {
        console.log('viewHazardDetails called with ID:', hazardId);
        const hazard = this.hazards.find(h => String(h.id) === String(hazardId));
        console.log('Found hazard:', hazard);
        if (!hazard) {
            console.error('Hazard not found with ID:', hazardId);
            return;
        }
        const body = document.getElementById('hazardDetailsContent');
        if (!body) {
            console.error('hazardDetailsContent element not found');
            return;
        }

        const status = (hazard.hazardStatus || hazard.status || 'unknown').toLowerCase();
        const severity = (hazard.hazardSeverity || hazard.severity || 'unknown').toLowerCase();
        const type = (hazard.hazardType || hazard.type || 'unknown').toLowerCase();
        const location = hazard.hazardLocation || hazard.location || 'Unknown';
        const dateStr = this.formatDate(hazard.hazardDate || hazard.reportedAt);
        const affected = hazard.peopleAffected || hazard.affected || 0;
        const description = hazard.hazardDescription || hazard.description || '';
        const municipality = hazard.municipality || '';

        // Status config
        const statusCfg = {
            'active':     { color: '#ef4444', bg: '#fef2f2', label: 'Active', icon: 'error' },
            'monitoring': { color: '#f59e0b', bg: '#fffbeb', label: 'Monitoring', icon: 'visibility' },
            'resolved':   { color: '#10b981', bg: '#ecfdf5', label: 'Resolved', icon: 'check_circle' }
        }[status] || { color: '#6b7280', bg: '#f9fafb', label: this.formatLabel(status), icon: 'help' };

        // Severity config
        const sevCfg = {
            'low':      { color: '#10b981', label: 'Low' },
            'medium':   { color: '#f59e0b', label: 'Medium' },
            'high':     { color: '#ef4444', label: 'High' },
            'critical': { color: '#7c3aed', label: 'Critical' }
        }[severity] || { color: '#6b7280', label: this.formatLabel(severity) };

        // Type emoji
        const typeEmoji = {
            'flash-flood': '🌊', 'flood': '🌊', 'earthquake': '🌍', 'typhoon': '🌀',
            'landslide': '🏔️', 'fire': '🔥', 'storm': '⛈️', 'other': '⚠️'
        }[type] || '⚠️';

        // Images HTML
        let imagesHtml = '';
        if (Array.isArray(hazard.images) && hazard.images.length) {
            const imgItems = hazard.images.map(p => {
                const src = (p && typeof p === 'string')
                    ? (p.startsWith('http') ? p : `${this.getBaseUrl()}${p.replace(/^\/+/, '')}`)
                    : '';
                const safeSrc = (src || '').replace(/"/g, '&quot;');
                return `<a href="${safeSrc}" target="_blank" rel="noopener" style="display:block; width:80px; height:80px; border-radius:10px; overflow:hidden; border:1px solid #e5e7eb; flex-shrink:0;">
                    <img src="${safeSrc}" alt="Hazard" style="width:100%;height:100%;object-fit:cover;display:block;">
                </a>`;
            }).join('');
            imagesHtml = `
                <div style="display:flex; gap:8px; flex-wrap:wrap; padding:0 20px 16px;">
                    ${imgItems}
                </div>`;
        }

        body.innerHTML = `
            <!-- Status Header Strip -->
            <div style="background: ${statusCfg.bg}; padding: 14px 20px; display:flex; align-items:center; justify-content:space-between; border-bottom: 2px solid ${statusCfg.color}20;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="material-icons" style="color:${statusCfg.color}; font-size:22px;">${statusCfg.icon}</span>
                    <span style="font-weight:700; font-size:14px; color:${statusCfg.color}; text-transform:uppercase; letter-spacing:0.5px;">${statusCfg.label}</span>
                    <span style="background:${sevCfg.color}; color:#fff; font-size:11px; font-weight:600; padding:2px 10px; border-radius:20px; text-transform:uppercase;">${sevCfg.label}</span>
                </div>
                <button onclick="window.hazardDashboard && window.hazardDashboard.hideHazardDetails()" style="background:none; border:none; cursor:pointer; padding:4px; border-radius:8px; display:flex; align-items:center;" onmouseover="this.style.background='#00000010'" onmouseout="this.style.background='none'">
                    <span class="material-icons" style="color:#6b7280; font-size:22px;">close</span>
                </button>
            </div>

            <!-- Main Content -->
            <div style="padding: 20px;">
                <!-- WHAT: Hazard Type Title -->
                <div style="margin-bottom:20px;">
                    <div style="font-size:28px; margin-bottom:4px;">${typeEmoji}</div>
                    <h2 style="margin:0; font-size:20px; font-weight:700; color:#111827;">${this.formatLabel(type)}</h2>
                    ${municipality ? `<p style="margin:4px 0 0; font-size:13px; color:#6b7280;">${municipality}</p>` : ''}
                </div>

                <!-- Key Info Cards -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px;">
                    <!-- WHERE -->
                    <div style="background:#f8fafc; border-radius:10px; padding:12px 14px; border:1px solid #e2e8f0;">
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                            <span class="material-icons" style="font-size:16px; color:#ef4444;">location_on</span>
                            <span style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.3px;">Where</span>
                        </div>
                        <p style="margin:0; font-size:14px; font-weight:600; color:#1e293b; line-height:1.3;">${location}</p>
                    </div>
                    <!-- WHEN -->
                    <div style="background:#f8fafc; border-radius:10px; padding:12px 14px; border:1px solid #e2e8f0;">
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                            <span class="material-icons" style="font-size:16px; color:#3b82f6;">schedule</span>
                            <span style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.3px;">When</span>
                        </div>
                        <p style="margin:0; font-size:14px; font-weight:600; color:#1e293b; line-height:1.3;">${dateStr}</p>
                    </div>
                    <!-- PEOPLE AFFECTED -->
                    <div style="background:#f8fafc; border-radius:10px; padding:12px 14px; border:1px solid #e2e8f0;">
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                            <span class="material-icons" style="font-size:16px; color:#f59e0b;">people</span>
                            <span style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.3px;">Affected</span>
                        </div>
                        <p style="margin:0; font-size:14px; font-weight:600; color:#1e293b;">${(typeof affected === 'number' || (!isNaN(affected) && affected !== '')) ? Number(affected).toLocaleString() + ' people' : (affected || 'Not specified')}</p>
                    </div>
                    <!-- REPORTED -->
                    <div style="background:#f8fafc; border-radius:10px; padding:12px 14px; border:1px solid #e2e8f0;">
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                            <span class="material-icons" style="font-size:16px; color:#8b5cf6;">person</span>
                            <span style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.3px;">Reported By</span>
                        </div>
                        <p style="margin:0; font-size:14px; font-weight:600; color:#1e293b;">${hazard.reporter || 'Unknown'}</p>
                    </div>
                </div>

                <!-- Description -->
                ${description ? `
                <div style="margin-bottom:16px;">
                    <p style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.3px; margin:0 0 6px;">Details</p>
                    <p style="margin:0; font-size:13px; color:#374151; line-height:1.6; background:#f8fafc; padding:12px; border-radius:10px; border:1px solid #e2e8f0;">${description}</p>
                </div>` : ''}
            </div>

            <!-- Images -->
            ${imagesHtml}
        `;
        
        const modalEl = document.getElementById('hazardDetailsModal');
        if (modalEl) {
            modalEl.classList.add('active');
            modalEl.style.display = 'flex';
        }
        // prevent background scroll while details are open
        document.body.style.overflow = 'hidden';
    }

    hideHazardDetails() {
        const modalEl = document.getElementById('hazardDetailsModal');
        if (modalEl) {
            modalEl.classList.remove('active');
            modalEl.style.display = 'none';
        }
        document.body.style.overflow = '';
    }

    async editHazard(hazardId) {
        console.log('editHazard called with ID:', hazardId);
        const hazard = this.hazards.find(h => String(h.id) === String(hazardId));
        console.log('Found hazard for edit:', hazard);
        if (!hazard) {
            console.error('Hazard not found for edit with ID:', hazardId);
            return;
        }
        // Reuse the Report Hazard modal as the Edit form template
        this.showReportModal();
        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
        // Mark as editing
        const editIdEl = document.getElementById('editHazardId');
        if (editIdEl) editIdEl.value = hazard.id;
        // Update header and submit button
        const headerEl = document.querySelector('#reportHazardModal .title-content h2');
        if (headerEl) headerEl.textContent = 'Edit Hazard';
        const submitBtn = document.querySelector('#reportHazardForm button[type="submit"]');
        if (submitBtn) submitBtn.textContent = 'Save Changes';
        // Populate shared fields
        const typeStr = (hazard.hazardType || hazard.type || '').toString().toLowerCase().replace(/\s+/g,'-');
        setVal('hazardType', typeStr);
        // Trigger change to update otherHazardGroup visibility
        const hazardTypeEl = document.getElementById('hazardType');
        if (hazardTypeEl) {
            hazardTypeEl.dispatchEvent(new Event('change'));
        }
        
        setVal('hazardSeverity', (hazard.hazardSeverity || hazard.severity || 'medium').toString().toLowerCase());
        // Ensure edit status select reflects current status
        const statusGroup = document.getElementById('hazardStatusGroup');
        if (statusGroup) statusGroup.style.display = 'block';
        setVal('hazardStatus', (hazard.status || 'active').toString().toLowerCase());
        setVal('hazardDate', hazard.hazardDate ? new Date(hazard.hazardDate).toISOString().slice(0,16) : (hazard.reportedAt ? new Date(hazard.reportedAt).toISOString().slice(0,16) : ''));
        setVal('hazardDescription', hazard.hazardDescription || hazard.description || '');
        setVal('hazardSource', hazard.hazardSource || hazard.informationSource || '');
        setVal('contactInfo', hazard.contactInfo || '');
        setVal('hazardLocation', hazard.hazardLocation || hazard.location || '');
        setVal('hazardLatitude', Array.isArray(hazard.coordinates)? hazard.coordinates[0] : '');
        setVal('hazardLongitude', Array.isArray(hazard.coordinates)? hazard.coordinates[1] : '');
        setVal('peopleAffected', hazard.peopleAffected || hazard.affected || '');
        // Update coordinate display and selected locations
        this.updateCoordinateDisplay();
        this.clearAllSelectedLocations();
        if (hazard.location) {
            const population = await this.getLocationPopulation(hazard.location);
            this.addLocationToSelectedWithPopulation(hazard.location, [hazard.coordinates?.[0] || 0, hazard.coordinates?.[1] || 0], population);
        }
    }

    requestDelete(hazardId) {
        console.log('requestDelete called with ID:', hazardId);
        this._pendingDeleteId = hazardId;
        const txt = document.getElementById('deleteConfirmText');
        if (txt) txt.textContent = `Are you sure you want to delete hazard #${hazardId}?`;
        const modal = document.getElementById('deleteConfirmModal');
        if (modal) {
            modal.classList.add('active');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    hideDeleteConfirm() {
        const modal = document.getElementById('deleteConfirmModal');
        if (modal) {
            modal.classList.remove('active');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
        this._pendingDeleteId = null;
    }

    async confirmDelete() {
        if (!this._pendingDeleteId) { this.hideDeleteConfirm(); return; }
        const btn = document.getElementById('confirmDeleteBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Deleting...'; }
        try {
            await this.deleteHazard(this._pendingDeleteId);
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Delete'; }
        }
    }

    async deleteHazard(hazardId) {
        console.log('deleteHazard called with ID:', hazardId);
        try {
            const r = await fetch('config/delete_hazard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ hazardId })
            });
            const j = await r.json();
            if (j.success) {
                this.hideDeleteConfirm();
                // Remove from data arrays and re-render instead of reloading
                this.hazards = this.hazards.filter(h => String(h.id) !== String(hazardId));
                this.filteredHazards = this.filteredHazards.filter(h => String(h.id) !== String(hazardId));
                this.populateHazardsList();
                this.showNotification('Hazard deleted successfully', 'success');
            } else {
                this.showNotification(j.error?.message || 'Failed to delete', 'error');
            }
        } catch (e) {
            this.showNotification('Network error', 'error');
        }
    }

    async showLocationSuggestions(query) {
        const dropdown = document.getElementById('locationDropdown');
        if (!dropdown) return;
        
        if (query.length < 1) {
            dropdown.classList.remove('show');
            return;
        }
        
        
        // Show loading indicator
        dropdown.innerHTML = `
            <div class="location-suggestion loading">
                <span class="material-icons">search</span>
                <div class="location-text">
                    <div class="location-name">Searching locations...</div>
                </div>
            </div>
        `;
        dropdown.classList.add('show');
        
        try {
            // Load barangay index data
            const data = await this.loadBarangayIndex();
            const locations = data.locations || [];
            
            // Search through locations
            const queryLower = query.toLowerCase().trim();
            const suggestions = [];
            
            for (const location of locations) {
                const name = location.name || '';
                const displayName = location.display_name || name;
                const searchTerms = location.search_terms || [];
                
                // Check if query matches name, display name, or search terms
                const nameMatch = name.toLowerCase().includes(queryLower);
                const displayMatch = displayName.toLowerCase().includes(queryLower);
                const searchMatch = searchTerms.some(term => term.toLowerCase().includes(queryLower));
                
                if (nameMatch || displayMatch || searchMatch) {
                    // Calculate relevance score
                    let score = 0;
                    
                    // Exact match gets highest score
                    if (name.toLowerCase() === queryLower || displayName.toLowerCase() === queryLower) {
                        score = 100;
                    }
                    // Starts with query gets high score
                    else if (name.toLowerCase().startsWith(queryLower) || displayName.toLowerCase().startsWith(queryLower)) {
                        score = 80;
                    }
                    // Contains query gets medium score
                    else if (nameMatch || displayMatch) {
                        score = 60;
                    }
                    // Search terms match gets lower score
                    else if (searchMatch) {
                        score = 40;
                    }
                    
                    // Boost score for municipalities and cities
                    if (location.type === 'city' || location.type === 'municipality') {
                        score += 20;
                    }
                    
                    // Boost score for higher population
                    if (location.population) {
                        score += Math.min(10, Math.log10(location.population));
                    }
                    
                    suggestions.push({
                        name: displayName,
                        context: this.getLocationContext(location),
                        type: location.type || 'place',
                        coordinates: location.coordinates || [0, 0],
                        population: location.population || 0,
                        score: score
                    });
                }
            }
            
            // Sort by score (highest first), then by name length (shortest first)
            suggestions.sort((a, b) => {
                if (b.score !== a.score) {
                    return b.score - a.score;
                }
                return a.name.length - b.name.length;
            });
            
            // Limit to 5 suggestions
            const finalSuggestions = suggestions.slice(0, 5);
            
            if (finalSuggestions.length > 0) {
                this.displaySuggestions(finalSuggestions, query);
            } else {
                // Show fallback suggestions if no matches found
                const fallbackSuggestions = this.getFallbackSuggestions(query);
                this.displaySuggestions(fallbackSuggestions, query);
            }
            
        } catch (error) {
            console.error('Error fetching location suggestions:', error);
            // Show fallback suggestions on error
            const fallbackSuggestions = this.getFallbackSuggestions(query);
            this.displaySuggestions(fallbackSuggestions, query);
        }
    }

    getLocationContext(location) {
        if (location.type === 'city' || location.type === 'municipality') {
            return 'Zamboanga del Sur';
        } else if (location.municipality) {
            return `${location.municipality}, Zamboanga del Sur`;
            } else {
            return 'Zamboanga del Sur';
        }
    }

    async lookupLocationCoordinates(location) {
        if (!location.trim()) return;
        
        try {
            let coords = null;
            
            // Use OpenStreetMap Nominatim API directly (fast, no local file loading)
            try {
                const south = 6.5, west = 122.0, north = 8.5, east = 124.5;
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(location)}&limit=1&addressdetails=1&countrycodes=ph&viewbox=${west},${north},${east},${south}&bounded=1`);
                const results = await response.json();
                if (results.length > 0) {
                    coords = [parseFloat(results[0].lat), parseFloat(results[0].lon)];
                }
            } catch (nominatimError) {
                console.log('Nominatim coordinates lookup failed:', nominatimError);
            }
            
            
            if (coords) {
                document.getElementById('hazardLatitude').value = coords[0];
                document.getElementById('hazardLongitude').value = coords[1];
                this.updateCoordinateDisplay();
                
                // Center map on found location but preserve zoom level
                this.map.setView([parseFloat(coords[0]), parseFloat(coords[1])], this.map.getZoom());
            }
            return coords;
        } catch (error) {
            console.error('Error looking up coordinates:', error);
            return null;
        }
    }

    updateCoordinateDisplay() {
        const latEl = document.getElementById('hazardLatitude');
        const lngEl = document.getElementById('hazardLongitude');
        const lat = latEl ? latEl.value : '';
        const lng = lngEl ? lngEl.value : '';
        const display = document.getElementById('coordinateDisplay');
        const text = document.getElementById('coordinateText');
        
        if (lat && lng) {
            if (text) text.textContent = `${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)}`;
            if (display) display.style.display = 'block';
        } else {
            if (display) display.style.display = 'none';
        }
    }

    updateActiveSuggestion(items) {
        items.forEach((el, idx) => {
            el.classList.toggle('active', idx === this._locationActiveIndex);
        });
    }

    scrollToActiveItem(items) {
        if (this._locationActiveIndex >= 0 && this._locationActiveIndex < items.length) {
            const activeItem = items[this._locationActiveIndex];
            const dropdown = document.getElementById('locationDropdown');
            
            if (activeItem && dropdown) {
                const itemRect = activeItem.getBoundingClientRect();
                const dropdownRect = dropdown.getBoundingClientRect();
                
                // Scroll to keep active item visible
                if (itemRect.bottom > dropdownRect.bottom) {
                    activeItem.scrollIntoView({ behavior: 'smooth', block: 'end' });
                } else if (itemRect.top < dropdownRect.top) {
                    activeItem.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }
    }


    normalizeSearchTerm(term) {
        if (!term) return '';
        
        return term
            .toLowerCase()
            .replace(/[àáâãäå]/g, 'a')
            .replace(/[èéêë]/g, 'e')
            .replace(/[ìíîï]/g, 'i')
            .replace(/[òóôõö]/g, 'o')
            .replace(/[ùúûü]/g, 'u')
            .replace(/[ñ]/g, 'n')
            .replace(/[ç]/g, 'c')
            .replace(/[^a-z0-9\s]/g, '') // Remove special characters except spaces
            .replace(/\s+/g, ' ') // Normalize spaces
            .trim();
    }

    calculateFlexibleMatch(query, target) {
        if (!query || !target) return 0;
        
        // Simple Levenshtein distance-based similarity
        const distance = this.levenshteinDistance(query, target);
        const maxLength = Math.max(query.length, target.length);
        
        if (maxLength === 0) return 1;
        
        return 1 - (distance / maxLength);
    }

    levenshteinDistance(str1, str2) {
        const matrix = [];
        
        for (let i = 0; i <= str2.length; i++) {
            matrix[i] = [i];
        }
        
        for (let j = 0; j <= str1.length; j++) {
            matrix[0][j] = j;
        }
        
        for (let i = 1; i <= str2.length; i++) {
            for (let j = 1; j <= str1.length; j++) {
                if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
                    matrix[i][j] = matrix[i - 1][j - 1];
                } else {
                    matrix[i][j] = Math.min(
                        matrix[i - 1][j - 1] + 1,
                        matrix[i][j - 1] + 1,
                        matrix[i - 1][j] + 1
                    );
                }
            }
        }
        
        return matrix[str2.length][str1.length];
    }

    addSearchIcons(locationInput) {
        // Create search icons container
        const iconsContainer = document.createElement('div');
        iconsContainer.className = 'search-icons';
        
        // Add search icon
        const searchIcon = document.createElement('span');
        searchIcon.className = 'material-icons search-icon';
        searchIcon.textContent = 'search';
        searchIcon.title = 'Search locations';
        
        // Add navigation icon
        const navIcon = document.createElement('span');
        navIcon.className = 'material-icons nav-icon';
        navIcon.textContent = 'north_east';
        navIcon.title = 'Open in map';
        
        iconsContainer.appendChild(searchIcon);
        iconsContainer.appendChild(navIcon);
        
        // Add to input container
        const inputContainer = locationInput.closest('.location-input-container');
        if (inputContainer) {
            inputContainer.appendChild(iconsContainer);
        }
    }

    getFallbackSuggestions(query) {
        // Always provide fallback suggestions based on popular locations
        // Corrected coordinates and municipalities
        const fallbackLocations = [
            // Zamboanga del Sur locations
            { name: 'Balangasan', context: 'Pagadian City, Zamboanga del Sur', type: 'place', coordinates: [7.8200, 123.4400] },
            { name: 'Pag-asa', context: 'Pagadian City, Zamboanga del Sur', type: 'place', coordinates: [7.8300, 123.4500] },
            { name: 'Poblacion', context: 'Pagadian City, Zamboanga del Sur', type: 'place', coordinates: [7.8258, 123.4370] },
            { name: 'Baliwasan', context: 'Pagadian City, Zamboanga del Sur', type: 'place', coordinates: [7.8100, 123.4200] },
            { name: 'Tumaga', context: 'Pagadian City, Zamboanga del Sur', type: 'place', coordinates: [7.8000, 123.4100] },
            { name: 'Mercedes', context: 'Pagadian City, Zamboanga del Sur', type: 'place', coordinates: [7.8400, 123.4600] },
            { name: 'Sta. Lucia', context: 'Pagadian City, Zamboanga del Sur', type: 'place', coordinates: [7.8300, 123.4300] },
            { name: 'San Jose', context: 'Pagadian City, Zamboanga del Sur', type: 'place', coordinates: [7.8150, 123.4250] },
            { name: 'Pagadian City', context: 'Zamboanga del Sur', type: 'city', coordinates: [7.8258, 123.4370] },
            { name: 'Aurora', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.9500, 123.5833] },
            { name: 'Molave', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.9167, 123.4167] },
            { name: 'Dumingag', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [8.1833, 123.3500] },
            { name: 'San Pablo', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.1167, 122.9000] },
            { name: 'Dimataling', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.5333, 123.3667] },
            { name: 'Dinas', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.6167, 123.3167] },
            { name: 'Dumalinao', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.8167, 123.3667] },
            { name: 'Guipos', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.7167, 123.3167] },
            { name: 'Josefina', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [8.2000, 123.5333] },
            { name: 'Kumalarang', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.7500, 123.1500] },
            { name: 'Labangan', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.8667, 123.5167] },
            { name: 'Lakewood', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.9500, 123.1500] },
            { name: 'Lapuyan', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.6333, 123.2000] },
            { name: 'Mahayag', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [8.1167, 123.4500] },
            { name: 'Margosatubig', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.5667, 123.1667] },
            { name: 'Midsalip', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.9833, 123.2667] },
            { name: 'Pitogo', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.4500, 123.2333] },
            { name: 'Ramon Magsaysay', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [8.0000, 123.5000] },
            { name: 'San Miguel', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.6500, 123.2667] },
            { name: 'Tabina', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.4667, 123.4000] },
            { name: 'Tambulig', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [8.0667, 123.5333] },
            { name: 'Tigbao', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.8333, 123.1667] },
            { name: 'Tukuran', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.8500, 123.5667] },
            { name: 'Vincenzo Sagun', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.5167, 123.1833] },
            { name: 'Bayog', context: 'Zamboanga del Sur', type: 'municipality', coordinates: [7.8500, 123.0500] },
            // Zamboanga City locations (in Zamboanga del Sur)
            { name: 'Tetuan', context: 'Zamboanga City, Zamboanga del Sur', type: 'barangay', coordinates: [6.9214, 122.0790] },
            { name: 'Zamboanga City', context: 'Zamboanga del Sur', type: 'city', coordinates: [6.9214, 122.0790] },
            { name: 'Pasonanca', context: 'Zamboanga City, Zamboanga del Sur', type: 'barangay', coordinates: [6.9000, 122.1000] },
            { name: 'Tumaga', context: 'Zamboanga City, Zamboanga del Sur', type: 'barangay', coordinates: [6.9500, 122.1200] },
            { name: 'Ayala', context: 'Zamboanga City, Zamboanga del Sur', type: 'barangay', coordinates: [6.9200, 122.0800] },
            { name: 'Sta. Maria', context: 'Zamboanga City, Zamboanga del Sur', type: 'barangay', coordinates: [6.9100, 122.0700] },
            { name: 'Sta. Catalina', context: 'Zamboanga City, Zamboanga del Sur', type: 'barangay', coordinates: [6.9300, 122.0900] },
            { name: 'Sta. Barbara', context: 'Zamboanga City, Zamboanga del Sur', type: 'barangay', coordinates: [6.9400, 122.1100] }
        ];
        
        // Filter and score fallback suggestions
        const normalizedQuery = this.normalizeSearchTerm(query.toLowerCase());
        const scored = fallbackLocations.map(location => {
            const normalizedName = this.normalizeSearchTerm(location.name.toLowerCase());
            const normalizedContext = this.normalizeSearchTerm(location.context.toLowerCase());
            
            let score = 0;
            
            // Check if query matches name
            if (normalizedName.includes(normalizedQuery)) {
                score += 5;
            }
            if (normalizedName.startsWith(normalizedQuery)) {
                score += 3;
            }
            
            // Check if query matches context
            if (normalizedContext.includes(normalizedQuery)) {
                score += 2;
            }
            
            // Flexible matching
            const flexibleMatch = this.calculateFlexibleMatch(normalizedQuery, normalizedName);
            if (flexibleMatch > 0.5) {
                score += flexibleMatch * 2;
            }
            
            return { score: Math.max(score, 0.1), location };
        });
        
        // Sort by score and return top results
        scored.sort((a, b) => b.score - a.score);
        return scored.slice(0, 8).map(({location}) => location);
    }

    displaySuggestions(suggestions, query) {
        const dropdown = document.getElementById('locationDropdown');
        if (!dropdown || suggestions.length === 0) {
            dropdown.classList.remove('show');
            return;
        }

        // Enhanced highlighting with better regex handling
        const highlight = (text) => {
            const safe = String(text || '');
            if (!safe || !query) return safe;
            
            try {
                // Escape special regex characters
                const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                // Create regex that matches whole words or parts
                const rx = new RegExp(`(${escapedQuery})`, 'gi');
                return safe.replace(rx, '<mark class="search-highlight">$1</mark>');
            } catch (_) {
                return safe;
            }
        };

        const typeToIcon = (t) => {
            const map = {
                village: 'schedule',
                suburb: 'location_on',
                barangay: 'location_on',
                town: 'location_on',
                city: 'location_on',
                county: 'map',
                hamlet: 'place',
                neighbourhood: 'place',
                place: 'place',
                school: 'school',
                hospital: 'local_hospital',
                church: 'church',
                market: 'store',
                government: 'account_balance'
            };
            return map[(t || '').toLowerCase()] || 'place';
        };

        dropdown.innerHTML = suggestions.map((location, index) => {
            const displayName = location.name || '';
            const subtitle = location.context || '';
            const coords = location.coordinates || [];
            const icon = typeToIcon(location.type);
            const isActive = index === this._locationActiveIndex;
            
            return `<div class="location-suggestion ${isActive ? 'active' : ''}" data-location="${displayName}" data-coords="${coords.join(',')}">
                <span class="material-icons location-icon">${icon}</span>
                <div class="location-text">
                    <div class="location-name">${highlight(displayName)}</div>
                    ${subtitle ? `<div class="location-sub">${highlight(subtitle)}</div>` : ''}
                </div>
            </div>`;
        }).join('');
        
        dropdown.classList.add('show');
        
        // Add click handlers
        dropdown.querySelectorAll('.location-suggestion').forEach((item, index) => {
            item.addEventListener('click', async () => {
                const locationName = item.dataset.location;
                const suggestion = suggestions[index];
                const population = suggestion?.population || 0;

                // Prefer geocoded coordinates for higher accuracy (bounded to Zamboanga del Sur)
                const query = suggestion?.context ? `${suggestion.name}, ${suggestion.context}` : (suggestion?.name || locationName);
                let coords = await this.lookupLocationCoordinates(query);
                if (!coords || !isFinite(coords[0]) || !isFinite(coords[1])) {
                    // Fallback to dataset coordinates in suggestion
                    const raw = (item.dataset.coords || '').split(',');
                    const latF = parseFloat(raw[0]);
                    const lngF = parseFloat(raw[1]);
                    if (isFinite(latF) && isFinite(lngF)) coords = [latF, lngF];
                }

                const lat = coords && isFinite(coords[0]) ? parseFloat(coords[0]) : NaN;
                const lng = coords && isFinite(coords[1]) ? parseFloat(coords[1]) : NaN;

                // Persist selected coordinates to hidden inputs for submission/display
                const latEl = document.getElementById('hazardLatitude');
                const lngEl = document.getElementById('hazardLongitude');
                if (latEl) latEl.value = isFinite(lat) ? lat : '';
                if (lngEl) lngEl.value = isFinite(lng) ? lng : '';

                // Set location input to name only (do not include coordinates)
                const locInput = document.getElementById('hazardLocation');
                if (locInput && locationName) {
                    locInput.value = locationName;
                }
                
                // Add location chip with the final coords
                const chipCoords = (isFinite(lat) && isFinite(lng)) ? [lat, lng] : (coords || []);
                this.addLocationToSelectedWithPopulation(locationName, chipCoords, population);

                this.updateCoordinateDisplay();
                dropdown.classList.remove('show');

                // Recenter map if we have valid coordinates (lookupLocationCoordinates already centers on success)
                if (isFinite(lat) && isFinite(lng) && this.map) {
                    this.map.setView([lat, lng], this.map.getZoom());
                }
            });
        });
        
        // Reset keyboard index when new list rendered
        this._locationActiveIndex = -1;
    }

    addManualPin(latlng) {
        // Update hidden coordinate fields
        document.getElementById('hazardLatitude').value = latlng.lat.toFixed(6);
        document.getElementById('hazardLongitude').value = latlng.lng.toFixed(6);
        
        // Do not modify the location text; only update the coordinate display
        this.updateCoordinateDisplay();
        
        // Add a temporary marker
        const marker = L.marker(latlng, {
            icon: L.divIcon({
                className: 'custom-marker',
                html: '<div class="marker-pin"></div>',
                iconSize: [20, 20],
                iconAnchor: [10, 20]
            })
        }).addTo(this.map);
        
        // Remove the marker after 5 seconds
        setTimeout(() => {
            this.map.removeLayer(marker);
        }, 5000);
        
        this.showNotification('Location pinned! Coordinates: ' + latlng.lat.toFixed(4) + ', ' + latlng.lng.toFixed(4), 'success');
    }

    addMapSearchControl() {
        // Create search control
        const searchControl = L.control({position: 'topright'});
        
        searchControl.onAdd = (map) => {
            const div = L.DomUtil.create('div', 'map-search-control');
            div.innerHTML = `
                <div class="search-control-container">
                    <input type="text" id="mapSearchInput" placeholder="Search location on map..." class="map-search-input">
                    <button id="mapSearchBtn" class="map-search-btn" title="Search">
                        <span class="material-icons">search</span>
                    </button>
                </div>
            `;
            
            // Add search functionality
            const searchInput = div.querySelector('#mapSearchInput');
            const searchBtn = div.querySelector('#mapSearchBtn');
            
            const performSearch = async () => {
                const query = searchInput.value.trim();
                if (!query) return;
                
                try {
                    let coords = null;
                    let label = null;

                    // Use OpenStreetMap Nominatim API directly (fast, no local file loading)
                    try {
                        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ', Zamboanga del Sur, Philippines')}&limit=1&addressdetails=1`);
                        const results = await response.json();
                        if (results.length > 0) {
                            coords = [parseFloat(results[0].lat), parseFloat(results[0].lon)];
                            label = query;
                        }
                    } catch (nominatimError) {
                        console.log('Nominatim geocoding failed:', nominatimError);
                    }


                    if (coords) {
                        const latlng = coords;
                        map.setView(latlng, 15);
                        const marker = L.marker(latlng).addTo(map);
                        marker.bindPopup(`<strong>${label}</strong><br>Coordinates: ${latlng[0]}, ${latlng[1]}`).openPopup();

                        document.getElementById('hazardLocation').value = label;
                        document.getElementById('hazardLatitude').value = latlng[0];
                        document.getElementById('hazardLongitude').value = latlng[1];
                        this.updateCoordinateDisplay();
                        this.showNotification('Location found and set!', 'success');
                    } else {
                        this.showNotification('Location not found. Try a different search term.', 'error');
                    }
                } catch (error) {
                    console.error('Search error:', error);
                    this.showNotification('Search failed. Please try again.', 'error');
                }
            };
            
            searchBtn.addEventListener('click', performSearch);
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });
            
            return div;
        };
        
        searchControl.addTo(this.map);
    }

    filterHazardsList() {
        const searchTerm = document.getElementById('hazardsSearch')?.value.toLowerCase() || '';
        const severityFilter = document.getElementById('hazardsSeverityFilter')?.value || '';
        const statusFilter = document.getElementById('hazardsStatusFilter')?.value || '';

        this.filteredHazards = this.hazards.filter(hazard => {
            const matchesSearch = 
                hazard.title.toLowerCase().includes(searchTerm) ||
                hazard.description.toLowerCase().includes(searchTerm) ||
                hazard.location.toLowerCase().includes(searchTerm);

            const matchesSeverity = !severityFilter || hazard.severity === severityFilter;
            const matchesStatus = !statusFilter || hazard.status === statusFilter;

            return matchesSearch && matchesSeverity && matchesStatus;
        });

        this.populateHazardsList();
    }

    toggleHazardHunterView() {
        try {
            // Open HazardHunterPH in new window
            const newWindow = window.open('https://hazardhunter.georisk.gov.ph/map', '_blank', 'width=1200,height=800');
            if (!newWindow) {
                // Popup blocked, show message
                if (typeof this.showNotification === 'function') {
                    this.showNotification('Popup blocked. Please allow popups for this site to view HazardHunterPH.', 'error');
                } else {
                    alert('Popup blocked. Please allow popups for this site to view HazardHunterPH.');
                }
            }
        } catch (error) {
            console.error('Error opening HazardHunterPH:', error);
            if (typeof this.showNotification === 'function') {
                this.showNotification('Error opening HazardHunterPH map', 'error');
            }
        }
    }

    loadHazardZones() {
        if (!this.map) {
            console.warn('Map not initialized, cannot load hazard zones');
            return;
        }

        // Try to load hazard zones from backend service
        this.fetchHazardZones();
    }

    async fetchHazardZones() {
        try {
            // Get current map bounds
            const bounds = this.map.getBounds();
            const center = this.map.getCenter();
            
            // Call backend service to get hazard assessment
            const response = await fetch(`config/get_hazard_zones.php?lat=${center.lat}&lng=${center.lng}&zoom=${this.map.getZoom()}`);
            
            if (response.ok) {
                const data = await response.json();
                this.addHazardZonesToMap(data);
            } else {
                // Fallback: Add sample hazard zones for Zamboanga del Sur
                this.addSampleHazardZones();
            }
        } catch (error) {
            console.warn('Could not fetch hazard zones, using sample data:', error);
            // Fallback: Add sample hazard zones
            this.addSampleHazardZones();
        }
    }

    addSampleHazardZones() {
        // Add sample flood-prone areas for Zamboanga del Sur
        // These are example zones - replace with actual data when available
        
        // Sample flood-prone area (Pagadian City area)
        const floodZone = L.polygon([
            [7.80, 123.40],
            [7.85, 123.45],
            [7.82, 123.48],
            [7.78, 123.43],
            [7.80, 123.40]
        ], {
            color: '#3b82f6',
            fillColor: '#3b82f6',
            fillOpacity: 0.3,
            weight: 2
        }).addTo(this.map);
        
        floodZone.bindPopup('<strong>Flood-Prone Area</strong><br>High risk zone for flooding during heavy rains');
        this.hazardLayers.flood = floodZone;

        // Sample landslide-prone area
        const landslideZone = L.polygon([
            [7.70, 123.30],
            [7.75, 123.35],
            [7.72, 123.38],
            [7.68, 123.33],
            [7.70, 123.30]
        ], {
            color: '#ef4444',
            fillColor: '#ef4444',
            fillOpacity: 0.3,
            weight: 2
        }).addTo(this.map);
        
        landslideZone.bindPopup('<strong>Landslide-Prone Area</strong><br>High risk zone for landslides during heavy rains');
        this.hazardLayers.landslide = landslideZone;

        // Add layer control (only if not already added)
        if (!this.layerControl) {
            const overlayMaps = {
                "Flood Zones": floodZone,
                "Landslide Zones": landslideZone
            };
            
            this.layerControl = L.control.layers(null, overlayMaps, {
                position: 'topright',
                collapsed: true
            }).addTo(this.map);
            // #region agent log
            // #endregion
        } else {
            // Update existing layer control
            this.layerControl.addOverlay(floodZone, "Flood Zones");
            this.layerControl.addOverlay(landslideZone, "Landslide Zones");
        }

        // Show notification
        if (typeof this.showNotification === 'function') {
            this.showNotification('Hazard zones loaded. Use layer control (top-right) to toggle zones.', 'info');
        }
    }

    addHazardZonesToMap(data) {
        if (!data || !data.zones) {
            this.addSampleHazardZones();
            return;
        }

        const overlayMaps = {};
        
        // Process each hazard type
        if (data.zones.flood && data.zones.flood.length > 0) {
            const floodGroup = L.layerGroup();
            data.zones.flood.forEach(zone => {
                if (zone.coordinates && zone.coordinates.length > 0) {
                    const polygon = L.polygon(zone.coordinates, {
                        color: '#3b82f6',
                        fillColor: '#3b82f6',
                        fillOpacity: 0.3,
                        weight: 2
                    });
                    polygon.bindPopup(`<strong>Flood-Prone Area</strong><br>${zone.description || 'High risk zone for flooding'}`);
                    floodGroup.addLayer(polygon);
                }
            });
            floodGroup.addTo(this.map);
            overlayMaps["Flood Zones"] = floodGroup;
            this.hazardLayers.flood = floodGroup;
        }

        if (data.zones.landslide && data.zones.landslide.length > 0) {
            const landslideGroup = L.layerGroup();
            data.zones.landslide.forEach(zone => {
                if (zone.coordinates && zone.coordinates.length > 0) {
                    const polygon = L.polygon(zone.coordinates, {
                        color: '#ef4444',
                        fillColor: '#ef4444',
                        fillOpacity: 0.3,
                        weight: 2
                    });
                    polygon.bindPopup(`<strong>Landslide-Prone Area</strong><br>${zone.description || 'High risk zone for landslides'}`);
                    landslideGroup.addLayer(polygon);
                }
            });
            landslideGroup.addTo(this.map);
            overlayMaps["Landslide Zones"] = landslideGroup;
            this.hazardLayers.landslide = landslideGroup;
        }

        // Add layer control if we have zones (only if not already added)
        if (Object.keys(overlayMaps).length > 0) {
            if (!this.layerControl) {
                this.layerControl = L.control.layers(null, overlayMaps, {
                    position: 'topright',
                    collapsed: true
                }).addTo(this.map);
                // #region agent log
                // #endregion
            } else {
                // Add overlays to existing control
                Object.keys(overlayMaps).forEach(name => {
                    this.layerControl.addOverlay(overlayMaps[name], name);
                });
            }
        }
    }

    _firstGeoJSONCoord(geojson) {
        if (!geojson) return null;
        const features = geojson.features || (geojson.geometries ? geojson.geometries.map(g => ({ geometry: g })) : []);
        const first = features[0];
        if (!first || !first.geometry) return null;
        const g = first.geometry;
        if (g.coordinates && g.coordinates.length) {
            const c = g.type === 'Point' ? g.coordinates : (g.type === 'MultiPolygon' ? g.coordinates[0]?.[0]?.[0] : g.coordinates[0]?.[0]);
            return c && c.length >= 2 ? c : null;
        }
        return null;
    }

    /** Transform GeoJSON coordinates from EPSG:3857 (Web Mercator) to WGS84. Modifies in place. No proj4 dependency. */
    _reprojectGeoJSON3857To4326(geojson) {
        const R = 6378137;
        const MAX_EXTENT = 20037508.34;
        const transform = (coord) => {
            if (Array.isArray(coord[0])) {
                coord.forEach(transform);
                return;
            }
            if (coord.length >= 2 && typeof coord[0] === 'number' && typeof coord[1] === 'number') {
                const x = coord[0];
                const y = coord[1];
                coord[0] = (x / MAX_EXTENT) * 180;
                coord[1] = (2 * Math.atan(Math.exp(y / R)) - Math.PI / 2) * (180 / Math.PI);
            }
        };
        const features = geojson.features || (geojson.geometries ? geojson.geometries.map(g => ({ geometry: g })) : []);
        features.forEach(f => {
            if (f.geometry && f.geometry.coordinates) transform(f.geometry.coordinates);
        });
        return geojson;
    }

    async loadGeoJSONHazardLayers() {
        if (!this.map) {
            console.warn('Map not initialized, cannot load GeoJSON hazard layers');
            return;
        }
        // #region agent log
        // #endregion

        const hazardConfigs = [
            { 
                key: 'flood50', 
                file: 'ready_flood50.json', 
                name: 'Flood Hazard (Zamboanga del Sur)',
                color: '#3b82f6',  // Blue
                description: 'Flood hazard 1:50,000 – Zamboanga del Sur'
            },
            { 
                key: 'rainInducedLandslide', 
                file: 'ready_raininducedlandslide.json', 
                name: 'Rain-Induced Landslide Hazard (Zamboanga del Sur)',
                color: '#b45309',  // Brown/amber
                description: 'Rain-induced landslide hazard 1:50,000 – Zamboanga del Sur (MGB)'
            }
        ];

        const overlayMaps = {};

        for (const config of hazardConfigs) {
            try {
                const response = await fetch(`${this.getBaseUrl()}config/data/${config.file}`);
                if (!response.ok) {
                    console.warn(`Could not load ${config.file}: ${response.status} ${response.statusText}`);
                    continue;
                }
                // #region agent log
                const t0 = Date.now();
                // #endregion

                const geojson = await response.json();
                
                // Validate GeoJSON structure
                if (!geojson || !geojson.type) {
                    console.warn(`Invalid GeoJSON format in ${config.file}`);
                    continue;
                }
                const featureCount = (geojson.features && geojson.features.length) || (geojson.geometries && geojson.geometries.length) || 0;
                // #region agent log
                // #endregion

                // Leaflet expects WGS84 (lng -180..180, lat -90..90). Zamboanga del Sur is ~lng 122-124, lat 6.5-8.5.
                const firstCoord = this._firstGeoJSONCoord(geojson);
                const looksProjected = firstCoord && (Math.abs(firstCoord[0]) > 180 || Math.abs(firstCoord[1]) > 90);
                if (firstCoord && looksProjected) {
                    console.warn(`${config.file} uses projected coordinates (not WGS84). Re-export as GeoJSON with CRS WGS 84 (EPSG:4326) in QGIS so layers appear on the map.`);
                }
                // #region agent log
                const mapCenter = this.map.getCenter();
                const mapBounds = this.map.getBounds();
                // #endregion

                // Reproject from Web Mercator (EPSG:3857) to WGS84 so layers show on the map (Zamboanga del Sur ~122–124°E, 6.5–8.5°N).
                if (looksProjected) {
                    this._reprojectGeoJSON3857To4326(geojson);
                }
                const coordAfter = this._firstGeoJSONCoord(geojson);
                // #region agent log
                // #endregion

                const layer = L.geoJSON(geojson, {
                    style: (feature) => {
                        const props = feature?.properties || {};
                        const rating = (props.rating || props.RATING || '').toLowerCase();
                        let color = config.color;
                        let fillColor = config.color;
                        if (rating.includes('high') || rating.includes('critical')) {
                            color = '#dc2626';
                            fillColor = '#dc2626';
                        } else if (rating.includes('medium') || rating.includes('moderate')) {
                            color = '#ea580c';
                            fillColor = '#ea580c';
                        } else if (rating.includes('low')) {
                            color = '#16a34a';
                            fillColor = '#16a34a';
                        }
                        return {
                            color: color,
                            fillColor: fillColor,
                            fillOpacity: 0.35,
                            weight: 2
                        };
                    },
                    onEachFeature: (feature, layer) => {
                        const props = feature.properties || {};
                        const rating = props.rating || props.RATING || '';
                        const popupContent = rating ? `${rating}<br>${props.name || props.description || config.description}` : (props.name || props.description || config.description);
                        layer.bindPopup(`<strong>${config.name}</strong><br>${popupContent}`);
                    }
                });
                
                this.hazardLayers[config.key] = layer;
                overlayMaps[config.name] = layer;
                // #region agent log
                let layerBoundsStr = null;
                try { const b = layer.getBounds(); if (b && b.isValid()) layerBoundsStr = [[b.getSouthWest().lat,b.getSouthWest().lng],[b.getNorthEast().lat,b.getNorthEast().lng]]; } catch (e) { layerBoundsStr = 'none'; }
                // #endregion

                console.log(`Loaded ${config.name} successfully`);
            } catch (error) {
                console.warn(`Error loading ${config.file}:`, error);
            }
        }

        // Add layers to layer control
        const overlayKeys = Object.keys(overlayMaps);
        // #region agent log
        // #endregion

        if (overlayKeys.length > 0) {
            if (!this.layerControl) {
                // If layer control doesn't exist yet, create it
                this.layerControl = L.control.layers(null, overlayMaps, {
                    position: 'topright',
                    collapsed: true
                }).addTo(this.map);
                // #region agent log
                // #endregion
            } else {
                // Add overlays to existing control
                Object.keys(overlayMaps).forEach(name => {
                    this.layerControl.addOverlay(overlayMaps[name], name);
                });
                // #region agent log
                // #endregion
            }
        }
    }

    addMapLegend() {
        if (!this.map) {
            return;
        }

        if (!document.getElementById('hazardMapLegendStyles')) {
            const style = document.createElement('style');
            style.id = 'hazardMapLegendStyles';
            style.textContent = `
                .hazard-map-legend {
                    background: rgba(31, 41, 55, 0.7);
                    backdrop-filter: blur(4px);
                    -webkit-backdrop-filter: blur(4px);
                    border-radius: 6px;
                    padding: 6px 8px;
                    font-size: 11px;
                    color: #e5e7eb;
                    max-width: 200px;
                    line-height: 1.3;
                    border: 1px solid rgba(15, 23, 42, 0.7);
                }
                .hazard-map-legend h4 {
                    margin: 0 0 4px 0;
                    font-size: 11px;
                    font-weight: 600;
                    color: #f9fafb;
                }
                .hazard-map-legend-row {
                    display: flex;
                    align-items: center;
                    margin-bottom: 2px;
                }
                .hazard-map-legend-row:last-child {
                    margin-bottom: 0;
                }
                .hazard-map-legend-swatch {
                    width: 10px;
                    height: 10px;
                    border-radius: 9999px;
                    margin-right: 6px;
                    border: 1px solid rgba(15, 23, 42, 0.9);
                    box-sizing: border-box;
                }
                .hazard-map-legend-note {
                    margin-top: 4px;
                    font-size: 10px;
                    color: #9ca3af;
                }
            `;
            (document.head || document.documentElement).appendChild(style);
        }

        if (this.legendControl) {
            try {
                this.legendControl.remove();
            } catch (_) {}
            this.legendControl = null;
        }

        const legend = L.control({ position: 'bottomright' });

        legend.onAdd = () => {
            const div = L.DomUtil.create('div', 'hazard-map-legend leaflet-control');

            const entries = [
                { color: '#16a34a', label: 'Low hazard' },
                { color: '#ea580c', label: 'Medium / Moderate hazard' },
                { color: '#dc2626', label: 'High / Critical hazard' },
                { color: '#3b82f6', label: 'Flood hazard zones' },
                { color: '#b45309', label: 'Rain-induced landslide zones' }
            ];

            const title = document.createElement('h4');
            title.textContent = 'Map Legend';
            div.appendChild(title);

            entries.forEach(entry => {
                const row = document.createElement('div');
                row.className = 'hazard-map-legend-row';

                const swatch = document.createElement('span');
                swatch.className = 'hazard-map-legend-swatch';
                swatch.style.backgroundColor = entry.color;

                const label = document.createElement('span');
                label.textContent = entry.label;

                row.appendChild(swatch);
                row.appendChild(label);
                div.appendChild(row);
            });

            const note = document.createElement('div');
            note.className = 'hazard-map-legend-note';
            note.textContent = 'Colors match hazard severity and zone layers.';
            div.appendChild(note);

            return div;
        };

        this.legendControl = legend;
        this.legendControl.addTo(this.map);
    }
}

// ---- Population auto-calculation wiring ----
let __barangayIndexCache = null;
async function loadBarangayIndex() {
    if (__barangayIndexCache) return __barangayIndexCache;
    const res = await fetch('config/data/barangay_index.json', { cache: 'no-store' });
    try {
        __barangayIndexCache = await res.json();
    } catch (_) {
        __barangayIndexCache = { locations: [] };
    }
    return __barangayIndexCache;
}

function findPlacePopulation(indexData, placeName) {
    if (!placeName) return 0;
    const list = Array.isArray(indexData.locations) ? indexData.locations : (Array.isArray(indexData) ? indexData : []);
    const n = String(placeName).toLowerCase();
    const hit = list.find(x => {
        const a = String(x.name || '').toLowerCase();
        const b = String(x.display_name || '').toLowerCase();
        return a === n || b === n;
    });
    const pop = hit && (hit.population || hit.pop || hit.total_population);
    return pop ? Number(pop) : 0;
}

async function recalcPeopleAffectedFor(containerId, targetInputId) {
    const idx = await loadBarangayIndex();
    const cont = document.getElementById(containerId);
    if (!cont) return;
    // Collect selected location labels (assumes chips inside .selected-locations-list with text)
    const chips = cont.querySelectorAll('.selected-locations-list .selected-location-item .location-name');
    const names = Array.from(chips).map(el => el.textContent.trim()).filter(Boolean);
    if (names.length === 0) {
        const out = document.getElementById(targetInputId);
        if (out) out.value = '';
        return;
    }
    const total = names.reduce((sum, nm) => sum + findPlacePopulation(idx, nm), 0);
    const out = document.getElementById(targetInputId);
    if (out) out.value = String(total);
}

function setupPopulationAutoCalc() {
    // Create modal
    const createContId = 'selectedLocationsContainer';
    const createTarget = 'peopleAffected';
    const createCont = document.getElementById(createContId);
    if (createCont) {
        const observer = new MutationObserver(() => recalcPeopleAffectedFor(createContId, createTarget));
        observer.observe(createCont, { childList: true, subtree: true, characterData: true });
    }
    // Edit modal
    const editContId = 'edit_selectedLocationsContainer';
    const editTarget = 'edit_peopleAffected';
    const editCont = document.getElementById(editContId);
    if (editCont) {
        const observer2 = new MutationObserver(() => recalcPeopleAffectedFor(editContId, editTarget));
        observer2.observe(editCont, { childList: true, subtree: true, characterData: true });
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.hazardDashboard = new HazardDashboard();
    console.log('HazardDashboard initialized:', window.hazardDashboard);
    console.log('hazardDashboard methods available:', Object.getOwnPropertyNames(Object.getPrototypeOf(window.hazardDashboard)));
    // Initialize population auto-calc observers after dashboard init
    try { setupPopulationAutoCalc(); } catch (_) {}
});

// Add notification styles
const notificationStyles = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        gap: 12px;
        z-index: 1001;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 400px;
    }

    .notification.show {
        transform: translateX(0);
    }

    .notification-success {
        border-left: 4px solid #10b981;
    }

    .notification-error {
        border-left: 4px solid #ef4444;
    }

    .notification-info {
        border-left: 4px solid #3b82f6;
    }

    .notification .material-icons {
        font-size: 20px;
    }

    .notification-success .material-icons {
        color: #10b981;
    }

    .notification-error .material-icons {
        color: #ef4444;
    }

    .notification-info .material-icons {
        color: #3b82f6;
    }

    .no-hazards {
        text-align: center;
        padding: 40px 20px;
        color: #64748b;
    }

    .no-hazards h3 {
        margin: 0 0 8px 0;
        color: #475569;
    }

    .no-hazards p {
        margin: 0;
    }

    .map-popup {
        min-width: 250px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
        -webkit-font-smoothing: antialiased !important;
        -moz-osx-font-smoothing: grayscale !important;
        text-rendering: optimizeLegibility !important;
    }

    .map-popup h3 {
        margin: 0 0 12px 0;
        font-size: 1.1rem;
        font-weight: 600 !important;
        color: #1e293b !important;
        -webkit-text-stroke: 0px !important;
        text-stroke: 0px !important;
        -webkit-font-smoothing: antialiased !important;
        -moz-osx-font-smoothing: grayscale !important;
    }

    .map-popup p {
        margin: 8px 0;
        font-size: 0.95rem;
        font-weight: 400 !important;
        color: #64748b !important;
        line-height: 1.4;
        -webkit-text-stroke: 0px !important;
        text-stroke: 0px !important;
        -webkit-font-smoothing: antialiased !important;
        -moz-osx-font-smoothing: grayscale !important;
    }

    .map-popup .btn {
        margin-top: 12px;
        padding: 8px 16px;
        font-size: 0.9rem;
        -webkit-font-smoothing: antialiased !important;
        -moz-osx-font-smoothing: grayscale !important;
    }

    .severity-high {
        color: #ef4444;
        font-weight: 600;
    }

    .severity-medium {
        color: #f59e0b;
        font-weight: 600;
    }

    .severity-low {
        color: #10b981;
        font-weight: 600;
    }

    .location-popup {
        min-width: 200px;
        text-align: center;
    }

    .location-popup h4 {
        margin: 0 0 8px 0;
        color: #1e293b;
        font-size: 1rem;
    }

    .location-popup p {
        margin: 0 0 12px 0;
        color: #64748b;
        font-size: 0.85rem;
    }

    .location-popup .btn {
        font-size: 0.8rem;
        padding: 6px 12px;
    }
`;

// Add styles to document
const styleSheet = document.createElement('style');
styleSheet.textContent = notificationStyles;
document.head.appendChild(styleSheet);

