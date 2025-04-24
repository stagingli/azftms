<?php
/**
 * fahrten_papierkorb.php
 * Zeigt gelöschte Fahrten an und ermöglicht die Wiederherstellung
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/helpers.php';

// Header einbinden
require_once __DIR__ . '/../assets/header.php';

// Gelöschte Fahrten laden
$sql = "SELECT
            f.id,
            f.abholdatum,
            f.abfahrtszeit,
            f.fahrer_id,
            f.deleted_at,
            f.kunde_id,
            k.vorname,
            k.nachname,
            k.firmenname,
            e1.wert AS ort_start,
            e2.wert AS ort_ziel,
            u.name AS fahrer
        FROM fahrten f
        LEFT JOIN kunden k ON f.kunde_id = k.id
        LEFT JOIN einstellungen e1 ON f.ort_start_id = e1.id
        LEFT JOIN einstellungen e2 ON f.ort_ziel_id = e2.id
        LEFT JOIN nutzer u ON f.fahrer_id = u.id
        WHERE f.deleted_at IS NOT NULL
        ORDER BY f.deleted_at DESC";

try {
    $stmt = $pdo->query($sql);
    $geloeschte_fahrten = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $geloeschte_fahrten = [];
    error_log("Datenbankfehler beim Laden gelöschter Fahrten: " . $e->getMessage());
}

// Formatierungsfunktionen
function formatDatum($datum) {
    if (empty($datum)) return '';
    
    try {
        $dateTime = new DateTime($datum);
        return $dateTime->format('d.m.Y');
    } catch (Exception $e) {
        return $datum;
    }
}

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

function formatDateTime($dateTime) {
    if (empty($dateTime)) return '';
    
    try {
        $dt = new DateTime($dateTime);
        return $dt->format('d.m.Y H:i') . ' Uhr';
    } catch (Exception $e) {
        return $dateTime;
    }
}

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
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><i class="fas fa-trash me-2"></i> Papierkorb</h1>
        <div>
            <a href="fahrten_liste.php" class="btn btn-primary me-2">
                <i class="fas fa-arrow-left me-1"></i> Zurück zur Fahrtenliste
            </a>
            <?php if (!empty($geloeschte_fahrten)): ?>
            <a href="fahrt_loeschen.php?id=<?= $geloeschte_fahrten[0]['id'] ?>&action=leeren" class="btn btn-danger">
                <i class="fas fa-trash-alt me-1"></i> Papierkorb leeren
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert <?= strpos($_SESSION['msg'], '✅') === 0 ? 'alert-success' : (strpos($_SESSION['msg'], 'ℹ️') === 0 ? 'alert-info' : 'alert-danger') ?> alert-dismissible fade show">
            <?= $_SESSION['msg'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
        </div>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>
    
    <?php if (empty($geloeschte_fahrten)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> Der Papierkorb ist leer.
        </div>
    <?php else: ?>
        <!-- Desktop-Ansicht (wird auf kleinen Bildschirmen ausgeblendet) -->
        <div class="d-none d-md-block">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                    <tr class="bg-dark text-white">
                        <th>ID</th>
                        <th>Gelöscht am</th>
                        <th>Abholdatum</th>
                        <th>Abfahrtszeit</th>
                        <th>Kunde</th>
                        <th>Strecke</th>
                        <th>Fahrer</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($geloeschte_fahrten as $fahrt): ?>
                        <tr>
                            <td><?= htmlspecialchars($fahrt['id']) ?></td>
                            <td><?= formatDateTime($fahrt['deleted_at']) ?></td>
                            <td><?= formatDatum($fahrt['abholdatum']) ?></td>
                            <td><?= formatZeit($fahrt['abfahrtszeit']) ?></td>
                            <td>
                                <?php
                                if (!empty($fahrt['firmenname'])) {
                                    echo htmlspecialchars($fahrt['firmenname']);
                                } else {
                                    $kunde = trim(($fahrt['vorname'] ?? '') . ' ' . ($fahrt['nachname'] ?? ''));
                                    echo $kunde ? htmlspecialchars($kunde) : '<em>Ohne Kunde</em>';
                                }
                                ?>
                            </td>
                            <td>
                            <?= formatiereOrt($fahrt['ort_start_id'] ?? null, $fahrt['ort_start'] ?? '', $fahrt['kunde_id'] ?? null, $pdo) ?> → 
                            <?= formatiereOrt($fahrt['ort_ziel_id'] ?? null, $fahrt['ort_ziel'] ?? '', $fahrt['kunde_id'] ?? null, $pdo) ?>
                            </td>
                            <td><?= !empty($fahrt['fahrer']) ? htmlspecialchars($fahrt['fahrer']) : '<em>Nicht zugewiesen</em>' ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="fahrt_loeschen.php?id=<?= htmlspecialchars($fahrt['id']) ?>&action=wiederherstellen" class="btn btn-sm btn-success">
                                        <i class="fas fa-trash-restore"></i> Wiederherstellen
                                    </a>
                                    <a href="fahrt_loeschen.php?id=<?= htmlspecialchars($fahrt['id']) ?>&action=endgueltig" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Endgültig löschen
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Mobile-Ansicht (wird nur auf kleinen Bildschirmen angezeigt) -->
        <div class="d-md-none">
            <?php foreach ($geloeschte_fahrten as $fahrt): 
                if (!empty($fahrt['firmenname'])) {
                    $kunde = htmlspecialchars($fahrt['firmenname']);
                } else {
                    $kunde = trim(($fahrt['vorname'] ?? '') . ' ' . ($fahrt['nachname'] ?? ''));
                    $kunde = $kunde ? htmlspecialchars($kunde) : '<em>Ohne Kunde</em>';
                }
                $fahrt_id = htmlspecialchars($fahrt['id']);
            ?>
                <div class="card mb-2">
                    <!-- Zusammengeklappte Kartenansicht (immer sichtbar) -->
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center" 
                         data-bs-toggle="collapse" data-bs-target="#fahrt-<?= $fahrt_id ?>" 
                         aria-expanded="false" aria-controls="fahrt-<?= $fahrt_id ?>" style="cursor: pointer;">
                        <div>
                            <strong><?= formatDatum($fahrt['abholdatum']) ?></strong>, 
                            <?= formatZeit($fahrt['abfahrtszeit']) ?>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-danger me-2" title="Im Papierkorb seit <?= formatDateTime($fahrt['deleted_at']) ?>">
                                <i class="fas fa-trash"></i>
                            </span>
                            <span class="me-2">#<?= $fahrt_id ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    
                    <!-- Kurze Zusammenfassung (immer sichtbar) -->
                    <div class="card-body py-2 px-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= $kunde ?></strong><br>
                                <small>
                                <?= formatiereOrt($fahrt['ort_start_id'] ?? null, $fahrt['ort_start'] ?? '', $fahrt['kunde_id'] ?? null, $pdo) ?> → 
                                <?= formatiereOrt($fahrt['ort_ziel_id'] ?? null, $fahrt['ort_ziel'] ?? '', $fahrt['kunde_id'] ?? null, $pdo) ?>
                                </small>
                            </div>
                            <div>
                                <small class="text-muted">Gelöscht am <?= formatDateTime($fahrt['deleted_at']) ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ausgeklappte Details (standardmäßig versteckt) -->
                    <div id="fahrt-<?= $fahrt_id ?>" class="collapse">
                        <div class="card-body p-3">
                            <!-- Aktionen -->
                            <div class="mb-3 d-flex justify-content-between">
                                <a href="fahrt_loeschen.php?id=<?= $fahrt_id ?>&action=wiederherstellen" class="btn btn-success">
                                    <i class="fas fa-trash-restore me-1"></i> Wiederherstellen
                                </a>
                                <a href="fahrt_loeschen.php?id=<?= $fahrt_id ?>&action=endgueltig" class="btn btn-danger">
                                    <i class="fas fa-trash me-1"></i> Endgültig löschen
                                </a>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">Fahrt-ID:</div>
                                <div class="col-7"><?= $fahrt_id ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">Gelöscht am:</div>
                                <div class="col-7"><?= formatDateTime($fahrt['deleted_at']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">Datum:</div>
                                <div class="col-7"><?= formatDatum($fahrt['abholdatum']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">Zeit:</div>
                                <div class="col-7"><?= formatZeit($fahrt['abfahrtszeit']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">Kunde:</div>
                                <div class="col-7"><?= $kunde ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">Start:</div>
                                <div class="col-7"><?= formatiereOrt($fahrt['ort_start_id'], $fahrt['ort_start'] ?? '', $fahrt['kunde_id'], $pdo) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">Ziel:</div>
                                <div class="col-7"><?= formatiereOrt($fahrt['ort_ziel_id'], $fahrt['ort_ziel'] ?? '', $fahrt['kunde_id'], $pdo) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">Fahrer:</div>
                                <div class="col-7"><?= !empty($fahrt['fahrer']) ? htmlspecialchars($fahrt['fahrer']) : '<em>Nicht zugewiesen</em>' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript für Card-Toggle -->
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
require_once __DIR__ . '/../assets/footer.php';
?>