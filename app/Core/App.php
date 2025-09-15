<?php
/**
 * Classe principale de l'application
 */
class App
{
    private $router;
    
    public function __construct()
    {
        $this->router = new Router();
        $this->setupRoutes();
    }
    
    public function run()
    {
        // Démarrer la session si pas encore fait
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Générer et vérifier le token CSRF
        SecurityService::generateCSRFToken();
        
        // Obtenir l'URL courante
        $uri = $_SERVER['REQUEST_URI'];
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Nettoyer l'URI
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/');
        if (empty($uri)) {
            $uri = '/';
        }
        
        // Router la requête
        $this->router->dispatch($method, $uri);
    }
    
    private function setupRoutes()
    {
        // Routes publiques
        $this->router->get('/', 'DashboardController@index');
        $this->router->get('/login', 'AuthController@showLogin');
        $this->router->post('/login', 'AuthController@login');
        $this->router->post('/logout', 'AuthController@logout');
        $this->router->get('/logout', 'AuthController@logout');
        
        // Routes protégées - Dashboard
        $this->router->get('/dashboard', 'DashboardController@index');
        
        // Routes protégées - SMS
        $this->router->get('/sms', 'SmsController@index');
        $this->router->get('/sms/send', 'SmsController@sendForm');
        $this->router->post('/sms/send', 'SmsController@send');
        $this->router->get('/sms/bulk', 'SmsController@bulkForm');
        $this->router->post('/sms/bulk', 'SmsController@bulk');
        $this->router->get('/sms/queue', 'SmsController@queue');
        
        // Routes protégées - Reports
        $this->router->get('/reports', 'ReportsController@index');
        $this->router->get('/reports/export', 'ReportsController@export');
        
        // Routes protégées - Users
        $this->router->get('/profile', 'UserController@profile');
        $this->router->post('/profile', 'UserController@updateProfile');
        $this->router->get('/users', 'UserController@index');
        $this->router->get('/users/create', 'UserController@create');
        $this->router->post('/users/create', 'UserController@store');
        $this->router->get('/users/:id/edit', 'UserController@edit');
        $this->router->post('/users/:id/update', 'UserController@update');
        $this->router->post('/users/:id/toggle-status', 'UserController@toggleStatus');
        $this->router->delete('/users/:id/delete', 'UserController@delete');
        
        // Routes protégées - Network (Admin only)
        $this->router->get('/network', 'NetworkController@index');
        $this->router->post('/network/update', 'NetworkController@update');
        $this->router->get('/network/test', 'NetworkController@test');
        $this->router->post('/network/backup', 'NetworkController@backup');
        $this->router->post('/network/restore', 'NetworkController@restore');
        
        // Routes SSL (Admin only)
        $this->router->get('/network/ssl/info', 'NetworkController@sslInfo');
        $this->router->post('/network/ssl/generate', 'NetworkController@generateSSL');
        $this->router->post('/network/ssl/upload', 'NetworkController@uploadSSL');
        $this->router->post('/network/ssl/restore', 'NetworkController@restoreSSL');
        
        // Routes de gestion des langues
        $this->router->get('/language/switch', 'LanguageController@switch');
        $this->router->post('/language/switch', 'LanguageController@switch');
        $this->router->get('/api/languages', 'LanguageController@getAvailable');
        
        // API Routes
        $this->router->post('/api/auth', 'SmsApiController@authenticate');
        $this->router->post('/api/sms/send', 'SmsApiController@send');
        $this->router->get('/api/sms/status/:id', 'SmsApiController@status');
        $this->router->get('/api/sms/list', 'SmsApiController@list');
        $this->router->get('/api/sms/received', 'SmsApiController@received');
        $this->router->post('/api/sms/retry', 'SmsApiController@retry');
        $this->router->delete('/api/sms/delete', 'SmsApiController@delete');
        $this->router->post('/api/sms/bulk', 'SmsApiController@bulk');
        $this->router->get('/api/queue/status', 'StatusApiController@queueStatus');
        $this->router->post('/api/queue/clear', 'StatusApiController@clearQueue');
        $this->router->get('/api/stats', 'StatusApiController@stats');
    }
}