<?php
header("Content-Type: application/json; charset=UTF-8");

require __DIR__ . '/../../app/config.php';

// Zugriffskontrolle: Nur Admins dürfen Änderungen durchführen
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id']) || !in_array('admin', $_SESSION['user']['rollen'])) {
    echo json_encode([
        "status"  => "error",
        "message" => "Keine Berechtigung (Session fehlt oder nicht Admin)"
    ]);
    exit();
}

// Nur POST-Anfragen erlauben
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "status"  => "error",
        "message" => "❌ Nur POST-Anfragen erlaubt"
    ]);
    exit();
}

// CSRF-Token prüfen
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode([
        "status"  => "error",
        "message" => "❌ CSRF-Token ungültig"
    ]);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'update_user':
            if (!isset($_POST['user_id'], $_POST['rollen'], $_POST['stundenlohn'])) {
                throw new Exception("❌ Fehlende Daten (user_id, rollen, stundenlohn).");
            }

            $userId = intval($_POST['user_id']);
            $newRoles = json_decode($_POST['rollen'], true);
            $newStundenlohn = floatval($_POST['stundenlohn']);
            $emailPassword = trim($_POST['email_password'] ?? '');

            if (!is_array($newRoles)) {
                throw new Exception("❌ Ungültiges Rollen-Format.");
            }

            // Geschützte Nutzer dürfen nicht verändert werden
            if (in_array($userId, [1, 2])) {
                throw new Exception("❌ Die Rolle dieses Nutzers darf nicht verändert werden.");
            }

            // Prüfen, ob Admin-Rolle enthalten bleiben muss
            $stmt = $pdo->prepare("SELECT rolle_id FROM nutzer_rolle WHERE nutzer_id = ?");
            $stmt->execute([$userId]);
            $currentRoles = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (in_array(1, $currentRoles) && !in_array(1, $newRoles)) {
                throw new Exception("❌ Die Admin-Rolle kann nicht entfernt werden.");
            }

            // Alte Rollen löschen
            $stmt = $pdo->prepare("DELETE FROM nutzer_rolle WHERE nutzer_id = ?");
            $stmt->execute([$userId]);

            // Neue Rollen setzen
            foreach ($newRoles as $roleId) {
                $stmt = $pdo->prepare("INSERT INTO nutzer_rolle (nutzer_id, rolle_id) VALUES (?, ?)");
                $stmt->execute([$userId, intval($roleId)]);
            }

            // Stundenlohn validieren
            if ($newStundenlohn < 12.82) {
                throw new Exception("❌ Der Stundenlohn muss mindestens 12,82€ betragen.");
            }

            // Update der Nutzerdaten vorbereiten
            $sql = "UPDATE nutzer SET stundenlohn = :stundenlohn";
            $params = [
                ':stundenlohn' => $newStundenlohn,
                ':id'          => $userId,
            ];

            // E-Mail-Passwort setzen, wenn eines eingegeben wurde
            $passwordUpdated = false;
            if (!empty($emailPassword)) {
                // Passwort-Hash erstellen
                $passwordHash = password_hash($emailPassword, PASSWORD_DEFAULT);
                
                // Ablaufdatum festlegen (31.12.2026)
                $ablaufDate = '2026-12-31 23:59:59';
                
                // SQL-Abfrage erweitern
                $sql .= ", email_einmalpasswort = :password_hash, email_passwort_verbleibend = 5, 
                       email_passwort_erstellt = NOW(), email_passwort_ablauf = :ablauf_date";
                $params[':password_hash'] = $passwordHash;
                $params[':ablauf_date'] = $ablaufDate;
                
                // Klartext-Passwort in separater Tabelle speichern
                // Zuerst alte Einträge für diesen Nutzer löschen
                $stmtDelete = $pdo->prepare("DELETE FROM email_passwords WHERE user_id = ?");
                $stmtDelete->execute([$userId]);
                
                // Neuen Eintrag anlegen
                $stmtInsert = $pdo->prepare("INSERT INTO email_passwords (user_id, password_hash, password_plaintext) 
                                           VALUES (?, ?, ?)");
                $stmtInsert->execute([$userId, $passwordHash, $emailPassword]);
                
                $passwordUpdated = true;
            }

            // SQL-Abfrage abschließen und ausführen
            $sql .= " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode([
                "status"  => "success",
                "message" => "✅ Nutzerdaten aktualisiert!",
                "email_password_status" => $passwordUpdated
            ]);
            exit();

        case 'delete_user':
            if (!isset($_POST['user_id'])) {
                throw new Exception("❌ Fehlende user_id.");
            }

            $userId = intval($_POST['user_id']);

            // Geschützte Nutzer dürfen nicht gelöscht werden
            if (in_array($userId, [1, 5])) {
                throw new Exception("❌ Dieser Nutzer darf nicht gelöscht werden.");
            }

            // E-Mail-Passwort-Einträge löschen
            $stmt = $pdo->prepare("DELETE FROM email_passwords WHERE user_id = ?");
            $stmt->execute([$userId]);

            // Nutzerrollen löschen
            $stmt = $pdo->prepare("DELETE FROM nutzer_rolle WHERE nutzer_id = ?");
            $stmt->execute([$userId]);

            // Nutzer löschen
            $stmt = $pdo->prepare("DELETE FROM nutzer WHERE id = ?");
            $stmt->execute([$userId]);

            echo json_encode([
                "status"  => "success",
                "message" => "✅ Nutzer wurde gelöscht."
            ]);
            exit();

        default:
            throw new Exception("❌ Unbekannte Aktion: " . htmlspecialchars($action, ENT_QUOTES, 'UTF-8'));
    }
} catch (Exception $e) {
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
    exit();
}

echo json_encode([
    "status"  => "error",
    "message" => "❌ Keine gültigen POST-Daten empfangen"
]);
?>