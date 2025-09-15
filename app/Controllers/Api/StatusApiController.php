<?php
/**
 * Contrôleur API pour les statuts et statistiques
 */
class StatusApiController extends Controller
{
    public function queueStatus()
    {
        try {
            $db = Database::getInstance();
            
            $stats = $db->selectOne("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM sms
            ");
            
            $this->json([
                'success' => true,
                'total' => (int)$stats['total'],
                'pending' => (int)$stats['pending'],
                'processing' => (int)$stats['processing'],
                'sent' => (int)$stats['sent'],
                'failed' => (int)$stats['failed']
            ]);
            
        } catch (Exception $e) {
            Logger::error('API queue status error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
    
    public function stats()
    {
        try {
            $db = Database::getInstance();
            
            // Stats générales
            $generalStats = $db->selectOne("
                SELECT 
                    COUNT(*) as total_sms,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM sms
            ");
            
            // Stats du jour
            $todayStats = $db->selectOne("
                SELECT 
                    COUNT(*) as today_total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as today_sent
                FROM sms 
                WHERE DATE(created_at) = CURDATE()
            ");
            
            // Calcul du taux de succès
            $successRate = $generalStats['total_sms'] > 0 ? 
                round(($generalStats['sent'] / $generalStats['total_sms']) * 100, 1) : 0;
            
            $this->json([
                'success' => true,
                'sent' => (int)$generalStats['sent'],
                'pending' => (int)$generalStats['pending'],
                'failed' => (int)$generalStats['failed'],
                'total' => (int)$generalStats['total_sms'],
                'today_total' => (int)($todayStats['today_total'] ?? 0),
                'today_sent' => (int)($todayStats['today_sent'] ?? 0),
                'success_rate' => $successRate
            ]);
            
        } catch (Exception $e) {
            Logger::error('API stats error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
    
    public function clearQueue()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        try {
            $db = Database::getInstance();
            
            // Supprimer seulement les SMS en attente et échoués
            $deleted = $db->delete('sms', "status IN ('pending', 'failed')");
            
            Logger::info("Queue cleared by user " . Auth::id() . ": {$deleted} SMS removed");
            
            $this->json([
                'success' => true,
                'message' => "{$deleted} SMS supprimés de la file d'attente"
            ]);
            
        } catch (Exception $e) {
            Logger::error('API clear queue error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur lors du vidage'], 500);
        }
    }
    
    public function modemStatus()
    {
        try {
            $modems = Modem::getAll();
            $status = [];
            
            foreach ($modems as $modem) {
                $status[] = [
                    'id' => $modem['id'],
                    'name' => $modem['name'],
                    'device_path' => $modem['device_path'],
                    'is_active' => (bool)$modem['is_active'],
                    'last_used' => $modem['last_used'],
                    'sms_sent' => (int)$modem['sms_sent']
                ];
            }
            
            $this->json([
                'success' => true,
                'modems' => $status
            ]);
            
        } catch (Exception $e) {
            Logger::error('API modem status error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
}