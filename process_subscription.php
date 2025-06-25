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
    // Usa la nuova stored procedure per creare l'abbonamento con monitoraggio automatico
    $stmt = $pdo->prepare("CALL CreateSubscriptionWithMonitoring(?, ?, ?)");
    
    $duration_months = 1; // Abbonamento mensile
    
    $result = $stmt->execute([
        $user_id,
        $subscription_id,
        $duration_months
    ]);
    
    if ($result) {
        // Ottieni la data di scadenza aggiornata
        $stmt = $pdo->prepare("SELECT subscription_end FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Abbonamento attivato con monitoraggio automatico',
            'end_date' => $user_data['subscription_end'],
            'monitoring_enabled' => true
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore database']);
    }
} catch (Exception $e) {
    error_log("Errore process_subscription: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore server']);
}
?>