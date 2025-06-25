<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

/**
 * Script automatizzato per controllare i pagamenti mancanti e gestire le cancellazioni
 * Questo script funziona SENZA API PayPal, basandosi solo su logica di date
 * Da eseguire tramite cron job ogni giorno
 */

function runAutomatedPaymentCheck($pdo) {
    try {
        echo "Esecuzione controllo automatico pagamenti...\n";
        
        // Esegui il controllo dei pagamenti mancanti
        $stmt = $pdo->prepare("CALL CheckMissingPayments()");
        $stmt->execute();
        
        echo "Controllo automatico completato.\n";
        
        // Ottieni statistiche sui pagamenti in ritardo
        $stmt = $pdo->query("SELECT * FROM overdue_payments");
        $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($overdue)) {
            echo "\nPagamenti in ritardo trovati:\n";
            foreach ($overdue as $payment) {
                echo "- {$payment['name']} ({$payment['email']}): {$payment['days_overdue']} giorni di ritardo - Status: {$payment['status']}\n";
            }
        } else {
            echo "\nNessun pagamento in ritardo.\n";
        }
        
        return count($overdue);
        
    } catch (Exception $e) {
        echo "Errore durante il controllo automatico: " . $e->getMessage() . "\n";
        return false;
    }
}

function generateAutomatedReport($pdo) {
    try {
        echo "\n=== REPORT AUTOMATIZZATO ABBONAMENTI ===\n";
        echo "Data: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Statistiche pagamenti
        $stmt = $pdo->query("SELECT * FROM payment_statistics");
        $payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "STATISTICHE PAGAMENTI:\n";
        echo "- Utenti paganti: {$payment_stats['paying_users']}\n";
        echo "- Pagamenti totali: {$payment_stats['total_payments']}\n";
        echo "- Ricavi totali: €" . number_format($payment_stats['total_revenue'], 2) . "\n";
        echo "- Pagamento medio: €" . number_format($payment_stats['avg_payment'], 2) . "\n";
        echo "- Pagamenti ultimi 30 giorni: {$payment_stats['payments_last_30_days']}\n";
        echo "- Ricavi ultimi 30 giorni: €" . number_format($payment_stats['revenue_last_30_days'], 2) . "\n\n";
        
        // Abbonamenti in scadenza
        $stmt = $pdo->query("SELECT * FROM expiring_subscriptions_simple ORDER BY days_until_expiry ASC");
        $expiring = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($expiring)) {
            echo "ABBONAMENTI IN SCADENZA (prossimi 7 giorni):\n";
            foreach ($expiring as $sub) {
                echo "- {$sub['name']} ({$sub['email']}): scade tra {$sub['days_until_expiry']} giorni\n";
            }
        } else {
            echo "Nessun abbonamento in scadenza nei prossimi 7 giorni.\n";
        }
        
        // Pagamenti in ritardo
        $stmt = $pdo->query("SELECT * FROM overdue_payments ORDER BY days_overdue DESC");
        $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($overdue)) {
            echo "\nPAGAMENTI IN RITARDO:\n";
            foreach ($overdue as $payment) {
                $status_text = [
                    'overdue' => 'In ritardo',
                    'grace_period' => 'Periodo di grazia',
                    'expired' => 'Scaduto'
                ];
                echo "- {$payment['name']} ({$payment['email']}): {$payment['days_overdue']} giorni - {$status_text[$payment['status']]}\n";
            }
        } else {
            echo "\nNessun pagamento in ritardo.\n";
        }
        
        echo "\n";
        
    } catch (Exception $e) {
        echo "Errore durante la generazione del report: " . $e->getMessage() . "\n";
    }
}

// Funzione per simulare la ricezione di un pagamento (per test)
function simulatePaymentReceived($pdo, $user_id, $subscription_id, $amount = 9.99) {
    try {
        $stmt = $pdo->prepare("CALL RegisterPaymentReceived(?, ?, ?, 'manual_entry', 'Test payment simulation')");
        $stmt->execute([$user_id, $subscription_id, $amount]);
        echo "Pagamento simulato registrato per utente ID: $user_id\n";
        return true;
    } catch (Exception $e) {
        echo "Errore simulazione pagamento: " . $e->getMessage() . "\n";
        return false;
    }
}

// Esecuzione dello script
echo "Inizio controllo automatizzato abbonamenti: " . date('Y-m-d H:i:s') . "\n";

// Controlla se è una simulazione di pagamento
if (isset($argv[1]) && $argv[1] === 'simulate-payment' && isset($argv[2]) && isset($argv[3])) {
    $user_id = (int)$argv[2];
    $subscription_id = $argv[3];
    simulatePaymentReceived($pdo, $user_id, $subscription_id);
    exit;
}

$overdue_count = runAutomatedPaymentCheck($pdo);

echo "\nRisultati:\n";
echo "- Pagamenti in ritardo gestiti: " . ($overdue_count !== false ? $overdue_count : 'Errore') . "\n";

generateAutomatedReport($pdo);

echo "\nControllo automatizzato completato: " . date('Y-m-d H:i:s') . "\n";

// Suggerimenti per azioni manuali se necessario
if ($overdue_count > 0) {
    echo "\n🔔 AZIONI CONSIGLIATE:\n";
    echo "- Controlla il pannello admin per pagamenti in ritardo\n";
    echo "- Verifica manualmente gli abbonamenti in periodo di grazia\n";
    echo "- Considera di contattare gli utenti con pagamenti scaduti\n";
}
?>