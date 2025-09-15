<?php
/**
 * Contrôleur du tableau de bord
 */
class DashboardController extends Controller
{
    public function index()
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }
        
        // Récupérer les statistiques
        $stats = $this->getStats();
        
        // Récupérer les SMS récents
        $recentSms = $this->getRecentSms();
        
        // Récupérer le statut des modems
        $modemStatus = $this->getModemStatus();
        
        $this->view('dashboard/index', [
            'title' => 'Tableau de bord',
            'stats' => $stats,
            'recent_sms' => $recentSms,
            'modem_status' => $modemStatus
        ]);
    }
    
    private function getStats()
    {
        $db = Database::getInstance();
        
        // Statistiques générales
        $stats = $db->selectOne("
            SELECT 
                COUNT(*) as total_sms,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_sms,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_sms,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_sms,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_sms
            FROM sms
        ");
        
        // Statistiques du jour
        $todayStats = $db->selectOne("
            SELECT 
                COUNT(*) as today_total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as today_sent
            FROM sms 
            WHERE DATE(created_at) = CURDATE()
        ");
        
        // Calcul du taux de réussite
        $successRate = $stats['sent_sms'] > 0 ? 
            round(($stats['sent_sms'] / $stats['total_sms']) * 100, 1) : 0;
        
        return array_merge($stats, $todayStats, ['success_rate' => $successRate]);
    }
    
    private function getRecentSms($limit = 10)
    {
        $db = Database::getInstance();
        
        return $db->select("
            SELECT s.*, u.username as sender_name
            FROM sms s
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY s.created_at DESC
            LIMIT ?
        ", [$limit]);
    }
    
    private function getModemStatus()
    {
        $modems = Modem::getAll();
        $status = [];
        
        foreach ($modems as $modem) {
            $isOnline = ModemService::checkModemStatus($modem['device_path']);
            $status[] = [
                'id' => $modem['id'],
                'name' => $modem['name'],
                'device_path' => $modem['device_path'],
                'is_online' => $isOnline,
                'last_used' => $modem['last_used']
            ];
        }
        
        return $status;
    }
}