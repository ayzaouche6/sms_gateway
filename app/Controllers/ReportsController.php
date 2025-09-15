<?php
/**
 * Contrôleur des rapports
 */
class ReportsController extends Controller
{
    public function index()
    {
        if (!$this->requireRole(ROLE_SUPERVISOR)) {
            return;
        }
        
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        $userId = $_GET['user_id'] ?? '';
        
        try {
            // Get all users for dropdown (supervisors and admins only)
            $users = [];
            if (Auth::hasRole(ROLE_SUPERVISOR)) {
                $users = User::getAll();
            }
            
            // Statistiques générales
            $stats = $this->getReportStats($dateFrom, $dateTo, $userId);
            
            // Graphique par jour
            $dailyStats = $this->getDailyStats($dateFrom, $dateTo, $userId);
            
            // Top utilisateurs
            $topUsers = $this->getTopUsers($dateFrom, $dateTo);
            
            // Codes d'erreur les plus fréquents
            $errorCodes = $this->getErrorCodes($dateFrom, $dateTo);
            
            $this->view('reports/index', [
                'title' => 'Rapports et statistiques',
                'users' => $users,
                'stats' => $stats,
                'daily_stats' => $dailyStats,
                'top_users' => $topUsers,
                'error_codes' => $errorCodes,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'user_id' => $userId
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error generating reports: ' . $e->getMessage());
            $this->view('reports/index', [
                'title' => 'Rapports et statistiques',
                'users' => [],
                'error' => 'Erreur lors de la génération des rapports'
            ]);
        }
    }
    
    public function export()
    {
        if (!$this->requireRole(ROLE_SUPERVISOR)) {
            return;
        }
        
        $format = $_GET['format'] ?? 'csv';
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        try {
            $db = Database::getInstance();
            $data = $db->select("
                SELECT 
                    s.id,
                    s.recipient,
                    s.message,
                    s.status,
                    s.error_code,
                    s.created_at,
                    s.sent_at,
                    u.username as sender
                FROM sms s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE DATE(s.created_at) BETWEEN ? AND ?
                ORDER BY s.created_at DESC
            ", [$dateFrom, $dateTo]);
            
            if ($format === 'csv') {
                $this->exportToCsv($data, $dateFrom, $dateTo);
            } else {
                $this->json(['success' => false, 'message' => 'Format non supporté']);
            }
            
        } catch (Exception $e) {
            Logger::error('Error exporting report: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur lors de l\'export']);
        }
    }
    
    private function getReportStats($dateFrom, $dateTo, $userId = '')
    {
        $db = Database::getInstance();
        $params = [$dateFrom, $dateTo];
        $userFilter = '';
        
        if ($userId) {
            $userFilter = 'AND user_id = ?';
            $params[] = $userId;
        }
        
        return $db->selectOne("
            SELECT 
                COUNT(*) as total_sms,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_sms,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_sms,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_sms,
                AVG(CASE WHEN sent_at IS NOT NULL THEN 
                    TIMESTAMPDIFF(SECOND, created_at, sent_at) ELSE NULL END) as avg_delivery_time
            FROM sms 
            WHERE DATE(created_at) BETWEEN ? AND ? {$userFilter}
        ", $params);
    }
    
    private function getDailyStats($dateFrom, $dateTo, $userId = '')
    {
        $db = Database::getInstance();
        $params = [$dateFrom, $dateTo];
        $userFilter = '';
        
        if ($userId) {
            $userFilter = 'AND user_id = ?';
            $params[] = $userId;
        }
        
        return $db->select("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM sms 
            WHERE DATE(created_at) BETWEEN ? AND ? {$userFilter}
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at)
        ", $params);
    }
    
    private function getTopUsers($dateFrom, $dateTo)
    {
        if (!Auth::hasRole(ROLE_SUPERVISOR)) {
            return [];
        }
        
        $db = Database::getInstance();
        return $db->select("
            SELECT 
                u.username,
                COUNT(*) as total_sms,
                SUM(CASE WHEN s.status = 'sent' THEN 1 ELSE 0 END) as sent_sms
            FROM sms s
            JOIN users u ON s.user_id = u.id
            WHERE DATE(s.created_at) BETWEEN ? AND ?
            GROUP BY u.id, u.username
            ORDER BY total_sms DESC
            LIMIT 10
        ", [$dateFrom, $dateTo]);
    }
    
    private function getErrorCodes($dateFrom, $dateTo)
    {
        $db = Database::getInstance();
        return $db->select("
            SELECT 
                error_code,
                COUNT(*) as count
            FROM sms 
            WHERE status = 'failed' 
            AND error_code IS NOT NULL 
            AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY error_code
            ORDER BY count DESC
            LIMIT 10
        ", [$dateFrom, $dateTo]);
    }
    
    private function exportToCsv($data, $dateFrom, $dateTo)
    {
        $filename = "sms_report_{$dateFrom}_to_{$dateTo}.csv";
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes CSV
        fputcsv($output, [
            'ID', 'Destinataire', 'Message', 'Statut', 'Code erreur',
            'Créé le', 'Envoyé le', 'Expéditeur'
        ]);
        
        // Données
        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['recipient'],
                substr($row['message'], 0, 100) . (strlen($row['message']) > 100 ? '...' : ''),
                $row['status'],
                $row['error_code'] ?: '',
                $row['created_at'],
                $row['sent_at'] ?: '',
                $row['sender']
            ]);
        }
        
        fclose($output);
        exit;
    }
}