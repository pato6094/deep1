-- Aggiornamento database per supportare le statistiche dei click

-- Tabella per il log dettagliato dei click (opzionale per analytics avanzate)
CREATE TABLE IF NOT EXISTS click_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deeplink_id VARCHAR(10) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referer TEXT,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deeplink_id) REFERENCES deeplinks(id) ON DELETE CASCADE
);

-- Indici per migliorare le performance delle query
CREATE INDEX idx_click_logs_deeplink_id ON click_logs(deeplink_id);
CREATE INDEX idx_click_logs_clicked_at ON click_logs(clicked_at);
CREATE INDEX idx_deeplinks_clicks ON deeplinks(clicks);
CREATE INDEX idx_deeplinks_user_clicks ON deeplinks(user_id, clicks);

-- Vista per statistiche rapide (opzionale)
CREATE OR REPLACE VIEW user_stats AS
SELECT 
    u.id as user_id,
    u.name,
    u.email,
    COUNT(d.id) as total_deeplinks,
    COALESCE(SUM(d.clicks), 0) as total_clicks,
    COALESCE(AVG(d.clicks), 0) as avg_clicks_per_deeplink,
    MAX(d.clicks) as max_clicks,
    COUNT(CASE WHEN d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as deeplinks_last_30_days,
    COALESCE(SUM(CASE WHEN d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN d.clicks ELSE 0 END), 0) as clicks_last_30_days
FROM users u
LEFT JOIN deeplinks d ON u.id = d.user_id
GROUP BY u.id, u.name, u.email;