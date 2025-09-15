<?php
/**
 * Modèle Utilisateur
 */
class User
{
    public static function find($id)
    {
        $db = Database::getInstance();
        return $db->selectOne("SELECT * FROM users WHERE id = ?", [$id]);
    }
    
    public static function findByEmail($email)
    {
        $db = Database::getInstance();
        return $db->selectOne("SELECT * FROM users WHERE email = ?", [$email]);
    }
    
    public static function create($data)
    {
        $db = Database::getInstance();
        
        // Hash du mot de passe
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // Langue par défaut
        if (!isset($data['language'])) {
            $data['language'] = currentLang();
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        
        return $db->insert('users', $data);
    }
    
    public static function update($id, $data)
    {
        $db = Database::getInstance();
        
        // Hash du mot de passe si fourni
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $db->update('users', $data, 'id = ?', [$id]);
    }
    
    public static function delete($id)
    {
        $db = Database::getInstance();
        return $db->delete('users', 'id = ?', [$id]);
    }
    
    public static function updateLastLogin($id)
    {
        $db = Database::getInstance();
        return $db->update('users', [
            'last_login' => date('Y-m-d H:i:s'),
            'login_count' => 'login_count + 1'
        ], 'id = ?', [$id]);
    }
    
    public static function getAll($limit = null, $offset = null)
    {
        $db = Database::getInstance();
        $sql = "SELECT id, username, email, role, is_active, created_at, last_login FROM users ORDER BY created_at DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }
        
        return $db->select($sql);
    }
    
    public static function search($query, $limit = 20, $offset = 0)
    {
        $db = Database::getInstance();
        return $db->select("
            SELECT id, username, email, role, is_active, created_at, last_login 
            FROM users 
            WHERE username LIKE ? OR email LIKE ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ", ["%{$query}%", "%{$query}%", $limit, $offset]);
    }
    
    public static function countByRole($role)
    {
        $db = Database::getInstance();
        $result = $db->selectOne("SELECT COUNT(*) as count FROM users WHERE role = ?", [$role]);
        return (int)$result['count'];
    }
    
    public static function isEmailTaken($email, $excludeId = null)
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $db->selectOne($sql, $params);
        return (int)$result['count'] > 0;
    }
    
    public static function isUsernameTaken($username, $excludeId = null)
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as count FROM users WHERE username = ?";
        $params = [$username];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $db->selectOne($sql, $params);
        return (int)$result['count'] > 0;
    }
}