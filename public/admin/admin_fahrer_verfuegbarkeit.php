<?php
// admin_fahrer_verfuegbarkeit.php - Admin-Ansicht für Fahrer-Verfügbarkeiten
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../assets/header.php';



// Ermittlung des anzuzeigenden Monats und Jahres
$display_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$display_year  = isset($_GET['year'])  ? (int)$_GET['year']  : date('Y');
$month_start   = date('Y-m-01', strtotime("$display_year-$display_month-01"));
$month_end     = date('Y-m-t', strtotime("$display_year-$display_month-01"));

// Alle Fahrer für den Filter abfragen
$fahrer_stmt = $pdo->query("SELECT DISTINCT n.id, n.name 
    FROM nutzer n
    INNER JOIN nutzer_rolle nr ON n.id = nr.nutzer_id
    INNER JOIN rollen r ON nr.rolle_id = r.id
    WHERE r.name = 'fahrer'
    ORDER BY n.name ASC");
$alle_fahrer = $fahrer_stmt->fetchAll(PDO::FETCH_ASSOC);

$filter_fahrer = (isset($_GET['fahrer']) && is_numeric($_GET['fahrer'])) ? (int)$_GET['fahrer'] : null;

// UNION-Query: Normale Verfügbarkeiten und zyklische (Muster) Verfügbarkeiten zusammenführen
$query = "SELECT * FROM (
  -- Normale Verfügbarkeiten
  SELECT 
    fv.id, 
    fv.nutzer_id, 
    fv.datum_von, 
    fv.datum_bis, 
    fv.typ, 
    fv.ganztags, 
    fv.zeit_von, 
    fv.zeit_bis, 
    n.name AS fahrer_name,
    0 AS ist_muster,
    NULL AS wochentag
  FROM fahrer_verfuegbarkeit fv
  INNER JOIN nutzer n ON fv.nutzer_id = n.id
  INNER JOIN nutzer_rolle nr ON n.id = nr.nutzer_id
  INNER JOIN rollen r ON nr.rolle_id = r.id
  WHERE r.name = 'fahrer'
    AND (
      (fv.datum_von BETWEEN :start AND :end)
      OR (fv.datum_bis BETWEEN :start AND :end)
      OR (:start BETWEEN fv.datum_von AND fv.datum_bis)
    )
  UNION ALL
  -- Zyklische Verfügbarkeiten (Muster)
  SELECT 
    fvm.id,
    fvm.nutzer_id,
    :start AS datum_von,
    :end AS datum_bis,
    fvm.typ,
    fvm.ganztags,
    fvm.zeit_von,
    fvm.zeit_bis,
    n.name AS fahrer_name,
    1 AS ist_muster,
    fvm.wochentag
  FROM fahrer_verfuegbarkeit_muster fvm
  INNER JOIN nutzer n ON fvm.nutzer_id = n.id
  INNER JOIN nutzer_rolle nr ON n.id = nr.nutzer_id
  INNER JOIN rollen r ON nr.rolle_id = r.id
  WHERE r.name = 'fahrer'
    AND fvm.aktiv = 1
) AS combined";

// Falls ein Fahrerfilter gesetzt ist, erweitern wir den Query
$params = [
    ':start' => $month_start,
    ':end'   => $month_end
];
if ($filter_fahrer) {
    $query = "SELECT * FROM ($query) AS filtered WHERE nutzer_id = :fahrer_id ORDER BY datum_von ASC";
    $params[':fahrer_id'] = $filter_fahrer;
} else {
    $query .= " ORDER BY datum_von ASC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$verfuegbarkeiten = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Array zur Übersetzung des Wochentags in den gewünschten Text
$wochentage = [
    1 => 'jeden Montag',
    2 => 'jeden Dienstag',
    3 => 'jeden Mittwoch',
    4 => 'jeden Donnerstag',
    5 => 'jeden Freitag',
    6 => 'jeden Samstag',
    7 => 'jeden Sonntag'
];
?>
<div class="container mt-4">
    <h1><i class="bi bi-calendar-check me-2"></i> Verfügbarkeitskalender</h1>

    <form method="GET" class="row mb-3">
        <div class="col-md-3">
            <label for="month" class="form-label">Monat</label>
            <select id="month" name="month" class="form-select">
                <?php 
                $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'LLLL');
                for ($m = 1; $m <= 12; $m++): 
                    $dateObj = DateTime::createFromFormat('!m', $m);
                    $monthName = $formatter->format($dateObj);
                ?>
                    <option value="<?= $m ?>" <?= $m == $display_month ? 'selected' : '' ?>><?= $monthName ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="year" class="form-label">Jahr</label>
            <select id="year" name="year" class="form-select">
                <?php for ($y = date('Y'); $y <= date('Y') + 2; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $display_year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="fahrer" class="form-label">Fahrer filtern</label>
            <select id="fahrer" name="fahrer" class="form-select">
                <option value="">Alle Fahrer</option>
                <?php foreach ($alle_fahrer as $fahrer): ?>
                    <option value="<?= $fahrer['id'] ?>" <?= $filter_fahrer == $fahrer['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fahrer['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Anzeigen</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped" id="verfuegbarkeit-liste">
            <thead>
                <tr>
                    <th>Datum von</th>
                    <th>Datum bis</th>
                    <th>Fahrer</th>
                    <th>Typ</th>
                    <th>Ganztags</th>
                    <th>Zeit von</th>
                    <th>Zeit bis</th>
                    <th>Zyklisch</th>
                    <th>Wochentag</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($verfuegbarkeiten as $v): ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime($v['datum_von'])) ?></td>
                        <td><?= date('d.m.Y', strtotime($v['datum_bis'])) ?></td>
                        <td><?= htmlspecialchars($v['fahrer_name']) ?></td>
                        <td>
                            <?php 
                                if ($v['typ'] === 'verfuegbar') {
                                    echo 'Verfügbar';
                                } elseif ($v['typ'] === 'nicht_verfuegbar') {
                                    echo 'Nicht verfügbar';
                                } elseif ($v['typ'] === 'urlaub') {
                                    echo 'Urlaub';
                                } else {
                                    echo htmlspecialchars($v['typ']);
                                }
                            ?>
                        </td>
                        <td><?= $v['ganztags'] ? 'Ja' : 'Nein' ?></td>
                        <td><?= $v['ganztags'] ? '-' : htmlspecialchars(substr($v['zeit_von'], 0, 5)) ?></td>
                        <td><?= $v['ganztags'] ? '-' : htmlspecialchars(substr($v['zeit_bis'], 0, 5)) ?></td>
                        <td><?= $v['ist_muster'] == 1 ? 'Ja' : 'Nein' ?></td>
                        <td>
                            <?php 
                              if (!empty($v['wochentag']) && isset($wochentage[$v['wochentag']])) {
                                  echo $wochentage[$v['wochentag']];
                              } else {
                                  echo "-";
                              }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#verfuegbarkeit-liste').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json' },
        order: [[0, 'asc']],
        paging: false
    });
});
</script>
<?php require_once __DIR__ . '/../assets/footer.php'; ?>
