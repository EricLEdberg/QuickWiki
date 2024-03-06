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
    function INIT($aConfig) { 
        $this->config['submitAction'] = "#";
		
		// Process $aConfig['Options']
		if (isset($aOptions['FileIO_Action']))    $this->config['FileIO_Action'] = $aOptions['FileIO_Action'];
		if (isset($aOptions['FileIO_Key']))       $this->config['FileIO_Key']    = $aOptions['FileIO_Key'];
		if (isset($aOptions['FileIO_filePath']))  $this->setFilePath($aOptions['filePath']);

		// Manage form fields input by user (so they are not encrypted)
		if (isset($_REQUEST['FileIO_FileNameNew'])) $this->config['Options']['FileIO_FileNameNew'] = $_REQUEST['FileIO_FileNameNew'];

		return true;
	}

	// --------------------------------------------
    // initialize parameters about file currently being processed
	// currently assume class only can process 1 file at a time (which is not a good assumption)
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
	// --------------------------------------------
	function isItemInRootFolder($xRootFolder, $xItem) {
		
		// Normalize to absolute paths
		$xRootFolder = realpath($xRootFolder);
		$xItem       = realpath($xItem);
	
		// Make sure both paths are valid
		if ($xRootFolder === false || $xItem === false) {
			return false;
		}
	
		// Check if the file path is equal to or a subdirectory of the root directory
		// return strpos($xItem, $xRootFolder) === 0 && $xItem !== $xRootFolder;
		return strpos($xItem, $xRootFolder) === 0;
	}

	// -----------------------------------------------------------------------------
	// Sanitize folder path
	// Do not error if file/path does not exist
	// realpath() returns false when file does not exist
    // -----------------------------------------------------------------------------
	public function sanitizeFolderPath ($xFilePath) {
		
		if (is_null($xFilePath) || (strcmp($xFilePath,"")==0)) return $xFilePath;

		// removes tags and encode special characters
		$xFilePath = filter_var($xFilePath, FILTER_SANITIZE_STRING);
		
		// replace more than 1 consequitive slash with a single slash
		$xFilePath = preg_replace('#/{2,}#', '/', $xFilePath);
		$xFilePath = preg_replace('/\\\\{2,}/', '\\',$xFilePath);
		$xFilePath = preg_replace('{^/*}','',$xFilePath);
			
		// realpath() resolves relative pathing to absolute and corrects mixed (unix/windows) and multiple orrucances folder seperators
		$xRealPath = realpath($xFilePath);
		if (!$xRealPath) {
			;
		} else {
			$xFilePath = $xRealPath;
		}
		
		return $xFilePath;
	}

	// --------------------------------------------
    // --------------------------------------------
    function FILEIO_FORM_HEAD(){
		$xKey = "FileIO_filePath=" . $this->config['Options']['FileIO_filePath'];

		echo "<form name='FileIO' method='POST' action='"   . $this->config['submitAction'] . "'>";
		echo "<input name='FileIO_Key' type=hidden value='" . $this->outgoingData($xKey)     . "'>";
		
		echo "<br><table>";
		echo "<tr><td colspan=20>";
			echo "<h2>Qwiki File Manager</h2>";
			echo "<B>Selected File:</B><br>&nbsp;&nbsp;&nbsp;<span class=title><B>" . basename($this->config['Options']['FileIO_filePath']) . "</B></span>";
	}    

	// --------------------------------------------
    // --------------------------------------------
    function FILEIO_OPERATIONS() {

		echo "<h3>Copy / Rename</h3>";
		echo "<ul>";
		echo "<li>Rename or Copy the file to a new file</li>";
		echo "<li>Will fail if the Specified File Name already exists</li>";
		echo "</ul>";
		echo "<br><B>New File Name:</B>&nbsp;&nbsp;";
		echo "<input type=text  name='FileIO_FileNameNew' size=100>";
		echo "<br><br>&nbsp;&nbsp;&nbsp;<input type=submit name='FileIO_Action' value='Copy File'>";
		echo "&nbsp;&nbsp;&nbsp;<input type=submit name='FileIO_Action' value='Rename File'>";

		echo "<h3>Delete</h3>";
		echo "<ul>";
		echo "<li>This process will delete the file</li>";
		echo "<li>There is no way for the Qwiki program to recover the file <em>(unless the computer administrator maintains it's own backups)</em></li>";
		echo "<li>You may want to <b>Archive</b> the file first unless you are sure it is no longer required</li>";
		echo "</ul>";
		echo "&nbsp;&nbsp;&nbsp;<input type=submit name='FileIO_Action' value='Delete File'>";
	
		echo "<h3>Archive</h3>";
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
	}

	// --------------------------------------------
    // --------------------------------------------
    function FILEIO_FORM_TAIL(){
		echo "</form>";

		echo "<h3>Notes:</h3><ul>";
			echo "<li>Qwiki File Manager must have RW permissions in the folder where the file resides.</li>";
			echo "<li>If you are authorized, you may be able to manually mount the folder or share where the file resides and manually perform changes.</li>";
		echo "</ul>";
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

$objFIO = new QWFileIO($objQW->config);

// Display FileIO Page Header
$objQW->displayPageOptions();
	

// delete file
switch (strtoupper($config['Options']['FileIO_Action'])) {
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

// Default to show FileIO "manage" options
$objFIO->FILEIO_FORM_HEAD(null);
$objFIO->FILEIO_OPERATIONS(null);
$objFIO->FILEIO_FORM_TAIL(null);

?>
