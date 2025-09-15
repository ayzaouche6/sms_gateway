#!/usr/bin/env php
<?php
/**
 * Script d'import de contacts depuis un fichier CSV
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
 * Classe d'import de contacts
 */
class ContactImporter
{
    private $db;
    private $verbose = false;
    
    public function __construct($verbose = false)
    {
        $this->db = Database::getInstance();
        $this->verbose = $verbose;
    }
    
    public function importFromCsv($filePath, $userId, $message, $dryRun = false)
    {
        if (!file_exists($filePath)) {
            throw new Exception("Fichier non trouvé: {$filePath}");
        }
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Impossible d'ouvrir le fichier: {$filePath}");
        }
        
        $contacts = [];
        $lineNumber = 0;
        $errors = [];
        
        // Lire le fichier ligne par ligne
        while (($data = fgetcsv($handle)) !== FALSE) {
            $lineNumber++;
            
            if (empty($data[0])) {
                continue; // Ignorer les lignes vides
            }
            
            $phone = $this->cleanPhoneNumber($data[0]);
            
            // Vérifier si c'est probablement un en-tête
            if ($lineNumber === 1 && !$this->isValidPhoneNumber($phone)) {
                if ($this->verbose) {
                    echo "Ligne 1 ignorée (probablement un en-tête): {$data[0]}\n";
                }
                continue;
            }
            
            if (!$this->isValidPhoneNumber($phone)) {
                $errors[] = "Ligne {$lineNumber}: Numéro invalide '{$data[0]}'";
                continue;
            }
            
            // Éviter les doublons
            if (!in_array($phone, $contacts)) {
                $contacts[] = $phone;
            }
        }
        
        fclose($handle);
        
        if ($this->verbose) {
            echo "Fichier analysé: {$lineNumber} lignes, " . count($contacts) . " contacts valides\n";
            if (!empty($errors)) {
                echo "Erreurs trouvées:\n";
                foreach ($errors as $error) {
                    echo "  - {$error}\n";
                }
            }
        }
        
        if (empty($contacts)) {
            throw new Exception("Aucun contact valide trouvé dans le fichier");
        }
        
        // Créer les SMS
        $created = 0;
        $smsService = new SmsService();
        
        if ($dryRun) {
            echo "MODE SIMULATION - Aucun SMS ne sera créé\n";
            echo "Contacts qui seraient traités:\n";
            foreach ($contacts as $contact) {
                echo "  - {$contact}\n";
            }
            return ['created' => count($contacts), 'errors' => count($errors)];
        }
        
        foreach ($contacts as $contact) {
            try {
                $smsId = $smsService->queueSms($contact, $message, $userId);
                $created++;
                
                if ($this->verbose) {
                    echo "SMS créé pour {$contact} (ID: {$smsId})\n";
                }
                
            } catch (Exception $e) {
                $errors[] = "Erreur pour {$contact}: " . $e->getMessage();
                if ($this->verbose) {
                    echo "Erreur pour {$contact}: " . $e->getMessage() . "\n";
                }
            }
        }
        
        return ['created' => $created, 'errors' => count($errors)];
    }
    
    private function cleanPhoneNumber($phone)
    {
        // Supprimer tous les caractères non numériques sauf +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Ajouter l'indicatif pays si manquant
        if (substr($phone, 0, 1) === '0') {
            $phone = '+33' . substr($phone, 1);
        } elseif (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
    
    private function isValidPhoneNumber($phone)
    {
        // Vérification basique d'un numéro international
        return preg_match('/^\+[1-9]\d{6,14}$/', $phone);
    }
}

// Fonction principale
function main()
{
    $options = getopt('f:u:m:', ['file:', 'user:', 'message:', 'verbose', 'dry-run', 'help']);
    
    if (isset($options['help'])) {
        echo "Usage: php ImportContacts.php [options]\n";
        echo "Options:\n";
        echo "  -f, --file=FILE     Fichier CSV à importer (requis)\n";
        echo "  -u, --user=ID       ID de l'utilisateur (requis)\n";
        echo "  -m, --message=MSG   Message à envoyer (requis)\n";
        echo "  --verbose           Mode verbeux\n";
        echo "  --dry-run           Simulation sans création de SMS\n";
        echo "  --help              Afficher cette aide\n";
        echo "\n";
        echo "Format du fichier CSV:\n";
        echo "  - Une colonne avec les numéros de téléphone\n";
        echo "  - Format international recommandé (+33...)\n";
        echo "  - La première ligne peut être un en-tête\n";
        exit(0);
    }
    
    // Vérifier les arguments obligatoires
    $file = $options['f'] ?? $options['file'] ?? null;
    $userId = $options['u'] ?? $options['user'] ?? null;
    $message = $options['m'] ?? $options['message'] ?? null;
    
    if (!$file || !$userId || !$message) {
        echo "Erreur: Les options --file, --user et --message sont obligatoires\n";
        echo "Utilisez --help pour plus d'informations\n";
        exit(1);
    }
    
    $verbose = isset($options['verbose']);
    $dryRun = isset($options['dry-run']);
    
    try {
        // Vérifier que l'utilisateur existe
        $user = User::find($userId);
        if (!$user) {
            throw new Exception("Utilisateur non trouvé: {$userId}");
        }
        
        if ($verbose) {
            echo "Import de contacts depuis: {$file}\n";
            echo "Utilisateur: {$user['username']} (ID: {$userId})\n";
            echo "Message: " . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '') . "\n";
            echo "Mode simulation: " . ($dryRun ? 'OUI' : 'NON') . "\n";
            echo "\n";
        }
        
        $importer = new ContactImporter($verbose);
        $result = $importer->importFromCsv($file, $userId, $message, $dryRun);
        
        echo "Import terminé:\n";
        echo "  - SMS créés: {$result['created']}\n";
        echo "  - Erreurs: {$result['errors']}\n";
        
        if (!$dryRun && $result['created'] > 0) {
            echo "\nLes SMS ont été ajoutés à la file d'attente.\n";
            echo "Utilisez le script send_queue.php pour les traiter.\n";
        }
        
    } catch (Exception $e) {
        echo "ERREUR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Exécuter le script
main();