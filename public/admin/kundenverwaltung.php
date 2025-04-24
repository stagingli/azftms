<?php 
require __DIR__ . '/../../app/config.php'; 
require __DIR__ . '/../../app/permissions.php';
require __DIR__ . '/../assets/header.php'; 

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
         . htmlspecialchars($_SESSION['error']) .
         '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button></div>';
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
         . htmlspecialchars($_SESSION['success']) .
         '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button></div>';
    unset($_SESSION['success']);
}

// Kunden + Fahrtenanzahl
$stmt = $pdo->query("SELECT k.id, k.kundennummer, k.vorname, k.nachname, k.firmenname, k.kundentyp, 
    (SELECT COUNT(*) FROM fahrten f WHERE f.kunde_id = k.id AND f.deleted_at IS NULL) AS fahrtenanzahl 
    FROM kunden k 
    WHERE k.deleted_at IS NULL 
    ORDER BY k.erstellt_am DESC");
$kunden = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <h1 class="fw-bold">Kundenverwaltung</h1>
    <div class="mb-3">
        <a href="kunde_formular.php" class="btn btn-primary">➕ Neuen Kunden anlegen</a>
        <a href="kundenarchiv.php" class="btn btn-secondary">🗑 Archiv anzeigen</a>
    </div>

    <div class="mb-3">
        <input type="text" id="kunden-suche" class="form-control" placeholder="🔍 Kunden suchen...">
    </div>

    <div class="table-responsive">
        <table id="kunden-tabelle" class="table table-striped table-hover">
            <thead class="table-light">
                <tr>
                    <th>Kundennummer</th>
                    <th>Name</th>
                    <th>Typ</th>
                    <th>Fahrten</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kunden as $kunde): ?>
                <tr>
                    <td><?= htmlspecialchars($kunde['kundennummer']) ?></td>
                    <td>
                        <?= $kunde['kundentyp'] === 'firma' && !empty($kunde['firmenname']) 
                            ? htmlspecialchars($kunde['firmenname']) 
                            : htmlspecialchars(trim($kunde['vorname'] . " " . $kunde['nachname'])) ?>
                    </td>
                    <td><?= htmlspecialchars($kunde['kundentyp']) ?></td>
                    <td>
                        <a href="fahrten_liste.php?kunde_id=<?= $kunde['id'] ?>" class="badge bg-info text-decoration-none">
                            <?= $kunde['fahrtenanzahl'] ?> Fahrt(en)
                        </a>
                    </td>
                    <td class="text-end">
                        <a href="kunde_formular.php?id=<?= htmlspecialchars($kunde['id']) ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit me-1"></i>Bearbeiten
                        </a>
                        <form method="POST" action="kunde_loeschen.php" style="display:inline;">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($kunde['id']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Möchtest du diesen Kunden wirklich archivieren?');">
                                <i class="fas fa-trash-alt me-1"></i>Löschen
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<style>
    div.dataTables_filter {
        display: none; /* Versteckt das integrierte Suchfeld */
    }
</style>
<script>
$(document).ready(function() {
    const table = $('#kunden-tabelle').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json' },
        pageLength: 50,
        lengthChange: false,
        searching: true, // Beibehalten, damit table.search() funktioniert
        processing: true,
        serverSide: true,
        ajax: {
            url: 'kunden_suche_ajax.php',
            type: 'POST',
            data: function(d) {
                d.csrf_token = '<?= $_SESSION['csrf_token']; ?>'; // CSRF-Schutz
            }
        },
        columns: [
            { data: 'kundennummer' },
            { data: 'name' },
            { data: 'kundentyp' },
            { data: 'fahrtenanzahl' },
            { data: 'aktionen', orderable: false, searchable: false }
        ]
    });

    // Manuelle Kundensuche
    $('#kunden-suche').on('keyup', function() {
        const query = $(this).val().trim();
        if (query.length > 2 || query.length === 0) {
            table.search(query).draw();
        }
    });
});
</script>

<?php include __DIR__ . '/../assets/footer.php'; ?>
