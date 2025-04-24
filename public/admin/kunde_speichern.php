<?php
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/../../app/config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Nur POST-Anfragen zulassen
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("❌ Ungültige Anfrage.");
}

// CSRF-Token überprüfen
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("❌ CSRF-Token ungültig oder fehlt!");
}

// Eingaben einlesen und validieren
// Zusätzlich: interne ID (falls vorhanden) aus dem Formular auslesen
$id              = trim($_POST['id'] ?? "");
$kundennummer    = trim($_POST['kundennummer'] ?? "");
$vorname         = htmlspecialchars(trim($_POST['vorname'] ?? ""), ENT_QUOTES, 'UTF-8');
$nachname        = htmlspecialchars(trim($_POST['nachname'] ?? ""), ENT_QUOTES, 'UTF-8');
$strasse         = htmlspecialchars(trim($_POST['strasse'] ?? ""), ENT_QUOTES, 'UTF-8');
$hausnummer      = htmlspecialchars(trim($_POST['hausnummer'] ?? ""), ENT_QUOTES, 'UTF-8');
$plz             = htmlspecialchars(trim($_POST['plz'] ?? ""), ENT_QUOTES, 'UTF-8');
$ort             = htmlspecialchars(trim($_POST['ort'] ?? ""), ENT_QUOTES, 'UTF-8');
$kundentyp       = htmlspecialchars(trim($_POST['kundentyp'] ?? "privat"), ENT_QUOTES, 'UTF-8');
$bemerkung       = htmlspecialchars(trim($_POST['bemerkung'] ?? ""), ENT_QUOTES, 'UTF-8');
$firmenname      = htmlspecialchars(trim($_POST['firmenname'] ?? ""), ENT_QUOTES, 'UTF-8');
$firmenanschrift = htmlspecialchars(trim($_POST['firmenanschrift'] ?? ""), ENT_QUOTES, 'UTF-8');
$telefon         = htmlspecialchars(trim($_POST['telefon'] ?? ""), ENT_QUOTES, 'UTF-8');
$mobil           = htmlspecialchars(trim($_POST['mobil'] ?? ""), ENT_QUOTES, 'UTF-8');
$email           = htmlspecialchars(trim($_POST['email'] ?? ""), ENT_QUOTES, 'UTF-8');
$isModal         = isset($_POST['modal']) && $_POST['modal'] == '1';
$ansprechpartner = $_POST['ansprechpartner'] ?? null;

// Sicherstellen, dass $kundentyp entweder 'privat' oder 'firma' ist
if ($kundentyp !== 'privat' && $kundentyp !== 'firma') {
    $kundentyp = 'privat';
}

// Vorname und Nachname müssen nur für Privatkunden vorhanden sein
if ($kundentyp === 'privat' && (!$vorname || !$nachname)) {
    $errorMsg = "❌ Vorname und Nachname sind erforderlich.";
    if ($isModal) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => true, 'message' => $errorMsg]);
    } else {
        $_SESSION['error'] = $errorMsg;
        header("Location: kunde_formular.php" . ($id ? "?id=" . urlencode($id) : ""));
    }
    exit();
}

// Dublettenprüfung: Gleicher Vor- und Nachname darf nicht mehrfach existieren
// (den eigenen Datensatz im Update-Fall anhand der ID ausnehmen)
if ($id) {
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT id FROM kunden 
        WHERE vorname = ? 
          AND nachname = ? 
          AND deleted_at IS NULL 
          AND id != ?
    ");
    $stmt->execute([$vorname, $nachname, $id]);
} else {
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT id FROM kunden 
        WHERE vorname = ? 
          AND nachname = ? 
          AND deleted_at IS NULL
    ");
    $stmt->execute([$vorname, $nachname]);
}
$existierenderKunde = $stmt->fetchColumn();
if ($existierenderKunde) {
    $errorMsg = "❌ Kunde mit diesem Namen existiert bereits!";
    if ($isModal) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => true, 'message' => $errorMsg]);
    } else {
        $_SESSION['error'] = $errorMsg;
        header("Location: kunde_formular.php" . ($id ? "?id=" . urlencode($id) : ""));
    }
    exit();
}

// Prüfen, ob eine Kundennummer angegeben wurde und ob sie ggf. bereits existiert
// (im Update-Fall den eigenen Datensatz ausnehmen)
if (!empty($kundennummer)) {
    // Einfache Formatprüfung: entweder exakt 8 Ziffern (JJJJNNNN) oder "TESTK_" gefolgt von 3 Ziffern
    if (!preg_match('/^(?:\d{8}|TESTK_\d{3})$/', $kundennummer)) {
        $errorMsg = "❌ Die Kundennummer muss das Format JJJJNNNN haben (z.B. " . date('Y') . "0001) oder TESTK_### sein.";
        if ($isModal) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => true, 'message' => $errorMsg]);
        } else {
            $_SESSION['error'] = $errorMsg;
            header("Location: kunde_formular.php" . ($id ? "?id=" . urlencode($id) : ""));
        }
        exit();
    }
}

// Eindeutigkeitsprüfung der Kundennummer
if ($id) {
    $stmt = $GLOBALS['pdo']->prepare("SELECT COUNT(*) FROM kunden WHERE kundennummer = ? AND id != ?");
    $stmt->execute([$kundennummer, $id]);
} else {
    $stmt = $GLOBALS['pdo']->prepare("SELECT COUNT(*) FROM kunden WHERE kundennummer = ?");
    $stmt->execute([$kundennummer]);
}
$nummerExistiert = $stmt->fetchColumn();
if ($nummerExistiert > 0) {
    $errorMsg = "❌ Die angegebene Kundennummer existiert bereits. Bitte wählen Sie eine andere.";
    if ($isModal) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => true, 'message' => $errorMsg]);
    } else {
        $_SESSION['error'] = $errorMsg;
        header("Location: kunde_formular.php" . ($id ? "?id=" . urlencode($id) : ""));
    }
    exit();
}

// Falls keine Kundennummer vorhanden ist, generieren wir eine:
if (empty($kundennummer)) {
    $jahr = date("Y");
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT kundennummer 
        FROM kunden 
        WHERE kundennummer LIKE ? 
        ORDER BY kundennummer DESC 
        LIMIT 1
    ");
    $likePattern = "{$jahr}%";
    $stmt->execute([$likePattern]);
    $letzteNummer = $stmt->fetchColumn();
    $neueNummer = $letzteNummer ? (intval(substr($letzteNummer, 4)) + 1) : 1;
    $kundennummer = $jahr . str_pad($neueNummer, 4, "0", STR_PAD_LEFT);
}

// Log-Eintrag zur Kundennummer
if ($id) {
    writeLog("Kunde ID {$id} wird aktualisiert mit Kundennummer: {$kundennummer}", 'INFO');
} else {
    writeLog("Neuer Kunde wird angelegt mit Kundennummer: {$kundennummer}", 'INFO');
}

// Entscheidung: Neuanlage oder Update anhand der internen ID
if ($id) {
    // Update eines bestehenden Datensatzes anhand der ID
    $stmt = $GLOBALS['pdo']->prepare("
        UPDATE kunden SET
            kundennummer = ?,
            vorname = ?,
            nachname = ?,
            strasse = ?,
            hausnummer = ?,
            plz = ?,
            ort = ?,
            kundentyp = ?,
            firmenname = ?,
            firmenanschrift = ?,
            ansprechpartner = ?,
            bemerkung = ?,
            telefon = ?,
            mobil = ?,
            email = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([
        $kundennummer,
        $vorname,
        $nachname,
        $strasse,
        $hausnummer,
        $plz,
        $ort,
        $kundentyp,
        $firmenname,
        $firmenanschrift,
        $ansprechpartner,
        $bemerkung,
        $telefon,
        $mobil,
        $email,
        $id
    ]);
    
    $successMsg = "✅ Kundendaten erfolgreich aktualisiert.";
} else {
    // Neuer Datensatz
    $stmt = $GLOBALS['pdo']->prepare("
        INSERT INTO kunden 
        (kundennummer, vorname, nachname, strasse, hausnummer, plz, ort, kundentyp, firmenname, firmenanschrift, ansprechpartner, bemerkung, telefon, mobil, email) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $kundennummer,
        $vorname,
        $nachname,
        $strasse,
        $hausnummer,
        $plz,
        $ort,
        $kundentyp,
        $firmenname,
        $firmenanschrift,
        $ansprechpartner,
        $bemerkung,
        $telefon,
        $mobil,
        $email
    ]);
    $id = $GLOBALS['pdo']->lastInsertId();
    
    $successMsg = "✅ Neuer Kunde erfolgreich angelegt.";
}

// Ausgabe: Entweder JSON (bei Modal) oder Weiterleitung
if ($isModal) {
    // Den gespeicherten Kunden nochmal abfragen, um alle Daten zu haben
    $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM kunden WHERE id = ?");
    $stmt->execute([$id]);
    $kundeData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ausgabe des JSON-Ergebnisses für XHR-Anfragen
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => $successMsg,
            'kundeData' => $kundeData
        ]);
    } else {
        // Alternativ für reguläre Anfragen: Event-Script hinzufügen
        echo '<script>
            if (window.parent && window.parent.document) {
                const event = new CustomEvent("kundenFormular:gespeichert", { 
                    detail: ' . json_encode($kundeData) . ' 
                });
                window.parent.document.dispatchEvent(event);
                
                // Zusätzlich eigene Seite schließen
                const eventData = ' . json_encode($kundeData) . ';
                window.parent.$(document).trigger("kundenFormular:gespeichert", [eventData]);
            }
        </script>';
    }
} else {
    $_SESSION['success'] = $successMsg;
    header("Location: kundenverwaltung.php");
}

// Ganz am Ende des Skripts, vor dem exit():
if (!$isModal && !$id) {
    // Nur bei neuen Kunden, nicht im Modal-Modus
    $neueId = $GLOBALS['pdo']->lastInsertId();
    $redirectUrl = "kundenverwaltung.php";
    
    // Optionaler Parameter: fahrt_erstellen
    if (isset($_POST['fahrt_erstellen']) && $_POST['fahrt_erstellen'] == '1') {
        $redirectUrl = "fahrt_formular.php?kunde_id=" . $neueId;
    }
    
    $_SESSION['success'] = $successMsg;
    header("Location: $redirectUrl");
    exit();
}
exit();
?>
