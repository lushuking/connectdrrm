<?php
/**
 * Fast Location Service for Barangay Matching
 * Optimized for quick loading and searching with "Barangay + Municipality" format
 * 
 * Endpoints:
 *   GET ?action=search&q=balangasan&limit=10
 *   GET ?action=coordinates&location=Balangasan, Pagadian City
 *   GET ?action=municipality&name=Pagadian
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration
$dataDir = __DIR__ . '/data';
$indexFile = $dataDir . '/barangay_index.json';

// Cache the index in memory for better performance
static $locationIndex = null;

function loadLocationIndex($indexFile) {
    global $locationIndex;
    
    if ($locationIndex !== null) {
        return $locationIndex;
    }
    
    if (!file_exists($indexFile)) {
        return ['error' => 'Location index not found. The index was built during initial setup. If needed, the build script is in config/archive/.'];
    }
    
    $json = file_get_contents($indexFile);
    $data = json_decode($json, true);
    
    if (!$data || !isset($data['locations'])) {
        return ['error' => 'Invalid location index format'];
    }
    
    $locationIndex = $data;
    return $locationIndex;
}

function normalizeQuery($query) {
    return trim(strtolower($query));
}

function searchLocations($query, $limit = 10, $type = null) {
    $index = loadLocationIndex($GLOBALS['indexFile']);
    
    if (isset($index['error'])) {
        return $index;
    }
    
    $query = normalizeQuery($query);
    if (empty($query)) {
        return [];
    }
    
    $results = [];
    $exactMatches = [];
    $startMatches = [];
    $containsMatches = [];
    
    foreach ($index['locations'] as $location) {
        // Skip if type filter is specified and doesn't match
        if ($type && $location['type'] !== $type) {
            continue;
        }
        
        $score = 0;
        $matchType = '';
        
        // Check all search terms
        foreach ($location['search_terms'] as $term) {
            if ($term === $query) {
                $score = 100; // Exact match
                $matchType = 'exact';
                break;
            } elseif (strpos($term, $query) === 0) {
                $score = max($score, 80); // Starts with query
                $matchType = 'starts';
            } elseif (strpos($term, $query) !== false) {
                $score = max($score, 60); // Contains query
                $matchType = 'contains';
            }
        }
        
        if ($score > 0) {
            $result = [
                'id' => $location['id'],
                'name' => $location['name'],
                'display_name' => $location['display_name'],
                'type' => $location['type'],
                'coordinates' => $location['coordinates'],
                'population' => $location['population'],
                'score' => $score,
                'match_type' => $matchType
            ];
            
            if ($location['type'] === 'barangay') {
                $result['municipality'] = $location['municipality'];
                $result['municipality_type'] = $location['municipality_type'];
            }
            
            // Categorize by match type for sorting
            if ($matchType === 'exact') {
                $exactMatches[] = $result;
            } elseif ($matchType === 'starts') {
                $startMatches[] = $result;
            } else {
                $containsMatches[] = $result;
            }
        }
    }
    
    // Sort each category by population (descending) then by name length
    $sortFunction = function($a, $b) {
        if ($a['population'] !== $b['population']) {
            return $b['population'] - $a['population'];
        }
        return strlen($a['name']) - strlen($b['name']);
    };
    
    usort($exactMatches, $sortFunction);
    usort($startMatches, $sortFunction);
    usort($containsMatches, $sortFunction);
    
    // Combine results with exact matches first
    $allResults = array_merge($exactMatches, $startMatches, $containsMatches);
    
    return array_slice($allResults, 0, $limit);
}

function getCoordinates($locationName) {
    $index = loadLocationIndex($GLOBALS['indexFile']);
    
    if (isset($index['error'])) {
        return $index;
    }
    
    $query = normalizeQuery($locationName);
    
    foreach ($index['locations'] as $location) {
        foreach ($location['search_terms'] as $term) {
            if ($term === $query) {
                return [
                    'name' => $location['name'],
                    'display_name' => $location['display_name'],
                    'coordinates' => $location['coordinates'],
                    'type' => $location['type'],
                    'found' => true
                ];
            }
        }
    }
    
    return ['found' => false, 'message' => 'Location not found'];
}

function getMunicipalityBarangays($municipalityName) {
    $index = loadLocationIndex($GLOBALS['indexFile']);
    
    if (isset($index['error'])) {
        return $index;
    }
    
    $query = normalizeQuery($municipalityName);
    $barangays = [];
    
    foreach ($index['locations'] as $location) {
        if ($location['type'] === 'barangay' && 
            normalizeQuery($location['municipality']) === $query) {
            $barangays[] = [
                'name' => $location['name'],
                'display_name' => $location['display_name'],
                'coordinates' => $location['coordinates'],
                'population' => $location['population']
            ];
        }
    }
    
    // Sort by population descending
    usort($barangays, function($a, $b) {
        return $b['population'] - $a['population'];
    });
    
    return $barangays;
}

// Handle requests
$action = $_GET['action'] ?? 'search';

try {
    switch ($action) {
        case 'search':
            $query = $_GET['q'] ?? '';
            $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
            $type = $_GET['type'] ?? null; // 'barangay' or 'municipality'
            
            $results = searchLocations($query, $limit, $type);
            echo json_encode($results);
            break;
            
        case 'coordinates':
            $location = $_GET['location'] ?? '';
            $result = getCoordinates($location);
            echo json_encode($result);
            break;
            
        case 'municipality':
            $name = $_GET['name'] ?? '';
            $barangays = getMunicipalityBarangays($name);
            echo json_encode([
                'municipality' => $name,
                'barangays' => $barangays,
                'count' => count($barangays)
            ]);
            break;
            
        case 'stats':
            $index = loadLocationIndex($indexFile);
            if (isset($index['error'])) {
                echo json_encode($index);
            } else {
                echo json_encode($index['metadata']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action. Use: search, coordinates, municipality, or stats']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
