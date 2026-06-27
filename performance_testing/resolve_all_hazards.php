<?php
require_once __DIR__ . '/../config/db.php';
$affected = $pdo->exec("UPDATE hazards SET status = 'resolved'");
echo "Updated $affected hazards to resolved status.\n";
?>
