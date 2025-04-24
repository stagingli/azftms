<?php
session_start();
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/permissions.php';

// Logging für bessere Fehlerdiagnose
function log_action($message) {
    error_log("[" . date('Y-m-d H:i:s') . "] Einstellungen: " . $message);
}

// Nur POST-Anfragen bearbeiten
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Ungültige Anfrage-Methode.";
    header("Location: fahrt_einstellungen.php");
    exit();
}

// CSRF-Token überprüfen
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    log_action("CSRF-Token ungültig");
    $_SESSION['error'] = "Sicherheitstoken ungültig. Bitte versuche es erneut.";
    header("Location: fahrt_einstellungen.php");
    exit();
}

// Aktion überprüfen
if (!isset($_POST['action'])) {
    $_SESSION['error'] = "Keine Aktion angegeben.";
    header("Location: fahrt_einstellungen.php");
    exit();
}

$action = $_POST['action'];
log_action("Aktion: " . $action);

try {
    switch ($action) {
        case 'add':
            // Prüfen, ob alle notwendigen Felder vorhanden sind
            if (!isset($_POST['kategorie']) || !isset($_POST['wert']) || empty($_POST['kategorie']) || empty($_POST['wert'])) {
                $_SESSION['error'] = "Alle Felder müssen ausgefüllt sein.";
                header("Location: fahrt_einstellungen.php");
                exit();
            }
            
            $kategorie = trim($_POST['kategorie']);
            $wert = trim($_POST['wert']);
            
            // Gültige Kategorie sicherstellen
            $erlaubte_kategorien = ['zahlungsmethode', 'fahrzeug', 'ort', 'zusatzequipment'];
            if (!in_array($kategorie, $erlaubte_kategorien)) {
                $_SESSION['error'] = "Ungültige Kategorie.";
                header("Location: fahrt_einstellungen.php");
                exit();
            }
            
            // Auf Duplikate prüfen
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM einstellungen WHERE kategorie = ? AND wert = ?");
            $stmt->execute([$kategorie, $wert]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = "Diese Einstellung existiert bereits.";
                header("Location: fahrt_einstellungen.php");
                exit();
            }
            
            // Einstellung hinzufügen
            $stmt = $pdo->prepare("INSERT INTO einstellungen (kategorie, wert) VALUES (?, ?)");
            $stmt->execute([$kategorie, $wert]);
            
            log_action("Einstellung hinzugefügt: Kategorie=" . $kategorie . ", Wert=" . $wert);
            $_SESSION['success'] = "Einstellung erfolgreich hinzugefügt.";
            header("Location: fahrt_einstellungen.php");
            break;
            
        case 'update':
            // Prüfen, ob alle notwendigen Felder vorhanden sind
            if (!isset($_POST['id']) || !isset($_POST['kategorie']) || !isset($_POST['wert']) || 
                empty($_POST['id']) || empty($_POST['kategorie']) || empty($_POST['wert'])) {
                $_SESSION['error'] = "Alle Felder müssen ausgefüllt sein.";
                header("Location: fahrt_einstellungen.php");
                exit();
            }
            
            $id = intval($_POST['id']);
            $kategorie = trim($_POST['kategorie']);
            $wert = trim($_POST['wert']);
            
            // Gültige Kategorie sicherstellen
            $erlaubte_kategorien = ['zahlungsmethode', 'fahrzeug', 'ort', 'zusatzequipment'];
            if (!in_array($kategorie, $erlaubte_kategorien)) {
                $_SESSION['error'] = "Ungültige Kategorie.";
                header("Location: fahrt_einstellungen_bearbeiten.php?id=" . $id);
                exit();
            }
            
            // Auf Duplikate prüfen (ausgenommen eigene ID)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM einstellungen WHERE kategorie = ? AND wert = ? AND id != ?");
            $stmt->execute([$kategorie, $wert, $id]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = "Eine Einstellung mit diesem Wert existiert bereits.";
                header("Location: fahrt_einstellungen_bearbeiten.php?id=" . $id);
                exit();
            }
            
            // Einstellung aktualisieren
            $stmt = $pdo->prepare("UPDATE einstellungen SET kategorie = ?, wert = ? WHERE id = ?");
            $stmt->execute([$kategorie, $wert, $id]);
            
            log_action("Einstellung aktualisiert: ID=" . $id . ", Kategorie=" . $kategorie . ", Wert=" . $wert);
            $_SESSION['success'] = "Einstellung erfolgreich aktualisiert.";
            header("Location: fahrt_einstellungen.php");
            break;
            
        case 'delete':
            // Prüfen, ob ID vorhanden ist
            if (!isset($_POST['id']) || empty($_POST['id'])) {
                $_SESSION['error'] = "Keine ID angegeben.";
                header("Location: fahrt_einstellungen.php");
                exit();
            }
            
            $id = intval($_POST['id']);
            
            // Prüfen, ob die Einstellung bereits verwendet wird (Beispiel für Abhängigkeitsprüfung)
            // Je nach Datenbankstruktur müsste diese Prüfung angepasst werden
            $benutzt = false;
            
            if ($benutzt) {
                $_SESSION['error'] = "Diese Einstellung kann nicht gelöscht werden, da sie bereits in Verwendung ist.";
                header("Location: fahrt_einstellungen.php");
                exit();
            }
            
            // Einstellung löschen
            $stmt = $pdo->prepare("DELETE FROM einstellungen WHERE id = ?");
            $stmt->execute([$id]);
            
            log_action("Einstellung gelöscht: ID=" . $id);
            $_SESSION['success'] = "Einstellung erfolgreich gelöscht.";
            header("Location: fahrt_einstellungen.php");
            break;
            
        default:
            $_SESSION['error'] = "Ungültige Aktion.";
            header("Location: fahrt_einstellungen.php");
            break;
    }
} catch (PDOException $e) {
    log_action("Datenbankfehler: " . $e->getMessage());
    $_SESSION['error'] = "Datenbankfehler: " . $e->getMessage();
    header("Location: fahrt_einstellungen.php");
    exit();
} catch (Exception $e) {
    log_action("Allgemeiner Fehler: " . $e->getMessage());
    $_SESSION['error'] = "Ein Fehler ist aufgetreten: " . $e->getMessage();
    header("Location: fahrt_einstellungen.php");
    exit();
}
?>