<?php
// email_templates.php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../assets/header.php';

// Templates laden
$stmt = $pdo->query("
    SELECT id, name, subject, description, created_at, updated_at 
    FROM email_templates 
    ORDER BY name ASC
");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-envelope me-2"></i> E-Mail-Templates</h1>
        <a href="email_template_editor.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Neues Template
        </a>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <?php if (empty($templates)): ?>
                <div class="alert alert-info">
                    Keine E-Mail-Templates vorhanden. Erstellen Sie ein neues Template über den Button oben rechts.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Betreff</th>
                                <th>Beschreibung</th>
                                <th>Aktualisiert</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($template['id']) ?></code></td>
                                    <td><?= htmlspecialchars($template['name']) ?></td>
                                    <td><?= htmlspecialchars(strip_tags($template['subject'])) ?></td> <!-- HTML entfernen -->
                                    <td><?= htmlspecialchars(substr($template['description'] ?? '', 0, 100)) ?><?= strlen($template['description'] ?? '') > 100 ? '...' : '' ?></td>
                                    <td><?= $template['updated_at'] ? date('d.m.Y H:i', strtotime($template['updated_at'])) : date('d.m.Y H:i', strtotime($template['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="email_template_editor.php?id=<?= urlencode($template['id']) ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-info preview-template" 
                                                    data-id="<?= htmlspecialchars($template['id']) ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger delete-template" 
                                                    data-id="<?= htmlspecialchars($template['id']) ?>"
                                                    data-name="<?= htmlspecialchars($template['name']) ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Vorschau-Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Template-Vorschau</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Hier wird die Vorschau eingefügt -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Löschen-Dialog
    document.querySelectorAll('.delete-template').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            if (confirm(`Möchten Sie das Template "${name}" wirklich löschen?`)) {
                window.location.href = `email_template_delete.php?id=${encodeURIComponent(id)}&csrf_token=<?= $_SESSION['csrf_token'] ?>`;
            }
        });
    });
    
    // Vorschau anzeigen
    document.querySelectorAll('.preview-template').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            fetch(`email_template_preview.php?id=${encodeURIComponent(id)}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('previewContent').innerHTML = data; // HTML-Inhalt direkt einfügen
                var previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
                previewModal.show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Laden der Vorschau');
            });
        });
    });
});
</script>

<?php include __DIR__ . '/../assets/footer.php'; ?>