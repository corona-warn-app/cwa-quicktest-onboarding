from time import sleep
import logging
from argparse import ArgumentParser
from os import urandom as random_bytes # This is test, we don't need strong crypto
import random
import string
from cryptography.hazmat.primitives.serialization import load_pem_public_key
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.primitives import padding, hashes
from cryptography.hazmat.primitives.asymmetric import padding as asym_padding
from cryptography.hazmat.backends import default_backend
from hashlib import sha256
import json
import cbor2
import requests
from base64 import b64encode
from binascii import hexlify
from datetime import datetime
from time import time, sleep





class LabSimulator:

    def __init__(self, config):
        self._config = config

    def respond_to_dgc_request( self, testId, dcci, publicKeyStr ):
        """ Beantwortet einen DCC-Antrag
            und ruft dazu handle_dgc_request auf"""
        payload = self.handle_dgc_request( testId, dcci, publicKeyStr )
        response = requests.post(   url=f'{self._config["dcc-endpoint"]}/version/v1/test/{testId}/dcc', 
                                    cert=self._config["dcc-client-cert"],
                                    json=payload ) 
        logging.info(f'Upload encrypted data: TestID: {testId} Status Code: {response.status_code}')   


    def handle_dgc_request( self, testId, dcci, publicKeyStr ):
        "Erstellt eine Antwort auf einen DCC-Antrag"

        # Zufälligen Schlüssel erzeugen
        # (Achtung! Pseudo-Zufallszahlen! Dies ist nur zum Testen)
        dek = random_bytes(32)
        
        # Payload aus testresults-Verzeichnis übernehmen oder zufällige Payload erzeugen
        try:
            with open(f"testresults/{testId}.json",encoding='utf-8') as resultfile:
                dcc_data = json.load(resultfile)
            logging.info(f"Loaded test result from file {testId}.json")
        except:
            logging.info("Using random negative test result")
            dcc_data = self._random_dgc_data() 
            dcc_data['t'][0]['ci'] = dcci

        logging.info(f'DCC-DATA = {dcc_data}')
        
        # Daten CBOR-kodieren
        cbor_data = self.dcc_cbor(dcc_data)
        # Konstante: COSE protected header (für den Hash)
        protected_header = cbor2.dumps({1:-7})   
        # CBOR-Daten, die signiert werden: Protected header und Payload
        cbor_to_sign = cbor2.dumps(["Signature1",protected_header, b"", cbor_data] )
        # Es wird AES im CBC Modus mit einem IV von 16 0-Bytes verwendet
        cipher = Cipher(algorithms.AES(dek),modes.CBC( b'\x00'*16), default_backend() ).encryptor()
        # Die Daten werden nach PKCS7 gepaddet
        padder = padding.PKCS7(128).padder()
        padded_data = padder.update(cbor_data) + padder.finalize()
        # Die Daten mit Padding werden verschlüsselt
        encrypted_data = cipher.update(padded_data) + cipher.finalize()
        # Die Daten (ohne Padding) werden SHA-256 gehasht
        hasher = sha256()
        hasher.update( cbor_to_sign )
        hex_hash = hexlify(hasher.digest())
        # Der symmetrische Schlüssel wird mit dem Public Key verschlüselt
        encrypted_key = self._encrypt_dek_with_public_key(dek, publicKeyStr)

        return {
            "dataEncryptionKey": b64encode(encrypted_key).decode('utf-8'), # encrypted DEK as base64
            "encryptedDcc": b64encode(encrypted_data).decode('utf-8'), # encrypted DCC material as base64
            "dccHash": hex_hash.decode('utf-8') # DCC hash as hex
        }


    def dcc_cbor(self, certData, issuedAtTimestamp=None, expiredAfterSeconds=None ):
        if issuedAtTimestamp is None: 
            issuedAtTimestamp = int(time())     # Wenn nichts angegeben, dann jetziger Zeitpunkt
        if expiredAfterSeconds is None:
            expiredAfterSeconds = 60 * 60 * 24  # Wenn nichts angegeben, 1 Tag Gültigkeit

        cborMap = {}
        cborMap[4] = issuedAtTimestamp + expiredAfterSeconds
        cborMap[6] = issuedAtTimestamp
        cborMap[1] = 'DE'
        cborMap[-260] = {1: certData}
        return cbor2.dumps(cborMap)


    def _encrypt_dek_with_public_key( self, dek, publicKeyStr ):
        """Verschlüsselt den symmetrischen DEK mit dem publicKey
            dek: binär
            publicKeyStr: Base64 kodiertes DER
            """
        publicKey = load_pem_public_key( self._wrap_public_key(publicKeyStr) , default_backend() )
        encrypted_key = publicKey.encrypt(dek,
            asym_padding.OAEP(
                mgf=asym_padding.MGF1(algorithm=hashes.SHA256()),
                algorithm=hashes.SHA256(),
                label=None
            )
        )
        return encrypted_key


    def _wrap_public_key(self, key):
        "Base64 kodiertes DER + BEGIN/END-Markierungen ergibt PEM"
        return ('-----BEGIN PUBLIC KEY-----\n' + key + '\n-----END PUBLIC KEY-----').encode('utf-8')

    def _random_dgc_data(self):
        data = self._config["dcc-template"].copy()
        fn = ''.join(random.choices(string.ascii_lowercase, k=random.randint(5,15))).capitalize()
        gn = ''.join(random.choices(string.ascii_lowercase, k=random.randint(5,15))).capitalize()

        data['nam']['fn'] = fn
        data['nam']['fnt'] = fn.upper()
        data['nam']['gn'] = gn
        data['nam']['gnt'] = gn.upper()
        data['dob'] = str(random.randint(1930,2000))+"-0"+str(random.randint(1,9))+"-"+str(random.randint(10,28))
        data['t'][0]['sc'] = datetime.fromtimestamp(datetime.utcnow().timestamp()-600).isoformat(timespec='seconds')+'Z'
        if 'dr' in data['t'][0]: # Abwärtskompatibilität mit Schema Version 1.0.0
            data['t'][0]['dr'] = datetime.fromtimestamp(datetime.utcnow().timestamp()-300).isoformat(timespec='seconds')+'Z'

        return data

    def run(self):
        logging.info(f'Endpoint: ' + self._config["dcc-endpoint"] )
        logging.info(f'Lab ID: {self._config["lab-ID"]}')

        while True: 
            response = requests.get( self._config["dcc-endpoint"]+'/version/v1/publicKey/search/'+self._config["lab-ID"] , 
                                     cert=self._config["dcc-client-cert"]) 
            logging.info( f'Polling response status code: {response.status_code} Length: {len(response.text)}')
            if args.dry_run: 
                logging.warning('Dry run: Will not upload DCC')
                with open('dry_run.txt','a') as dry_run_file:
                    for dcc_request in response.json():
                        dry_run_file.write("INPUT: " + json.dumps(dcc_request)+"\n")
                        dry_run_file.write("OUTPUT: " + json.dumps(self.handle_dgc_request( dcc_request['testId'], dcc_request['dcci'], dcc_request['publicKey'])) +"\n\n")
                logging.warning('Exiting')
                break 


            for dcc_request in response.json():
                logging.info(f'Received DCC request: {dcc_request}')
                try: 
                    self.respond_to_dgc_request( dcc_request['testId'], dcc_request['dcci'], dcc_request['publicKey'])
                except Exception as e: 
                    logging.error(e)
            sleep(self._config["polling-period"])
    
def main(args):
    config = json.load( open(args.config_file, encoding='utf-8' ))
    simulator = LabSimulator(config)
    simulator.run()



if __name__ == '__main__':
    try: 
        import coloredlogs
        coloredlogs.install()
    except:
        pass # If we don't have colored logs, it's not important

    logging.basicConfig(level=logging.INFO, format='%(asctime)s %(name)-12s %(levelname)-8s %(message)s')

    parser = ArgumentParser(description='''
Simulator for Covid-Test Center or Lab''')

    parser.add_argument('-f', '--config-file', default='config.json', help='Configuration file')
    parser.add_argument( '--dry-run', action='store_true', help='Do not upload DCCs but write to dry_run.txt')
    args = parser.parse_args()
    logging.info("Starting DCC lab simulator")
    main(args)
