<?php
require __DIR__ . '/../../app/config.php';

// Nur POST-Anfragen erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("❌ Ungültige Anfrage.");
}

// CSRF-Token prüfen
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("❌ CSRF-Token ungültig oder fehlt!");
}

// Interne ID prüfen
if (!isset($_POST['id']) || empty($_POST['id'])) {
    $_SESSION['error'] = "❌ Fehler: Keine gültige Kunden-ID übergeben.";
    header("Location: kundenarchiv.php");
    exit();
}

$id = trim($_POST['id']);

// Prüfen, ob der Kunde existiert und archiviert wurde
$stmt = $GLOBALS['pdo']->prepare("SELECT id FROM kunden WHERE id = ? AND deleted_at IS NOT NULL");
$stmt->execute([$id]);
$existierenderKunde = $stmt->fetchColumn();

if (!$existierenderKunde) {
    $_SESSION['error'] = "❌ Fehler: Kunde nicht gefunden oder nicht im Archiv.";
    header("Location: kundenarchiv.php");
    exit();
}

// Wiederherstellen: deleted_at zurücksetzen
$stmt = $GLOBALS['pdo']->prepare("UPDATE kunden SET deleted_at = NULL WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['success'] = "✅ Kunde erfolgreich wiederhergestellt.";
header("Location: kundenarchiv.php");
exit();
?>
