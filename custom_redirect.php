<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_GET['name'])) {
    http_response_code(404);
    include '404.php';
    exit;
}

$custom_name = $_GET['name'];

// Verifica che il nome custom sia valido
if (!preg_match('/^[a-zA-Z0-9\-_]{3,20}$/', $custom_name)) {
    http_response_code(404);
    include '404.php';
    exit;
}

try {
    // Cerca il deeplink con il nome personalizzato
    $stmt = $pdo->prepare("
        SELECT id, deeplink, original_url, title, user_id, created_at, custom_name 
        FROM deeplinks 
        WHERE custom_name = :custom_name
    ");
    $stmt->execute([':custom_name' => $custom_name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        include '404.php';
        exit;
    }

    // Verifica se il link è scaduto (solo per utenti free)
    $user_has_subscription = false;
    if ($row['user_id']) {
        $user_has_subscription = has_active_subscription($pdo, $row['user_id']);
    }

    if (is_deeplink_expired($row['created_at'], $user_has_subscription)) {
        // Reindirizza alla pagina di scadenza con l'ID originale
        header("Location: countdown.php?id=" . urlencode($row['id']));
        exit;
    }

    // Reindirizza alla pagina di countdown con l'ID originale
    header("Location: countdown.php?id=" . urlencode($row['id']));
    exit;

} catch (Exception $e) {
    error_log("Errore custom_redirect: " . $e->getMessage());
    http_response_code(500);
    include '404.php';
    exit;
}
?>