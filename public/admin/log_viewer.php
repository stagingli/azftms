<?php
// log_viewer.php – Anzeige und optionales Leeren von Logdateien
ob_start();

// Zentrale Konfiguration und Header laden
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../assets/header.php';
require_once __DIR__ . '/../../app/permissions.php';




// Verzeichnis mit den Logdateien
$logDir = __DIR__ . '/../../app/logs';

// Alle .log-Dateien im Verzeichnis suchen
$logFiles = glob($logDir . '/*.log');

$success = null;
$error   = null;

// Logs leeren, wenn per POST angefordert
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["clear_logs"])) {
    // CSRF-Token prüfen
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "❌ CSRF-Token ungültig!";
    } else {
        foreach ($logFiles as $logFile) {
            if (is_writable($logFile)) {
                file_put_contents($logFile, "");  // Datei leeren
                // Protokolliere das Leeren in der jeweiligen Logdatei
                writeLog("🔄 Log geleert: " . basename($logFile), 'INFO', $logFile);
            } else {
                $error = "⚠️ Keine Schreibrechte für " . basename($logFile);
            }
        }
        if (!$error) {
            $success = "✅ Logs erfolgreich geleert!";
        }
    }
}

// Logs auslesen
$logs = [];
foreach ($logFiles as $logFile) {
    if (is_readable($logFile)) {
        $logs[$logFile] = file_get_contents($logFile);
    } else {
        $logs[$logFile] = "⚠️ Logdatei nicht lesbar oder nicht vorhanden.";
    }
}

ob_end_flush();
?>

<main>
  <div class="container mt-5">
    <h2 class="fw-bold">Logs</h2>
    
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php foreach ($logs as $logFile => $logContent): ?>
      <div class="log-section my-4">
        <h3><?= htmlspecialchars(basename($logFile)); ?></h3>
        <pre class="border p-3 bg-light" style="max-height:400px; overflow-y:auto;"><?php
          // Inhalt zeilenweise verarbeiten
          $lines = explode(PHP_EOL, $logContent);
          foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Prüfen, ob es sich um JSON handelt
            $json = json_decode($line, true);
            if ($json !== null) {
              // JSON formatieren
              $timestamp = $json['timestamp'] ?? '';
              $level = $json['level'] ?? 'INFO';
              $message = $json['message'] ?? '';
              $ip = isset($json['ip']) && $json['ip'] ? " [IP: {$json['ip']}]" : "";
              
              echo htmlspecialchars("[{$timestamp}] [{$level}]{$ip} {$message}") . PHP_EOL;
            } else {
              // Ursprüngliche Zeile ausgeben
              echo htmlspecialchars($line) . PHP_EOL;
            }
          }
        ?></pre>
      </div>
    <?php endforeach; ?>
    
    <!-- Formular zum Leeren der Logs -->
    <form method="POST" class="mt-3">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
      <button type="submit" name="clear_logs" class="btn btn-warning">🔄 Logs leeren</button>
    </form>
  </div>
</main>