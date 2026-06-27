<?php
/**
 * Migration script to add originalToDRRMO column to requests table
 * This column stores the intended provider municipality when a request
 * is routed through Head of DRRMO for approval first.
 */

require_once __DIR__ . '/db.php';

try {
    // Check if column already exists
    $checkColumn = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'requests' 
        AND COLUMN_NAME = 'originalToDRRMO'
    ");
    $checkColumn->execute();
    $result = $checkColumn->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo "Column 'originalToDRRMO' already exists in 'requests' table.\n";
        exit(0);
    }
    
    // Add the column
    $alterSql = "ALTER TABLE requests ADD COLUMN originalToDRRMO INT NULL AFTER toDRRMO";
    $pdo->exec($alterSql);
    
    echo "Successfully added 'originalToDRRMO' column to 'requests' table.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

