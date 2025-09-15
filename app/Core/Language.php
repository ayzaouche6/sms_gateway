<?php
/**
 * Gestionnaire de langues et traductions
 */
class Language
{
    private static $instance = null;
    private $currentLanguage = 'fr';
    private $translations = [];
    private $supportedLanguages = [
        'fr' => ['name' => 'Français', 'flag' => '🇫🇷', 'rtl' => false],
        'en' => ['name' => 'English', 'flag' => '🇺🇸', 'rtl' => false],
        'ar' => ['name' => 'العربية', 'flag' => '🇸🇦', 'rtl' => true]
    ];
    
    private function __construct()
    {
        $this->loadLanguage($this->detectLanguage());
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function detectLanguage()
    {
        // 1. Vérifier la session (choix utilisateur temporaire)
        if (isset($_SESSION['temp_language']) && $this->isSupported($_SESSION['temp_language'])) {
            return $_SESSION['temp_language'];
        }
        
        // 2. Vérifier les préférences utilisateur
        if (Auth::check()) {
            $user = Auth::user();
            if ($user && !empty($user['language']) && $this->isSupported($user['language'])) {
                return $user['language'];
            }
        }
        
        // 3. Vérifier le paramètre GET
        if (isset($_GET['lang']) && $this->isSupported($_GET['lang'])) {
            $_SESSION['temp_language'] = $_GET['lang'];
            return $_GET['lang'];
        }
        
        // 4. Vérifier l'en-tête Accept-Language du navigateur
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($browserLangs as $lang) {
                $lang = strtolower(substr(trim($lang), 0, 2));
                if ($this->isSupported($lang)) {
                    return $lang;
                }
            }
        }
        
        // 5. Langue par défaut
        return 'fr';
    }
    
    public function setLanguage($language)
    {
        if ($this->isSupported($language)) {
            $this->currentLanguage = $language;
            $_SESSION['temp_language'] = $language;
            $this->loadLanguage($language);
            return true;
        }
        return false;
    }
    
    public function getCurrentLanguage()
    {
        return $this->currentLanguage;
    }
    
    public function getSupportedLanguages()
    {
        return $this->supportedLanguages;
    }
    
    public function isRTL()
    {
        return $this->supportedLanguages[$this->currentLanguage]['rtl'] ?? false;
    }
    
    public function getLanguageInfo($lang = null)
    {
        $lang = $lang ?: $this->currentLanguage;
        return $this->supportedLanguages[$lang] ?? null;
    }
    
    private function isSupported($language)
    {
        return isset($this->supportedLanguages[$language]);
    }
    
    private function loadLanguage($language)
    {
        $this->currentLanguage = $language;
        
        $langFile = APP_PATH . "/Languages/{$language}.php";
        if (file_exists($langFile)) {
            $this->translations = include $langFile;
        } else {
            // Fallback vers le français
            $fallbackFile = APP_PATH . "/Languages/fr.php";
            if (file_exists($fallbackFile)) {
                $this->translations = include $fallbackFile;
            }
        }
    }
    
    public function translate($key, $params = [])
    {
        $keys = explode('.', $key);
        $value = $this->translations;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $key; // Retourner la clé si traduction non trouvée
            }
        }
        
        // Remplacer les paramètres
        if (!empty($params) && is_string($value)) {
            foreach ($params as $param => $replacement) {
                $value = str_replace(':' . $param, $replacement, $value);
            }
        }
        
        return $value;
    }
}

// Fonction globale pour les traductions
function __($key, $params = [])
{
    return Language::getInstance()->translate($key, $params);
}

// Fonction pour obtenir la langue courante
function currentLang()
{
    return Language::getInstance()->getCurrentLanguage();
}

// Fonction pour vérifier si RTL
function isRTL()
{
    return Language::getInstance()->isRTL();
}