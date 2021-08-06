Version 2.3 

Just an functional example for reference. 
Add your connectivity credentials (.cer,.key and pwd) for WRU - they have to be whitelisted for Test-Result AND DCC-Proxy 
Please mind to use the libraries CBOREncode-master and PHPSecLib (Version 2). The VIZ - MRT conversion function is beta and just an example. 

Howto use: 
1) Execute with php test_dcc.php 
2) Scan the cwa link (e.g. generate a QR with a QR-Generator) and request a certificate via CWA 
3) Press ENTER to trigger the DCC generation by script, backend and CWA
