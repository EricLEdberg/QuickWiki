<?php

$ThisProgram           = "/Qwiki/QW/Editor.php";
$SubmitAction          = $ThisProgram . "?EditorSubmitForm=TRUE";
$MyDebug               = FALSE;

// --------------------------------------------------------------------
// --------------------------------------------------------------------
if (is_object($objQW)) {
	$EditorFile                = $config['rootPath'] . $config['Options']['EditorFile'];
} else {
	$EditorFile                = $_REQUEST["EditorFile"];
}

$EditorMode                = $_REQUEST["EditorMode"];           // Values: Insert, Append, Replace
$EditorText                = $_REQUEST["EditorText"];           // Typically passed in editor form
$EditorSubmitForm          = $_REQUEST["EditorSubmitForm"];     // non-empty when submitting information
$EditorHeight              = $_REQUEST["EditorHeight"];
$EditorWidth               = $_REQUEST["EditorWidth"];

if ($EditorHeight          = "") $EditorHeight = 800;
if ($EditorWidth           = "") $EditorWidth  = 1000;

// ----------------------------------------------------------
// Update file being edited
// ----------------------------------------------------------
if (isset($_REQUEST["EditorSubmitForm"])) {
	if (!isset($_REQUEST['EditorText'])) {
		echo "ERROR:  EditorText was not provided, Get Help";
		exit;
	}
	$EditorText = $_REQUEST['EditorText'];
	$EditorFile = $_REQUEST['EditorFile'];

	// echo "<li>Updating File:  $EditorFile</li>";
	if (!file_put_contents($EditorFile, $EditorText)) {
		echo "ERROR:  Updating QW contents failed";
		exit;
	}

	echo <<<EOWHERE
	<script>
	window.onunload = refreshParent;
	function refreshParent() {
		window.opener.location.reload();
	}
	window.close();
	</script>
	EOWHERE;

	exit;
}

// ----------------------------------------------------------
// ----------------------------------------------------------
$EditorText = "<em>Input updated text here</em>";

if ( strcmp($EditorFile,"")!=0 && strcmp($EditorSubmitForm,"")==0 ) {
	if ($MyDebug) echo "<li>EditorFile(" . $EditorFile . ")</li>";
	
	// rename files from .asp to .php (and hope they don't contain any vb code...)
	$xOldFileName = str_replace(".php",".asp",$EditorFile);
	if (file_exists($xOldFileName)) {
		if (!rename($xOldFileName,$EditorFile)) {
			echo "ERROR: failed to rename file from .asp to .php, Get Help";
			exit;
		}
	}

	if (file_exists($EditorFile)) {
		$EditorText = file_get_contents($EditorFile);
	}
}

// ----------------------------------------------------------
// Update file
// ----------------------------------------------------------
Function SubmitEditorForm() {
    $EditorFile = $_REQUEST["EditorFile"];
	$EditorText = $_REQUEST["EditorText"];
	$bRet = FILEIO_WriteFile($xMode, $EditorFile, $EditorText);
	exit;
};


// ------------------------------------------------------------
// ------------------------------------------------------------
echo "<script src=\"ckeditor_4510/ckeditor.js\"></script>";

// ------------------------------------------------------------
// ------------------------------------------------------------
echo "<form name=Editor method=POST action='" . $SubmitAction . "'>";
echo "<input type=hidden name='EditorSubmitForm'      value=TRUE>";
echo "<input type=hidden name='EditorMode'            value='" . $EditorMode . "'>";
echo "<input type=hidden name='EditorFile'            value='" . $EditorFile . "'>";

// ------------------------------------------------------------
// ------------------------------------------------------------
echo "<table>";
echo "<tr><td colspan=20>&nbsp;</td></tr>";
echo "<tr>";
echo "<td align=center><input type=submit value='Submit Edit'></td>";
echo "<td aligh=center><input type=submit value='Cancel' onclick=\"window.close();\"></td>";
//echo "<td aligh=center>File: " . $config['Options']['EditorFile'] . "</td>";
echo "<td align=center><font color=Maroon><b>Qwiki Editor</b></font></td>";
echo "</tr>";

echo "<tr><td colspan=20>";
	 echo "<br><textarea class=\"ckeditor\" name=\"EditorText\">" . $EditorText . "</textarea>";
echo "</td></tr>";
echo "</table>";

echo "</form>";

// ------------------------------------------------------------
// CKEditor 4 run-time options
// See:  http://docs.ckeditor.com/#!/guide/dev_configuration
// ------------------------------------------------------------
?>

<script>

(function() {
	'use strict';

	CKEDITOR.config.extraPlugins = 'toolbar';

	CKEDITOR.on( 'instanceReady', function( evt ) {
		var editor = evt.editor,
			editorCurrent = editor.name == 'editorCurrent',
			defaultToolbar = !( editor.config.toolbar || editor.config.toolbarGroups ),
			pre = CKEDITOR.document.getById( editor.name + 'Cfg' ),
			output = '';

		if ( editorCurrent ) {
			// If default toolbar configuration has been modified, show "current toolbar" section.
			if ( !defaultToolbar )
				CKEDITOR.document.getById( 'currentToolbar' ).show();
			else
				return;
		}

		// Toolbar isn't set explicitly, so it was created automatically from toolbarGroups.
		if ( !editor.config.toolbar ) {
			output +=
				'// Toolbar configuration generated automatically by the editor based on config.toolbarGroups.\n' +
				dumpToolbarConfiguration( editor ) +
				'\n\n' +
				'// Toolbar groups configuration.\n' +
				dumpToolbarConfiguration( editor, true )
		}
		// Toolbar groups doesn't count in this case - print only toolbar.
		else {
			output += '// Toolbar configuration.\n' +
				dumpToolbarConfiguration( editor );
		}

		// Recreate to avoid old IE from loosing whitespaces on filling <pre> content.
		var preOutput = pre.getOuterHtml().replace( /(?=<\/)/, output );
		CKEDITOR.dom.element.createFromHtml( preOutput ).replace( pre );
	} );

	// These option verified to work
	CKEDITOR.config.uiColor = //ffe066';
	CKEDITOR.config.removeButtons = 'Scayt,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField';
	CKEDITOR.config.height = '450';
		
	function dumpToolbarConfiguration( editor, printGroups ) {
		var output = [],
			toolbar = editor.toolbar;

		for ( var i = 0; i < toolbar.length; ++i ) {
			var group = dumpToolbarGroup( toolbar[ i ], printGroups );
			if ( group )
				output.push( group );
		}

		return 'config.toolbar' + ( printGroups ? 'Groups' : '' ) + ' = [\n\t' + output.join( ',\n\t' ) + '\n];';
	}

	function dumpToolbarGroup( group, printGroups ) {
		var output = [];

		if ( typeof group == 'string' )
			return '\'' + group + '\'';
		if ( CKEDITOR.tools.isArray( group ) )
			return dumpToolbarItems( group );
		// Skip group when printing entire toolbar configuration and there are no items in this group.
		if ( !printGroups && !group.items )
			return;

		if ( group.name )
			output.push( 'name: \'' + group.name + '\'' );

		if ( group.groups )
			output.push( 'groups: ' + dumpToolbarItems( group.groups ) );

		if ( !printGroups )
			output.push( 'items: ' + dumpToolbarItems( group.items ) );

		return '{ ' + output.join( ', ' ) + ' }';
	}

	function dumpToolbarItems( items ) {
		if ( typeof items == 'string' )
			return '\'' + items + '\'';
		return '[ \'' + items.join( '\', \'' ) + '\' ]';
	}

})();
	</script>