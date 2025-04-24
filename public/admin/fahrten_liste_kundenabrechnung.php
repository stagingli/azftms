<?php
/**
 * fahrten_liste_allefahrten.php
 * Zeigt eine einfache Liste aller Fahrten an mit Kunden-/Firmenadresse und Kundenname.
 */

// ============================================================
// EINBINDUNGEN
// ============================================================
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../assets/header.php';

define('DEBUG_MODE', true);
date_default_timezone_set('Europe/Berlin');

// ============================================================
// HILFSFUNKTIONEN
// ============================================================
function setMySQLTimezone(PDO $pdo) {
    try {
        $offset = (new DateTime())->format('P');
        $pdo->exec("SET time_zone = '$offset'");
        if (DEBUG_MODE) error_log("MySQL-Zeitzone gesetzt auf $offset");
    } catch (PDOException $e) {
        error_log("Fehler beim Setzen der MySQL-Zeitzone: " . $e->getMessage());
    }
}

function formatDatum($d) {
    if (!$d) return '';
    try { return (new DateTime($d))->format('d.m.Y'); }
    catch (Exception $e) { return htmlspecialchars($d, ENT_QUOTES, 'UTF-8'); }
}

function formatZeit($t) {
    if (!$t) return '';
    if (preg_match('/^\d{2}:\d{2}/', $t)) {
        list($h, $m) = explode(':', $t);
        return sprintf('%02d:%02d Uhr', $h, $m);
    }
    try { return (new DateTime($t))->format('H:i') . ' Uhr'; }
    catch (Exception $e) { return htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); }
}

function formatGeld($b) {
    return number_format((float)$b, 2, ',', '.') . ' €';
}

function getKundenadresse(int $id, PDO $pdo): string {
    $stmt = $pdo->prepare(
        "SELECT kundentyp, firmenname, firmenanschrift, strasse, hausnummer, plz, ort FROM kunden WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);
    $k = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$k) return '';
    $addr = [];
    if ($k['kundentyp'] === 'firma' && $k['firmenname']) {
        $addr[] = '<strong>' . htmlspecialchars($k['firmenname'], ENT_QUOTES, 'UTF-8') . '</strong>';
        if ($k['firmenanschrift']) {
            $addr[] = htmlspecialchars($k['firmenanschrift'], ENT_QUOTES, 'UTF-8');
            return implode('<br>', $addr);
        }
    }
    if ($k['strasse'] || $k['hausnummer']) {
        $addr[] = htmlspecialchars(trim($k['strasse'].' '.$k['hausnummer']), ENT_QUOTES, 'UTF-8');
    }
    if ($k['plz'] || $k['ort']) {
        $addr[] = htmlspecialchars(trim($k['plz'].' '.$k['ort']), ENT_QUOTES, 'UTF-8');
    }
    return implode('<br>', $addr);
}

function formatiereOrt(int $oid, string $wert, int $kid, PDO $pdo): string {
    $key = strtolower($wert);
    if (in_array($key, ['kundenadresse', 'firmenadresse'])) {
        return getKundenadresse($kid, $pdo) ?: htmlspecialchars($wert, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($wert, ENT_QUOTES, 'UTF-8');
}

// ============================================================
// DATENLADUNG
// ============================================================
setMySQLTimezone($pdo);
$startTime = microtime(true);

$allowedCols = ['id','abholdatum','abfahrtszeit','fahrtpreis'];
$sortCol = in_array($_GET['sort'] ?? 'abholdatum', $allowedCols)
    ? ($_GET['sort'] ?? 'abholdatum') : 'abholdatum';
$sortDir = (($_GET['direction'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';

$sql = "SELECT f.id, f.abholdatum, f.abfahrtszeit, f.kunde_id,
               f.ort_start_id, f.ort_ziel_id,
               e1.wert AS start, e2.wert AS ziel,
               k.kundentyp, k.firmenname, k.vorname, k.nachname,
               n.name AS fahrer, f.personenanzahl, f.fahrtpreis, f.rechnungsnummer
        FROM fahrten f
        LEFT JOIN einstellungen e1 ON f.ort_start_id = e1.id
        LEFT JOIN einstellungen e2 ON f.ort_ziel_id  = e2.id
        LEFT JOIN nutzer n      ON f.fahrer_id     = n.id
        LEFT JOIN kunden k      ON f.kunde_id      = k.id
        WHERE f.deleted_at IS NULL
        ORDER BY $sortCol $sortDir";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$fahrten = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// HTML AUSGABE
// ============================================================
?>
<div class="container mt-4">
    <h1 class="mb-4">Alle Fahrten</h1>
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label">Sortieren nach</label>
            <select name="sort" class="form-select" onchange="this.form.submit()">
                <option value="abholdatum" <?= $sortCol==='abholdatum'?'selected':'' ?>>Datum</option>
                <option value="abfahrtszeit" <?= $sortCol==='abfahrtszeit'?'selected':'' ?>>Zeit</option>
                <option value="fahrtpreis" <?= $sortCol==='fahrtpreis'?'selected':'' ?>>Preis</option>
                <option value="id" <?= $sortCol==='id'?'selected':'' ?>>ID</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Richtung</label>
            <select name="direction" class="form-select" onchange="this.form.submit()">
                <option value="asc" <?= $sortDir==='ASC'?'selected':'' ?>>Aufsteigend</option>
                <option value="desc" <?= $sortDir==='DESC'?'selected':'' ?>>Absteigend</option>
            </select>
        </div>
    </form>
    <?php if (empty($fahrten)): ?>
        <div class="alert alert-info">Keine Fahrten gefunden.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Datum</th>
                        <th>Zeit</th>
                        <th>Kunde</th>
                        <th>Adresse</th>
                        <th>Start</th>
                        <th>Ziel</th>
                        <th>Fahrer</th>
                        <th class="text-center">Personen</th>
                        <th class="text-end">Preis</th>
                        <th>Rechnung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fahrten as $f): ?>
                        <?php
                        // Kundenname
                        if ($f['kundentyp']==='firma' && $f['firmenname']) {
                            $kundeName = $f['firmenname'];
                        } else {
                            $kundeName = trim($f['vorname'].' '.$f['nachname']);
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($f['id'],ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= formatDatum($f['abholdatum']) ?></td>
                            <td><?= formatZeit($f['abfahrtszeit']) ?></td>
                            <td><?= htmlspecialchars($kundeName?:'-',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= getKundenadresse((int)$f['kunde_id'],$pdo) ?></td>
                            <td><?= formatiereOrt($f['ort_start_id'],$f['start'],$f['kunde_id'],$pdo) ?></td>
                            <td><?= formatiereOrt($f['ort_ziel_id'], $f['ziel'], $f['kunde_id'],$pdo) ?></td>
                            <td><?= htmlspecialchars($f['fahrer']?:'-',ENT_QUOTES,'UTF-8') ?></td>
                            <td class="text-center"><?= (int)$f['personenanzahl'] ?></td>
                            <td class="text-end"><?= formatGeld($f['fahrtpreis']) ?></td>
                            <td><?= htmlspecialchars($f['rechnungsnummer']?:'-',ENT_QUOTES,'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="text-end text-muted mt-3">
        Abfrage: <?= number_format((microtime(true)-$startTime)*1000,2) ?> ms
    </div>
</div>
<?php require_once __DIR__ . '/../assets/footer.php'; ?>
