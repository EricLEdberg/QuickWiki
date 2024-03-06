<?php

require_once("clsSearchFullText.php");

$objSFT          = new clsSearchFullText();
// $objSFT->MyDebug = True;


// ------------------------------------------
// ------------------------------------------
Function FTS_Header ($aOptions) {
	Global $objSFT;

	$FTS_Header = False;
	
	echo <<<EOCAT
		<table width='100%' height=40 cellpadding=1 cellspacing=0 border=0><tr>
		<!--- <td align=left  width=100><img src='/Qwiki/app/images/NokiaLogo.gif' height=25></td> -->
		<td align=center><font size=4><b>Qwiki Full Text Search<b></font></td>
		<td align=right><font size=3><a href='https://susiepc/Qwiki/EdbergQwiki'>EdbergNet Qwiki</font></td>
		</tr></table>
EOCAT;

	$FTS_Header = True;
}

// ------------------------------------------
// Delete references to: pkey, root and sub-folders in searchfulltext table
// If ID is not known then pass dummy value zero (0)
// ------------------------------------------
Function FTS_SearchDelete ($aOptions) {
	Global $objSFT;
	
	$xID   = $_REQUEST["id"];
	$xPKEY = $_REQUEST["pkey"];
	
	echo "<h1>Delete Cached Search Records</h1>";

	//echo "<li>...xID: $xID</li>";
	//echo "<li>...xPKEY: $xPKEY</li>";
	
	
	$objSFT->InitDB();

	// Delete specific row
	$xVal = str_replace("\\","\\\\\\\\",$xPKEY);
	$xSql = "DELETE FROM searchfulltext WHERE (id='" . $objSFT->MySQLEscape($xID) . "' AND id > 0) OR pkey='" . $xVal . "';";	
	echo "<li><font size=2 color=maroon>" . $xSql . "</font></li>";
	$bRet = $objSFT->ExecuteSQL($xSql);
	if ($bRet == False) {
		echo "<font color=red size=3><em>...Oops,  The query returned false</em></font>";
	}
	

	// Delete search references to all sub-folders of the one being deleted since their parent pathing does not exist anymore
	$xVal = str_replace("\\","\\\\\\\\",$xPKEY) . "%";
	$xSql = "DELETE FROM searchfulltext WHERE pkey LIKE '" . $xVal . "%';";
	echo "<li><font size=2 color=maroon>" . $xSql . "</font></li>";
	$bRet = $objSFT->ExecuteSQL($xSql);
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
	Global $objSFT;
	
	$FTS_SearchForm = False;
	$FTS_Criteria   = "";

	If (isset($_REQUEST['FTS_Criteria'])) $FTS_Criteria = $_REQUEST['FTS_Criteria'];
	
	echo <<<EOT
		<form id=FTS_SearchForm action="Search.php">
		<table cellpadding=1 cellspacing=0>
		<tr><td colspan=20 align=left><font size=4><b>Full Text Search Form</b></font></td></tr>
		<tr>
			<td align=right><font color=blue><b>Criteria</b>:</font></td>
			<td align=left><input type=text size=40 name="FTS_Criteria" value="{$FTS_Criteria}" autofocus></td>
			<td>&nbsp;&nbsp;&nbsp;</td>
			<td><input type=Submit value='Search'></td>
			<td>
				&nbsp;&nbsp
				<font color=blue><em>e.g.: +Saved test ~Info* -BadWord</em></font>
			</td>
		</tr>
EOT;
	
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
	Global $objSFT;
	
	$FTS_SearchInstances = False;
	
	$xSql = "SELECT DISTINCT instance FROM searchfulltext WHERE Instance != '' ORDER BY instance;";
		
	// echo "<li><font size=2 color=maroon>" . $xSql . "</font></li><br>";
	
	$bRet = $objSFT->QueryDB($xSql);

	if ($bRet == False) {
		// echo "<font color=red size=3><em>...Oops,  The query did not return any results</em></font>";
		return;
	}

	mysqli_data_seek($objSFT->QueryResults,0);
	echo "<select name='FTS_INSTANCE'>\n";
	echo "<option value=''>All Instances</option>\n";
	while($row = mysqli_fetch_array($objSFT->QueryResults)) {
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
	Global $objSFT;
	
	$FTS_SearchExecute = False;
	
	$FTS_Criteria = "";
	If (isset($_REQUEST['FTS_Criteria'])) $FTS_Criteria = $_REQUEST['FTS_Criteria'];
	
	if ($FTS_Criteria == "") return;

	// $FTS_Criteria = "+Saved test -Information";      # do not include word:  Information
	// $FTS_Criteria = "+Saved test ~Information -BadWord";      # penalize (rank lower) word:   Information
	// $FTS_Criteria = "+test Installation";

	if (IsSet($_REQUEST['query'])) $FTS_Criteria = $_REQUEST['query'];
	
	// -----------------------------------------------------------------
	// -----------------------------------------------------------------
	$FTS_INSTANCE          = "";
	$FST_INSTANCE_CRITERIA = "";
	If (isset($_REQUEST['FTS_INSTANCE'])) {
		$FTS_INSTANCE = $_REQUEST['FTS_INSTANCE'];
		if ($FTS_INSTANCE != "") {
			$FST_INSTANCE_CRITERIA = " AND instance='" . $FTS_INSTANCE . "' ";
		}
	}
	
	// -----------------------------------------------------------------
	// https://dev.mysql.com/doc/refman/8.0/en/fulltext-boolean.html
	// https://www.mysqltutorial.org/mysql-full-text-search/mysql-match-against/
	// -----------------------------------------------------------------
	
	$xMode  = " IN BOOLEAN MODE ";

	// Default mode defined here for clarity
	//$xMode  = " IN NATURAL LANGUAGE MODE ";
		
	$xSql = "SELECT *, MATCH(title, body) AGAINST ('" . $FTS_Criteria . "'" . $xMode . ") AS Score FROM searchfulltext WHERE MATCH(title, body) AGAINST('" . $FTS_Criteria . "'" . $xMode . ") " . $FST_INSTANCE_CRITERIA . " ORDER BY  Score DESC LIMIT 50;";
				
	echo "<li><font size=2 color=maroon>" . $xSql . "</font></li><br>";
		
	$bRet = $objSFT->QueryDB($xSql);

	if ($bRet == False) {
		echo "<font color=red size=3><em>...Oops,  The query did not return any results</em></font>";
		return;
	}
	
	echo <<<EOCAT
		<style>
		table {
			font-size: 13px
		}
		td {
			vertical-align:top;
		}
		</style>
EOCAT;



	# ------------------------------------------------------------
	# ------------------------------------------------------------
	echo "<table border=1 cellpadding=1 cellspacing=0 bgcolor='FFFFee'>\n";
	echo "<tr bgcolor='FFFFAA'>" .
		"<th align=left>Appliction<br>Instance</th>" .
		"<th align=left>Score<br>Last Updated</th>" .
		"<th>Folder Path</th>" .
		"<th>Text<br><em><small><font color=Maroon>(First 500 Characters)</font></small></em></th>" .
		"<th>Admin</th>" .
		"</tr>\n";
			
	mysqli_data_seek($objSFT->QueryResults,0);
	while($row = mysqli_fetch_array($objSFT->QueryResults)) {
		
		$xpkey = str_replace("\\","/",$row["pkey"]);
		$xpkey = str_replace("/-Content.asp","",$xpkey);
		$xapp  = str_replace("Qwiki","QWiki",$row["app"]);
		

		# ------------------------------------------------------------
		# ------------------------------------------------------------
		echo "<tr>";
		
		// echo '<td>&nbsp;' . $row["id"]          . '</td>';
		// echo '<td>&nbsp;' . $row["pkey"]        . '</td>';
		// echo '<td>&nbsp;' . $row["title"]       . '</td>';
		// echo '<td>&nbsp;' . $row["instance"]    . '</td>';
		// echo '<td>&nbsp;' . $row["server"]      . '</td>';
		// echo '<td>&nbsp;' . $row["url"]      . '</td>';

		// ---------------------------------------------------
		// ---------------------------------------------------
		echo '<td>' .
			$row["app"] .
			"<br>" . $row["instance"] .
			"</td>";

		// ---------------------------------------------------
		// ---------------------------------------------------
		echo '<td>' .
			sprintf("%.2f",$row["Score"]*100) .
			"<br>" . $row["dateupdated"] .
			"</td>";
	
		// ---------------------------------------------------
		// ---------------------------------------------------
		echo '<td>';
			echo "<a href='" . $row["url"] . "'>" . $row["title"] . "</a>";
		echo '</td>';
		
		// ---------------------------------------------------
		// Body
		// ---------------------------------------------------
		echo '<td >&nbsp;<font size=2>' . substr($row["body"], 0, 512)        . '</font></td>';
		

		// ---------------------------------------------------
		// id   == MySQL DB uniq row number
		// pkey == Folder Path with trailing slash to content
		//
		// TODO:  Update with "admin" permissions authorized to delete and/or those who created page if possible
		// TODO:  Should be encrypted
		// TODO:  Should use JavaScript to "Ajax" submit request to prevent robots from deleting
		// ---------------------------------------------------
		echo '<td>';
			echo "<a href='Search.php?cmdtype=Delete&id=" . URLEncode($row["id"]) . "&pkey=" . URLEncode($row["pkey"]) . "' title='Delete the reference to this record from the Search Full Text DB.  Select to delete references to folders that were manually deleted or moved to another folder. This process does NOT delete the file or folder, it only deletes references in the database.  These references are automatically recreated by Qwiki when users traverse them should they now reside in alternate locations.'>Delete</a>";
		echo '</td>';
		
		echo "<tr>\n";
	}
	echo "</table>\n";

	$FTS_SearchExecute = True;
}

// ------------------------------------------
// START OF MAIN
// ------------------------------------------

$bRet = FTS_Header("");

echo <<<EOCAT
<br>
<table width=800 bgcolor=eFFFFF border=1 cellpadding=2 cellspacing=0 style="font-size: 13px"><tr><td>
The <b>Qwiki Full Text Search</b> application updates it's database as users view Qwiki or edit pages.
When users manually install new files, modify or delete files and/or folders, or edit web pages the Qwiki search database 
will only detect those changes after someone views that folder using the Qwiki web application.
A numerical <em>score</em> is calculated as the sorting key where higher matching results appear first.

<ul>
<li>See:  <a href='https://dev.mysql.com/doc/refman/5.5/en/fulltext-boolean.html'>Boolean Search Syntax</a> for advanced search syntax</li>
<li>Use a leading: <b>+</b>, character to require that the string apprears in each row <em>e.g.: +ReqThisString</em></li>
<li>Append a trailing: <b>*</b>, character to search for strings with <= 3 characters <em>e.g.:  MAS*</em></li>
<li><a href='https://dev.mysql.com/doc/refman/5.7/en/fulltext-stopwords.html'>MySQL MyISAM Stop Keywords</a> are excluded during indexing or when contained in search criteria</li>
</ul></td></tr></table>
<br>
EOCAT;

$xCmdtype = "";
If (IsSet($_REQUEST["cmdtype"])) $xCmdtype = $_REQUEST["cmdtype"];

// ------------------------------------------------------
// ------------------------------------------------------
if (strcmp($xCmdtype,"Delete")===0) {
	$bRet = FTS_SearchDelete("");
	exit(0);
}

// ------------------------------------------------------
// ------------------------------------------------------
echo "<br>";
$bRet = FTS_SearchForm("");

$bRet = FTS_SearchExecute("");

?>