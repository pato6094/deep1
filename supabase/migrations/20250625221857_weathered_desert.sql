/*
  # Sistema automatizzato per gestione abbonamenti senza API

  1. Nuove Tabelle
    - `subscription_monitoring` - Monitora i pagamenti PayPal
    - `payment_tracking` - Traccia i pagamenti ricevuti

  2. Automazione
    - Controllo automatico dei pagamenti mancanti
    - Gestione automatica delle scadenze
    - Sistema di grace period automatico

  3. Sicurezza
    - Indici per performance
    - Trigger automatici per aggiornamenti
*/

-- Tabella per monitorare i pagamenti PayPal
CREATE TABLE IF NOT EXISTS subscription_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id VARCHAR(255) NOT NULL,
    expected_payment_date DATE NOT NULL,
    payment_received BOOLEAN DEFAULT FALSE,
    payment_received_date TIMESTAMP NULL,
    grace_period_start DATE NULL,
    grace_period_end DATE NULL,
    auto_cancelled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_monitoring_user_id (user_id),
    INDEX idx_monitoring_subscription_id (subscription_id),
    INDEX idx_monitoring_expected_date (expected_payment_date),
    INDEX idx_monitoring_payment_received (payment_received)
);

-- Tabella per tracciare i pagamenti ricevuti (da webhook o manuale)
CREATE TABLE IF NOT EXISTS payment_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id VARCHAR(255) NOT NULL,
    payment_amount DECIMAL(10,2),
    payment_date TIMESTAMP NOT NULL,
    payment_source ENUM('paypal_webhook', 'manual_entry', 'system_detected') DEFAULT 'manual_entry',
    payment_reference VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_payment_user_id (user_id),
    INDEX idx_payment_subscription_id (subscription_id),
    INDEX idx_payment_date (payment_date)
);

-- Stored procedure per creare un nuovo abbonamento con monitoraggio automatico
DELIMITER //
CREATE OR REPLACE PROCEDURE CreateSubscriptionWithMonitoring(
    IN p_user_id INT,
    IN p_subscription_id VARCHAR(255),
    IN p_duration_months INT
)
BEGIN
    DECLARE v_end_date DATETIME;
    DECLARE v_next_payment_date DATE;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Calcola date
    SET v_end_date = DATE_ADD(NOW(), INTERVAL p_duration_months MONTH);
    SET v_next_payment_date = DATE_ADD(CURDATE(), INTERVAL p_duration_months MONTH);
    
    -- Aggiorna l'utente
    UPDATE users 
    SET 
        subscription_status = 'active',
        subscription_id = p_subscription_id,
        subscription_start = NOW(),
        subscription_end = v_end_date,
        subscription_created_at = NOW(),
        last_payment_date = NOW()
    WHERE id = p_user_id;
    
    -- Crea il monitoraggio per il prossimo pagamento
    INSERT INTO subscription_monitoring (
        user_id, 
        subscription_id, 
        expected_payment_date,
        payment_received
    ) VALUES (
        p_user_id, 
        p_subscription_id, 
        v_next_payment_date,
        FALSE
    );
    
    -- Registra il pagamento iniziale
    INSERT INTO payment_tracking (
        user_id,
        subscription_id,
        payment_amount,
        payment_date,
        payment_source,
        payment_reference
    ) VALUES (
        p_user_id,
        p_subscription_id,
        9.99,
        NOW(),
        'system_detected',
        'Initial subscription payment'
    );
    
    -- Log dell'evento
    INSERT INTO simple_subscription_logs (
        user_id, 
        action, 
        performed_by,
        notes
    ) VALUES (
        p_user_id, 
        'subscription_created', 
        'system',
        CONCAT('Abbonamento creato con monitoraggio automatico. Subscription ID: ', p_subscription_id)
    );
    
    COMMIT;
END //
DELIMITER ;

-- Stored procedure per il controllo automatico dei pagamenti mancanti
DELIMITER //
CREATE OR REPLACE PROCEDURE CheckMissingPayments()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_user_id INT;
    DECLARE v_subscription_id VARCHAR(255);
    DECLARE v_expected_date DATE;
    DECLARE v_monitoring_id INT;
    DECLARE v_grace_end DATE;
    
    DECLARE payment_cursor CURSOR FOR
        SELECT sm.id, sm.user_id, sm.subscription_id, sm.expected_payment_date
        FROM subscription_monitoring sm
        JOIN users u ON sm.user_id = u.id
        WHERE sm.payment_received = FALSE
        AND sm.expected_payment_date <= CURDATE()
        AND sm.auto_cancelled = FALSE
        AND u.subscription_status = 'active';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN payment_cursor;
    
    payment_loop: LOOP
        FETCH payment_cursor INTO v_monitoring_id, v_user_id, v_subscription_id, v_expected_date;
        IF done THEN
            LEAVE payment_loop;
        END IF;
        
        -- Calcola il periodo di grazia (7 giorni dopo la data prevista)
        SET v_grace_end = DATE_ADD(v_expected_date, INTERVAL 7 DAY);
        
        IF CURDATE() <= v_grace_end THEN
            -- Siamo nel periodo di grazia, aggiorna il monitoraggio
            UPDATE subscription_monitoring 
            SET 
                grace_period_start = v_expected_date,
                grace_period_end = v_grace_end
            WHERE id = v_monitoring_id;
            
            -- Log del periodo di grazia
            INSERT INTO simple_subscription_logs (
                user_id, 
                action, 
                performed_by,
                notes
            ) VALUES (
                v_user_id, 
                'subscription_expired', 
                'system',
                CONCAT('Pagamento mancante per ', v_expected_date, '. Periodo di grazia fino al ', v_grace_end)
            );
            
        ELSE
            -- Periodo di grazia scaduto, cancella l'abbonamento
            UPDATE users 
            SET 
                subscription_status = 'expired',
                subscription_end = NOW()
            WHERE id = v_user_id;
            
            UPDATE subscription_monitoring 
            SET auto_cancelled = TRUE
            WHERE id = v_monitoring_id;
            
            -- Log della cancellazione automatica
            INSERT INTO simple_subscription_logs (
                user_id, 
                action, 
                performed_by,
                notes
            ) VALUES (
                v_user_id, 
                'subscription_expired', 
                'system',
                CONCAT('Abbonamento cancellato automaticamente. Pagamento mancante dal ', v_expected_date, '. Periodo di grazia scaduto.')
            );
        END IF;
        
    END LOOP;
    
    CLOSE payment_cursor;
END //
DELIMITER ;

-- Stored procedure per registrare un pagamento ricevuto
DELIMITER //
CREATE OR REPLACE PROCEDURE RegisterPaymentReceived(
    IN p_user_id INT,
    IN p_subscription_id VARCHAR(255),
    IN p_payment_amount DECIMAL(10,2),
    IN p_payment_source ENUM('paypal_webhook', 'manual_entry', 'system_detected'),
    IN p_payment_reference VARCHAR(255)
)
BEGIN
    DECLARE v_new_end_date DATETIME;
    DECLARE v_next_payment_date DATE;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Registra il pagamento
    INSERT INTO payment_tracking (
        user_id,
        subscription_id,
        payment_amount,
        payment_date,
        payment_source,
        payment_reference
    ) VALUES (
        p_user_id,
        p_subscription_id,
        p_payment_amount,
        NOW(),
        p_payment_source,
        p_payment_reference
    );
    
    -- Aggiorna il monitoraggio esistente
    UPDATE subscription_monitoring 
    SET 
        payment_received = TRUE,
        payment_received_date = NOW(),
        grace_period_start = NULL,
        grace_period_end = NULL
    WHERE user_id = p_user_id 
    AND subscription_id = p_subscription_id 
    AND payment_received = FALSE
    ORDER BY expected_payment_date ASC
    LIMIT 1;
    
    -- Estendi l'abbonamento di 1 mese
    SELECT subscription_end INTO v_new_end_date FROM users WHERE id = p_user_id;
    
    IF v_new_end_date IS NULL OR v_new_end_date < NOW() THEN
        SET v_new_end_date = DATE_ADD(NOW(), INTERVAL 1 MONTH);
    ELSE
        SET v_new_end_date = DATE_ADD(v_new_end_date, INTERVAL 1 MONTH);
    END IF;
    
    -- Aggiorna l'utente
    UPDATE users 
    SET 
        subscription_status = 'active',
        subscription_end = v_new_end_date,
        last_payment_date = NOW()
    WHERE id = p_user_id;
    
    -- Crea il monitoraggio per il prossimo pagamento
    SET v_next_payment_date = DATE_ADD(DATE(v_new_end_date), INTERVAL 0 DAY);
    
    INSERT INTO subscription_monitoring (
        user_id, 
        subscription_id, 
        expected_payment_date,
        payment_received
    ) VALUES (
        p_user_id, 
        p_subscription_id, 
        v_next_payment_date,
        FALSE
    );
    
    -- Log dell'evento
    INSERT INTO simple_subscription_logs (
        user_id, 
        action, 
        performed_by,
        notes
    ) VALUES (
        p_user_id, 
        'subscription_renewed', 
        'system',
        CONCAT('Pagamento ricevuto: €', p_payment_amount, '. Abbonamento esteso fino al ', DATE_FORMAT(v_new_end_date, '%d/%m/%Y'))
    );
    
    COMMIT;
END //
DELIMITER ;

-- Vista per monitorare i pagamenti in ritardo
CREATE OR REPLACE VIEW overdue_payments AS
SELECT 
    sm.id as monitoring_id,
    u.id as user_id,
    u.name,
    u.email,
    sm.subscription_id,
    sm.expected_payment_date,
    sm.grace_period_start,
    sm.grace_period_end,
    DATEDIFF(CURDATE(), sm.expected_payment_date) as days_overdue,
    CASE 
        WHEN sm.grace_period_end IS NOT NULL AND CURDATE() <= sm.grace_period_end THEN 'grace_period'
        WHEN sm.grace_period_end IS NOT NULL AND CURDATE() > sm.grace_period_end THEN 'expired'
        ELSE 'overdue'
    END as status
FROM subscription_monitoring sm
JOIN users u ON sm.user_id = u.id
WHERE sm.payment_received = FALSE
AND sm.expected_payment_date <= CURDATE()
AND sm.auto_cancelled = FALSE
ORDER BY sm.expected_payment_date ASC;

-- Vista per statistiche dei pagamenti
CREATE OR REPLACE VIEW payment_statistics AS
SELECT 
    COUNT(DISTINCT pt.user_id) as paying_users,
    COUNT(*) as total_payments,
    SUM(pt.payment_amount) as total_revenue,
    AVG(pt.payment_amount) as avg_payment,
    COUNT(CASE WHEN pt.payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as payments_last_30_days,
    SUM(CASE WHEN pt.payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN pt.payment_amount ELSE 0 END) as revenue_last_30_days
FROM payment_tracking pt;