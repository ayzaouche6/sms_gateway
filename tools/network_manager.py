#!/usr/bin/env python3
"""
Script Python pour la gestion de la configuration réseau via netplan
Utilisé par l'application PHP SMS Gateway
"""

import sys
import argparse
import subprocess
import json
import logging
import os
import yaml
import shutil
import socket
from typing import Dict, Any, Optional, List

# Configuration du logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class NetworkError(Exception):
    """Exception personnalisée pour les erreurs réseau"""
    pass

class NetworkManager:
    """Gestionnaire de configuration réseau via netplan"""
    
    def __init__(self):
        self.netplan_path = '/etc/netplan/'
        self.config_file = None
        self.interface_name = None
        
    def get_network_interface(self) -> Dict[str, Any]:
        """Récupère l'interface réseau principale"""
        try:
            # Lister les interfaces réseau
            result = subprocess.run(
                ['ip', 'link', 'show'],
                capture_output=True,
                text=True,
                timeout=10
            )
            
            if result.returncode != 0:
                raise NetworkError(f"Erreur lors de la récupération des interfaces: {result.stderr}")
            
            interfaces = []
            for line in result.stdout.split('\n'):
                if ': ' in line and 'state UP' in line:
                    parts = line.split(': ')
                    if len(parts) >= 2:
                        interface_name = parts[1].split('@')[0]
                        # Ignorer loopback et interfaces virtuelles
                        if not interface_name.startswith(('lo', 'docker', 'br-', 'veth')):
                            interfaces.append(interface_name)
            
            if not interfaces:
                raise NetworkError("Aucune interface réseau active trouvée")
            
            # Prendre la première interface trouvée
            self.interface_name = interfaces[0]
            
            # Récupérer les détails de l'interface
            interface_info = self.get_interface_details(self.interface_name)
            
            return {
                'name': self.interface_name,
                'details': interface_info
            }
            
        except subprocess.TimeoutExpired:
            raise NetworkError("Timeout lors de la récupération des interfaces")
        except Exception as e:
            raise NetworkError(f"Erreur inattendue: {str(e)}")
    
    def get_interface_details(self, interface_name: str) -> Dict[str, Any]:
        """Récupère les détails d'une interface réseau"""
        try:
            # Récupérer les adresses IP
            result = subprocess.run(
                ['ip', 'addr', 'show', interface_name],
                capture_output=True,
                text=True,
                timeout=10
            )
            
            if result.returncode != 0:
                raise NetworkError(f"Erreur lors de la récupération des détails: {result.stderr}")
            
            details = {
                'ip_addresses': [],
                'mac_address': None,
                'status': 'down'
            }
            
            for line in result.stdout.split('\n'):
                line = line.strip()
                
                if 'state UP' in line:
                    details['status'] = 'up'
                elif line.startswith('link/ether'):
                    parts = line.split()
                    if len(parts) >= 2:
                        details['mac_address'] = parts[1]
                elif line.startswith('inet '):
                    parts = line.split()
                    if len(parts) >= 2:
                        details['ip_addresses'].append(parts[1])
            
            return details
            
        except subprocess.TimeoutExpired:
            raise NetworkError("Timeout lors de la récupération des détails")
        except Exception as e:
            raise NetworkError(f"Erreur lors de la récupération des détails: {str(e)}")
    
    def get_current_configuration(self) -> Dict[str, Any]:
        """Récupère la configuration netplan actuelle"""
        try:
            # Trouver le fichier de configuration netplan
            config_files = []
            if os.path.exists(self.netplan_path):
                for file in os.listdir(self.netplan_path):
                    if file.endswith('.yaml') or file.endswith('.yml'):
                        config_files.append(os.path.join(self.netplan_path, file))
            
            if not config_files:
                raise NetworkError("Aucun fichier de configuration netplan trouvé")
            
            # Prendre le premier fichier trouvé
            self.config_file = config_files[0]
            
            # Lire la configuration
            with open(self.config_file, 'r') as f:
                config = yaml.safe_load(f)
            
            # Extraire les informations réseau
            network_config = self.parse_netplan_config(config)
            
            # Ajouter les informations de l'interface actuelle
            interface_info = self.get_network_interface()
            network_config['current_interface'] = interface_info
            
            return network_config
            
        except Exception as e:
            raise NetworkError(f"Erreur lors de la lecture de la configuration: {str(e)}")
    
    def parse_netplan_config(self, config: Dict[str, Any]) -> Dict[str, Any]:
        """Parse la configuration netplan"""
        try:
            network = config.get('network', {})
            ethernets = network.get('ethernets', {})
            
            if not ethernets:
                raise NetworkError("Aucune configuration ethernet trouvée")
            
            # Prendre la première interface ethernet
            interface_name = list(ethernets.keys())[0]
            interface_config = ethernets[interface_name]
            
            addresses = interface_config.get('addresses', [])
            routes = interface_config.get('routes', [])
            nameservers = interface_config.get('nameservers', {})
            
            # Extraire les informations
            primary_ip = None
            secondary_ip = None
            subnet_mask = None
            gateway = None
            
            # Analyser les adresses
            for addr in addresses:
                if '192.168.' in addr or '172.' in addr:
                    primary_ip = addr.split('/')[0]
                    subnet_mask = addr.split('/')[1] if '/' in addr else '24'
                elif '10.0.0.' in addr:
                    secondary_ip = addr
            
            # Analyser les routes
            for route in routes:
                if route.get('to') == 'default':
                    gateway = route.get('via')
            
            # Analyser les DNS
            dns_servers = nameservers.get('addresses', [])
            dns_primary = dns_servers[0] if len(dns_servers) > 0 else None
            dns_secondary = dns_servers[1] if len(dns_servers) > 1 else None
            
            return {
                'interface_name': interface_name,
                'primary_ip': primary_ip,
                'secondary_ip': secondary_ip,
                'subnet_mask': subnet_mask,
                'gateway': gateway,
                'dns_primary': dns_primary,
                'dns_secondary': dns_secondary,
                'raw_config': config
            }
            
        except Exception as e:
            raise NetworkError(f"Erreur lors du parsing de la configuration: {str(e)}")
    
    def apply_configuration(self, primary_ip: str, subnet_mask: str, gateway: str, 
                          dns_primary: str, dns_secondary: str, secondary_ip: str) -> Dict[str, Any]:
        """Applique une nouvelle configuration réseau"""
        try:
            # Récupérer la configuration actuelle pour garder la structure
            current_config = self.get_current_configuration()
            interface_name = current_config['interface_name']
            
            # Créer la nouvelle configuration
            new_config = {
                'network': {
                    'version': 2,
                    'renderer': 'networkd',
                    'ethernets': {
                        interface_name: {
                            'addresses': [
                                f"{primary_ip}/{subnet_mask}",
                                secondary_ip
                            ],
                            'routes': [
                                {
                                    'to': 'default',
                                    'via': gateway
                                }
                            ],
                            'nameservers': {
                                'addresses': [dns_primary, dns_secondary]
                            }
                        }
                    }
                }
            }
            
            # Écrire la nouvelle configuration
            with open(self.config_file, 'w') as f:
                yaml.dump(new_config, f, default_flow_style=False, indent=2)
            
            # Appliquer la configuration
            result = subprocess.run(
                ['sudo', 'netplan', 'apply'],
                capture_output=True,
                text=True,
                timeout=30
            )
            
            if result.returncode != 0:
                raise NetworkError(f"Erreur lors de l'application de la configuration: {result.stderr}")
            
            return {
                'success': True,
                'message': 'Configuration appliquée avec succès',
                'output': result.stdout
            }
            
        except subprocess.TimeoutExpired:
            raise NetworkError("Timeout lors de l'application de la configuration")
        except Exception as e:
            raise NetworkError(f"Erreur lors de l'application: {str(e)}")
    
    def test_connectivity(self) -> Dict[str, Any]:
        """Test la connectivité réseau"""
        tests = {
            'gateway': False,
            'dns_primary': False,
            'dns_secondary': False,
            'internet': False
        }
        
        try:
            # Récupérer la configuration actuelle
            config = self.get_current_configuration()
            
            # Test de la passerelle
            if config['gateway']:
                tests['gateway'] = self.ping_host(config['gateway'])
            
            # Test des DNS
            if config['dns_primary']:
                tests['dns_primary'] = self.ping_host(config['dns_primary'])
            
            if config['dns_secondary']:
                tests['dns_secondary'] = self.ping_host(config['dns_secondary'])
            
            # Test de connectivité Internet
            tests['internet'] = self.ping_host('8.8.8.8')
            
            return {
                'tests': tests,
                'overall_status': all(tests.values())
            }
            
        except Exception as e:
            raise NetworkError(f"Erreur lors du test de connectivité: {str(e)}")
    
    def ping_host(self, host: str, timeout: int = 5) -> bool:
        """Ping un hôte pour tester la connectivité"""
        try:
            result = subprocess.run(
                ['ping', '-c', '1', '-W', str(timeout), host],
                capture_output=True,
                text=True,
                timeout=timeout + 2
            )
            return result.returncode == 0
        except:
            return False
    
    def backup_configuration(self, backup_file: str) -> Dict[str, Any]:
        """Sauvegarde la configuration actuelle"""
        try:
            if not self.config_file:
                # Trouver le fichier de configuration
                self.get_current_configuration()
            
            # Copier le fichier de configuration
            shutil.copy2(self.config_file, backup_file)
            
            return {
                'success': True,
                'message': 'Sauvegarde créée avec succès',
                'file': backup_file
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': f"Erreur lors de la sauvegarde: {str(e)}"
            }
    
    def restore_configuration(self, backup_file: str) -> Dict[str, Any]:
        """Restaure une configuration depuis une sauvegarde"""
        try:
            if not os.path.exists(backup_file):
                raise NetworkError(f"Fichier de sauvegarde non trouvé: {backup_file}")
            
            if not self.config_file:
                self.get_current_configuration()
            
            # Restaurer le fichier
            shutil.copy2(backup_file, self.config_file)
            
            # Appliquer la configuration
            result = subprocess.run(
                ['sudo', 'netplan', 'apply'],
                capture_output=True,
                text=True,
                timeout=30
            )
            
            if result.returncode != 0:
                raise NetworkError(f"Erreur lors de l'application: {result.stderr}")
            
            return {
                'success': True,
                'message': 'Configuration restaurée avec succès',
                'output': result.stdout
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': f"Erreur lors de la restauration: {str(e)}"
            }

def main():
    """Fonction principale"""
    parser = argparse.ArgumentParser(
        description='Gestionnaire de configuration réseau via netplan'
    )
    
    parser.add_argument('--action', required=True,
                       choices=['get_config', 'get_interface', 'apply_config', 
                               'test_connectivity', 'backup_config', 'restore_config'],
                       help='Action à effectuer')
    
    # Paramètres pour apply_config
    parser.add_argument('--primary-ip', help='Adresse IP primaire')
    parser.add_argument('--subnet-mask', help='Masque de sous-réseau')
    parser.add_argument('--gateway', help='Passerelle par défaut')
    parser.add_argument('--dns-primary', help='DNS primaire')
    parser.add_argument('--dns-secondary', help='DNS secondaire')
    parser.add_argument('--secondary-ip', help='Adresse IP secondaire')
    
    # Paramètres pour backup/restore
    parser.add_argument('--backup-file', help='Fichier de sauvegarde')
    
    parser.add_argument('--verbose', '-v', action='store_true', help='Mode verbeux')
    
    args = parser.parse_args()
    
    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    
    try:
        manager = NetworkManager()
        
        if args.action == 'get_config':
            result = manager.get_current_configuration()
            print(json.dumps({'success': True, 'data': result}, indent=2))
            
        elif args.action == 'get_interface':
            result = manager.get_network_interface()
            print(json.dumps({'success': True, 'data': result}, indent=2))
            
        elif args.action == 'apply_config':
            if not all([args.primary_ip, args.subnet_mask, args.gateway, 
                       args.dns_primary, args.dns_secondary, args.secondary_ip]):
                raise NetworkError("Tous les paramètres de configuration sont requis")
            
            result = manager.apply_configuration(
                args.primary_ip, args.subnet_mask, args.gateway,
                args.dns_primary, args.dns_secondary, args.secondary_ip
            )
            print(json.dumps(result, indent=2))
            
        elif args.action == 'test_connectivity':
            result = manager.test_connectivity()
            print(json.dumps({'success': True, 'data': result}, indent=2))
            
        elif args.action == 'backup_config':
            if not args.backup_file:
                raise NetworkError("Fichier de sauvegarde requis")
            
            result = manager.backup_configuration(args.backup_file)
            print(json.dumps(result, indent=2))
            
        elif args.action == 'restore_config':
            if not args.backup_file:
                raise NetworkError("Fichier de sauvegarde requis")
            
            result = manager.restore_configuration(args.backup_file)
            print(json.dumps(result, indent=2))
        
        sys.exit(0)
        
    except NetworkError as e:
        error_result = {
            'success': False,
            'error': str(e),
            'error_type': 'NETWORK_ERROR'
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