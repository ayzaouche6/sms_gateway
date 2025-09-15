<?php
/**
 * Modèle SMS Reçus
 */
class ReceivedSms
{
    public static function find($id)
    {
        $db = Database::getInstance();
        return $db->selectOne("SELECT * FROM received_sms WHERE id = ?", [$id]);
    }
    
    public static function getAll($limit = 50, $offset = 0)
    {
        $db = Database::getInstance();
        return $db->select("
            SELECT r.*, m.name as modem_name
            FROM received_sms r
            LEFT JOIN modems m ON r.modem_id = m.id
            ORDER BY r.received_at DESC
            LIMIT ? OFFSET ?
        ", [$limit, $offset]);
    }
    
    public static function search($query, $limit = 50, $offset = 0)
    {
        $db = Database::getInstance();
        return $db->select("
            SELECT r.*, m.name as modem_name
            FROM received_sms r
            LEFT JOIN modems m ON r.modem_id = m.id
            WHERE r.sender LIKE ? OR r.message LIKE ?
            ORDER BY r.received_at DESC
            LIMIT ? OFFSET ?
        ", ["%{$query}%", "%{$query}%", $limit, $offset]);
    }
    
    public static function count($search = '')
    {
        $db = Database::getInstance();
        
        if ($search) {
            $result = $db->selectOne("
                SELECT COUNT(*) as count 
                FROM received_sms 
                WHERE sender LIKE ? OR message LIKE ?
            ", ["%{$search}%", "%{$search}%"]);
        } else {
            $result = $db->selectOne("SELECT COUNT(*) as count FROM received_sms");
        }
        
        return (int)$result['count'];
    }
    
    public static function getRecent($limit = 10)
    {
        $db = Database::getInstance();
        return $db->select("
            SELECT r.*, m.name as modem_name
            FROM received_sms r
            LEFT JOIN modems m ON r.modem_id = m.id
            ORDER BY r.received_at DESC
            LIMIT ?
        ", [$limit]);
    }
    
    public static function getStatsByPeriod($dateFrom, $dateTo)
    {
        $db = Database::getInstance();
        return $db->select("
            SELECT 
                DATE(received_at) as date,
                COUNT(*) as total,
                COUNT(DISTINCT sender) as unique_senders
            FROM received_sms 
            WHERE DATE(received_at) BETWEEN ? AND ?
            GROUP BY DATE(received_at)
            ORDER BY DATE(received_at)
        ", [$dateFrom, $dateTo]);
    }
    
    public static function cleanup($daysOld = 30)
    {
        $db = Database::getInstance();
        return $db->delete(
            'received_sms', 
            'received_at < DATE_SUB(NOW(), INTERVAL ? DAY)', 
            [$daysOld]
        );
    }
}