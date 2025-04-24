<?php
// Starte die Session, falls sie nicht bereits gestartet wurde
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Starte Output Buffering, um Weiterleitungen zu ermöglichen
ob_start();

// Einbinden der Konfiguration, Rechte und Header
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/permissions.php';
require __DIR__ . '/../assets/header.php';

// Aktiviere die Fehleranzeige (nur in der Entwicklung)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Erstelle einen CSRF-Token, falls noch nicht vorhanden
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Saubere URL für Redirects
$clean_url = strtok($_SERVER["REQUEST_URI"], '?');

// Logging-Funktion
function log_action($message) {
    error_log("[" . date('Y-m-d H:i:s') . "] Debug: " . $message);
}

// Funktion zum Abrufen aller Einstellungen nach Kategorie
function getSettingsByCategory() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM tms_einstellungen WHERE kategorie != 'design' ORDER BY kategorie, schluessel");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $categorized = [];
    foreach ($settings as $setting) {
        $categorized[$setting['kategorie']][] = $setting;
    }
    
    return $categorized;
}

// Funktion zur Überprüfung und ggf. Erstellung der Mailer-Einstellung
function ensureMailerSettingExists() {
    global $pdo;
    
    // Prüfen, ob die Mailer-Einstellung bereits existiert
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tms_einstellungen WHERE schluessel = 'mailer_aktiv'");
    $stmt->execute();
    $exists = (int) $stmt->fetchColumn();
    
    if ($exists === 0) {
        // Einstellung erstellen, falls sie noch nicht existiert
        $stmt = $pdo->prepare("INSERT INTO tms_einstellungen 
            (schluessel, wert, beschreibung, typ, kategorie) 
            VALUES ('mailer_aktiv', '1', 'E-Mail-Versand aktivieren oder deaktivieren', 'bool', 'system')");
        $stmt->execute();
        
        log_action("Mailer-Einstellung wurde automatisch erstellt.");
    }
}

// Stelle sicher, dass die Mailer-Einstellung existiert
ensureMailerSettingExists();

// Einstellung speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    // CSRF-Schutz
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "❌ CSRF-Token ungültig!";
        header("Location: system_einstellungen.php");
        exit;
    }
    
    $updates = 0;
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $settingKey = substr($key, 8); // 'setting_' entfernen
            $stmt = $pdo->prepare("UPDATE tms_einstellungen SET wert = ? WHERE schluessel = ?");
            if ($stmt->execute([$value, $settingKey])) {
                $updates++;
            }
        }
    }
    
    if ($updates > 0) {
        $_SESSION['success'] = "✅ {$updates} Einstellungen wurden aktualisiert.";
    } else {
        $_SESSION['info'] = "ℹ️ Es wurden keine Änderungen vorgenommen.";
    }
    
    header("Location: system_einstellungen.php");
    exit;
}

// Alle Einstellungen nach Kategorie abrufen
$settingsByCategory = getSettingsByCategory();
?>

<div class="container mt-5">
    <h1 class="fw-bold">Systemeinstellungen</h1>
    <p>Hier können Sie grundlegende Einstellungen für das TMS-System vornehmen.</p>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info">
            <?= htmlspecialchars($_SESSION['info']) ?>
            <?php unset($_SESSION['info']); ?>
        </div>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="action" value="save_settings">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <?php foreach ($settingsByCategory as $category => $settings): ?>
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h3 class="card-title mb-0 text-capitalize"><?= htmlspecialchars($category) ?></h3>
                </div>
                <div class="card-body">
                    <?php foreach ($settings as $setting): ?>
                        <div class="mb-3">
                            <label for="setting_<?= htmlspecialchars($setting['schluessel'] ?? '') ?>" class="form-label">
                                <?= htmlspecialchars($setting['beschreibung'] ?? '') ?>
                            </label>
                            
                            <?php if ($setting['typ'] === 'bool'): ?>
                                <select class="form-select" name="setting_<?= htmlspecialchars($setting['schluessel'] ?? '') ?>" id="setting_<?= htmlspecialchars($setting['schluessel'] ?? '') ?>">
                                    <option value="1" <?= ($setting['wert'] ?? '') == '1' ? 'selected' : '' ?>>Aktiviert</option>
                                    <option value="0" <?= ($setting['wert'] ?? '') == '0' ? 'selected' : '' ?>>Deaktiviert</option>
                                </select>
                            <?php elseif ($setting['typ'] === 'select' && !empty($setting['optionen'])): ?>
                                <select class="form-select" name="setting_<?= htmlspecialchars($setting['schluessel']) ?>" id="setting_<?= htmlspecialchars($setting['schluessel']) ?>">
                                    <?php foreach (json_decode($setting['optionen'] ?? '[]', true) as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value ?? '') ?>" <?= ($setting['wert'] ?? '') == $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($setting['typ'] === 'number'): ?>
                                <input type="number" step="0.01" class="form-control" name="setting_<?= htmlspecialchars($setting['schluessel']) ?>" id="setting_<?= htmlspecialchars($setting['schluessel']) ?>" value="<?= htmlspecialchars($setting['wert']) ?>">
                            <?php elseif ($setting['typ'] === 'html'): ?>
                                <textarea class="form-control" name="setting_<?= htmlspecialchars($setting['schluessel']) ?>" id="setting_<?= htmlspecialchars($setting['schluessel']) ?>" rows="4"><?= htmlspecialchars($setting['wert']) ?></textarea>
                            <?php else: ?>
                                <input type="text" class="form-control" name="setting_<?= htmlspecialchars($setting['schluessel'] ?? '') ?>" id="setting_<?= htmlspecialchars($setting['schluessel'] ?? '') ?>" value="<?= htmlspecialchars($setting['wert'] ?? '') ?>">
                            <?php endif; ?>
                            
                            <small class="form-text text-muted">Schlüssel: <?= htmlspecialchars($setting['schluessel'] ?? '') ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
    </form>
</div>

<?php 
include __DIR__ . '/../assets/footer.php';
ob_end_flush();
?>