<?php
// Database configuration
$host = 'localhost';
$dbname = 'buonafor_deep';
$username = 'buonafor_dee';
$password = 'Pianeta123!';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Errore connessione DB: " . $e->getMessage());
}
?>