<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 2;
$_GET['modal'] = 'campaign_form';
$_SESSION['csrf_token'] = 'test';
require 'ngo_campaigns.php';
