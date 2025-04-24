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
require __DIR__ . '/../assets/header.php';

// Aktiviere die Fehleranzeige (nur in der Entwicklung)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Erstelle einen CSRF-Token, falls noch nicht vorhanden
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Saubere URL für Redirects
$clean_url = strtok($_SERVER["REQUEST_URI"], '?');

// Logging-Funktion
function log_action($message) {
    error_log("[" . date('Y-m-d H:i:s') . "] Debug: " . $message);
}

// Hilfsfunktion für Icons - NUR EINMAL DEKLARIERT
function getTypIcon($typ) {
    switch ($typ) {
        case 'bug': return '🐞';
        case 'hinweis': return '💡';
        case 'idee': return '🧠';
        default: return '📝';
    }
}

// Benutzerinformation aus der Session holen
$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
$user_rolle = isset($_SESSION['user']['rolle']) ? $_SESSION['user']['rolle'] : null;
$is_admin = ($user_rolle === 'admin');

// Array für Fehlermeldungen initialisieren
$messages = [
    'error' => isset($_SESSION['error']) ? $_SESSION['error'] : null,
    'success' => isset($_SESSION['success']) ? $_SESSION['success'] : null
];

// Session-Messages löschen, nachdem wir sie gespeichert haben
unset($_SESSION['error'], $_SESSION['success']);

try {
    // Verarbeite Formular-Daten
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // CSRF-Prüfung
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = "Sicherheitstoken ungültig.";
            header("Location: " . $clean_url);
            exit();
        }
    
        if ($_POST['action'] === 'add_entry' && isset($_POST['inhalt'])) {
            if (!$user_id) {
                $_SESSION['error'] = "Du musst angemeldet sein, um Einträge hinzuzufügen.";
            } else {
                $inhalt = trim($_POST['inhalt']);
                $typ = isset($_POST['typ']) ? $_POST['typ'] : 'idee';
                
                if (empty($inhalt)) {
                    $_SESSION['error'] = "Der Inhalt darf nicht leer sein.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO forum_ideen (nutzer_id, inhalt, typ) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $inhalt, $typ]);
                    $_SESSION['success'] = "Dein Eintrag wurde erfolgreich hinzugefügt.";
                }
            }
        } elseif ($_POST['action'] === 'add_comment' && isset($_POST['inhalt']) && isset($_POST['eintrag_id'])) {
            if (!$user_id) {
                $_SESSION['error'] = "Du musst angemeldet sein, um Kommentare hinzuzufügen.";
            } else {
                $inhalt = trim($_POST['inhalt']);
                $eintrag_id = intval($_POST['eintrag_id']);
                
                if (empty($inhalt)) {
                    $_SESSION['error'] = "Der Kommentar darf nicht leer sein.";
                } else {
                    // Prüfen, ob die Idee existiert
                    $check_idee = $pdo->prepare("SELECT id FROM forum_ideen WHERE id = ?");
                    $check_idee->execute([$eintrag_id]);
                    
                    if ($check_idee->rowCount() > 0) {
                        $stmt = $pdo->prepare("INSERT INTO forum_kommentare (eintrag_id, nutzer_id, kommentar) VALUES (?, ?, ?)");
                        $stmt->execute([$eintrag_id, $user_id, $inhalt]);
                        $_SESSION['success'] = "Dein Kommentar wurde erfolgreich hinzugefügt.";
                    } else {
                        $_SESSION['error'] = "Die Idee existiert nicht.";
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete_comment' && isset($_POST['comment_id'])) {
            if (!$user_id) {
                $_SESSION['error'] = "Du musst angemeldet sein, um Kommentare zu löschen.";
            } else {
                $comment_id = intval($_POST['comment_id']);
                
                // Prüfen, ob der Benutzer der Ersteller ist oder Admin-Rechte hat
                $stmt = $pdo->prepare("SELECT nutzer_id FROM forum_kommentare WHERE id = ?");
                $stmt->execute([$comment_id]);
                $kommentar = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($kommentar && ($kommentar['nutzer_id'] == $user_id || $is_admin)) {
                    $stmt = $pdo->prepare("DELETE FROM forum_kommentare WHERE id = ?");
                    $stmt->execute([$comment_id]);
                    $_SESSION['success'] = "Der Kommentar wurde erfolgreich gelöscht.";
                } else {
                    $_SESSION['error'] = "Du bist nicht berechtigt, diesen Kommentar zu löschen.";
                }
            }
        } elseif ($_POST['action'] === 'delete_entry' && isset($_POST['id'])) {
            if (!$user_id) {
                $_SESSION['error'] = "Du musst angemeldet sein, um Einträge zu löschen.";
            } else {
                $id = intval($_POST['id']);
                
                // Prüfe, ob der Benutzer der Ersteller ist oder Admin-Rechte hat
                $stmt = $pdo->prepare("SELECT nutzer_id FROM forum_ideen WHERE id = ?");
                $stmt->execute([$id]);
                $eintrag = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($eintrag && ($eintrag['nutzer_id'] == $user_id || $is_admin)) {
                    $stmt = $pdo->prepare("DELETE FROM forum_ideen WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success'] = "Der Eintrag wurde erfolgreich gelöscht.";
                } else {
                    $_SESSION['error'] = "Du bist nicht berechtigt, diesen Eintrag zu löschen.";
                }
            }
        } elseif ($_POST['action'] === 'mark_done' && isset($_POST['id'])) {
            if (!$user_id) {
                $_SESSION['error'] = "Du musst angemeldet sein, um Einträge zu bearbeiten.";
            } else {
                $id = intval($_POST['id']);
                // Prüfe, ob der Eintrag existiert
                $stmt = $pdo->prepare("SELECT erledigt FROM forum_ideen WHERE id = ?");
                $stmt->execute([$id]);
                $eintrag = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($eintrag) {
                    if ($eintrag['erledigt']) {
                        $_SESSION['error'] = "Der Eintrag wurde bereits als erledigt markiert.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE forum_ideen SET erledigt = 1 WHERE id = ?");
                        $stmt->execute([$id]);
                        $_SESSION['success'] = "Der Eintrag wurde als erledigt markiert.";
                    }
                } else {
                    $_SESSION['error'] = "Der Eintrag existiert nicht.";
                }
            }
        }
    
        // Zur sauberen URL zurückleiten mittels JavaScript statt header()
        echo '<script>window.location.href = "' . $clean_url . '";</script>';
        exit();
    }
    
    // Forum-Tab Auswahl: 'offen' (Standard) oder 'erledigt'
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'offen';
    
    // Einträge abrufen
    $eintraege = [];
    
    // Prüfe, ob die Tabelle 'nutzer' die Spalte 'name' enthält
    $checkColumn = $pdo->query("SHOW COLUMNS FROM nutzer LIKE 'name'");
    $nameColumnExists = $checkColumn->rowCount() > 0;
    
    // Verwende 'name' oder 'email' je nach Verfügbarkeit
    $userNameColumn = $nameColumnExists ? 'name' : 'email';
    
    // Basis-SQL
    $sql = "
        SELECT 
            f.*, 
            n.$userNameColumn as nutzer_name
        FROM 
            forum_ideen f
            JOIN nutzer n ON f.nutzer_id = n.id
    ";
    // Filter je nach Tab-Auswahl
    if ($tab === 'offen') {
        $sql .= " WHERE f.erledigt = 0 ";
    } elseif ($tab === 'erledigt') {
        $sql .= " WHERE f.erledigt = 1 ";
    }
    $sql .= " ORDER BY f.created_at DESC";
    
    $stmt = $pdo->query($sql);
    $eintraege = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kommentare für alle Einträge abrufen
    $kommentare = [];
    
    if (!empty($eintraege)) {
        $ideen_ids = array_column($eintraege, 'id');
        $placeholders = implode(',', array_fill(0, count($ideen_ids), '?'));
        
        $kommentar_sql = "
            SELECT 
                k.*,
                n.$userNameColumn as nutzer_name
            FROM 
                forum_kommentare k
                JOIN nutzer n ON k.nutzer_id = n.id
            WHERE 
                k.eintrag_id IN ($placeholders)
            ORDER BY 
                k.created_at ASC
        ";
        
        try {
            $stmt = $pdo->prepare($kommentar_sql);
            $stmt->execute($ideen_ids);
            $alle_kommentare = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Gruppiere Kommentare nach eintrag_id
            foreach ($alle_kommentare as $kommentar) {
                $kommentare[$kommentar['eintrag_id']][] = $kommentar;
            }
        } catch (Exception $e) {
            log_action("Fehler beim Abrufen der Kommentare: " . $e->getMessage());
            // Weiter mit leerer Kommentarliste
        }
    }
} catch (Exception $e) {
    // Speichere Fehler für spätere Anzeige
    $error_message = 'Fehler: ' . $e->getMessage();
    log_action($error_message);
}
?>

<div class="container mt-3">
    <?php if (isset($messages['error']) && $messages['error']): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($messages['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($messages['success']) && $messages['success']): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($messages['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
</div>

<!-- Schnellbuttons -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Schnellzugriff</h2>
        <div>
            <!-- Schnellbutton: Kunde anlegen -->
            <a href="kunde_formular.php" class="btn btn-primary me-2">
                <i class="fas fa-user-plus me-1"></i> Kunde anlegen
            </a>
            <!-- Schnellbutton: Fahrt anlegen -->
            <a href="fahrt_formular.php" class="btn btn-success">
                <i class="fas fa-car me-1"></i> Fahrt anlegen
            </a>
        </div>
    </div>
</div>

<div class="container mt-5">
    <h1 class="fw-bold">Admin-Dashboard</h1>
    <p class="lead">Willkommen im Admin-Bereich von Mini-TMS.</p>
    <p>Hier könnten Cards für verschiedene Teilbereiche stehen, etwa Nutzerverwaltung, Fahrtenstatistiken und mehr.</p>
    
    <div class="row">
        <!-- Nutzerverwaltung Card -->
        <div class="col-md-4">
            <div class="card shadow-sm dashboard-card">
                <div class="card-body">
                    <h5 class="card-title">👥 Nutzerverwaltung</h5>
                    <p class="card-text">Verwalten Sie Nutzer, sperren oder schalten Sie diese frei.</p>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="nutzerverwaltung.php" class="btn btn-primary">🔍 Verwaltung öffnen</a>
                </div>
            </div>
        </div>
        
        <!-- Fahrtenstatistiken Card -->
        <div class="col-md-4">
            <div class="card shadow-sm dashboard-card">
                <div class="card-body">
                    <h5 class="card-title">📊 Fahrtenstatistiken</h5>
                    <p class="card-text">Erhalten Sie Einsicht in Fahrten und deren Statistiken.</p>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="fahrtenstatistiken.php" class="btn btn-primary">📈 Statistiken ansehen</a>
                </div>
            </div>
        </div>
        
        <!-- Einstellungen Card -->
        <div class="col-md-4">
            <div class="card shadow-sm dashboard-card">
                <div class="card-body">
                    <h5 class="card-title">⚙️ Einstellungen</h5>
                    <p class="card-text">Verwalten Sie allgemeine Einstellungen für das Mini-TMS.</p>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="fahrt_einstellungen.php" class="btn btn-primary">⚙️ Einstellungen bearbeiten</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- FORUM-BEREICH -->
    <div class="forum-container">
        <div class="card shadow-sm mt-5">
            <div class="card-header bg-light">
                <h3 class="card-title mb-0">🧠 Brainstorming & Ideen & Bugs</h3>
            </div>
            <div class="card-body">
                <!-- Tab-Navigation für Forum -->
                <div class="forum-tabs mb-3">
                    <ul class="nav">
                        <li class="nav-item">
                            <a class="nav-link <?= ($tab === 'offen' ? 'active' : '') ?>" href="?tab=offen">Offen</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($tab === 'erledigt' ? 'active' : '') ?>" href="?tab=erledigt">Erledigt</a>
                        </li>
                    </ul>
                </div>
                
                <!-- Formular zum Hinzufügen neuer Einträge (nur im Tab "Offen") -->
                <?php if ($tab === 'offen'): ?>
                <form method="post" class="forum-entry-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="add_entry">
                    
                    <div class="input-group mb-3">
                        <!-- Das Select erhält durch die CSS-Regel einen rechten Abstand -->
                        <select name="typ" class="form-select">
                            <option value="idee">🧠 Idee</option>
                            <option value="bug">🐞 Bug</option>
                            <option value="hinweis">💡 Hinweis</option>
                        </select>
                        <input type="text" name="inhalt" class="form-control" placeholder="Neuen Eintrag hinzufügen..." required <?= !$user_id ? 'disabled' : '' ?>>
                        <button type="submit" class="btn btn-success" <?= !$user_id ? 'disabled' : '' ?>>Hinzufügen</button>
                    </div>
                    <?php if (!$user_id): ?>
                        <small class="text-muted">Du musst angemeldet sein, um Einträge hinzuzufügen.</small>
                    <?php endif; ?>
                </form>
                <?php endif; ?>
                
                <!-- Liste der vorhandenen Einträge -->
                <?php if (empty($eintraege)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> Noch keine Einträge vorhanden.
                    </div>
                <?php else: ?>
                    <ul class="list-unstyled forum-list">
    <?php foreach ($eintraege as $eintrag): ?>
        <li class="forum-entry mb-3 <?= ($eintrag['erledigt'] ? 'done' : '') ?>">
            <div class="forum-entry-header">
                <div class="forum-entry-content">
                    <span class="typ-icon">
                        <?= getTypIcon($eintrag['typ']) ?> 
                    </span>
                    <strong><?= htmlspecialchars($eintrag['inhalt']) ?></strong>
                    <small class="ms-2 d-inline-block">
                        von <?= htmlspecialchars($eintrag['nutzer_name']) ?> 
                        am <?= date('d.m.Y H:i', strtotime($eintrag['created_at'])) ?>
                    </small>
                </div>
                
                <div class="forum-entry-actions">
                    <?php if ($user_id): ?>
                        <?php if (!$eintrag['erledigt'] && $tab === 'offen'): ?>
                            <!-- Button um den Eintrag als erledigt zu markieren -->
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="mark_done">
                                <input type="hidden" name="id" value="<?= $eintrag['id'] ?>">
                                <button type="submit" class="btn forum-btn-done btn-sm" 
                                        onclick="return confirm('Eintrag als erledigt markieren?');">
                                    Erledigt
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($eintrag['nutzer_id'] == $user_id || $is_admin): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="delete_entry">
                                <input type="hidden" name="id" value="<?= $eintrag['id'] ?>">
                                <button type="submit" class="btn forum-btn-delete btn-sm" 
                                        onclick="return confirm('Eintrag wirklich löschen?');">
                                    Löschen
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Kommentare anzeigen -->
            <?php if (isset($kommentare[$eintrag['id']]) && !empty($kommentare[$eintrag['id']])): ?>
                <div class="comments-container mb-3">
                    <div class="small fw-bold mb-2">Kommentare:</div>
                    <?php foreach ($kommentare[$eintrag['id']] as $kommentar): ?>
                        <div class="forum-comment p-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <p class="mb-0">
                                    <?= htmlspecialchars($kommentar['kommentar']) ?>
                                    <small class="ms-2 text-muted">
                                        - <?= htmlspecialchars($kommentar['nutzer_name']) ?> 
                                        (<?= date('d.m.Y H:i', strtotime($kommentar['created_at'])) ?>)
                                    </small>
                                </p>
                                <?php if ($user_id && ($kommentar['nutzer_id'] == $user_id || $is_admin)): ?>
                                    <form method="post" class="d-inline ms-2">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="delete_comment">
                                        <input type="hidden" name="comment_id" value="<?= $kommentar['id'] ?>">
                                        <button type="submit" class="btn forum-btn-delete btn-sm" 
                                                onclick="return confirm('Kommentar wirklich löschen?');">
                                            &times;
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Kommentar-Formular (nur im Tab "Offen") -->
            <?php if ($user_id && $tab === 'offen'): ?>
                <div class="forum-entry-form ms-4">
                    <form method="post" class="d-flex">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="eintrag_id" value="<?= $eintrag['id'] ?>">
                        <input type="text" name="inhalt" class="form-control form-control-sm me-2" placeholder="Kommentar hinzufügen..." required>
                        <button type="submit" class="btn forum-btn-comment btn-sm">Kommentieren</button>
                    </form>
                </div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../assets/footer.php';
// Sende gesamten gepufferten Inhalt zum Browser
ob_end_flush();
?>
