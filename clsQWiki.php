<?php

class QuickWiki {
    
    public $config;
    public $folder;
    public $folderPath;
    public $MyDebug;

    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    public function __construct($aConfig) {
        $this->MyDebug = true;
        
        $this->config            = $aConfig;
        $this->HtmlheaderEnabled = False;

        $this->INIT(null);
    }

    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    public function __destruct() {

        if ($this->MyDebug) {
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
            echo "</div>";
        }
        
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
    // INIT must be called on page load to validate and initialize config[] options
    // Almost always, config['folder'] must be provided indicating what folder under rootFolder to view
    // -----------------------------------------------------------------------------
    function INIT($aOptions) { 
        
        if (is_null($this->config)) return true;

        // Use folderSanitized if folder was not provided
        if ( is_null($this->config['folder']) || (strcmp($this->config['folder'],"")==0) ) {
            if (!is_null($this->config['folderSanitized']) && (!strcmp($this->config['folderSanitized'],"")==0) ) {
                $this->config['folder'] = $this->config['folderSanitized'];
            }
        }

        if (is_null($this->config['folder']) || (strcmp($this->config['folder'],"")==0) ) {
			$this->config['folderPath'] = $this->config['rootPath'];
		} else {
            $this->config['folderPath'] = $this->config['rootPath'] . $this->config['folder'];
        }
        
        $this->config['folderSanitized'] = $this->sanitizeFolderPath($this->config['folder']);
        
        $this->config['realPath'] = realpath($this->config['folderPath']); 
        if (!$this->config['realPath']) echo "<li>ERROR:  real path does not exist:  $this->config['realPath']</li>";

        if (!is_dir($this->config['folderPath'])){
            // TODO:  expand on error message
			echo "ERROR: the specified Qwiki folder does not exist anymore...";
			die;
		}

        $this->config['folderTitle']  = str_replace($this->config['folderSep']," -> ",ltrim($this->config['folder'], $this->config['folderSep']));
        $this->deriveFolderParent($this->config['folderSanitized']);
    }

    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    public function ReturnToQwikiButton($aOptions) {
        $xOptions = "folder=" . $this->config['folder'] . "&realPath=" . $this->config['realPath'] . "&folderSep=" . $this->config['folderSep'];
        $this->config['ReturnToQwikiURL'] = $xOptions;
        if ($this->config['ENCRYPTION']) {
             $xOptions = $this->objENC->encryptData($xOptions); 
        }
        $xURL = $this->config['qwServerURL'] . "?" . $xOptions;
        
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
		
		if (is_null($xFolder) || (strcmp($xFolder,"")==0)) return $xFolder;

		// removes tags and encode special characters
		$xFolder = filter_var($xFolder, FILTER_SANITIZE_STRING);
		
		// replace more than 1 consequitive slash with a single slash
		$xFolder = preg_replace('#/{2,}#', DIRECTORY_SEPARATOR, $xFolder);
		$xFolder = preg_replace('#/{2,}#', '/', $xFolder);
		
		//  remove leading slash is it exists
		$xFolder = preg_replace('{^/*}','',$xFolder);

		// If folder contain a parent folder reference immediately throw error as this should never-ever happen
		if (strpos($xFolder, '..') !== false) {
			echo "ERROR: folder path contains illegal characters, Get Help";
			die;
		}
		
		return $xFolder;
	}
    
    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
   public function listContents($aOptions) {
        $folders = [];
        $files   = [];
        
        $this->INIT(null);

        // DISPLAY INFO....
        $this->getFoldersFiles(null);
        $this->displayPageOptions();
        $this->listFoldersInColumns();
        $this->displayFiles(null);
        $this->displayContent();
        $this->displayUrlInIframe(null);
        
        return TRUE;
    }
    
    
    // -----------------------------------------------------------------------------
    // TODO:  check for different allowed file names and case issues
    // -----------------------------------------------------------------------------
    public function displayUrlInIframe () {

        // Do not display on home page of Qwiki
        if ( (strcmp($this->config['folder'],"")==0) || is_null($this->config['folder']) ) return true;

        //if ($this->MyDebug) {
        //    $xURL = "https://example.com";
        //} else {
            $xFile = $this->config['realPath'] . $this->config['folderSep'] . "index.html";
            if (!file_exists($xFile)) return false;
            $xURL = $this->config['rootURL'] . $this->config['folderSep'] . "index.html";
        //}

        $aOptions                       = array();
        $aOptions['url']                = $xURL;
        $aOptions['initial_width']      = '800px';
        $aOptions['initial_height']     = '600px';
        echo $this->generate_webpage_iframe($aOptions);
    }

    // -----------------------------------------------------------------------------
    // folders and files must be passed by reference e.g.:  getFoldersFiles($folder, &$folders, &$files)
    // return false on error
    // -----------------------------------------------------------------------------
    public function getFoldersFiles($aOptions) {
        
        if (!is_dir($this->config['folderPath'])){
			echo "ERROR: the specified folder does not exist anymore...";
			die;
		}

        $items   = scandir($this->config['folderPath']);
        $folders = array();
        $files   = array();

        foreach ($items as $item) {
            $itemPath = $this->config['folderPath'] . $this->config['folderSep'] . $item;

            if (is_dir($itemPath) && $item != '.' && $item != '..') {
                $folders[] = $item;
            } elseif (is_file($itemPath)) {
                $files[] = [
                    'name' => $item,
                    'modified' => date('Y-m-d H:i:s', filemtime($itemPath)),
                    'size' => filesize($itemPath)
                ];
            }
        }

        $this->config['folders'] = $folders;
        $this->config['files']   = $files;

        $this->folders = $folders;
        $this->files   = $files;

        return true;
    }
    
    // -----------------------------------------------------------------------------
    // derive a folders parent relative to the root of the Qwiki.
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
    // -----------------------------------------------------------------------------
    public function displayPageOptions() {  
        
        echo "<table class=vs width='100%'><tr>";

        // -------------------------------------------------
        echo '<td align=center style="width: 1%; white-space: nowrap;">' .
            '<a href="' . $this->config['qwServerURL'] . '" title="Qwiki Home">' . 
            '<img src="' . $this->config['qwURL'] . '/Images/Icon_FolderHome.png" border=0 height=30>' .
            '<br><span class=comment>Home</span></a>' .
            '</td>';
        
        // -------------------------------------------------
        echo '<td align=center style="width: 1%; white-space: nowrap;" >' .
            '<a href="#" onclick="location.reload(); return false;"  title="Reload Page">' . 
            '<img src="' . $this->config['qwURL'] . '/Images/Icon_FolderRefresh.png" border=0 height=30>' .
            '<br><span class=comment>Refresh</span></a>' .
            '</td>';     
        
        // -------------------------------------------------
        // Upload
        // -------------------------------------------------
        $xUploadOpt = "upload=1&folder=" . $this->config['folder'];
        if (!is_null($this->config['folderPath']) && strcmp($this->config['folderPath'],"")!=0 ) {
            $xUploadOpt = $xUploadOpt . "&folderPath=" . $this->config['folderPath'];
        }
        if ($this->config['ENCRYPTION']) $xUploadOpt = $this->objENC->encryptData($xUploadOpt);
        
        $xUploadURL = $this->config['qwServerURL'] . "?" . $xUploadOpt;

        // -------------------------------------------------
        echo '<td align=center style="width: 1%; white-space: nowrap;">' .
            '<a href="' . $xUploadURL . '" title="Upload (replace) File(s) In Current Working Directory">' .
            '<img src="' . $this->config['qwURL'] . '/Images/Icon_FolderUpload.png" border=0 height=30>' .
            '<br><span class=comment>Upload</span></a>' .
            '</td>';
        
        // -------------------------------------------------
        $xUpDirOpt = "";
        if (!is_null($this->config['folderParent']) && strcmp($this->config['folderParent'],"")!=0 ) {
            $xUpDirOpt = "folder=" . $this->config['folderParent'];
            if ($this->config['ENCRYPTION']) $xUpDirOpt = $this->objENC->encryptData($xUpDirOpt);
            $xUpDirOpt = "?" . $xUpDirOpt;
        }

        // -------------------------------------------------
        echo '<td align=center style="width: 1%; white-space: nowrap;">' .
            '<a href="'  . $this->config['qwServerURL'] . $xUpDirOpt . '" title="Up To Parent Directory">' .
            '<img src="' . $this->config['qwURL'] . '/Images/Icon_FolderUp2.png" border=0 height=30>' .
            '<br><span class=comment>UpDir</span></a>' .
            '</td>';
        
            
        // -------------------------------------------------
        If ($this->config['SearchEnabled'] ) {
            echo '<td align=center style="width: 1%; white-space: nowrap;">' .
                '<a href="'  . $this->config['qwURL'] . '/Search.php' . '">' .
                '<img src="' . $this->config['qwURL'] . '/Images/Icon_FolderSearch.jpg" border=0 height=30>' .
                '<br><span class=comment>Search</span></a>' .
                '</td>';
        }

		// -------------------------------------------------
if (0) {
        $xURL  = $this->config['qwURL'] . "/Editor.php?EditorFile=" . $this->config['folderPath'] . "\\" . "-Content.php";
		$xURL  = str_replace("\\","\\\\",$xURL);     
} else {
        $xOptions = "Editor=1&folder=" . $this->config['folder'] . "&EditorFile=" . $this->config['folder'] . $this->config['folderSep'] . "-Content.php";
        if ($this->config['ENCRYPTION']) $xOptions = $this->objENC->encryptData($xOptions);
        $xURL  = $this->config['qwServerURL'] . "?" . $xOptions;
}
        // -------------------------------------------------
        echo '<td align=center style="width: 1%; white-space: nowrap;">' .
            "<a href=\"#\" onClick=\"window.open('" . $xURL . "');\" title='Edit Qwiki Content'>" .
            '<img src="' . $this->config['qwURL'] . '/Images/Icon_EditText2.png" border=0 height=30 title="Edit Qwiki Content">' .
            '<br><span class=comment>Edit</span></a>' .
            '</td>';
        
        // -------------------------------------- ---------
        echo '<td align=center style="width: 1%;  ite-space: nowrap;">' .
            '<a href="#" onclick="javascript: history.back(); return false">' .
            '<img src="' . $this->config['qwURL'] . '/Images/Icon_GreenCircleLeftArrow.png" border=0 height=30 title="Back to previous page">' .
            '<br><span class=comment>Back</span></a>' .
            '</td>';
        

        // -------------------------------------------------
         echo '<td width="40%" align=center>' .
            '<b><font color=red style="font-size: 22;">'        . $this->config['QWNAME']      . '</font></b>' . 
            '<br><em><font color=green style="font-size: 18;">' . $this->config['folderTitle'] . '</font></em>' . 
            '</td>';   
        
        // -------------------------------------------------
        if ($this->objUser->config['User']['id']) {
            echo '<td align=center>' .
                '<b><font color=blue style="font-size: 14;">Hi, ' . ($this->objUser->config['User']['First']) . '</font></b>' . 
                '</td>';
        }
            
        // -------------------------------------------------
        $this->nav_menu(null);

        // -------------------------------------------------
        $xOpt = $this->objENC->encryptData("Profile=1");
        echo '<td align=right style="width: 1%; white-space: nowrap;">' . 
            '<a href="'  . $this->config['qwServerURL'] . '?' . $xOpt . '">' .
            '<img src="' . $this->config['qwURL'] . '/Images/Icon_Profile1.png" border=0 height=30 title="Profile">' .
            '</a></td>'; 

        echo "</tr></table>";
        
        return TRUE;
    }
    
    // ----------------------------------------------------------------------
	// ----------------------------------------------------------------------
	public function nav_profile($aOptions) {
        $qwURL = $this->config['qwURL'];
        $qwHP  = $this->config['qwServerURL'];
    }

    // ----------------------------------------------------------------------
	// ----------------------------------------------------------------------
	public function nav_menu($aOptions) {
        $qwURL = $this->config['qwURL'];
        $qwHP  = $this->config['qwServerURL'];
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
        <img src="$qwURL/Images/Icon_HamburgerMenu2.png"  border=0 height=30 title="Menu"   style="cursor: pointer;" onclick="showMenuPopup(event)"> 
        </td>
        
        <div id="menuPopup" class="menu-popup" onmouseleave="hideMenuPopup()">
          <a href="#" onclick="openURL('$qwHP','_self')">Qwiki Home Page</a>
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
    // client browser loads $xURL asynchronously and execute callback (see QW.js)
    // Loads results into html object identified by it's ID
    // NOTE:  JS is the only method that natively verifies https/SSL certificate authority (since the browser natively supports it)
    //        There is a slight load delay between when the html page loads and it's JS engine retrieves and displays qw content
    //        Requires:  QW.js
    // -----------------------------------------------------------------------------
    private function displayContent($xOptions=null) {
        
        $xMethod = "FILEGETCONTENTS";
        $xMethod = "CURL";
        $xMethod = "JS";
        
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
            $method     = 'POST';
            $postFields = ['param1' => 'value1', 'param2' => 'value2'];
            $cookies    = ['cookie1' => 'value1', 'cookie2' => 'value2'];
            $result     = $this->submitCurlRequest($xURL, $method, $postFields, $cookies);
            echo $result;
            return true;

        }

        // default: FILEGETCONTENTS
        $method     = 'POST';
        $postFields = ['param1' => 'value1', 'param2' => 'value2'];
        $cookies    = ['cookie1' => 'value1', 'cookie2' => 'value2'];
        return $result = $this->submitGetFileContentsRequest($xURL, $method, $postFields, $cookies);
        
    }

    private function listFoldersInColumns() { 
        $xCurCol = 0;
        $xMaxCol = 5;
        $xHeader = '<h2>Folders</h2><table class=qwfolders border=0><tr>';
        foreach ($this->config['folders'] as $folder) {         
             // Delete ROOT path.  Pathing must be relative to $this->config['rootPath']
             $relFolderName = preg_replace('/^' . preg_quote($this->config['rootPath'], '/') . '/', '', $this->config['folderPath'] . $this->config['folderSep'] . $folder);
             $relFolderName = str_replace('\\\\','\\',$relFolderName);
             $folderName    = basename($folder);
             
            if (!is_null($xHeader)) {
                echo $xHeader;
                $xHeader = null;
            }
            if ($xCurCol==$xMaxCol) {
                echo "</tr><tr>";
                $xCurCol = 0;
            }

            // ------------------------
            // ------------------------
            if ($this->config['ENCRYPTION']) {
                $xQS = 'folder=' . $relFolderName;
                $xQS = $this->objENC->encryptData($xQS);
            } else {
                $xQS = 'folder=' . $relFolderName;
            }
             
            echo '<td width=150 style="white-space: nowrap;">' . '<a href="' . $this->config['qwServerURL'] . '?' . $xQS . '">' . $folderName . '</a></td>';
            echo "<td width=30>&nbsp;</td>";
            $xCurCol = $xCurCol + 1;
         }
         echo "</tr>";
         echo '</table>';
         return TRUE;
     }

     // save for now
     private function listFoldersInColumnsDIV() { 
        echo '<div style="column-count: 5;">';
         foreach ($this->config['folders'] as $folder) {         
             // Delete ROOT path.  All pathing must be relative to $this->config['rootPath']
             $relFolderName = preg_replace('/^' . preg_quote($this->config['rootPath'], '/') . '/', '', $this->config['folderPath'] . "\\" . $folder);
             $folderName    = basename($folder);
             echo '<div>' . '<a href="' . $this->qwconfig['qwServerURL'] . '?folder=' . $relFolderName . '">' . $folderName . '</a></div>';
         }
         echo '</div>';
         return TRUE;
     }
  
    // -----------------------------------------------------------------------------
    // -----------------------------------------------------------------------------
    private function displayFiles($aOptions) {
        
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

        // -----------------------------
        // TODO:  move these definitions to a $this->config()?;
        // Display only allowed "Files"
        // always lower case....
        // -----------------------------
        $aOptions = array();
        // $aOptions['fileName'] = "xxx";
        // always lower case....
        // $aOptions['allowedExtensions'] = [];       
        // $aOptions['allowedFileNames']  = [];       
        $aOptions['invalidFileNames']  = ['index.', 'synctoy', 'sync.ffs_lock', 'thumbs', 'web'];
        $aOptions['invalidExtensions'] = ['asp', 'htm', 'html', 'php', 'pl', 'db', '.exe', '.sh', '.ksh', '.bsh'];
        $aOptions['invalidHidden']     = ['.', '-', '~'];

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
                $xfPath = $this->objENC->encryptData("folder=" . $this->config['folder'] . "&realPath=" . $xfPath . "&FileIO_Action=Manage&FileIO_filePath=" . $xfPath);
                $xMURL  = $this->config['qwServerURL'] . "?" . $xfPath;
                echo "<tr>
                    <td>
                    <a href='$xfURL'>{$file['name']}</a></td>
                    <td>{$file['modified']}</td>
                    <td>{$file['size']}</td>
                    <td>
                        <a href='#' onClick=\"location.href='$xMURL'\">Manage</a>
                    </td>
                </tr>";
            }
        }
        echo "</table>";
    }

    // -----------------------------------------------------------------------------
    // TODO: support regular expresssions
    //       add default values
    // -----------------------------------------------------------------------------
    public function isFileMatched($aOptions) {

        //$aOptions['fileName'] = "xxx";
        // always lower case....
        // $aOptions['allowedExtensions'] = [];       
        // $aOptions['allowedFileNames']  = [];       
        // $aOptions['invalidFileNames']  = ['index.', 'synctoy', 'sync.ffs_lock', 'thumbs', 'web'];
        // $aOptions['invalidExtensions'] = ['asp', 'htm', 'html', 'php', 'pl', 'db', '.exe'];
        // $aOptions['invalidHidden']     = ['.', '-', '~'];

        // echo "<li>fileName: $aOptions['fileName']</li>";
        if (is_null($aOptions['fileName']) || (strcmp($aOptions['fileName'],"")==0) ) return False;

        $xFileExtension  = strtolower(pathinfo($aOptions['fileName'], PATHINFO_EXTENSION));
       
        // Allow by file extensions type
        if (isset($aOptions['allowedExtensions'])) {
            if ( in_array($xFileExtension, $aOptions['allowedExtensions']) ) {
                return false;
            }
        }
        
        // Allow by file extensions type
        if (isset($aOptions['allowedFileNames'])) {
            if ( in_array($xFileExtension, $aOptions['allowedFileNames']) ) {
                return false;
            }
        }
        

        // Exclude by file extensions type
        if (isset($aOptions['invalidExtensions'])) {
            if ( in_array($xFileExtension, $aOptions['invalidExtensions']) ) {
                return false;
            }
        }

        // Exclude when normally-hidden  e.g.:  $invalidHidden = ['.', '-', '~'];
        if (isset($aOptions['invalidHidden'])) {
            $xFirstChar = strtolower(substr($aOptions['fileName'],0,1));
            if (in_array($xFirstChar, $aOptions['invalidHidden'])) {
                return false;
            }
        }

        // Exclude when complete file name match is specificially excluded
        if (isset($aOptions['invalidFileNames'])) {
            if (in_array($aOptions['invalidFileNames'], $aOptions['invalidFileNames']) ) {
                return false;
            }
        }

        return true;
    }

    // -----------------------------------------------------------------------------
    // JavaScript manages form, submits uploaded files, and displays results w/o submitting form
    // upload.js verifies allowed file types and size limitations and submits files to:  upload.php
    // upload.php again verifies allowed file types and size limitations and places them in the proper folder (cwd) 
    // TODO:  accept tag needs improvement and thought.  Possibly set what to exclude?
    // -----------------------------------------------------------------------------
    public function displayUploadForm($aOptions){
        
        $xOptions          = "folder=" . $this->config['folder'] . "&realPath=" . $this->config['realPath'] . "&folderSep=" . $this->config['folderSep'];
        if ($this->config['ENCRYPTION']) {
             $xOptions     = $this->objENC->encryptData($xOptions); 
        } 
        $xURL    = $this->config['qwServerURL'] . "?" . $xOptions;
        $xTitle  = $this->config['folder'];
        $xQW     = $this->config['QWNAME'];
        
echo <<<EOHERE

<link rel="stylesheet" type="text/css" href="Upload.css">

<div class=title>$xQW</div>
<br>
You will upload file(s) into this folder:
&nbsp;&nbsp;<div class=info style="display:inline-block;">$xTitle</div>
<br>
<br>
<ul class=md>
<li>Select one of more files to upoad</li>
<li>A total of 6MB may be uploaded each upload attempt</li> 
<li>Specific file extensions may not be allowed e.g.:  .exe</li>
<li>Uploaded files will silenty replace files with the same name</li>
</ul>
<br>  
<form>
<input type="file" id="uploadFiles" name="uploadFiles" accept=".jpg, .jpeg, .png, .gif, .pdf, .txt, .zip, .7z" multiple="" style="opacity: 0;">
<input type="text" id="uploadKey" name="uploadKey" style="display:none" value="$xOptions">
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

        // Note:  upload.js must be after form and <input> tag are defined
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
        echo "<li>url: $url</li>";
        $ch = curl_init();

        // $url = curl_escape($ch, $url);

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
        if ($method === 'POST' && !empty($postFields)) {
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

}

?>


