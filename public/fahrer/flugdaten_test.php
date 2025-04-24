<?php
// Datei: fahrer/flugdaten_test.php
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Flugdaten Test – Ankunft für LX 1058</title>
</head>
<body>
  <h1>Flugdaten Test – Ankunft für LX 1058</h1>
  
  <!-- Formular: Datum und Flugnummer -->
  <form method="GET">
    <label for="date">Datum (YYYY-MM-DD):</label>
    <input type="text" id="date" name="date" 
           value="<?php echo htmlspecialchars($_GET['date'] ?? date('Y-m-d')); ?>">
    <br><br>
    
    <label for="flightNumber">Flugnummer (z.B. LX 1058):</label>
    <input type="text" id="flightNumber" name="flightNumber" 
           value="<?php echo htmlspecialchars($_GET['flightNumber'] ?? 'LX 1058'); ?>">
    <br><br>
    
    <button type="submit">Abfrage starten</button>
  </form>
  
  <?php
  if (!empty($_GET['date']) && !empty($_GET['flightNumber'])) {
      // Parameter holen
      $date = $_GET['date'];
      $flightNumber = $_GET['flightNumber'];
      $type = 'arrivals'; // fest für Ankünfte
      
      // Falls die Flugnummer als "LX1058" eingegeben wird, formatieren wir sie in "LX 1058"
      if (preg_match('/^([A-Za-z]{2})(\d+)$/', $flightNumber, $matches)) {
          $flightNumber = $matches[1] . ' ' . $matches[2];
      }
      
      // Da cURL in URLs keine rohen Leerzeichen akzeptiert, ersetzen wir das Leerzeichen durch %20,
      // was von der API in der Regel intern als Leerzeichen interpretiert wird.
      $flightNumberEncoded = str_replace(' ', '%20', $flightNumber);
      
      // URL zusammenbauen
      $apiUrl = "https://rest.api.hamburg-airport.de/v2/flights/{$type}?from={$date}&to={$date}&flightNumber={$flightNumberEncoded}";
      
      // Deinen echten API-Key hier eintragen
      $apiKey = '60c911c817624162a365c4928af68ee7';
      
      // Debug-Ausgabe
      echo "<hr><h2>Debug-Info</h2>";
      echo "<p><strong>Eingegebene Flugnummer:</strong> " . htmlspecialchars($flightNumber) . "</p>";
      echo "<p><strong>Aufgerufene URL:</strong> " . htmlspecialchars($apiUrl) . "</p>";
      
      // cURL-Request an die API
      $ch = curl_init($apiUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Accept: application/json',
          'Ocp-Apim-Subscription-Key: ' . $apiKey
      ]);
      $response = curl_exec($ch);
      $err      = curl_error($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      
      echo "<hr><h2>Ergebnis</h2>";
      if ($err) {
          echo "<p><strong>cURL-Fehler:</strong> " . htmlspecialchars($err) . "</p>";
      } else {
          echo "<p><strong>HTTP-Code:</strong> " . htmlspecialchars($httpCode) . "</p>";
          echo "<pre>" . htmlspecialchars($response) . "</pre>";
      }
  }
  ?>
</body>
</html>
