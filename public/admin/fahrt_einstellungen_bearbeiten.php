<?php
session_start();
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/permissions.php';
require __DIR__ . '/../assets/header.php';

// CSRF-Token generieren (falls nicht vorhanden)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ID prüfen
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Ungültige Einstellungs-ID.";
    header("Location: fahrt_einstellungen.php");
    exit();
}

$id = intval($_GET['id']);

// Einstellung abrufen
$stmt = $pdo->prepare("SELECT * FROM einstellungen WHERE id = ?");
$stmt->execute([$id]);
$einstellung = $stmt->fetch(PDO::FETCH_ASSOC);

// Prüfen, ob Einstellung existiert
if (!$einstellung) {
    $_SESSION['error'] = "Die angeforderte Einstellung wurde nicht gefunden.";
    header("Location: fahrt_einstellungen.php");
    exit();
}

// Fehlermeldungen anzeigen
if (isset($_SESSION['error'])) {
    echo '<div class="container mt-3"><div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div></div>';
    unset($_SESSION['error']);
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h1 class="fw-bold">Einstellung bearbeiten</h1>
            <p>Hier kannst du die ausgewählte Einstellung bearbeiten.</p>
            
            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="fahrt_einstellungen_speichern.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $einstellung['id'] ?>">
                        
                        <div class="mb-3">
                            <label for="kategorie" class="form-label">Kategorie</label>
                            <select name="kategorie" id="kategorie" class="form-select" required>
                                <option value="zahlungsmethode" <?= $einstellung['kategorie'] === 'zahlungsmethode' ? 'selected' : '' ?>>Zahlungsmethode</option>
                                <option value="fahrzeug" <?= $einstellung['kategorie'] === 'fahrzeug' ? 'selected' : '' ?>>Fahrzeug</option>
                                <option value="ort" <?= $einstellung['kategorie'] === 'ort' ? 'selected' : '' ?>>Ort</option>
                                <option value="zusatzequipment" <?= $einstellung['kategorie'] === 'zusatzequipment' ? 'selected' : '' ?>>Zusatzequipment</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="wert" class="form-label">Wert</label>
                            <input type="text" name="wert" id="wert" class="form-control" value="<?= htmlspecialchars($einstellung['wert']) ?>" required>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="fahrt_einstellungen.php" class="btn btn-secondary">Abbrechen</a>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../assets/footer.php'; ?>