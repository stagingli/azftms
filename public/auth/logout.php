<?php
// Starte Output Buffering, falls nicht bereits gestartet
ob_start();

// Konfigurationsdatei einbinden
require_once __DIR__ . '/../../app/config.php';

// Optional: Logout-Ereignis loggen
if (isset($_SESSION['user']['email']) && LOG_LOGIN_EVENTS) {
    writeLog("🔓 Logout: " . $_SESSION['user']['email'] . " von IP: " . $_SERVER['REMOTE_ADDR'], 'SECURITY', SECURITY_LOG_FILE);
}

// Session zurücksetzen und zerstören
$_SESSION = [];
session_unset();
session_destroy();
session_write_close();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Sichere HTTP-Header setzen und Redirect auf Startseite
header("Clear-Site-Data: \"cookies\", \"storage\", \"executionContexts\"");
header("Location: /index.php");
exit;
?>
