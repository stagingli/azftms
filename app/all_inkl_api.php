<?php
// app/all_inkl_api.php

return [
    // Basis-URL der KasAPI – je nach Umgebung (Test oder Produktion) anpassen
    'api_url'           => 'https://kasapi.kasserver.com/kasapi/',
    
    // Authentifizierungsparameter für die API
    'kas_auth_type'     => 'plain',          // Authentifizierungsmethode laut Dokumentation
    'kas_auth_name'     => 'w0202736',       // Dein API-Benutzername
    'kas_auth_password' => '92GNTcrMEqYEpSCDQcXq', // Dein API-Passwort

    // Optional: Feste Standardwerte, z.B. für die Domain der E-Mail-Accounts
    'default_domain'    => 'ihre_domain.de', 
];
