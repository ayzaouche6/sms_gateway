#!/usr/bin/env python3
"""
Script Python pour la gestion des certificats SSL
Utilisé par l'application PHP SMS Gateway
"""

import sys
import argparse
import subprocess
import json
import logging
import os
import shutil
import datetime
from pathlib import Path
from typing import Dict, Any, Optional

# Configuration du logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class SSLError(Exception):
    """Exception personnalisée pour les erreurs SSL"""
    pass

class SSLManager:
    """Gestionnaire de certificats SSL"""
    
    def __init__(self):
        self.ssl_path = '/etc/ssl/sms-gateway'
        self.nginx_ssl_path = '/etc/nginx/ssl'
        self.backup_path = '/var/backups/ssl'
        self.cert_file = 'sms-gateway.crt'
        self.key_file = 'sms-gateway.key'
        self.nginx_config = '/etc/nginx/sites-enabled/sms-gateway'
        
        # Créer les répertoires nécessaires
        self.ensure_directories()
    
    def ensure_directories(self):
        """Créer les répertoires SSL s'ils n'existent pas"""
        for path in [self.ssl_path, self.nginx_ssl_path, self.backup_path]:
            os.makedirs(path, mode=0o755, exist_ok=True)
    
    def generate_self_signed_certificate(self, days: int = 3650, country: str = "MA", 
                                       state: str = "Casablanca", city: str = "Casablanca",
                                       organization: str = "SMS Gateway", 
                                       common_name: str = "localhost") -> Dict[str, Any]:
        """Génère un certificat SSL auto-signé"""
        try:
            cert_path = os.path.join(self.ssl_path, self.cert_file)
            key_path = os.path.join(self.ssl_path, self.key_file)
            
            # Créer le sujet du certificat
            subject = f"/C={country}/ST={state}/L={city}/O={organization}/CN={common_name}"
            
            # Générer la clé privée et le certificat
            command = [
                'openssl', 'req', '-x509', '-newkey', 'rsa:4096',
                '-keyout', key_path,
                '-out', cert_path,
                '-days', str(days),
                '-nodes',  # Pas de passphrase
                '-subj', subject
            ]
            
            logger.info(f"Génération du certificat SSL pour {days} jours")
            
            result = subprocess.run(
                command,
                capture_output=True,
                text=True,
                timeout=60
            )
            
            if result.returncode != 0:
                raise SSLError(f"Erreur lors de la génération: {result.stderr}")
            
            # Définir les permissions appropriées
            os.chmod(cert_path, 0o644)
            os.chmod(key_path, 0o600)
            
            # Copier vers le répertoire nginx
            nginx_cert = os.path.join(self.nginx_ssl_path, self.cert_file)
            nginx_key = os.path.join(self.nginx_ssl_path, self.key_file)
            
            shutil.copy2(cert_path, nginx_cert)
            shutil.copy2(key_path, nginx_key)
            
            # Obtenir les informations du certificat
            cert_info = self.get_certificate_info(cert_path)
            
            return {
                'success': True,
                'message': 'Certificat SSL généré avec succès',
                'certificate_path': cert_path,
                'key_path': key_path,
                'info': cert_info
            }
            
        except subprocess.TimeoutExpired:
            raise SSLError("Timeout lors de la génération du certificat")
        except Exception as e:
            raise SSLError(f"Erreur lors de la génération: {str(e)}")
    
    def upload_certificate(self, cert_content: str, key_content: str) -> Dict[str, Any]:
        """Upload et installe un certificat personnalisé"""
        try:
            # Valider les certificats
            if not self.validate_certificate_content(cert_content, key_content):
                raise SSLError("Certificat ou clé privée invalide")
            
            # Sauvegarder les certificats actuels
            backup_result = self.backup_current_certificates()
            if not backup_result['success']:
                raise SSLError(f"Erreur lors de la sauvegarde: {backup_result['error']}")
            
            # Écrire les nouveaux certificats
            cert_path = os.path.join(self.ssl_path, 'custom-' + self.cert_file)
            key_path = os.path.join(self.ssl_path, 'custom-' + self.key_file)
            
            with open(cert_path, 'w') as f:
                f.write(cert_content)
            
            with open(key_path, 'w') as f:
                f.write(key_content)
            
            # Définir les permissions
            os.chmod(cert_path, 0o644)
            os.chmod(key_path, 0o600)
            
            # Copier vers nginx
            nginx_cert = os.path.join(self.nginx_ssl_path, self.cert_file)
            nginx_key = os.path.join(self.nginx_ssl_path, self.key_file)
            
            shutil.copy2(cert_path, nginx_cert)
            shutil.copy2(key_path, nginx_key)
            
            # Recharger nginx
            reload_result = self.reload_nginx()
            if not reload_result['success']:
                # Restaurer en cas d'erreur
                self.restore_certificates()
                raise SSLError(f"Erreur lors du rechargement nginx: {reload_result['error']}")
            
            # Obtenir les informations du nouveau certificat
            cert_info = self.get_certificate_info(cert_path)
            
            return {
                'success': True,
                'message': 'Certificat personnalisé installé avec succès',
                'backup_created': backup_result['backup_file'],
                'info': cert_info
            }
            
        except Exception as e:
            raise SSLError(f"Erreur lors de l'upload: {str(e)}")
    
    def validate_certificate_content(self, cert_content: str, key_content: str) -> bool:
        """Valide le contenu d'un certificat et sa clé"""
        try:
            # Créer des fichiers temporaires
            import tempfile
            
            with tempfile.NamedTemporaryFile(mode='w', suffix='.crt', delete=False) as cert_temp:
                cert_temp.write(cert_content)
                cert_temp_path = cert_temp.name
            
            with tempfile.NamedTemporaryFile(mode='w', suffix='.key', delete=False) as key_temp:
                key_temp.write(key_content)
                key_temp_path = key_temp.name
            
            try:
                # Vérifier le certificat
                cert_result = subprocess.run(
                    ['openssl', 'x509', '-in', cert_temp_path, '-noout', '-text'],
                    capture_output=True,
                    timeout=10
                )
                
                # Vérifier la clé privée
                key_result = subprocess.run(
                    ['openssl', 'rsa', '-in', key_temp_path, '-noout', '-check'],
                    capture_output=True,
                    timeout=10
                )
                
                # Vérifier que la clé correspond au certificat
                cert_modulus = subprocess.run(
                    ['openssl', 'x509', '-in', cert_temp_path, '-noout', '-modulus'],
                    capture_output=True,
                    text=True,
                    timeout=10
                )
                
                key_modulus = subprocess.run(
                    ['openssl', 'rsa', '-in', key_temp_path, '-noout', '-modulus'],
                    capture_output=True,
                    text=True,
                    timeout=10
                )
                
                return (cert_result.returncode == 0 and 
                       key_result.returncode == 0 and
                       cert_modulus.stdout == key_modulus.stdout)
                
            finally:
                # Nettoyer les fichiers temporaires
                os.unlink(cert_temp_path)
                os.unlink(key_temp_path)
                
        except Exception as e:
            logger.error(f"Erreur lors de la validation: {str(e)}")
            return False
    
    def get_certificate_info(self, cert_path: str) -> Dict[str, Any]:
        """Récupère les informations d'un certificat"""
        try:
            result = subprocess.run(
                ['openssl', 'x509', '-in', cert_path, '-noout', '-text'],
                capture_output=True,
                text=True,
                timeout=10
            )
            
            if result.returncode != 0:
                return {'error': 'Impossible de lire le certificat'}
            
            info = {}
            lines = result.stdout.split('\n')
            
            for i, line in enumerate(lines):
                line = line.strip()
                
                if 'Subject:' in line:
                    info['subject'] = line.replace('Subject:', '').strip()
                elif 'Issuer:' in line:
                    info['issuer'] = line.replace('Issuer:', '').strip()
                elif 'Not Before:' in line:
                    info['valid_from'] = line.replace('Not Before:', '').strip()
                elif 'Not After :' in line:
                    info['valid_until'] = line.replace('Not After :', '').strip()
                elif 'Public Key Algorithm:' in line:
                    info['algorithm'] = line.replace('Public Key Algorithm:', '').strip()
                elif 'RSA Public-Key:' in line:
                    info['key_size'] = line.replace('RSA Public-Key:', '').strip()
            
            return info
            
        except Exception as e:
            return {'error': f'Erreur lors de la lecture: {str(e)}'}
    
    def backup_current_certificates(self) -> Dict[str, Any]:
        """Sauvegarde les certificats actuels"""
        try:
            timestamp = datetime.datetime.now().strftime('%Y%m%d_%H%M%S')
            backup_dir = os.path.join(self.backup_path, f'ssl_backup_{timestamp}')
            os.makedirs(backup_dir, mode=0o755, exist_ok=True)
            
            # Sauvegarder depuis le répertoire SSL principal
            cert_source = os.path.join(self.ssl_path, self.cert_file)
            key_source = os.path.join(self.ssl_path, self.key_file)
            
            if os.path.exists(cert_source):
                shutil.copy2(cert_source, os.path.join(backup_dir, self.cert_file))
            
            if os.path.exists(key_source):
                shutil.copy2(key_source, os.path.join(backup_dir, self.key_file))
            
            # Créer un fichier d'information
            info_file = os.path.join(backup_dir, 'backup_info.json')
            with open(info_file, 'w') as f:
                json.dump({
                    'timestamp': timestamp,
                    'type': 'ssl_certificates',
                    'files': [self.cert_file, self.key_file]
                }, f, indent=2)
            
            return {
                'success': True,
                'backup_file': backup_dir,
                'timestamp': timestamp
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': f'Erreur lors de la sauvegarde: {str(e)}'
            }
    
    def restore_certificates(self, backup_dir: str = None) -> Dict[str, Any]:
        """Restaure les certificats depuis une sauvegarde"""
        try:
            if not backup_dir:
                # Trouver la sauvegarde la plus récente
                backup_dirs = [d for d in os.listdir(self.backup_path) 
                              if d.startswith('ssl_backup_') and 
                              os.path.isdir(os.path.join(self.backup_path, d))]
                
                if not backup_dirs:
                    raise SSLError("Aucune sauvegarde trouvée")
                
                backup_dirs.sort(reverse=True)
                backup_dir = os.path.join(self.backup_path, backup_dirs[0])
            
            # Restaurer les fichiers
            backup_cert = os.path.join(backup_dir, self.cert_file)
            backup_key = os.path.join(backup_dir, self.key_file)
            
            if not os.path.exists(backup_cert) or not os.path.exists(backup_key):
                raise SSLError("Fichiers de sauvegarde manquants")
            
            # Copier vers le répertoire SSL
            cert_dest = os.path.join(self.ssl_path, self.cert_file)
            key_dest = os.path.join(self.ssl_path, self.key_file)
            
            shutil.copy2(backup_cert, cert_dest)
            shutil.copy2(backup_key, key_dest)
            
            # Copier vers nginx
            nginx_cert = os.path.join(self.nginx_ssl_path, self.cert_file)
            nginx_key = os.path.join(self.nginx_ssl_path, self.key_file)
            
            shutil.copy2(cert_dest, nginx_cert)
            shutil.copy2(key_dest, nginx_key)
            
            # Recharger nginx
            reload_result = self.reload_nginx()
            if not reload_result['success']:
                raise SSLError(f"Erreur lors du rechargement nginx: {reload_result['error']}")
            
            return {
                'success': True,
                'message': 'Certificats restaurés avec succès',
                'restored_from': backup_dir
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': f'Erreur lors de la restauration: {str(e)}'
            }
    
    def get_current_certificate_info(self) -> Dict[str, Any]:
        """Récupère les informations du certificat actuel"""
        try:
            cert_path = os.path.join(self.nginx_ssl_path, self.cert_file)
            
            if not os.path.exists(cert_path):
                return {
                    'exists': False,
                    'message': 'Aucun certificat SSL installé'
                }
            
            info = self.get_certificate_info(cert_path)
            info['exists'] = True
            info['file_path'] = cert_path
            info['file_size'] = os.path.getsize(cert_path)
            info['file_modified'] = datetime.datetime.fromtimestamp(
                os.path.getmtime(cert_path)
            ).strftime('%Y-%m-%d %H:%M:%S')
            
            return info
            
        except Exception as e:
            return {
                'exists': False,
                'error': f'Erreur lors de la lecture: {str(e)}'
            }
    
    def update_nginx_config_for_ssl(self) -> Dict[str, Any]:
        """Met à jour la configuration nginx pour HTTPS"""
        try:
            ssl_config = f"""server {{
    listen 80;
    server_name localhost;
    return 301 https://$server_name$request_uri;
}}

server {{
    listen 443 ssl http2;
    server_name localhost;

    root /var/www/html/sms-gateway/public;
    index index.php index.html;

    # SSL Configuration
    ssl_certificate {self.nginx_ssl_path}/{self.cert_file};
    ssl_certificate_key {self.nginx_ssl_path}/{self.key_file};
    
    # SSL Security Settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES128-SHA256:ECDHE-RSA-AES256-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection "1; mode=block" always;

    access_log /var/log/nginx/sms-gateway.access.log;
    error_log /var/log/nginx/sms-gateway.error.log;

    location / {{
        try_files $uri $uri/ /index.php?$query_string;
    }}

    location ~ \.php$ {{
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.3-fmp.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
        fastcgi_index index.php;
    }}

    location ~* \.(js|css|png|jpg|jpeg|gif|ico)$ {{
        try_files $uri =404;
        expires max;
        add_header Cache-Control "public, immutable";
    }}
}}"""
            
            # Sauvegarder la configuration actuelle
            if os.path.exists(self.nginx_config):
                backup_config = f"{self.nginx_config}.backup.{datetime.datetime.now().strftime('%Y%m%d_%H%M%S')}"
                shutil.copy2(self.nginx_config, backup_config)
            
            # Écrire la nouvelle configuration
            with open(self.nginx_config, 'w') as f:
                f.write(ssl_config)
            
            # Tester la configuration nginx
            test_result = subprocess.run(
                ['nginx', '-t'],
                capture_output=True,
                text=True,
                timeout=10
            )
            
            if test_result.returncode != 0:
                raise SSLError(f"Configuration nginx invalide: {test_result.stderr}")
            
            # Recharger nginx
            reload_result = self.reload_nginx()
            if not reload_result['success']:
                raise SSLError(f"Erreur lors du rechargement: {reload_result['error']}")
            
            return {
                'success': True,
                'message': 'Configuration nginx mise à jour pour HTTPS'
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': f'Erreur lors de la mise à jour nginx: {str(e)}'
            }
    
    def reload_nginx(self) -> Dict[str, Any]:
        """Recharge la configuration nginx"""
        try:
            result = subprocess.run(
                ['systemctl', 'reload', 'nginx'],
                capture_output=True,
                text=True,
                timeout=30
            )
            
            if result.returncode != 0:
                return {
                    'success': False,
                    'error': f'Erreur systemctl: {result.stderr}'
                }
            
            return {
                'success': True,
                'message': 'Nginx rechargé avec succès'
            }
            
        except subprocess.TimeoutExpired:
            return {
                'success': False,
                'error': 'Timeout lors du rechargement nginx'
            }
        except Exception as e:
            return {
                'success': False,
                'error': f'Erreur lors du rechargement: {str(e)}'
            }
    
    def list_backups(self) -> List[Dict[str, Any]]:
        """Liste les sauvegardes SSL disponibles"""
        try:
            backups = []
            
            if not os.path.exists(self.backup_path):
                return backups
            
            for item in os.listdir(self.backup_path):
                backup_dir = os.path.join(self.backup_path, item)
                if os.path.isdir(backup_dir) and item.startswith('ssl_backup_'):
                    info_file = os.path.join(backup_dir, 'backup_info.json')
                    
                    backup_info = {
                        'name': item,
                        'path': backup_dir,
                        'timestamp': item.replace('ssl_backup_', ''),
                        'size': self.get_directory_size(backup_dir)
                    }
                    
                    if os.path.exists(info_file):
                        try:
                            with open(info_file, 'r') as f:
                                backup_info.update(json.load(f))
                        except:
                            pass
                    
                    backups.append(backup_info)
            
            # Trier par timestamp (plus récent en premier)
            backups.sort(key=lambda x: x['timestamp'], reverse=True)
            
            return backups
            
        except Exception as e:
            logger.error(f"Erreur lors de la liste des sauvegardes: {str(e)}")
            return []
    
    def get_directory_size(self, path: str) -> int:
        """Calcule la taille d'un répertoire"""
        total_size = 0
        try:
            for dirpath, dirnames, filenames in os.walk(path):
                for filename in filenames:
                    filepath = os.path.join(dirpath, filename)
                    total_size += os.path.getsize(filepath)
        except:
            pass
        return total_size

def main():
    """Fonction principale"""
    parser = argparse.ArgumentParser(
        description='Gestionnaire de certificats SSL pour SMS Gateway'
    )
    
    parser.add_argument('--action', required=True,
                       choices=['generate', 'upload', 'get_info', 'backup', 'restore', 
                               'update_nginx', 'list_backups'],
                       help='Action à effectuer')
    
    # Paramètres pour generate
    parser.add_argument('--days', type=int, default=3650, help='Durée de validité en jours')
    parser.add_argument('--country', default='MA', help='Code pays (2 lettres)')
    parser.add_argument('--state', default='Casablanca', help='État/Province')
    parser.add_argument('--city', default='Casablanca', help='Ville')
    parser.add_argument('--organization', default='SMS Gateway', help='Organisation')
    parser.add_argument('--common-name', default='localhost', help='Nom commun (domaine)')
    
    # Paramètres pour upload
    parser.add_argument('--cert-file', help='Fichier certificat à uploader')
    parser.add_argument('--key-file', help='Fichier clé privée à uploader')
    
    # Paramètres pour restore
    parser.add_argument('--backup-dir', help='Répertoire de sauvegarde à restaurer')
    
    parser.add_argument('--verbose', '-v', action='store_true', help='Mode verbeux')
    
    args = parser.parse_args()
    
    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    
    try:
        manager = SSLManager()
        
        if args.action == 'generate':
            result = manager.generate_self_signed_certificate(
                days=args.days,
                country=args.country,
                state=args.state,
                city=args.city,
                organization=args.organization,
                common_name=args.common_name
            )
            print(json.dumps(result, indent=2))
            
        elif args.action == 'upload':
            if not args.cert_file or not args.key_file:
                raise SSLError("Fichiers certificat et clé requis")
            
            with open(args.cert_file, 'r') as f:
                cert_content = f.read()
            
            with open(args.key_file, 'r') as f:
                key_content = f.read()
            
            result = manager.upload_certificate(cert_content, key_content)
            print(json.dumps(result, indent=2))
            
        elif args.action == 'get_info':
            result = manager.get_current_certificate_info()
            print(json.dumps({'success': True, 'data': result}, indent=2))
            
        elif args.action == 'backup':
            result = manager.backup_current_certificates()
            print(json.dumps(result, indent=2))
            
        elif args.action == 'restore':
            result = manager.restore_certificates(args.backup_dir)
            print(json.dumps(result, indent=2))
            
        elif args.action == 'update_nginx':
            result = manager.update_nginx_config_for_ssl()
            print(json.dumps(result, indent=2))
            
        elif args.action == 'list_backups':
            backups = manager.list_backups()
            print(json.dumps({'success': True, 'backups': backups}, indent=2))
        
        sys.exit(0)
        
    except SSLError as e:
        error_result = {
            'success': False,
            'error': str(e),
            'error_type': 'SSL_ERROR'
        }
        print(json.dumps(error_result))
        sys.exit(1)
        
    except KeyboardInterrupt:
        logger.info("Interruption par l'utilisateur")
        sys.exit(1)
        
    except Exception as e:
        error_result = {
            'success': False,
            'error': str(e),
            'error_type': 'SYSTEM_ERROR'
        }
        print(json.dumps(error_result))
        sys.exit(1)

if __name__ == '__main__':
    main()