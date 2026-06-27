<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get form data
    $hazardType = $_POST['hazardType'] ?? '';
    $severity = $_POST['hazardSeverity'] ?? '';
    $location = $_POST['hazardLocation'] ?? '';
    $hazardDate = $_POST['hazardDate'] ?? '';
    $statusInput = $_POST['hazardStatus'] ?? '';
    $peopleAffected = intval($_POST['peopleAffected'] ?? 0);
    $description = $_POST['hazardDescription'] ?? '';
    $informationSource = $_POST['hazardSource'] ?? '';
    $contactInfo = $_POST['contactInfo'] ?? '';
    
    // Validate required fields
    if (empty($hazardType) || empty($severity) || empty($location)) {
        throw new Exception('Required fields are missing');
    }
    
    // Get user session data
    if (!isset($_SESSION['municipality_id']) || !$_SESSION['municipality_id']) {
        throw new Exception('User session not found. Please log in again.');
    }
    if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
        throw new Exception('User ID not found. Please log in again.');
    }
    
    $drrmoID = $_SESSION['municipality_id'];
    $reportedBy = $_SESSION['user_id'];
    
    // Convert severity to database format
    $intensityMap = [
        'low' => 'Low',
        'medium' => 'Medium', 
        'high' => 'High',
        'critical' => 'Critical'
    ];
    $intensity = $intensityMap[$severity] ?? 'Medium';
    
    // Convert hazard type to database format
    $hazardTypeMap = [
        'flash-flood' => 'Flash Flood',
        'earthquake' => 'Earthquake',
        'typhoon' => 'Typhoon',
        'landslide' => 'Landslide',
        'fire' => 'Fire',
        'drought' => 'Drought',
        'volcanic' => 'Volcanic Activity',
        'other' => 'Other'
    ];
    $hazardTypeFormatted = $hazardTypeMap[$hazardType] ?? $hazardType;
    
    // Convert information source to database format
    $sourceMap = [
        'direct-observation' => 'Direct Observation',
        'citizen-report' => 'Citizen Report',
        'government-agency' => 'Government Agency',
        'media-report' => 'Media Report',
        'satellite-data' => 'Satellite Data',
        'other' => 'Other'
    ];
    $informationSourceFormatted = $sourceMap[$informationSource] ?? $informationSource;
    
    // Use explicit coordinates from form if provided; this gives highest accuracy
    $latitude = isset($_POST['hazardLatitude']) && $_POST['hazardLatitude'] !== ''
        ? floatval($_POST['hazardLatitude']) : null;
    $longitude = isset($_POST['hazardLongitude']) && $_POST['hazardLongitude'] !== ''
        ? floatval($_POST['hazardLongitude']) : null;

    // If coordinates are still missing, try to parse from location text as a fallback
    if ($latitude === null || $longitude === null) {
        if (preg_match('/(\d+\.?\d*),\s*(\d+\.?\d*)/', $location, $matches)) {
            $latitude = floatval($matches[1]);
            $longitude = floatval($matches[2]);
        }
    }

    // Validate coordinates within Zamboanga del Sur bounds
    if ($latitude === null || $longitude === null
        || $latitude < 6.5 || $latitude > 8.5
        || $longitude < 122.0 || $longitude > 124.5) {
        throw new Exception('Please select a valid location within Zamboanga del Sur (lat/lng required).');
    }
    
    // Use form date or current timestamp
    $reportedAt = $hazardDate ? date('Y-m-d H:i:s', strtotime($hazardDate)) : date('Y-m-d H:i:s');
    
    // Normalize status (enum: active, monitoring, resolved, false-alarm)
    $status = strtolower(trim($statusInput));
    if (!in_array($status, ['active','monitoring','resolved'], true)) {
        $status = 'active';
    }

    // If editHazardId present, update instead of insert
    $editId = isset($_POST['editHazardId']) && $_POST['editHazardId'] !== '' ? (int)$_POST['editHazardId'] : 0;
    if ($editId > 0) {
        // Ownership/admin/same-municipality check
        $check = $pdo->prepare('SELECT reportedBy, drrmoID FROM hazards WHERE hazardID = ?');
        $check->execute([$editId]);
        $own = $check->fetch(PDO::FETCH_ASSOC);
        $allow = $own && (
            (int)($own['reportedBy'] ?? 0) === (int)$reportedBy || 
            (int)($own['drrmoID'] ?? 0) === (int)$drrmoID || 
            in_array(($_SESSION['user_type'] ?? ''), ['admin','emergency_coordinator'], true)
        );
        if (!$allow) {
            throw new Exception('You are not allowed to edit this hazard');
        }

        $sql = "UPDATE hazards SET drrmoID = ?, hazardType = ?, description = ?, intensity = ?, location = ?, latitude = ?, longitude = ?, reportedAt = ?, status = ?, affectedPopulation = ?, informationSource = ?, contactInfo = ?, resolvedAt = ? WHERE hazardID = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $drrmoID,
            $hazardTypeFormatted,
            $description,
            $intensity,
            $location,
            $latitude,
            $longitude,
            $reportedAt,
            $status,
            $peopleAffected,
            $informationSourceFormatted,
            $contactInfo,
            $status === 'resolved' ? date('Y-m-d H:i:s') : null,
            $editId
        ]);
    } else {
        // Insert into database
        $sql = "INSERT INTO hazards (
            drrmoID, hazardType, description, intensity, location, 
            latitude, longitude, reportedAt, status, reportedBy, 
            affectedPopulation, informationSource, contactInfo, resolvedAt
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $drrmoID,
            $hazardTypeFormatted,
            $description,
            $intensity,
            $location,
            $latitude,
            $longitude,
            $reportedAt,
            $status,
            $reportedBy,
            $peopleAffected,
            $informationSourceFormatted,
            $contactInfo,
            $status === 'resolved' ? date('Y-m-d H:i:s') : null
        ]);
    }
    
    if ($result) {
        $hazardID = $editId > 0 ? $editId : $pdo->lastInsertId();

        // --- Optional image uploads (hazardImages[]) ---
        $savedImages = [];
        if (isset($_FILES['hazardImages']) && is_array($_FILES['hazardImages']['name'] ?? null)) {
            $names = $_FILES['hazardImages']['name'];
            $tmpNames = $_FILES['hazardImages']['tmp_name'];
            $errors = $_FILES['hazardImages']['error'];
            $sizes = $_FILES['hazardImages']['size'];
            $count = count($names);

            if ($count > 0) {
                $maxFiles = 5;
                $maxSize = 5 * 1024 * 1024; // 5MB
                if ($count > $maxFiles) {
                    throw new Exception('Please upload up to 5 images only.');
                }

                // Create table if missing (so feature works without manual migration)
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `hazard_images` (
                      `imageID` INT NOT NULL AUTO_INCREMENT,
                      `hazardID` INT NOT NULL,
                      `filePath` VARCHAR(500) NOT NULL,
                      `uploadedAt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`imageID`),
                      KEY `idx_hazard_images_hazard` (`hazardID`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                $projectRoot = realpath(__DIR__ . '/..');
                if (!$projectRoot) {
                    throw new Exception('Upload path misconfigured.');
                }
                $uploadDir = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'hazards' . DIRECTORY_SEPARATOR . (string)$hazardID;
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        throw new Exception('Failed to create upload directory.');
                    }
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];

                $ins = $pdo->prepare('INSERT INTO hazard_images (hazardID, filePath) VALUES (?, ?)');

                for ($i = 0; $i < $count; $i++) {
                    $errCode = $errors[$i] ?? UPLOAD_ERR_NO_FILE;
                    if ($errCode === UPLOAD_ERR_NO_FILE) continue;
                    if ($errCode !== UPLOAD_ERR_OK) {
                        throw new Exception('Image upload failed.');
                    }
                    $tmp = $tmpNames[$i] ?? '';
                    if (!$tmp || !is_uploaded_file($tmp)) {
                        throw new Exception('Invalid uploaded image.');
                    }
                    $size = (int)($sizes[$i] ?? 0);
                    if ($size <= 0 || $size > $maxSize) {
                        throw new Exception('Each image must be 5MB or smaller.');
                    }
                    $mime = $finfo->file($tmp) ?: '';
                    if (!isset($allowed[$mime])) {
                        throw new Exception('Only JPG, PNG, or WebP images are allowed.');
                    }
                    $ext = $allowed[$mime];

                    $rand = bin2hex(random_bytes(16));
                    $filename = 'hazard_' . (string)$hazardID . '_' . $rand . '.' . $ext;
                    $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;

                    if (!move_uploaded_file($tmp, $dest)) {
                        throw new Exception('Failed to save uploaded image.');
                    }

                    $relativePath = 'uploads/hazards/' . (string)$hazardID . '/' . $filename;
                    $ins->execute([(int)$hazardID, $relativePath]);
                    $savedImages[] = $relativePath;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => $editId > 0 ? 'Hazard updated successfully' : 'Hazard report submitted successfully',
            'hazardID' => $hazardID,
            'images' => $savedImages
        ]);
    } else {
        throw new Exception('Failed to insert hazard report');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
