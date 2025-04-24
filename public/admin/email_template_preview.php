<?php
// email_template_preview.php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/email_template_functions.php';

// Prüfen, ob GET oder POST verwendet wird
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Template aus der Datenbank laden
    $template_id = $_GET['id'] ?? '';
    
    if (empty($template_id)) {
        echo '<div class="alert alert-danger">Keine Template-ID angegeben.</div>';
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT subject, body FROM email_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        echo '<div class="alert alert-danger">Template nicht gefunden.</div>';
        exit();
    }
    
    $subject = $template['subject'];
    $body = $template['body'];
} else {
    // Daten aus POST-Request verwenden
    // CSRF-Schutz
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo '<div class="alert alert-danger">Sicherheitstoken ungültig.</div>';
        exit();
    }
    
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';
}

// Lade eine echte Beispielfahrt aus der Datenbank
$example_data = [];
try {
    // Neueste Fahrt mit vollständigen Daten laden
    $stmt = $pdo->prepare("
        SELECT f.*, 
               k.vorname AS kunde_vorname, k.nachname AS kunde_nachname, 
               k.email AS kunde_email, k.telefon AS kunde_telefon, 
               k.strasse AS kunde_strasse, k.hausnummer AS kunde_hausnummer, 
               k.plz AS kunde_plz, k.ort AS kunde_ort,
               n.name AS fahrer_name, n.email AS fahrer_email,
               estart.wert AS start_ort, eziel.wert AS ziel_ort
        FROM fahrten f
        LEFT JOIN kunden k ON f.kunde_id = k.id
        LEFT JOIN nutzer n ON f.fahrer_id = n.id
        LEFT JOIN einstellungen estart ON f.ort_start_id = estart.id
        LEFT JOIN einstellungen eziel ON f.ort_ziel_id = eziel.id
        WHERE f.deleted_at IS NULL
        ORDER BY f.erstellt_am DESC
        LIMIT 1
    ");
    $stmt->execute();
    $fahrt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($fahrt) {
        $example_data = [
            'fahrt_id' => $fahrt['id'],
            'abholdatum' => $fahrt['abholdatum'],
            'abfahrtszeit' => $fahrt['abfahrtszeit'],
            'start_ort' => $fahrt['start_ort'],
            'ziel_ort' => $fahrt['ziel_ort'],
            'fahrtpreis' => $fahrt['fahrtpreis'],
            
            'kunde_vorname' => $fahrt['kunde_vorname'],
            'kunde_nachname' => $fahrt['kunde_nachname'],
            'kunde_email' => $fahrt['kunde_email'],
            'kunde_telefon' => $fahrt['kunde_telefon'],
            'kunde_strasse' => $fahrt['kunde_strasse'],
            'kunde_hausnummer' => $fahrt['kunde_hausnummer'],
            'kunde_plz' => $fahrt['kunde_plz'],
            'kunde_ort' => $fahrt['kunde_ort'],
            
            'fahrer_name' => $fahrt['fahrer_name'],
            'fahrer_email' => $fahrt['fahrer_email'],
            'fahrer_mobil' => '(nicht verfügbar)' // Falls nicht in der Datenbank vorhanden
        ];
    } else {
        // Fallback: Statische Beispieldaten, falls keine Fahrt gefunden wird
        $example_data = [
            'fahrt_id' => '12345',
            'abholdatum' => date('Y-m-d', strtotime('+2 days')),
            'abfahrtszeit' => '09:30:00',
            'start_ort' => 'Hamburg Hauptbahnhof',
            'ziel_ort' => 'Hamburg Airport',
            'fahrtpreis' => 49.90,
            
            'kunde_vorname' => 'Max',
            'kunde_nachname' => 'Mustermann',
            'kunde_email' => 'max.mustermann@example.com',
            'kunde_telefon' => '+49 123 4567890',
            'kunde_strasse' => 'Musterstraße',
            'kunde_hausnummer' => '123',
            'kunde_plz' => '12345',
            'kunde_ort' => 'Musterstadt',
            
            'fahrer_name' => 'Erika Musterfahrerin',
            'fahrer_mobil' => '+49 987 6543210',
            'fahrer_email' => 'fahrer@example.com'
        ];
    }
    
    // Allgemeine Daten immer setzen
    $example_data['datum_heute'] = date('d.m.Y');
    $example_data['firma_name'] = 'Ab-zum-Flieger';
    $example_data['firma_kontakt'] = 'Email: info@ab-zum-flieger.com, Tel: +49 40 123456789';
    
} catch (PDOException $e) {
    echo '<div class="alert alert-warning">Fehler beim Laden der Beispieldaten: ' . $e->getMessage() . '</div>';
    // Trotzdem weitermachen mit Standard-Daten
    $example_data = [
        'fahrt_id' => 'ERROR-' . rand(1000, 9999),
        'abholdatum' => date('Y-m-d'),
        'abfahrtszeit' => '12:00:00',
        'start_ort' => 'Fehler beim Laden',
        'ziel_ort' => 'Fehler beim Laden',
        'fahrtpreis' => 0.00,
        // ... weitere Standard-Werte
    ];
}

// Shortcodes durch Beispieldaten ersetzen
$processed_body = replace_shortcodes($body, $example_data);
$processed_subject = replace_shortcodes($subject, $example_data);

// Ausgabe
echo '<div id="previewContent">';
echo '<div class="preview-subject">Betreff: ' . htmlspecialchars(strip_tags($processed_subject)) . '</div>';
echo '<div class="preview-body">' . $processed_body . '</div>';

// Optionale Debug-Anzeige der verwendeten Daten
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo '<div class="debug-data">';
    echo '<details>';
    echo '<summary><strong>Verwendete Testdaten (klicken zum Anzeigen)</strong></summary>';
    echo '<pre style="max-height: 300px; overflow-y: auto;">' . print_r($example_data, true) . '</pre>';
    echo '</details>';
    echo '</div>';
}
echo '</div>';

?>
<style>
/* Vorschau-Container */
#previewContent {
    background-color: #2e3b4e; /* Dunkler Hintergrund */
    color: #ffffff; /* Helle Schriftfarbe */
    padding: 20px;
    border-radius: 8px;
    font-family: Arial, sans-serif;
    line-height: 1.6;
}

/* Betreff */
#previewContent .preview-subject {
    font-size: 18px;
    font-weight: bold;
    color: #ffcc00; /* Gelber Farbton für den Betreff */
    margin-bottom: 15px;
}

/* Text */
#previewContent p {
    color: #d1d5db; /* Heller Grauton für normalen Text */
}

/* Hervorgehobener Text */
#previewContent strong {
    color: #ffffff; /* Weiße Schrift für Hervorhebungen */
}

/* Links */
#previewContent a {
    color: #4aa3ff; /* Heller Blauton für Links */
    text-decoration: underline;
}

#previewContent a:hover {
    color: #82caff; /* Hellere Farbe beim Hover */
}

/* Tabellen */
#previewContent table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

#previewContent table th,
#previewContent table td {
    border: 1px solid #4a5568; /* Dunkler Rahmen */
    padding: 8px;
    text-align: left;
    color: #d1d5db; /* Heller Grauton */
}

#previewContent table th {
    background-color: #374151; /* Dunkler Hintergrund für Tabellenüberschriften */
    color: #ffffff; /* Weiße Schrift */
}

/* Debug-Daten */
#previewContent .debug-data {
    background-color: #1f2937; /* Sehr dunkler Hintergrund */
    color: #d1d5db; /* Heller Grauton */
    padding: 10px;
    border-radius: 6px;
    margin-top: 20px;
    font-size: 14px;
    overflow-x: auto;
}
</style>