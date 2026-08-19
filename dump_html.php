<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 5; // Coordinator
$_SESSION['csrf_token'] = 'dummy';
$_SESSION['full_name'] = 'Test Coord';
$_SESSION['email'] = 'test@test.com';

ob_start();
require 'coordinator_tasks.php';
$html = ob_get_clean();
file_put_contents('debug_tasks.html', $html);
echo "HTML dumped.";
