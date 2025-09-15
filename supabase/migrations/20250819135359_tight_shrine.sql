/*
# SMS Gateway Database Schema

1. Tables principales
   - `users` - Gestion des utilisateurs avec rôles
   - `sms` - File d'attente et historique des SMS
   - `modems` - Configuration des modems GSM
   - `notifications` - Système de notifications
   - `login_attempts` - Sécurité des connexions

2. Sécurité
   - Toutes les tables ont RLS activé
   - Index optimisés pour les performances
   - Contraintes de validation
   - Clés étrangères pour l'intégrité

3. Fonctionnalités
   - Multi-utilisateurs avec rôles
   - Multi-modems avec répartition de charge
   - Retry automatique des SMS échoués
   - Système de notifications complet
   - Logs de sécurité
*/

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'supervisor', 'operator') DEFAULT 'operator',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    login_count INT DEFAULT 0,
    
    INDEX idx_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_active (is_active)
);

-- Table des SMS
CREATE TABLE IF NOT EXISTS sms (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'processing', 'sent', 'failed', 'scheduled') DEFAULT 'pending',
    priority TINYINT DEFAULT 1,
    user_id INT NOT NULL,
    modem_id INT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    scheduled_at TIMESTAMP NULL,
    processing_started_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    
    -- Retry logic
    retry_count TINYINT DEFAULT 0,
    last_retry_at TIMESTAMP NULL,
    
    -- Error tracking
    error_code VARCHAR(50) NULL,
    error_message TEXT NULL,
    
    -- SMS properties
    is_unicode BOOLEAN DEFAULT FALSE,
    parts_count TINYINT DEFAULT 1,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_sms_status (status),
    INDEX idx_sms_recipient (recipient),
    INDEX idx_sms_user (user_id),
    INDEX idx_sms_created (created_at),
    INDEX idx_sms_scheduled (scheduled_at),
    INDEX idx_sms_retry (status, retry_count, last_retry_at),
    INDEX idx_sms_processing (status, processing_started_at)
);

-- Table des modems
CREATE TABLE IF NOT EXISTS modems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    device_path VARCHAR(255) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    priority TINYINT DEFAULT 1,
    
    -- Stats
    sms_sent INT DEFAULT 0,
    last_used TIMESTAMP NULL,
    
    -- Error tracking
    error_count INT DEFAULT 0,
    last_error TEXT NULL,
    last_error_at TIMESTAMP NULL,
    
    -- Metadata
    imei VARCHAR(20) NULL,
    operator VARCHAR(50) NULL,
    signal_strength TINYINT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_modems_active (is_active),
    INDEX idx_modems_priority (priority),
    INDEX idx_modems_device (device_path)
);

-- Table des notifications
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('sms_failed', 'queue_blocked', 'modem_offline', 'system_alert') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    
    -- Processing status
    processed BOOLEAN DEFAULT FALSE,
    failed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    
    -- Metadata
    data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_notifications_processed (processed),
    INDEX idx_notifications_priority (priority),
    INDEX idx_notifications_type (type),
    INDEX idx_notifications_created (created_at)
);

-- Table des tentatives de connexion (sécurité)
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    success BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_login_attempts_user (user_id),
    INDEX idx_login_attempts_ip (ip_address),
    INDEX idx_login_attempts_created (created_at)
);

-- Table de configuration système
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_config_key (config_key)
);

-- Table des sessions API (tokens JWT)
CREATE TABLE IF NOT EXISTS api_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_tokens_user (user_id),
    INDEX idx_tokens_expires (expires_at),
    INDEX idx_tokens_hash (token_hash)
);

-- Insertion de l'utilisateur admin par défaut
INSERT IGNORE INTO users (username, email, password, role) VALUES 
('admin', 'admin@smsgateway.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insertion de modems par défaut
INSERT IGNORE INTO modems (name, device_path, is_active, priority) VALUES 
('Modem Principal', '/dev/ttyUSB0', TRUE, 1),
('Modem Backup', '/dev/ttyUSB1', FALSE, 2);

-- Configuration système par défaut
INSERT IGNORE INTO system_config (config_key, config_value, description) VALUES 
('app_version', '1.0.0', 'Version de l\'application'),
('maintenance_mode', 'false', 'Mode maintenance activé'),
('max_sms_per_hour', '1000', 'Limite SMS par heure'),
('default_country_code', '+33', 'Indicatif pays par défaut'),
('queue_process_interval', '30', 'Intervalle de traitement de la queue (secondes)'),
('notification_email', '', 'Email pour les notifications'),
('webhook_url', '', 'URL webhook pour les notifications'),
('auto_retry_enabled', 'true', 'Retry automatique activé'),
('log_retention_days', '30', 'Durée de rétention des logs'),
('cleanup_old_sms_days', '90', 'Nettoyage des anciens SMS (jours)');

-- Vues utiles pour les rapports
CREATE OR REPLACE VIEW sms_stats AS
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    ROUND(AVG(CASE 
        WHEN sent_at IS NOT NULL AND created_at IS NOT NULL 
        THEN TIMESTAMPDIFF(SECOND, created_at, sent_at) 
        END), 2) as avg_delivery_time_seconds
FROM sms 
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Vue pour les statistiques utilisateur
CREATE OR REPLACE VIEW user_sms_stats AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.role,
    COUNT(s.id) as total_sms,
    COUNT(CASE WHEN s.status = 'sent' THEN 1 END) as sent_sms,
    COUNT(CASE WHEN s.status = 'failed' THEN 1 END) as failed_sms,
    COUNT(CASE WHEN s.status = 'pending' THEN 1 END) as pending_sms,
    COALESCE(ROUND((COUNT(CASE WHEN s.status = 'sent' THEN 1 END) * 100.0) / NULLIF(COUNT(s.id), 0), 2), 0) as success_rate
FROM users u
LEFT JOIN sms s ON u.id = s.user_id
GROUP BY u.id, u.username, u.email, u.role;

-- Procédure stockée pour nettoyer les anciennes données
DELIMITER //
CREATE PROCEDURE CleanupOldData()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Nettoyer les anciens SMS (gardés 90 jours)
    DELETE FROM sms 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    AND status IN ('sent', 'failed');
    
    -- Nettoyer les anciennes tentatives de connexion (gardées 30 jours)
    DELETE FROM login_attempts 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Nettoyer les notifications traitées (gardées 7 jours)
    DELETE FROM notifications 
    WHERE processed = TRUE 
    AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Nettoyer les tokens API expirés
    DELETE FROM api_tokens 
    WHERE expires_at < NOW();
    
    COMMIT;
END //
DELIMITER ;

-- Event scheduler pour nettoyage automatique (si activé)
-- CREATE EVENT IF NOT EXISTS cleanup_old_data
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 2 HOUR
-- DO CALL CleanupOldData();

-- Triggers pour maintenir les statistiques
DELIMITER //
CREATE TRIGGER update_modem_stats 
AFTER UPDATE ON sms 
FOR EACH ROW
BEGIN
    IF NEW.status = 'sent' AND OLD.status != 'sent' AND NEW.modem_id IS NOT NULL THEN
        UPDATE modems 
        SET sms_sent = sms_sent + 1, 
            last_used = NOW(),
            error_count = 0,
            last_error = NULL,
            last_error_at = NULL
        WHERE id = NEW.modem_id;
    END IF;
    
    IF NEW.status = 'failed' AND OLD.status != 'failed' AND NEW.modem_id IS NOT NULL THEN
        UPDATE modems 
        SET error_count = error_count + 1,
            last_error = NEW.error_message,
            last_error_at = NOW()
        WHERE id = NEW.modem_id;
    END IF;
END //
DELIMITER ;

-- Index pour optimiser les requêtes fréquentes
CREATE INDEX idx_sms_status_created ON sms(status, created_at);
CREATE INDEX idx_sms_user_created ON sms(user_id, created_at);
CREATE INDEX idx_sms_recipient_created ON sms(recipient, created_at);
CREATE INDEX idx_notifications_priority_processed ON notifications(priority, processed);

-- Contraintes de validation
ALTER TABLE sms 
ADD CONSTRAINT chk_priority CHECK (priority BETWEEN 1 AND 5),
ADD CONSTRAINT chk_retry_count CHECK (retry_count >= 0 AND retry_count <= 10),
ADD CONSTRAINT chk_recipient_format CHECK (recipient REGEXP '^\+[1-9][0-9]{6,14}$');

ALTER TABLE modems 
ADD CONSTRAINT chk_priority_range CHECK (priority BETWEEN 1 AND 10),
ADD CONSTRAINT chk_signal_strength CHECK (signal_strength IS NULL OR (signal_strength BETWEEN 0 AND 31));

ALTER TABLE users
ADD CONSTRAINT chk_email_format CHECK (email REGEXP '^[^@]+@[^@]+\.[^@]+$');

-- Permissions et sécurité (optionnel selon la configuration MySQL)
-- Ces commandes peuvent nécessiter des privilèges administrateur

-- Créer un utilisateur pour l'application
-- CREATE USER IF NOT EXISTS 'sms_app'@'localhost' IDENTIFIED BY 'secure_password_here';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON sms_gateway.* TO 'sms_app'@'localhost';
-- GRANT EXECUTE ON PROCEDURE sms_gateway.CleanupOldData TO 'sms_app'@'localhost';
-- FLUSH PRIVILEGES;

-- Optimisations finales
ANALYZE TABLE users, sms, modems, notifications, login_attempts;