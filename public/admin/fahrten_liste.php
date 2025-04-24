<?php
/**
 * fahrten_liste.php
 * Zeigt alle Fahrten mit Filterfunktionen in einer einfachen Tabelle an.
 */

// ============================================================
// TEIL 1: EINBINDUNGEN UND INITIALISIERUNG
// ============================================================
// Keine Ausgabe (z. B. Leerzeilen) vor dem öffnenden <?php!

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/helpers.php';

// Header einbinden (z. B. HTML-Kopf, Navigation etc.)
require_once __DIR__ . '/../assets/header.php';

// DEBUG_MODE für Entwicklung
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', 1);
}

// Zeitzone setzen
date_default_timezone_set('Europe/Berlin');
// ============================================================
// TEIL 2: HILFSFUNKTIONEN
// ============================================================

/**
 * MySQL-Zeitzone setzen
 */
function setMySQLTimezone(PDO $pdo) {
    try {
        $pdo->query("SET time_zone = 'Europe/Berlin'");
        if (DEBUG_MODE) {
            error_log("MySQL-Zeitzone gesetzt");
        }
    } catch (Exception $e) {
        error_log("Fehler beim Setzen der MySQL-Zeitzone: " . $e->getMessage());
    }
}

/**
 * Lädt alle Fahrer (Nutzer mit Rolle = 'fahrer')
 */
/**
 * Lädt alle Fahrer (Nutzer mit Rolle = 'fahrer')
 */
function getFahrer(PDO $pdo): array {
    try {
        // Der ursprüngliche Code sucht nach Nutzern mit rolle='fahrer', aber die Rollenzuweisung 
        // erfolgt in der Tabelle nutzer_rolle, nicht direkt in der Tabelle nutzer
        $stmt = $pdo->prepare("
            SELECT n.id, n.name 
            FROM nutzer n 
            JOIN nutzer_rolle nr ON n.id = nr.nutzer_id 
            JOIN rollen r ON nr.rolle_id = r.id 
            WHERE r.name = 'fahrer' 
            ORDER BY n.name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Laden der Fahrer: " . $e->getMessage());
        return [];
    }
}

/**
 * Lädt alle Kunden
 */
function getKunden(PDO $pdo): array {
    try {
        return $pdo->query("SELECT id, vorname, nachname FROM kunden ORDER BY nachname, vorname")
                   ->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Laden der Kunden: " . $e->getMessage());
        return [];
    }
}

/**
 * Formatiert ein Datum ins deutsche Format
 */
function formatDatum($datum) {
    if (empty($datum)) return '';
    
    try {
        $dateTime = new DateTime($datum);
        return $dateTime->format('d.m.Y');
    } catch (Exception $e) {
        return $datum;
    }
}

/**
 * Formatiert eine Zeit
 */
function formatZeit($zeit) {
    if (empty($zeit)) return '';
    
    try {
        if (is_string($zeit) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $zeit)) {
            $teile = explode(':', $zeit);
            return $teile[0] . ':' . $teile[1] . ' Uhr';
        }
        
        $dateTime = new DateTime($zeit);
        return $dateTime->format('H:i') . ' Uhr';
    } catch (Exception $e) {
        return $zeit;
    }
}


/**
 * Generiert einen Sortierlink für Tabellenüberschriften
 */
function sortLink($column, $label, $currentSort, $currentDirection) {
    // Alle bestehenden Parameter erhalten
    $params = $_GET;
    
    // Sortierreihenfolge festlegen
    $newDirection = 'asc'; // Standardmäßig aufsteigend
    if ($column === $currentSort) {
        // Wenn bereits nach dieser Spalte sortiert wird, Richtung umkehren
        $newDirection = ($currentDirection === 'asc') ? 'desc' : 'asc';
    }
    
    // Parameter für Sortierung aktualisieren
    $params['sort'] = $column;
    $params['direction'] = $newDirection;
    
    // Als Query-String formatieren
    $queryString = http_build_query($params);
    
    // Sortierindikator hinzufügen
    $indicator = '';
    if ($column === $currentSort) {
        $indicator = ($currentDirection === 'asc') ? ' ↑' : ' ↓';
    }
    
    // Link mit weißer Textfarbe erstellen
    return '<a href="?' . $queryString . '" class="text-white text-decoration-none">' . $label . $indicator . '</a>';
}

/**
 * Lädt die Kundenadresse für einen bestimmten Kunden
 */
function getKundenadresse($kundeId, PDO $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                kundentyp, firmenname, firmenanschrift,
                strasse, hausnummer, plz, ort
            FROM kunden 
            WHERE id = ?
        ");
        $stmt->execute([$kundeId]);
        $kunde = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$kunde) {
            return "Kunde nicht gefunden";
        }
        
        $adresse = [];
        
        // Firmeninformationen, wenn es sich um eine Firma handelt
        if ($kunde['kundentyp'] === 'firma' && !empty($kunde['firmenname'])) {
            $adresse[] = '<strong>' . htmlspecialchars($kunde['firmenname']) . '</strong>';
            
            if (!empty($kunde['firmenanschrift'])) {
                $adresse[] = htmlspecialchars($kunde['firmenanschrift']);
                return implode('<br>', $adresse);
            }
        }
        
        // Straße und Hausnummer
        if (!empty($kunde['strasse']) || !empty($kunde['hausnummer'])) {
            $adresse[] = htmlspecialchars(
                trim($kunde['strasse'] . ' ' . $kunde['hausnummer'])
            );
        }
        
        // PLZ und Ort
        if (!empty($kunde['plz']) || !empty($kunde['ort'])) {
            $adresse[] = htmlspecialchars(
                trim($kunde['plz'] . ' ' . $kunde['ort'])
            );
        }
        
        return implode('<br>', $adresse);
    } catch (Exception $e) {
        error_log("Fehler beim Laden der Kundenadresse: " . $e->getMessage());
        return "Fehler beim Laden der Adresse";
    }
}

/**
 * Gibt den formatierten Ort aus, bei Kundenadresse entsprechend formatiert
 */
function formatiereOrt($ortId, $ortWert, $kundeId, PDO $pdo) {
    // Wenn es sich nicht um eine "Kundenadresse" oder "Firmenadresse" handelt, einfach den Wert zurückgeben
    if (strtolower($ortWert) != 'kundenadresse' && strtolower($ortWert) != 'firmenadresse') {
        return htmlspecialchars($ortWert);
    }
    
    // Bei Kundenadresse oder Firmenadresse die entsprechend formatierte Adresse zurückgeben
    if (!empty($kundeId)) {
        return getKundenadresse($kundeId, $pdo);
    }
    
    return htmlspecialchars($ortWert) . ' (Kein Kunde zugeordnet)';
}

/**
 * Prüft, ob eine Fahrt eine Rückfahrt hat
 */
function hatRueckfahrt($fahrtId, PDO $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fahrten WHERE hinfahrt_id = ? AND deleted_at IS NULL");
        $stmt->execute([$fahrtId]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Fehler beim Prüfen auf Rückfahrt: " . $e->getMessage());
        return false;
    }
}
// ============================================================
// TEIL 3: HAUPTLOGIK - FILTER UND DATEN LADEN
// ============================================================

// Zeitzone in der Datenbank setzen
setMySQLTimezone($pdo);

// Startzeit für Performance-Messung
$startTime = microtime(true);

// Standard: Filterformular ist immer geöffnet
$filterStatus = $_COOKIE['filter_status'] ?? 'open';

// Standard-Filter: Zeige heutiges Datum und alle zukünftigen Fahrten an
$heute_zukunft_standard = empty($_GET) || (count($_GET) === 1 && isset($_GET['sort']));

// Filter-Parameter aus GET
$filter = [
    'kunde_id'  => $_GET['kunde_id']  ?? null,
    'fahrer_id' => $_GET['fahrer_id'] ?? null,
    'von_datum' => $_GET['von_datum'] ?? null,
    'bis_datum' => $_GET['bis_datum'] ?? null
];

// Wenn keine Filter gesetzt sind, standardmäßig ab heute filtern (ohne Enddatum)
if ($heute_zukunft_standard) {
    $filter['von_datum'] = date('Y-m-d'); // Ab heute
    $filter['bis_datum'] = null; // Kein Ende (alle zukünftigen Fahrten)
}

// Schnellfilter: Heute, Morgen, Gestern, Diese Woche, Nächste Woche, Letzte Woche, Dieser Monat
if (isset($_GET['heute'])) {
    $filter['von_datum'] = date('Y-m-d');
    $filter['bis_datum'] = date('Y-m-d');
}
if (isset($_GET['morgen'])) {
    $filter['von_datum'] = date('Y-m-d', strtotime('+1 day'));
    $filter['bis_datum'] = date('Y-m-d', strtotime('+1 day'));
}
if (isset($_GET['gestern'])) {
    $filter['von_datum'] = date('Y-m-d', strtotime('-1 day'));
    $filter['bis_datum'] = date('Y-m-d', strtotime('-1 day'));
}
if (isset($_GET['diese_woche'])) {
    // Montag dieser Woche bis Sonntag dieser Woche
    $filter['von_datum'] = date('Y-m-d', strtotime('monday this week'));
    $filter['bis_datum'] = date('Y-m-d', strtotime('sunday this week'));
}
if (isset($_GET['naechste_woche'])) {
    // Montag nächster Woche bis Sonntag nächster Woche
    $filter['von_datum'] = date('Y-m-d', strtotime('monday next week'));
    $filter['bis_datum'] = date('Y-m-d', strtotime('sunday next week'));
}
if (isset($_GET['letzte_woche'])) {
    // Montag letzter Woche bis Sonntag letzter Woche
    $filter['von_datum'] = date('Y-m-d', strtotime('monday last week'));
    $filter['bis_datum'] = date('Y-m-d', strtotime('sunday last week'));
}
if (isset($_GET['dieser_monat'])) {
    // Erster Tag des aktuellen Monats bis letzter Tag des aktuellen Monats
    $filter['von_datum'] = date('Y-m-01');
    $filter['bis_datum'] = date('Y-m-t');
}

// Suchbegriff verarbeiten
$suchbegriff = $_GET['suchbegriff'] ?? '';

// Sortierung
// Standardsortierung bei heute+zukunft: Nach Datum aufsteigend
if ($heute_zukunft_standard) {
    $sort_column = $_GET['sort'] ?? 'abholdatum';
    $sort_direction = $_GET['direction'] ?? 'asc';
} else {
    $sort_column = $_GET['sort'] ?? 'abholdatum';
    $sort_direction = $_GET['direction'] ?? 'desc';
}

// Basis-SQL-Abfrage definieren
$sql = "SELECT
    f.id,
    f.abholdatum,
    f.abfahrtszeit,
    f.fahrer_id,
    f.ort_start_id,
    f.ort_ziel_id,
    f.kunde_id,
    f.deleted_at,
    f.hinfahrt_id,
    f.personenanzahl,
    f.fahrtpreis,
    f.rechnungsnummer AS rechnung,
    f.zahlungsmethode_id,
    f.flugnummer,
    f.fahrer_bemerkung,
    k.vorname,
    k.nachname,
    z.wert AS zahlungsmethode_name,
    e1.wert AS ort_start,
    e2.wert AS ort_ziel,
    e3.wert AS fahrzeug_info,
    u.name AS fahrer
FROM fahrten f
LEFT JOIN kunden k ON f.kunde_id = k.id
LEFT JOIN einstellungen e1 ON f.ort_start_id = e1.id
LEFT JOIN einstellungen e2 ON f.ort_ziel_id = e2.id
LEFT JOIN einstellungen e3 ON f.fahrzeug_id = e3.id
LEFT JOIN einstellungen z ON f.zahlungsmethode_id = z.id AND z.kategorie = 'zahlungsmethode'
LEFT JOIN nutzer u ON f.fahrer_id = u.id
WHERE f.deleted_at IS NULL";



$params = [];

// Filterbedingungen anhängen
if (!empty($suchbegriff)) {
    $such_param = '%' . $suchbegriff . '%';
    $sql .= " AND (
        k.vorname LIKE :suchbegriff OR
        k.nachname LIKE :suchbegriff OR
        e1.wert LIKE :suchbegriff OR
        e2.wert LIKE :suchbegriff OR
        u.name LIKE :suchbegriff
    )";
    $params[':suchbegriff'] = $such_param;
}
if (!empty($filter['von_datum'])) {
    $sql .= " AND DATE(f.abholdatum) >= :von_datum";
    $params[':von_datum'] = $filter['von_datum'];
}
if (!empty($filter['bis_datum'])) {
    $sql .= " AND DATE(f.abholdatum) <= :bis_datum";
    $params[':bis_datum'] = $filter['bis_datum'];
}
if (!empty($filter['kunde_id'])) {
    $sql .= " AND f.kunde_id = :kunde_id";
    $params[':kunde_id'] = $filter['kunde_id'];
}
if (!empty($filter['fahrer_id'])) {
    $sql .= " AND f.fahrer_id = :fahrer_id";
    $params[':fahrer_id'] = $filter['fahrer_id'];
}

// Sortierung festlegen
$allowed_columns = ['id', 'abholdatum', 'abfahrtszeit', 'nachname', 'ort_start', 'ort_ziel', 'fahrer'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'abholdatum';
}
$sort_direction = strtolower($sort_direction) === 'desc' ? 'DESC' : 'ASC';

if ($sort_column === 'nachname') {
    $sql .= " ORDER BY k.nachname $sort_direction, k.vorname $sort_direction";
} else if ($sort_column === 'ort_start') {
    $sql .= " ORDER BY e1.wert $sort_direction";
} else if ($sort_column === 'ort_ziel') {
    $sql .= " ORDER BY e2.wert $sort_direction";
} else if ($sort_column === 'fahrer') {
    $sql .= " ORDER BY u.name $sort_direction";
} else {
    $sql .= " ORDER BY f.$sort_column $sort_direction";
}

// Fahrten abrufen
try {
    if (DEBUG_MODE) {
        error_log("SQL: " . $sql);
        error_log("Parameter: " . print_r($params, true));
    }
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $fahrten = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (DEBUG_MODE) {
        error_log("Gefundene Fahrten: " . count($fahrten));
    }
} catch (Exception $e) {
    $fahrten = [];
    error_log("Datenbankfehler: " . $e->getMessage());
    echo '<div class="alert alert-danger">Fehler bei der Datenbankabfrage: ' . $e->getMessage() . '</div>';
}

// Zusätzliche Daten für Filter laden
$fahrer_liste = getFahrer($pdo);
$kunden_liste = getKunden($pdo);
?>
<!-- ============================================================ -->
<!-- TEIL 4: HTML-AUSGABE - FILTERBEREICH                         -->
<!-- ============================================================ -->

<!-- Einfache Alert-Anzeige -->
<?php if (isset($_SESSION['alert_msg'])): ?>
    <div class="alert alert-<?= $_SESSION['alert_type'] ?? 'warning' ?> alert-dismissible fade show">
        <?= htmlspecialchars($_SESSION['alert_msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
    </div>
    <?php
    unset($_SESSION['alert_msg']);
    unset($_SESSION['alert_type']);
    unset($_SESSION['alert_icon']);
    ?>
<?php endif; ?>

<div class="container mt-4">
    <h1 class="mb-4">Fahrten</h1>
    
<!-- Aktionen -->
<div class="mb-3 d-flex justify-content-between">
    <div>
        <a href="fahrt_formular.php" class="btn btn-success">Neue Fahrt anlegen</a>
    </div>
    <div>
        <a href="fahrten_papierkorb.php" class="btn btn-outline-danger">
            <i class="fas fa-trash me-1"></i> Papierkorb
        </a>
    </div>
</div>
    
    <!-- Schnellfilter -->
    <div class="mb-3">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Schnellfilter</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="?heute=1" class="btn btn-outline-primary">Heute</a>
                    <a href="?morgen=1" class="btn btn-outline-info">Morgen</a>
                    <a href="?gestern=1" class="btn btn-outline-warning">Gestern</a>
                    <a href="?diese_woche=1" class="btn btn-outline-success">Diese Woche</a>
                    <a href="?naechste_woche=1" class="btn btn-outline-secondary">Nächste Woche</a>
                    <a href="?letzte_woche=1" class="btn btn-outline-dark">Letzte Woche</a>
                    <a href="?dieser_monat=1" class="btn btn-outline-danger">Dieser Monat</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter-Formular -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center" id="filterHeader" style="cursor: pointer;">
            <h5 class="mb-0">Erweiterter Filter</h5>
            <span id="filterToggle">
                <?= $filterStatus === 'open' ? '<i class="fas fa-chevron-up"></i>' : '<i class="fas fa-chevron-down"></i>' ?>
            </span>
        </div>
        <div class="card-body collapse <?= $filterStatus === 'open' ? 'show' : '' ?>" id="filterBody">
            <form action="" method="GET" class="row g-3">
                <!-- Suchfeld -->
                <div class="col-12 mb-3">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Suche nach Kunde, Ort oder Fahrer..."
                               name="suchbegriff" value="<?= htmlspecialchars($suchbegriff) ?>">
                        <button class="btn btn-primary" type="submit">Suchen</button>
                    </div>
                </div>
                
                <!-- Filter-Zeile 1: Fahrer und Kunden -->
                <div class="col-md-4 col-6">
                    <label for="fahrer_id" class="form-label">Fahrer</label>
                    <select class="form-select" id="fahrer_id" name="fahrer_id">
                        <option value="">Alle Fahrer</option>
                        <?php foreach ($fahrer_liste as $fahrer): ?>
                            <option value="<?= htmlspecialchars($fahrer['id']) ?>" <?= ($filter['fahrer_id'] == $fahrer['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fahrer['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
           <!--     <div class="col-md-4 col-6">
                    <label for="kunde_id" class="form-label">Kunde</label>
                    <select class="form-select" id="kunde_id" name="kunde_id">
                        <option value="">Alle Kunden</option>
                        <?php foreach ($kunden_liste as $kunde): ?>
                            <option value="<?= htmlspecialchars($kunde['id']) ?>" <?= ($filter['kunde_id'] == $kunde['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kunde['nachname'] . ', ' . $kunde['vorname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div> -->
                
                <!-- Filter-Zeile 2: Datumsfilter -->
                <div class="col-md-4 col-6">
                    <label for="von_datum" class="form-label">Von Datum</label>
                    <input type="date" class="form-control" id="von_datum" name="von_datum"
                           value="<?= htmlspecialchars($filter['von_datum'] ?? '') ?>">
                </div>
                <div class="col-md-4 col-6">
                    <label for="bis_datum" class="form-label">Bis Datum</label>
                    <input type="date" class="form-control" id="bis_datum" name="bis_datum"
                           value="<?= htmlspecialchars($filter['bis_datum'] ?? '') ?>">
                </div>
                
                <!-- Filter-Buttons -->
                <div class="col-md-4 col-12 mt-3 d-flex align-items-center justify-content-end">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i> Filtern
                        </button>
                        <a href="?" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i> Zurücksetzen
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Gefundene Fahrten -->
    <p>
        <?= count($fahrten) ?> Fahrten gefunden.
        <?php if (!empty($suchbegriff)): ?>
            <span class="badge bg-info">Suchbegriff: <?= htmlspecialchars($suchbegriff) ?></span>
        <?php endif; ?>
        <?php if (!empty($filter['fahrer_id'])): ?>
            <span class="badge bg-info">Fahrer gefiltert</span>
        <?php endif; ?>
        <?php if (!empty($filter['kunde_id'])): ?>
            <span class="badge bg-info">Kunde gefiltert</span>
        <?php endif; ?>
        
        <?php if($heute_zukunft_standard): ?>
            <span class="badge bg-primary">Ab heute</span>
        <?php elseif(isset($_GET['heute'])): ?>
            <span class="badge bg-info">Nur heute</span>
        <?php elseif(isset($_GET['morgen'])): ?>
            <span class="badge bg-success">Morgen</span>
        <?php elseif(isset($_GET['gestern'])): ?>
            <span class="badge bg-warning">Gestern</span>
        <?php elseif(isset($_GET['diese_woche'])): ?>
            <span class="badge bg-success">Diese Woche</span>
        <?php elseif(isset($_GET['naechste_woche'])): ?>
            <span class="badge bg-secondary">Nächste Woche</span>
        <?php elseif(isset($_GET['letzte_woche'])): ?>
            <span class="badge bg-dark">Letzte Woche</span>
        <?php elseif(isset($_GET['dieser_monat'])): ?>
            <span class="badge bg-danger">Dieser Monat</span>
        <?php elseif (!empty($filter['von_datum']) || !empty($filter['bis_datum'])): ?>
            <span class="badge bg-info">Benutzerdefinierter Datumsfilter</span>
        <?php endif; ?>
    </p>
    <!-- ============================================================ -->
<!-- TEIL 5: HTML-AUSGABE - TABELLE UND MOBILE ANSICHT             -->
<!-- ============================================================ -->
    
<?php if (empty($fahrten)): ?>
        <div class="alert alert-info">
            Keine Fahrten gefunden.
        </div>
    <?php else: ?>
<!-- Desktop-Ansicht (wird auf kleinen Bildschirmen ausgeblendet) -->
<div class="d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
            <tr class="bg-dark text-white">
                <th>
                    <a href="?sort=id&direction=<?= ($sort_column === 'id' && $sort_direction === 'asc') ? 'desc' : 'asc' ?><?= isset($_GET['heute']) ? '&heute=1' : '' ?><?= isset($_GET['morgen']) ? '&morgen=1' : '' ?><?= isset($_GET['gestern']) ? '&gestern=1' : '' ?><?= isset($_GET['diese_woche']) ? '&diese_woche=1' : '' ?><?= isset($_GET['naechste_woche']) ? '&naechste_woche=1' : '' ?><?= isset($_GET['letzte_woche']) ? '&letzte_woche=1' : '' ?><?= isset($_GET['dieser_monat']) ? '&dieser_monat=1' : '' ?><?= isset($_GET['kunde_id']) ? '&kunde_id=' . htmlspecialchars($_GET['kunde_id']) : '' ?><?= isset($_GET['fahrer_id']) ? '&fahrer_id=' . htmlspecialchars($_GET['fahrer_id']) : '' ?><?= isset($_GET['von_datum']) ? '&von_datum=' . htmlspecialchars($_GET['von_datum']) : '' ?><?= isset($_GET['bis_datum']) ? '&bis_datum=' . htmlspecialchars($_GET['bis_datum']) : '' ?><?= isset($_GET['suchbegriff']) ? '&suchbegriff=' . htmlspecialchars($_GET['suchbegriff']) : '' ?>" class="text-white text-decoration-none">
                    ID <?= ($sort_column === 'id') ? ($sort_direction === 'asc' ? '↑' : '↓') : '' ?>
                    </a>
                </th>
                <th>
                    <a href="?sort=abholdatum&direction=<?= ($sort_column === 'abholdatum' && strtolower($sort_direction) === 'asc') ? 'desc' : 'asc' ?><?= isset($_GET['heute']) ? '&heute=1' : '' ?><?= isset($_GET['morgen']) ? '&morgen=1' : '' ?><?= isset($_GET['gestern']) ? '&gestern=1' : '' ?><?= isset($_GET['diese_woche']) ? '&diese_woche=1' : '' ?><?= isset($_GET['naechste_woche']) ? '&naechste_woche=1' : '' ?><?= isset($_GET['letzte_woche']) ? '&letzte_woche=1' : '' ?><?= isset($_GET['dieser_monat']) ? '&dieser_monat=1' : '' ?><?= isset($_GET['kunde_id']) ? '&kunde_id=' . htmlspecialchars($_GET['kunde_id']) : '' ?><?= isset($_GET['fahrer_id']) ? '&fahrer_id=' . htmlspecialchars($_GET['fahrer_id']) : '' ?><?= isset($_GET['von_datum']) ? '&von_datum=' . htmlspecialchars($_GET['von_datum']) : '' ?><?= isset($_GET['bis_datum']) ? '&bis_datum=' . htmlspecialchars($_GET['bis_datum']) : '' ?><?= isset($_GET['suchbegriff']) ? '&suchbegriff=' . htmlspecialchars($_GET['suchbegriff']) : '' ?>" class="text-white text-decoration-none">
                    Abholdatum <?= ($sort_column === 'abholdatum') ? (strtolower($sort_direction) === 'asc' ? '↑' : '↓') : '' ?>
                    </a>
                </th>
                <th>
                    <a href="?sort=abfahrtszeit&direction=<?= ($sort_column === 'abfahrtszeit' && strtolower($sort_direction) === 'asc') ? 'desc' : 'asc' ?><?= isset($_GET['heute']) ? '&heute=1' : '' ?><?= isset($_GET['morgen']) ? '&morgen=1' : '' ?><?= isset($_GET['gestern']) ? '&gestern=1' : '' ?><?= isset($_GET['diese_woche']) ? '&diese_woche=1' : '' ?><?= isset($_GET['naechste_woche']) ? '&naechste_woche=1' : '' ?><?= isset($_GET['letzte_woche']) ? '&letzte_woche=1' : '' ?><?= isset($_GET['dieser_monat']) ? '&dieser_monat=1' : '' ?><?= isset($_GET['kunde_id']) ? '&kunde_id=' . htmlspecialchars($_GET['kunde_id']) : '' ?><?= isset($_GET['fahrer_id']) ? '&fahrer_id=' . htmlspecialchars($_GET['fahrer_id']) : '' ?><?= isset($_GET['von_datum']) ? '&von_datum=' . htmlspecialchars($_GET['von_datum']) : '' ?><?= isset($_GET['bis_datum']) ? '&bis_datum=' . htmlspecialchars($_GET['bis_datum']) : '' ?><?= isset($_GET['suchbegriff']) ? '&suchbegriff=' . htmlspecialchars($_GET['suchbegriff']) : '' ?>" class="text-white text-decoration-none">
                    Abfahrtszeit <?= ($sort_column === 'abfahrtszeit') ? (strtolower($sort_direction) === 'asc' ? '↑' : '↓') : '' ?>
                    </a>
                </th>
                <th>
                    <a href="?sort=nachname&direction=<?= ($sort_column === 'nachname' && strtolower($sort_direction) === 'asc') ? 'desc' : 'asc' ?><?= isset($_GET['heute']) ? '&heute=1' : '' ?><?= isset($_GET['morgen']) ? '&morgen=1' : '' ?><?= isset($_GET['gestern']) ? '&gestern=1' : '' ?><?= isset($_GET['diese_woche']) ? '&diese_woche=1' : '' ?><?= isset($_GET['naechste_woche']) ? '&naechste_woche=1' : '' ?><?= isset($_GET['letzte_woche']) ? '&letzte_woche=1' : '' ?><?= isset($_GET['dieser_monat']) ? '&dieser_monat=1' : '' ?><?= isset($_GET['kunde_id']) ? '&kunde_id=' . htmlspecialchars($_GET['kunde_id']) : '' ?><?= isset($_GET['fahrer_id']) ? '&fahrer_id=' . htmlspecialchars($_GET['fahrer_id']) : '' ?><?= isset($_GET['von_datum']) ? '&von_datum=' . htmlspecialchars($_GET['von_datum']) : '' ?><?= isset($_GET['bis_datum']) ? '&bis_datum=' . htmlspecialchars($_GET['bis_datum']) : '' ?><?= isset($_GET['suchbegriff']) ? '&suchbegriff=' . htmlspecialchars($_GET['suchbegriff']) : '' ?>" class="text-white text-decoration-none">
                    Kunde <?= ($sort_column === 'nachname') ? (strtolower($sort_direction) === 'asc' ? '↑' : '↓') : '' ?>
                    </a>
                </th>
                <th>
                    <a href="?sort=ort_start&direction=<?= ($sort_column === 'ort_start' && strtolower($sort_direction) === 'asc') ? 'desc' : 'asc' ?><?= isset($_GET['heute']) ? '&heute=1' : '' ?><?= isset($_GET['morgen']) ? '&morgen=1' : '' ?><?= isset($_GET['gestern']) ? '&gestern=1' : '' ?><?= isset($_GET['diese_woche']) ? '&diese_woche=1' : '' ?><?= isset($_GET['naechste_woche']) ? '&naechste_woche=1' : '' ?><?= isset($_GET['letzte_woche']) ? '&letzte_woche=1' : '' ?><?= isset($_GET['dieser_monat']) ? '&dieser_monat=1' : '' ?><?= isset($_GET['kunde_id']) ? '&kunde_id=' . htmlspecialchars($_GET['kunde_id']) : '' ?><?= isset($_GET['fahrer_id']) ? '&fahrer_id=' . htmlspecialchars($_GET['fahrer_id']) : '' ?><?= isset($_GET['von_datum']) ? '&von_datum=' . htmlspecialchars($_GET['von_datum']) : '' ?><?= isset($_GET['bis_datum']) ? '&bis_datum=' . htmlspecialchars($_GET['bis_datum']) : '' ?><?= isset($_GET['suchbegriff']) ? '&suchbegriff=' . htmlspecialchars($_GET['suchbegriff']) : '' ?>" class="text-white text-decoration-none">
                    Start <?= ($sort_column === 'ort_start') ? (strtolower($sort_direction) === 'asc' ? '↑' : '↓') : '' ?>
                    </a>
                </th>
                <th>
                    <a href="?sort=ort_ziel&direction=<?= ($sort_column === 'ort_ziel' && strtolower($sort_direction) === 'asc') ? 'desc' : 'asc' ?><?= isset($_GET['heute']) ? '&heute=1' : '' ?><?= isset($_GET['morgen']) ? '&morgen=1' : '' ?><?= isset($_GET['gestern']) ? '&gestern=1' : '' ?><?= isset($_GET['diese_woche']) ? '&diese_woche=1' : '' ?><?= isset($_GET['naechste_woche']) ? '&naechste_woche=1' : '' ?><?= isset($_GET['letzte_woche']) ? '&letzte_woche=1' : '' ?><?= isset($_GET['dieser_monat']) ? '&dieser_monat=1' : '' ?><?= isset($_GET['kunde_id']) ? '&kunde_id=' . htmlspecialchars($_GET['kunde_id']) : '' ?><?= isset($_GET['fahrer_id']) ? '&fahrer_id=' . htmlspecialchars($_GET['fahrer_id']) : '' ?><?= isset($_GET['von_datum']) ? '&von_datum=' . htmlspecialchars($_GET['von_datum']) : '' ?><?= isset($_GET['bis_datum']) ? '&bis_datum=' . htmlspecialchars($_GET['bis_datum']) : '' ?><?= isset($_GET['suchbegriff']) ? '&suchbegriff=' . htmlspecialchars($_GET['suchbegriff']) : '' ?>" class="text-white text-decoration-none">
                    Ziel <?= ($sort_column === 'ort_ziel') ? (strtolower($sort_direction) === 'asc' ? '↑' : '↓') : '' ?>
                    </a>
                </th>
                <th>
                    <a href="?sort=fahrer&direction=<?= ($sort_column === 'fahrer' && strtolower($sort_direction) === 'asc') ? 'desc' : 'asc' ?><?= isset($_GET['heute']) ? '&heute=1' : '' ?><?= isset($_GET['morgen']) ? '&morgen=1' : '' ?><?= isset($_GET['gestern']) ? '&gestern=1' : '' ?><?= isset($_GET['diese_woche']) ? '&diese_woche=1' : '' ?><?= isset($_GET['naechste_woche']) ? '&naechste_woche=1' : '' ?><?= isset($_GET['letzte_woche']) ? '&letzte_woche=1' : '' ?><?= isset($_GET['dieser_monat']) ? '&dieser_monat=1' : '' ?><?= isset($_GET['kunde_id']) ? '&kunde_id=' . htmlspecialchars($_GET['kunde_id']) : '' ?><?= isset($_GET['fahrer_id']) ? '&fahrer_id=' . htmlspecialchars($_GET['fahrer_id']) : '' ?><?= isset($_GET['von_datum']) ? '&von_datum=' . htmlspecialchars($_GET['von_datum']) : '' ?><?= isset($_GET['bis_datum']) ? '&bis_datum=' . htmlspecialchars($_GET['bis_datum']) : '' ?><?= isset($_GET['suchbegriff']) ? '&suchbegriff=' . htmlspecialchars($_GET['suchbegriff']) : '' ?>" class="text-white text-decoration-none">
                    Fahrer <?= ($sort_column === 'fahrer') ? (strtolower($sort_direction) === 'asc' ? '↑' : '↓') : '' ?>
                    </a>
                </th>
                <th class="text-white">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($fahrten as $fahrt): 
                  $hatRueckfahrt = hatRueckfahrt($fahrt['id'], $pdo);
                  $istRueckfahrt = !empty($fahrt['hinfahrt_id']);
            ?>
                <tr>
                    <td><?= htmlspecialchars($fahrt['id']) ?></td>
                    <td><?= formatDatum($fahrt['abholdatum']) ?></td>
                    <td><?= formatZeit($fahrt['abfahrtszeit']) ?></td>
                    <td>
                        <?php
                        $kunde = trim(($fahrt['vorname'] ?? '') . ' ' . ($fahrt['nachname'] ?? ''));
                        echo $kunde ? htmlspecialchars($kunde) : '<em>Ohne Kunde</em>';
                        ?>
                    </td>
                    <td><?= formatiereOrt($fahrt['ort_start_id'], $fahrt['ort_start'] ?? '', $fahrt['kunde_id'], $pdo) ?></td>
                    <td><?= formatiereOrt($fahrt['ort_ziel_id'], $fahrt['ort_ziel'] ?? '', $fahrt['kunde_id'], $pdo) ?></td>
                    <td><?= !empty($fahrt['fahrer']) ? htmlspecialchars($fahrt['fahrer']) : '<em>Nicht zugewiesen</em>' ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="fahrt_formular.php?id=<?= htmlspecialchars($fahrt['id']) ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i>
                            </a>
                            
                            <?php if (!$hatRueckfahrt && !$istRueckfahrt): ?>
                            <!-- "Fahrt drehen" Button, nur wenn keine Rückfahrt existiert und die Fahrt selbst keine Rückfahrt ist -->
                            <a href="fahrt_formular.php?id=<?= htmlspecialchars($fahrt['id']) ?>&drehen=1" 
                               class="btn btn-sm btn-info" 
                               title="Rückfahrt erstellen">
                                <i class="fas fa-exchange-alt"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($istRueckfahrt): ?>
                            <!-- Badge wenn es eine Rückfahrt ist -->
                            <span class="btn btn-sm btn-secondary" title="Dies ist eine Rückfahrt">
                                <i class="fas fa-reply"></i>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($hatRueckfahrt): ?>
                            <!-- Badge wenn diese Fahrt eine Rückfahrt hat -->
                            <span class="btn btn-sm btn-success" title="Hat Rückfahrt">
                                <i class="fas fa-check"></i>
                            </span>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="if(confirm('Wirklich löschen?')) window.location.href='fahrt_loeschen.php?id=<?= htmlspecialchars($fahrt['id']) ?>'">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Mobile Ansicht (wird nur auf kleinen Bildschirmen angezeigt) -->
<!-- Mobile-Ansicht (wird nur auf kleinen Bildschirmen angezeigt) -->
<div class="d-md-none">
<?php foreach ($fahrten as $fahrt): 
    $kunde = trim(($fahrt['vorname'] ?? '') . ' ' . ($fahrt['nachname'] ?? ''));
    $kunde = $kunde ? htmlspecialchars($kunde) : '<em>Ohne Kunde</em>';
    $fahrt_id = htmlspecialchars($fahrt['id']);
    $hatRueckfahrt = hatRueckfahrt($fahrt['id'], $pdo);
    $istRueckfahrt = !empty($fahrt['hinfahrt_id']);
?>
    <div class="card mb-2">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center" 
             data-bs-toggle="collapse" data-bs-target="#fahrt-<?= $fahrt_id ?>" 
             aria-expanded="false" aria-controls="fahrt-<?= $fahrt_id ?>" style="cursor: pointer;">
            <div>
                <strong><?= formatDatum($fahrt['abholdatum']) ?></strong>, 
                <?= formatZeit($fahrt['abfahrtszeit']) ?>
            </div>
            <div class="d-flex align-items-center">
                <?php if ($istRueckfahrt): ?>
                <span class="badge bg-secondary me-2" title="Dies ist eine Rückfahrt">
                    <i class="fas fa-reply"></i>
                </span>
                <?php endif; ?>
                <?php if ($hatRueckfahrt): ?>
                <span class="badge bg-success me-2" title="Hat Rückfahrt">
                    <i class="fas fa-check"></i>
                </span>
                <?php endif; ?>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>

        <div class="card-body py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= $kunde ?></strong><br>
                    <small>
                        <?= formatiereOrt($fahrt['ort_start_id'], $fahrt['ort_start'] ?? '', $fahrt['kunde_id'], $pdo) ?> → 
                        <?= formatiereOrt($fahrt['ort_ziel_id'], $fahrt['ort_ziel'] ?? '', $fahrt['kunde_id'], $pdo) ?>
                    </small>
                </div>
                <div>
                    <?php if (!empty($fahrt['fahrer'])): ?>
                        <span class="badge bg-success"><?= htmlspecialchars($fahrt['fahrer']) ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Kein Fahrer</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="fahrt-<?= $fahrt_id ?>" class="collapse">
            <div class="card-body p-3">
                <div class="row mb-2"><div class="col-5 fw-bold">📅 Datum:</div><div class="col-7"><?= formatDatum($fahrt['abholdatum']) ?></div></div>
                <div class="row mb-2"><div class="col-5 fw-bold">⏰ Uhrzeit:</div><div class="col-7"><?= formatZeit($fahrt['abfahrtszeit']) ?></div></div>
                <div class="row mb-2"><div class="col-5 fw-bold">👤 Kunde:</div><div class="col-7"><?= $kunde ?></div></div>
                <div class="row mb-2"><div class="col-5 fw-bold">📍 Start:</div><div class="col-7"><?= formatiereOrt($fahrt['ort_start_id'], $fahrt['ort_start'] ?? '', $fahrt['kunde_id'], $pdo) ?></div></div>
                <div class="row mb-2"><div class="col-5 fw-bold">🏁 Ziel:</div><div class="col-7"><?= formatiereOrt($fahrt['ort_ziel_id'], $fahrt['ort_ziel'] ?? '', $fahrt['kunde_id'], $pdo) ?></div></div>
                <div class="row mb-2"><div class="col-5 fw-bold">🧍 Fahrer:</div><div class="col-7"><?= !empty($fahrt['fahrer']) ? htmlspecialchars($fahrt['fahrer']) : '<em>Nicht zugewiesen</em>' ?></div></div>
                <div class="row mb-2"><div class="col-5 fw-bold">🚐 Fahrzeug:</div><div class="col-7"><?= htmlspecialchars($fahrt['fahrzeug_info'] ?? '–') ?></div></div>
                <div class="row mb-2"><div class="col-5 fw-bold">👥 Personen:</div><div class="col-7"><?= (int)$fahrt['personenanzahl'] ?> Person(en)</div></div>
                <div class="row mb-2"><div class="col-5 fw-bold">💶 Preis:</div><div class="col-7"><strong><?= number_format((float)($fahrt['fahrtpreis'] ?? 0), 2, ',', '.') ?> €</strong></div></div>
                <?php if (!empty($fahrt['rechnung'])): ?>
                <div class="row mb-2"><div class="col-5 fw-bold">📄 Rechnung:</div><div class="col-7"><?= htmlspecialchars($fahrt['rechnung']) ?></div></div>
                <?php endif; ?>
                <?php if (!empty($fahrt['zahlungsmethode_name'])): ?>
                <div class="row mb-2"><div class="col-5 fw-bold">💳 Zahlung:</div><div class="col-7"><?= htmlspecialchars($fahrt['zahlungsmethode_name']) ?></div></div>
                <?php endif; ?>
                <?php if (!empty($fahrt['flugnummer'])): ?>
                <div class="row mb-2"><div class="col-5 fw-bold">✈️ Flugnr.:</div><div class="col-7"><?= htmlspecialchars($fahrt['flugnummer']) ?></div></div>
                <?php endif; ?>
                <?php if (!empty($fahrt['fahrer_bemerkung'])): ?>
                <div class="row mb-2"><div class="col-5 fw-bold">📝 Fahrer-Bemerkung:</div><div class="col-7"><?= nl2br(htmlspecialchars($fahrt['fahrer_bemerkung'])) ?></div></div>
                <?php endif; ?>
                <?php if ($istRueckfahrt): ?>
                <div class="row mb-2"><div class="col-5 fw-bold">Status:</div><div class="col-7"><span class="badge bg-secondary">Rückfahrt zu #<?= htmlspecialchars($fahrt['hinfahrt_id']) ?></span></div></div>
                <?php endif; ?>
                <?php if ($hatRueckfahrt): ?>
                <div class="row mb-2"><div class="col-5 fw-bold">Status:</div><div class="col-7"><span class="badge bg-success">Hat Rückfahrt</span></div></div>
                <?php endif; ?>

                <div class="mt-3 d-flex flex-column gap-2">
                    <a href="fahrt_formular.php?id=<?= $fahrt_id ?>" class="btn btn-warning w-100">
                        <i class="fas fa-edit"></i> Bearbeiten
                    </a>
                    <a href="fahrt_formular.php?kopieren=<?= $fahrt_id ?>" class="btn btn-secondary w-100">
                        <i class="fas fa-copy"></i> Duplizieren
                    </a>
                    <?php if (!$hatRueckfahrt && !$istRueckfahrt): ?>
                    <a href="fahrt_formular.php?id=<?= $fahrt_id ?>&drehen=1" class="btn btn-info w-100">
                        <i class="fas fa-exchange-alt"></i> Rückfahrt
                    </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-danger w-100" 
                            onclick="if(confirm('Wirklich löschen?')) window.location.href='fahrt_loeschen.php?id=<?= $fahrt_id ?>'">
                        <i class="fas fa-trash"></i> Löschen
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>


</div>



</div>

    <?php endif; ?>
    
    <div class="mt-3 text-end">
        <small class="text-muted">
            Abfrage: <?= number_format((microtime(true) - $startTime) * 1000, 2) ?> ms
        </small>
    </div>
</div>
<!-- JavaScript für Filter-Klappfunktion und Card-Toggle -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter-Klappfunktion
    const filterHeader = document.getElementById('filterHeader');
    const filterBody = document.getElementById('filterBody');
    const filterToggle = document.getElementById('filterToggle');
    
    filterHeader.addEventListener('click', function() {
        // Bootstrap 5 collapse
        const bsCollapse = new bootstrap.Collapse(filterBody, {
            toggle: true
        });
        
        // Icon und Cookie entsprechend umschalten
        setTimeout(function() {
            const isOpen = filterBody.classList.contains('show');
            filterToggle.innerHTML = isOpen ? 
                '<i class="fas fa-chevron-up"></i>' : 
                '<i class="fas fa-chevron-down"></i>';
            
            // Status in Cookie speichern
            document.cookie = `filter_status=${isOpen ? 'open' : 'closed'}; path=/; max-age=2592000`;
        }, 350);
    });
    
    // Card-Toggle für mobile Ansicht
    const cardHeaders = document.querySelectorAll('[data-bs-toggle="collapse"]');
    cardHeaders.forEach(header => {
        header.addEventListener('click', function() {
            // Icon umschalten beim Auf-/Zuklappen
            setTimeout(() => {
                const target = this.getAttribute('data-bs-target');
                const isOpen = document.querySelector(target).classList.contains('show');
                const icon = this.querySelector('i.fas');
                if (icon) {
                    if (isOpen) {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    } else {
                        icon.classList.remove('fa-chevron-up');
                        icon.classList.add('fa-chevron-down');
                    }
                }
            }, 350);
        });
    });
});
</script>

<?php
// ============================================================
// TEIL 6: FOOTER EINBINDEN
// ============================================================
require_once __DIR__ . '/../assets/footer.php';
?>