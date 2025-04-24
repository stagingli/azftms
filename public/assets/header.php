<?php
// assets/header.php – zentraler Header
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/helpers.php';

$appName = getSystemSetting('app_name', 'AZF-Verwaltung');
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="googlebot" content="noindex, nofollow, noarchive">
    <title><?= htmlspecialchars($appName) ?><?= isset($page_title) ? ' - ' . htmlspecialchars($page_title) : '' ?></title>
    <!-- Bootstrap 5.3 CSS (CDN) -->
    <link rel="stylesheet" href="/assets/css/styles-new.css">

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
    
    <!-- jQuery (nur falls benötigt) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap 5.3 JS (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Eigene Styles -->
    <link rel="stylesheet" href="/assets/css/footer.css">
    <link rel="stylesheet" href="/assets/css/badge bg-warning.css">
    <link rel="stylesheet" href="/assets/css/styles-custom.css">
</head>
<body>

<!-- Navigation einbinden -->
<?php include __DIR__ . '/navbar.php'; ?>
