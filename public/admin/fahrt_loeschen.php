<?php
require __DIR__ . '/../../app/config.php';

// Aktion zur Wiederherstellung einer gelöschten Fahrt
if (isset($_GET['action']) && $_GET['action'] === 'wiederherstellen' && isset($_GET['id'])) {
    $fahrt_id = intval($_GET['id']);
    
    // Prüfen, ob die Fahrt existiert und im Papierkorb ist
    $stmt = $GLOBALS['pdo']->prepare("SELECT id FROM fahrten WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->execute([$fahrt_id]);
    
    if ($stmt->rowCount() > 0) {
        // Fahrt wiederherstellen
        $stmt = $GLOBALS['pdo']->prepare("UPDATE fahrten SET deleted_at = NULL WHERE id = ?");
        $stmt->execute([$fahrt_id]);
        
        $_SESSION['msg'] = "✅ Fahrt #$fahrt_id wurde wiederhergestellt.";
        writeLog("Fahrt ID $fahrt_id wurde aus dem Papierkorb wiederhergestellt.", 'INFO');
    } else {
        $_SESSION['msg'] = "❌ Fehler: Fahrt nicht gefunden oder nicht im Papierkorb.";
    }
    
    header("Location: fahrten_papierkorb.php");
    exit();
}

// Aktion zum endgültigen Löschen einer Fahrt
if (isset($_GET['action']) && $_GET['action'] === 'endgueltig' && isset($_GET['id'])) {
    $fahrt_id = intval($_GET['id']);
    
    // Bestätigungsseite anzeigen
    require __DIR__ . '/../assets/header.php';
?>
<div class="container my-4">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-danger text-white">
            <h2 class="mb-0 py-2">
                <i class="fas fa-trash me-2"></i> Fahrt endgültig löschen
            </h2>
        </div>
        <div class="card-body bg-light">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Achtung:</strong> Möchten Sie die Fahrt #<?= $fahrt_id ?> wirklich <strong>endgültig</strong> löschen? 
                Dieser Vorgang kann nicht rückgängig gemacht werden!
            </div>
            
            <form action="fahrt_loeschen.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id" value="<?= $fahrt_id ?>">
                <input type="hidden" name="action" value="endgueltig_loeschen">
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="fahrten_papierkorb.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Abbrechen
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i> Endgültig löschen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
    require __DIR__ . '/../assets/footer.php';
    exit();
}

// Aktion zum Leeren des Papierkorbs
if (isset($_GET['action']) && $_GET['action'] === 'leeren') {
    // Bestätigungsseite anzeigen
    require __DIR__ . '/../assets/header.php';
    
    // Anzahl der zu löschenden Fahrten ermitteln
    $stmt = $GLOBALS['pdo']->query("SELECT COUNT(*) FROM fahrten WHERE deleted_at IS NOT NULL");
    $count = $stmt->fetchColumn();
?>
<div class="container my-4">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-danger text-white">
            <h2 class="mb-0 py-2">
                <i class="fas fa-trash me-2"></i> Papierkorb leeren
            </h2>
        </div>
        <div class="card-body bg-light">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Achtung:</strong> Sie sind dabei, den gesamten Papierkorb zu leeren. 
                <?= $count ?> Fahrten werden endgültig gelöscht. Dieser Vorgang kann nicht rückgängig gemacht werden!
            </div>
            
            <form action="fahrt_loeschen.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="papierkorb_leeren">
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="fahrten_papierkorb.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Abbrechen
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i> Papierkorb leeren
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
    require __DIR__ . '/../assets/footer.php';
    exit();
}

// POST-Methode zum Verarbeiten von Bestätigungsaktionen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token prüfen
    if (
        !isset($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("❌ CSRF-Token ungültig oder fehlt!");
    }
    
    // Endgültiges Löschen einer Fahrt
    if (isset($_POST['action']) && $_POST['action'] === 'endgueltig_loeschen' && isset($_POST['id'])) {
        $fahrt_id = intval($_POST['id']);
        
        // Prüfen, ob die Fahrt existiert und im Papierkorb ist
        $stmt = $GLOBALS['pdo']->prepare("SELECT id FROM fahrten WHERE id = ? AND deleted_at IS NOT NULL");
        $stmt->execute([$fahrt_id]);
        
        if ($stmt->rowCount() > 0) {
            // Fahrt endgültig löschen
            $stmt = $GLOBALS['pdo']->prepare("DELETE FROM fahrten WHERE id = ?");
            $stmt->execute([$fahrt_id]);
            
            $_SESSION['msg'] = "✅ Fahrt #$fahrt_id wurde endgültig gelöscht.";
            writeLog("Fahrt ID $fahrt_id wurde endgültig gelöscht.", 'WARNING');
        } else {
            $_SESSION['msg'] = "❌ Fehler: Fahrt nicht gefunden oder nicht im Papierkorb.";
        }
        
        header("Location: fahrten_papierkorb.php");
        exit();
    }
    
    // Papierkorb leeren
    if (isset($_POST['action']) && $_POST['action'] === 'papierkorb_leeren') {
        try {
            // Alle gelöschten Fahrten endgültig löschen
            $stmt = $GLOBALS['pdo']->query("DELETE FROM fahrten WHERE deleted_at IS NOT NULL");
            $count = $stmt->rowCount();
            
            $_SESSION['msg'] = "✅ Der Papierkorb wurde geleert. $count Fahrten wurden endgültig gelöscht.";
            writeLog("Papierkorb geleert: $count Fahrten endgültig gelöscht.", 'WARNING');
        } catch (PDOException $e) {
            $_SESSION['msg'] = "❌ Datenbankfehler: " . $e->getMessage();
            writeLog("Fehler beim Leeren des Papierkorbs: " . $e->getMessage(), 'ERROR');
        }
        
        header("Location: fahrten_papierkorb.php");
        exit();
    }

    // Standard-Lösch-Funktion (Soft-Delete)
    if (isset($_POST['id'])) {
        $id = intval($_POST['id']);

        // Prüfen, ob die Fahrt existiert
        $stmt = $GLOBALS['pdo']->prepare("SELECT id FROM fahrten WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $existierendeFahrt = $stmt->fetch();

        if (!$existierendeFahrt) {
            $_SESSION['msg'] = "❌ Fehler: Fahrt nicht gefunden oder bereits gelöscht.";
            header("Location: fahrten_liste.php");
            exit();
        }

        // Soft-Delete: Fahrt in den Papierkorb verschieben (deleted_at setzen)
        $stmt = $GLOBALS['pdo']->prepare("UPDATE fahrten SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['msg'] = "✅ Fahrt wurde in den Papierkorb verschoben.";

        // Prüfen ob Rücksprung-URL vorhanden ist
        if (isset($_POST['return']) && !empty($_POST['return'])) {
            header("Location: " . $_POST['return']);
        } else {
            header("Location: fahrten_liste.php");
        }
        exit();
    } else {
        $_SESSION['msg'] = "❌ Fehler: Keine gültige Fahrt-ID übergeben.";
        header("Location: fahrten_liste.php");
        exit();
    }
} else {
    // GET-Anfrage ohne bekannte Aktion - zeige Confirmation Dialog
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        
        // Fahrtdetails laden für Bestätigungsdialog
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT f.*, 
                   k.vorname, k.nachname, k.firmenname,
                   start.wert AS start_ort,
                   ziel.wert AS ziel_ort
            FROM fahrten f
            LEFT JOIN kunden k ON f.kunde_id = k.id
            LEFT JOIN einstellungen start ON f.ort_start_id = start.id
            LEFT JOIN einstellungen ziel ON f.ort_ziel_id = ziel.id
            WHERE f.id = ? AND f.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $fahrt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fahrt) {
            $_SESSION['msg'] = 'Fehler: Fahrt nicht gefunden oder bereits gelöscht.';
            header("Location: fahrten_liste.php");
            exit();
        }
        
        // Bestätigungsseite anzeigen
        require __DIR__ . '/../assets/header.php';
?>
<div class="container my-4">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-danger text-white">
            <h2 class="mb-0 py-2">
                <i class="fas fa-trash-alt me-2"></i> Fahrt löschen
            </h2>
        </div>
        <div class="card-body bg-light">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Achtung:</strong> Sind Sie sicher, dass Sie diese Fahrt löschen möchten? Die Fahrt wird in den Papierkorb verschoben und kann dort wiederhergestellt werden.
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Fahrtdetails</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <tr>
                            <th style="width: 200px;">Fahrt-ID:</th>
                            <td><?= htmlspecialchars($fahrt['id']) ?></td>
                        </tr>
                        <tr>
                            <th>Kunde:</th>
                            <td>
                                <?php if ($fahrt['firmenname']): ?>
                                    <?= htmlspecialchars($fahrt['firmenname']) ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($fahrt['vorname'] . ' ' . $fahrt['nachname']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Datum:</th>
                            <td><?= date('d.m.Y', strtotime($fahrt['abholdatum'])) ?></td>
                        </tr>
                        <tr>
                            <th>Zeit:</th>
                            <td><?= date('H:i', strtotime($fahrt['abfahrtszeit'])) ?> Uhr</td>
                        </tr>
                        <tr>
                            <th>Strecke:</th>
                            <td><?= htmlspecialchars($fahrt['start_ort']) ?> → <?= htmlspecialchars($fahrt['ziel_ort']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <form action="fahrt_loeschen.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <?php if (isset($_GET['return'])): ?>
                    <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return']) ?>">
                <?php endif; ?>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="<?= isset($_GET['return']) ? htmlspecialchars($_GET['return']) : 'fahrten_liste.php' ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Abbrechen
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-2"></i> In Papierkorb verschieben
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
        require __DIR__ . '/../assets/footer.php';
        exit();
    } else {
        $_SESSION['msg'] = "❌ Fehler: Keine Fahrt-ID angegeben.";
        header("Location: fahrten_liste.php");
        exit();
    }
}
?>