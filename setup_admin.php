<?php
require_once 'config/database.php';

try {
    // Aggiungi il campo is_admin alla tabella users se non esiste
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
        echo "Campo is_admin aggiunto con successo!<br>";
    } else {
        echo "Campo is_admin già presente.<br>";
    }
    
    // Imposta il primo utente (ID=1) come admin
    $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = 1");
    if ($stmt->execute()) {
        echo "Primo utente impostato come admin!<br>";
    }
    
    // Mostra gli utenti admin attuali
    $stmt = $pdo->query("SELECT id, name, email FROM users WHERE is_admin = 1");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Utenti Admin attuali:</h3>";
    if (empty($admins)) {
        echo "Nessun admin trovato.<br>";
    } else {
        foreach ($admins as $admin) {
            echo "ID: {$admin['id']} - Nome: {$admin['name']} - Email: {$admin['email']}<br>";
        }
    }
    
    echo "<br><a href='admin/index.php'>Vai al Pannello Admin</a>";
    
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage();
}
?>