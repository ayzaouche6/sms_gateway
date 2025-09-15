<?php
/**
 * Autoloader personnalisé pour l'application
 */
class Autoload
{
    private static $registered = false;
    
    public static function register()
    {
        if (self::$registered) {
            return;
        }
        
        spl_autoload_register([__CLASS__, 'load']);
        self::$registered = true;
    }
    
    public static function load($className)
    {
        // Nettoyer le nom de classe
        $className = ltrim($className, '\\');
        
        // Chemins de base pour les classes
        $basePaths = [
            APP_PATH . '/Core/',
            APP_PATH . '/Controllers/',
            APP_PATH . '/Controllers/Api/',
            APP_PATH . '/Models/',
            APP_PATH . '/Services/'
        ];
        
        // Essayer de charger la classe
        foreach ($basePaths as $basePath) {
            $file = $basePath . $className . '.php';
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
        
        // Essayer avec des sous-répertoires
        $parts = explode('\\', $className);
        if (count($parts) > 1) {
            $subPath = implode('/', $parts) . '.php';
            foreach ($basePaths as $basePath) {
                $file = $basePath . $subPath;
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
        }
        
        return false;
    }
}