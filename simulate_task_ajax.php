<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 5; // Event Coordinator
$_SESSION['csrf_token'] = 'test';
$_GET['modal'] = 'create_task';

require_once __DIR__ . '/coordinator_tasks.php';
