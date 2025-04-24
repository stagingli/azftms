<?php
// email_template_delete.php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';

// CSRF-Schutz
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Sicherheitstoken ungültig.";
    header("Location: email_templates.php");
    exit();
}

// ID prüfen
$template_id = $_GET['id'] ?? '';
if (empty($template_id)) {
    $_SESSION['error'] = "Keine Template-ID angegeben.";
    header("Location: email_templates.php");
    exit();
}

// Liste der geschützten Templates, die nicht gelöscht werden dürfen
$protected_templates = ['fahrer_neue_fahrt', 'neue_fruehe_fahrt'];

if (in_array($template_id, $protected_templates)) {
    $_SESSION['error'] = "Das Template '$template_id' ist ein Systemtemplate und kann nicht gelöscht werden.";
    header("Location: email_templates.php");
    exit();
}

try {
    // Template-Namen für Erfolgsmeldung abrufen
    $stmt = $pdo->prepare("SELECT name FROM email_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template_name = $stmt->fetchColumn();
    
    if (!$template_name) {
        $_SESSION['error'] = "Template nicht gefunden.";
        header("Location: email_templates.php");
        exit();
    }
    
    // Prüfen, ob Template in Verwendung ist
    $usage_check = false;
    
    // Hier können Sie weitere Nutzungsprüfungen einbauen, z.B.:
    // - Prüfen, ob das Template in einer Automatisierung verwendet wird
    // - Prüfen, ob das Template in einer Einstellung referenziert wird
    
    if ($usage_check) {
        $_SESSION['error'] = "Das Template '$template_name' wird noch verwendet und kann nicht gelöscht werden.";
        header("Location: email_templates.php");
        exit();
    }
    
    // Template löschen
    $stmt = $pdo->prepare("DELETE FROM email_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    
    $_SESSION['success'] = "Template '$template_name' wurde erfolgreich gelöscht.";
} catch (PDOException $e) {
    $_SESSION['error'] = "Fehler beim Löschen des Templates: " . $e->getMessage();
}

header("Location: email_templates.php");
exit();