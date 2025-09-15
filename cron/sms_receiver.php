#!/usr/bin/env php
<?php
/**
 * Script cron pour la réception de SMS
 * À exécuter périodiquement via cron
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
$lockFile = LOGS_PATH . '/sms_receiver.lock';
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    // Si le fichier de verrouillage a plus de 5 minutes, le supprimer
    if (time() - $lockTime > 300) {
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
    
    // Vérifier si la réception SMS est activée
    $db = Database::getInstance();
    $config = $db->selectOne("SELECT config_value FROM system_config WHERE config_key = 'sms_receive_enabled'");
    $receiveEnabled = ($config && $config['config_value'] === 'true');
    
    if (!$receiveEnabled) {
        Logger::debug('SMS receiving is disabled');
        exit(0);
    }
    
    // Exécuter le script Python de réception
    $pythonScript = ROOT_PATH . '/tools/receive_sms_mmcli.py';
    $command = "python3 {$pythonScript} --check-once --json-output";
    
    Logger::debug("Executing SMS receive command: " . $command);
    
    $output = shell_exec($command);
    $result = json_decode($output, true);
    
    if ($result && $result['success']) {
        if ($result['processed'] > 0) {
            Logger::info("SMS receive cycle completed: {$result['processed']} SMS processed from {$result['modems']} modems");
        } else {
            Logger::debug("SMS receive cycle completed: no new SMS");
        }
    } else {
        $error = $result['error'] ?? 'Unknown error';
        Logger::error("SMS receive cycle failed: " . $error);
    }
    
    // Nettoyage périodique (une fois par jour)
    $lastCleanup = LOGS_PATH . '/last_sms_cleanup.txt';
    $shouldCleanup = false;
    
    if (!file_exists($lastCleanup)) {
        $shouldCleanup = true;
    } else {
        $lastTime = (int)file_get_contents($lastCleanup);
        $shouldCleanup = (time() - $lastTime) > 86400; // 24 heures
    }
    
    if ($shouldCleanup) {
        Logger::info('Starting SMS cleanup');
        
        // Nettoyer les anciens SMS reçus
        $cleanedSms = ReceivedSms::cleanup(30);
        Logger::info("Cleaned {$cleanedSms} old received SMS");
        
        // Marquer le nettoyage comme fait
        file_put_contents($lastCleanup, time());
        
        Logger::info('SMS cleanup completed');
    }
    
} catch (Exception $e) {
    Logger::error('SMS receiver cron error: ' . $e->getMessage());
} finally {
    cleanup();
}