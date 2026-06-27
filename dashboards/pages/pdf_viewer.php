<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../../login.php');
    exit;
}

// Get request ID or groupId from URL parameter
$requestId = isset($_GET['id']) ? intval($_GET['id']) : null;
$groupId = isset($_GET['groupId']) ? trim($_GET['groupId']) : null;

if (!$requestId && !$groupId) {
    die('Request ID or Group ID is required');
}

// Load ZIP code mapping
$zipCodesPath = __DIR__ . '/../../config/data/zds_zipcodes.json';
$zipCodes = [];
if (file_exists($zipCodesPath)) {
    $zipCodes = json_decode(file_get_contents($zipCodesPath), true);
    if (!is_array($zipCodes)) { $zipCodes = []; }
}
// Build a normalized lookup (lowercased keys, stripped of DRRMO tokens)
$zipIndex = [];
$normalizeName = function($name) {
    $n = (string)$name;
    $n = preg_replace('/^\s*(Municipality of|City of)\s+/i', '', $n);
    $n = preg_replace('/\s+City\s*$/i', '', $n);
    $n = preg_replace('/\b(?:[A-Z]{0,3}DRRMO)\b/i', '', $n); // remove CDRRMO/MDRRMO/DRRMO
    $n = preg_replace('/\s{2,}/', ' ', $n);
    return strtolower(trim($n));
};
foreach ($zipCodes as $k => $v) {
    $zipIndex[$normalizeName($k)] = $v;
}

// Fetch request details - handle both single request and grouped requests
try {
    $requests = [];
    
    if ($groupId) {
        // Fetch all requests in the group
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   res.resourceName, res.unit,
                   from_drrmo.name as fromDRRMOName,
                   to_drrmo.name as toDRRMOName,
                   original_drrmo.name as originalToDRRMOName
            FROM requests r
            JOIN resources res ON r.resourceID = res.resourceID
            JOIN drrmo from_drrmo ON r.fromDRRMO = from_drrmo.drrmoID
            JOIN drrmo to_drrmo ON r.toDRRMO = to_drrmo.drrmoID
            LEFT JOIN drrmo original_drrmo ON r.originalToDRRMO = original_drrmo.drrmoID
            WHERE r.requestGroupId = ?
            ORDER BY r.requestID ASC
        ");
        $stmt->execute([$groupId]);
        $requests = $stmt->fetchAll();
        
        if (empty($requests)) {
            die('No requests found for this group');
        }
        
        // Use first request as the base request (for shared fields)
        $request = $requests[0];
        
        // Use originalToDRRMOName if available (for head approval routing), otherwise use toDRRMOName
        // Apply to group requests too (not just single requests)
        $request['toDRRMOName'] = !empty($request['originalToDRRMOName']) ? $request['originalToDRRMOName'] : $request['toDRRMOName'];
    } else {
        // Single request
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   res.resourceName, res.unit,
                   from_drrmo.name as fromDRRMOName,
                   to_drrmo.name as toDRRMOName,
                   original_drrmo.name as originalToDRRMOName
            FROM requests r
            JOIN resources res ON r.resourceID = res.resourceID
            JOIN drrmo from_drrmo ON r.fromDRRMO = from_drrmo.drrmoID
            JOIN drrmo to_drrmo ON r.toDRRMO = to_drrmo.drrmoID
            LEFT JOIN drrmo original_drrmo ON r.originalToDRRMO = original_drrmo.drrmoID
            WHERE r.requestID = ?
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        
        if (!$request) {
            die('Request not found');
        }
        
        $requests = [$request]; // Single request as array for consistency
        
        // Use originalToDRRMOName if available (for head approval routing), otherwise use toDRRMOName
        $request['toDRRMOName'] = !empty($request['originalToDRRMOName']) ? $request['originalToDRRMOName'] : $request['toDRRMOName'];
    }

    // Try to fetch municipality logos from DB (tolerant to schema)
    $getDrrmoLogo = function($pdo, $drrmoId) {
        if (!$drrmoId) return null;
        try {
            $q = $pdo->prepare('SELECT * FROM drrmo WHERE drrmoID = ? LIMIT 1');
            $q->execute([$drrmoId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            // Prefer canonical logo_url if present
            if (!empty($row['logo_url'])) return $row['logo_url'];
            foreach (['logo','logoUrl','seal','sealUrl','emblem','image','logo_path','seal_path'] as $key) {
                if (!empty($row[$key])) return $row[$key];
            }
            return null;
        } catch (Exception $e) { return null; }
    };
    $fromLogoUrl = $getDrrmoLogo($pdo, $request['fromDRRMO'] ?? null);
    // Use originalToDRRMO for logo if available (for head approval routing), otherwise use toDRRMO
    $targetToDRRMO = !empty($request['originalToDRRMO']) ? $request['originalToDRRMO'] : ($request['toDRRMO'] ?? null);
    $toLogoUrl   = $getDrrmoLogo($pdo, $targetToDRRMO);

    // Normalize logo URLs for current page location (/dashboards/pages/)
    $normalizeUrl = function($u) {
        if (!$u) return $u;
        if (preg_match('~^(?:https?:)?//|^/|^data:~i', $u)) return $u; // absolute or data URL
        if (strpos($u, 'assets/') === 0) return '../../' . $u;        // project-relative
        if (strpos($u, './') === 0) return $u;                         // already relative
        return '../../' . ltrim($u, '/');
    };
    $fromLogoUrl = $normalizeUrl($fromLogoUrl);
    $toLogoUrl   = $normalizeUrl($toLogoUrl);

    // Fetch officials (operator and DRRMO head) from DB if columns exist
    $getDrrmoOfficials = function($pdo, $drrmoId) {
        if (!$drrmoId) return [];
        try {
            $q = $pdo->prepare('SELECT * FROM drrmo WHERE drrmoID = ? LIMIT 1');
            $q->execute([$drrmoId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) return [];
            $pick = function($row, $cands){ foreach ($cands as $k) { if (isset($row[$k]) && trim((string)$row[$k]) !== '') return $row[$k]; } return null; };
            return [
                'operatorName'   => $pick($row, ['operator_name','operatorName','prepared_by','requestorName']),
                'operatorTitle'  => $pick($row, ['operator_title','operatorTitle','prepared_by_title','requestorTitle']),
                'drrmoHead'      => $pick($row, ['drrmo_head','drrmoHead','head_name','approvingAuthority']),
                'drrmoHeadTitle' => $pick($row, ['drrmo_head_title','drrmoHeadTitle','head_title','approverTitle'])
            ];
        } catch (Exception $e) { return []; }
    };
    $fromOfficials = $getDrrmoOfficials($pdo, $request['fromDRRMO'] ?? null);
    // Use targetToDRRMO (calculated above) for officials
    $toOfficials   = $getDrrmoOfficials($pdo, $targetToDRRMO);

    // Auto-match ZIP code based on municipality name
    $municipalityName = $request['fromDRRMOName'];
    $cleanKey = $normalizeName($municipalityName);
    // Get ZIP code from normalized mapping
    $zipCode = isset($zipIndex[$cleanKey]) && $zipIndex[$cleanKey] ? $zipIndex[$cleanKey] : '7011';
    
    // Calculate return date if not set, based on deliveryDate + expectedDuration
    $calculateReturnDate = function($deliveryDate, $expectedDuration, $returnDate = null) {
        // If returnDate is already set and valid, use it
        if (!empty($returnDate) && $returnDate !== '0000-00-00' && $returnDate !== '1970-01-01') {
            $timestamp = strtotime($returnDate);
            if ($timestamp !== false && $timestamp > 0) {
                return date('F j, Y', $timestamp);
            }
        }
        
        // If no deliveryDate, return fallback
        if (empty($deliveryDate)) {
            return 'the agreed date';
        }
        
        // Calculate based on expectedDuration
        $deliveryTimestamp = strtotime($deliveryDate);
        if ($deliveryTimestamp === false || $deliveryTimestamp <= 0) {
            return 'the agreed date';
        }
        
        $daysToAdd = 7; // Default: 1 week
        
        // Parse expectedDuration and add appropriate days
        if (!empty($expectedDuration)) {
            $duration = strtolower(trim($expectedDuration));
            // Handle "indefinite"
            if ($duration === 'indefinite') {
                $daysToAdd = 365; // 1 year
            } else {
                // Try new format: "number unit" (e.g., "5 days", "2 weeks", "3 months")
                if (preg_match('/^(\d+)\s+(days?|weeks?|months?)$/', $duration, $matches)) {
                    $number = (int)$matches[1];
                    $unit = $matches[2];
                    if (strpos($unit, 'day') === 0) {
                        $daysToAdd = $number;
                    } elseif (strpos($unit, 'week') === 0) {
                        $daysToAdd = $number * 7;
                    } elseif (strpos($unit, 'month') === 0) {
                        $daysToAdd = $number * 30; // Approximate 30 days per month
                    }
                }
                // Fallback: try old format "number-unit" (e.g., "5-days", "2-weeks")
                elseif (preg_match('/^(\d+)-(days|weeks|months)$/', $duration, $matches)) {
                    $number = (int)$matches[1];
                    $unit = $matches[2];
                    if ($unit === 'days') {
                        $daysToAdd = $number;
                    } elseif ($unit === 'weeks') {
                        $daysToAdd = $number * 7;
                    } elseif ($unit === 'months') {
                        $daysToAdd = $number * 30; // Approximate 30 days per month
                    }
                }
            }
        }
        
        $calculated = strtotime("+{$daysToAdd} days", $deliveryTimestamp);
        if ($calculated === false || $calculated <= 0) {
            return 'the agreed date';
        }
        
        return date('F j, Y', $calculated);
    };
    
    // Calculate return date - handle null/empty/invalid dates
    $returnDateFormatted = $calculateReturnDate(
        $request['deliveryDate'] ?? null,
        $request['expectedDuration'] ?? null,
        $request['returnDate'] ?? null
    );
    
    // Determine signature visibility based on request status
    $requestStatus = strtolower(trim($request['status'] ?? ''));
    
    // Check if request is approved (exclude 'pending' - only show after actual approval)
    $isApproved = in_array($requestStatus, ['approved', 'fulfilled', 'received', 'return pending', 'returned']);
    
    // Check if viewing from receiving municipality (toDRRMO) - for transparency, always show full signatures
    $currentMunicipalityId = $_SESSION['municipality_id'] ?? null;
    $isReceivingMunicipality = ($currentMunicipalityId && $currentMunicipalityId == $request['toDRRMO']);
    
    // Check if this is the requesting municipality (fromDRRMO) - for "My Requests" tab
    // If request is not pending_head_approval, it means it's been approved by head and is in "My Requests"
    $isRequestingMunicipality = ($currentMunicipalityId && $currentMunicipalityId == $request['fromDRRMO']);
    $isNotPendingHeadApproval = ($requestStatus !== 'pending_head_approval' && $requestStatus !== 'group_approved_pending' && $requestStatus !== 'group_rejected_pending');
    
    // Show approver signature if:
    // 1. Request is approved (or later statuses) - always show for approved requests
    // 2. Request is in "My Requests" (from requesting municipality and not pending_head_approval) - show head signature
    // 3. Viewing from receiving municipality AND request is approved (for transparency)
    // Note: For pending_head_approval requests, approver signature should NOT show
    // If approved or in "My Requests", we'll load the head signature from profile if not in request data
    $showApproverSignature = $isApproved || ($isRequestingMunicipality && $isNotPendingHeadApproval) || ($isReceivingMunicipality && $isApproved);
    
} catch (Exception $e) {
    die('Error fetching request: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Request Document - <?php echo htmlspecialchars($request['resourceName']); ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* Screen styles below. Print overrides in @media print */
        @media print {
            /* Fix paper size and margins for consistent layout */
            @page { size: letter portrait; margin: 0.5in; }
            html, body { width: 8.5in; min-height: 11in; background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

            /* Hide viewer controls and any non-document UI */
            .no-print, .btn, .modal, .modal-backdrop { display: none !important; }

            /* Only render the document content */
            body * { visibility: hidden; }
            #print-content, #print-content * { visibility: visible; }
            #print-content {
                position: static;
                width: auto;
                max-width: none;
                margin: 0;
                padding: 0;  /* margins handled by @page */
                box-shadow: none !important;
                background: #fff !important;
            }

            body {
                margin: 0;
                padding: 0;
                font-family: Arial, Helvetica, sans-serif !important;
                font-size: 12pt;
                line-height: 1.35;
            }

            * { font-family: Arial, Helvetica, sans-serif !important; }

            .header { margin-bottom: 16pt; }
            .header p { margin: 0.5pt 0; font-family: Arial, Helvetica, sans-serif; font-size: 12pt; }
            .header .municipality { font-size: 12pt; font-weight: 600; letter-spacing: 0.2px; }
            .header .office { font-size: 12pt; font-weight: 700; letter-spacing: 0.2px; text-transform: uppercase; }

            .date, .addressee, .greeting, .body-text { margin-bottom: 10pt; font-size: 12pt; }
            .addressee p { margin: 1pt 0; }
            .addressee p:first-child { font-weight: 700; }
            .addressee p:not(:first-child) { font-style: italic; text-transform: uppercase; }
            .body-text { text-align: justify; }

            .signature-section { margin-top: 15pt; }
            .signature-block p { margin: 0; padding: 0; line-height: 1; }
            .signature-img { display: block; max-height: 80px; margin: 0 auto; padding: 0; margin-bottom: -50px; }
            .signature-section .name { font-weight: 600; margin: -2px 0 0 0; padding: 0; line-height: 1; text-transform: none; }
            .signature-section .title { font-style: normal; font-size: 10pt; margin: 0; padding: 0; line-height: 1; text-transform: capitalize; }
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.35;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            font-size: 12pt;
        }
        
        .no-print {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        #print-content {
            background: white;
            max-width: 8.5in; /* match paper width */
            margin: 0 auto;
            padding: 0.25in 0.3in; /* lighter top/sides for screen preview */
            box-shadow: 0 0 6px rgba(0,0,0,0.06);
        }
        
.header {
            margin-bottom: 12pt; /* tighter to reduce whitespace */
            text-align: center;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        
.text-section {
            flex: 1;
            text-align: center;
            padding-top: 0;
            margin-top: 2pt; /* minimal offset; avoid large header height for export */
        }
        
.logo-section {
            flex: 0 0 auto;
            width: 42pt;
            display: flex;
            align-items: center; /* prevent tall header */
            justify-content: center;
            padding-bottom: 0;
            margin-top: 0;
        }
.logo-section img {
            max-height: 32pt;
            max-width: 32pt;
            transform: none;
            display: none; /* overridden to block when data present */
        }
        
        .logo-section {
            flex: 0 0 auto;
            width: 80pt;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-section img {
            max-height: 80pt;
            max-width: 80pt;
            display: none;
        }
        
        .header p {
            margin: 2px 0;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }
        
        .header .municipality {
            font-weight: 600;
            font-size: 12pt;
            letter-spacing: 0.2px;
        }
        
        .header .office {
            font-size: 12pt;
            font-weight: 700;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }
        
        .date {
            margin-bottom: 20px;
        }
        
        .addressee {
            margin-bottom: 20px;
        }
        
        .addressee p {
            margin: 2px 0;
        }
        .addressee p:first-child { font-weight: 700; }
        .addressee p:not(:first-child) { font-style: italic; text-transform: uppercase; }
        
        .greeting {
            margin-bottom: 20px;
        }
        
        .body-text {
            margin-bottom: 20px;
            text-align: justify;
        }
        
        .signature-section {
            margin-top: 40px;
        }
        
        .signature-block {
            text-align: left;
            display: inline-block;
            min-width: 260px;
            margin-top: 18px;
            line-height: 1;
        }
        
        .signature-block > p:first-child {
            margin: 0 0 6px 0;
            line-height: 1.2;
        }
        
        .signature-img {
            display: block;
            max-height: 100px;
            width: auto;
            margin: 0 auto;
            padding: 0;
            object-fit: contain;
            margin-bottom: -50px;
        }
        
        .signature-section .name {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12pt;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }
        
        .signature-section .title {
            font-style: italic;
            color: #000;
            font-size: 11.5pt;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-secondary" onclick="window.close()" type="button">
            <span class="material-icons">arrow_back</span>
            Back
        </button>
        <button class="btn btn-primary" onclick="window.print()" type="button">
            <span class="material-icons">print</span>
            Print
        </button>
        <button class="btn btn-success" onclick="exportPDF()" type="button">
            <span class="material-icons">download</span>
            Export PDF
        </button>
    </div>

    <div id="print-content">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="logo-section">
                    <img id="municipalityLogoLeft" alt="From Municipality Logo" src="<?php echo !empty($fromLogoUrl) ? htmlspecialchars($fromLogoUrl) : '';?>" style="<?php echo !empty($fromLogoUrl) ? 'display:block;' : 'display:none;'; ?> max-height:80pt; max-width:80pt;">
                </div>
                <div class="text-section">
                    <p>Republic of the Philippines</p>
                    <p>Province of Zamboanga Del Sur</p>
                    <p class="municipality">MUNICIPALITY OF <?php echo strtoupper(htmlspecialchars($request['toDRRMOName'])); ?></p>
                    <p id="zipLine">=<?php echo htmlspecialchars($zipCode); ?>=</p>
                    <p class="office">MUNICIPAL DISASTER RISK REDUCTION & MANAGEMENT OFFICE</p>
                </div>
                <div class="logo-section">
                    <img id="drrmoLogoRight" alt="To Municipality Logo" src="<?php echo !empty($toLogoUrl) ? htmlspecialchars($toLogoUrl) : '';?>" style="<?php echo !empty($toLogoUrl) ? 'display:block;' : 'display:none;'; ?> max-height:80pt; max-width:80pt;">
                </div>
            </div>
        </div>

        <!-- Date -->
        <div class="date">
            <p><?php echo date('F j, Y', strtotime($request['requestDate'])); ?></p>
        </div>

        <!-- Addressee -->
        <!-- Note: toDRRMOName is already set to use originalToDRRMOName if available (for head approval routing) -->
        <div class="addressee">
            <p><?php echo htmlspecialchars($toOfficials['drrmoHead'] ?? $request['toDRRMOName']); ?></p>
            <p><?php 
                $headTitle = $toOfficials['drrmoHeadTitle'] ?? '';
                echo htmlspecialchars($headTitle !== '' ? $headTitle : 'Municipal Disaster Risk Reduction & Management Officer'); 
            ?></p>
            <p><?php echo strtoupper(htmlspecialchars($request['toDRRMOName'])); ?></p>
        </div>

        <!-- Greeting -->
        <div class="greeting">
            <p>Good day Sir!</p>
        </div>

        <!-- Main Body -->
        <?php 
        ?>
        <div class="body-text">
            <?php if (count($requests) > 1): ?>
                <!-- Multiple resources - list all -->
                <p>
                    The <?php echo htmlspecialchars($request['fromDRRMOName']); ?> respectfully requests the following resources from <?php echo htmlspecialchars($request['toDRRMOName']); ?>:
                </p>
            </div>
            <div class="body-text" style="margin-left: 20px;">
                <ol style="margin: 0; padding-left: 20px;">
                    <?php foreach ($requests as $req): ?>
                        <li style="margin-bottom: 8px;">
                            <strong><?php echo htmlspecialchars($req['resourceName']); ?></strong> - 
                            <?php echo htmlspecialchars($req['quantity']); ?> <?php echo htmlspecialchars($req['unit'] ?? 'unit(s)'); ?> 
                            (Priority: <?php echo strtolower(htmlspecialchars($req['priority'])); ?>)
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <div class="body-text">
                <p>
                    We kindly ask that these resources be delivered to <?php echo htmlspecialchars($request['deliveryLocation'] ?? 'specified location'); ?> by <?php echo $request['deliveryDate'] ? date('F j, Y', strtotime($request['deliveryDate'])) : 'the requested date'; ?><?php if (!empty($returnDateFormatted) && $returnDateFormatted !== 'the agreed date'): ?>, and returned by <?php echo $returnDateFormatted; ?><?php endif; ?>. This request is for <?php echo htmlspecialchars($request['purposeOfRequest'] ?? 'emergency response'); ?> purposes. For coordination, please contact the <?php echo htmlspecialchars($request['fromDRRMOName']); ?> staff at <?php echo htmlspecialchars($request['contactPhone'] ?? 'provided contact'); ?>. <?php if (!empty($request['notes'])) { echo 'Additional notes: ' . htmlspecialchars($request['notes']); } ?>
                </p>
            <?php else: ?>
                <!-- Single resource - original format -->
                <p>
                    The <?php echo htmlspecialchars($request['fromDRRMOName']); ?> respectfully requests <?php echo htmlspecialchars($request['quantity']); ?> unit(s) of <?php echo htmlspecialchars($request['resourceName']); ?> from <?php echo htmlspecialchars($request['toDRRMOName']); ?> with <?php echo strtolower(htmlspecialchars($request['priority'])); ?> priority. We kindly ask that the <?php echo htmlspecialchars($request['resourceName']); ?> be delivered to <?php echo htmlspecialchars($request['deliveryLocation'] ?? 'specified location'); ?> by <?php echo $request['deliveryDate'] ? date('F j, Y', strtotime($request['deliveryDate'])) : 'the requested date'; ?>, and returned by <?php echo $returnDateFormatted; ?>. This request is for <?php echo htmlspecialchars($request['purposeOfRequest'] ?? 'emergency response'); ?> purposes. For coordination, please contact the <?php echo htmlspecialchars($request['fromDRRMOName']); ?> staff at <?php echo htmlspecialchars($request['contactPhone'] ?? 'provided contact'); ?>. <?php if (!empty($request['notes'])) { echo 'Additional notes: ' . htmlspecialchars($request['notes']); } ?>
                </p>
            <?php endif; ?>
            </div>
        <div class="body-text">
            <p>
                Thank you in advance and we look forward to a favorable and considerable response regarding this matter.
            </p>
        </div>
        
        <!-- Signatures -->
        <div class="signature-section">
            <div class="signature-block">
                <p>Prepared by:</p>
                <?php if (!empty($request['requestorSignature'])): ?>
                    <img src="<?php echo htmlspecialchars($request['requestorSignature']); ?>" alt="Requestor Signature" class="signature-img" id="operatorSignatureFromRequest">
                <?php else: ?>
                    <!-- Placeholder for JavaScript to inject operator signature from profile -->
                    <img id="operatorSignatureFromProfile" alt="Requestor Signature" class="signature-img" style="display: none;">
                <?php endif; ?>
                <?php 
                    // Prepared by should reflect the requesting municipality (FROM) profile
                    $preparedName = $fromOfficials['operatorName']
                        ?? ($request['requestorName'] ?? $request['fromDRRMOName']);
                    $preparedTitle = $fromOfficials['operatorTitle']
                        ?? ($request['requestorTitle'] ?? 'Requesting Municipality');
                ?>
                <p class="name" id="operatorNameText"><?php echo htmlspecialchars($preparedName); ?></p>
                <p class="title" id="operatorTitleText"><?php echo htmlspecialchars($preparedTitle); ?></p>
            </div>
        </div>

        <div class="signature-section">
            <div class="signature-block">
                <p>Approved by:</p>
                <?php if ($showApproverSignature && !empty($request['approverSignature'])): ?>
                    <img src="<?php echo htmlspecialchars($request['approverSignature']); ?>" alt="Approver Signature" class="signature-img" id="approverSignatureFromRequest">
                <?php elseif ($showApproverSignature): ?>
                    <!-- Placeholder for JavaScript to inject approver signature from profile -->
                    <img id="approverSignatureFromProfile" alt="Approver Signature" class="signature-img" style="display: none;">
                <?php endif; ?>
                <p class="name"><?php echo htmlspecialchars($request['approvingAuthority'] ?? 'Approving Authority'); ?></p>
                <p class="title"><?php echo htmlspecialchars($request['approverTitle'] ?? 'Municipal Disaster Risk Reduction & Management Officer'); ?></p>
            </div>
        </div>
    </div>

    <script>
    // Apply profile data from localStorage
    (function applyProfileData(){
        try {
            const profile = JSON.parse(localStorage.getItem('municipalityProfile') || '{}');
            // Provide names to JS
            const fromName = <?php echo json_encode($request['fromDRRMOName']); ?>;
            const toName   = <?php echo json_encode($request['toDRRMOName']); ?>;
            const normalize = (s)=>String(s||'')
                .replace(/^(Municipality of|City of)\s+/i,'')
                .replace(/\s+City$/i,'')
                .replace(/\b[A-Z]{0,3}DRRMO\b/ig,'')
                .replace(/\s{2,}/g,' ')
                .trim()
                .toLowerCase();

            // Keys for FROM/TO municipalities (used for mapped data retrieval)
            const fromKey = normalize(fromName);
            const toKey   = normalize(toName);

            // Server-provided officials from DB for the FROM municipality
            const serverOfficialsFrom = <?php echo json_encode($fromOfficials ?? []); ?>;

            // Officials map from localStorage (municipality-specific), if present
            let officialsMap = {};
            try { officialsMap = JSON.parse(localStorage.getItem('municipalityOfficials') || '{}') || {}; } catch(_) {}
            const mappedOfficialsFrom = officialsMap[fromKey] || {};

            // Also try ID-indexed map if available
            let officialsById = {};
            try { officialsById = JSON.parse(localStorage.getItem('municipalityOfficialsById') || '{}') || {}; } catch(_) {}
            const fromId = <?php echo json_encode($request['fromDRRMO'] ?? null); ?>;
            const mappedById = fromId ? (officialsById[String(fromId)] || {}) : {};
            const mergedOfficialsFrom = Object.assign({}, mappedById, mappedOfficialsFrom, serverOfficialsFrom);

            // Operator data (Prepared by)
            // Prefer the requesting municipality's profile (FROM):
            // 1) ID/name-mapped officials from localStorage
            // 2) Server-provided officials
            // 3) Local device profile fallback (municipalityProfile)
            {
                const nameEl = document.getElementById('operatorNameText');
                if (nameEl) {
                    if (mergedOfficialsFrom.operatorName) {
                        nameEl.textContent = mergedOfficialsFrom.operatorName;
                    } else if (profile && profile.operatorName) {
                        nameEl.textContent = profile.operatorName;
                    }
                }
                const titleEl = document.getElementById('operatorTitleText');
                if (titleEl) {
                    if (mergedOfficialsFrom.operatorTitle) {
                        titleEl.textContent = mergedOfficialsFrom.operatorTitle;
                    } else if (profile && profile.operatorTitle) {
                        titleEl.textContent = profile.operatorTitle;
                    }
                }
                
                // Fallback: Load operator signature from profile if not in request
                // Operator signature should ALWAYS show if available (from request OR profile)
                const operatorSignatureFromRequest = document.getElementById('operatorSignatureFromRequest');
                const operatorSignatureFromProfile = document.getElementById('operatorSignatureFromProfile');
                const hasRequestSignature = operatorSignatureFromRequest && operatorSignatureFromRequest.src && !operatorSignatureFromRequest.src.includes('undefined') && operatorSignatureFromRequest.src.trim() !== '';
                
                if (!hasRequestSignature && profile && profile.operatorSignature) {
                    // Use placeholder or create new image element
                    if (operatorSignatureFromProfile) {
                        // Update placeholder
                        operatorSignatureFromProfile.src = profile.operatorSignature;
                        operatorSignatureFromProfile.style.display = 'block';
                    } else {
                        // Create signature image if placeholder doesn't exist
                        const operatorSignatureSection = document.querySelector('.signature-section:first-child');
                        const signatureBlock = operatorSignatureSection ? operatorSignatureSection.querySelector('.signature-block') : null;
                        if (signatureBlock) {
                            const nameEl = signatureBlock.querySelector('.name');
                            if (nameEl) {
                                const img = document.createElement('img');
                                img.src = profile.operatorSignature;
                                img.alt = 'Requestor Signature';
                                img.className = 'signature-img';
                                nameEl.parentNode.insertBefore(img, nameEl);
                            }
                        }
                    }
                }
            }
            
            // DRRMO Head data (Approved by)
            {
                const approverNameEl = document.querySelector('.signature-section:last-child .name');
                if (approverNameEl) {
                    if (mergedOfficialsFrom.drrmoHead) approverNameEl.textContent = mergedOfficialsFrom.drrmoHead;
                    else if (profile && profile.drrmoHead) approverNameEl.textContent = profile.drrmoHead;
                }
                const approverTitleEl = document.querySelector('.signature-section:last-child .title');
                if (approverTitleEl) {
                    const defaultTitle = 'Municipal Disaster Risk Reduction & Management Officer';
                    if (mergedOfficialsFrom.drrmoHeadTitle) approverTitleEl.textContent = mergedOfficialsFrom.drrmoHeadTitle;
                    else if (profile && profile.drrmoHeadTitle) approverTitleEl.textContent = profile.drrmoHeadTitle;
                    else if (!approverTitleEl.textContent.trim()) approverTitleEl.textContent = defaultTitle;
                }
                
                // Fallback: Load approver signature from profile if not in request AND should show
                // For approved requests in "My Requests", always show head signature from profile if available
                const shouldShowApprover = <?php echo $showApproverSignature ? 'true' : 'false'; ?>;
                
                if (shouldShowApprover) {
                    const approverSignatureFromRequest = document.getElementById('approverSignatureFromRequest');
                    const approverSignatureFromProfile = document.getElementById('approverSignatureFromProfile');
                    const hasRequestSignature = approverSignatureFromRequest && approverSignatureFromRequest.src && !approverSignatureFromRequest.src.includes('undefined') && approverSignatureFromRequest.src.trim() !== '';
                    
                    if (!hasRequestSignature && profile && profile.drrmoHeadSignature) {
                        // Use placeholder or create new image element
                        if (approverSignatureFromProfile) {
                            // Update placeholder
                            approverSignatureFromProfile.src = profile.drrmoHeadSignature;
                            approverSignatureFromProfile.style.display = 'block';
                        } else {
                            // Create signature image if placeholder doesn't exist
                            const approverSignatureSection = document.querySelector('.signature-section:last-child');
                            const signatureBlock = approverSignatureSection ? approverSignatureSection.querySelector('.signature-block') : null;
                            if (signatureBlock) {
                                const nameEl = signatureBlock.querySelector('.name');
                                if (nameEl) {
                                    const img = document.createElement('img');
                                    img.src = profile.drrmoHeadSignature;
                                    img.alt = 'Approver Signature';
                                    img.className = 'signature-img';
                                    nameEl.parentNode.insertBefore(img, nameEl);
                                }
                            }
                        }
                    }
                }
            }
            
            // Logo map by municipality stored in localStorage (optional)
            let logoMap = {};
            try { logoMap = JSON.parse(localStorage.getItem('municipalityLogos') || '{}') || {}; } catch(_) {}
            const leftLogo = document.getElementById('municipalityLogoLeft');
            const rightLogo = document.getElementById('drrmoLogoRight');

            // Prefer mapped logos if present; otherwise use profile.logo

            const fix = (s)=>{ if(!s) return s; if (/^(?:https?:)\/\//i.test(s) || s.startsWith('/') || s.startsWith('data:')) return s; if (s.startsWith('assets/')) return '../../'+s; return '../../'+s.replace(/^\/+/, ''); };

            if (leftLogo && !leftLogo.getAttribute('src')) {
                const mapped = logoMap[fromKey] || profile.logo || profile.municipalityLogo || '';
                const src = fix(mapped);
                if (src) { leftLogo.src = src; leftLogo.style.display = 'block'; }
            }
            
            if (rightLogo && !rightLogo.getAttribute('src')) {
                const mappedRight = logoMap[toKey] || profile.drrmoLogo || '';
                const srcR = fix(mappedRight);
                if (srcR) { rightLogo.src = srcR; rightLogo.style.display = 'block'; }
            }
            
            // Mayor data (if needed for approval)
            if (profile && profile.mayorName) {
                console.log('Mayor available:', profile.mayorName);
            }
            
        } catch(e) {
            console.log('Error loading profile data:', e);
        }
    })();

    function exportPDF() {
        const element = document.getElementById('print-content');
        const municipalityName = <?php echo json_encode($request['fromDRRMOName']); ?>;
        const filename = 'Resource_Request_' + municipalityName.replace(/\s+/g, '_') + '.pdf';
        
        const opt = {
            margin: 0.5,
            filename: filename,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2,
                useCORS: true,
                logging: false
            },
            jsPDF: { 
                unit: 'in', 
                format: 'letter', 
                orientation: 'portrait' 
            }
        };
        
        html2pdf().set(opt).from(element).save();
    }
    </script>
</body>
</html>