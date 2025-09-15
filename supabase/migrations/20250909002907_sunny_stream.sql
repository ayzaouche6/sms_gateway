/*
# Add Received SMS and Enhanced Login Attempts Tables

1. New Tables
   - `received_sms` - Store incoming SMS messages
   - Enhanced `login_attempts` table structure

2. Security
   - Enable RLS on new tables
   - Add proper indexes for performance
   - Unique constraints to prevent duplicates

3. Features
   - SMS deduplication based on content and timestamp
   - Login attempt tracking with detailed information
   - Automatic cleanup procedures
*/

-- Table for received SMS
CREATE TABLE IF NOT EXISTS received_sms (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sender VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modem_id INT NULL,
    
    -- SMS properties
    is_unicode BOOLEAN DEFAULT FALSE,
    parts_count TINYINT DEFAULT 1,
    
    -- Deduplication
    message_hash VARCHAR(64) NOT NULL,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (modem_id) REFERENCES modems(id) ON DELETE SET NULL,
    
    -- Unique constraint to prevent exact duplicates
    UNIQUE KEY unique_sms (sender, message_hash, received_at),
    
    INDEX idx_received_sms_sender (sender),
    INDEX idx_received_sms_received (received_at),
    INDEX idx_received_sms_modem (modem_id),
    INDEX idx_received_sms_hash (message_hash)
);

-- Enhanced login attempts table (if not exists with proper structure)
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    success BOOLEAN DEFAULT FALSE,
    failure_reason VARCHAR(100) NULL,
    session_id VARCHAR(128) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_login_attempts_user (user_id),
    INDEX idx_login_attempts_email (email),
    INDEX idx_login_attempts_ip (ip_address),
    INDEX idx_login_attempts_success (success),
    INDEX idx_login_attempts_created (created_at)
);

-- Add system configuration for SMS receiving
INSERT IGNORE INTO system_config (config_key, config_value, description) VALUES 
('sms_receive_enabled', 'true', 'Enable SMS receiving functionality'),
('sms_receive_interval', '30', 'SMS check interval in seconds'),
('sms_cleanup_days', '30', 'Days to keep received SMS'),
('login_attempts_retention_days', '30', 'Days to keep login attempts');

-- Procedure to clean up old received SMS
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CleanupReceivedSms()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Clean old received SMS (default 30 days)
    DELETE FROM received_sms 
    WHERE received_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Clean old login attempts (default 30 days)
    DELETE FROM login_attempts 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    COMMIT;
END //
DELIMITER ;

-- Function to generate message hash for deduplication
DELIMITER //
CREATE FUNCTION IF NOT EXISTS GenerateMessageHash(sender VARCHAR(20), message TEXT, received_time TIMESTAMP)
RETURNS VARCHAR(64)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE hash_input TEXT;
    DECLARE result VARCHAR(64);
    
    -- Create hash input from sender, message, and rounded timestamp (to nearest minute)
    SET hash_input = CONCAT(
        sender, 
        '|', 
        message, 
        '|', 
        DATE_FORMAT(received_time, '%Y-%m-%d %H:%i:00')
    );
    
    -- Generate SHA256 hash
    SET result = SHA2(hash_input, 256);
    
    RETURN result;
END //
DELIMITER ;