<?php
/**
 * Hazard Zones Service
 * Fetches hazard-prone areas for a given location
 * This can be extended to call HazardHunterPH API when available
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : 7.8258;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : 123.4370;
$zoom = isset($_GET['zoom']) ? intval($_GET['zoom']) : 8;

// For now, return sample data for Zamboanga del Sur
// TODO: Integrate with HazardHunterPH API when available
// TODO: Contact PHIVOLCS/MGB for actual hazard zone data

$response = [
    'success' => true,
    'location' => ['lat' => $lat, 'lng' => $lng],
    'zones' => [
        'flood' => [],
        'landslide' => [],
        'stormSurge' => [],
        'earthquake' => []
    ],
    'message' => 'Sample hazard zones. Contact PHIVOLCS for official data.'
];

// Sample flood-prone areas for Zamboanga del Sur
// These are example coordinates - replace with actual data
if ($lat >= 7.5 && $lat <= 8.0 && $lng >= 123.0 && $lng <= 124.0) {
    $response['zones']['flood'][] = [
        'coordinates' => [
            [7.80, 123.40],
            [7.85, 123.45],
            [7.82, 123.48],
            [7.78, 123.43],
            [7.80, 123.40]
        ],
        'description' => 'Pagadian City flood-prone area',
        'risk_level' => 'high'
    ];
    
    $response['zones']['landslide'][] = [
        'coordinates' => [
            [7.70, 123.30],
            [7.75, 123.35],
            [7.72, 123.38],
            [7.68, 123.33],
            [7.70, 123.30]
        ],
        'description' => 'Mountainous area - landslide risk',
        'risk_level' => 'high'
    ];
}

// Future: Add HazardHunterPH API integration here
// Example:
// $hazardHunterUrl = "https://hazardhunter.georisk.gov.ph/api/assess?lat={$lat}&lng={$lng}";
// $apiResponse = @file_get_contents($hazardHunterUrl);
// if ($apiResponse) {
//     $hazardData = json_decode($apiResponse, true);
//     // Process and return hazard zones
// }

echo json_encode($response);
?>


























