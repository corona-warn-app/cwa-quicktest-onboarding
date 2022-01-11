<?php

/* CBOREncode-master Library */
use CBOR\Decoder;
use CBOR\ListObject;
use CBOR\MapObject;
use CBOR\ByteStringObject;
use CBOR\TextStringObject;

/* connectivity credentials - please enter your files and password */
$certfile = 'YOUR.cer';
$keyfile = 'YOUR.key';
$pwd = 'YOUR-PASSWORD';


/* e.g. ö --> Ö */
setlocale (LC_ALL, 'de_DE@euro', 'de_DE', 'de', 'ge');

// Some constants
$validity = 60 * 60 * 24 * 2;

$fn = trim(readline('Enter a first name or press ENTER to use random value: '));
if (!strlen($fn))
{
	$fn = generateRandomString(7);
	print("Using first name: $fn\n");
}

$ln = trim(readline('Enter a last name or press ENTER to use random value: '));
if (!strlen($ln))
{
	$ln = generateRandomString(6);
	print("Using last name: $ln\n");
}
$rand_number = mt_rand(631152000,1262055681);
$dob = date("Y-m-d",$rand_number);
$testid = uniqid();
$timestamp = time();
$salt = strtoupper( bin2hex( random_bytes(16) ) );
$data = $dob . "#" . $fn . "#" . $ln . "#" . $timestamp . "#" . $testid . "#" . $salt;
$hash = hash( 'sha256', $data );
$labid = 'mdf00004';

$data_arr = array(
	'fn' => $fn,
	'ln' => $ln,
	'dob' => $dob,
	'timestamp' => $timestamp,
	'testid' => $testid,
	'salt' => $salt,
	'hash' => $hash,
	'dgc' => true

);

print("\n");print_r($data_arr);print("\n");

$data_json = json_encode( $data_arr );
print("\n");print_r($data_json);print("\n");
$url1 = 'https://s.coronawarn.app?v=1#' . base64url_encode( $data_json );
print("\n");print_r($url1);print("\n");

// Check if running in Linux and use qrencode if possible
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')
{
	exec("command -v qrencode", $out, $ret);

	if ($ret == 0)
	{
		exec("qrencode -o qrcode.png $url1");
		exec("xdg-open qrcode.png >/dev/null 2>/dev/null &");
	}
	else
	{
		print("qrencode not found. Use URL to create QR code\n");
	}
}

$test_results = array(
	'testResults' => array(
		array(
			'id' => $hash,
			'result' => 6
		)
	),
	'labId' => $labid
);

$test_results = json_encode( $test_results );

print("\n");print_r($test_results);print("\n");

print("\n Press ENTER to set test result\n");
fgets(STDIN);

$url = 'https://quicktest-result-cff4f7147260.coronawarn.app/api/v1/quicktest/results';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
curl_setopt($ch, CURLOPT_SSLCERT, $certfile);
curl_setopt($ch, CURLOPT_SSLKEY, $keyfile);
curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $pwd);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt( $ch, CURLOPT_POSTFIELDS, $test_results );
curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
curl_setopt( $ch, CURLOPT_VERBOSE, 1); 

print("\n");

$result = curl_exec($ch);
$error = curl_error($ch);

print("\n");print_r($result);print("\n");
print("\n");print_r($error);print("\n");


//Warten auf Enter 
print("\n Press ENTER to continue with DCC processing\n");
fgets(STDIN); 

// Start DCC Part here
print("\n Processing DCC\n");

//print("\n result");print_r($result);print("\n");
$jsonArrayResponse = searchTest($labid, $hash, $certfile, $keyfile, $pwd);
//print("\n Array");print_r($jsonArrayResponse);print("\n");


// assemble HCERT JSON as base for encryptedDcc 


// 2tvenom CBOREncode
include "CBOREncode-master/src/CBOR/CBOREncoder.php";
include "CBOREncode-master/src/CBOR/Types/CBORByteString.php"; 

$values = array(
    1 => "DE",
    4 => ((int)($timestamp + $validity)),	// Important convert timestamp to int, this is for sample collection time
    6 => ((int)time()), 			// Important convert timestamp to int, this is for DCC request Time
    -260 => array(
        1 => array(
            "t" => array(
                0 => array(
                    "ci" => (string)$dcci,
                    "co" => "DE",
                    "is" => "Robert Koch-Institut",
                    "tg" => "840539006",
                    "tt" => "LP217198-3",
                    "sc" => (string)date("Y-m-d\TH:i:s\Z", $timestamp), // Important you need Zulu Timestamp
                    "tr" => "260415000",
                    "tc" => "MDF Testzentrum (1234)",
                    "ma" => "2098"
                )
            ),
            "dob"=>(string)$dob,
            "nam"=> array(
                "fn"=> $ln,
                "fnt"=> convertAccentsAndSpecialToICAONormal(mb_strtoupper($ln,"UTF-8")),
                "gn"=> $fn,
                "gnt"=> convertAccentsAndSpecialToICAONormal(mb_strtoupper($fn,"UTF-8"))
            ),
            "ver" => "1.3.0"
        )
    )
);

// HCERT CBOR 

$encoded_data = \CBOR\CBOREncoder::encode($values);
$byte_arr = unpack("C*", $encoded_data);
$cbor_hcert= implode("", array_map(function ($byte) {
        if (strlen(strtoupper(dechex($byte))) == 1)
            return "0" . strtoupper(dechex($byte));
        return "" . strtoupper(dechex($byte));

    }, $byte_arr));
	
echo "CBOR: ".$cbor_hcert.PHP_EOL;

// Assemble COSE structure for dccHash calculation only   

// Creates CBOR for the signing object for the dccHash calculation only 
// Since the CBOR prefix coding is always the same, just add the CBOR of the HCERT
// Attention: This just an example - has to be adapted if CBOR HEX > 255 Byte (0xFF)!

echo "CBOR Lenght HCERT: ".dechex(strlen($cbor_hcert)/2).PHP_EOL;
echo "CBOR: ".$cbor_hcert.PHP_EOL;

$byteCount = strlen(dechex(strlen($cbor_hcert)/2));
echo "byteCount: ".$byteCount.PHP_EOL;

if ( $byteCount == 1 )
{
		$cbor_cose = '846A5369676E61747572653143A1012640580' . dechex(strlen($cbor_hcert)/2) . $cbor_hcert;
}
else if ( $byteCount == 2 )
{
		$cbor_cose = '846A5369676E61747572653143A101264058' . dechex(strlen($cbor_hcert)/2) . $cbor_hcert;	
}
else if ( $byteCount == 3 )
{
		$cbor_cose = '846A5369676E61747572653143A1012640590' . dechex(strlen($cbor_hcert)/2) . $cbor_hcert;
	
}
else if ( $byteCount == 4 )
{
		$cbor_cose = '846A5369676E61747572653143A101264059' . dechex(strlen($cbor_hcert)/2) . $cbor_hcert;	
}
else 
{
	echo "Unexpeted Error EXITING - CBOR TOO LONG".PHP_EOL;
	exit; 
}	

echo "CBOR for Hash: ".$cbor_cose.PHP_EOL;

$dccHashHex = openssl_digest(hexToStr($cbor_cose), "sha256", false);

echo "Hash: " . $dccHashHex . PHP_EOL; 

// encypt CBOR of HCERT 
// generate a 32 Byte Key
$dek = bin2hex(random_bytes(32)); 
echo "AES key: " . $dek . PHP_EOL;
$iv = str_repeat("0",32);
// encrypt DCC with AES256 (CBC/PKSC5Padding) 
$encryptedDcc = base64_encode(openssl_encrypt(hexToStr($cbor_hcert),"AES-256-CBC",hexToStr($dek),OPENSSL_RAW_DATA,hexToStr($iv)));
echo "encryptedDCC = " . $encryptedDcc . PHP_EOL; 

// encrypt DEK with Public Key 

// PHPSecLib Version 2 

set_include_path(get_include_path() . PATH_SEPARATOR . 'phpseclib');

include('phpseclib/Net/SSH2.php');
include('phpseclib/Net/SFTP.php');
include('phpseclib/Crypt/RSA.php');
include('phpseclib/Crypt/Random.php');
include('phpseclib/Math/BigInteger.php');
include('phpseclib/Crypt/Hash.php');

use phpseclib\Crypt\RSA;
use phpseclib\Crypt\Random;
use phpseclib\Crypt\Common;
use phpseclib\Net\SFTP;

$rsaObj = new \phpseclib\Crypt\RSA();


$rsaObj->loadKey($publicKey);
$rsaObj->setMGFHash('sha256');
$rsaObj->setHash('sha256');
$rsaObj->setEncryptionMode(phpseclib\Crypt\RSA::ENCRYPTION_OAEP);

$dataEncryptionKey = base64_encode($rsaObj->encrypt(hexToStr($dek), phpseclib\Crypt\RSA::ENCRYPTION_OAEP));

echo "dataEncryptionKey: " . $dataEncryptionKey . PHP_EOL;

// Send DCC Data to Proxy 

$dcc_json = array(
		'dccHash' => $dccHashHex,
		'encryptedDcc' => $encryptedDcc,
		'dataEncryptionKey' => $dataEncryptionKey
);

$dcc_json = json_encode( $dcc_json );

print("\n");print_r($dcc_json);print("\n");

// Before you can retrieve the DCC, the test must have been imported into the system and scanned by a smartphone,
// only then will the test appear in the search and also only then can a DCC be requested.

$url = 'https://dcc-proxy-cff4f7147260.coronawarn.app/version/v1/test/'. $testId . '/dcc';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
curl_setopt($ch, CURLOPT_SSLCERT, $certfile);
curl_setopt($ch, CURLOPT_SSLKEY, $keyfile);
curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $pwd);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt( $ch, CURLOPT_POSTFIELDS, $dcc_json );
curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
curl_setopt( $ch, CURLOPT_VERBOSE, 1); 

print("\n");

$result = curl_exec($ch);
$error = curl_error($ch);

print("\n");print_r($result);print("\n");
print("\n");print_r($error);print("\n");


// FUNCTIONS 

function base64url_encode($data) {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateRandomString($length = 10) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function strToHex($string)
{
	$hex='';
	for ($i=0; $i < strlen($string); $i++)
	{
	    $hex .= dechex(ord($string[$i]));
	}
	return $hex;
}

function hexToStr($hex)
{
	$string='';
	for ($i=0; $i < strlen($hex)-1; $i+=2)
	{
		if ( $hex[$i] == ' ') continue;
		$string .= chr(hexdec($hex[$i].$hex[$i+1]));
	}
	return $string;
}



/**
 * Replaces special characters in a string with their "non-special" counterpart.
 * The function is an example only and not completely tested, please perform further tests and 
 * changes to meet the requirements of VIZ to MRT conversion
 *
 * See See 9303_p3 Section 6 - A, B and C (04.08.2021) ICAO Eighth Edition 2021
 * Conversion of arabic Numbers to Roman numbers from 1-9 only
 * fn or ln should NOT contain arabic numbers! - See Definition of VIZ - anyhow, some Numbers are mapped below.
 * Known problems: arabic (Section C) without shadda (double) and teh marbuta (end) handling ... 
 *
 * @param string
 * @return string
 */
function convertAccentsAndSpecialToICAONormal($string) {
    $table = array(
'1'=>'I',
'2'=>'II',
'3'=>'III',
'4'=>'IV',
'5'=>'V',
'6'=>'VI',
'7'=>'VII',
'8'=>'VIII',
'9'=>'IX',
' '=>'<',
'-'=>'<',
'\''=>'',
','=>'',
':'=>'',
';'=>'',
'.'=>'',
'À'=>'A',
'Á'=>'A',
'Â'=>'A',
'Ã'=>'A',
'Ä'=>'AE',
'Å'=>'AA',
'Æ'=>'AE',
'Ç'=>'C',
'È'=>'E',
'É'=>'E',
'Ê'=>'E',
'Ë'=>'E',
'Ì'=>'I',
'Í'=>'I',
'Î'=>'I',
'Ï'=>'I',
'Ð'=>'D',
'Ñ'=>'N',
'Ò'=>'O',
'Ó'=>'O',
'Ô'=>'O',
'Õ'=>'O',
'Ö'=>'OE',
'Ø'=>'OE',
'Ù'=>'U',
'Ú'=>'U',
'Û'=>'U',
'Ü'=>'UE',
'Ý'=>'Y',
'Þ'=>'TH',
'Ā'=>'A',
'Ă'=>'A',
'Ą'=>'A',
'Ć'=>'C',
'Ĉ'=>'C',
'Ċ'=>'C',
'Č'=>'C',
'Ď'=>'D',
'Ð'=>'D',
'Ē'=>'E',
'Ĕ'=>'E',
'Ė'=>'E',
'Ę'=>'E',
'Ě'=>'E',
'Ĝ'=>'G',
'Ğ'=>'G',
'Ġ'=>'G',
'Ģ'=>'G',
'Ĥ'=>'H',
'Ħ'=>'H',
'Ĩ'=>'I',
'Ī'=>'I',
'Ĭ'=>'I',
'Į'=>'I',
'İ'=>'I',
'I'=>'I',
'Ĳ'=>'IJ',
'Ĵ'=>'J',
'Ķ'=>'K',
'Ĺ'=>'L',
'Ļ'=>'L',
'Ľ'=>'L',
'Ŀ'=>'L',
'Ł'=>'L',
'Ń'=>'N',
'Ņ'=>'N',
'Ň'=>'N',
'Ŋ'=>'N',
'Ō'=>'O',
'Ŏ'=>'O',
'Ő'=>'O',
'Œ'=>'OE',
'Ŕ'=>'R',
'Ŗ'=>'R',
'Ř'=>'R',
'Ś'=>'S',
'Ŝ'=>'S',
'Ş'=>'S',
'Š'=>'S',
'Ţ'=>'T',
'Ť'=>'T',
'Ŧ'=>'T',
'Ũ'=>'U',
'Ū'=>'U',
'Ŭ'=>'U',
'Ů'=>'U',
'Ű'=>'U',
'Ų'=>'U',
'Ŵ'=>'W',
'Ŷ'=>'Y',
'Ÿ'=>'Y',
'Ź'=>'Z',
'Ż'=>'Z',
'Ž'=>'Z',
'ẞ'=>'SS',
'Ё'=>'E',
'Ћ'=>'D',
'Є'=>'IE',
'Ѕ'=>'DZ',
'І'=>'I ',
'Ї'=>'I',
'Ј'=>'J',
'Љ'=>'LJ',
'Њ'=>'NJ',
'Ќ'=>'K',
'ў'=>'U',
'Џ'=>'DZ',
'А'=>'A',
'Б'=>'B',
'В'=>'V',
'Г'=>'G',
'Д'=>'D',
'Е'=>'E',
'Ж'=>'ZH',
'З'=>'Z',
'И'=>'I',
'Й'=>'I',
'К'=>'K',
'Л'=>'L',
'М'=>'M',
'Н'=>'N',
'О'=>'O',
'П'=>'P',
'Р'=>'R',
'С'=>'S',
'Т'=>'T',
'У'=>'U',
'Ф'=>'F',
/* Table C */
'ء'=>'XE',
'آ'=>'XAA',
'أ'=>'XAE',
'ؤ'=>'U',
'إ'=>'I',
'ئ'=>'XI',
'ا'=>'A',
'ب'=>'B',
'ة'=>'XTA',
'ت'=>'T',
'ث'=>'XTH',
'ج'=>'J',
'ح'=>'XH',
'خ'=>'XKH',
'د'=>'D',
'ذ'=>'XDH',
'ر'=>'R',
'ز'=>'Z',
'س'=>'S',
'ش'=>'XSH',
'ص'=>'XSS',
'ض'=>'XDZ',
'ط'=>'XTT',
'ظ'=>'XZZ',
'ع'=>'E',
'غ'=>'G',
'ف'=>'F',
'ق'=>'Q',
'ك'=>'K',
'ل'=>'L',
'م'=>'M',
'ن'=>'N',
'ه'=>'H',
'و'=>'W',
'ى'=>'XAY',
'ي'=>'Y',
'ٱ'=>'XXA',
'ٹ'=>'XXT',
'ټ'=>'XRT',
'پ'=>'P',
'ځ'=>'XKE',
'څ'=>'XXH',
'چ'=>'XC',
'ڈ'=>'XXD',
'ډ'=>'XDR',
'ڑ'=>'XXR',
'ړ'=>'XRR',
'ږ'=>'XRX',
'ژ'=>'XJ',
'ښ'=>'XXS',
'ک'=>'XKK',
'ګ'=>'XXK',
'ڭ'=>'XNG',
'گ'=>'XGG',
'ں'=>'XNN',
'ڼ'=>'XXN',
'ه'=>'XDO',
'ۀ'=>'XYH',
'ہ'=>'XXG',
'ۂ'=>'XGE',
'ۃ'=>'XTG',
'ى'=>'XYA',
'ۍ'=>'XXY',
'ې'=>'Y',
'ے'=>'XYB',
'ۓ'=>'XBE'
    );

    $string = strtr($string, $table);
    // Currency symbols: £¤¥€  - we dont bother with them for now
    $string = preg_replace("/[^\x9\xA\xD\x20-\x7F]/u", "", $string);

    return $string;
}

function searchTest($labId,$hash, $certfile, $keyfile, $pwd ,$timeout=0){
	
//wait for Timeout
sleep($timeout);
	
// get Public Key -- polling 
// Test will apear in search after it is scanned, this can take up to 5min
$url = 'https://dcc-proxy-cff4f7147260.coronawarn.app/version/v1/publicKey/search/' . $labid;

print("\n");print_r($labid);print("\n");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
curl_setopt($ch, CURLOPT_SSLCERT, $certfile);
curl_setopt($ch, CURLOPT_SSLKEY, $keyfile);
curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $pwd);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, 1); 
	
$error = curl_error($ch);
print("\n Error: ");print_r($error);print("\n");

// calculate the expected testId as hash from hash 
$expTestId = hash("sha256",$hash);

echo 'search for testId: ' . $expTestId . PHP_EOL; 

$dcci = "";
$publicKey = "";
$testId = ""; 

// fetch public key and dcci from response
foreach ($jsonArrayResponse as $element)
{
	
	if ($expTestId == $element["testId"])
	{
		echo 'dcci:' . $element["dcci"] . ' - publicKey:' . $element["publicKey"] . ' - testId:' . $element["testId"] . PHP_EOL;
		$dcci = $element["dcci"];
		$publicKey = $element["publicKey"];
		$testId = $element["testId"]; 
		$hasResult = true;
	}
	//get the matching testId 
}

$result = trim(curl_exec($ch));
	
if($hasResult){
	return $jsonArrayResponse = json_decode($result, true);
}
	
return searchTests($labId,$hash, $certfile, $keyfile, $pwd, 60);
}

