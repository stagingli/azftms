<?php
// Diagnostik für Sessions
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Session-Parameter - nur setzen, wenn keine Session aktiv ist
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
} else {
    // Wenn bereits eine Session aktiv ist, geben wir eine Nachricht aus
    echo "<p><strong>Hinweis:</strong> Eine Session ist bereits aktiv. Cookie-Parameter können nicht geändert werden.</p>";
}

// Diagnostische Ausgabe
echo "<h1>Session-Diagnose</h1>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Status: " . session_status() . " (1=disabled, 2=enabled but no session, 3=active)</p>";
echo "<p>Session Save Path: " . session_save_path() . "</p>";

// Test-Wert in Session speichern
$_SESSION['test'] = 'Session funktioniert: ' . date('Y-m-d H:i:s');
echo "<p>Test-Wert gesetzt: " . $_SESSION['test'] . "</p>";

// Cookie-Informationen anzeigen
echo "<h2>Cookies:</h2>";
echo "<pre>" . print_r($_COOKIE, true) . "</pre>";

// Alle Session-Variablen anzeigen
echo "<h2>Session-Variablen:</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Alle Server-Variablen anzeigen
echo "<h2>Server-Variablen:</h2>";
echo "<pre>" . print_r($_SERVER, true) . "</pre>";

// HTTP-Cookie-Header prüfen
if (isset($_SERVER['HTTP_COOKIE'])) {
    echo "<h2>HTTP Cookie-Header:</h2>";
    echo "<p>" . htmlspecialchars($_SERVER['HTTP_COOKIE']) . "</p>";
    
    // Prüfen, ob multiple PHPSESSID-Cookies vorhanden sind
    $cookie_count = substr_count($_SERVER['HTTP_COOKIE'], 'PHPSESSID');
    if ($cookie_count > 1) {
        echo "<p style='color: red;'><strong>Warnung:</strong> Es wurden $cookie_count PHPSESSID-Cookies gefunden. Dies kann zu Sessionproblemen führen.</p>";
        echo "<p><a href='clear_session.php' style='color: blue;'>Alle Session-Cookies löschen</a></p>";
    }
}