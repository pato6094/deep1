<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Email e password sono obbligatori.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            
            header('Location: ../dashboard.php');
            exit;
        } else {
            $error = 'Email o password non corretti.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DeepLink Generator</title>
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
                    <h2>Accedi al tuo Account</h2>
                    <p style="color: #666; margin-bottom: 2rem;">Benvenuto! Inserisci le tue credenziali per continuare</p>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Accedi
                        </button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee;">
                        <p>Non hai un account? <a href="register.php" style="color: #667eea; text-decoration: none;">Registrati qui</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>