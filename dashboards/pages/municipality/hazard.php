<?php
// Hazard Information System Dashboard
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/db.php';

// Get user's DRRMO ID
$drrmoID = $_SESSION['municipality_id'] ?? null;
$userMunicipalityName = null;

// Fetch real hazard data
$hazards = [];
$activeHazards = 0;
$criticalAlerts = 0;
$peopleAffected = 0;

// Show all hazards globally for all users
try {
    // Get hazards from the last 3 years from all municipalities
    $sql = "SELECT h.*, d.name as municipalityName,
                   (SELECT GROUP_CONCAT(filePath SEPARATOR '||')
                    FROM hazard_images hi
                    WHERE hi.hazardID = h.hazardID) AS imagePaths
            FROM hazards h
            LEFT JOIN drrmo d ON h.drrmoID = d.drrmoID
            WHERE h.reportedAt >= DATE_SUB(NOW(), INTERVAL 3 YEAR)
            ORDER BY h.reportedAt DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rawHazards = $stmt->fetchAll();
    
    // Fetch user's municipality name if logged in
    if ($drrmoID) {
        $stmtName = $pdo->prepare("SELECT name FROM drrmo WHERE drrmoID = ? LIMIT 1");
        $stmtName->execute([$drrmoID]);
        $rowName = $stmtName->fetch();
        if ($rowName && !empty($rowName['name'])) {
            $userMunicipalityName = $rowName['name'];
        }
    }
} catch (Exception $e) {
    error_log('Error fetching hazards: ' . $e->getMessage());
}

// Build population index from barangay_index.json for fallback display
$populationIndex = [];
try {
    $idxPath = __DIR__ . '/../../../config/data/barangay_index.json';
    if (is_readable($idxPath)) {
        $json = json_decode(file_get_contents($idxPath), true);
        foreach (($json['locations'] ?? []) as $loc) {
            $pop = isset($loc['population']) ? (int)$loc['population'] : 0;
            if ($pop <= 0) continue;
            $keys = [];
            if (!empty($loc['display_name'])) $keys[] = strtolower($loc['display_name']);
            if (!empty($loc['name'])) {
                $keys[] = strtolower($loc['name']);
                if (!empty($loc['municipality'])) {
                    $keys[] = strtolower($loc['name'] . ', ' . $loc['municipality']);
                }
            }
            foreach ($keys as $k) {
                // Keep the max pop for duplicate keys
                $populationIndex[$k] = isset($populationIndex[$k]) ? max($populationIndex[$k], $pop) : $pop;
            }
        }
    }
} catch (Exception $e) { /* ignore */ }

if (!function_exists('computePopulationSumFromLocation')) {
    function computePopulationSumFromLocation($location, $populationIndex) {
        if (!$location) return 0;
        $sum = 0;
        // split by comma/semicolon
        $parts = preg_split('/[;,]+/', $location);
        foreach ($parts as $p) {
            $token = strtolower(trim($p));
            if ($token === '') continue;
            if (isset($populationIndex[$token])) {
                $sum += (int)$populationIndex[$token];
                continue;
            }
            // Try without the word 'barangay'
            $token2 = preg_replace('/\bbarangay\b\s*/i', '', $token);
            if (isset($populationIndex[$token2])) {
                $sum += (int)$populationIndex[$token2];
            }
        }
        return $sum;
    }
}

if (isset($rawHazards)) {
    // Transform data to match JavaScript expectations
    $hazards = [];
    foreach ($rawHazards as $hazard) {
        $images = [];
        if (!empty($hazard['imagePaths'])) {
            $images = array_values(array_filter(explode('||', (string)$hazard['imagePaths'])));
        }
        $affectedVal = isset($hazard['affectedPopulation']) ? (int)$hazard['affectedPopulation'] : 0;
        if ($affectedVal <= 0) {
            $affectedVal = computePopulationSumFromLocation($hazard['location'] ?? '', $populationIndex);
        }
        $hazards[] = [
            'id' => $hazard['hazardID'] ?? 'N/A',
            'title' => ($hazard['hazardType'] ?? 'Unknown') . ' - ' . ($hazard['location'] ?? 'Unknown Location'),
            'type' => strtolower(str_replace(' ', '-', $hazard['hazardType'] ?? 'unknown')),
            'severity' => strtolower($hazard['intensity'] ?? 'medium'),
            'status' => strtolower($hazard['status'] ?? 'active'),
            'location' => $hazard['location'] ?? 'Unknown Location',
            'description' => $hazard['description'] ?? 'No description available',
            'coordinates' => [
                floatval($hazard['latitude'] ?? 0), 
                floatval($hazard['longitude'] ?? 0)
            ],
            'affected' => $affectedVal,
            'reportedAt' => $hazard['reportedAt'] ?? date('Y-m-d H:i:s'),
            'reportedBy' => isset($hazard['reportedBy']) ? (int)$hazard['reportedBy'] : null,
            'reporter' => isset($hazard['reportedBy']) && $hazard['reportedBy'] ? 'User ' . $hazard['reportedBy'] : 'Unknown',
            'municipality' => $hazard['municipalityName'] ?? 'Unknown Municipality',
            'images' => $images,
            'resolvedAt' => $hazard['resolvedAt'] ?? null,
            'drrmoID' => isset($hazard['drrmoID']) ? (int)$hazard['drrmoID'] : null
        ];
    }
    
    // Calculate metrics
    $activeHazards = count(array_filter($hazards, function($h) { return $h['status'] === 'active'; }));
    $criticalAlerts = count(array_filter($hazards, function($h) { return $h['severity'] === 'critical'; }));
    $peopleAffected = array_sum(array_column($hazards, 'affected'));
}
?>

<script>
// Pass hazard data to JavaScript
window.hazardData = <?php echo json_encode($hazards); ?>;
window.userMunicipality = <?php echo json_encode($userMunicipalityName); ?>;
window.currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
window.userDrrmoId = <?php echo json_encode($_SESSION['municipality_id'] ?? null); ?>;
window.currentUserType = <?php echo json_encode($_SESSION['user_type'] ?? null); ?>;

// Debug: Log the hazard data
console.log('Hazard data passed to JavaScript:', window.hazardData);
console.log('Number of hazards:', window.hazardData ? window.hazardData.length : 0);
console.log('Current user ID:', window.currentUserId);
</script>

<div class="hazard-dashboard">
    <!-- Premium Title Bar -->
    <div class="hazard-page-title-bar">
        <div class="title-left">
            <div class="title-icon">
                <span class="material-icons">warning</span>
            </div>
            <div>
                <h1>Hazard Information System</h1>
                <p>Real-time monitoring &amp; reporting for Zamboanga del Sur</p>
            </div>
        </div>
        <button class="btn btn-report-hazard" id="reportHazardBtn">
            <span class="material-icons">add</span>
            Report Hazard
        </button>
    </div>

    <!-- Metrics Cards -->
    <div class="hazard-metrics">
        <div class="metric-card">
            <div class="metric-icon">
                <span class="material-icons">warning</span>
            </div>
            <div class="metric-content">
                <h3>Active Hazards</h3>
                <span class="metric-number" id="activeHazards"><?php echo $activeHazards; ?></span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon">
                <span class="material-icons">people</span>
            </div>
            <div class="metric-content">
                <h3>Population Affected</h3>
                <span class="metric-number" id="peopleAffected"><?php echo number_format($peopleAffected); ?></span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon">
                <span class="material-icons">priority_high</span>
            </div>
            <div class="metric-content">
                <h3>Critical Alerts</h3>
                <span class="metric-number" id="criticalAlerts"><?php echo $criticalAlerts; ?></span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon">
                <span class="material-icons">visibility</span>
            </div>
            <div class="metric-content">
                <h3>Under Monitor</h3>
                <span class="metric-number" id="underMonitor">2</span>
            </div>
        </div>
    </div><!-- /.hazard-metrics -->

    <!-- Search and Filter Bar -->

    <div class="hazard-controls">
        <div class="search-container">
            <div class="search-input">
                <span class="material-icons">search</span>
                <input type="text" placeholder="Search Hazard" id="hazardSearch">
            </div>
        </div>
        
        <div class="filter-container">
            <select class="filter-select" id="severityFilter">
                <option value="">Severity</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>
            
            <select class="filter-select" id="typeFilter">
                <option value="">Types</option>
                <option value="flash-flood">Flash Flood</option>
                <option value="earthquake">Earthquake</option>
                <option value="typhoon">Typhoon</option>
                <option value="landslide">Landslide</option>
                <option value="fire">Fire</option>
            </select>
            
            <select class="filter-select" id="statusFilter">
                <option value="">Status</option>
                <option value="active">Active</option>
                <option value="monitoring">Monitoring</option>
                <option value="resolved">Resolved</option>
                <option value="false-alarm">False Alarm</option>
            </select>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="hazard-content">
        <!-- Map Section -->
        <div class="hazard-map-section">
            <div class="hazard-map-header">
                <h6 class="hazard-map-title">Hazards Map</h6>
                <div class="btn-group" role="group">
                    <button class="btn btn-primary btn-sm" id="toggleMapView" title="Toggle Map View">
                        <span class="material-icons me-1">map</span>
                        Show
                    </button>
                </div>
            </div>
            <div id="hazardMap" class="hazard-map" style="height: 500px; position: relative;"></div>
        </div>

        <!-- Hazards List Panel -->
        <div class="hazards-list-section">
            <div class="hazards-list-header">
                <h6 class="hazards-list-title">Hazards List</h6>
                <div class="btn-group" role="group">
                    <button class="btn btn-outline-primary btn-sm" id="refreshHazardsList" title="Refresh List">
                        <span class="material-icons">refresh</span>
                    </button>
                    <button class="btn btn-primary btn-sm" id="reportHazardBtn" title="Report New Hazard">
                        <span class="material-icons me-1">add</span>
                        Report Hazard
                    </button>
                </div>
            </div>
            
            <div class="hazards-list-container">
                <div class="hazards-list-filters">
                    <div class="filter-group">
                        <input type="text" class="form-control" id="hazardsSearch" placeholder="Search hazards...">
                    </div>
                    <div class="filter-group">
                        <select class="form-select" id="hazardsSeverityFilter">
                            <option value="">All Severity</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <select class="form-select" id="hazardsStatusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="resolved">Resolved</option>
                            <option value="monitoring">Monitoring</option>
                        </select>
                    </div>
                </div>
                
                <div class="hazards-table-container table-responsive requests-page">
                    <table class="table table-striped table-hover align-middle mb-0 resources-table hazards-table">
                        <thead>
                            <tr>
                                <th>Hazard Type</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>Municipality</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="hazardsTableBody">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Report Hazard Modal -->
<div class="modal" id="reportHazardModal">
    <div class="modal-content modal-large">
        <!-- Enhanced Header with Gradient -->
        <div class="modal-header hazard-modal-header">
            <div class="modal-title">
                <div class="hazard-icon-container">
                    <span class="material-icons hazard-icon">warning</span>
                </div>
                <div class="title-content">
                    <h2>Report New Hazard</h2>
                    <p class="modal-subtitle">Emergency hazard reporting system</p>
                </div>
            </div>
            <button class="modal-close" id="closeReportModal">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <div class="modal-body">
            <form id="reportHazardForm" enctype="multipart/form-data">
                <input type="hidden" id="editHazardId" name="editHazardId" value="">
                <!-- Priority Section -->
                <div class="form-section priority-section">
                    <div class="section-header">
                        <span class="material-icons">priority_high</span>
                        <h3>Hazard Classification</h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group enhanced-form-group">
                            <label for="hazardType" class="enhanced-label">
                                <span class="material-icons">category</span>
                                <span class="label-text">Hazard Type</span>
                                <span class="required-indicator">*</span>
                            </label>
                            <div class="select-wrapper">
                                <select id="hazardType" name="hazardType" required class="enhanced-select" onchange="document.getElementById('otherHazardGroup').style.display = this.value === 'other' ? 'block' : 'none'; document.getElementById('otherHazardType').required = this.value === 'other';">
                                    <option value="">Choose hazard type...</option>
                                    <option value="flash-flood">🌊 Flash Flood</option>
                                    <option value="earthquake">🌍 Earthquake</option>
                                    <option value="typhoon">🌀 Typhoon</option>
                                    <option value="landslide">🏔️ Landslide</option>
                                    <option value="fire">🔥 Fire</option>
                                    <option value="other">⚠️ Other</option>
                                </select>
                                <span class="select-arrow material-icons">keyboard_arrow_down</span>
                            </div>
                        </div>

                        <div class="form-group enhanced-form-group" id="otherHazardGroup" style="display: none; flex: 1 1 100%;">
                            <label for="otherHazardType" class="enhanced-label">
                                <span class="material-icons">edit</span>
                                <span class="label-text">Specify Other Hazard</span>
                                <span class="required-indicator">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="otherHazardType" name="otherHazardType" 
                                       placeholder="Enter the hazard type..." 
                                       class="enhanced-input">
                                <span class="input-icon material-icons">edit</span>
                            </div>
                        </div>
                        
                        <div class="form-group enhanced-form-group">
                            <label for="hazardSeverity" class="enhanced-label">
                                <span class="material-icons">priority_high</span>
                                <span class="label-text">Severity Level</span>
                                <span class="required-indicator">*</span>
                            </label>
                            <div class="select-wrapper">
                                <select id="hazardSeverity" name="hazardSeverity" required class="enhanced-select">
                                    <option value="">Choose severity...</option>
                                    <option value="low">🟢 Low - Minor impact</option>
                                    <option value="medium">🟡 Medium - Moderate impact</option>
                                    <option value="high">🟠 High - Significant impact</option>
                                    <option value="critical">🔴 Critical - Severe impact</option>
                                </select>
                                <span class="select-arrow material-icons">keyboard_arrow_down</span>
                            </div>
                        </div>

                        <div class="form-group enhanced-form-group" id="hazardStatusGroup" style="display: none;">
                            <label for="hazardStatus" class="enhanced-label">
                                <span class="material-icons">lens</span>
                                <span class="label-text">Current Status</span>
                                <span class="required-indicator">*</span>
                            </label>
                            <div class="select-wrapper">
                                <select id="hazardStatus" name="hazardStatus" class="enhanced-select">
                                    <option value="active">🔴 Active</option>
                                    <option value="monitoring">🟡 Monitoring</option>
                                    <option value="resolved">🟢 Resolved</option>
                                </select>
                                <span class="select-arrow material-icons">keyboard_arrow_down</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Location Section -->
                <div class="form-section location-section">
                    <div class="section-header">
                        <span class="material-icons">location_on</span>
                        <h3>Affected Locations</h3>
                        <span class="section-badge">Multiple Selection</span>
                    </div>
                    
                    <div class="form-group enhanced-form-group">
                        <label for="hazardLocation" class="enhanced-label">
                            <span class="material-icons">search</span>
                            <span class="label-text">Search & Select Locations</span>
                            <span class="required-indicator">*</span>
                        </label>
                        <div class="location-input-container enhanced-input-container">
                            <input type="text" id="hazardLocation" name="hazardLocation" 
                                   placeholder="Type to search barangays and municipalities..." 
                                   autocomplete="off" class="enhanced-input">
                            <div class="input-hint">
                                <span class="material-icons">lightbulb</span>
                                <span>Tip: Select multiple locations for hazards affecting multiple areas</span>
                            </div>
                            
                            <!-- Hidden fields for coordinates -->
                            <input type="hidden" id="hazardLatitude" name="hazardLatitude">
                            <input type="hidden" id="hazardLongitude" name="hazardLongitude">
                            
                            <!-- Location suggestions dropdown -->
                            <div id="locationDropdown" class="location-dropdown"></div>
                            
                            <!-- Coordinate display -->
                            <div class="coordinate-display" id="coordinateDisplay" style="display: none;">
                                <div class="coordinate-info">
                                    <span class="material-icons">my_location</span>
                                    <span class="coordinate-label">Center Point:</span>
                                    <span id="coordinateText">Not set</span>
                                </div>
                                <button type="button" class="btn-clear-coords" id="clearCoordinates" title="Clear all locations">
                                    <span class="material-icons">clear</span>
                                    <span>Clear All</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Selected locations display -->
                        <div id="selectedLocationsContainer" class="selected-locations-container" style="display: none;">
                            <div class="selected-locations-header">
                                <div class="header-content">
                                    <span class="material-icons">place</span>
                                    <span class="header-text">Selected Locations</span>
                                    <span class="location-count">0</span>
                                </div>
                            </div>
                            <div class="selected-locations-list"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Impact Section -->
                <div class="form-section impact-section">
                    <div class="section-header">
                        <span class="material-icons">assessment</span>
                        <h3>Impact Assessment</h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group enhanced-form-group">
                            <label for="hazardDate" class="enhanced-label">
                                <span class="material-icons">event</span>
                                <span class="label-text">Date of Occurrence</span>
                                <span class="required-indicator">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="datetime-local" id="hazardDate" name="hazardDate" required class="enhanced-input">
                                <span class="input-icon material-icons">schedule</span>
                            </div>
                        </div>
                        
                        <div class="form-group enhanced-form-group">
                            <label for="peopleAffected" class="enhanced-label">
                                <span class="material-icons">people</span>
                                <span class="label-text">People Affected</span>
                                <span class="required-indicator">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="number" id="peopleAffected" name="peopleAffected" 
                                       placeholder="Enter total people affected..." 
                                       min="0" class="enhanced-input" required>
                                <span class="input-icon material-icons">people</span>
                            </div>
                            <div class="input-hint">
                                <span class="material-icons">info</span>
                                <span>Please provide an estimate of the total population affected by this hazard.</span>
                            </div>
                            <small id="populationHelper" class="text-muted" style="display: none;"></small>
                        </div>
                    </div>
                </div>
                
                <!-- Details Section -->
                <div class="form-section details-section">
                    <div class="section-header">
                        <span class="material-icons">description</span>
                        <h3>Additional Information</h3>
                    </div>


                    <div class="form-row">
                        <div class="form-group enhanced-form-group">
                            <label for="hazardSource" class="enhanced-label">
                                <span class="material-icons">source</span>
                                <span class="label-text">Information Source</span>
                                <span class="required-indicator">*</span>
                            </label>
                            <div class="select-wrapper">
                                <select id="hazardSource" name="hazardSource" required class="enhanced-select" onchange="document.getElementById('otherSourceGroup').style.display = this.value === 'other' ? 'block' : 'none'; document.getElementById('otherHazardSource').required = this.value === 'other';">
                                    <option value="">Choose information source...</option>
                                    <option value="direct-observation">👁️ Direct Observation</option>
                                    <option value="citizen-report">👤 Citizen Report</option>
                                    <option value="government-agency">🏛️ Government Agency</option>
                                    <option value="media-report">📺 Media Report</option>
                                    <option value="satellite-data">🛰️ Satellite Data</option>
                                    <option value="other">📋 Other</option>
                                </select>
                                <span class="select-arrow material-icons">keyboard_arrow_down</span>
                            </div>
                        </div>
                        
                        <div class="form-group enhanced-form-group">
                            <label for="contactInfo" class="enhanced-label">
                                <span class="material-icons">contact_phone</span>
                                <span class="label-text">Contact Information</span>
                                <span class="optional-badge">Optional</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="contactInfo" name="contactInfo" 
                                       placeholder="Your contact number or email for follow-up" 
                                       class="enhanced-input">
                                <span class="input-icon material-icons">phone</span>
                            </div>
                        </div>

                        <div class="form-group enhanced-form-group" id="otherSourceGroup" style="display: none; flex: 1 1 100%;">
                            <label for="otherHazardSource" class="enhanced-label">
                                <span class="material-icons">edit</span>
                                <span class="label-text">Specify Other Source</span>
                                <span class="required-indicator">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="otherHazardSource" name="otherHazardSource" 
                                       placeholder="Enter the information source..." 
                                       class="enhanced-input">
                                <span class="input-icon material-icons">edit</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attachments Section -->
                <div class="form-section details-section">
                    <div class="section-header">
                        <span class="material-icons">photo_camera</span>
                        <h3>Images</h3>
                        <span class="section-badge">Optional</span>
                    </div>
                    <div class="form-group enhanced-form-group">
                        <label for="hazardImages" class="enhanced-label">
                            <span class="material-icons">image</span>
                            <span class="label-text">Upload images (max 5)</span>
                            <span class="optional-badge">Optional</span>
                        </label>
                        <div class="input-wrapper">
                            <input
                                type="file"
                                id="hazardImages"
                                name="hazardImages[]"
                                accept="image/*"
                                multiple
                                class="enhanced-input"
                            >
                        </div>
                        <div class="input-hint">
                            <span class="material-icons">info</span>
                            <span>Accepted: JPG/PNG/WebP. Up to 5 images, 5MB each.</span>
                        </div>
                        <div id="hazardImagesPreview" style="display:none; margin-top: 10px; gap: 10px; flex-wrap: wrap;"></div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <div class="note-field">
                        <textarea name="note" placeholder="Add a note (optional)" rows="3"></textarea>
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-cancel" id="cancelReport">Cancel</button>
                        <button type="submit" class="btn btn-confirm">Confirm</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hazard Details Modal -->
<div class="modal" id="hazardDetailsModal" style="z-index: 10002;">
    <div class="modal-content" style="z-index: 10003; max-width: 560px; border-radius: 16px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.18); border: none;">
        <div id="hazardDetailsContent">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Edit Hazard Modal -->
<div class="modal" id="editHazardModal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2>Edit Hazard</h2>
            <button class="modal-close" onclick="window.hazardDashboard && window.hazardDashboard.hideEditHazard()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <form id="editHazardForm">
                <input type="hidden" id="edit_hazardId" name="hazardId">
                
                <!-- Hazard Type -->
                <div class="form-group">
                    <label for="edit_hazardType">Hazard Type *</label>
                    <select id="edit_hazardType" name="hazardType" required onchange="document.getElementById('edit_otherHazardGroup').style.display = this.value === 'other' ? 'block' : 'none'; document.getElementById('edit_otherHazardType').required = this.value === 'other';">
                        <option value="flood">Flood</option>
                        <option value="earthquake">Earthquake</option>
                        <option value="fire">Fire</option>
                        <option value="landslide">Landslide</option>
                        <option value="storm">Storm</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <!-- Severity -->
                <div class="form-group">
                    <label for="edit_hazardSeverity">Severity *</label>
                    <select id="edit_hazardSeverity" name="hazardSeverity" required>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                
                <!-- Status -->
                <div class="form-group">
                    <label for="edit_hazardStatus">Current Status *</label>
                    <select id="edit_hazardStatus" name="hazardStatus" required>
                        <option value="active">Active</option>
                        <option value="monitoring">Monitoring</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>
                
                <!-- Location -->
                <div class="form-group">
                    <label for="edit_hazardLocation">Location *</label>
                    <input type="text" id="edit_hazardLocation" name="hazardLocation" required>
                </div>
                
                <!-- Coordinates -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_hazardLatitude">Latitude</label>
                        <input type="number" id="edit_hazardLatitude" name="hazardLatitude" step="any">
                    </div>
                    <div class="form-group">
                        <label for="edit_hazardLongitude">Longitude</label>
                        <input type="number" id="edit_hazardLongitude" name="hazardLongitude" step="any">
                    </div>
                </div>
                
                <!-- Date -->
                <div class="form-group">
                    <label for="edit_hazardDate">Date & Time</label>
                    <input type="datetime-local" id="edit_hazardDate" name="hazardDate">
                </div>
                
                <!-- People Affected -->
                <div class="form-group">
                    <label for="edit_peopleAffected">People Affected</label>
                    <input type="number" id="edit_peopleAffected" name="peopleAffected" min="0">
                </div>
                
                <!-- Other Hazard Type -->
                <div class="form-group" id="edit_otherHazardGroup" style="display: none;">
                    <label for="edit_otherHazardType">Specify Other Hazard *</label>
                    <input type="text" id="edit_otherHazardType" name="otherHazardType">
                </div>
                

                
                <!-- Information Source -->
                <div class="form-group">
                    <label for="edit_hazardSource">Information Source</label>
                    <input type="text" id="edit_hazardSource" name="hazardSource">
                </div>
                
                <!-- Contact Info -->
                <div class="form-group">
                    <label for="edit_contactInfo">Contact Information</label>
                    <input type="text" id="edit_contactInfo" name="contactInfo">
                </div>
                
                <!-- Action Buttons -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.hazardDashboard && window.hazardDashboard.hideEditHazard()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div><!-- /.hazard-dashboard -->

<!-- Delete Confirm Modal -->
<div class="modal" id="deleteConfirmModal">
    <div class="modal-content modal-confirm">
        <div class="modal-header">
            <div class="modal-title">
                <span class="material-icons" aria-hidden="true">warning</span>
                <div class="title-content">
                    <h2>Delete Hazard</h2>
                    <p class="modal-subtitle">This action cannot be undone</p>
                </div>
            </div>
            <button class="modal-close" id="closeDeleteConfirm">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <p id="deleteConfirmText">Are you sure you want to delete this hazard?</p>
            <div class="form-actions">
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>
</div>
