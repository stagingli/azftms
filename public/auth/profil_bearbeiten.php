<?php
ob_start();
require __DIR__ . '/../../app/config.php';

// Prüfen, ob der Nutzer eingeloggt ist
if (!isset($_SESSION["user"]) || empty($_SESSION["user"]["id"])) {
    $_SESSION['error'] = "❌ Bitte logge dich ein, um dein Profil zu bearbeiten!";
    header("Location: /index.php");
    exit;
}

// AJAX-Handler für Passwort-Counter reduzieren vor jeglicher HTML-Ausgabe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'decrease_counter') {
    // Vor der JSON-Ausgabe den Output Buffer leeren, um unerwünschte Ausgaben zu vermeiden
    ob_clean();
    header('Content-Type: application/json');

    // CSRF-Schutz prüfen
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Ungültiger CSRF-Token']);
        exit;
    }

    // Sicherstellen, dass die Datenbankverbindung existiert
    if (!isset($pdo)) {
        echo json_encode(['success' => false, 'error' => 'Datenbankverbindung fehlgeschlagen!']);
        exit;
    }

    $userId = $_SESSION["user"]["id"];

    // Counter reduzieren
    $stmt = $pdo->prepare("UPDATE nutzer SET email_passwort_verbleibend = GREATEST(email_passwort_verbleibend - 1, 0) WHERE id = ?");
    if ($stmt->execute([$userId])) {
        // Neue verbleibende Anzeigen auslesen
        $stmt = $pdo->prepare("SELECT email_passwort_verbleibend FROM nutzer WHERE id = ?");
        $stmt->execute([$userId]);
        $remaining = $stmt->fetchColumn();

        // Wenn keine Anzeigen mehr übrig, Passwort löschen
        if ($remaining <= 0) {
            $stmt = $pdo->prepare("UPDATE nutzer SET email_einmalpasswort = NULL, email_passwort_verbleibend = 0, email_passwort_erstellt = NULL WHERE id = ?");
            $stmt->execute([$userId]);

            // Auch aus der email_passwords Tabelle entfernen
            $stmt = $pdo->prepare("DELETE FROM email_passwords WHERE user_id = ?");
            $stmt->execute([$userId]);
        }

        // Passwort abrufen
        $password = '';
        if ($remaining > 0) {
            $stmt = $pdo->prepare("SELECT password_plaintext FROM email_passwords WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $password = $stmt->fetchColumn();
        }

        echo json_encode([
            'success'   => true,
            'remaining' => $remaining,
            'password'  => $password
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Fehler beim Aktualisieren des Counters']);
    }
    exit;
}

// Nach dem AJAX-Handler folgt die normale HTML-Ausgabe
require 'assets/header.php';

// Sicherstellen, dass die Datenbankverbindung existiert
if (!isset($pdo)) {
    die("❌ Datenbankverbindung fehlgeschlagen!");
}

$error   = "";
$success = "";

// Nutzerdaten abrufen
$userId = $_SESSION["user"]["id"];
$stmt   = $pdo->prepare("SELECT id, name, nutzername, email, passwort, email_einmalpasswort, email_passwort_verbleibend, email_passwort_erstellt, email_passwort_ablauf FROM nutzer WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    $_SESSION['error'] = "❌ Nutzerdaten konnten nicht geladen werden!";
    header("Location: /index.php");
    exit;
}

// Prüfen, ob das E-Mail-Passwort angezeigt werden soll
$showEmailPassword = false;
if (!empty($userData['email_einmalpasswort']) && $userData['email_passwort_verbleibend'] > 0) {
    $showEmailPassword = true;
}

// Verarbeitung von Profildaten- und Passwort-Updates via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("❌ CSRF-Token ungültig!");
    }
    
    if (isset($_POST['update_profile'])) {
        $name       = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');
        $email      = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $nutzername = htmlspecialchars(trim($_POST['nutzername']), ENT_QUOTES, 'UTF-8');
        
        // Prüfen, ob die E-Mail bereits existiert (bei einem anderen Nutzer)
        $stmt = $pdo->prepare("SELECT id FROM nutzer WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $error = "❌ Diese E-Mail ist bereits registriert!";
        } else {
            // Prüfen, ob der Nutzername bereits existiert (bei einem anderen Nutzer)
            $stmt = $pdo->prepare("SELECT id FROM nutzer WHERE nutzername = ? AND id != ?");
            $stmt->execute([$nutzername, $userId]);
            if ($stmt->fetch()) {
                $error = "❌ Dieser Nutzername ist bereits vergeben!";
            } else {
                // Profildaten aktualisieren
                $stmt = $pdo->prepare("UPDATE nutzer SET name = ?, email = ?, nutzername = ? WHERE id = ?");
                if ($stmt->execute([$name, $email, $nutzername, $userId])) {
                    $_SESSION["user"]["email"] = $email;
                    $_SESSION["user"]["nutzername"] = $nutzername;
                    $success = "✅ Profildaten wurden erfolgreich aktualisiert!";
                    
                    // Daten neu laden
                    $stmt = $pdo->prepare("SELECT id, name, nutzername, email, email_einmalpasswort, email_passwort_verbleibend, email_passwort_erstellt, email_passwort_ablauf FROM nutzer WHERE id = ?");
                    $stmt->execute([$userId]);
                    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "❌ Fehler beim Aktualisieren der Profildaten!";
                }
            }
        }
    } elseif (isset($_POST['update_password'])) {
        $currentPassword = trim($_POST['current_password']);
        $newPassword     = trim($_POST['new_password']);
        $confirmPassword = trim($_POST['confirm_password']);
        
        // Prüfen, ob das aktuelle Passwort korrekt ist
        if (!password_verify($currentPassword, $userData['passwort'])) {
            $error = "❌ Das aktuelle Passwort ist nicht korrekt!";
        } elseif (strlen($newPassword) < 8) {
            $error = "❌ Das neue Passwort muss mindestens 8 Zeichen lang sein!";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "❌ Die neuen Passwörter stimmen nicht überein!";
        } else {
            // Neues Passwort hashen und speichern
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE nutzer SET passwort = ? WHERE id = ?");
            if ($stmt->execute([$passwordHash, $userId])) {
                $success = "✅ Passwort wurde erfolgreich geändert!";
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $error = "❌ Fehler beim Aktualisieren des Passworts!";
            }
        }
    }
}

// Standard-Ablaufdatum, falls keines gesetzt ist
$defaultAblaufDate = '31.12.2026 23:59';

ob_end_flush();
?>

<div class="container mt-5">
    <h1 class="fw-bold text-center">Profil bearbeiten</h1>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Profildaten</h5>
                </div>
                <div class="card-body">
                    <form action="profil_bearbeiten.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($userData['name'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="nutzername" class="form-label">Nutzername</label>
                            <input type="text" name="nutzername" id="nutzername" class="form-control" value="<?= htmlspecialchars($userData['nutzername'] ?? ''); ?>" required>
                            <div class="form-text">Der Nutzername muss einzigartig sein und wird zur Anmeldung verwendet.</div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-Mail</label>
                            <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($userData['email'] ?? ''); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary w-100">Profildaten aktualisieren</button>
                    </form>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Passwort ändern</h5>
                </div>
                <div class="card-body">
                    <form action="profil_bearbeiten.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Aktuelles Passwort</label>
                            <input type="password" name="current_password" id="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Neues Passwort (mind. 8 Zeichen)</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Neues Passwort bestätigen</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="8">
                        </div>
                        <button type="submit" name="update_password" class="btn btn-warning w-100">Passwort ändern</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">E-Mail-Zugang</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-3">
                        <a href="https://mail.ab-zum-flieger.com" target="_blank" class="btn btn-primary">
                            <i class="fas fa-envelope me-2"></i>Webmailer öffnen
                        </a>
                        
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="fw-bold">Zugangsdaten für den E-Mail-Account</h6>
                                <div class="mb-2">
                                    <strong>Benutzername:</strong> <?= htmlspecialchars($userData['email'] ?? ''); ?>
                                </div>
                                
                                <div id="emailPasswordSection">
                                    <?php if ($showEmailPassword): ?>
                                        <div class="mb-2" id="passwordContainer">
                                            <strong>Passwort:</strong>
                                            <div class="input-group mb-1">
                                                <input type="password" class="form-control" id="emailPasswordField" 
                                                       value="********" readonly>
                                                <button class="btn btn-outline-secondary" type="button" id="toggleEmailPwdBtn">
                                                    <i class="fas fa-eye"></i> Anzeigen
                                                </button>
                                                <button class="btn btn-outline-secondary" type="button" id="copyEmailPwdBtn">
                                                    <i class="fas fa-copy"></i> Kopieren
                                                </button>
                                            </div>
                                            <small class="text-muted">
                                                Verbleibende Anzeigen: <span id="passwordCounter"><?= intval($userData['email_passwort_verbleibend']); ?></span><br>
                                                Gültig bis: <?= htmlspecialchars(!empty($userData['email_passwort_ablauf']) ? 
                                                    date('d.m.Y H:i', strtotime($userData['email_passwort_ablauf'])) : 
                                                    $defaultAblaufDate); ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Kein E-Mail-Passwort verfügbar. Bitte kontaktieren Sie den Administrator.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="fw-bold">Anleitung zum Einrichten deines E-Mail-Programms</h6>
                                <div class="accordion" id="emailSetupAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#outlookSetup">
                                                Microsoft Outlook
                                            </button>
                                        </h2>
                                        <div id="outlookSetup" class="accordion-collapse collapse" data-bs-parent="#emailSetupAccordion">
                                            <div class="accordion-body">
                                                <ol class="mb-0">
                                                    <li>Öffne Outlook und gehe zu <strong>Datei > Konto hinzufügen</strong></li>
                                                    <li>Wähle <strong>Manuelle Konfiguration</strong></li>
                                                    <li>Wähle <strong>POP/IMAP</strong> und füge folgende Daten ein:
                                                        <ul>
                                                            <li>Name: Dein Name</li>
                                                            <li>E-Mail-Adresse: <?= htmlspecialchars($userData['email'] ?? ''); ?></li>
                                                            <li>Benutzername: <?= htmlspecialchars($userData['email'] ?? ''); ?></li>
                                                            <li>Passwort: Dein kopiertes E-Mail-Passwort</li>
                                                            <li>Eingangsserver: mail.ab-zum-flieger.com (IMAP, Port 993, SSL)</li>
                                                            <li>Ausgangsserver: mail.ab-zum-flieger.com (SMTP, Port 587, TLS)</li>
                                                        </ul>
                                                    </li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#thunderbirdSetup">
                                                Mozilla Thunderbird
                                            </button>
                                        </h2>
                                        <div id="thunderbirdSetup" class="accordion-collapse collapse" data-bs-parent="#emailSetupAccordion">
                                            <div class="accordion-body">
                                                <ol class="mb-0">
                                                    <li>Öffne Thunderbird und wähle <strong>E-Mail-Konto einrichten</strong></li>
                                                    <li>Wähle <strong>Manuelle Konfiguration</strong> und füge folgende Daten ein:
                                                        <ul>
                                                            <li>Name: Dein Name</li>
                                                            <li>E-Mail-Adresse: <?= htmlspecialchars($userData['email'] ?? ''); ?></li>
                                                            <li>Benutzername: <?= htmlspecialchars($userData['email'] ?? ''); ?></li>
                                                            <li>Passwort: Dein kopiertes E-Mail-Passwort</li>
                                                            <li>Eingangsserver: mail.ab-zum-flieger.com (IMAP, Port 993, SSL/TLS)</li>
                                                            <li>Ausgangsserver: mail.ab-zum-flieger.com (SMTP, Port 587, STARTTLS)</li>
                                                        </ul>
                                                    </li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#iphoneSetup">
                                                iPhone / iPad (iOS)
                                            </button>
                                        </h2>
                                        <div id="iphoneSetup" class="accordion-collapse collapse" data-bs-parent="#emailSetupAccordion">
                                            <div class="accordion-body">
                                                <ol class="mb-0">
                                                    <li>Gehe zu <strong>Einstellungen > Mail > Accounts > Account hinzufügen</strong></li>
                                                    <li>Wähle <strong>Andere</strong> und dann <strong>Mail-Account hinzufügen</strong></li>
                                                    <li>Füge folgende Daten ein:
                                                        <ul>
                                                            <li>Name: Dein Name</li>
                                                            <li>E-Mail: <?= htmlspecialchars($userData['email'] ?? ''); ?></li>
                                                            <li>Passwort: Dein kopiertes E-Mail-Passwort</li>
                                                            <li>Beschreibung: Ab-zum-Flieger</li>
                                                        </ul>
                                                    </li>
                                                    <li>Wähle <strong>IMAP</strong> und füge die Server-Daten ein:
                                                        <ul>
                                                            <li>Eingangsserver: mail.ab-zum-flieger.com (Port 993, SSL)</li>
                                                            <li>Ausgangsserver: mail.ab-zum-flieger.com (Port 587, TLS)</li>
                                                        </ul>
                                                    </li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#androidSetup">
                                                Android
                                            </button>
                                        </h2>
                                        <div id="androidSetup" class="accordion-collapse collapse" data-bs-parent="#emailSetupAccordion">
                                            <div class="accordion-body">
                                                <ol class="mb-0">
                                                    <li>Öffne die <strong>E-Mail-App</strong> und wähle <strong>Konto hinzufügen</strong></li>
                                                    <li>Wähle <strong>Andere</strong> oder <strong>Benutzerdefiniert</strong></li>
                                                    <li>Füge folgende Daten ein:
                                                        <ul>
                                                            <li>E-Mail-Adresse: <?= htmlspecialchars($userData['email'] ?? ''); ?></li>
                                                            <li>Passwort: Dein kopiertes E-Mail-Passwort</li>
                                                            <li>Eingangsserver: mail.ab-zum-flieger.com (IMAP, Port 993, SSL)</li>
                                                            <li>Benutzername: <?= htmlspecialchars($userData['email'] ?? ''); ?></li>
                                                            <li>Ausgangsserver: mail.ab-zum-flieger.com (SMTP, Port 587, TLS)</li>
                                                            <li>Benutzername: <?= htmlspecialchars($userData['email'] ?? ''); ?></li>
                                                        </ul>
                                                    </li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <a href="/index.php" class="btn btn-secondary">Zurück zur Startseite</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // CSRF-Token für AJAX-Anfragen
    const csrfToken = '<?= $_SESSION['csrf_token']; ?>';
    
    // Element-Referenzen
    const passwordField = document.getElementById('emailPasswordField');
    const toggleBtn     = document.getElementById('toggleEmailPwdBtn');
    const copyBtn       = document.getElementById('copyEmailPwdBtn');
    const counterSpan   = document.getElementById('passwordCounter');
    const passwordContainer = document.getElementById('passwordContainer');
    
    // Counter-Wert auslesen
    let remainingShows = counterSpan ? parseInt(counterSpan.textContent) : 0;
    
    // Funktion zum Reduzieren des Counters via AJAX
    function decreaseCounter() {
        return fetch('profil_bearbeiten.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=decrease_counter&csrf_token=${csrfToken}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Netzwerkfehler beim Aktualisieren des Counters');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Counter aktualisieren
                remainingShows = data.remaining;
                if (counterSpan) {
                    counterSpan.textContent = remainingShows;
                }
                
                // Wenn keine Anzeigen mehr übrig sind, Passwort-Anzeige ausblenden
                if (remainingShows <= 0 && passwordContainer) {
                    passwordContainer.innerHTML = `
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Sie haben das Passwort 5x angezeigt. Bitte kontaktieren Sie den Administrator für ein neues Passwort.
                        </div>
                    `;
                }
                
                return data.password || '';
            } else {
                throw new Error(data.error || 'Fehler beim Aktualisieren des Counters');
            }
        });
    }
    
    if (toggleBtn && passwordField) {
        // Passwort anzeigen/verstecken
        toggleBtn.addEventListener('click', function() {
            if (passwordField.type === 'password') {
                toggleBtn.disabled = true;
                toggleBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Laden...';
                
                decreaseCounter()
                    .then(password => {
                        if (password) {
                            passwordField.type = 'text';
                            passwordField.value = password;
                            toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> Verbergen';
                            toggleBtn.disabled = false;
                            
                            // Nach 5 Sekunden automatisch wieder ausblenden
                            setTimeout(() => {
                                if (passwordField && passwordField.type === 'text') {
                                    passwordField.type = 'password';
                                    passwordField.value = '********';
                                    toggleBtn.innerHTML = '<i class="fas fa-eye"></i> Anzeigen';
                                }
                            }, 5000);
                        } else {
                            toggleBtn.innerHTML = '<i class="fas fa-eye"></i> Anzeigen';
                            toggleBtn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Fehler:', error);
                        toggleBtn.innerHTML = '<i class="fas fa-eye"></i> Anzeigen';
                        toggleBtn.disabled = false;
                        alert('Fehler beim Abrufen des Passworts: ' + error.message);
                    });
            } else {
                passwordField.type = 'password';
                passwordField.value = '********';
                toggleBtn.innerHTML = '<i class="fas fa-eye"></i> Anzeigen';
            }
        });
    }
    
    if (copyBtn && passwordField) {
        // Passwort kopieren
        copyBtn.addEventListener('click', function() {
            copyBtn.disabled = true;
            copyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Laden...';
            
            decreaseCounter()
                .then(password => {
                    if (password) {
                        passwordField.type = 'text';
                        passwordField.value = password;
                        passwordField.select();
                        document.execCommand('copy');
                        
                        copyBtn.innerHTML = '<i class="fas fa-check"></i> Kopiert!';
                        copyBtn.disabled = false;
                        
                        setTimeout(() => {
                            if (passwordField) {
                                passwordField.type = 'password';
                                passwordField.value = '********';
                                copyBtn.innerHTML = '<i class="fas fa-copy"></i> Kopieren';
                            }
                        }, 2000);
                    } else {
                        copyBtn.innerHTML = '<i class="fas fa-copy"></i> Kopieren';
                        copyBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Fehler:', error);
                    copyBtn.innerHTML = '<i class="fas fa-copy"></i> Kopieren';
                    copyBtn.disabled = false;
                    alert('Fehler beim Abrufen des Passworts: ' + error.message);
                });
        });
    }
});
</script>

<?php include 'assets/footer.php'; ?>
