<?php
// autocomplete.php
require __DIR__ . '/../../app/config.php';

if (isset($_POST['query'])) {
    $query = trim($_POST['query']);
    
    if (!empty($query)) {
        try {
            // Setup PDO connection
            $pdo = new PDO("mysql:host=localhost;dbname=d0430432;charset=utf8mb4", "d0430432", "UdMmeTjQmFuMt6EqEMeV");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Prepare and execute SQL query
            $stmt = $pdo->prepare("SELECT id, vorname, nachname, firmenname
                                   FROM kunden 
                                   WHERE vorname LIKE :q
                                      OR nachname LIKE :q
                                      OR firmenname LIKE :q
                                   ORDER BY firmenname, nachname, vorname
                                   LIMIT 10");
            $stmt->execute([':q' => "%$query%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Check if results were found
            if ($results) {
                foreach ($results as $row) {
                    // Determine the display name (company name or full name)
                    $displayName = !empty($row['firmenname'])
                        ? $row['firmenname']
                        : ($row['vorname'] . ' ' . $row['nachname']);

                    // Output each suggestion
                    echo '<div class="autocomplete-suggestion" data-id="' . $row['id'] . '">'
                         . htmlspecialchars($displayName) . '</div>';
                }
            } else {
                // No results found
                echo '<div class="autocomplete-suggestion">Keine Ergebnisse</div>';
            }
        } catch (PDOException $e) {
            // Error handling
            echo '<div class="autocomplete-suggestion">Fehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>
