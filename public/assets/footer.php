<?php
// footer.php
// Da config.php bereits im Header eingebunden wird, brauchen wir hier keine erneute Einbindung.
// Wir gehen davon aus, dass die Funktion getSystemSetting() verfügbar ist.

$footer_text = getSystemSetting('footer_text', '© ' . date('Y') . ' Mini-TMS - Alle Rechte vorbehalten');
?>

</div> <!-- Schließt den Container aus dem Hauptinhalt -->

<footer class="footer bg-dark text-white text-center py-3 mt-4">
    <div class="container">
        <p class="mb-1"><?= htmlspecialchars($footer_text) ?></p>
        <p>
            <a href="#" class="text-white">Impressum</a> |
            <a href="#" class="text-white">Datenschutz</a>
        </p>
    </div>
</footer>

<!-- Falls nötig: Bootstrap JS oder weitere Skripte hier -->
</body>
</html>