<?php
// Requires QW.php to initialize: $config['FTSDB']

require_once("clsFullTextSearch.php");

$objFTS = new clsFullTextSearch($config['FTSDB']);

// ------------------------------------------
// Delete references to: pkey, root and sub-folders in searchfulltext table
// If ID is not known then pass dummy value zero (0)
// ------------------------------------------
Function FTS_SearchDelete ($aOptions) {
	Global $objFTS;
	
	$xID   = $_REQUEST["id"];
	$xPKEY = $_REQUEST["pkey"];
	
	echo "<h1>Delete Cached Search Records</h1>";
		
	$objFTS->InitDB();

	// Delete specific row
	$xVal = str_replace("\\","\\\\\\\\",$xPKEY);
	$xSql = "DELETE FROM searchfulltext WHERE (id='" . $objFTS->MySQLEscape($xID) . "' AND id > 0) OR pkey='" . $xVal . "';";	
	echo "<li><font size=2 color=maroon>" . $xSql . "</font></li>";
	$bRet = $objFTS->ExecuteSQL($xSql);
	if ($bRet == False) {
		echo "<font color=red size=3><em>...Oops,  The query returned false</em></font>";
	}
	

	// Delete search references to all sub-folders of the one being deleted since their parent pathing does not exist anymore
	$xVal = str_replace("\\","\\\\\\\\",$xPKEY) . "%";
	$xSql = "DELETE FROM searchfulltext WHERE pkey LIKE '" . $xVal . "%';";
	echo "<li><font size=2 color=maroon>" . $xSql . "</font></li>";
	$bRet = $objFTS->ExecuteSQL($xSql);
	if ($bRet == False) {
		echo "<font color=red size=3><em>...Oops,  The query returned false</em></font>";
	}
	

echo <<<EOCAT
<p>
This process deleted all references to the Qwiki folder from the search database.
It also deleted references to all sub-folders since their parent folder path changed.
Qwiki folders are automatically recreated as users traverse Qwiki.
EOCAT;

	return True;
}

// ------------------------------------------
// ------------------------------------------
Function FTS_SearchForm ($aOptions) {
	Global $objFTS;
	Global $objQW;

	$FTS_SearchForm = False;
	$FTS_Criteria   = "";
	$xURL = $objQW->config['QWSERVERURL'];

	If (isset($_REQUEST['FTS_Criteria'])) $FTS_Criteria = $_REQUEST['FTS_Criteria'];
	
	echo <<<EOHERE
		<form id=FTS_SearchForm action="$xURL">
		<input type=hidden name='FTS_ENABLED' value='1'>
		<table cellpadding=1 cellspacing=0>
		<tr>
			<td align=right><font color=blue size=4><b>Criteria</b>:&nbsp;</font></td>
			<td align=left><input type=text style="font-size: 14" size=40 name="FTS_Criteria" value="{$FTS_Criteria}" autofocus></td>
			<td>&nbsp;&nbsp;&nbsp;</td>
			<td><input type=Submit value='Search'></td>
			<td>
				&nbsp;&nbsp
				<font color=blue><em>e.g.: "two words"  +Saved  test  ~Info*  -BadWord</em></font>
			</td>
		</tr>
	EOHERE;
	
	// Disabled for now
	$bFTSINSTANCES = False;
	If ($bFTSINSTANCES == True) {
		echo "<tr>";
		echo "<td align=right><font color=blue><b>Qwiki Instance</b>:</font></td>";
		echo "<td align=left>";
			$bRet = FTS_SearchInstances("");
		echo "</td>";
		echo "</tr>";
	}
	
	echo "</table>";
	echo "</form>";

	$FTS_SearchForm = True;
}

// ------------------------------------------
// Query FTS Database and return unique list of Qwiki instances
// ------------------------------------------

Function FTS_SearchInstances ($aOptions) {
	Global $objFTS;
	
	$FTS_SearchInstances = False;
	
	$xSql = "SELECT DISTINCT instance FROM searchfulltext WHERE Instance != '' ORDER BY instance;";
		
	// echo "<li><font size=2 color=maroon>" . $xSql . "</font></li><br>";
	
	$bRet = $objFTS->QueryDB($xSql);

	if ($bRet == False) {
		// echo "<font color=red size=3><em>...Oops,  The query did not return any results</em></font>";
		return;
	}

	mysqli_data_seek($objFTS->QueryResults,0);
	echo "<select name='FTS_INSTANCE'>\n";
	echo "<option value=''>All Instances</option>\n";
	while($row = mysqli_fetch_array($objFTS->QueryResults)) {
		$xInstance = $row["instance"];
		// echo "<li>...Instance:  $xInstance</li>";
		echo "<option value='" . $xInstance . "'>" . $xInstance . "</option>\n";
	}
	echo "</select>\n";
	
	$FTS_SearchInstances = True;
}



// ------------------------------------------
// Query FTS Database
// http://dev.mysql.com/doc/refman/5.5/en/fulltext-boolean.html
// http://www.hackingwithphp.com/9/3/18/advanced-text-searching-using-full-text-indexes
// ------------------------------------------

Function FTS_SearchExecute ($aOptions) {
	Global $objFTS;
	Global $objQW;
	
	$FTS_SearchExecute = False;
	
	$FTS_Criteria = "";
	If (isset($_REQUEST['FTS_Criteria'])) $FTS_Criteria = $_REQUEST['FTS_Criteria'];
	
	if ($FTS_Criteria == "") return;

	if (IsSet($_REQUEST['query'])) $FTS_Criteria = $_REQUEST['query'];
	
	// ------------------------
	// ------------------------
	$FTS_INSTANCE          = "";
	$FST_INSTANCE_CRITERIA = "";
	If (isset($_REQUEST['FTS_INSTANCE'])) {
		$FTS_INSTANCE = $_REQUEST['FTS_INSTANCE'];
		if ($FTS_INSTANCE != "") {
			$FST_INSTANCE_CRITERIA = " AND instance='" . $FTS_INSTANCE . "' ";
		}
	}
	
	// ------------------------
	// https://dev.mysql.com/doc/refman/8.0/en/fulltext-boolean.html
	// https://www.mysqltutorial.org/mysql-full-text-search/mysql-match-against/
	// ------------------------
	
	$xMode  = " IN BOOLEAN MODE ";

	// Default mode defined here for clarity
	//$xMode  = " IN NATURAL LANGUAGE MODE ";
		
	$xFields = "app,instance,server,dateupdated,url,title,left(body,512) AS body,id,pkey";
	$xSql = "SELECT $xFields, MATCH(title, body) AGAINST ('" . $FTS_Criteria . "'" . $xMode . ") AS Score FROM searchfulltext" .
		" WHERE MATCH(title, body) AGAINST('" . $FTS_Criteria . "'" . $xMode . ") " . $FST_INSTANCE_CRITERIA .
		" ORDER BY  Score DESC LIMIT 50;";
				
	echo "<li><font size=2 color=maroon>" . $xSql . "</font></li><br>";
		
	$bRet = $objFTS->QueryDB($xSql);

	if ($bRet == False) {
		echo "<font color=red size=3><em>...Oops,  The query did not return any results</em></font>";
		return;
	}

	echo <<<EOCAT
		<style>
		table.search_results {
			font-size: 13px;
			background-color: #FFFFee;
			border-spacing: 0px;
			border-style: solid;
			border-width: 2px;
			padding: 0px;
		}
		table.search_results td {
			vertical-align: top;
			border-spacing: 0px;
			border-style: solid;
			border-width: 1px;
			padding: 1px;
			word-wrap: break-word
		}
		table.search_results th {
			white-space: nowrap;
			border-style: solid;
			border-spacing: 0px;
			border-width: 1px;
			padding: 1px;
		}
		</style>
	EOCAT;



	# ------------------------------------------------------------
	# ------------------------------------------------------------
	echo "<table class=search_results>\n";

	echo "<tr bgcolor='FFFFAA'>" .
		"<th align=left>Appliction<br>Instance<br>Server</th>" .
		"<th align=left>Score<br>Last Updated</th>" .
		"<th>Text<br><em><small><font color=Maroon>(First 512 Characters)</font></small></em></th>" .
		"<th>Admin</th>" .
		"</tr>\n";
			
	mysqli_data_seek($objFTS->QueryResults,0);
	while($row = mysqli_fetch_array($objFTS->QueryResults)) {
		
		$xpkey = str_replace("\\","/",$row["pkey"]);
		$xpkey = str_replace("/-Content.asp","",$xpkey);
		$xapp  = str_replace("Qwiki","QWiki",$row["app"]);
		
		// ----------
		echo "<tr>";
		
		// ----------
		// ----------
		echo '<td style="white-space: nowrap;">' .
			$row["app"] .
			"<br>" . $row["instance"] .
			"<br>" . $row['server'] .
			"</td>\n";

		// ----------
		// ----------
		echo '<td style="white-space: nowrap;">' .
			sprintf("%.2f",$row["Score"]*100) .
			"<br>" . substr(strstr($row["dateupdated"]," "), 1);              // only print date
			"</td>\n";
		
		// ----------
		// Body
		// ----------
		echo "<td style=\"word-wrap: break-word\">";
		echo "<b><a href='" . $row["url"] . "'>" . $row["title"] . "</a></b>";
		echo '<br><font size=2>' . $row["body"] . '</font>';
		echo "</td>\n";
		

		// ----------
		// id   == MySQL DB uniq row number
		// pkey == Folder Path with trailing slash to content
		//
		// TODO:  Update with "admin" permissions authorized to delete and/or those who created page if possible
		// TODO:  Should be encrypted
		// TODO:  Should use JavaScript to "Ajax" submit request to prevent robots from deleting
		// ----------
		echo "<td>";
			echo "<a href='QW.php?FTS_ENABLED=1&cmdtype=Delete&id=" . URLEncode($row["id"]) . "&pkey=" . URLEncode($row["pkey"]) . "' title='Delete the reference to this record from the Search Full Text DB.  Select to delete references to folders that were manually deleted or moved to another folder. This process does NOT delete the file or folder, it only deletes references in the database.  These references are automatically recreated by Qwiki when users traverse them should they now reside in alternate locations.'>Delete</a>";
		echo "</td>\n";
		
		echo "<tr>\n";
	}
	echo "</table>\n";

	$FTS_SearchExecute = True;
}

// ------------------------------------------
// START OF MAIN
// ------------------------------------------

echo <<<EOCAT
<br>
<table width=800 bgcolor=eFFFFF border=1 cellpadding=2 cellspacing=0 style="font-size: 13px"><tr><td>
The <b>Qwiki Full Text Search</b> application updates it's database as users view Qwiki or edit pages.
When users manually install new files, modify or delete files and/or folders, or edit web pages the Qwiki search database 
will detect those changes after someone views that folder using the Qwiki web application.
A numerical <em>score</em> is calculated as the sorting key where higher matching results appear first.

<ul>
<li>See:  <a href='https://dev.mysql.com/doc/refman/5.5/en/fulltext-boolean.html'>Boolean Search Syntax</a> for advanced search syntax</li>
<li>Use a leading: <b>+</b>, character to require that the string apprears in each row <em>e.g.: +ReqThisString</em></li>
<li>Append a trailing: <b>*</b>, character to search for strings with <= 3 characters <em>e.g.:  MAS*</em></li>
<li><a href='https://dev.mysql.com/doc/refman/5.7/en/fulltext-stopwords.html'>MySQL MyISAM Stop Keywords</a> are excluded during indexing or when contained in search criteria</li>
</ul></td></tr></table>
EOCAT;

$xCmdtype = "";
If (IsSet($_REQUEST["cmdtype"])) $xCmdtype = $_REQUEST["cmdtype"];

// -------------
// -------------
if (strcmp($xCmdtype,"Delete")===0) {
	$bRet = FTS_SearchDelete("");
	exit(0);
}

// -------------
// -------------
echo "<br>";
$bRet = FTS_SearchForm("");

$bRet = FTS_SearchExecute("");

?>