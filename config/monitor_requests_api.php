<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

// Require PDRRMO-level role (emergency_coordinator or admin per auth.php)
try {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    // Only allow emergency_coordinator or admin to use this endpoint
    $role = $_SESSION['user_type'] ?? '';
    if ($role !== 'emergency_coordinator' && $role !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }

    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        // Optional filters
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
        $municipality = isset($_GET['municipality']) ? trim($_GET['municipality']) : '';

        $sql = "
            SELECT 
                r.*,
                LOWER(r.priority) AS priority,
                LOWER(r.status) AS status,
                from_drrmo.name AS fromMunicipality,
                to_drrmo.name AS toMunicipality,
                res.resourceName,
                res.category,
                res.unit,
                COALESCE(res.description, '') AS description
            FROM requests r
            JOIN drrmo from_drrmo ON r.fromDRRMO = from_drrmo.drrmoID
            JOIN drrmo to_drrmo ON r.toDRRMO = to_drrmo.drrmoID
            JOIN resources res ON r.resourceID = res.resourceID
            ORDER BY r.requestGroupId IS NULL ASC, r.requestGroupId ASC, r.requestDate DESC, r.requestID DESC
        ";

        $params = [];
        $where = [];

        if ($status !== '') {
            $where[] = 'LOWER(r.status) = ?';
            $params[] = strtolower($status);
        }
        if ($priority !== '') {
            $where[] = 'LOWER(r.priority) = ?';
            $params[] = strtolower($priority);
        }
        if ($municipality !== '') {
            $where[] = '(from_drrmo.name = ? OR to_drrmo.name = ?)';
            $params[] = $municipality;
            $params[] = $municipality;
        }

        if (!empty($where)) {
            $sql = preg_replace('/\s+ORDER BY[\s\S]*/', '', $sql);
            $sql .= ' WHERE ' . implode(' AND ', $where) . ' ORDER BY r.requestGroupId IS NULL ASC, r.requestGroupId ASC, r.requestDate DESC, r.requestID DESC LIMIT 1000';
        } else {
            $sql .= ' LIMIT 1000';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $requests = array_map(function($row) {
            // Clean municipality labels: drop prefixes like CDRRMO/MDRRMO and suffix " DRRMO"
            $cleanName = function($name) {
                $n = (string)($name ?? '');
                // remove prefix variants
                $n = preg_replace('/^(?:[A-Z]{0,3}DRRMO\s+)/', '', $n);
                // remove suffix " DRRMO"
                $n = preg_replace('/\s+DRRMO$/', '', $n);
                // remove leading descriptors
                $n = preg_replace('/^(City of\s+|Municipality of\s+)/i', '', $n);
                // remove trailing " City"
                $n = preg_replace('/\s+City$/i', '', $n);
                return trim($n);
            };
            $formatId = 'REQ-' . (string)$row['requestID'];
            return [
                // Core identifiers
                'id' => $formatId,
                'requestID' => (int)$row['requestID'],
                'requestGroupId' => $row['requestGroupId'] ?? null,
                // Parties
                'fromMunicipality' => $cleanName($row['fromMunicipality'] ?? ''),
                'toMunicipality' => $cleanName($row['toMunicipality'] ?? ''),
                // Resource details
                'resourceType' => $row['resourceName'] ?? '',
                'category' => $row['category'] ?? '',
                'unit' => $row['unit'] ?? '',
                'description' => $row['description'] ?? '',
                // Quantities and status
                'quantity' => isset($row['quantity']) ? (int)$row['quantity'] : 0,
                'priority' => $row['priority'] ?: 'medium',
                'status' => $row['status'] ?: 'pending',
                'requestDate' => $row['requestDate'] ?? '',
                'responseDate' => $row['responseDate'] ?? '',
                'returnRequestedAt' => $row['returnRequestedAt'] ?? '',
                'returnedAt' => $row['returnedAt'] ?? '',
                'notes' => $row['notes'] ?? '',
                // Extended modal fields (if present in table)
                'urgency' => $row['urgency'] ?? '',
                'deliveryDate' => $row['deliveryDate'] ?? '',
                'deliveryLocation' => $row['deliveryLocation'] ?? '',
                'requestorName' => $row['requestorName'] ?? '',
                'contactPhone' => $row['contactPhone'] ?? '',
                'contactEmail' => $row['contactEmail'] ?? '',
                'alternativeContact' => $row['alternativeContact'] ?? '',
                'purposeOfRequest' => $row['purposeOfRequest'] ?? '',
                'incidentReference' => $row['incidentReference'] ?? '',
                'expectedDuration' => $row['expectedDuration'] ?? '',
                'returnDate' => $row['returnDate'] ?? '',
                'transportationMethod' => $row['transportationMethod'] ?? '',
                'specialHandling' => $row['specialHandling'] ?? '',
                'approvingAuthority' => $row['approvingAuthority'] ?? '',
                'headApprovalStatus' => $row['head_approval_status'] ?? '',
                'headApprovedBy' => $row['head_approved_by'] ?? '',
                'approverTitle' => $row['approverTitle'] ?? '',
                'approverSignature' => $row['approverSignature'] ?? '',
                'budgetCode' => $row['budgetCode'] ?? '',
                'emergencyContact' => $row['emergencyContact'] ?? '',
                // Redundant labels for simple filters
                'municipality' => str_replace(' DRRMO', '', $row['fromMunicipality'] ?? '')
            ];
        }, $rows);

        echo json_encode(['success' => true, 'requests' => $requests]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    error_log('[monitor_requests_api] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>


