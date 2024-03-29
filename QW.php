<?php

// --------------------------------------------
// TODO:  need to support landing page for multiple QWs on same server
// --------------------------------------------

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 'stdout');
session_start();

// --------------------------------------------
// --------------------------------------------
function dump($var) {
    echo "<div class=dbg><pre>";
    print_r($var);
    echo "</pre></div>";
}

require_once('./clsQWiki.php');
require_once('./clsProfile.php');

// --------------------------------------------
// Configuration options that may be overridded by .QWconfig.json
// --------------------------------------------
$config                      = Array();
$config['folderSep']         = DIRECTORY_SEPARATOR;                            // Native file system seperator:  win: \ and linux: /  (this can be automated)
$config['contentFileName']   = "-Content.php";                                 // TODO:  should rename to:  .QWcontent.php
$config['QWconfigFileName']  = ".QWconfig.json";                               // Placeholder for folder-specific config options (not used yet)
$config['ArchiveFolderName'] = "Save";                                         //

// --------------------------------------------
// encryption keys requied by clsEncryptions are stored in the .env file
// --------------------------------------------
$qwConfigEnv           = parse_ini_file('.env');
if (!$qwConfigEnv) {
    echo "ERROR: QW .env file is invalid, Get Help";
    exit;
}
$config = array_merge($config, $qwConfigEnv);

require_once('./clsEncryption.php');
$configEnc['KEY1']     = $qwConfigEnv['KEY1'];
$configEnc['KEY2']     = $qwConfigEnv['KEY2'];
$objENC                = new clsEncryption($configEnc);


// --------------------------------------------
// Read this servers QW configuration
// --------------------------------------------
$qwConfigJson        = file_get_contents(".QWconfig.json");
$qwConfigJsonDecoded = json_decode($qwConfigJson, true);
if (is_null($qwConfigJsonDecoded)) {
    echo "ERROR:  invalid QW configuration JSON, Get Help";
    exit;
}
$config = array_merge($config, (array) $qwConfigJsonDecoded['config']);

// --------------------------------------------
// Pick QW instance
// must be called before clsQW is instanciated (for some reason)
// TODO:  improve to detect which server were on in case config supports multiple servers?  Is it necessary?
// TODO:  Possibly list all servers where QWs reside?
// --------------------------------------------
function pickqwi($aOptions) {
    global $qwConfigJsonDecoded;
    global $objENC;
    global $config;

    $xQWI = null;
    if (isset($_REQUEST['qwi'])) $xQWI = $_REQUEST['qwi'];
    
    // count number of instances on this QW.
    // If 1 then select it and continue
    $xCnt = count($qwConfigJsonDecoded['server'][$aOptions['ServerName']]);
    if ($xCnt==1) {
        foreach ($qwConfigJsonDecoded['server'][$aOptions['ServerName']] as $key => $val){  
            $xQWI = $key;
            break;
        }
    }       
    
    // user selected QW instance.  Load it's configuration and continue.
     if (!is_null($xQWI)) {
        $aConfig         = $qwConfigJsonDecoded['server'][$aOptions['ServerName']][$xQWI];      
        $_SESSION['qwi'] = $objENC->encryptData(serialize($aConfig));
        $xURL            = $aConfig['qwURL'] . "/QW.php";
        echo <<<EOHERE
        <script>
        window.location.href = '$xURL';
        </script>
        <h1>ERROR: redirect error to: $xURL, Get Help</h1>
        EOHERE;
        exit;
    }

    // Present user with QW choices
    echo <<<EOWHERE
    <h1>Choose The QWiki Instance You Wish To View</h1>
    EOWHERE;  

    $xKeys = array_keys($qwConfigJsonDecoded['server'][$aOptions['ServerName']]);

    foreach ($qwConfigJsonDecoded['server']['susiepc'] as $key => $aValues) {     
        echo "<p><h3>" . 
        "<a href='?qwi=" . $key   . "'>" . $aValues['QWNAME'] . "</a>" .
        "<br>" .
        "<em><font color=green>" . $aValues['QWABSTRACT'] . "</font></em>" .
        "</h3>";
    }
    exit;
}


// --------------------------------------------
// Multiple QWs may reside on 1 web server
// If user has not chosen a QW, or if they want to choose another one, then prompt for QW and store choice in a session
// --------------------------------------------
if ( isset($_REQUEST['ChooseQwiki']) && isset($_SESSION["qwi"]) ) unset($_SESSION["qwi"]);
if (!isset($_SESSION["qwi"])) {   
    $aOptions = Array();
    $aOptions['ServerName'] = "susiepc";
    pickqwi($aOptions);
    exit;
}

// Load specific QW configuration
//dump($config);
//$x = $_SESSION["qwi"];
//dump($x);
//echo "<h4>decryptData</h4>";
//dump($objENC->decryptData($x));
$config = array_merge($config, unserialize($objENC->decryptData($_SESSION["qwi"])));


// --------------------------------------------
// Configurations that require .QWconfig.json parameters to resolve
// --------------------------------------------
$config['qwServerURL']      = $config['qwURL'] . "/QW.php";                    // URL to QW program
$config['folderPath']       = $config['rootPath'];                             // Default root folder of a QW.  Most always overwritten by option below           = "";                                              // Mostly specified on QS except when viewing root of QW (which does not need a sub-folder)

// --------------------------------------------
// Most, but not all, information is specified in a preferrably-encrypted QUERY_STRING
// qwikiWi $objQW->INIT() derives other options on instanciation
// Some options are contained in POST such as: clsUpload (files[]) and clsFileIO (button press values)
// NOTE:  all folders and files MUST BE relative to a physical (realpath) of the qwiki ROOT path.  
//        Various checks are performed to ensure this is maintained by INIT()
// --------------------------------------------
if (!(strcmp($_SERVER['QUERY_STRING'],"")==0)) {
    $xOptions                      = $_SERVER['QUERY_STRING'];
    if ($config['ENCRYPTION']) {
        $config['QS_Original']     = $xOptions;
        $xOptions                  = $objENC->decryptData($xOptions);
        $config['QS_Decrypted']    = $xOptions;
    }
    $xOptions                      = $objENC->strToNameValueArray($xOptions,"&","=");
    $config['Options']             = $xOptions;

    // Not all options may be specified and could be derived later
    $config['folder']          = $xOptions['folder'];
    $config['folderSanitized'] = $xOptions['folderSanitized'];
    $config['folderPath']      = $config['rootPath'] . $xOptions['folder'];    
}


// --------------------------------------------
// clsProfile.php is an implementation of composer PHPauth/PHPauth
// QW uses this and internal MAP for user authentication
// --------------------------------------------
$config['Auth']['Enabled']   = true;
$config['Auth']['serverURL'] = "https://susiepc/QWiki/Auth/Auth.php";
$config['Auth']['loginURL']  = $config['Auth']['serverURL'] . "?Action=LoginFormDisplay&AuthReturnURL=" . urlencode($config['qwServerURL'] . "?" . $objENC->encryptData("Profile=1")); 
// log out of QW, not the authentication server.  QW would need to use Ajax to send a log-out cmd to remote auth server?
$config['Auth']['logoutURL'] = $config['qwServerURL'] . "?"  . $objENC->encryptData("Profile=1&profileAction=logout");


// --------------------------------------------
// --------------------------------------------
$objQW         = new QuickWiki($config);
$objQW->objENC = $objENC;

// --------------------------------------------
// Process clsFileIO (file and folder manager) options
// --------------------------------------------
if (isset($_POST)) {
    if (isset($_POST['FileIO_Action'])) {
        $config['Options']['FileIO_Action'] = $_POST['FileIO_Action'];
    }
    if (isset($_POST['FileIO_Key'])) {
        $config['Options']['FileIO_Key']    = $_POST['FileIO_Key'];
    }
}
// Decode key since clsFileIO does not have callback to access decryptData() method (yet)
// TODO:   Transform clsFileIO FileIO_Key into FileIO_Options array
if (isset($config['Options']['FileIO_Key'])) {
    if ($config['ENCRYPTION']) {
        $config['Options']['FileIO_Key_Encrypted'] = $config['Options']['FileIO_Key'];
        $config['Options']['FileIO_Key']           = $objENC->decryptData($config['Options']['FileIO_Key']); 
    }
    // ISSUE:  does arrary_merge() return results into a destination array?
    //         does this line really do anything?
    array_merge($config['Options'], $objENC->strToNameValueArray($config['Options']['FileIO_Key'],"&","=") );
}

// --------------------------------------------
// HTML HEAD
// Methods below often require MAP define in: QW.js, QW.css, etc
// --------------------------------------------
$objQW->showHtmlHeader();

// --------------------------------------------
// --------------------------------------------
$objUser = new QWProfile($objQW->config);
$objQW->objUser = $objUser;

// --------------------------------------------
// Upload Operations
// Upload manages operations using JavaScript & Ajax using Upload.js which submits to Upload.php
// It does not submit or re-load any pages
// --------------------------------------------
if (isset($config['Options']['upload'])) {
    $aOptions = Array();
    $aOptions['realPath'] = $config['realPath'];
    $objQW->displayUploadForm($aOptions);
    exit;
}

// --------------------------------------------
// File Manager Operations
// --------------------------------------------
if (isset($config['Options']['FileIO_Action'])) {
    require_once('./clsFileIO.php');
    exit;
}

// --------------------------------------------
// Content Editor Operations
// --------------------------------------------
if ( (isset($_REQUEST["EditorSubmitForm"])) || (isset($config['Options']['Editor'])) ) {
    $objQW->displayPageOptions(null);
    echo "<p>";
    require_once('./Editor.php');
    exit;
}

// --------------------------------------------
// Display qwiki
// --------------------------------------------
$objQW->listContents(null);

?>

