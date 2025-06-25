<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$deeplink_url = "";
$error = "";

// Se l'utente è loggato, reindirizza alla dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $original_url = filter_var($_POST['url'], FILTER_SANITIZE_URL);

    if (filter_var($original_url, FILTER_VALIDATE_URL)) {
        $deeplink = generate_deeplink($original_url);
        $id = substr(hash('sha256', $original_url), 0, 6);

        // Inserimento nel DB senza user_id per utenti non registrati
        $stmt = $pdo->prepare("REPLACE INTO deeplinks (id, original_url, deeplink, created_at) VALUES (:id, :original_url, :deeplink, NOW())");
        $stmt->execute([
            ':id' => $id,
            ':original_url' => $original_url,
            ':deeplink' => $deeplink
        ]);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $deeplink_url = "$protocol://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/redirect.php?id=$id";
    } else {
        $error = "Inserisci un URL valido.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeepLink Generator - Converti URL in Deeplink</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">DeepLink Pro</a>
                <div class="nav-links">
                    <a href="pricing.php">Prezzi</a>
                    <a href="auth/login.php">Login</a>
                    <a href="auth/register.php" class="btn btn-primary">Registrati</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div class="hero">
                <h1>Genera Deeplink Professionali</h1>
                <p>Converti qualsiasi URL in deeplink per aprire direttamente le app mobili</p>
            </div>

            <div style="max-width: 600px; margin: 0 auto;">
                <div class="card">
                    <h2>Prova Gratuita</h2>
                    <p style="color: #666; margin-bottom: 2rem;">
                        Genera il tuo primo deeplink gratuitamente. Registrati per funzionalità avanzate!
                    </p>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="url">URL da convertire</label>
                            <input type="url" id="url" name="url" class="form-control" 
                                   placeholder="https://www.youtube.com/watch?v=..." required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Genera Deeplink
                        </button>
                    </form>
                    
                    <?php if ($deeplink_url): ?>
                        <div class="result">
                            <strong>Il tuo deeplink è pronto!</strong><br>
                            <a href="<?= htmlspecialchars($deeplink_url) ?>" target="_blank">
                                <?= htmlspecialchars($deeplink_url) ?>
                            </a>
                            <div style="background: #fff3cd; color: #856404; padding: 0.75rem; border-radius: 8px; margin-top: 1rem; border: 1px solid #ffeaa7;">
                                ⏰ <strong>Attenzione:</strong> I link gratuiti scadono dopo 5 giorni. 
                                <a href="auth/register.php" style="color: #856404;">Registrati</a> per creare fino a 5 link al mese!
                            </div>
                        </div>
                        
                        <div class="alert alert-info" style="margin-top: 1rem;">
                            <strong>Vuoi di più?</strong> 
                            <a href="auth/register.php" style="color: #667eea;">Registrati</a> 
                            per creare fino a 5 deeplink al mese gratuitamente!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Caratteristiche -->
            <div style="margin-top: 4rem;">
                <h2 style="text-align: center; color: white; margin-bottom: 3rem;">Perché Scegliere DeepLink Pro?</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div class="card">
                        <h3 style="color: #667eea; margin-bottom: 1rem;">🚀 Veloce e Affidabile</h3>
                        <p>Genera deeplink in millisecondi con la nostra tecnologia ottimizzata.</p>
                    </div>
                    
                    <div class="card">
                        <h3 style="color: #667eea; margin-bottom: 1rem;">📱 Multi-Piattaforma</h3>
                        <p>Supporto per YouTube, Instagram, Twitch, Amazon e molte altre app.</p>
                    </div>
                    
                    <div class="card">
                        <h3 style="color: #667eea; margin-bottom: 1rem;">📊 Analytics Avanzate</h3>
                        <p>Traccia le performance dei tuoi deeplink con statistiche dettagliate.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>