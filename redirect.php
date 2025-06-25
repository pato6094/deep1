<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    http_response_code(404);
    echo "Link non trovato";
    exit;
}

$id = $_GET['id'];

// Reindirizza alla pagina di countdown
header("Location: countdown.php?id=" . urlencode($id));
exit;
?>