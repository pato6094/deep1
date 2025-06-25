<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

/**
 * Script semplificato per controllare periodicamente lo stato degli abbonamenti
 * Basato solo su controlli di date, senza API esterne
 * Da eseguire tramite cron job ogni giorno
 */

function checkExpiredSubscriptionsSimple($pdo) {
    $updated = 0;
    
    try {
        // Trova abbonamenti scaduti che sono ancora attivi
        $stmt = $pdo->prepare("
            SELECT id, name, email, subscription_end, last_payment_date
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
                CALL UpdateSubscriptionSimple(?, 'expired', ?, 'system', 'subscription_expired', ?)
            ");
            
            $notes = "Abbonamento scaduto automaticamente il " . date('Y-m-d H:i:s');
            if ($user['last_payment_date']) {
                $notes .= ". Ultimo pagamento: " . $user['last_payment_date'];
            }
            
            $stmt->execute([
                $user['id'],
                $user['subscription_end'],
                $notes
            ]);
            
            $updated++;
            
            echo "Abbonamento scaduto per utente: {$user['name']} ({$user['email']})\n";
        }
        
    } catch (Exception $e) {
        echo "Errore durante il controllo abbonamenti scaduti: " . $e->getMessage() . "\n";
    }
    
    return $updated;
}

function generateSubscriptionReportSimple($pdo) {
    try {
        echo "\n=== REPORT ABBONAMENTI SEMPLIFICATO ===\n";
        echo "Data: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Statistiche generali
        $stmt = $pdo->query("SELECT * FROM subscription_stats_simple");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "STATISTICHE GENERALI:\n";
        echo "- Utenti totali: {$stats['total_users']}\n";
        echo "- Abbonamenti attivi: {$stats['active_subscriptions']}\n";
        echo "- Abbonamenti scaduti: {$stats['expired_subscriptions']}\n";
        echo "- Utenti gratuiti: {$stats['free_users']}\n";
        echo "- Abbonamenti verificati manualmente: {$stats['manually_verified_subscriptions']}\n";
        echo "- Durata media abbonamento: " . round($stats['avg_subscription_days'] ?? 0, 1) . " giorni\n\n";
        
        // Abbonamenti in scadenza
        $stmt = $pdo->query("SELECT * FROM expiring_subscriptions_simple ORDER BY days_until_expiry ASC");
        $expiring = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($expiring)) {
            echo "ABBONAMENTI IN SCADENZA (prossimi 7 giorni):\n";
            foreach ($expiring as $sub) {
                $verified_text = $sub['manually_verified'] ? " [VERIFICATO]" : "";
                echo "- {$sub['name']} ({$sub['email']}): scade tra {$sub['days_until_expiry']} giorni{$verified_text}\n";
            }
        } else {
            echo "Nessun abbonamento in scadenza nei prossimi 7 giorni.\n";
        }
        
        // Eventi recenti
        $stmt = $pdo->query("
            SELECT ssl.*, u.name, u.email 
            FROM simple_subscription_logs ssl 
            JOIN users u ON ssl.user_id = u.id 
            ORDER BY ssl.created_at DESC 
            LIMIT 10
        ");
        $recent_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($recent_events)) {
            echo "\nEVENTI RECENTI:\n";
            foreach ($recent_events as $event) {
                $date = date('d/m/Y H:i', strtotime($event['created_at']));
                echo "- {$date}: {$event['name']} - {$event['action']} ({$event['performed_by']})\n";
            }
        }
        
        echo "\n";
        
    } catch (Exception $e) {
        echo "Errore durante la generazione del report: " . $e->getMessage() . "\n";
    }
}

// Esecuzione dello script
echo "Inizio controllo abbonamenti semplificato: " . date('Y-m-d H:i:s') . "\n";

$expired_count = checkExpiredSubscriptionsSimple($pdo);

echo "\nRisultati:\n";
echo "- Abbonamenti scaduti aggiornati: $expired_count\n";

generateSubscriptionReportSimple($pdo);

echo "\nControllo completato: " . date('Y-m-d H:i:s') . "\n";
?>