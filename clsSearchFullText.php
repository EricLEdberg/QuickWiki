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
	
	public  $Version         = "1.2";
	public  $MyDebug         = True;
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
		// $this->server      = gethostname();                // > php5.3.0
		$this->server      = php_uname('n');
		$this->app         = "qwiki";
		$this->pkey        = time();
		
		if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {
			echo 'ERROR:  php extension:  mysqli, is not loaded in php.ini.';
			exit;
		}

	}

	public function __destruct() {
		if ($this->MyDebug == True) print "<li>__destruct(): doing</li>";
		if ($this->objDBLocal == true) {
			// close if objDB was opened within the scope of this class
			$this->objDB->close();
		}
	}
	
	// ----------------------------------------------------------
	// Open connection to DB if object was not passed by calling application
	// See: Global.php, for typical definition
	// ----------------------------------------------------------
	public function InitDB() {
		
		// Needed to execute self-contained regression tests below
		if (isnull($this->gaaMYSQLInfo)) {
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
		
		// Open connection to DB if it was not opened/initialized by calling application
		if (is_null($this->objDB)) $this->InitDB();

		$this->QueryResults      = $this->objDB->query($xSql);
		$this->QueryResultsCnt   = $this->QueryResults->num_rows;
		
		if ($this->QueryResultsCnt < 1){
			if ($this->MyDebug == True) echo "<li>QueryDB(): Error: did not return record(s)</li>";
			mysqli_free_result($this->QueryResults);
			return false;
		}

		// copy results to global associative array
		// Should we leave the results in the SQL object or attempt to free and close the sql object asap?
		// A later question related to resource and memory management...
		
		// Note:  mysqli_fetch_all() does not work on Ubuntu pls-01.  Possibly missing libraries or PHP version?
		$this->aResults = mysqli_fetch_all($this->QueryResults);
	
		// Cycle through inidividuals rows.
//		mysqli_data_seek($this->QueryResults,0);
//		while($row = mysqli_fetch_array($this->QueryResults)) {
//			echo "<li>app: " . $row['app'] . ", server: " . $row['server'] . "</li>";
//		}
		
		// Do not free results since calling application may need access
		// mysqli_free_result($xResults);

		return true;
	}
	
	// ---------------------------------------------------------------------
	// Insert record into DB
	// Optionally delete dependent keys aDkey(s)
	// ---------------------------------------------------------------------
	public function InsertDB(&$aaOptions) {
		$xFields = "";
		$xValues = "";
		$xComma  = ", ";

		if ($this->MyDebug == True) {
			echo '<font color=blue><li><strong>clsSearchFullText.InsertDB()</strong></li>';
		}
		
		
		// ---------------------------------------------------------------------
		// ---------------------------------------------------------------------
		try {
			if (is_null($this->objDB)) $this->InitDB();
			
			// -----------------------
			// Turn query into a transaction because multiple inter-related commands are performed
			// http://stackoverflow.com/questions/2708237/php-mysql-transactions-examples
			// https://stackoverflow.com/questions/12091971/how-to-start-and-end-transaction-in-mysqli
			// -----------------------
			
			//if ($this->MyDebug == True) echo "<li>...MYSQLI begin_transaction() enabled</li>";
			//$this->objDB->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
			$this->objDB->autocommit(FALSE);

			// -----------------------
			// SQL Parameters
			// -----------------------

			$xFields   = "";
			$xValues   = "";
			$xStrDup   = "";

			$xFields  .= "server";
			$xValues  .= "'" . mysqli_real_escape_string($this->objDB, $this->server) . "'";
			$xStrDup  .=  "server=VALUES(server)";
			
			$xFields  .= ", app";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $this->app) . "'";
			$xStrDup  .=  ", app=VALUES(app)";
			
			$xFields  .= ", url";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $this->url) . "'";
			$xStrDup  .=  ", url=VALUES(url)";
			
			$xFields  .= ", instance";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $this->instance) . "'";
			$xStrDup  .=  ", instance=VALUES(instance)";
			
			$xFields  .= ", pkey";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $this->pkey) . "'";
			$xStrDup  .=  ", pkey=VALUES(pkey)";
			
			$xFields  .= ", title";
			$xValues  .= ",'" . mysqli_real_escape_string($this->objDB, $this->title) . "'";
			$xStrDup  .=  ", title=VALUES(title)";
			
			$xFields  .= ", body";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $this->body) . "'";
			$xStrDup  .=  ", body=VALUES(body)";
			
			// $xValues could specify more than 1 row of values e.g.:   (...) (...) 
			$xValues   = "(" . $xValues . ")";
			
			$xSql      = "INSERT INTO " . $this->gaaMYSQLInfo['TABLENAME'] . " (" . $xFields . ") VALUES " . $xValues;		
			$xSql    .= " ON DUPLICATE KEY UPDATE " . $xStrDup;
			$xSql    .= ";";
			
			
			// ------------
			// Submit Insert/Update query
			// ------------
			if ($this->MyDebug == True) echo "<li>...SQL: " . $xSql . "</li>";
			$objSqlResults = $this->objDB->query($xSql);
			
			// -----------------------
			// If Dependent Keys are specified then delete any dependent key that is not specified in the list.
			// -----------------------
			
			$xList  = "";
			$xComma = "";
			
			// Add known dependent keys to list AND their dependents
			$xCnt   = count($this->aDkey);
			If ($xCnt == 0) {
				// TODO:  delete any folder in pkey.  
				// The last sub-folder may have been deleted.

			} Else If ($xCnt > 0) {

				// -----------------------
				// Example debugged SQL to delete
				// -----------------------
				// DELETE FROM searchfulltext WHERE searchfulltext.id IN (
				// SELECT T2.id FROM (
				// SELECT searchfulltext.id FROM searchfulltext
				// WHERE
				//       searchfulltext.`server`='testingservices.web.alcatel-lucent.com' 
				//   AND searchfulltext.app='Qwiki' 
				//   AND searchfulltext.instance='/QWiki/FLWPC' 
				//   AND searchfulltext.pkey LIKE '\\\\FLWPC\\\\SSL Certificates\\\\Nokia Apache SSL Instructions\\\\%' 			
				//   AND searchfulltext.pkey NOT LIKE '\\\\FLWPC\\\\SSL Certificates\\\\Nokia Apache SSL Instructions\\\\' 
				//   AND searchfulltext.pkey NOT LIKE '\\\\FLWPC\\\\SSL Certificates\\\\Nokia Apache SSL Instructions\\\\ELE TESTING TWO\\\\%'
				//) AS T2
				// );

								
				$dkeySQL  = "DELETE FROM searchfulltext WHERE searchfulltext.id IN ( ";
				$dkeySQL .= "SELECT T2.id FROM (SELECT searchfulltext.id, searchfulltext.pkey FROM searchfulltext ";
				$dkeySQL .= "WHERE ";
				$dkeySQL .= "    searchfulltext.server='"   . mysqli_real_escape_string($this->objDB, $this->server)   . "' ";
				$dkeySQL .= "AND searchfulltext.app='"      . mysqli_real_escape_string($this->objDB, $this->app)      . "' ";
				$dkeySQL .= "AND searchfulltext.instance='" . mysqli_real_escape_string($this->objDB, $this->instance) . "' ";							
				// Recursively add all folders relative to the current pkey folder tree node
				$dkeySQL .= "AND searchfulltext.pkey LIKE '"         . $this->MySQLEscape(str_replace("\\","\\\\",$this->pkey))  . "%' ";
				// Exclude pkey root folder
				$dkeySQL .= "AND searchfulltext.pkey NOT LIKE '"     . $this->MySQLEscape(str_replace("\\","\\\\",$this->pkey))  . "' ";
				// Exclude existing dkey sub-folder tree nodes
				for($i=0; $i<$xCnt; $i++)  {
					$dkeySQL .= "AND searchfulltext.pkey NOT LIKE '" .  $this->MySQLEscape(str_replace("\\","\\\\",$this->aDkey[$i])) . "%'";
				}
				$dkeySQL .= ") AS T2 ";
				$dkeySQL .= ");";
			
				if ($this->MyDebug == True) echo "<li>...SQL: " . $dkeySQL . "</li>";
				
				// ------------
				// Submit query that manages dependent objects
				// ------------
				$objSqlResults = $this->objDB->query($dkeySQL);
			
			}			

			// -----------------------
			// -----------------------
			if ($this->MyDebug == True) echo "<li>...COMMIT()ing transaction" . "\n";
			$objCommitResults = $this->objDB->commit();
			$this->objDB->autocommit(TRUE);                          // end transaction.  Is this needed?
			
			if ($this->MyDebug == True) echo "</font>";

		// ---------------------------------------------------------------------
		// Error is SQL failed for any reason
		// ---------------------------------------------------------------------
		} catch (Exception $e ) {
			
			if ($this->MyDebug == True) echo "<li><font color=red>clsSearchFullText.php->InsertDB(): EXCEPTION ERROR: " . print_r($e) . "</li>";
			
			// before rolling back the transaction, you may want  to make sure that the exception was db-related
			$this->objDB->rollback(); 
			$this->objDB->autocommit(TRUE);                               // end transaction
			return False;
		}


		return True;
	}

	// ---------------------------------------------------------------------
	// Execute an SQL statement
	// Can be used by other classes once the DB has been opened.
	// ---------------------------------------------------------------------
	public function ExecuteSQL($xSql) {
				
		if ($this->MyDebug == True) {
			echo '<h3>ExecuteSQL</h3>';
			echo "<li>xSQL: " . $xSQL  . "</li>";
		}
		
		// Open connection to DB if it was not provided by calling application
		if (is_null($this->objDB)) $this->InitDB();
		
		if ($this->MyDebug == True) echo "<li>InsertDB(): sql: " . $xSql . "</li>";
		
		$xRet = $this->objDB->query($xSql);

		if($xRet){
			if ($this->MyDebug == True) echo "<li>ExecuteSQL(): Success\n";
		}else{
			die('Error : ('. $this->objDB->errno .') '. $this->objDB->error);
		}

		return true;
	}


	public function saveit() {

			// -----------------------
			// Execute Transaction
			// -----------------------
						
//			if($objSqlResults){
//				if ($this->MyDebug == True) echo "<li>...InsertDB(): Success, Record ID: " . $this->objDB->insert_id . "\n";
//			} else {
//				//$objSqlResults->free();
//				throw new Exception($this->objDB->error);
//				// die('Error : ('. $this->objDB->errno .') '. $this->objDB->error);
//			}


		// Initialize pkey in list of keys
		// $xList  = "'" . $this->MySQLEscape(str_replace("\\","\\\\",$this->pkey)) . "'";
		$xList  = "'" . $this->MySQLEscape($this->pkey) . "'";
		$xComma = ", ";

		for($i=0; $i<$xCnt; $i++)  {
			$xVal = $this->MySQLEscape($this->aDkey[$i]);
			// echo "<li>...dkey: " . $xVal . "</li>";
			$xList = $xList . $xComma . "'" . $xVal . "'";
		}
		
		// --------------------------------------------------------------------------------------------
		// --> SQL escape each "\" character as "\\"
		// --> Also, when using REGEXP,  escape each "\" character as "\\" a second time (4 \\\\'s for each \; geesh)
		// https://stackoverflow.com/questions/34691809/regex-match-folder-and-all-subfolders
		// REGEX:   [^\/]+       # matches any character other than / one or more times
		// Example: SELECT * FROM searchfulltext WHERE ( pkey REGEXP '^\\\\FLWPC\\\\SSL Certificates\\\\[^\\\\/]+\\\\$' );
		// --------------------------------------------------------------------------------------------
		
		// Delete sub-folders in pkey folder that do not exist anymore
		// The current folder (pkey) and list of current sub-folders (dkey) are provided
		// Goal is to delete references to sub-folders that do not exist anymore

		// Extract folder, and sub-folders only 1 level, that match pkey
		$dkeySQL  = " SELECT DISTINCT pkey FROM searchfulltext WHERE (";
		$dkeySQL .= " ( pkey REGEXP '" . $this->MySQLEscape(str_replace("\\","\\\\",$this->pkey)) . "[^\\\\\\\\/]+\\\\\\\\$' ) ";
		// Exclude sub-folders that still/curently exist
		$dkeySQL .= " AND ( pkey NOT IN (" . $xList . ") )";
		$dkeySQL .= " ); ";
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
		
		$objSFT          = new clsSearchFullText();
		$objSFT->MyDebug = True;
		
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
			
			if ($objSFT->MyDebug == True) echo "<li>clsSearchFullText.php:  xMODE: " . $xMODE . ", " . $_REQUEST['app'] . "</li>";
			
			If (IsSet($_REQUEST['app']))        $objSFT->app      = $_REQUEST['app'];
			If (IsSet($_REQUEST['instance']))   $objSFT->instance = $_REQUEST['instance'];
			If (IsSet($_REQUEST['pkey']))       $objSFT->pkey     = $_REQUEST['pkey'];
			If (IsSet($_REQUEST['server']))     $objSFT->server   = $_REQUEST['server'];
			If (IsSet($_REQUEST['url']))        $objSFT->url      = $_REQUEST['url'];
			If (IsSet($_REQUEST['title']))      $objSFT->title    = $_REQUEST['title'];
			If (IsSet($_REQUEST['body']))       $objSFT->body     = $_REQUEST['body'];
			If (IsSet($_REQUEST['dkey'])) {
				$objSFT->aDkey     = $_REQUEST['dkey'];
				echo "<li>aDkey: " . $objSFT->aDkey . "</li>";
			}
			
			$objSFT->InsertDB($xSql);
			
			exit;
		}

		//echo "<xmp>";
		//print_r($objSFT->aResults);
		//echo "</xmp>";
	}
	
}

?>
