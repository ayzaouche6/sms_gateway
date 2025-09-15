<?php
/**
 * Service de gestion des SMS
 */
class SmsService
{
    public function queueSms($recipient, $message, $userId, $scheduledAt = null, $priority = 1)
    {
        // Nettoyer et valider le numéro
        $recipient = $this->cleanPhoneNumber($recipient);
        if (!$this->isValidPhoneNumber($recipient)) {
            throw new Exception('Numéro de téléphone invalide');
        }
        
        // Vérifier la longueur du message
        if (strlen($message) > SMS_MAX_LENGTH) {
            throw new Exception('Message trop long (max ' . SMS_MAX_LENGTH . ' caractères)');
        }
        
        // Vérifier les limites utilisateur
        if (!$this->checkUserLimits($userId)) {
            throw new Exception('Limite d\'envoi atteinte');
        }
        
        $data = [
            'recipient' => $recipient,
            'message' => $message,
            'user_id' => $userId,
            'status' => $scheduledAt ? SMS_STATUS_SCHEDULED : SMS_STATUS_PENDING,
            'priority' => $priority,
            'scheduled_at' => $scheduledAt,
            'is_unicode' => $this->containsUnicode($message)
        ];
        
        $smsId = Sms::create($data);
        
        Logger::info("SMS queued", [
            'sms_id' => $smsId,
            'recipient' => $recipient,
            'user_id' => $userId
        ]);
        
        return $smsId;
    }
    
    public function processBulkSms($csvFile, $message, $userId)
    {
        if ($csvFile['size'] > UPLOAD_MAX_SIZE) {
            throw new Exception('Fichier trop volumineux');
        }
        
        $ext = pathinfo($csvFile['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), UPLOAD_ALLOWED_TYPES)) {
            throw new Exception('Type de fichier non autorisé');
        }
        
        $recipients = $this->parseCsvFile($csvFile['tmp_name']);
        $success = 0;
        $errors = 0;
        
        foreach ($recipients as $recipient) {
            try {
                $this->queueSms($recipient, $message, $userId);
                $success++;
            } catch (Exception $e) {
                Logger::warning("Bulk SMS error for {$recipient}: " . $e->getMessage());
                $errors++;
            }
        }
        
        return ['success' => $success, 'errors' => $errors];
    }
    
    public function getSmsQueue($search = '', $status = '', $limit = 20, $offset = 0, $userId = null)
    {
        $sms = Sms::search($search, $status, $userId, $limit, $offset);
        $total = Sms::countSearch($search, $status, $userId);
        
        return ['sms' => $sms, 'total' => $total];
    }
    
    public function retrySms($smsId)
    {
        $sms = Sms::find($smsId);
        if (!$sms) {
            throw new Exception('SMS non trouvé');
        }
        
        if ($sms['status'] !== SMS_STATUS_FAILED) {
            throw new Exception('Seuls les SMS échoués peuvent être relancés');
        }
        
        Sms::resetToQueue($smsId);
        
        Logger::info("SMS retry queued", ['sms_id' => $smsId]);
    }
    
    private function cleanPhoneNumber($phone)
    {
        // Supprimer tous les caractères non numériques sauf +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Ajouter l'indicatif pays si manquant
        if (substr($phone, 0, 1) === '0') {
            $phone = '+33' . substr($phone, 1);
        } elseif (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
    
    private function isValidPhoneNumber($phone)
    {
        // Vérification basique d'un numéro international
        return preg_match('/^\+[1-9]\d{6,14}$/', $phone);
    }
    
    private function checkUserLimits($userId)
    {
        $db = Database::getInstance();
        
        // Vérifier la limite par minute
        $recentCount = $db->selectOne("
            SELECT COUNT(*) as count 
            FROM sms 
            WHERE user_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ", [$userId]);
        
        return $recentCount['count'] < SMS_MAX_PER_MINUTE;
    }
    
    private function containsUnicode($text)
    {
        return mb_strlen($text, 'UTF-8') !== strlen($text);
    }
    
    private function parseCsvFile($filePath)
    {
        $recipients = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            throw new Exception('Impossible de lire le fichier CSV');
        }
        
        // Ignorer la première ligne si elle contient des en-têtes
        $firstLine = fgetcsv($handle);
        if (!$this->isValidPhoneNumber($this->cleanPhoneNumber($firstLine[0]))) {
            // Probablement une ligne d'en-tête
        } else {
            $recipients[] = $this->cleanPhoneNumber($firstLine[0]);
        }
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (!empty($data[0])) {
                $phone = $this->cleanPhoneNumber($data[0]);
                if ($this->isValidPhoneNumber($phone)) {
                    $recipients[] = $phone;
                }
            }
        }
        
        fclose($handle);
        
        if (empty($recipients)) {
            throw new Exception('Aucun numéro valide trouvé dans le fichier');
        }
        
        return array_unique($recipients);
    }
}