<?php
/**
 * Contrôleur de base
 */
abstract class Controller
{
    protected $data = [];
    
    protected function view($view, $data = [])
    {
        $this->data = array_merge($this->data, $data);
        
        // Extraire les variables pour la vue
        extract($this->data);
        
        // Inclure le layout principal
        include APP_PATH . '/Views/layout.php';
    }
    
    protected function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    
    protected function redirect($url, $code = 302)
    {
        http_response_code($code);
        header("Location: $url");
        exit();
    }
    
    protected function validateCSRF()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!SecurityService::validateCSRFToken($token)) {
                if ($this->isApiRequest()) {
                    $this->json(['success' => false, 'message' => 'Token CSRF invalide'], 403);
                } else {
                    $this->redirect('/dashboard');
                }
                return false;
            }
        }
        return true;
    }
    
    protected function isApiRequest()
    {
        return strpos($_SERVER['REQUEST_URI'], '/api/') === 0;
    }
    
    protected function requireRole($role)
    {
        $user = Auth::user();
        if (!$user || !Auth::hasRole($role)) {
            if ($this->isApiRequest()) {
                $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
            } else {
                $this->redirect('/dashboard');
            }
            return false;
        }
        return true;
    }
}