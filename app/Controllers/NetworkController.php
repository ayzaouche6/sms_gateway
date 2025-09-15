<?php
/**
 * Contrôleur de gestion réseau
 */
class NetworkController extends Controller
{
    public function index()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        try {
            $networkService = new NetworkService();
            $currentConfig = $networkService->getCurrentConfiguration();
            $networkInterface = $networkService->getNetworkInterface();
            
            $this->view('network/index', [
                'title' => 'Configuration réseau',
                'current_config' => $currentConfig,
                'network_interface' => $networkInterface
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error loading network configuration: ' . $e->getMessage());
            $this->view('network/index', [
                'title' => 'Configuration réseau',
                'error' => 'Erreur lors du chargement de la configuration réseau: ' . $e->getMessage()
            ]);
        }
    }
    
    public function update()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        if (!$this->validateCSRF()) {
            return;
        }
        
        $validation = Middleware::validateInput([
            'primary_ip' => 'required',
            'subnet_mask' => 'required',
            'gateway' => 'required',
            'dns_primary' => 'required',
            'dns_secondary' => 'required'
        ]);
        
        if ($validation !== true) {
            $this->json(['success' => false, 'errors' => $validation], 400);
            return;
        }
        
        try {
            $networkService = new NetworkService();
            
            $config = [
                'primary_ip' => trim($_POST['primary_ip']),
                'subnet_mask' => trim($_POST['subnet_mask']),
                'gateway' => trim($_POST['gateway']),
                'dns_primary' => trim($_POST['dns_primary']),
                'dns_secondary' => trim($_POST['dns_secondary']),
                'secondary_ip' => '10.0.0.10/24' // IP fixe
            ];
            
            // Valider les adresses IP
            if (!$networkService->validateIpConfiguration($config)) {
                throw new Exception('Configuration IP invalide');
            }
            
            // Appliquer la configuration
            $result = $networkService->applyConfiguration($config);
            
            if ($result['success']) {
                Logger::info("Network configuration updated by user " . Auth::id(), $config);
                
                $this->json([
                    'success' => true,
                    'message' => 'Configuration réseau mise à jour avec succès',
                    'details' => $result['output']
                ]);
            } else {
                throw new Exception($result['error']);
            }
            
        } catch (Exception $e) {
            Logger::error('Error updating network configuration: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function test()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        try {
            $networkService = new NetworkService();
            $testResults = $networkService->testConnectivity();
            
            $this->json([
                'success' => true,
                'results' => $testResults
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error testing network connectivity: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Erreur lors du test de connectivité: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function backup()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        try {
            $networkService = new NetworkService();
            $backup = $networkService->backupConfiguration();
            
            if ($backup['success']) {
                $this->json([
                    'success' => true,
                    'message' => 'Sauvegarde créée avec succès',
                    'backup_file' => $backup['file']
                ]);
            } else {
                throw new Exception($backup['error']);
            }
            
        } catch (Exception $e) {
            Logger::error('Error creating network backup: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function restore()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        if (!$this->validateCSRF()) {
            return;
        }
        
        try {
            $networkService = new NetworkService();
            $result = $networkService->restoreConfiguration();
            
            if ($result['success']) {
                Logger::info("Network configuration restored by user " . Auth::id());
                
                $this->json([
                    'success' => true,
                    'message' => 'Configuration réseau restaurée avec succès'
                ]);
            } else {
                throw new Exception($result['error']);
            }
            
        } catch (Exception $e) {
            Logger::error('Error restoring network configuration: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Erreur lors de la restauration: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function sslInfo()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        try {
            $sslService = new SSLService();
            $sslInfo = $sslService->getCurrentCertificateInfo();
            
            $this->json([
                'success' => true,
                'ssl_info' => $sslInfo
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error getting SSL info: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations SSL'
            ], 500);
        }
    }
    
    public function generateSSL()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        if (!$this->validateCSRF()) {
            return;
        }
        
        try {
            $sslService = new SSLService();
            $result = $sslService->generateSelfSignedCertificate(3650, 'localhost');
            
            if ($result['success']) {
                Logger::info("Self-signed SSL certificate generated by user " . Auth::id());
                
                $this->json([
                    'success' => true,
                    'message' => 'Certificat SSL auto-signé généré avec succès (valide 10 ans)',
                    'info' => $result['info'] ?? null
                ]);
            } else {
                throw new Exception($result['error']);
            }
            
        } catch (Exception $e) {
            Logger::error('Error generating SSL certificate: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function uploadSSL()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        if (!$this->validateCSRF()) {
            return;
        }
        
        try {
            if (!isset($_FILES['cert_file']) || !isset($_FILES['key_file'])) {
                throw new Exception('Fichiers certificat et clé privée requis');
            }
            
            $sslService = new SSLService();
            $result = $sslService->uploadCustomCertificate($_FILES['cert_file'], $_FILES['key_file']);
            
            if ($result['success']) {
                Logger::info("Custom SSL certificate uploaded by user " . Auth::id());
                
                $this->json([
                    'success' => true,
                    'message' => 'Certificat personnalisé installé avec succès',
                    'backup_created' => $result['backup_created'] ?? null
                ]);
            } else {
                throw new Exception($result['error']);
            }
            
        } catch (Exception $e) {
            Logger::error('Error uploading SSL certificate: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function restoreSSL()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        if (!$this->validateCSRF()) {
            return;
        }
        
        try {
            $sslService = new SSLService();
            $result = $sslService->restoreCertificates();
            
            if ($result['success']) {
                Logger::info("SSL certificates restored by user " . Auth::id());
                
                $this->json([
                    'success' => true,
                    'message' => 'Certificats SSL restaurés avec succès'
                ]);
            } else {
                throw new Exception($result['error']);
            }
            
        } catch (Exception $e) {
            Logger::error('Error restoring SSL certificates: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Erreur lors de la restauration: ' . $e->getMessage()
            ], 500);
        }
    }
}