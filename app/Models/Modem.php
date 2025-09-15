<?php
/**
 * Modèle Modem
 */
class Modem
{
    public static function find($id)
    {
        $db = Database::getInstance();
        return $db->selectOne("SELECT * FROM modems WHERE id = ?", [$id]);
    }
    
    public static function getAll()
    {
        $db = Database::getInstance();
        return $db->select("SELECT * FROM modems ORDER BY name");
    }
    
    public static function getActive()
    {
        $db = Database::getInstance();
        return $db->select("SELECT * FROM modems WHERE is_active = 1 ORDER BY priority DESC, name");
    }
    
    public static function create($data)
    {
        $db = Database::getInstance();
        $data['created_at'] = date('Y-m-d H:i:s');
        return $db->insert('modems', $data);
    }
    
    public static function update($id, $data)
    {
        $db = Database::getInstance();
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $db->update('modems', $data, 'id = ?', [$id]);
    }
    
    public static function delete($id)
    {
        $db = Database::getInstance();
        return $db->delete('modems', 'id = ?', [$id]);
    }
    
    public static function findByDevicePath($devicePath)
    {
        $db = Database::getInstance();
        return $db->selectOne("SELECT * FROM modems WHERE device_path = ?", [$devicePath]);
    }
    
    public static function updateLastUsed($id)
    {
        $db = Database::getInstance();
        return $db->update('modems', [
            'last_used' => date('Y-m-d H:i:s'),
            'sms_sent' => 'sms_sent + 1'
        ], 'id = ?', [$id]);
    }
    
    public static function setActive($id, $active = true)
    {
        $db = Database::getInstance();
        return $db->update('modems', ['is_active' => $active ? 1 : 0], 'id = ?', [$id]);
    }
    
    public static function getBestAvailable()
    {
        $db = Database::getInstance();
        
        // Trouver le modem actif avec la plus haute priorité et le moins utilisé récemment
        return $db->selectOne("
            SELECT * FROM modems 
            WHERE is_active = 1 
            ORDER BY 
                priority DESC,
                COALESCE(last_used, '1970-01-01') ASC,
                sms_sent ASC
            LIMIT 1
        ");
    }
    
    public static function recordError($id, $errorMessage)
    {
        $db = Database::getInstance();
        return $db->update('modems', [
            'last_error' => $errorMessage,
            'last_error_at' => date('Y-m-d H:i:s'),
            'error_count' => 'error_count + 1'
        ], 'id = ?', [$id]);
    }
    
    public static function clearError($id)
    {
        $db = Database::getInstance();
        return $db->update('modems', [
            'last_error' => null,
            'last_error_at' => null
        ], 'id = ?', [$id]);
    }
    
    public static function getStats($id)
    {
        $db = Database::getInstance();
        return $db->selectOne("
            SELECT 
                m.*,
                COUNT(s.id) as total_sms,
                SUM(CASE WHEN s.status = 'sent' THEN 1 ELSE 0 END) as sent_sms,
                SUM(CASE WHEN s.status = 'failed' THEN 1 ELSE 0 END) as failed_sms
            FROM modems m
            LEFT JOIN sms s ON m.id = s.modem_id
            WHERE m.id = ?
            GROUP BY m.id
        ", [$id]);
    }
}