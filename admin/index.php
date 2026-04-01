<?php
/**
 * ILDEN KI Consulting — Admin Dashboard
 * Alle Anfragen anzeigen, filtern, als gelesen markieren
 */
session_start();
require_once __DIR__ . '/../backend/config.php';

// --- Login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_auth'] = true;
    } else {
        $loginError = 'Falsches Passwort';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['admin_auth'])) {
    showLogin($loginError ?? null);
    exit;
}

// --- Aktionen ---
$db = new SQLite3(DB_PATH);

// Als gelesen markieren
if (isset($_GET['read'])) {
    $id = (int) $_GET['read'];
    $stmt = $db->prepare("UPDATE anfragen SET gelesen = 1 WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    header('Location: index.php');
    exit;
}

// Status ändern
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $status = $_GET['status'];
    $allowed = ['neu', 'in_bearbeitung', 'kontaktiert', 'abgeschlossen', 'abgelehnt'];
    if (in_array($status, $allowed)) {
        $stmt = $db->prepare("UPDATE anfragen SET status = :status WHERE id = :id");
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
    }
    header('Location: index.php');
    exit;
}

// Löschen
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM anfragen WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    header('Location: index.php');
    exit;
}

// --- Daten laden ---
$filter = $_GET['filter'] ?? 'alle';
$where = '';
if ($filter === 'neu') $where = "WHERE status = 'neu'";
elseif ($filter === 'in_bearbeitung') $where = "WHERE status = 'in_bearbeitung'";
elseif ($filter === 'kontaktiert') $where = "WHERE status = 'kontaktiert'";
elseif ($filter === 'abgeschlossen') $where = "WHERE status = 'abgeschlossen'";
elseif ($filter === 'ungelesen') $where = "WHERE gelesen = 0";

$anfragen = [];
$result = $db->query("SELECT * FROM anfragen $where ORDER BY erstellt_am DESC");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $anfragen[] = $row;
}

// Statistiken
$stats = [
    'total' => $db->querySingle("SELECT COUNT(*) FROM anfragen"),
    'neu' => $db->querySingle("SELECT COUNT(*) FROM anfragen WHERE status = 'neu'"),
    'ungelesen' => $db->querySingle("SELECT COUNT(*) FROM anfragen WHERE gelesen = 0"),
    'diese_woche' => $db->querySingle("SELECT COUNT(*) FROM anfragen WHERE erstellt_am >= datetime('now', '-7 days')"),
];

$db->close();
showDashboard($anfragen, $stats, $filter);

// ===================================================================
// VIEWS
// ===================================================================

function showLogin($error = null) { ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — ILDEN KI Consulting</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #0b0f18; color: #e8e6e1; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box { background: #131a28; border: 1px solid rgba(255,255,255,0.06); border-radius: 20px; padding: 48px; width: 100%; max-width: 400px; text-align: center; }
        .login-box h1 { font-size: 1.5rem; margin-bottom: 8px; }
        .login-box p { color: #9aa3b4; font-size: 0.9rem; margin-bottom: 32px; }
        .login-box input { width: 100%; padding: 14px 16px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.08); background: #0b0f18; color: #e8e6e1; font-size: 0.9rem; margin-bottom: 16px; outline: none; }
        .login-box input:focus { border-color: #4a90d9; box-shadow: 0 0 0 3px rgba(74,144,217,0.1); }
        .login-box button { width: 100%; padding: 14px; border-radius: 60px; border: none; background: #fff; color: #1a2744; font-weight: 600; font-size: 0.9rem; cursor: pointer; }
        .login-box button:hover { box-shadow: 0 8px 24px rgba(255,255,255,0.1); }
        .error { color: #ef4444; font-size: 0.85rem; margin-bottom: 16px; }
        .logo { font-weight: 800; font-size: 1.3rem; margin-bottom: 24px; }
        .logo span { color: #4a90d9; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">IL<span>DEN</span> Admin</div>
        <h1>Dashboard Login</h1>
        <p>Zugang zum Anfragen-Management</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <input type="password" name="password" placeholder="Admin-Passwort" required autofocus>
            <button type="submit">Anmelden</button>
        </form>
    </div>
</body>
</html>
<?php }

function showDashboard($anfragen, $stats, $filter) {
    $statusColors = [
        'neu' => '#4a90d9',
        'in_bearbeitung' => '#f59e0b',
        'kontaktiert' => '#8b5cf6',
        'abgeschlossen' => '#10b981',
        'abgelehnt' => '#ef4444',
    ];
    $statusLabels = [
        'neu' => 'Neu',
        'in_bearbeitung' => 'In Bearbeitung',
        'kontaktiert' => 'Kontaktiert',
        'abgeschlossen' => 'Abgeschlossen',
        'abgelehnt' => 'Abgelehnt',
    ];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — ILDEN KI Consulting</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #0b0f18; color: #e8e6e1; line-height: 1.5; }
        .header { background: #131a28; border-bottom: 1px solid rgba(255,255,255,0.06); padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .header .logo { font-weight: 800; font-size: 1.1rem; } .header .logo span { color: #4a90d9; }
        .header nav { display: flex; gap: 16px; align-items: center; }
        .header nav a { color: #9aa3b4; font-size: 0.85rem; text-decoration: none; } .header nav a:hover { color: #fff; }
        .main { max-width: 1200px; margin: 0 auto; padding: 32px; }

        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: #131a28; border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 24px; text-align: center; }
        .stat-card .num { font-size: 2rem; font-weight: 800; color: #4a90d9; }
        .stat-card .label { font-size: 0.8rem; color: #9aa3b4; margin-top: 4px; }

        .filters { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .filters a { padding: 8px 16px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; text-decoration: none; color: #9aa3b4; background: #131a28; border: 1px solid rgba(255,255,255,0.06); }
        .filters a.active { background: #4a90d9; color: #fff; border-color: #4a90d9; }
        .filters a:hover { border-color: rgba(255,255,255,0.15); }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 16px; font-size: 0.75rem; font-weight: 700; color: #5a6577; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 1px solid rgba(255,255,255,0.06); }
        td { padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 0.88rem; vertical-align: top; }
        tr:hover td { background: rgba(74,144,217,0.03); }
        tr.ungelesen td { border-left: 3px solid #4a90d9; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 600; }
        .actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .actions a { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; text-decoration: none; color: #9aa3b4; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); }
        .actions a:hover { background: rgba(74,144,217,0.1); color: #fff; }
        .actions a.danger:hover { background: rgba(239,68,68,0.1); color: #ef4444; }

        select.status-select { background: #0b0f18; color: #e8e6e1; border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; padding: 4px 8px; font-size: 0.78rem; cursor: pointer; }

        .empty { text-align: center; padding: 64px; color: #5a6577; }
        .detail-text { color: #9aa3b4; font-size: 0.82rem; max-width: 300px; }

        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr 1fr; }
            .main { padding: 16px; }
            .header { padding: 12px 16px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">IL<span>DEN</span> Admin</div>
        <nav>
            <a href="../index.html">Website</a>
            <a href="?logout=1">Abmelden</a>
        </nav>
    </div>

    <div class="main">
        <div class="stats-row">
            <div class="stat-card"><div class="num"><?= $stats['total'] ?></div><div class="label">Gesamt</div></div>
            <div class="stat-card"><div class="num"><?= $stats['neu'] ?></div><div class="label">Neue Anfragen</div></div>
            <div class="stat-card"><div class="num"><?= $stats['ungelesen'] ?></div><div class="label">Ungelesen</div></div>
            <div class="stat-card"><div class="num"><?= $stats['diese_woche'] ?></div><div class="label">Diese Woche</div></div>
        </div>

        <div class="filters">
            <a href="?filter=alle" class="<?= $filter === 'alle' ? 'active' : '' ?>">Alle</a>
            <a href="?filter=neu" class="<?= $filter === 'neu' ? 'active' : '' ?>">Neu</a>
            <a href="?filter=ungelesen" class="<?= $filter === 'ungelesen' ? 'active' : '' ?>">Ungelesen</a>
            <a href="?filter=in_bearbeitung" class="<?= $filter === 'in_bearbeitung' ? 'active' : '' ?>">In Bearbeitung</a>
            <a href="?filter=kontaktiert" class="<?= $filter === 'kontaktiert' ? 'active' : '' ?>">Kontaktiert</a>
            <a href="?filter=abgeschlossen" class="<?= $filter === 'abgeschlossen' ? 'active' : '' ?>">Abgeschlossen</a>
        </div>

        <?php if (empty($anfragen)): ?>
            <div class="empty">Keine Anfragen gefunden.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Datum</th>
                        <th>Name</th>
                        <th>Unternehmen</th>
                        <th>Anliegen</th>
                        <th>Budget</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($anfragen as $a): ?>
                    <tr class="<?= $a['gelesen'] ? '' : 'ungelesen' ?>">
                        <td><?= $a['id'] ?></td>
                        <td style="white-space:nowrap"><?= date('d.m.Y', strtotime($a['erstellt_am'])) ?><br><span style="color:#5a6577;font-size:0.75rem"><?= date('H:i', strtotime($a['erstellt_am'])) ?></span></td>
                        <td>
                            <strong><?= htmlspecialchars($a['vorname'] . ' ' . $a['nachname']) ?></strong><br>
                            <span style="color:#9aa3b4;font-size:0.8rem"><?= htmlspecialchars($a['email']) ?></span>
                            <?php if ($a['position']): ?><br><span style="color:#5a6577;font-size:0.75rem"><?= htmlspecialchars($a['position']) ?></span><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($a['unternehmen'] ?: '–') ?></td>
                        <td><?= htmlspecialchars($a['anliegen']) ?></td>
                        <td style="white-space:nowrap"><?= htmlspecialchars($a['budget'] ?: '–') ?></td>
                        <td>
                            <span class="badge" style="background: <?= $statusColors[$a['status']] ?? '#5a6577' ?>20; color: <?= $statusColors[$a['status']] ?? '#5a6577' ?>">
                                <?= $statusLabels[$a['status']] ?? $a['status'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <?php if (!$a['gelesen']): ?>
                                    <a href="?read=<?= $a['id'] ?>">Gelesen</a>
                                <?php endif; ?>
                                <a href="?id=<?= $a['id'] ?>&status=in_bearbeitung">Bearbeiten</a>
                                <a href="?id=<?= $a['id'] ?>&status=kontaktiert">Kontaktiert</a>
                                <a href="?id=<?= $a['id'] ?>&status=abgeschlossen">Fertig</a>
                                <a href="?delete=<?= $a['id'] ?>" class="danger" onclick="return confirm('Anfrage #<?= $a['id'] ?> wirklich löschen?')">Löschen</a>
                            </div>
                        </td>
                    </tr>
                    <?php if ($a['nachricht']): ?>
                    <tr>
                        <td></td>
                        <td colspan="7"><div class="detail-text"><?= nl2br(htmlspecialchars($a['nachricht'])) ?></div></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php }
