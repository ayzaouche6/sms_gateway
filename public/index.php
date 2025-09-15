<?php
/**
 * Front Controller - Point d'entrée principal
 */

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Définir les constantes de base
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Charger le bootstrap de l'application
require_once APP_PATH . '/bootstrap.php';

try {
    // Initialiser l'application
    $app = new App();
    $app->run();
} catch (Exception $e) {
    // Log l'erreur
    error_log("Application Error: " . $e->getMessage());
    
    // Afficher une page d'erreur générique en production
    if (!defined('DEBUG') || !DEBUG) {
        http_response_code(500);
        include APP_PATH . '/Views/errors/500.php';
    } else {
        throw $e;
    }
}