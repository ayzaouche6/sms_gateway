<?php
/**
 * Router simple pour l'application
 */
class Router
{
    private $routes = [];
    
    public function get($path, $handler)
    {
        $this->addRoute('GET', $path, $handler);
    }
    
    public function post($path, $handler)
    {
        $this->addRoute('POST', $path, $handler);
    }
    
    public function put($path, $handler)
    {
        $this->addRoute('PUT', $path, $handler);
    }
    
    public function delete($path, $handler)
    {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    private function addRoute($method, $path, $handler)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }
    
    public function dispatch($method, $uri)
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            // Convertir les paramètres de route (:param) en regex
            $pattern = preg_replace('/:\w+/', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Retirer le match complet
                
                // Appliquer les middlewares si nécessaire
                if (!$this->applyMiddleware($uri)) {
                    return;
                }
                
                return $this->callHandler($route['handler'], $matches);
            }
        }
        
        // Route non trouvée
        http_response_code(404);
        include APP_PATH . '/Views/errors/404.php';
    }
    
    private function applyMiddleware($uri)
    {
        // Routes publiques qui ne nécessitent pas d'authentification
        $publicRoutes = ['/login', '/api/auth'];
        
        if (in_array($uri, $publicRoutes)) {
            return true;
        }
        
        // Vérifier l'authentification pour les routes API
        if (strpos($uri, '/api/') === 0) {
            return Middleware::authenticateApi();
        }
        
        // Vérifier l'authentification pour les autres routes
        if (!Auth::check()) {
            if (strpos($uri, '/api/') === 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Non autorisé']);
                return false;
            } else {
                header('Location: /login');
                return false;
            }
        }
        
        return true;
    }
    
    private function callHandler($handler, $params = [])
    {
        if (is_string($handler)) {
            list($controllerName, $method) = explode('@', $handler);
            
            if (class_exists($controllerName)) {
                $controller = new $controllerName();
                
                if (method_exists($controller, $method)) {
                    return call_user_func_array([$controller, $method], $params);
                }
            }
            
            Logger::error("Controller method not found: {$handler}");
            http_response_code(500);
            echo "Internal Server Error";
            return;
        }
        
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }
        
        Logger::error("Invalid handler: " . print_r($handler, true));
        http_response_code(500);
        echo "Internal Server Error";
    }
}