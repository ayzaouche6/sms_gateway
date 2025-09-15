<?php
/**
 * Contrôleur SMS
 */
class SmsController extends Controller
{
    public function index()
    {
        $this->redirect('/sms/queue');
    }
    
    public function sendForm()
    {
        $this->view('sms/send', [
            'title' => 'Envoyer un SMS'
        ]);
    }
    
    public function send()
    {
        if (!$this->validateCSRF()) {
            return;
        }
        
        $validation = Middleware::validateInput([
            'recipient' => 'required|phone',
            'message' => 'required|min:1|max:1000'
        ]);
        
        if ($validation !== true) {
            $this->view('sms/send', [
                'title' => 'Envoyer un SMS',
                'errors' => $validation,
                'recipient' => $_POST['recipient'] ?? '',
                'message' => $_POST['message'] ?? ''
            ]);
            return;
        }
        
        try {
            $smsService = new SmsService();
            $smsId = $smsService->queueSms(
                $_POST['recipient'],
                $_POST['message'],
                Auth::id(),
                $_POST['scheduled_at'] ?? null
            );
            
            $this->view('sms/send', [
                'title' => 'Envoyer un SMS',
                'success' => 'SMS ajouté à la file d\'attente avec l\'ID: ' . $smsId
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error sending SMS: ' . $e->getMessage());
            $this->view('sms/send', [
                'title' => 'Envoyer un SMS',
                'error' => 'Erreur lors de l\'envoi: ' . $e->getMessage(),
                'recipient' => $_POST['recipient'],
                'message' => $_POST['message']
            ]);
        }
    }
    
    public function bulkForm()
    {
        $this->view('sms/bulk', [
            'title' => 'Envoi en masse'
        ]);
    }
    
    public function bulk()
    {
        if (!$this->validateCSRF()) {
            return;
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $this->view('sms/bulk', [
                'title' => 'Envoi en masse',
                'error' => 'Veuillez sélectionner un fichier CSV valide'
            ]);
            return;
        }
        
        try {
            $smsService = new SmsService();
            $result = $smsService->processBulkSms(
                $_FILES['csv_file'],
                $_POST['message'],
                Auth::id()
            );
            
            $this->view('sms/bulk', [
                'title' => 'Envoi en masse',
                'success' => "Traitement terminé: {$result['success']} SMS ajoutés, {$result['errors']} erreurs"
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error processing bulk SMS: ' . $e->getMessage());
            $this->view('sms/bulk', [
                'title' => 'Envoi en masse',
                'error' => 'Erreur lors du traitement: ' . $e->getMessage()
            ]);
        }
    }
    
    public function queue()
    {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        
        try {
            $smsService = new SmsService();
            $result = $smsService->getSmsQueue($search, $status, $limit, $offset);
            
            $this->view('sms/queue', [
                'title' => 'File d\'attente SMS',
                'sms_list' => $result['sms'],
                'total' => $result['total'],
                'page' => $page,
                'total_pages' => ceil($result['total'] / $limit),
                'search' => $search,
                'status' => $status
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error loading SMS queue: ' . $e->getMessage());
            $this->view('sms/queue', [
                'title' => 'File d\'attente SMS',
                'error' => 'Erreur lors du chargement de la file d\'attente'
            ]);
        }
    }
}