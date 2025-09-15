#!/usr/bin/env python3
"""
Script Python pour recevoir des SMS via mmcli (ModemManager)
Utilisé par l'application PHP SMS Gateway pour récupérer les SMS entrants
"""

import sys
import argparse
import subprocess
import json
import logging
import time
import re
import hashlib
import mysql.connector
import os
from typing import Dict, Any, Optional, List
from datetime import datetime, timedelta

# Configuration du logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class SMSReceiveError(Exception):
    """Exception personnalisée pour les erreurs de réception SMS"""
    pass

class DatabaseManager:
    """Gestionnaire de base de données pour les SMS reçus"""
    
    def __init__(self, config):
        self.config = config
        self.connection = None
        self.connect()
    
    def connect(self):
        """Établit la connexion à la base de données"""
        try:
            self.connection = mysql.connector.connect(
                host=self.config['host'],
                port=self.config['port'],
                user=self.config['user'],
                password=self.config['password'],
                database=self.config['database'],
                charset='utf8mb4',
                autocommit=True
            )
            logger.debug("Database connection established")
        except mysql.connector.Error as e:
            raise SMSReceiveError(f"Database connection failed: {e}")
    
    def store_received_sms(self, sender: str, message: str, received_at: datetime, modem_id: int = None) -> bool:
        """Stocke un SMS reçu en évitant les doublons"""
        try:
            cursor = self.connection.cursor()
            
            # Générer le hash pour la déduplication
            message_hash = self.generate_message_hash(sender, message, received_at)
            
            # Vérifier si le SMS existe déjà (dans les 5 dernières minutes)
            check_query = """
                SELECT id FROM received_sms 
                WHERE sender = %s 
                AND message_hash = %s 
                AND received_at BETWEEN %s AND %s
            """
            
            time_window_start = received_at - timedelta(minutes=5)
            time_window_end = received_at + timedelta(minutes=5)
            
            cursor.execute(check_query, (sender, message_hash, time_window_start, time_window_end))
            
            if cursor.fetchone():
                logger.info(f"Duplicate SMS ignored from {sender}")
                return False
            
            # Insérer le nouveau SMS
            insert_query = """
                INSERT INTO received_sms (sender, message, received_at, modem_id, message_hash, is_unicode, parts_count)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
            """
            
            is_unicode = self.contains_unicode(message)
            parts_count = self.calculate_parts_count(message, is_unicode)
            
            cursor.execute(insert_query, (
                sender, message, received_at, modem_id, 
                message_hash, is_unicode, parts_count
            ))
            
            sms_id = cursor.lastrowid
            logger.info(f"SMS stored with ID {sms_id} from {sender}")
            
            cursor.close()
            return True
            
        except mysql.connector.Error as e:
            logger.error(f"Database error storing SMS: {e}")
            return False
    
    def generate_message_hash(self, sender: str, message: str, received_at: datetime) -> str:
        """Génère un hash pour la déduplication"""
        # Arrondir à la minute pour éviter les doublons temporels
        rounded_time = received_at.replace(second=0, microsecond=0)
        
        hash_input = f"{sender}|{message}|{rounded_time.isoformat()}"
        return hashlib.sha256(hash_input.encode()).hexdigest()
    
    def contains_unicode(self, text: str) -> bool:
        """Vérifie si le texte contient des caractères Unicode"""
        return len(text.encode('utf-8')) != len(text)
    
    def calculate_parts_count(self, message: str, is_unicode: bool) -> int:
        """Calcule le nombre de parties SMS"""
        max_length = 70 if is_unicode else 160
        return max(1, (len(message) + max_length - 1) // max_length)
    
    def get_modem_id_by_device(self, device_path: str) -> Optional[int]:
        """Récupère l'ID du modem par son chemin de périphérique"""
        try:
            cursor = self.connection.cursor()
            cursor.execute("SELECT id FROM modems WHERE device_path = %s", (device_path,))
            result = cursor.fetchone()
            cursor.close()
            return result[0] if result else None
        except mysql.connector.Error as e:
            logger.error(f"Error getting modem ID: {e}")
            return None
    
    def close(self):
        """Ferme la connexion à la base de données"""
        if self.connection:
            self.connection.close()

class SMSReceiver:
    """Classe principale pour la réception de SMS"""
    
    def __init__(self, db_config):
        self.db = DatabaseManager(db_config)
        self.processed_sms = set()  # Cache pour éviter les doublons dans la session
    
    def find_modems(self) -> List[Dict[str, Any]]:
        """Trouve tous les modems disponibles"""
        try:
            result = subprocess.run(
                ['mmcli', '-L'],
                capture_output=True,
                text=True,
                timeout=10
            )
            
            if result.returncode != 0:
                raise SMSReceiveError(f"Erreur lors de la recherche de modems: {result.stderr}")
            
            modems = []
            for line in result.stdout.split('\n'):
                if '/org/freedesktop/ModemManager1/Modem/' in line:
                    match = re.search(r'/Modem/(\d+)', line)
                    if match:
                        modem_id = match.group(1)
                        modem_info = self.get_modem_info(modem_id)
                        if modem_info:
                            modems.append(modem_info)
            
            return modems
        
        except subprocess.TimeoutExpired:
            raise SMSReceiveError("Timeout lors de la recherche de modems")
        except Exception as e:
            raise SMSReceiveError(f"Erreur inattendue: {str(e)}")
    
    def get_modem_info(self, modem_id: str) -> Optional[Dict[str, Any]]:
        """Récupère les informations d'un modem"""
        try:
            result = subprocess.run(
                ['mmcli', '-m', modem_id],
                capture_output=True,
                text=True,
                timeout=10
            )
            
            if result.returncode != 0:
                return None
            
            info = {'id': modem_id}
            
            for line in result.stdout.split('\n'):
                line = line.strip()
                
                if 'Primary port:' in line:
                    match = re.search(r'Primary port:\s*(.+)', line)
                    if match:
                        info['device_path'] = match.group(1).strip()
                
                elif 'Status |' in line and 'state:' in line:
                    if 'registered' in line or 'connected' in line:
                        info['status'] = 'ready'
                    else:
                        info['status'] = 'not_ready'
            
            return info if info.get('device_path') else None
            
        except subprocess.TimeoutExpired:
            return None
        except Exception as e:
            logger.warning(f"Error getting modem info: {str(e)}")
            return None
    
    def get_sms_list(self, modem_id: str) -> List[str]:
        """Récupère la liste des SMS sur un modem"""
        try:
            result = subprocess.run(
                ['mmcli', '-m', modem_id, '--messaging-list-sms'],
                capture_output=True,
                text=True,
                timeout=15
            )
            
            if result.returncode != 0:
                return []
            
            sms_ids = []
            for line in result.stdout.split('\n'):
                if '/org/freedesktop/ModemManager1/SMS/' in line:
                    match = re.search(r'/SMS/(\d+)', line)
                    if match:
                        sms_ids.append(match.group(1))
            
            return sms_ids
            
        except subprocess.TimeoutExpired:
            logger.warning(f"Timeout getting SMS list for modem {modem_id}")
            return []
        except Exception as e:
            logger.warning(f"Error getting SMS list: {str(e)}")
            return []
    
    def get_sms_details(self, sms_id: str) -> Optional[Dict[str, Any]]:
        """Récupère les détails d'un SMS"""
        try:
            result = subprocess.run(
                ['mmcli', '-s', sms_id],
                capture_output=True,
                text=True,
                timeout=10
            )
            
            if result.returncode != 0:
                return None
            
            sms_info = {'id': sms_id}
            
            for line in result.stdout.split('\n'):
                line = line.strip()
                
                if 'Number:' in line:
                    match = re.search(r'Number:\s*(.+)', line)
                    if match:
                        sms_info['sender'] = match.group(1).strip()
                
                elif 'Text:' in line:
                    match = re.search(r'Text:\s*(.+)', line)
                    if match:
                        sms_info['message'] = match.group(1).strip()
                
                elif 'Timestamp:' in line:
                    match = re.search(r'Timestamp:\s*(.+)', line)
                    if match:
                        timestamp_str = match.group(1).strip()
                        try:
                            # Parse timestamp (format peut varier)
                            sms_info['timestamp'] = self.parse_timestamp(timestamp_str)
                        except:
                            sms_info['timestamp'] = datetime.now()
            
            return sms_info if sms_info.get('sender') and sms_info.get('message') else None
            
        except subprocess.TimeoutExpired:
            return None
        except Exception as e:
            logger.warning(f"Error getting SMS details: {str(e)}")
            return None
    
    def parse_timestamp(self, timestamp_str: str) -> datetime:
        """Parse le timestamp du SMS"""
        # Essayer différents formats
        formats = [
            '%Y-%m-%d %H:%M:%S%z',
            '%Y-%m-%d %H:%M:%S',
            '%Y-%m-%dT%H:%M:%S%z',
            '%Y-%m-%dT%H:%M:%S'
        ]
        
        for fmt in formats:
            try:
                return datetime.strptime(timestamp_str, fmt)
            except ValueError:
                continue
        
        # Si aucun format ne fonctionne, utiliser l'heure actuelle
        return datetime.now()
    
    def delete_sms_from_modem(self, modem_id: str, sms_id: str) -> bool:
        """Supprime un SMS de la mémoire du modem"""
        try:
            result = subprocess.run(
                ['mmcli', '-m', modem_id, '--messaging-delete-sms', sms_id],
                capture_output=True,
                text=True,
                timeout=10
            )
            
            return result.returncode == 0
            
        except subprocess.TimeoutExpired:
            logger.warning(f"Timeout deleting SMS {sms_id}")
            return False
        except Exception as e:
            logger.warning(f"Error deleting SMS: {str(e)}")
            return False
    
    def process_modem_sms(self, modem: Dict[str, Any]) -> int:
        """Traite tous les SMS d'un modem"""
        processed_count = 0
        modem_id = modem['id']
        device_path = modem['device_path']
        
        logger.info(f"Processing SMS for modem {modem_id} ({device_path})")
        
        # Récupérer l'ID du modem dans la base de données
        db_modem_id = self.db.get_modem_id_by_device(device_path)
        
        # Récupérer la liste des SMS
        sms_list = self.get_sms_list(modem_id)
        
        if not sms_list:
            logger.debug(f"No SMS found on modem {modem_id}")
            return 0
        
        logger.info(f"Found {len(sms_list)} SMS on modem {modem_id}")
        
        for sms_id in sms_list:
            try:
                # Éviter de traiter le même SMS plusieurs fois
                cache_key = f"{modem_id}_{sms_id}"
                if cache_key in self.processed_sms:
                    continue
                
                # Récupérer les détails du SMS
                sms_details = self.get_sms_details(sms_id)
                
                if not sms_details:
                    logger.warning(f"Could not get details for SMS {sms_id}")
                    continue
                
                # Stocker en base de données
                stored = self.db.store_received_sms(
                    sms_details['sender'],
                    sms_details['message'],
                    sms_details['timestamp'],
                    db_modem_id
                )
                
                if stored:
                    processed_count += 1
                    logger.info(f"Stored SMS from {sms_details['sender']}: {sms_details['message'][:50]}...")
                
                # Supprimer le SMS du modem pour libérer la mémoire
                if self.delete_sms_from_modem(modem_id, sms_id):
                    logger.debug(f"Deleted SMS {sms_id} from modem memory")
                
                # Marquer comme traité
                self.processed_sms.add(cache_key)
                
            except Exception as e:
                logger.error(f"Error processing SMS {sms_id}: {str(e)}")
                continue
        
        return processed_count
    
    def run_receive_cycle(self) -> Dict[str, Any]:
        """Exécute un cycle de réception SMS"""
        try:
            modems = self.find_modems()
            
            if not modems:
                logger.warning("No modems found")
                return {'success': True, 'processed': 0, 'modems': 0}
            
            total_processed = 0
            active_modems = 0
            
            for modem in modems:
                if modem.get('status') == 'ready':
                    active_modems += 1
                    processed = self.process_modem_sms(modem)
                    total_processed += processed
            
            return {
                'success': True,
                'processed': total_processed,
                'modems': active_modems,
                'total_modems': len(modems)
            }
            
        except Exception as e:
            logger.error(f"Error in receive cycle: {str(e)}")
            return {
                'success': False,
                'error': str(e)
            }

def load_config() -> Dict[str, Any]:
    """Charge la configuration depuis le fichier .env"""
    config = {
        'host': 'localhost',
        'port': 3306,
        'user': 'root',
        'password': '',
        'database': 'sms_gateway'
    }
    
    # Essayer de charger depuis .env
    env_file = '/var/www/html/sms-gateway/config/.env'
    if os.path.exists(env_file):
        try:
            with open(env_file, 'r') as f:
                for line in f:
                    line = line.strip()
                    if line and not line.startswith('#') and '=' in line:
                        key, value = line.split('=', 1)
                        key = key.strip()
                        value = value.strip().strip('"\'')
                        
                        if key == 'DB_HOST':
                            config['host'] = value
                        elif key == 'DB_PORT':
                            config['port'] = int(value)
                        elif key == 'DB_USER':
                            config['user'] = value
                        elif key == 'DB_PASS':
                            config['password'] = value
                        elif key == 'DB_NAME':
                            config['database'] = value
        except Exception as e:
            logger.warning(f"Could not load .env file: {e}")
    
    return config

def main():
    """Fonction principale"""
    parser = argparse.ArgumentParser(
        description='Réception de SMS via mmcli (ModemManager)',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Exemples d'utilisation:
  %(prog)s --check-once
  %(prog)s --daemon --interval 30
  %(prog)s --list-modems
        """
    )
    
    parser.add_argument('--check-once', action='store_true',
                       help='Vérifier une seule fois les SMS reçus')
    parser.add_argument('--daemon', action='store_true',
                       help='Mode démon - vérification continue')
    parser.add_argument('--interval', type=int, default=30,
                       help='Intervalle de vérification en secondes (mode démon)')
    parser.add_argument('--list-modems', action='store_true',
                       help='Lister tous les modems disponibles')
    parser.add_argument('--verbose', '-v', action='store_true',
                       help='Mode verbose')
    parser.add_argument('--json-output', action='store_true',
                       help='Sortie au format JSON')
    
    args = parser.parse_args()
    
    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    
    try:
        # Charger la configuration
        db_config = load_config()
        
        receiver = SMSReceiver(db_config)
        
        if args.list_modems:
            modems = receiver.find_modems()
            
            if args.json_output:
                print(json.dumps({'modems': modems}, indent=2))
            else:
                print("Modems disponibles:")
                for modem in modems:
                    print(f"  ID: {modem['id']}")
                    print(f"  Périphérique: {modem.get('device_path', 'N/A')}")
                    print(f"  Statut: {modem.get('status', 'N/A')}")
                    print()
            
            sys.exit(0)
        
        if args.daemon:
            logger.info(f"Starting SMS receiver daemon (interval: {args.interval}s)")
            
            while True:
                try:
                    result = receiver.run_receive_cycle()
                    
                    if args.verbose and result['processed'] > 0:
                        logger.info(f"Processed {result['processed']} SMS from {result['modems']} modems")
                    
                    time.sleep(args.interval)
                    
                except KeyboardInterrupt:
                    logger.info("Daemon stopped by user")
                    break
                except Exception as e:
                    logger.error(f"Error in daemon cycle: {e}")
                    time.sleep(args.interval)
        
        elif args.check_once:
            result = receiver.run_receive_cycle()
            
            if args.json_output:
                print(json.dumps(result, indent=2))
            else:
                if result['success']:
                    print(f"SMS check completed: {result['processed']} SMS processed from {result['modems']} active modems")
                else:
                    print(f"Error: {result['error']}")
        
        else:
            parser.print_help()
        
        sys.exit(0)
        
    except SMSReceiveError as e:
        if args.json_output:
            error_result = {
                'success': False,
                'error': str(e),
                'error_type': 'SMS_RECEIVE_ERROR'
            }
            print(json.dumps(error_result))
        else:
            logger.error(f"SMS receive error: {e}")
        sys.exit(1)
        
    except KeyboardInterrupt:
        logger.info("Interrupted by user")
        sys.exit(0)
        
    except Exception as e:
        if args.json_output:
            error_result = {
                'success': False,
                'error': str(e),
                'error_type': 'SYSTEM_ERROR'
            }
            print(json.dumps(error_result))
        else:
            logger.error(f"System error: {e}")
        sys.exit(1)
    
    finally:
        try:
            receiver.db.close()
        except:
            pass

if __name__ == '__main__':
    main()