''' Usage: python offline_check.py [input file]
    --> File will be modified

    There's no error handling. Exceptions will crash the program intentionally

    Example input file: 

    {
        "INPUT" : {
            "publicKey" : "MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEA+E2RG2ukM089RGBX7q3sbvB70l5yTmH8oNssskBSMkbbFVMFtWOh7Azi0QUUY5VmMqdxzR4OMnNe2AKvjFGHPWjLg0O9+9YjVSyvGS5iAvpX9QmM3O35UvdIcEX4FltoLhMHpvoYG38s0aPqtQZxqnNyjpjNJlRYeWjSqnSjtIb1Jkh/w6nH0itLoZB3ItEyoI4Kd5uMS6dtUd4LnA2RH9YYqN53VEAuDYOzMPh6T1k4bvlv6dtsxERqpzlomSL/XCH0IKfGH3eCWNxGTiTXMCdaysN93MZjd726jXd+qnMRiYchrOxy7WCBgpufhGGovs4XuzGLjElAfc+XnLHIdELkisoH6e9MhniNDAotTiS2o3IsO6+k/J58JhlpTzUoXvB6ANrj/fw+Sj+/2MfNzkxxu2NcCpzy5QZmhcXYK5yFPn8C0CLsK1UdlbbgkWRQ+ntyGi8Zwm4OUBRYiy1qUpG6LdwtOaSRHGo7sQX6wD6nunAHMKTj+qhxp8ygKM9RAgMBAAE=",
            "testId" : "05fc279242122a96363cbf0cd2aa51e61926963a3d7692db9ce81bc97141709d", 
            "dek" : "000102030405060708090A0B0C0D0E0F101112131415161718191A1B1C1D1E1F", 
            "issuedAt" : 1623765022, 
            "validUntil" : 1623851422,
            "country" : "DE",            
            "certContents" : {
                "ver": "1.2.0",
                "nam": {
                    "fn": "Meier-Müller",
                    "fnt": "MEIER<MUELLER",
                    "gn": "Max",
                    "gnt": "MAX"
                },
                "dob": "1999-09-09",
                "t": [
                    {
                        "tg": "840539006",
                        "tt": "LP6464-4",
                        "sc": "2021-06-01T12:34:56Z",
                        "tr": "260415000",
                        "tc": "Beispiel Test Center",
                        "co": "DE",
                        "is": "FTA DCC Issuance",
                        "ci": "urn:uvci:01:DE:123456789012345678901234567890123"
                    }
                ]
            }
        },        
        "OUTPUT" : "Will be overwritten and doesn't matter if it's actually there",
        "STEPS" :  " ^ same as above"
    }
    
'''
import logging
from argparse import ArgumentParser
import string
from cryptography.hazmat.primitives.serialization import load_pem_public_key
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.primitives import padding, hashes
from cryptography.hazmat.primitives.asymmetric import padding as asym_padding
from hashlib import sha256
import json
import cbor2
import requests
from base64 import b64encode


class OfflineCheck:
    def __init__(self):
        self.step_no = 0
        self.step_log = {}

    def main(self, args):
        logging.info(f'Loading file {args.file}')

        with open(args.file, encoding='utf-8') as inputfile: 
            data = json.load(inputfile)
            
        cbor_data = cbor2.dumps({
            4 : data['INPUT']['validUntil'],
            6 : data['INPUT']['issuedAt'],
            1 : data['INPUT']['country'],
            -260 : {1:data['INPUT']['certContents']}        
        })
        self.log_step('cbor_data', cbor_data.hex())

        protected_header = cbor2.dumps({1:-7})   
        self.log_step('protected_header', protected_header.hex())

        cbor_to_sign = cbor2.dumps(["Signature1",protected_header, b"", cbor_data] )
        self.log_step('cbor_to_sign', cbor_to_sign.hex())


        padder = padding.PKCS7(128).padder()
        padded_data = padder.update(cbor_data) + padder.finalize()
        self.log_step('padded_data', padded_data.hex())

        dek = bytes.fromhex(data['INPUT']['dek'])
        cipher = Cipher(algorithms.AES(dek),modes.CBC( b'\x00'*16) ).encryptor()
        encrypted_data = cipher.update(padded_data) + cipher.finalize()
        self.log_step('encrypted_data', encrypted_data.hex())

        hasher = sha256()
        hasher.update( cbor_to_sign )
        hex_hash = hasher.digest().hex()
        self.log_step('hex_hash', hex_hash)

        encrypted_key = self._encrypt_dek_with_public_key(dek, data['INPUT']['publicKey'])
        self.log_step('encrypted_key', encrypted_key.hex())

        data['STEPS'] = self.step_log
        data['OUTPUT'] = {
            "dataEncryptionKey": b64encode(encrypted_key).decode('utf-8'), # encrypted DEK as base64
            "encryptedDcc": b64encode(encrypted_data).decode('utf-8'), # encrypted DCC material as base64
            "dccHash": hex_hash # DCC hash as hex
        }

        json_out = json.dumps(data, indent=4, ensure_ascii=False)
        with open(args.file, 'w', encoding='utf-8') as outfile: 
            outfile.write(json_out)


    def _encrypt_dek_with_public_key( self, dek, publicKeyStr ):
        publicKey = load_pem_public_key( self._wrap_public_key(publicKeyStr) )
        encrypted_key = publicKey.encrypt(dek,
            asym_padding.OAEP(
                mgf=asym_padding.MGF1(algorithm=hashes.SHA256()),
                algorithm=hashes.SHA256(),
                label=None
            )
        )
        return encrypted_key

    def _wrap_public_key(self, key):
        return ('-----BEGIN PUBLIC KEY-----\n' + key + '\n-----END PUBLIC KEY-----').encode('utf-8')

    def log_step(self, name, value):
        logging.info( f'{name}: {value}')
        self.step_no += 1
        self.step_log[f'{self.step_no:02d}_{name}'] = value


if __name__ == '__main__':
    try: 
        import coloredlogs
        coloredlogs.install()
    except:
        pass # If we don't have colored logs, it's not important

    logging.basicConfig(level=logging.INFO, format='%(asctime)s %(name)-12s %(levelname)-8s %(message)s')

    parser = ArgumentParser(description='Offline Lab Simulator')

    parser.add_argument('file', help='Input file')
    args = parser.parse_args()
    oc = OfflineCheck()
    oc.main(args)
