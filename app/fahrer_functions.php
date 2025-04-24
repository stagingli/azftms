<?php
/**
 * fahrer_functions.php
 * Gemeinsame Funktionen für das Fahrer-Dashboard und die Fahrten-Übersicht
 * Enthält Cache-Mechanismen und optimierte Abfragen
 */

/**
 * Holt alle benötigten Fahrten für einen Fahrer aus der Datenbank.
 * Mit Caching-Unterstützung für wiederholte Abfragen.
 * 
 * @param PDO $pdo Die Datenbankverbindung.
 * @param int $fahrer_id Die ID des Fahrers.
 * @param array $filter Optionale Filterparameter (von_datum, bis_datum, status, etc.)
 * @return array Array mit Fahrten.
 */
function getDriverRides(PDO $pdo, int $fahrer_id, array $filter = []) {
    // Caching-Mechanismus für wiederholte Abfragen
    static $rideCache = [];
    
    // Cache-Key generieren
    $cacheKey = $fahrer_id . '_' . md5(serialize($filter));
    
    // Wenn Daten im Cache sind, diese zurückgeben
    if (isset($rideCache[$cacheKey])) {
        return $rideCache[$cacheKey];
    }
    
    // Standardfilter setzen
    $von_datum = $filter['von_datum'] ?? date('Y-m-d');
    $bis_datum = $filter['bis_datum'] ?? date('Y-m-d');
    $status_filter = $filter['status'] ?? '';
    $keine_zeiten = $filter['keine_zeiten'] ?? false;
    
    // SQL-Abfrage mit allen benötigten Feldern und Joins
    $sql = "
        SELECT
            f.id,
            f.rechnungsnummer,
            f.fahrtpreis,
            f.zahlungsmethode_id,
            f.fahrer_id,
            f.fahrzeug_id,
            f.kunde_id,
            f.ort_start_id,
            f.ort_ziel_id,
            f.abholdatum,
            f.abfahrtszeit,
            f.fahrzeit_von,
            f.fahrzeit_bis,
            f.fahrzeit_summe,
            f.lohn_fahrt,
            f.lohn_auszahlbetrag,
            f.ausgaben,
            f.wartezeit,
            f.fahrer_bemerkung,
            f.alternative_abholadresse,
            f.alternative_zieladresse,
            f.flugnummer,
            f.personenanzahl,
            f.zusatzequipment,
            f.dispo_bemerkung,
            f.hinfahrt_id,
            hf.abholdatum AS hinfahrt_datum,
            hf.abfahrtszeit AS hinfahrt_zeit,
            -- Kundendaten
            k.vorname AS kunde_vorname,
            k.nachname AS kunde_nachname,
            k.kundentyp,
            k.firmenname,
            k.firmenanschrift,
            k.strasse,
            k.hausnummer,
            k.plz,
            k.ort AS kunde_ort,
            k.telefon,
            k.mobil,
            k.email,
            k.bemerkung AS kunde_bemerkung,
            -- Einstellungsdaten
            e1.wert AS ort_start_name,
            e2.wert AS ort_ziel_name,
            e3.wert AS fahrzeug_info,
            ez.wert AS zahlungsart
        FROM fahrten f
        LEFT JOIN kunden k ON f.kunde_id = k.id
        LEFT JOIN einstellungen e1 ON f.ort_start_id = e1.id
        LEFT JOIN einstellungen e2 ON f.ort_ziel_id = e2.id
        LEFT JOIN einstellungen e3 ON f.fahrzeug_id = e3.id
        LEFT JOIN einstellungen ez ON f.zahlungsmethode_id = ez.id
        LEFT JOIN fahrten hf ON f.hinfahrt_id = hf.id
        WHERE f.fahrer_id = :fahrer_id
    ";
    
    // Filter hinzufügen
    if (!$keine_zeiten) {
        $sql .= " AND f.abholdatum BETWEEN :von_datum AND :bis_datum";
    } else {
        $sql .= " AND (f.fahrzeit_von IS NULL OR f.fahrzeit_von = '')";
    }
    
    $sql .= " AND f.deleted_at IS NULL";
    
    // Sortierung
    $sql .= " ORDER BY f.abholdatum ASC, f.abfahrtszeit ASC";
    
    // Query vorbereiten und ausführen
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':fahrer_id', $fahrer_id, PDO::PARAM_INT);
    
    if (!$keine_zeiten) {
        $stmt->bindValue(':von_datum', $von_datum);
        $stmt->bindValue(':bis_datum', $bis_datum);
    }
    
    $stmt->execute();
    $fahrten = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statusfilterung in PHP (für mehr Flexibilität)
    if (!empty($status_filter)) {
        $fahrten = array_filter($fahrten, function($fahrt) use ($status_filter) {
            $status = getFahrtStatus($fahrt);
            return strtolower($status['text']) === strtolower($status_filter);
        });
    }
    
    // Ergebnis im Cache speichern
    $rideCache[$cacheKey] = $fahrten;
    
    return $fahrten;
}

/**
 * Bestimmt den Status einer Fahrt.
 * 
 * @param array $fahrt Fahrtdaten.
 * @return array Status als ['text' => '...', 'color' => '...'].
 */
function getFahrtStatus($fahrt) {
    $heute = date('Y-m-d');
    $jetzt = date('H:i:s');
    
    // Ist in der Vergangenheit
    if ($fahrt['abholdatum'] < $heute) {
        if (!empty($fahrt['fahrzeit_bis'])) {
            return ['text' => 'Abgeschlossen', 'color' => 'success'];
        }
        return ['text' => 'Offen', 'color' => 'warning'];
    }
    
    // Ist heute
    if ($fahrt['abholdatum'] == $heute) {
        if (!empty($fahrt['fahrzeit_bis'])) {
            return ['text' => 'Abgeschlossen', 'color' => 'success'];
        }
        
        if (!empty($fahrt['fahrzeit_von'])) {
            return ['text' => 'Aktiv', 'color' => 'primary'];
        }
        
        if ($fahrt['abfahrtszeit'] < $jetzt) {
            return ['text' => 'Fällig', 'color' => 'danger'];
        }
        
        // Noch nicht fällig, aber in < 1h
        $abfahrt = strtotime($fahrt['abfahrtszeit']);
        $jetztTime = strtotime($jetzt);
        if (($abfahrt - $jetztTime) < 3600) {
            return ['text' => 'Bald', 'color' => 'warning'];
        }
        
        return ['text' => 'Heute', 'color' => 'info'];
    }
    
    // Ist in der Zukunft
    return ['text' => 'Geplant', 'color' => 'secondary'];
}

/**
 * Berechnet die verbleibende Zeit bis zur Abfahrt einer Fahrt.
 * 
 * @param array $fahrt Die Fahrtdaten
 * @return array ['text' => 'formatierte Zeit', 'minutes' => Minuten bis Abfahrt]
 */
function getRemainingTime($fahrt) {
    $heute = date('Y-m-d');
    
    // Wenn Fahrt in der Vergangenheit oder bereits abgeschlossen
    if ($fahrt['abholdatum'] < $heute || !empty($fahrt['fahrzeit_bis'])) {
        return ['text' => 'Abgeschlossen', 'minutes' => 0];
    }
    
    // Wenn Fahrt in der Zukunft
    if ($fahrt['abholdatum'] > $heute) {
        $days = (strtotime($fahrt['abholdatum']) - strtotime($heute)) / 86400;
        if ($days > 1) {
            return ['text' => 'In ' . floor($days) . ' Tagen', 'minutes' => $days * 1440];
        } else {
            return ['text' => 'Morgen', 'minutes' => 1440];
        }
    }
    
    // Wenn Fahrt heute ist
    $jetzt = time();
    $abfahrt = strtotime($heute . ' ' . $fahrt['abfahrtszeit']);
    
    // Wenn Fahrt bereits begonnen
    if (!empty($fahrt['fahrzeit_von'])) {
        return ['text' => 'Gestartet', 'minutes' => 0];
    }
    
    // Wenn Abfahrtszeit in der Vergangenheit
    if ($abfahrt < $jetzt) {
        return ['text' => 'Überfällig', 'minutes' => 0];
    }
    
    // Berechnung der verbleibenden Zeit
    $diffMinutes = round(($abfahrt - $jetzt) / 60);
    
    if ($diffMinutes < 60) {
        return ['text' => 'In ' . $diffMinutes . ' Min', 'minutes' => $diffMinutes];
    } else {
        $hours = floor($diffMinutes / 60);
        $mins = $diffMinutes % 60;
        return [
            'text' => 'In ' . $hours . ' Std' . ($mins > 0 ? ' ' . $mins . ' Min' : ''),
            'minutes' => $diffMinutes
        ];
    }
}

/**
 * Bereitet Adressdaten basierend auf DB-Werten auf.
 * 
 * @param string $ort_name Der Name des Ortes aus der DB.
 * @param array $kunde Die Kundendaten.
 * @return string Die formatierte Adresse.
 */
function formatAdresse($ort_name, $kunde) {
    if (strtolower($ort_name) !== 'kundenadresse') {
        return $ort_name;
    }
    
    if (!$kunde) return 'Kundenadresse (Kunde nicht gefunden)';
    
    if (($kunde['kundentyp'] ?? '') === 'firma') {
        $teile = [];
        if (!empty($kunde['firmenname'])) {
            $teile[] = trim($kunde['firmenname']);
        }
        if (!empty($kunde['firmenanschrift'])) {
            $teile[] = trim(str_replace(["\r", "\n"], [', ', ', '], $kunde['firmenanschrift']));
        }
        return implode(', ', array_filter($teile));
    } else {
        $teile = [];
        $strasse = trim(($kunde['strasse'] ?? '') . ' ' . ($kunde['hausnummer'] ?? ''));
        $ort = trim(($kunde['plz'] ?? '') . ' ' . ($kunde['kunde_ort'] ?? ''));
        
        if (!empty($strasse)) $teile[] = $strasse;
        if (!empty($ort)) $teile[] = $ort;
        
        return implode(', ', array_filter($teile));
    }
}

/**
 * Bereitet eine Fahrt für die Anzeige vor.
 * 
 * @param array $fahrt Die Rohdaten der Fahrt aus der DB.
 * @return array Die aufbereiteten Daten für die Anzeige.
 */
function prepareRideForDisplay($fahrt) {
    // Kundeninformationen
    if (($fahrt['kundentyp'] ?? '') === 'firma' && !empty($fahrt['firmenname'])) {
        $kunde = $fahrt['firmenname'];
        $is_firma = true;
        $ansprechpartner = trim(($fahrt['kunde_vorname'] ?? '') . ' ' . ($fahrt['kunde_nachname'] ?? ''));
    } else {
        $kunde = trim(($fahrt['kunde_vorname'] ?? '') . ' ' . ($fahrt['kunde_nachname'] ?? ''));
        $is_firma = false;
        $ansprechpartner = '';
    }
    
    // Adressinformationen
    $start = !empty($fahrt['alternative_abholadresse'])
        ? $fahrt['alternative_abholadresse']
        : formatAdresse($fahrt['ort_start_name'] ?? '', $fahrt);
    
    $ziel = !empty($fahrt['alternative_zieladresse'])
        ? $fahrt['alternative_zieladresse']
        : formatAdresse($fahrt['ort_ziel_name'] ?? '', $fahrt);
    
    // Zeitinformationen (HH:MM-Format)
    $zeit = substr($fahrt['abfahrtszeit'] ?? '', 0, 5);
    $datum = isset($fahrt['abholdatum']) ? date('d.m.Y', strtotime($fahrt['abholdatum'])) : '';
    $ist_heute = isset($fahrt['abholdatum']) && $fahrt['abholdatum'] == date('Y-m-d');
    
    // Wochentag
    $wochentage = [
        'Monday' => 'Montag', 'Tuesday' => 'Dienstag', 'Wednesday' => 'Mittwoch',
        'Thursday' => 'Donnerstag', 'Friday' => 'Freitag',
        'Saturday' => 'Samstag', 'Sunday' => 'Sonntag'
    ];
    $wochentag = isset($fahrt['abholdatum']) 
        ? ($wochentage[date('l', strtotime($fahrt['abholdatum']))] ?? '')
        : '';
    
    // Zusatzequipment
    $zusatzequipment = [];
    if (!empty($fahrt['zusatzequipment'])) {
        $decoded = json_decode($fahrt['zusatzequipment'], true);
        $zusatzequipment = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
            ? $decoded
            : [$fahrt['zusatzequipment']];
    }
    
    // Status
    $status = getFahrtStatus($fahrt);
    $verbleibende_zeit = getRemainingTime($fahrt);
    $ist_aktiv = !empty($fahrt['fahrzeit_von']) && empty($fahrt['fahrzeit_bis']);
    
    // Lohnbeträge formatieren
    // Stundenlohn ermitteln: Falls lohn_fahrt nicht gesetzt oder ≤ 0, dann nutze den Stundenlohn
    // des Nutzers (Fahrer) – Fallback auf tms_einstellungen -> mindest_stundenlohn.
    if (empty($fahrt['lohn_fahrt']) || floatval($fahrt['lohn_fahrt']) <= 0) {
        global $pdo, $user_id;
        
        // Fallback-Stundenlohn
        $lohn_fahrt = 13.82; // Standard-Stundenlohn
        
        // Versuche, den Stundenlohn des Fahrers zu ermitteln
        if (isset($pdo) && isset($fahrt['fahrer_id'])) {
            try {
                $stmt = $pdo->prepare("SELECT stundenlohn FROM nutzer WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $fahrt['fahrer_id']]);
                $driverData = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($driverData && floatval($driverData['stundenlohn']) > 0) {
                    $lohn_fahrt = floatval($driverData['stundenlohn']);
                } else {
                    // System-Einstellung für Mindestlohn abrufen
                    $stmt = $pdo->prepare("SELECT wert FROM tms_einstellungen WHERE schluessel = 'mindest_stundenlohn' LIMIT 1");
                    $stmt->execute();
                    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($setting) {
                        $lohn_fahrt = floatval($setting['wert']);
                    }
                }
            } catch (Exception $e) {
                // Bei Fehler Standard-Stundenlohn beibehalten
            }
        }
    } else {
        $lohn_fahrt = floatval($fahrt['lohn_fahrt']);
    }
    
    $lohn_fahrt_formatted = $lohn_fahrt > 0 ? number_format($lohn_fahrt, 2, ',', '.') . ' €' : '–';
    
    $lohn_auszahlbetrag = isset($fahrt['lohn_auszahlbetrag']) && floatval($fahrt['lohn_auszahlbetrag']) > 0
        ? floatval($fahrt['lohn_auszahlbetrag'])
        : 0;
    $lohn_auszahlbetrag_formatted = $lohn_auszahlbetrag > 0 ? number_format($lohn_auszahlbetrag, 2, ',', '.') . ' €' : '–';
    
    // Ergebnis zusammenbauen
    return [
        'id' => $fahrt['id'] ?? 0,
        'kunde_id' => $fahrt['kunde_id'] ?? 0,
        'rechnungsnummer' => $fahrt['rechnungsnummer'] ?? '',
        'fahrtpreis' => isset($fahrt['fahrtpreis']) ? number_format($fahrt['fahrtpreis'], 2, ',', '.') . ' €' : '–',
        'fahrtpreis_raw' => $fahrt['fahrtpreis'] ?? 0,
        'zeit' => $zeit,
        'datum' => $datum,
        'wochentag' => $wochentag,
        'ist_heute' => $ist_heute,
        'ist_aktiv' => $ist_aktiv,
        'kunde' => $kunde,
        'is_firma' => $is_firma,
        'ansprechpartner' => $ansprechpartner,
        'route' => $start . ' → ' . $ziel,
        'start' => $start,
        'ziel' => $ziel,
        'personen' => $fahrt['personenanzahl'] ?? '',
        'flugnummer' => $fahrt['flugnummer'] ?? '',
        'bemerkung' => $fahrt['fahrer_bemerkung'] ?? '',
        'dispo_bemerkung' => $fahrt['dispo_bemerkung'] ?? '',
        'kunde_bemerkung' => $fahrt['kunde_bemerkung'] ?? '',
        'abfahrtszeit_raw' => $fahrt['abfahrtszeit'] ?? '',
        'abholdatum_raw' => $fahrt['abholdatum'] ?? '',
        'zusatzequipment' => $zusatzequipment,
        'zusatzequipment_raw' => $fahrt['zusatzequipment'] ?? '',
        'fahrzeug' => $fahrt['fahrzeug_info'] ?? '',
        'status' => $status['text'],
        'status_color' => $status['color'],
        'verbleibende_zeit' => $verbleibende_zeit['text'],
        'verbleibende_minuten' => $verbleibende_zeit['minutes'],
        'fahrzeit_von' => $fahrt['fahrzeit_von'] ?? '',
        'fahrzeit_bis' => $fahrt['fahrzeit_bis'] ?? '',
        'fahrzeit_summe' => $fahrt['fahrzeit_summe'] ?? '',
        'ausgaben' => $fahrt['ausgaben'] ?? '',
        'wartezeit' => $fahrt['wartezeit'] ?? '',
        'alternative_abholadresse' => $fahrt['alternative_abholadresse'] ?? '',
        'alternative_zieladresse' => $fahrt['alternative_zieladresse'] ?? '',
        'fahrer_bemerkung' => $fahrt['fahrer_bemerkung'] ?? '',
        'lohn_fahrt' => $lohn_fahrt_formatted,
        'lohn_fahrt_raw' => $lohn_fahrt,
        'lohn_auszahlbetrag' => $lohn_auszahlbetrag_formatted,
        'lohn_auszahlbetrag_raw' => $lohn_auszahlbetrag,
        'zahlungsart' => $fahrt['zahlungsart'] ?? '',
        'hinfahrt_id' => $fahrt['hinfahrt_id'] ?? '',
        'hinfahrt_datum' => isset($fahrt['hinfahrt_datum']) ? date('d.m.Y', strtotime($fahrt['hinfahrt_datum'])) : '',
        'hinfahrt_zeit' => isset($fahrt['hinfahrt_zeit']) ? substr($fahrt['hinfahrt_zeit'], 0, 5) . ' Uhr' : '',
        'telefon' => $fahrt['telefon'] ?? '',
        'mobil' => $fahrt['mobil'] ?? '',
        'email' => $fahrt['email'] ?? ''
    ];
}

/**
 * Generiert einheitliche Datenattribute für den Modal-Aufruf.
 * 
 * @param array $ride Die aufbereiteten Fahrtdaten.
 * @return string HTML-Attributstring.
 */
function generateModalAttributes($ride) {
    $attributes = [
        'data-id' => $ride['id'],
        'data-kunde-id' => $ride['kunde_id'],
        'data-rechnungsnummer' => $ride['rechnungsnummer'],
        'data-fahrtpreis' => $ride['fahrtpreis_raw'],
        'data-is-firma' => $ride['is_firma'] ? '1' : '0',
        'data-kunde' => $ride['kunde'],
        'data-ansprechpartner' => $ride['ansprechpartner'],
        'data-telefon' => $ride['telefon'],
        'data-mobil' => $ride['mobil'],
        'data-email' => $ride['email'],
        'data-abholort' => $ride['start'],
        'data-zielort' => $ride['ziel'],
        'data-abholdatum' => $ride['abholdatum_raw'],
        'data-abfahrtszeit' => $ride['abfahrtszeit_raw'],
        'data-fahrzeit-von' => $ride['fahrzeit_von'],
        'data-fahrzeit-bis' => $ride['fahrzeit_bis'],
        'data-fahrzeit-summe' => $ride['fahrzeit_summe'],
        'data-ausgaben' => $ride['ausgaben'],
        'data-wartezeit' => $ride['wartezeit'],
        'data-dispo-bemerkung' => $ride['dispo_bemerkung'],
        'data-kunde-bemerkung' => $ride['kunde_bemerkung'],
        'data-fahrer-bemerkung' => $ride['fahrer_bemerkung'],
        'data-fahrzeug' => $ride['fahrzeug'],
        'data-personenanzahl' => $ride['personen'],
        'data-flugnummer' => $ride['flugnummer'],
        'data-zusatzequipment' => $ride['zusatzequipment_raw'],
        'data-lohn-fahrt' => $ride['lohn_fahrt_raw'],
        'data-lohn-auszahlbetrag' => $ride['lohn_auszahlbetrag_raw'],
        'data-status' => $ride['status'],
        'data-status-color' => $ride['status_color'],
        'data-ist-heute' => $ride['ist_heute'] ? '1' : '0',
        'data-zahlungsart' => $ride['zahlungsart'],
        'data-hinfahrt-id' => $ride['hinfahrt_id'],
        'data-hinfahrt-datum' => $ride['hinfahrt_datum'],
        'data-hinfahrt-zeit' => $ride['hinfahrt_zeit']
    ];
    
    $attributeString = '';
    foreach ($attributes as $key => $value) {
        $attributeString .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
    }
    
    return $attributeString;
}

/**
 * Berechnet den Lohn für eine Fahrt basierend auf Fahrzeit und Stundenlohn.
 *
 * @param string $fahrzeitVon Startzeit (Format: HH:MM:SS)
 * @param string $fahrzeitBis Endzeit (Format: HH:MM:SS)
 * @param float $stundenlohn Stundenlohn des Fahrers
 * @param int $wartezeit Wartezeit in Minuten
 * @return array ['lohn' => berechneter Lohn, 'stunden' => Arbeitsstunden, 'erklärung' => Textuelle Erklärung]
 */
function calculateRideWage($fahrzeitVon, $fahrzeitBis, $stundenlohn, $wartezeit = 0) {
    // Standardrückgabe bei fehlenden Werten
    if (empty($fahrzeitVon) || empty($fahrzeitBis)) {
        return [
            'lohn' => 0,
            'stunden' => 0,
            'erklärung' => 'Keine Fahrzeiten angegeben'
        ];
    }
    
    // Zeiten parsen
    $start = strtotime('1970-01-01 ' . $fahrzeitVon);
    $end = strtotime('1970-01-01 ' . $fahrzeitBis);
    
    // Falls die Endzeit vor der Startzeit liegt (über Mitternacht)
    if ($end < $start) {
        $end = strtotime('1970-01-02 ' . $fahrzeitBis);
    }
    
    // Differenz in Minuten berechnen
    $diffMinutes = ($end - $start) / 60;
    
    // Wartezeit hinzuaddieren (falls vorhanden)
    $totalMinutes = $diffMinutes + $wartezeit;
    
    // Umrechnung in Stunden
    $hours = $totalMinutes / 60;
    
    // Lohn berechnen
    $wage = $hours * $stundenlohn;
    
    // Erklärung generieren
    $explanation = sprintf(
        'Fahrzeit: %s - %s (%d Min), Wartezeit: %d Min, Gesamt: %.2f Std × %.2f €/h',
        substr($fahrzeitVon, 0, 5),
        substr($fahrzeitBis, 0, 5),
        $diffMinutes,
        $wartezeit,
        $hours,
        $stundenlohn
    );
    
    return [
        'lohn' => $wage,
        'stunden' => $hours,
        'erklärung' => $explanation
    ];
}
?>