<?php
// Alle bisherigen Session-Cookies löschen
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 3600, '/');
}

echo "<h1>Session-Bereinigung</h1>";
echo "<p>Alle bestehenden Session-Cookies wurden gelöscht.</p>";
echo "<p>Bitte <a href='/index.php'>zurück zur Startseite</a> und versuche erneut dich anzumelden.</p>";