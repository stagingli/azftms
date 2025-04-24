<?php
/**
 * fahrten_liste_fahrerabrechnung.php
 * Zeigt die monatliche Abrechnung für Fahrer an, mit Übersicht und Details.
 */

// ============================================================
// TEIL 1: EINBINDUNGEN UND INITIALISIERUNG
// ============================================================
// Keine Ausgabe (z. B. Leerzeichen) vor dem öffnenden <?php!

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
function getFahrer(PDO $pdo): array {
    try {
        $stmt = $pdo->prepare("
            SELECT n.id, n.name, n.stundenlohn 
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
 * Formatiert einen Geldbetrag im deutschen Format
 */
function formatGeld($betrag) {
    return number_format((float)$betrag, 2, ',', '.') . ' €';
}

/**
 * Formatiert eine Fahrzeit (HH:MM:SS) zu Stunden und Minuten
 */
function formatFahrzeit($fahrzeit) {
    if (empty($fahrzeit)) return '-';
    
    try {
        // Prüfen, ob das Format HH:MM:SS ist
        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $fahrzeit, $matches)) {
            $stunden = (int)$matches[1];
            $minuten = (int)$matches[2];
            
            if ($stunden > 0) {
                return $stunden . ' Std. ' . $minuten . ' Min.';
            } else {
                return $minuten . ' Min.';
            }
        }
        
        return $fahrzeit;
    } catch (Exception $e) {
        return $fahrzeit;
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
 * Generiert eine Liste von Monaten für die Monatsauswahl
 */
function getMonate($anzahlMonate = 12) {
    $monate = [];
    
    // Aktueller Monat und die vergangenen Monate
    for ($i = 0; $i < $anzahlMonate; $i++) {
        $datum = new DateTime();
        $datum->modify("-$i month");
        
        $monatJahr = $datum->format('m.Y');
        $monatName = $datum->format('F Y');
        
        // Monatsnamen in Deutsch umwandeln
        $monatsDeutsch = [
            'January' => 'Januar',
            'February' => 'Februar',
            'March' => 'März',
            'April' => 'April',
            'May' => 'Mai',
            'June' => 'Juni',
            'July' => 'Juli',
            'August' => 'August',
            'September' => 'September',
            'October' => 'Oktober',
            'November' => 'November',
            'December' => 'Dezember'
        ];
        
        foreach ($monatsDeutsch as $englisch => $deutsch) {
            $monatName = str_replace($englisch, $deutsch, $monatName);
        }
        
        $monate[$datum->format('Y-m')] = $monatName;
    }
    
    return $monate;
}

/**
 * Holt die Abrechnungsdaten für einen bestimmten Monat
 */
function getAbrechnungsdaten(PDO $pdo, $monat) {
    try {
        // Monat aufteilen in Jahr und Monat
        list($jahr, $monat) = explode('-', $monat);
        
        // Erste und letzte Sekunde des Monats berechnen
        $startDatum = "$jahr-$monat-01";
        $endDatum = date('Y-m-t 23:59:59', strtotime($startDatum));
        
        // SQL für die Fahrer-Zusammenfassung
        $sql = "
            SELECT 
                f.fahrer_id,
                n.name AS fahrer_name,
                n.stundenlohn,
                COUNT(f.id) AS anzahl_fahrten,
                SUM(f.lohn_fahrt) AS summe_lohn,
                SUM(f.ausgaben) AS summe_ausgaben,
                SUM(f.lohn_auszahlbetrag) AS summe_auszahlung,
                SEC_TO_TIME(SUM(TIME_TO_SEC(f.fahrzeit_summe))) AS gesamt_fahrzeit
            FROM 
                fahrten f
            JOIN 
                nutzer n ON f.fahrer_id = n.id
            WHERE 
                f.deleted_at IS NULL 
                AND f.abholdatum BETWEEN :start_datum AND :end_datum
                AND f.fahrer_id IS NOT NULL
            GROUP BY 
                f.fahrer_id, n.name, n.stundenlohn
            ORDER BY 
                n.name
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':start_datum', $startDatum);
        $stmt->bindValue(':end_datum', $endDatum);
        $stmt->execute();
        
        $fahrerSummen = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Berechne Gesamtsummen
        $gesamtSummen = [
            'anzahl_fahrten' => 0,
            'summe_lohn' => 0,
            'summe_ausgaben' => 0,
            'summe_auszahlung' => 0
        ];
        
        foreach ($fahrerSummen as $fahrer) {
            $gesamtSummen['anzahl_fahrten'] += $fahrer['anzahl_fahrten'];
            $gesamtSummen['summe_lohn'] += $fahrer['summe_lohn'];
            $gesamtSummen['summe_ausgaben'] += $fahrer['summe_ausgaben'];
            $gesamtSummen['summe_auszahlung'] += $fahrer['summe_auszahlung'];
        }
        
        return [
            'fahrer_summen' => $fahrerSummen,
            'gesamt_summen' => $gesamtSummen
        ];
    } catch (PDOException $e) {
        error_log("Fehler beim Laden der Abrechnungsdaten: " . $e->getMessage());
        return [
            'fahrer_summen' => [],
            'gesamt_summen' => [
                'anzahl_fahrten' => 0,
                'summe_lohn' => 0,
                'summe_ausgaben' => 0,
                'summe_auszahlung' => 0
            ]
        ];
    }
}

/**
 * Holt die Details der Fahrten eines Fahrers für einen bestimmten Monat
 */
function getFahrerFahrten(PDO $pdo, $fahrerId, $monat) {
    try {
        // Monat aufteilen in Jahr und Monat
        list($jahr, $monat) = explode('-', $monat);
        
        // Erste und letzte Sekunde des Monats berechnen
        $startDatum = "$jahr-$monat-01";
        $endDatum = date('Y-m-t 23:59:59', strtotime($startDatum));
        
        $sql = "
            SELECT
                f.id,
                f.abholdatum,
                f.abfahrtszeit,
                f.fahrzeit_von,
                f.fahrzeit_bis,
                f.fahrzeit_summe,
                f.lohn_fahrt,
                f.ausgaben,
                f.lohn_auszahlbetrag,
                f.wartezeit,
                f.personenanzahl,
                k.vorname,
                k.nachname,
                e1.wert AS ort_start,
                e2.wert AS ort_ziel,
                e3.wert AS fahrzeug_info
            FROM fahrten f
            LEFT JOIN kunden k ON f.kunde_id = k.id
            LEFT JOIN einstellungen e1 ON f.ort_start_id = e1.id
            LEFT JOIN einstellungen e2 ON f.ort_ziel_id = e2.id
            LEFT JOIN einstellungen e3 ON f.fahrzeug_id = e3.id
            WHERE 
                f.deleted_at IS NULL
                AND f.fahrer_id = :fahrer_id
                AND f.abholdatum BETWEEN :start_datum AND :end_datum
            ORDER BY 
                f.abholdatum ASC, f.abfahrtszeit ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':fahrer_id', $fahrerId);
        $stmt->bindValue(':start_datum', $startDatum);
        $stmt->bindValue(':end_datum', $endDatum);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Laden der Fahrer-Fahrten: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// TEIL 3: HAUPTLOGIK - FILTER UND DATEN LADEN
// ============================================================

// Zeitzone in der Datenbank setzen
setMySQLTimezone($pdo);

// Startzeit für Performance-Messung
$startTime = microtime(true);

// Monatsliste für Auswahl erstellen
$monatsliste = getMonate(24); // 24 Monate zurück anzeigen

// Aktiver Monat aus GET oder aktueller Monat
$aktiverMonat = $_GET['monat'] ?? date('Y-m');

// Ausgewählter Fahrer für Detailansicht
$ausgewaehlterFahrer = $_GET['fahrer_id'] ?? null;

// Sortierung für die Details
$sort_column = $_GET['sort'] ?? 'abholdatum';
$sort_direction = $_GET['direction'] ?? 'asc';

// Abrechnungsdaten laden
$abrechnungsdaten = getAbrechnungsdaten($pdo, $aktiverMonat);

// Fahrer-Detailfahrten laden, wenn ein Fahrer ausgewählt ist
$fahrerFahrten = [];
if ($ausgewaehlterFahrer) {
    $fahrerFahrten = getFahrerFahrten($pdo, $ausgewaehlterFahrer, $aktiverMonat);
}

// Fahrername für Überschrift ermitteln, wenn ein Fahrer ausgewählt ist
$fahrerName = '';
if ($ausgewaehlterFahrer) {
    foreach ($abrechnungsdaten['fahrer_summen'] as $fahrer) {
        if ($fahrer['fahrer_id'] == $ausgewaehlterFahrer) {
            $fahrerName = $fahrer['fahrer_name'];
            break;
        }
    }
}
?>
<!-- ============================================================ -->
<!-- TEIL 4: HTML-AUSGABE                                          -->
<!-- ============================================================ -->

<div class="container mt-4">
    <h1 class="mb-4">Fahrer-Abrechnung</h1>
    
    <!-- Monats-Auswahl -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Monatsauswahl</h5>
        </div>
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <?php if ($ausgewaehlterFahrer): ?>
                    <input type="hidden" name="fahrer_id" value="<?= htmlspecialchars($ausgewaehlterFahrer) ?>">
                <?php endif; ?>
                
                <div class="col-md-6">
                    <select class="form-select" name="monat" id="monat" onchange="this.form.submit()">
                        <?php foreach ($monatsliste as $monatKey => $monatName): ?>
                            <option value="<?= $monatKey ?>" <?= $monatKey === $aktiverMonat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($monatName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-alt me-1"></i> Monat anzeigen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summenübersicht pro Fahrer -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Fahrer-Übersicht: <?= htmlspecialchars($monatsliste[$aktiverMonat] ?? 'Aktueller Monat') ?></h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($abrechnungsdaten['fahrer_summen'])): ?>
                <div class="alert alert-info m-3">
                    <i class="fas fa-info-circle me-2"></i> 
                    Keine Abrechnungsdaten für den ausgewählten Monat gefunden.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr class="bg-secondary text-white">
                                <th>Fahrer</th>
                                <th class="text-center">Fahrten</th>
                                <th class="text-end">Lohn</th>
                                <th class="text-end">Ausgaben</th>
                                <th class="text-end">Auszahlbetrag</th>
                                <th class="text-center">Gesamtfahrzeit</th>
                                <th class="text-center">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($abrechnungsdaten['fahrer_summen'] as $fahrer): ?>
                                <tr <?= ($ausgewaehlterFahrer == $fahrer['fahrer_id']) ? 'class="table-primary"' : '' ?>>
                                    <td><?= htmlspecialchars($fahrer['fahrer_name']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($fahrer['anzahl_fahrten']) ?></td>
                                    <td class="text-end"><?= formatGeld($fahrer['summe_lohn']) ?></td>
                                    <td class="text-end"><?= formatGeld($fahrer['summe_ausgaben']) ?></td>
                                    <td class="text-end fw-bold"><?= formatGeld($fahrer['summe_auszahlung']) ?></td>
                                    <td class="text-center"><?= formatFahrzeit($fahrer['gesamt_fahrzeit']) ?></td>
                                    <td class="text-center">
                                        <a href="?monat=<?= $aktiverMonat ?>&fahrer_id=<?= $fahrer['fahrer_id'] ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-list"></i> Details
                                        </a>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <!-- Gesamtsummen-Zeile -->
                            <tr class="table-dark fw-bold">
                                <td>Gesamt</td>
                                <td class="text-center"><?= $abrechnungsdaten['gesamt_summen']['anzahl_fahrten'] ?></td>
                                <td class="text-end"><?= formatGeld($abrechnungsdaten['gesamt_summen']['summe_lohn']) ?></td>
                                <td class="text-end"><?= formatGeld($abrechnungsdaten['gesamt_summen']['summe_ausgaben']) ?></td>
                                <td class="text-end"><?= formatGeld($abrechnungsdaten['gesamt_summen']['summe_auszahlung']) ?></td>
                                <td class="text-center">-</td>
                                <td class="text-center"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Einzelfahrten-Details, wenn ein Fahrer ausgewählt ist -->
    <?php if ($ausgewaehlterFahrer && !empty($fahrerName)): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Fahrten-Details: <?= htmlspecialchars($fahrerName) ?> - <?= htmlspecialchars($monatsliste[$aktiverMonat] ?? 'Aktueller Monat') ?>
                </h5>
                <a href="?monat=<?= $aktiverMonat ?>" class="btn btn-sm btn-light">
                    <i class="fas fa-times"></i> Details schließen
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($fahrerFahrten)): ?>
                    <div class="alert alert-info m-3">
                        <i class="fas fa-info-circle me-2"></i> 
                        Keine Fahrten für diesen Fahrer im ausgewählten Monat gefunden.
                    </div>
                <?php else: ?>
                    <!-- Desktop-Ansicht (wird auf kleinen Bildschirmen ausgeblendet) -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr class="bg-dark text-white">
                                        <th><?= sortLink('id', 'ID', $sort_column, $sort_direction) ?></th>
                                        <th><?= sortLink('abholdatum', 'Datum', $sort_column, $sort_direction) ?></th>
                                        <th><?= sortLink('abfahrtszeit', 'Zeit', $sort_column, $sort_direction) ?></th>
                                        <th>Kunde</th>
                                        <th>Start → Ziel</th>
                                        <th>Fahrzeit</th>
                                        <th class="text-end">Lohn</th>
                                        <th class="text-end">Ausgaben</th>
                                        <th class="text-end">Auszahlung</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fahrerFahrten as $fahrt): ?>
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
                                            <td>
                                                <?= htmlspecialchars($fahrt['ort_start'] ?? '-') ?> → 
                                                <?= htmlspecialchars($fahrt['ort_ziel'] ?? '-') ?>
                                            </td>
                                            <td><?= formatFahrzeit($fahrt['fahrzeit_summe']) ?></td>
                                            <td class="text-end"><?= formatGeld($fahrt['lohn_fahrt']) ?></td>
                                            <td class="text-end"><?= formatGeld($fahrt['ausgaben']) ?></td>
                                            <td class="text-end fw-bold"><?= formatGeld($fahrt['lohn_auszahlbetrag']) ?></td>
                                            <td>
                                                <a href="fahrt_formular.php?id=<?= htmlspecialchars($fahrt['id']) ?>" 
                                                   class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile Ansicht (wird nur auf kleinen Bildschirmen angezeigt) -->
                    <div class="d-md-none">
                        <?php foreach ($fahrerFahrten as $fahrt): ?>
                            <div class="card mb-2 mx-2 mt-2">
                                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center" 
                                     data-bs-toggle="collapse" data-bs-target="#fahrt-<?= $fahrt['id'] ?>" 
                                     aria-expanded="false" aria-controls="fahrt-<?= $fahrt['id'] ?>" 
                                     style="cursor: pointer;">
                                    <div>
                                        <strong><?= formatDatum($fahrt['abholdatum']) ?></strong>,
                                        <?= formatZeit($fahrt['abfahrtszeit']) ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                </div>
                                <div class="card-body py-2 px-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <?php
                                            $kunde = trim(($fahrt['vorname'] ?? '') . ' ' . ($fahrt['nachname'] ?? ''));
                                            echo $kunde ? htmlspecialchars($kunde) : '<em>Ohne Kunde</em>';
                                            ?>
                                        </div>
                                        <div class="fw-bold">
                                            <?= formatGeld($fahrt['lohn_auszahlbetrag']) ?>
                                        </div>
                                    </div>
                                </div>
                                <div id="fahrt-<?= $fahrt['id'] ?>" class="collapse">
                                    <div class="card-body p-3">
                                        <div class="row mb-2"><div class="col-5 fw-bold">ID:</div><div class="col-7"><?= htmlspecialchars($fahrt['id']) ?></div></div>
                                        <div class="row mb-2"><div class="col-5 fw-bold">Datum:</div><div class="col-7"><?= formatDatum($fahrt['abholdatum']) ?></div></div>
                                        <div class="row mb-2"><div class="col-5 fw-bold">Uhrzeit:</div><div class="col-7"><?= formatZeit($fahrt['abfahrtszeit']) ?></div></div>
                                        <div class="row mb-2"><div class="col-5 fw-bold">Start:</div><div class="col-7"><?= htmlspecialchars($fahrt['ort_start'] ?? '-') ?></div></div>
                                        <div class="row mb-2"><div class="col-5 fw-bold">Ziel:</div><div class="col-7"><?= htmlspecialchars($fahrt['ort_ziel'] ?? '-') ?></div></div>
                                        <div class="row mb-2"><div class="col-5 fw-bold">Fahrzeit:</div><div class="col-7"><?= formatFahrzeit($fahrt['fahrzeit_summe']) ?></div></div>
                                        <div class="row mb-2"><div class="col-5 fw-bold">Lohn:</div><div class="col-7"><?= formatGeld($fahrt['lohn_fahrt']) ?></div></div>
                                        <div class="row mb-2"><div class="col-5 fw-bold">Ausgaben:</div><div class="col-7"><?= formatGeld($fahrt['ausgaben']) ?></div></div>
                                        <div class="row mb-2"><div class="col-5 fw-bold">Auszahlung:</div><div class="col-7 fw-bold"><?= formatGeld($fahrt['lohn_auszahlbetrag']) ?></div></div>

                                        <div class="mt-3">
                                            <a href="fahrt_formular.php?id=<?= htmlspecialchars($fahrt['id']) ?>" class="btn btn-warning w-100">
                                                <i class="fas fa-edit"></i> Bearbeiten
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-3 text-end">
        <small class="text-muted">
            Abfrage: <?= number_format((microtime(true) - $startTime) * 1000, 2) ?> ms
        </small>
    </div>
</div>

<!-- JavaScript für Card-Toggle in der mobilen Ansicht -->
<script>
document.addEventListener('DOMContentLoaded', function() {
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