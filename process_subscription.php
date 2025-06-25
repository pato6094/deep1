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
    // Aggiorna lo stato dell'abbonamento dell'utente
    $stmt = $pdo->prepare("
        UPDATE users 
        SET subscription_status = 'active', 
            subscription_id = :subscription_id,
            subscription_start = NOW(),
            subscription_end = DATE_ADD(NOW(), INTERVAL 1 MONTH)
        WHERE id = :user_id
    ");
    
    $result = $stmt->execute([
        ':subscription_id' => $subscription_id,
        ':user_id' => $user_id
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Abbonamento attivato']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore database']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Errore server']);
}
?>