<?php
require __DIR__ . '/../../../app/config.php';
require __DIR__ . '/../../../app/permissions.php';

// Beispiel-CSV herunterladen
if (isset($_GET['beispiel']) && $_GET['beispiel'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=beispiel_kunden.csv');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($output, [
        'kundennummer', 'vorname', 'nachname', 'strasse', 'hausnummer', 'plz', 'ort',
        'kundentyp', 'firmenname', 'firmenanschrift', 'bemerkung', 'telefon', 'mobil', 'email'
    ], ",", '"', "\\");
    exit;
}

// CSV-Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=kunden_export_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($output, [
        'kundennummer', 'vorname', 'nachname', 'strasse', 'hausnummer', 'plz', 'ort',
        'kundentyp', 'firmenname', 'firmenanschrift', 'bemerkung', 'telefon', 'mobil', 'email'
    ], ",", '"', "\\");

    $stmt = $pdo->query("SELECT kundennummer, vorname, nachname, strasse, hausnummer, plz, ort,
                                kundentyp, firmenname, firmenanschrift, bemerkung, telefon, mobil, email
                         FROM kunden
                         WHERE deleted_at IS NULL");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row, ",", '"', "\\");
    }
    exit;
}

// CSV-Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $headers = fgetcsv($file, 1000, ",", '"', "\\");

        $inserted = 0;
        $updated = 0;

        while (($data = fgetcsv($file, 1000, ",", '"', "\\")) !== false) {
            if (count($data) === 14) { // Anzahl der Spalten inklusive Telefon, Mobil, Email
                $stmt = $pdo->prepare("
                    INSERT INTO kunden 
                    (kundennummer, vorname, nachname, strasse, hausnummer, plz, ort, kundentyp, 
                     firmenname, firmenanschrift, bemerkung, telefon, mobil, email)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        vorname = VALUES(vorname),
                        nachname = VALUES(nachname),
                        strasse = VALUES(strasse),
                        hausnummer = VALUES(hausnummer),
                        plz = VALUES(plz),
                        ort = VALUES(ort),
                        kundentyp = VALUES(kundentyp),
                        firmenname = VALUES(firmenname),
                        firmenanschrift = VALUES(firmenanschrift),
                        bemerkung = VALUES(bemerkung),
                        telefon = VALUES(telefon),
                        mobil = VALUES(mobil),
                        email = VALUES(email),
                        updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute($data);
                $rowCount = $stmt->rowCount();
                $updated += $rowCount === 2 ? 1 : 0;
                $inserted += $rowCount === 1 ? 1 : 0;
            }
        }

        $_SESSION['success'] = "Import abgeschlossen: $inserted neue Kunden, $updated aktualisiert.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['error'] = "Fehler beim Hochladen der Datei.";
    }
}
?>

<?php include __DIR__ . '/../../assets/header.php'; ?>

<div class="container mt-5">
    <h1 class="fw-bold">Import/Export Kunden</h1>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="mb-4 d-flex flex-wrap gap-2">
        <a href="?export=csv" class="btn btn-success">📤 Kunden exportieren (CSV)</a>
        <a href="?beispiel=csv" class="btn btn-outline-secondary">📄 Beispiel-CSV herunterladen</a>
    </div>

    <form method="POST" enctype="multipart/form-data" class="border p-4 bg-light rounded">
        <div class="mb-3">
            <label for="csv_file" class="form-label">📥 CSV-Datei hochladen</label>
            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
        </div>

        <div class="mb-3">
            <label class="form-label">🔍 Vorschau (max. 10 Zeilen):</label>
            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-bordered table-sm" id="csv-preview"></table>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Import starten</button>
    </form>
</div>

<script>
document.getElementById('csv_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('csv-preview');
    preview.innerHTML = '';

    if (file && file.type.includes("csv")) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const lines = e.target.result.split('\n').slice(0, 10); // Vorschau max. 10 Zeilen
            lines.forEach((line, idx) => {
                if (line.trim() === "") return;
                const row = document.createElement('tr');
                const cells = line.split(',');

                cells.forEach(cell => {
                    const el = document.createElement(idx === 0 ? 'th' : 'td');
                    el.textContent = cell.trim().replace(/^"|"$/g, '');
                    row.appendChild(el);
                });

                preview.appendChild(row);
            });
        };
        reader.readAsText(file, 'UTF-8');
    }
});
</script>

<?php include __DIR__ . '/../../assets/footer.php'; ?>
