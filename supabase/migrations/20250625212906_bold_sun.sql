/*
  # Sistema di tracciamento abbonamenti PayPal

  1. Nuove Tabelle
    - `subscription_events` - Log di tutti gli eventi degli abbonamenti
    - `webhook_logs` - Log delle chiamate webhook per debugging

  2. Aggiornamenti
    - Aggiunta colonne per tracciare meglio gli stati degli abbonamenti
    - Trigger per aggiornare automaticamente gli stati

  3. Sicurezza
    - Indici per performance
    - Campi per tracciare la provenienza degli aggiornamenti
*/

-- Tabella per tracciare gli eventi degli abbonamenti
CREATE TABLE IF NOT EXISTS subscription_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id VARCHAR(255) NOT NULL,
    event_type ENUM('created', 'activated', 'suspended', 'cancelled', 'expired', 'payment_failed', 'payment_completed') NOT NULL,
    event_data JSON,
    paypal_event_id VARCHAR(255),
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_subscription_events_user_id (user_id),
    INDEX idx_subscription_events_subscription_id (subscription_id),
    INDEX idx_subscription_events_type (event_type),
    INDEX idx_subscription_events_paypal_id (paypal_event_id)
);

-- Tabella per log dei webhook (per debugging)
CREATE TABLE IF NOT EXISTS webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id VARCHAR(255),
    event_type VARCHAR(100),
    resource_type VARCHAR(100),
    summary TEXT,
    raw_data JSON,
    processed BOOLEAN DEFAULT FALSE,
    error_message TEXT,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_webhook_logs_webhook_id (webhook_id),
    INDEX idx_webhook_logs_processed (processed),
    INDEX idx_webhook_logs_received_at (received_at)
);

-- Aggiungi colonne per tracciare meglio gli abbonamenti
DO $$
BEGIN
    -- Colonna per tracciare l'ultimo aggiornamento dello stato
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'users' AND column_name = 'subscription_updated_at'
    ) THEN
        ALTER TABLE users ADD COLUMN subscription_updated_at TIMESTAMP NULL;
    END IF;
    
    -- Colonna per tracciare la fonte dell'ultimo aggiornamento
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'users' AND column_name = 'subscription_update_source'
    ) THEN
        ALTER TABLE users ADD COLUMN subscription_update_source ENUM('manual', 'paypal_webhook', 'system_check') DEFAULT 'manual';
    END IF;
    
    -- Colonna per tracciare se l'abbonamento è in stato di grazia
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'users' AND column_name = 'subscription_grace_period'
    ) THEN
        ALTER TABLE users ADD COLUMN subscription_grace_period TIMESTAMP NULL;
    END IF;
END $$;

-- Stored procedure per aggiornare lo stato dell'abbonamento
DELIMITER //
CREATE OR REPLACE PROCEDURE UpdateSubscriptionStatus(
    IN p_user_id INT,
    IN p_subscription_id VARCHAR(255),
    IN p_status ENUM('free', 'active', 'cancelled', 'expired'),
    IN p_end_date DATETIME,
    IN p_source ENUM('manual', 'paypal_webhook', 'system_check'),
    IN p_event_type VARCHAR(50),
    IN p_event_data JSON
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
        subscription_updated_at = NOW(),
        subscription_update_source = p_source,
        subscription_grace_period = CASE 
            WHEN p_status = 'cancelled' THEN DATE_ADD(NOW(), INTERVAL 3 DAY)
            ELSE NULL 
        END
    WHERE id = p_user_id;
    
    -- Registra l'evento
    INSERT INTO subscription_events (
        user_id, 
        subscription_id, 
        event_type, 
        event_data,
        paypal_event_id
    ) VALUES (
        p_user_id, 
        p_subscription_id, 
        p_event_type, 
        p_event_data,
        JSON_UNQUOTE(JSON_EXTRACT(p_event_data, '$.id'))
    );
    
    COMMIT;
END //
DELIMITER ;

-- Vista per monitorare gli abbonamenti che stanno per scadere
CREATE OR REPLACE VIEW expiring_subscriptions AS
SELECT 
    u.id,
    u.name,
    u.email,
    u.subscription_id,
    u.subscription_status,
    u.subscription_end,
    u.subscription_updated_at,
    u.subscription_update_source,
    DATEDIFF(u.subscription_end, NOW()) as days_until_expiry
FROM users u
WHERE u.subscription_status = 'active'
  AND u.subscription_end IS NOT NULL
  AND u.subscription_end <= DATE_ADD(NOW(), INTERVAL 7 DAY);

-- Vista per statistiche degli abbonamenti
CREATE OR REPLACE VIEW subscription_stats AS
SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active_subscriptions,
    SUM(CASE WHEN subscription_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_subscriptions,
    SUM(CASE WHEN subscription_status = 'expired' THEN 1 ELSE 0 END) as expired_subscriptions,
    SUM(CASE WHEN subscription_status = 'free' THEN 1 ELSE 0 END) as free_users,
    AVG(CASE WHEN subscription_status = 'active' AND subscription_start IS NOT NULL 
        THEN DATEDIFF(NOW(), subscription_start) ELSE NULL END) as avg_subscription_days
FROM users;