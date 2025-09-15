<?php
/**
 * Service de gestion réseau
 */
class NetworkService
{
    private $pythonScript;
    private $netplanPath = '/etc/netplan/';
    private $backupPath;
    
    public function __construct()
    {
        $this->pythonScript = ROOT_PATH . '/tools/network_manager.py';
        $this->backupPath = ROOT_PATH . '/backups/network/';
        
        // Créer le répertoire de sauvegarde si nécessaire
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }
    
    public function getCurrentConfiguration()
    {
        $result = $this->executePythonScript(['action' => 'get_config']);
        
        if (!$result['success']) {
            throw new Exception('Impossible de récupérer la configuration réseau: ' . $result['error']);
        }
        
        return $result['data'];
    }
    
    public function getNetworkInterface()
    {
        $result = $this->executePythonScript(['action' => 'get_interface']);
        
        if (!$result['success']) {
            throw new Exception('Impossible de récupérer l\'interface réseau: ' . $result['error']);
        }
        
        return $result['data'];
    }
    
    public function validateIpConfiguration($config)
    {
        // Valider l'IP primaire
        if (!filter_var($config['primary_ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        
        // Valider la passerelle
        if (!filter_var($config['gateway'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        
        // Valider les DNS
        if (!filter_var($config['dns_primary'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        
        if (!filter_var($config['dns_secondary'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        
        // Valider le masque de sous-réseau
        if (!in_array($config['subnet_mask'], ['8', '16', '24', '25', '26', '27', '28', '29', '30'])) {
            return false;
        }
        
        return true;
    }
    
    public function applyConfiguration($config)
    {
        // Créer une sauvegarde avant modification
        $this->backupConfiguration();
        
        $params = [
            'action' => 'apply_config',
            'primary_ip' => $config['primary_ip'],
            'subnet_mask' => $config['subnet_mask'],
            'gateway' => $config['gateway'],
            'dns_primary' => $config['dns_primary'],
            'dns_secondary' => $config['dns_secondary'],
            'secondary_ip' => $config['secondary_ip']
        ];
        
        return $this->executePythonScript($params);
    }
    
    public function testConnectivity()
    {
        $result = $this->executePythonScript(['action' => 'test_connectivity']);
        
        if (!$result['success']) {
            throw new Exception('Erreur lors du test de connectivité: ' . $result['error']);
        }
        
        return $result['data'];
    }
    
    public function backupConfiguration()
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $this->backupPath . "netplan_backup_{$timestamp}.yaml";
        
        $result = $this->executePythonScript([
            'action' => 'backup_config',
            'backup_file' => $backupFile
        ]);
        
        return $result;
    }
    
    public function restoreConfiguration()
    {
        // Trouver la sauvegarde la plus récente
        $backupFiles = glob($this->backupPath . 'netplan_backup_*.yaml');
        
        if (empty($backupFiles)) {
            return ['success' => false, 'error' => 'Aucune sauvegarde trouvée'];
        }
        
        // Trier par date de modification (plus récent en premier)
        usort($backupFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $latestBackup = $backupFiles[0];
        
        $result = $this->executePythonScript([
            'action' => 'restore_config',
            'backup_file' => $latestBackup
        ]);
        
        return $result;
    }
    
    private function executePythonScript($params)
    {
        $command = 'python3 ' . escapeshellarg($this->pythonScript);
        
        // Ajouter les paramètres
        foreach ($params as $key => $value) {
            $command .= ' --' . escapeshellarg($key) . ' ' . escapeshellarg($value);
        }
        
        Logger::debug("Executing network command: " . $command);
        
        // Exécuter avec timeout
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $pipes);
        
        if (!is_resource($process)) {
            throw new Exception('Impossible de démarrer le processus Python');
        }
        
        // Fermer stdin
        fclose($pipes[0]);
        
        // Lire la sortie
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $returnCode = proc_close($process);
        
        // Parser la sortie JSON
        $result = json_decode($output, true);
        
        if ($result === null) {
            if ($returnCode === 0) {
                return ['success' => true, 'output' => $output];
            } else {
                return ['success' => false, 'error' => $error ?: $output];
            }
        }
        
        return $result;
    }
}