<?php
/**
 * ---------------------------------------------------------------------------------
 * QW methods to use and manage MySQL Full Text Search
 * Eric Edberg 9/2016, 2/2024
 * ---------------------------------------------------------------------------------
 */

/*
CREATE TABLE `searchfulltext` (
  `id` int NOT NULL AUTO_INCREMENT,
  `server` varchar(64) NOT NULL,
  `url` varchar(256) DEFAULT NULL,
  `app` varchar(64) NOT NULL,
  `instance` varchar(64) NOT NULL,
  `pkey` varchar(256) NOT NULL,
  `title` varchar(256) NOT NULL,
  `body` text NOT NULL,
  `dateupdated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'GMT time this mysql record was updated',
  `datemodified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'GMT body was last updated',
  PRIMARY KEY (`server`,`app`,`pkey`,`instance`),
  KEY `IX_app` (`app`),
  KEY `IX_server` (`server`),
  KEY `IX_id` (`id`),
  KEY `IX_instance` (`instance`),
  KEY `IX_pkey` (`pkey`),
  FULLTEXT KEY `IX_body` (`body`,`title`)
) ENGINE=InnoDB AUTO_INCREMENT=1783 DEFAULT CHARSET=latin1;

*/

class clsFullTextSearch {
	
	public  $Version         = "1.5";
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

	public function __construct($aConfig) {		
		
		$this->server      = php_uname('n');
		$this->app         = "qwiki";
		$this->pkey        = time();
		
		// DB connection details
		// TODO:  should contain other options too

		//$this->gaaMYSQLInfo = $aConfig['config']['FTSDB'];
		//$this->gaaMYSQLInfo = $aConfig['FTSDB'];
		$this->gaaMYSQLInfo = $aConfig;
		
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
	// ISSUE:  connection to DB on invalid host (ip) times out after 30 seconds and causes error.
	//         Why is connect_err not set when the DB is not responding or even exist?
	// ----------------------------------------------------------
	public function InitDB() {

		$this->objDB = new mysqli($this->gaaMYSQLInfo['SERVER'], $this->gaaMYSQLInfo['USER'], $this->gaaMYSQLInfo['PASS'], $this->gaaMYSQLInfo['DBNAME'],$this->gaaMYSQLInfo['PORT']);
		if($this->objDB->connect_errno > 0){
			die('clsFullTextSearch.InitDB() - Unable to connect to database [' . $this->objDB->connect_error . ']');
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
	// This algorithm is specific for managing a parent folder and optional child folders.
	// INSERT or UPDATE Full Text Search database
	// - delete key(s) contained in DKEYS array that are dependent on the PKEY
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
			echo '<li><strong>clsFullTextSearch.InsertDB()</strong></li>';
		}
			
		// ---------------------------------------------------------------------
		// query is a transaction
		// ---------------------------------------------------------------------
		try {
			if (is_null($this->objDB)) $this->InitDB();
		
			if ($this->MyDebug) {
				$this->dump($aOptions);
			}

			// -----------------------
			// Insert or update record
			// MySQL table "Primary Key" fields distinquish if an insert or update should occur
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
			
			if (isset($aOptions['DATEMODIFIED'])) {
				$xDT       = date('Y-m-d H:i:s', $aOptions['DATEMODIFIED']);               // convert GMT mtime to datetime
				$xFields  .= ", datemodified";
				$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $xDT) . "'";
				$xStrDup  .=  ", datemodified=VALUES(datemodified)";
			}
			
			$xFields  .= ", url";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $aOptions['URL']) . "'";
			$xStrDup  .=  ", url=VALUES(url)";
			
			$xFields  .= ", title";
			$xValues  .= ",'" . mysqli_real_escape_string($this->objDB, $aOptions['TITLE']) . "'";
			$xStrDup  .=  ", title=VALUES(title)";
			
			$xFields  .= ", body";
			$xValues  .= ", '" . mysqli_real_escape_string($this->objDB, $aOptions['BODY']) . "'";
			$xStrDup  .=  ", body=VALUES(body)";

			// hint:   $xValues could specify more than 1 row of values at a time e.g.:   (...) (...) 
			// It is very efficient if many rows are submitted simultaneously.  Data validation is key though to prevent transaction from failing!
			// I previously used to bulk-update 200 rows at a time in other applications
			$xValues   = "(" . $xValues . ")";
			
			$xSql      = "INSERT INTO " . $this->gaaMYSQLInfo['TABLENAME'] . " (" . $xFields . ") VALUES " . $xValues;		
			$xSql    .= " ON DUPLICATE KEY UPDATE " . $xStrDup;
			$xSql    .= ";";
			
			// Submit Insert/Update query
			if ($this->MyDebug){
				echo "<li>...SQL1: " . $xSql . "</li>";
				exit;
			}
			$objSqlResults = $this->objDB->query($xSql);
			
			// -----------------------
			// Delete child folders of the current folder that no longer exist
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
						
					// record KEY
					$dkeySQL  = "DELETE FROM searchfulltext WHERE searchfulltext.id IN ( ";
					$dkeySQL .= "SELECT T2.id FROM (SELECT searchfulltext.id, searchfulltext.pkey FROM searchfulltext ";
					$dkeySQL .= "WHERE ";
					$dkeySQL .= "    searchfulltext.server='"   . mysqli_real_escape_string($this->objDB, $aOptions['SERVER'])   . "' ";
					$dkeySQL .= "AND searchfulltext.app='"      . mysqli_real_escape_string($this->objDB, $aOptions['APP'])      . "' ";
					$dkeySQL .= "AND searchfulltext.instance='" . mysqli_real_escape_string($this->objDB, $aOptions['INSTANCE']) . "' ";

					// Step 1:  Delete ALL folders relative the current folder recursively, including the current folder.
					$dkeySQL .= "AND searchfulltext.pkey LIKE '"         . $this->MySQLEscape(str_replace("\\","\\\\",$aOptions['PKEY']))  . "%' ";

					// Step 2:  Exclude current folder since it's the one were currently indexing
					$dkeySQL .= "AND searchfulltext.pkey NOT LIKE '"     . $this->MySQLEscape(str_replace("\\","\\\\",$aOptions['PKEY']))  . "' ";

					// Step 3:  Exclude all child folders (and their sub-folders) that still exist in the current folder
					// Any child folder (and it's sub-folders) that physically does not exist anymore will be deleted from FTS based on step #1...
					foreach($aOptions['DKEYS'] as $xKey => $xValue) {
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
				echo "<li>clsFullTextSearch.php->InsertDB(): EXCEPTION ERROR: " . print_r($e) . "</li>";
			} 
			
			$this->objDB->rollback(); 
			$this->objDB->autocommit(TRUE);  // restore setting
			return False;
		}

		if ($this->MyDebug) echo "<li>objFTS->InsertDB(): updated database</li>";
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
	if (basename($_SERVER["SCRIPT_NAME"]) == "clsFullTextSearch.php") {
		// echo "<h1>Class Full Text Search Test</h1>";
		
		$xMODE = "";

		If (IsSet($_REQUEST['MODE'])) $xMODE = $_REQUEST['MODE'];
		
		$objFTS          = new clsFullTextSearch();
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
			
			if ($objFTS->MyDebug == True) echo "<li>clsFullTextSearch.php:  xMODE: " . $xMODE . ", " . $_REQUEST['app'] . "</li>";
			
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
