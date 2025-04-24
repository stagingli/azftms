<?php
/**
 * verfuegbarkeit.php
 * Verwaltungsseite für Fahrer-Verfügbarkeiten
 */

// Konfiguration einbinden
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../assets/header.php';
require_once __DIR__ . '/../../app/classes/FahrerVerfuegbarkeit.php';

// Nur Fahrer dürfen diese Seite nutzen
if (!isset($_SESSION['user']) || !in_array('fahrer', $_SESSION['user']['rollen'])) {
    header("Location: /auth/login.php");
    exit;
}

// Verfügbarkeitsklasse initialisieren
$verfManager = new FahrerVerfuegbarkeit($pdo);

// Aktuelle Nutzer-ID aus der Session holen
$nutzerId = $_SESSION['user']['id'];
$userName = $_SESSION['user']['name'] ?? 'Unbekannt';

// Standard-Zeitraum: Aktueller Monat + 2 Monate
$heute = new DateTime();
$vonDatum = $heute->format('Y-m-01'); // Erster Tag des aktuellen Monats
$ende = clone $heute;
$ende->modify('+2 months');
$ende->modify('last day of this month');
$bisDatum = $ende->format('Y-m-d');

// Filter über GET-Parameter
if (isset($_GET['von']) && !empty($_GET['von'])) {
    $vonDatum = $_GET['von'];
}
if (isset($_GET['bis']) && !empty($_GET['bis'])) {
    $bisDatum = $_GET['bis'];
}

// AJAX-Handler für POST-Anfragen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // CSRF-Schutz prüfen
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF-Token']);
        exit;
    }
    
    $response = ['success' => false, 'message' => 'Unbekannte Aktion'];
    
    switch ($_POST['action']) {
        case 'add':
            // Neuen Eintrag hinzufügen
            $datumVon = $_POST['datum_von'] ?? date('Y-m-d');
            $datumBis = $_POST['datum_bis'] ?? $datumVon;
            $typ = $_POST['typ'] ?? 'urlaub';
            $ganztags = isset($_POST['ganztags']) ? (bool)$_POST['ganztags'] : true;
            $zeitVon = $ganztags ? null : ($_POST['zeit_von'] ?? null);
            $zeitBis = $ganztags ? null : ($_POST['zeit_bis'] ?? null);
            
            $result = $verfManager->eintragHinzufuegen(
                $nutzerId, 
                $datumVon, 
                $datumBis, 
                $typ, 
                $ganztags, 
                $zeitVon, 
                $zeitBis
            );
            
            if ($result) {
                $response = [
                    'success' => true, 
                    'message' => 'Eintrag erfolgreich hinzugefügt',
                    'id' => $result
                ];
            } else {
                $response = [
                    'success' => false, 
                    'message' => 'Fehler beim Hinzufügen des Eintrags'
                ];
            }
            break;
            
        case 'delete':
            // Eintrag löschen
            $id = $_POST['id'] ?? 0;
            
            if ($verfManager->eintragLoeschen($id, $nutzerId)) {
                $response = [
                    'success' => true, 
                    'message' => 'Eintrag erfolgreich gelöscht'
                ];
            } else {
                $response = [
                    'success' => false, 
                    'message' => 'Fehler beim Löschen des Eintrags'
                ];
            }
            break;
            
        case 'update':
            // Eintrag aktualisieren
            $id = $_POST['id'] ?? 0;
            $daten = [];
            
            if (isset($_POST['datum_von'])) $daten['datum_von'] = $_POST['datum_von'];
            if (isset($_POST['datum_bis'])) $daten['datum_bis'] = $_POST['datum_bis'];
            if (isset($_POST['typ'])) $daten['typ'] = $_POST['typ'];
            if (isset($_POST['ganztags'])) $daten['ganztags'] = (bool)$_POST['ganztags'];
            if (isset($_POST['zeit_von'])) $daten['zeit_von'] = $_POST['zeit_von'];
            if (isset($_POST['zeit_bis'])) $daten['zeit_bis'] = $_POST['zeit_bis'];
            
            if ($verfManager->eintragAktualisieren($id, $daten, $nutzerId)) {
                $response = [
                    'success' => true, 
                    'message' => 'Eintrag erfolgreich aktualisiert'
                ];
            } else {
                $response = [
                    'success' => false, 
                    'message' => 'Fehler beim Aktualisieren des Eintrags'
                ];
            }
            break;
            
        case 'add_pattern':
            // Verfügbarkeitsmuster hinzufügen
            $wochentag = $_POST['wochentag'] ?? 1;
            $zeitVon = $_POST['zeit_von'] ?? '08:00:00';
            $zeitBis = $_POST['zeit_bis'] ?? '17:00:00';
            
            $result = $verfManager->musterHinzufuegen(
                $nutzerId, 
                $wochentag, 
                $zeitVon, 
                $zeitBis
            );
            
            if ($result) {
                $response = [
                    'success' => true, 
                    'message' => 'Verfügbarkeitsmuster erfolgreich hinzugefügt',
                    'id' => $result
                ];
            } else {
                $response = [
                    'success' => false, 
                    'message' => 'Fehler beim Hinzufügen des Verfügbarkeitsmusters'
                ];
            }
            break;
            
        case 'delete_pattern':
            // Verfügbarkeitsmuster löschen
            $id = $_POST['id'] ?? 0;
            
            if ($verfManager->musterLoeschen($id, $nutzerId)) {
                $response = [
                    'success' => true, 
                    'message' => 'Verfügbarkeitsmuster erfolgreich gelöscht'
                ];
            } else {
                $response = [
                    'success' => false, 
                    'message' => 'Fehler beim Löschen des Verfügbarkeitsmusters'
                ];
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Holen der Daten für die Anzeige
$eintraege = $verfManager->eintraegeAbrufen($nutzerId, $vonDatum, $bisDatum);
$muster = $verfManager->musterAbrufen($nutzerId);

// Zeitraum-Navigationshilfe
$previousMonth = new DateTime($vonDatum);
$previousMonth->modify('-1 month');
$nextMonth = new DateTime($vonDatum);
$nextMonth->modify('+1 month');

$previousUrl = '?von=' . $previousMonth->format('Y-m-01') . '&bis=' . $previousMonth->format('Y-m-t');
$nextUrl = '?von=' . $nextMonth->format('Y-m-01') . '&bis=' . $nextMonth->format('Y-m-t');
$currentMonthUrl = '?von=' . date('Y-m-01') . '&bis=' . date('Y-m-t');
$next3MonthsUrl = '?von=' . date('Y-m-01') . '&bis=' . date('Y-m-d', strtotime('+3 months -1 day'));

// Formatiere die Einträge für den Kalender
$calendarEvents = [];
foreach ($eintraege as $eintrag) {
    $title = $eintrag['typ'] === 'urlaub' ? 'Urlaub' : 'Nicht verfügbar';
    $color = $eintrag['typ'] === 'urlaub' ? '#dc3545' : '#ffc107'; // Rot für Urlaub, Gelb für Nicht-Verfügbar
}

    $calendarEvents[] = [
        'id' => $eintrag['id'],
        'title' => $title,
        'start' => $eintrag['datum_von'] . ($eintrag['ganztags'] ? '' : 'T' . $eintrag['zeit_von']),
        'end' => $eintrag['datum_bis'] . ($eintrag['ganztags'] ? '' : 'T' . $eintrag['zeit_bis']),
        'color' => $color
    ];