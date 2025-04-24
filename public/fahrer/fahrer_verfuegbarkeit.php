<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../assets/header.php';


if (!isset($_SESSION['user']) || !in_array('fahrer', $_SESSION['user']['rollen'])) {
    header("Location: /auth/login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$today = date('Y-m-d');

// Einzelverfügbarkeiten (ALLE anzeigen)
$stmt = $pdo->prepare("SELECT * FROM fahrer_verfuegbarkeit WHERE nutzer_id = :nutzer_id ORDER BY datum_von DESC");
$stmt->execute([':nutzer_id' => $user_id]);
$verfuegbarkeiten = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Musterverfügbarkeiten
$stmt = $pdo->prepare("SELECT * FROM fahrer_verfuegbarkeit_muster WHERE nutzer_id = :nutzer_id AND aktiv = 1 ORDER BY wochentag ASC");
$stmt->execute([':nutzer_id' => $user_id]);
$muster = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container py-4">
    <h2>Meine Verfügbarkeit</h2>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <form method="post" action="fahrer_verfuegbarkeit_speichern.php" class="card p-4 mb-4">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="save_availability">

        <div class="mb-3">
            <label for="typ" class="form-label">Verfügbarkeits-Typ</label>
            <select name="typ" id="typ" class="form-select" required>
                <option value="nicht_verfuegbar">Abwesend (z.B. Urlaub)</option>
                <option value="verfuegbar">Verfügbar (z.B. zyklisch)</option>
            </select>
        </div>

        <div class="row mb-3">
            <div class="col">
                <label for="datum_von" class="form-label">Von</label>
                <input type="date" name="datum_von" id="datum_von" class="form-control" min="<?= $today ?>" required>
            </div>
            <div class="col">
                <label for="datum_bis" class="form-label">Bis</label>
                <input type="date" name="datum_bis" id="datum_bis" class="form-control" min="<?= $today ?>" required>
            </div>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="ganztags" name="ganztags" checked>
            <label class="form-check-label" for="ganztags">Ganztags</label>
        </div>

        <div class="row mb-3" id="zeitContainer" style="display: none;">
            <div class="col">
                <label for="zeit_von" class="form-label">Zeit von</label>
                <input type="time" name="zeit_von" id="zeit_von" class="form-control">
            </div>
            <div class="col">
                <label for="zeit_bis" class="form-label">Zeit bis</label>
                <input type="time" name="zeit_bis" id="zeit_bis" class="form-control">
            </div>
        </div>

        <div class="mb-3">
            <label for="wochentag" class="form-label">Wochentag (optional für zyklisch)</label>
            <select name="wochentag" id="wochentag" class="form-select">
                <option value="">Kein Muster</option>
                <option value="1">Montag</option>
                <option value="2">Dienstag</option>
                <option value="3">Mittwoch</option>
                <option value="4">Donnerstag</option>
                <option value="5">Freitag</option>
                <option value="6">Samstag</option>
                <option value="7">Sonntag</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Eintragen</button>
    </form>

    <h4>Alle Einträge</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Typ</th>
                <th>Von</th>
                <th>Bis</th>
                <th>Ganztags</th>
                <th>Zeitraum</th>
                <th>Status</th>
                <th>Aktion</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($verfuegbarkeiten as $v): ?>
            <tr>
                <td><?= htmlspecialchars($v['typ']) ?></td>
                <td><?= htmlspecialchars($v['datum_von']) ?></td>
                <td><?= htmlspecialchars($v['datum_bis']) ?></td>
                <td><?= $v['ganztags'] ? 'Ja' : 'Nein' ?></td>
                <td><?= !$v['ganztags'] ? substr($v['zeit_von'], 0, 5) . ' - ' . substr($v['zeit_bis'], 0, 5) : '-' ?></td>
                <td><?= $v['typ'] === 'verfuegbar' ? 'Verfügbar' : 'Nicht verfügbar' ?></td>
                <td>
                    <form method="post" action="fahrer_verfuegbarkeit_speichern.php">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="delete_availability">
                        <input type="hidden" name="id" value="<?= $v['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Wirklich löschen?')">Löschen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h4 class="mt-5">Zyklische Verfügbarkeiten</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Wochentag</th>
                <th>Typ</th>
                <th>Ganztags</th>
                <th>Zeitraum</th>
                <th>Aktion</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($muster as $m): ?>
            <tr>
                <td><?= ['Mo','Di','Mi','Do','Fr','Sa','So'][$m['wochentag'] - 1] ?></td>
                <td><?= htmlspecialchars($m['typ']) ?></td>
                <td><?= $m['ganztags'] ? 'Ja' : 'Nein' ?></td>
                <td><?= !$m['ganztags'] ? substr($m['zeit_von'], 0, 5) . ' - ' . substr($m['zeit_bis'], 0, 5) : '-' ?></td>
                <td>
                    <form method="post" action="fahrer_verfuegbarkeit_speichern.php">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="delete_pattern">
                        <input type="hidden" name="pattern_id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Wirklich löschen?')">Löschen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
document.getElementById('ganztags').addEventListener('change', function() {
    const zeitContainer = document.getElementById('zeitContainer');
    zeitContainer.style.display = this.checked ? 'none' : 'flex';
});
</script>
<?php require_once __DIR__ . '/../assets/footer.php'; ?>
