<?php
ob_start(); // Ausgabe-Pufferung aktivieren
require __DIR__ . '/../../app/config.php';  // Zentrale Konfiguration inkl. Session, CSRF etc.
require 'assets/header.php';

// Prüfung, ob Registrierung erlaubt ist
$registrationAllowed = getSystemSetting('registrierung_aktiv', 1);
if (!$registrationAllowed) {
    $_SESSION['error'] = "❌ Registrierung ist derzeit deaktiviert!";
    header("Location: /index.php");
    exit;
}

// Sicherstellen, dass die Datenbankverbindung existiert
if (!isset($pdo)) {
    die("❌ Datenbankverbindung fehlgeschlagen!");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("❌ CSRF-Token ungültig!");
    }

    $name = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');
    $nutzername = htmlspecialchars(trim($_POST['nutzername']), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);

    if (strlen($password) < 8) {
        $_SESSION['error'] = "❌ Das Passwort muss mindestens 8 Zeichen lang sein!";
        header("Location: register.php");
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Prüfen, ob die E-Mail bereits existiert
    $stmt = $pdo->prepare("SELECT id FROM nutzer WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "❌ Diese E-Mail ist bereits registriert!";
        header("Location: register.php");
        exit;
    }

    // Prüfen, ob der Nutzername bereits existiert
    $stmt = $pdo->prepare("SELECT id FROM nutzer WHERE nutzername = ?");
    $stmt->execute([$nutzername]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "❌ Dieser Nutzername ist bereits vergeben!";
        header("Location: register.php");
        exit;
    }

    // Neuen Nutzer einfügen
    $stmt = $pdo->prepare("INSERT INTO nutzer (name, nutzername, email, passwort) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        die("SQL-Fehler: " . implode(" - ", $pdo->errorInfo()));
    }

    if ($stmt->execute([$name, $nutzername, $email, $passwordHash])) {
        $userId = $pdo->lastInsertId(); // Die ID des neuen Nutzers holen

        // Standardrolle "neu" zuweisen (falls notwendig kannst du "fahrer" setzen)
        $stmt = $pdo->prepare("INSERT INTO nutzer_rolle (nutzer_id, rolle_id) 
                               VALUES (?, (SELECT id FROM rollen WHERE name = 'neu'))");
        $stmt->execute([$userId]);

        $_SESSION['success'] = "✅ Registrierung erfolgreich! Bitte logge dich ein.";
        header("Location: /index.php");
        exit;
    } else {
        $_SESSION['error'] = "❌ Fehler bei der Registrierung!";
        header("Location: register.php");
        exit;
    }
}

ob_end_flush(); // Ausgabe-Puffer beenden
?>

<div class="container text-center mt-5">
    <h1 class="fw-bold">Registrierung</h1>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_SESSION['error']); ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <div class="col-md-4 offset-md-4 mt-4">
        <form action="register.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <div class="mb-3">
                <input type="text" name="name" class="form-control" placeholder="Vollständiger Name" required>
            </div>
            <div class="mb-3">
                <input type="text" name="nutzername" class="form-control" placeholder="Nutzername (für die Anmeldung)" required>
                <div class="form-text text-start">Der Nutzername muss einzigartig sein.</div>
            </div>
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="E-Mail" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Passwort (min. 8 Zeichen)" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Registrieren</button>
        </form>
    </div>
    
    <div class="mt-3">
        <a href="login.php" class="text-decoration-none">Bereits registriert? Hier anmelden</a>
    </div>
</div>

<?php include 'assets/footer.php'; ?>