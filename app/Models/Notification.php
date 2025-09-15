<?php
/**
 * Modèle Notification
 */
class Notification
{
    public static function find($id)
    {
        $db = Database::getInstance();
        return $db->selectOne("SELECT * FROM notifications WHERE id = ?", [$id]);
    }
    
    public static function create($data)
    {
        $db = Database::getInstance();
        $data['created_at'] = date('Y-m-d H:i:s');
        return $db->insert('notifications', $data);
    }
    
    public static function update($id, $data)
    {
        $db = Database::getInstance();
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $db->update('notifications', $data, 'id = ?', [$id]);
    }
    
    public static function delete($id)
    {
        $db = Database::getInstance();
        return $db->delete('notifications', 'id = ?', [$id]);
    }
    
    public static function createSmsFailedNotification($smsId, $recipient, $errorMessage)
    {
        return self::create([
            'type' => 'sms_failed',
            'title' => 'Échec d\'envoi SMS',
            'message' => "Échec d'envoi SMS vers {$recipient}: {$errorMessage}",
            'data' => json_encode(['sms_id' => $smsId, 'recipient' => $recipient]),
            'priority' => 'high'
        ]);
    }
    
    public static function createQueueBlockedNotification($queueSize)
    {
        return self::create([
            'type' => 'queue_blocked',
            'title' => 'File d\'attente bloquée',
            'message' => "La file d'attente SMS contient {$queueSize} messages en attente",
            'priority' => 'medium'
        ]);
    }
    
    public static function createModemOfflineNotification($modemName, $devicePath)
    {
        return self::create([
            'type' => 'modem_offline',
            'title' => 'Modem hors ligne',
            'message' => "Le modem {$modemName} ({$devicePath}) ne répond pas",
            'data' => json_encode(['modem_name' => $modemName, 'device_path' => $devicePath]),
            'priority' => 'high'
        ]);
    }
    
    public static function getPending($limit = 50)
    {
        $db = Database::getInstance();
        return $db->select("
            SELECT * FROM notifications 
            WHERE processed = 0 
            ORDER BY 
                CASE priority 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 3 
                END,
                created_at ASC
            LIMIT ?
        ", [$limit]);
    }
    
    public static function markAsProcessed($id)
    {
        return self::update($id, [
            'processed' => 1,
            'processed_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public static function markAsFailed($id, $errorMessage)
    {
        return self::update($id, [
            'failed' => 1,
            'error_message' => $errorMessage,
            'processed_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public static function cleanup($daysOld = 30)
    {
        $db = Database::getInstance();
        return $db->delete(
            'notifications', 
            'created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', 
            [$daysOld]
        );
    }
    
    public static function getRecent($limit = 20)
    {
        $db = Database::getInstance();
        return $db->select("
            SELECT * FROM notifications 
            ORDER BY created_at DESC 
            LIMIT ?
        ", [$limit]);
    }
    
    public static function countByStatus($status = 'pending')
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as count FROM notifications";
        $params = [];
        
        if ($status === 'pending') {
            $sql .= " WHERE processed = 0 AND failed = 0";
        } elseif ($status === 'processed') {
            $sql .= " WHERE processed = 1";
        } elseif ($status === 'failed') {
            $sql .= " WHERE failed = 1";
        }
        
        $result = $db->selectOne($sql, $params);
        return (int)$result['count'];
    }
}