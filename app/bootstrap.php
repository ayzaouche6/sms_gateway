<?php
/**
 * Bootstrap de l'application
 * Initialise l'autoloader et les composants de base
 */

// Définir les chemins si pas déjà fait
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
    define('APP_PATH', ROOT_PATH . '/app');
    define('CONFIG_PATH', ROOT_PATH . '/config');
    define('LOGS_PATH', ROOT_PATH . '/logs');
}

// Charger la configuration
$config = require_once CONFIG_PATH . '/config.php';

// Créer les répertoires nécessaires s'ils n'existent pas
$dirs = [LOGS_PATH, LOGS_PATH . '/error', LOGS_PATH . '/info', LOGS_PATH . '/debug'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Charger l'autoloader
require_once APP_PATH . '/Core/Autoload.php';
Autoload::register();

// Initialiser le logger
Logger::init();

// Initialiser la base de données
try {
    Database::getInstance();
} catch (Exception $e) {
    Logger::error('Database connection failed: ' . $e->getMessage());
    if (DEBUG) {
        throw $e;
    }
    die('Database connection failed');
}

// Initialiser les middleware de sécurité
SecurityService::init();

// Initialiser le système de langues
Language::getInstance();

// Configuration des erreurs globales
set_error_handler([Logger::class, 'handleError']);
set_exception_handler([Logger::class, 'handleException']);
register_shutdown_function([Logger::class, 'handleShutdown']);