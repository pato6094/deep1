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

// Gestione controllo manuale PayPal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_paypal_status'])) {
    $subscription_id = trim($_POST['subscription_id']);
    
    if (empty($subscription_id)) {
        $error = "Inserisci un Subscription ID valido.";
    } else {
        // Trova l'utente con questo subscription_id
        $stmt = $pdo->prepare("SELECT id, name, email, subscription_status FROM users WHERE subscription_id = ?");
        $stmt->execute([$subscription_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = "Nessun utente trovato con questo Subscription ID.";
        } else {
            // Qui potresti aggiungere una chiamata API PayPal per verificare lo stato
            // Per ora, mostriamo solo le informazioni e permettiamo azione manuale
            $success = "Utente trovato: {$user['name']} ({$user['email']}) - Stato attuale: {$user['subscription_status']}";
        }
    }
}

// Gestione cancellazione manuale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_subscription_manual'])) {
    $user_id = (int)$_POST['user_id'];
    $notes = trim($_POST['notes']);
    
    try {
        // Imposta scadenza a fine mese corrente (periodo di grazia)
        $grace_end = date('Y-m-d H:i:s', strtotime('last day of this month 23:59:59'));
        
        $stmt = $pdo->prepare("
            CALL UpdateSubscriptionSimple(?, 'active', ?, 'admin', 'manual_downgrade', ?)
        ");
        
        $admin_notes = "Abbonamento cancellato manualmente (PayPal). Periodo di grazia fino a fine mese. Note: {$notes}";
        
        if ($stmt->execute([$user_id, $grace_end, $admin_notes])) {
            $success = "Abbonamento cancellato con periodo di grazia fino al " . date('d/m/Y', strtotime($grace_end));
        } else {
            $error = "Errore durante la cancellazione dell'abbonamento.";
        }
    } catch (Exception $e) {
        $error = "Errore: " . $e->getMessage();
    }
}

// Lista utenti con abbonamenti attivi per controllo rapido
$stmt = $pdo->query("
    SELECT id, name, email, subscription_id, subscription_end, last_payment_date, manually_verified
    FROM users 
    WHERE subscription_status = 'active' 
    AND subscription_id IS NOT NULL
    ORDER BY subscription_end ASC
    LIMIT 20
");
$active_subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controllo PayPal - Admin Panel</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        .admin-header .logo {
            color: white;
        }
        .paypal-check {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .manual-action {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .subscription-id {
            font-family: monospace;
            font-size: 0.9rem;
            background: #f8f9fa;
            padding: 0.25rem;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <header class="header admin-header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">🛡️ Admin Panel - Controllo PayPal</a>
                <div class="nav-links">
                    <a href="index.php">Dashboard</a>
                    <a href="subscription_management_simple.php">Abbonamenti</a>
                    <a href="paypal_check.php">Controllo PayPal</a>
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

            <!-- Controllo Manuale PayPal -->
            <div class="card">
                <h2>🔍 Controllo Manuale PayPal</h2>
                <div class="paypal-check">
                    <h3>Verifica Stato Abbonamento</h3>
                    <p style="color: #004085; margin-bottom: 1rem;">
                        Inserisci il Subscription ID PayPal per verificare lo stato di un abbonamento.
                        Utile quando un utente segnala problemi o cancellazioni.
                    </p>
                    
                    <form method="POST">
                        <div style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 300px;">
                                <label for="subscription_id">Subscription ID PayPal:</label>
                                <input type="text" id="subscription_id" name="subscription_id" 
                                       class="form-control" 
                                       placeholder="I-BW452GLLEP1G"
                                       value="<?= htmlspecialchars($_POST['subscription_id'] ?? '') ?>">
                            </div>
                            <button type="submit" name="check_paypal_status" class="btn btn-primary">
                                Verifica Stato
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Azioni Manuali per Cancellazioni -->
            <div class="card">
                <h2>❌ Gestione Cancellazioni PayPal</h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    Quando un utente cancella l'abbonamento su PayPal, usa questa sezione per gestire la cancellazione manualmente.
                </p>
                
                <div class="manual-action">
                    <h3>Cancella Abbonamento con Periodo di Grazia</h3>
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">
                        L'utente manterrà l'accesso Premium fino alla fine del mese corrente, poi tornerà al piano gratuito.
                    </p>
                    
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <label>ID Utente:</label>
                                <input type="number" name="user_id" required 
                                       style="width: 100%; padding: 0.5rem; margin-top: 0.25rem;">
                            </div>
                            <div>
                                <label>Periodo di grazia fino a:</label>
                                <input type="text" value="<?= date('d/m/Y', strtotime('last day of this month')) ?>" 
                                       readonly style="width: 100%; padding: 0.5rem; margin-top: 0.25rem; background: #f8f9fa;">
                            </div>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label>Note sulla cancellazione:</label>
                            <textarea name="notes" placeholder="Es: Utente ha cancellato su PayPal il [data]..." 
                                      style="width: 100%; padding: 0.5rem; margin-top: 0.25rem; height: 80px;"></textarea>
                        </div>
                        <button type="submit" name="cancel_subscription_manual" class="btn btn-secondary">
                            Cancella con Periodo di Grazia
                        </button>
                    </form>
                </div>
            </div>

            <!-- Lista Abbonamenti Attivi -->
            <div class="card">
                <h2>📋 Abbonamenti Attivi da Monitorare</h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    Lista degli abbonamenti attivi con Subscription ID PayPal. Monitora questi per eventuali cancellazioni.
                </p>
                
                <div style="overflow-x: auto;">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Utente</th>
                                <th>Email</th>
                                <th>Subscription ID</th>
                                <th>Scadenza</th>
                                <th>Ultimo Pagamento</th>
                                <th>Stato</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_subscriptions as $sub): ?>
                            <tr>
                                <td><?= $sub['id'] ?></td>
                                <td><?= htmlspecialchars($sub['name']) ?></td>
                                <td><?= htmlspecialchars($sub['email']) ?></td>
                                <td>
                                    <span class="subscription-id">
                                        <?= htmlspecialchars(substr($sub['subscription_id'], 0, 20)) ?>...
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($sub['subscription_end'])) ?></td>
                                <td>
                                    <?= $sub['last_payment_date'] ? date('d/m/Y', strtotime($sub['last_payment_date'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <?php if ($sub['manually_verified']): ?>
                                        <span style="background: #28a745; color: white; padding: 0.125rem 0.5rem; border-radius: 10px; font-size: 0.75rem;">
                                            VERIFICATO
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #666;">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="fillCancelForm(<?= $sub['id'] ?>, '<?= htmlspecialchars($sub['name']) ?>')" 
                                            class="btn btn-secondary btn-sm">
                                        Cancella
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Istruzioni -->
            <div class="card">
                <h2>📖 Istruzioni per la Gestione Manuale</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div>
                        <h3>🔍 Quando Verificare</h3>
                        <ul style="color: #666;">
                            <li>Utente segnala problemi di accesso</li>
                            <li>Utente dice di aver cancellato su PayPal</li>
                            <li>Controllo periodico degli abbonamenti</li>
                            <li>Prima della scadenza naturale</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3>⚡ Azioni Rapide</h3>
                        <ul style="color: #666;">
                            <li><strong>Verifica:</strong> Controlla Subscription ID</li>
                            <li><strong>Cancella:</strong> Imposta periodo di grazia</li>
                            <li><strong>Estendi:</strong> Aggiungi tempo se necessario</li>
                            <li><strong>Documenta:</strong> Aggiungi sempre note dettagliate</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3>📝 Best Practices</h3>
                        <ul style="color: #666;">
                            <li>Sempre aggiungere note dettagliate</li>
                            <li>Dare periodo di grazia per cancellazioni</li>
                            <li>Verificare con l'utente prima di azioni drastiche</li>
                            <li>Tenere log di tutte le comunicazioni</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function fillCancelForm(userId, userName) {
            // Scorri alla sezione di cancellazione
            document.querySelector('input[name="user_id"]').value = userId;
            document.querySelector('textarea[name="notes"]').value = `Cancellazione richiesta per ${userName} - Verificare su PayPal`;
            document.querySelector('textarea[name="notes"]').focus();
            
            // Scorri alla sezione
            document.querySelector('.manual-action').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
```