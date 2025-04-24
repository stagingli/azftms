<?php
// email_template_functions.php
/**
 * Ersetzt Shortcodes in einem Template mit tatsächlichen Daten
 * 
 * @param string $template_content Der Template-Inhalt mit Shortcodes
 * @param array $data Die Daten zum Ersetzen
 * @return string Der fertige Template-Inhalt
 */
function replace_shortcodes($template_content, $data) {
    $shortcodes = [
        // Fahrtdaten
        '[fahrt_id]' => $data['fahrt_id'] ?? '',
        '[fahrt_datum]' => isset($data['abholdatum']) ? date('d.m.Y', strtotime($data['abholdatum'])) : '',
        '[fahrt_uhrzeit]' => isset($data['abfahrtszeit']) ? substr($data['abfahrtszeit'], 0, 5) . ' Uhr' : '',
        '[fahrt_strecke]' => ($data['start_ort'] ?? '') . ' → ' . ($data['ziel_ort'] ?? ''),
        '[fahrt_preis]' => isset($data['fahrtpreis']) ? number_format($data['fahrtpreis'], 2, ',', '.') . ' €' : '',
        '[fahrt_start]' => $data['start_ort'] ?? '',
        '[fahrt_ziel]' => $data['ziel_ort'] ?? '',
        
        // Kundendaten
        '[kunde_name]' => trim(($data['kunde_vorname'] ?? '') . ' ' . ($data['kunde_nachname'] ?? '')),
        '[kunde_vorname]' => $data['kunde_vorname'] ?? '',
        '[kunde_nachname]' => $data['kunde_nachname'] ?? '',
        '[kunde_anrede]' => isset($data['kunde_vorname']) ? 'Sehr geehrte(r) ' . $data['kunde_vorname'] . ' ' . ($data['kunde_nachname'] ?? '') : 'Sehr geehrte Damen und Herren',
        '[kunde_adresse]' => formatAdresse($data),
        '[kunde_email]' => $data['kunde_email'] ?? '',
        '[kunde_telefon]' => $data['kunde_telefon'] ?? '',
        
        // Fahrerdaten
        '[fahrer_name]' => $data['fahrer_name'] ?? '',
        '[fahrer_mobil]' => $data['fahrer_mobil'] ?? '',
        '[fahrer_email]' => $data['fahrer_email'] ?? '',
        
        // Allgemeine Daten
        '[datum_heute]' => date('d.m.Y'),
        '[firma_name]' => 'Ab-zum-Flieger', // Aus Einstellungen holen
        '[firma_kontakt]' => 'Email: info@ab-zum-flieger.com, Tel: +49 40 123456789' // Aus Einstellungen holen
    ];
    
    // Shortcodes ersetzen
    $content = $template_content;
    foreach ($shortcodes as $code => $value) {
        $content = str_replace($code, $value, $content);
    }
    
    return $content;
}

/**
 * Formatiert eine Adresse aus den Kundendaten
 * 
 * @param array $data Kundendaten
 * @return string Formatierte Adresse
 */
function formatAdresse($data) {
    $adresse = [];
    
    if (!empty($data['kunde_firma'])) {
        $adresse[] = $data['kunde_firma'];
    }
    
    $adresse[] = trim(($data['kunde_vorname'] ?? '') . ' ' . ($data['kunde_nachname'] ?? ''));
    
    if (!empty($data['kunde_strasse']) || !empty($data['kunde_hausnummer'])) {
        $adresse[] = trim(($data['kunde_strasse'] ?? '') . ' ' . ($data['kunde_hausnummer'] ?? ''));
    }
    
    if (!empty($data['kunde_plz']) || !empty($data['kunde_ort'])) {
        $adresse[] = trim(($data['kunde_plz'] ?? '') . ' ' . ($data['kunde_ort'] ?? ''));
    }
    
    return implode('<br>', $adresse);
}

/**
 * Lädt ein E-Mail-Template aus der Datenbank
 * 
 * @param string $template_id Die ID des Templates
 * @param PDO $pdo Datenbankverbindung
 * @return array|null Das Template oder null, wenn nicht gefunden
 */
function load_email_template($template_id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Erstellt eine E-Mail-Nachricht aus einem Template und Daten
 * 
 * @param string $template_id Die ID des Templates
 * @param array $data Die Daten zum Ersetzen der Shortcodes
 * @param PDO $pdo Datenbankverbindung
 * @return array|false Das aufbereitete Template mit subject und body, oder false bei Fehler
 */
function create_email_from_template($template_id, $data, $pdo) {
    $template = load_email_template($template_id, $pdo);
    if (!$template) {
        return false;
    }
    
    return [
        'subject' => replace_shortcodes($template['subject'], $data),
        'body' => replace_shortcodes($template['body'], $data)
    ];
}