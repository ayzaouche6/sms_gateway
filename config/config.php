<?php
/**
 * Configuration principale de l'application
 */

// Chargement des variables d'environnement
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, '"\'');
        putenv(trim($name) . '=' . trim($value, '"\''));
    }
}

// Configuration de base
define('DEBUG', $_ENV['DEBUG'] ?? false);
define('APP_NAME', $_ENV['APP_NAME'] ?? 'SMS Gateway');
define('APP_VERSION', '1.0.0');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');

// Configuration de la base de données
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'sms_gateway');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// Configuration des SMS
define('SMS_PYTHON_SCRIPT', ROOT_PATH . '/tools/send_sms_mmcli.py');
define('SMS_MAX_LENGTH', 160);
define('SMS_UNICODE_MAX_LENGTH', 70);
define('SMS_MAX_PER_MINUTE', $_ENV['SMS_MAX_PER_MINUTE'] ?? 60);
define('SMS_RETRY_ATTEMPTS', 3);
define('SMS_RETRY_DELAY', 300); // 5 minutes

// Configuration des modems
define('MODEM_CHECK_INTERVAL', 30); // secondes
define('MODEM_TIMEOUT', 30); // secondes pour l'envoi
define('DEFAULT_MODEM_PATH', '/dev/ttyUSB0');

// Configuration de sécurité
define('SESSION_LIFETIME', 3600); // 1 heure
define('LOGIN_ATTEMPTS_MAX', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'your-secret-key-here');
define('JWT_EXPIRY', 86400); // 24 heures
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_LIFETIME', 3600);

// Configuration des logs
define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'INFO');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('LOG_MAX_FILES', 5);

// Configuration des notifications
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? '');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_FROM', $_ENV['SMTP_FROM'] ?? '');
define('WEBHOOK_URL', $_ENV['WEBHOOK_URL'] ?? '');

// Configuration des uploads
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['csv', 'xlsx', 'xls']);
define('UPLOAD_DIR', ROOT_PATH . '/uploads/');

// Configuration du cache
define('CACHE_ENABLED', $_ENV['CACHE_ENABLED'] ?? true);
define('CACHE_LIFETIME', 300); // 5 minutes

// Configuration API
define('API_RATE_LIMIT', 100); // requêtes par minute
define('API_TOKEN_HEADER', 'X-API-Token');

// Rôles utilisateur
define('ROLE_ADMIN', 'admin');
define('ROLE_SUPERVISOR', 'supervisor');
define('ROLE_OPERATOR', 'operator');

// Statuts SMS
define('SMS_STATUS_PENDING', 'pending');
define('SMS_STATUS_PROCESSING', 'processing');
define('SMS_STATUS_SENT', 'sent');
define('SMS_STATUS_FAILED', 'failed');
define('SMS_STATUS_SCHEDULED', 'scheduled');

// Configuration timezone
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'Europe/Paris');

// Configuration PHP
ini_set('display_errors', DEBUG ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', LOGS_PATH . '/php_errors.log');

// Configuration de session sécurisée
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

return [
    'app' => [
        'name' => APP_NAME,
        'version' => APP_VERSION,
        'url' => APP_URL,
        'debug' => DEBUG
    ],
    'database' => [
        'host' => DB_HOST,
        'port' => DB_PORT,
        'name' => DB_NAME,
        'user' => DB_USER,
        'password' => DB_PASS,
        'charset' => DB_CHARSET
    ],
    'sms' => [
        'python_script' => SMS_PYTHON_SCRIPT,
        'max_length' => SMS_MAX_LENGTH,
        'unicode_max_length' => SMS_UNICODE_MAX_LENGTH,
        'max_per_minute' => SMS_MAX_PER_MINUTE,
        'retry_attempts' => SMS_RETRY_ATTEMPTS,
        'retry_delay' => SMS_RETRY_DELAY
    ],
    'security' => [
        'session_lifetime' => SESSION_LIFETIME,
        'login_attempts_max' => LOGIN_ATTEMPTS_MAX,
        'login_lockout_time' => LOGIN_LOCKOUT_TIME,
        'jwt_secret' => JWT_SECRET,
        'jwt_expiry' => JWT_EXPIRY,
        'password_min_length' => PASSWORD_MIN_LENGTH
    ],
    'logging' => [
        'level' => LOG_LEVEL,
        'max_size' => LOG_MAX_SIZE,
        'max_files' => LOG_MAX_FILES
    ]
];