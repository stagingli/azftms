<?php
/**
 * gutschriften.php
 * Einfache Übersicht der Fahrervergütungen
 */

// Konfiguration und Header einbinden
require_once __DIR__ . '/../../app/config.php';
// require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../assets/header.php';

// Benutzerrechte prüfen
if (!isset($_SESSION['user']) || !in_array('fahrer', $_SESSION['user']['rollen'])) {
    header("Location: /auth/login.php");
    exit;
}

// Grundlegende Variablen definieren
$fahrer_id = $_SESSION['user']['id'] ?? 0;
$user_name = $_SESSION['user']['name'] ?? 'Unbekannt';

// Aktuelles Datum
$today = date('Y-m-d');

// Filterparameter aus GET verarbeiten
$von_datum = $_GET['von_datum'] ?? date('Y-m-01'); // Standardmäßig Monatsanfang
$bis_datum = $_GET['bis_datum'] ?? $today; // Standardmäßig heute

// Stundenlohn des Fahrers abrufen
try {
    $stmt = $pdo->prepare("SELECT stundenlohn FROM nutzer WHERE id = ?");
    $stmt->execute([$fahrer_id]);
    $fahrer_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stundenlohn = $fahrer_data ? floatval($fahrer_data['stundenlohn']) : 0;
} catch (Exception $e) {
    $stundenlohn = 0;
    error_log("Fehler beim Abrufen des Stundenlohns: " . $e->getMessage());
}

// Fahrten und Vergütungen abrufen
try {
    $stmt = $pdo->prepare("
        SELECT 
            f.id, 
            f.abholdatum, 
            f.abfahrtszeit, 
            f.fahrtpreis, 
            f.ausgaben, 
            f.lohn_fahrt, 
            f.fahrzeit_summe, 
            f.lohn_auszahlbetrag,
            estart.wert as start_ort, 
            eziel.wert as ziel_ort
        FROM 
            fahrten f
            LEFT JOIN einstellungen estart ON f.ort_start_id = estart.id
            LEFT JOIN einstellungen eziel ON f.ort_ziel_id = eziel.id
        WHERE 
            f.fahrer_id = ? 
            AND f.abholdatum BETWEEN ? AND ?
            AND f.deleted_at IS NULL
        ORDER BY 
            f.abholdatum DESC, f.abfahrtszeit DESC
    ");
    $stmt->execute([$fahrer_id, $von_datum, $bis_datum]);
    $fahrten = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $fahrten = [];
    error_log("Fehler beim Abrufen der Fahrten: " . $e->getMessage());
}

// Statistiken initialisieren mit korrekten Defaultwerten
$stats = [
    'anzahl_fahrten' => count($fahrten),
    'summe_lohn' => 0,
    'summe_ausgaben' => 0,
    'summe_gesamt' => 0,
    'stundenlohn' => floatval($stundenlohn)
];

// Statistiken berechnen und Fahrten formatieren
foreach ($fahrten as &$fahrt) {
    // Summen berechnen - Verwende floatval um NULL zu verhindern
    $stats['summe_lohn'] += floatval($fahrt['lohn_fahrt'] ?? 0);
    $stats['summe_ausgaben'] += floatval($fahrt['ausgaben'] ?? 0);
    $stats['summe_gesamt'] += floatval($fahrt['lohn_auszahlbetrag'] ?? 0);
    
    // Formatieren für die Anzeige
    $fahrt['datum_formatiert'] = date('d.m.Y', strtotime($fahrt['abholdatum']));
    $fahrt['zeit_formatiert'] = substr($fahrt['abfahrtszeit'], 0, 5) . ' Uhr';
    $fahrt['route'] = $fahrt['start_ort'] . ' → ' . $fahrt['ziel_ort'];
    
    // Sicherstellen, dass keine NULL-Werte formatiert werden
    $fahrt['lohn_formatiert'] = number_format(floatval($fahrt['lohn_fahrt'] ?? 0), 2, ',', '.') . ' €';
    $fahrt['ausgaben_formatiert'] = number_format(floatval($fahrt['ausgaben'] ?? 0), 2, ',', '.') . ' €';
    $fahrt['gesamt_formatiert'] = number_format(floatval($fahrt['lohn_auszahlbetrag'] ?? 0), 2, ',', '.') . ' €';
}
?>

<div class="container mt-4">
    <!-- Überschrift und Link zurück -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-money-bill-wave me-2"></i> Vergütungsübersicht</h1>
        <a href="dashboard_fahrer.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Zurück zum Dashboard
        </a>
    </div>
    
    <!-- Statistik-Kacheln -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow text-center">
                <div class="card-body">
                    <i class="fas fa-list fa-2x text-primary mb-3"></i>
                    <h5 class="card-title">Fahrten</h5>
                    <p class="display-6"><?= $stats['anzahl_fahrten'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow text-center">
                <div class="card-body">
                    <i class="fas fa-money-bill fa-2x text-success mb-3"></i>
                    <h5 class="card-title">Lohn für Fahrzeit</h5>
                    <p class="display-6"><?= number_format(floatval($stats['summe_lohn']), 2, ',', '.') ?> €</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow text-center">
                <div class="card-body">
                    <i class="fas fa-receipt fa-2x text-info mb-3"></i>
                    <h5 class="card-title">Auslagen</h5>
                    <p class="display-6"><?= number_format(floatval($stats['summe_ausgaben']), 2, ',', '.') ?> €</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow text-center">
                <div class="card-body">
                    <i class="fas fa-euro-sign fa-2x text-danger mb-3"></i>
                    <h5 class="card-title">Stundenlohn</h5>
                    <p class="display-6"><?= number_format(floatval($stats['stundenlohn']), 2, ',', '.') ?> €</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Zeitraum-Filter</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label for="von_datum" class="form-label">Von Datum</label>
                    <input type="date" name="von_datum" id="von_datum" class="form-control" value="<?= htmlspecialchars($von_datum) ?>">
                </div>
                <div class="col-md-5">
                    <label for="bis_datum" class="form-label">Bis Datum</label>
                    <input type="date" name="bis_datum" id="bis_datum" class="form-control" value="<?= htmlspecialchars($bis_datum) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Anwenden</button>
                </div>
            </form>
            
            <!-- Schnell-Filter Buttons -->
            <div class="mt-3">
                <a href="?von_datum=<?= date('Y-m-01') ?>&bis_datum=<?= date('Y-m-t') ?>" class="btn btn-outline-primary">
                    <i class="fas fa-calendar me-1"></i> Aktueller Monat
                </a>
                <a href="?von_datum=<?= date('Y-m-01', strtotime('-1 month')) ?>&bis_datum=<?= date('Y-m-t', strtotime('-1 month')) ?>" class="btn btn-outline-primary">
                    <i class="fas fa-calendar-minus me-1"></i> Letzter Monat
                </a>
                <a href="?von_datum=<?= date('Y-01-01') ?>&bis_datum=<?= date('Y-12-31') ?>" class="btn btn-outline-primary">
                    <i class="fas fa-calendar-alt me-1"></i> Aktuelles Jahr
                </a>
            </div>
        </div>
    </div>
    
    <!-- Vergütungsliste -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i> Vergütungen</h5>
                <span class="badge bg-light text-dark">Zeitraum: <?= date('d.m.Y', strtotime($von_datum)) ?> - <?= date('d.m.Y', strtotime($bis_datum)) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($fahrten)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Keine Fahrten im gewählten Zeitraum gefunden.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Fahrt #</th>
                                <th>Datum</th>
                                <th>Strecke</th>
                                <th>Fahrtlohn</th>
                                <th>Auslagen</th>
                                <th>Gesamtbetrag</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fahrten as $fahrt): ?>
                                <tr>
                                    <td><?= $fahrt['id'] ?></td>
                                    <td><?= $fahrt['datum_formatiert'] ?></td>
                                    <td><?= htmlspecialchars($fahrt['route']) ?></td>
                                    <td class="text-end"><?= $fahrt['lohn_formatiert'] ?></td>
                                    <td class="text-end"><?= $fahrt['ausgaben_formatiert'] ?></td>
                                    <td class="text-end fw-bold"><?= $fahrt['gesamt_formatiert'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="3" class="text-end">Summen:</td>
                                <td class="text-end"><?= number_format(floatval($stats['summe_lohn']), 2, ',', '.') ?> €</td>
                                <td class="text-end"><?= number_format(floatval($stats['summe_ausgaben']), 2, ',', '.') ?> €</td>
                                <td class="text-end"><?= number_format(floatval($stats['summe_gesamt']), 2, ',', '.') ?> €</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Zusammenfassung -->
                <div class="alert alert-success mt-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-info-circle me-2"></i>Zusammenfassung</h5>
                            <p>Im gewählten Zeitraum haben Sie insgesamt <strong><?= $stats['anzahl_fahrten'] ?> Fahrten</strong> durchgeführt.</p>
                            <p>Ihr aktueller Stundenlohn beträgt <strong><?= number_format(floatval($stats['stundenlohn']), 2, ',', '.') ?> €</strong>.</p>
                        </div>
                        <div class="col-md-6">
                            <h5>Vergütungsübersicht</h5>
                            <p>Fahrtlohn: <strong><?= number_format(floatval($stats['summe_lohn']), 2, ',', '.') ?> €</strong></p>
                            <p>Auslagen: <strong><?= number_format(floatval($stats['summe_ausgaben']), 2, ',', '.') ?> €</strong></p>
                            <p>Gesamtvergütung: <strong><?= number_format(floatval($stats['summe_gesamt']), 2, ',', '.') ?> €</strong></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../assets/footer.php'; ?>