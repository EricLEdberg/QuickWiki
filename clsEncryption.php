<?php
/* -------------------------------------------------------------
 * Supports SYMETRIC encryption where keys remain static and are known/managed by the application
 * 
 * Encryption keys must be stored base64_encode()
 * 8, 16, 32 & 64 byte example keys are auto-generated on class instanciation.  $this->dump($config), to view them
 *
 * A clear and concise class example, similar to what's implemented here
 * - https://medium.com/@einnar82/aes-256-cbc-encryption-and-decryption-in-php-0449d41fa1e3
 *
 * This class is not intended for encrypting large file/data sizes.  It is suitable for encryption smaller amounts of data e.g.:  program options
 * 
 * You can encrypt/decrypt using 1 key or 2 keys.  
 * -  1-key encryption simply encrypts the data using KEY1
 * -  2-key encryption then calculates and prepends the HMAC_HASH on the originally encrypted data 
 *    During decryption, the will verify that data originally encrypted was not tampered with using the extracted hash
 * 
 * This class, as you may know, expects and assumes that the application has properly secured encryption keys!
 * 
 * Author:   Eric L. Edberg   ele@EdbergNet.com 4/24
 *
*/

// Namespace required by Composer and packagist for:    ericledberg\php-encryption
namespace clsEncryption;

class clsEncryption {
	public  $config       = Array();
	public  $Version      = ".0.4";
	public  $MyDebug      = false;
	public  $error        = null;
    
	// ----------------------------------------------------------
	// ----------------------------------------------------------
	public function __construct($aOptions) {
        $this->config = $aOptions;

        if(!extension_loaded('openssl')) {
			throw new Exception('clsEncryption requires PHP openssl extension, Get Help.');
		}

        // Check versions with Heartbleed vulnerabilities
        if (OPENSSL_VERSION_NUMBER <= 268443727) {
            throw new \RuntimeException('OpenSSL version too old <= 268443727');
        }

        if (!$this->readINI(null)){
            throw new Exception('clsEncryption cannot read key file, Get Help.');
        }

        // Dynamically generate base64-encoded keys at runtime, as examples...
        // Application would need to store and manage these keys across multiple program instanciations
        // legacy openssl_random_pseudo_bytes() may not return cryptographically secure key on older servers
        if (!isset($this->config['KEY8']))  $this->config['KEY8']  = base64_encode(bin2hex(random_bytes(8)));
        if (!isset($this->config['KEY16'])) $this->config['KEY16'] = base64_encode(bin2hex(random_bytes(16)));
        // 32 bytes == 256 bit encryption == AES 256-bit Encryption
        if (!isset($this->config['KEY32'])) $this->config['KEY32'] = base64_encode(bin2hex(random_bytes(32)));
        if (!isset($this->config['KEY64'])) $this->config['KEY64'] = base64_encode(bin2hex(random_bytes(64)));
        
        return true;
	}

    // ----------------------------------------------------------
	// ----------------------------------------------------------
	public function __destruct() {
        if ($this->MyDebug) {
            echo "<h4>clsEncryption()</h4>";
            $this-dump($this->config);
        }
	}

    private function dump($var) {
        echo "<div class=dbg><pre>";
        print_r($var);
        echo "</pre></div>";
    }

    // --------------------------------------------
    // encryption keys may optionally be stored in a user-secured .env file
    // --------------------------------------------
    private function readINI($aOptions) {
        
        // .env keyfile does not exist.  Assume application will initialize keys using $this->setKeys()
        if (!isset($this->config['ENVKEYFILE'])) return true;
        
        if (!file_exists($this->config['ENVKEYFILE'])) {
            $this->config['error'] = "ERROR: ENVKEYFILE does not exist, Get Help";
            return false;
        }
        
        $this->config['ENV'] = parse_ini_file($this->config['ENVKEYFILE']);
        if (!$this->config['ENV']) {
            $this->config['error'] = "ERROR:  ENVKEYFILE keyfile may be incorrectly formatted, Get Help";
            return false;
        }

        // KEY1 is mandatory
        if (!isset($this->config['ENV']['KEY1'])) {
            $this->config['error'] = "ERROR:  ENENVKEYFILEV KEY1 was not provided, Get Help";
            return false;  
        }

        // KEY2 is optional
        $xk2 = null;
        if (isset($this->config['ENV']['KEY2'])) $xk2 = $this->config['ENV']['KEY2'];
        
        // Activate keys
        if (!$this->setKeys($this->config['ENV']['KEY1'], $xk2)) {
            echo "<h1>ERROR: ENVKEYFILE: " . $this->config['error'] . "</h1>";
            exit;
        }
        
        return true;
    }

    // --------------------------------------------
    // KEY1 is mandatory, KEY2 is optional
    // KEY2 must be exactly twice the size of KEY1 e.g.:  if key1 is 16 bytes, key2 must be 32 bytes...
    // --------------------------------------------
    public function setKeys($xKey1, $xKey2=null) {
        
        if ( !isset($xKey1) ) {
            $this->config['error'] = "ERROR:  encryption KEY1 was not provided";
            return false;
        }

        $this->config['KEY1']      = $xKey1;
        $k1len                     = strlen(base64_decode($xKey1));
        if ($k1len < 8) {
            $this->config['error'] = "ERROR:  encryption:  KEY1 is not large enough, Get Help";
            return false;
        }
        if (!is_null($xKey2)) {
            $this->config['KEY2'] = $xKey2;
            $k2len                = strlen(base64_decode($xKey2));
            if ($k2len/$k1len != 2) {
                $this->config['error'] = "ERROR:  encryption:  KEY2 is not correct length, Get Help";
                return false;
            }
        }
        
        return true;
    }
    
    // ----------------------------------------------------------
	// see:  https://www.php.net/manual/en/function.openssl-encrypt.php
    // $this->config['KEY1'] is mandatory
    // $this->config['KEY2'] is optional
    // ----------------------------------------------------------
	function encryptData($data) {
        if (!isset($data) || trim($data) == '') return false;             // null or empty
        
        $first_key            = base64_decode($this->config['KEY1']);
        $method               = "aes-256-cbc";    
        $iv_length            = openssl_cipher_iv_length($method);
        $iv                   = random_bytes($iv_length);
        // Just thinking about alterations
        // See:  https://medium.com/@einnar82/aes-256-cbc-encryption-and-decryption-in-php-0449d41fa1e3
        //$iv_enc               = base64_encode($iv);
        $first_encrypted      = openssl_encrypt($data,$method,$first_key, OPENSSL_RAW_DATA ,$iv);
        $output               = base64_encode($iv.$first_encrypted);
        //$output               = ($iv_hex:$first_encrypted);

        if (isset($this->config['KEY2'])) {
            $xKEY2                = base64_decode($this->config['KEY2']);
            $second_encrypted     = hash_hmac('sha3-512', $first_encrypted, $xKEY2, TRUE);
            $output               = base64_encode($iv.$second_encrypted.$first_encrypted);
        }

        return $output;        
    }

    // ------------------------------------------------------------------
	// ------------------------------------------------------------------
	function decryptData($input) {
        if (!isset($input) || trim($input) == '') return false;             // null or empty

        $xEncryptedData      = base64_decode($input);        
        $xKEY1               = base64_decode($this->config['KEY1']);
        $method              = "aes-256-cbc";
        $iv_length           = openssl_cipher_iv_length($method);            
        $iv                  = substr($xEncryptedData,0,$iv_length);       
        
        if (!isset($this->config['KEY2'])) {
            $xDataEncrypted = substr($xEncryptedData,$iv_length);
            return openssl_decrypt($xDataEncrypted,$method,$xKEY1,OPENSSL_RAW_DATA,$iv);

        } else {
            $xKEY2                = base64_decode($this->config['KEY2']);       
            $second_encrypted     = substr($xEncryptedData,$iv_length,64);
            $first_encrypted      = substr($xEncryptedData,$iv_length+64);
            $data                 = openssl_decrypt($first_encrypted,$method,$xKEY1,OPENSSL_RAW_DATA,$iv);      
            $second_encrypted_new = hash_hmac('sha3-512', $first_encrypted, $xKEY2, TRUE);
            
            if (hash_equals($second_encrypted,$second_encrypted_new)) return $data;
        }     
        
        return false;
    }

    // ------------------------------------------------------------------
	// ------------------------------------------------------------------
	public function EncryptDataUrl($xString) {
        if (is_null($xString) || (strcmp($xString,"")==0) ) return false;
		return urlencode($this->encryptData($xString));
	}

    // ---------------------------------------------------------------------
    // for each character in a string, convert to it's hex value
    // QUESTION:   will this work for foreign languages?
    // ---------------------------------------------------------------------
    public function strToHex($string){
        $hex = '';
        for ($i=0; $i<strlen($string); $i++){
            $ord     = ord($string[$i]);
            $hexCode = dechex($ord);
            $hex    .= substr('0'.$hexCode, -2);
        }
        return strToUpper($hex);
    }
    
    // ---------------------------------------------------------------------
    // for each pair of hex characters, convert them to a character
    // QUESTION:   will this work for foreign languages?
    // ---------------------------------------------------------------------
    public function hexToStr($hex){
        $string='';
        for ($i=0; $i < strlen($hex)-1; $i+=2){
            $string .= chr(hexdec($hex[$i].$hex[$i+1]));
        }
        return $string;
    }
    
    // ---------------------------------------------------------------------
    // Parse a comma-seperated name/value pair string into an array
    // ISSUE:  string cannot have split1 or split2 characters in them or this function will fail
    // TODO:   need to test how this reacts for arrays and possibly encode split characters when originally building string
	// ---------------------------------------------------------------------
	function strToNameValueArray($inputString, $split1, $split2) {
        $result = array();

        if (is_null($inputString) || (strcmp($inputString, "")  ==0) ) return array();
		if (is_null($split1)      || (strcmp($split1,      "")  ==0) ) $split1 = ",";
        if (is_null($split2)      || (strcmp($split2,      "")  ==0) ) $split2 = "=";
        
        // Split the input string into name/value pairs
        $pairs = explode($split1, $inputString);

        foreach ($pairs as $pair) {
            // Split each pair into name and value
            list($name, $value) = explode($split2, $pair, 2);

            // Trim leading or trailing whitespaces
            $name  = trim($name);
            $value = trim($value);

            // Check if name already exists in result array
            if (isset($result[$name])) {
                // If not, convert existing value to an array if it's not already
                if (!is_array($result[$name])) {
                    $result[$name] = array($result[$name]);
                }
                // Add new value to array
                $result[$name][] = $value;
            } else {
                // If name doesn't exist, simply set the value
                $result[$name] = $value;
            }
        }

        return $result;
    }

    // ---------------------------------------------------------------------
    // ERROR:        Does not work since openssl.cnf has not been tested/integrated
	// TEST ONLY:    RSA public/private key generation
    // ERROR:        Does not work unless PHP is configured with additional openssl cnf
    // See:          https://www.php.net/manual/en/openssl.installation.php
    // See:          https://medium.com/@viniciusamparo/a-simple-guide-to-client-side-encryption-and-decryption-using-javascript-jsencrypt-and-php-20c2f179b6e5
    // ---------------------------------------------------------------------
	public function generateRSAKeys($aOptions) {
        
        // Define an array with the configuration settings for the keys to be generated.
        $config = array(
            "config" => "C:/xampp/php/extras/openssl/openssl.cnf",
            "digest_alg"       => "sha512",               // hash function to use
            "private_key_bits" => 2048,                   // size of private key
            "private_key_type" => OPENSSL_KEYTYPE_RSA,    // type of private key (OPENSSL_KEYTYPE_RSA == RSA key).
        );

        // Generate private and public key pair
        // openssl_pkey_new() returns resource that holds the key pair
        $res = openssl_pkey_new($config);
        if (!$res) {
            echo "<li>ERROR, openssl_pkey_new() failed</li>";
            $this->dump($res);
            return false;
        }

        // Extract private key
        // openssl_pkey_export() extracts private key as a string
        $xRet = openssl_pkey_export($res, $privKey);

        // Extract public key
        // openssl_pkey_get_details() returns an array with key details, including the public key.
        $pubKey = openssl_pkey_get_details($res);
        $pubKey = $pubKey["key"];

        // Save private key as 'private_key.pem'
        file_put_contents('.QWprivatekey.pem', $privKey);

        // Save public key as 'public_key.pem'
        file_put_contents('.QWpublickey.pem', $pubKey);

        echo "<h1>Completed Generating QW RSA Keys</h1>";
    }

    // ---------------------------------------------------------------------
	// ---------------------------------------------------------------------
	function ExecuteTests() {
        $this->MyDebug = true;

        echo "<h4>Test:  Default Encryption (default class key method)</h4>";
        $dataToEncrypt = "12345";
        echo "Data: " . $dataToEncrypt;
        $xData = $this->encryptData($dataToEncrypt);
        echo "<br>Encrypted Data: " . $xData;
        $xData = $this->decryptData($xData);
        echo "<br>Data: " . $xData;

        echo "<h4>Test:  URL Encryption (default class key method)</h4>";
        $xData = $this->EncryptDataUrl($dataToEncrypt);
        echo "URL Encrypted Data: " . $xData;
        $xData = urldecode($xData);
        echo "<br>URLDECODE() Data: " . $xData;
        $xData = $this->decryptData($xData);
        echo "<br>Data: " . $xData;

        // Test 2 KEY encryption
        echo "<h4>Test:  2 Key Encryption (keys in Example.env)</h4>";
        $aENC = parse_ini_file("Example.env");
        $this->setKeys($aENC['KEY1'],$aENC['KEY2']);
        $xData = $this->encryptData($dataToEncrypt);
        echo "Data: " . $dataToEncrypt;
        echo "<br>Encrypted Data: " . $xData;
        $xData = $this->decryptData($xData);
        echo "<br>DecryptedData: " . $xData;

        // Test 1 KEY encryption
        echo "<h4>Test:  1 Key Encryption (keys in Example.env)</h4>";
        $aENC = parse_ini_file("Example.env");
        $this->setKeys($aENC['KEY1']);
        $xData = $this->encryptData($dataToEncrypt);
        echo "Data: " . $dataToEncrypt;
        echo "<br>Encrypted Data: " . $xData;
        $xData = $this->decryptData($xData);
        echo "<br>DecryptedData: " . $xData;

        echo "<h4>Test:  strToNameValueArray (default class key method)</h4>";
        $inputString = "name1=value1,name2=value2,name1=value3,name3=value4";
        echo "Input String: " . $inputString;
        $resultArray = $this->strToNameValueArray($inputString, ",", "=");
        $this->dump($resultArray);

        // TEST ONLY:   needs to be worked on later...
        // Requirement: Need to have openssl.cnf file created
        // See:  https://www.php.net/manual/en/function.openssl-pkey-new.php
        // Test:   Generate private and public keys for this application
        //echo "<h4>Generating PRIVATE and PUBLIC keys</h4>";
        //$this-> generateRSAKeys(null);
    }

}

?>



