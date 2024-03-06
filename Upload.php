<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 'stdout');
session_start();

// get encryption keys
$QWENV                  = parse_ini_file('.env');

// -----------------------------------------------------
// Upload options
// -----------------------------------------------------
$config                 = array();
$config['targetDir']    = "../uploads/";
$config['maxFileSize']  = 1024 * 1024 * 1;                      // 1MB
$config['totFileSize']  = $config['maxFileSize'] * 10;          // 10MB
$config['allowedTypes'] = array('image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'text/plain', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

// -----------------------------------------------------
// -----------------------------------------------------
require_once('clsEncryption.php');
$config['key1']           = $QWENV['KEY1'];
$config['key2']           = $QWENV['KEY2'];
$objENC                   = new clsEncryption($config);

// TODO:
if (!isset($_POST['uploadKey'])) {
	echo "<font color=red>ERROR: upload key was not provided, Get Help</font>";
	exit;
}
$config['encryptedKey']   = $_POST['uploadKey'];
$config['decryptedKey']   = $objENC->decryptData($config['encryptedKey']);

// TODO:   pass json data?
$config['arrayData']      = $objENC->strToNameValueArray($config['decryptedKey'],"&","=");

// -----------------------------------------------------
// folder where to save the file(s)
// -----------------------------------------------------
if (!isset($config['arrayData']['realPath'])) {
	echo "<font color=red>ERROR: destination folder not available, Get Help</font>";
	exit;
}

// -----------------------------------------------------
// -----------------------------------------------------
if (empty($_FILES['files']['name'][0])) {
	echo "<li><font color=red>Oops:  no files were selected and uploaded</font></li>";
	exit;
}

// -----------------------------------------------------
// -----------------------------------------------------

$tot_fileSize = 0;

foreach ($_FILES['files']['name'] as $key => $name) {
	
	$tempName       = $_FILES['files']['tmp_name'][$key];
	$fileSize       = $_FILES['files']['size'][$key];
	$fileType       = $_FILES['files']['type'][$key];
	$fileName       = basename($name);
	$targetFileName = $config['arrayData']['realPath'] . DIRECTORY_SEPARATOR . $fileName;

	// Check file size
	if ($fileSize > $config['maxFileSize']) {
		echo "<font color=red>ERROR:  file: {$fileName}, is too large. Maximum size allowed is: 1MB</font><br>";
		unlink($tempName);
		continue;
	}

	// Check file type
	if (!in_array($fileType, $config['allowedTypes'])) {
		echo "<font color=red>ERROR:  invalid file type: {$fileType}, for: {$fileName}</font><br>";
		unlink($tempName);
		continue;
	}

	$tot_fileSize += $fileSize;
	if ($tot_fileSize > $config['totFileSize']) {
		echo "<font color=red>ERROR:  the total size of the files exceeds the maximum allowed per upload attempt.  The total size of all files is limited to: " . $config['totFileSize'] . " Bytes</font><br>";
		exit;
	}


	// See:   https://www.php.net/manual/en/function.move-uploaded-file.php
	// Move uploaded file to target directory
	if (move_uploaded_file($tempName, $targetFileName)) {
		echo "<li><font color=green>{$fileName}, was successfully uploaded</font></li>";
	} else {
		echo "<li><font color=red>ERROR: moving uploaded file to destination folder</font></li>";
	}
}

function dump($var) {
	echo "<div class=dbg><pre>";
	print_r($var);
	echo "</pre></div>";
}

function check_file_uploaded_name ($filename) {
    (bool) ((preg_match("`^[-0-9A-Z_\.]+$`i",$filename)) ? true : false);
}
function check_file_uploaded_length ($filename) {
    return (bool) ((mb_strlen($filename,"UTF-8") > 225) ? true : false);
}
?>
