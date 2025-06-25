<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verifica se l'admin è loggato
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Gestione azioni admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upgrade_user_manual'])) {
        $user_id = (int)$_POST['user_id'];
        $duration_months = (int)$_POST['duration_months'];
        $notes = trim($_POST['notes']);
        
        try {
            $end_date = date('Y-m-d H:i:s', strtotime("+{$duration_months} months"));
            
            $stmt = $pdo->prepare("
                CALL UpdateSubscriptionSimple(?, 'active', ?, 'admin', 'manual_upgrade', ?)
            ");
            
            $admin_notes = "Upgrade manuale da admin. Durata: {$duration_months} mesi. Note: {$notes}";
            
            if ($stmt->execute([$user_id, $end_date, $admin_notes])) {
                $success = "Utente aggiornato a Premium manualmente con successo! Scadenza: " . date('d/m/Y', strtotime($end_date));
            } else {
                $error = "Errore durante l'aggiornamento dell'utente.";
            }
        } catch (Exception $e) {
            $error = "Errore: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['downgrade_user_manual'])) {
        $user_id = (int)$_POST['user_id'];
        $notes = trim($_POST['notes']);
        
        try {
            $stmt = $pdo->prepare("
                CALL UpdateSubscriptionSimple(?, 'free', NULL, 'admin', 'manual_downgrade', ?)
            ");
            
            $admin_notes = "Downgrade manuale da admin. Note: {$notes}";
            
            if ($stmt->execute([$user_id, $admin_notes])) {
                $success = "Utente riportato al piano gratuito con successo!";
            } else {
                $error = "Errore durante il downgrade dell'utente.";
            }
        } catch (Exception $e) {
            $error = "Errore: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['extend_subscription'])) {
        $user_id = (int)$_POST['user_id'];
        $extend_months = (int)$_POST['extend_months'];
        $notes = trim($_POST['notes']);
        
        try {
            // Ottieni la data di scadenza attuale
            $stmt = $pdo->prepare("SELECT subscription_end FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($current_user && $current_user['subscription_end'] && strtotime($current_user['subscription_end']) > time()) {
                // Estendi dalla data di scadenza attuale
                $new_end_date = date('Y-m-d H:i:s', strtotime($current_user['subscription_end'] . " +{$extend_months} months"));
            } else {
                // Inizia da ora
                $new_end_date = date('Y-m-d H:i:s', strtotime("+{$extend_months} months"));
            }
            
            $stmt = $pdo->prepare("
                CALL UpdateSubscriptionSimple(?, 'active', ?, 'admin', 'subscription_renewed', ?)
            ");
            
            $admin_notes = "Estensione manuale da admin. Durata aggiunta: {$extend_months} mesi. Note: {$notes}";
            
            if ($stmt->execute([$user_id, $new_end_date, $admin_notes])) {
                $success = "Abbonamento esteso con successo! Nuova scadenza: " . date('d/m/Y', strtotime($new_end_date));
            } else {
                $error = "Errore durante l'estensione dell'abbonamento.";
            }
        } catch (Exception $e) {
            $error = "Errore: " . $e->getMessage();
        }
    }
}

// Statistiche abbonamenti
$stmt = $pdo->query("SELECT * FROM subscription_stats_simple");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Abbonamenti in scadenza
$stmt = $pdo->query("SELECT * FROM expiring_subscriptions_simple ORDER BY days_until_expiry ASC LIMIT 10");
$expiring_subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Eventi recenti
$stmt = $pdo->query("
    SELECT ssl.*, u.name, u.email 
    FROM simple_subscription_logs ssl 
    JOIN users u ON ssl.user_id = u.id 
    ORDER BY ssl.created_at DESC 
    LIMIT 20
");
$recent_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Abbonamenti Semplificata - Admin Panel</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        .admin-header .logo {
            color: white;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
        }
        .event-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .event-subscription_created { background: #d4edda; color: #155724; }
        .event-subscription_renewed { background: #cce7ff; color: #004085; }
        .event-subscription_expired { background: #e2e3e5; color: #383d41; }
        .event-manual_upgrade { background: #d1ecf1; color: #0c5460; }
        .event-manual_downgrade { background: #f8d7da; color: #721c24; }
        .manual-actions {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .verified-badge {
            background: #28a745;
            color: white;
            padding: 0.125rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <header class="header admin-header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">🛡️ Admin Panel - Abbonamenti</a>
                <div class="nav-links">
                    <a href="index.php">Dashboard</a>
                    <a href="subscription_management_simple.php">Abbonamenti</a>
                    <a href="logout.php">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Statistiche Abbonamenti -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_users'] ?></div>
                    <div class="stat-label">Utenti Totali</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['active_subscriptions'] ?></div>
                    <div class="stat-label">Abbonamenti Attivi</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['expired_subscriptions'] ?></div>
                    <div class="stat-label">Scaduti</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['free_users'] ?></div>
                    <div class="stat-label">Utenti Gratuiti</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['manually_verified_subscriptions'] ?></div>
                    <div class="stat-label">Verificati Manualmente</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= round($stats['avg_subscription_days'] ?? 0, 1) ?></div>
                    <div class="stat-label">Giorni Medi</div>
                </div>
            </div>

            <!-- Abbonamenti in Scadenza -->
            <?php if (!empty($expiring_subscriptions)): ?>
            <div class="card">
                <h2>⚠️ Abbonamenti in Scadenza (7 giorni)</h2>
                <div style="overflow-x: auto;">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Utente</th>
                                <th>Email</th>
                                <th>Scadenza</th>
                                <th>Giorni Rimanenti</th>
                                <th>Ultimo Pagamento</th>
                                <th>Stato</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiring_subscriptions as $sub): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['name']) ?></td>
                                <td><?= htmlspecialchars($sub['email']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($sub['subscription_end'])) ?></td>
                                <td>
                                    <span style="color: <?= $sub['days_until_expiry'] <= 1 ? '#dc3545' : '#fd7e14' ?>; font-weight: 600;">
                                        <?= $sub['days_until_expiry'] ?> giorni
                                    </span>
                                </td>
                                <td>
                                    <?= $sub['last_payment_date'] ? date('d/m/Y', strtotime($sub['last_payment_date'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <?php if ($sub['manually_verified']): ?>
                                        <span class="verified-badge">VERIFICATO</span>
                                    <?php else: ?>
                                        <span style="color: #666;">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="manual-actions">
                                        <form method="POST" style="display: inline-block; margin-right: 0.5rem;">
                                            <input type="hidden" name="user_id" value="<?= $sub['id'] ?>">
                                            <input type="number" name="extend_months" value="1" min="1" max="12" style="width: 60px; padding: 0.25rem;">
                                            <input type="text" name="notes" placeholder="Note..." style="width: 100px; padding: 0.25rem;">
                                            <button type="submit" name="extend_subscription" class="btn btn-success btn-sm">
                                                Estendi
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Gestione Manuale Abbonamenti -->
            <div class="card">
                <h2>🛠️ Gestione Manuale Abbonamenti</h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    Gestisci manualmente gli abbonamenti degli utenti. Tutte le azioni verranno registrate nel log.
                </p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <!-- Upgrade Manuale -->
                    <div class="manual-actions">
                        <h3>➕ Upgrade Manuale</h3>
                        <form method="POST">
                            <div style="margin-bottom: 1rem;">
                                <label>ID Utente:</label>
                                <input type="number" name="user_id" required style="width: 100%; padding: 0.5rem; margin-top: 0.25rem;">
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label>Durata (mesi):</label>
                                <select name="duration_months" style="width: 100%; padding: 0.5rem; margin-top: 0.25rem;">
                                    <option value="1">1 mese</option>
                                    <option value="3">3 mesi</option>
                                    <option value="6">6 mesi</option>
                                    <option value="12">12 mesi</option>
                                </select>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label>Note:</label>
                                <textarea name="notes" placeholder="Motivo dell'upgrade..." style="width: 100%; padding: 0.5rem; margin-top: 0.25rem; height: 60px;"></textarea>
                            </div>
                            <button type="submit" name="upgrade_user_manual" class="btn btn-success">
                                Upgrade a Premium
                            </button>
                        </form>
                    </div>
                    
                    <!-- Downgrade Manuale -->
                    <div class="manual-actions">
                        <h3>➖ Downgrade Manuale</h3>
                        <form method="POST">
                            <div style="margin-bottom: 1rem;">
                                <label>ID Utente:</label>
                                <input type="number" name="user_id" required style="width: 100%; padding: 0.5rem; margin-top: 0.25rem;">
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label>Note:</label>
                                <textarea name="notes" placeholder="Motivo del downgrade..." style="width: 100%; padding: 0.5rem; margin-top: 0.25rem; height: 60px;"></textarea>
                            </div>
                            <button type="submit" name="downgrade_user_manual" class="btn btn-secondary">
                                Downgrade a Gratuito
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Eventi Recenti -->
            <div class="card">
                <h2>📋 Eventi Abbonamenti Recenti</h2>
                <div style="overflow-x: auto;">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Utente</th>
                                <th>Azione</th>
                                <th>Eseguita da</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_events as $event): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($event['created_at'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($event['name']) ?><br>
                                    <small style="color: #666;"><?= htmlspecialchars($event['email']) ?></small>
                                </td>
                                <td>
                                    <span class="event-badge event-<?= $event['action'] ?>">
                                        <?= strtoupper(str_replace('_', ' ', $event['action'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="text-transform: capitalize; font-weight: 600;">
                                        <?= htmlspecialchars($event['performed_by']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($event['notes']): ?>
                                        <span style="font-size: 0.9rem; color: #666;">
                                            <?= htmlspecialchars(substr($event['notes'], 0, 100)) ?>
                                            <?= strlen($event['notes']) > 100 ? '...' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Strumenti -->
            <div class="card">
                <h2>🛠️ Strumenti di Gestione</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div style="padding: 1rem; border: 1px solid #dee2e6; border-radius: 8px;">
                        <h3>Controllo Manuale</h3>
                        <p style="color: #666; font-size: 0.9rem;">Esegui un controllo manuale degli abbonamenti scaduti</p>
                        <a href="../check_subscriptions_simple.php" class="btn btn-secondary" target="_blank">
                            Esegui Controllo
                        </a>
                    </div>
                    
                    <div style="padding: 1rem; border: 1px solid #dee2e6; border-radius: 8px;">
                        <h3>Sistema Semplificato</h3>
                        <p style="color: #666; font-size: 0.9rem;">Gestione basata su date, senza API esterne</p>
                        <ul style="font-size: 0.8rem; color: #666;">
                            <li>Controllo automatico scadenze</li>
                            <li>Gestione manuale abbonamenti</li>
                            <li>Log completo delle azioni</li>
                        </ul>
                    </div>
                    
                    <div style="padding: 1rem; border: 1px solid #dee2e6; border-radius: 8px;">
                        <h3>Configurazione Cron</h3>
                        <p style="color: #666; font-size: 0.9rem;">Imposta un cron job per il controllo automatico</p>
                        <code style="background: #f8f9fa; padding: 0.25rem; border-radius: 4px; font-size: 0.8rem; display: block;">
                            0 2 * * * php check_subscriptions_simple.php
                        </code>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>