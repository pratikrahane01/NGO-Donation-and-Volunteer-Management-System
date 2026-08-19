<?php
// privacy.php
session_start();
require_once __DIR__ . '/config/config.php';
$pageTitle = "Privacy Policy | " . APP_NAME;
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
        <h1>Privacy Policy</h1>
        <p>Your privacy is important to us. It is Arohan Foundation's policy to respect your privacy regarding any information we may collect from you across our website, <a href="<?php echo APP_URL; ?>"><?php echo APP_URL; ?></a>, and other sites we own and operate.</p>
        
        <h2>1. Information we collect</h2>
        <p>We only ask for personal information when we truly need it to provide a service to you. We collect it by fair and lawful means, with your knowledge and consent.</p>

        <h2>2. Use of Information</h2>
        <p>We use your information to facilitate donations, coordinate volunteer activities, and improve our services.</p>

        <h2>3. Data Security</h2>
        <p>We don't share any personally identifying information publicly or with third-parties, except when required to by law.</p>

        <p style="margin-top: 50px;"><em>Last updated: July 2026</em></p>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
