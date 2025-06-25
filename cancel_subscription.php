<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Recupera informazioni abbonamento
$stmt = $pdo->prepare("
    SELECT subscription_id, subscription_status 
    FROM users 
    WHERE id = :user_id AND subscription_status = 'active'
");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$user['subscription_id']) {
    echo json_encode(['success' => false, 'message' => 'Nessun abbonamento attivo trovato']);
    exit;
}

$subscription_id = $user['subscription_id'];

// Configurazione PayPal
$paypal_client_id = 'AQJDnagVff_mI2EtgXHdsCD_hduUKkOwKnGn2goqziCThEKDgzGDV3UWbza5b6Bz5w-kz4Ba-qqwxWyr';
$paypal_client_secret = 'EBWKjlELKMYqRNQ6sVD4XfSqjE7ty5TuHVIxHQMp_2MU8mMpnpep7_7txjWKqQOOBxaU6RuJzJHVxUOE'; // Sostituisci con il tuo client secret
$paypal_base_url = 'https://api-m.sandbox.paypal.com'; // Usa https://api-m.paypal.com per produzione

try {
    // 1. Ottieni token di accesso PayPal
    $auth_response = get_paypal_access_token($paypal_client_id, $paypal_client_secret, $paypal_base_url);
    
    if (!$auth_response || !isset($auth_response['access_token'])) {
        throw new Exception('Impossibile ottenere token PayPal');
    }
    
    $access_token = $auth_response['access_token'];
    
    // 2. Cancella l'abbonamento su PayPal
    $cancel_response = cancel_paypal_subscription($subscription_id, $access_token, $paypal_base_url);
    
    if ($cancel_response) {
        // 3. Aggiorna lo stato nel database
        $stmt = $pdo->prepare("
            UPDATE users 
            SET subscription_status = 'cancelled'
            WHERE id = :user_id
        ");
        
        if ($stmt->execute([':user_id' => $user_id])) {
            echo json_encode([
                'success' => true, 
                'message' => 'Abbonamento cancellato con successo su PayPal'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Abbonamento cancellato su PayPal ma errore aggiornamento database'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Errore durante la cancellazione su PayPal'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Errore cancellazione abbonamento PayPal: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}

/**
 * Ottiene il token di accesso PayPal
 */
function get_paypal_access_token($client_id, $client_secret, $base_url) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $base_url . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en_US',
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_USERPWD => $client_id . ':' . $client_secret,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return false;
}

/**
 * Cancella l'abbonamento PayPal
 */
function cancel_paypal_subscription($subscription_id, $access_token, $base_url) {
    $ch = curl_init();
    
    $cancel_data = json_encode([
        'reason' => 'Cancellazione richiesta dall\'utente'
    ]);
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $base_url . '/v1/billing/subscriptions/' . $subscription_id . '/cancel',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $cancel_data,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
            'Prefer: return=minimal'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // PayPal restituisce 204 per cancellazione riuscita
    return $http_code === 204;
}
?>