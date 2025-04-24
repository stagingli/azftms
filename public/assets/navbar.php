<?php  
$eingeloggt = $GLOBALS['user_is_logged_in'] ?? (isset($_SESSION["user"]) && !empty($_SESSION["user"]));
$rollen = [];

// Dynamically add roles based on folder structure
$baseDir = __DIR__ . '/../../';
$roleFolders = ['admin', 'fahrer', 'neueruser'];

foreach ($roleFolders as $roleFolder) {
    if (is_dir($baseDir . $roleFolder)) {
        $rollen[] = $roleFolder;
    }
}

if ($eingeloggt) {
    if (isset($_SESSION["user"]["rollen"]) && is_array($_SESSION["user"]["rollen"])) {
        $rollen = $_SESSION["user"]["rollen"];
    } elseif (isset($_SESSION["user"]["rolle"])) {
        $rollen[] = $_SESSION["user"]["rolle"];
    }

    if (!isset($_SESSION["user"]["id"])) {
        writeLog("Nutzer-Session ohne ID gefunden, setze eingeloggt=false", 'WARNING', DEBUGGING_LOG_FILE);
        $eingeloggt = false;
        $rollen = [];
    }
}

$hat_rolle_admin = in_array("admin", $rollen);
$hat_rolle_fahrer = in_array("fahrer", $rollen);
$hat_rolle_neu = in_array("neu", $rollen);
$username = $_SESSION["user"]["name"] ?? "";
?>

<!-- Custom Style für kompaktere Navbar -->
<style>
    .navbar-nav .nav-link {
        padding-left: 0.6rem;
        padding-right: 0.6rem;
        font-size: 0.9rem;
    }

    .navbar-nav .nav-link i {
        font-size: 0.85rem;
    }

    .navbar {
        padding-top: 0.4rem;
        padding-bottom: 0.4rem;
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/index.php">
            <i class="fas fa-plane me-2"></i><?= htmlspecialchars($appName) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Navigation umschalten">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (!$eingeloggt): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php">
                            <i class="fas fa-home me-1"></i>Startseite
                        </a>
                    </li>
                <?php else: ?>
                    <?php if ($hat_rolle_admin): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/dashboard_admin.php">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/fahrten_liste.php">
                                <i class="fas fa-route me-1"></i>Fahrten
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/kundenverwaltung.php">
                                <i class="fas fa-users me-1"></i>Kunden
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/kunde_formular.php">
                                <i class="fas fa-user-plus me-1"></i>Kunde anlegen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/fahrt_formular.php">
                                <i class="fas fa-plus-circle me-1"></i>Fahrt anlegen
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cogs me-1"></i>Administration
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item" href="/admin/nutzerverwaltung.php"><i class="fas fa-users-cog me-1"></i>Nutzerverwaltung</a></li>
                                <li><a class="dropdown-item" href="/admin/log_viewer.php"><i class="fas fa-clipboard-list me-1"></i>Logs</a></li>
                                <li><a class="dropdown-item" href="/admin/admin_mailer.php"><i class="fas fa-envelope me-1"></i>Admin Mailer</a></li>
                                <li><a class="dropdown-item" href="/admin/email_templates.php"><i class="fas fa-envelope-open-text me-1"></i>E-Mail-Templates</a></li>
                                <li><a class="dropdown-item" href="/admin/admin_fahrer_verfuegbarkeit.php"><i class="fas fa-calendar-check me-1"></i>Verfügbarkeit</a></li>
                                <li><a class="dropdown-item" href="/admin/system_einstellungen.php"><i class="fas fa-sliders-h me-1"></i>Systemeinstellungen</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php if ($hat_rolle_fahrer): ?>
                        <?php if ($hat_rolle_admin): ?>
                            <li class="nav-item">
                                <span class="nav-link">|</span>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/fahrer/dashboard_fahrer.php">
                                <i class="fas fa-tachometer-alt me-1"></i>Fahrer-Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/fahrer/fahrten.php">
                                <i class="fas fa-route me-1"></i>Meine Fahrten
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/fahrer/fahrer_verfuegbarkeit.php">
                                <i class="fas fa-calendar-check me-1"></i>Verfügbarkeit
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($hat_rolle_neu): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/neueruser/dashboard_neuer_user.php">
                                <i class="fas fa-hourglass-half me-1"></i>Warte auf Freigabe
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <?php if ($eingeloggt): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($username) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="/auth/profil_bearbeiten.php"><i class="fas fa-id-card me-1"></i>Profil bearbeiten</a></li>
                            <li>
                                <form action="/auth/logout.php" method="POST" id="logoutForm">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>