<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['subscriptionID']) || !isset($input['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Dati mancanti']);
    exit;
}

$subscription_id = $input['subscriptionID'];
$user_id = $input['user_id'];

// Verifica che l'utente corrisponda alla sessione
if ($user_id != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Utente non valido']);
    exit;
}

try {
    // Calcola la data di scadenza (1 mese da ora)
    $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
    
    // Aggiorna lo stato dell'abbonamento dell'utente usando la stored procedure semplificata
    $stmt = $pdo->prepare("
        CALL UpdateSubscriptionSimple(?, 'active', ?, 'user', 'subscription_created', ?)
    ");
    
    $notes = "Abbonamento creato tramite PayPal. Subscription ID: {$subscription_id}";
    
    $result = $stmt->execute([
        $user_id,
        $end_date,
        $notes
    ]);
    
    if ($result) {
        // Aggiorna anche il subscription_id nella tabella users
        $stmt = $pdo->prepare("
            UPDATE users 
            SET subscription_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$subscription_id, $user_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Abbonamento attivato',
            'end_date' => $end_date
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore database']);
    }
} catch (Exception $e) {
    error_log("Errore process_subscription: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore server']);
}
?>