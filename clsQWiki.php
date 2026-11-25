<?php

class QuickWiki {
    
    public $config;
    public $folder;
    public $folderPath;
    public $MyDebug;
    public $QWCONTENT;
    public $QWIFRAME;
    public $objENC;
    public $HtmlHeaderEnabled;
    public $FTS_BODY;
    public $objFTS;
    public $objUser;
    public $qwconfig;
        
    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    public function __construct(&$aConfig) {
        global $objENC;
        global $objUser;

        $this->objUser           = $objUser;                   // reference to global user object
        $this->objENC            = $objENC;                    // reference to global encryption object
        $this->objFTS            = null;                       // reference to global FTS object.  Instanciated in QW.php if search enabled
        $this->MyDebug           = false;
        $this->config            = &$aConfig;                  // QW.php and clsQWiki.php "config" are one and the same.  Changes here apply to QW.php too.
        $this->config['folders'] = null;
        $this->config['files']   = null;
        $this->HtmlHeaderEnabled = false;
        $this->QWCONTENT         = null;                       // when .QWContent.php is evaluated run-time, it's output is saved to:  $this->QWCONTENT
        $this->QWIFRAME          = null;                       // when index.htm* is evaluated at run-time. it's output is saved to:   $this->QWIFRAME          
        $this->FTS_BODY          = null;                       // when .QWContent.php is evaluated run-time, if it contains a <div id=FTS_BODY>...</div> then the content of that div is saved to:  $this->FTS_BODY.  This allows FTS content to be indexed separately from the rest of the content which may contain data that should not be indexed (e.g. urls in iframes).
        
        $this->INIT(null);
    }

    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    public function __destruct() {
        global $objENC;   

        //if ($this->MyDebug) {
        
        $dbgText  = "<h3 class=info>config</h3>";
        //$dbgText .= $this->dump($this->config);

        echo '<div id=qwdebug style="display: none;"><br><br><hr><h2 class=info>Qwiki Debug</h2>';
        //$this->dump($_REQUEST);
        echo "<h3 class=info>config</h3>";
        $this->dump($this->config);
        echo "<h3 class=info>_REQUEST</h3>";
        $this->dump($_REQUEST);
        echo "<h3 class=info>_SESSION</h3>";
        $this->dump($_SESSION);
        //echo "<h3 class=info>_SERVER</h3>";
        //$this->dump($_SERVER);
        //phpinfo();
        echo "</div>";
        //}
        
        // --------------------------------------------------------------------------------
        // Note:  Must manually call child class destruct methods
        //        PHP, for some reason, does not destruct these classes automatically
        // --------------------------------------------------------------------------------
        
        if ($this->HtmlHeaderEnabled) {
            echo "</body></html>";
        }
    }
    
    function dump($var, $toVar = false) {
        echo "<div class=dbg><pre>";
        print_r($var);
        echo "</pre></div>";
    }

    // -----------------------------------------------------------------------------
    // INIT must be called on page load to validate and initialize more $this->config options
    // Almost always, config['folder'] must be provided indicating what folder under rootFolder to view
    //     
    // rootPath:         complete physical path to the root folder of the QW                                    - no trailing slash
    // folder:           relative path to the folder currently being viewed (root folder == folderSep)          - no leading   slash
    // folderSanitized:  The "folder" after being verified that it does not contain illegal characters.
    // folderPath:       Complete physical path to current folder being viewed                                  - May or may not contain trailing slash
    // realPath:         Complete physical path to current folder bing viewed.  Validated by PHP realpath()
    // -----------------------------------------------------------------------------
    function INIT($aOptions) { 
        
        if (is_null($this->config)) return true;

        // folder is relative path to the current qw folder being viewed
		// Defaults to root of current qw (folderSep)
		// Does contain a leading slash   e.g.:   /, /MyDir, /MyDir/MyChildDir
		// Does not contain a trailing slash
        
        // folder to view was specified on query string or by program option
        if (isset($this->config['Options']['folder']))                                               $this->config['folder'] = $this->config['Options']['folder'];
        if (!isset($this->config['folder']) && isset($this->config['Options']['folderSanitized']))   $this->config['folder'] = $this->config['folderSanitized'];
        if (!isset($this->config['folder']) && isset($this->config['folderSanitized']))              $this->config['folder'] = $this->config['folderSanitized'];
        if (!isset($this->config['folder']))                                                         $this->config['folder'] = $this->config['folderSep']; 

		$this->config['folderPath']      = $this->config['rootPath'] . $this->config['folder'];      
        $this->config['folderSanitized'] = $this->sanitizeFolderPath($this->config['folder']);
        $this->config['realPath']        = realpath($this->config['folderPath']); 
        
		if (!is_dir($this->config['folderPath'])){
            echo "<font color=red>ERROR: the Qwiki folder does not exist. Get Help.</font>";
			die;
		}
		
        if (!$this->config['realPath']) {
            echo "<li>ERROR:  real path does not exist:  $this->config['realPath']</li>";
            die;
        }

        $this->config['folderTitle']  = str_replace($this->config['folderSep']," -> ",ltrim($this->config['folder'], $this->config['folderSep']));

        $this->deriveFolderParent($this->config['folderSanitized']);

        // information about the current folder
        $this->config['FOLDER']['rootPath']        = $this->config['rootPath'];
        $this->config['FOLDER']['folder']          = $this->config['folder'];
        $this->config['FOLDER']['folderSanitized'] = $this->config['folderSanitized'];
        $this->config['FOLDER']['folderPath']      = $this->config['folderPath'];
        $this->config['FOLDER']['realPath']        = $this->config['realPath'];        
        $this->config['FOLDER']['folderTitle']     = $this->config['folderTitle'];
        $this->config['FOLDER']['type']            = filetype($this->config['realPath']);
        $this->config['FOLDER']['filemtime']       = filemtime($this->config['realPath']);                           // gmt forlder was created/altered, not last time contents were modified
        $this->config['FOLDER']['filemtimeStr']    = date('Y-m-d H:i:s', filemtime($this->config['realPath']));
        $this->config['FOLDER']['size']            = filesize($this->config['realPath']);
        $this->config['FOLDER']['owner']           = fileowner(($this->config['realPath']));
        $this->config['FOLDER']['permissions']     = substr(sprintf('%o', fileperms($this->config['realPath'])), -4);
        $this->config['FOLDER']['is_writable']     = is_writable($this->config['realPath']);
        $this->config['FOLDER']['dateLastModification'] = $this->config['FOLDER']['filemtime'];                       // may be updated if child file or folder was modified more recently

        //$this->config['FOLDER']['permaLink']       = $this->config['QWPROGRAM'] . "?QWS=" . urlencode($this->config['QWS']) . "&QWI=" . urlencode($this->config['QWI']) . "&folder=" . urlencode($this->config['folderSanitized']);
        $this->config['FOLDER']['permaLink']       = "QWS=" . urlencode($this->config['QWS']) . "&QWI=" . urlencode($this->config['QWI']) . "&folder=" . urlencode($this->config['folderSanitized']);
        //echo "<li>TEST:  <a href='" . $xURL . "'>Test URL</a></li>";
     }
    
    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    public function ReturnToQwikiButton($aOptions) {
        $xOptions = "folder=" . $this->config['folder'] . "&realPath=" . $this->config['realPath'] . "&folderSep=" . $this->config['folderSep'];
        $this->config['ReturnToQwikiURL'] = $xOptions;
        $xOptions = $this->objENC->encryptData($xOptions); 
        $xURL = $this->config['QWSERVERURL'] . "?" . $xOptions;
        
        echo <<<EOHERE
        <div class="button" onclick="GoToURL('$xURL','_self'); return false;">
        <button>Return To Qwiki</button>
        </div>
        EOHERE;
    }
    
    // -----------------------------------------------------------------------------
    // Called by QW application:  QW.php, when displaying a html web page
    // $this->__destruct() prints /body & /html tags
    //
    // Test Performance Metrics
    // outdated: <script type="text/javascript" src="clsPerformanceTiming.js"></script>
    // <script type="text/javascript" src="clsPerformanceNavigationTiming.js"></script>  
    // -----------------------------------------------------------------------------
    public function showHtmlHeader($xOptions=null) {
        $this->HtmlHeaderEnabled = true;
        $x = $this->config['CUSTOMBODY'];
        echo <<<EOWHERE
        <html>
        <head>
        <script type="text/javascript" src="QW.js"></script>
        <link rel="stylesheet" type="text/css" href="QW.css">
        </head>
        <body $x>
        EOWHERE;
    }
    
    // -----------------------------------------------------------------------------
	// Sanitize folder path.  
    // Path must remain RELATIVE to rootPath (aka: chroot)
	// Remove leading & multiple sequential slash(es)
	// -----------------------------------------------------------------------------
	public function sanitizeFolderPath ($xFolder) {
		
		if (is_null($xFolder) || (strcmp($xFolder,"")==0)) return $this->config['folderSep'];
        
		// Deprecated in PHP 8.1+
        // removes tags and encode special characters
		// $xFolder = filter_var($xFolder, FILTER_SANITIZE_STRING);
		
		// replace more than 1 consequitive slash with a single slash
		$xFolder = preg_replace('#/{2,}#', DIRECTORY_SEPARATOR, $xFolder);
		$xFolder = preg_replace('#/{2,}#', '/', $xFolder);
		
		// remove leading slash if it exists
        // search expects a leading slash?  Will this break anything else?
		// $xFolder = preg_replace('{^/*}','',$xFolder);

		// If folder contains a parent folder reference immediately throw error as this should never-ever happen
		if (strpos($xFolder, '..') !== false) {
			echo "ERROR 194: folder path contains illegal characters, Get Help";
			die;
		}
		
		return $xFolder;
	}
    
    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
   public function displayQwikiMain($aOptions) {
        
        // INIT() is called on class instanciation for all cases.
        // Why is this here a second time?  
        // Possibly to support when pickqwiki() processes user choice which ma be different than what was previously chosen?
        // -  If so, then INIT should be called by pickqwiki()
        $this->INIT(null);

        $this->scanFolderFiles(null);
        
        // TODO:  Enable $aFeatures based on optioning?
        $aFeatures = array('displayPageOptions','displayGrid','displayFolders','displayFiles','displayContent','displayUrlInIframe','searchSubmitUpdates');  
        
        foreach ($aFeatures as $xFeature) {
            $xOpt = null;
            if (strcmp($xFeature,"displayContent")==0) {
                $xOpt = Array();
                //$xOpt['method'] = "JS";                      // if JS is used QW cannot index "content" and "iframe" data
                //$xOpt['method'] = "FILEGETCONTENTS";         // possibly does not support https?  See function for comments
                $xOpt['method'] = "CURL";
            }
            
            // See:  https://stackoverflow.com/questions/1005857/how-to-call-a-function-from-a-string-stored-in-a-variable
            // See:  above URL for how to unpack function arguments stored in an array.  QW mostly accepts a single array as the only argument though...
            $xRet = self::$xFeature($xOpt);
        }
        return TRUE;
    }
     
    
    // Don't really use this...
    // Recursively sort associative array independent of case
    public function sortNestedArrayAssoc(&$a) {
        ksort($a,SORT_FLAG_CASE|SORT_STRING);
        foreach ($a as $key => $value) {
            if (is_array($value)) {
                $this->sortNestedArrayAssoc($value);
            }
        }
    }

    // -----------------------------------------------------------------------------
    // https://webcheatsheet.com/php/working_with_directories
    // https://www.phptutorial.net/php-tutorial/php-file-permissions/
    // -----------------------------------------------------------------------------
    public function scanFolderFiles($aOptions) {

        if (!is_dir($this->config['FOLDER']['realPath'])){
            echo "ERROR: the specified folderPath does not exist anymore...";
			die;
		}

        $items   = scandir($this->config['FOLDER']['realPath']);
        
        // -----------------------------------------------------------------------------
        // Detect if this folder contains files generated by jAlbum.
        // If it was created by jalbum, "data1.json" contains metadata about what files should be displayed.
        // Replace $items with only those files/folders specified in data1.json.
        // TODO:  This process and any other jAlbum custom rules should be in a TBP clsJalbum class.  
        // ----------------------------------------------------------------------------
        $xFile = $this->config['FOLDER']['realPath'] . $this->config['folderSep'] . "data1.json";
        if (file_exists($xFile)){
            $this->config['jAlbumData1Arr'] = json_decode(file_get_contents($xFile), true);
            if (!is_array($this->config['jAlbumData1Arr'])) {
                echo "<li>ERROR:  jAlbum data1.json file exists but was not converted to a valid ARRAY: (" . $xFile . ")</li>";
             } else {      
                $items = [];
                foreach ($this->config['jAlbumData1Arr']['objects'] as $obj) {
                    $xItem = $this->config['FOLDER']['realPath'] . $this->config['folderSep'] . $obj['name'];            
                    if (!file_exists($xItem) && !is_dir($xItem) ) {
                        echo "<li>ERROR:  jAlbum data1.json object does not exist: (" . $xItem . ")</li>";
                        continue;
                    }
                    array_push($items, $obj['name']);
                }
                $this->config['FEATURE']['GRID']  = true;
                $this->config['FEATURE']['FILES'] = false;
            }
        }

        # -----------------------------------------------------------------------------
        # Automatically enable/toggle displayGrid() when folder name matches those that commonly store media.
        # TODO:  Should be a function that can be called by multiple features, not just when scanning.
        # TODO:  Upgrade folder path scan to support when specification includes multiple levels of folders.
        # -----------------------------------------------------------------------------
        $xbn = strtolower(basename($this->config['folder']));
        if (in_array(strtolower($xbn), $this->config['GRID']['FolderNames'], true)) {
            $this->config['FEATURE']['GRID']  = true;
            $this->config['FEATURE']['FILES'] = false;
        }


        foreach ($items as $item) {
            
            $itemPath  = $this->config['FOLDER']['realPath'] . $this->config['folderSep'] . $item;
            $xFileTime = null;

            if (is_dir($itemPath) && $item != '.' && $item != '..') {
                $this->config['folders'][$item] = [
                    'name'        => $item,
                    'realpath'    => realpath($itemPath),
                    'type'        => filetype($itemPath),
                    'filemtime'   => filemtime($itemPath),
                    'created'     => date('Y-m-d H:i:s', filectime($itemPath)),
                    'modified'    => date('Y-m-d H:i:s', filemtime($itemPath)),
                    'size'        => filesize($itemPath),
                    'owner'       => fileowner(($itemPath)),                                // always returns zero on Windows because it does not support numeric user ids
                    'permissions' => substr(sprintf('%o', fileperms($itemPath)), -4),
                    'is_writable' => is_writable($itemPath),
                ];

                $xFileTime = filemtime($itemPath);

            } elseif (is_file($itemPath) && is_readable($itemPath) ) {
                
                // Determine if file is an image.  Needed by grid and content display modes.
                // ERROR: getimagesize() does not work?
                $xMimeType = mime_content_type($itemPath);
                if (in_array($xMimeType, $this->config['IMAGES']['supported_types'], true)) {    
                    $xIsImage = true;
                } else {
                    $xIsImage = false;
                }

                $this->config['files'][$item] = [
                    'name'        => $item,
                    'realpath'    => realpath($itemPath),
                    'type'        => filetype($itemPath),
                    'filemtime'   => filemtime($itemPath),
                    'modified'    => date('Y-m-d H:i:s', filemtime($itemPath)),
                    'created'     => date('Y-m-d H:i:s', filectime($itemPath)),
                    'owner'       => fileowner(($itemPath)),                                // always returns zero on Windows because it does not support numeric user ids
                    'permissions' => substr(sprintf('%o', fileperms($itemPath)), -4),
                    'size'        => filesize($itemPath),
                    'is_writable' => is_writable($itemPath),
                    'mimeType'    => mime_content_type($itemPath),
                    'is_image'    => $xIsImage
                ];

                # Add parameters based on file mime type
                if ($this->gridOptions($this->config['files'][$item])) {
                    // $this->dump($this->config['files'][$item]);
                };

      

                $xFileTime = filemtime($itemPath);
            }


            // TODO:  xKey is constructed in multiple places.  Should be defined in 1 common location.
            // See:   $this->searchSubmitUpdates()
            if (!is_null($xFileTime) && isset($_SESSION['QWFTSUPDATES']) && is_array($_SESSION['QWFTSUPDATES'])) {
                $xKey = $this->config['QWI'] . ":/" . str_replace("\\","/",ltrim($this->config['folder']));
                if ( array_key_exists($xKey, $_SESSION['QWFTSUPDATES'])) {
                    $iVal = (int) $_SESSION['QWFTSUPDATES'][$xKey];
                    // item modified after last FTS update for current folder.   Force another update.
                    if ($xFileTime > $iVal) {
                        $this->config['FTS']['FORCEUPDATE'] = true;
                    }
                }
            }
            
            if ($xFileTime > $this->config['FOLDER']['dateLastModification']) $this->config['FOLDER']['dateLastModification'] = $xFileTime;

        }       

        // Sort files and folders case insensitive
        // https://www.php.net/manual/en/function.ksort.php
        // https://brainbell.com/php/sorting-nested-arrays.html
        // $this->sortNestedArrayAssoc($this->config['folders']);
        if (!is_null($this->config['folders'])) ksort($this->config['folders'],SORT_FLAG_CASE|SORT_STRING);
        if (!is_null($this->config['files']))   ksort($this->config['files']  ,SORT_FLAG_CASE|SORT_STRING);
        
        return true;
    }
    
    // -----------------------------------------------------------------------------
    // derive folders parent relative to the root of the Qwiki.
    // -----------------------------------------------------------------------------
    public function deriveFolderParent($aOptions) {
        $this->config['folderParent'] = $this->config['folderSep'];     // default to Qwiki root home
        if (is_null($this->config['folder']) ||  (strcmp($this->config['folder'],"")==0) ) return null;
        $xArr = explode($this->config['folderSep'], $this->config['folder']);    
        $xCnt = sizeof($xArr);
        if ($xCnt<=2) return null;
        unset($xArr[$xCnt-1]);
        $this->config['folderParent'] = implode($this->config['folderSep'],$xArr);
        return true;
    }

    // -----------------------------------------------------------------------------
    // TODO:  Write alternative to support cell phone.  Menu should be a pull-down list.
    // -----------------------------------------------------------------------------
    public function displayPageOptions() {  
        
        echo "<table class=vs width='100%'><tr>";

        // -------------------------------------------------
        // QW Application Home Folder
        // -------------------------------------------------
        echo '<td align=center style="width: 1%; white-space: nowrap;">' .
            '<a href="' . $this->config['QWSERVERURL'] . '" title="Qwiki Home">' . 
            '<img src="' . $this->config['QWURL'] . '/Images/Icon_FolderHome.png" border=0 height=30>' .
            '<br><span class=comment>Home</span></a>' .
            '</td>';
        
        // -------------------------------------------------
        // REFRESH PAGE
        // -------------------------------------------------
        echo '<td align=center style="width: 1%; white-space: nowrap;" >' .
            '<a href="#" onclick="location.reload(); return false;"  title="Reload Page">' . 
            '<img src="' . $this->config['QWURL'] . '/Images/Icon_FolderRefresh.png" border=0 height=30>' .
            '<br><span class=comment>Refresh</span></a>' .
            '</td>';
            
            
        // -------------------------------------------------
        // NEW FOLDER
        // -------------------------------------------------
        $xOptions = "FileIO_Action=CreateFolder&folder=" . $this->config['folder'];
        $xOptions = $this->objENC->encryptData($xOptions);
        $xURL     = $this->config['QWSERVERURL'] . "?" . $xOptions;    
        
        echo '<td align=center style="width: 1%; white-space: nowrap;" >' .
            '<a href="' . $xURL . '" title="Create New Folder In Current Working Directory">' .
            '<img src="' . $this->config['QWURL'] . '/Images/Icon_FolderAdd.png" border=0 height=30>' .
            '<br><span class=comment>New Dir</span></a>' .
            '</td>';
        
        // -------------------------------------------------
        // Upload
        // -------------------------------------------------
        if ($this->config['FEATURE']['UPLOAD'] ) {
            $xUploadOpt = "upload=1&folder=" . $this->config['folder'];
            if (!is_null($this->config['folderPath']) && strcmp($this->config['folderPath'],"")!=0 ) {
                $xUploadOpt = $xUploadOpt . "&folderPath=" . $this->config['folderPath'];
            }
            $xUploadOpt = $this->objENC->encryptData($xUploadOpt);
            
            $xUploadURL = $this->config['QWSERVERURL'] . "?" . $xUploadOpt;
    
            echo '<td align=center style="width: 1%; white-space: nowrap;">' .
                '<a href="' . $xUploadURL . '" title="Upload (replace) File(s) In Current Working Directory">' .
                '<img src="' . $this->config['QWURL'] . '/Images/Icon_FolderUpload.png" border=0 height=30>' .
                '<br><span class=comment>Upload</span></a>' .
                '</td>';
        }
                   
        // -------------------------------------------------
        // SEARCH
        // -------------------------------------------------
        if ($this->config['FEATURE']['SEARCH'] ) {
            $xSearchOpt = "FTS_ENABLED=1";
            $xSearchOpt = $this->objENC->encryptData($xSearchOpt);
            echo '<td align=center style="width: 1%; white-space: nowrap;">' .
                '<a href="'  . $this->config['QWURL'] . '/QW.php?' . $xSearchOpt . '">' .
                '<img src="' . $this->config['QWURL'] . '/Images/Icon_FolderSearch.jpg" border=0 height=30>' .
                '<br><span class=comment>Search</span></a>' .
                '</td>';
        }
        
        // -------------------------------------------------
        // EDIT
        // -------------------------------------------------
        if ($this->config['FEATURE']['EDIT'] ) {
            $xOptions = "Editor=1&folder=" . $this->config['folder'] . "&EditorFile=" . $this->config['folder'] . $this->config['folderSep'] . "-Content.php";
            $xOptions = $this->objENC->encryptData($xOptions);
            $xURL  = $this->config['QWSERVERURL'] . "?" . $xOptions;
        
            echo '<td align=center style="width: 1%; white-space: nowrap;">' .
                "<a href=\"#\" onClick=\"window.open('" . $xURL . "');\" title='Edit Qwiki Content'>" .
                '<img src="' . $this->config['QWURL'] . '/Images/Icon_EditText2.png" border=0 height=30 title="Edit Qwiki Content">' .
                '<br><span class=comment>Edit</span></a>' .
                '</td>';
        }
        
        // -------------------------------------------------
        // UPDIR
        // -------------------------------------------------
        $xUpDirOpt = "";
        if (!is_null($this->config['folderParent']) && strcmp($this->config['folderParent'],"")!=0 ) {
            $xUpDirOpt = "folder=" . $this->config['folderParent'];
            $xUpDirOpt = $this->objENC->encryptData($xUpDirOpt);
            $xUpDirOpt = "?" . $xUpDirOpt;
        }
        echo '<td align=center style="width: 1%; white-space: nowrap;">' .
            '<a href="'  . $this->config['QWSERVERURL'] . $xUpDirOpt . '" title="Up To Parent Directory">' .
            '<img src="' . $this->config['QWURL'] . '/Images/Icon_FolderUp2.png" border=0 height=30>' .
            '<br><span class=comment>UpDir</span></a>' .
            '</td>';
        
        // -------------------------------------- ---------
        // BACK
        // -------------------------------------------------
        echo '<td align=center style="width: 1%;  ite-space: nowrap;">' .
            '<a href="#" onclick="javascript: history.back(); return false">' .
            '<img src="' . $this->config['QWURL'] . '/Images/Icon_GreenCircleLeftArrow.png" border=0 height=30 title="Back to previous page">' .
            '<br><span class=comment>Back</span></a>' .
            '</td>';
        
        // -------------------------------------- ---------
        // Show development/test options
        // This function includes testing for opening a debug window and writing messages to it from the parent window.  Not fully implemented yet.
        // -------------------------------------------------
        // $this->ShowDevOptions(null);

        // -------------------------------------------------
        // FOLDER TITLE
        // -------------------------------------------------
        echo '<td width="40%" align=center>' .
            '<b><font color=red style="font-size: 22;">'        . $this->config['QWNAME']      . '</font></b>' . 
            '<br><em><font color=green style="font-size: 18;">' . $this->config['folderTitle'] . '</font></em>' . 
            '</td>';   
        
        // -------------------------------------------------
        // USER IDENTITY
        // -------------------------------------------------
        if ($this->config['FEATURE']['AUTH'] ) {
            if (isset($this->objUser->config['User']['id'])) {
                echo '<td align=center>' .
                    '<b><font color=blue style="font-size: 14;">Hi, ' . ($this->objUser->config['User']['First']) . '</font></b>' . 
                    '</td>';
            }
        }
        
        // -------------------------------------------------
        // PROGRAM OPTIONS
        // -------------------------------------------------
        $this->nav_menu(null);

        // -------------------------------------------------
        // USER AUTHORIZATION
        // -------------------------------------------------
        if ($this->config['FEATURE']['AUTH'] ) {
            $xOpt = "Profile=1&folder=" . $this->config['folder'];
            $xOpt = $this->objENC->encryptData($xOpt);
            echo '<td align=right style="width: 1%; white-space: nowrap;">' . 
                '<a href="'  . $this->config['QWSERVERURL'] . '?' . $xOpt . '">' .
                '<img src="' . $this->config['QWURL'] . '/Images/Icon_Profile1.png" border=0 height=30 title="Profile">' .
                '</a></td>'; 
        }


        echo "</tr></table>";
        
        return TRUE;
    }
    
    // ----------------------------------------------------------------------
    // QWiki Debugging Test 
    // Not fully implemented yet.  Can open window....  Need to be able to write debug messages when page load terminates
	// ----------------------------------------------------------------------
	public function ShowDevOptions($aOptions) {

        echo <<< EOHERE
            <td align=center ite-space: nowrap;">
            <script>
    
            var QWwindowName         = "QWwindowName";
            window.windowParent      = window;
            window.name              = QWwindowName;
    
            var windowDebugName      = "QWwindowDebugName";
            window.windowDebug       = null;
            window.windowDebugName   = windowDebugName;
    
            function openDebugWindow (xUrl, xTarget, xFeatures) {
                if ( typeof(window.windowDebug) !== "undefined" && window.windowDebug !== null ) return;
                window.windowDebug   = window.open(xUrl,xTarget,xFeatures);
                window.windowDebug.addEventListener('load', window.debugWindowLoaded, true); 
            };
            function debugWindowLoaded() {
                window.windowDebug.name         = windowDebugName;
                window.windowDebug.windowParent = window.windowParent;   // Verify this passes parent window to debug window ?????
                window.windowDebug.document.getElementById('debugWindowText').innerHTML += "<li>...Qwiki debugging enabled</li>";
                window.focus();
            };
            function debugWindowClose() {
                if (window.windowDebug != null) window.windowDebug.close();
                window.windowDebug = null;
            };
            function debugWindowText(xText) {
                if (window.windowDebug != null) {
                    window.windowDebug.document.getElementById('debugWindowText').innerHTML += xText;
                };
            };
    
            // Close debug window which is  bound to the currently running Qwiki browser instance
            // GAK:  onbeforeunload executes when page is reloaded/refreshed too, not just when the page instance terminates
            // window.onbeforeunload = debugWindowClose;
    
            window.onload = function () { 
                debugWindowText('<h2>QWiki Window Completed Loading</h2>');
            }
    
            // If needed, this is how to open the debug window on-load...
            // openDebugWindow('https://susiepc/QWiki/QW/QWDebug.php','_QWdebug','width=1200,height=200');
    
            </script>
    
            <div class=debugWindow>
            <button onclick="openDebugWindow('https://susiepc/QWiki/QW/QWDebug.php','_QWdebug','width=1200,height=200');">Enable<br>Debugging</button>
            &nbsp;
            <button onclick="debugWindowText('<li>This is additional debug text</li>');">Append<br>Text</button>
            &nbsp;
            <button onclick="debugWindowClose();">Close Debug<br>Window</button>
            </td>
        
        EOHERE;
            
    }
        
    
    // ----------------------------------------------------------------------
	// ----------------------------------------------------------------------
	public function nav_profile($aOptions) {
        $QWURL = $this->config['QWURL'];
        $qwHP  = $this->config['QWSERVERURL'];
    }

    // ----------------------------------------------------------------------
	// ----------------------------------------------------------------------
	public function nav_menu($aOptions) {
        $QWURL  = $this->config['QWURL'];
        $qwHP   = $this->config['QWSERVERURL'];
        $qwD    = $this->config['QWSERVERURL'] . "?ChooseQwiki=1";
        $qwPURL = $this->config['QWSERVERURL'] . "?" . $this->config['FOLDER']['permaLink'];
        
        echo <<<EOHERE
        <style>
          .menu-popup {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 120px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            padding: 8px;
            z-index: 1;
            overflow-y: auto;
            max-height: 200px;
          }
          .menu-popup a {
            color: black;
            padding: 8px 0;
            text-decoration: none;
            display: block;
          }
          .menu-popup a:hover {
            background-color: #f1f1f1;
          }
        </style>
        
        <td align=right style="width: 1%; white-space: nowrap;">
        <img src="$QWURL/Images/Icon_HamburgerMenu2.png"  border=0 height=30 title="Menu"   style="cursor: pointer;" onclick="showMenuPopup(event)"> 
        </td>
        
        <div id="menuPopup" class="menu-popup" onmouseleave="hideMenuPopup()">
        <a href="#" onclick="openURL('$qwHP','_self')">Qwiki Home Page</a>
        <a href="#" onclick="openURL('$qwD','_self')">Select a Different Qwiki</a>
          <!-- <a href="#" onclick="showDebugWindow('qwdebug'); return false;">Qwiki Debugging Info</a> -->
          <a href="#" onclick="changeFontSize('increase')">Increase Font Size</a>
          <a href="#" onclick="changeFontSize('decrease')">Decrease Font Size</a>
          <a href="#" onClick="copyToClipboard('$qwPURL'); alert('The PermaLink URL of this file was copied to the browser clippad: $qwPURL');">Copy PermaLink to Clipboard</a>
          <a href="#" onclick="openURL('https://github.com/ericledberg','_blank')">Qwiki GitHub</a>
        </div>

        <script>
        function showMenuPopup(event) {
          event.preventDefault();
          var menuPopup = document.getElementById("menuPopup");
          menuPopup.style.display = "block";
          menuPopup.style.left = (event.pageX - 100) + "px";
          menuPopup.style.top  = (event.pageY + 10) + "px";
          menuPopup.focus();
        }      
        function showDebugWindow(element) {
            document.getElementById("menuPopup").style.display = "none";
            if (!document.getElementById(element)) return;
            toggleElementVisibility('qwdebug');
        }	
        function toggleElementVisibility(element) {
            if (!document.getElementById(element)) return;
            if (document.getElementById(element).style.display === 'none') {
                document.getElementById(element).style.display       = 'block';
            } else {
                document.getElementById(element).style.display       = 'none';
            }
        }
        function hideMenuPopup() {
          var menuPopup = document.getElementById("menuPopup");
          menuPopup.style.display = "none";
        }        
        function openURL(url,target) {
          window.open(url,target);
        }        
        function changeFontSize(action) {
          var body = document.body;
          var currentSize = parseFloat(window.getComputedStyle(body, null).getPropertyValue('font-size'));
          var newSize = action === 'increase' ? currentSize + 1 : currentSize - 1;
          body.style.fontSize = newSize + 'px';
        }
        </script>        
        EOHERE;
    }

    // --------------------------------------------\
    // Resolve ICON to display for various file types.
    // Defaults to a generic file icon.
    // --------------------------------------------
    
    var $gaFileTypes = null;
    private function gridOptions(&$file) {

        if (is_null($this->gaFileTypes)) {
            $this->gaFileTypes = $this->config['FILETYPES']['type'];
        }
        if (!is_array($this->gaFileTypes)) return false;

        # Derive file extension using trailing .xxx and mime type.  
        # Always use mime type as primary since file extensions can be spoofed/manipulated
        # Default to a generic file icon

        $xExtension = pathinfo($file['name'], PATHINFO_EXTENSION);

        $xMimeType = "";
        if (array_key_exists('mimeType',$file)) {
            $xMimeType = $file['mimeType'];
            $xMimeType = explode('/',$xMimeType)[1];
        }
        
        foreach ($this->gaFileTypes as $aTypes) {
            $curName = null;
            $curIcon = null;
            $curMimeType = null;
            if (array_key_exists('_name',$aTypes))     $curName = $aTypes['_name'];
            if (array_key_exists('_icon',$aTypes))     $curIcon = $aTypes['_icon'];
            if (array_key_exists('_mimeType',$aTypes)) $curMimeType = $aTypes['_mimeType'];
            
            if (!is_array($aTypes['ext'])) {
                echo "<li>gridOptions:  ERROR: ext is not an array</li>";
                continue;   
            }       

            $xCnt    = 0;
            $xCntMax = count($aTypes['ext']);

            while ($xCnt<$xCntMax) {
                $xExt = $aTypes['ext'][$xCnt];
                
                $xDoit = false;
                if (strcmp($xMimeType,$xExt)==0) {
                    $xDoit = true;
                } else if (strcmp($xExtension,$xExt)==0) {
                    $xDoit = true;
                }
                
                if ($xDoit) {
                    if (is_null($curIcon)) {
                        // echo "<li>gridOptions:  INFO: file: " . $file['name'] . ", xMimeType: $xMimeType, icon not defined</li>";
                        break;   
                    }
                    $file['gridIcon'] = $curIcon;
                    //echo "<li>gridOptions:  MATCH FOUND!  xMimeType: $xMimeType, xExt: $xExt, xIcon: $curIcon</li>";
                    return true;
                 }

                $xCnt = $xCnt + 1;
            }
        }

        # TODO:  should initialize a default generic file icon in the config and assign it here if no match is found.  For now, just return false and display file name without an icon.

        return false;
    }
    
    // --------------------------------------------
    // Display folder content in a grid format.  This alternative view shows visual thumbnails for images and files.  
    // Grid view is better at browsing images stored in a folder.
    // ISSUE:  current method does not support pagnation (ignores xMaxRows) and displays all items on single page.
    // --------------------------------------------
    private function displayGrid() { 

        if (!$this->config['FEATURE']['GRID']) return true;

        echo '<h2>Files</h2>';
     
        if (!is_array($this->config['files'])) return true;
        
        $xMaxColumns = $this->config['GRID']['colsPerPage'];         // max number of columns before starting a new row
        $xMaxRows    = $this->config['GRID']['rowsPerPage'];         // max number of rows per ctable before starting a new page
        
        $xCurRow  = 0;             // current number of folders printed in a column
        $xCurCell = 0;            // current number of folders printed in a column
        $xHead    = true;
        
        
        $xHeadTxt = "<div class=qwgridcontainer>";
        $xHeadTxt = $xHeadTxt . '<table class=qwgrid border=0 cellpadding=0 cellspacing=0 bgcolor="#ffffe6"><tr>';

        foreach ($this->config['files'] as $file) {

            $aOptions['fileName']    = $file['name'];
            $aOptions['displayMode'] = "fileGrid";               # display images and files in a grid
            
            # Only display authorized files
            if (!$this->isFileMatched($aOptions)) continue;       
           
            if ($xHead){
                echo $xHeadTxt;
                $xHead = false;
            }

            echo "<td align=center valign=middle style='white-space: nowrap; padding-top: 3px; padding-bottom: 3px; padding-left: 3px; padding-right: 3px;'>";

            # TODO:  URL to each itme should be encoded/obfuscated to prevent user from directly accessing file outside of Qwiki interface
            if (array_key_exists('gridIcon',$file) && !is_null($file['gridIcon'])) {
                $xURL = $this->config['rootURL'] . $this->config['folder'] . $this->config['folderSep'] . $file['name'];
                echo "<a href='$xURL'><img src='" . $this->config['QWURL'] . "/Images/" . $file['gridIcon'] . "' border=0 style='max-width: 75px; max-height: 75px;'>";
                echo "<br><small>" . $file['name'] . "</small></a>";

            } else if ($file['is_image']===true) {
                $xURL = $this->config['rootURL'] . $this->config['folder'] . $this->config['folderSep'] . $file['name'];
                echo "<a href='$xURL'><img src='$xURL' border=0 style='max-width: 75px; max-height: 75px;'>";
                echo "<br><small>" . $file['name'] . "</small></a>";
            } else {
                # TODO:  add default icon
                echo $file['name'];
            }
            echo "</td>";
            
            $xCurCell = $xCurCell + 1;
            if ($xCurCell>=$xMaxColumns) {
                echo "</tr><tr>";
                $xCurCell = 0;
            }
        }
        # If something was display pad out the end of the row and close the table
        if (!$xHead) {
            while ($xCurCell>0 && $xCurCell<$xMaxColumns) {
                echo "<td>&nbsp;</td>";
                $xCurCell = $xCurCell + 1;
            }
            echo "</tr></table>";
            echo "</div>";
        }

        return true;
    }

    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    private function displayFiles($aOptions) {
        global $objENC;

        if (!$this->config['FEATURE']['FILES']) return true;

        if (!is_array($this->config['files'])) return true;
        
        $xHead    = true;
        $xHeadTxt = <<<EOHERE
        <h2>Files</h2>
        <table border=1 cellpadding=4 cellspacing=0>
        <tr>
        <th>File</th>
        <th>Modified</th>
        <th>Size</th>
        <th>Admin</th>
        </tr>
        EOHERE;

        foreach ($this->config['files'] as $file) {
            
            $aOptions['fileName'] = $file['name'];

            if ($this->isFileMatched($aOptions)) {
                if ($xHead){
                    echo $xHeadTxt;
                    $xHead = false;
                }
                $xfURL  = $this->config['rootURL']  . $this->config['folder'] . $this->config['folderSep'] . $file['name'];
                $xfPath = $this->config['rootPath'] . $this->config['folder'] . $this->config['folderSep'] . $file['name'];
                $xfPath = realpath($xfPath);
                //echo "<li>xfPath: $xfPath</li>";
                //$x = $this->objENC->strToHex($xfPath);
                //echo "<li>strToHex: " . $this->objENC->strToHex($xfPath) . "</li>";
                // echo "<li>strToHex: " . $this->objENC->hexToStr($x) . "</li>";
                $xfPath = $this->objENC->encryptData("FileIO_Action=Manage&folder=" . $this->config['folder'] . "&realPath=" . $xfPath . "&FileIO_filePath=" . $this->objENC->strToHex($xfPath));
                $xMURL  = $this->config['QWSERVERURL'] . "?" . $xfPath;
                echo "<tr>
                    <td>
                    <a href='$xfURL'>{$file['name']}</a></td>
                    <td>{$file['modified']}</td>
                    <td>{$file['size']}</td>
                    <td>
                        <a href='#' onClick=\"location.href='$xMURL'\">Manage</a>
                    </td>
                </tr>" . "\n";

            }
        }
        if (!$xHead) {
            echo "</table>" . "\n";
        }
    }
    
    // --------------------------------------------
    // display $xCurCnt folders in each column
    // --------------------------------------------
    private function displayFolders() { 
        
        if (!$this->config['FEATURE']['FOLDERS']) return true;

        $xCurCnt     = 0;             // current number of folders printed in a column
        $xMaxCnt     = 0;             // Max number of folder names listed in a column

        $xCurColumn  = 1;             // Current column
        $xMaxColumns = 5;             // max number of columns

        // no folders exist
        if (is_null($this->config['folders']) )  return true;
        if (!is_array($this->config['folders'])) return true;
        
        $xNumFolders = count($this->config['folders']);
        if ($xNumFolders==0) return true;
        
        $xMaxCnt = intval($xNumFolders/$xMaxColumns)+1;

        echo '<h2>Folders</h2><table class=qwfolders border=0><tr><td valign=top  style="white-space: nowrap;">' . "\n";

        foreach ($this->config['folders'] as $folder) {         
            // Delete ROOT path.  Pathing must be relative to $this->config['rootPath']
            $relFolderName = preg_replace('/^' . preg_quote($this->config['rootPath'], '/') . '/', '', $this->config['folderPath'] . $this->config['folderSep'] . $folder['name']);
            $relFolderName = str_replace('\\\\','\\',$relFolderName);
            $folderName    = basename($folder['name']);
            $xQS           = 'folder=' . $relFolderName;
            $xQS           = $this->objENC->encryptData($xQS);

            echo '<a href="' . $this->config['QWSERVERURL'] . '?' . $xQS . '">' . $folderName . '</a><br>' . "\n";
            
            $xCurCnt = $xCurCnt + 1;
            if ($xCurCnt>=$xMaxCnt) {
                echo '</td><td width=15>&nbsp</td><td valign=top style="white-space: nowrap;">' . "\n";
                $xCurCnt = 0;
            }

         }
         echo "</td><td>";
         echo "</tr>";
         echo '</table>' . "\n";
         return TRUE;
     }

   // -----------------------------------------------------------------------------
   // save for now
   // -----------------------------------------------------------------------------
   private function displayFoldersDIV() {
             
        // no folders exist in cwd
        if (is_null($this->config['folders']) ) return true;

        echo '<div style="column-count: 5;">';
         foreach ($this->config['folders'] as $folder) {         
             // Delete ROOT path.  All pathing must be relative to $this->config['rootPath']
             $relFolderName = preg_replace('/^' . preg_quote($this->config['rootPath'], '/') . '/', '', $this->config['folderPath'] . "\\" . $folder['name']);
             $folderName    = basename($folder['name']);
             echo '<div>' . '<a href="' . $this->qwconfig['QWSERVERURL'] . '?folder=' . $relFolderName . '">' . $folderName . '</a></div>';
         }
         echo '</div>';
         return TRUE;
     }
  
    
    // -----------------------------------------------------------------------------
    // client browser loads $xURL asynchronously and execute callback (see QW.js)
    // Loads results into html object identified by it's ID
    // NOTE:  JS is the only method that natively verifies https/SSL certificate authority (since the browser natively supports it)
    //        There is a slight load delay between when the html page loads and it's JS engine retrieves and displays qw content
    //        Requires:  QW.js
    // -----------------------------------------------------------------------------
    private function displayContent($aOptions) {
        
        // Default is for browser client to load content
        //$xMethod = "FILEGETCONTENTS";
        //$xMethod = "CURL";
        $xMethod = "JS";
        if (!is_null($aOptions)) {
            if ( (strcmp($aOptions['method'],"")!=0)) {
                $xMethod = $aOptions['method'];
            }
        }
        
        $xContent = $this->config['folderPath'] . $this->config['folderSep'] . $this->config['contentFileName'];

        // --------------------------------------------
        // ELE HACK:  automatically rename -Content.asp to -Content.php
        // .asp pages was the original default.  
        // .php extenstion is now used since it's portable to both windows and linux.
        // this program automatically changes extension from .asp to .php when in "editor" mode.
        // --------------------------------------------
        $xContentOLD = $this->config['folderPath'] . $this->config['folderSep'] . "-Content.asp";
        if (file_exists($xContentOLD)) {
            $this->config['contentAutoRenamed'] = "displayContent(): " . $xContentOLD . ' to: ' . $xContent;
            if (!rename($xContentOLD,$xContent)) echo "displayContent(): ERROR: ELE HACK:  failed to rename old content file:  $xContentOLD";
        }
        
        if (!file_exists($xContent)) return true;
        $this->config['qwcontent'] = $xContent;
        
        echo "<div id=qwcontent>&nbsp;</div>";
        
        $xURL    = str_replace("\\","/",$this->config['folder']);    // is this needed?
        $xURL    = $this->config['rootURL'] . $xURL . "/" . $this->config['contentFileName'] ;
        
        if ($xMethod==="JS") {
            return $this->displayContentJS($xURL,"qwcontent");

        } elseif ($xMethod==="CURL") {
            // TODO:  curlMethod should be in aOptions...
            $curlMethod       = 'GET';
            //$curlMethod     = 'POST';
            $postFields       = ['param1' => 'value1', 'param2' => 'value2'];
            $cookies          = ['cookie1' => 'value1', 'cookie2' => 'value2'];
            $this->QWCONTENT  = $this->submitCurlRequest($xURL, $curlMethod, $postFields, $cookies);
            
            echo $this->QWCONTENT;
            return true;

        }

        // default: FILEGETCONTENTS
        $method     = 'POST';
        $postFields = ['param1' => 'value1', 'param2' => 'value2'];
        $cookies    = ['cookie1' => 'value1', 'cookie2' => 'value2'];
        return $result = $this->submitGetFileContentsRequest($xURL, $method, $postFields, $cookies);
        
    }
    
    // -----------------------------------------------------------------------------
    // Load URL in and IFrame
    // Method 1:   Program the client to load the URL
    // Method 2:   Have the server load the URL and dump it to the client
    // ISSUE:      FTS can build an index of the contents of the URL using Method #2, but can't using Method 1.
    //             For now, always use method 2 which will index and update the FTS search database with URL contents
    //             ==> similar to how QW loads QWCONTENT (the text entered in the QW editor)
    //
    // TODO:  check for different allowed file names and case issues
    // currently supports:  index.html, index.htm
    // -----------------------------------------------------------------------------
    public function displayUrlInIframe () {

        // Do not display QW root folder
        if ( (strcmp($this->config['folder'],"\\\\")==0)  || (strcmp($this->config['folder'],"//")==0) || (strcmp($this->config['folder'],"")==0) || is_null($this->config['folder']) ) return true;

        $xFile     = "index.html";
        $xFilePath = $this->config['realPath'] . $this->config['folderSep'] . $xFile;
        if (!file_exists($xFilePath)) {
            $xFile     = "index.htm";
            $xFilePath = $this->config['realPath'] . $this->config['folderSep'] . $xFile;
        }
        if (!file_exists($xFilePath)) return false;    
        
        $xURL = $this->config['rootURL'] . str_replace("\\","/",$this->config['folderSanitized']) . "/" . $xFile;
        
        // ------------------------------------------------------------------------
        // Method 2:  server loads the URL and indexes it's content into the FTS search database
        // ISSUE:  we cannot index contents for FTS search database when it is loaded by the client.
        // ELE ISSUE:  clarify what postFields, and cookies are requireda and why they are commented out here?
        // ------------------------------------------------------------------------
        if (0) {
            $curlMethod       = 'GET';
            $postFields       = "";
            $cookies          = "";
            //$postFields     = ['param1' => 'value1', 'param2' => 'value2'];
            //$cookies        = ['cookie1' => 'value1', 'cookie2' => 'value2'];
            $this->QWIFRAME   = $this->submitCurlRequest($xURL, $curlMethod, $postFields, $cookies);
        }

        // ------------------------------------------------------------------------
        // Method #1:  client is programmed using JS to load the URL in an IFrame
        // ------------------------------------------------------------------------
        $aOptions                       = array();
        $aOptions['url']                = $xURL;
        $aOptions['initial_width']      = '800px';
        $aOptions['initial_height']     = '600px';

        echo $this->generate_webpage_iframe($aOptions);
    }

    // -----------------------------------------------------------------------------
    // Determine if a file name is should be displayed by qw or if should be excluded
    // return true or false
    // -----------------------------------------------------------------------------
    public function isFileImage($aOptions) { 
        
        return false;
    }
    // -----------------------------------------------------------------------------
    // Determine if a file name is should be displayed by qw or if should be excluded
    // return true or false
    // -----------------------------------------------------------------------------
    public function isFileMatched($aOptions) {      

        if (is_null($aOptions['fileName']) || (strcmp($aOptions['fileName'],"")==0) ) return False;
        
        $xFileName       = strtolower($aOptions['fileName']);
        $xFileExtension  = strtolower(pathinfo($aOptions['fileName'], PATHINFO_EXTENSION));
       
        // echo "<h4>xFileName: $xFileName</li>";
            
        // Allow by file extensions type
        if (isset($this->config['allowedExtensions'])) {
            if ( in_array($xFileExtension, $this->config['allowedExtensions']) ) {
                return false;
            }
        }
        
        // Allowed file extension types
        if (isset($this->config['allowedFileNames'])) {
            if ( in_array($xFileExtension, $this->config['allowedFileNames']) ) {
                return false;
            }
        }
        
        // Exclude by file extension type
        if (isset($this->config['invalidExtensions'])) {
            $xArr = explode(',',$this->config['invalidFileNames']);
            if ( in_array($xFileExtension, $xArr) ) {
                return false;
            }
        }

        // Exclude when normally hidden by leading first character
        if (isset($this->config['invalidHidden'])) {
            $xFirstChar = $xFileName[0];
            $xArr       = explode(',',$this->config['invalidHidden']);
            if (is_array($xArr)) {
                foreach ($xArr as $xMatch) {
                    if (strcmp($xFirstChar, trim($xMatch))==0) {
                        return false;
                    }
                }
            }
        }

        // Exclude by file name match
        // https://stackoverflow.com/questions/19445798/check-if-string-contains-a-value-in-array
        // TODO:  update to use regular expressions
        if (isset($this->config['invalidFileNames'] )) {
            $xFileName   = strtolower($aOptions['fileName']);
            $xArr        = explode(',',$this->config['invalidFileNames']);
            if (is_array($xArr)) {
                foreach ($xArr as $xMatch) {
                    if (strpos($xFileName, strtolower(trim($xMatch))) !== FALSE) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    // -----------------------------------------------------------------------------
    // JavaScript manages form, submits uploaded files, and displays results w/o submitting form
    // upload.js verifies allowed file types and size limitations and submits files to:  upload.php
    // upload.php again verifies allowed file types and size limitations and places them in the proper folder (cwd) 
    // Note:  upload.js must be after this form which creates <input> tags it references
    // -----------------------------------------------------------------------------
    public function displayUploadForm($aOptions){
        
        if (! isset($this->config['realPath']) && ! isset($this->config['folder'])) {
            echo "ERROR: displayUploadForm(), realPath and/or folder not defined, Get Help";
            exit;
        }

        $xOptions = "realPath=" . $this->config['realPath'] . "&folderPath=" . $this->config['folderPath'];
        $xKey     = $this->objENC->encryptData($xOptions);
        $xURL     = $this->config['QWSERVERURL'] . "?" . $this->objENC->encryptData("folder=" . $this->config['folder']);
        $xTitle   = $this->config['folderSanitized'];
        $xQW      = $this->config['QWNAME'];
        
        echo <<<EOHERE

        <link rel="stylesheet" type="text/css" href="Upload.css">

        <div class=title>$xQW</div>
        <br>
        Files will reside in this folder:
        &nbsp;&nbsp;<div class=info style="display:inline-block;">$xTitle</div>
        <br>
        <br>
        <ul class=md>
        <li>Select one of more files to upoad</li>
        <li>A total of 6MB may be uploaded each attempt</li> 
        <li>Specific file extensions may not be allowed e.g.:  .exe</li>
        <li>Uploaded files will silenty replace files with the same name</li>
        </ul>
        <br>  
        <form>
        <input type="file" id="uploadFiles" name="uploadFiles" accept=".jpg, .jpeg, .png, .gif, .pdf, .txt, .zip, .7z" multiple="" style="opacity: 0;">
        <input type="text" id="uploadKey" name="uploadKey" style="display:none" value="$xKey">
        <div>
            <label for="uploadFiles">Choose file(s) to upload</label>
            &nbsp;&nbsp;<button style="display:inline-block;" onclick="GoToURL('$xURL','_self'); return false;">Return To Qwiki</button>
            <div id="buttonSubmit" style="display:none" onclick="submitUpload(); return false;">
                &nbsp;&nbsp;<button>Submit</button>
            </div>
        </div>
        <br>
        <div class="preview"></div>
        <div id="status">
            <br>
        </div>
        </form>
        <script src="Upload.js"></script>
        EOHERE;

   }

    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    public function generate_webpage_iframe($aOptions) {

        $src = htmlspecialchars($aOptions['url']);

//        $this->dump($src);

        // example iframe definition from ASP qwiki
        // Response.Write "<a href=""#"" onclick=""document.getElementById('iFrame').contentDocument.location.reload(true); return false;"" 
        // title=""Click to load a fresh copy of the index web page.  
        // This is required if the file was recently updated and the current iFrame is already in your browsers cache..."">Refresh</a>" & vbCrLf
	

        // Generate HTML code for iframe
        $iframe ="<br>
        &nbsp;&nbsp;<button id='toggleVisibilityBtn' onclick='toggleVisibility()'>Hide Window</button>
        &nbsp;&nbsp;<button id='toggleWidthBtn' onclick='toggleWidth()'>Expand</button>
        &nbsp;&nbsp;<button onclick='loadOriginal()'>View Page</button>

        <div id='iframeDiv' style='width: {$aOptions['initial_width']}; height: {$aOptions['initial_height']}; border: 1px solid #ccc; position: relative;'>
            <iframe id='myIframe' src='{$src}' style='width: 100%; height: 100%; border: none; overflow: auto;'></iframe>
        </div>
        <script>                       
            function toggleVisibility() {
                var iframe = document.getElementById('iframeDiv');
                if (document.getElementById('iframeDiv').style.display === 'none') {
                    document.getElementById('iframeDiv').style.display       = 'block';
                    document.getElementById('toggleVisibilityBtn').innerHTML = 'Hide Window';
                } else {
                    document.getElementById('iframeDiv').style.display       = 'none';
                    document.getElementById('toggleVisibilityBtn').innerHTML = 'Show Window';
                }
            }
            function toggleWidth() {
                if (document.getElementById('toggleWidthBtn').innerHTML == 'Expand') {
                    document.getElementById('iframeDiv').style.width         = '100%';
                    document.getElementById('iframeDiv').style.display       = 'block';
                    document.getElementById('toggleVisibilityBtn').innerHTML = 'Hide Window';
                    document.getElementById('toggleWidthBtn').innerHTML      = 'Collapse';
                } else {
                    document.getElementById('iframeDiv').style.display       = 'block';
                    document.getElementById('toggleVisibilityBtn').innerHTML = 'Hide Window';
                    document.getElementById('iframeDiv').style.width         = '{$aOptions['initial_width']}';
                    document.getElementById('toggleWidthBtn').innerHTML      = 'Expand';
                }  
            }
            function loadOriginal() {
                window.open('{$src}','_blank');
            }           
        </script>";

        return $iframe;

    }

    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    public function generate_webpage_iframe1($aOptions) {
        
        // Check if URL is remote or local
        $is_remote = filter_var($aOptions['url'], FILTER_VALIDATE_URL) !== false;
    
        // Generate HTML for header with link and buttons
        $header_html = '<br><div id="iframe-header">';
        if ($is_remote) {
            $header_html .= '&nbsp;&nbsp;<a href="' . htmlspecialchars($aOptions['url']) . '" target="_blank">View Original</a>';
        }
        $header_html .= '&nbsp;<button id="expand-btn">Expand</button>';
        $header_html .= '&nbsp;<button id="collapse-btn">Collapse</button>';
        $header_html .= '</div>';
    
        // Generate iframe HTML
        $iframe_html = '<iframe id="webpage-iframe" src="' . htmlspecialchars($aOptions['url']) . '" width="' . $aOptions['initial_width'] . '" height="' . $aOptions['initial_height'] . '"></iframe>';
    
        // Combine header and iframe HTML
        $html = $header_html . $iframe_html;
    
        // Generate JavaScript for iframe functionality
        $javascript = '
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var iframe = document.getElementById("webpage-iframe");
                var expandBtn = document.getElementById("expand-btn");
                var collapseBtn = document.getElementById("collapse-btn");
    
                expandBtn.addEventListener("click", function() {
                    iframe.style.width = "100%";
                });
    
                collapseBtn.addEventListener("click", function() {
                    iframe.style.width = "' . $aOptions['initial_width'] . '";
                });
            });
        </script>';
    
        // Combine HTML and JavaScript
        $html .= $javascript;
    
        return $html;
    }
     
    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
   public function createFolder($folderName) {
        $folderPath = $this->config['rootPath'] . '/' . $folderName;

        if (!file_exists($folderPath) && mkdir($folderPath, 0777, true)) {
            echo "Folder '$folderName' created successfully.";
        } else {
            echo "Failed to create folder '$folderName'.";
        }
    }

    // -----------------------------------------------------------------------------
   public function deleteFolder($folderName) {
        $folderPath = $this->config['rootPath'] . '/' . $folderName;

        if (is_dir($folderPath) && rmdir($folderPath)) {
            echo "Folder '$folderName' deleted successfully.";
        } else {
            echo "Failed to delete folder '$folderName'.";
        }
    }

    // -----------------------------------------------------------------------------
   public function uploadFile($file, $folderName = '') {
        $uploadPath = $this->config['rootPath'] . '/' . $folderName;

        if (!file_exists($uploadPath)) {
            $this->createFolder($folderName);
        }

        $targetPath = $uploadPath . '/' . basename($file['name']);

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo "File '{$file['name']}' uploaded successfully.";
        } else {
            echo "Failed to upload file '{$file['name']}'.";
        }
    }

    // -----------------------------------------------------------------------------
    // Assumes that fetchURL() is included.  See QW.js
    // -----------------------------------------------------------------------------
    private function displayContentJS($xURL, $xID="qwcontent", $xOptions=null) {
        echo <<<EOHERE
        <script>
        fetchURL("$xURL", "loadContentIntoID", "$xID");
        </script>
        EOHERE;
        return true;
    }   
    
    // -----------------------------------------------------------------------------
    // Get URL using cURL
    // TODO:  Need to support SSL certificate verification which is disabled below
    // -----------------------------------------------------------------------------
    public function submitCurlRequest($url, $method = 'GET', $postFields = [], $cookies = []) {
        
        $ch = curl_init();

        // $url = curl_escape($ch, $url);                        // encodes server URL portion too, not just GET arguments
        // $url = urlencode($url);                               // encodes server URL portion too, not just GET arguments    
        $url = str_replace(' ','%20',$url);

        // Set the URL
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set the request method (GET or POST)
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        // Include cookies in the request
        if (!empty($cookies)) {
            $cookieString = http_build_query($cookies, '', '; ');
            curl_setopt($ch, CURLOPT_COOKIE, $cookieString);
        }

        // Include form data in the request if it's a POST request
        if (!empty($postFields) && $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        }

        // Set other cURL options as needed (e.g., timeouts, SSL verification, etc.)
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Disable SSL certificate verification (for now)
        // TODO: really should figure out how to correctly verify using certificate authority
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Set option to return the response instead of outputting it directly
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            // Handle error as needed
            echo 'ERROR: cURL: ' . curl_error($ch);
        }

        // Close cURL session
        // curl_close($ch);

        // Return the response
        return $response;
    }
    
    public function strToHex($string){
        $hex = '';
        for ($i=0; $i<strlen($string); $i++){
            $ord = ord($string[$i]);
            $hexCode = dechex($ord);
            $hex .= substr('0'.$hexCode, -2);
        }
        return strToUpper($hex);
    }
    
    public function hexToStr($hex){
        $string='';
        for ($i=0; $i < strlen($hex)-1; $i+=2){
            $string .= chr(hexdec($hex[$i].$hex[$i+1]));
        }
        return $string;
    }

    // -----------------------------------------------------------------------------
    // Get URL using:   file_get_contents()
    // This method supports HTTPS SSL certificate verification
    // -----------------------------------------------------------------------------
    function submitGetFileContentsRequest($url, $method = 'GET', $postFields = [], $cookies = []) {
        // Set up options for the stream context
        $timeout = 10;        
        
        // SSL options to load specific root authorization
        //'ssl' => [
        //    'cafile' => '/path/to/bundle/cacert.pem',
        //    'verify_peer'=> true,
        //    'verify_peer_name'=> true,
        //],
        
        $options = [
            'http' => [
                'method' => strtoupper($method),
                'header' => [
                    'Content-type: application/x-www-form-urlencoded',
                ],
                'content' => http_build_query($postFields),
                'timeout' => $timeout,
            ],
            'ssl' => [
                'verify_peer'=> false,
                'verify_peer_name'=> false,
            ],
        ];

        // Include cookies in the request
        if (!empty($cookies)) {
            $cookieString = http_build_query($cookies, '', '; ');
            $options['http']['header'][] = 'Cookie: ' . $cookieString;
        }

        // Create stream context with defined options
        $context = stream_context_create($options);

        // Set custom timeout using ini_set
        ini_set('default_socket_timeout', $timeout);

        // Make  request and get response
        $response = file_get_contents($url, false, $context);


        // Check for errors and handle them
        // Should we return $error or $response?
        if ($response === false) {
            $error = error_get_last();
            echo 'ERROR: submitGetFileContentsRequest(): ' . $error['message'];
        }

        return $response;
    }

    // -----------------------------------------------------------------------------
    // Update Full Text Search db with info about this folder
    // Do not update more that once per browser session unless the folder has been modified in the last 24 hours
    // -----------------------------------------------------------------------------
    public function searchSubmitUpdates($aOptions) {
        
        if (!$this->config['FEATURE']['SEARCH']) return true;
        
        // ------------------------------------------------------
        // Only update FTS once per _SESSION, unless cwd.mtime is newer
        // OR if any folder, file, or content was updated.
        // See:  scanFolderFiles() which initializes:  folders and files lists
        // Note: folder modification dates are not updated when file, sub-folder, or qw content is modified.  
        //       You cannot rely on folder mdate
        // ------------------------------------------------------
        $xKey = $this->config['QWI'] . ":/" . str_replace("\\","/",ltrim($this->config['folder']));
        
        // Was current folder updated?
        $aFTStoday = Array();
        if (isset($_SESSION['QWFTSUPDATES']))  $aFTStoday = $_SESSION['QWFTSUPDATES'];
        if ( array_key_exists($xKey, $aFTStoday)) {
            if ($this->config['FOLDER']['filemtime'] > $_SESSION['QWFTSUPDATES'][$xKey]) {
                $this->config['FTS']['FORCEUPDATE'] = true;
            }
        } else {
            // key does not exist so force FTS indexing...
            $this->config['FTS']['FORCEUPDATE'] = true;
        }
$this->config['FTS']['FORCEUPDATE'] = true;

        // Were any FILES or child FOLDERs updated?
        // See:  scanFolderFiles() which initializes:  folders and files lists, and check this case

        // Was QW content updated?
        $xFileName = $this->config['realPath'] . $this->config['folderSep'] . $this->config['contentFileName'];           
        if (file_exists($xFileName) && isset($_SESSION['QWFTSUPDATES'][$xKey])) {            
            if (filemtime($xFileName) > $_SESSION['QWFTSUPDATES'][$xKey]) {
                $this->config['FTS']['FORCEUPDATE'] = true;
            }
        }

        // TODO:  Was Index.htm[l] created or updated?

        // No updates to index in FTS
        // FORCEIPDATE is set in other class functions.  See:  INIT()
        if (!$this->config['FTS']['FORCEUPDATE']){
            return true;
        }
            
        // current folder mtime cannot be used 
        // When sub-folders or files are updated the parent folder mtime is not always updated
        $_SESSION['QWFTSUPDATES'][$xKey] = time();         // GMT mtime
            
        // ------------------------------------------------------
        // Construct BODY of text to index
        // ------------------------------------------------------
        
        $this->FTS_BODY = "";
        
        // Add folder names
        if (!is_null($this->config['folders'])) {
            foreach($this->config['folders'] as $xKey => $xValue) {
                $this->FTS_BODY .= $xKey . " ";
            }
        }
    
        // Add file names
        if (!is_null($this->config['files'])) {
            foreach($this->config['files'] as $xKey => $xValue) {
                $aOptions['fileName'] = $xKey;
                if ($this->isFileMatched($aOptions)) {               
                    $this->FTS_BODY .= $xKey . " ";
                }
            }
        }

        $this->config['FTS']['MODE']         = "INSERT";
        $this->config['FTS']['APP']          = "QWiki";
        $this->config['FTS']['SERVER']       = $_SERVER['SERVER_NAME'];                                      // Q: should this just be the standard SERVER_NAME PHP variable?
        $this->config['FTS']['INSTANCE']     = $this->config['QWI'];
        $this->config['FTS']['DATEMODIFIED'] = $this->config['FOLDER']['dateLastModification'];              // Use MTIME to easy date comparison
        
        // URL to load current page.  
        // ISSUE:  assumes encryption keys never change...  
        // - When QW can't decode encrypted URLs it defaults to the QW home page currently stored in the pickqwiki() session
        // - This is not correct as the search URL could be a different QW instance on the same server
        // $this->config['FTS']['URL']          = $this->config['QWSERVERURL'] . "?" . $this->config['QS_Original'];
        // 
        // URL must store un-encrypted URL to return to the same QWS/QWI/FOLDER
        $this->config['FTS']['URL']          = $this->config['QWSERVERURL'] . "?" . "QWS=" . urlencode($this->config['QWS']) . "&QWI=" . urlencode($this->config['QWI']) . "&folder=" . urlencode($this->config['folder']);
        
        // Prefix folder with emulated URL pathing syntax.  This is what's printed in FTS search results for the URL
        // 'folder' always has a leading slash.  Only add 1 slash to preserve pseudo url lookalike.
        // Normalize to "/" character.  Windows uses "\" which does not look right
        $this->config['FTS']['TITLE']        = $this->config['QWI'] . ":/" . str_replace("\\","/",ltrim($this->config['folder']));
        
        // construct cwd folder pkey
        // FTS PKEY for any "folder" must contain both leading and trailing folderSep characters to uniquely identify & terminate the path.
        // -  Multiple sub-folders may exist in the cwd and contain the same leading text e.g.:   /mydir/test and /mydir/test2
        // -  Including a trailing slash is a requirement to terminate the folder path  
        // See:  clsFullTextSearch.php->InsertDB() DKEYS processing.                                                     // folder relative to root of this Qwiki instance
        $this->config['FTS']['PKEY']          = $this->config['folderSanitized'];
        $this->config['FTS']['PKEY']         .= $this->config['folderSep'];                                              // FTS requires a trailing slash for folders

        // construct sub-folder(s) pkey.
        // Assumptions:   "folders" does not contain a trailing slash
        if (is_array($this->config['folders'])) {
            $this->config['FTS']['DKEYS']                     = &$this->config['folders'];
            foreach ($this->config['FTS']['DKEYS'] as $xKey   => $xValue ){
                $this->config['FTS']['DKEYS'][$xKey]['pkey']  = $this->config['folderSanitized'];                  
                if ( (strcmp($this->config['folderSanitized'],"\\")!=0) ) {                                                // At root of a QW
                    $this->config['FTS']['DKEYS'][$xKey]['pkey'] .= $this->config['folderSep'];
                }
                $this->config['FTS']['DKEYS'][$xKey]['pkey'] .= $xKey;
                $this->config['FTS']['DKEYS'][$xKey]['pkey'] .= $this->config['folderSep'];                            // FTS requires a trailing slash for folders
            }
        }

        // If JS method was used within the client browser to load QWCONTENT then QW does not know what it is; FTS cannot index it
        // If CURL or FILEGETCONTENTS methods loaded QWCONTENT at run-time, strip out html, images, etc, that should not be indexed and append to BODY
        if (!is_null($this->QWCONTENT)) {
            $this->FTS_BODY .= gfHtml2Text($this->QWCONTENT);
        }

        // If a web page was loaded by QW using curl or another mean load the returned web data into the QW iFrame       
        if (!is_null($this->QWIFRAME)) {
            $this->FTS_BODY .= gfHtml2Text($this->QWIFRAME);
        }

        // config['FTS']['BODY'] is stripped of html/objects/js,etc and only contains information that should be entered into the FTS database
        $this->config['FTS']['BODY']         = $this->FTS_BODY;

        $bRet = $this->objFTS->InsertDB($this->config['FTS']);
        if ($bRet) $this->config['FTS']['DBUPDATED'] = true;
    }


}

// ------------------------------------------------------------
// Convert an HTML document to TEXT   (used by Full Text Search when indexing web page)
// Algorithm is not 100% correct but does well enough.
//
// http://www.codeproject.com/Articles/639/Removing-HTML-from-the-text-in-ASP
// https://gist.github.com/gwobcke/1027133
// http://www.barattalo.it/asp/asp-strip-tags-function-equivalent-to-php/
// http://forums.devshed.com/asp-programming-51/regex-remove-ascii-characters-string-395202.html
// https://snipplr.com/view/8404/how-to-strip-html-tags-scripts-and-styles-from-a-web-page
//
// ELE - 9/2016     Wrote original ASP version
// ELE - 4/24       Ported to PHP and added some methods
// ------------------------------------------------------------
function gfHtml2Text($xStr) {
	$i = 0;
    
	if ( is_null($xStr) || (strcmp($xStr,"")==0) ) {
		return $xStr;
  }

    // remove everything between open/close tag including everything between the tags
    // Cannot use this....  It deletes evrything between <html></html> or body, etc...
    // $xPattern    = '/(<.*>.+?)+(<\/.*>)/i';
    // $xStr        = preg_replace($xPattern, '', $xStr);
    

    // Have not verified what this actually removes.  Will have to further validate PHP BODY content
    // ISSUE-1:  when processing an Email that was saved as index.html, it deleted the entire contents of the html-formatted email.
    // Commented out until additional testing can commence.
    $xStr2 = preg_replace(
        array(
          // Remove invisible content
            '@<head[^>]*?>.*?</head>@siu',
            '@<style[^>]*?>.*?</style>@siu',
            '@<script[^>]*?.*?</script>@siu',
            '@<object[^>]*?.*?</object>@siu',
            '@<embed[^>]*?.*?</embed>@siu',
            '@<applet[^>]*?.*?</applet>@siu',
            '@<noframes[^>]*?.*?</noframes>@siu',
            '@<noscript[^>]*?.*?</noscript>@siu',
            '@<noembed[^>]*?.*?</noembed>@siu',
          // Add line breaks before and after blocks
            '@</?((address)|(blockquote)|(center)|(del))@iu',
            '@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu',
            '@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu',
            '@</?((table)|(th)|(td)|(caption))@iu',
            '@</?((form)|(button)|(fieldset)|(legend)|(input))@iu',
            '@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu',
            '@</?((frameset)|(frame)|(iframe))@iu',
        ),
        array(
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
            "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0",
            "\n\$0", "\n\$0",
        ),
        $xStr );


    // Remove images including embedded image data (see .QWContent.php when users paste images into the editor)
    // Must be before strip_tags()
    $xPattern    = '/(<img>.+?)+(<\/img>)/i';
    $xStr        = preg_replace($xPattern, '', $xStr);
    
    // https://www.php.net/manual/en/function.strip-tags.php
    // This leaves all text between the tags
    $xStr        = strip_tags($xStr);
    
    // replace text backslash characters (not rendered)
    $xPattern    = '[\\\\n|\\\\t|\\\\r|\\\\v|\\\\f|\\\\b]';
    if (!is_null($xStr)) $xStr        = preg_replace($xPattern," ",$xStr);

    // Replace HTML special characters.
    $xPattern    = "'&[a-zA-Z]{4};'";
    if (!is_null($xStr)) $xStr        = preg_replace($xPattern," ",$xStr);
    
    // strip some non-ascii characters
    $xPattern    = "/^\x20-\x7E/";
    if (!is_null($xStr)) $xStr        = preg_replace($xPattern," ",$xStr);
    
    // remove other non-ascii characters (this seems to work better that above)
    // https://stackoverflow.com/questions/8781911/remove-non-ascii-characters-from-string
    // This DOES NOT WORK PROPERLY.  It concatenates subsqquent lines together without spaces which messes up meta-character search expectations
    //    $xStr = preg_replace('/[[:^print:]]/', '', $xStr);
    
    // Don't know what this does :-(  It was in the original ASP version so just keep it.
    // Possibly remove 1 or more newlines?
    $xPattern    = "[<(.|\n)+?>]";;
    if (!is_null($xStr)) $xStr        = preg_replace($xPattern," ",$xStr);
    
    // Untested - Remove HTML Tags (from php preg_replace manual in the comments section)
    // $string      = preg_replace ('/<[^>]*>/', ' ', $string); 
    $xPattern    = "'/<[^>]*>/'";;
    if (!is_null($xStr)) $xStr        = preg_replace($xPattern," ",$xStr);
    
    // LAST replace 1-or-more spaces with 1 space
    $xPattern    = "[\s{2,}]";
    if (!is_null($xStr)) $xStr        = preg_replace($xPattern," ",$xStr);
	
	return $xStr;
}

?>


