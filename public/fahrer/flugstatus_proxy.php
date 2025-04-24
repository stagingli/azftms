<?php
/**
 * flugstatus_proxy.php
 *
 * Liest die GET-Parameter (type, from, to, flightNumber) und ruft die Hamburg-Airport-API per cURL auf.
 * Es wird zunächst mit der Standardformatierung (ein Leerzeichen) abgefragt.
 * Falls das Ergebnis leer ist, wird ein zweiter Versuch mit zwei Leerzeichen durchgeführt.
 * Alle Abfragen und Ergebnisse werden in der Datei "airport.log" im "/app/logs/"-Ordner protokolliert.
 */

header('Content-Type: application/json; charset=utf-8');

// Log-Funktionen
function logToFile($message, $data = null) {
    // Korrigierter Pfad basierend auf der Verzeichnisstruktur
    $logDir = dirname(dirname(dirname(__FILE__))) . '/app/logs';
    $logFile = $logDir . '/airport.log';
    
    // Prüfen, ob das Log-Verzeichnis existiert, sonst erstellen
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Timestamp für den Logeintrag
    $timestamp = date('Y-m-d H:i:s');
    
    // Basisnachricht formatieren
    $logEntry = "[{$timestamp}] {$message}";
    
    // Daten hinzufügen, wenn vorhanden
    if ($data !== null) {
        $formattedData = print_r($data, true);
        $logEntry .= "\nData: " . $formattedData;
    }
    
    // Eintrag mit Trennlinie abschließen
    $logEntry .= "\n------------------------------------------------\n";
    
    // In Datei schreiben (anhängen)
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Parameter aus GET
$type            = $_GET['type']         ?? 'arrivals';
$from            = $_GET['from']         ?? date('Y-m-d');
$to              = $_GET['to']           ?? date('Y-m-d');
$rawFlightNumber = $_GET['flightNumber'] ?? '';

// API-Key (bitte den echten Key eintragen)
$apiKey = '60c911c817624162a365c4928af68ee7';

// Eingehende Anfrage loggen
logToFile("Neue Anfrage empfangen", [
    'type' => $type,
    'from' => $from,
    'to' => $to,
    'flightNumber' => $rawFlightNumber,
    'userIP' => $_SERVER['REMOTE_ADDR'],
    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
]);

// Funktion: Formatierung mit einem Leerzeichen (Standard)
function formatFlightNumberOneSpace($rawNo) {
    $rawNo = trim($rawNo);
    if (preg_match('/^([A-Za-z]{2})(\d+)$/', $rawNo, $matches)) {
        return strtoupper($matches[1]) . ' ' . $matches[2];
    }
    return $rawNo;
}

// Funktion: Formatierung mit zwei Leerzeichen
function formatFlightNumberTwoSpaces($rawNo) {
    $rawNo = trim($rawNo);
    if (preg_match('/^([A-Za-z]{2})(\d+)$/', $rawNo, $matches)) {
        return strtoupper($matches[1]) . '  ' . $matches[2];
    }
    return $rawNo;
}

// Funktion: Führe den Request durch und dekodiere JSON
function performRequest($apiUrl, $apiKey) {
    // Die Anfrage an die API loggen
    logToFile("API-Anfrage gesendet", [
        'url' => $apiUrl,
        'method' => 'GET'
    ]);
    
    $startTime = microtime(true);
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Ocp-Apim-Subscription-Key: ' . $apiKey
    ]);
    $response   = curl_exec($ch);
    $curlError  = curl_error($ch);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $endTime = microtime(true);
    $executionTime = round(($endTime - $startTime) * 1000, 2); // In Millisekunden
    
    $result = [
        'response'  => $response,
        'curlError' => $curlError,
        'httpCode'  => $httpCode,
        'data'      => json_decode($response, true),
        'executionTime' => $executionTime
    ];
    
    // Die Antwort der API loggen
    logToFile("API-Antwort erhalten", [
        'httpCode' => $httpCode,
        'executionTime' => $executionTime . ' ms',
        'curlError' => $curlError ?: 'Keine',
        'responseLength' => strlen($response),
        'responsePreview' => substr($response, 0, 500) . (strlen($response) > 500 ? '...' : '')
    ]);
    
    return $result;
}

// Debug-Info initialisieren
$debugInfo = [
    'originalFlightNumber' => $rawFlightNumber,
    'type'                 => $type,
    'from'                 => $from,
    'to'                   => $to,
];

// Wenn eine Flugnummer angegeben wurde
if (!empty($rawFlightNumber)) {
    // Entferne alle Leerzeichen für konsistente Verarbeitung
    $cleanFlightNumber = preg_replace('/\s+/', '', $rawFlightNumber);
    $debugInfo['cleanedFlightNumber'] = $cleanFlightNumber;
    
    logToFile("Flugnummer verarbeiten", [
        'original' => $rawFlightNumber,
        'cleaned' => $cleanFlightNumber
    ]);
    
    // Versuch 1: Formatierung mit einem Leerzeichen
    $formatted1 = formatFlightNumberOneSpace($cleanFlightNumber);
    $debugInfo['attempt1_formatted'] = $formatted1;
    $flightNumberEncoded1 = str_replace(' ', '%20', $formatted1);
    $apiUrl1 = "https://rest.api.hamburg-airport.de/v2/flights/{$type}?from={$from}&to={$to}&flightNumber={$flightNumberEncoded1}";
    $debugInfo['attempt1_url'] = $apiUrl1;
    
    logToFile("Erster Versuch mit einem Leerzeichen", [
        'formattedFlightNumber' => $formatted1,
        'url' => $apiUrl1
    ]);
    
    $result1 = performRequest($apiUrl1, $apiKey);
    $debugInfo['attempt1'] = [
        'httpCode'  => $result1['httpCode'],
        'curlError' => $result1['curlError'],
        'response'  => substr($result1['response'], 0, 500) // Gekürzt auf 500 Zeichen für bessere Übersicht
    ];
    
    // Funktion zum Extrahieren von Flugdaten: Prüft, ob in data.flights, data.data oder numerischen Keys vorhanden.
    function extractFlightData($data) {
        if (is_array($data)) {
            if (isset($data['flights']) && is_array($data['flights']) && count($data['flights']) > 0) {
                return $data['flights'][0];
            } elseif (isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
                return $data['data'][0];
            } else {
                $numericKeys = array_filter(array_keys($data), function($k){ return is_numeric($k); });
                if (!empty($numericKeys)) {
                    return $data[reset($numericKeys)];
                }
            }
        }
        return null;
    }
    
    $flightData = extractFlightData($result1['data']);
    
    if ($flightData === null || (is_array($flightData) && count($flightData) === 0)) {
        logToFile("Keine Daten im ersten Versuch gefunden, versuche mit zwei Leerzeichen");
        
        // Versuch 2: Formatierung mit zwei Leerzeichen
        $formatted2 = formatFlightNumberTwoSpaces($cleanFlightNumber);
        $debugInfo['attempt2_formatted'] = $formatted2;
        $flightNumberEncoded2 = str_replace(' ', '%20', $formatted2);
        $apiUrl2 = "https://rest.api.hamburg-airport.de/v2/flights/{$type}?from={$from}&to={$to}&flightNumber={$flightNumberEncoded2}";
        $debugInfo['attempt2_url'] = $apiUrl2;
        
        logToFile("Zweiter Versuch mit zwei Leerzeichen", [
            'formattedFlightNumber' => $formatted2,
            'url' => $apiUrl2
        ]);
        
        $result2 = performRequest($apiUrl2, $apiKey);
        $debugInfo['attempt2'] = [
            'httpCode'  => $result2['httpCode'],
            'curlError' => $result2['curlError'],
            'response'  => substr($result2['response'], 0, 500) // Gekürzt auf 500 Zeichen
        ];
        
        $flightData = extractFlightData($result2['data']);
        
        if ($flightData === null || (is_array($flightData) && count($flightData) === 0)) {
            // Beide Versuche liefern keine Daten
            $errorResponse = [
                'error'   => true,
                'message' => "Keine Flugdaten für Flug {$rawFlightNumber} gefunden.",
                'debug'   => $debugInfo
            ];
            
            logToFile("Keine Flugdaten gefunden", [
                'flightNumber' => $rawFlightNumber,
                'bothAttemptsFailed' => true
            ]);
            
            echo json_encode($errorResponse);
            exit;
        } else {
            $finalData = $result2['data'];
            $finalDebug = $debugInfo;
            
            logToFile("Flugdaten im zweiten Versuch gefunden", [
                'flightNumber' => $rawFlightNumber,
                'format' => 'Zwei Leerzeichen'
            ]);
        }
    } else {
        $finalData = $result1['data'];
        $finalDebug = $debugInfo;
        
        logToFile("Flugdaten im ersten Versuch gefunden", [
            'flightNumber' => $rawFlightNumber,
            'format' => 'Ein Leerzeichen'
        ]);
    }
    
    if (is_array($finalData)) {
        $finalData['debug'] = $finalDebug;
        $jsonResponse = json_encode($finalData);
        
        logToFile("Erfolgreiche Antwort generiert", [
            'flightNumber' => $rawFlightNumber,
            'responseSize' => strlen($jsonResponse)
        ]);
        
        echo $jsonResponse;
    } else {
        $errorResponse = [
            'error'   => true,
            'message' => "Unerwartetes Datenformat",
            'debug'   => $debugInfo
        ];
        
        logToFile("Fehler: Unerwartetes Datenformat", [
            'flightNumber' => $rawFlightNumber
        ]);
        
        echo json_encode($errorResponse);
    }
} else {
    // Kein flightNumber-Parameter: Anfrage ohne diesen Parameter
    $apiUrl = "https://rest.api.hamburg-airport.de/v2/flights/{$type}?from={$from}&to={$to}";
    $debugInfo['requestUrl'] = $apiUrl;
    
    logToFile("Allgemeine Flugdatenabfrage ohne spezifische Flugnummer", [
        'type' => $type,
        'from' => $from,
        'to' => $to,
        'url' => $apiUrl
    ]);
    
    $result = performRequest($apiUrl, $apiKey);
    $debugInfo['result'] = [
        'httpCode'  => $result['httpCode'],
        'curlError' => $result['curlError'],
        'response'  => substr($result['response'], 0, 500) // Gekürzt auf 500 Zeichen
    ];
    
    $data = $result['data'];
    if (!$data) {
        $errorResponse = [
            'error'   => true,
            'message' => "Ungültige JSON-Antwort",
            'debug'   => array_merge($debugInfo, ['response' => $result['response']])
        ];
        
        logToFile("Fehler: Ungültige JSON-Antwort", [
            'httpCode' => $result['httpCode'],
            'error' => $result['curlError'] ?: 'Keine cURL-Fehler'
        ]);
        
        echo json_encode($errorResponse);
        exit;
    }
    
    $data['debug'] = $debugInfo;
    $jsonResponse = json_encode($data);
    
    logToFile("Erfolgreiche allgemeine Flugdatenabfrage", [
        'type' => $type,
        'from' => $from,
        'to' => $to,
        'responseSize' => strlen($jsonResponse),
        'flightCount' => isset($data['flights']) ? count($data['flights']) : 'unbekannt'
    ]);
    
    echo $jsonResponse;
}