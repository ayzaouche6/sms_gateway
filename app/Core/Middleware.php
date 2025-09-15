<?php
/**
 * Middleware pour les requêtes
 */
class Middleware
{
    public static function authenticateApi()
    {
        // Récupérer le token depuis l'en-tête
        $token = $_SERVER['HTTP_X_API_TOKEN'] ?? 
                $_SERVER['HTTP_AUTHORIZATION'] ?? 
                $_GET['api_token'] ?? 
                $_POST['api_token'] ?? '';
        
        // Nettoyer le token Bearer
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        
        if (empty($token)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token API requis']);
            return false;
        }
        
        $payload = Auth::validateApiToken($token);
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token API invalide']);
            return false;
        }
        
        // Stocker les informations utilisateur pour cette requête
        $_SESSION['api_user_id'] = $payload['user_id'];
        
        return true;
    }
    
    public static function rateLimiter($identifier = null)
    {
        $identifier = $identifier ?: $_SERVER['REMOTE_ADDR'];
        $key = "rate_limit:" . $identifier;
        $limit = API_RATE_LIMIT;
        $window = 60; // 1 minute
        
        // Utilisation simple basée sur les fichiers (peut être améliorée avec Redis)
        $file = LOGS_PATH . '/rate_limit_' . md5($key) . '.txt';
        $requests = [];
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $requests = $content ? json_decode($content, true) : [];
        }
        
        // Nettoyer les anciennes requêtes
        $now = time();
        $requests = array_filter($requests, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        if (count($requests) >= $limit) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Trop de requêtes']);
            return false;
        }
        
        // Ajouter la requête actuelle
        $requests[] = $now;
        file_put_contents($file, json_encode($requests));
        
        return true;
    }
    
    public static function validateInput($rules)
    {
        $errors = [];
        $data = array_merge($_GET, $_POST);
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            if (strpos($rule, 'required') !== false && empty($value)) {
                $errors[$field] = "Le champ {$field} est requis";
                continue;
            }
            
            if (strpos($rule, 'email') !== false && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "Le champ {$field} doit être un email valide";
            }
            
            if (preg_match('/min:(\d+)/', $rule, $matches)) {
                $min = (int)$matches[1];
                if (strlen($value) < $min) {
                    $errors[$field] = "Le champ {$field} doit contenir au moins {$min} caractères";
                }
            }
            
            if (preg_match('/max:(\d+)/', $rule, $matches)) {
                $max = (int)$matches[1];
                if (strlen($value) > $max) {
                    $errors[$field] = "Le champ {$field} ne peut pas dépasser {$max} caractères";
                }
            }
            
            if (strpos($rule, 'phone') !== false && !preg_match('/^[\+]?[0-9\s\-\(\)]+$/', $value)) {
                $errors[$field] = "Le champ {$field} doit être un numéro de téléphone valide";
            }
        }
        
        return empty($errors) ? true : $errors;
    }
}