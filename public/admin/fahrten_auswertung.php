<?php
/**
 * fahrten_auswertung.php
 * Detaillierte Auswertung der Fahrten mit Gruppierung nach Fahrern
 * und Berechnung von Summen für verschiedene Kennzahlen
 */

// ============================================================
// TEIL 1: EINBINDUNGEN UND INITIALISIERUNG
// ============================================================

// Fehlerberichterstattung aktivieren für Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Externe Konfiguration laden
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/helpers.php';

// Header einbinden
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
 * Lädt alle Fahrer (Nutzer mit Rolle 'fahrer')
 */
function getFahrer(PDO $pdo): array {
    try {
        $sql = "SELECT n.id, n.name, n.stundenlohn
                FROM nutzer n
                JOIN nutzer_rolle nr ON n.id = nr.nutzer_id
                JOIN rollen r ON nr.rolle_id = r.id
                WHERE r.name = 'fahrer'
                ORDER BY n.name";
                
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Laden der Fahrer: " . $e->getMessage());
        return [];
    }
}

/**
 * Kundenadresse formatieren basierend auf dem Kundentyp
 * (inkl. Fallback auf strasse/hausnummer/plz/ort, falls 'firmenanschrift' leer ist)
 */
function formatKundenadresse($kunde) {
    if (empty($kunde)) {
        return "Unbekannt";
    }

    // --------------------------
    // Firmenkunde
    // --------------------------
    if (isset($kunde['kundentyp']) && $kunde['kundentyp'] === 'firma') {
        $adresse = [];
        
        // Firmenname immer zuerst
        if (!empty($kunde['firmenname'])) {
            $adresse[] = '<strong>' . htmlspecialchars($kunde['firmenname']) . '</strong>';
        }
        
        // Firmenanschrift vorhanden?
        if (!empty($kunde['firmenanschrift'])) {
            // Zeilenumbrüche in <br> umwandeln
            $anschrift = nl2br(htmlspecialchars($kunde['firmenanschrift']));
            $adresse[] = $anschrift;
        } else {
            // Fallback: Straßen-/PLZ-Logik wie bei Privat
            $strasse = [];
            if (!empty($kunde['strasse'])) {
                $strasse[] = htmlspecialchars($kunde['strasse']);
            }
            if (!empty($kunde['hausnummer'])) {
                $strasse[] = htmlspecialchars($kunde['hausnummer']);
            }
            if (!empty($strasse)) {
                $adresse[] = implode(' ', $strasse);
            }

            $ort = [];
            if (!empty($kunde['plz'])) {
                $ort[] = htmlspecialchars($kunde['plz']);
            }
            if (!empty($kunde['ort'])) {
                $ort[] = htmlspecialchars($kunde['ort']);
            }
            if (!empty($ort)) {
                $adresse[] = implode(' ', $ort);
            }
        }
        
        // Falls wirklich gar nichts befüllt ist
        if (empty($adresse)) {
            return "Unbekannt";
        }
        
        return implode('<br>', $adresse);
    }

    // --------------------------
    // Privatkunde
    // --------------------------
    $adresse = [];
    
    // Straße und Hausnummer
    $strasse = [];
    if (!empty($kunde['strasse'])) {
        $strasse[] = htmlspecialchars($kunde['strasse']);
    }
    if (!empty($kunde['hausnummer'])) {
        $strasse[] = htmlspecialchars($kunde['hausnummer']);
    }
    
    if (!empty($strasse)) {
        $adresse[] = implode(' ', $strasse);
    }
    
    // PLZ und Ort
    $ort = [];
    if (!empty($kunde['plz'])) {
        $ort[] = htmlspecialchars($kunde['plz']);
    }
    if (!empty($kunde['ort'])) {
        $ort[] = htmlspecialchars($kunde['ort']);
    }
    
    if (!empty($ort)) {
        $adresse[] = implode(' ', $ort);
    }
    
    return !empty($adresse) ? implode('<br>', $adresse) : 'Unbekannt';
}

/**
 * Formatiert den Ort für Start- und Zielorte, mit spezieller Behandlung für "Kundenadresse" und "Firmenanschrift"
 */
function formatiereOrt($ortWert, $kunde) {
    if (empty($ortWert)) {
        return "Unbekannt";
    }
    
    // Wenn es sich um "Kundenadresse" oder "Firmenanschrift"/"Firmenadresse" handelt
    $lc = strtolower($ortWert);
    if ($lc === 'kundenadresse') {
        if (empty($kunde)) {
            return "Kundenadresse (Kunde nicht gefunden)";
        }
        return formatKundenadresse($kunde);
    } 
    else if ($lc === 'firmenanschrift' || $lc === 'firmenadresse') {
        if (empty($kunde) || $kunde['kundentyp'] !== 'firma') {
            return "Firmenadresse (ungültig für diesen Kunden)";
        }
        return formatKundenadresse($kunde);
    }
    
    // Für alle anderen Orte einfach den Wert zurückgeben
    return htmlspecialchars($ortWert);
}

/**
 * Formatiert ein Datum ins deutsche Format
 */
function formatDatum($datum) {
    if (empty($datum)) {
        return '';
    }
    
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
    if (empty($zeit)) {
        return '';
    }
    
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
 * Berechnet die Fahrzeit in Minuten aus den Start- und Endzeiten
 */
function berechneFahrzeit($von, $bis) {
    if (empty($von) || empty($bis)) {
        return 0;
    }
    
    try {
        $start = new DateTime("1970-01-01 {$von}");
        $ende = new DateTime("1970-01-01 {$bis}");
        
        // Falls die Endzeit vor der Startzeit liegt (Übergang über Mitternacht)
        if ($ende < $start) {
            $ende->modify('+1 day');
        }
        
        $diff = $start->diff($ende);
        // Umrechnung in Minuten
        return $diff->h * 60 + $diff->i + $diff->s / 60;
    } catch (Exception $e) {
        error_log("Fehler bei Fahrzeitberechnung: " . $e->getMessage());
        return 0;
    }
}

/**
 * Berechnet den Fahrerlohn basierend auf Fahrzeit und Stundenlohn
 */
function berechneFahrerlohn($fahrzeitMinuten, $stundenlohn) {
    // Umrechnung von Minuten in Stunden
    $fahrzeitStunden = $fahrzeitMinuten / 60;
    return $fahrzeitStunden * $stundenlohn;
}

/**
 * Generiert einen Sortierlink für Tabellenüberschriften
 */
function sortLink($column, $label, $currentSort, $currentDirection) {
    $direction = ($currentSort === $column && $currentDirection === 'asc') ? 'desc' : 'asc';
    
    $params = $_GET;
    unset($params['sort'], $params['direction']);
    $params['sort'] = $column;
    $params['direction'] = $direction;
    $query = http_build_query($params);
    
    $directionIcon = '';
    if ($currentSort === $column) {
        $directionIcon = $currentDirection === 'asc' ? ' ▲' : ' ▼';
    }
    
    return '<a href="?' . $query . '" class="text-decoration-none text-dark">' . $label . $directionIcon . '</a>';
}

// ============================================================
// TEIL 3: HAUPTLOGIK - FILTER UND DATEN LADEN
// ============================================================

// Zeitzone setzen
setMySQLTimezone($pdo);

// Startzeit für Performance-Messung
$startTime = microtime(true);

// Filter-Parameter aus GET
$filter = [
    'fahrer_id' => $_GET['fahrer_id'] ?? null,
    'von_datum' => $_GET['von_datum'] ?? date('Y-m-01'), // Standardmäßig erster Tag des aktuellen Monats
    'bis_datum' => $_GET['bis_datum'] ?? date('Y-m-t'),  // Standardmäßig letzter Tag des aktuellen Monats
    'gruppierung' => $_GET['gruppierung'] ?? 'fahrer'    // Standardmäßig nach Fahrer gruppieren
];

// Schnellfilter: Heute, Diese Woche, Dieser Monat, Dieses Jahr
if (isset($_GET['heute'])) {
    $filter['von_datum'] = date('Y-m-d');
    $filter['bis_datum'] = date('Y-m-d');
} elseif (isset($_GET['diese_woche'])) {
    $filter['von_datum'] = date('Y-m-d', strtotime('monday this week'));
    $filter['bis_datum'] = date('Y-m-d', strtotime('sunday this week'));
} elseif (isset($_GET['dieser_monat'])) {
    $filter['von_datum'] = date('Y-m-01');
    $filter['bis_datum'] = date('Y-m-t');
} elseif (isset($_GET['dieses_jahr'])) {
    $filter['von_datum'] = date('Y-01-01');
    $filter['bis_datum'] = date('Y-12-31');
}

// Sortierung
$sort_column = $_GET['sort'] ?? 'abholdatum';
$sort_direction = $_GET['direction'] ?? 'desc';

// SQL-Abfrage für Fahrten mit allen relevanten Daten
$sql = "SELECT
            f.id,
            f.abholdatum,
            f.abfahrtszeit,
            f.fahrer_id,
            f.ort_start_id,
            f.ort_ziel_id,
            f.kunde_id,
            f.fahrzeug_id,
            f.fahrtpreis,
            f.fahrzeit_von,
            f.fahrzeit_bis,
            f.fahrzeit_summe,
            f.wartezeit,
            f.ausgaben,
            f.lohn_fahrt,
            k.vorname AS kunde_vorname,
            k.nachname AS kunde_nachname,
            k.kundentyp,
            k.firmenname,
            k.firmenanschrift,
            k.strasse,
            k.hausnummer,
            k.plz,
            k.ort AS kunde_ort,
            e1.wert AS ort_start,
            e2.wert AS ort_ziel,
            e3.wert AS fahrzeug_info,
            u.name AS fahrer_name,
            u.stundenlohn
        FROM fahrten f
        LEFT JOIN kunden k ON f.kunde_id = k.id
        LEFT JOIN einstellungen e1 ON f.ort_start_id = e1.id
        LEFT JOIN einstellungen e2 ON f.ort_ziel_id = e2.id
        LEFT JOIN einstellungen e3 ON f.fahrzeug_id = e3.id
        LEFT JOIN nutzer u ON f.fahrer_id = u.id
        WHERE f.deleted_at IS NULL";

$params = [];

// Datumsfilter anwenden
if (!empty($filter['von_datum'])) {
    $sql .= " AND DATE(f.abholdatum) >= :von_datum";
    $params[':von_datum'] = $filter['von_datum'];
}
if (!empty($filter['bis_datum'])) {
    $sql .= " AND DATE(f.abholdatum) <= :bis_datum";
    $params[':bis_datum'] = $filter['bis_datum'];
}

// Fahrer-Filter anwenden
if (!empty($filter['fahrer_id'])) {
    $sql .= " AND f.fahrer_id = :fahrer_id";
    $params[':fahrer_id'] = $filter['fahrer_id'];
}

// Sortierung
$allowed_columns = ['id', 'abholdatum', 'abfahrtszeit', 'kunde_nachname', 'ort_start', 'ort_ziel', 'fahrer_name', 'fahrtpreis'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'abholdatum';
}
$sort_direction = strtolower($sort_direction) === 'desc' ? 'DESC' : 'ASC';

if ($sort_column === 'kunde_nachname') {
    $sql .= " ORDER BY k.nachname $sort_direction, k.vorname $sort_direction";
} else if ($sort_column === 'ort_start') {
    $sql .= " ORDER BY e1.wert $sort_direction";
} else if ($sort_column === 'ort_ziel') {
    $sql .= " ORDER BY e2.wert $sort_direction";
} else if ($sort_column === 'fahrer_name') {
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
    // Bei Fehler leeres Array und Fehler anzeigen
    $fahrten = [];
    error_log("Datenbankfehler: " . $e->getMessage());
    echo '<div class="alert alert-danger">Fehler bei der Datenbankabfrage: ' . $e->getMessage() . '</div>';
}

// Fahrer für Filter laden
$fahrer_liste = getFahrer($pdo);

// ============================================================
// TEIL 4: DATEN-AUFBEREITUNG UND GRUPPIERUNG
// ============================================================

// Daten nach Fahrern gruppieren
$gruppierte_fahrten = [];
$gesamtsummen = [
    'fahrtpreis' => 0,
    'fahrzeit' => 0,
    'wartezeit' => 0,
    'ausgaben' => 0,
    'fahrerlohn' => 0,
    'ergebnis' => 0,
    'anzahl' => 0
];

foreach ($fahrten as $fahrt) {
    $fahrerid = $fahrt['fahrer_id'] ?? 0;
    $fahrername = !empty($fahrt['fahrer_name']) ? $fahrt['fahrer_name'] : 'Nicht zugewiesen';
    $stundenlohn = floatval($fahrt['stundenlohn'] ?? 0);
    
    // Kundeninformationen für Adressanzeige
    $kundenDaten = [
        'kundentyp' => $fahrt['kundentyp'] ?? '',
        'vorname' => $fahrt['kunde_vorname'] ?? '',
        'nachname' => $fahrt['kunde_nachname'] ?? '',
        'firmenname' => $fahrt['firmenname'] ?? '',
        'firmenanschrift' => $fahrt['firmenanschrift'] ?? '',
        'strasse' => $fahrt['strasse'] ?? '',
        'hausnummer' => $fahrt['hausnummer'] ?? '',
        'plz' => $fahrt['plz'] ?? '',
        'ort' => $fahrt['kunde_ort'] ?? ''
    ];
    
    // Fehlende Werte mit Standardwerten ersetzen
    $fahrt['fahrtpreis'] = floatval($fahrt['fahrtpreis'] ?? 0);
    
    // Fahrzeit berechnen
    $fahrt['fahrzeit_minuten'] = 0;
    if (!empty($fahrt['fahrzeit_summe'])) {
        // Wenn fahrzeit_summe vorhanden, in Minuten umrechnen
        $time_parts = explode(':', $fahrt['fahrzeit_summe']);
        if (count($time_parts) >= 2) {
            $fahrt['fahrzeit_minuten'] = intval($time_parts[0]) * 60 + intval($time_parts[1]);
            if (count($time_parts) >= 3) {
                $fahrt['fahrzeit_minuten'] += intval($time_parts[2]) / 60;
            }
        }
    } else if (!empty($fahrt['fahrzeit_von']) && !empty($fahrt['fahrzeit_bis'])) {
        // Alternativ aus von/bis berechnen
        $fahrt['fahrzeit_minuten'] = berechneFahrzeit($fahrt['fahrzeit_von'], $fahrt['fahrzeit_bis']);
    }
    
    $fahrt['wartezeit'] = intval($fahrt['wartezeit'] ?? 0);
    $fahrt['ausgaben'] = floatval($fahrt['ausgaben'] ?? 0);
    
    // Fahrerlohn bestimmen
    if (!empty($fahrt['lohn_fahrt'])) {
        $fahrt['fahrerlohn'] = floatval($fahrt['lohn_fahrt']);
    } else if ($stundenlohn > 0 && $fahrt['fahrzeit_minuten'] > 0) {
        $fahrt['fahrerlohn'] = berechneFahrerlohn($fahrt['fahrzeit_minuten'], $stundenlohn);
    } else {
        $fahrt['fahrerlohn'] = 0;
    }
    
    // Ergebnis berechnen (Fahrtpreis - Fahrerlohn)
    $fahrt['ergebnis'] = $fahrt['fahrtpreis'] - $fahrt['fahrerlohn'];
    
    // Adressen für Anzeige formatieren
    $fahrt['formatted_start'] = formatiereOrt($fahrt['ort_start'] ?? 'Unbekannt', $kundenDaten);
    $fahrt['formatted_ziel'] = formatiereOrt($fahrt['ort_ziel'] ?? 'Unbekannt', $kundenDaten);
    
    // Gruppierung initialisieren, falls noch nicht vorhanden
    if (!isset($gruppierte_fahrten[$fahrerid])) {
        $gruppierte_fahrten[$fahrerid] = [
            'name' => $fahrername,
            'stundenlohn' => $stundenlohn,
            'fahrten' => [],
            'summen' => [
                'fahrtpreis' => 0,
                'fahrzeit' => 0,
                'wartezeit' => 0,
                'ausgaben' => 0,
                'fahrerlohn' => 0,
                'ergebnis' => 0,
                'anzahl' => 0
            ]
        ];
    }
    
    // Fahrt zur Gruppe hinzufügen
    $gruppierte_fahrten[$fahrerid]['fahrten'][] = $fahrt;
    
    // Summen aktualisieren
    $gruppierte_fahrten[$fahrerid]['summen']['fahrtpreis'] += $fahrt['fahrtpreis'];
    $gruppierte_fahrten[$fahrerid]['summen']['fahrzeit'] += $fahrt['fahrzeit_minuten'];
    $gruppierte_fahrten[$fahrerid]['summen']['wartezeit'] += $fahrt['wartezeit'];
    $gruppierte_fahrten[$fahrerid]['summen']['ausgaben'] += $fahrt['ausgaben'];
    $gruppierte_fahrten[$fahrerid]['summen']['fahrerlohn'] += $fahrt['fahrerlohn'];
    $gruppierte_fahrten[$fahrerid]['summen']['ergebnis'] += $fahrt['ergebnis'];
    $gruppierte_fahrten[$fahrerid]['summen']['anzahl']++;
    
    // Gesamtsummen aktualisieren
    $gesamtsummen['fahrtpreis'] += $fahrt['fahrtpreis'];
    $gesamtsummen['fahrzeit'] += $fahrt['fahrzeit_minuten'];
    $gesamtsummen['wartezeit'] += $fahrt['wartezeit'];
    $gesamtsummen['ausgaben'] += $fahrt['ausgaben'];
    $gesamtsummen['fahrerlohn'] += $fahrt['fahrerlohn'];
    $gesamtsummen['ergebnis'] += $fahrt['ergebnis'];
    $gesamtsummen['anzahl']++;
}
?>

<!-- ============================================================ -->
<!-- TEIL 5: HTML-AUSGABE - FILTERBEREICH                         -->
<!-- ============================================================ -->

<div class="container-fluid mt-4">
    <h1 class="mb-4">Auswertung Fahrten</h1>
    
    <!-- Filter-Formular -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Zeitraum und Filter</h5>
        </div>
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <!-- Fahrer-Filter -->
                <div class="col-md-3">
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
                
                <!-- Datumsfilter -->
                <div class="col-md-3">
                    <label for="von_datum" class="form-label">Von Datum</label>
                    <input type="date" class="form-control" id="von_datum" name="von_datum"
                           value="<?= htmlspecialchars($filter['von_datum'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="bis_datum" class="form-label">Bis Datum</label>
                    <input type="date" class="form-control" id="bis_datum" name="bis_datum"
                           value="<?= htmlspecialchars($filter['bis_datum'] ?? '') ?>">
                </div>
                
                <!-- Filter-Buttons -->
                <div class="col-md-3 d-flex align-items-end">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filtern</button>
                        <a href="?" class="btn btn-outline-light">Zurücksetzen</a>
                    </div>
                </div>
                
                <!-- Schnellfilter -->
                <div class="col-12 mt-3">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?heute=1" class="btn btn-outline-primary">Heute</a>
                        <a href="?diese_woche=1" class="btn btn-outline-info">Diese Woche</a>
                        <a href="?dieser_monat=1" class="btn btn-outline-success">Dieser Monat</a>
                        <a href="?dieses_jahr=1" class="btn btn-outline-warning">Dieses Jahr</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Gefundene Fahrten -->
    <p>
        <?= number_format($gesamtsummen['anzahl'], 0, ',', '.') ?> Fahrten gefunden im Zeitraum 
        <?= formatDatum($filter['von_datum']) ?> bis <?= formatDatum($filter['bis_datum']) ?>.
        
        <?php if (!empty($filter['fahrer_id'])): ?>
            <span class="badge bg-info">Fahrer gefiltert</span>
        <?php endif; ?>
        
        <?php if(isset($_GET['heute'])): ?>
            <span class="badge bg-primary">Heute</span>
        <?php elseif(isset($_GET['diese_woche'])): ?>
            <span class="badge bg-info">Diese Woche</span>
        <?php elseif(isset($_GET['dieser_monat'])): ?>
            <span class="badge bg-success">Dieser Monat</span>
        <?php elseif(isset($_GET['dieses_jahr'])): ?>
            <span class="badge bg-warning">Dieses Jahr</span>
        <?php endif; ?>
    </p>
    <!-- ============================================================ -->
    <!-- TEIL 6: HTML-AUSGABE - GESAMTÜBERSICHT UND TABELLEN         -->
    <!-- ============================================================ -->
    
    <?php if (empty($fahrten)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> Keine Fahrten gefunden.
        </div>
    <?php else: ?>
        <!-- Gesamtübersicht -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Gesamtübersicht</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr class="bg-primary text-white">
                                <th>Auswertung/Gesamt</th>
                                <th class="text-end">Fahrten</th>
                                <th class="text-end">Fahrzeit</th>
                                <th class="text-end">Wartezeit</th>
                                <th class="text-end">Ausgaben</th>
                                <th class="text-end">Fahrerlohn</th>
                                <th class="text-end">Fahrtpreis</th>
                                <th class="text-end">Ergebnis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="fw-bold">
                                <td>Gesamt</td>
                                <td class="text-end"><?= number_format($gesamtsummen['anzahl'], 0, ',', '.') ?></td>
                                <td class="text-end"><?= number_format($gesamtsummen['fahrzeit'], 0, ',', '.') ?> Min.</td>
                                <td class="text-end"><?= number_format($gesamtsummen['wartezeit'], 0, ',', '.') ?> Min.</td>
                                <td class="text-end"><?= formatCurrency($gesamtsummen['ausgaben']) ?></td>
                                <td class="text-end"><?= formatCurrency($gesamtsummen['fahrerlohn']) ?></td>
                                <td class="text-end"><?= formatCurrency($gesamtsummen['fahrtpreis']) ?></td>
                                <td class="text-end"><?= formatCurrency($gesamtsummen['ergebnis']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Fahrer-Übersichten -->
        <?php foreach ($gruppierte_fahrten as $fahrerid => $fahrergruppe): ?>
            <div class="card mb-4">
                <div class="card-header" style="background-color: #AED581; color: #333;">
                    <h5 class="mb-0">
                        Auswertung: <?= htmlspecialchars($fahrergruppe['name']) ?> 
                        <?php if (!empty($fahrergruppe['stundenlohn'])): ?>
                            <span class="badge bg-info ms-2">Stundenlohn: <?= formatCurrency($fahrergruppe['stundenlohn']) ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr style="background-color: #AED581; color: #333;">
                                    <th>Abfahrt</th>
                                    <th>Kunde</th>
                                    <th>Start</th>
                                    <th>Ziel</th>
                                    <th>Fahrzeug</th>
                                    <th>Fahrzeit</th>
                                    <th>Wartezeit</th>
                                    <th>Ausgaben</th>
                                    <th>Lohn</th>
                                    <th>Preis</th>
                                    <th>Ergebnis</th>
                                    <!-- Neue Spalte Aktionen -->
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fahrergruppe['fahrten'] as $fahrt): ?>
                                    <tr>
                                        <td><?= formatZeit($fahrt['abfahrtszeit']) ?></td>
                                        <td>
                                            <?php
                                            $kunde = '';
                                            if (!empty($fahrt['kunde_nachname'])) {
                                                $kunde = htmlspecialchars($fahrt['kunde_nachname']);
                                                if (!empty($fahrt['kunde_vorname'])) {
                                                    $kunde .= ', ' . htmlspecialchars($fahrt['kunde_vorname']);
                                                }
                                            } else {
                                                $kunde = '<em>Ohne Kunde</em>';
                                            }
                                            echo $kunde;
                                            ?>
                                        </td>
                                        <td><?= $fahrt['formatted_start'] ?></td>
                                        <td><?= $fahrt['formatted_ziel'] ?></td>
                                        <td><?= htmlspecialchars($fahrt['fahrzeug_info'] ?? 'Unbekannt') ?></td>
                                        <td>
                                            <?php if (!empty($fahrt['fahrzeit_minuten'])): ?>
                                                <?= number_format($fahrt['fahrzeit_minuten'], 0, ',', '.') ?> Min.
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($fahrt['wartezeit'])): ?>
                                                <?= number_format($fahrt['wartezeit'], 0, ',', '.') ?> Min.
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= formatCurrency($fahrt['ausgaben'] ?? 0) ?></td>
                                        <td><?= formatCurrency($fahrt['fahrerlohn'] ?? 0) ?></td>
                                        <td><?= formatCurrency($fahrt['fahrtpreis'] ?? 0) ?></td>
                                        <td>
                                            <span class="<?= ($fahrt['ergebnis'] < 0) ? 'text-danger' : '' ?>">
                                                <?= formatCurrency($fahrt['ergebnis'] ?? 0) ?>
                                            </span>
                                        </td>
                                        <!-- Editieren-Button -->
                                        <td>
                                        <a href="fahrt_formular.php?id=<?= htmlspecialchars($fahrt['id']) ?>&return=fahrten_auswertung.php"
   class="btn btn-sm btn-warning"
   title="Fahrt bearbeiten">
   <i class="fas fa-edit"></i>
</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <!-- Summenzeile pro Fahrer -->
                                <tr class="table-info fw-bold">
                                    <td colspan="6" class="text-end">
                                        Summe für <?= htmlspecialchars($fahrergruppe['name']) ?> (<?= $fahrergruppe['summen']['anzahl'] ?> Fahrten):
                                    </td>
                                    <td><?= number_format($fahrergruppe['summen']['wartezeit'], 0, ',', '.') ?> Min.</td>
                                    <td><?= formatCurrency($fahrergruppe['summen']['ausgaben']) ?></td>
                                    <td><?= formatCurrency($fahrergruppe['summen']['fahrerlohn']) ?></td>
                                    <td><?= formatCurrency($fahrergruppe['summen']['fahrtpreis']) ?></td>
                                    <td class="<?= ($fahrergruppe['summen']['ergebnis'] < 0) ? 'text-danger' : '' ?>">
                                        <?= formatCurrency($fahrergruppe['summen']['ergebnis']) ?>
                                    </td>
                                    <!-- Keine "Aktionen" in Summenzeile -->
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Zusammenfassung nach Fahrern -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Zusammenfassung nach Fahrern</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr class="bg-primary text-white">
                                <th>Fahrer</th>
                                <th class="text-end">Anzahl Fahrten</th>
                                <th class="text-end">Fahrzeit gesamt</th>
                                <th class="text-end">Fahrtpreis gesamt</th>
                                <th class="text-end">Fahrerlohn gesamt</th>
                                <th class="text-end">Ergebnis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gruppierte_fahrten as $fahrerid => $fahrergruppe): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($fahrergruppe['name']) ?>
                                        <?php if (!empty($fahrergruppe['stundenlohn'])): ?>
                                            <span class="badge bg-info"><?= formatCurrency($fahrergruppe['stundenlohn']) ?>/h</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= number_format($fahrergruppe['summen']['anzahl'], 0, ',', '.') ?></td>
                                    <td class="text-end"><?= number_format($fahrergruppe['summen']['fahrzeit'], 0, ',', '.') ?> Min.</td>
                                    <td class="text-end"><?= formatCurrency($fahrergruppe['summen']['fahrtpreis']) ?></td>
                                    <td class="text-end"><?= formatCurrency($fahrergruppe['summen']['fahrerlohn']) ?></td>
                                    <td class="text-end <?= ($fahrergruppe['summen']['ergebnis'] < 0) ? 'text-danger' : 'text-success' ?>">
                                        <?= formatCurrency($fahrergruppe['summen']['ergebnis']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="mt-3 text-end">
        <small class="text-muted">
            Abfrage: <?= number_format((microtime(true) - $startTime) * 1000, 2) ?> ms
        </small>
    </div>
</div>

<script>
// JavaScript zur Auswahl aller Fahrten eines Fahrers (falls nötig)
document.addEventListener('DOMContentLoaded', function() {
    // Alle Fahrer-Checkboxes finden
    const fahrerCheckboxes = document.querySelectorAll('input[id^="check-fahrer-"]');
    
    // Event-Listener für jede Fahrer-Checkbox
    fahrerCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const fahrerId = this.id.replace('check-fahrer-', '');
            const isChecked = this.checked;
            
            // Alle zugehörigen Fahrt-Checkboxen finden und aktualisieren
            const fahrtCheckboxes = document.querySelectorAll(`input[id^="check-fahrt-"]`);
            fahrtCheckboxes.forEach(fahrtCheckbox => {
                if (fahrtCheckbox.closest('table').querySelector(`input[id="check-fahrer-${fahrerId}"]`)) {
                    fahrtCheckbox.checked = isChecked;
                }
            });
        });
    });
});
</script>

<?php
// ============================================================
// TEIL 7: FOOTER EINBINDEN
// ============================================================

// Footer einbinden
require_once __DIR__ . '/../assets/footer.php';
?>
