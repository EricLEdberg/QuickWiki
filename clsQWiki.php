<?php

class QuickWiki {
    
    public $config;
    public $folder;
    public $folderPath;
    public $MyDebug;
    public $QWCONTENT;
    public $QWIFRAME;

    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    public function __construct(&$aConfig) {

        $this->MyDebug           = false;
        $this->config            = &$aConfig;                 // QW.php and clsQWiki.php "config" are one and the same.  Changes here apply to QW.php too.
        $this->config['folders'] = null;
        $this->config['files']   = null;
        $this->HtmlheaderEnabled = False;
        $this->QWCONTENT         = null;                       // when .QWContent.php is evaluated run-time, it's output is saved to:  $this->QWCONTENT
        $this->QWIFRAME          = null;                       // when index.htm* is evaluated at run-time. it's output is saved to:   $this->QWIFRAME
        
        $this->INIT(null);
    }

    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    public function __destruct() {
        global $objENC;   

        //if ($this->MyDebug) {
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
        
        if ($this->HtmlheaderEnabled) {
            echo "</body></html>";
        }
    }
    
    function dump($var) {
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
        
		if (!$this->config['realPath']) echo "<li>ERROR:  real path does not exist:  $this->config['realPath']</li>";

        if (!is_dir($this->config['folderPath'])){
            $this->dump($this->config);
			echo "<font color=red>ERROR: the Qwiki folder does not exist. Get Help.</font>";
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
		
		// remove leading slash is it exists
        // search expects a leading slash?  Will this break anything else?
		// $xFolder = preg_replace('{^/*}','',$xFolder);

		// If folder contains a parent folder reference immediately throw error as this should never-ever happen
		if (strpos($xFolder, '..') !== false) {
			echo "ERROR: folder path contains illegal characters, Get Help";
			die;
		}
		
		return $xFolder;
	}
    
    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
   public function displayQwikiMain($aOptions) {
        
        $this->INIT(null);
        $this->scanFolderFiles(null);
        
        // TODO:  possibly move $aFeatures definition into .QWconfig.json?
        $aFeatures = array('displayPageOptions','displayFolders','displayFiles','displayContent','displayUrlInIframe','searchSubmitUpdates');  
        
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
        
        foreach ($items as $item) {
            $itemPath  = $this->config['FOLDER']['realPath'] . $this->config['folderSep'] . $item;
            $xFileTime = null;

            if (is_dir($itemPath) && $item != '.' && $item != '..') {
                $this->config['folders'][$item] = [
                    'name'        => $item,
                    'realpath'    => realpath($itemPath),
                    'type'        => filetype($itemPath),
                    'filemtime'   => filemtime($itemPath),
                    'modified'    => date('Y-m-d H:i:s', filemtime($itemPath)),
                    'size'        => filesize($itemPath),
                    'owner'       => fileowner(($itemPath)),                                // always returns zero on Windows because it does not support numeric user ids
                    'permissions' => substr(sprintf('%o', fileperms($itemPath)), -4),
                    'is_writable' => is_writable($itemPath)
                ];

                $xFileTime = filemtime($itemPath);

            } elseif (is_file($itemPath) && is_readable($itemPath) ) {
                $this->config['files'][$item] = [
                    'name'        => $item,
                    'realpath'    => realpath($itemPath),
                    'type'        => filetype($itemPath),
                    'filemtime'   => filemtime($itemPath),
                    'modified'    => date('Y-m-d H:i:s', filemtime($itemPath)),
                    'owner'       => fileowner(($itemPath)),                                // always returns zero on Windows because it does not support numeric user ids
                    'permissions' => substr(sprintf('%o', fileperms($itemPath)), -4),
                    'size'        => filesize($itemPath),
                    'is_writable' => is_writable($itemPath)
                ];

                $xFileTime = filemtime($itemPath);
            }


            // TODO:  xKey is constructed in multiple places.  Should be defined in 1 common location.
            // See:   $this->searchSubmitUpdates()
            if (!is_null($xFileTime) && is_array($_SESSION['QWFTSUPDATES'])) {
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
        // QW HOME
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
            echo '<td align=center style="width: 1%; white-space: nowrap;">' .
                '<a href="'  . $this->config['QWURL'] . '/Search.php' . '">' .
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
	// ----------------------------------------------------------------------
	public function nav_profile($aOptions) {
        $QWURL = $this->config['QWURL'];
        $qwHP  = $this->config['QWSERVERURL'];
    }

    // ----------------------------------------------------------------------
	// ----------------------------------------------------------------------
	public function nav_menu($aOptions) {
        $QWURL = $this->config['QWURL'];
        $qwHP  = $this->config['QWSERVERURL'];
        $qwD   = $this->config['QWSERVERURL'] . "?ChooseQwiki=1";
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
          <a href="#" onclick="showDebugWindow('qwdebug'); return false;">Qwiki Debugging Info</a>
          <a href="#" onclick="openURL('https://github.com/ericledberg','_blank')">Qwiki GitHub</a>
          <a href="#" onclick="changeFontSize('increase')">Increase Font Size</a>
          <a href="#" onclick="changeFontSize('decrease')">Decrease Font Size</a>
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

        // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    private function displayFiles($aOptions) {
        global $objENC;

        if (!is_array($this->config['files'])) return true;
        
        $xHead = <<<EOHERE
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
                if (!is_null($xHead)){
                    echo $xHead;
                    $xHead = null;
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
        echo "</table>" . "\n";
    }
    
    // --------------------------------------------
    // display $xCurCnt folders in each column
    // --------------------------------------------
    private function displayFolders() { 
        
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
        
        // ISSUE:  we cannot index contents for FTS search database when it is loaded by the client.
        // KLUDGE: load page similar to QWCONTENT in test mode until solution is found.
        if (1) {
            $curlMethod       = 'GET';
            //$postFields     = ['param1' => 'value1', 'param2' => 'value2'];
            //$cookies        = ['cookie1' => 'value1', 'cookie2' => 'value2'];
            $this->QWIFRAME   = $this->submitCurlRequest($xURL, $curlMethod, $postFields, $cookies);
        }

        // default is to have client load this page
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
        $is_remote = filter_var($url, FILTER_VALIDATE_URL) !== false;
    
        // Generate HTML for header with link and buttons
        $header_html = '<br><div id="iframe-header">';
        if ($is_remote) {
            $header_html .= '&nbsp;&nbsp;<a href="' . htmlspecialchars($url) . '" target="_blank">View Original</a>';
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
        //echo "<li>url: $url</li>";
        //echo "<li>method: $method</li>";
        
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
        curl_close($ch);

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

        // Were any FILES or child FOLDERs updated?
        // See:  scanFolderFiles() which initializes:  folders and files lists, and check this case

        // Was QW content updated?
        $xFileName = $this->config['realPath'] . $this->config['folderSep'] . $this->config['contentFileName'];           
        if (file_exists($xFileName)) {            
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
        $this->config['FTS']['PKEY']         .= $this->config['folderSep'];                                                      // FTS requires a trailing slash for folders

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
            require_once('./clsHmtlToText.php');
            $this->FTS_BODY .= gfHtml2Text($this->QWCONTENT);
        }

        // If a web page exists and it was loaded at run time strip and append to BODY similar to QWCONTENT
        if (!is_null($this->QWIFRAME)) {
            require_once('./clsHmtlToText.php');
            $this->FTS_BODY .= gfHtml2Text($this->QWIFRAME);
        }

        // config['FTS']['BODY'] is stripped of html/objects/js,etc and only contains information that should be entered into the FTS database
        $this->config['FTS']['BODY']         = $this->FTS_BODY;

        $bRet = $this->objFTS->InsertDB($this->config['FTS']);
        if ($bRet) $this->config['FTS']['DBUPDATED'] = true;
    }


}

?>


