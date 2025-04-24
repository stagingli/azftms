<?php
declare(strict_types=1);

require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/permissions.php';
require __DIR__ . '/../assets/header.php';

// In nutzer.php oben in der Datei hinzufügen
if (isset($_GET['refresh_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Nutzer aus der Datenbank abrufen
$stmt = $pdo->prepare("
    SELECT 
        n.id, 
        n.name, 
        n.email,
        n.stundenlohn, 
        n.email_einmalpasswort,
        n.email_passwort_verbleibend,
        n.email_passwort_erstellt,
        n.email_passwort_ablauf,
        GROUP_CONCAT(r.id SEPARATOR ',') AS rollen_ids, 
        GROUP_CONCAT(r.name SEPARATOR ', ') AS rollen
    FROM nutzer n
    LEFT JOIN nutzer_rolle nr ON n.id = nr.nutzer_id
    LEFT JOIN rollen r ON nr.rolle_id = r.id
    GROUP BY n.id
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Alle verfügbaren Rollen abrufen
$stmt = $pdo->query("SELECT id, name FROM rollen");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Definieren des Ablaufdatums (31.12.2026)
$defaultAblaufDate = '2026-12-31 23:59:59';
?>

<div class="container mt-5">
    <h1 class="fw-bold">Nutzerverwaltung</h1>
    <p>Verwalten Sie Ihre Nutzer. Die bestehende User-E-Mail und das Login-Passwort bleiben unberührt.</p>
    
    <div id="debug-output" class="alert alert-danger d-none"></div>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>User E-Mail</th>
                    <th>Rollen</th>
                    <th>Stundenlohn (€)</th>
                    <th>E-Mail-Passwort</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr id="user-row-<?= htmlspecialchars((string)$user['id']) ?>">
                    <td><?= htmlspecialchars((string)$user['id']) ?></td>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                        <div class="rollen-container">
                            <?php foreach ($roles as $role): ?>
                                <div class="form-check">
                                    <input class="form-check-input nutzer-rollen" type="checkbox" 
                                           data-id="<?= htmlspecialchars((string)$user['id']) ?>" 
                                           value="<?= htmlspecialchars((string)$role['id']) ?>"
                                           <?= strpos((string)($user['rollen_ids'] ?? ''), (string)$role['id']) !== false ? 'checked' : '' ?>
                                           <?= in_array((int)$user['id'], [1, 5], true) && $role['name'] === 'admin' ? 'disabled' : '' ?>>
                                    <label class="form-check-label"><?= htmlspecialchars(ucfirst($role['name'])) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td>
                        <input type="number" class="form-control stundenlohn" data-id="<?= htmlspecialchars((string)$user['id']) ?>" 
                               value="<?= number_format((float)$user['stundenlohn'], 2, '.', '') ?>" min="12.82" step="0.01"
                               <?= in_array((int)$user['id'], [1, 5], true) ? 'disabled' : '' ?>>
                    </td>
                    <td>
                        <div class="email-password-container">
                            <?php if (!empty($user['email_einmalpasswort'])): ?>
                                <div class="badge bg-success mb-2">Passwort gesetzt</div>
                                <div class="small">
                                    Verbleibende Anzeigen: <?= htmlspecialchars((string)$user['email_passwort_verbleibend']) ?><br>
                                    Erstellt: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($user['email_passwort_erstellt']))) ?><br>
                                    Ablauf: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($user['email_passwort_ablauf'] ?? $defaultAblaufDate))) ?>
                                </div>
                            <?php else: ?>
                                <div class="badge bg-warning mb-2">Kein Passwort</div>
                            <?php endif; ?>
                            <div class="mt-2">
                                <input type="text" class="form-control form-control-sm email-password" 
                                       data-id="<?= htmlspecialchars((string)$user['id']) ?>" 
                                       placeholder="Neues E-Mail-Passwort">
                            </div>
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-success btn-sm speichern-nutzer" data-id="<?= htmlspecialchars((string)$user['id']) ?>">Speichern</button>
                        <?php if (!in_array((int)$user['id'], [1, 5], true)): ?>
                            <button class="btn btn-outline-danger btn-sm delete-user" data-id="<?= htmlspecialchars((string)$user['id']) ?>">Löschen</button>
                        <?php else: ?>
                            <small class="text-muted">Gesichert</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="text-center">Keine Nutzer gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Funktion zur Anzeige von Fehlermeldungen
function showError(message) {
    const debugDiv = document.getElementById("debug-output");
    debugDiv.textContent = message;
    debugDiv.classList.remove("d-none");
    console.error("❌ Fehler:", message);
}

// Speichern der Nutzer-Daten inkl. Rollen und Stundenlohn
document.querySelectorAll(".speichern-nutzer").forEach(button => {
    button.addEventListener("click", function () {
        const userId = this.dataset.id;
        const selectedRoles = Array.from(document.querySelectorAll(`.nutzer-rollen[data-id='${userId}']:checked`))
                                   .map(checkbox => checkbox.value);
        const stundenlohn = document.querySelector(`.stundenlohn[data-id='${userId}']`).value;
        const emailPassword = document.querySelector(`.email-password[data-id='${userId}']`).value;

        const formData = new FormData();
        formData.append("action", "update_user");
        formData.append("user_id", userId);
        formData.append("rollen", JSON.stringify(selectedRoles));
        formData.append("stundenlohn", stundenlohn);
        formData.append("email_password", emailPassword);
        formData.append("csrf_token", "<?= $_SESSION['csrf_token']; ?>");

        fetch("/admin/nutzer_handler.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log("✅ Antwort:", data);
            if (data.status === "success") {
                alert("✅ Änderungen erfolgreich gespeichert!");
                // Passwortfeld leeren nach dem Speichern
                document.querySelector(`.email-password[data-id='${userId}']`).value = '';
                
                // Aktualisiere die Anzeige ohne Neuladen (optional)
                if (data.email_password_status) {
                    const passwordContainer = document.querySelector(`#user-row-${userId} .email-password-container`);
                    passwordContainer.innerHTML = `
                        <div class="badge bg-success mb-2">Passwort gesetzt</div>
                        <div class="small">
                            Verbleibende Anzeigen: 5<br>
                            Erstellt: ${new Date().toLocaleDateString('de-DE')} ${new Date().toLocaleTimeString('de-DE')}<br>
                            Ablauf: 31.12.2026 23:59
                        </div>
                        <div class="mt-2">
                            <input type="text" class="form-control form-control-sm email-password" 
                                   data-id="${userId}" 
                                   placeholder="Neues E-Mail-Passwort">
                        </div>
                    `;
                }
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error("❌ Netzwerkfehler:", error);
            showError("Netzwerkfehler. Bitte später erneut versuchen.");
        });
    });
});

// Löschen eines Nutzers
document.querySelectorAll(".delete-user").forEach(button => {
    button.addEventListener("click", function () {
        const userId = this.dataset.id;
        if (!confirm("Möchten Sie diesen Nutzer wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.")) return;
        
        const formData = new FormData();
        formData.append("action", "delete_user");
        formData.append("user_id", userId);
        formData.append("confirm_delete", "yes");
        formData.append("csrf_token", "<?= $_SESSION['csrf_token']; ?>");

        fetch("/admin/nutzer_handler.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log("✅ Antwort:", data);
            if (data.status === "success") {
                document.getElementById(`user-row-${userId}`).remove();
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error("❌ Netzwerkfehler:", error);
            showError("Netzwerkfehler. Bitte später erneut versuchen.");
        });
    });
});
</script>

<?php include __DIR__ . '/../assets/footer.php'; ?>