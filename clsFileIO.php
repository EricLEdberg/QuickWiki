<?php

class clsFileIO {
	
	public function __construct($aConfig) {
		$this->MyDebug = false;
		$this->config  = $aConfig;
        $this->INIT($aConfig);
    }

    public function __destruct() {
		if ($this->MyDebug) {
			echo "<h2 class=info>clsFileIO Debug</h2>";
			$this->dump($this->config);
			echo "<h2 class=info>_REQUEST</h2>";
			$this->dump($_REQUEST);
		}
    }
    
    function dump($var) {
        echo "<div class=dbg><pre>";
        print_r($var);
        echo "</pre></div>";
    }

    // --------------------------------------------
    // INIT must be called on each page load to initialize some config[] options
    // --------------------------------------------
    function INIT(&$aConfig) { 
        global $objQW;
		
		$this->config['submitAction'] = "#";
		
		// ERROR:  are these lines doing anything since were passing in $aConfig?
		// Process $aConfig['Options']
		if (isset($aOptions['FileIO_Action']))    $this->config['FileIO_Action'] = $aOptions['FileIO_Action'];
		if (isset($aOptions['FileIO_Key']))       $this->config['FileIO_Key']    = $aOptions['FileIO_Key'];
		if (isset($aOptions['FileIO_filePath']))  $this->setFilePath($aOptions['filePath']);


		if (isset($aConfig['Options']['FileIO_filePath'])) {
			$this->setFilePath($aConfig['Options']['FileIO_filePath']);
		}

		// Manage form fields input by user (so they are not encrypted)
		if (isset($_REQUEST['FileIO_FileNameNew'])) $this->config['Options']['FileIO_FileNameNew'] = $_REQUEST['FileIO_FileNameNew'];

		return true;
	}

	// --------------------------------------------
    // initialize parameters about file currently being processed
	// currently assume class can only process 1 file at a time (which is not a good assumption)
	// return false if file does not exist
	// --------------------------------------------
    public function setFilePath(string $xFilePath) {	
		$this->config['filePathOrig'] = $xFilePath;
        $xFilePath                    = $this->sanitizeFolderPath($xFilePath);
		$this->config['fileRealPath'] = realpath($xFilePath);
		$this->config['filePath']     = $this->config['fileRealPath'];
		$this->config['fileName']     = basename($this->config['filePath']);

		if (!file_exists($this->config['fileRealPath'])) {
            $this->config['error'] = "File does not exist";
            return false;
        }
		return true;
    }

	// --------------------------------------------
    // Placeholder functions that can be overloaded by calling program to encrypt/decrypt strings
	// GET, POST, or PUT options are the target to prevent snooping of actual file information
	// Let's try to not natively embed encryption methods into this class
	// --------------------------------------------
    public function outgoingData(string $xStr) {
		return($xStr);
	}
	public function incommingData(string $xStr) {
		return($xStr);
	}
	
	// --------------------------------------------
    // verify if xItem (file or folder) resides in or under xRootFolder
	// Kinda CHROOT like...
	// Note that there are all sorts of issues when using realpath()... See manual page.
	// --------------------------------------------
	function isItemInRootFolder($xRootFolder, $xItem, $xCheckExistance = true) {
		
		if ($xCheckExistance) {
			// Normalize to absolute paths
			$xRootFolder = realpath($xRootFolder);
			$xItem       = realpath($xItem);
		}
		
		// Make sure both paths are valid
		if ($xRootFolder === false || $xItem === false) {
			return false;
		}	

		// Check if the file path is equal to or a subdirectory of the root directory
		// return strpos($xItem, $xRootFolder) === 0 && $xItem !== $xRootFolder;
		return strpos($xItem, $xRootFolder) === 0;
	}
	

	// -----------------------------------------------------------------------------
	// https://stackoverflow.com/questions/2021624/string-sanitizer-for-filename
	// Removes illegl characters
	// Can then compare origiinal to filtered to determine differences and error accordingly
	// -----------------------------------------------------------------------------
	function filterFilePath($xFilePath) {
		
		$xFilePath = preg_replace(
			'~
			[<>:"/\\\|?*]|           # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
			[\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
			[\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
			[#\[\]@!$&\'()+,;=]|     # URI reserved https://www.rfc-editor.org/rfc/rfc3986#section-2.2
			[{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
			~x',
			'-', $xFilePath);
		
		// avoids ".", ".." or ".hiddenFiles"
		$xFilePath = ltrim($xFilePath, '.-');
		
		// maximize filename length to 255 bytes http://serverfault.com/a/9548/44086
		$ext      = pathinfo($xFilePath, PATHINFO_EXTENSION);
		$xFilePath = mb_strcut(pathinfo($xFilePath, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($xFilePath)) . ($ext ? '.' . $ext : '');
		
		return $xFilePath;
	}

	// -----------------------------------------------------------------------------
	// Validate that file does not contain illegal characters
	// Must support both Linux and Windows naming conventions
	// Return true when an illegal character is found
	// ISSUE:  this check only works for the name of a folder, not the entire path
	//         since it prevents slash characters in file names
	//         need to upgrade function to check for either the name or path of a folder in addition to file names
	// -----------------------------------------------------------------------------
	public function containsIllegalCharacters ($xItem, &$err) {
		
		// Check for null or empty path
		if (empty($xItem)) {
			$err = "cannot be empty";
			return true;
		}

		// Check for null byte character
		if (strpos($xItem, "\0") !== false) {
			$err = "contains null byte character";
			return true;
		}

		// Check for trailing/leading spaces
		if (trim($xItem) !== $xItem) {
			$err = "contains space character";
			return true;
		}
	
		// Check for too long file/folder name
		if (strlen($xItem) > 255) {
			$err = "too long (>255 characters)";
			return true;
		}

		// Check for relative pathing using '.' or '..' characters
		if (strpos($xItem, './') !== false || strpos($xItem, '../') !== false || strpos($xItem, '.\\') !== false || strpos($xItem, '..\\') !== false ) {
			$err = "relative pathing using '.' or '..' characters is not allowed.";
		}
		
		// Define illegal characters for both Linux and Windows
		$illegalCharacters = [
			'/', '\\', ':', '*', '?', '"', '<', '>', '|'
		];
		
		// Check if the name contains any illegal characters
		foreach ($illegalCharacters as $char) {
			if (strpos($xItem, $char) !== false) {
				$err = "contians the illegal/prohibited character: " . $char;
				return true;
			}
		}
		
		// Additional check for Windows: Names cannot end with a dot or space
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			if (preg_match('/[\s\.]+$/', $xItem)) {
				$err = "contains a trailing . or space character";
				return true;
			}
		}

		// Additional checks 
		$xItem2 = $this->filterFilePath($xItem);
		if ( (strcmp($xItem,$xItem2)!=0) ) {
			$err = "Contains illegal/prohibited characters per filterFilePath()";
			return true;
		}
		
		return false;
	}
	
	// -----------------------------------------------------------------------------
	// Sanitize folder path
	// Do not error if file/path does not exist
	// realpath() returns false if file does not exist
    // -----------------------------------------------------------------------------
	public function sanitizeFolderPath ($xFolderPath) {
		
		if (is_null($xFolderPath) || (strcmp($xFolderPath,"")==0)) return $xFolderPath;

		// Deprecated in PHP 8.1+
		// removes tags and encode special characters
		// $xFolderPath = filter_var($xFolderPath, FILTER_SANITIZE_STRING);
		
		// replace more than 1 consequitive slash with a single slash
		$xFolderPath = preg_replace('#/{2,}#', '/', $xFolderPath);
		$xFolderPath = preg_replace('/\\\\{2,}/', '\\',$xFolderPath);
		
		// Why are we removing leading slash from folder?
		// Updated QW requires a leading slash on all folder definitions
		// $xFolderPath = preg_replace('{^/*}','',$xFolderPath);
		
		// realpath() resolves relative pathing to absolute and corrects mixed (unix/windows) and multiple orrucances folder seperators
		$xRealPath = realpath($xFolderPath);
		if (!$xRealPath) {
			;
		} else {
			$xFolderPath = $xRealPath;
		}
		
		return $xFolderPath;
	}

	// --------------------------------------------
    // --------------------------------------------
    function FILEIO_FORM_HEAD(){
		
		echo "<form name='FileIO' method='POST' action='"   . $this->config['submitAction'] . "'>";
		
		if (isset($config['Options']['FileIO_Key'])) {
			$xKey = "FileIO_filePath=" . $this->config['Options']['FileIO_filePath'];
			echo "<input name='FileIO_Key' type=hidden value='" . $this->outgoingData($xKey) . "'>";
		}
		
	}    

	// --------------------------------------------
    // --------------------------------------------
    function FILEIO_FOLDER_OPERATIONS() {
		echo "<h2>QWiki Folder Manager</h2>";
		if (strcmp($this->config['Options']['folder'],"")!=0) echo "<h3>Current Folder:&nbsp;&nbsp;&nbsp;<font color=blue><B>" . $this->config['Options']['folder'] . "</font></h3>";
		echo "<h3>Create New Sub Folder</h3>";
		echo "<ul>";
		echo "<li>This task will create a new folder in the current QWiki folder above</li>";
		echo "<li>Relative pathing using the '.' character, spaces, and other common folder naming restrictions apply and will be verified before folder creation</li>";
		echo "</ul>";
		echo "<h3>Input New Folder Name:&nbsp;&nbsp;";
			echo "<input type=text  name='FileIO_CreateFolderName' size=40>";
			echo "</h3>";
		echo "&nbsp;&nbsp;&nbsp;<input type=submit name='FileIO_Action' value='Create New Folder'>";

		echo "<h3>Notes:</h3><ul>";
		echo "<li>Folder operations will fail if the server web process does not have write permission to modify the Selected Folder</li>";
		echo "</ul>";
	}

	// --------------------------------------------
    // --------------------------------------------
    function FILEIO_OPERATIONS() {
		
		echo "<h2>QWiki File Manager</h2>";

		echo "<h3>Current Folder:&nbsp;&nbsp;&nbsp;<font color=blue><B>" . $this->config['Options']['folder'] . "</font></h3>";
		echo "<h3>Selected File:&nbsp;&nbsp;&nbsp;<font color=blue><b>" . basename($this->config['Options']['FileIO_filePath']) . "</B></font></h3>";

		echo "<h3>Copy / Rename File</h3>";
		echo "<ul>";
		echo "<li>Rename or Copy the file to a new file</li>";
		echo "<li>Will fail if the Specified File Name already exists</li>";
		echo "</ul>";
		echo "<B>New File Name:</B>&nbsp;&nbsp;";
		echo "<input type=text  name='FileIO_FileNameNew' size=40>";
		echo "<br><br>&nbsp;&nbsp;&nbsp;<input type=submit name='FileIO_Action' value='Copy File'>";
		echo "&nbsp;&nbsp;&nbsp;<input type=submit name='FileIO_Action' value='Rename File'>";

		echo "<h3>Delete File</h3>";
		echo "<ul>";
		echo "<li>This process will delete the file</li>";
		echo "<li>There is no way for the QWiki program to recover the file <em>(unless the computer administrator maintains it's own backups)</em></li>";
		echo "<li>You may want to <b>Archive</b> the file first unless you are sure it is no longer required</li>";
		echo "</ul>";
		echo "&nbsp;&nbsp;&nbsp;<input type=submit name='FileIO_Action' value='Delete File'>";
	
		echo "<h3>Archive File</h3>";
		echo "<ul>";
		echo "<li>This process will archive a date-stampted copy of the file in a folder named: <em>Save</em></li>";
		echo "<li>The file will have the: _YYYY-MM-DD, date stamp appended to the root portion of the file name. if (the file name is:  MyDocument.ppt, it will be saved as:  MyDocument_YYYY-MM-DD.ppt</li>";
		echo "<li>A new Save directory will be created if it does not exists</li>";
		echo "<li>An archive will only occur only once per day.  It will not overwrite a previously-achived file with the same date-stamp</li>";
		echo "</ul>";
		echo "&nbsp;&nbsp;&nbsp;<input type=submit name='FileIO_Action' value='Archive File'>";
		// echo "<br>&nbsp;&nbsp;&nbsp;<input type=checkbox name=ARCHIVE_DELETE_ORIGINAL>&nbsp;&nbsp;Delete original file after successful archive";

if(0) {
		echo "<h3>Uncompress</h3>";
		echo "<ul>";
		echo "<li>This task will uncompress the selected file into the directory where the archive resides</li>";
		echo "<li>Only .zip and .gz files types are supported</li>";
		echo "<li>It will automaticaly overwrite the contents of all directories and files recursively</li>";
		echo "<li>The file name of a gzip-encoded (.gz) file will be the filename without the .gz postfix</li>";
		echo "<li><font color=red>Make sure you know what is in the contents of the archive before continuing.  File restoration may not be possible in some cases.</font></li>";
		echo "</ul>";
		echo "&nbsp;&nbsp;&nbsp;<input type=submit name='FileIO_Action' value='Uncompress File'>";
}

		echo "</td></tr>";
		echo "</table>";

		echo "<h3>Notes:</h3><ul>";
		echo "<li>File operations will fail if the server web process does not have write permission to modify the Current Folder or the Selected File</li>";
		echo "</ul>";

	}

	// --------------------------------------------
    // --------------------------------------------
    function FILEIO_FORM_TAIL(){
		echo "</form>";

	}

	// --------------------------------------------
    // --------------------------------------------
    function FOLDER_CREATE($aOptions) {
		
		// current folder path
		$xFolderPath = $this->config['folderPath'];		
		if (is_null($xFolderPath) || (strcmp($xFolderPath,"")==0) ) {
			$this->config['error'] = "The folder path was not provided, Get Help";
			return false;
		}
		if (! is_dir($xFolderPath)) {
			$this->config['error'] = "The parent folder does not exist, Get Help";
			return false;
		}

		// new folder name
		$xFileIO_CreateFolderName = null;
		if (isset($_REQUEST['FileIO_CreateFolderName'])) $xFileIO_CreateFolderName = $_REQUEST['FileIO_CreateFolderName'];
		if (is_null($xFileIO_CreateFolderName) || (strcmp($xFileIO_CreateFolderName,"")==0) ) {
			$this->config['error'] = "Folder name was not provided";
			return false;
		}
		$this->config['Options']['FileIO_CreateFolderName'] = $xFileIO_CreateFolderName;
		
		// Verify name of new folder does not contain prohibited or illegal characters.
		if ($this->containsIllegalCharacters($this->config['Options']['FileIO_CreateFolderName'], $this->config['error'])) {
		 	return false;
		}

		// new folder path
		$xFolderPathNew = $xFolderPath . $this->config['folderSep'] . $xFileIO_CreateFolderName;
		$this->config['Options']['FileIO_CreateFolderPath'] = $this->sanitizeFolderPath($xFolderPathNew);
		

		// Verify new folder does not exist
		if (is_dir($this->config['Options']['FileIO_CreateFolderPath'])) {
			$this->config['error'] = "New folder already exists, please input an alternate name and try again";
			return false;
		}
		
		// Verify new folder path is a sub-directory of the root folder
		if (! $this->isItemInRootFolder($this->config['rootPath'], $this->config['Options']['FileIO_CreateFolderPath'], false) ) {
			$this->config['error'] = "New folder does not reside in QWiki root, Get Help";
			return false;
		}
		
		// Verify that new folder path does not contain illegal characters
		// ISSUE:  currently it checks for slash characters which is valid in a folder path
		//if ($this->containsIllegalCharacters($this->config['Options']['FileIO_CreateFolderPath'], $this->config['error'])) {
		//	return false;
		//}

		
		if ($this->MyDebug) {
			$this->config['error'] = "ATTENTION:  debugging is enabled, the folder process was verified but the actual folder was not created";
			return false;
		} else {
			if (!mkdir($this->config['Options']['FileIO_CreateFolderPath']) ){
				$this->config['error'] = "Failed to create new folder: " . $this->config['Options']['FileIO_CreateFolderName'] . ", could there be permission restrictions?";
				return false;
			}
		}

        return true;
    }

	// --------------------------------------------
    // Requires: filePath (relative or full)
    // --------------------------------------------
    function FILEIO_DELETE($aOptions) {

		if (!file_exists($this->config['Options']['FileIO_filePath'])) {
            $this->config['error'] = "File does not exist";
            return false;
        }
        
        if (unlink($this->config['Options']['FileIO_filePath'])) {
            if ($this->MyDebug) echo "File: " . basename($this->config['Options']['FileIO_filePath']) . ", deleted successfully.";
            return true;
        } 

        $this->config['error'] = "Failed to delete (unlink) file";
        return false;
    }

	// --------------------------------------------
    // --------------------------------------------
    function FILEIO_COPY($aOptions) {
		if (!file_exists($aOptions['src'])) {
            $this->config['error'] = "Source file does not exist?";
            return false;
        }
        if (file_exists($aOptions['dst'])) {
            $this->config['error'] = "Destination file with the same name already exists";
            return false;
        }
		if (!copy($aOptions['src'], $aOptions['dst']) ){
			$this->config['error'] = "Failed to copy file, could there be folder or file permission restrictions?";
			return false;
		}
        return true;
    }
	// --------------------------------------------
    // --------------------------------------------
    function FILEIO_RENAME($aOptions) {
		if (!file_exists($aOptions['src'])) {
            $this->config['error'] = "Source file does not exist?";
            return false;
        }
        if (file_exists($aOptions['dst'])) {
            $this->config['error'] = "New file name already exists";
            return false;
        }
		if (!rename($aOptions['src'], $aOptions['dst']) ){
			$this->config['error'] = "Failed to RENAME file, could there be folder or file permission restrictions?";
			return false;
		}
        return true;
    }
	// --------------------------------------------
	// parent folder must already exist.  See mkdir options for recursion.
	// Q: what about permissions?
    // --------------------------------------------
    function FILEIO_ARCHIVE($aOptions) {
		
		if (is_dir($aOptions['folderArchive']) === false) {
			if (mkdir($aOptions['folderArchive']) === false) {
	            $this->config['error'] = "Creatiion of archive folder faild, could there be folder or file permission restrictions?";
    	        return false;
			}
        }
		
		// Create archive file name
		$xMMDDYY              = "_" . date('Y-m-d');
		$aOptions['pathinfo'] = pathinfo($aOptions['src']);
		$aOptions['dst']      = $aOptions['folderArchive'] . $aOptions['folderSep'] . $aOptions['pathinfo']['filename'] . $xMMDDYY . "." . $aOptions['pathinfo']['extension'];

        return $this->FILEIO_COPY($aOptions);
    }

	// --------------------------------------------
	// function below here are not tested and only included as example and for future inclusion in the class
    // --------------------------------------------
    // removes files and non-empty directories
	function rrmdir($dir) {
	if (is_dir($dir)) {
		$files = scandir($dir);
		foreach ($files as $file)
		if ($file != "." && $file != "..") rrmdir("$dir/$file");
		rmdir($dir);
	}
	else if (file_exists($dir)) unlink($dir);
	} 

	// copies files and non-empty directories
	function rcopy($src, $dst) {
	if (file_exists($dst)) rrmdir($dst);
	if (is_dir($src)) {
		mkdir($dst);
		$files = scandir($src);
		foreach ($files as $file)
		if ($file != "." && $file != "..") rcopy("$src/$file", "$dst/$file"); 
	}
	else if (file_exists($src)) copy($src, $dst);
	}
}

// This class overloads clsFileIO and encrypts/decrypts post and query_string variables when it builds or processes client pages/forms
class QWFileIO extends clsFileIO {
	public function outgoingdata ($xStr) {
		global $objENC;
		if (is_null($xStr) || (strcmp($xStr,"")==0) ) return $xStr;
		return $objENC->encryptData($xStr);
	}
	public function incommingData ($xStr) {
		global $objENC;
		if (is_null($xStr) || (strcmp($xStr,"")==0) ) return $xStr;
		return $objENC->decryptData($xStr);
	}
}


// --------------------------------------------
// Process clsFileIO (file and folder manager) options
// --------------------------------------------
if (isset($_POST['FileIO_Action'])) {
    $config['Options']['FileIO_Action'] = $_POST['FileIO_Action'];
}
if (isset($_POST['FileIO_Key'])) {
	$config['Options']['FileIO_Key']    = $_POST['FileIO_Key'];
}
if (isset($config['Options']['FileIO_Key'])) {
    $config['Options']['FileIO_Key_Encrypted'] = $config['Options']['FileIO_Key'];
    $config['Options']['FileIO_Key']           = $objENC->decryptData($config['Options']['FileIO_Key']); 
}
// filePath may contain , or & characters on Windows.  These need to be encoded when constructing encrypted query string
// We do this by converting the filePath to hex.   See QW.php and clsFileIO.php
if (isset($config['Options']['FileIO_filePath'])) {
    $config['Options']['FileIO_filePath']      = $objENC->hexToStr($config['Options']['FileIO_filePath']);
}

// --------------------------------------------
// --------------------------------------------
$objFIO = new QWFileIO($objQW->config);

// Display FileIO Page Header
$objQW->displayPageOptions();

// ----------------------------------------------------------
// User submitted fileio form to perform an action
// ----------------------------------------------------------
switch (strtoupper($config['Options']['FileIO_Action'])) {
	case "CREATE NEW FOLDER":
		if (!$objFIO->FOLDER_CREATE($config) ) {
			echo "<br><br><div class=title>ERROR: " . $objFIO->config['error'] . "</div>";
		} else {
			echo "<br><br><div class=info>The folder was successfully created</div>";
		}
		echo "<br>&nbsp;&nbsp;";
		$objQW->ReturnToQwikiButton(null);
		exit;
		break;

	case "DELETE FILE":
			if (!$objFIO->FILEIO_DELETE($config['Options']['FileIO_Action'])) {
				echo "<br><br><div class=title>ERROR: " . $objFIO->config['error'] . "</div>";
			} else {
				echo "<br><br><div class=info>The file was successfully deleted</div>";
			}
			echo "<br>&nbsp;&nbsp;";
			$objQW->ReturnToQwikiButton(null);
			exit;
			break;
	
	case "COPY FILE":
		// realPath to src file initialized by FileIO Manage File form as an encrypted form parameter
		// TODO:  should also pass folderSep in form too if clsQW is not used by the main application
		// TODO:  write and include a:  fileSanitize() method to validate it does not contain illegal characters
		$aOptions['src'] = $objFIO->config['Options']['realPath'];         
		$aOptions['dst'] = dirname($objFIO->config['Options']['realPath']) . $objQW->config['folderSep'] . $objFIO->config['Options']['FileIO_FileNameNew'];
		if (!$objFIO->FILEIO_COPY($aOptions)) {
			echo "<br><br><div class=title>ERROR: " . $objFIO->config['error'] . "</div>";
		} else {
			echo "<br><br><div class=info>The file was successfully copied</div>";
		}
		echo "<br>&nbsp;&nbsp;";
		$objQW->ReturnToQwikiButton(null);
		exit;
		break;

	case "RENAME FILE":
		// realPath to src file initialized by FileIO Manage File form as an encrypted form parameter
		// TODO:  should also pass folderSep in form too if clsQW is not used by the main application
		// TODO:  write and include a:  fileSanitize() method to validate it does not contain illegal characters
		$aOptions['src'] = $objFIO->config['Options']['realPath'];         
		$aOptions['dst'] = dirname($objFIO->config['Options']['realPath']) . $objQW->config['folderSep'] . $objFIO->config['Options']['FileIO_FileNameNew'];
		if (!$objFIO->FILEIO_RENAME($aOptions)) {
			echo "<br><br><div class=title>ERROR: " . $objFIO->config['error'] . "</div>";
		} else {
			echo "<br><br><div class=info>The file was successfully renamed</div>";
		}
		echo "<br>&nbsp;&nbsp;";
		$objQW->ReturnToQwikiButton(null);
		exit;
		break;

	case "ARCHIVE FILE":
		$aOptions['folderSep']     = $objQW->config['folderSep'];
		$aOptions['src']           = $objFIO->config['Options']['realPath'];         
		$aOptions['folderArchive'] = dirname($objFIO->config['Options']['realPath']) . $objQW->config['folderSep'] . $objQW->config['ArchiveFolderName'];
		if (!$objFIO->FILEIO_ARCHIVE($aOptions)) {
			echo "<br><br><div class=title>ERROR: " . $objFIO->config['error'] . "</div>";
		} else {
			echo "<br><br><div class=info>The file was successfully archived</div>";
		}
		echo "<br>&nbsp;&nbsp;";
		$objQW->ReturnToQwikiButton(null);
		exit;
		break;
	}


// -------------------------------------------------------
// Default is to show file or folder operations page 
// -------------------------------------------------------

$objFIO->FILEIO_FORM_HEAD(null);

$xFIOAction = "";
if (isset($objFIO->config['Options']['FileIO_Action'])) $xFIOAction = $objFIO->config['Options']['FileIO_Action'];

// Only present folder or file operation menus depending on what where managing
switch (strtoupper($xFIOAction)) {
	case "CREATEFOLDER":
		$objFIO->FILEIO_FOLDER_OPERATIONS(null);
		break;
	default:
		$objFIO->FILEIO_OPERATIONS(null);
		break;
}

$objFIO->FILEIO_FORM_TAIL(null);

?>
