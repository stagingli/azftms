<?php

$page_title = "Fahrerdashboard";

// In der Entwicklung ggf. Fehleranzeige aktivieren
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Konfiguration, Rechte und Header einbinden
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/fahrer_functions.php';
require_once __DIR__ . '/../assets/header.php';

// Benutzerrechte prüfen
if (!isset($_SESSION['user']) || !in_array('fahrer', $_SESSION['user']['rollen'])) {
    header("Location: /auth/login.php");
    exit;
}

// Grundlegende Variablen
$fahrer_id = $_SESSION['user']['id'] ?? 0;
$user_name = $_SESSION['user']['name'] ?? 'Unbekannt';

// Fahrer-Stundenlohn abrufen
try {
    $stmt = $pdo->prepare("SELECT stundenlohn FROM nutzer WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $fahrer_id]);
    $driverData = $stmt->fetch(PDO::FETCH_ASSOC);
    $driverWage = $driverData ? floatval($driverData['stundenlohn']) : 0;
    
    if ($driverWage <= 0) {
        // Mindestlohn aus tms_einstellungen laden
        $stmt = $pdo->prepare("SELECT wert FROM tms_einstellungen WHERE schluessel = 'mindest_stundenlohn' LIMIT 1");
        $stmt->execute();
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        $driverWage = $setting ? floatval($setting['wert']) : 12.82;
    }
} catch (Exception $e) {
    $driverWage = 12.82; // Fallback
}

// Stundenlohn an JS übergeben (WICHTIG: vor dem Modal!)
echo '<script>const DRIVER_WAGE = ' . json_encode($driverWage) . ';</script>';

// Aktuelles Datum
$today = date('Y-m-d');

// Filterparameter aus GET
$filter = [];
$filter['von_datum'] = $_GET['von_datum'] ?? $today;
$filter['bis_datum'] = date('Y-m-d', strtotime('+7 days'));
if (isset($_GET['status'])) {
    $filter['status'] = $_GET['status'];
}
if (isset($_GET['keine_zeiten'])) {
    $filter['keine_zeiten'] = ($_GET['keine_zeiten'] === '1');
}

// Fahrten abrufen
$fahrten_raw = getDriverRides($pdo, $fahrer_id, $filter);

// Aufbereiten für die Anzeige
$fahrten = [];
foreach ($fahrten_raw as $fahrt) {
    $fahrten[] = prepareRideForDisplay($fahrt);
}

// Heute, zukünftig, vergangen
$aktuelle_fahrten = [];
$future_fahrten = [];
$past_fahrten = [];

foreach ($fahrten as $ride) {
    $rideTs = strtotime($ride['abholdatum_raw']);
    $todayTs = strtotime($today);

    if ($rideTs === $todayTs) {
        $aktuelle_fahrten[] = $ride;
    } elseif ($rideTs > $todayTs) {
        $future_fahrten[] = $ride;
    } else {
        $past_fahrten[] = $ride;
    }
}

// Sortierung
usort($aktuelle_fahrten, function($a, $b) {
    $tsA = strtotime($a['abholdatum_raw'].' '.$a['abfahrtszeit_raw']);
    $tsB = strtotime($b['abholdatum_raw'].' '.$b['abfahrtszeit_raw']);
    return $tsA <=> $tsB;
});
usort($future_fahrten, function($a, $b) {
    $tsA = strtotime($a['abholdatum_raw'].' '.$a['abfahrtszeit_raw']);
    $tsB = strtotime($b['abholdatum_raw'].' '.$b['abfahrtszeit_raw']);
    return $tsA <=> $tsB;
});

// Nächste Fahrt / aktive Fahrt
$active_fahrt = null;
$next_fahrt = null;
$pending_fahrten = array_merge($aktuelle_fahrten, $future_fahrten);

// 1) aktive Fahrt suchen
foreach ($pending_fahrten as $ride) {
    if ($ride['ist_aktiv']) {
        $active_fahrt = $ride;
        break;
    }
}
// 2) wenn keine aktiv, erste anstehende
if (!$active_fahrt) {
    foreach ($pending_fahrten as $ride) {
        if (empty($ride['fahrzeit_bis'])) {
            $next_fahrt = $ride;
            break;
        }
    }
} else {
    $next_fahrt = $active_fahrt;
}

// Titel
$titleNextRide = "Nächste Fahrt";
if ($next_fahrt && $next_fahrt['ist_aktiv']) {
    $titleNextRide = "Aktuelle Fahrt";
}

// Debug & manuelle Zählung kommender Fahrten
$debug_today = $today;
$debug_future_count = count($future_fahrten);
$debug_fahrten_all = [];
$manual_future_count = 0;

foreach ($fahrten as $f) {
    $rideDate = strtotime($f['abholdatum_raw']);
    $todayDate = strtotime($today);
    $vergleich = 'gleich';

    if ($rideDate > $todayDate) {
        $vergleich = 'größer';
        $manual_future_count++;
    } elseif ($rideDate < $todayDate) {
        $vergleich = 'kleiner';
    }

    $debug_fahrten_all[] = [
        'id' => $f['id'],
        'datum' => $f['abholdatum_raw'],
        'vergleich' => $vergleich
    ];
}
?>
<div class="container mt-4">
  <!-- Dashboard Header -->
  <div class="card bg-primary text-white mb-4 border-0 shadow">
    <div class="card-body">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i> Fahrer-Dashboard</h1>
          <p class="lead mb-0">Willkommen zurück, <?= htmlspecialchars($user_name) ?>!</p>
        </div>
        <div class="col-md-4 text-md-end">
          <div class="d-flex flex-wrap justify-content-md-end gap-2 mt-3 mt-md-0">
            <?php if (count($aktuelle_fahrten) > 0): ?>
              <span class="badge bg-light text-primary fs-6 px-3 py-2">
                <i class="fas fa-calendar-day me-1"></i> Heute: <?= count($aktuelle_fahrten) ?>
              </span>
            <?php endif; ?>
            <?php if (count($future_fahrten) > 0): ?>
              <span class="badge bg-light text-primary fs-6 px-3 py-2">
                <i class="fas fa-calendar-week me-1"></i> Geplant: <?= count($future_fahrten) ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Widget: Nächste / Aktuelle Fahrt -->
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="card border-0 shadow <?= $next_fahrt && $next_fahrt['ist_aktiv'] ? 'bg-primary text-white' : '' ?>">
        <div class="card-header d-flex justify-content-between align-items-center py-3 <?= $next_fahrt && $next_fahrt['ist_aktiv'] ? 'bg-primary text-white border-0' : 'bg-light' ?>">
          <h5 class="mb-0 fs-4"><?= $titleNextRide ?></h5>
          <?php if ($next_fahrt): ?>
            <span class="badge <?= $next_fahrt['ist_aktiv'] ? 'bg-white text-primary' : 'bg-' . $next_fahrt['status_color'] ?> fs-6">
              <?= htmlspecialchars($next_fahrt['status']) ?>
            </span>
          <?php else: ?>
            <span class="badge bg-secondary">
              <i class="fas fa-exclamation-circle me-1"></i> Keine Fahrt
            </span>
          <?php endif; ?>
        </div>
        <div class="card-body p-4">
          <?php if ($next_fahrt): ?>
            <!-- Detailansicht der nächsten/aktuellen Fahrt -->
            <div class="row align-items-center">
                <div class="col-lg-4 text-center mb-3 mb-lg-0">
                    <div class="display-4 fw-bold <?= $next_fahrt['ist_aktiv'] ? 'text-warning' : 'text-primary' ?>">
                        <?= htmlspecialchars($next_fahrt['zeit']) ?>
                    </div>
                    <div class="fs-5 <?= $next_fahrt['ist_aktiv'] ? 'text-white-50' : 'text-muted' ?>">
                        <?= htmlspecialchars($next_fahrt['datum']) ?>
                    </div>
                </div>
                <div class="col-lg-8">
                    <h5 class="card-title fs-3 d-flex align-items-center mb-3">
                        <i class="fas fa-user me-2"></i>
                        <?php if ($next_fahrt['is_firma']): ?>
                            <span class="badge bg-info me-1">Firma</span>
                            <?= htmlspecialchars($next_fahrt['kunde']) ?>
                            <?php if (!empty($next_fahrt['ansprechpartner'])): ?>
                                <small class="<?= $next_fahrt['ist_aktiv'] ? 'text-white-50' : 'text-muted' ?>">
                                    (Ansprechpartner: <?= htmlspecialchars($next_fahrt['ansprechpartner']) ?>)
                                </small>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= htmlspecialchars($next_fahrt['kunde']) ?>
                        <?php endif; ?>
                    </h5>
                    <p class="fs-5 mb-0">
                        <i class="fas fa-map-marker-alt me-2"></i> <?= htmlspecialchars($next_fahrt['route']) ?>
                    </p>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="mb-2 d-flex align-items-center">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <?= htmlspecialchars($next_fahrt['datum']) ?>
                            </div>
                            <div class="mb-0 d-flex align-items-center">
                                <i class="fas fa-car-side me-2"></i>
                                <?= htmlspecialchars($next_fahrt['fahrzeug']) ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2 d-flex align-items-center">
                                <i class="fas fa-users me-2"></i>
                                <?= !empty($next_fahrt['personen']) ? $next_fahrt['personen'].' Personen' : 'Personenzahl nicht angegeben' ?>
                            </div>
                            <?php if (!empty($next_fahrt['flugnummer'])): ?>
                                <div class="mb-0 d-flex align-items-center">
                                    <i class="fas fa-plane me-2"></i>
                                    Flug: <?= htmlspecialchars($next_fahrt['flugnummer']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <!-- Fahrt starten/beenden -->
                        <?php if ($next_fahrt['ist_aktiv']): ?>
                            <button id="endFahrtBtn" data-id="<?= htmlspecialchars($next_fahrt['id']) ?>" class="btn btn-danger">
                                <i class="fas fa-stop-circle me-1"></i> Fahrt beenden
                            </button>
                        <?php else: ?>
                            <button id="startFahrtBtn" data-id="<?= htmlspecialchars($next_fahrt['id']) ?>" class="btn btn-success">
                                <i class="fas fa-play-circle me-1"></i> Fahrt starten
                            </button>
                        <?php endif; ?>

                        <!-- Details anzeigen -->
                        <button class="btn btn-outline-primary open-fahrt-modal" <?= generateModalAttributes($next_fahrt); ?>>
                            <i class="fas fa-info-circle me-1"></i> Details
                        </button>

                        <!-- Navigation -->
                        <a href="https://www.google.com/maps/dir/?api=1&origin=<?= urlencode($next_fahrt['start']) ?>&destination=<?= urlencode($next_fahrt['ziel']) ?>"
                           target="_blank" class="btn btn-outline-success">
                            <i class="fas fa-directions me-1"></i> Navigation
                        </a>
                    </div>
                </div>
            </div>
          <?php else: ?>
            <div class="text-center py-4">
              <i class="fas fa-calendar-times fa-3x mb-3 text-muted"></i>
              <h5 class="mb-0">Aktuell ist keine Fahrt geplant.</h5>
              <p class="text-muted">Sie können alle Ihre Fahrten in der <a href="fahrten.php">Fahrtenübersicht</a> sehen.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Fahrtenübersicht (Tabs: Heute / Kommend) -->
  <div class="row mb-4">
    <div class="col-lg-8">
      <div class="card border-0 shadow">
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
          <h5 class="card-title mb-0">
            <i class="fas fa-calendar-check me-2"></i> Fahrtenübersicht
          </h5>
          <a href="fahrten.php" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-list me-1"></i> Alle anzeigen
          </a>
        </div>
        <div class="card-body">

          <?php if (empty($aktuelle_fahrten) && empty($future_fahrten)): ?>
            <div class="alert alert-info">
              <i class="fas fa-info-circle me-2"></i>
              Sie haben aktuell keine offenen Fahrten.
            </div>
          <?php else: ?>
            <!-- Tabs: Heute / Kommend -->
            <ul class="nav nav-tabs" id="fahrtTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="heute-tab" data-bs-toggle="tab" data-bs-target="#heute" type="button" role="tab">
                  <i class="fas fa-calendar-day me-1"></i> Heute
                  <span class="badge bg-secondary ms-1"><?= count($aktuelle_fahrten) ?></span>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="kommend-tab" data-bs-toggle="tab" data-bs-target="#kommend" type="button" role="tab">
                  <i class="fas fa-calendar-week me-1"></i> Kommend
                  <span class="badge bg-secondary ms-1"><?= $manual_future_count ?></span>
                </button>
              </li>
            </ul>

            <div class="tab-content mt-3" id="fahrtTabsContent">
              <!-- Heute Tab -->
              <div class="tab-pane fade show active" id="heute" role="tabpanel">
                <?php if (empty($aktuelle_fahrten)): ?>
                  <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Keine Fahrten für heute geplant.
                  </div>
                <?php else: ?>
                  <?php foreach ($aktuelle_fahrten as $ride): ?>
                    <div class="card mb-3 border-0 bg-light">
                      <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                          <h5 class="mb-0 text-primary fw-bold">
                            <?= htmlspecialchars($ride['zeit']) ?> Uhr
                          </h5>
                          <span class="badge bg-<?= htmlspecialchars($ride['status_color']) ?>">
                            <?= htmlspecialchars($ride['status']) ?>
                          </span>
                        </div>
                        <p class="mb-1 fw-bold">
                          <?php if ($ride['is_firma']): ?>
                            <span class="badge bg-info me-1">Firma</span>
                            <?= htmlspecialchars($ride['kunde']) ?>
                          <?php else: ?>
                            <?= htmlspecialchars($ride['kunde']) ?>
                          <?php endif; ?>
                        </p>
                        <p class="mb-2 text-muted small">
                          <i class="fas fa-map-marker-alt me-1"></i>
                          <?= htmlspecialchars($ride['route']) ?>
                        </p>
                        <?php if (!empty($ride['flugnummer'])): ?>
                          <p class="mb-2 small">
                            <i class="fas fa-plane me-1"></i> Flug: <?= htmlspecialchars($ride['flugnummer']) ?>
                            <span class="flight-status ms-1 badge bg-secondary"
                                  data-flightnumber="<?= htmlspecialchars($ride['flugnummer']) ?>"
                                  data-abholort="<?= htmlspecialchars($ride['start']) ?>"
                                  data-zielort="<?= htmlspecialchars($ride['ziel']) ?>"
                                  data-date="<?= htmlspecialchars($ride['abholdatum_raw']) ?>">
                              <i class="fas fa-sync-alt fa-spin"></i>
                            </span>
                          </p>
                        <?php endif; ?>
                        <div class="mt-3 text-end">
                          <button class="btn btn-sm btn-outline-primary me-1 open-fahrt-modal" <?= generateModalAttributes($ride); ?>>
                            <i class="fas fa-edit me-1"></i> Details
                          </button>
                          <a href="https://www.google.com/maps/dir/?api=1&origin=<?= urlencode($ride['start']) ?>&destination=<?= urlencode($ride['ziel']) ?>"
                             target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-directions"></i> NAV
                          </a>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <!-- Kommend Tab -->
              <div class="tab-pane fade" id="kommend" role="tabpanel">
                <?php if (empty($future_fahrten)): ?>
                  <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Keine kommenden Fahrten geplant.
                  </div>
                <?php else: ?>
                  <?php
                    $currentDate = '';
                    foreach ($future_fahrten as $ride):
                      $date = $ride['datum'];
                      if ($currentDate != $date):
                        $currentDate = $date;
                  ?>
                    <div class="bg-light text-center fw-bold py-2 mb-2 rounded">
                      <i class="fas fa-calendar-alt me-1"></i>
                      <?= htmlspecialchars($ride['wochentag']) ?>, <?= $date ?>
                    </div>
                  <?php endif; ?>
                    <div class="card mb-3 border-0 bg-light">
                      <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                          <h5 class="mb-0 text-primary fw-bold">
                            <?= htmlspecialchars($ride['zeit']) ?> Uhr
                          </h5>
                          <span class="badge bg-<?= htmlspecialchars($ride['status_color']) ?>">
                            <?= htmlspecialchars($ride['status']) ?>
                          </span>
                        </div>
                        <p class="mb-1 fw-bold">
                          <?php if ($ride['is_firma']): ?>
                            <span class="badge bg-info me-1">Firma</span>
                            <?= htmlspecialchars($ride['kunde']) ?>
                          <?php else: ?>
                            <?= htmlspecialchars($ride['kunde']) ?>
                          <?php endif; ?>
                        </p>
                        <p class="mb-2 text-muted small">
                          <i class="fas fa-map-marker-alt me-1"></i>
                          <?= htmlspecialchars($ride['route']) ?>
                        </p>
                        <?php if (!empty($ride['flugnummer'])): ?>
                          <p class="mb-2 small">
                            <i class="fas fa-plane me-1"></i> Flug: <?= htmlspecialchars($ride['flugnummer']) ?>
                          </p>
                        <?php endif; ?>
                        <div class="mt-3 text-end">
                          <button class="btn btn-sm btn-outline-primary me-1 open-fahrt-modal" <?= generateModalAttributes($ride); ?>>
                            <i class="fas fa-edit me-1"></i> Details
                          </button>
                          <a href="https://www.google.com/maps/dir/?api=1&origin=<?= urlencode($ride['start']) ?>&destination=<?= urlencode($ride['ziel']) ?>"
                             target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-directions"></i> NAV
                          </a>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <!-- Sidebar Widgets -->
    <div class="col-lg-4 col-md-12">
      <div class="row g-4">
        <!-- Alle Fahrten Widget -->
        <div class="col-md-6 col-lg-12">
          <div class="card border-0 shadow h-100">
            <div class="card-body d-flex flex-column">
              <div class="text-center mb-3">
                <i class="fas fa-list-alt fa-3x text-primary"></i>
              </div>
              <h5 class="card-title text-center">Fahrtenübersicht</h5>
              <p class="card-text text-center flex-grow-1">Alle Ihre Fahrten anzeigen und verwalten.</p>
              <a href="fahrten.php" class="btn btn-primary mt-auto">
                <i class="fas fa-list me-1"></i> Zur Fahrtenübersicht
              </a>
            </div>
          </div>
        </div>
        
        <!-- Gutschriften Widget -->
        <div class="col-md-6 col-lg-12">
          <div class="card border-0 shadow h-100">
            <div class="card-body d-flex flex-column">
              <div class="text-center mb-3">
                <i class="fas fa-receipt fa-3x text-primary"></i>
              </div>
              <h5 class="card-title text-center">Gutschriften</h5>
              <p class="card-text text-center flex-grow-1">Gutschriften und Abrechnungen verwalten.</p>
              <a href="gutschriften.php" class="btn btn-primary mt-auto">
                <i class="fas fa-receipt me-1"></i> Zu den Gutschriften
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal zum Bearbeiten -->
<?php include __DIR__ . '/fahrer_modal.php'; ?>

<!-- Footer -->
<?php require_once __DIR__ . '/../assets/footer.php'; ?>

<!-- JavaScript für Flugstatus, Fahrtaktualisierungen usw. -->
<script>
document.addEventListener('DOMContentLoaded', function() {

  // ============= FLUGSTATUS =============
  document.querySelectorAll('.flight-status').forEach(function(el) {
    const flightNumber = el.dataset.flightnumber;
    const date = el.dataset.date;
    const abholort = el.dataset.abholort;
    const zielort = el.dataset.zielort;
    
    if (flightNumber && date) {
      fetchFlightStatus(flightNumber, date, abholort, zielort, function(status) {
        el.innerHTML = status;
        
        // Farbe basierend auf Status setzen
        if (status.includes('Pünktlich') || status.includes('Gelandet')) {
          el.classList.remove('bg-secondary');
          el.classList.add('bg-success');
        } else if (status.includes('Verspätet') || status.includes('verzögert')) {
          el.classList.remove('bg-secondary');
          el.classList.add('bg-warning', 'text-dark');
        } else if (status.includes('Gestrichen') || status.includes('Fehler')) {
          el.classList.remove('bg-secondary');
          el.classList.add('bg-danger');
        }
      });
    }
  });
  
  function fetchFlightStatus(flightNumber, date, abholort, zielort, callback) {
    const isAirport = (str) => {
      str = (str || '').toLowerCase();
      return str.includes('flughafen') || str.includes('airport');
    };
    
    let flightType = '';
    if (isAirport(abholort)) {
      flightType = 'arrivals';   // Abholen am Flughafen -> Ankunft
    } else if (isAirport(zielort)) {
      flightType = 'departures'; // Ziel ist Flughafen -> Abflug
    }
    
    if (!flightType) {
      callback('Kein Flughafen');
      return;
    }
    
    fetch(`/fahrer/flugstatus_proxy.php?type=${flightType}&from=${date}&to=${date}&flightNumber=${encodeURIComponent(flightNumber)}`)
      .then(res => res.json())
      .then(data => {
        if (data.error) {
          callback('Keine Daten');
          return;
        }
        
        let flight = null;
        if (Array.isArray(data.flights) && data.flights.length > 0) {
          flight = data.flights[0];
        } else if (Array.isArray(data.data) && data.data.length > 0) {
          flight = data.data[0];
        } else {
          const numericKeys = Object.keys(data).filter(k => !isNaN(Number(k)));
          if (numericKeys.length > 0) {
            flight = data[numericKeys[0]];
          }
        }
        
        if (!flight) {
          callback('Keine Daten');
          return;
        }
        
        let status = 'Pünktlich';
        
        // flightStatusDeparture/flightStatusArrival auswerten
        if (flight.flightStatusDeparture) {
          switch(flight.flightStatusDeparture.toUpperCase()) {
            case 'DEP': status = 'Abgeflogen'; break;
            case 'DEL': status = 'Verspätet'; break;
            case 'BRD': status = 'Boarding'; break;
            case 'CNL': status = 'Gestrichen'; break;
            default:    status = flight.flightStatusDeparture;
          }
        } else if (flight.flightStatusArrival) {
          switch(flight.flightStatusArrival.toUpperCase()) {
            case 'ARR': status = 'Gelandet'; break;
            case 'DEL': status = 'Verspätet'; break;
            case 'DIV': status = 'Umgeleitet'; break;
            case 'CNL': status = 'Gestrichen'; break;
            default:    status = flight.flightStatusArrival;
          }
        } else {
          // Verspätung checken
          const planned = flight.plannedDepartureTime || flight.plannedArrivalTime;
          const expected = flight.expectedDepartureTime || flight.expectedArrivalTime;
          
          if (planned && expected) {
            const plannedDate = new Date(planned.replace(/\[.*$/, ''));
            const expectedDate = new Date(expected.replace(/\[.*$/, ''));
            if (!isNaN(plannedDate) && !isNaN(expectedDate)) {
              const diffMins = Math.round((expectedDate - plannedDate) / 60000);
              if (diffMins > 10) {
                status = `Verspätet (+${diffMins} Min)`;
              }
            }
          }
        }
        
        callback(status);
      })
      .catch(err => {
        callback('Fehler');
        console.error('Fehler beim Abrufen des Flugstatus:', err);
      });
  }

  // ============= FAHRT STARTEN / BEENDEN =============
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
  
  function startFahrt(fahrtId) {
    if (!fahrtId) return;
    const jetzt = new Date();
    const zeit = jetzt.getHours().toString().padStart(2, '0') + ':' + jetzt.getMinutes().toString().padStart(2, '0');
    
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
    const zeit = jetzt.getHours().toString().padStart(2, '0') + ':' + jetzt.getMinutes().toString().padStart(2, '0');
    
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
