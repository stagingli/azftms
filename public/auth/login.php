<?php
// Output Buffering starten
ob_start();

// Konfiguration einbinden (sorgt auch für Session-Handling, Logging-Einstellungen etc.)
require_once __DIR__ . '/../../app/config.php';

// Hilfsfunktion, um Debug-Nachrichten in der Browser-Konsole auszugeben (nur bei aktivem DEBUG_MODE)
function debug_to_console($data) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo '<script>console.log(' . json_encode($data) . ');</script>';
    }
}

debug_to_console(['Session CSRF-Token' => $_SESSION['csrf_token'] ?? 'nicht gesetzt']);

// Schreibe Log zum Login-Versuch (nutzt die in config.php definierte LOG_LOGIN_EVENTS-Konstante)
writeLog("Login-Versuch von IP " . $_SERVER['REMOTE_ADDR'], 'INFO', SECURITY_LOG_FILE);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF-Token prüfen
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        writeLog("⚠️ CSRF-Fehlversuch von IP " . $_SERVER['REMOTE_ADDR'], 'SECURITY', SECURITY_LOG_FILE);
        debug_to_console("CSRF-Token ungültig oder nicht gesetzt.");
        die("❌ CSRF-Token ungültig! Lade die Seite neu.");
    }
    
    // Login-Input (kann E-Mail, Name oder Nutzername sein)
    $login_input = trim($_POST["email"]); // Feldname bleibt "email", kann aber einen Namen oder Nutzernamen enthalten
    debug_to_console(['Eingabe Login' => $login_input]);
    
    if (empty($login_input)) {
        $_SESSION['error'] = "❌ Bitte geben Sie eine E-Mail, einen Namen oder Nutzernamen ein.";
        header("Location: /index.php");
        exit;
    }
    
    // Prüfen, ob der Input eine gültige E-Mail-Adresse ist
    if (filter_var($login_input, FILTER_VALIDATE_EMAIL)) {
        // Login input ist eine E-Mail-Adresse: säubern und in Kleinbuchstaben umwandeln
        $login_param = strtolower(filter_var($login_input, FILTER_SANITIZE_EMAIL));
        $searchQuery = "SELECT id, email, name, nutzername, passwort FROM nutzer WHERE email = ?";
    } else {
        // Andernfalls wird der Input als Benutzername oder Name interpretiert
        $login_param = $login_input;
        $searchQuery = "SELECT id, email, name, nutzername, passwort FROM nutzer WHERE name = ? OR nutzername = ?";
    }
    
    // Passwort aus POST abrufen und trimmen
    $password = trim($_POST["password"]);
    debug_to_console(['Eingabe Passwort' => str_repeat('*', strlen($password))]);
    
    // Benutzer anhand der Login-Daten abrufen (vorausgesetzt, $pdo wurde in config.php initialisiert)
    $stmt = $pdo->prepare($searchQuery);
    if (filter_var($login_input, FILTER_VALIDATE_EMAIL)) {
        $stmt->execute([$login_param]);
    } else {
        $stmt->execute([$login_param, $login_param]);
    }
    $user = $stmt->fetch();
    debug_to_console(['Gefundener Nutzer' => $user ? 'Ja (ID: ' . $user['id'] . ')' : 'Nein']);
    
    if ($user && password_verify($password, $user['passwort'])) {
        // Starte mit einer sauberen Session
        $_SESSION = array();
        session_regenerate_id(true);
        
        // Neuen CSRF-Token erstellen
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // Rollen des Nutzers abrufen
        $stmt_roles = $pdo->prepare("
            SELECT r.name 
            FROM rollen r
            JOIN nutzer_rolle nr ON r.id = nr.rolle_id
            WHERE nr.nutzer_id = ?
        ");
        $stmt_roles->execute([$user["id"]]);
        $rollen = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);
        debug_to_console(['Nutzerrollen' => $rollen]);
        
        // Falls keine Rollen gefunden wurden, füge die 'neu'-Rolle hinzu
        if (empty($rollen)) {
            try {
                $role_stmt = $pdo->prepare("SELECT id FROM rollen WHERE name = 'neu'");
                $role_stmt->execute();
                $new_role_id = $role_stmt->fetchColumn();
                
                if ($new_role_id) {
                    $insert_stmt = $pdo->prepare("INSERT IGNORE INTO nutzer_rolle (nutzer_id, rolle_id) VALUES (?, ?)");
                    $insert_stmt->execute([$user["id"], $new_role_id]);
                    $rollen = ["neu"];
                    writeLog("Neue 'neu'-Rolle für Nutzer ID {$user['id']} zugewiesen", 'INFO', SECURITY_LOG_FILE);
                }
            } catch (Exception $e) {
                writeLog("Fehler beim Zuweisen der 'neu'-Rolle: " . $e->getMessage(), 'ERROR', ERROR_LOG_FILE);
            }
        }
        
        if (empty($rollen)) {
            $rollen = ["neu"];
        }
        
        // Setze Nutzerdaten in die Session
        $_SESSION["user"] = [
            "id"         => $user["id"],
            "email"      => strtolower($user["email"]),
            "name"       => $user["name"],
            "nutzername" => $user["nutzername"],
            "rollen"     => $rollen
        ];
        
        if (LOG_LOGIN_EVENTS) {
            writeLog("🔐 Login erfolgreich: {$user['email']} (Rollen: " . implode(",", $rollen) . ") von IP: " . $_SERVER['REMOTE_ADDR'], 'SECURITY', SECURITY_LOG_FILE);
        }
        
        writeLog("Finale Session nach Login: " . json_encode($_SESSION), 'DEBUG', DEBUGGING_LOG_FILE);
        
        // Weiterleitung basierend auf den Rollen
        $role_redirects = [
            "admin"  => "/admin/dashboard_admin.php",
            "fahrer" => "/fahrer/dashboard_fahrer.php",
            "neu"    => "/neueruser/dashboard_neuer_user.php"
        ];
        $redirect_url = "/index.php";  // Standardziel
        foreach ($rollen as $rolle) {
            if (isset($role_redirects[$rolle])) {
                $redirect_url = $role_redirects[$rolle];
                debug_to_console("Weiterleitung zu: " . $redirect_url);
                break;
            }
        }
        writeLog("Weiterleitung nach Login zu: " . $redirect_url, 'DEBUG', DEBUGGING_LOG_FILE);
        
        header("Location: " . $redirect_url);
        exit;
    } else {
        if (LOG_LOGIN_EVENTS) {
            writeLog("⚠️ Fehlgeschlagener Login für: {$login_param} von IP: " . $_SERVER['REMOTE_ADDR'], 'SECURITY', SECURITY_LOG_FILE);
        }
        
        debug_to_console("Falsche Login-Daten für: " . $login_input);
        $_SESSION['error'] = "❌ Falsche Login-Daten.";
        header("Location: /index.php");
        exit;
    }
}

// Falls jemand direkt über GET auf login.php zugreift:
header("Location: /index.php");
exit;

ob_end_flush();
?>
