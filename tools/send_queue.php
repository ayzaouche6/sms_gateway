#!/usr/bin/env php
<?php
/**
 * Script de traitement de la file d'attente SMS
 * À exécuter périodiquement via cron ou systemd
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
$lockFile = LOGS_PATH . '/queue_worker.lock';
if (file_exists($lockFile)) {
    $pid = file_get_contents($lockFile);
    if ($pid && posix_kill($pid, 0)) {
        echo "Le processus de traitement de la queue est déjà en cours (PID: {$pid})\n";
        exit(1);
    } else {
        // Le processus précédent est mort, supprimer le fichier de verrouillage
        unlink($lockFile);
    }
}

// Créer le fichier de verrouillage
file_put_contents($lockFile, getmypid());

// Fonction de nettoyage en cas de sortie
function cleanup() {
    global $lockFile;
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

// Enregistrer les fonctions de nettoyage
register_shutdown_function('cleanup');
pcntl_signal(SIGTERM, function() { cleanup(); exit(0); });
pcntl_signal(SIGINT, function() { cleanup(); exit(0); });

try {
    // Charger le bootstrap de l'application
    require_once APP_PATH . '/bootstrap.php';
    
    echo "[" . date('Y-m-d H:i:s') . "] Démarrage du traitement de la file d'attente SMS\n";
    
    // Options de ligne de commande
    $options = getopt('', ['max-sms:', 'verbose', 'dry-run', 'help']);
    
    if (isset($options['help'])) {
        echo "Usage: php send_queue.php [options]\n";
        echo "Options:\n";
        echo "  --max-sms=N     Nombre maximum de SMS à traiter (défaut: 50)\n";
        echo "  --verbose       Mode verbeux\n";
        echo "  --dry-run       Simulation sans envoi réel\n";
        echo "  --help          Afficher cette aide\n";
        exit(0);
    }
    
    $maxSms = isset($options['max-sms']) ? (int)$options['max-sms'] : 50;
    $verbose = isset($options['verbose']);
    $dryRun = isset($options['dry-run']);
    
    if ($dryRun) {
        echo "MODE SIMULATION - Aucun SMS ne sera réellement envoyé\n";
    }
    
    // Initialiser le service de queue
    $queueService = new QueueService();
    
    // Nettoyer les SMS bloqués
    $stuckCount = $queueService->clearStuckSms();
    if ($stuckCount > 0) {
        echo "Réinitialisé {$stuckCount} SMS bloqués\n";
    }
    
    // Récupérer les SMS en attente
    $pendingSms = Sms::getPendingSms($maxSms);
    
    if (empty($pendingSms)) {
        if ($verbose) {
            echo "Aucun SMS en attente\n";
        }
        exit(0);
    }
    
    echo "Traitement de " . count($pendingSms) . " SMS en attente\n";
    
    $processed = 0;
    $sent = 0;
    $failed = 0;
    
    foreach ($pendingSms as $sms) {
        try {
            if ($verbose) {
                echo "Traitement SMS #{$sms['id']} vers {$sms['recipient']}\n";
            }
            
            // Marquer comme en cours de traitement
            if (!$dryRun) {
                Sms::markAsProcessing($sms['id']);
            }
            
            // Obtenir un modem disponible
            $modem = Modem::getBestAvailable();
            if (!$modem) {
                throw new Exception('Aucun modem disponible');
            }
            
            if ($verbose) {
                echo "  Utilisation du modem: {$modem['name']} ({$modem['device_path']})\n";
            }
            
            if (!$dryRun) {
                // Mettre à jour avec le modem sélectionné
                Sms::update($sms['id'], ['modem_id' => $modem['id']]);
                
                // Envoyer le SMS via Python
                $result = sendSmsViaPython($sms, $modem, $verbose);
                
                if ($result['success']) {
                    Sms::markAsSent($sms['id']);
                    Modem::updateLastUsed($modem['id']);
                    $sent++;
                    
                    if ($verbose) {
                        echo "  ✓ SMS envoyé avec succès\n";
                    }
                } else {
                    throw new Exception($result['error'] ?? 'Erreur inconnue');
                }
            } else {
                // Mode simulation
                echo "  [SIMULATION] SMS envoyé via {$modem['name']}\n";
                $sent++;
            }
            
            $processed++;
            
            // Petite pause entre les envois pour éviter la surcharge
            if (!$dryRun) {
                usleep(500000); // 0.5 seconde
            }
            
        } catch (Exception $e) {
            $failed++;
            
            if ($verbose) {
                echo "  ✗ Erreur: " . $e->getMessage() . "\n";
            }
            
            if (!$dryRun) {
                handleSmsError($sms, $e->getMessage(), $verbose);
            }
        }
        
        // Vérifier les signaux
        pcntl_signal_dispatch();
    }
    
    // Traiter les tentatives de réenvoi
    if (!$dryRun) {
        $retryCount = processRetries($verbose);
        if ($retryCount > 0) {
            echo "Remis en queue {$retryCount} SMS pour nouvelle tentative\n";
        }
    }
    
    // Résumé
    echo "Traitement terminé: {$processed} traités, {$sent} envoyés, {$failed} échoués\n";
    
    // Vérifier la santé de la queue
    if (!$dryRun) {
        NotificationService::checkQueueHealth();
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Fin du traitement\n";
    
} catch (Exception $e) {
    echo "ERREUR FATALE: " . $e->getMessage() . "\n";
    Logger::error('Queue worker fatal error: ' . $e->getMessage());
    exit(1);
} finally {
    cleanup();
}

/**
 * Envoie un SMS via le script Python
 */
function sendSmsViaPython($sms, $modem, $verbose = false)
{
    $pythonScript = SMS_PYTHON_SCRIPT;
    
    $command = sprintf(
        'python3 %s --device %s --recipient %s --message %s --json-output',
        escapeshellarg($pythonScript),
        escapeshellarg($modem['device_path']),
        escapeshellarg($sms['recipient']),
        escapeshellarg($sms['message'])
    );
    
    if ($verbose) {
        echo "  Commande: {$command}\n";
    }
    
    // Exécuter la commande avec timeout
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ], $pipes);
    
    if (!is_resource($process)) {
        throw new Exception('Impossible de démarrer le processus Python');
    }
    
    // Fermer stdin
    fclose($pipes[0]);
    
    // Lire la sortie avec timeout
    $output = '';
    $error = '';
    $timeout = 60; // 60 secondes
    $start = time();
    
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    
    while (time() - $start < $timeout) {
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;
        
        if (stream_select($read, $write, $except, 1) > 0) {
            foreach ($read as $stream) {
                if ($stream === $pipes[1]) {
                    $output .= fread($stream, 8192);
                } elseif ($stream === $pipes[2]) {
                    $error .= fread($stream, 8192);
                }
            }
        }
        
        // Vérifier si le processus est toujours en cours
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
    }
    
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $returnCode = proc_close($process);
    
    // Parser la sortie JSON
    $result = json_decode($output, true);
    
    if ($result === null) {
        // Pas de JSON valide, utiliser la sortie brute
        if ($returnCode === 0) {
            return ['success' => true, 'output' => $output];
        } else {
            return ['success' => false, 'error' => $error ?: $output];
        }
    }
    
    return $result;
}

/**
 * Gère les erreurs d'envoi SMS
 */
function handleSmsError($sms, $errorMessage, $verbose = false)
{
    Sms::incrementRetryCount($sms['id']);
    
    if ($sms['retry_count'] >= SMS_RETRY_ATTEMPTS) {
        // Marquer comme échoué définitivement
        Sms::markAsFailed($sms['id'], 'max_retries', $errorMessage);
        
        // Créer une notification
        NotificationService::createSmsFailedNotification(
            $sms['id'], 
            $sms['recipient'], 
            $errorMessage
        );
        
        if ($verbose) {
            echo "  SMS #{$sms['id']} marqué comme échoué définitivement\n";
        }
        
        Logger::error("SMS failed permanently", [
            'sms_id' => $sms['id'],
            'recipient' => $sms['recipient'],
            'error' => $errorMessage,
            'retry_count' => $sms['retry_count']
        ]);
    } else {
        // Programmer pour une nouvelle tentative
        Sms::markAsFailed($sms['id'], 'retry_later', $errorMessage);
        
        if ($verbose) {
            echo "  SMS #{$sms['id']} programmé pour nouvelle tentative\n";
        }
        
        Logger::warning("SMS failed, will retry", [
            'sms_id' => $sms['id'],
            'recipient' => $sms['recipient'],
            'error' => $errorMessage,
            'retry_count' => $sms['retry_count']
        ]);
    }
}

/**
 * Traite les SMS à relancer
 */
function processRetries($verbose = false)
{
    $retrySmsList = Sms::getFailedSmsForRetry();
    
    if (empty($retrySmsList)) {
        return 0;
    }
    
    if ($verbose) {
        echo "Traitement de " . count($retrySmsList) . " SMS à relancer\n";
    }
    
    foreach ($retrySmsList as $sms) {
        Sms::resetToQueue($sms['id']);
        
        if ($verbose) {
            echo "  SMS #{$sms['id']} remis en queue\n";
        }
        
        Logger::info("SMS queued for retry", ['sms_id' => $sms['id']]);
    }
    
    return count($retrySmsList);
}