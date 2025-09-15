<?php
/**
 * Modèle SMS
 */
class Sms
{
    public static function find($id)
    {
        $db = Database::getInstance();
        return $db->selectOne("SELECT * FROM sms WHERE id = ?", [$id]);
    }
    
    public static function create($data)
    {
        $db = Database::getInstance();
        $data['created_at'] = date('Y-m-d H:i:s');
        return $db->insert('sms', $data);
    }
    
    public static function update($id, $data)
    {
        $db = Database::getInstance();
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $db->update('sms', $data, 'id = ?', [$id]);
    }
    
    public static function delete($id)
    {
        $db = Database::getInstance();
        return $db->delete('sms', 'id = ?', [$id]);
    }
    
    public static function getQueue($status = null, $limit = 100, $offset = 0)
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM sms";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY priority DESC, created_at ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $db->select($sql, $params);
    }
    
    public static function getPendingSms($limit = 10)
    {
        $db = Database::getInstance();
        return $db->select("
            SELECT * FROM sms 
            WHERE status IN ('pending', 'scheduled') 
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            ORDER BY priority DESC, created_at ASC 
            LIMIT ?
        ", [$limit]);
    }
    
    public static function markAsProcessing($id, $modemId = null)
    {
        $db = Database::getInstance();
        $data = [
            'status' => 'processing',
            'processing_started_at' => date('Y-m-d H:i:s')
        ];
        
        if ($modemId) {
            $data['modem_id'] = $modemId;
        }
        
        return self::update($id, $data);
    }
    
    public static function markAsSent($id)
    {
        $db = Database::getInstance();
        return self::update($id, [
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public static function markAsFailed($id, $errorCode = null, $errorMessage = null)
    {
        $db = Database::getInstance();
        return self::update($id, [
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'failed_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public static function incrementRetryCount($id)
    {
        $db = Database::getInstance();
        return $db->query("UPDATE sms SET retry_count = retry_count + 1 WHERE id = ?", [$id]);
    }
    
    public static function search($query, $status = '', $userId = null, $limit = 20, $offset = 0)
    {
        $db = Database::getInstance();
        
        $sql = "
            SELECT s.*, u.username as sender_name
            FROM sms s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($query)) {
            $sql .= " AND (s.recipient LIKE ? OR s.message LIKE ?)";
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
        }
        
        if (!empty($status)) {
            $sql .= " AND s.status = ?";
            $params[] = $status;
        }
        
        if ($userId !== null) {
            $sql .= " AND s.user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $db->select($sql, $params);
    }
    
    public static function countSearch($query, $status = '', $userId = null)
    {
        $db = Database::getInstance();
        
        $sql = "SELECT COUNT(*) as count FROM sms WHERE 1=1";
        $params = [];
        
        if (!empty($query)) {
            $sql .= " AND (recipient LIKE ? OR message LIKE ?)";
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
        }
        
        if (!empty($status)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $result = $db->selectOne($sql, $params);
        return (int)$result['count'];
    }
    
    public static function getFailedSmsForRetry()
    {
        $db = Database::getInstance();
        return $db->select("
            SELECT * FROM sms 
            WHERE status = 'failed' 
            AND retry_count < ? 
            AND (last_retry_at IS NULL OR last_retry_at < DATE_SUB(NOW(), INTERVAL ? SECOND))
            ORDER BY created_at ASC
            LIMIT 50
        ", [SMS_RETRY_ATTEMPTS, SMS_RETRY_DELAY]);
    }
    
    public static function resetToQueue($id)
    {
        return self::update($id, [
            'status' => 'pending',
            'processing_started_at' => null,
            'error_code' => null,
            'error_message' => null,
            'last_retry_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public static function getStatsByPeriod($dateFrom, $dateTo, $userId = null)
    {
        $db = Database::getInstance();
        $sql = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM sms 
            WHERE DATE(created_at) BETWEEN ? AND ?
        ";
        $params = [$dateFrom, $dateTo];
        
        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " GROUP BY DATE(created_at) ORDER BY DATE(created_at)";
        
        return $db->select($sql, $params);
    }
}