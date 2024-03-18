<?php

class clsEncryption {
	public  $Config       = Array();
	public  $Version      = ".0.3";
	public  $MyDebug      = false;
	public  $error        = null;
    
	// ----------------------------------------------------------
	// ----------------------------------------------------------
	public function __construct($xConfig) {
        $this->Config = $xConfig;

        if(!extension_loaded('openssl')) {
			throw new Exception('clsEncryption requires PHP openssl extension, Get Help.');
		}

        // See README.MD which references using KEY16 and KEY32 when installing QW application (obtained when setting $this->Mydebug=true)
        // Dynamically generate base64-encoded keys at runtime
        // Application would store and manage keys themselves across multiple program instanciations
        // legacy openssl_random_pseudo_bytes() may not return cryptographically secure key on older servers
        if (!isset($this->Config['KEY16'])) $this->Config['KEY16'] = base64_encode(bin2hex(random_bytes(16)));
        if (!isset($this->Config['KEY32'])) $this->Config['KEY32'] = base64_encode(bin2hex(random_bytes(32)));
        if (!isset($this->Config['KEY64'])) $this->Config['KEY64'] = base64_encode(bin2hex(random_bytes(64)));
    
	}

    // ----------------------------------------------------------
	// ----------------------------------------------------------
	public function __destruct() {
        if ($this->MyDebug) {
            echo "<h4>clsEncryption()</h4>";
            $this-dump($this->Config);
        }
	}

    function dump($var) {
        echo "<div class=dbg><pre>";
        print_r($var);
        echo "</pre></div>";
    }

    // ----------------------------------------------------------
	// see:  https://www.php.net/manual/en/function.openssl-encrypt.php
    // ----------------------------------------------------------
	function encryptData($data) {
        
        if (is_null($data))        return false;
		if (!is_string($data))     return false;
		if ((strcmp($data,"")==0)) return false;
		
        $first_key            = base64_decode($this->Config['KEY1']);
        $second_key           = base64_decode($this->Config['KEY2']);                 
        $method               = "aes-256-cbc";    
        $iv_length            = openssl_cipher_iv_length($method);
        $iv                   = random_bytes($iv_length);
        $first_encrypted      = openssl_encrypt($data,$method,$first_key, OPENSSL_RAW_DATA ,$iv);    
        $second_encrypted     = hash_hmac('sha3-512', $first_encrypted, $second_key, TRUE);
        $output               = base64_encode($iv.$second_encrypted.$first_encrypted);
        return $output;        
    }

    // ------------------------------------------------------------------
	// ------------------------------------------------------------------
	function decryptData($input) {

        $first_key            = base64_decode($this->Config['KEY1']);
        $second_key           = base64_decode($this->Config['KEY2']);
        $mix                  = base64_decode($input);        
        $method               = "aes-256-cbc";
        $iv_length            = openssl_cipher_iv_length($method);            
        $iv                   = substr($mix,0,$iv_length);
        $second_encrypted     = substr($mix,$iv_length,64);
        $first_encrypted      = substr($mix,$iv_length+64);
        $data                 = openssl_decrypt($first_encrypted,$method,$first_key,OPENSSL_RAW_DATA,$iv);      
        $second_encrypted_new = hash_hmac('sha3-512', $first_encrypted, $second_key, TRUE);
        if (hash_equals($second_encrypted,$second_encrypted_new)) return $data;
        return false;
    }

    // ------------------------------------------------------------------

	// ------------------------------------------------------------------
	public function EncryptDataUrl($xString) {
        if (is_null($xString) || (strcmp($xString,"")==0) ) return false;
		return urlencode($this->encryptData($xString));
	}

    // ---------------------------------------------------------------------
    // Parse a string in comma-seperated name/value pairs into an array
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

            // Trim any leading or trailing whitespaces
            $name  = trim($name);
            $value = trim($value);

            // Check if the name already exists in the result array
            if (isset($result[$name])) {
                // If it does, convert the existing value to an array if it's not already
                if (!is_array($result[$name])) {
                    $result[$name] = array($result[$name]);
                }
                // Add the new value to the array
                $result[$name][] = $value;
            } else {
                // If the name doesn't exist, simply set the value
                $result[$name] = $value;
            }
        }

        return $result;
    }

    // ---------------------------------------------------------------------
	// Test RSA public/private key generation
    // ERROR:  does not work unless PHP is configured with additional openssl cnf
    // See:   https://medium.com/@viniciusamparo/a-simple-guide-to-client-side-encryption-and-decryption-using-javascript-jsencrypt-and-php-20c2f179b6e5
    // ---------------------------------------------------------------------
	public function generateRSAKeys($aOptions) {
        
        // Define an array with the configuration settings for the keys to be generated.
        $config = array(
            "digest_alg" => "sha512",                     // hash function to use
            "private_key_bits" => 4096,                   // size of private key
            "private_key_type" => OPENSSL_KEYTYPE_RSA,    // type of private key (OPENSSL_KEYTYPE_RSA == RSA key).
        );

        // Generate private and public key pair
        // openssl_pkey_new() returns resource that holds the key pair
        $res = openssl_pkey_new($config);
        if (!$res) echo "<li>ERROR, openssl_pkey_new() failed</li>";
        // Extract private key
        // openssl_pkey_export() extracts private key as a string
        $xRet = openssl_pkey_export($res, $privKey);

        // Extract public key
        // openssl_pkey_get_details() returns an array with key details, including the public key.
        $pubKey = openssl_pkey_get_details($res);
        $pubKey = $pubKey["key"];

        // Save the private key to a file named 'private_key.pem' for later use.
        // The file_put_contents() function writes data to a file. If the file does not exist, it will be created.
        file_put_contents('.QWprivatekey.pem', $privKey);

        // Similarly, save the public key to a file named 'public_key.pem' for later use.
        file_put_contents('.QWpublickey.pem', $pubKey);

        echo "<h1>Completed Generating QW RSA Keys</h1>";
    }

    // ---------------------------------------------------------------------
	// ---------------------------------------------------------------------
	function ExecuteTests() {

        $dataToEncrypt = "12345";
        echo "<br>Data: " . $dataToEncrypt;

        $xData = $this->encryptData($dataToEncrypt);
        echo "<br>Encrypted Data: " . $xData;
        $xData = $this->decryptData($xData);
        echo "<br>Data: " . $xData;

        $xData = $this->EncryptDataUrl($dataToEncrypt);
        echo "<br><br>URL Encrypted Data: " . $xData;

        $xData = urldecode($xData);
        echo "<br>URLDECODE() Data: " . $xData;

        $xData = $this->decryptData($xData);
        echo "<br>Data: " . $xData;

        $inputString = "name1=value1,name2=value2,name1=value3,name3=value4";
        $resultArray = $this->strToNameValueArray($inputString, ",", "=");
        $this->dump($resultArray);


        // example how to set the encryption key in a cookie (make sure to set secure and httpOnly flags)
        // definately do not want to do this as it's extremely insecure
        // setcookie("key", $key, 0, '/', '', true, true);

    }

}

?>



