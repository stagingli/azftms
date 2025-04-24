<?php
require __DIR__ . '/../../app/config.php';

// Entscheide, welcher Modus verwendet wird basierend auf den Parametern
$isDataTablesRequest = isset($_POST['draw']);
$isAutocompleteRequest = isset($_GET['term']);

// Setze Header
header('Content-Type: application/json');

// CSRF-Token-Prüfung nur für POST-Anfragen (DataTables)
if ($isDataTablesRequest) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['error' => 'Ungültiger CSRF-Token']);
        exit;
    }
}

// AUTOCOMPLETE-MODUS
if ($isAutocompleteRequest) {
    $searchTerm = $_GET['term'];
    
    // Bereinige und trimme die Suchanfrage
    $searchTerm = trim($searchTerm);
    
    $sql = "SELECT k.id, k.kundennummer, k.vorname, k.nachname, k.firmenname, 
                   k.strasse, k.hausnummer, k.plz, k.ort, k.kundentyp, k.bemerkung,
                   k.firmenanschrift, k.ansprechpartner
            FROM kunden k
            WHERE k.deleted_at IS NULL AND 
                  (k.kundennummer LIKE :search 
                   OR k.vorname LIKE :search 
                   OR k.nachname LIKE :search
                   OR k.firmenname LIKE :search
                   OR CONCAT(k.vorname, ' ', k.nachname) LIKE :search)
            ORDER BY 
               CASE 
                  WHEN k.kundennummer = :exactSearch THEN 1
                  WHEN k.nachname = :exactSearch THEN 2
                  WHEN k.vorname = :exactSearch THEN 3
                  WHEN k.firmenname = :exactSearch THEN 4
                  ELSE 5
               END,
               k.nachname, k.vorname
            LIMIT 15";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':search', "%$searchTerm%", PDO::PARAM_STR);
    $stmt->bindValue(':exactSearch', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();
    $kunden = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = array_map(function($kunde) {
        // Konsistente Formatierung des Anzeigenamens
        $label = $kunde['kundentyp'] === 'firma' && !empty($kunde['firmenname']) 
                ? $kunde['firmenname'] . ' (Firma)' 
                : trim($kunde['vorname'] . ' ' . $kunde['nachname']);
        
        // Füge Kundennummer hinzu
        $label .= ' [' . $kunde['kundennummer'] . ']';
        
        // Komplettes Kundenobjekt für die Client-Seite
        return [
            'id' => $kunde['id'],
            'label' => $label,
            'value' => $label,
            'kundennummer' => $kunde['kundennummer'],
            'vorname' => $kunde['vorname'],
            'nachname' => $kunde['nachname'],
            'strasse' => $kunde['strasse'],
            'hausnummer' => $kunde['hausnummer'],
            'plz' => $kunde['plz'],
            'ort' => $kunde['ort'],
            'kundentyp' => $kunde['kundentyp'],
            'firmenname' => $kunde['firmenname'],
            'firmenanschrift' => $kunde['firmenanschrift'],
            'bemerkung' => $kunde['bemerkung'],
            'ansprechpartner' => $kunde['ansprechpartner']
        ];
    }, $kunden);
    
    echo json_encode($result);
    exit;
}

// DATATABLES-MODUS
else if ($isDataTablesRequest) {
    // DataTables-Parameter
    $start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    $search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
    
    // Ordnungsspalte und -richtung
    $orderColumn = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 0;
    $orderDir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';
    
    // Mapping der Spalten-Indizes zu DB-Feldern
    $columns = [
        0 => 'k.kundennummer',
        1 => 'name',
        2 => 'k.kundentyp',
        3 => 'fahrtenanzahl'
    ];
    
    // Sicherstellen, dass die angeforderte Ordnungsspalte existiert
    $orderByField = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'k.erstellt_am';

    // SQL-Abfrage
    $sql = "SELECT k.id, k.kundennummer, 
            CASE 
                WHEN k.kundentyp = 'firma' THEN k.firmenname 
                ELSE CONCAT(k.vorname, ' ', k.nachname) 
            END AS name, 
            k.kundentyp, 
            (SELECT COUNT(*) FROM fahrten f WHERE f.kunde_id = k.id AND f.deleted_at IS NULL) AS fahrtenanzahl 
            FROM kunden k 
            WHERE k.deleted_at IS NULL";

    // Suchbedingung, wenn eine Suche durchgeführt wird
    $searchCondition = "";
    if (!empty($search)) {
        $searchCondition = " AND (k.kundennummer LIKE :search 
                    OR k.vorname LIKE :search 
                    OR k.nachname LIKE :search 
                    OR k.firmenname LIKE :search
                    OR CONCAT(k.vorname, ' ', k.nachname) LIKE :search)";
        $sql .= $searchCondition;
    }

    // Ordering und Paging
    $sql .= " ORDER BY " . $orderByField . " " . $orderDir . " LIMIT :start, :length";

    $stmt = $pdo->prepare($sql);
    
    // Parameter binden
    if (!empty($search)) {
        $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $kunden = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Gesamtanzahl der Kunden (ohne Filter)
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM kunden WHERE deleted_at IS NULL");
    $total = $totalStmt->fetchColumn();

    // Anzahl der gefilterten Kunden
    $filtered = $total; // Default, wenn keine Suche
    if (!empty($search)) {
        $filteredSql = "SELECT COUNT(*) FROM kunden k WHERE k.deleted_at IS NULL" . $searchCondition;
        $filteredStmt = $pdo->prepare($filteredSql);
        $filteredStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $filteredStmt->execute();
        $filtered = $filteredStmt->fetchColumn();
    }

    // JSON-Ausgabe für DataTables
    $response = [
        'draw' => $draw,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => array_map(function($kunde) {
            return [
                'kundennummer' => htmlspecialchars($kunde['kundennummer']),
                'name' => htmlspecialchars($kunde['name']),
                'kundentyp' => htmlspecialchars($kunde['kundentyp']),
                'fahrtenanzahl' => '<a href="fahrten_liste.php?kunde_id=' . $kunde['id'] . '" class="badge bg-info text-decoration-none">' . $kunde['fahrtenanzahl'] . ' Fahrt(en)</a>',
                'aktionen' => '<a href="kunde_formular.php?id=' . htmlspecialchars($kunde['id']) . '" class="btn btn-warning btn-sm"><i class="fas fa-edit me-1"></i>Bearbeiten</a>
                               <form method="POST" action="kunde_loeschen.php" style="display:inline;">
                                   <input type="hidden" name="id" value="' . htmlspecialchars($kunde['id']) . '">
                                   <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                                   <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Möchtest du diesen Kunden wirklich archivieren?\');">
                                       <i class="fas fa-trash-alt me-1"></i>Löschen
                                   </button>
                               </form>'
            ];
        }, $kunden)
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Wenn keiner der Modi erkannt wurde
echo json_encode(['error' => 'Ungültiger Anfrage-Typ']);
exit;