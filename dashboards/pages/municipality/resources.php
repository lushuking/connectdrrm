<?php
// Resources page with real data
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/db.php';

// Get user's DRRMO ID
$drrmoID = $_SESSION['municipality_id'] ?? null;

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo "<div class='alert alert-warning'>Please log in to view resources.</div>";
    return;
}

// Fetch r eal resources data
$resources = [];
$totalResources = 0;

try {
    // Get all DRRMOs and their resources
    $sql = "
        SELECT 
            d.drrmoID,
            d.name as drrmoName,
            d.location,
            COUNT(r.resourceID) as totalItems,
            COUNT(DISTINCT r.category) as resourceTypes
        FROM drrmo d
        LEFT JOIN resources r ON d.drrmoID = r.drrmoID
        GROUP BY d.drrmoID, d.name, d.location
        ORDER BY d.name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $drrmoData = $stmt->fetchAll();
    
    // Get subcategories for dropdown
    $subcatStmt = $pdo->query('SELECT DISTINCT subcategory FROM resources WHERE subcategory IS NOT NULL AND subcategory != "" ORDER BY subcategory');
    $subcategories = $subcatStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get distinct main categories count
    $catStmt = $pdo->query('SELECT COUNT(DISTINCT category) as cat_count FROM resources WHERE category IS NOT NULL AND category != ""');
    $categoryCount = (int)$catStmt->fetchColumn();
    
    // Transform to municipalities data structure
    $municipalities = [];
    $totalResources = 0;
    
    foreach ($drrmoData as $drrmo) {
        $isOwn = ($drrmo['drrmoID'] == $drrmoID);
        $municipalities[] = [
            'id' => $drrmo['drrmoID'],
            'name' => $drrmo['drrmoName'],
            'totalItems' => (int)$drrmo['totalItems'],
            'resourceTypes' => (int)$drrmo['resourceTypes'],
            'lastUpdated' => date('Y-m-d H:i:s'),
            'isOwn' => $isOwn
        ];
        $totalResources += (int)$drrmo['totalItems'];
    }
    
    // Get all resources for all municipalities
    $allResources = [];
    $sql = "
        SELECT 
            r.*,
            d.name as drrmoName
        FROM resources r
        JOIN drrmo d ON r.drrmoID = d.drrmoID
        ORDER BY d.name, r.resourceName
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rawResources = $stmt->fetchAll();
    
    foreach ($rawResources as $resource) {
        $allResources[] = [
            'id' => $resource['resourceID'],
            'drrmoID' => $resource['drrmoID'],
            'drrmoName' => $resource['drrmoName'],
            'name' => $resource['resourceName'],
            'resourceName' => $resource['resourceName'],
            'type' => $resource['subcategory'] ?: $resource['category'], // Use subcategory if available, fallback to category
            'category' => $resource['category'],
            'subcategory' => $resource['subcategory'],
            'quantity' => $resource['availableStock'], // For display
            'minQuantity' => $resource['minimumStock'], // For display
            'totalStock' => $resource['totalStock'] ?? 0, // For editing
            'availableStock' => $resource['availableStock'], // For editing
            'minimumStock' => $resource['minimumStock'] ?? 0, // For editing
            'unit' => $resource['unit'] ?? '', // For editing
            'description' => $resource['description'],
            'storageLocation' => $resource['storageLocation'] ?? null, // For editing
            'lastUpdated' => $resource['updatedAt'] ?? date('Y-m-d H:i:s')
        ];
    }
    
    // Get resources for the current user's DRRMO (for detailed view)
    $resources = array_filter($allResources, function($resource) use ($drrmoID) {
        return $resource['drrmoID'] == $drrmoID;
    });
    
} catch (Exception $e) {
    error_log('Error fetching resources: ' . $e->getMessage());
}
?>

<script>
// Pass resources data to JavaScript
window.resourcesData = <?php echo json_encode($municipalities); ?>;
window.resourcesList = <?php echo json_encode($resources); ?>;
window.allResources = <?php echo json_encode($allResources); ?>;
window.totalResources = <?php echo $totalResources; ?>;
window.userMunicipalityId = <?php echo json_encode($drrmoID); ?>;
window.availableSubcategories = <?php echo json_encode($subcategories ?? []); ?>;
</script>

<!-- resources.php -->
<div class="resources-page">
    <!-- Main Resources Overview -->
    <div id="resourcesOverview">

        <!-- Premium Header Banner -->
        <div class="card border-0 rounded-4 shadow-sm mb-4 overflow-hidden" style="background: linear-gradient(135deg, #1A3D63 0%, #2d6a9f 60%, #4A7FA7 100%);">
            <div class="card-body p-4">
                <div class="row align-items-center g-3">
                    <!-- Title + Stats -->
                    <div class="col-md-7">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:48px;height:48px;">
                                <span class="material-icons text-white" style="font-size:26px;">inventory_2</span>
                            </div>
                            <div>
                                <h5 class="text-white fw-bold mb-0">Resource Management</h5>
                                <p class="text-white text-opacity-75 small mb-0">Monitor and manage all DRRM resources across municipalities</p>
                            </div>
                        </div>
                        <div class="d-flex gap-3 mt-3">
                            <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 text-center">
                                <div class="text-white fw-bold fs-4" id="totalResourcesCount"><?php echo $totalResources; ?></div>
                                <div class="text-white text-opacity-75" style="font-size:0.72rem;letter-spacing:0.5px;">TOTAL ITEMS</div>
                            </div>
                            <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 text-center">
                                <div class="text-white fw-bold fs-4"><?php echo count($municipalities); ?></div>
                                <div class="text-white text-opacity-75" style="font-size:0.72rem;letter-spacing:0.5px;">MUNICIPALITIES</div>
                            </div>
                            <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 text-center">
                                <div class="text-white fw-bold fs-4"><?php echo $categoryCount; ?></div>
                                <div class="text-white text-opacity-75" style="font-size:0.72rem;letter-spacing:0.5px;">RESOURCE TYPES</div>
                            </div>
                        </div>
                    </div>
                    <!-- Actions -->
                    <div class="col-md-5">
                        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-md-end">
                            <div class="input-group rounded-pill overflow-hidden shadow-sm" style="max-width:260px;">
                                <span class="input-group-text bg-white border-0 ps-3">
                                    <span class="material-icons text-muted" style="font-size:18px;">search</span>
                                </span>
                                <input type="text" class="form-control border-0 py-2" id="municipalitySearch" placeholder="Search municipalities..." style="font-size:0.9rem;">
                            </div>
                            <button class="btn bg-white text-primary fw-semibold rounded-pill px-4 shadow-sm d-flex align-items-center gap-1" onclick="manageMyResources()" style="white-space:nowrap;">
                                <span class="material-icons" style="font-size:18px;">edit_note</span>
                                Manage My Resources
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Municipalities Grid -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center px-4 py-3">
                        <div class="dropdown-container">
                            <select class="form-select view-select-as-title" id="resourcesViewSelect">
                                <option value="all">All Resources</option>
                                <option value="overview">Municipalities Overview</option>
                            </select>
                        </div>
                        <div></div>
                    </div>
                    <div class="card-body p-3">
                        <div id="municipalitiesGrid">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading municipalities...</p>
                            </div>
                        </div>
                        <div class="d-flex justify-content-center mt-3" id="municipalitiesPagination"></div>

                        <!-- All Resources Container (toggles with dropdown) -->
                        <div id="allResourcesContainer" style="display: none; padding-top: 0; margin-top: -1rem;">
                            <div class="resources-controls">
                                <div class="controls-left">
                                    <div class="search-box">
                                        <span class="material-icons" aria-hidden="true">search</span>
                                        <input type="text" id="allResSearch" placeholder="Search municipality or resource..." style="width: 400px;" aria-label="Search resources by municipality or resource name">
                                    </div>
                                </div>
                                <div class="controls-right">
                                    <div class="filter-group">
                                        <select id="allResTypeFilter" class="filter-select" aria-label="Filter by resource subcategory">
                                            <option value="">All Subcategories</option>
                                            <?php foreach ($subcategories as $subcat): ?>
                                                <?php if (!str_starts_with($subcat, 'Unmapped')): ?>
                                                    <option value="<?= htmlspecialchars($subcat) ?>"><?= htmlspecialchars($subcat) ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <select id="allResStatusFilter" class="filter-select" aria-label="Filter by resource availability status">
                                            <option value="">All Status</option>
                                            <option value="available">Available</option>
                                            <option value="unavailable">Unavailable</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <select id="allResSort" class="filter-select" aria-label="Sort resources">
                                            <option value="">Sort: Default</option>
                                            <option value="name_asc">Name A–Z</option>
                                            <option value="name_desc">Name Z–A</option>
                                            <option value="municipality_asc">Municipality A–Z</option>
                                            <option value="category_asc">Category A–Z</option>
                                            <option value="qty_desc">Quantity (High → Low)</option>
                                            <option value="qty_asc">Quantity (Low → High)</option>
                                            <option value="status">Availability (Available first)</option>
                                            <option value="updated_desc">Recently Updated</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover resources-table" id="allResourcesTable">
                                    <thead>
                                        <tr>
                                            <th>Resource</th>
                                            <th>Municipality</th>
                                            <th>Subcategory</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Last Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody id="allResourcesTableBody">
                                        <tr>
                                            <td colspan="6" class="loading-row">
                                                <div class="loading">Loading resources...</div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <nav aria-label="All resources pagination" class="mt-3">
                                <ul id="allResourcesPagination" class="pagination pagination-sm justify-content-center mb-0"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Detailed Resources View -->
    <div class="resources-detail" id="resourcesDetail" style="display: none;">

        <!-- Premium Detail Header -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
            <div class="card-body p-0">
                <!-- Top gradient bar -->
                <div class="px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-3" style="background: linear-gradient(135deg, #1A3D63 0%, #2d6a9f 100%);">
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-sm btn-detail-back border-0 rounded-pill px-3 d-flex align-items-center gap-1" onclick="backToOverview()">
                            <span class="material-icons" style="font-size:16px;">arrow_back</span>
                            <span style="font-size:0.85rem;">Back</span>
                        </button>
                        <div class="vr bg-white bg-opacity-25" style="height:28px;"></div>
                        <div>
                            <h6 class="text-white fw-bold mb-0" id="municipalityName">Municipality Name</h6>
                            <p class="text-white text-opacity-75 mb-0" style="font-size:0.78rem;" id="municipalityDescription">Resource inventory</p>
                        </div>
                    </div>
                    <div id="detailActions" class="d-flex gap-2">
                        <!-- Actions populated by JS -->
                    </div>
                </div>
                <!-- Controls bar -->
                <div class="px-4 py-3 bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="search-box">
                        <span class="material-icons" aria-hidden="true">search</span>
                        <input type="text" id="resourceSearch" placeholder="Search resources..." aria-label="Search resources by name or description">
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <select id="resourceSort" class="filter-select" aria-label="Sort resources">
                            <option value="">Sort: Default</option>
                            <option value="name_asc">Name A–Z</option>
                            <option value="name_desc">Name Z–A</option>
                            <option value="qty_desc">Quantity (High → Low)</option>
                            <option value="qty_asc">Quantity (Low → High)</option>
                            <option value="status">Availability (Available first)</option>
                            <option value="updated_desc">Recently Updated</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resources Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover resources-table" id="resourcesTable">
                <thead>
                    <tr>
                        <th>Resource Name</th>
                        <th>Subcategory</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody id="resourcesTableBody">
                    <tr>
                        <td colspan="6" class="loading-row">
                            <div class="loading">Loading resources...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Resource Modal -->
<div class="modal" id="resourceModal" style="display: none;">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <div class="modal-title">
                <span class="material-icons">inventory</span>
                <div>
                    <h2 id="modalTitle">Add New Resource</h2>
                    <p id="modalSubtitle">Complete the form below to add a new resource to your inventory</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeResourceModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <form id="resourceForm">
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <span class="material-icons">info</span>
                        Basic Information
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="resourceName">
                                <span class="material-icons">label</span>
                                Resource Name
                            </label>
                            <input type="text" id="resourceName" name="name" placeholder="Enter resource name" required>
                            <small class="form-help">Enter a clear, descriptive name for the resource</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="resourceCategory">
                                <span class="material-icons">category</span>
                                Resource Category
                            </label>
                            <select id="resourceCategory" name="category" required onchange="handleCategoryChange()">
                                <option value="">Select Category</option>
                                <option value="Early Warning System">Early Warning System</option>
                                <option value="Heavy Equipment">Heavy Equipment</option>
                                <option value="Rescue Boat">Rescue Boat</option>
                                <option value="Transport/Rescue">Transport/Rescue</option>
                                <option value="add_new_category">+ Add New Category</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="resourceSubcategory">
                                <span class="material-icons">subdirectory_arrow_right</span>
                                Subcategory
                            </label>
                            <select id="resourceSubcategory" name="subcategory" onchange="handleSubcategoryChange()">
                                <option value="">Select Subcategory</option>
                                <option value="Ambulance/PTV">Ambulance/PTV</option>
                                <option value="Backhoe">Backhoe</option>
                                <option value="Earthquake Intensity Meter">Earthquake Intensity Meter</option>
                                <option value="Evacuation Center">Evacuation Center</option>
                                <option value="Loader">Loader</option>
                                <option value="Rain Gauge">Rain Gauge</option>
                                <option value="Rescue Truck">Rescue Truck</option>
                                <option value="Rescue Vehicle">Rescue Vehicle</option>
                                <option value="Rubber Boat">Rubber Boat</option>
                                <option value="Sea Craft">Sea Craft</option>
                                <option value="Self-Loading">Self-Loading</option>
                                <option value="Tsunami Alerting Station">Tsunami Alerting Station</option>
                                <option value="Water Truck">Water Truck</option>
                                <option value="add_new_subcategory">+ Add New Subcategory</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="resourceDescription">
                            <span class="material-icons">description</span>
                            Description
                        </label>
                        <textarea id="resourceDescription" name="description" rows="3" placeholder="Provide detailed description of the resource, including specifications, condition, and usage notes..."></textarea>
                        <small class="form-help">Include any important details about the resource</small>
                    </div>
                </div>

                <!-- Inventory Management Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <span class="material-icons">inventory_2</span>
                        Inventory Management
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="totalStock">
                                <span class="material-icons">inventory</span>
                                Total Stock
                            </label>
                            <input type="number" id="totalStock" name="totalStock" min="0" placeholder="0" required>
                            <small class="form-help">Total quantity of this resource in inventory</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="availableStock">
                                <span class="material-icons">check_circle</span>
                                Available Stock
                            </label>
                            <input type="number" id="availableStock" name="availableStock" min="0" placeholder="0" required>
                            <small class="form-help">Currently available quantity for use</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="minimumStock">
                                <span class="material-icons">warning</span>
                                Minimum Stock Level
                            </label>
                            <input type="number" id="minimumStock" name="minimumStock" min="0" placeholder="0" value="0">
                            <small class="form-help">Alert when stock falls below this level</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="resourceUnit">
                                <span class="material-icons">straighten</span>
                                Unit of Measurement
                            </label>
                            <select id="resourceUnit" name="unit" required>
                                <option value="">Select Unit</option>
                                <option value="pieces">Pieces</option>
                                <option value="boxes">Boxes</option>
                                <option value="kits">Kits</option>
                                <option value="liters">Liters</option>
                                <option value="kilograms">Kilograms</option>
                                <option value="meters">Meters</option>
                                <option value="sets">Sets</option>
                                <option value="units">Units</option>
                                <option value="packs">Packs</option>
                                <option value="bottles">Bottles</option>
                                <option value="trucks">Trucks</option>
                                <option value="boats">Boats</option>
                                <option value="stations">Stations</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Location & Storage Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <span class="material-icons">location_on</span>
                        Storage Information
                    </h3>
                    
                    <div class="form-group">
                        <label for="storageLocation">
                            <span class="material-icons">warehouse</span>
                            Storage Location
                        </label>
                        <input type="text" id="storageLocation" name="storageLocation" placeholder="e.g., Warehouse A, Room 101, Central Storage">
                        <small class="form-help">Where is this resource stored?</small>
                    </div>
                </div>


            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeResourceModal()">
                <span class="material-icons">close</span>
                Cancel
            </button>
            <button type="button" class="btn btn-primary" onclick="saveResource()" id="saveButtonText">
                <span class="material-icons">add_circle</span>
                Add Resource
            </button>
        </div>
    </div>
    </div>
</div>

<!-- Add New Category Modal -->
<div class="modal" id="addCategoryModal" style="display: none;">
    <div class="modal-content modal-small">
        <div class="modal-header">
            <div class="modal-title">
                <span class="material-icons">add_circle</span>
                <div>
                    <h2>Add New Category</h2>
                    <p>Please input new category name</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeAddCategoryModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="newCategoryName">
                    <span class="material-icons">label</span>
                    Category Name
                </label>
                <input type="text" id="newCategoryName" placeholder="Enter new category name" required>
                <small class="form-help">Enter a clear, descriptive name for the new category</small>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary btn-sm" onclick="saveNewCategory()">
                <span class="material-icons">save</span>
                Save Category
            </button>
        </div>
    </div>
</div>

<!-- Add New Subcategory Modal -->
<div class="modal" id="addSubcategoryModal" style="display: none;">
    <div class="modal-content modal-small">
        <div class="modal-header">
            <div class="modal-title">
                <span class="material-icons">add_circle</span>
                <div>
                    <h2>Add New Subcategory</h2>
                    <p>Please input new subcategory name</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeAddSubcategoryModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="newSubcategoryName">
                    <span class="material-icons">label</span>
                    Subcategory Name
                </label>
                <input type="text" id="newSubcategoryName" placeholder="Enter new subcategory name" required>
                <small class="form-help">Enter a clear, descriptive name for the new subcategory</small>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary btn-sm" onclick="saveNewSubcategory()">
                <span class="material-icons">save</span>
                Save Subcategory
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal" style="display: none;">
    <div class="modal-content modal-small">
        <div class="modal-header">
            <div class="modal-title">
                <span class="material-icons">warning</span>
                <div>
                    <h2>Confirm Deletion</h2>
                    <p>This action cannot be undone</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeDeleteModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <div style="text-align: center; padding: 20px 0;">
                <div style="font-size: 48px; color: #ef4444; margin-bottom: 16px;">⚠️</div>
                <h3 style="margin: 0 0 12px 0; color: #1e293b;">Delete Resource</h3>
                <p style="margin: 0; color: #64748b; line-height: 1.5;">
                    Are you sure you want to delete <strong id="deleteResourceName">this resource</strong>? 
                    This action will permanently remove it from your inventory and cannot be undone.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                <span class="material-icons">arrow_back</span>
                Keep Resource
            </button>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                <span class="material-icons">delete_forever</span>
                Delete Permanently
            </button>
        </div>
    </div>
</div>