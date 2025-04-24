<?php
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/permissions.php';
require __DIR__ . '/../assets/header.php';

// CSRF-Token generieren (falls nicht vorhanden)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fehlermeldungen oder Erfolgsmeldungen anzeigen
if (isset($_SESSION['error'])) {
    echo '<div class="container mt-3"><div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div></div>';
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    echo '<div class="container mt-3"><div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div></div>';
    unset($_SESSION['success']);
}

// Bestehende Einstellungen abrufen
$stmt = $pdo->query("SELECT * FROM einstellungen ORDER BY kategorie ASC, wert ASC");
$einstellungen = $stmt->fetchAll();
?>

<div class="container mt-5">
    <h1 class="fw-bold">Fahrt Einstellungen</h1>
    <p>Hier kannst du Zahlungsmethoden, Fahrzeuge, Abholorte & Zielorte verwalten.</p>
    
    <!-- Formular zum Hinzufügen von Einstellungen -->
    <form id="add-setting-form" class="mb-4" action="fahrt_einstellungen_speichern.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="action" value="add">
        <div class="row">
            <div class="col-md-4">
                <select name="kategorie" class="form-select" required>
                    <option value="zahlungsmethode">Zahlungsmethode</option>
                    <option value="fahrzeug">Fahrzeug</option>
                    <option value="ort">Ort</option>
                    <option value="zusatzequipment">Zusatzequipment</option>
                </select>
            </div>
            <div class="col-md-5">
                <input type="text" name="wert" class="form-control" placeholder="Wert eingeben..." required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success w-100">Hinzufügen</button>
            </div>
        </div>
    </form>
    
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Kategorie</th>
                <th>Wert</th>
                <th>Aktion</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($einstellungen as $einstellung): ?>
            <tr>
                <td><?= ucfirst(htmlspecialchars($einstellung['kategorie'])) ?></td>
                <td><?= htmlspecialchars($einstellung['wert']) ?></td>
                <td>
                    <div class="d-flex gap-2">
                        <!-- Link zum Bearbeiten -->
                        <a href="fahrt_einstellungen_bearbeiten.php?id=<?= $einstellung['id'] ?>" class="btn btn-primary btn-sm">Bearbeiten</a>
                        
                        <!-- Formular zum Löschen -->
                        <form method="POST" action="fahrt_einstellungen_speichern.php" onsubmit="return confirm('Möchtest du diese Einstellung wirklich löschen?');">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $einstellung['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../assets/footer.php'; ?>