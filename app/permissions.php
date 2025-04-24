<?php
// ✅ Zugriffskontrolle aktivieren oder deaktivieren (z. B. für Entwicklungszwecke)
define('ZUGRIFFSKONTROLLE_AKTIV', true);

// 🔐 Definiere die Berechtigungen für jede Seite
$permissions = [
    // 🔐 AUTHENTIFIZIERUNG
    "login.php"                 => ["neu"],
    "logout.php"                => ["admin", "fahrer", "neu"],
    "register.php"              => ["neu"],
    "session.php"               => ["admin", "fahrer", "neu"],
    "profil_bearbeiten.php"     => ["admin", "fahrer", "neu"],

    // 🧭 DASHBOARDS
    "dashboard_admin.php"       => ["admin"],
    "dashboard_fahrer.php"      => ["fahrer", "admin"],
    "dashboard_neuer_user.php"  => ["neu"],

    // 👥 KUNDENVERWALTUNG (ADMIN)
    "kundenverwaltung.php"      => ["admin"],
    "kunde_formular.php"        => ["admin"],
    "kunde_formular.php_sicherung" => ["admin"],
    "kunde_speichern.php"       => ["admin"],
    "kunde_loeschen.php"        => ["admin"],
    "kunde_wiederherstellen.php"=> ["admin"],
    "kundenarchiv.php"          => ["admin"],
    "kunden_importexport.php"   => ["admin"],
    "kunden_suche_ajax.php"     => ["admin"],

    // 🚗 FAHRTENVERWALTUNG (ADMIN)
    "fahrten_liste.php"         => ["admin"],
    "fahrten_liste_fahrerabrechnung.php" => ["admin"],
    "fahrten_liste_kundenabrechnung.php" => ["admin"],
    "fahrten_papierkorb.php"    => ["admin"],
    "fahrten_auswertung.php"    => ["admin"],
    "fahrten_auswertung.php_firmenadresse" => ["admin"],
    "fahrtenstatistiken.php"    => ["admin"],

    "fahrt_formular.php"        => ["admin"],
    "fahrt_formular.php_sicherung" => ["admin"],
    "fahrt_speichern.php"       => ["admin"],
    "fahrt_speichern.php_sicherung" => ["admin"],
    "fahrt_loeschen.php"        => ["admin"],
    "check_driver_availability.php" => ["admin"],
    "autocomplete.php"          => ["admin"],

    // ⚙ SYSTEM / EINSTELLUNGEN (ADMIN)
    "nutzerverwaltung.php"      => ["admin"],
    "nutzer_handler.php"        => ["admin"],
    "system_einstellungen.php"  => ["admin"],
    "settings_handler.php"      => ["admin"],
    "fahrt_einstellungen.php"   => ["admin"],
    "fahrt_einstellungen_bearbeiten.php" => ["admin"],
    "fahrt_einstellungen_speichern.php" => ["admin"],
    "admin_fahrer_verfuegbarkeit.php" => ["admin"],
    "log_viewer.php"            => ["admin"],

    // 📧 E-MAIL (ADMIN)
    "admin_mailer.php"          => ["admin"],
    "email_templates.php"       => ["admin"],
    "email_template_editor.php" => ["admin"],
    "email_template_save.php"   => ["admin"],
    "email_templates_delete.php"=> ["admin"],
    "email_template_preview.php"=> ["admin"],
    "email_template_test.php"   => ["admin"],

    // 👨‍✈️ FAHRER-BEREICH
    "fahrten.php"               => ["fahrer", "admin"],
    "fahrten_alle_fahrer.php"   => ["fahrer", "admin"],
    "fahrer_modal.php"          => ["fahrer", "admin"],
    "fahrer_chat.php"           => ["fahrer", "admin"],
    "fahrer_verfuegbarkeit.php" => ["fahrer", "admin"],
    "fahrer_verfuegbarkeit_speichern.php" => ["fahrer", "admin"],
    "flugstatus_proxy.php"      => ["fahrer", "admin"],
    "flugdaten_test.php"        => ["fahrer", "admin"],
    "gutschriften.php"          => ["fahrer", "admin"],
    "update_fahrt.php"          => ["fahrer", "admin"],

    // 🧪 TEMP / DEBUG / SONSTIGES
    "clear_session.php"         => ["admin"],
    "fahrten.php_ohne functions"=> ["fahrer", "admin"],
    "shared_fahrer_content.php_löschen?" => ["fahrer", "admin"]
];

// 🔹 Ausgabe-Puffer aktivieren (verhindert header-Probleme)
ob_start();

// 🔎 Aktuelle Seite holen und bereinigen
$current_page = basename($_SERVER['SCRIPT_NAME']);

// ➤ Zugriffskontrolle umgehen, falls deaktiviert
if (!ZUGRIFFSKONTROLLE_AKTIV) {
    error_log("⚠ Zugriffskontrolle deaktiviert für Seite: $current_page");
    ob_end_flush();
    return;
}

// 🔹 Prüfen, ob der Nutzer eingeloggt ist
if (!isset($_SESSION["user"]["rollen"]) || !is_array($_SESSION["user"]["rollen"])) {
    $_SESSION['error_message'] = 'Keine Rolle zugewiesen';
    header("Location: /index.php?error=no_role");
    exit();
}

$user_roles = $_SESSION["user"]["rollen"];

// 🔹 Prüfen, ob die Seite in den Berechtigungen existiert
if (!array_key_exists($current_page, $permissions)) {
    $_SESSION['error_message'] = 'Ungültige Seite angefordert';
    header("Location: /index.php?error=invalid_page");
    exit();
}

// 🔹 Zugriff prüfen: Hat der Nutzer mindestens eine passende Rolle?
$allowed_roles = $permissions[$current_page];
$has_access = !empty(array_intersect($user_roles, $allowed_roles));

if (!$has_access) {
    $_SESSION['error_message'] = 'Zugang verweigert';
    header("Location: /index.php?error=no_access");
    exit();
}

ob_end_flush(); // 🔹 Ausgabe-Puffer leeren & beenden
?>
