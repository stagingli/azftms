<?php
// app/helpers.php

/**
 * Formatiert eine Zeit als "H:i".
 *
 * @param string $timeStr
 * @return string
 */
function formatTime($timeStr) {
    return date('H:i', strtotime($timeStr));
}

/**
 * Formatiert einen Betrag als Währung im deutschen Format.
 *
 * @param float $amount
 * @param string $currency
 * @return string
 */
function formatCurrency($amount, $currency = '€') {
    // Deutsche Formatierung: 2 Dezimalstellen, Dezimaltrennzeichen: Komma, Tausendertrennzeichen: Punkt
    return number_format($amount, 2, ',', '.') . ' ' . $currency;
}

/**
 * Konvertiert eine Dezimalzahl in ein gültiges Float-Format.
 * Unterstützt sowohl Komma als auch Punkt als Dezimaltrennzeichen.
 *
 * @param string $numberStr
 * @return float
 */
function parseDecimalNumber($numberStr) {
    // Entferne Tausendertrennzeichen (Punkte bei deutschen Zahlen)
    $numberStr = str_replace('.', '', $numberStr);

    // Ersetze Komma durch Punkt für die PHP-Verarbeitung
    $numberStr = str_replace(',', '.', $numberStr);

    // Konvertiere zu Float
    return floatval($numberStr);
}