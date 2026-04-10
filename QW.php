<?php
// --------------------------------------------
// Author:  Eric L. Edberg, 2012-2026 ele@EdbergNet.com
// --------------------------------------------

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 'stdout');
session_start();
$config = Array();

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
// Read QW configuration:  .QWconfig.json and merge
// --------------------------------------------
if (!file_exists(".QWconfig.json")) {
    echo "ERROR:  QW config json does not exist, Get Help";
    exit;
}
$qwConfigJson        = file_get_contents(".QWconfig.json");
$qwConfigJsonDecoded = json_decode($qwConfigJson, true);      // json to array()

if (is_null($qwConfigJsonDecoded)) {
    echo "ERROR:  invalid QW config JSON, Get Help";
	echo $qwConfigJson;
    exit;
}
$config = array_merge($config, (array) $qwConfigJsonDecoded['config']);

// --------------------------------------------
// --------------------------------------------
$aOptions = array();
$aOptions['ENVKEYFILE'] = __DIR__ . $config['folderSep'] . ".env";
require_once('./clsEncryption.php');
$objENC = new \clsEncryption\clsEncryption($aOptions);
//$objENC->ExecuteTests();
//exit;


// --------------------------------------------
// Chose QW
// Developer Hint:  To unset current environment choose: Admin menu -> Select Different Qwiki.  
// -  It will delete QWI cookie and session parameters starting fresh.
// --------------------------------------------

// unset _SESSION which forces pickqwi() to load again
// Note:  currently, you must unset the cookie which initializes the session
if ( isset($_REQUEST['ChooseQwiki'] ) && isset($_SESSION["QWI"]) ) {
    unset($_COOKIE['QWI']);
    unset($_SESSION["QWI"]);
}

// Testing using cookies to store QW choice.  Cookies will remember QWI choice across browser restarts.
// Today, set a 1-day cookie persistance so user does not have to log in again within a 24 hour period.  See:  pickqwiki().
// Today, initialize _SESSION (which was developed/used first) with cookie data (if it exists); they should contain identicle data
// In the future, we code to use only cookies or only sessions

if (isset($_COOKIE['QWI'])) {
    $_SESSION["QWI"] = $_COOKIE['QWI'];
};

// If user clicked link to a specific QWI OR user has not already picked a QWI
if ( isset($_REQUEST['QWI']) || !isset($_SESSION["QWI"]) )  {
    pickqwi(null);
    exit;
}

// --------------------------------------------
// Load QW choice stored in session
// Data is serialized and encrypted
// --------------------------------------------
$config = array_merge($config, unserialize($objENC->decryptData($_SESSION["QWI"])));

// --------------------------------------------
// class - Full Text Search
// Default DB connection parameters may be altered dependent on QW choice
// --------------------------------------------

if ($config['FEATURE']['SEARCH'] ) {
    require("./clsFullTextSearch.php");
    $objFTS = new clsFullTextSearch($config['FTSDB']);
}
    
// --------------------------------------------
// Configuration options that require a QW instance choice to resolve
// TODO:  both of these could be automated/removed
// --------------------------------------------
$config['QWSERVERURL']      = $config['QWURL'] . "/QW.php";                 // URL to QW program
$config['folderPath']       = $config['rootPath'];                          // Default root folder of a QW.
if (isset($_REQUEST['folder'])) $config['folder'] = $_REQUEST['folder'];    // Accept un-encrypted folder.  FTS search cannot store encrypted URLs long-term as the keys may change?

// --------------------------------------------
// Process options contained in the optional encrypted query string.
// Merge them into $config['Options']
// Other functions may initialize/replace/create additional Options variables based on need
// See qwikiWi $objQW->INIT() which also derives additional options
// Additional options may be obtained from a POST submission too
//
// ISSUE:  if an value contains a comma or ampersond it will mess up array conversion
// TODO:   figure better way to pass options (serialize) or convert values to hex (see clsFileIO below)
// --------------------------------------------
if (!(strcmp($_SERVER['QUERY_STRING'],"")==0)) {
    $config['QS_Original']     = $_SERVER['QUERY_STRING'];
    $config['QS_Decrypted']    = $objENC->decryptData($config['QS_Original']);
    $config['Options']         = $objENC->strToNameValueArray($config['QS_Decrypted'],"&","="); 
}

// --------------------------------------------
// clsProfile.php is an implementation of: composer PHPauth/PHPauth
// QW uses this reflector for user authentication
// TODO:  serverURL should be defined in .QWconfig.json
// --------------------------------------------
if ($config['FEATURE']['AUTH'] ) {
    $config['AUTH']['Enabled']   = true;
    $config['AUTH']['serverURL'] = "https://ELEW11/Auth/Auth.php";
    $config['AUTH']['loginURL']  = $config['AUTH']['serverURL'] . "?" . $objENC->encryptData("Action=LoginFormDisplay&AuthReturnURL=" . urlencode($config['QWSERVERURL'] . "?" . $objENC->encryptData("Profile=1")) ); 
    // log out of QW, not the remote authentication server.  
    // QW probably would use Ajax to send a remote log-out cmd to remote auth server.  TODO I guess....
    $config['AUTH']['logoutURL'] = $config['QWSERVERURL']       . "?"  . $objENC->encryptData("Profile=1&profileAction=logout");
    //dump($config);
    //exit;
}

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
if (isset($config['Options']['FileIO_Key'])) {
    $config['Options']['FileIO_Key_Encrypted'] = $config['Options']['FileIO_Key'];
    $config['Options']['FileIO_Key']           = $objENC->decryptData($config['Options']['FileIO_Key']); 
}

// ISSUE:   clsFileIO.php decodes FileIO_filePath too after being encoded when constructing the "Manage" url
//          Appears to not be required here????
// filePath may contain , or & characters on Windows.  These need to be encoded when constructing encrypted query string
// We do this by converting the filePath to hex.   See QW.php and clsFileIO.php
//if (isset($config['Options']['FileIO_filePath'])) {
//    echo "<li>FileIO_filePath: " . $config['Options']['FileIO_filePath'] . "</li>";
//    $config['Options']['FileIO_filePath']      = $objENC->hexToStr($config['Options']['FileIO_filePath']);
//}

// --------------------------------------------
// Finally, instanciate QW application
// --------------------------------------------
$objQW         = new QuickWiki($config);

$objQW->objENC = $objENC;
if ($config['FEATURE']['SEARCH'] ) {
    $objQW->objFTS = $objFTS;
}

// --------------------------------------------
// HTML HEAD
// --------------------------------------------
$objQW->showHtmlHeader();

// --------------------------------------------
// --------------------------------------------
if ($config['FEATURE']['AUTH'] ) {
    $objUser = new QWProfile($objQW->config);
    $objQW->objUser = $objUser;
}

// --------------------------------------------
// Upload Operations
// Upload manages operations using JavaScript & Ajax in Upload.js which submits to Upload.php
// It does not submit or re-load any pages
// --------------------------------------------

if ($config['FEATURE']['UPLOAD'] && isset($config['Options']['upload'])) {   
    $aOptions = Array();
    $aOptions['realPath'] = $config['realPath'];
    $objQW->displayUploadForm($aOptions);
    exit;
}

// --------------------------------------------
// Content Editor Operations
// --------------------------------------------
if ($config['FEATURE']['EDIT'] ) {
    if ( (isset($_REQUEST["EditorSubmitForm"])) || (isset($config['Options']['Editor'])) ) {
        $objQW->displayPageOptions(null);
        echo "<p>";
        require_once('./Editor.php');
        exit;
    }
}

// --------------------------------------------
// File Manager Operations
// --------------------------------------------
if (isset($config['Options']['FileIO_Action'])) {  
    require_once('./clsFileIO.php');
    exit;
}

// --------------------------------------------
// FTS Search Operations
// --------------------------------------------

if ( isset($config['FEATURE']['SEARCH']) && (isset($config['Options']['FTS_ENABLED']) || isset($_REQUEST['FTS_ENABLED'])) ){  
    $objQW->displayPageOptions(null);
    echo "<p>";
    require_once('./Search.php');
    exit;
}

// --------------------------------------------
// default:   Display qwiki
// --------------------------------------------
$objQW->displayQwikiMain(null);


// --------------------------------------------
// Pick QW instance
// Must be called before clsQW is instanciated
// QWS and QWI must be provided as a pair
// --------------------------------------------
function pickqwi($aOptions) {
    global $qwConfigJsonDecoded;
    global $objENC;
    global $config;

    $xQWI = null;
    if (isset($_REQUEST['QWI'])) {
        $xQWS = $_REQUEST['QWS'];          // QW server
        $xQWI = $_REQUEST['QWI'];          // QW instance
    }
    
    // A QW server & instance was selected
    // Load it's configuration and store in _SESSION['QWI']
     if (!is_null($xQWI)) {
        
        if (!isset($qwConfigJsonDecoded['server'][$xQWS][$xQWI])) {
            echo "<h2>ERROR: Qwiki instance: $xQWS:$xQWI, does not exist anymore, Get Help</h2>";
            exit;
        }
        
        $aConfig         = $qwConfigJsonDecoded['server'][$xQWS][$xQWI];
        $aConfig['QWD']  = time();                                             // GMT time.  Future security/replay checks
        $aConfig['PSID'] = session_id();
        $aConfig['QWS']  = $xQWS;
        $aConfig['QWI']  = $xQWI;
        if (isset($_SESSION['QWI'])){
            unset($_SESSION['QWI']);
        }
        
        $xData           = $objENC->encryptData(serialize($aConfig));  
        
        // Save instance choice in a session        
        $_SESSION['QWI'] = $xData;         
        
        // Save QW instance in browser cookie using JS with a 1-day expiration
        // Prevents user from having to make same QWI choice when they close their browser periodically and re-visit QW
        echo <<< EOHERE
        <script>
        var value   = "$xData";
        var days    = 1;
        var date    = new Date();
        var expires = "";
        date.setTime(date.getTime() + (days*24*60*60*1000));
        expires     = "; expires=" + date.toUTCString();
        document.cookie = "QWI" + "=" + (value || "")  + expires + "; path=/";
        </script>
        EOHERE;

        // Pass-through additional (optional) query string arguments to destination URL after encrypting them
        $xOptions = null;
        $xAmper   = "";
        foreach($_REQUEST as $key => $val) {
            switch ($key) {
                case "QWS":
                case "QWI":
                    // no need to pass these as they are only needed by pickqwiki() on initial submission per session
                default:
                $xOptions  = $xAmper . $key . "=" . $val;
                $xAmper    = "&";
            }
        }
        $xOptions = $objENC->encryptData($xOptions);
        
        $xURL     = $aConfig['QWURL'] . "/QW.php";       
        if (!is_null($xOptions)) $xURL .= "?" . $xOptions;

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

    $xKeys = array_keys($qwConfigJsonDecoded['server']);
    foreach ($qwConfigJsonDecoded['server'] as $key1 => $aValues1) {     
        foreach ($qwConfigJsonDecoded['server'][$key1] as $key2 => $aValues2) {     
            echo "<p><h3>" . 
            "<a href='" . $aValues2['QWURL'] . "/QW.php?QWS=" . $key1 . "&QWI=" . $key2   . "'>" . $aValues2['QWNAME'] . "</a>" .
            "<br>" .
            "<em><font color=green>" . $aValues2['QWABSTRACT'] . "</font></em>" .
            "</h3>";
        }
    }

    exit;
}

?>

