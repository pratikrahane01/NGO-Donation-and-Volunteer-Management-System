<?php
// simulate_ajax.php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 1; // Super Admin
$_SESSION['csrf_token'] = 'test';
$_GET['modal'] = 'campaign_form';

// Prevent actual redirect
require_once __DIR__ . '/admin_campaigns.php';
