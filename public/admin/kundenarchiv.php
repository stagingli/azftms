<?php
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../assets/header.php';
require __DIR__ . '/../../app/permissions.php';

// Abfrage: Archivierte Kunden (deleted_at IS NOT NULL)
$stmt = $GLOBALS['pdo']->query("SELECT id, kundennummer, vorname, nachname, kundentyp FROM kunden WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
$kunden = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <h1 class="fw-bold">🗑 Archivierte Kunden</h1>
    <a href="kundenverwaltung.php" class="btn btn-secondary">🔙 Zurück zur Kundenverwaltung</a>

    <table class="table table-striped mt-3">
        <thead>
            <tr>
                <th>Kundennummer</th>
                <th>Name</th>
                <th>Typ</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($kunden)): ?>
                <tr>
                    <td colspan="4" class="text-center">Keine archivierten Kunden.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($kunden as $kunde): ?>
                    <tr>
                        <td><?= htmlspecialchars($kunde['kundennummer']) ?></td>
                        <td><?= htmlspecialchars($kunde['vorname'] . " " . $kunde['nachname']) ?></td>
                        <td><?= htmlspecialchars($kunde['kundentyp']) ?></td>
                        <td>
                            <!-- Formular zur Wiederherstellung -->
                            <form method="POST" action="kunde_wiederherstellen.php" style="display:inline;">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($kunde['id']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Möchtest du diesen Kunden wirklich wiederherstellen?');">♻ Wiederherstellen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../assets/footer.php'; ?>
