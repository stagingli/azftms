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
    header("Location: kundenverwaltung.php");
    exit();
}

$id = trim($_POST['id']);

// Prüfen, ob der Kunde existiert
$stmt = $GLOBALS['pdo']->prepare("SELECT id FROM kunden WHERE id = ?");
$stmt->execute([$id]);
$existierenderKunde = $stmt->fetchColumn();

if (!$existierenderKunde) {
    $_SESSION['error'] = "❌ Fehler: Kunde nicht gefunden.";
    header("Location: kundenverwaltung.php");
    exit();
}

// Soft-Delete: Kunde archivieren (deleted_at setzen)
$stmt = $GLOBALS['pdo']->prepare("UPDATE kunden SET deleted_at = NOW() WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['success'] = "✅ Kunde erfolgreich gelöscht.";
header("Location: kundenverwaltung.php");
exit();
?>
