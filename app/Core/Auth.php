<?php
/**
 * Gestionnaire d'authentification
 */
class Auth
{
    public static function attempt($email, $password)
    {
        $user = User::findByEmail($email);
        
        if (!$user) {
            Logger::info("Login attempt with non-existent email: {$email}");
            return false;
        }
        
        // Vérifier le verrouillage du compte
        if (self::isAccountLocked($user['id'])) {
            Logger::warning("Login attempt on locked account: {$email}");
            return false;
        }
        
        // Vérifier le mot de passe
        if (!password_verify($password, $user['password'])) {
            self::recordFailedAttempt($user['id'], $email);
            return false;
        }
        
        // Connexion réussie
        self::clearFailedAttempts($user['id']);
        
        // Enregistrer la tentative réussie
        $db = Database::getInstance();
        $db->insert('login_attempts', [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'success' => 1,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        self::login($user);
        
        Logger::info("Successful login: {$email}");
        return true;
    }
    
    public static function login($user)
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Régénérer l'ID de session pour sécurité
        session_regenerate_id(true);
        
        // Mettre à jour la dernière connexion
        User::updateLastLogin($user['id']);
    }
    
    public static function logout()
    {
        $email = $_SESSION['user_email'] ?? 'unknown';
        
        // Détruire les données de session
        $_SESSION = [];
        
        // Détruire le cookie de session si il existe
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Détruire la session
        session_destroy();
        
        Logger::info("User logged out: {$email}");
    }
    
    public static function check()
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Vérifier la validité de la session
        if (isset($_SESSION['login_time'])) {
            if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
                self::logout();
                return false;
            }
        }
        
        return true;
    }
    
    public static function user()
    {
        if (!self::check()) {
            return null;
        }
        
        return User::find($_SESSION['user_id']);
    }
    
    public static function id()
    {
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function hasRole($role)
    {
        if (!self::check()) {
            return false;
        }
        
        $userRole = $_SESSION['user_role'];
        
        // Admin a tous les droits
        if ($userRole === ROLE_ADMIN) {
            return true;
        }
        
        // Superviseur peut faire ce que fait l'opérateur
        if ($role === ROLE_OPERATOR && $userRole === ROLE_SUPERVISOR) {
            return true;
        }
        
        return $userRole === $role;
    }
    
    public static function generateApiToken($userId)
    {
        $payload = [
            'user_id' => $userId,
            'issued_at' => time(),
            'expires_at' => time() + JWT_EXPIRY
        ];
        
        return self::encodeJWT($payload);
    }
    
    public static function validateApiToken($token)
    {
        try {
            $payload = self::decodeJWT($token);
            
            if ($payload['expires_at'] < time()) {
                return false;
            }
            
            return $payload;
        } catch (Exception $e) {
            Logger::warning("Invalid API token: " . $e->getMessage());
            return false;
        }
    }
    
    private static function encodeJWT($payload)
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $headerEncoded = base64url_encode($header);
        $payloadEncoded = base64url_encode($payload);
        
        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, JWT_SECRET, true);
        $signatureEncoded = base64url_encode($signature);
        
        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }
    
    private static function decodeJWT($token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        $signature = base64url_decode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, JWT_SECRET, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Invalid token signature');
        }
        
        $payload = json_decode(base64url_decode($payloadEncoded), true);
        if (!$payload) {
            throw new Exception('Invalid token payload');
        }
        
        return $payload;
    }
    
    private static function isAccountLocked($userId)
    {
        $db = Database::getInstance();
        $attempts = $db->selectOne(
            "SELECT COUNT(*) as count, MAX(created_at) as last_attempt 
             FROM login_attempts 
             WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$userId, LOGIN_LOCKOUT_TIME]
        );
        
        return $attempts['count'] >= LOGIN_ATTEMPTS_MAX;
    }
    
    private static function recordFailedAttempt($userId, $email)
    {
        $db = Database::getInstance();
        $db->insert('login_attempts', [
            'user_id' => $userId,
            'email' => $email,
            'success' => 0,
            'failure_reason' => 'invalid_credentials',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        Logger::warning("Failed login attempt: {$email} from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    private static function clearFailedAttempts($userId)
    {
        $db = Database::getInstance();
        $db->delete('login_attempts', 'user_id = ?', [$userId]);
    }
}

// Fonctions utilitaires pour JWT
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}