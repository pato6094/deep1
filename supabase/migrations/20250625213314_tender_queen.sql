/*
  # Sistema di tracciamento abbonamenti semplificato

  1. Aggiornamenti alla tabella users
    - Campi per tracciare durata e scadenza abbonamenti
    - Sistema di controllo basato su date

  2. Tabella per log eventi
    - Tracciamento manuale degli eventi importanti

  3. Sistema automatico di controllo scadenze
    - Basato solo su date, senza API esterne
*/

-- Aggiungi colonne per tracciamento semplificato degli abbonamenti
DO $$
BEGIN
    -- Colonna per tracciare quando l'abbonamento è stato creato/rinnovato
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'users' AND column_name = 'subscription_created_at'
    ) THEN
        ALTER TABLE users ADD COLUMN subscription_created_at TIMESTAMP NULL;
    END IF;
    
    -- Colonna per tracciare l'ultimo pagamento ricevuto
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'users' AND column_name = 'last_payment_date'
    ) THEN
        ALTER TABLE users ADD COLUMN last_payment_date TIMESTAMP NULL;
    END IF;
    
    -- Colonna per note admin
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'users' AND column_name = 'admin_notes'
    ) THEN
        ALTER TABLE users ADD COLUMN admin_notes TEXT NULL;
    END IF;
    
    -- Colonna per tracciare se l'abbonamento è stato verificato manualmente
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'users' AND column_name = 'manually_verified'
    ) THEN
        ALTER TABLE users ADD COLUMN manually_verified BOOLEAN DEFAULT FALSE;
    END IF;
END $$;

-- Tabella per log eventi semplificati
CREATE TABLE IF NOT EXISTS simple_subscription_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action ENUM('subscription_created', 'subscription_renewed', 'subscription_expired', 'manual_upgrade', 'manual_downgrade') NOT NULL,
    performed_by ENUM('system', 'admin', 'user') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_simple_logs_user_id (user_id),
    INDEX idx_simple_logs_action (action),
    INDEX idx_simple_logs_created_at (created_at)
);

-- Vista per abbonamenti in scadenza (prossimi 7 giorni)
CREATE OR REPLACE VIEW expiring_subscriptions_simple AS
SELECT 
    u.id,
    u.name,
    u.email,
    u.subscription_status,
    u.subscription_end,
    u.last_payment_date,
    u.manually_verified,
    DATEDIFF(u.subscription_end, NOW()) as days_until_expiry
FROM users u
WHERE u.subscription_status = 'active'
  AND u.subscription_end IS NOT NULL
  AND u.subscription_end <= DATE_ADD(NOW(), INTERVAL 7 DAY)
  AND u.subscription_end > NOW();

-- Vista per statistiche abbonamenti
CREATE OR REPLACE VIEW subscription_stats_simple AS
SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active_subscriptions,
    SUM(CASE WHEN subscription_status = 'expired' THEN 1 ELSE 0 END) as expired_subscriptions,
    SUM(CASE WHEN subscription_status = 'free' THEN 1 ELSE 0 END) as free_users,
    SUM(CASE WHEN subscription_status = 'active' AND manually_verified = TRUE THEN 1 ELSE 0 END) as manually_verified_subscriptions,
    AVG(CASE WHEN subscription_status = 'active' AND subscription_created_at IS NOT NULL 
        THEN DATEDIFF(NOW(), subscription_created_at) ELSE NULL END) as avg_subscription_days
FROM users;

-- Stored procedure per aggiornare abbonamento (versione semplificata)
DELIMITER //
CREATE OR REPLACE PROCEDURE UpdateSubscriptionSimple(
    IN p_user_id INT,
    IN p_status ENUM('free', 'active', 'expired'),
    IN p_end_date DATETIME,
    IN p_performed_by ENUM('system', 'admin', 'user'),
    IN p_action ENUM('subscription_created', 'subscription_renewed', 'subscription_expired', 'manual_upgrade', 'manual_downgrade'),
    IN p_notes TEXT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Aggiorna lo stato dell'utente
    UPDATE users 
    SET 
        subscription_status = p_status,
        subscription_end = p_end_date,
        last_payment_date = CASE WHEN p_action IN ('subscription_created', 'subscription_renewed', 'manual_upgrade') THEN NOW() ELSE last_payment_date END,
        subscription_created_at = CASE WHEN p_action = 'subscription_created' THEN NOW() ELSE subscription_created_at END,
        manually_verified = CASE WHEN p_performed_by = 'admin' THEN TRUE ELSE manually_verified END
    WHERE id = p_user_id;
    
    -- Registra l'evento nel log
    INSERT INTO simple_subscription_logs (
        user_id, 
        action, 
        performed_by,
        notes
    ) VALUES (
        p_user_id, 
        p_action, 
        p_performed_by,
        p_notes
    );
    
    COMMIT;
END //
DELIMITER ;