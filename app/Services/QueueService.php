<?php
/**
 * Service de traitement de la file d'attente SMS
 */
class QueueService
{
    private $pythonScript;
    private $maxConcurrent = 5;
    
    public function __construct()
    {
        $this->pythonScript = SMS_PYTHON_SCRIPT;
    }
    
    public function processQueue()
    {
        Logger::info("Starting queue processing");
        
        // Récupérer les SMS en attente
        $pendingSms = Sms::getPendingSms($this->maxConcurrent);
        
        if (empty($pendingSms)) {
            Logger::debug("No pending SMS found");
            return;
        }
        
        Logger::info("Processing " . count($pendingSms) . " SMS");
        
        foreach ($pendingSms as $sms) {
            $this->processSingleSms($sms);
        }
        
        // Traiter les tentatives de réenvoi
        $this->processRetries();
        
        Logger::info("Queue processing completed");
    }
    
    private function processSingleSms($sms)
    {
        try {
            // Marquer comme en cours de traitement
            Sms::markAsProcessing($sms['id']);
            
            // Obtenir un modem disponible
            $modem = Modem::getBestAvailable();
            if (!$modem) {
                throw new Exception('Aucun modem disponible');
            }
            
            // Mettre à jour avec le modem sélectionné
            Sms::update($sms['id'], ['modem_id' => $modem['id']]);
            
            // Envoyer le SMS
            $result = $this->sendSmsViaPython($sms, $modem);
            
            if ($result['success']) {
                Sms::markAsSent($sms['id']);
                Modem::updateLastUsed($modem['id']);
                
                Logger::info("SMS sent successfully", [
                    'sms_id' => $sms['id'],
                    'recipient' => $sms['recipient'],
                    'modem_id' => $modem['id']
                ]);
            } else {
                throw new Exception($result['error'] ?? 'Erreur inconnue');
            }
            
        } catch (Exception $e) {
            $this->handleSmsError($sms, $e->getMessage());
        }
    }
    
    private function sendSmsViaPython($sms, $modem)
    {
        $command = sprintf(
            'python3 %s --device %s --recipient %s --message %s',
            escapeshellarg($this->pythonScript),
            escapeshellarg($modem['device_path']),
            escapeshellarg($sms['recipient']),
            escapeshellarg($sms['message'])
        );
        
        Logger::debug("Executing command: " . $command);
        
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
        
        // Lire la sortie
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $returnCode = proc_close($process);
        
        if ($returnCode === 0) {
            return ['success' => true, 'output' => $output];
        } else {
            return ['success' => false, 'error' => $error ?: $output];
        }
    }
    
    private function handleSmsError($sms, $errorMessage)
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
            
            Logger::error("SMS failed permanently", [
                'sms_id' => $sms['id'],
                'recipient' => $sms['recipient'],
                'error' => $errorMessage,
                'retry_count' => $sms['retry_count']
            ]);
        } else {
            // Programmer pour une nouvelle tentative
            Sms::markAsFailed($sms['id'], 'retry_later', $errorMessage);
            
            Logger::warning("SMS failed, will retry", [
                'sms_id' => $sms['id'],
                'recipient' => $sms['recipient'],
                'error' => $errorMessage,
                'retry_count' => $sms['retry_count']
            ]);
        }
    }
    
    private function processRetries()
    {
        $retrySmsList = Sms::getFailedSmsForRetry();
        
        if (empty($retrySmsList)) {
            return;
        }
        
        Logger::info("Processing " . count($retrySmsList) . " SMS retries");
        
        foreach ($retrySmsList as $sms) {
            Sms::resetToQueue($sms['id']);
            Logger::info("SMS queued for retry", ['sms_id' => $sms['id']]);
        }
    }
    
    public function getQueueStatus()
    {
        $db = Database::getInstance();
        return $db->selectOne("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM sms
        ");
    }
    
    public function clearStuckSms()
    {
        // Réinitialiser les SMS bloqués en "processing" depuis trop longtemps
        $db = Database::getInstance();
        $affected = $db->update('sms', [
            'status' => SMS_STATUS_PENDING,
            'processing_started_at' => null
        ], "status = 'processing' AND processing_started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        
        if ($affected > 0) {
            Logger::info("Reset {$affected} stuck SMS from processing to pending");
        }
        
        return $affected;
    }
}