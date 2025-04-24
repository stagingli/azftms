<?php
// Stelle sicher, dass in dieser Datei NICHT vor "<?php" bereits irgendeine Ausgabe (inklusive Leerzeilen oder BOM) erfolgt!

// 🔹 Fehleranzeige & Konfiguration
define("DEBUG_MODE", false);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 🔹 Log-Einstellungen
define("LOG_LOGIN_EVENTS", false);
define("LOG_SECURITY_EVENTS", false);
define("LOG_DB_ERRORS", true);

// 🔹 Session-Handling
if (session_status() === PHP_SESSION_NONE) {
    // Absoluten Pfad zum tmp-Verzeichnis festlegen,
    // wobei das Verzeichnis 'tmp' im Projektstamm liegt.
    // Da diese Datei in "app" liegt, geht man einen Ordner nach oben:
    $sessionPath = dirname(__DIR__) . '/tmp';

    // Verzeichnis prüfen und ggf. erstellen
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    
    // Session-Speicherpfad setzen
    session_save_path($sessionPath);
    
    // Session-Cookie-Parameter setzen. Die 'domain' wird leer gelassen,
    // damit automatisch die aktuelle Domain genutzt wird.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Session starten
    session_start();
}

// Erstelle CSRF-Token, falls noch nicht vorhanden
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Session-ID regelmäßig erneuern (Schutz vor Session Fixation)
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    // Speichere wichtige Session-Daten
    $user_data = $_SESSION['user'] ?? null;
    $csrf_token = $_SESSION['csrf_token'] ?? null;
    
    // Regeneriere Session-ID
    session_regenerate_id(true);
    
    // Stelle wichtige Daten wieder her
    if ($user_data) $_SESSION['user'] = $user_data;
    if ($csrf_token) $_SESSION['csrf_token'] = $csrf_token;
    
    // Aktualisiere Zeitstempel
    $_SESSION['last_regeneration'] = time();
}

// 🔹 Logs-Ordner und Dateien
$logDir = __DIR__ . "/logs";
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
define("ERROR_LOG_FILE", $logDir . "/error.log");
define("SECURITY_LOG_FILE", $logDir . "/security.log");
define("DEBUGGING_LOG_FILE", $logDir . "/debugging.log");

// 🔹 SSO-Schlüssel – muss überall identisch sein!
define("SSO_SECRET_KEY", "dein_sicherer_sso_schluessel");

if (!function_exists('writeLog')) {
    function writeLog($message, $level = 'INFO', $logFile = ERROR_LOG_FILE) {
        if (file_exists($logFile) && filesize($logFile) > (5 * 1024 * 1024)) {
            rename($logFile, $logFile . '.' . time());
        }
        $timestamp = date("Y-m-d H:i:s");
        $level = strtoupper($level);
        $logEntry = json_encode([
            'timestamp' => $timestamp,
            'level'     => $level,
            'message'   => $message,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'session_id' => session_id(), // Session-ID für besseres Debugging
            'uri'       => $_SERVER['REQUEST_URI'] ?? 'Unknown'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND);
    }
}

// 🔹 Datenbankverbindung
$GLOBALS['dbUser'] = "d0430432";
$GLOBALS['dbPass'] = "UdMmeTjQmFuMt6EqEMeV";
$GLOBALS['dsn'] = "mysql:host=localhost;dbname=d0430432;charset=utf8mb4";
$GLOBALS['pdo_options'] = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];
try {
    $GLOBALS['pdo'] = new PDO($GLOBALS['dsn'], $GLOBALS['dbUser'], $GLOBALS['dbPass'], $GLOBALS['pdo_options']);
} catch (PDOException $e) {
    if (LOG_DB_ERRORS) {
        writeLog("❌ Datenbankfehler: " . $e->getMessage(), 'ERROR', ERROR_LOG_FILE);
    }
    die("Fehler bei der Verbindung zur Datenbank. Bitte später erneut versuchen.");
}
$pdo = $GLOBALS['pdo'];

// 🔹 Mehr-Rollen-System
if (isset($_SESSION["user"]["id"]) && !isset($_SESSION["user"]["rollen"])) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.name FROM rollen r
            JOIN nutzer_rolle nr ON r.id = nr.rolle_id
            WHERE nr.nutzer_id = ?
        ");
        $stmt->execute([$_SESSION["user"]["id"]]);
        $user_roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($user_roles)) {
            // Versuche, die "neu"-Rolle automatisch zuzuweisen
            $stmt_role = $pdo->prepare("SELECT id FROM rollen WHERE name = 'neu'");
            $stmt_role->execute();
            $new_role_id = $stmt_role->fetchColumn();
            
            if ($new_role_id) {
                $stmt_insert = $pdo->prepare("INSERT IGNORE INTO nutzer_rolle (nutzer_id, rolle_id) VALUES (?, ?)");
                $stmt_insert->execute([$_SESSION["user"]["id"], $new_role_id]);
                $user_roles = ["neu"];
                writeLog("Neue 'neu'-Rolle automatisch zugewiesen für Nutzer {$_SESSION["user"]["id"]}", 'INFO', SECURITY_LOG_FILE);
            }
        }
        
        $_SESSION["user"]["rollen"] = $user_roles ?: ["neu"];
        
        // Debug-Log für Rollenprobleme
        writeLog("Rollen für Nutzer {$_SESSION["user"]["id"]} neu geladen: " . implode(", ", $_SESSION["user"]["rollen"]), 'DEBUG', DEBUGGING_LOG_FILE);
    } catch (Exception $e) {
        writeLog("Fehler beim Abrufen der Nutzerrollen: " . $e->getMessage(), 'ERROR', ERROR_LOG_FILE);
        $_SESSION["user"]["rollen"] = ["neu"];
    }
}

// Legacy-Support: Wandle alte 'rolle' in 'rollen'-Array um
if (isset($_SESSION["user"]["rolle"]) && !isset($_SESSION["user"]["rollen"])) {
    $_SESSION["user"]["rollen"] = [$_SESSION["user"]["rolle"]];
    writeLog("Legacy-Rolle konvertiert für Nutzer ID: " . ($_SESSION["user"]["id"] ?? 'unbekannt'), 'DEBUG', DEBUGGING_LOG_FILE);
    unset($_SESSION["user"]["rolle"]);
}

require_once __DIR__ . '/helpers.php';

if (!function_exists('getSystemSetting')) {
    function getSystemSetting($key, $default = null) {
        static $cache = [];
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT wert FROM tms_einstellungen WHERE schluessel = ?");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            $cache[$key] = $value !== false ? $value : $default;
            return $cache[$key];
        } catch (Exception $e) {
            writeLog("Fehler beim Abrufen der Systemeinstellung '$key': " . $e->getMessage(), 'ERROR', ERROR_LOG_FILE);
            return $default;
        }
    }
}

if (!function_exists('updateSystemSetting')) {
    function updateSystemSetting($key, $value) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("INSERT INTO tms_einstellungen (schluessel, wert) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE wert = VALUES(wert)");
            return $stmt->execute([$key, $value]);
        } catch (Exception $e) {
            writeLog("Fehler beim Aktualisieren der Systemeinstellung '$key': " . $e->getMessage(), 'ERROR', ERROR_LOG_FILE);
            return false;
        }
    }
}

// Funktion zur Prüfung des Anmeldestatus
function isUserLoggedIn() {
    return isset($_SESSION['user']) && 
           isset($_SESSION['user']['id']) && 
           isset($_SESSION['user']['rollen']) &&
           !empty($_SESSION['user']['rollen']);
}

// Debug-Funktion für Session-Probleme
if (DEBUG_MODE) {
    // Log aktuelle Session-Daten für Login-bezogene Seiten
    $login_pages = ['/index.php', '/auth/login.php', '/auth/logout.php', 
                   '/admin/dashboard_admin.php', '/fahrer/dashboard_fahrer.php'];
    if (in_array($_SERVER['REQUEST_URI'], $login_pages)) {
        writeLog("Session auf {$_SERVER['REQUEST_URI']}: " . json_encode([
            'id' => session_id(),
            'user_exists' => isset($_SESSION['user']),
            'user_id' => $_SESSION['user']['id'] ?? 'nicht gesetzt',
            'rollen' => $_SESSION['user']['rollen'] ?? 'nicht gesetzt',
            'cookie_exists' => isset($_COOKIE[session_name()]),
            'session_status' => session_status() // 1=disabled, 2=enabled no session, 3=active
        ]), 'DEBUG', DEBUGGING_LOG_FILE);
    }
}
