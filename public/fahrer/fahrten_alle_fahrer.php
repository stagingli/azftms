<?php
/**
 * fahrten_alle_fahrer.php
 * Zeigt alle Fahrten (aller Fahrer und unzugeteilte) an, die in der Zukunft liegen
 * und nicht dem aktuell angemeldeten Fahrer zugeordnet sind.
 */

$page_title = "Alle Fahrten";

// Konfiguration, Berechtigungsprüfung, gemeinsame Funktionen und Header einbinden
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/fahrer_functions.php';
require_once __DIR__ . '/../assets/header.php';

// Benutzerrechte prüfen
if (!isset($_SESSION['user']) || !in_array('fahrer', $_SESSION['user']['rollen'])) {
    header("Location: /auth/login.php");
    exit;
}

// Grundlegende Variablen definieren
$fahrer_id = $_SESSION['user']['id'] ?? 0;
$user_name = $_SESSION['user']['name'] ?? 'Unbekannt';

// Fahrer-Stundenlohn abrufen
try {
    $stmt = $pdo->prepare("SELECT stundenlohn FROM nutzer WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $fahrer_id]);
    $driverData = $stmt->fetch(PDO::FETCH_ASSOC);
    $driverWage = $driverData ? floatval($driverData['stundenlohn']) : 0;
    
    if ($driverWage <= 0) {
        $stmt = $pdo->prepare("SELECT wert FROM tms_einstellungen WHERE schluessel = 'mindest_stundenlohn' LIMIT 1");
        $stmt->execute();
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        $driverWage = $setting ? floatval($setting['wert']) : 12.82;
    }
} catch (Exception $e) {
    $driverWage = 12.82; // Standard-Fallback
}

// Stundenlohn für JavaScript verfügbar machen
echo '<script>const DRIVER_WAGE = ' . json_encode($driverWage) . ';</script>';

// Filterparameter aus GET verarbeiten
$filter = [];

// Standardfilter: ab heute bis 1 Monat in die Zukunft
$filter['von_datum'] = $_GET['von_datum'] ?? $today;
$filter['bis_datum'] = $_GET['bis_datum'] ?? date('Y-m-d', strtotime('+1 month'));

// Entfernt: Status-Filter aus GET

// Entfernt: Filter für "nur freie Fahrten"

// Fahrten abrufen
try {
    // SQL-Abfrage
    $sql = "
        SELECT 
            f.id,
            f.rechnungsnummer,
            f.fahrtpreis,
            f.zahlungsmethode_id,
            f.fahrer_id,
            f.fahrzeug_id,
            f.kunde_id,
            f.ort_start_id,
            f.ort_ziel_id,
            f.abholdatum,
            f.abfahrtszeit,
            f.fahrzeit_von,
            f.fahrzeit_bis,
            f.fahrzeit_summe,
            f.lohn_fahrt,
            f.lohn_auszahlbetrag,
            f.ausgaben,
            f.wartezeit,
            f.fahrer_bemerkung,
            f.alternative_abholadresse,
            f.alternative_zieladresse,
            f.flugnummer,
            f.personenanzahl,
            f.zusatzequipment,
            f.dispo_bemerkung,
            f.hinfahrt_id,
            -- Kundendaten
            k.vorname AS kunde_vorname,
            k.nachname AS kunde_nachname,
            k.kundentyp,
            k.firmenname,
            k.firmenanschrift,
            k.strasse,
            k.hausnummer,
            k.plz,
            k.ort AS kunde_ort,
            k.telefon,
            k.mobil,
            k.email,
            k.bemerkung AS kunde_bemerkung,
            -- Einstellungsdaten
            e1.wert AS ort_start_name,
            e2.wert AS ort_ziel_name,
            e3.wert AS fahrzeug_info,
            ez.wert AS zahlungsart,
            -- Fahrerdaten
            n.name AS fahrer_name
        FROM fahrten f
        LEFT JOIN kunden k ON f.kunde_id = k.id
        LEFT JOIN einstellungen e1 ON f.ort_start_id = e1.id
        LEFT JOIN einstellungen e2 ON f.ort_ziel_id = e2.id
        LEFT JOIN einstellungen e3 ON f.fahrzeug_id = e3.id
        LEFT JOIN einstellungen ez ON f.zahlungsmethode_id = ez.id
        LEFT JOIN nutzer n ON f.fahrer_id = n.id
        WHERE f.abholdatum BETWEEN :von_datum AND :bis_datum
            AND f.deleted_at IS NULL
            AND f.fahrer_id <> :fahrer_id
    ";
    
        // Entfernt: Filter für "nur freie Fahrten"
    
    // Sortierung
    $sql .= " ORDER BY f.abholdatum ASC, f.abfahrtszeit ASC";

    // Query vorbereiten und ausführen
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':von_datum', $filter['von_datum']);
    $stmt->bindValue(':bis_datum', $filter['bis_datum']);
    $stmt->bindValue(':fahrer_id', $fahrer_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $fahrten_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fehlerbehandlung
    writeLog("Fehler beim Laden der Fahrten: " . $e->getMessage(), 'ERROR', ERROR_LOG_FILE);
    $fahrten_raw = [];
}

// Jede Fahrt für die Anzeige aufbereiten
$fahrten = [];
foreach ($fahrten_raw as $fahrt) {
    $preparedRide = prepareRideForDisplay($fahrt);
    // Fahrername hinzufügen
    $preparedRide['fahrer_name'] = $fahrt['fahrer_name'] ?? 'Nicht zugewiesen';
    $fahrten[] = $preparedRide;
}

// Entfernt: Status-Filter-Logik
?>

<div class="container mt-4">
    <!-- Überschrift und Aktionsbutton -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="mb-1"><i class="fas fa-list-ul me-2"></i> Alle Fahrten</h1>
            <p class="text-muted">Übersicht aller verfügbaren Fahrten im System</p>
        </div>
        <div>
            <a href="dashboard_fahrer.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-tachometer-alt me-1"></i> Zum Dashboard
            </a>
            <a href="fahrten.php" class="btn btn-outline-secondary">
                <i class="fas fa-user me-1"></i> Meine Fahrten
            </a>
        </div>
    </div>
        
    <!-- Schnellfilter-Buttons -->
    <div class="card border-0 shadow mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Schnellfilter</h5>
        </div>
        <div class="card-body">
            <div class="btn-group">
                <a href="fahrten_alle_fahrer.php?von_datum=<?= $today ?>&bis_datum=<?= $today ?>" class="btn btn-outline-primary">
                    <i class="fas fa-calendar-day me-1"></i> Heute
                </a>
                <a href="fahrten_alle_fahrer.php?von_datum=<?= date('Y-m-d', strtotime('+1 day')) ?>&bis_datum=<?= date('Y-m-d', strtotime('+1 day')) ?>" class="btn btn-outline-primary">
                    <i class="fas fa-calendar-plus me-1"></i> Morgen
                </a>
            </div>
        </div>
    </div>
    
    <!-- Fahrtenliste -->
    <div class="card border-0 shadow">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i> Fahrtenliste</h5>
                <span class="badge bg-primary"><?= count($fahrten) ?> Fahrten gefunden</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($fahrten)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-2"></i> Keine Fahrten gefunden, die den Filterkriterien entsprechen.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Datum</th>
                                <th>Zeit</th>
                                <th>Kunde</th>
                                <th>Route</th>
                                <th>Status</th>
                                <th>Zugewiesener Fahrer</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fahrten as $ride): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ride['id']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($ride['datum']) ?>
                                        <?php if ($ride['ist_heute']): ?>
                                            <span class="badge bg-info">Heute</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($ride['zeit']) ?></td>
                                    <td>
                                        <?php if ($ride['is_firma']): ?>
                                            <span class="badge bg-info me-1">Firma</span> 
                                        <?php endif; ?>
                                        <?= htmlspecialchars($ride['kunde']) ?>
                                    </td>
                                    <td class="text-truncate" style="max-width: 200px;"><?= htmlspecialchars($ride['route']) ?></td>
                                    <td><span class="badge bg-<?= $ride['status_color'] ?>"><?= htmlspecialchars($ride['status']) ?></span></td>
                                    <td>
                                        <?= htmlspecialchars($ride['fahrer_name']) ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary open-fahrt-modal" <?= generateModalAttributes($ride); ?>>
                                                <i class="fas fa-eye me-1"></i> Details
                                            </button>
                                            <a href="https://www.google.com/maps/dir/?api=1&origin=<?= urlencode($ride['start']) ?>&destination=<?= urlencode($ride['ziel']) ?>" 
                                               class="btn btn-success" target="_blank">
                                                <i class="fas fa-directions"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal zum Bearbeiten -->
<?php include __DIR__ . '/fahrer_modal.php'; ?>

<!-- Footer -->
<?php require_once __DIR__ . '/../assets/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Datum-Picker mit Standardwerten, falls leer
    const vonDatum = document.getElementById('von_datum');
    const bisDatum = document.getElementById('bis_datum');
    
    if (vonDatum && !vonDatum.value) {
        vonDatum.value = new Date().toISOString().split('T')[0];
    }
    
    if (bisDatum && !bisDatum.value) {
        const date = new Date();
        date.setMonth(date.getMonth() + 1);
        bisDatum.value = date.toISOString().split('T')[0];
    }
    
    // Modal-Öffnen-Funktionalität
    document.querySelectorAll('.open-fahrt-modal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            // Modal mit Bootstrap manuell initialisieren
            const fahrtModal = new bootstrap.Modal(document.getElementById('fahrtModal'));
            
            // Modal öffnen
            fahrtModal.show();
            
            // Felder für Nur-Lesen konfigurieren
            setTimeout(function() {
                const modal = document.getElementById('fahrtModal');
                
                // Alle Felder schreibgeschützt machen
                modal.querySelectorAll('input, textarea, select').forEach(elem => {
                    if (elem.id !== 'navigation-link') {
                        elem.setAttribute('readonly', 'readonly');
                        if (elem.tagName === 'SELECT') {
                            elem.setAttribute('disabled', 'disabled');
                        }
                    }
                });
                
                // Aktionsbuttons ausblenden
                ['#saveFahrtBtn', '#startFahrtBtn', '#endFahrtBtn', '#deleteFahrtBtn'].forEach(selector => {
                    const button = modal.querySelector(selector);
                    if (button) button.style.display = 'none';
                });
                
                // Titel anpassen
                const modalTitle = document.querySelector('#fahrtModalLabel');
                if (modalTitle) {
                    modalTitle.textContent = 'Fahrtdetails (Nur Ansicht)';
                }
            }, 100); // Kurze Verzögerung, um sicherzustellen, dass das Modal geladen ist
        });
    });
});
</script>