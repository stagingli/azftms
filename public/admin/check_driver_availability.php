<?php
ob_start();
require_once __DIR__ . '/../../app/config.php';
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$date = $_GET['date'] ?? null;
$time = $_GET['time'] ?? null;

if (!$date || !$time) {
    ob_clean();
    echo json_encode([]);
    exit;
}

$datetime = $date . ' ' . $time . ':00';
$wochentag = (int) date('N', strtotime($date));

// Alle Fahrer holen
$sql = "SELECT n.id, n.name
        FROM nutzer n
        JOIN nutzer_rolle nr ON n.id = nr.nutzer_id
        JOIN rollen r ON nr.rolle_id = r.id
        WHERE r.name = 'fahrer'
        ORDER BY n.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$fahrerList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];

foreach ($fahrerList as $fahrer) {
    $isAbsent = false;
    $absenceReason = '';
    $isAvailable = null; // Initialisiere mit null

    // Einzelverfügbarkeiten prüfen
    $stmt = $pdo->prepare("SELECT * FROM fahrer_verfuegbarkeit
        WHERE nutzer_id = :id 
        AND :datum BETWEEN datum_von AND datum_bis");
    $stmt->execute([
        ':id' => $fahrer['id'],
        ':datum' => $date
    ]);
    $eintraege = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($eintraege as $e) {
        if ($e['ganztags']) {
            if ($e['typ'] === 'nicht_verfuegbar') {
                $isAbsent = true;
                $absenceReason = 'Ganztägig nicht verfügbar';
                $isAvailable = false;
            } elseif ($e['typ'] === 'verfuegbar') {
                $isAvailable = true;
            }
        } elseif ($e['zeit_von'] && $e['zeit_bis']) {
            $start = $e['datum_von'] . ' ' . $e['zeit_von'];
            $ende  = $e['datum_bis'] . ' ' . $e['zeit_bis'];
            if ($datetime >= $start && $datetime <= $ende) {
                if ($e['typ'] === 'nicht_verfuegbar') {
                    $isAbsent = true;
                    $absenceReason = 'Nicht verfügbar ' . substr($e['zeit_von'], 0, 5) . ' - ' . substr($e['zeit_bis'], 0, 5);
                    $isAvailable = false;
                } elseif ($e['typ'] === 'verfuegbar') {
                    $isAvailable = true;
                }
            }
        }
    }

    // Musterverfügbarkeiten (zyklisch)
    $stmt = $pdo->prepare("SELECT * FROM fahrer_verfuegbarkeit_muster
        WHERE nutzer_id = :id AND wochentag = :wt AND aktiv = 1");
    $stmt->execute([
        ':id' => $fahrer['id'],
        ':wt' => $wochentag
    ]);
    $musterList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($musterList as $m) {
        if ($m['ganztags']) {
            if ($m['typ'] === 'nicht_verfuegbar') {
                $isAbsent = true;
                $absenceReason = 'Zyklisch nicht verfügbar (ganztags)';
                $isAvailable = false;
            } elseif ($m['typ'] === 'verfuegbar') {
                if (!isset($isAvailable)) $isAvailable = true;
            }
        } elseif ($m['zeit_von'] && $m['zeit_bis']) {
            $start = $date . ' ' . $m['zeit_von'];
            $ende  = $date . ' ' . $m['zeit_bis'];
            if ($datetime >= $start && $datetime <= $ende) {
                if ($m['typ'] === 'nicht_verfuegbar') {
                    $isAbsent = true;
                    $absenceReason = 'Zyklisch nicht verfügbar ' . substr($m['zeit_von'], 0, 5) . ' - ' . substr($m['zeit_bis'], 0, 5);
                    $isAvailable = false;
                } elseif ($m['typ'] === 'verfuegbar') {
                    if (!isset($isAvailable)) $isAvailable = true;
                }
            }
        }
    }

    if ($isAvailable === null) {
        $isAvailable = false;
        $absenceReason = 'Keine Angabe';
    }
    
    $status = $isAbsent ? 'absent' : ($isAvailable ? 'available' : 'unknown');
    
    $result[] = [
        'id' => $fahrer['id'],
        'name' => $fahrer['name'],
        'isAbsent' => $isAbsent,
        'absenceReason' => $absenceReason,
        'verfuegbar' => $isAvailable,
        'status' => $status
    ];
}

ob_clean();
echo json_encode($result);
exit;
