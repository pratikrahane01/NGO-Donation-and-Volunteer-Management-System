<?php
require 'config/database.php';
$pdo = getDatabase();
$stmt = $pdo->query('SHOW TABLES');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
?>
