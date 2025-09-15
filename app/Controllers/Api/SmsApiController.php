<?php
/**
 * Contrôleur API pour les SMS
 */
class SmsApiController extends Controller
{
    public function authenticate()
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $this->json(['success' => false, 'message' => 'Email et mot de passe requis'], 400);
            return;
        }
        
        if (Auth::attempt($email, $password)) {
            $user = Auth::user();
            $token = Auth::generateApiToken($user['id']);
            
            $this->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Identifiants invalides'], 401);
        }
    }
    
    public function send()
    {
        if (!Middleware::rateLimiter()) {
            return;
        }
        
        $validation = Middleware::validateInput([
            'recipient' => 'required|phone',
            'message' => 'required|min:1|max:1000'
        ]);
        
        if ($validation !== true) {
            $this->json(['success' => false, 'errors' => $validation], 400);
            return;
        }
        
        try {
            $smsService = new SmsService();
            $smsId = $smsService->queueSms(
                $_POST['recipient'],
                $_POST['message'],
                $_SESSION['api_user_id'] ?? Auth::id(),
                $_POST['scheduled_at'] ?? null
            );
            
            $this->json([
                'success' => true,
                'sms_id' => $smsId,
                'message' => 'SMS ajouté à la file d\'attente'
            ]);
            
        } catch (Exception $e) {
            Logger::error('API SMS send error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function status($smsId)
    {
        try {
            $sms = Sms::find($smsId);
            
            if (!$sms) {
                $this->json(['success' => false, 'message' => 'SMS non trouvé'], 404);
                return;
            }
            
            // Vérifier les permissions
            $userId = $_SESSION['api_user_id'] ?? Auth::id();
            if ($sms['user_id'] != $userId && !Auth::hasRole(ROLE_SUPERVISOR)) {
                $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
                return;
            }
            
            $this->json([
                'success' => true,
                'sms' => [
                    'id' => $sms['id'],
                    'recipient' => $sms['recipient'],
                    'status' => $sms['status'],
                    'created_at' => $sms['created_at'],
                    'sent_at' => $sms['sent_at'],
                    'error_code' => $sms['error_code']
                ]
            ]);
            
        } catch (Exception $e) {
            Logger::error('API SMS status error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
    
    public function list()
    {
        try {
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $search = $_GET['search'] ?? $_GET['q'] ?? '';
            $status = $_GET['status'] ?? '';
            
            $smsService = new SmsService();
            $userId = $_SESSION['api_user_id'] ?? Auth::id();
            
            // Les superviseurs voient tous les SMS, les autres seulement les leurs
            $userFilter = Auth::hasRole(ROLE_SUPERVISOR) ? null : $userId;
            
            $result = $smsService->getSmsQueue($search, $status, $limit, $offset, $userFilter);
            
            $this->json([
                'success' => true,
                'sms' => $result['sms'],
                'total' => $result['total'],
                'page' => $page,
                'per_page' => $limit,
                'total_pages' => ceil($result['total'] / $limit)
            ]);
            
        } catch (Exception $e) {
            Logger::error('API SMS list error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
    
    
    public function retry()
    {
        $smsId = $_POST['id'] ?? 0;
        
        try {
            $sms = Sms::find($smsId);
            
            if (!$sms) {
                $this->json(['success' => false, 'message' => 'SMS non trouvé'], 404);
                return;
            }
            
            if ($sms['status'] !== 'failed') {
                $this->json(['success' => false, 'message' => 'Seuls les SMS échoués peuvent être relancés'], 400);
                return;
            }
            
            // Vérifier les permissions
            $userId = $_SESSION['api_user_id'] ?? Auth::id();
            if ($sms['user_id'] != $userId && !Auth::hasRole(ROLE_SUPERVISOR)) {
                $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
                return;
            }
            
            $smsService = new SmsService();
            $smsService->retrySms($smsId);
            
            $this->json(['success' => true, 'message' => 'SMS remis en file d\'attente']);
            
        } catch (Exception $e) {
            Logger::error('API SMS retry error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function delete()
    {
        if (!$this->requireRole(ROLE_SUPERVISOR)) {
            return;
        }
        
        $smsId = $_POST['id'] ?? $_REQUEST['id'] ?? 0;
        
        try {
            $deleted = Sms::delete($smsId);
            
            if ($deleted) {
                $this->json(['success' => true, 'message' => 'SMS supprimé']);
            } else {
                $this->json(['success' => false, 'message' => 'SMS non trouvé'], 404);
            }
            
        } catch (Exception $e) {
            Logger::error('API SMS delete error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur lors de la suppression'], 500);
        }
    }
    
    public function bulk()
    {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'Fichier CSV requis'], 400);
            return;
        }
        
        $message = $_POST['message'] ?? '';
        if (empty($message)) {
            $this->json(['success' => false, 'message' => 'Message requis'], 400);
            return;
        }
        
        try {
            $smsService = new SmsService();
            $result = $smsService->processBulkSms(
                $_FILES['csv_file'],
                $message,
                $_SESSION['api_user_id'] ?? Auth::id()
            );
            
            $this->json([
                'success' => true,
                'count' => $result['success'],
                'errors' => $result['errors'],
                'message' => "Traitement terminé: {$result['success']} SMS ajoutés"
            ]);
            
        } catch (Exception $e) {
            Logger::error('API bulk SMS error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function received()
    {
        try {
            $search = $_GET['search'] ?? '';
            $limit = min(50, max(10, intval($_GET['limit'] ?? 20)));
            $offset = max(0, intval($_GET['offset'] ?? 0));
            
            if ($search) {
                $sms = ReceivedSms::search($search, $limit, $offset);
                $total = ReceivedSms::count($search);
            } else {
                $sms = ReceivedSms::getAll($limit, $offset);
                $total = ReceivedSms::count();
            }
            
            $this->json([
                'success' => true,
                'sms' => $sms,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            
        } catch (Exception $e) {
            Logger::error('API received SMS error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
}