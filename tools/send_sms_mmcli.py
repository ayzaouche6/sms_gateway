#!/usr/bin/env python3
"""
Script Python pour envoyer des SMS via mmcli (ModemManager)
Utilisé par l'application PHP SMS Gateway
"""

import sys
import argparse
import subprocess
import json
import logging
import time
import re
from typing import Dict, Any, Optional, List

# Configuration du logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class SMSError(Exception):
    """Exception personnalisée pour les erreurs SMS"""
    pass

class ModemManager:
    """Gestionnaire de modems via mmcli"""
    
    def __init__(self, device_path: str = None):
        self.device_path = device_path
        self.modem_id = None
        
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
                raise SMSError(f"Erreur lors de la recherche de modems: {result.stderr}")
            
            modems = []
            for line in result.stdout.split('\n'):
                if '/org/freedesktop/ModemManager1/Modem/' in line:
                    # Extraire l'ID du modem
                    match = re.search(r'/Modem/(\d+)', line)
                    if match:
                        modem_id = match.group(1)
                        modem_info = self.get_modem_info(modem_id)
                        if modem_info:
                            modems.append(modem_info)
            
            return modems
        
        except subprocess.TimeoutExpired:
            raise SMSError("Timeout lors de la recherche de modems")
        except Exception as e:
            raise SMSError(f"Erreur inattendue lors de la recherche de modems: {str(e)}")
    
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
                logger.warning(f"Impossible d'obtenir les infos du modem {modem_id}")
                return None
            
            info = {'id': modem_id}
            
            # Parser les informations du modem
            for line in result.stdout.split('\n'):
                line = line.strip()
                
                if 'Status |' in line and 'state:' in line:
                    if 'registered' in line or 'connected' in line:
                        info['status'] = 'ready'
                    else:
                        info['status'] = 'not_ready'
                
                elif '3GPP |' in line and 'imei:' in line:
                    match = re.search(r'imei:\s*(\w+)', line)
                    if match:
                        info['imei'] = match.group(1)
                
                elif '3GPP |' in line and 'operator name:' in line:
                    match = re.search(r'operator name:\s*(.+)', line)
                    if match:
                        info['operator'] = match.group(1).strip()
                
                elif 'Signal |' in line and 'quality:' in line:
                    match = re.search(r'quality:\s*(\d+)%', line)
                    if match:
                        info['signal_quality'] = int(match.group(1))
                
                elif 'Primary port:' in line:
                    match = re.search(r'Primary port:\s*(.+)', line)
                    if match:
                        info['device_path'] = match.group(1).strip()
            
            return info
            
        except subprocess.TimeoutExpired:
            logger.warning(f"Timeout lors de la récupération des infos du modem {modem_id}")
            return None
        except Exception as e:
            logger.warning(f"Erreur lors de la récupération des infos du modem {modem_id}: {str(e)}")
            return None
    
    def find_modem_by_device(self, device_path: str) -> Optional[str]:
        """Trouve l'ID d'un modem par son chemin de périphérique"""
        modems = self.find_modems()
        
        for modem in modems:
            if modem.get('device_path') == device_path:
                return modem['id']
        
        return None
    
    def get_best_modem(self) -> str:
        """Trouve le meilleur modem disponible"""
        if self.device_path:
            modem_id = self.find_modem_by_device(self.device_path)
            if modem_id:
                return modem_id
            else:
                raise SMSError(f"Modem non trouvé pour le périphérique: {self.device_path}")
        
        # Chercher le meilleur modem disponible
        modems = self.find_modems()
        ready_modems = [m for m in modems if m.get('status') == 'ready']
        
        if not ready_modems:
            raise SMSError("Aucun modem prêt trouvé")
        
        # Trier par qualité de signal (descendant)
        ready_modems.sort(key=lambda x: x.get('signal_quality', 0), reverse=True)
        return ready_modems[0]['id']
    
    def enable_sms(self, modem_id: str):
        """Active les SMS sur le modem"""
        try:
            result = subprocess.run(
                ['mmcli', '-m', modem_id, '--messaging-create-sms'],
                capture_output=True,
                text=True,
                timeout=30
            )
            
            if result.returncode != 0:
                logger.warning(f"Impossible d'activer les SMS: {result.stderr}")
            
        except subprocess.TimeoutExpired:
            logger.warning("Timeout lors de l'activation des SMS")
        except Exception as e:
            logger.warning(f"Erreur lors de l'activation des SMS: {str(e)}")
    
    def send_sms(self, recipient: str, message: str) -> Dict[str, Any]:
        """Envoie un SMS"""
        try:
            # Trouver le meilleur modem
            modem_id = self.get_best_modem()
            logger.info(f"Utilisation du modem {modem_id} pour envoyer SMS à {recipient}")
            
            # S'assurer que les SMS sont activés
            self.enable_sms(modem_id)
            
            # Créer le SMS
            create_result = subprocess.run([
                'mmcli', '-m', modem_id,
                '--messaging-create-sms',
                f'--messaging-create-sms-text={message}',
                f'--messaging-create-sms-number={recipient}'
            ], capture_output=True, text=True, timeout=30)
            
            if create_result.returncode != 0:
                raise SMSError(f"Erreur création SMS: {create_result.stderr}")
            
            # Extraire l'ID du SMS créé
            sms_id = None
            for line in create_result.stdout.split('\n'):
                if 'messaging/sms/' in line:
                    match = re.search(r'messaging/sms/(\d+)', line)
                    if match:
                        sms_id = match.group(1)
                        break
            
            if not sms_id:
                raise SMSError("Impossible d'obtenir l'ID du SMS créé")
            
            logger.info(f"SMS créé avec l'ID: {sms_id}")
            
            # Envoyer le SMS
            send_result = subprocess.run([
                'mmcli', '-s', sms_id, '--send'
            ], capture_output=True, text=True, timeout=60)
            
            if send_result.returncode != 0:
                # Tenter de supprimer le SMS créé
                try:
                    subprocess.run(['mmcli', '-m', modem_id, '--messaging-delete-sms', sms_id], 
                                 timeout=10, capture_output=True)
                except:
                    pass
                
                raise SMSError(f"Erreur envoi SMS: {send_result.stderr}")
            
            logger.info(f"SMS envoyé avec succès à {recipient}")
            
            # Nettoyer - supprimer le SMS de la mémoire du modem
            try:
                subprocess.run([
                    'mmcli', '-m', modem_id, 
                    '--messaging-delete-sms', sms_id
                ], timeout=10, capture_output=True)
            except:
                logger.warning(f"Impossible de supprimer le SMS {sms_id} de la mémoire")
            
            return {
                'success': True,
                'modem_id': modem_id,
                'sms_id': sms_id,
                'recipient': recipient,
                'message_length': len(message)
            }
            
        except subprocess.TimeoutExpired:
            raise SMSError("Timeout lors de l'envoi du SMS")
        except SMSError:
            raise
        except Exception as e:
            raise SMSError(f"Erreur inattendue: {str(e)}")

class SMSSender:
    """Classe principale pour l'envoi de SMS"""
    
    def __init__(self):
        self.modem_manager = None
    
    def send(self, recipient: str, message: str, device_path: str = None) -> Dict[str, Any]:
        """Envoie un SMS avec validation"""
        
        # Validation du destinataire
        if not self.validate_phone_number(recipient):
            raise SMSError(f"Numéro de téléphone invalide: {recipient}")
        
        # Validation du message
        if not message or not message.strip():
            raise SMSError("Message vide")
        
        if len(message) > 1600:  # Limite raisonnable pour éviter les très longs SMS
            raise SMSError(f"Message trop long: {len(message)} caractères")
        
        # Initialiser le gestionnaire de modems
        self.modem_manager = ModemManager(device_path)
        
        # Envoyer le SMS
        start_time = time.time()
        result = self.modem_manager.send_sms(recipient, message)
        end_time = time.time()
        
        result['send_duration'] = round(end_time - start_time, 2)
        result['timestamp'] = time.strftime('%Y-%m-%d %H:%M:%S')
        
        return result
    
    def validate_phone_number(self, phone: str) -> bool:
        """Valide un numéro de téléphone au format international"""
        # Supprimer les espaces et caractères spéciaux
        phone_clean = re.sub(r'[\s\-\(\)]', '', phone)
        
        # Vérifier le format international
        pattern = r'^\+[1-9]\d{6,14}$'
        return bool(re.match(pattern, phone_clean))
    
    def list_modems(self) -> List[Dict[str, Any]]:
        """Liste tous les modems disponibles"""
        self.modem_manager = ModemManager()
        return self.modem_manager.find_modems()

def main():
    """Fonction principale"""
    parser = argparse.ArgumentParser(
        description='Envoie des SMS via mmcli (ModemManager)',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Exemples d'utilisation:
  %(prog)s --recipient "+33612345678" --message "Hello World"
  %(prog)s --device "/dev/ttyUSB0" --recipient "+33612345678" --message "Test"
  %(prog)s --list-modems
        """
    )
    
    parser.add_argument('--recipient', '-r', 
                       help='Numéro de téléphone du destinataire (format international)')
    parser.add_argument('--message', '-m', 
                       help='Message à envoyer')
    parser.add_argument('--device', '-d',
                       help='Chemin du périphérique modem (ex: /dev/ttyUSB0)')
    parser.add_argument('--list-modems', '-l', action='store_true',
                       help='Lister tous les modems disponibles')
    parser.add_argument('--verbose', '-v', action='store_true',
                       help='Mode verbose')
    parser.add_argument('--json-output', action='store_true',
                       help='Sortie au format JSON')
    
    args = parser.parse_args()
    
    # Configuration du logging
    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    
    try:
        sender = SMSSender()
        
        if args.list_modems:
            # Lister les modems
            modems = sender.list_modems()
            
            if args.json_output:
                print(json.dumps({'modems': modems}, indent=2))
            else:
                print("Modems disponibles:")
                for modem in modems:
                    print(f"  ID: {modem['id']}")
                    print(f"  Périphérique: {modem.get('device_path', 'N/A')}")
                    print(f"  IMEI: {modem.get('imei', 'N/A')}")
                    print(f"  Opérateur: {modem.get('operator', 'N/A')}")
                    print(f"  Signal: {modem.get('signal_quality', 'N/A')}%")
                    print(f"  Statut: {modem.get('status', 'N/A')}")
                    print()
            
            sys.exit(0)
        
        # Vérifier les arguments obligatoires pour l'envoi
        if not args.recipient or not args.message:
            parser.error("--recipient et --message sont requis pour envoyer un SMS")
        
        # Envoyer le SMS
        logger.info(f"Envoi SMS à {args.recipient}")
        result = sender.send(args.recipient, args.message, args.device)
        
        if args.json_output:
            print(json.dumps(result, indent=2))
        else:
            print("SMS envoyé avec succès!")
            print(f"Destinataire: {result['recipient']}")
            print(f"Modem utilisé: {result['modem_id']}")
            print(f"Durée d'envoi: {result['send_duration']}s")
        
        sys.exit(0)
        
    except SMSError as e:
        if args.json_output:
            error_result = {
                'success': False,
                'error': str(e),
                'error_type': 'SMS_ERROR'
            }
            print(json.dumps(error_result))
        else:
            logger.error(f"Erreur SMS: {e}")
        sys.exit(1)
        
    except KeyboardInterrupt:
        logger.info("Interruption par l'utilisateur")
        sys.exit(1)
        
    except Exception as e:
        if args.json_output:
            error_result = {
                'success': False,
                'error': str(e),
                'error_type': 'SYSTEM_ERROR'
            }
            print(json.dumps(error_result))
        else:
            logger.error(f"Erreur système: {e}")
        sys.exit(1)

if __name__ == '__main__':
    main()