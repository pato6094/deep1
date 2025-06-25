<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    http_response_code(404);
    echo "Link non trovato";
    exit;
}

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT deeplink, original_url, title, user_id, created_at FROM deeplinks WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo "Link non trovato";
    exit;
}

// Verifica se il link è scaduto (solo per utenti free)
$user_has_subscription = false;
if ($row['user_id']) {
    $user_has_subscription = has_active_subscription($pdo, $row['user_id']);
}

if (is_deeplink_expired($row['created_at'], $user_has_subscription)) {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Link Scaduto - DeepLink Pro</title>
        <link rel="stylesheet" href="assets/style.css">
        <style>
            .expired-container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .expired-card {
                background: white;
                border-radius: 20px;
                padding: 3rem;
                text-align: center;
                max-width: 500px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }
            .expired-icon {
                font-size: 4rem;
                margin-bottom: 1rem;
            }
        </style>
    </head>
    <body>
        <div class="expired-container">
            <div class="expired-card">
                <div class="expired-icon">⏰</div>
                <h2 style="color: #dc3545; margin-bottom: 1rem;">Link Scaduto</h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    Questo deeplink è scaduto. I link gratuiti durano 5 giorni dalla creazione.
                </p>
                <?php if ($row['title']): ?>
                <div style="margin-bottom: 1rem;">
                    <strong>Titolo:</strong><br>
                    <span style="color: #333;"><?= htmlspecialchars($row['title']) ?></span>
                </div>
                <?php endif; ?>
                <div style="margin-bottom: 2rem;">
                    <strong>URL originale:</strong><br>
                    <span style="color: #666; word-break: break-all;"><?= htmlspecialchars($row['original_url']) ?></span>
                </div>
                <a href="pricing.php" class="btn btn-primary" style="margin-right: 1rem;">
                    Passa a Premium
                </a>
                <a href="index.php" class="btn btn-secondary">
                    Torna alla Home
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Determina il tipo di app dal deeplink
$app_name = "l'app";
$app_icon = "📱";

if (strpos($row['deeplink'], 'youtube://') !== false) {
    $app_name = "YouTube";
    $app_icon = "📺";
} elseif (strpos($row['deeplink'], 'instagram://') !== false) {
    $app_name = "Instagram";
    $app_icon = "📷";
} elseif (strpos($row['deeplink'], 'twitch://') !== false) {
    $app_name = "Twitch";
    $app_icon = "🎮";
} elseif (strpos($row['deeplink'], 'amazon://') !== false) {
    $app_name = "Amazon";
    $app_icon = "🛒";
}

// Usa il titolo se disponibile, altrimenti usa il nome dell'app
$display_title = $row['title'] ? htmlspecialchars($row['title']) : "Apertura " . htmlspecialchars($app_name);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $display_title ?> - DeepLink Pro</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .countdown-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .countdown-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        .countdown-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            animation: loading 3s ease-in-out;
        }
        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        .app-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .countdown-number {
            font-size: 3rem;
            font-weight: 700;
            color: #667eea;
            margin: 1rem 0;
            animation: countdown 1s infinite;
        }
        @keyframes countdown {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .loading-dots {
            display: inline-block;
            position: relative;
            width: 80px;
            height: 20px;
            margin-top: 1rem;
        }
        .loading-dots div {
            position: absolute;
            top: 8px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #667eea;
            animation: loading-dots 1.2s linear infinite;
        }
        .loading-dots div:nth-child(1) { left: 8px; animation-delay: 0s; }
        .loading-dots div:nth-child(2) { left: 32px; animation-delay: -0.4s; }
        .loading-dots div:nth-child(3) { left: 56px; animation-delay: -0.8s; }
        @keyframes loading-dots {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }
        .fallback-link {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
            display: none;
        }
        .deeplink-title {
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="countdown-container">
        <div class="countdown-card">
            <div class="app-icon"><?= $app_icon ?></div>
            <?php if ($row['title']): ?>
                <div class="deeplink-title"><?= htmlspecialchars($row['title']) ?></div>
            <?php endif; ?>
            <h2 style="color: #333; margin-bottom: 1rem;">
                Sto aprendo <?= htmlspecialchars($app_name) ?> per te!
            </h2>
            <div class="countdown-number" id="countdown">3</div>
            <p style="color: #666;">
                Preparazione del deeplink in corso...
            </p>
            <div class="loading-dots">
                <div></div>
                <div></div>
                <div></div>
            </div>
            
            <div class="fallback-link" id="fallback">
                <p style="color: #666; margin-bottom: 1rem;">
                    L'app non si è aperta automaticamente?
                </p>
                <a href="<?= htmlspecialchars($row['deeplink']) ?>" class="btn btn-primary" style="margin-right: 1rem;">
                    Apri <?= htmlspecialchars($app_name) ?>
                </a>
                <a href="<?= htmlspecialchars($row['original_url']) ?>" class="btn btn-secondary" target="_blank">
                    Apri nel Browser
                </a>
            </div>
        </div>
    </div>

    <script>
        let countdown = 3;
        const countdownElement = document.getElementById('countdown');
        const fallbackElement = document.getElementById('fallback');
        
        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                
                // Aggiorna il contatore dei click
                fetch('update_click.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: '<?= $id ?>'
                    })
                });
                
                // Prova ad aprire l'app
                window.location.href = '<?= htmlspecialchars($row['deeplink']) ?>';
                
                // Mostra il fallback dopo 2 secondi
                setTimeout(() => {
                    fallbackElement.style.display = 'block';
                    countdownElement.style.display = 'none';
                    document.querySelector('h2').textContent = 'Apertura completata!';
                    document.querySelector('p').textContent = 'Se l\'app non si è aperta, usa i pulsanti qui sotto.';
                }, 2000);
            }
        }, 1000);
    </script>
</body>
</html>