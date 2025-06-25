<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Non Trovato - DeepLink Pro</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .error-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 1rem;
            line-height: 1;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <div class="error-icon">🔍</div>
            <div class="error-code">404</div>
            <h2 style="color: #333; margin-bottom: 1rem;">Link Non Trovato</h2>
            <p style="color: #666; margin-bottom: 2rem;">
                Il deeplink che stai cercando non esiste o potrebbe essere stato rimosso.
            </p>
            <div style="margin-bottom: 2rem;">
                <p style="color: #666; font-size: 0.9rem;">
                    Possibili cause:
                </p>
                <ul style="color: #666; font-size: 0.9rem; text-align: left; margin: 1rem 0;">
                    <li>Il link è stato digitato incorrettamente</li>
                    <li>Il deeplink è scaduto (link gratuiti durano 5 giorni)</li>
                    <li>Il link è stato rimosso dal proprietario</li>
                </ul>
            </div>
            <a href="index.php" class="btn btn-primary" style="margin-right: 1rem;">
                Torna alla Home
            </a>
            <a href="auth/register.php" class="btn btn-secondary">
                Crea Account
            </a>
        </div>
    </div>
</body>
</html>