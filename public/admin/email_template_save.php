<?php
// email_template_save.php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';

// Sicherheitsprüfungen
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Ungültige Anfrage");
}

// CSRF-Schutz
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Sicherheitstoken ungültig");
}

// Daten einlesen und validieren
$template_id = trim($_POST['id'] ?? '');
$name = trim($_POST['name'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$body = $_POST['body'] ?? '';
$description = trim($_POST['description'] ?? '');

// Validierung
if (empty($template_id) || empty($name) || empty($subject) || empty($body)) {
    $_SESSION['error'] = "Bitte alle Pflichtfelder ausfüllen.";
    header("Location: email_template_editor.php" . (!empty($template_id) ? "?id=" . urlencode($template_id) : ""));
    exit();
}

// Prüfen, ob ID bereits existiert (für Neuanlage)
$check_stmt = $pdo->prepare("SELECT COUNT(*) FROM email_templates WHERE id = ? AND id != ?");
$check_stmt->execute([$template_id, $template_id]); // Bei Update ist zweiter Parameter identisch
if ($check_stmt->fetchColumn() > 0) {
    $_SESSION['error'] = "Die Template-ID '$template_id' existiert bereits. Bitte wählen Sie eine andere ID.";
    header("Location: email_template_editor.php" . (!empty($template_id) ? "?id=" . urlencode($template_id) : ""));
    exit();
}

try {
    // Prüfen, ob Update oder Neuanlage
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM email_templates WHERE id = ?");
    $check_stmt->execute([$template_id]);
    $exists = $check_stmt->fetchColumn() > 0;
    
    if ($exists) {
        // Update
        $stmt = $pdo->prepare("
            UPDATE email_templates SET
                name = ?,
                subject = ?,
                body = ?,
                description = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$name, $subject, $body, $description, $template_id]);
        $_SESSION['success'] = "E-Mail-Template '$name' wurde aktualisiert.";
    } else {
        // Neuanlage
        $stmt = $pdo->prepare("
            INSERT INTO email_templates
                (id, name, subject, body, description)
            VALUES 
                (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$template_id, $name, $subject, $body, $description]);
        $_SESSION['success'] = "E-Mail-Template '$name' wurde erfolgreich erstellt.";
    }
    
    // Zurück zur Übersicht
    header("Location: email_templates.php");
    exit();
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Datenbankfehler: " . $e->getMessage();
    header("Location: email_template_editor.php" . (!empty($template_id) ? "?id=" . urlencode($template_id) : ""));
    exit();
}
?>
<input type="hidden" name="subject" id="form_subject" value="">
<input type="hidden" name="body" id="form_body" value="">