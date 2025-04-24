<?php
$page_title = "Meine Fahrten";

// Fehleranzeige aktivieren (nur in der Entwicklung)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Aktuelles Datum
$today = date('Y-m-d');

// Filterparameter aus GET verarbeiten
$filter = [];

// Standard-7-Tage-Filter, falls keine GET-Parameter übergeben wurden
if (empty($_GET)) {
    $filter['von_datum'] = date('Y-m-d'); // Heute
    $filter['bis_datum'] = date('Y-m-d', strtotime('+7 days')); // 7 Tage in die Zukunft
} else {
    // Filterparameter aus GET nehmen, wenn vorhanden
    if (isset($_GET['von_datum'])) {
        $filter['von_datum'] = $_GET['von_datum'];
    }
    if (isset($_GET['bis_datum'])) {
        $filter['bis_datum'] = $_GET['bis_datum'];
    }
    if (isset($_GET['keine_zeiten'])) {
        $filter['keine_zeiten'] = $_GET['keine_zeiten'] === '1';
    }
    if (isset($_GET['filter_typ'])) {
        $filter['filter_typ'] = $_GET['filter_typ'];
    }
}

// Fahrten abrufen
$fahrten_raw = getDriverRides($pdo, $fahrer_id, $filter);

// Jede Fahrt für die Anzeige aufbereiten
$fahrten = [];
foreach ($fahrten_raw as $fahrt) {
    $fahrten[] = prepareRideForDisplay($fahrt);
}

// Statistik berechnen
$stats = [
    'totalFahrten'    => count($fahrten),
    'totalLohn'       => 0,
    'totalAusgaben'   => 0,
    'totalAuszahlung' => 0,
];
foreach ($fahrten as $ride) {
    $stats['totalLohn']       += $ride['lohn_fahrt_raw'];
    $stats['totalAusgaben']   += $ride['ausgaben'];
    $stats['totalAuszahlung'] += $ride['lohn_auszahlbetrag_raw'];
}
?>

<div class="container mt-4">
  <!-- Überschrift und Aktionsbutton -->
  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <h1 class="mb-1"><i class="fas fa-route me-2"></i> Meine Fahrten</h1>
      <p class="text-muted">Übersicht und Verwaltung aller Fahrten</p>
    </div>
    <div class="d-flex gap-2">
      <a href="fahrten_alle_fahrer.php" class="btn btn-outline-success">
        <i class="fas fa-search me-1"></i> Fahrten aller Fahrer
      </a>
      <a href="dashboard_fahrer.php" class="btn btn-outline-primary">
        <i class="fas fa-tachometer-alt me-1"></i> Zum Dashboard
      </a>
    </div>
  </div>
  
  <!-- Filter-Box -->
  <div class="card border-0 shadow mb-4">
    <div class="card-header bg-light">
      <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Filter</h5>
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3">
        <div class="col-md-4">
          <label for="von_datum" class="form-label">Von Datum</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
            <input type="date" name="von_datum" id="von_datum" class="form-control" value="<?= isset($filter['von_datum']) ? htmlspecialchars($filter['von_datum']) : '' ?>">
          </div>
        </div>
        <div class="col-md-4">
          <label for="bis_datum" class="form-label">Bis Datum</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
            <input type="date" name="bis_datum" id="bis_datum" class="form-control" value="<?= isset($filter['bis_datum']) ? htmlspecialchars($filter['bis_datum']) : '' ?>">
          </div>
        </div>

        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="keine_zeiten" id="keine_zeiten" value="1" <?= isset($filter['keine_zeiten']) && $filter['keine_zeiten'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="keine_zeiten">Nur Fahrten ohne Zeiterfassung anzeigen</label>
          </div>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-search me-1"></i> Filter anwenden
          </button>
          <a href="fahrten.php" class="btn btn-outline-secondary">
            <i class="fas fa-times me-1"></i> Zurücksetzen
          </a>
          
          <!-- Schnellfilter-Buttons -->
          <div class="btn-group ms-md-2 mt-2 mt-md-0">
            <a href="fahrten.php?von_datum=<?= date('Y-m-d') ?>&bis_datum=<?= date('Y-m-d') ?>" class="btn btn-outline-primary">
              <i class="fas fa-calendar-day me-1"></i> Heute
            </a>
            <a href="fahrten.php?von_datum=<?= date('Y-m-d', strtotime('+1 day')) ?>&bis_datum=<?= date('Y-m-d', strtotime('+1 day')) ?>" class="btn btn-outline-primary">
              <i class="fas fa-calendar-plus me-1"></i> Morgen
            </a>
            <a href="fahrten.php?von_datum=<?= date('Y-m-d') ?>&bis_datum=<?= date('Y-m-d', strtotime('+7 days')) ?>" class="btn btn-outline-primary">
              <i class="fas fa-calendar-week me-1"></i> 7 Tage
            </a>
            <a href="fahrten.php?von_datum=<?= date('Y-m-01') ?>&bis_datum=<?= date('Y-m-t') ?>" class="btn btn-outline-primary">
              <i class="fas fa-calendar-alt me-1"></i> Monat
            </a>
            <a href="fahrten.php?keine_zeiten=1" class="btn btn-outline-primary">
              <i class="fas fa-clock me-1"></i> Ohne Zeiten
            </a>
          </div>
        </div>
      </form>
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
                <th>Lohn (Auszahlung)</th>
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
                  <td><?= htmlspecialchars($ride['lohn_auszahlbetrag']) ?></td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <button type="button" class="btn btn-primary open-fahrt-modal" <?= generateModalAttributes($ride) ?>>
                        <i class="fas fa-edit me-1"></i> Details
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

<!-- Modal zum Bearbeiten der Fahrtdetails -->
<?php include __DIR__ . '/fahrer_modal.php'; ?>

<!-- Footer -->
<?php require_once __DIR__ . '/../assets/footer.php'; ?>

<script>
// Fahrtendatum-Picker initialisieren
document.addEventListener('DOMContentLoaded', function() {
  // Tabelle-Sortierfunktion (optional)
  if (typeof sortTable === 'function') {
    document.querySelectorAll('th[data-sort]').forEach(th => {
      th.addEventListener('click', function() {
        const table = th.closest('table');
        const index = Array.from(th.parentNode.children).indexOf(th);
        const direction = th.classList.contains('sort-asc') ? 'desc' : 'asc';
        
        // Sortierrichtung aktualisieren
        th.parentNode.querySelectorAll('th').forEach(header => {
          header.classList.remove('sort-asc', 'sort-desc');
        });
        th.classList.add('sort-' + direction);
        
        // Tabelle sortieren
        sortTable(table, index, direction);
      });
    });
  }
  
  // Event-Handler für "Fahrt starten" und "Fahrt beenden" Buttons
  document.querySelectorAll('#startFahrtBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const fahrtId = this.dataset.id;
      startFahrt(fahrtId);
    });
  });
  
  document.querySelectorAll('#endFahrtBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const fahrtId = this.dataset.id;
      endFahrt(fahrtId);
    });
  });
  
  // Funktionen für Fahrtsteuerung
  function startFahrt(fahrtId) {
    if (!fahrtId) return;
    
    const jetzt = new Date();
    const zeit = jetzt.getHours().toString().padStart(2, '0') + ':' + 
                 jetzt.getMinutes().toString().padStart(2, '0');
    
    const formData = new FormData();
    formData.append('fahrt_id', fahrtId);
    formData.append('fahrzeit_von', zeit);
    formData.append('action', 'start_fahrt');
    
    fetch('/fahrer/update_fahrt.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
      }
    })
    .catch(error => {
      alert('Fehler: ' + error.message);
    });
  }
  
  function endFahrt(fahrtId) {
    if (!fahrtId) return;
    
    const jetzt = new Date();
    const zeit = jetzt.getHours().toString().padStart(2, '0') + ':' + 
                 jetzt.getMinutes().toString().padStart(2, '0');
    
    const formData = new FormData();
    formData.append('fahrt_id', fahrtId);
    formData.append('fahrzeit_bis', zeit);
    formData.append('action', 'end_fahrt');
    
    fetch('/fahrer/update_fahrt.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
      }
    })
    .catch(error => {
      alert('Fehler: ' + error.message);
    });
  }
});
</script>