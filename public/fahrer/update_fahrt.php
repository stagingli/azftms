<?php
/**
 * update_fahrt.php
 * API-Endpoint zum Aktualisieren von Fahrtdaten (Startzeit, Endzeit, Ausgaben, etc.)
 * Gibt JSON-Responses zurück
 */

// In Produktion besser keine sichtbaren PHP-Fehler, um das JSON nicht zu zerbrechen
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Konfiguration und Berechtigungsprüfung einbinden
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';

// JSON-Antwort als Standardformat mit UTF-8
header('Content-Type: application/json; charset=utf-8');

// Nur für eingeloggte Fahrer zugänglich
if (!isset($_SESSION['user']) || !in_array('fahrer', $_SESSION['user']['rollen'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Keine Berechtigung',
        'code' => 403
    ]);
    exit;
}

// Prüfe, ob die Fahrt-ID vorhanden ist
if (!isset($_POST['fahrt_id']) || empty($_POST['fahrt_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Fahrt-ID fehlt',
        'code' => 400
    ]);
    exit;
}

// Fahrer-ID aus der Session
$fahrer_id = $_SESSION['user']['id'];
$fahrt_id = intval($_POST['fahrt_id']);

// Prüfen, ob die Fahrt dem angemeldeten Fahrer gehört
try {
    $stmt = $pdo->prepare("
        SELECT id
        FROM fahrten
        WHERE id = :id
          AND fahrer_id = :fahrer_id
          AND deleted_at IS NULL
    ");
    $stmt->execute([
        ':id'        => $fahrt_id,
        ':fahrer_id' => $fahrer_id
    ]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Fahrt nicht gefunden oder keine Berechtigung',
            'code'    => 404
        ]);
        exit;
    }
} catch (PDOException $e) {
    // Optional ins Logfile schreiben (falls definiert)
    if (defined('LOG_DB_ERRORS') && LOG_DB_ERRORS) {
        writeLog("Fehler bei Datenbankabfrage: " . $e->getMessage(), 'ERROR', ERROR_LOG_FILE);
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Datenbankfehler',
        'code'    => 500
    ]);
    exit;
}

// Spezielle Aktionen verarbeiten
$action = $_POST['action'] ?? '';

/**
 * HILFSFUNKTION:
 * Prüft, ob das Abholdatum in der Zukunft liegt
 * (also > aktuelles Tagesdatum)
 */
function isFutureRide(PDO $pdo, int $fahrtId): bool
{
    // Aktuelles Datum
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT abholdatum
        FROM fahrten
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $fahrtId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Sicher ist sicher – wenn gar nichts gefunden, brechen wir ab
        return false;
    }

    return (strtotime($row['abholdatum']) > strtotime($today));
}

if ($action === 'start_fahrt') {
    // ZUERST prüfen: Ist die Fahrt in der Zukunft?
    if (isFutureRide($pdo, $fahrt_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Fahrt liegt in der Zukunft und kann nicht gestartet werden.'
        ]);
        exit;
    }

    // Fahrt jetzt starten
    $fahrzeit_von = $_POST['fahrzeit_von'] ?? date('H:i:s');
    try {
        $stmt = $pdo->prepare("
            UPDATE fahrten
            SET fahrzeit_von = :fahrzeit_von
            WHERE id = :id
        ");
        $stmt->execute([
            ':fahrzeit_von' => $fahrzeit_von,
            ':id'           => $fahrt_id
        ]);
        
        echo json_encode([
            'success'      => true,
            'message'      => 'Fahrt erfolgreich gestartet',
            'fahrzeit_von' => $fahrzeit_von
        ]);
        exit;
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Fehler beim Starten der Fahrt',
            'error'   => $e->getMessage()
        ]);
        exit;
    }

} elseif ($action === 'end_fahrt') {
    // ZUERST prüfen: Ist die Fahrt in der Zukunft?
    if (isFutureRide($pdo, $fahrt_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Fahrt liegt in der Zukunft und kann nicht beendet werden.'
        ]);
        exit;
    }

    // Fahrt jetzt beenden
    $fahrzeit_bis = $_POST['fahrzeit_bis'] ?? date('H:i:s');
    
    try {
        // Prüfen, ob fahrzeit_von gesetzt ist
        $stmt = $pdo->prepare("SELECT fahrzeit_von FROM fahrten WHERE id = :id");
        $stmt->execute([':id' => $fahrt_id]);
        $fahrt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (empty($fahrt['fahrzeit_von'])) {
            // Wenn keine Startzeit, setzen wir sie automatisch auf eine Stunde vor Endzeit
            $fahrzeit_von = date('H:i:s', strtotime($fahrzeit_bis) - 3600);
            
            $stmt = $pdo->prepare("
                UPDATE fahrten 
                SET fahrzeit_bis   = :fahrzeit_bis,
                    fahrzeit_von   = :fahrzeit_von,
                    fahrzeit_summe = TIMEDIFF(:fahrzeit_bis, :fahrzeit_von)
                WHERE id = :id
            ");
            $stmt->execute([
                ':fahrzeit_bis' => $fahrzeit_bis,
                ':fahrzeit_von' => $fahrzeit_von,
                ':id'           => $fahrt_id
            ]);
        } else {
            // Normale Beendigung mit existierender Startzeit
            $stmt = $pdo->prepare("
                UPDATE fahrten 
                SET fahrzeit_bis   = :fahrzeit_bis, 
                    fahrzeit_summe = TIMEDIFF(:fahrzeit_bis, fahrzeit_von)
                WHERE id = :id
            ");
            $stmt->execute([
                ':fahrzeit_bis' => $fahrzeit_bis,
                ':id'           => $fahrt_id
            ]);
        }
        
        echo json_encode([
            'success'      => true,
            'message'      => 'Fahrt erfolgreich beendet',
            'fahrzeit_bis' => $fahrzeit_bis
        ]);
        exit;
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Fehler beim Beenden der Fahrt',
            'error'   => $e->getMessage()
        ]);
        exit;
    }

} else {
    // Allgemeine Aktualisierung der Fahrtdaten
    
    // Mögliche Felder, die upgedatet werden dürfen
    $allowedFields = [
        'fahrzeit_von',
        'fahrzeit_bis',
        'ausgaben',
        'wartezeit',
        'fahrer_bemerkung'
    ];
    
    $updateData = [];
    $params     = [':id' => $fahrt_id];
    
    // Felder sammeln
    foreach ($allowedFields as $field) {
        if (isset($_POST[$field])) {
            $updateData[]       = "$field = :$field";
            $params[":$field"]  = $_POST[$field];
        }
    }
    
    // Falls Start- & Endzeit gleichzeitig gepostet -> fahrzeit_summe berechnen
    if (!empty($_POST['fahrzeit_von']) && !empty($_POST['fahrzeit_bis'])) {
        $updateData[] = 'fahrzeit_summe = TIMEDIFF(:fahrzeit_bis, :fahrzeit_von)';
    }
    
    // Update durchführen, wenn mindestens ein Feld vorliegt
    if (!empty($updateData)) {
        try {
            $sql = "UPDATE fahrten SET " . implode(', ', $updateData) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success'        => true,
                'message'        => 'Fahrt erfolgreich aktualisiert',
                'updated_fields' => array_keys($params)
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Fehler beim Aktualisieren der Fahrt',
                'error'   => $e->getMessage()
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Keine aktualisierbaren Felder gefunden',
            'code'    => 400
        ]);
        exit;
    }
}
