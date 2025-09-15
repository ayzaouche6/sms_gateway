<?php
/**
 * Contrôleur de gestion des langues
 */
class LanguageController extends Controller
{
    public function switch()
    {
        $language = $_GET['lang'] ?? $_POST['lang'] ?? '';
        $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '/dashboard';
        
        if (empty($language)) {
            $this->redirect($redirect);
            return;
        }
        
        $langService = Language::getInstance();
        
        if ($langService->setLanguage($language)) {
            // Si l'utilisateur est connecté, sauvegarder la préférence
            if (Auth::check()) {
                try {
                    $result = User::update(Auth::id(), ['language' => $language]);
                    if ($result) {
                        // Mettre à jour la session pour refléter le changement
                        $_SESSION['user_language'] = $language;
                    }
                    Logger::info("User language updated", [
                        'user_id' => Auth::id(),
                        'language' => $language
                    ]);
                } catch (Exception $e) {
                    Logger::error('Error updating user language: ' . $e->getMessage());
                }
            }
            
            // Rediriger vers la page demandée
            $this->redirect($redirect);
        } else {
            // Langue non supportée, rediriger sans changement
            $this->redirect($redirect);
        }
    }
    
    public function getAvailable()
    {
        $langService = Language::getInstance();
        
        $this->json([
            'success' => true,
            'languages' => $langService->getSupportedLanguages(),
            'current' => $langService->getCurrentLanguage()
        ]);
    }
}