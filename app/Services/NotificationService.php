<?php
/**
 * Service de gestion des notifications
 */
class NotificationService
{
    public static function createSmsFailedNotification($smsId, $recipient, $errorMessage)
    {
        $notificationId = Notification::createSmsFailedNotification($smsId, $recipient, $errorMessage);
        
        // Traiter immédiatement si critique
        self::processHighPriorityNotification($notificationId);
        
        return $notificationId;
    }
    
    public static function createQueueBlockedNotification($queueSize)
    {
        return Notification::createQueueBlockedNotification($queueSize);
    }
    
    public static function createModemOfflineNotification($modemName, $devicePath)
    {
        return Notification::createModemOfflineNotification($modemName, $devicePath);
    }
    
    public static function processNotifications()
    {
        $notifications = Notification::getPending(20);
        
        foreach ($notifications as $notification) {
            self::processNotification($notification);
        }
    }
    
    private static function processNotification($notification)
    {
        try {
            $sent = false;
            
            // Envoyer par email si configuré
            if (!empty(SMTP_HOST)) {
                $sent = self::sendEmailNotification($notification) || $sent;
            }
            
            // Envoyer par webhook si configuré
            if (!empty(WEBHOOK_URL)) {
                $sent = self::sendWebhookNotification($notification) || $sent;
            }
            
            if ($sent) {
                Notification::markAsProcessed($notification['id']);
                Logger::info("Notification processed", ['notification_id' => $notification['id']]);
            } else {
                Logger::warning("No notification method configured", ['notification_id' => $notification['id']]);
                Notification::markAsProcessed($notification['id']); // Marquer comme traité quand même
            }
            
        } catch (Exception $e) {
            Notification::markAsFailed($notification['id'], $e->getMessage());
            Logger::error("Notification processing failed", [
                'notification_id' => $notification['id'],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private static function processHighPriorityNotification($notificationId)
    {
        $notification = Notification::find($notificationId);
        if ($notification && $notification['priority'] === 'high') {
            self::processNotification($notification);
        }
    }
    
    private static function sendEmailNotification($notification)
    {
        if (empty(SMTP_HOST) || empty(SMTP_FROM)) {
            return false;
        }
        
        try {
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . SMTP_FROM,
                'Reply-To: ' . SMTP_FROM,
                'X-Mailer: SMS Gateway',
                'Priority: ' . ($notification['priority'] === 'high' ? '1' : '3')
            ];
            
            $subject = '[SMS Gateway] ' . $notification['title'];
            $body = self::formatEmailBody($notification);
            
            // Utiliser une bibliothèque SMTP plus robuste en production
            // Pour simplicité, utilisation de mail() basique ici
            $result = mail(
                SMTP_FROM, // En production, utiliser une liste de destinataires
                $subject,
                $body,
                implode("\r\n", $headers)
            );
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error("Email notification failed", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    private static function sendWebhookNotification($notification)
    {
        if (empty(WEBHOOK_URL)) {
            return false;
        }
        
        try {
            $payload = [
                'type' => $notification['type'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'priority' => $notification['priority'],
                'timestamp' => $notification['created_at'],
                'data' => json_decode($notification['data'], true)
            ];
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/json',
                        'User-Agent: SMS Gateway Webhook'
                    ],
                    'content' => json_encode($payload),
                    'timeout' => 10
                ]
            ]);
            
            $result = file_get_contents(WEBHOOK_URL, false, $context);
            
            return $result !== false;
            
        } catch (Exception $e) {
            Logger::error("Webhook notification failed", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    private static function formatEmailBody($notification)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($notification['title']) . '</title>
        </head>
        <body style="font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;">
            <div style="max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <h1 style="color: #333; margin-bottom: 20px;">' . htmlspecialchars($notification['title']) . '</h1>
                <p style="color: #666; font-size: 16px; line-height: 1.5; margin-bottom: 20px;">
                    ' . nl2br(htmlspecialchars($notification['message'])) . '
                </p>
                <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px;">
                    <p style="margin: 0; color: #666; font-size: 14px;">
                        <strong>Priorité:</strong> ' . ucfirst($notification['priority']) . '<br>
                        <strong>Heure:</strong> ' . $notification['created_at'] . '
                    </p>
                </div>
                <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                <p style="color: #999; font-size: 12px; text-align: center; margin: 0;">
                    SMS Gateway - Notification automatique
                </p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    public static function checkQueueHealth()
    {
        $queueService = new QueueService();
        $status = $queueService->getQueueStatus();
        
        // Alerte si trop de SMS en attente
        if ($status['pending'] > 100) {
            self::createQueueBlockedNotification($status['pending']);
        }
        
        // Nettoyer les SMS bloqués
        $queueService->clearStuckSms();
    }
    
    public static function cleanup($daysOld = 30)
    {
        return Notification::cleanup($daysOld);
    }
}