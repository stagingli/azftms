<?php
/**
 * fahrtenstatistiken.php
 * Statistikseite für das Hauptgeschäft "Fahrten"
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/helpers.php';

// Header einbinden
require_once __DIR__ . '/../assets/header.php';

date_default_timezone_set('Europe/Berlin');

// ----------------------------------------------------------------
// Zeitraum-Filter: Standardmäßig vom 1. Januar des aktuellen Jahres bis heute
// ----------------------------------------------------------------
$defaultVonDatum = date('Y-01-01');
$defaultBisDatum = date('Y-m-d');

$von_datum = $_GET['von_datum'] ?? $defaultVonDatum;
$bis_datum = $_GET['bis_datum'] ?? $defaultBisDatum;

// Optionaler Fahrer-Filter (falls gesetzt)
$fahrer_id = $_GET['fahrer_id'] ?? '';

// ----------------------------------------------------------------
// Aggregierte Kennzahlen ermitteln
// ----------------------------------------------------------------
$sql_agg = "SELECT 
              COUNT(*) AS ride_count,
              SUM(TIME_TO_SEC(IFNULL(fahrzeit_summe, '00:00:00'))/60) AS total_fahrzeit,
              SUM(IFNULL(wartezeit, 0)) AS total_wartezeit,
              SUM(IFNULL(ausgaben, 0)) AS total_ausgaben,
              SUM(IFNULL(lohn_fahrt, 0)) AS total_fahrerlohn,
              SUM(IFNULL(fahrtpreis, 0)) AS total_umsatz,
              SUM(IFNULL(fahrtpreis, 0) - IFNULL(lohn_fahrt, 0)) AS total_ergebnis
            FROM fahrten
            WHERE abholdatum BETWEEN :von_datum AND :bis_datum
            AND deleted_at IS NULL";

if (!empty($fahrer_id)) {
    $sql_agg .= " AND fahrer_id = :fahrer_id";
}

if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $params = [':von_datum' => $von_datum, ':bis_datum' => $bis_datum];
    if (!empty($fahrer_id)) {
        $params[':fahrer_id'] = $fahrer_id;
    }
    writeLog("Filter-Debug: SQL=" . $sql_agg . ", Params=" . json_encode($params), 'DEBUG', DEBUGGING_LOG_FILE);
}

$stmt = $pdo->prepare($sql_agg);
$stmt->bindValue(':von_datum', $von_datum);
$stmt->bindValue(':bis_datum', $bis_datum);
if (!empty($fahrer_id)) {
    $stmt->bindValue(':fahrer_id', $fahrer_id);
}
$stmt->execute();
$agg = $stmt->fetch(PDO::FETCH_ASSOC);

// ----------------------------------------------------------------
// Kennzahl: Fahrtanzahl im aktuellen Monat
// ----------------------------------------------------------------
$currentMonthStart = date('Y-m-01');
$currentMonthEnd = date('Y-m-t');
$sql_month = "SELECT COUNT(*) AS ride_count_month 
              FROM fahrten 
              WHERE abholdatum BETWEEN :month_start AND :month_end
              AND deleted_at IS NULL";
if (!empty($fahrer_id)) {
    $sql_month .= " AND fahrer_id = :fahrer_id";
}
$stmt = $pdo->prepare($sql_month);
$stmt->bindValue(':month_start', $currentMonthStart);
$stmt->bindValue(':month_end', $currentMonthEnd);
if (!empty($fahrer_id)) {
    $stmt->bindValue(':fahrer_id', $fahrer_id);
}
$stmt->execute();
$monthData = $stmt->fetch(PDO::FETCH_ASSOC);

// ----------------------------------------------------------------
// Graph: Monatliche Fahrtenentwicklung im laufenden Jahr
// ----------------------------------------------------------------
$currentYear = date('Y');
$yearStart = $currentYear . '-01-01';
$yearEnd = $currentYear . '-12-31';
$sql_graph = "SELECT MONTH(abholdatum) AS month, COUNT(*) AS ride_count,
                    SUM(IFNULL(fahrtpreis, 0)) AS monthly_revenue
              FROM fahrten 
              WHERE abholdatum BETWEEN :year_start AND :year_end 
              AND deleted_at IS NULL";
if (!empty($fahrer_id)) {
    $sql_graph .= " AND fahrer_id = :fahrer_id";
}
$sql_graph .= " GROUP BY MONTH(abholdatum)
                ORDER BY month ASC";
$stmt = $pdo->prepare($sql_graph);
$stmt->bindValue(':year_start', $yearStart);
$stmt->bindValue(':year_end', $yearEnd);
if (!empty($fahrer_id)) {
    $stmt->bindValue(':fahrer_id', $fahrer_id);
}
$stmt->execute();
$graphData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Für den Chart: Arrays für alle 12 Monate initialisieren (Standard: 0 Fahrten, 0 Umsatz)
$monthlyCounts = array_fill(1, 12, 0);
$monthlyRevenue = array_fill(1, 12, 0);

foreach ($graphData as $row) {
    $month = (int)$row['month'];
    $monthlyCounts[$month] = (int)$row['ride_count'];
    $monthlyRevenue[$month] = (float)$row['monthly_revenue'];
}

// Fahrerdetails abrufen, wenn ein Filter gesetzt ist
$fahrerName = '';
if (!empty($fahrer_id)) {
    $stmt_fahrerdetail = $pdo->prepare("SELECT name FROM nutzer WHERE id = :fahrer_id");
    $stmt_fahrerdetail->bindValue(':fahrer_id', $fahrer_id);
    $stmt_fahrerdetail->execute();
    $fahrerDetail = $stmt_fahrerdetail->fetch(PDO::FETCH_ASSOC);
    $fahrerName = $fahrerDetail['name'] ?? 'Unbekannt';
}
?>

<div class="container mt-4">
    <h1 class="mb-4">Statistiken zu Fahrten</h1>
    
    <!-- Zeitraum- und Fahrer-Filter -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Zeitraum wählen</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="von_datum" class="form-label">Von Datum</label>
                    <input type="date" id="von_datum" name="von_datum" class="form-control" value="<?= htmlspecialchars($von_datum) ?>">
                </div>
                <div class="col-md-3">
                    <label for="bis_datum" class="form-label">Bis Datum</label>
                    <input type="date" id="bis_datum" name="bis_datum" class="form-control" value="<?= htmlspecialchars($bis_datum) ?>">
                </div>
                <div class="col-md-3">
                    <label for="fahrer_id" class="form-label">Fahrer (optional)</label>
                    <select id="fahrer_id" name="fahrer_id" class="form-select">
                        <option value="">Alle Fahrer</option>
                        <?php
                        // Fahrer aus Datenbank laden
                        $sql_fahrer = "SELECT n.id, n.name 
                                    FROM nutzer n 
                                    JOIN nutzer_rolle nr ON n.id = nr.nutzer_id 
                                    JOIN rollen r ON nr.rolle_id = r.id 
                                    WHERE r.name = 'fahrer' 
                                    ORDER BY n.name ASC";
                        $stmt_fahrer = $pdo->prepare($sql_fahrer);
                        $stmt_fahrer->execute();
                        $fahrerList = $stmt_fahrer->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach($fahrerList as $fahrer): 
                            $selected = ($fahrer_id == $fahrer['id']) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($fahrer['id']) ?>" <?= $selected ?>>
                                <?= htmlspecialchars($fahrer['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter me-1"></i> Filter anwenden
                    </button>
                    <?php if (!empty($fahrer_id)): ?>
                        <a href="?von_datum=<?= htmlspecialchars($von_datum) ?>&bis_datum=<?= htmlspecialchars($bis_datum) ?>" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-x-circle me-1"></i> Fahrer-Filter zurücksetzen
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Aktueller Filter-Status -->
    <?php if (!empty($fahrer_id)): ?>
    <div class="alert alert-info mt-3 mb-4">
        <i class="bi bi-info-circle me-2"></i>
        Statistiken gefiltert für Fahrer: <strong><?= htmlspecialchars($fahrerName) ?></strong>
        für den Zeitraum vom <strong><?= date('d.m.Y', strtotime($von_datum)) ?></strong> bis 
        <strong><?= date('d.m.Y', strtotime($bis_datum)) ?></strong>
    </div>
    <?php elseif($von_datum !== $defaultVonDatum || $bis_datum !== $defaultBisDatum): ?>
    <div class="alert alert-info mt-3 mb-4">
        <i class="bi bi-info-circle me-2"></i>
        Statistiken für alle Fahrer im Zeitraum vom <strong><?= date('d.m.Y', strtotime($von_datum)) ?></strong> bis 
        <strong><?= date('d.m.Y', strtotime($bis_datum)) ?></strong>
    </div>
    <?php endif; ?>
    
    <!-- Kennzahlen anzeigen -->
    <div class="row mb-4">
        <!-- Erste Zeile: 4 Karten -->
        <div class="col-md-3">
            <div class="card text-white bg-info mb-3 h-100">
                <div class="card-header">Fahrtanzahl (Zeitraum)</div>
                <div class="card-body">
                    <h5 class="card-title fs-1"><?= number_format($agg['ride_count'] ?? 0, 0, ',', '.') ?></h5>
                    <p class="card-text">Fahrten insgesamt</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-secondary mb-3 h-100">
                <div class="card-header">Fahrtanzahl (aktueller Monat)</div>
                <div class="card-body">
                    <h5 class="card-title fs-1"><?= number_format($monthData['ride_count_month'] ?? 0, 0, ',', '.') ?></h5>
                    <p class="card-text">Fahrten in <?= date('F Y') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3 h-100">
                <div class="card-header">Fahrzeiten gesamt (Minuten)</div>
                <div class="card-body">
                    <h5 class="card-title fs-1"><?= number_format($agg['total_fahrzeit'] ?? 0, 0, ',', '.') ?></h5>
                    <p class="card-text">Minuten (<?= number_format(($agg['total_fahrzeit'] ?? 0)/60, 1, ',', '.') ?> Stunden)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger mb-3 h-100">
                <div class="card-header">Wartezeit Summe (Minuten)</div>
                <div class="card-body">
                    <h5 class="card-title fs-1"><?= number_format($agg['total_wartezeit'] ?? 0, 0, ',', '.') ?></h5>
                    <p class="card-text">Minuten Wartezeit</p>
                </div>
            </div>
        </div>
        
        <!-- Zweite Zeile: 4 Karten -->
        <div class="col-md-3">
            <div class="card text-dark bg-warning mb-3 h-100">
                <div class="card-header">Ausgaben Summe</div>
                <div class="card-body">
                    <h5 class="card-title fs-1"><?= formatCurrency((float)($agg['total_ausgaben'] ?? 0)) ?></h5>
                    <p class="card-text">Gesamte Ausgaben</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-dark mb-3 h-100">
                <div class="card-header">Fahrerlohn gesamt</div>
                <div class="card-body">
                    <h5 class="card-title fs-1"><?= formatCurrency((float)($agg['total_fahrerlohn'] ?? 0)) ?></h5>
                    <p class="card-text">Lohn für Fahrer</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3 h-100">
                <div class="card-header">Umsatz Summe</div>
                <div class="card-body">
                    <h5 class="card-title fs-1"><?= formatCurrency((float)($agg['total_umsatz'] ?? 0)) ?></h5>
                    <p class="card-text">Brutto-Einnahmen</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light mb-3 h-100">
                <div class="card-header">Ergebnis (Umsatz - Lohn)</div>
                <div class="card-body">
                    <h5 class="card-title fs-1 <?= ($agg['total_ergebnis'] < 0) ? 'text-danger' : 'text-success' ?>">
                        <?= formatCurrency((float)($agg['total_ergebnis'] ?? 0)) ?>
                    </h5>
                    <p class="card-text">Netto-Ergebnis</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Grafische Darstellung: Monatliche Fahrtenentwicklung -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Fahrtentwicklung über das Jahr <?= htmlspecialchars($currentYear) ?></h5>
        </div>
        <div class="card-body">
            <canvas id="rideChart" width="400" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Chart.js über CDN einbinden -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('rideChart').getContext('2d');
    const rideChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
            datasets: [
                {
                    label: 'Fahrten',
                    data: <?= json_encode(array_values($monthlyCounts)) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Umsatz (€)',
                    data: <?= json_encode(array_values($monthlyRevenue)) ?>,
                    type: 'line',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Anzahl Fahrten'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false,
                    },
                    title: {
                        display: true,
                        text: 'Umsatz (€)'
                    }
                }
            }
        }
    });
</script>

<?php
require_once __DIR__ . '/../assets/footer.php';
?>