<?php
// Starte die Session, falls sie nicht bereits gestartet wurde
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Starte Output Buffering, um Weiterleitungen zu ermöglichen
ob_start();

// Einbinden der Konfiguration, Rechte und Header
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/permissions.php';

// Mailer-Konfiguration einbinden
require_once __DIR__ . '/../../app/mailer_config.php';

require __DIR__ . '/../assets/header.php';

// Fehleranzeige für Entwicklung (für Produktion auskommentieren)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Hilfsfunktion zur Bereinigung von Eingaben (Ersatz für FILTER_SANITIZE_STRING)
function sanitizeInput($input) {
    if (is_string($input)) {
        // Entferne HTML-Tags und konvertiere Sonderzeichen
        $input = htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
        // Trimme Leerzeichen am Anfang und Ende
        return trim($input);
    }
    return $input;
}

// Initialisierung der Variablen
$success = '';
$error = '';
$defaults = [
    'to_email' => '',
    'to_name' => '',
    'subject' => '',
    'message' => '',
    'cc_email' => '',
    'bcc_email' => '',
    'reply_to_email' => '',
    'reply_to_name' => ''
];

// Formular wurde abgeschickt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Formularwerte holen und validieren
    $to_email = filter_input(INPUT_POST, 'to_email', FILTER_VALIDATE_EMAIL);
    $to_name = isset($_POST['to_name']) ? sanitizeInput($_POST['to_name']) : '';
    $subject = isset($_POST['subject']) ? sanitizeInput($_POST['subject']) : '';
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    $cc_email = filter_input(INPUT_POST, 'cc_email', FILTER_VALIDATE_EMAIL);
    $bcc_email = filter_input(INPUT_POST, 'bcc_email', FILTER_VALIDATE_EMAIL);
    $reply_to_email = filter_input(INPUT_POST, 'reply_to_email', FILTER_VALIDATE_EMAIL);
    $reply_to_name = isset($_POST['reply_to_name']) ? sanitizeInput($_POST['reply_to_name']) : '';
    $priority = isset($_POST['priority']) && in_array($_POST['priority'], ['1', '3', '5']) ? $_POST['priority'] : '3';
    
    // Formularwerte für erneute Anzeige speichern
    $defaults = [
        'to_email' => $to_email ?: '',
        'to_name' => $to_name,
        'subject' => $subject,
        'message' => $message,
        'cc_email' => $cc_email ?: '',
        'bcc_email' => $bcc_email ?: '',
        'reply_to_email' => $reply_to_email ?: '',
        'reply_to_name' => $reply_to_name
    ];
    
    // Validierung
    if (empty($to_email)) {
        $error = "Bitte geben Sie eine gültige E-Mail-Adresse ein.";
    } elseif (empty($subject)) {
        $error = "Bitte geben Sie einen Betreff ein.";
    } elseif (empty($message)) {
        $error = "Bitte geben Sie eine Nachricht ein.";
    } else {
        try {
            // Anhänge verarbeiten
            $attachments = [];
            if (!empty($_FILES['attachment']['name'][0])) {
                $upload_dir = 'uploads/';
                
                // Upload-Verzeichnis anlegen, falls nicht vorhanden
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Mehrere Anhänge verarbeiten
                $file_count = count($_FILES['attachment']['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    if (!empty($_FILES['attachment']['name'][$i])) {
                        $tmp_name = $_FILES['attachment']['tmp_name'][$i];
                        $name = $_FILES['attachment']['name'][$i];
                        $new_file_path = $upload_dir . uniqid() . '_' . $name;
                        
                        if (move_uploaded_file($tmp_name, $new_file_path)) {
                            $attachments[] = [
                                'path' => $new_file_path,
                                'name' => $name
                            ];
                        }
                    }
                }
            }
            
            // E-Mail-Parameter vorbereiten
            $email_params = [
                'to_email' => $to_email,
                'to_name' => $to_name,
                'subject' => $subject,
                'body' => $message,
                'priority' => $priority,
                'attachments' => $attachments
            ];
            
            // Optionale Parameter hinzufügen
            if (!empty($cc_email)) {
                $email_params['cc_email'] = $cc_email;
            }
            
            if (!empty($bcc_email)) {
                $email_params['bcc_email'] = $bcc_email;
            }
            
            if (!empty($reply_to_email)) {
                $email_params['reply_to_email'] = $reply_to_email;
                $email_params['reply_to_name'] = $reply_to_name;
            }
            
            // E-Mail über die zentrale Funktion senden
            $result = sendMail($email_params, true);
            
            if ($result) {
                $success = 'Die E-Mail wurde erfolgreich versendet.';
                
                // Formular zurücksetzen
                $defaults = [
                    'to_email' => '',
                    'to_name' => '',
                    'subject' => '',
                    'message' => '',
                    'cc_email' => '',
                    'bcc_email' => '',
                    'reply_to_email' => '',
                    'reply_to_name' => ''
                ];
                
                // Temporäre Anhänge nach Versand löschen
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment['path'])) {
                        unlink($attachment['path']);
                    }
                }
            } else {
                $error = "Die E-Mail konnte nicht versendet werden.";
            }
        } catch (Exception $e) {
            $error = "Die E-Mail konnte nicht versendet werden. Fehler: " . $e->getMessage();
            
            // Temporäre Anhänge bei Fehler löschen
            foreach ($attachments as $attachment) {
                if (file_exists($attachment['path'])) {
                    unlink($attachment['path']);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail versenden</title>
    <style>
        .required-field::after {
            content: " *";
            color: red;
        }
        .form-hint {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .tox-tinymce {
            border-radius: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">E-Mail versenden</h3>
                    </div>
                    <div class="card-body">
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="to_email" class="form-label required-field">Empfänger-E-Mail</label>
                                    <input type="email" class="form-control" id="to_email" name="to_email" value="<?php echo htmlspecialchars($defaults['to_email']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="to_name" class="form-label">Empfängername</label>
                                    <input type="text" class="form-control" id="to_name" name="to_name" value="<?php echo htmlspecialchars($defaults['to_name']); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cc_email" class="form-label">CC</label>
                                    <input type="email" class="form-control" id="cc_email" name="cc_email" value="<?php echo htmlspecialchars($defaults['cc_email']); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="bcc_email" class="form-label">BCC</label>
                                    <input type="email" class="form-control" id="bcc_email" name="bcc_email" value="<?php echo htmlspecialchars($defaults['bcc_email']); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="reply_to_email" class="form-label">Antwort an (E-Mail)</label>
                                    <input type="email" class="form-control" id="reply_to_email" name="reply_to_email" value="<?php echo htmlspecialchars($defaults['reply_to_email']); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="reply_to_name" class="form-label">Antwort an (Name)</label>
                                    <input type="text" class="form-control" id="reply_to_name" name="reply_to_name" value="<?php echo htmlspecialchars($defaults['reply_to_name']); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label required-field">Betreff</label>
                                <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($defaults['subject']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priorität</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="3">Normal</option>
                                    <option value="1">Hoch</option>
                                    <option value="5">Niedrig</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label required-field">Nachricht</label>
                                <!-- Normale Textarea ohne Rich-Text-Editor -->
                                <textarea class="form-control" id="message" name="message" rows="12" required><?php echo htmlspecialchars($defaults['message']); ?></textarea>
                                <div class="form-hint mt-1">HTML-Formatierung ist erlaubt (z.B. &lt;b&gt;fett&lt;/b&gt;, &lt;i&gt;kursiv&lt;/i&gt;, &lt;a href="..."&gt;Link&lt;/a&gt;).</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="attachment" class="form-label">Anhänge</label>
                                <input type="file" class="form-control" id="attachment" name="attachment[]" multiple>
                                <div class="form-text">Sie können mehrere Dateien auswählen.</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <input type="submit" name="submit" value="E-Mail senden" class="btn btn-primary btn-lg">
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-muted">
                        <small>Pflichtfelder sind mit * gekennzeichnet</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>