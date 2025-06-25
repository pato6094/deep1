<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID mancante']);
    exit;
}

$deeplink_id = $input['id'];
$user_id = $_SESSION['user_id'];

try {
    // Verifica che il deeplink appartenga all'utente
    $stmt = $pdo->prepare("SELECT id FROM deeplinks WHERE id = :id AND user_id = :user_id");
    $stmt->execute([
        ':id' => $deeplink_id,
        ':user_id' => $user_id
    ]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Deeplink non trovato o non autorizzato']);
        exit;
    }
    
    // Elimina il deeplink
    $stmt = $pdo->prepare("DELETE FROM deeplinks WHERE id = :id AND user_id = :user_id");
    $result = $stmt->execute([
        ':id' => $deeplink_id,
        ':user_id' => $user_id
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Deeplink eliminato con successo']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione']);
    }
    
} catch (Exception $e) {
    error_log("Errore eliminazione deeplink: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore del server']);
}
?>