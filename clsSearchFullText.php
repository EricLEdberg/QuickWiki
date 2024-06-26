<?php
/**
 * ---------------------------------------------------------------------------------
 * Methods to use and manage the MySQL Full Text Search
 * Eric Edberg 9/2016, 2/2024
 * ---------------------------------------------------------------------------------
 */

/*

-- ----------------------------------------------------------------------------
-- Table testingservices.searchfulltext
-- Mobility Database Script - ELE 2017/05/30 
-- Note ENGINE=MyISAM required to support Full Text Search using MySQL 5.5
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `testingservices`.`searchfulltext` (
  `id` INT(11) NOT NULL COMMENT '',
  `server` VARCHAR(64) NOT NULL COMMENT '',
  `url` VARCHAR(256) NULL DEFAULT NULL COMMENT '',
  `app` VARCHAR(45) NOT NULL COMMENT '',
  `instance` VARCHAR(45) NULL DEFAULT NULL COMMENT '',
  `pkey` VARCHAR(256) NOT NULL COMMENT '',
  `title` VARCHAR(256) NOT NULL COMMENT '',
  `body` TEXT NOT NULL COMMENT '',
  `dateupdated` DATETIME NULL COMMENT '',
  PRIMARY KEY (`server`, `app`, `pkey`(255))  COMMENT '',
  INDEX `IX_app` (`app` ASC)  COMMENT '',
  INDEX `IX_server` (`server` ASC)  COMMENT '',
  INDEX `IX_id` (`id` ASC)  COMMENT '',
  INDEX `IX_instance` (`instance` ASC)  COMMENT '',
  INDEX `IX_pkey` (`pkey`(255) ASC)  COMMENT '',
  FULLTEXT INDEX `IX_body` (`body`(255) ASC, `title`(255) ASC)  COMMENT '')
ENGINE = MyISAM
AUTO_INCREMENT = 4266
DEFAULT CHARACTER SET = latin1;

-- ----------------------------------------------------------------------------
-- Table testingservices.searchfulltext
-- PLS Database Script
-- ELE 2017 initial repository
-- Note default ENGINE=InnoDB with built-in support for Full Text Search using MySQL 5.6+
-- ----------------------------------------------------------------------------
CREATE TABLE `searchfulltext` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server` varchar(64) NOT NULL,
  `url` varchar(256) DEFAULT NULL,
  `app` varchar(45) NOT NULL,
  `instance` varchar(45) DEFAULT NULL,
  `pkey` varchar(256) NOT NULL,
  `title` varchar(256) NOT NULL,
  `body` text NOT NULL,
  `dateupdated` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`server`,`app`,`pkey`),
  KEY `IX_app` (`app`),
  KEY `IX_server` (`server`),
  KEY `IX_id` (`id`),
  KEY `IX_instance` (`instance`),
  KEY `IX_pkey` (`pkey`),
  FULLTEXT KEY `IX_body` (`body`,`title`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=latin1;
*/

class clsSearchFullText {
	
	public  $Version         = "1.3";
	public  $MyDebug         = false;
	public  $error           = NULL;
	public  $objDB           = NULL;
	private $objDBLocal      = False;                         // set true when class opens SQL objDB
	public  $aResults        = array();
	public  $QueryResults    = "";
	public  $QueryResultsCnt = 0;                             // count of items returned by query

	public  $server          = "";                            // server / hostname
	public  $url             = "";                            // URL to page being indexed
	public  $app             = "";                            // Application name e.g.:  RMS, Qwiki_LBS, etc.
	public  $instance        = "";                            // Instance of an application
	public  $pkey            = "";                            // Uniquely identify page/text reference being indexed by the app
	public  $title           = "";                            // 
	public  $body            = "";                            // 
	public  $aDkey           = array();                       // Dependent Keys dependent on Parent Key

	public  $gaaMYSQLInfo     = NULL;                          // Default is NULL and should be initialized by app

	public function __construct() {		
		$this->server      = php_uname('n');
		$this->app         = "qwiki";
		$this->pkey        = time();
		
		if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {
			echo 'ERROR:  php extension:  mysqli, is not loaded in php.ini.';
			exit;
		}
	}

	public function __destruct() {
		
		if ($this->objDBLocal) {
			// close if objDB was opened by this class
			$this->objDB->close();
		}
	}
	
	// ----------------------------------------------------------
	// Open connection to DB if object was not passed by calling application
	// See: Global.php, for typical definition
	// ----------------------------------------------------------
	public function InitDB() {
		
		// Needed to execute self-contained regression tests below
		if (is_null($this->gaaMYSQLInfo)) {
			$this->gaaMYSQLInfo  = array(
				"SERVER"    => "192.168.0.141",
				"DBNAME"    => "EdbergTools",
				"TABLENAME" => "searchfulltext", 
				"USER"      => "eledb", 
				"PASS"      => "DataEdb3rg1234#",
				"PORT"      => "3306"
			);
		}
		
		$this->objDB = new mysqli($this->gaaMYSQLInfo['SERVER'], $this->gaaMYSQLInfo['USER'], $this->gaaMYSQLInfo['PASS'], $this->gaaMYSQLInfo['DBNAME'],$this->gaaMYSQLInfo['PORT']);
		if($this->objDB->connect_errno > 0){
			die('clsSearchFullText.InitDB() - Unable to connect to database [' . $this->objDB->connect_error . ']');
		}

		$this->objDBLocal = true;
		
	}

	function dump($var) {
        echo "<div class=dbg><pre>";
        print_r($var);
        echo "</pre></div>";
    }

	// ---------------------------------------------------------------------
	// Open connection to DB if it was not opened by calling application
	// ---------------------------------------------------------------------
	public function MySQLEscape($xStr) {
		if (is_null($this->objDB)) $this->InitDB();
		return mysqli_real_escape_string($this->objDB, $xStr);
	}
	
	// ---------------------------------------------------------------------
	// Return true on success 
	// Copy results to PHP variable if not NULL or default $this->aResults
	// ---------------------------------------------------------------------
	public function QueryDB($xSql) {
		
		if (is_null($this->objDB)) $this->InitDB();

		$this->QueryResults      = $this->objDB->query($xSql);
		$this->QueryResultsCnt   = $this->QueryResults->num_rows;
		
		if ($this->QueryResultsCnt < 1){
			if ($this->MyDebug) echo "<li>QueryDB(): Error: did not return record(s)</li>";
			mysqli_free_result($this->QueryResults);
			return false;
		}

		// copy results to global associative array
		// Should we leave the results in the SQL object or attempt to free and close the sql object asap?
		// A later question related to resource and memory management...
		
		$this->aResults = mysqli_fetch_all($this->QueryResults);
		
		// Do not free results since calling application may need to access
		// mysqli_free_result($xResults);

		return true;
	}

	// ---------------------------------------------------------------------
	// INSERT or UPDATE Full Text Search database
	// - delete key(s) contained in DKEYS array that are dependent on the PKEY (folders....)
	//   This algorithm assumes that the pkey is a folder/directory and dkey(s) are sub-folders e.g.:  pkey == current_folder & dkey(s) are sub-folders
	//   It is tightly coupled to QwickWiki.   This code should be in clsQwiki.php as a callback since it's not a generic search method for other applications.
	// ---------------------------------------------------------------------
	public function InsertDB(&$aOptions) {

		$this->MyDebug = false;

		$xFields  = "";
		$xValues  = "";
		$xComma   = ", ";

		if ( (strcmp($aOptions['BODY'],"")==0) && (strcmp($aOptions['DKEYS'],"")==0) ) {
			if ($this->MyDebug) echo "<li>InsertDB(clsFullTextSearch):  BODY and DKEYS are both empty, no folder or files either...</li>";
			return false;
		}


		if ($this->MyDebug) {
			echo '<li><strong>clsSearchFullText.InsertDB()</strong></li>';
		}
			
		// ---------------------------------------------------------------------
		// query is a transaction because multiple inter-related commands are performed
		// ---------------------------------------------------------------------
		try {
			if (is_null($this->objDB)) $this->InitDB();
			
		
			if ($this->MyDebug) {
				$this->dump($aOptions);
			}

			// -----------------------
			// Insert FTS record for CWD
			// -----------------------

			$xFields   = "";
			$xValues   = "";
			$xStrDup   = "";

			$xFields  .= "server";
			$xValues  .= "'" . mysqli_real_escape_string($this->objDB, $aOptions['SERVER']) . "'";
			$xStrDup  .=  "server=VALUES(server)";
			
			$xFields  .= ", app";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $aOptions['APP']). "'";
			$xStrDup  .=  ", app=VALUES(app)";
			
			$xFields  .= ", pkey";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $aOptions['PKEY']) . "'";
			$xStrDup  .=  ", pkey=VALUES(pkey)";
			
			$xFields  .= ", instance";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $aOptions['INSTANCE']) . "'";
			$xStrDup  .=  ", instance=VALUES(instance)";
			
			$xFields  .= ", url";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $aOptions['URL']) . "'";
			$xStrDup  .=  ", url=VALUES(url)";
			
			$xFields  .= ", title";
			$xValues  .= ",'" . mysqli_real_escape_string($this->objDB, $aOptions['TITLE']) . "'";
			$xStrDup  .=  ", title=VALUES(title)";
			
			$xFields  .= ", body";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $aOptions['BODY']) . "'";
			$xStrDup  .=  ", body=VALUES(body)";
			
			// $xValues could specify more than 1 row of values e.g.:   (...) (...) 
			$xValues   = "(" . $xValues . ")";
			
			$xSql      = "INSERT INTO " . $this->gaaMYSQLInfo['TABLENAME'] . " (" . $xFields . ") VALUES " . $xValues;		
			$xSql    .= " ON DUPLICATE KEY UPDATE " . $xStrDup;
			$xSql    .= ";";
			
			// Submit Insert/Update query
			if ($this->MyDebug) echo "<li>...SQL1: " . $xSql . "</li>";
			$objSqlResults = $this->objDB->query($xSql);
			
			// -----------------------
			// Delete child folders of the CWD that do no longer exist
			// These folders were deleted/removed by unknown means, often manually by users managing files/folders/content themselves
			// -----------------------
			if (is_array($aOptions['DKEYS'])) {
				$xList  = "";
				$xComma = "";

				// Add known dependent keys to list AND their dependents
				$xCnt   = count($aOptions['DKEYS']);
				If ($xCnt == 0) {
					// TODO:  delete any folder in pkey.  
					// The last sub-folder may have been deleted.

				} Else If ($xCnt > 0) {
								
					$dkeySQL  = "DELETE FROM searchfulltext WHERE searchfulltext.id IN ( ";
					$dkeySQL .= "SELECT T2.id FROM (SELECT searchfulltext.id, searchfulltext.pkey FROM searchfulltext ";
					$dkeySQL .= "WHERE ";
					$dkeySQL .= "    searchfulltext.server='"   . mysqli_real_escape_string($this->objDB, $aOptions['SERVER'])   . "' ";
					$dkeySQL .= "AND searchfulltext.app='"      . mysqli_real_escape_string($this->objDB, $aOptions['APP'])      . "' ";
					$dkeySQL .= "AND searchfulltext.instance='" . mysqli_real_escape_string($this->objDB, $aOptions['INSTANCE']) . "' ";							
					// Recursively add all folders relative to the current pkey folder tree node
					$dkeySQL .= "AND searchfulltext.pkey LIKE '"         . $this->MySQLEscape(str_replace("\\","\\\\",$aOptions['PKEY']))  . "%' ";
					// Exclude pkey root folder
					$dkeySQL .= "AND searchfulltext.pkey NOT LIKE '"     . $this->MySQLEscape(str_replace("\\","\\\\",$aOptions['PKEY']))  . "' ";

					// do not delete sub-folder (dkey) that currently exists
					foreach($aOptions['DKEYS'] as $xKey => $xValue) {
						// $this->dump($xValue);
						$xKey     = $xValue['pkey'];
						$dkeySQL .= "AND searchfulltext.pkey NOT LIKE '" .  $this->MySQLEscape(str_replace("\\","\\\\",$xKey)) . "%' ";
					}	
			
					$dkeySQL .= ") AS T2 ";
					$dkeySQL .= ");";
					
					if ($this->MyDebug) echo "<li>...SQL(DKEYS): " . $dkeySQL . "</li>";
					
					// Submit query that manages dependent objects
					$objSqlResults = $this->objDB->query($dkeySQL);
				
				}			
			}

			// -----------------------
			if ($this->MyDebug) echo "<li>...COMMIT transaction" . "\n";
			$objCommitResults = $this->objDB->commit();
			$this->objDB->autocommit(TRUE);

		// ---------------------------------------------------------------------
		// SQL failed :-(
		// ---------------------------------------------------------------------
		} catch (Exception $e ) {
			$aOptions['ERROR'] = $e;

			if ($this->MyDebug){
				echo "<li>clsSearchFullText.php->InsertDB(): EXCEPTION ERROR: " . print_r($e) . "</li>";
			} 
			
			$this->objDB->rollback(); 
			$this->objDB->autocommit(TRUE);  // restore setting
			return False;
		}

		return True;
	}

	// ---------------------------------------------------------------------
	// Execute an SQL statement
	// Can be used by other classes once the DB has been opened.
	// ---------------------------------------------------------------------
	public function ExecuteSQL($xSql) {
				
		if ($this->MyDebug) {
			echo '<h3>ExecuteSQL</h3>';
			echo "<li>xSQL: " . $xSQL  . "</li>";
		}
		
		// Open connection to DB if it was not provided by calling application
		if (is_null($this->objDB)) $this->InitDB();
		
		if ($this->MyDebug) echo "<li>InsertDB(): sql: " . $xSql . "</li>";
		
		$xRet = $this->objDB->query($xSql);

		if($xRet){
			if ($this->MyDebug) echo "<li>ExecuteSQL(): Success\n";
		}else{
			die('Error : ('. $this->objDB->errno .') '. $this->objDB->error);
		}

		return true;
	}
	
}


// ---------------------------------------------------------------------
// Various regression and/or development tests
// Not really  tests :-(
// ---------------------------------------------------------------------
if ( isset($_SERVER["SCRIPT_NAME"])) {
	if (basename($_SERVER["SCRIPT_NAME"]) == "clsSearchFullText.php") {
		// echo "<h1>Class Full Text Search Test</h1>";
		
		$xMODE = "";

		If (IsSet($_REQUEST['MODE'])) $xMODE = $_REQUEST['MODE'];
		
		$objFTS          = new clsSearchFullText();
		$objFTS->MyDebug = True;
		
		// ------------------------------------------
		// ------------------------------------------
		if ($xMODE == "JSON_TEST1") {
			
			echo "<h1>JSON TEST 1</h1>";
			$JSON_DataFileName = "JsonData.txt";
			
			$json_data = json_decode(file_get_contents($JSON_DataFileName), true);
			print_r($json_data);
			
			exit(0);
			for ($i = 0, $len = count($json_data); $i < $len; ++$i) {
				$json_data[$i]['num'] = (string) ($i + 1);
			}
			// file_put_contents($JSON_DataFileName, json_encode($json_data));
		}

		// ------------------------------------------
		// TODO:  Check for required variables.
		// TODO:  Add encryption key aka: password, to verify user is authorized to insert data.
		// ------------------------------------------
		if ($xMODE == "INSERT"){
			
			if ($objFTS->MyDebug == True) echo "<li>clsSearchFullText.php:  xMODE: " . $xMODE . ", " . $_REQUEST['app'] . "</li>";
			
			If (IsSet($_REQUEST['app']))        $objFTS->app      = $_REQUEST['app'];
			If (IsSet($_REQUEST['instance']))   $objFTS->instance = $_REQUEST['instance'];
			If (IsSet($_REQUEST['pkey']))       $objFTS->pkey     = $_REQUEST['pkey'];
			If (IsSet($_REQUEST['server']))     $objFTS->server   = $_REQUEST['server'];
			If (IsSet($_REQUEST['url']))        $objFTS->url      = $_REQUEST['url'];
			If (IsSet($_REQUEST['title']))      $objFTS->title    = $_REQUEST['title'];
			If (IsSet($_REQUEST['body']))       $objFTS->body     = $_REQUEST['body'];
			If (IsSet($_REQUEST['dkey'])) {
				$objFTS->aDkey     = $_REQUEST['dkey'];
				echo "<li>aDkey: " . $objFTS->aDkey . "</li>";
			}
			
			$objFTS->InsertDB($xSql);
			
			exit;
		}

		//echo "<pre>";
		//print_r($objFTS->aResults);
		//echo "</pre>";
	}

}

?>
