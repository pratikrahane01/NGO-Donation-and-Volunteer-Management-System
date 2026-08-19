<?php
require 'config/database.php';
$pdo = getDatabase();
$stmt = $pdo->query('SELECT id, title, coordinator_id, status FROM events ORDER BY id DESC LIMIT 5;');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
