<?php
/**
 * Système de logging
 */
class Logger
{
    private static $initialized = false;
    
    public static function init()
    {
        if (self::$initialized) {
            return;
        }
        
        // Créer les répertoires de logs si nécessaires
        $dirs = [
            LOGS_PATH,
            LOGS_PATH . '/error',
            LOGS_PATH . '/info',
            LOGS_PATH . '/debug'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        self::$initialized = true;
    }
    
    public static function error($message, $context = [])
    {
        self::log('ERROR', $message, $context);
    }
    
    public static function warning($message, $context = [])
    {
        self::log('WARNING', $message, $context);
    }
    
    public static function info($message, $context = [])
    {
        self::log('INFO', $message, $context);
    }
    
    public static function debug($message, $context = [])
    {
        if (LOG_LEVEL === 'DEBUG' || DEBUG) {
            self::log('DEBUG', $message, $context);
        }
    }
    
    private static function log($level, $message, $context = [])
    {
        if (!self::$initialized) {
            self::init();
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
        
        // Log principal
        $mainLogFile = LOGS_PATH . '/app.log';
        self::writeToFile($mainLogFile, $logEntry);
        
        // Log par niveau
        $levelDir = strtolower($level);
        if (in_array($levelDir, ['error', 'info', 'debug'])) {
            $levelLogFile = LOGS_PATH . "/{$levelDir}/" . date('Y-m-d') . '.log';
            self::writeToFile($levelLogFile, $logEntry);
        }
    }
    
    private static function writeToFile($file, $content)
    {
        // Rotation des logs si trop volumineux
        if (file_exists($file) && filesize($file) > LOG_MAX_SIZE) {
            self::rotateLog($file);
        }
        
        file_put_contents($file, $content, FILE_APPEND | LOCK_EX);
    }
    
    private static function rotateLog($file)
    {
        for ($i = LOG_MAX_FILES - 1; $i > 0; $i--) {
            $oldFile = $file . '.' . $i;
            $newFile = $file . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
        
        if (file_exists($file)) {
            rename($file, $file . '.1');
        }
    }
    
    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        $message = "PHP Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}";
        self::error($message);
        
        // Ne pas arrêter l'exécution pour les warnings et notices
        return !in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
    }
    
    public static function handleException($exception)
    {
        $message = "Uncaught exception: " . $exception->getMessage() . 
                   " in " . $exception->getFile() . 
                   " on line " . $exception->getLine();
        self::error($message, ['trace' => $exception->getTraceAsString()]);
    }
    
    public static function handleShutdown()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $message = "Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}";
            self::error($message);
        }
    }
}
</botlAction>