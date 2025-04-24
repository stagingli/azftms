<?php
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/mailer_config.php'; // Mailer-Konfiguration laden
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Eigenes Debug-Log definieren
$debug_log = __DIR__ . '/../../app/logs/fahrt_speichern_debug.log';
if (!is_dir(dirname($debug_log))) mkdir(dirname($debug_log), 0777, true);
function debug_log($message, $data = null) {
    global $debug_log;
    $log_entry = date('Y-m-d H:i:s') . " - $message";
    if ($data !== null) $log_entry .= "\n" . print_r($data, true);
    $log_entry .= "\n--------------------------------------------------\n";
    file_put_contents($debug_log, $log_entry, FILE_APPEND);
}

debug_log("Fahrt speichern gestartet", $_POST);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debug_log("Keine POST-Anfrage - Umleitung");
    $_SESSION['alert_type'] = 'warning';
    $_SESSION['alert_icon'] = 'exclamation-triangle';
    $_SESSION['alert_msg'] = 'Ungültige Anfrage. Bitte verwenden Sie das Fahrtformular.';
    header("Location: fahrten_liste.php");
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    debug_log("Ungültiger CSRF-Token", $_POST['csrf_token'] ?? 'nicht gesetzt');
    $_SESSION['alert_type'] = 'danger';
    $_SESSION['alert_icon'] = 'shield-exclamation';
    $_SESSION['alert_msg'] = 'Sicherheitsfehler: Ungültiger CSRF-Token. Bitte laden Sie die Seite neu.';
    header("Location: fahrten_liste.php");
    exit();
}

try {
    $felder = [
        'kunde_id'           => FILTER_VALIDATE_INT,
        'abholdatum'         => FILTER_SANITIZE_SPECIAL_CHARS,
        'abfahrtszeit'       => FILTER_SANITIZE_SPECIAL_CHARS,
        'fahrer'             => FILTER_VALIDATE_INT,
        'fahrzeug'           => FILTER_VALIDATE_INT,
        'abholort'           => FILTER_VALIDATE_INT,
        'ziel'               => FILTER_VALIDATE_INT,
        'zahlungsmethode'    => FILTER_VALIDATE_INT,
        'flugnummer'         => FILTER_SANITIZE_SPECIAL_CHARS,
        'fahrtpreis'         => FILTER_VALIDATE_FLOAT,
        'personenanzahl'     => FILTER_VALIDATE_INT,
        'dispo_bemerkung'    => FILTER_SANITIZE_SPECIAL_CHARS,
        'rechnungsnummer'    => FILTER_SANITIZE_SPECIAL_CHARS,
        'hinfahrt_id'        => FILTER_VALIDATE_INT
    ];
    $daten = filter_input_array(INPUT_POST, $felder);
    debug_log("Gefilterte Daten", $daten);

    $pflichtfelder = ['kunde_id', 'abholdatum', 'abfahrtszeit', 'fahrer', 'fahrzeug', 'abholort', 'ziel', 'zahlungsmethode'];
    foreach ($pflichtfelder as $feld) {
        if (empty($daten[$feld])) {
            throw new Exception("Das Feld '$feld' ist ein Pflichtfeld und darf nicht leer sein.");
        }
    }

    // Rechnungsnummer generieren, falls keine vorhanden ist
    if (empty($daten['rechnungsnummer'])) {
        // Höchste existierende Rechnungsnummer finden (numerischer Teil und Jahr)
        $stmt = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING(rechnungsnummer, 5) AS UNSIGNED)) AS last_number,
                   MAX(SUBSTRING(rechnungsnummer, 1, 4)) AS last_year
            FROM fahrten 
            WHERE rechnungsnummer REGEXP '^[0-9]{4}[0-9]+$'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $lastNumber = $result['last_number'] ?? null;
        $lastYear = $result['last_year'] ?? null;

        // Aktuelles Jahr basierend auf dem Fahrtdatum oder dem heutigen Datum
        $jahr = !empty($daten['abholdatum']) ? date('Y', strtotime($daten['abholdatum'])) : date('Y');

        // Wenn das Jahr gewechselt hat oder keine Rechnungsnummer existiert, starte mit dem Startwert
        if ($lastYear !== $jahr || !$lastNumber) {
            $stmt = $pdo->prepare("SELECT wert FROM tms_einstellungen WHERE schluessel = 'rechnungsnummer_start' LIMIT 1");
            $stmt->execute();
            $startNumber = (int)$stmt->fetchColumn();
            $nextNumber = $startNumber > 0 ? $startNumber + 1 : 1; // Fallback, falls kein Wert gesetzt ist
        } else {
            // Sonst nächste Nummer
            $nextNumber = $lastNumber + 1;
        }

        // Rechnungsnummer im Format "YYYYNNNN" generieren
        $rechnungsnummer = $jahr . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        // Sicherheitscheck: Prüfen, ob diese Rechnungsnummer bereits existiert
        do {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM fahrten WHERE rechnungsnummer = ?");
            $stmt->execute([$rechnungsnummer]);
            if ($stmt->fetchColumn() > 0) {
                // Falls die Nummer bereits existiert, erhöhen wir sie einfach um 1
                $nextNumber++;
                $rechnungsnummer = $jahr . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            } else {
                break; // Nummer ist eindeutig
            }
        } while (true);

        $daten['rechnungsnummer'] = $rechnungsnummer;
        debug_log("Rechnungsnummer generiert", ["nummer" => $rechnungsnummer, "next_id" => $nextNumber]);
    }

    if (!empty($daten['abfahrtszeit'])) {
        if (preg_match('/^\d{4}$/', $daten['abfahrtszeit'])) {
            $stunden = substr($daten['abfahrtszeit'], 0, 2);
            $minuten = substr($daten['abfahrtszeit'], 2, 2);
            $daten['abfahrtszeit'] = "$stunden:$minuten";
        } elseif (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $daten['abfahrtszeit'])) {
            $daten['abfahrtszeit'] = substr($daten['abfahrtszeit'], 0, 5);
        } else {
            throw new Exception("Ungültiges Format für Abfahrtszeit. Bitte im Format HH:mm eingeben.");
        }
    }

    if (!empty($daten['abholdatum'])) {
        $datumTeile = explode('.', $daten['abholdatum']);
        if (count($datumTeile) === 3) {
            $daten['abholdatum'] = "{$datumTeile[2]}-{$datumTeile[1]}-{$datumTeile[0]}";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $daten['abholdatum'])) {
            throw new Exception("Ungültiges Format für Abholdatum. Bitte im Format d.m.Y eingeben.");
        }
    } else {
        throw new Exception("Abholdatum ist erforderlich.");
    }

    debug_log("Verarbeitete Daten", $daten);

    $sql_common = "kunde_id = :kunde_id,
                   abholdatum = :abholdatum,
                   abfahrtszeit = :abfahrtszeit,
                   fahrer_id = :fahrer,
                   fahrzeug_id = :fahrzeug,
                   ort_start_id = :abholort,
                   ort_ziel_id = :ziel,
                   zahlungsmethode_id = :zahlungsmethode,
                   flugnummer = :flugnummer,
                   fahrtpreis = :fahrtpreis,
                   personenanzahl = :personenanzahl,
                   dispo_bemerkung = :dispo_bemerkung,
                   rechnungsnummer = :rechnungsnummer,
                   hinfahrt_id = :hinfahrt_id";

    if (!empty($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE fahrten SET $sql_common WHERE id = :id");
        $daten['id'] = $_POST['id'];
        $stmt->execute($daten);
        debug_log("Fahrt aktualisiert", ["id" => $_POST['id']]);
        $_SESSION['alert_msg'] = "Fahrt erfolgreich aktualisiert.";
        
        // Prüfen, ob es sich um eine frühere Fahrt handelt als dem Fahrer bekannt
        $emailResult = checkAndSendEarlyRideNotification($_POST['id'], $daten['abholdatum'], $daten['fahrer'], $pdo);
        if ($emailResult['success']) {
            debug_log("E-Mail-Benachrichtigung erfolgreich", $emailResult);
            $_SESSION['alert_msg'] .= ' Fahrer wurde über frühere Fahrt informiert.';
        } else {
            debug_log("Keine E-Mail-Benachrichtigung gesendet", $emailResult);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO fahrten SET $sql_common, erstellt_am = NOW()");
        $stmt->execute($daten);
        $newId = $pdo->lastInsertId();
        debug_log("Neue Fahrt erstellt", ["id" => $newId]);
        $_SESSION['alert_msg'] = "Neue Fahrt erfolgreich gespeichert.";

        // Prüfen, ob es sich um eine frühere Fahrt handelt als dem Fahrer bekannt
        $emailResult = checkAndSendEarlyRideNotification($newId, $daten['abholdatum'], $daten['fahrer'], $pdo);
        if ($emailResult['success']) {
            debug_log("E-Mail-Benachrichtigung erfolgreich", $emailResult);
            $_SESSION['alert_msg'] .= ' Fahrer wurde über frühere Fahrt informiert.';
        } else {
            debug_log("Keine E-Mail-Benachrichtigung gesendet", $emailResult);
        }
    }

    $_SESSION['alert_type'] = 'success';
    $_SESSION['alert_icon'] = 'check-circle';
    header("Location: fahrten_liste.php");
    exit();

} catch (Exception $e) {
    debug_log("Fehler beim Speichern der Fahrt", [
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
    $_SESSION['alert_type'] = 'danger';
    $_SESSION['alert_icon'] = 'exclamation-circle';
    $_SESSION['alert_msg'] = 'Fehler beim Speichern: ' . $e->getMessage();
    header("Location: fahrt_formular.php");
    exit();
}

/**
 * Prüft, ob die Fahrt früher ist als die früheste offene Fahrt des Fahrers und sendet ggf. eine E-Mail
 */
function checkAndSendEarlyRideNotification($fahrt_id, $fahrt_datum, $fahrer_id, $pdo) {
    debug_log("Prüfe, ob neue Fahrt früher als die früheste offene Fahrt des Fahrers ist", [
        "fahrt_id" => $fahrt_id, 
        "fahrt_datum" => $fahrt_datum,
        "fahrer_id" => $fahrer_id
    ]);
    
    // Frühestes Datum aller offenen Fahrten des Fahrers ermitteln (ohne fahrzeit_bis)
    $stmt = $pdo->prepare("
        SELECT MIN(abholdatum) AS earliest_date 
        FROM fahrten 
        WHERE id != ? 
          AND fahrer_id = ? 
          AND fahrzeit_bis IS NULL 
          AND deleted_at IS NULL
    ");
    $stmt->execute([$fahrt_id, $fahrer_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $earliest_date = $result['earliest_date'] ?? null;
    
    debug_log("Frühestes offenes Datum des Fahrers", ["earliest_date" => $earliest_date]);
    
    // Wenn kein Datum gefunden oder das neue Datum ist früher
    if ($earliest_date === null || $fahrt_datum < $earliest_date) {
        debug_log("Neue Fahrt ist früher als die früheste offene Fahrt des Fahrers, bereite E-Mail vor");
        
        // Lade die vollständigen Fahrtdetails
        $stmt = $pdo->prepare("
            SELECT f.*, 
                   k.vorname AS kunde_vorname, k.nachname AS kunde_nachname, 
                   k.email AS kunde_email, k.telefon AS kunde_telefon,
                   n.name AS fahrer_name, n.email AS fahrer_email,
                   estart.wert AS start_ort, eziel.wert AS ziel_ort
            FROM fahrten f
            LEFT JOIN kunden k ON f.kunde_id = k.id
            LEFT JOIN nutzer n ON f.fahrer_id = n.id
            LEFT JOIN einstellungen estart ON f.ort_start_id = estart.id
            LEFT JOIN einstellungen eziel ON f.ort_ziel_id = eziel.id
            WHERE f.id = ?
        ");
        $stmt->execute([$fahrt_id]);
        $fahrt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fahrt) {
            debug_log("Fahrt konnte nicht geladen werden", ["fahrt_id" => $fahrt_id]);
            return ['success' => false, 'reason' => 'Fahrt konnte nicht geladen werden'];
        }
        
        // Wenn der Fahrer keine E-Mail hat, nicht fortfahren
        if (empty($fahrt['fahrer_email'])) {
            debug_log("Fahrer hat keine E-Mail-Adresse", ["fahrer_id" => $fahrer_id]);
            return ['success' => false, 'reason' => 'Fahrer hat keine E-Mail-Adresse'];
        }
        
        // Wenn die neue Fahrt selbst bereits abgeschlossen ist, keine Benachrichtigung senden
        if (!empty($fahrt['fahrzeit_bis'])) {
            debug_log("Neue Fahrt ist bereits abgeschlossen, keine Benachrichtigung notwendig", 
                ["fahrt_id" => $fahrt_id, "fahrzeit_bis" => $fahrt['fahrzeit_bis']]);
            return ['success' => false, 'reason' => 'Fahrt ist bereits abgeschlossen'];
        }
        
        $result = sendRideNotification($fahrt, $fahrt_id);
        return ['success' => $result, 'reason' => $result ? 'E-Mail erfolgreich gesendet' : 'Fehler beim E-Mail-Versand'];
    } else {
        debug_log("Neue Fahrt ist nicht früher als die früheste offene Fahrt des Fahrers, keine E-Mail notwendig");
        return ['success' => false, 'reason' => 'Fahrt ist nicht früher als die früheste offene Fahrt des Fahrers'];
    }
}

/**
 * Sendet eine E-Mail-Benachrichtigung an den Fahrer
 */
function sendRideNotification($fahrt, $fahrtId) {
    global $pdo;
    
    // Wenn bereits Fahrtdaten übergeben wurden, diese benutzen
    if (is_array($fahrt) && isset($fahrt['fahrer_email']) && isset($fahrt['fahrer_name'])) {
        $fahrer = [
            'email' => $fahrt['fahrer_email'],
            'name' => $fahrt['fahrer_name']
        ];
    } 
    // Sonst Fahrerdaten abrufen
    else {
        $stmt = $pdo->prepare("SELECT n.email, n.name FROM nutzer n WHERE n.id = ?");
        $stmt->execute([$fahrt['fahrer_id']]);
        $fahrer = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$fahrer || empty($fahrer['email'])) {
        debug_log("Fahrer hat keine E-Mail-Adresse", $fahrer);
        return false;
    }

    // E-Mail-Template laden
    $templateStmt = $pdo->prepare("SELECT subject, body FROM email_templates WHERE id = 'fahrer_neue_fahrt'");
    $templateStmt->execute();
    $template = $templateStmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        debug_log("E-Mail-Template 'fahrer_neue_fahrt' nicht gefunden");
        return false;
    }

    // Lese Start- und Zielort aus DB, falls nicht in $fahrt vorhanden
    if (!isset($fahrt['start_ort']) || !isset($fahrt['ziel_ort'])) {
        $stmt = $pdo->prepare("
            SELECT estart.wert AS start_ort, eziel.wert AS ziel_ort
            FROM fahrten f
            LEFT JOIN einstellungen estart ON f.ort_start_id = estart.id
            LEFT JOIN einstellungen eziel ON f.ort_ziel_id = eziel.id
            WHERE f.id = ?
        ");
        $stmt->execute([$fahrtId]);
        $orte = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($orte) {
            $fahrt['start_ort'] = $orte['start_ort'];
            $fahrt['ziel_ort'] = $orte['ziel_ort'];
        }
    }

    // Lese Kundendaten aus DB, falls nicht in $fahrt vorhanden
    if (!isset($fahrt['kunde_vorname']) || !isset($fahrt['kunde_nachname'])) {
        $stmt = $pdo->prepare("
            SELECT k.vorname AS kunde_vorname, k.nachname AS kunde_nachname
            FROM fahrten f
            LEFT JOIN kunden k ON f.kunde_id = k.id
            WHERE f.id = ?
        ");
        $stmt->execute([$fahrtId]);
        $kunde = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($kunde) {
            $fahrt['kunde_vorname'] = $kunde['kunde_vorname'];
            $fahrt['kunde_nachname'] = $kunde['kunde_nachname'];
        }
    }

    // Vorbereiten der Ersetzungswerte
    $kunde_name = isset($fahrt['kunde_vorname']) && isset($fahrt['kunde_nachname']) 
                ? trim($fahrt['kunde_vorname'] . ' ' . $fahrt['kunde_nachname']) 
                : '---';

    $fahrt_strecke = isset($fahrt['start_ort']) && isset($fahrt['ziel_ort'])
                   ? $fahrt['start_ort'] . ' → ' . $fahrt['ziel_ort']
                   : '---';

    // Felder für die Ersetzung
    $suche = [
        '[fahrt_id]', 
        '[fahrt_datum]', 
        '[fahrt_uhrzeit]', 
        '[fahrer_name]', 
        '[kunde_name]', 
        '[fahrt_strecke]', 
        '[to_name]', 
        '[firma_name]'
    ];
    
    $ersetze = [
        $fahrtId,
        date('d.m.Y', strtotime($fahrt['abholdatum'])),
        $fahrt['abfahrtszeit'] . ' Uhr',
        $fahrer['name'],
        $kunde_name,
        $fahrt_strecke,
        $fahrer['name'],
        'Ab-zum-Flieger'
    ];

    $subject = str_replace($suche, $ersetze, $template['subject']);
    $body = str_replace($suche, $ersetze, $template['body']);

    // Verwende die neue mailer_config.php wenn vorhanden
    if (function_exists('sendMail')) {
        $email_params = [
            'to_email' => $fahrer['email'],
            'to_name' => $fahrer['name'],
            'subject' => $subject,
            'body' => $body,
            'debug_level' => 0
        ];
        
        try {
            $result = sendMail($email_params, false, true); // force_send = true
            
            if ($result) {
                debug_log("E-Mail erfolgreich mit mailer_config.php gesendet an", [$fahrer['email']]);
                return true;
            } else {
                debug_log("Fehler beim Senden der E-Mail mit mailer_config.php", [$fahrer['email']]);
                return false;
            }
        } catch (Exception $e) {
            debug_log("Exception beim Senden der E-Mail mit mailer_config.php", [$e->getMessage()]);
            // Fallback zur direkten PHPMailer-Methode
        }
    }

    // Fallback: Direkter PHPMailer-Aufruf
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'dd52114.kasserver.com'; // SMTP-Server deines Providers
        $mail->SMTPAuth = true;
        $mail->Username = 'm07601d8'; // SMTP-Benutzername
        $mail->Password = 'wwAfXWdqT3nqSStTRJSG'; // SMTP-Passwort
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('tms@ab-zum-flieger.com', 'TMS Ab-zum-Flieger');
        $mail->addAddress($fahrer['email'], $fahrer['name']);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $result = $mail->send();
        debug_log("E-Mail mit direktem PHPMailer gesendet an", [$fahrer['email']]);
        return true;
    } catch (Exception $e) {
        debug_log("Fehler beim E-Mail-Versand mit direktem PHPMailer", [$mail->ErrorInfo, $e->getMessage()]);
        return false;
    }
}