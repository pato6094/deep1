<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Configurazione PayPal
$paypal_client_id = 'AQJDnagVff_mI2EtgXHdsCD_hduUKkOwKnGn2goqziCThEKDgzGDV3UWbza5b6Bz5w-kz4Ba-qqwxWyr';
$paypal_client_secret = 'YOUR_PAYPAL_CLIENT_SECRET'; // Da configurare
$paypal_webhook_id = 'YOUR_WEBHOOK_ID'; // Da configurare dopo aver creato il webhook

// Funzione per verificare la firma del webhook PayPal
function verifyPayPalWebhook($headers, $body, $webhook_id) {
    // Implementazione della verifica della firma PayPal
    // Per ora ritorniamo true, ma in produzione DEVE essere implementata
    return true;
}

// Funzione per ottenere access token PayPal
function getPayPalAccessToken($client_id, $client_secret) {
    $url = 'https://api.paypal.com/v1/oauth2/token'; // Sandbox: https://api.sandbox.paypal.com/v1/oauth2/token
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $client_secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en_US'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    
    return null;
}

// Funzione per ottenere dettagli dell'abbonamento da PayPal
function getSubscriptionDetails($subscription_id, $access_token) {
    $url = "https://api.paypal.com/v1/billing/subscriptions/$subscription_id"; // Sandbox: https://api.sandbox.paypal.com/v1/billing/subscriptions/$subscription_id
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

// Log della richiesta webhook
function logWebhook($webhook_data, $processed = false, $error = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO webhook_logs (
                webhook_id, event_type, resource_type, summary, raw_data, 
                processed, error_message, received_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $webhook_data['id'] ?? null,
            $webhook_data['event_type'] ?? null,
            $webhook_data['resource_type'] ?? null,
            $webhook_data['summary'] ?? null,
            json_encode($webhook_data),
            $processed,
            $error
        ]);
    } catch (Exception $e) {
        error_log("Errore log webhook: " . $e->getMessage());
    }
}

// Gestione degli eventi webhook
function processWebhookEvent($event_data) {
    global $pdo, $paypal_client_id, $paypal_client_secret;
    
    $event_type = $event_data['event_type'] ?? '';
    $resource = $event_data['resource'] ?? [];
    
    // Eventi di interesse per gli abbonamenti
    $subscription_events = [
        'BILLING.SUBSCRIPTION.ACTIVATED',
        'BILLING.SUBSCRIPTION.CANCELLED',
        'BILLING.SUBSCRIPTION.SUSPENDED',
        'BILLING.SUBSCRIPTION.EXPIRED',
        'PAYMENT.SALE.COMPLETED',
        'PAYMENT.SALE.DENIED'
    ];
    
    if (!in_array($event_type, $subscription_events)) {
        return ['success' => true, 'message' => 'Evento non gestito'];
    }
    
    $subscription_id = $resource['billing_agreement_id'] ?? $resource['id'] ?? null;
    
    if (!$subscription_id) {
        throw new Exception('ID abbonamento non trovato nell\'evento');
    }
    
    // Trova l'utente con questo subscription_id
    $stmt = $pdo->prepare("SELECT id FROM users WHERE subscription_id = ?");
    $stmt->execute([$subscription_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("Utente non trovato per subscription_id: $subscription_id");
    }
    
    $user_id = $user['id'];
    
    // Ottieni dettagli aggiornati dell'abbonamento da PayPal
    $access_token = getPayPalAccessToken($paypal_client_id, $paypal_client_secret);
    $subscription_details = null;
    
    if ($access_token) {
        $subscription_details = getSubscriptionDetails($subscription_id, $access_token);
    }
    
    // Determina il nuovo stato e la data di scadenza
    $new_status = 'free';
    $end_date = null;
    $db_event_type = 'payment_completed';
    
    switch ($event_type) {
        case 'BILLING.SUBSCRIPTION.ACTIVATED':
            $new_status = 'active';
            $db_event_type = 'activated';
            // Calcola data di scadenza (1 mese da ora)
            $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
            break;
            
        case 'BILLING.SUBSCRIPTION.CANCELLED':
            $new_status = 'cancelled';
            $db_event_type = 'cancelled';
            // Mantieni accesso fino alla fine del periodo pagato
            if ($subscription_details && isset($subscription_details['billing_info']['next_billing_time'])) {
                $end_date = date('Y-m-d H:i:s', strtotime($subscription_details['billing_info']['next_billing_time']));
            } else {
                // Fallback: 30 giorni da ora
                $end_date = date('Y-m-d H:i:s', strtotime('+30 days'));
            }
            break;
            
        case 'BILLING.SUBSCRIPTION.SUSPENDED':
            $new_status = 'cancelled';
            $db_event_type = 'suspended';
            $end_date = date('Y-m-d H:i:s'); // Scade immediatamente
            break;
            
        case 'BILLING.SUBSCRIPTION.EXPIRED':
            $new_status = 'expired';
            $db_event_type = 'expired';
            $end_date = date('Y-m-d H:i:s'); // Già scaduto
            break;
            
        case 'PAYMENT.SALE.COMPLETED':
            $new_status = 'active';
            $db_event_type = 'payment_completed';
            // Estendi di 1 mese dalla data attuale di scadenza o da ora
            $current_end = null;
            $stmt = $pdo->prepare("SELECT subscription_end FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($current_user && $current_user['subscription_end'] && strtotime($current_user['subscription_end']) > time()) {
                // Estendi dalla data di scadenza attuale
                $end_date = date('Y-m-d H:i:s', strtotime($current_user['subscription_end'] . ' +1 month'));
            } else {
                // Nuovo abbonamento o scaduto, inizia da ora
                $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
            }
            break;
            
        case 'PAYMENT.SALE.DENIED':
            // Non cambiare lo stato immediatamente, potrebbe essere un problema temporaneo
            $db_event_type = 'payment_failed';
            return ['success' => true, 'message' => 'Pagamento fallito registrato, stato non modificato'];
    }
    
    // Aggiorna lo stato dell'abbonamento
    $stmt = $pdo->prepare("
        CALL UpdateSubscriptionStatus(?, ?, ?, ?, 'paypal_webhook', ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $subscription_id,
        $new_status,
        $end_date,
        $db_event_type,
        json_encode($event_data)
    ]);
    
    return [
        'success' => true, 
        'message' => "Abbonamento aggiornato: $new_status",
        'user_id' => $user_id,
        'new_status' => $new_status,
        'end_date' => $end_date
    ];
}

// Gestione della richiesta webhook
try {
    // Leggi il corpo della richiesta
    $input = file_get_contents('php://input');
    $headers = getallheaders();
    
    if (empty($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Corpo richiesta vuoto']);
        exit;
    }
    
    $webhook_data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON non valido']);
        exit;
    }
    
    // Log della richiesta
    logWebhook($webhook_data);
    
    // Verifica la firma del webhook (IMPORTANTE per la sicurezza)
    if (!verifyPayPalWebhook($headers, $input, $paypal_webhook_id)) {
        http_response_code(401);
        logWebhook($webhook_data, false, 'Firma webhook non valida');
        echo json_encode(['error' => 'Firma non valida']);
        exit;
    }
    
    // Processa l'evento
    $result = processWebhookEvent($webhook_data);
    
    // Aggiorna il log come processato
    $stmt = $pdo->prepare("
        UPDATE webhook_logs 
        SET processed = TRUE, processed_at = NOW() 
        WHERE webhook_id = ?
    ");
    $stmt->execute([$webhook_data['id'] ?? null]);
    
    // Risposta di successo
    http_response_code(200);
    echo json_encode($result);
    
} catch (Exception $e) {
    // Log dell'errore
    error_log("Errore webhook PayPal: " . $e->getMessage());
    
    if (isset($webhook_data)) {
        logWebhook($webhook_data, false, $e->getMessage());
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno del server']);
}
?>