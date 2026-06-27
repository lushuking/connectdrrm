<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $action = $_GET['action'] ?? 'overview';
    $userRole = $_SESSION['user_type'] ?? '';
    $municipalityID = $_SESSION['municipality_id'] ?? null;

    switch ($action) {
        case 'overview':
        case 'municipality_overview':
            getMunicipalityOverview();
            break;
        case 'unified':
            getUnifiedMunicipalityReport();
            break;
        case 'my_resources':
            getMyResourcesReport();
            break;
        case 'borrowed_resources':
            getBorrowedResourcesReport();
            break;
        case 'my_requests':
            getMyRequestsReport();
            break;
        case 'my_hazards':
            getMyHazardsReport();
            break;
        case 'my_performance':
            getMyPerformanceReport();
            break;
        case 'resource_utilization':
            getResourceUtilizationReport();
            break;
        case 'emergency_preparedness':
            getEmergencyPreparednessReport();
            break;
        case 'monthly_summary':
            getMonthlySummaryReport();
            break;
        case 'pdrrmo_analytics':
            getPdrrmoAnalytics();
            break;
        case 'pdrrmo_request_frequency':
            getPdrrmoRequestFrequency();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function getMunicipalityOverview() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        if (!$municipalityID) {
            throw new Exception('Municipality ID not found');
        }

        // Get municipality name
        $municipalityStmt = $pdo->prepare("SELECT name FROM drrmo WHERE drrmoID = ?");
        $municipalityStmt->execute([$municipalityID]);
        $municipalityName = $municipalityStmt->fetch(PDO::FETCH_ASSOC)['name'];

        // Get my municipality's resources
        $resourceStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as totalResources,
                SUM(availableStock) as totalStock,
                COUNT(CASE WHEN availableStock <= 10 THEN 1 END) as lowStockCount,
                COUNT(DISTINCT category) as resourceCategories
            FROM resources
            WHERE drrmoID = ?
        ");
        $resourceStmt->execute([$municipalityID]);
        $resourceStats = $resourceStmt->fetch(PDO::FETCH_ASSOC);

        // Get my requests (requests I made to others)
        $myRequestsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as totalRequests,
                COUNT(CASE WHEN LOWER(status) IN ('pending', 'pending_head_approval') THEN 1 END) as pendingRequests,
                COUNT(CASE WHEN LOWER(status) IN ('approved', 'group_approved_pending') THEN 1 END) as approvedRequests,
                COUNT(CASE WHEN LOWER(status) = 'fulfilled' THEN 1 END) as fulfilledRequests
            FROM requests
            WHERE fromDRRMO = ?
        ");
        $myRequestsStmt->execute([$municipalityID]);
        $myRequestStats = $myRequestsStmt->fetch(PDO::FETCH_ASSOC);

        // Get requests made to me (others borrowing from me)
        $borrowedFromMeStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as totalBorrowed,
                COUNT(CASE WHEN LOWER(status) IN ('pending', 'pending_head_approval') THEN 1 END) as pendingBorrowed,
                COUNT(CASE WHEN LOWER(status) IN ('approved', 'group_approved_pending') THEN 1 END) as approvedBorrowed,
                COUNT(CASE WHEN LOWER(status) = 'fulfilled' THEN 1 END) as fulfilledBorrowed
            FROM requests
            WHERE toDRRMO = ?
        ");
        $borrowedFromMeStmt->execute([$municipalityID]);
        $borrowedStats = $borrowedFromMeStmt->fetch(PDO::FETCH_ASSOC);

        // Get my hazards
        $hazardCount = 0;
        $activeHazards = 0;
        try {
            $hazardStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as totalHazards,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as activeHazards
                FROM hazards
                WHERE drrmoID = ?
            ");
            $hazardStmt->execute([$municipalityID]);
            $hazardStats = $hazardStmt->fetch(PDO::FETCH_ASSOC);
            $hazardCount = $hazardStats['totalHazards'];
            $activeHazards = $hazardStats['activeHazards'];
        } catch (Exception $e) {
            // Hazards table might not exist yet
            $hazardCount = 0;
            $activeHazards = 0;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'municipalityName' => $municipalityName,
                'municipalityID' => $municipalityID,
                'myResources' => $resourceStats,
                'myRequests' => $myRequestStats,
                'borrowedFromMe' => $borrowedStats,
                'myHazards' => [
                    'total' => $hazardCount,
                    'active' => $activeHazards
                ]
            ]
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching municipality overview: ' . $e->getMessage());
    }
}

function getMyResourcesReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        if (!$municipalityID) {
            throw new Exception('Municipality ID not found');
        }

        // Get municipality name for report header
        $munStmt = $pdo->prepare("SELECT name, logo_url FROM drrmo WHERE drrmoID = ?");
        $munStmt->execute([$municipalityID]);
        $municipalityRow = $munStmt->fetch(PDO::FETCH_ASSOC);
        $municipalityName = $municipalityRow ? $municipalityRow['name'] : null;
        $logoUrl = $municipalityRow ? $municipalityRow['logo_url'] : null;

        // Get my municipality's resources
        $sql = "
            SELECT 
                r.resourceID,
                r.resourceName,
                r.category,
                r.availableStock,
                r.unit,
                r.description,
                CASE 
                    WHEN r.availableStock > 50 THEN 'Well Stocked'
                    WHEN r.availableStock > 10 THEN 'Adequate'
                    WHEN r.availableStock > 0 THEN 'Low Stock'
                    ELSE 'Out of Stock'
                END as stockStatus
            FROM resources r
            WHERE r.drrmoID = ?
            ORDER BY r.category, r.resourceName
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$municipalityID]);
        $myResources = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get resource distribution by category
        $categoryStmt = $pdo->prepare("
            SELECT 
                category,
                COUNT(*) as count,
                SUM(availableStock) as totalStock,
                AVG(availableStock) as avgStock,
                COUNT(CASE WHEN availableStock <= 10 THEN 1 END) as lowStockCount
            FROM resources
            WHERE drrmoID = ?
            GROUP BY category
            ORDER BY count DESC
        ");
        $categoryStmt->execute([$municipalityID]);
        $categoryDistribution = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get low stock alerts for my municipality
        $lowStockStmt = $pdo->prepare("
            SELECT 
                resourceName,
                category,
                availableStock,
                unit
            FROM resources
            WHERE drrmoID = ? AND availableStock <= 10
            ORDER BY availableStock ASC
        ");
        $lowStockStmt->execute([$municipalityID]);
        $lowStockItems = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'municipalityName' => $municipalityName,
                'logoUrl' => $logoUrl,
                'myResources' => $myResources,
                'categoryDistribution' => $categoryDistribution,
                'lowStockItems' => $lowStockItems
            ]
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching my resources: ' . $e->getMessage());
    }
}

function getRequestManagementReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        // Get request statistics
        $requestStats = $pdo->prepare("
            SELECT 
                COUNT(*) as totalRequests,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pendingRequests,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approvedRequests,
                COUNT(CASE WHEN status = 'fulfilled' THEN 1 END) as fulfilledRequests,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejectedRequests,
                AVG(TIMESTAMPDIFF(HOUR, requestDate, COALESCE(responseDate, NOW()))) as avgResponseTime
            FROM requests
        ");
        $requestStats->execute();
        $stats = $requestStats->fetch(PDO::FETCH_ASSOC);

        // Get requests by municipality
        $municipalityRequests = $pdo->prepare("
            SELECT 
                from_drrmo.name as fromMunicipality,
                to_drrmo.name as toMunicipality,
                COUNT(*) as requestCount,
                COUNT(CASE WHEN r.status = 'fulfilled' THEN 1 END) as fulfilledCount,
                AVG(TIMESTAMPDIFF(HOUR, r.requestDate, COALESCE(r.responseDate, NOW()))) as avgResponseTime
            FROM requests r
            JOIN drrmo from_drrmo ON r.fromDRRMO = from_drrmo.drrmoID
            JOIN drrmo to_drrmo ON r.toDRRMO = to_drrmo.drrmoID
            GROUP BY r.fromDRRMO, r.toDRRMO, from_drrmo.name, to_drrmo.name
            ORDER BY requestCount DESC
        ");
        $municipalityRequests->execute();
        $municipalityData = $municipalityRequests->fetchAll(PDO::FETCH_ASSOC);

        // Get recent requests
        $recentRequests = $pdo->prepare("
            SELECT 
                r.requestID,
                r.quantity,
                r.priority,
                r.status,
                r.requestDate,
                from_drrmo.name as fromMunicipality,
                to_drrmo.name as toMunicipality,
                res.resourceName,
                res.category
            FROM requests r
            JOIN drrmo from_drrmo ON r.fromDRRMO = from_drrmo.drrmoID
            JOIN drrmo to_drrmo ON r.toDRRMO = to_drrmo.drrmoID
            JOIN resources res ON r.resourceID = res.resourceID
            ORDER BY r.requestDate DESC
            LIMIT 20
        ");
        $recentRequests->execute();
        $recentData = $recentRequests->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'statistics' => $stats,
                'municipalityRequests' => $municipalityData,
                'recentRequests' => $recentData
            ]
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching request management data: ' . $e->getMessage());
    }
}

function getHazardAssessmentReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        // Check if hazards table exists
        $tableExists = false;
        try {
            $checkStmt = $pdo->prepare("SELECT 1 FROM hazards LIMIT 1");
            $checkStmt->execute();
            $tableExists = true;
        } catch (Exception $e) {
            $tableExists = false;
        }

        if (!$tableExists) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'message' => 'Hazards table not found. No hazard data available.',
                    'hazards' => [],
                    'statistics' => [
                        'totalHazards' => 0,
                        'activeHazards' => 0,
                        'highRisk' => 0,
                        'mediumRisk' => 0,
                        'lowRisk' => 0
                    ]
                ]
            ]);
            return;
        }

        // Get hazard statistics
        $hazardStats = $pdo->prepare("
            SELECT 
                COUNT(*) as totalHazards,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as activeHazards,
                COUNT(CASE WHEN intensity = 'High' THEN 1 END) as highRisk,
                COUNT(CASE WHEN intensity = 'Medium' THEN 1 END) as mediumRisk,
                COUNT(CASE WHEN intensity = 'Low' THEN 1 END) as lowRisk,
                SUM(affectedPopulation) as totalPeopleAffected
            FROM hazards
        ");
        $hazardStats->execute();
        $stats = $hazardStats->fetch(PDO::FETCH_ASSOC);

        // Get hazards by type
        $hazardTypes = $pdo->prepare("
            SELECT 
                hazardType,
                COUNT(*) as count,
                SUM(affectedPopulation) as totalAffected
            FROM hazards
            GROUP BY hazardType
            ORDER BY count DESC
        ");
        $hazardTypes->execute();
        $typeData = $hazardTypes->fetchAll(PDO::FETCH_ASSOC);

        // Get recent hazards
        $recentHazards = $pdo->prepare("
            SELECT 
                hazardID,
                hazardType,
                intensity,
                location,
                affectedPopulation,
                reportedAt,
                status,
                description
            FROM hazards
            ORDER BY reportedAt DESC
            LIMIT 20
        ");
        $recentHazards->execute();
        $recentData = $recentHazards->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'statistics' => $stats,
                'hazardTypes' => $typeData,
                'recentHazards' => $recentData
            ]
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching hazard assessment: ' . $e->getMessage());
    }
}

function getMunicipalityPerformanceReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        // Get municipality performance metrics
        $performanceStmt = $pdo->prepare("
            SELECT 
                d.name as municipality,
                COUNT(r.resourceID) as totalResources,
                SUM(r.availableStock) as totalStock,
                COUNT(req.requestID) as totalRequests,
                COUNT(CASE WHEN req.status = 'fulfilled' THEN 1 END) as fulfilledRequests,
                AVG(TIMESTAMPDIFF(HOUR, req.requestDate, COALESCE(req.responseDate, NOW()))) as avgResponseTime
            FROM drrmo d
            LEFT JOIN resources r ON d.drrmoID = r.drrmoID
            LEFT JOIN requests req ON d.drrmoID = req.fromDRRMO
            GROUP BY d.drrmoID, d.name
            ORDER BY totalResources DESC
        ");
        $performanceStmt->execute();
        $performanceData = $performanceStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate performance scores
        foreach ($performanceData as &$municipality) {
            $fulfillmentRate = $municipality['totalRequests'] > 0 ? 
                ($municipality['fulfilledRequests'] / $municipality['totalRequests']) * 100 : 100;
            
            $responseScore = $municipality['avgResponseTime'] <= 24 ? 100 : 
                max(0, 100 - (($municipality['avgResponseTime'] - 24) * 2));
            
            $resourceScore = min(100, ($municipality['totalResources'] / 50) * 100);
            
            $municipality['fulfillmentRate'] = round($fulfillmentRate, 2);
            $municipality['performanceScore'] = round(($responseScore + $fulfillmentRate + $resourceScore) / 3, 2);
        }

        echo json_encode([
            'success' => true,
            'data' => $performanceData
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching municipality performance: ' . $e->getMessage());
    }
}

function getEmergencyResponseReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        // Get emergency response metrics
        $responseStmt = $pdo->prepare("
            SELECT 
                d.name as municipality,
                COUNT(req.requestID) as emergencyRequests,
                COUNT(CASE WHEN req.priority = 'high' THEN 1 END) as highPriorityRequests,
                AVG(TIMESTAMPDIFF(HOUR, req.requestDate, COALESCE(req.responseDate, NOW()))) as avgResponseTime,
                COUNT(CASE WHEN req.status = 'fulfilled' THEN 1 END) as successfulResponses
            FROM drrmo d
            LEFT JOIN requests req ON d.drrmoID = req.fromDRRMO
            WHERE req.requestDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY d.drrmoID, d.name
            ORDER BY emergencyRequests DESC
        ");
        $responseStmt->execute();
        $responseData = $responseStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get response time analysis
        $timeAnalysis = $pdo->prepare("
            SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(HOUR, requestDate, COALESCE(responseDate, NOW())) <= 2 THEN 'Excellent (≤2h)'
                    WHEN TIMESTAMPDIFF(HOUR, requestDate, COALESCE(responseDate, NOW())) <= 6 THEN 'Good (2-6h)'
                    WHEN TIMESTAMPDIFF(HOUR, requestDate, COALESCE(responseDate, NOW())) <= 24 THEN 'Fair (6-24h)'
                    ELSE 'Poor (>24h)'
                END as responseCategory,
                COUNT(*) as requestCount
            FROM requests
            WHERE requestDate >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY responseCategory
        ");
        $timeAnalysis->execute();
        $timeData = $timeAnalysis->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'municipalityResponses' => $responseData,
                'responseTimeAnalysis' => $timeData
            ]
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching emergency response data: ' . $e->getMessage());
    }
}

function getResourceSharingReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        // Get inter-municipality resource sharing
        $sharingStmt = $pdo->prepare("
            SELECT 
                from_drrmo.name as fromMunicipality,
                to_drrmo.name as toMunicipality,
                COUNT(r.requestID) as requestCount,
                COUNT(CASE WHEN r.status = 'fulfilled' THEN 1 END) as fulfilledCount,
                AVG(TIMESTAMPDIFF(HOUR, r.requestDate, COALESCE(r.responseDate, NOW()))) as avgResponseTime,
                SUM(r.quantity) as totalQuantityRequested
            FROM requests r
            JOIN drrmo from_drrmo ON r.fromDRRMO = from_drrmo.drrmoID
            JOIN drrmo to_drrmo ON r.toDRRMO = to_drrmo.drrmoID
            WHERE r.requestDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY r.fromDRRMO, r.toDRRMO, from_drrmo.name, to_drrmo.name
            ORDER BY requestCount DESC
        ");
        $sharingStmt->execute();
        $sharingData = $sharingStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get most requested resources
        $popularResources = $pdo->prepare("
            SELECT 
                res.resourceName,
                res.category,
                COUNT(r.requestID) as requestCount,
                SUM(r.quantity) as totalQuantityRequested
            FROM requests r
            JOIN resources res ON r.resourceID = res.resourceID
            WHERE r.requestDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY res.resourceID, res.resourceName, res.category
            ORDER BY requestCount DESC
            LIMIT 10
        ");
        $popularResources->execute();
        $popularData = $popularResources->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'resourceSharing' => $sharingData,
                'popularResources' => $popularData
            ]
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching resource sharing data: ' . $e->getMessage());
    }
}

function getCapacityAssessmentReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        // Get DRRM capacity assessment
        $capacityStmt = $pdo->prepare("
            SELECT 
                d.name as municipality,
                COUNT(r.resourceID) as totalResources,
                SUM(r.availableStock) as totalStock,
                COUNT(DISTINCT r.category) as resourceCategories,
                COUNT(req.requestID) as totalRequests,
                COUNT(CASE WHEN req.status = 'fulfilled' THEN 1 END) as fulfilledRequests,
                AVG(TIMESTAMPDIFF(HOUR, req.requestDate, COALESCE(req.responseDate, NOW()))) as avgResponseTime
            FROM drrmo d
            LEFT JOIN resources r ON d.drrmoID = r.drrmoID
            LEFT JOIN requests req ON d.drrmoID = req.fromDRRMO
            GROUP BY d.drrmoID, d.name
            ORDER BY totalResources DESC
        ");
        $capacityStmt->execute();
        $capacityData = $capacityStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate capacity scores
        foreach ($capacityData as &$municipality) {
            $resourceScore = min(100, ($municipality['totalResources'] / 100) * 100);
            $stockScore = min(100, ($municipality['totalStock'] / 1000) * 100);
            $diversityScore = min(100, ($municipality['resourceCategories'] / 5) * 100);
            $responseScore = $municipality['avgResponseTime'] <= 12 ? 100 : 
                max(0, 100 - (($municipality['avgResponseTime'] - 12) * 5));
            
            $municipality['capacityScore'] = round(($resourceScore + $stockScore + $diversityScore + $responseScore) / 4, 2);
        }

        echo json_encode([
            'success' => true,
            'data' => $capacityData
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching capacity assessment: ' . $e->getMessage());
    }
}

function getComplianceMonitoringReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        // Get compliance monitoring data
        $complianceStmt = $pdo->prepare("
            SELECT 
                d.name as municipality,
                COUNT(r.resourceID) as totalResources,
                COUNT(CASE WHEN r.availableStock <= 10 THEN 1 END) as lowStockCount,
                COUNT(req.requestID) as totalRequests,
                COUNT(CASE WHEN req.status = 'fulfilled' THEN 1 END) as fulfilledRequests,
                AVG(TIMESTAMPDIFF(HOUR, req.requestDate, COALESCE(req.responseDate, NOW()))) as avgResponseTime,
                COUNT(DISTINCT r.category) as resourceCategories
            FROM drrmo d
            LEFT JOIN resources r ON d.drrmoID = r.drrmoID
            LEFT JOIN requests req ON d.drrmoID = req.fromDRRMO
            WHERE req.requestDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY d.drrmoID, d.name
            ORDER BY d.name
        ");
        $complianceStmt->execute();
        $complianceData = $complianceStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate compliance scores
        foreach ($complianceData as &$municipality) {
            $stockCompliance = $municipality['totalResources'] > 0 ? 
                (($municipality['totalResources'] - $municipality['lowStockCount']) / $municipality['totalResources']) * 100 : 100;
            
            $responseCompliance = $municipality['avgResponseTime'] <= 24 ? 100 : 
                max(0, 100 - (($municipality['avgResponseTime'] - 24) * 2));
            
            $fulfillmentCompliance = $municipality['totalRequests'] > 0 ? 
                ($municipality['fulfilledRequests'] / $municipality['totalRequests']) * 100 : 100;
            
            $municipality['complianceScore'] = round(($stockCompliance + $responseCompliance + $fulfillmentCompliance) / 3, 2);
        }

        echo json_encode([
            'success' => true,
            'data' => $complianceData
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching compliance monitoring: ' . $e->getMessage());
    }
}

function getUnifiedMunicipalityReport() {
    global $pdo, $municipalityID;

    try {
        if (!$municipalityID) {
            throw new Exception('Municipality ID not found');
        }

        // My Resources (summary list)
        $resStmt = $pdo->prepare("SELECT resourceName, category, unit, availableStock, CASE 
                    WHEN availableStock > 50 THEN 'Well Stocked'
                    WHEN availableStock > 10 THEN 'Adequate'
                    WHEN availableStock > 0 THEN 'Low Stock'
                    ELSE 'Out of Stock'
                END as stockStatus
            FROM resources WHERE drrmoID = ? ORDER BY category, resourceName");
        $resStmt->execute([$municipalityID]);
        $myResources = $resStmt->fetchAll(PDO::FETCH_ASSOC);

        // Borrowed from me (requests to me)
        $borrowStmt = $pdo->prepare("SELECT r.requestID, from_drrmo.name as fromMunicipality, res.resourceName, res.unit, r.quantity, r.priority, r.status, r.requestDate, r.responseDate
            FROM requests r
            JOIN drrmo from_drrmo ON r.fromDRRMO = from_drrmo.drrmoID
            JOIN resources res ON r.resourceID = res.resourceID
            WHERE r.toDRRMO = ? ORDER BY r.requestDate DESC");
        $borrowStmt->execute([$municipalityID]);
        $borrowedFromMe = $borrowStmt->fetchAll(PDO::FETCH_ASSOC);

        // My requests (requests I made)
        $myReqStmt = $pdo->prepare("SELECT r.requestID, to_drrmo.name as toMunicipality, res.resourceName, res.unit, r.quantity, r.priority, r.status, r.requestDate, r.responseDate
            FROM requests r
            JOIN drrmo to_drrmo ON r.toDRRMO = to_drrmo.drrmoID
            JOIN resources res ON r.resourceID = res.resourceID
            WHERE r.fromDRRMO = ? ORDER BY r.requestDate DESC");
        $myReqStmt->execute([$municipalityID]);
        $myRequests = $myReqStmt->fetchAll(PDO::FETCH_ASSOC);

        // My hazards
        $myHazards = [];
        try {
            $hazStmt = $pdo->prepare("SELECT hazardType, intensity, location, affectedPopulation, reportedAt, status FROM hazards WHERE drrmoID = ? ORDER BY reportedAt DESC");
            $hazStmt->execute([$municipalityID]);
            $myHazards = $hazStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $myHazards = [];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'myResources' => $myResources,
                'borrowedFromMe' => $borrowedFromMe,
                'myRequests' => $myRequests,
                'myHazards' => $myHazards
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error fetching unified report: ' . $e->getMessage()]);
    }
}

function getBorrowedResourcesReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        if (!$municipalityID) {
            throw new Exception('Municipality ID not found');
        }

        // Get municipality details for report header
        $munStmt = $pdo->prepare("SELECT name, logo_url FROM drrmo WHERE drrmoID = ?");
        $munStmt->execute([$municipalityID]);
        $munRow = $munStmt->fetch(PDO::FETCH_ASSOC);
        $municipalityName = $munRow ? $munRow['name'] : null;
        $logoUrl = $munRow ? $munRow['logo_url'] : null;

        // Get resources borrowed from my municipality (limit to recent for performance)
        $sql = "
            SELECT 
                r.requestID,
                r.quantity,
                r.priority,
                r.status,
                r.requestDate,
                r.responseDate,
                from_drrmo.name as fromMunicipality,
                res.resourceName,
                res.category,
                res.unit,
                TIMESTAMPDIFF(HOUR, r.requestDate, COALESCE(r.responseDate, NOW())) as responseTime
            FROM requests r
            JOIN drrmo from_drrmo ON r.fromDRRMO = from_drrmo.drrmoID
            JOIN resources res ON r.resourceID = res.resourceID
            WHERE r.toDRRMO = ?
            ORDER BY r.requestDate DESC
            LIMIT 1000
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$municipalityID]);
        $borrowedRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get statistics
        $statsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as totalBorrowed,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pendingBorrowed,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approvedBorrowed,
                COUNT(CASE WHEN status = 'fulfilled' THEN 1 END) as fulfilledBorrowed,
                AVG(TIMESTAMPDIFF(HOUR, requestDate, COALESCE(responseDate, NOW()))) as avgResponseTime
            FROM requests
            WHERE toDRRMO = ?
        ");
        $statsStmt->execute([$municipalityID]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'municipalityName' => $municipalityName,
                'logoUrl' => $logoUrl,
                'borrowedRequests' => $borrowedRequests,
                'statistics' => $stats
            ]
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching borrowed resources: ' . $e->getMessage());
    }
}

function getMyRequestsReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        if (!$municipalityID) {
            throw new Exception('Municipality ID not found');
        }

        // Get municipality details for report header
        $munStmt = $pdo->prepare("SELECT name, logo_url FROM drrmo WHERE drrmoID = ?");
        $munStmt->execute([$municipalityID]);
        $munRow = $munStmt->fetch(PDO::FETCH_ASSOC);
        $municipalityName = $munRow ? $munRow['name'] : null;
        $logoUrl = $munRow ? $munRow['logo_url'] : null;

        // Get my requests to other municipalities (limit to recent for performance)
        $sql = "
            SELECT 
                r.requestID,
                r.quantity,
                r.priority,
                r.status,
                r.requestDate,
                r.responseDate,
                to_drrmo.name as toMunicipality,
                res.resourceName,
                res.category,
                res.unit,
                TIMESTAMPDIFF(HOUR, r.requestDate, COALESCE(r.responseDate, NOW())) as responseTime
            FROM requests r
            JOIN drrmo to_drrmo ON r.toDRRMO = to_drrmo.drrmoID
            JOIN resources res ON r.resourceID = res.resourceID
            WHERE r.fromDRRMO = ?
            ORDER BY r.requestDate DESC
            LIMIT 1000
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$municipalityID]);
        $myRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get statistics
        $statsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as totalRequests,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pendingRequests,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approvedRequests,
                COUNT(CASE WHEN status = 'fulfilled' THEN 1 END) as fulfilledRequests,
                AVG(TIMESTAMPDIFF(HOUR, requestDate, COALESCE(responseDate, NOW()))) as avgResponseTime
            FROM requests
            WHERE fromDRRMO = ?
        ");
        $statsStmt->execute([$municipalityID]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'municipalityName' => $municipalityName,
                'logoUrl' => $logoUrl,
                'myRequests' => $myRequests,
                'statistics' => $stats
            ]
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching my requests: ' . $e->getMessage());
    }
}

function getMyHazardsReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        if (!$municipalityID) {
            throw new Exception('Municipality ID not found');
        }

        // Get municipality details for report header
        $munStmt = $pdo->prepare("SELECT name, logo_url FROM drrmo WHERE drrmoID = ?");
        $munStmt->execute([$municipalityID]);
        $munRow = $munStmt->fetch(PDO::FETCH_ASSOC);
        $municipalityName = $munRow ? $munRow['name'] : null;
        $logoUrl = $munRow ? $munRow['logo_url'] : null;

        // Check if hazards table exists
        $tableExists = false;
        try {
            $checkStmt = $pdo->prepare("SELECT 1 FROM hazards LIMIT 1");
            $checkStmt->execute();
            $tableExists = true;
        } catch (Exception $e) {
            $tableExists = false;
        }

        if (!$tableExists) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'municipalityName' => $municipalityName,
                    'logoUrl' => $logoUrl,
                    'message' => 'Hazards table not found. No hazard data available.',
                    'myHazards' => [],
                    'statistics' => [
                        'totalHazards' => 0,
                        'activeHazards' => 0,
                        'highRisk' => 0,
                        'mediumRisk' => 0,
                        'lowRisk' => 0
                    ]
                ]
            ]);
            return;
        }

        // Get my municipality's hazards
        $sql = "
            SELECT 
                hazardID,
                hazardType,
                intensity,
                location,
                affectedPopulation,
                reportedAt,
                status,
                description
            FROM hazards
            WHERE drrmoID = ?
            ORDER BY reportedAt DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$municipalityID]);
        $myHazards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get statistics
        $statsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as totalHazards,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as activeHazards,
                COUNT(CASE WHEN intensity = 'High' THEN 1 END) as highRisk,
                COUNT(CASE WHEN intensity = 'Medium' THEN 1 END) as mediumRisk,
                COUNT(CASE WHEN intensity = 'Low' THEN 1 END) as lowRisk,
                SUM(affectedPopulation) as totalPeopleAffected
            FROM hazards
            WHERE drrmoID = ?
        ");
        $statsStmt->execute([$municipalityID]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'municipalityName' => $municipalityName,
                'logoUrl' => $logoUrl,
                'myHazards' => $myHazards,
                'statistics' => $stats
            ]
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching my hazards: ' . $e->getMessage());
    }
}

function getMyPerformanceReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        if (!$municipalityID) {
            throw new Exception('Municipality ID not found');
        }

        // Get performance metrics for my municipality
        $sql = "
            SELECT 
                'Resource Management' as category,
                COUNT(r.resourceID) as totalResources,
                SUM(r.availableStock) as totalStock,
                COUNT(CASE WHEN r.availableStock <= 10 THEN 1 END) as lowStockCount,
                ROUND((COUNT(CASE WHEN r.availableStock > 10 THEN 1 END) / COUNT(r.resourceID)) * 100, 2) as stockEfficiency
            FROM resources r
            WHERE r.drrmoID = ?
            
            UNION ALL
            
            SELECT 
                'Request Management' as category,
                COUNT(req.requestID) as totalRequests,
                COUNT(CASE WHEN req.status = 'fulfilled' THEN 1 END) as fulfilledRequests,
                AVG(TIMESTAMPDIFF(HOUR, req.requestDate, COALESCE(req.responseDate, NOW()))) as avgResponseTime,
                ROUND((COUNT(CASE WHEN req.status = 'fulfilled' THEN 1 END) / COUNT(req.requestID)) * 100, 2) as fulfillmentRate
            FROM requests req
            WHERE req.fromDRRMO = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$municipalityID, $municipalityID]);
        $performanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $performanceData
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching my performance: ' . $e->getMessage());
    }
}

function getResourceUtilizationReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        if (!$municipalityID) {
            throw new Exception('Municipality ID not found');
        }

        // Get resource utilization data
        $sql = "
            SELECT 
                r.resourceName,
                r.category,
                r.availableStock,
                r.unit,
                COUNT(req.requestID) as timesBorrowed,
                SUM(req.quantity) as totalQuantityBorrowed,
                COUNT(CASE WHEN req.status = 'fulfilled' THEN 1 END) as successfulBorrows
            FROM resources r
            LEFT JOIN requests req ON r.resourceID = req.resourceID AND req.toDRRMO = ?
            WHERE r.drrmoID = ?
            GROUP BY r.resourceID, r.resourceName, r.category, r.availableStock, r.unit
            ORDER BY timesBorrowed DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$municipalityID, $municipalityID]);
        $utilizationData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $utilizationData
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching resource utilization: ' . $e->getMessage());
    }
}

function getEmergencyPreparednessReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        if (!$municipalityID) {
            throw new Exception('Municipality ID not found');
        }

        // Get emergency preparedness metrics
        $sql = "
            SELECT 
                'Resource Readiness' as category,
                COUNT(r.resourceID) as totalResources,
                COUNT(CASE WHEN r.availableStock > 50 THEN 1 END) as wellStocked,
                COUNT(CASE WHEN r.availableStock <= 10 THEN 1 END) as lowStock,
                ROUND((COUNT(CASE WHEN r.availableStock > 10 THEN 1 END) / COUNT(r.resourceID)) * 100, 2) as readinessScore
            FROM resources r
            WHERE r.drrmoID = ?
            
            UNION ALL
            
            SELECT 
                'Response Capability' as category,
                COUNT(req.requestID) as totalRequests,
                COUNT(CASE WHEN req.status = 'fulfilled' THEN 1 END) as successfulResponses,
                AVG(TIMESTAMPDIFF(HOUR, req.requestDate, COALESCE(req.responseDate, NOW()))) as avgResponseTime,
                ROUND((COUNT(CASE WHEN req.status = 'fulfilled' THEN 1 END) / COUNT(req.requestID)) * 100, 2) as responseRate
            FROM requests req
            WHERE req.toDRRMO = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$municipalityID, $municipalityID]);
        $preparednessData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $preparednessData
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching emergency preparedness: ' . $e->getMessage());
    }
}

function getMonthlySummaryReport() {
    global $pdo, $municipalityID, $userRole;
    
    try {
        if (!$municipalityID) {
            throw new Exception('Municipality ID not found');
        }

        // Get monthly summary data
        $sql = "
            SELECT 
                'Resources' as category,
                COUNT(r.resourceID) as totalItems,
                SUM(r.availableStock) as totalStock,
                COUNT(CASE WHEN r.availableStock <= 10 THEN 1 END) as lowStockItems
            FROM resources r
            WHERE r.drrmoID = ?
            
            UNION ALL
            
            SELECT 
                'Requests Made' as category,
                COUNT(req.requestID) as totalRequests,
                COUNT(CASE WHEN req.status = 'fulfilled' THEN 1 END) as fulfilledRequests,
                AVG(TIMESTAMPDIFF(HOUR, req.requestDate, COALESCE(req.responseDate, NOW()))) as avgResponseTime
            FROM requests req
            WHERE req.fromDRRMO = ? AND req.requestDate >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            
            UNION ALL
            
            SELECT 
                'Requests Received' as category,
                COUNT(req.requestID) as totalRequests,
                COUNT(CASE WHEN req.status = 'fulfilled' THEN 1 END) as fulfilledRequests,
                AVG(TIMESTAMPDIFF(HOUR, req.requestDate, COALESCE(req.responseDate, NOW()))) as avgResponseTime
            FROM requests req
            WHERE req.toDRRMO = ? AND req.requestDate >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$municipalityID, $municipalityID, $municipalityID]);
        $summaryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $summaryData
        ]);

    } catch (Exception $e) {
        throw new Exception('Error fetching monthly summary: ' . $e->getMessage());
    }
}

/**
 * PDRRMO Analytics: province-wide aggregated request data.
 * - Request frequency per resource/equipment type
 * - Request trends over time
 * - High-demand equipment
 * - Reporting patterns across municipalities
 * Restricted to emergency_coordinator or admin.
 */
function getPdrrmoAnalytics() {
    global $pdo, $userRole;

    $userRole = $userRole ?? ($_SESSION['user_type'] ?? '');
    if ($userRole !== 'emergency_coordinator' && $userRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden: PDRRMO role required']);
        exit;
    }

    $months = isset($_GET['months']) ? max(1, min(24, (int)$_GET['months'])) : 12;

    try {
        // Clean municipality labels (drop CDRRMO/MDRRMO prefix/suffix for display)
        $cleanName = function ($n) {
            $n = (string)($n ?? '');
            $n = preg_replace('/^(?:[A-Z]{0,3}DRRMO\s+)/', '', $n);
            $n = preg_replace('/\s+DRRMO$/', '', $n);
            $n = preg_replace('/^(City of\s+|Municipality of\s+)/i', '', $n);
            $n = preg_replace('/\s+City$/i', '', $n);
            return trim($n);
        };

        // 1. Request frequency per request type (resource/equipment): count and total quantity
        $freqSql = "
            SELECT
                r.resourceID,
                res.resourceName,
                res.category,
                res.unit,
                COUNT(*) AS requestCount,
                COALESCE(SUM(r.quantity), 0) AS totalQuantity
            FROM requests r
            JOIN resources res ON r.resourceID = res.resourceID
            WHERE r.requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY r.resourceID, res.resourceName, res.category, res.unit
            ORDER BY requestCount DESC, totalQuantity DESC
        ";
        $freqStmt = $pdo->prepare($freqSql);
        $freqStmt->execute([$months]);
        $requestFrequencyByType = $freqStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Request trends by month
        $trendsSql = "
            SELECT
                DATE_FORMAT(r.requestDate, '%Y-%m') AS period,
                COUNT(*) AS requestCount
            FROM requests r
            WHERE r.requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(r.requestDate, '%Y-%m')
            ORDER BY period ASC
        ";
        $trendsStmt = $pdo->prepare($trendsSql);
        $trendsStmt->execute([$months]);
        $requestTrends = $trendsStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. High-demand equipment (top 10 by request count; derived from frequency)
        $highDemandEquipment = array_slice($requestFrequencyByType, 0, 10);

        // 3b. Most requested resources by municipality (as requester): top N per municipality
        // Note: Built in PHP for MySQL compatibility (no window functions required).
        $muniTopSql = "
            SELECT
                r.fromDRRMO AS drrmoID,
                d.name AS municipalityName,
                res.resourceID,
                res.resourceName,
                res.unit,
                COUNT(*) AS requestCount,
                COALESCE(SUM(r.quantity), 0) AS totalQuantity
            FROM requests r
            JOIN drrmo d ON r.fromDRRMO = d.drrmoID
            JOIN resources res ON r.resourceID = res.resourceID
            WHERE r.requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY r.fromDRRMO, d.name, res.resourceID, res.resourceName, res.unit
            ORDER BY d.name ASC, requestCount DESC, totalQuantity DESC
        ";
        $muniTopStmt = $pdo->prepare($muniTopSql);
        $muniTopStmt->execute([$months]);
        $muniRows = $muniTopStmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Reporting patterns by municipality: as requester (fromDRRMO) and as provider (toDRRMO)
        $patternSql = "
            SELECT
                d.drrmoID,
                d.name AS municipalityName,
                COALESCE(req_as_requester.cnt, 0) AS requestsAsRequester,
                COALESCE(prov_as_provider.cnt, 0) AS requestsAsProvider
            FROM drrmo d
            LEFT JOIN (
                SELECT fromDRRMO AS drrmoID, COUNT(*) AS cnt
                FROM requests
                WHERE requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY fromDRRMO
            ) req_as_requester ON d.drrmoID = req_as_requester.drrmoID
            LEFT JOIN (
                SELECT toDRRMO AS drrmoID, COUNT(*) AS cnt
                FROM requests
                WHERE requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY toDRRMO
            ) prov_as_provider ON d.drrmoID = prov_as_provider.drrmoID
            WHERE COALESCE(req_as_requester.cnt, 0) + COALESCE(prov_as_provider.cnt, 0) > 0
            ORDER BY (COALESCE(req_as_requester.cnt, 0) + COALESCE(prov_as_provider.cnt, 0)) DESC
        ";
        $patternStmt = $pdo->prepare($patternSql);
        $patternStmt->execute([$months, $months]);
        $reportingPatternsByMunicipality = $patternStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reportingPatternsByMunicipality as &$row) {
            $row['municipalityNameDisplay'] = $cleanName($row['municipalityName'] ?? '');
        }
        unset($row);

        // Build mostRequestedByMunicipality with cleaned municipality names and top resources (top 5)
        $mostRequestedByMunicipality = [];
        $byMuni = [];
        foreach ($muniRows as $r) {
            $id = (string)($r['drrmoID'] ?? '');
            if ($id === '') continue;
            if (!isset($byMuni[$id])) {
                $byMuni[$id] = [
                    'drrmoID' => $r['drrmoID'],
                    'municipalityName' => $r['municipalityName'] ?? '',
                    'municipalityNameDisplay' => $cleanName($r['municipalityName'] ?? ''),
                    'topResources' => [],
                ];
            }
            // Keep only top 5 resources per municipality (sorted already by requestCount desc within municipality name).
            if (count($byMuni[$id]['topResources']) < 5) {
                $byMuni[$id]['topResources'][] = [
                    'resourceID' => $r['resourceID'],
                    'resourceName' => $r['resourceName'] ?? '',
                    'unit' => $r['unit'] ?? '',
                    'requestCount' => (int)($r['requestCount'] ?? 0),
                    'totalQuantity' => (int)($r['totalQuantity'] ?? 0),
                ];
            }
        }
        // Stable, readable ordering: by municipality display name
        $mostRequestedByMunicipality = array_values($byMuni);
        usort($mostRequestedByMunicipality, function ($a, $b) {
            return strcmp((string)($a['municipalityNameDisplay'] ?? ''), (string)($b['municipalityNameDisplay'] ?? ''));
        });

        // 5. Hazard hotspots (optional): hazards table might be missing in some installs
        $hazardTypeFrequency = [];
        $hazardHotspots = [];
        $hazardAffectedPopulationByMunicipality = [];
        $hazardMonthlyVolume = [];
        try {
            $checkHaz = $pdo->prepare("SELECT 1 FROM hazards LIMIT 1");
            $checkHaz->execute();

            $hazTypeSql = "
                SELECT
                    h.hazardType,
                    COUNT(*) AS hazardCount,
                    COUNT(CASE WHEN h.status = 'active' THEN 1 END) AS activeCount,
                    COUNT(CASE WHEN h.intensity IN ('High','Critical') THEN 1 END) AS highCount,
                    COUNT(CASE WHEN h.intensity = 'Medium' THEN 1 END) AS mediumCount,
                    COUNT(CASE WHEN h.intensity = 'Low' THEN 1 END) AS lowCount,
                    COALESCE(SUM(h.affectedPopulation), 0) AS totalAffected
                FROM hazards h
                WHERE h.reportedAt >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY h.hazardType
                ORDER BY hazardCount DESC, totalAffected DESC
                LIMIT 10
            ";
            $hazTypeStmt = $pdo->prepare($hazTypeSql);
            $hazTypeStmt->execute([$months]);
            $hazardTypeFrequency = $hazTypeStmt->fetchAll(PDO::FETCH_ASSOC);

            $hazHotspotSql = "
                SELECT
                    d.drrmoID,
                    d.name AS municipalityName,
                    COUNT(*) AS hazardCount,
                    COUNT(CASE WHEN h.status = 'active' THEN 1 END) AS activeCount,
                    COALESCE(SUM(h.affectedPopulation), 0) AS totalAffected
                FROM hazards h
                JOIN drrmo d ON h.drrmoID = d.drrmoID
                WHERE h.reportedAt >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY d.drrmoID, d.name
                ORDER BY activeCount DESC, totalAffected DESC, hazardCount DESC
                LIMIT 12
            ";
            $hazHotspotStmt = $pdo->prepare($hazHotspotSql);
            $hazHotspotStmt->execute([$months]);
            $hazardHotspots = $hazHotspotStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($hazardHotspots as &$h) {
                $h['municipalityNameDisplay'] = $cleanName($h['municipalityName'] ?? '');
            }
            unset($h);

            $hazAffectSql = "
                SELECT
                    d.drrmoID,
                    d.name AS municipalityName,
                    COALESCE(SUM(h.affectedPopulation), 0) AS affectedPopulation,
                    COUNT(CASE WHEN h.status = 'active' THEN 1 END) AS activeHazards
                FROM hazards h
                JOIN drrmo d ON h.drrmoID = d.drrmoID
                WHERE h.reportedAt >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY d.drrmoID, d.name
                ORDER BY affectedPopulation DESC
                LIMIT 8
            ";
            $hazAffectStmt = $pdo->prepare($hazAffectSql);
            $hazAffectStmt->execute([$months]);
            $hazardAffectedPopulationByMunicipality = $hazAffectStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($hazardAffectedPopulationByMunicipality as &$r) {
                $r['municipalityNameDisplay'] = $cleanName($r['municipalityName'] ?? '');
            }
            unset($r);

            $hazMonthlySql = "
                SELECT
                    DATE_FORMAT(h.reportedAt, '%Y-%m') AS period,
                    COUNT(*) AS totalCount,
                    COUNT(CASE WHEN h.intensity IN ('High','Critical') THEN 1 END) AS highCount,
                    COUNT(CASE WHEN h.intensity = 'Medium' THEN 1 END) AS mediumCount,
                    COUNT(CASE WHEN h.intensity = 'Low' THEN 1 END) AS lowCount
                FROM hazards h
                WHERE h.reportedAt >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(h.reportedAt, '%Y-%m')
                ORDER BY period ASC
            ";
            $hazMonthlyStmt = $pdo->prepare($hazMonthlySql);
            $hazMonthlyStmt->execute([$months]);
            $hazardMonthlyVolume = $hazMonthlyStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // hazards table not available; keep empty arrays
        }

        // 6. Request fairness audit: dependency, fulfillment, priority, processing time, return compliance
        $dependencyByMunicipality = [];
        $fulfillmentRateByMunicipality = [];
        $priorityDistribution = [];
        $avgProcessingTimeCorridors = [];
        $returnComplianceByMunicipality = [];

        // 6a. Dependency (sent vs provided)
        $depSql = "
            SELECT
                d.drrmoID,
                d.name AS municipalityName,
                COALESCE(sent.cnt, 0) AS requestsSent,
                COALESCE(provided.cnt, 0) AS requestsProvided
            FROM drrmo d
            LEFT JOIN (
                SELECT fromDRRMO AS drrmoID, COUNT(*) AS cnt
                FROM requests
                WHERE requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY fromDRRMO
            ) sent ON d.drrmoID = sent.drrmoID
            LEFT JOIN (
                SELECT toDRRMO AS drrmoID, COUNT(*) AS cnt
                FROM requests
                WHERE requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                  AND status IN ('fulfilled','returned','approved')
                GROUP BY toDRRMO
            ) provided ON d.drrmoID = provided.drrmoID
            WHERE COALESCE(sent.cnt, 0) + COALESCE(provided.cnt, 0) > 0
            ORDER BY (COALESCE(sent.cnt, 0) + COALESCE(provided.cnt, 0)) DESC
        ";
        $depStmt = $pdo->prepare($depSql);
        $depStmt->execute([$months, $months]);
        $dependencyByMunicipality = $depStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dependencyByMunicipality as &$r) {
            $r['municipalityNameDisplay'] = $cleanName($r['municipalityName'] ?? '');
            $sent = (int)($r['requestsSent'] ?? 0);
            $provided = (int)($r['requestsProvided'] ?? 0);
            $ratio = $provided > 0 ? ($sent / $provided) : ($sent > 0 ? 999 : 0);
            $r['dependencyRatio'] = $ratio;
        }
        unset($r);

        // 6b. Fulfillment rate by municipality (approved+fulfilled+returned / total sent)
        $ffSql = "
            SELECT
                d.drrmoID,
                d.name AS municipalityName,
                COUNT(*) AS totalSent,
                COUNT(CASE WHEN r.status IN ('approved','fulfilled','returned') THEN 1 END) AS approvedOrBetter,
                COUNT(CASE WHEN r.status = 'rejected' THEN 1 END) AS rejectedCount,
                COUNT(CASE WHEN r.status LIKE 'pending%' THEN 1 END) AS pendingCount
            FROM requests r
            JOIN drrmo d ON r.fromDRRMO = d.drrmoID
            WHERE r.requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY d.drrmoID, d.name
            ORDER BY totalSent DESC
        ";
        $ffStmt = $pdo->prepare($ffSql);
        $ffStmt->execute([$months]);
        $fulfillmentRateByMunicipality = $ffStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fulfillmentRateByMunicipality as &$r) {
            $r['municipalityNameDisplay'] = $cleanName($r['municipalityName'] ?? '');
            $total = (int)($r['totalSent'] ?? 0);
            $ok = (int)($r['approvedOrBetter'] ?? 0);
            $r['fulfillmentRate'] = $total > 0 ? round(($ok / $total) * 100, 1) : 100.0;
        }
        unset($r);

        // 6c. Priority distribution (priority + urgency combined)
        $priSql = "
            SELECT
                COALESCE(priority, 'unknown') AS priority,
                COALESCE(urgency, 'unknown') AS urgency,
                COUNT(*) AS cnt
            FROM requests
            WHERE requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY COALESCE(priority, 'unknown'), COALESCE(urgency, 'unknown')
        ";
        $priStmt = $pdo->prepare($priSql);
        $priStmt->execute([$months]);
        $priRows = $priStmt->fetchAll(PDO::FETCH_ASSOC);
        $bucket = ['critical' => 0, 'high' => 0, 'normal' => 0, 'low' => 0];
        foreach ($priRows as $pr) {
            $p = strtolower((string)($pr['priority'] ?? 'unknown'));
            $u = strtolower((string)($pr['urgency'] ?? 'unknown'));
            $cnt = (int)($pr['cnt'] ?? 0);
            if ($p === 'high' || $u === 'high') $bucket['critical'] += $cnt;
            else if ($p === 'medium' || $u === 'medium') $bucket['high'] += $cnt;
            else if ($p === 'low' || $u === 'low') $bucket['normal'] += $cnt;
            else $bucket['low'] += $cnt;
        }
        $totalPri = array_sum($bucket);
        $priorityDistribution = [
            ['label' => 'Critical / Urgent', 'key' => 'critical', 'count' => $bucket['critical'], 'pct' => $totalPri ? round(($bucket['critical'] / $totalPri) * 100, 1) : 0],
            ['label' => 'High Priority', 'key' => 'high', 'count' => $bucket['high'], 'pct' => $totalPri ? round(($bucket['high'] / $totalPri) * 100, 1) : 0],
            ['label' => 'Normal', 'key' => 'normal', 'count' => $bucket['normal'], 'pct' => $totalPri ? round(($bucket['normal'] / $totalPri) * 100, 1) : 0],
            ['label' => 'Low / Unset', 'key' => 'low', 'count' => $bucket['low'], 'pct' => $totalPri ? round(($bucket['low'] / $totalPri) * 100, 1) : 0],
        ];

        // 6d. Average processing time by corridor (from -> to)
        $procSql = "
            SELECT
                f.drrmoID AS fromDRRMO,
                f.name AS fromName,
                t.drrmoID AS toDRRMO,
                t.name AS toName,
                COUNT(*) AS totalRequests,
                AVG(TIMESTAMPDIFF(HOUR, r.requestDate, COALESCE(r.responseDate, NOW()))) AS avgHours
            FROM requests r
            JOIN drrmo f ON r.fromDRRMO = f.drrmoID
            JOIN drrmo t ON r.toDRRMO = t.drrmoID
            WHERE r.requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY f.drrmoID, f.name, t.drrmoID, t.name
            HAVING totalRequests >= 2
            ORDER BY avgHours ASC
            LIMIT 12
        ";
        $procStmt = $pdo->prepare($procSql);
        $procStmt->execute([$months]);
        $avgProcessingTimeCorridors = $procStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($avgProcessingTimeCorridors as &$r) {
            $r['fromNameDisplay'] = $cleanName($r['fromName'] ?? '');
            $r['toNameDisplay'] = $cleanName($r['toName'] ?? '');
            $r['avgHours'] = round((float)($r['avgHours'] ?? 0), 1);
        }
        unset($r);

        // 6e. Return compliance (borrower) - on-time returns vs returnDate
        $retSql = "
            SELECT
                d.drrmoID,
                d.name AS municipalityName,
                COUNT(*) AS totalReturnedWithDue,
                COUNT(CASE WHEN returnedAt IS NOT NULL AND returnDate IS NOT NULL AND returnedAt <= DATE_ADD(returnDate, INTERVAL 1 DAY) THEN 1 END) AS onTimeCount,
                COUNT(CASE WHEN returnedAt IS NOT NULL AND returnDate IS NOT NULL AND returnedAt > DATE_ADD(returnDate, INTERVAL 1 DAY) THEN 1 END) AS lateCount
            FROM requests r
            JOIN drrmo d ON r.fromDRRMO = d.drrmoID
            WHERE r.requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
              AND r.returnDate IS NOT NULL
              AND r.returnedAt IS NOT NULL
              AND r.status = 'returned'
            GROUP BY d.drrmoID, d.name
            ORDER BY totalReturnedWithDue DESC
        ";
        $retStmt = $pdo->prepare($retSql);
        $retStmt->execute([$months]);
        $returnComplianceByMunicipality = $retStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($returnComplianceByMunicipality as &$r) {
            $r['municipalityNameDisplay'] = $cleanName($r['municipalityName'] ?? '');
            $total = (int)($r['totalReturnedWithDue'] ?? 0);
            $onTime = (int)($r['onTimeCount'] ?? 0);
            $r['onTimePct'] = $total > 0 ? round(($onTime / $total) * 100, 1) : 0.0;
        }
        unset($r);

        echo json_encode([
            'success' => true,
            'data' => [
                'requestFrequencyByType' => $requestFrequencyByType,
                'requestTrends' => $requestTrends,
                'highDemandEquipment' => $highDemandEquipment,
                'mostRequestedByMunicipality' => $mostRequestedByMunicipality,
                'reportingPatternsByMunicipality' => $reportingPatternsByMunicipality,
                'hazardTypeFrequency' => $hazardTypeFrequency,
                'hazardHotspots' => $hazardHotspots,
                'hazardAffectedPopulationByMunicipality' => $hazardAffectedPopulationByMunicipality,
                'hazardMonthlyVolume' => $hazardMonthlyVolume,
                'dependencyByMunicipality' => $dependencyByMunicipality,
                'fulfillmentRateByMunicipality' => $fulfillmentRateByMunicipality,
                'priorityDistribution' => $priorityDistribution,
                'avgProcessingTimeCorridors' => $avgProcessingTimeCorridors,
                'returnComplianceByMunicipality' => $returnComplianceByMunicipality,
                'periodMonths' => $months,
            ],
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}

/**
 * PDRRMO: Frequency of reports per request type (resource/equipment).
 * Returns counts and quantities per resource. Restricted to PDRRMO roles.
 */
function getPdrrmoRequestFrequency() {
    global $pdo, $userRole;

    $userRole = $userRole ?? ($_SESSION['user_type'] ?? '');
    if ($userRole !== 'emergency_coordinator' && $userRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden: PDRRMO role required']);
        exit;
    }

    $months = isset($_GET['months']) ? max(1, min(24, (int)$_GET['months'])) : 12;

    try {
        $sql = "
            SELECT
                r.resourceID,
                res.resourceName,
                res.category,
                res.unit,
                COUNT(*) AS frequency,
                COALESCE(SUM(r.quantity), 0) AS totalQuantity
            FROM requests r
            JOIN resources res ON r.resourceID = res.resourceID
            WHERE r.requestDate >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY r.resourceID, res.resourceName, res.category, res.unit
            ORDER BY frequency DESC, totalQuantity DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$months]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $rows, 'periodMonths' => $months]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}
?>
