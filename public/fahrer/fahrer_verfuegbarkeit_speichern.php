<?php
require_once __DIR__ . '/../../app/config.php';

if (!isset($_SESSION['user']) || !in_array('fahrer', $_SESSION['user']['rollen'])) {
    $_SESSION['error_message'] = "Nicht autorisiert. Bitte melden Sie sich als Fahrer an.";
    header("Location: /auth/login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Ungültige Anfrage. Nur POST-Anfragen sind erlaubt.";
    header("Location: fahrer_verfuegbarkeit.php");
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error_message'] = "Ungültiger CSRF-Token. Bitte versuchen Sie es erneut.";
    header("Location: fahrer_verfuegbarkeit.php");
    exit;
}

$redirect_url = "fahrer_verfuegbarkeit.php";

try {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save_availability') {
            $datum_von = $_POST['datum_von'];
            $datum_bis = $_POST['datum_bis'];
            $typ = $_POST['typ'] ?? 'verfuegbar';
            $ganztags = isset($_POST['ganztags']) ? 1 : 0;
            $zeit_von = $ganztags ? null : $_POST['zeit_von'];
            $zeit_bis = $ganztags ? null : $_POST['zeit_bis'];
            $wochentag = $_POST['wochentag'] ?? '';

            // Zyklisch? Dann speichere in Muster-Tabelle
            if (!empty($wochentag)) {
                $stmt = $GLOBALS['pdo']->prepare("SELECT id FROM fahrer_verfuegbarkeit_muster 
                                                  WHERE nutzer_id = :nutzer_id AND wochentag = :wochentag");
                $stmt->execute([
                    ':nutzer_id' => $user_id,
                    ':wochentag' => (int)$wochentag
                ]);
                $existingId = $stmt->fetchColumn();

                if ($existingId) {
                    $stmt = $GLOBALS['pdo']->prepare("UPDATE fahrer_verfuegbarkeit_muster 
                        SET typ = :typ, ganztags = :ganztags, zeit_von = :zeit_von, zeit_bis = :zeit_bis, aktiv = 1 
                        WHERE id = :id");
                    $success = $stmt->execute([
                        ':typ' => $typ,
                        ':ganztags' => $ganztags,
                        ':zeit_von' => $zeit_von,
                        ':zeit_bis' => $zeit_bis,
                        ':id' => $existingId
                    ]);
                } else {
                    $stmt = $GLOBALS['pdo']->prepare("INSERT INTO fahrer_verfuegbarkeit_muster 
                        (nutzer_id, wochentag, typ, ganztags, zeit_von, zeit_bis, aktiv) 
                        VALUES (:nutzer_id, :wochentag, :typ, :ganztags, :zeit_von, :zeit_bis, 1)");
                    $success = $stmt->execute([
                        ':nutzer_id' => $user_id,
                        ':wochentag' => (int)$wochentag,
                        ':typ' => $typ,
                        ':ganztags' => $ganztags,
                        ':zeit_von' => $zeit_von,
                        ':zeit_bis' => $zeit_bis
                    ]);
                }

                $_SESSION[$success ? 'success_message' : 'error_message'] = $success
                    ? "Zyklisches Muster wurde gespeichert!"
                    : "Fehler beim Speichern des Musters.";

            } else {
                // Normale Verfügbarkeit (einmalig)
                $stmt = $GLOBALS['pdo']->prepare("INSERT INTO fahrer_verfuegbarkeit 
                    (nutzer_id, datum_von, datum_bis, typ, ganztags, zeit_von, zeit_bis) 
                    VALUES (:nutzer_id, :datum_von, :datum_bis, :typ, :ganztags, :zeit_von, :zeit_bis)");
                $success = $stmt->execute([
                    ':nutzer_id' => $user_id,
                    ':datum_von' => $datum_von,
                    ':datum_bis' => $datum_bis,
                    ':typ' => $typ,
                    ':ganztags' => $ganztags,
                    ':zeit_von' => $zeit_von,
                    ':zeit_bis' => $zeit_bis
                ]);

                $_SESSION[$success ? 'success_message' : 'error_message'] = $success
                    ? "Verfügbarkeit wurde gespeichert!"
                    : "Fehler beim Speichern der Verfügbarkeit.";
            }

        } elseif ($_POST['action'] === 'delete_availability') {
            $id = (int)$_POST['id'];
            $stmt = $GLOBALS['pdo']->prepare("DELETE FROM fahrer_verfuegbarkeit WHERE id = :id AND nutzer_id = :nutzer_id");
            $success = $stmt->execute([
                ':id' => $id,
                ':nutzer_id' => $user_id
            ]);
            $_SESSION[$success ? 'success_message' : 'error_message'] = $success
                ? "Eintrag wurde gelöscht!"
                : "Fehler beim Löschen des Eintrags.";

        } elseif ($_POST['action'] === 'delete_pattern') {
            $id = (int)$_POST['pattern_id'];
            $stmt = $GLOBALS['pdo']->prepare("UPDATE fahrer_verfuegbarkeit_muster 
                                              SET aktiv = 0 
                                              WHERE id = :id AND nutzer_id = :nutzer_id");
            $success = $stmt->execute([
                ':id' => $id,
                ':nutzer_id' => $user_id
            ]);
            $_SESSION[$success ? 'success_message' : 'error_message'] = $success
                ? "Muster wurde entfernt!"
                : "Fehler beim Entfernen des Musters.";
        }
    } else {
        $_SESSION['error_message'] = "Keine Aktion angegeben.";
    }

} catch (PDOException $e) {
    writeLog("Datenbankfehler in fahrer_verfuegbarkeit_speichern.php: " . $e->getMessage(), 'ERROR', ERROR_LOG_FILE);
    $_SESSION['error_message'] = "Ein Datenbankfehler ist aufgetreten: " . $e->getMessage();
} catch (Exception $e) {
    writeLog("Allgemeiner Fehler in fahrer_verfuegbarkeit_speichern.php: " . $e->getMessage(), 'ERROR', ERROR_LOG_FILE);
    $_SESSION['error_message'] = "Ein Fehler ist aufgetreten: " . $e->getMessage();
}

header("Location: $redirect_url");
exit;
