<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 5; // Event Coordinator
$_SESSION['csrf_token'] = 'test';
$_GET['modal'] = 'edit_task';
$_GET['id'] = 1;

require_once __DIR__ . '/coordinator_tasks.php';
