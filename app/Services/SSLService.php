<?php
/**
 * Service de gestion SSL
 */
class SSLService
{
    private $pythonScript;
    private $sslPath = '/etc/ssl/sms-gateway';
    private $nginxSslPath = '/etc/nginx/ssl';
    private $backupPath = '/var/backups/ssl';
    
    public function __construct()
    {
        $this->pythonScript = ROOT_PATH . '/tools/ssl_manager.py';
    }
    
    public function generateSelfSignedCertificate($days = 3650, $commonName = 'localhost')
    {
        $params = [
            'action' => 'generate',
            'days' => $days,
            'common_name' => $commonName,
            'country' => 'MA',
            'state' => 'Casablanca',
            'city' => 'Casablanca',
            'organization' => 'SMS Gateway'
        ];
        
        $result = $this->executePythonScript($params);
        
        if ($result['success']) {
            // Mettre à jour nginx pour HTTPS
            $nginxResult = $this->updateNginxForSSL();
            if (!$nginxResult['success']) {
                Logger::warning('SSL certificate generated but nginx update failed: ' . $nginxResult['error']);
            }
        }
        
        return $result;
    }
    
    public function uploadCustomCertificate($certFile, $keyFile)
    {
        // Valider les fichiers uploadés
        if ($certFile['error'] !== UPLOAD_ERR_OK || $keyFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur lors de l\'upload des fichiers');
        }
        
        // Vérifier les types de fichiers
        $allowedTypes = ['application/x-x509-ca-cert', 'text/plain', 'application/octet-stream'];
        if (!in_array($certFile['type'], $allowedTypes) || !in_array($keyFile['type'], $allowedTypes)) {
            throw new Exception('Type de fichier non autorisé');
        }
        
        // Lire le contenu des fichiers
        $certContent = file_get_contents($certFile['tmp_name']);
        $keyContent = file_get_contents($keyFile['tmp_name']);
        
        if (!$certContent || !$keyContent) {
            throw new Exception('Impossible de lire les fichiers');
        }
        
        // Utiliser le script Python pour l'installation
        $params = [
            'action' => 'upload',
            'cert_file' => $certFile['tmp_name'],
            'key_file' => $keyFile['tmp_name']
        ];
        
        return $this->executePythonScript($params);
    }
    
    public function getCurrentCertificateInfo()
    {
        $result = $this->executePythonScript(['action' => 'get_info']);
        
        if (!$result['success']) {
            throw new Exception('Impossible de récupérer les informations SSL: ' . $result['error']);
        }
        
        return $result['data'];
    }
    
    public function backupCurrentCertificates()
    {
        return $this->executePythonScript(['action' => 'backup']);
    }
    
    public function restoreCertificates($backupDir = null)
    {
        $params = ['action' => 'restore'];
        if ($backupDir) {
            $params['backup_dir'] = $backupDir;
        }
        
        return $this->executePythonScript($params);
    }
    
    public function updateNginxForSSL()
    {
        return $this->executePythonScript(['action' => 'update_nginx']);
    }
    
    public function listBackups()
    {
        $result = $this->executePythonScript(['action' => 'list_backups']);
        
        if (!$result['success']) {
            return [];
        }
        
        return $result['backups'] ?? [];
    }
    
    public function validateCertificateFiles($certPath, $keyPath)
    {
        // Vérification basique des fichiers
        if (!file_exists($certPath) || !file_exists($keyPath)) {
            return false;
        }
        
        $certContent = file_get_contents($certPath);
        $keyContent = file_get_contents($keyPath);
        
        // Vérifier les marqueurs PEM
        return (strpos($certContent, '-----BEGIN CERTIFICATE-----') !== false &&
                strpos($keyContent, '-----BEGIN PRIVATE KEY-----') !== false);
    }
    
    private function executePythonScript($params)
    {
        $command = 'python3 ' . escapeshellarg($this->pythonScript);
        
        // Ajouter les paramètres
        foreach ($params as $key => $value) {
            $command .= ' --' . escapeshellarg($key) . ' ' . escapeshellarg($value);
        }
        
        Logger::debug("Executing SSL command: " . $command);
        
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