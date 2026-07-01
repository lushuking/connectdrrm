<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT u.email, u.role, d.name as drrmoName FROM users u LEFT JOIN drrmo d ON u.drrmoID = d.drrmoID LIMIT 10");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "Email: {$row['email']}, Role: {$row['role']}, DRRMO: {$row['drrmoName']}\n";
}
?>
