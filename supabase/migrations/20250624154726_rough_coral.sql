-- Database schema per DeepLink Generator

-- Tabella utenti
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    subscription_status ENUM('free', 'active', 'cancelled', 'expired') DEFAULT 'free',
    subscription_id VARCHAR(255) NULL,
    subscription_start DATETIME NULL,
    subscription_end DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabella deeplinks (aggiornata)
CREATE TABLE IF NOT EXISTS deeplinks (
    id VARCHAR(10) PRIMARY KEY,
    original_url TEXT NOT NULL,
    deeplink TEXT NOT NULL,
    user_id INT NULL,
    clicks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Indici per performance
CREATE INDEX idx_deeplinks_user_id ON deeplinks(user_id);
CREATE INDEX idx_deeplinks_created_at ON deeplinks(created_at);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_subscription ON users(subscription_status, subscription_end);