<?php
/**
 * Service de sécurité
 */
class SecurityService
{
    public static function init()
    {
        // Configuration des en-têtes de sécurité
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            if (isset($_SERVER['HTTPS'])) {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
        }
    }
    
    public static function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        // Régénérer le token s'il est trop ancien
        if (time() - ($_SESSION['csrf_token_time'] ?? 0) > CSRF_TOKEN_LIFETIME) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token)
    {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Vérifier l'expiration
        if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function sanitizeInput($input, $type = 'string')
    {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            
            case 'phone':
                return preg_replace('/[^\d+\-\(\)\s]/', '', $input);
            
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            case 'sql':
                return trim($input); // PDO se charge de l'échappement
            
            default:
                return trim(strip_tags($input));
        }
    }
    
    public static function validatePassword($password)
    {
        $errors = [];
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = "Le mot de passe doit contenir au moins " . PASSWORD_MIN_LENGTH . " caractères";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une minuscule";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une majuscule";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un chiffre";
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un caractère spécial";
        }
        
        return empty($errors) ? true : $errors;
    }
    
    public static function checkRateLimit($identifier, $maxAttempts = 10, $timeWindow = 300)
    {
        $key = 'rate_limit_' . $identifier;
        $file = LOGS_PATH . '/rate_limits/' . md5($key) . '.json';
        
        // Créer le répertoire si nécessaire
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $now = time();
        $attempts = [];
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $attempts = $content ? json_decode($content, true) : [];
        }
        
        // Nettoyer les anciennes tentatives
        $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        if (count($attempts) >= $maxAttempts) {
            return false;
        }
        
        // Enregistrer cette tentative
        $attempts[] = $now;
        file_put_contents($file, json_encode($attempts));
        
        return true;
    }
    
    public static function logSecurityEvent($event, $details = [])
    {
        $logData = [
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => Auth::id(),
            'timestamp' => date('Y-m-d H:i:s'),
            'details' => $details
        ];
        
        $logFile = LOGS_PATH . '/security.log';
        $logEntry = json_encode($logData) . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        Logger::warning("Security event: {$event}", $logData);
    }
    
    public static function detectSqlInjection($input)
    {
        $patterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bselect\b.*\bfrom\b)/i',
            '/(\binsert\b.*\binto\b)/i',
            '/(\bdelete\b.*\bfrom\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/(\bupdate\b.*\bset\b)/i',
            '/(\'.*\bor\b.*\')/i',
            '/(\".*\bor\b.*\")/i',
            '/(\-\-)/i',
            '/(\#)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function detectXSS($input)
    {
        $patterns = [
            '/<script[^>]*>/i',
            '/<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/<form[^>]*>/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function validateRequest()
    {
        // Vérifier les tentatives d'injection SQL
        $allInput = array_merge($_GET, $_POST, $_COOKIE);
        foreach ($allInput as $key => $value) {
            if (is_string($value)) {
                if (self::detectSqlInjection($value)) {
                    self::logSecurityEvent('sql_injection_attempt', ['field' => $key, 'value' => $value]);
                    http_response_code(400);
                    die('Requête invalide');
                }
                
                if (self::detectXSS($value)) {
                    self::logSecurityEvent('xss_attempt', ['field' => $key, 'value' => $value]);
                    // On peut nettoyer automatiquement ou bloquer
                    $_REQUEST[$key] = $_POST[$key] = $_GET[$key] = htmlspecialchars($value, ENT_QUOTES);
                }
            }
        }
        
        // Vérifier la limite de taux
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!self::checkRateLimit($clientIp)) {
            self::logSecurityEvent('rate_limit_exceeded', ['ip' => $clientIp]);
            http_response_code(429);
            die('Trop de requêtes');
        }
    }
    
    public static function generateSecureToken($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }
    
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }
}