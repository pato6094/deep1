<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$deeplink_url = "";
$error = "";
$success = "";

// Statistiche utente
$monthly_count = count_monthly_deeplinks($pdo, $user_id);
$has_subscription = has_active_subscription($pdo, $user_id);
$can_create = can_create_deeplink($pdo, $user_id);

// Statistiche totali click (solo per utenti PRO)
$total_clicks = $has_subscription ? get_total_clicks($pdo, $user_id) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    if (!$can_create) {
        $error = "Hai raggiunto il limite mensile di 5 deeplink. Passa al piano Premium per deeplink illimitati!";
    } else {
        $original_url = filter_var($_POST['url'], FILTER_SANITIZE_URL);

        if (filter_var($original_url, FILTER_VALIDATE_URL)) {
            $deeplink = generate_deeplink($original_url);
            $id = substr(hash('sha256', $original_url . $user_id . time()), 0, 8);

            $stmt = $pdo->prepare("
                INSERT INTO deeplinks (id, original_url, deeplink, user_id, created_at) 
                VALUES (:id, :original_url, :deeplink, :user_id, NOW())
            ");
            
            if ($stmt->execute([
                ':id' => $id,
                ':original_url' => $original_url,
                ':deeplink' => $deeplink,
                ':user_id' => $user_id
            ])) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                $deeplink_url = "$protocol://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/redirect.php?id=$id";
                $success = "Deeplink creato con successo!";
            } else {
                $error = "Errore durante la creazione del deeplink.";
            }
        } else {
            $error = "Inserisci un URL valido.";
        }
    }
}

// Recupera gli ultimi deeplink dell'utente con statistiche
$stmt = $pdo->prepare("
    SELECT id, original_url, clicks, created_at 
    FROM deeplinks 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([':user_id' => $user_id]);
$recent_deeplinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 deeplink più cliccati (solo per utenti PRO)
$top_deeplinks = [];
if ($has_subscription) {
    $stmt = $pdo->prepare("
        SELECT id, original_url, clicks, created_at 
        FROM deeplinks 
        WHERE user_id = :user_id AND clicks > 0
        ORDER BY clicks DESC 
        LIMIT 5
    ");
    $stmt->execute([':user_id' => $user_id]);
    $top_deeplinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DeepLink Generator</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .expiry-info {
            background: #fff3cd;
            color: #856404;
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            border: 1px solid #ffeaa7;
        }
        .expiry-warning {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .expiry-expired {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">DeepLink Pro</a>
                <div class="nav-links">
                    <span>Ciao, <?= htmlspecialchars($_SESSION['user_name']) ?>!</span>
                    <?php if (!$has_subscription): ?>
                        <a href="pricing.php" class="btn btn-success" style="padding: 0.5rem 1rem;">Upgrade Premium</a>
                    <?php endif; ?>
                    <a href="auth/logout.php">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <!-- Statistiche -->
            <div class="usage-stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $monthly_count ?></div>
                    <div class="stat-label">Deeplink questo mese</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $has_subscription ? '∞' : (5 - $monthly_count) ?></div>
                    <div class="stat-label"><?= $has_subscription ? 'Illimitati' : 'Rimanenti' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $has_subscription ? $total_clicks : '🔒' ?></div>
                    <div class="stat-label"><?= $has_subscription ? 'Click Totali' : 'Solo PRO' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $has_subscription ? 'PRO' : 'FREE' ?></div>
                    <div class="stat-label">Piano attuale</div>
                </div>
            </div>

            <!-- Generatore Deeplink -->
            <div class="card">
                <h2>Genera Nuovo Deeplink</h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    Supportiamo YouTube, Instagram, Twitch, Amazon e molti altri servizi
                </p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <?php if (!$can_create): ?>
                    <div class="alert alert-info">
                        Hai raggiunto il limite mensile. <a href="pricing.php" style="color: #667eea;">Passa a Premium</a> per deeplink illimitati!
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="url">URL da convertire</label>
                        <input type="url" id="url" name="url" class="form-control" 
                               placeholder="https://www.youtube.com/watch?v=..." 
                               <?= !$can_create ? 'disabled' : '' ?> required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" <?= !$can_create ? 'disabled' : '' ?>>
                        Genera Deeplink
                    </button>
                </form>
                
                <?php if ($deeplink_url): ?>
                    <div class="result">
                        <strong>Il tuo deeplink è pronto!</strong><br>
                        <a href="<?= htmlspecialchars($deeplink_url) ?>" target="_blank">
                            <?= htmlspecialchars($deeplink_url) ?>
                        </a>
                        <?php if (!$has_subscription): ?>
                            <div class="expiry-info">
                                ⏰ <strong>Attenzione:</strong> Questo link scadrà tra 5 giorni. 
                                <a href="pricing.php" style="color: #856404;">Passa a Premium</a> per link permanenti!
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Deeplink per Click (Solo PRO) -->
            <?php if ($has_subscription && !empty($top_deeplinks)): ?>
            <div class="card">
                <h2>🏆 Top Deeplink per Click</h2>
                <p style="color: #666; margin-bottom: 2rem;">I tuoi deeplink più performanti</p>
                
                <div style="overflow-x: auto;">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Posizione</th>
                                <th>URL Originale</th>
                                <th>Click</th>
                                <th>Data Creazione</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_deeplinks as $index => $link): ?>
                            <tr>
                                <td>
                                    <div class="performance-rank" style="position: static; width: 30px; height: 30px; font-size: 0.8rem;">
                                        #<?= $index + 1 ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="url-cell">
                                        <div class="url-text">
                                            <?= htmlspecialchars(substr($link['original_url'], 0, 60)) ?><?= strlen($link['original_url']) > 60 ? '...' : '' ?>
                                        </div>
                                        <div class="url-domain">
                                            <?= parse_url($link['original_url'], PHP_URL_HOST) ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="click-badge active">
                                        <?= $link['clicks'] ?>
                                    </div>
                                </td>
                                <td class="date-cell">
                                    <?= date('d/m/Y H:i', strtotime($link['created_at'])) ?>
                                </td>
                                <td>
                                    <a href="redirect.php?id=<?= $link['id'] ?>" target="_blank" 
                                       class="btn btn-secondary btn-sm">
                                        Apri
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif (!$has_subscription): ?>
            <div class="card">
                <h2>🏆 Top Deeplink per Click</h2>
                <div class="alert alert-info">
                    <strong>Funzionalità Premium</strong><br>
                    Le statistiche dettagliate sui click sono disponibili solo per gli utenti Premium.
                    <a href="pricing.php" style="color: #667eea;">Passa a Premium</a> per sbloccare questa funzionalità!
                </div>
            </div>
            <?php endif; ?>

            <!-- Deeplink Recenti con Statistiche -->
            <?php if (!empty($recent_deeplinks)): ?>
            <div class="card">
                <h2>📊 I tuoi Deeplink Recenti</h2>
                <p style="color: #666; margin-bottom: 2rem;">
                    Cronologia completa dei tuoi deeplink
                    <?php if (!$has_subscription): ?>
                        <span style="color: #f39c12;">(Statistiche click disponibili solo per utenti Premium)</span>
                    <?php endif; ?>
                </p>
                
                <div style="overflow-x: auto;">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>URL Originale</th>
                                <th>Click</th>
                                <th>Data Creazione</th>
                                <th>Stato</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_deeplinks as $link): ?>
                            <?php 
                                $is_expired = is_deeplink_expired($link['created_at'], $has_subscription);
                                $days_remaining = get_days_until_expiry($link['created_at'], $has_subscription);
                            ?>
                            <tr>
                                <td>
                                    <div class="url-cell">
                                        <div class="url-text">
                                            <?= htmlspecialchars(substr($link['original_url'], 0, 60)) ?><?= strlen($link['original_url']) > 60 ? '...' : '' ?>
                                        </div>
                                        <div class="url-domain">
                                            <?= parse_url($link['original_url'], PHP_URL_HOST) ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($has_subscription): ?>
                                        <div class="click-badge <?= $link['clicks'] > 0 ? 'active' : '' ?>">
                                            <?= $link['clicks'] ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="click-badge" style="background: #f8f9fa; color: #999;">
                                            🔒
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="date-cell">
                                    <?= date('d/m/Y H:i', strtotime($link['created_at'])) ?>
                                </td>
                                <td>
                                    <?php if ($has_subscription): ?>
                                        <span style="color: #28a745; font-weight: 600;">✓ Permanente</span>
                                    <?php elseif ($is_expired): ?>
                                        <span style="color: #dc3545; font-weight: 600;">✗ Scaduto</span>
                                    <?php elseif ($days_remaining <= 1): ?>
                                        <span style="color: #fd7e14; font-weight: 600;">⚠ Scade oggi</span>
                                    <?php else: ?>
                                        <span style="color: #6c757d;">⏰ <?= $days_remaining ?> giorni</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$is_expired): ?>
                                        <a href="redirect.php?id=<?= $link['id'] ?>" target="_blank" 
                                           class="btn btn-secondary btn-sm">
                                            Apri
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-size: 0.875rem;">Link scaduto</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Suggerimenti per migliorare le performance -->
            <div class="card tips-card">
                <h2>💡 Suggerimenti per Migliorare le Performance</h2>
                <div class="tips-grid">
                    <div class="tip-item">
                        <div class="tip-icon">📱</div>
                        <div class="tip-content">
                            <h3>Condividi sui Social</h3>
                            <p>I deeplink funzionano meglio quando condivisi direttamente sui social media</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon">⏰</div>
                        <div class="tip-content">
                            <h3>Timing Ottimale</h3>
                            <p>Condividi i tuoi deeplink negli orari di maggiore attività del tuo pubblico</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon">🎯</div>
                        <div class="tip-content">
                            <h3>Contenuto Rilevante</h3>
                            <p>Assicurati che il contenuto linkato sia interessante per il tuo target</p>
                        </div>
                    </div>
                    <?php if (!$has_subscription): ?>
                    <div class="tip-item">
                        <div class="tip-icon">📊</div>
                        <div class="tip-content">
                            <h3>Statistiche Premium</h3>
                            <p><a href="pricing.php" style="color: #667eea;">Passa a Premium</a> per vedere le statistiche dettagliate dei click</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>