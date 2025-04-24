<?php
require __DIR__ . '/../../app/config.php';  // Korrekt: Zwei Ebenen hoch zur app/config.php
require __DIR__ . '/../../app/permissions.php'; // Zugriffskontrolle
require __DIR__ . '/../assets/header.php';  // Header korrekt aus public/assets laden
?>

<div class="container mt-5">
    <h1 class="fw-bold">Freigabe ausstehend</h1>
    <p class="lead">Dein Konto ist noch nicht freigeschaltet. Bitte warte, bis ein Administrator dich aktiviert.</p>
</div>

<?php include __DIR__ . '/../assets/footer.php'; ?>
