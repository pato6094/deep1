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

// Gestione registrazione pagamento manuale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_payment'])) {
    $user_id = (int)$_POST['user_id'];
    $subscription_id = trim($_POST['subscription_id']);
    $amount = (float)$_POST['amount'];
    $reference = trim($_POST['reference']);
    
    try {
        $stmt = $pdo->prepare("CALL RegisterPaymentReceived(?, ?, ?, 'manual_entry', ?)");
        
        if ($stmt->execute([$user_id, $subscription_id, $amount, $reference])) {
            $success = "Pagamento registrato con successo! Abbonamento esteso automaticamente.";
        } else {
            $error = "Errore durante la registrazione del pagamento.";
        }
    } catch (Exception $e) {
        $error = "Errore: " . $e->getMessage();
    }
}

// Statistiche pagamenti
$stmt = $pdo->query("SELECT * FROM payment_statistics");
$payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Pagamenti in ritardo
$stmt = $pdo->query("SELECT * FROM overdue_payments ORDER BY days_overdue DESC LIMIT 20");
$overdue_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagamenti recenti
$stmt = $pdo->query("
    SELECT pt.*, u.name, u.email 
    FROM payment_tracking pt 
    JOIN users u ON pt.user_id = u.id 
    ORDER BY pt.payment_date DESC 
    LIMIT 20
");
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoraggio Automatizzato - Admin Panel</title>
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .status-grace_period { background: #fff3cd; color: #856404; }
        .status-expired { background: #e2e3e5; color: #383d41; }
        .payment-source {
            padding: 0.125rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .source-paypal_webhook { background: #cce7ff; color: #004085; }
        .source-manual_entry { background: #d4edda; color: #155724; }
        .source-system_detected { background: #e2e3e5; color: #383d41; }
        .automated-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <header class="header admin-header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">🛡️ Admin Panel - Monitoraggio Automatizzato</a>
                <div class="nav-links">
                    <a href="index.php">Dashboard</a>
                    <a href="subscription_management_simple.php">Abbonamenti</a>
                    <a href="automated_monitoring.php">Monitoraggio Auto</a>
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

            <!-- Info Sistema Automatizzato -->
            <div class="automated-info">
                <h2 style="color: #0c5460; margin-bottom: 1rem;">🤖 Sistema di Monitoraggio Automatizzato</h2>
                <p style="color: #0c5460; margin-bottom: 1rem;">
                    <strong>Come Funziona:</strong> Il sistema monitora automaticamente i pagamenti previsti e gestisce le scadenze senza bisogno di API PayPal.
                </p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div>
                        <h4 style="color: #0c5460;">✅ Automatico:</h4>
                        <ul style="color: #0c5460; font-size: 0.9rem;">
                            <li>Controllo pagamenti mancanti</li>
                            <li>Periodo di grazia (7 giorni)</li>
                            <li>Cancellazione automatica</li>
                            <li>Estensione su pagamento ricevuto</li>
                        </ul>
                    </div>
                    <div>
                        <h4 style="color: #0c5460;">🔧 Manuale:</h4>
                        <ul style="color: #0c5460; font-size: 0.9rem;">
                            <li>Registrazione pagamenti</li>
                            <li>Gestione casi speciali</li>
                            <li>Monitoraggio in tempo reale</li>
                            <li>Report dettagliati</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Statistiche Pagamenti -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $payment_stats['paying_users'] ?></div>
                    <div class="stat-label">Utenti Paganti</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $payment_stats['total_payments'] ?></div>
                    <div class="stat-label">Pagamenti Totali</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">€<?= number_format($payment_stats['total_revenue'], 0) ?></div>
                    <div class="stat-label">Ricavi Totali</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $payment_stats['payments_last_30_days'] ?></div>
                    <div class="stat-label">Pagamenti (30gg)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">€<?= number_format($payment_stats['revenue_last_30_days'], 0) ?></div>
                    <div class="stat-label">Ricavi (30gg)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">€<?= number_format($payment_stats['avg_payment'], 2) ?></div>
                    <div class="stat-label">Pagamento Medio</div>
                </div>
            </div>

            <!-- Pagamenti in Ritardo -->
            <?php if (!empty($overdue_payments)): ?>
            <div class="card">
                <h2>⚠️ Pagamenti in Ritardo - Gestione Automatica</h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    Il sistema gestisce automaticamente questi pagamenti con periodo di grazia di 7 giorni.
                </p>
                <div style="overflow-x: auto;">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Utente</th>
                                <th>Email</th>
                                <th>Subscription ID</th>
                                <th>Pagamento Previsto</th>
                                <th>Giorni di Ritardo</th>
                                <th>Stato</th>
                                <th>Periodo di Grazia</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overdue_payments as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['name']) ?></td>
                                <td><?= htmlspecialchars($payment['email']) ?></td>
                                <td style="font-family: monospace; font-size: 0.8rem;">
                                    <?= htmlspecialchars(substr($payment['subscription_id'], 0, 15)) ?>...
                                </td>
                                <td><?= date('d/m/Y', strtotime($payment['expected_payment_date'])) ?></td>
                                <td>
                                    <span style="color: #dc3545; font-weight: 600;">
                                        <?= $payment['days_overdue'] ?> giorni
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $payment['status'] ?>">
                                        <?php
                                        $status_labels = [
                                            'overdue' => 'IN RITARDO',
                                            'grace_period' => 'GRAZIA',
                                            'expired' => 'SCADUTO'
                                        ];
                                        echo $status_labels[$payment['status']];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['grace_period_end']): ?>
                                        <?= date('d/m/Y', strtotime($payment['grace_period_end'])) ?>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="fillPaymentForm(<?= $payment['user_id'] ?>, '<?= htmlspecialchars($payment['subscription_id']) ?>', '<?= htmlspecialchars($payment['name']) ?>')" 
                                            class="btn btn-success btn-sm">
                                        Registra Pagamento
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Registrazione Pagamento Manuale -->
            <div class="card">
                <h2>💳 Registra Pagamento Ricevuto</h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    Quando ricevi conferma di un pagamento PayPal, registralo qui per estendere automaticamente l'abbonamento.
                </p>
                
                <form method="POST" style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label>ID Utente:</label>
                            <input type="number" name="user_id" id="payment_user_id" required 
                                   style="width: 100%; padding: 0.5rem; margin-top: 0.25rem;">
                        </div>
                        <div>
                            <label>Subscription ID:</label>
                            <input type="text" name="subscription_id" id="payment_subscription_id" required 
                                   style="width: 100%; padding: 0.5rem; margin-top: 0.25rem;">
                        </div>
                        <div>
                            <label>Importo (€):</label>
                            <input type="number" name="amount" value="9.99" step="0.01" required 
                                   style="width: 100%; padding: 0.5rem; margin-top: 0.25rem;">
                        </div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label>Riferimento Pagamento:</label>
                        <input type="text" name="reference" placeholder="Es: PayPal Transaction ID, Email conferma..." 
                               style="width: 100%; padding: 0.5rem; margin-top: 0.25rem;">
                    </div>
                    <button type="submit" name="register_payment" class="btn btn-success">
                        Registra Pagamento ed Estendi Abbonamento
                    </button>
                </form>
            </div>

            <!-- Pagamenti Recenti -->
            <div class="card">
                <h2>📋 Pagamenti Recenti</h2>
                <div style="overflow-x: auto;">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Utente</th>
                                <th>Importo</th>
                                <th>Fonte</th>
                                <th>Riferimento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($payment['payment_date'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($payment['name']) ?><br>
                                    <small style="color: #666;"><?= htmlspecialchars($payment['email']) ?></small>
                                </td>
                                <td>
                                    <strong>€<?= number_format($payment['payment_amount'], 2) ?></strong>
                                </td>
                                <td>
                                    <span class="payment-source source-<?= $payment['payment_source'] ?>">
                                        <?= strtoupper(str_replace('_', ' ', $payment['payment_source'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['payment_reference']): ?>
                                        <span style="font-size: 0.9rem; color: #666;">
                                            <?= htmlspecialchars(substr($payment['payment_reference'], 0, 50)) ?>
                                            <?= strlen($payment['payment_reference']) > 50 ? '...' : '' ?>
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
                <h2>🛠️ Strumenti di Monitoraggio</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div style="padding: 1rem; border: 1px solid #dee2e6; border-radius: 8px;">
                        <h3>Controllo Automatico</h3>
                        <p style="color: #666; font-size: 0.9rem;">Esegui il controllo automatico dei pagamenti mancanti</p>
                        <a href="../check_subscriptions_automated.php" class="btn btn-secondary" target="_blank">
                            Esegui Controllo
                        </a>
                    </div>
                    
                    <div style="padding: 1rem; border: 1px solid #dee2e6; border-radius: 8px;">
                        <h3>Simulazione Pagamento</h3>
                        <p style="color: #666; font-size: 0.9rem;">Per test: simula la ricezione di un pagamento</p>
                        <code style="background: #f8f9fa; padding: 0.25rem; border-radius: 4px; font-size: 0.8rem; display: block;">
                            php check_subscriptions_automated.php simulate-payment [USER_ID] [SUB_ID]
                        </code>
                    </div>
                    
                    <div style="padding: 1rem; border: 1px solid #dee2e6; border-radius: 8px;">
                        <h3>Configurazione Cron</h3>
                        <p style="color: #666; font-size: 0.9rem;">Esegui il controllo automatico ogni giorno</p>
                        <code style="background: #f8f9fa; padding: 0.25rem; border-radius: 4px; font-size: 0.8rem; display: block;">
                            0 2 * * * php check_subscriptions_automated.php
                        </code>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function fillPaymentForm(userId, subscriptionId, userName) {
            document.getElementById('payment_user_id').value = userId;
            document.getElementById('payment_subscription_id').value = subscriptionId;
            
            // Scorri alla sezione di registrazione pagamento
            document.querySelector('form').scrollIntoView({ behavior: 'smooth' });
            
            // Focus sul campo riferimento
            document.querySelector('input[name="reference"]').focus();
            document.querySelector('input[name="reference"]').placeholder = `Pagamento per ${userName} - Inserisci riferimento PayPal`;
        }
    </script>
</body>
</html>