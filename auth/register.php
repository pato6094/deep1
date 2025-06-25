<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validazione
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Tutti i campi sono obbligatori.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida.';
    } elseif (strlen($password) < 6) {
        $error = 'La password deve essere di almeno 6 caratteri.';
    } elseif ($password !== $confirm_password) {
        $error = 'Le password non coincidono.';
    } else {
        // Verifica se l'email esiste già
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        
        if ($stmt->fetch()) {
            $error = 'Email già registrata.';
        } else {
            // Registrazione utente
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, created_at) 
                VALUES (:name, :email, :password, NOW())
            ");
            
            if ($stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $hashed_password
            ])) {
                $success = 'Registrazione completata! Ora puoi effettuare il login.';
            } else {
                $error = 'Errore durante la registrazione.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione - DeepLink Generator</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="../index.php" class="logo">DeepLink Pro</a>
                <div class="nav-links">
                    <a href="../auth/login.php">Login</a>
                    <a href="../auth/register.php">Registrati</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div style="max-width: 500px; margin: 0 auto;">
                <div class="card">
                    <h2>Crea il tuo Account</h2>
                    <p style="color: #666; margin-bottom: 2rem;">Registrati per iniziare a creare deeplink personalizzati</p>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Nome Completo</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            <small style="color: #666;">Minimo 6 caratteri</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Conferma Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Registrati
                        </button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee;">
                        <p>Hai già un account? <a href="login.php" style="color: #667eea; text-decoration: none;">Accedi qui</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>