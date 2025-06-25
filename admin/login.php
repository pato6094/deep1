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
        $stmt = $pdo->prepare("SELECT id, name, email, password, is_admin FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_admin'] == 1) {
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_name'] = $user['name'];
                $_SESSION['admin_email'] = $user['email'];
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Non hai i permessi per accedere al pannello admin.';
            }
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
    <title>Admin Login - DeepLink Pro</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-login-body {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .admin-login-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .admin-logo {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .admin-title {
            color: #dc3545;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body class="admin-login-body">
    <div class="admin-login-card">
        <div class="admin-logo">🛡️</div>
        <h2 class="admin-title">Admin Panel</h2>
        <p style="color: #666; margin-bottom: 2rem;">Accesso riservato agli amministratori</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Admin</label>
                <input type="email" id="email" name="email" class="form-control" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                Accedi al Pannello
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee;">
            <a href="../index.php" style="color: #666; text-decoration: none;">← Torna al sito</a>
        </div>
    </div>
</body>
</html>