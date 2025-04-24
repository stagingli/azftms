<?php
// email_template_editor.php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../assets/header.php';

// Mailer-Konfiguration einbinden
require_once __DIR__ . '/../../app/mailer_config.php';

use PHPMailer\PHPMailer\Exception;

// Direkter E-Mail-Test bei Klick auf entsprechenden Button
$testmail_result = '';
$testmail_error = '';

if (isset($_POST['send_test_mail']) && isset($_POST['test_email'])) {
    $test_email = $_POST['test_email'];
    $test_subject = $_POST['template_subject'] ?? 'Test Subject';
    $test_body = $_POST['template_body'] ?? '<p>Dies ist ein Test.</p>';
    
    // Lade eine echte Beispielfahrt aus der Datenbank
    $example_data = [];
    try {
        // Neueste Fahrt mit vollständigen Daten laden
        $stmt = $pdo->prepare("
            SELECT f.*, 
                   k.vorname AS kunde_vorname, k.nachname AS kunde_nachname, 
                   k.email AS kunde_email, k.telefon AS kunde_telefon, 
                   k.strasse AS kunde_strasse, k.hausnummer AS kunde_hausnummer, 
                   k.plz AS kunde_plz, k.ort AS kunde_ort,
                   n.name AS fahrer_name, n.email AS fahrer_email,
                   estart.wert AS start_ort, eziel.wert AS ziel_ort
            FROM fahrten f
            LEFT JOIN kunden k ON f.kunde_id = k.id
            LEFT JOIN nutzer n ON f.fahrer_id = n.id
            LEFT JOIN einstellungen estart ON f.ort_start_id = estart.id
            LEFT JOIN einstellungen eziel ON f.ort_ziel_id = eziel.id
            WHERE f.deleted_at IS NULL
            ORDER BY f.erstellt_am DESC
            LIMIT 1
        ");
        $stmt->execute();
        $fahrt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fahrt) {
            $example_data = [
                'fahrt_id' => $fahrt['id'],
                'abholdatum' => $fahrt['abholdatum'],
                'abfahrtszeit' => $fahrt['abfahrtszeit'],
                'start_ort' => $fahrt['start_ort'],
                'ziel_ort' => $fahrt['ziel_ort'],
                'fahrtpreis' => $fahrt['fahrtpreis'],
                
                'kunde_vorname' => $fahrt['kunde_vorname'],
                'kunde_nachname' => $fahrt['kunde_nachname'],
                'kunde_email' => $fahrt['kunde_email'],
                'kunde_telefon' => $fahrt['kunde_telefon'],
                'kunde_strasse' => $fahrt['kunde_strasse'],
                'kunde_hausnummer' => $fahrt['kunde_hausnummer'],
                'kunde_plz' => $fahrt['kunde_plz'],
                'kunde_ort' => $fahrt['kunde_ort'],
                
                'fahrer_name' => $fahrt['fahrer_name'],
                'fahrer_email' => $fahrt['fahrer_email'],
                'fahrer_mobil' => '(nicht verfügbar)' // Falls nicht in der Datenbank vorhanden
            ];
        } else {
            // Fallback: Statische Beispieldaten, falls keine Fahrt gefunden wird
            $example_data = [
                'fahrt_id' => '12345',
                'abholdatum' => date('Y-m-d', strtotime('+2 days')),
                'abfahrtszeit' => '09:30:00',
                'start_ort' => 'Hamburg Hauptbahnhof',
                'ziel_ort' => 'Hamburg Airport',
                'fahrtpreis' => 49.90,
                
                'kunde_vorname' => 'Max',
                'kunde_nachname' => 'Mustermann',
                'kunde_email' => 'max.mustermann@example.com',
                'kunde_telefon' => '+49 123 4567890',
                'kunde_strasse' => 'Musterstraße',
                'kunde_hausnummer' => '123',
                'kunde_plz' => '12345',
                'kunde_ort' => 'Musterstadt',
                
                'fahrer_name' => 'Erika Musterfahrerin',
                'fahrer_mobil' => '+49 987 6543210',
                'fahrer_email' => 'fahrer@example.com'
            ];
        }
        
        // Allgemeine Daten immer setzen
        $example_data['datum_heute'] = date('d.m.Y');
        $example_data['firma_name'] = 'Ab-zum-Flieger';
        $example_data['firma_kontakt'] = 'Email: info@ab-zum-flieger.com, Tel: +49 40 123456789';
        
    } catch (PDOException $e) {
        $testmail_error = "Fehler beim Laden der Beispieldaten: " . $e->getMessage();
        // Trotzdem weitermachen mit Standard-Daten
        $example_data = [
            'fahrt_id' => 'ERROR-' . rand(1000, 9999),
            'abholdatum' => date('Y-m-d'),
            'abfahrtszeit' => '12:00:00',
            // ... weitere Standard-Werte
        ];
    }
    
    // Ausgabe der verwendeten Daten für Debug-Zwecke
    $debug_data = '<div class="mt-3 p-3 bg-light rounded"><strong>Verwendete Testdaten:</strong><pre>' . 
                  print_r($example_data, true) . '</pre></div>';
    
    // Shortcodes durch echte Daten ersetzen
    require_once __DIR__ . '/../../app/email_template_functions.php';
    $processed_subject = replace_shortcodes($test_subject, $example_data);
    $processed_body = replace_shortcodes($test_body, $example_data);
    
    // E-Mail mit Hilfe der mailer_config.php senden
    $mail_params = [
        'to_email' => $test_email,
        'subject' => "[TEST] " . $processed_subject,
        'body' => $processed_body,
        'debug_level' => 2 // Optional: 0 für Produktion, höher für Debugging
    ];
    
    try {
        $result = sendMail($mail_params);
        
        if ($result) {
            $testmail_result = "E-Mail erfolgreich an $test_email gesendet!" . $debug_data;
        } else {
            $testmail_error = "Fehler beim Senden der E-Mail. Bitte prüfen Sie die Serverkonfiguration.";
        }
    } catch (Exception $e) {
        $testmail_error = "Fehler beim Senden: " . $e->getMessage();
    }
}

// Prüfen, ob ein Template bearbeitet werden soll
$template_id = $_GET['id'] ?? '';
$template = null;

if (!empty($template_id)) {
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        $_SESSION['error'] = "Template mit ID '$template_id' nicht gefunden.";
        header("Location: email_templates.php");
        exit();
    }
}

// Verfügbare Shortcodes definieren
$shortcodes = [
    'fahrt' => [
        ['code' => '[fahrt_id]', 'description' => 'ID der Fahrt'],
        ['code' => '[fahrt_datum]', 'description' => 'Datum der Fahrt'],
        ['code' => '[fahrt_uhrzeit]', 'description' => 'Abfahrtszeit'],
        ['code' => '[fahrt_strecke]', 'description' => 'Strecke (Start → Ziel)'],
        ['code' => '[fahrt_preis]', 'description' => 'Preis der Fahrt'],
        ['code' => '[fahrt_start]', 'description' => 'Startort'],
        ['code' => '[fahrt_ziel]', 'description' => 'Zielort']
    ],
    'kunde' => [
        ['code' => '[kunde_name]', 'description' => 'Name des Kunden'],
        ['code' => '[kunde_vorname]', 'description' => 'Vorname des Kunden'],
        ['code' => '[kunde_nachname]', 'description' => 'Nachname des Kunden'],
        ['code' => '[kunde_anrede]', 'description' => 'Anrede des Kunden'],
        ['code' => '[kunde_adresse]', 'description' => 'Vollständige Adresse'],
        ['code' => '[kunde_email]', 'description' => 'E-Mail des Kunden'],
        ['code' => '[kunde_telefon]', 'description' => 'Telefonnummer des Kunden']
    ],
    'fahrer' => [
        ['code' => '[fahrer_name]', 'description' => 'Name des Fahrers'],
        ['code' => '[fahrer_mobil]', 'description' => 'Mobilnummer des Fahrers'],
        ['code' => '[fahrer_email]', 'description' => 'E-Mail des Fahrers']
    ],
    'allgemein' => [
        ['code' => '[datum_heute]', 'description' => 'Aktuelles Datum'],
        ['code' => '[firma_name]', 'description' => 'Name der Firma'],
        ['code' => '[firma_kontakt]', 'description' => 'Kontaktinformationen der Firma']
    ]
];
?>

<div class="container my-4">
    <?php if (!empty($testmail_result)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?= $testmail_result ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($testmail_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i> <?= $testmail_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-lg border-0">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0 py-2">
                <i class="fas fa-envelope me-2"></i> <?= $template ? "Template bearbeiten" : "Neues Template erstellen" ?>
            </h2>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <form id="templateForm" method="POST" action="email_template_save.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="template_name" class="form-label">Template-Name</label>
                        <input type="text" class="form-control" id="template_name" name="name" 
                               value="<?= htmlspecialchars($template['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="template_id" class="form-label">Template-ID</label>
                        <input type="text" class="form-control" id="template_id" name="id" 
                               value="<?= htmlspecialchars($template['id'] ?? '') ?>" required
                               pattern="[a-z0-9_-]+" title="Nur Kleinbuchstaben, Zahlen, Unterstriche und Bindestriche erlaubt"
                               <?= $template ? 'readonly' : '' ?>>
                        <small class="text-muted">Eindeutiger Identifier für dieses Template</small>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-12">
                        <label for="editor-subject" class="form-label">E-Mail-Betreff</label>
                        <textarea id="editor-subject" class="form-control"><?= htmlspecialchars($template['subject'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12">
                        <label for="editor-body" class="form-label">E-Mail-Inhalt</label>
                        <textarea id="editor-body" class="form-control"><?= htmlspecialchars($template['body'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12">
                        <label for="template_description" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="template_description" name="description" rows="2"><?= htmlspecialchars($template['description'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="email_templates.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Zurück zur Übersicht
                    </a>
                    <div>
                        <button type="button" id="previewBtn" class="btn btn-info me-2">
                            <i class="fas fa-eye me-2"></i> Vorschau
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i> Speichern
                        </button>
                    </div>
                </div>

                <!-- Versteckte Felder für die Synchronisierung (jetzt innerhalb des Formulars) -->
                <input type="hidden" name="subject" id="form_subject" value="">
                <input type="hidden" name="body" id="form_body" value="">
            </form>
            
            <!-- Direkter E-Mail-Test-Formular -->
            <div class="mt-4 pt-3 border-top">
                <h5><i class="fas fa-paper-plane me-2"></i>Direkte E-Mail-Test</h5>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="template_subject" id="form_subject" value="">
                    <input type="hidden" name="template_body" id="form_body" value="">
                    
                    <div class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text">E-Mail:</span>
                                <input type="email" name="test_email" class="form-control" value="benny@grvl.in" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" name="send_test_mail" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Test-E-Mail senden
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">Sendet eine Test-E-Mail mit den aktuellen Template-Inhalten an die angegebene E-Mail-Adresse.</small>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Shortcode-Hilfe -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Verfügbare Shortcodes</h5>
        </div>
        <div class="card-body">
            <div class="accordion" id="shortcodeAccordion">
                <?php foreach ($shortcodes as $category => $codes): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading_<?= $category ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                data-bs-target="#collapse_<?= $category ?>" aria-expanded="false" 
                                aria-controls="collapse_<?= $category ?>">
                            <?= ucfirst($category) ?>-Shortcodes
                        </button>
                    </h2>
                    <div id="collapse_<?= $category ?>" class="accordion-collapse collapse" 
                         aria-labelledby="heading_<?= $category ?>" data-bs-parent="#shortcodeAccordion">
                        <div class="accordion-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Shortcode</th>
                                            <th>Beschreibung</th>
                                            <th>Aktion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($codes as $code): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($code['code']) ?></code></td>
                                            <td><?= htmlspecialchars($code['description']) ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary insert-shortcode" 
                                                        data-shortcode="<?= htmlspecialchars($code['code']) ?>">
                                                    Einfügen
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Vorschau-Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">E-Mail-Vorschau</h5>
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
</div>

<!-- CKEditor 5 Classic Editor einbinden -->
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<style>
/* Editor-Inhalt */
.ck.ck-editor__editable {
    background-color: #1e1e2d; /* Dunkler Hintergrund */
 /*   color: #e0e0e0; /* Helle Schriftfarbe */
    border: 1px solid #3a3a4f; /* Rahmenfarbe */
    padding: 12px; /* Innenabstand */
    border-radius: 6px; /* Abgerundete Ecken */
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Modernere Schriftart */
    font-size: 15px; /* Etwas größere Schriftgröße */
    line-height: 1.6; /* Angenehmer Zeilenabstand */
}

/* Toolbar */
.ck.ck-toolbar {
    background-color: #2a2a3c; /* Dunkler Hintergrund für die Toolbar */
    border: 1px solid #3a3a4f; /* Rahmenfarbe */
    color: #ffffff; /* Weiße Schrift */
    border-radius: 6px 6px 0 0; /* Abgerundete obere Ecken */
}

/* Toolbar-Buttons */
.ck.ck-toolbar .ck-button {
    color: #ffffff; /* Weiße Schrift für Buttons */
    background-color: transparent; /* Transparenter Hintergrund */
    border: none; /* Keine Rahmen */
}

.ck.ck-toolbar .ck-button:hover {
    background-color: #3a3a4f; /* Hover-Effekt für Buttons */
    color: #ffffff; /* Schrift bleibt weiß */
}

.ck.ck-toolbar .ck-button.ck-on {
    background-color: #4a4a6a; /* Aktiver Button */
    color: #ffffff;
}

/* Fokus auf den Editor */
.ck.ck-editor__editable:focus {
    outline: none; /* Entfernt den Fokusrahmen */
    border-color: #5a5a7a; /* Rahmenfarbe bei Fokus */
}

/* Dropdown-Menüs */
.ck.ck-dropdown .ck-dropdown__panel {
    background-color: #2a2a3c; /* Dunkler Hintergrund für Dropdowns */
    color: #ffffff; /* Weiße Schrift */
    border: 1px solid #3a3a4f; /* Rahmenfarbe */
}

/* Tabellen */
.ck.ck-editor__editable table {
    border-collapse: collapse; /* Tabellenrahmen zusammenführen */
    width: 100%; /* Tabellenbreite */
    color: #e0e0e0; /* Schriftfarbe */
}

.ck.ck-editor__editable table th,
.ck.ck-editor__editable table td {
    border: 1px solid #3a3a4f; /* Tabellenrahmen */
    padding: 8px; /* Innenabstand */
    text-align: left; /* Linksbündiger Text */
}

.ck.ck-editor__editable table th {
    background-color: #3a3a4f; /* Hintergrund für Tabellenüberschriften */
    color: #ffffff; /* Weiße Schrift */
}

/* Links */
.ck.ck-editor__editable a {
    color: #4a90e2; /* Blaue Links */
    text-decoration: underline; /* Unterstrichene Links */
}

.ck.ck-editor__editable a:hover {
    color: #72b4ff; /* Hellere Farbe beim Hover */
}

/* Scrollbar */
.ck.ck-editor__editable {
    scrollbar-width: thin; /* Dünne Scrollbar */
    scrollbar-color: #4a4a6a #1e1e2d; /* Scrollbar-Farben */
}

.ck.ck-editor__editable::-webkit-scrollbar {
    width: 8px; /* Breite der Scrollbar */
}

.ck.ck-editor__editable::-webkit-scrollbar-thumb {
    background-color: #4a4a6a; /* Farbe des Scrollbalkens */
    border-radius: 4px; /* Abgerundete Ecken */
}

.ck.ck-editor__editable::-webkit-scrollbar-track {
    background-color: #1e1e2d; /* Hintergrund der Scrollbar */
}
.ck.ck-editor__main>.ck-editor__editable {
    background: #1e1e2d;
    border-radius: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Klassischen CKEditor für den Betreff initialisieren
    ClassicEditor
        .create(document.querySelector('#editor-subject'), {
            toolbar: [
                'undo', 'redo', '|',
                'bold', 'italic', 'underline', '|',
                'link', 'removeFormat'
            ]
        })
        .then(editor => {
            window.subjectEditor = editor; // Editor-Instanz global speichern

            // Synchronisiere den Inhalt mit dem versteckten Feld
            editor.model.document.on('change:data', () => {
                document.getElementById('form_subject').value = editor.getData();
            });

            // Initial den Inhalt setzen
            document.getElementById('form_subject').value = editor.getData();
        })
        .catch(error => {
            console.error('CKEditor Fehler (Betreff):', error);
        });

    // Klassischen CKEditor für den E-Mail-Inhalt initialisieren
    ClassicEditor
        .create(document.querySelector('#editor-body'), {
            toolbar: [
                'undo', 'redo', '|',
                'heading', '|',
                'bold', 'italic', 'underline', 'strikethrough', '|',
                'bulletedList', 'numberedList', '|',
                'link', 'blockQuote', 'insertTable', '|',
                'alignment', 'removeFormat', '|',
                'sourceEditing' // HTML-Quellcode-Bearbeitung hinzufügen
            ],
            table: {
                contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
            }
        })
        .then(editor => {
            window.bodyEditor = editor; // Editor-Instanz global speichern

            // Synchronisiere den Inhalt mit dem versteckten Feld
            editor.model.document.on('change:data', () => {
                document.getElementById('form_body').value = editor.getData();
            });

            // Initial den Inhalt setzen
            document.getElementById('form_body').value = editor.getData();
        })
        .catch(error => {
            console.error('CKEditor Fehler (Inhalt):', error);
        });
});
</script>

<?php include __DIR__ . '/../assets/footer.php'; ?>