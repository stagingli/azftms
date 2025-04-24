<?php
require __DIR__ . '/assets/header.php';

// ✅ CSRF-Token generieren, falls nicht vorhanden
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Behandle spezifische Fehler aus URL-Parametern
if (isset($_GET['error'])) {
    $error_code = $_GET['error'];
    
    switch ($error_code) {
        case 'no_role':
            // Versuche, die fehlende Rolle zu beheben
            if (isset($_SESSION['user']['id'])) {
                // Prüfe, ob der Benutzer bereits Rollen hat
                if (empty($_SESSION['user']['rollen'])) {
                    try {
                        // Prüfe, ob die 'neu'-Rolle existiert
                        $role_stmt = $pdo->prepare("SELECT id FROM rollen WHERE name = 'neu'");
                        $role_stmt->execute();
                        $new_role_id = $role_stmt->fetchColumn();
                        
                        if ($new_role_id) {
                            // Füge die 'neu'-Rolle für den Benutzer hinzu
                            $insert_stmt = $pdo->prepare("INSERT IGNORE INTO nutzer_rolle (nutzer_id, rolle_id) VALUES (?, ?)");
                            $insert_stmt->execute([$_SESSION['user']['id'], $new_role_id]);
                            
                            // Aktualisiere die Session
                            $_SESSION['user']['rollen'] = ["neu"];
                            
                            writeLog("'neu'-Rolle für Nutzer ID {$_SESSION['user']['id']} wegen no_role-Fehler zugewiesen", 'INFO', SECURITY_LOG_FILE);
                            
                            // Leite zum passenden Dashboard weiter
                            header("Location: /neueruser/dashboard_neuer_user.php");
                            exit;
                        }
                    } catch (Exception $e) {
                        writeLog("Fehler beim Beheben des no_role-Problems: " . $e->getMessage(), 'ERROR', ERROR_LOG_FILE);
                    }
                }
            }
            
            $_SESSION['error'] = "Ihrem Benutzerkonto sind keine Rollen zugewiesen. Bitte kontaktieren Sie den Administrator.";
            break;
            
        case 'invalid_credentials':
            $_SESSION['error'] = "Ungültige Anmeldedaten. Bitte überprüfen Sie Ihre E-Mail/Benutzernamen und Ihr Passwort.";
            break;
            
        default:
            $_SESSION['error'] = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.";
    }
}

// Verbesserte Login-Status-Prüfung
$eingeloggt = isset($_SESSION['user']) && isset($_SESSION['user']['id']) && !empty($_SESSION['user']['id']);

// Nachrichten aus der Session abrufen und dann löschen
$error_message = $_SESSION['error'] ?? null;
$success_message = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

// Melde Probleme mit fehlenden Rollen
if ($eingeloggt && (!isset($_SESSION['user']['rollen']) || empty($_SESSION['user']['rollen']))) {
    writeLog("Benutzer ID {$_SESSION['user']['id']} ist eingeloggt aber hat keine Rollen!", 'WARNING', SECURITY_LOG_FILE);
    // Versuche, Rollen nachzuladen
    if (!isset($_SESSION['role_reload_attempted'])) {
        $_SESSION['role_reload_attempted'] = true;
        
        try {
            $stmt = $pdo->prepare("
                SELECT r.name FROM rollen r
                JOIN nutzer_rolle nr ON r.id = nr.rolle_id
                WHERE nr.nutzer_id = ?
            ");
            $stmt->execute([$_SESSION['user']['id']]);
            $user_roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($user_roles)) {
                $_SESSION['user']['rollen'] = $user_roles;
                writeLog("Rollen für Nutzer {$_SESSION['user']['id']} nachgeladen: " . implode(", ", $user_roles), 'INFO', DEBUGGING_LOG_FILE);
            } else {
                // Weiterleitung zur index.php mit no_role-Fehler
                header("Location: /index.php?error=no_role");
                exit;
            }
        } catch (Exception $e) {
            writeLog("Fehler beim Nachladen der Rollen: " . $e->getMessage(), 'ERROR', ERROR_LOG_FILE);
        }
    }
}
?>

<div class="container text-center mt-5">
    <!-- Fehlermeldungen und Erfolgsmeldungen anzeigen -->
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
    </div>
    <?php endif; ?>

    <h1 class="fw-bold">Willkommen bei Mini-FMS</h1>
    <p class="lead">Diese Plattform ist nur für das Personal von AB-ZUM-FLIEGER bestimmt.</br>
    Bitte nutze den Login um dich anzumelden.</p>
 
    <div class="col-md-6 offset-md-3 mt-4">
        <?php if ($eingeloggt): ?>
            <!-- ✅ Logout-Button -->
            <form action="/auth/logout.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <p class="fw-bold">Eingeloggt als: <?= htmlspecialchars($_SESSION['user']['email']) ?></p>
                <?php if (isset($_SESSION['user']['rollen'])): ?>
                <p class="text-muted small">Rollen: <?= htmlspecialchars(implode(', ', $_SESSION['user']['rollen'])) ?></p>
                <?php endif; ?>
                <button type="submit" class="btn btn-danger w-100">🚪 Abmelden</button>
            </form>
            
            <!-- ✅ Dashboard-Links basierend auf Rollen -->
            <?php if (isset($_SESSION['user']['rollen'])): ?>
                <div class="mt-3">
                    <?php if (in_array('admin', $_SESSION['user']['rollen'])): ?>
                        <a href="/admin/dashboard_admin.php" class="btn btn-primary w-100 mb-2">Admin-Dashboard</a>
                    <?php endif; ?>
                    
                    <?php if (in_array('fahrer', $_SESSION['user']['rollen'])): ?>
                        <a href="/fahrer/dashboard_fahrer.php" class="btn btn-success w-100 mb-2">Fahrer-Dashboard</a>
                    <?php endif; ?>
                    
                    <?php if (in_array('neu', $_SESSION['user']['rollen'])): ?>
                        <a href="/neueruser/dashboard_neuer_user.php" class="btn btn-info w-100 mb-2">Neuer Benutzer</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Keine Rollen zugewiesen. <a href="/index.php?error=no_role" class="alert-link">Beheben</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- 🔹 Login-Formular kommt jetzt zuletzt -->
    <?php if (!$eingeloggt): ?>
        <div class="col-md-6 offset-md-3 mt-5">
            <form action="/auth/login.php" method="POST" class="p-4 border rounded bg-light">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <h3 class="mb-3">Anmelden</h3>
                <div class="mb-3">
                    <!-- Angepasst: Typ auf "text" und Platzhalter erweitert -->
                    <input type="text" name="email" class="form-control" placeholder="E-Mail oder Benutzername" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Passwort" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Anmelden</button>
            </form>
            
            <p class="mt-3">
                Noch kein Konto? <a href="/auth/register.php" class="fw-bold text-decoration-none">Hier registrieren</a>
            </p>
        </div>
    <?php endif; ?>
    
</div>

<?php include __DIR__ . '/assets/footer.php'; ?>
