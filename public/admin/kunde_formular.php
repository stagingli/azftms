<?php
// Session wird bereits in config.php gestartet
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/permissions.php';


// Sicherstellen, dass ein CSRF-Token existiert
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Falls nicht im Modal, dann Header laden
$isModal = isset($_GET['modal']) && $_GET['modal'] == 1;
if (!$isModal) {
    require __DIR__ . '/../assets/header.php';
}

// Falls Fehler in der Session stehen, diese anzeigen und anschließend löschen
if (isset($_SESSION['error'])) {
    $errorMsg = $_SESSION['error'];
    unset($_SESSION['error']);
} else {
    $errorMsg = '';
}

// Standardwerte für einen neuen Kunden
$kunde = [
    'id'              => '',
    'kundennummer'    => '',
    'vorname'         => '',
    'nachname'        => '',
    'strasse'         => '',
    'hausnummer'      => '',
    'plz'             => '',
    'ort'             => '',
    'kundentyp'       => 'privat',
    'firmenname'      => '',
    'firmenanschrift' => '',
    'ansprechpartner' => '',
    'bemerkung'       => '',
    'telefon'         => '',
    'mobil'           => '',
    'email'           => ''
];

$bearbeiten = false;
// Kunden aus Datenbank holen
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $bearbeiten = true;
    try {
        $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM kunden WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $kunde = $result;
        } else {
            echo "<p class='alert alert-danger'>❌ Kunde mit ID nicht gefunden.</p>";
            $bearbeiten = false;
        }
    } catch (PDOException $e) {
        echo "<p class='alert alert-danger'>❌ DB-Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} elseif (isset($_GET['kundennummer']) && !empty($_GET['kundennummer'])) {
    $bearbeiten = true;
    try {
        $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM kunden WHERE kundennummer = ?");
        $stmt->execute([$_GET['kundennummer']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $kunde = $result;
        } else {
            echo "<p class='alert alert-danger'>❌ Kunde mit Kundennummer nicht gefunden.</p>";
            $bearbeiten = false;
        }
    } catch (PDOException $e) {
        echo "<p class='alert alert-danger'>❌ DB-Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Neue Kundennummer vorschlagen
$vorgeschlagene_kundennummer = '';
if (!$bearbeiten) {
    $jahr = date("Y");
    $stmt = $GLOBALS['pdo']->prepare("SELECT kundennummer FROM kunden WHERE kundennummer LIKE ? ORDER BY kundennummer DESC LIMIT 1");
    $stmt->execute([$jahr.'%']);
    $letzteNummer = $stmt->fetchColumn();
    $neueNummer = $letzteNummer ? (intval(substr($letzteNummer, 4)) + 1) : 1;
    $vorgeschlagene_kundennummer = $jahr . str_pad($neueNummer, 4, "0", STR_PAD_LEFT);
}
?>

<div class="container mt-3">
    <?php
    // Fehlerausgabe direkt im Formular anzeigen
    if (!empty($errorMsg)):
    ?>
    <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars($errorMsg); ?>
    </div>
    <?php endif; ?>

    <?php if (!$isModal): ?>
    <div class="card shadow-lg border-0">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0 py-2">
                <i class="fas fa-user me-2"></i> <?= $bearbeiten ? "Kunde bearbeiten" : "Neuen Kunden anlegen" ?>
            </h2>
        </div>
        <div class="card-body">
    <?php endif; ?>

    <form id="kundenForm" action="kunde_speichern.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars($kunde['id'] ?? ''); ?>">
        <?php if ($isModal): ?><input type="hidden" name="modal" value="1"><?php endif; ?>

        <div class="row mt-3">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="kundennummer" class="form-label">Kundennummer</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="kundennummer" name="kundennummer" 
                            value="<?= $bearbeiten ? htmlspecialchars($kunde['kundennummer'] ?? '') : htmlspecialchars($vorgeschlagene_kundennummer); ?>">
                        <button class="btn btn-outline-secondary" type="button" id="genKundennummer" data-vorschlag="<?= htmlspecialchars($vorgeschlagene_kundennummer); ?>">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="privat-fields">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="vorname" class="form-label fw-bold">Vorname*</label>
                        <input type="text" class="form-control" id="vorname" name="vorname" value="<?= htmlspecialchars($kunde['vorname'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="nachname" class="form-label fw-bold">Nachname*</label>
                        <input type="text" class="form-control" id="nachname" name="nachname" value="<?= htmlspecialchars($kunde['nachname'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="kundentyp" class="form-label">Kundentyp</label>
            <select class="form-select" id="kundentyp" name="kundentyp">
                <option value="privat" <?= ($kunde['kundentyp'] ?? '') == 'privat' ? 'selected' : ''; ?>>Privatkunde</option>
                <option value="firma" <?= ($kunde['kundentyp'] ?? '') == 'firma' ? 'selected' : ''; ?>>Firmenkunde</option>
            </select>
        </div>

        <div id="firma-fields" style="display: <?= ($kunde['kundentyp'] ?? '') == 'firma' ? 'block' : 'none'; ?>;">
            <div class="mb-3">
                <label for="firmenname" class="form-label">Firmenname</label>
                <input type="text" class="form-control" id="firmenname" name="firmenname" value="<?= htmlspecialchars($kunde['firmenname'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label for="firmenanschrift" class="form-label">Firmenanschrift</label>
                <textarea class="form-control" id="firmenanschrift" name="firmenanschrift" rows="2"><?= htmlspecialchars($kunde['firmenanschrift'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="ansprechpartner" class="form-label">Ansprechpartner</label>
                <input type="text" class="form-control" id="ansprechpartner" name="ansprechpartner" value="<?= htmlspecialchars($kunde['ansprechpartner'] ?? ''); ?>">
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="mb-3">
                    <label for="strasse" class="form-label">Straße</label>
                    <input type="text" class="form-control" id="strasse" name="strasse" value="<?= htmlspecialchars($kunde['strasse'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="hausnummer" class="form-label">Hausnummer</label>
                    <input type="text" class="form-control" id="hausnummer" name="hausnummer" value="<?= htmlspecialchars($kunde['hausnummer'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="plz" class="form-label">PLZ</label>
                    <input type="text" class="form-control" id="plz" name="plz" value="<?= htmlspecialchars($kunde['plz'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-8">
                <div class="mb-3">
                    <label for="ort" class="form-label">Ort</label>
                    <input type="text" class="form-control" id="ort" name="ort" value="<?= htmlspecialchars($kunde['ort'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="telefon" class="form-label">Telefon</label>
                    <input type="text" class="form-control" id="telefon" name="telefon" value="<?= htmlspecialchars($kunde['telefon'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="mobil" class="form-label">Mobil</label>
                    <input type="text" class="form-control" id="mobil" name="mobil" value="<?= htmlspecialchars($kunde['mobil'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="email" class="form-label">E-Mail</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($kunde['email'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="bemerkung" class="form-label">Bemerkung</label>
            <textarea class="form-control" id="bemerkung" name="bemerkung" rows="3"><?= htmlspecialchars($kunde['bemerkung'] ?? ''); ?></textarea>
        </div>

        <?php if (!$bearbeiten && !$isModal): ?>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="fahrt_erstellen" name="fahrt_erstellen" value="1">
            <label class="form-check-label" for="fahrt_erstellen">Nach dem Speichern gleich eine Fahrt für diesen Kunden anlegen</label>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between mt-4">
            <?php if (!$isModal): ?>
                <div>
                    <a href="kundenverwaltung.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Zurück zur Übersicht
                    </a>
                    <?php if ($bearbeiten): ?>
                    <a href="fahrt_formular.php?kunde_id=<?= htmlspecialchars($kunde['id']); ?>" class="btn btn-info ms-2">
                        <i class="fas fa-car me-2"></i>Neue Fahrt für diesen Kunden
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save me-2"></i><?= $bearbeiten ? "Änderungen speichern" : "Kunde anlegen" ?>
            </button>
        </div>
    </form>

    <?php if (!$isModal): ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    initKundenFormular();
});

// Separate Funktion, die sowohl vom DOMContentLoaded-Event als auch vom Modal-Lader aufgerufen werden kann
function initKundenFormular() {
    const formContainer = document.getElementById('kundenForm').closest('form');
    const kundentypSelect = formContainer.querySelector("#kundentyp");
    const privatFields = formContainer.querySelector("#privat-fields");
    const firmaFields = formContainer.querySelector("#firma-fields");
    const vornameField = formContainer.querySelector("#vorname");
    const nachnameField = formContainer.querySelector("#nachname");
    const ansprechpartnerField = formContainer.querySelector("#ansprechpartner");
    const genKundennummerBtn = formContainer.querySelector("#genKundennummer");

    if (kundentypSelect) {
        kundentypSelect.addEventListener("change", function() {
            if (this.value === "firma") {
                // Zeige Firmenfelder und verstecke Privatfelder
                if (firmaFields) firmaFields.style.display = "block";
                if (privatFields) privatFields.style.display = "none";

                // Entferne "required" von Vorname und Nachname
                if (vornameField) vornameField.removeAttribute("required");
                if (nachnameField) nachnameField.removeAttribute("required");

                // Übertrage Vorname und Nachname in das Ansprechpartner-Feld
                if (ansprechpartnerField && vornameField && nachnameField) {
                    ansprechpartnerField.value = `${vornameField.value} ${nachnameField.value}`.trim();
                    vornameField.value = "";
                    nachnameField.value = "";
                }
            } else {
                // Zeige Privatfelder und verstecke Firmenfelder
                if (firmaFields) firmaFields.style.display = "none";
                if (privatFields) privatFields.style.display = "block";

                // Füge "required" zu Vorname und Nachname hinzu
                if (vornameField) vornameField.setAttribute("required", "required");
                if (nachnameField) nachnameField.setAttribute("required", "required");

                // Übertrage Ansprechpartner zurück in Vorname und Nachname
                if (ansprechpartnerField && vornameField && nachnameField) {
                    const ansprechpartnerParts = ansprechpartnerField.value.split(" ");
                    const vorname = ansprechpartnerParts.shift() || "";
                    const nachname = ansprechpartnerParts.join(" ") || "";
                    
                    vornameField.value = vorname;
                    nachnameField.value = nachname;
                    ansprechpartnerField.value = "";
                }
            }
        });
    }

    // Generiere eine neue Kundennummer
    if (genKundennummerBtn) {
        genKundennummerBtn.addEventListener("click", function() {
            let vorschlag = this.getAttribute('data-vorschlag');
            formContainer.querySelector("#kundennummer").value = vorschlag;
        });
    }
}
</script>

<?php if ($isModal): ?>
<script>
// AJAX-Formular-Übermittlung im Modal-Kontext
$(document).ready(function() {
    $("#kundenForm").on("submit", function(e) {
        e.preventDefault();
        
        $.ajax({
            url: "kunde_speichern.php",
            method: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(response) {
                if (response.error) {
                    // Fehler anzeigen
                    alert(response.message);
                } else {
                    // Erfolgsmeldung auslösen
                    $(document).trigger("kundenFormular:gespeichert", [response.kundeData]);
                    // Modal schließen
                    $('#kundenModal').modal('hide');
                }
            },
            error: function(xhr, status, error) {
                alert("Fehler beim Speichern des Kunden: " + error);
            }
        });
    });
});
</script>
<?php endif; ?>

<?php if (!$isModal) include __DIR__ . '/../assets/footer.php'; ?>
