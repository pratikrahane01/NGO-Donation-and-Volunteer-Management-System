<?php
require_once __DIR__ . '/config/database.php';
$pdo = getDatabase();
$stmt = $pdo->query("SELECT id, title, coordinator_id FROM events;");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $pdo->query("SELECT id, full_name, role_id FROM users WHERE role_id = 5 OR role_id = 4;");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
