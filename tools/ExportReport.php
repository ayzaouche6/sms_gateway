#!/usr/bin/env php
<?php
/**
 * Script d'export de rapports SMS
 */

// Vérifier que le script est exécuté en CLI
if (php_sapi_name() !== 'cli') {
    die('Ce script doit être exécuté en ligne de commande');
}

// Définir les chemins
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Charger le bootstrap de l'application
require_once APP_PATH . '/bootstrap.php';

/**
 * Classe d'export de rapports
 */
class ReportExporter
{
    private $db;
    private $verbose = false;
    
    public function __construct($verbose = false)
    {
        $this->db = Database::getInstance();
        $this->verbose = $verbose;
    }
    
    public function exportSmsReport($dateFrom, $dateTo, $format = 'csv', $outputFile = null, $userId = null)
    {
        // Valider les dates
        if (!$this->isValidDate($dateFrom) || !$this->isValidDate($dateTo)) {
            throw new Exception("Format de date invalide. Utilisez YYYY-MM-DD");
        }
        
        if ($dateFrom > $dateTo) {
            throw new Exception("La date de début doit être antérieure à la date de fin");
        }
        
        // Construire la requête
        $sql = "
            SELECT 
                s.id,
                s.recipient,
                s.message,
                s.status,
                s.priority,
                s.created_at,
                s.scheduled_at,
                s.sent_at,
                s.failed_at,
                s.retry_count,
                s.error_code,
                s.error_message,
                s.is_unicode,
                s.parts_count,
                u.username as sender,
                u.email as sender_email,
                m.name as modem_name,
                m.device_path as modem_device
            FROM sms s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN modems m ON s.modem_id = m.id
            WHERE DATE(s.created_at) BETWEEN ? AND ?
        ";
        
        $params = [$dateFrom, $dateTo];
        
        if ($userId) {
            $sql .= " AND s.user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        if ($this->verbose) {
            echo "Récupération des données de {$dateFrom} à {$dateTo}\n";
            if ($userId) {
                echo "Filtré pour l'utilisateur ID: {$userId}\n";
            }
        }
        
        $data = $this->db->select($sql, $params);
        
        if (empty($data)) {
            throw new Exception("Aucune donnée trouvée pour la période spécifiée");
        }
        
        if ($this->verbose) {
            echo "Données récupérées: " . count($data) . " enregistrements\n";
        }
        
        // Générer le nom de fichier si non spécifié
        if (!$outputFile) {
            $userSuffix = $userId ? "_user{$userId}" : "";
            $outputFile = "sms_report_{$dateFrom}_to_{$dateTo}{$userSuffix}.{$format}";
        }
        
        // Exporter selon le format
        switch (strtolower($format)) {
            case 'csv':
                $this->exportToCsv($data, $outputFile);
                break;
            case 'json':
                $this->exportToJson($data, $outputFile);
                break;
            case 'xlsx':
                $this->exportToExcel($data, $outputFile);
                break;
            default:
                throw new Exception("Format non supporté: {$format}");
        }
        
        return $outputFile;
    }
    
    public function exportStatsReport($dateFrom, $dateTo, $format = 'csv', $outputFile = null)
    {
        if (!$this->isValidDate($dateFrom) || !$this->isValidDate($dateTo)) {
            throw new Exception("Format de date invalide. Utilisez YYYY-MM-DD");
        }
        
        // Statistiques par jour
        $dailyStats = $this->db->select("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                ROUND(AVG(CASE 
                    WHEN sent_at IS NOT NULL AND created_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(SECOND, created_at, sent_at) 
                    END), 2) as avg_delivery_time_seconds
            FROM sms 
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date
        ", [$dateFrom, $dateTo]);
        
        // Statistiques par utilisateur
        $userStats = $this->db->select("
            SELECT 
                u.username,
                u.email,
                COUNT(s.id) as total_sms,
                COUNT(CASE WHEN s.status = 'sent' THEN 1 END) as sent_sms,
                COUNT(CASE WHEN s.status = 'failed' THEN 1 END) as failed_sms,
                ROUND((COUNT(CASE WHEN s.status = 'sent' THEN 1 END) * 100.0) / COUNT(s.id), 2) as success_rate
            FROM users u
            LEFT JOIN sms s ON u.id = s.user_id AND DATE(s.created_at) BETWEEN ? AND ?
            GROUP BY u.id, u.username, u.email
            HAVING total_sms > 0
            ORDER BY total_sms DESC
        ", [$dateFrom, $dateTo]);
        
        // Codes d'erreur
        $errorStats = $this->db->select("
            SELECT 
                error_code,
                COUNT(*) as count,
                ROUND((COUNT(*) * 100.0) / (SELECT COUNT(*) FROM sms WHERE status = 'failed' AND DATE(created_at) BETWEEN ? AND ?), 2) as percentage
            FROM sms 
            WHERE status = 'failed' 
            AND error_code IS NOT NULL 
            AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY error_code
            ORDER BY count DESC
        ", [$dateFrom, $dateTo, $dateFrom, $dateTo]);
        
        $statsData = [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'daily_stats' => $dailyStats,
            'user_stats' => $userStats,
            'error_stats' => $errorStats
        ];
        
        if (!$outputFile) {
            $outputFile = "sms_stats_{$dateFrom}_to_{$dateTo}.{$format}";
        }
        
        switch (strtolower($format)) {
            case 'json':
                $this->exportStatsToJson($statsData, $outputFile);
                break;
            case 'csv':
                $this->exportStatsToCsv($statsData, $outputFile);
                break;
            default:
                throw new Exception("Format non supporté pour les statistiques: {$format}");
        }
        
        return $outputFile;
    }
    
    private function exportToCsv($data, $outputFile)
    {
        $handle = fopen($outputFile, 'w');
        if (!$handle) {
            throw new Exception("Impossible de créer le fichier: {$outputFile}");
        }
        
        // En-têtes CSV
        $headers = [
            'ID', 'Destinataire', 'Message', 'Statut', 'Priorité',
            'Créé le', 'Programmé le', 'Envoyé le', 'Échoué le',
            'Tentatives', 'Code erreur', 'Message erreur',
            'Unicode', 'Parties', 'Expéditeur', 'Email expéditeur',
            'Modem', 'Périphérique modem'
        ];
        
        fputcsv($handle, $headers);
        
        // Données
        foreach ($data as $row) {
            $csvRow = [
                $row['id'],
                $row['recipient'],
                substr($row['message'], 0, 100) . (strlen($row['message']) > 100 ? '...' : ''),
                $row['status'],
                $row['priority'],
                $row['created_at'],
                $row['scheduled_at'] ?: '',
                $row['sent_at'] ?: '',
                $row['failed_at'] ?: '',
                $row['retry_count'],
                $row['error_code'] ?: '',
                $row['error_message'] ?: '',
                $row['is_unicode'] ? 'Oui' : 'Non',
                $row['parts_count'],
                $row['sender'] ?: '',
                $row['sender_email'] ?: '',
                $row['modem_name'] ?: '',
                $row['modem_device'] ?: ''
            ];
            
            fputcsv($handle, $csvRow);
        }
        
        fclose($handle);
        
        if ($this->verbose) {
            echo "Export CSV créé: {$outputFile}\n";
        }
    }
    
    private function exportToJson($data, $outputFile)
    {
        $jsonData = [
            'export_date' => date('Y-m-d H:i:s'),
            'total_records' => count($data),
            'data' => $data
        ];
        
        $json = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($outputFile, $json) === false) {
            throw new Exception("Impossible de créer le fichier: {$outputFile}");
        }
        
        if ($this->verbose) {
            echo "Export JSON créé: {$outputFile}\n";
        }
    }
    
    private function exportStatsToJson($statsData, $outputFile)
    {
        $jsonData = [
            'export_date' => date('Y-m-d H:i:s'),
            'export_type' => 'statistics',
            'data' => $statsData
        ];
        
        $json = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($outputFile, $json) === false) {
            throw new Exception("Impossible de créer le fichier: {$outputFile}");
        }
        
        if ($this->verbose) {
            echo "Export statistiques JSON créé: {$outputFile}\n";
        }
    }
    
    private function exportStatsToCsv($statsData, $outputFile)
    {
        $handle = fopen($outputFile, 'w');
        if (!$handle) {
            throw new Exception("Impossible de créer le fichier: {$outputFile}");
        }
        
        // Statistiques journalières
        fputcsv($handle, ['=== STATISTIQUES JOURNALIERES ===']);
        fputcsv($handle, ['Date', 'Total', 'Envoyés', 'Échoués', 'En attente', 'Temps moyen (s)']);
        
        foreach ($statsData['daily_stats'] as $day) {
            fputcsv($handle, [
                $day['date'],
                $day['total'],
                $day['sent'],
                $day['failed'],
                $day['pending'],
                $day['avg_delivery_time_seconds'] ?: ''
            ]);
        }
        
        // Ligne vide
        fputcsv($handle, []);
        
        // Statistiques utilisateurs
        fputcsv($handle, ['=== STATISTIQUES UTILISATEURS ===']);
        fputcsv($handle, ['Utilisateur', 'Email', 'Total SMS', 'Envoyés', 'Échoués', 'Taux succès (%)']);
        
        foreach ($statsData['user_stats'] as $user) {
            fputcsv($handle, [
                $user['username'],
                $user['email'],
                $user['total_sms'],
                $user['sent_sms'],
                $user['failed_sms'],
                $user['success_rate']
            ]);
        }
        
        // Ligne vide
        fputcsv($handle, []);
        
        // Codes d'erreur
        fputcsv($handle, ['=== CODES D\'ERREUR ===']);
        fputcsv($handle, ['Code erreur', 'Occurrences', 'Pourcentage (%)']);
        
        foreach ($statsData['error_stats'] as $error) {
            fputcsv($handle, [
                $error['error_code'],
                $error['count'],
                $error['percentage']
            ]);
        }
        
        fclose($handle);
        
        if ($this->verbose) {
            echo "Export statistiques CSV créé: {$outputFile}\n";
        }
    }
    
    private function isValidDate($date)
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

// Fonction principale
function main()
{
    $options = getopt('', [
        'type:', 'from:', 'to:', 'format:', 'output:', 'user:', 
        'verbose', 'help'
    ]);
    
    if (isset($options['help'])) {
        echo "Usage: php ExportReport.php [options]\n";
        echo "Options:\n";
        echo "  --type=TYPE         Type de rapport (sms|stats) [défaut: sms]\n";
        echo "  --from=DATE         Date de début (YYYY-MM-DD) [requis]\n";
        echo "  --to=DATE           Date de fin (YYYY-MM-DD) [requis]\n";
        echo "  --format=FORMAT     Format d'export (csv|json|xlsx) [défaut: csv]\n";
        echo "  --output=FILE       Fichier de sortie [auto-généré]\n";
        echo "  --user=ID           Filtrer par utilisateur (ID)\n";
        echo "  --verbose           Mode verbeux\n";
        echo "  --help              Afficher cette aide\n";
        echo "\n";
        echo "Exemples:\n";
        echo "  php ExportReport.php --from=2024-01-01 --to=2024-01-31\n";
        echo "  php ExportReport.php --type=stats --from=2024-01-01 --to=2024-01-31 --format=json\n";
        echo "  php ExportReport.php --from=2024-01-01 --to=2024-01-31 --user=1 --output=rapport.csv\n";
        exit(0);
    }
    
    // Vérifier les arguments obligatoires
    $dateFrom = $options['from'] ?? null;
    $dateTo = $options['to'] ?? null;
    
    if (!$dateFrom || !$dateTo) {
        echo "Erreur: Les options --from et --to sont obligatoires\n";
        echo "Utilisez --help pour plus d'informations\n";
        exit(1);
    }
    
    $type = $options['type'] ?? 'sms';
    $format = $options['format'] ?? 'csv';
    $outputFile = $options['output'] ?? null;
    $userId = $options['user'] ?? null;
    $verbose = isset($options['verbose']);
    
    try {
        $exporter = new ReportExporter($verbose);
        
        if ($type === 'stats') {
            $outputFile = $exporter->exportStatsReport($dateFrom, $dateTo, $format, $outputFile);
        } else {
            $outputFile = $exporter->exportSmsReport($dateFrom, $dateTo, $format, $outputFile, $userId);
        }
        
        echo "Export terminé avec succès: {$outputFile}\n";
        
        if (file_exists($outputFile)) {
            $size = filesize($outputFile);
            echo "Taille du fichier: " . number_format($size) . " octets\n";
        }
        
    } catch (Exception $e) {
        echo "ERREUR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Exécuter le script
main();