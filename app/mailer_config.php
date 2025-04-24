<?php
// mailer_config.php - Konfiguration und Hilfsfunktionen für E-Mail-Versand

require_once '/www/htdocs/w00f9852/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Konfiguration
$config = [
    'smtp' => [
        'host'       => 'dd52114.kasserver.com',
        'username'   => 'm07648c1',
        'password'   => '5uZ3AhoKZqprR6QS8D65',
        'encryption' => 'tls',
        'port'       => 587,
        'from_email' => 'fahrer@ab-zum-flieger.com',
        'from_name'  => 'FMS - sAb-zum-Flieger',
    ],
    'debug_level' => 0, // 0 = deaktiviert, 2 = aktiv mit Details
    'charset'     => 'UTF-8',
    'language'    => 'de',
    'admin_bcc'   => 'tms@ab-zum-flieger.com', // E-Mail-Adresse für die automatische BCC
];

// Optional: SMTP-Konfiguration ins Error-Log schreiben
error_log('📧 SMTP-Konfiguration geladen: ' . print_r($config['smtp'], true));

function isMailerEnabled() {
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT wert FROM tms_einstellungen WHERE schluessel = 'mailer_aktiv'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['wert'] === '1';
    } catch (PDOException $e) {
        error_log('⚠️ Fehler beim Prüfen des Mailer-Status: ' . $e->getMessage());
        return false;
    }
}

function initMailer($options = []) {
    global $config;

    $mail = new PHPMailer(true);

    // SMTP-Setup
    $mail->isSMTP();
    $mail->Host = $config['smtp']['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp']['username'];
    $mail->Password = $config['smtp']['password'];
    $mail->SMTPSecure = $config['smtp']['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = $config['smtp']['port'];
    $mail->CharSet = $config['charset'];
    $mail->Timeout = 30;

    // Debugging
    $mail->SMTPDebug = $options['debug_level'] ?? $config['debug_level'];
    $mail->Debugoutput = function ($str, $level) {
        error_log("📡 SMTP Debug [$level]: $str");
    };

    // Absender
    $mail->setFrom(
        $options['from_email'] ?? $config['smtp']['from_email'],
        $options['from_name'] ?? $config['smtp']['from_name']
    );

    // Empfänger + Optionen
    if (!empty($options['to_email'])) {
        $mail->addAddress($options['to_email'], $options['to_name'] ?? '');
    }

    if (!empty($options['cc_email'])) {
        $mail->addCC($options['cc_email']);
    }

    if (!empty($options['bcc_email'])) {
        $mail->addBCC($options['bcc_email']);
    }
    
    // Automatische BCC an Admin hinzufügen, wenn konfiguriert und nicht explizit deaktiviert
    if (!empty($config['admin_bcc']) && (!isset($options['skip_admin_bcc']) || !$options['skip_admin_bcc'])) {
        // Prüfen, ob der BCC-Empfänger nicht bereits der Hauptempfänger ist, um Doppelsendungen zu vermeiden
        if (empty($options['to_email']) || strtolower($options['to_email']) !== strtolower($config['admin_bcc'])) {
            $mail->addBCC($config['admin_bcc']);
            error_log('📧 Automatische BCC an Admin hinzugefügt: ' . $config['admin_bcc']);
        }
    }

    if (!empty($options['reply_to_email'])) {
        $mail->addReplyTo($options['reply_to_email'], $options['reply_to_name'] ?? '');
    }

    if (!empty($options['subject'])) {
        $mail->Subject = $options['subject'];
    }

    if (!empty($options['body'])) {
        $mail->isHTML(true);
        $mail->Body = $options['body'];
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $options['body']));
    }

    if (!empty($options['priority']) && in_array($options['priority'], ['1', '3', '5'])) {
        $mail->Priority = $options['priority'];
    }

    // Anhänge
    if (!empty($options['attachments']) && is_array($options['attachments'])) {
        foreach ($options['attachments'] as $attachment) {
            if (!empty($attachment['path'])) {
                $mail->addAttachment($attachment['path'], $attachment['name'] ?? basename($attachment['path']));
            }
        }
    }

    return $mail;
}

function sendMail($params, $throw_exceptions = false, $force_send = false) {
    if (!$force_send && !isMailerEnabled()) {
        error_log('🚫 Mailer deaktiviert – keine E-Mail gesendet.');

        if (isset($params['debug_level']) && $params['debug_level'] > 0) {
            error_log('ℹ️ Mail-Daten: ' . json_encode([
                'to' => $params['to_email'] ?? 'n/a',
                'subject' => $params['subject'] ?? 'n/a'
            ]));
        }

        return false;
    }

    if (empty($params['to_email']) || !filter_var($params['to_email'], FILTER_VALIDATE_EMAIL)) {
        error_log('❌ Ungültige Empfängeradresse: ' . ($params['to_email'] ?? 'leer'));
        return false;
    }

    try {
        $mail = initMailer($params);
        $success = $mail->send();

        if ($success) {
            error_log('✅ E-Mail erfolgreich gesendet an: ' . $params['to_email']);
        } else {
            error_log('❌ PHPMailer send() fehlgeschlagen für: ' . $params['to_email']);
            error_log('🧨 ErrorInfo: ' . $mail->ErrorInfo);
        }

        return $success;
    } catch (Exception $e) {
        error_log('💥 Exception beim Mailversand: ' . $e->getMessage());

        if (isset($params['debug_level']) && $params['debug_level'] > 0) {
            error_log('📦 Mail-Inhalt: ' . print_r($params, true));
        }

        if ($throw_exceptions) {
            throw $e;
        }

        return false;
    }
}

function sendTemplateEmail($template_id, $data, $options = [], $throw_exceptions = false, $force_send = false) {
    global $pdo;

    if (!$force_send && !isMailerEnabled()) {
        error_log('🚫 Template-Mail wurde nicht gesendet (Mailer deaktiviert): ' . $template_id);
        return false;
    }

    require_once __DIR__ . '/email_template_functions.php';
    $email = create_email_from_template($template_id, $data, $pdo);

    if (!$email) {
        error_log('❌ Template nicht gefunden: ' . $template_id);
        return false;
    }

    $email_params = array_merge($options, [
        'subject' => $email['subject'],
        'body'    => $email['body']
    ]);

    return sendMail($email_params, $throw_exceptions, $force_send);
}