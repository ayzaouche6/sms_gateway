#!/usr/bin/env php
<?php
/**
 * Script cron pour le traitement périodique de la file d'attente
 * À utiliser si systemd n'est pas disponible
 */

// Vérifier que le script est exécuté en CLI
if (php_sapi_name() !== 'cli') {
    die('Ce script doit être exécuté en ligne de commande');
}

// Définir les chemins
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Empêcher l'exécution simultanée
$lockFile = LOGS_PATH . '/cron_worker.lock';
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    // Si le fichier de verrouillage a plus de 10 minutes, le supprimer
    if (time() - $lockTime > 600) {
        unlink($lockFile);
    } else {
        // Un autre processus est en cours
        exit(0);
    }
}

// Créer le fichier de verrouillage
touch($lockFile);

// Fonction de nettoyage
function cleanup() {
    global $lockFile;
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

register_shutdown_function('cleanup');

try {
    // Charger le bootstrap de l'application
    require_once APP_PATH . '/bootstrap.php';
    
    // Vérifier si le mode maintenance est activé
    $maintenanceMode = false;
    try {
        $db = Database::getInstance();
        $config = $db->selectOne("SELECT config_value FROM system_config WHERE config_key = 'maintenance_mode'");
        $maintenanceMode = ($config && $config['config_value'] === 'true');
    } catch (Exception $e) {
        // En cas d'erreur de DB, continuer quand même
        Logger::warning('Cannot check maintenance mode: ' . $e->getMessage());
    }
    
    if ($maintenanceMode) {
        Logger::info('Queue processing skipped: maintenance mode enabled');
        exit(0);
    }
    
    // Traitement de la queue
    $queueService = new QueueService();
    
    // Nettoyer les SMS bloqués
    $stuckCount = $queueService->clearStuckSms();
    if ($stuckCount > 0) {
        Logger::info("Cleared {$stuckCount} stuck SMS");
    }
    
    // Traiter la queue
    $queueService->processQueue();
    
    // Traiter les notifications en attente
    NotificationService::processNotifications();
    
    // Nettoyage périodique (une fois par jour)
    $lastCleanup = LOGS_PATH . '/last_cleanup.txt';
    $shouldCleanup = false;
    
    if (!file_exists($lastCleanup)) {
        $shouldCleanup = true;
    } else {
        $lastTime = (int)file_get_contents($lastCleanup);
        $shouldCleanup = (time() - $lastTime) > 86400; // 24 heures
    }
    
    if ($shouldCleanup) {
        Logger::info('Starting daily cleanup');
        
        // Nettoyer les anciennes notifications
        $cleanedNotifications = NotificationService::cleanup(7);
        Logger::info("Cleaned {$cleanedNotifications} old notifications");
        
        // Nettoyer les anciens logs de rate limiting
        $rateLimitDir = LOGS_PATH . '/rate_limits';
        if (is_dir($rateLimitDir)) {
            $files = glob($rateLimitDir . '/*.json');
            $cleaned = 0;
            foreach ($files as $file) {
                if (time() - filemtime($file) > 3600) { // 1 heure
                    unlink($file);
                    $cleaned++;
                }
            }
            if ($cleaned > 0) {
                Logger::info("Cleaned {$cleaned} old rate limit files");
            }
        }
        
        // Marquer le nettoyage comme fait
        file_put_contents($lastCleanup, time());
        
        Logger::info('Daily cleanup completed');
    }
    
} catch (Exception $e) {
    Logger::error('Cron worker error: ' . $e->getMessage());
} finally {
    cleanup();
}