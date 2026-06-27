<?php
ini_set('memory_limit', '1024M');
// Lightweight local geocoding service for Report Hazard modal
// Endpoints:
//   GET ?action=search&q=balangasan[&municipality=Pagadian]
//   GET ?action=coordinates&location=Balangasan, Pagadian City

header('Content-Type: application/json');

// Allow from same origin only; adjust if needed
header('Access-Control-Allow-Origin: *');

// Configuration
$dataDir = __DIR__ . '/data';
$indexFile = $dataDir . '/geocoder_index.json';

// Zamboanga del Sur rough bounds (lat, lng)
$ZDS_BOUNDS = [
    'minLat' => 6.5,
    'maxLat' => 8.5,
    'minLng' => 122.0,
    'maxLng' => 124.5,
];

function within_bounds($lat, $lng, $bounds) {
    return $lat >= $bounds['minLat'] && $lat <= $bounds['maxLat'] && $lng >= $bounds['minLng'] && $lng <= $bounds['maxLng'];
}

function load_geojson($dataDir) {
    // Try to find a .geojson file in config/data. Prefer files that look like province extracts
    $candidates = glob($dataDir . '/*.geojson');
    if (!$candidates) return [null, 'No GeoJSON files found in config/data'];

    // Prioritize smaller, named files if multiple exist
    usort($candidates, function($a, $b) {
        return filesize($a) <=> filesize($b);
    });

    // Use the first one for now
    $file = $candidates[0];

    // Attempt streaming decode; fallback to file_get_contents
    $json = @file_get_contents($file);
    if ($json === false) return [null, 'Failed to read GeoJSON file'];
    $data = json_decode($json, true);
    if (!$data) return [null, 'Invalid GeoJSON JSON'];
    if (!isset($data['type']) || $data['type'] !== 'FeatureCollection') return [null, 'Unsupported GeoJSON structure'];
    return [$data, null];
}

function feature_to_entry($feature) {
    $props = $feature['properties'] ?? [];
    $geom  = $feature['geometry'] ?? null;
    if (!$geom) return null;

    // Coordinates: pick a representative point
    $coords = null;
    $type = strtolower($geom['type'] ?? '');
    if ($type === 'point') {
        // GeoJSON order is [lng, lat]
        $lng = $geom['coordinates'][0] ?? null;
        $lat = $geom['coordinates'][1] ?? null;
        if ($lat !== null && $lng !== null) $coords = [$lat, $lng];
    } else if ($type === 'linestring' && !empty($geom['coordinates'][0])) {
        $first = $geom['coordinates'][0];
        $lng = $first[0] ?? null;
        $lat = $first[1] ?? null;
        if ($lat !== null && $lng !== null) $coords = [$lat, $lng];
    } else if ($type === 'polygon' && !empty($geom['coordinates'][0][0])) {
        $first = $geom['coordinates'][0][0];
        $lng = $first[0] ?? null;
        $lat = $first[1] ?? null;
        if ($lat !== null && $lng !== null) $coords = [$lat, $lng];
    }

    if (!$coords) return null;

    // Name and context
    $name = $props['name'] ?? $props['display_name'] ?? $props['addr:place'] ?? '';
    $alt  = $props['alt_name'] ?? '';
    $typeProp = $props['place'] ?? $props['highway'] ?? $props['boundary'] ?? $props['amenity'] ?? '';

    // Build context string from common address-like tags
    $contextParts = [];
    foreach (['addr:street','addr:suburb','addr:city','addr:municipality','addr:district','addr:province','addr:state','addr:region'] as $key) {
        if (!empty($props[$key])) $contextParts[] = $props[$key];
    }
    if (!empty($props['is_in'])) $contextParts[] = $props['is_in'];
    $context = implode(', ', array_unique(array_filter($contextParts)));

    return [
        'name' => $name ?: $alt,
        'alt' => $alt,
        'coordinates' => $coords,
        'type' => $typeProp ?: 'place',
        'context' => $context,
    ];
}

function normalize_query($s) {
    return trim(mb_strtolower($s ?? ''));
}

$action = $_GET['action'] ?? 'search';

// Prefer using a prebuilt lightweight index if available
$entries = [];
if (file_exists($indexFile)) {
    $json = @file_get_contents($indexFile);
    if ($json !== false) {
        $arr = json_decode($json, true);
        if (is_array($arr)) {
            foreach ($arr as $row) {
                if (!isset($row['lat']) || !isset($row['lng'])) continue;
                $lat = (float)$row['lat'];
                $lng = (float)$row['lng'];
                if (!within_bounds($lat, $lng, $ZDS_BOUNDS)) continue;
                $entries[] = [
                    'name' => $row['name'] ?? '',
                    'alt' => $row['alt'] ?? '',
                    'coordinates' => [$lat, $lng],
                    'type' => $row['type'] ?? 'place',
                    'context' => $row['context'] ?? '',
                ];
            }
        }
    }
}

// Fallback: load GeoJSON directly (may require high memory on large files)
if (empty($entries)) {
    [$geo, $err] = load_geojson($dataDir);
    if ($err) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $err . ' (tip: the index build script is in config/archive/ if needed)']);
        exit;
    }
    foreach (($geo['features'] ?? []) as $feature) {
        $entry = feature_to_entry($feature);
        if (!$entry) continue;
        $lat = $entry['coordinates'][0];
        $lng = $entry['coordinates'][1];
        if (!within_bounds($lat, $lng, $ZDS_BOUNDS)) continue;
        $entries[] = $entry;
    }
}

if ($action === 'search') {
    $q = normalize_query($_GET['q'] ?? '');
    $municipality = normalize_query($_GET['municipality'] ?? '');
    if ($q === '') {
        echo json_encode([]);
        exit;
    }

    // Score by name/context match; prefer startsWith then includes
    $scored = [];
    foreach ($entries as $e) {
        $nameL = normalize_query($e['name']);
        $ctxL = normalize_query($e['context']);
        $score = -1;
        if ($nameL !== '') {
            if (strpos($nameL, $q) === 0) $score = max($score, 3);
            if ($score < 0 && strpos($nameL, $q) !== false) $score = max($score, 2);
        }
        if ($ctxL !== '') {
            if (strpos($ctxL, $q) === 0) $score = max($score, 1.5);
            if ($score < 0 && strpos($ctxL, $q) !== false) $score = max($score, 1);
        }
        if ($score < 0) continue;

        // Light municipality bias if provided
        if ($municipality && strpos($ctxL, $municipality) !== false) {
            $score += 0.5;
        }

        $scored[] = ['score' => $score, 'entry' => $e];
    }

    // Sort by score desc, then shorter name
    usort($scored, function($a, $b) {
        if ($a['score'] === $b['score']) {
            return strlen($a['entry']['name']) <=> strlen($b['entry']['name']);
        }
        return $a['score'] < $b['score'] ? 1 : -1;
    });

    // Limit results
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $results = array_slice(array_map(function($x) { return $x['entry']; }, $scored), 0, $limit);
    echo json_encode($results);
    exit;
}

if ($action === 'coordinates') {
    $location = trim($_GET['location'] ?? '');
    if ($location === '') {
        echo json_encode(['success' => false, 'error' => 'Missing location']);
        exit;
    }
    $q = normalize_query($location);
    $best = null;
    $bestScore = -INF;
    foreach ($entries as $e) {
        $nameL = normalize_query($e['name']);
        $ctxL = normalize_query($e['context']);
        $score = -1;
        if ($nameL !== '') {
            if (strpos($nameL, $q) === 0) $score = max($score, 3);
            if ($score < 0 && strpos($nameL, $q) !== false) $score = max($score, 2);
        }
        if ($ctxL !== '') {
            if (strpos($ctxL, $q) === 0) $score = max($score, 1.5);
            if ($score < 0 && strpos($ctxL, $q) !== false) $score = max($score, 1);
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $e;
        }
    }

    if ($best) {
        echo json_encode(['success' => true, 'coordinates' => $best['coordinates']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not found']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
 