<?php
// settings_handler.php
// JSON-Antwort – daher korrekter Content-Type
header("Content-Type: application/json; charset=UTF-8");

// Zentrale Konfiguration inkl. Session, CSRF und Datenbank
require __DIR__ . '/../../app/config.php';
// Zugriffskontrolle: Nur Admins dürfen Einstellungen ändern
require __DIR__ . '/../../app/permissions.php';

// Debugging: Optionale Log-Ausgaben (optional, hier via writeLog() in config.php)
writeLog("🔍 SESSION: " . print_r($_SESSION, true), 'DEBUG', SECURITY_LOG_FILE);
writeLog("📡 POST-Daten: " . print_r($_POST, true), 'DEBUG', SECURITY_LOG_FILE);

// Nur POST-Anfragen erlauben
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "❌ Nur POST-Anfragen erlaubt"]);
    exit();
}

// CSRF-Token prüfen
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    writeLog("❌ CSRF-Fehlversuch von IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unbekannt'), 'SECURITY', SECURITY_LOG_FILE);
    echo json_encode(["status" => "error", "message" => "❌ CSRF-Token ungültig"]);
    exit();
}

// Aktion prüfen
if (empty($_POST['action'])) {
    writeLog("❌ Fehlende Aktion! POST-Daten: " . print_r($_POST, true), 'ERROR', SECURITY_LOG_FILE);
    echo json_encode(["status" => "error", "message" => "❌ Fehlende Aktion"]);
    exit();
}

$action = $_POST['action'];
writeLog("🔄 Aktuelle Aktion: " . $action, 'INFO', SECURITY_LOG_FILE);

try {
    switch ($action) {
        case 'add_setting':
            if (empty(trim($_POST['kategorie'] ?? '')) || empty(trim($_POST['wert'] ?? ''))) {
                throw new Exception("❌ Fehlende oder ungültige Daten (kategorie oder wert).");
            }
            $kategorie = htmlspecialchars(trim($_POST['kategorie']), ENT_QUOTES, 'UTF-8');
            $wert = htmlspecialchars(trim($_POST['wert']), ENT_QUOTES, 'UTF-8');
            
            $stmt = $pdo->prepare("INSERT INTO einstellungen (kategorie, wert) VALUES (?, ?)");
            if (!$stmt->execute([$kategorie, $wert])) {
                throw new Exception("❌ Fehler beim Speichern der Einstellung.");
            }
            
            $newId = $pdo->lastInsertId();
            echo json_encode(["status" => "success", "message" => "✅ Einstellung gespeichert!", "id" => $newId]);
            exit();
        
        case 'update_setting':
            if (!isset($_POST['id']) || !is_numeric($_POST['id']) ||
                empty(trim($_POST['kategorie'] ?? '')) || empty(trim($_POST['wert'] ?? ''))) {
                throw new Exception("❌ Fehlende oder ungültige Daten für die Aktualisierung.");
            }
            
            $id = intval($_POST['id']);
            $kategorie = htmlspecialchars(trim($_POST['kategorie']), ENT_QUOTES, 'UTF-8');
            $wert = htmlspecialchars(trim($_POST['wert']), ENT_QUOTES, 'UTF-8');
            
            $stmt = $pdo->prepare("UPDATE einstellungen SET kategorie = ?, wert = ? WHERE id = ?");
            if (!$stmt->execute([$kategorie, $wert, $id])) {
                throw new Exception("❌ Fehler beim Aktualisieren der Einstellung.");
            }
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("❌ Keine Änderung vorgenommen oder Einstellung nicht gefunden.");
            }
            
            echo json_encode([
                "status"    => "success", 
                "message"   => "✅ Einstellung aktualisiert!", 
                "id"        => $id,
                "kategorie" => $kategorie,
                "wert"      => $wert
            ]);
            exit();
        
        case 'delete_setting':
            if (!isset($_POST["id"]) || !is_numeric($_POST["id"])) {
                throw new Exception("❌ Ungültige ID.");
            }
            $id = intval($_POST["id"]);
            $stmt = $pdo->prepare("DELETE FROM einstellungen WHERE id = ?");
            if (!$stmt->execute([$id])) {
                throw new Exception("❌ Fehler beim Löschen der Einstellung.");
            }
            echo json_encode(["status" => "success", "message" => "🗑️ Einstellung gelöscht!"]);
            exit();
        
        case 'get_setting':
            if (!isset($_POST["id"]) || !is_numeric($_POST["id"])) {
                throw new Exception("❌ Ungültige ID.");
            }
            $id = intval($_POST["id"]);
            $stmt = $pdo->prepare("SELECT * FROM einstellungen WHERE id = ?");
            if (!$stmt->execute([$id])) {
                throw new Exception("❌ Fehler beim Abrufen der Einstellung.");
            }
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$setting) {
                throw new Exception("❌ Einstellung nicht gefunden.");
            }
            echo json_encode(["status" => "success", "setting" => $setting]);
            exit();
        
        default:
            throw new Exception("❌ Unbekannte Aktion: " . htmlspecialchars($action, ENT_QUOTES, 'UTF-8'));
    }
} catch (Exception $e) {
    writeLog("❌ Fehler in settings_handler.php: " . $e->getMessage(), 'ERROR', SECURITY_LOG_FILE);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit();
}

echo json_encode(["status" => "error", "message" => "❌ Keine gültigen POST-Daten empfangen"]);
?>
