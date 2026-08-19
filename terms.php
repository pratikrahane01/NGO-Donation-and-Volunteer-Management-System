<?php
// terms.php
session_start();
require_once __DIR__ . '/config/config.php';
$pageTitle = "Terms of Service | " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <main style="padding: 100px 20px; max-width: 800px; margin: auto;">
        <h1>Terms of Service</h1>
        <p>By accessing our website, you are agreeing to be bound by these terms of service, all applicable laws and regulations, and agree that you are responsible for compliance with any applicable local laws.</p>
        
        <h2>1. Use License</h2>
        <p>Permission is granted to temporarily download one copy of the materials (information or software) on Arohan Foundation's website for personal, non-commercial transitory viewing only.</p>

        <h2>2. Donations and Refunds</h2>
        <p>Donations made are non-refundable. Please ensure all details are correct before completing a transaction.</p>

        <h2>3. Volunteer Conduct</h2>
        <p>Volunteers are expected to act professionally and follow the guidelines set by the Event Coordinators.</p>

        <p style="margin-top: 50px;"><em>Last updated: July 2026</em></p>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
