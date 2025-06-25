<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verifica se l'admin è loggato
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Gestione upgrade utente a PRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET subscription_status = 'active',
                subscription_start = NOW(),
                subscription_end = DATE_ADD(NOW(), INTERVAL 1 YEAR)
            WHERE id = :user_id
        ");
        
        if ($stmt->execute([':user_id' => $user_id])) {
            $success = "Utente aggiornato a Premium con successo!";
        } else {
            $error = "Errore durante l'aggiornamento dell'utente.";
        }
    } catch (Exception $e) {
        $error = "Errore: " . $e->getMessage();
    }
}

// Gestione downgrade utente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['downgrade_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET subscription_status = 'free',
                subscription_start = NULL,
                subscription_end = NULL,
                subscription_id = NULL
            WHERE id = :user_id
        ");
        
        if ($stmt->execute([':user_id' => $user_id])) {
            $success = "Utente riportato al piano gratuito con successo!";
        } else {
            $error = "Errore durante il downgrade dell'utente.";
        }
    } catch (Exception $e) {
        $error = "Errore: " . $e->getMessage();
    }
}

// Statistiche generali
$stats = get_admin_stats($pdo);

// Lista utenti con paginazione
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($filter !== 'all') {
    $where_conditions[] = "u.subscription_status = :filter";
    $params[':filter'] = $filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Conta totale utenti
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users u $where_clause");
$count_stmt->execute($params);
$total_users = (int)$count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = max(1, ceil($total_users / $per_page));

// Recupera utenti
$query = "
    SELECT u.*, 
           COUNT(d.id) AS total_deeplinks,
           COALESCE(SUM(d.clicks), 0) AS total_clicks
    FROM users u
    LEFT JOIN deeplinks d ON u.id = d.user_id
    $where_clause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT :per_page OFFSET :offset
";

$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - DeepLink Pro</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        .admin-header .logo {
            color: white;
        }
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .admin-stat-card {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
        }
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filters input, .filters select {
            padding: 0.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
        }
        .user-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-free {
            background: #f8f9fa;
            color: #666;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }
        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .pagination a:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <header class="header admin-header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">🛡️ Admin Panel - DeepLink Pro</a>
                <div class="nav-links">
                    <span>Admin: <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                    <a href="../dashboard.php">Dashboard Utente</a>
                    <a href="logout.php">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <!-- Statistiche Admin -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <div class="stat-number"><?= $stats['total_users'] ?></div>
                    <div class="stat-label">Utenti Totali</div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-number"><?= $stats['premium_users'] ?></div>
                    <div class="stat-label">Utenti Premium</div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-number"><?= $stats['free_users'] ?></div>
                    <div class="stat-label">Utenti Gratuiti</div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-number"><?= $stats['total_deeplinks'] ?></div>
                    <div class="stat-label">Deeplink Totali</div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-number"><?= $stats['total_clicks'] ?></div>
                    <div class="stat-label">Click Totali</div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-number"><?= $stats['new_users_today'] ?></div>
                    <div class="stat-label">Nuovi Oggi</div>
                </div>
            </div>

            <!-- Gestione Utenti -->
            <div class="card">
                <h2>👥 Gestione Utenti</h2>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Filtri -->
                <form method="GET" class="filters">
                    <input type="text" name="search" placeholder="Cerca per nome o email..." 
                           value="<?= htmlspecialchars($search) ?>">
                    
                    <select name="filter">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tutti gli utenti</option>
                        <option value="free" <?= $filter === 'free' ? 'selected' : '' ?>>Solo Gratuiti</option>
                        <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Solo Premium</option>
                        <option value="expired" <?= $filter === 'expired' ? 'selected' : '' ?>>Scaduti</option>
                    </select>
                    
                    <button type="submit" class="btn btn-secondary">Filtra</button>
                    <a href="index.php" class="btn btn-link">Reset</a>
                </form>

                <!-- Tabella Utenti -->
                <div style="overflow-x: auto;">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Piano</th>
                                <th>Deeplinks</th>
                                <th>Click Totali</th>
                                <th>Registrato</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $user['subscription_status'] ?>">
                                            <?php
                                            switch($user['subscription_status']) {
                                                case 'active': echo 'Premium'; break;
                                                case 'expired': echo 'Scaduto'; break;
                                                case 'cancelled': echo 'Cancellato'; break;
                                                default: echo 'Gratuito';
                                            }
                                            ?>
                                        </span>
                                        <?php if ($user['subscription_end']): ?>
                                            <br><small style="color: #666;">
                                                Scade: <?= date('d/m/Y', strtotime($user['subscription_end'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $user['total_deeplinks'] ?></td>
                                    <td><?= $user['total_clicks'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div class="user-actions">
                                            <?php if ($user['subscription_status'] !== 'active'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="upgrade_user" 
                                                            class="btn btn-success btn-sm"
                                                            onclick="return confirm('Vuoi davvero aggiornare questo utente a Premium?')">
                                                        Upgrade PRO
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="downgrade_user" 
                                                            class="btn btn-secondary btn-sm"
                                                            onclick="return confirm('Vuoi davvero riportare questo utente al piano gratuito?')">
                                                        Downgrade
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #666;">Nessun utente trovato</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginazione -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>">« Precedente</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>">Successiva »</a>
                    <?php endif; ?>
                </div>
                
                <p style="text-align: center; color: #666; margin-top: 1rem;">
                    Pagina <?= $page ?> di <?= $total_pages ?> (<?= $total_users ?> utenti totali)
                </p>
                <?php endif; ?>
            </div>

            <!-- Statistiche Dettagliate -->
            <div class="card">
                <h2>📊 Statistiche Dettagliate</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div>
                        <h3>Crescita Utenti</h3>
                        <ul style="list-style: none; padding: 0;">
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid #eee;">
                                <strong>Oggi:</strong> <?= $stats['new_users_today'] ?> nuovi utenti
                            </li>
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid #eee;">
                                <strong>Questa settimana:</strong> <?= $stats['new_users_week'] ?> nuovi utenti
                            </li>
                            <li style="padding: 0.5rem 0;">
                                <strong>Questo mese:</strong> <?= $stats['new_users_month'] ?> nuovi utenti
                            </li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3>Performance</h3>
                        <ul style="list-style: none; padding: 0;">
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid #eee;">
                                <strong>Click oggi:</strong> <?= $stats['clicks_today'] ?>
                            </li>
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid #eee;">
                                <strong>Deeplink oggi:</strong> <?= $stats['deeplinks_today'] ?>
                            </li>
                            <li style="padding: 0.5rem 0;">
                                <strong>Media click/deeplink:</strong> <?= number_format($stats['avg_clicks_per_deeplink'], 2) ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>