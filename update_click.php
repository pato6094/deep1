<?php
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ID']);
    exit;
}

$id = $input['id'];

try {
    // Aggiorna il contatore dei click
    $stmt = $pdo->prepare("UPDATE deeplinks SET clicks = clicks + 1 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    // Log del click per analytics future (opzionale)
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO click_logs (deeplink_id, ip_address, user_agent, referer, clicked_at) 
            VALUES (:deeplink_id, :ip_address, :user_agent, :referer, NOW())
        ");
        $stmt->execute([
            ':deeplink_id' => $id,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent,
            ':referer' => $referer
        ]);
    } catch (Exception $e) {
        // Se la tabella click_logs non esiste, ignora l'errore
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>