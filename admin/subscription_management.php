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
    if (isset($_POST['sync_subscription'])) {
        $user_id = (int)$_POST['user_id'];
        // Qui potresti implementare una sincronizzazione manuale con PayPal
        $success = "Sincronizzazione richiesta per l'utente ID: $user_id";
    }
}

// Statistiche abbonamenti
$stmt = $pdo->query("SELECT * FROM subscription_stats");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Abbonamenti in scadenza
$stmt = $pdo->query("SELECT * FROM expiring_subscriptions ORDER BY days_until_expiry ASC LIMIT 10");
$expiring_subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Eventi recenti
$stmt = $pdo->query("
    SELECT se.*, u.name, u.email 
    FROM subscription_events se 
    JOIN users u ON se.user_id = u.id 
    ORDER BY se.processed_at DESC 
    LIMIT 20
");
$recent_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Log webhook recenti
$stmt = $pdo->query("
    SELECT * FROM webhook_logs 
    ORDER BY received_at DESC 
    LIMIT 10
");
$webhook_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Abbonamenti - Admin Panel</title>
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
        .event-activated { background: #d4edda; color: #155724; }
        .event-cancelled { background: #f8d7da; color: #721c24; }
        .event-expired { background: #e2e3e5; color: #383d41; }
        .event-payment_completed { background: #cce7ff; color: #004085; }
        .event-payment_failed { background: #fff3cd; color: #856404; }
        .webhook-status {
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .webhook-processed { background: #d4edda; color: #155724; }
        .webhook-error { background: #f8d7da; color: #721c24; }
        .webhook-pending { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <header class="header admin-header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">🛡️ Admin Panel - Abbonamenti</a>
                <div class="nav-links">
                    <a href="index.php">Dashboard</a>
                    <a href="subscription_management.php">Abbonamenti</a>
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
                    <div class="stat-number"><?= $stats['cancelled_subscriptions'] ?></div>
                    <div class="stat-label">Cancellati</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['expired_subscriptions'] ?></div>
                    <div class="stat-label">Scaduti</div>
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
                                <th>Subscription ID</th>
                                <th>Scadenza</th>
                                <th>Giorni Rimanenti</th>
                                <th>Ultimo Aggiornamento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiring_subscriptions as $sub): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['name']) ?></td>
                                <td><?= htmlspecialchars($sub['email']) ?></td>
                                <td style="font-family: monospace; font-size: 0.8rem;">
                                    <?= htmlspecialchars(substr($sub['subscription_id'], 0, 20)) ?>...
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($sub['subscription_end'])) ?></td>
                                <td>
                                    <span style="color: <?= $sub['days_until_expiry'] <= 1 ? '#dc3545' : '#fd7e14' ?>; font-weight: 600;">
                                        <?= $sub['days_until_expiry'] ?> giorni
                                    </span>
                                </td>
                                <td>
                                    <?= $sub['subscription_updated_at'] ? date('d/m/Y H:i', strtotime($sub['subscription_updated_at'])) : 'N/A' ?>
                                    <br><small style="color: #666;"><?= $sub['subscription_update_source'] ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Eventi Recenti -->
            <div class="card">
                <h2>📋 Eventi Abbonamenti Recenti</h2>
                <div style="overflow-x: auto;">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Utente</th>
                                <th>Evento</th>
                                <th>Subscription ID</th>
                                <th>PayPal Event ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_events as $event): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($event['processed_at'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($event['name']) ?><br>
                                    <small style="color: #666;"><?= htmlspecialchars($event['email']) ?></small>
                                </td>
                                <td>
                                    <span class="event-badge event-<?= $event['event_type'] ?>">
                                        <?= strtoupper($event['event_type']) ?>
                                    </span>
                                </td>
                                <td style="font-family: monospace; font-size: 0.8rem;">
                                    <?= htmlspecialchars(substr($event['subscription_id'], 0, 15)) ?>...
                                </td>
                                <td style="font-family: monospace; font-size: 0.8rem;">
                                    <?= $event['paypal_event_id'] ? htmlspecialchars(substr($event['paypal_event_id'], 0, 15)) . '...' : 'N/A' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Log Webhook -->
            <div class="card">
                <h2>🔗 Log Webhook PayPal</h2>
                <div style="overflow-x: auto;">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Evento</th>
                                <th>Stato</th>
                                <th>Webhook ID</th>
                                <th>Errore</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($webhook_logs as $log): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($log['received_at'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($log['event_type']) ?><br>
                                    <small style="color: #666;"><?= htmlspecialchars($log['resource_type']) ?></small>
                                </td>
                                <td>
                                    <?php if ($log['processed']): ?>
                                        <span class="webhook-status webhook-processed">PROCESSATO</span>
                                    <?php elseif ($log['error_message']): ?>
                                        <span class="webhook-status webhook-error">ERRORE</span>
                                    <?php else: ?>
                                        <span class="webhook-status webhook-pending">IN ATTESA</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-family: monospace; font-size: 0.8rem;">
                                    <?= $log['webhook_id'] ? htmlspecialchars(substr($log['webhook_id'], 0, 15)) . '...' : 'N/A' ?>
                                </td>
                                <td>
                                    <?php if ($log['error_message']): ?>
                                        <span style="color: #dc3545; font-size: 0.8rem;">
                                            <?= htmlspecialchars(substr($log['error_message'], 0, 50)) ?>...
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #28a745;">OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Azioni Admin -->
            <div class="card">
                <h2>🛠️ Strumenti di Gestione</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div style="padding: 1rem; border: 1px solid #dee2e6; border-radius: 8px;">
                        <h3>Controllo Manuale</h3>
                        <p style="color: #666; font-size: 0.9rem;">Esegui un controllo manuale degli abbonamenti scaduti</p>
                        <a href="../check_subscriptions.php" class="btn btn-secondary" target="_blank">
                            Esegui Controllo
                        </a>
                    </div>
                    
                    <div style="padding: 1rem; border: 1px solid #dee2e6; border-radius: 8px;">
                        <h3>Webhook PayPal</h3>
                        <p style="color: #666; font-size: 0.9rem;">URL per configurare i webhook PayPal</p>
                        <code style="background: #f8f9fa; padding: 0.25rem; border-radius: 4px; font-size: 0.8rem;">
                            <?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http" ?>://<?= $_SERVER['HTTP_HOST'] ?>/webhook_paypal.php
                        </code>
                    </div>
                    
                    <div style="padding: 1rem; border: 1px solid #dee2e6; border-radius: 8px;">
                        <h3>Configurazione</h3>
                        <p style="color: #666; font-size: 0.9rem;">Ricorda di configurare le credenziali PayPal</p>
                        <ul style="font-size: 0.8rem; color: #666;">
                            <li>Client Secret PayPal</li>
                            <li>Webhook ID PayPal</li>
                            <li>Cron job per check_subscriptions.php</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>