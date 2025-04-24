<?php
require_once __DIR__ . '/../assets/header.php';

try {
    // SOAP-Client für die Authentifizierung initialisieren
    $SoapLogon = new SoapClient(
        'https://kasapi.kasserver.com/soap/wsdl/KasAuth.wsdl',
        [
            'trace'      => 1,  // Aktiviert Debugging (Request/Response)
            'exceptions' => true
        ]
    );
  
    // Parameter für die Authentifizierung definieren
    $authParams = array(
        'kas_login'               => 'w0213456',   // dein KAS-Login
        'kas_auth_type'           => 'plain',      // Authentifizierungstyp
        'kas_auth_data'           => 'JHuAbH5W53Mfi9irBpvb', // dein KAS-Passwort
        'session_lifetime'        => 600,          // Token-Gültigkeit in Sekunden
        'session_update_lifetime' => 'Y'           // verlängert die Session mit jedem Request
        // Optional: 'session_2fa'   => 123456,    // falls 2FA aktiviert ist
    );
  
    // Authentifizierung durchführen (Parameter als JSON übergeben)
    $CredentialToken = $SoapLogon->KasAuth(json_encode($authParams));
  
    echo "<h3>Session-Token erhalten:</h3>";
    echo "<pre>" . htmlspecialchars($CredentialToken) . "</pre>";
  
    // Hier könntest du den Token in einer Session speichern:
    // session_start();
    // $_SESSION['credential_token'] = $CredentialToken;
  
} catch (SoapFault $fault) {
    // Verwende exit() anstelle von trigger_error(), um den Fehler zu melden und das Skript zu beenden.
    exit("SOAP-Fehler bei der Authentifizierung: Fehlernummer: {$fault->faultcode}, Fehlermeldung: {$fault->faultstring}, Verursacher: {$fault->faultactor}, Details: {$fault->detail}");
}
?>
