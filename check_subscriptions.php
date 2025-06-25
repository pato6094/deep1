<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

/**
 * Script per controllare periodicamente lo stato degli abbonamenti
 * Da eseguire tramite cron job ogni giorno
 */

function checkExpiredSubscriptions($pdo) {
    $updated = 0;
    
    try {
        // Trova abbonamenti scaduti che sono ancora attivi
        $stmt = $pdo->prepare("
            SELECT id, name, email, subscription_id, subscription_end 
            FROM users 
            WHERE subscription_status = 'active' 
            AND subscription_end IS NOT NULL 
            AND subscription_end < NOW()
        ");
        $stmt->execute();
        $expired_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($expired_users as $user) {
            // Aggiorna lo stato a scaduto
            $stmt = $pdo->prepare("
                CALL UpdateSubscriptionStatus(?, ?, 'expired', ?, 'system_check', 'expired', ?)
            ");
            
            $stmt->execute([
                $user['id'],
                $user['subscription_id'],
                $user['subscription_end'],
                json_encode([
                    'reason' => 'automatic_expiry_check',
                    'expired_at' => $user['subscription_end']
                ])
            ]);
            
            $updated++;
            
            echo "Abbonamento scaduto per utente: {$user['name']} ({$user['email']})\n";
        }
        
    } catch (Exception $e) {
        echo "Errore durante il controllo abbonamenti scaduti: " . $e->getMessage() . "\n";
    }
    
    return $updated;
}

function checkGracePeriodExpired($pdo) {
    $updated = 0;
    
    try {
        // Trova utenti il cui periodo di grazia è scaduto
        $stmt = $pdo->prepare("
            SELECT id, name, email, subscription_id 
            FROM users 
            WHERE subscription_status = 'cancelled' 
            AND subscription_grace_period IS NOT NULL 
            AND subscription_grace_period < NOW()
        ");
        $stmt->execute();
        $grace_expired_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($grace_expired_users as $user) {
            // Aggiorna lo stato a gratuito
            $stmt = $pdo->prepare("
                CALL UpdateSubscriptionStatus(?, ?, 'free', NULL, 'system_check', 'expired', ?)
            ");
            
            $stmt->execute([
                $user['id'],
                $user['subscription_id'],
                json_encode([
                    'reason' => 'grace_period_expired',
                    'expired_at' => date('Y-m-d H:i:s')
                ])
            ]);
            
            $updated++;
            
            echo "Periodo di grazia scaduto per utente: {$user['name']} ({$user['email']})\n";
        }
        
    } catch (Exception $e) {
        echo "Errore durante il controllo periodo di grazia: " . $e->getMessage() . "\n";
    }
    
    return $updated;
}

function generateSubscriptionReport($pdo) {
    try {
        echo "\n=== REPORT ABBONAMENTI ===\n";
        echo "Data: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Statistiche generali
        $stmt = $pdo->query("SELECT * FROM subscription_stats");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "STATISTICHE GENERALI:\n";
        echo "- Utenti totali: {$stats['total_users']}\n";
        echo "- Abbonamenti attivi: {$stats['active_subscriptions']}\n";
        echo "- Abbonamenti cancellati: {$stats['cancelled_subscriptions']}\n";
        echo "- Abbonamenti scaduti: {$stats['expired_subscriptions']}\n";
        echo "- Utenti gratuiti: {$stats['free_users']}\n";
        echo "- Durata media abbonamento: " . round($stats['avg_subscription_days'] ?? 0, 1) . " giorni\n\n";
        
        // Abbonamenti in scadenza
        $stmt = $pdo->query("SELECT * FROM expiring_subscriptions ORDER BY days_until_expiry ASC");
        $expiring = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($expiring)) {
            echo "ABBONAMENTI IN SCADENZA (prossimi 7 giorni):\n";
            foreach ($expiring as $sub) {
                echo "- {$sub['name']} ({$sub['email']}): scade tra {$sub['days_until_expiry']} giorni\n";
            }
        } else {
            echo "Nessun abbonamento in scadenza nei prossimi 7 giorni.\n";
        }
        
        echo "\n";
        
    } catch (Exception $e) {
        echo "Errore durante la generazione del report: " . $e->getMessage() . "\n";
    }
}

// Esecuzione dello script
echo "Inizio controllo abbonamenti: " . date('Y-m-d H:i:s') . "\n";

$expired_count = checkExpiredSubscriptions($pdo);
$grace_expired_count = checkGracePeriodExpired($pdo);

echo "\nRisultati:\n";
echo "- Abbonamenti scaduti aggiornati: $expired_count\n";
echo "- Periodi di grazia scaduti: $grace_expired_count\n";

generateSubscriptionReport($pdo);

echo "\nControllo completato: " . date('Y-m-d H:i:s') . "\n";
?>