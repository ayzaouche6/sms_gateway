# SMS Gateway

Une application web complète pour l'envoi et la gestion de SMS via modems GSM utilisant mmcli (ModemManager).

## Fonctionnalités

### 🚀 Fonctionnalités principales
- **Envoi de SMS** : Interface web intuitive pour envoyer des SMS individuels
- **Envoi en masse** : Import CSV/Excel pour envois groupés avec drag & drop
- **File d'attente intelligente** : Gestion automatique avec retry et priorités
- **Multi-modems** : Support de plusieurs modems avec répartition de charge
- **Programmation** : SMS programmés dans le temps
- **API REST** : Interface API complète avec authentification JWT

### 👥 Gestion des utilisateurs
- **Rôles multiples** : Admin, Superviseur, Opérateur
- **Authentification sécurisée** : Protection contre les attaques par force brute
- **Sessions sécurisées** : Gestion avancée des sessions avec timeout

### 📊 Reporting et statistiques
- **Dashboard en temps réel** : Statistiques live avec mise à jour automatique
- **Rapports détaillés** : Analyses par période, utilisateur, statut
- **Export de données** : CSV, JSON, Excel
- **Graphiques interactifs** : Visualisation des tendances

### 🔒 Sécurité
- **Protection CSRF** : Tokens anti-CSRF sur tous les formulaires
- **Rate limiting** : Limitation des requêtes par IP/utilisateur
- **Validation stricte** : Sanitisation et validation de toutes les entrées
- **Logs de sécurité** : Traçabilité complète des actions

### 🔧 Administration
- **Gestion des modems** : Configuration et monitoring des modems GSM
- **Notifications** : Alertes email/webhook pour les événements critiques
- **Maintenance** : Scripts de nettoyage et optimisation automatiques
- **Monitoring** : Surveillance de la santé du système

## Architecture technique

### Stack technologique
- **Backend** : PHP 8.1+ avec architecture MVC personnalisée
- **Frontend** : Bootstrap 5 + jQuery avec interface responsive
- **Base de données** : MySQL 8.0+ avec optimisations
- **SMS** : Python 3 + mmcli (ModemManager)
- **Serveur web** : Nginx recommandé

### Structure du projet
```
sms-gateway/
├── public/                 # Point d'entrée web
├── app/                    # Code source MVC
│   ├── Core/              # Composants de base
│   ├── Controllers/       # Contrôleurs
│   ├── Models/           # Modèles de données
│   ├── Services/         # Services métiers
│   └── Views/            # Vues et templates
├── config/               # Configuration
├── database/             # Schéma et migrations
├── tools/                # Scripts utilitaires
├── logs/                 # Fichiers de logs
└── systemd/              # Services système
```

## Installation

### Prérequis
- PHP 8.1+ avec extensions : pdo_mysql, json, mbstring, curl
- MySQL 8.0+
- Python 3.8+ avec mmcli
- Nginx ou Apache
- Modems GSM compatibles ModemManager

### Installation rapide

1. **Cloner le projet**
```bash
git clone https://github.com/votre-repo/sms-gateway.git
cd sms-gateway
```

2. **Configuration de la base de données**
```bash
mysql -u root -p < database/migrations.sql
```

3. **Configuration de l'application**
```bash
cp config/.env.example config/.env
# Éditer config/.env avec vos paramètres
```

4. **Configuration Nginx**
```nginx
server {
    listen 80;
    server_name sms-gateway.local;
    root /var/www/sms-gateway/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

5. **Permissions**
```bash
sudo chown -R www-data:www-data /var/www/sms-gateway
sudo chmod -R 755 /var/www/sms-gateway
sudo chmod -R 777 /var/www/sms-gateway/logs
```

6. **Service de traitement de la queue**
```bash
sudo cp systemd/send_queue.service /etc/systemd/system/
sudo systemctl enable send_queue.service
sudo systemctl start send_queue.service
```

### Configuration des modems

1. **Vérifier les modems détectés**
```bash
mmcli -L
```

2. **Tester l'envoi via Python**
```bash
python3 tools/send_sms_mmcli.py --list-modems
python3 tools/send_sms_mmcli.py --recipient "+33612345678" --message "Test"
```

3. **Configurer dans l'interface web**
- Connectez-vous avec admin/password
- Allez dans la configuration des modems
- Ajoutez vos modems avec les bons chemins de périphériques

## Utilisation

### Interface web
1. **Connexion** : `http://votre-serveur/login`
   - Admin par défaut : `admin@smsgateway.local` / `password`

2. **Envoi de SMS**
   - SMS individuel : Menu "Envoyer SMS"
   - SMS en masse : Menu "Envoi en masse" avec import CSV

3. **Suivi**
   - Dashboard : Vue d'ensemble en temps réel
   - File d'attente : Suivi détaillé des SMS
   - Rapports : Analyses et exports

### API REST

**Authentification**
```bash
curl -X POST http://votre-serveur/api/auth \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@smsgateway.local","password":"password"}'
```

**Envoi de SMS**
```bash
curl -X POST http://votre-serveur/api/sms/send \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"recipient":"+33612345678","message":"Hello via API"}'
```

**Statut d'un SMS**
```bash
curl -X GET http://votre-serveur/api/sms/status/123 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Scripts en ligne de commande

**Traitement manuel de la queue**
```bash
php tools/send_queue.php --verbose
```

**Import de contacts**
```bash
php tools/ImportContacts.php --file contacts.csv --user 1 --message "Hello"
```

**Export de rapports**
```bash
php tools/ExportReport.php --from 2024-01-01 --to 2024-01-31 --format csv
```

## Configuration avancée

### Variables d'environnement (.env)
```env
# Application
APP_NAME="SMS Gateway"
APP_URL="http://localhost"
DEBUG=false

# Base de données
DB_HOST=localhost
DB_NAME=sms_gateway
DB_USER=sms_user
DB_PASS=secure_password

# SMS
SMS_MAX_PER_MINUTE=60

# Sécurité
JWT_SECRET=your-very-secure-secret-key
LOGIN_ATTEMPTS_MAX=5

# Notifications
SMTP_HOST=smtp.gmail.com
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
WEBHOOK_URL=https://your-webhook-url.com/sms-alerts
```

### Optimisations de performance

**MySQL**
```sql
-- Index pour les requêtes fréquentes
CREATE INDEX idx_sms_status_created ON sms(status, created_at);
CREATE INDEX idx_sms_recipient_created ON sms(recipient, created_at);

-- Nettoyage automatique
CALL CleanupOldData();
```

**PHP (php.ini)**
```ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 10M
post_max_size = 10M
```

### Monitoring et logs

**Logs disponibles**
- `logs/app.log` : Log principal de l'application
- `logs/error/` : Logs d'erreurs par jour
- `logs/security.log` : Événements de sécurité
- `logs/php_errors.log` : Erreurs PHP

**Monitoring avec systemd**
```bash
# Statut du service
sudo systemctl status send_queue.service

# Logs en temps réel
sudo journalctl -u send_queue.service -f

# Redémarrage
sudo systemctl restart send_queue.service
```

## Maintenance

### Nettoyage automatique
Le système inclut un nettoyage automatique qui :
- Supprime les anciens SMS (90 jours)
- Archive les logs volumineux
- Nettoie les tentatives de connexion anciennes
- Supprime les notifications traitées

### Sauvegarde
```bash
# Base de données
mysqldump -u root -p sms_gateway > backup_$(date +%Y%m%d).sql

# Fichiers de configuration
tar -czf config_backup_$(date +%Y%m%d).tar.gz config/ logs/
```

### Mise à jour
```bash
# Sauvegarder
mysqldump -u root -p sms_gateway > backup_before_update.sql

# Mettre à jour le code
git pull origin main

# Appliquer les migrations si nécessaire
mysql -u root -p sms_gateway < database/migrations.sql

# Redémarrer les services
sudo systemctl restart send_queue.service
sudo systemctl reload nginx
```

## Dépannage

### Problèmes courants

**Les SMS ne sont pas envoyés**
1. Vérifier le service : `sudo systemctl status send_queue.service`
2. Vérifier les modems : `mmcli -L`
3. Tester manuellement : `python3 tools/send_sms_mmcli.py --list-modems`
4. Vérifier les logs : `tail -f logs/app.log`

**Erreurs de base de données**
1. Vérifier la connexion : `mysql -u sms_user -p sms_gateway`
2. Vérifier les permissions : `SHOW GRANTS FOR 'sms_user'@'localhost';`
3. Vérifier l'espace disque : `df -h`

**Interface web inaccessible**
1. Vérifier Nginx : `sudo systemctl status nginx`
2. Vérifier PHP-FPM : `sudo systemctl status php8.1-fpm`
3. Vérifier les permissions : `ls -la /var/www/sms-gateway/`
4. Vérifier les logs : `tail -f /var/log/nginx/error.log`

### Support et contribution

**Logs de debug**
Pour activer le mode debug, modifier dans `config/.env` :
```env
DEBUG=true
LOG_LEVEL=DEBUG
```

**Signaler un bug**
1. Activer le mode debug
2. Reproduire le problème
3. Collecter les logs pertinents
4. Créer une issue avec les détails

## Licence

Ce projet est sous licence MIT. Voir le fichier LICENSE pour plus de détails.

## Auteurs

- Développé avec ❤️ pour la communauté open source
- Contributions bienvenues !

---

Pour plus d'informations, consultez la documentation complète ou créez une issue sur GitHub.