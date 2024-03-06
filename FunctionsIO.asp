<!--#INCLUDE VIRTUAL = "/QWiki/APP/Include/clsContent.asp"-->

<%

'# --------------------------------------------------------
'# This module contains the primary functions that display content in folders as a web page
'# Eric L. Edberg
'# --------------------------------------------------------

Dim objFSO, objFolder, objFiles
Set objFSO = Nothing

'# ------------------------------------------------
'# ------------------------------------------------
Dim FILEIO_FOLDERS_MODE, FILEIO_FOLDERS_TABULAR, FILEIO_FOLDERS_LIST
FILEIO_FOLDERS_LIST    = "1"
FILEIO_FOLDERS_TABULAR = "2"
FILEIO_FOLDER_MODE     = FILEIO_FOLDERS_LIST

FILEIO_FOLDER_MODE = Session ("FILEIO_FOLDER_MODE")
If FILEIO_FOLDER_MODE = "" OR IsNull(FILEIO_FOLDER_MODE) Then FILEIO_FOLDER_MODE = FILEIO_FOLDERS_LIST
FIOFM = Request("FIOFM")
If FIOFM <> "" Then FILEIO_FOLDER_MODE = FIOFM
Session ("FILEIO_FOLDER_MODE") = FILEIO_FOLDER_MODE

'# ------------------------------------------------
'# ------------------------------------------------
'# Specify the TOP-level 
FILEIO_NODE_ENABLED = FALSE
'# Note sure if we need these yet.
FILEIO_NODE_PATH    = SESSION("FILEIO_NODE_PATH")
FILEIO_NODE_URL     = SESSION("FILEIO_NODE_URL")

'# Show MANAGE URL next to each FILE
Dim FILEIO_ADMIN_ENABLED
If FILEIO_ADMIN_ENABLED = "" OR IsNull(FILEIO_ADMIN_ENABLED) Then FILEIO_ADMIN_ENABLED = TRUE

Dim ARCHIVE_DELETE_ORIGINAL
ARCHIVE_DELETE_ORIGINAL = REQUEST("ARCHIVE_DELETE_ORIGINAL")


'# ------------------------------------------------
'# ------------------------------------------------
Class clsFileIO
	Public  MyDebug
	
	Public dicNode

	'# If IsNull(dicCheckpoint) Then Set dicCheckpoint = CreateObject("Scripting.Dictionary")

	'# -----------------------------------------
	'# -----------------------------------------
	Private Sub Class_Initialize()
		MyDebug        = False
		dicNode        = Null
	End Sub
	
	'# -----------------------------------------
	'# -----------------------------------------
	Private Sub Class_Terminate()
		If MyDebug = TRUE Then Response.Write "<li>clsTimer.Terminate() doing bLogResults("&bLogResults&")</li>"
		Set dicNode = Nothing
	End Sub

End Class

'# ------------------------------------------------
'# ------------------------------------------------
Class clsFileNode
	Public FileName
	Public DirectoryName
	Public FileSize
End Class


'# ------------------------------------------------------------
'# Placeholder for application specific callback.  See FunctionsIO.asp for initialized default placeholder.
'# Function is (should be) overrridden by application to perform custom tasks
'# Only supports some functions below.
'# 8/7/2014 - ele
'# ------------------------------------------------------------
Function FILEIO_CALLBACK(xOption,oFolder,oFile)
	Select Case xOption
		Case "Test"
			Response.Write "FILEIO_CALLBACK(Test) oFile.Name(" & oFile.Name & ")"
	End Select
End Function

'# ------------------------------------------------
'# ------------------------------------------------
Function FILEIO_GetFileName(xSCRIPT_NAME, ByRef xFileName, xOptions)
	Dim arPath
	xFileName = ""
	If IsNull(xSCRIPT_NAME) Or xSCRIPT_NAME = "" Then xSCRIPT_NAME = Request.ServerVariables("SCRIPT_NAME")
	arPath = Split(xSCRIPT_NAME, "/")
	'# last item in array contains file name
	xFileName = arPath(UBound(arPath,1))
	If IsArray(arPath) Then Erase arPath
End Function
	
'# ------------------------------------------------
'# Determine PATH with a trailing "\" characater
'# D:\APP\PPM\file.ppt  --> D:\APP\PPM\
'# ------------------------------------------------
Function FILEIO_GetFilePath(xSCRIPT_PATH_TRANSLATED, ByRef xPath, xOptions)
	Dim arPath
	
	FILEIO_GetFilePath = FALSE
	xPath = ""

	If MyDebug = True Then 
		Response.Write "<li><font color=red>...FILEIO_GetFilePath(): xSCRIPT_PATH_TRANSLATED: " & xSCRIPT_PATH_TRANSLATED & ", xPath: " & xPath & "</font></li>"
	End If

	If IsNull(xSCRIPT_PATH_TRANSLATED) Or xSCRIPT_PATH_TRANSLATED = "" Then xSCRIPT_PATH_TRANSLATED = Request.ServerVariables("SCRIPT_PATH_TRANSLATED")
	arPath = Split(xSCRIPT_PATH_TRANSLATED, "\")
	arPath(UBound(arPath,1)) = ""        '# Set file name to blank (leaves the trailing \ character)
	xPath = Join(arPath, "\")       
	FILEIO_GetFilePath = True
	If IsArray(arPath) Then Erase arPath
End Function
	
'# ------------------------------------------------
'# ------------------------------------------------
Function FILEIO_OpenFS (ByRef oFS)
	FILEIO_OpenFS = FALSE
	'If oFS = Nothing Then
		Set oFS = Server.CreateObject("Scripting.FileSystemObject")
	'End If
	FILEIO_OpenFS = TRUE
End Function

Function FILEIO_DisplayPageOptions(ByRef xMode, ByRef xFolderPath, xFolderURL, xOptions)

	Dim aPATH, xURL
	FILEIO_DisplayPageOptions = False
	FILEIO_TH                 = True

	'# ----------------------------------------------------------------
	'# Get visual path to Qwiki folder
	'# ----------------------------------------------------------------
	xQwikiVisualPathToFolder = FILEIO_DisplayQwikiPath(SERVER_URL, xFolderURL, "")
	
	'# ----------------------------------------------------------------
	'# ----------------------------------------------------------------
	Response.Write vbCrLf & "<div class=AppIconHeader>" & vbCrLf
	Response.Write "<table class=vs><tr>" & vbCrLf

	If APPLICATION_DIRECTORY_PATH <> xFolderPath Then Response.Write "<td valign=top align=center><a href='" & SESSION("FILEIO_NODE_URL") & "' title='Qwiki Home'><img src='" & APPLICATION_URL_PATH & "/images/Icon_FolderHome.png' border=0 height=30><br><span class=comment>Home</span></a></td>" & vbCrLf

	Response.Write "<td valign=top align=center><a href='#' onclick='location.reload(); return false;'  title='Reload Page'><img src='" & APPLICATION_URL_PATH & "/images/Icon_FolderRefresh.png' border=0 height=30><br><span class=comment>Refresh</span></a></td>" & vbCrLf
	
	
	If APPLICATION_SA = TRUE Then
		If APPLICATION_DIRECTORY_PATH <> xFolderPath Then Response.Write "<td valign=top align=center nospan><a href='" & APPLICATION_URL_PATH & "/bin/FileIO.asp?CreateDirectory=" & xFolderPath & "' target='_blank' title='Create Directory'><img src='" & APPLICATION_URL_PATH & "/images/Icon_FolderAdd.png' border=0 height=30><br><span class=comment>NewDir</span></a></td>" & vbCrLf
		
		If APPLICATION_DIRECTORY_PATH <> xFolderPath Then 
			Response.Write "<td valign=top align=center><a href='" & APPLICATION_URL_PATH & "/bin/Upload.asp?CreateDirectory=" & xFolderPath & "' target='_blank' title='Upload (replace) File In Current Working Directory'><img src='" & APPLICATION_URL_PATH & "/images/Icon_FolderUpload.png' border=0 height=30><br><span class=comment>Upload</span></a></td>" & vbCrLf
		End If

		'# ----------------------------------------------------------------------
		'# CKEditor v4 2016-09-07 ele
		'# TODO:  xFolderPath exposes pysical location of file.  Options should be encrypted.
		'# TODO:  FST feature should be enabled/disabled using option flag
		'# ----------------------------------------------------------------------
		
		'# ----------------------------------------------------------------------
		'# These options are required by Full Text Search to link the page being edited to the text inserted into the database
		'# Full text search maintains a database of page text
		'# Options should be managed by FTS object method, not hard coded here
		'# ----------------------------------------------------------------------
		FTS_SERVER   = SERVER_NAME
		FTS_APP      = "Qwiki"
		FTS_INSTANCE = APPLICATION_QWIKI_DIR
		FTS_URL      = SERVER_URL_URI
		FTS_TITLE    = xQwikiVisualPathToFolder
		xFST         = "&FTS_SERVER=" & Server.URLEncode(SERVER_NAME) & "&FTS_INSTANCE=" & Server.URLEncode(APPLICATION_QWIKI_DIR) & "&FTS_APP=" & Server.URLEncode(FTS_APP) & "&FTS_URL=" & Server.URLEncode(FTS_URL) & "&FTS_TITLE=" & Server.URLEncode(FTS_TITLE)

		xURL = APPLICATION_URL_PATH & "/bin/Editor.asp?EditorFile=" & xFolderPath & "-Content.asp" & xFST
		xURL = Replace(xURL,"\","\\")
		
		Response.Write "<td valign=top align=center><a onClick=""window.open('" & xURL & "');"" title='Edit Qwiki Content'><img src='" & APPLICATION_URL_PATH & "/images/Icon_EditText2.png' border=0 height=30><br><span class=comment>Edit</span></a></td>" & vbCrLf	

	End If

	'# ----------------------------------------------------------------------
	'# ----------------------------------------------------------------------
	If xFolderURL <> SESSION("FILEIO_NODE_URL") Then
		aPATH                  = Split(xFolderURL, "/")
		aPATH(UBound(aPATH,1)) = ""        '# Set last item in array to blank
		xURL                   = Join(aPATH, "/")
		xURL                   = Left(xURL,Len(xURL)-1)
		If MyDebug = TRUE Then Response.Write "<li class=dbg>xURL("&xURL&")</li>"
		xDLST_URL              = Replace(xURL,SERVER_URL,"")
		If MyDebug = TRUE Then Response.Write "<li class=dbg>xDLST_URL("&xDLST_URL&")</li>"
		Erase aPATH
		If APPLICATION_DIRECTORY_PATH <> xFolderPath Then Response.Write "<td valign=top align=center>&nbsp;<a href='" & SESSION("FILEIO_NODE_URL") & "/Index.asp?DLST_DIR=" & xDLST_URL & "' title='Up 1 Directory'><img src='" & APPLICATION_URL_PATH & "/images/Icon_FolderUp2.png' border=0 height=27><br><span class=comment>UpDir</span></a></td>" & vbCrLf
	End If
	
	'# ----------------------------------------------------------------
	'# SEARCH button
	'# ----------------------------------------------------------------
	If APPLICATION_SEARCH_ENABLED = True Then
		Response.Write "<td valign=top align=center><a href='" & APPLICATION_SEARCH_URL & "'><img src='" & SERVER_URL & APPLICATION_URL_RELATIVE & "/Images/Icon_FolderSearch.jpg' border=0 height=30 title='Search Qwiki Content'><br><span class=comment>Search</span></a></td>" & vbCrLf
	End If

	'# ----------------------------------------------------------------
	'# BACK button
	'# http://www.hunlock.com/blogs/Mastering_The_Back_Button_With_Javascript
	'# http://www.pageresource.com/jscript/jhist.htm
	'# ----------------------------------------------------------------
	Response.Write "<td valign=top align=center><a href='#'><img src='" & SERVER_URL & APPLICATION_URL_RELATIVE& "/Images/Icon_GreenCircleLeftArrow.png' border=0 height=30 title='Back to previous page' onclick=""javascript: history.back(); return false""><br><span class=comment>Back</span></a></td>" & vbCrLf
	
	'# ----------------------------------------------------------------
	'# Qwiki path to folder
	'# e.g.:  QWiki -> LBS -> CoreOS -> CoreOS Debugging Tools
	'# ISSUE:  does not support POSTING form data to page if required :-(
	'# ----------------------------------------------------------------
	Response.Write "<td width='90%' align=middle>"
		If APPLICATION_SECURITY_ENABLED = TRUE Then Response.Write "<img src='" & SERVER_URL & APPLICATION_URL_RELATIVE & "/Images/Icon_Key.gif' width=23 border=0 align=""absmiddle"" title=""Directory and contents are restricted to authorized users. Contact application SA for assistance"">&nbsp;&nbsp;"

		'# ----------------------------------------------------------------
		'# Get visual path to Qwiki folder
		'# ----------------------------------------------------------------
		xQwikiVisualPathToFolder = FILEIO_DisplayQwikiPath(SERVER_URL, xFolderURL, "")
		
		Response.Write vbCrLf & "<font class=lg><b><i><a href='" & SERVER_URL_URI & "' style=""color: Blue;"" >" & xQwikiVisualPathToFolder & "</a></i></b></font>" & vbCrLf
	Response.Write "</td>"

	Response.Write "</tr></table>" & vbCrLf
	Response.write "</div>" & vbCrLf

	FILEIO_DisplayPageOptions = TRUE
End Function

'# ----------------------------------------------------------------
'# Return visual path to Qwiki folder
'# e.g.:    QWiki -> LBS -> CoreOS -> CoreOS Debugging Tools
'# ISSUE:  does not support POSTING form data to page if required :-(
'# ----------------------------------------------------------------
Function FILEIO_DisplayQwikiPath(xSERVER_URL, xFolderURL, xOptions)
	Dim xPath
	
	xPath  = Replace(xFolderURL,SERVER_URL&"/","")
	xPath  = Replace(xPath,"/"," -> ")

	FILEIO_DisplayQwikiPath = xPath
End Function

'# ----------------------------------------------------------------
'# ISSUE:  don't have enough information to pass the search full text "pkey" to Search.php?cmdtype=delete
'# Call Search.php in background to delete reference to pkey in searchfulltext database
'# Pass id=0 since we don't know the row id number
'# TODO:  This should be call to clsSearchFullText.php specifing all required options:  server, app, instanace
'# ----------------------------------------------------------------
Function FILEIO_SearchFullText_Delete(xMode, xPkey, xOptions)
	'# Example URL from Search.php
	'# echo "<a href='Search.php?cmdtype=Delete&id=" . URLEncode($row["id"]) . "&pkey=" . URLEncode($row["pkey"]) . 
	xURL = "Search.php?cmdtype=Delete&id=0&pkey=" & Server.URLEncode(xPkey)
	'Response.Write "<li>xURL:  " & xURL & "</li>"
	
	'bRet = gfServerXMLHTTP("GET", xURL, xResult, objXML, "5", "", xRetMsg)

End Function

'# ----------------------------------------------------------------
'# ----------------------------------------------------------------
Function FILEIO_DisplayFolder(xMode, xFolderPath, xFolderURL, xOptions)
	Dim xDir, objFSO, objFolder, objSubFolders, objFiles
	
	bRet              = FILEIO_GetFilePath(xFolderPath, xDir, xOptions)
	bRet              = FILEIO_OpenFS(objFSO)
	
	'Response.Write "<li>xFolderPath:  " & xFolderPath & "</li>"
	'Response.Write "<li>xDir:  " & xDir & "</li>"
	'Response.Write "<li>bRet:  " & bRet & "</li>"
	'Response.Write "<li>HTTP_REFERER:  " & request.ServerVariables("HTTP_REFERER") & "</li>"
	
	If (Not objFSO.FolderExists(xDir)) Then
		Response.write "<br><br><font color=Red class=xl><b>ERROR:&nbsp;&nbsp;&nbsp;The Qwiki folder: " & xDir & " does not exist!</b>.<br><br><ul><li>This is normal if someone manually deleted the folder outside of Qwiki using a file manager</li>"
		
		If InStr(request.ServerVariables("HTTP_REFERER"), "QWiki/APP/bin/Search.php") Then
			Response.Write "<br><li>You came to this page using Qwiki Search...<br><br><u>Please hit the <a href='javascript:history.back()'>BACK</a> key and manually DELETE the stale folder reference</u></li>"
		End If
		
		'Response.Write "<li>Stale references to this folder in the Qwiki search database were removed <em>(if they existed)</em></li></font></ul>"
		Response.Write "</font></ul>"
		
		'# Need to specify either searchfulltext.id or searchfulltext.pkey (preferred)
		' bRet = FILEIO_SearchFullText_Delete("", xDir, xOptions)
		
		FILEIO_DisplayFolder = False
		Exit Function
	End If


	Set objFolder     = objFSO.GetFolder(xDir)
	Set objSubFolders = objFolder.SubFolders
	Set objFiles      = objFolder.Files
	
	'# --------------------------------------------
	'# List sub-folders in CWD
	'# --------------------------------------------
	If objSubFolders.Count > 0 Then
		Response.Write "<br><span class=XL><B>Folders</B></span>" & vbCrLf

		'FILEIO_FOLDERS_LIST    = 1
		'FILEIO_FOLDERS_TABULAR = 2
		'FILEIO_FOLDER_MODE     = FILEIO_FOLDERS_LIST
		
		xURL = ""
		bRet = gfSaveProgramOptions(xURL)
		
		'# remove existing QueryString option from current URL
		xURL = Replace(xURL,"FIOFM=1","")
		xURL = Replace(xURL,"FIOFM=2","")
		xURL = Replace(xURL,"&&","&")

		xURL = SERVER_URL & SCRIPT_NAME & "?" & xURL
				
		If FILEIO_FOLDER_MODE = FILEIO_FOLDERS_TABULAR Then
			xURL = xURL & "&FIOFM=" & FILEIO_FOLDERS_LIST
			xURL = Replace(xURL,"&&","&")
			xURL = Replace(xURL,"?&","?")
				
			Response.Write "<input type=button class=tiny onClick=""parent.location='" & xURL & "'"" value=""View as List"" style=""padding:0px; border-width:1px"">"

			bRet = FILEIO_ListSubFolders_Tabular ("", xDir, xFolderURL, xOptions)
		Else
			xURL = xURL & "&FIOFM=" & FILEIO_FOLDERS_TABULAR
			xURL = Replace(xURL,"&&","&")
			xURL = Replace(xURL,"?&","?")
			
			Response.Write "<input type=button class=tiny onClick=""parent.location='" & xURL & "'"" value=""View as Table"" style=""padding:0px; border-width:1px"">"
			
			bRet = FILEIO_ListSubFolders ("", xDir, xFolderURL, xOptions)
		End If

		Response.Write "<br>"
	End If
	
	'# --------------------------------------------
	'# List Files in the folder
	'# Exclude specific files
	'# --------------------------------------------
	xCnt = 0
	For Each objFile in objFolder.Files
		xFile = LCase(objFile.Name)
		If InStr(xFile,"index.htm") > 0 Then
			xCnt = xCnt + 1
		ElseIf IsNull(xFile) OR xFile = "" Then
			'#
		ElseIf InStr(xFile,"xxx.exe") > 0   Then
			'#
		ElseIf InStr(xFile,".asp") > 0   Then
			'#
		ElseIf InStr(xFile,".htm") > 0   Then
			'#
		ElseIf InStr(xFile,".php") > 0   Then
			'#
		ElseIf InStr(xFile,".pl") > 0   Then
			'#
		ElseIf InStr(xFile,"synctoy") > 0   Then
			'#
		ElseIf InStr(xFile,"thumbs.db") > 0   Then
			'#
		ElseIf Left(xFile,1) = "-"       Then
			'#
		ElseIf Left(xFile,1) = "."       Then
			'#
		ElseIf InStr(xFile,"web.config") > 0   Then
			'#
		ElseIf InStr(xFile,"sync.ffs_lock") > 0   Then
			'#
		ElseIf Left(xFile,1) = "~"       Then
			'#
		Else
			xCnt = xCnt + 1
		End If
	Next
	
	If xCnt > 0 Then
		Response.Write "<span class=xl><B>Content</B></span><br>"
		Response.Write "<table id=""APP_FILEIO_FILES"" class=""tablesorter"" border=1 cellpadding=4 cellspacing=0>" & vbCrLf
		Response.WRite "<thead><tr bgcolor=""#F2FFFF""><th>File</th><th>Modified</th><th>Size</th>"
		If FILEIO_ADMIN_ENABLED = TRUE Then Response.Write "<th>Admin</th>"
		Response.Write "</tr></thead>" & vbCrLf & "<tbody>"
		bRet = FILEIO_ListFiles ("Tabular", xDir, xFolderURL, objFSO, objFolder, xOptions)
		Response.Write "</tbody></table>" & vbCrLf
	End If
	
	Set objFiles      = Nothing
	Set objSubFolders = Nothing
	Set objFolder     = Nothing
	Set objFSO        = Nothing

End Function


'# ------------------------------------------------
'# xMode:  to-be-defined
'# xDir:   must be FQ path e.g.: "D:\ProjectWeb\cpdr\documents"
'# ------------------------------------------------
Function FILEIO_ListSubFolders_Tabular (xMode, xPath, xFolderURL, xOptions)
	Dim xFolderCnt, objSubFolder
	FILEIO_ListSubFolders_Tabular = False
	bRet                          = FILEIO_OpenFS(objFSO)
	
	Set objFolder = objFSO.GetFolder(xPath)
	
	Response.Write "<table id=""APP_FILEIO_DIRECTORIES"" class=""tablesorter"" border=1 cellpadding=4 cellspacing=0>" & vbCrLf
	Response.WRite "<thead><tr bgcolor=""#F2FFFF""><th>Directory</th><th>Modified</th><th>Size KB</th></tr></thead>" & vbCrLf
	Response.Write "<tbody>" & vbCrLf
	
	xFolderCnt = 0
	For Each objSubFolder in objFolder.SubFolders
		bRet = FILEIO_ListSubFolders_Callback(xMode, xPath, xFolderURL, objFSO, objFolder, objSubFolder)
		xFolderCnt = xFolderCnt + 1
	Next
	
	Response.Write "</tbody></table>"
	FILEIO_ListSubFolders_Tabular = TRUE
	Set objFS0                    = Nothing

End Function

'# ------------------------------------------------
'# xMode:  to-be-defined
'# xDir:   must be FQ path e.g.: "D:\ProjectWeb\cpdr\documents"
'# ------------------------------------------------
Function FILEIO_ListSubFolders (xMode, xPath, xFolderURL, xOptions)
	Dim xFolderCnt, objSubFolder
	FILEIO_ListSubFolders = False
	bRet                  = FILEIO_OpenFS(objFSO)
	
	'If Session("EmployeeCSL") = "ele" Then MyDebug = True
	
	Set objFolder = objFSO.GetFolder(xPath)
	
	'# How many folders listed in each column?
	'# Only show 5 columns due to width issues
	xMaxFolderCnt         = Int(gfParseOption("","=","Rows",xOptions))
	If Not IsNumeric(xMaxFolderCnt) Then xMaxFolderCnt = 5
	xMaxFolderCnt         = Int(objFolder.SubFolders.Count/5)

	Response.Write "<table cellpadding=3><tr><td valign=top>"
	Response.wRite "<ul>"
	xFolderCnt = 0
	For Each objSubFolder in objFolder.SubFolders
		
		xName = LCase(objSubFolder.Name)
		If MyDebug = True Then Response.Write "<li>objSubFolder.Name: (" & xName & ")</li>"
	
		'# Prevent permission denied errors when listing P:/ PUB file system - ELE
		If InStr(xName,"$recycle.bin") > 0 Then
			xCnt = xCnt + 1
		ElseIf IsNull(xName) OR xName = "" Then
			'#
		ElseIf InStr(xName,"system volume information") > 0   Then
			'#
		Else
			bRet = FILEIO_ListSubFolders_Callback(xMode, xPath, xFolderURL, objFSO, objFolder, objSubFolder)
			
			If xFolderCnt >= xMaxFolderCnt Then
				If xMaxFolderCnt <> -1 Then Response.Write "</ul></td><td width=10>&nbsp;</td><td valign=top><ul>"
				xFolderCnt = 0
			Else
				xFolderCnt = xFolderCnt + 1
			End If
		End If
	Next
	Response.Write "</ul>"
	Response.Write "</td></tr></table>"
	
	FILEIO_ListSubFolders = TRUE
	Set objFS0            = Nothing

End Function

'# ----------------------------------------------------------------
'# Default callback to display SubFolders in CWD
'# Function may be overridden by application to customize behaviour
'# ----------------------------------------------------------------
Function FILEIO_ListSubFolders_Callback(xMode, xPath, xFolderURL, objFSO, objFolder, objSubFolder)
	Dim xSubFolder, xDLST_URL, xSubFolderCount

	FILEIO_ListSubFolders_Callback = FALSE
	xSubFolder      = LCase(objSubFolder.Name)
	xFolderSize     = objSubFolder.Size / 1000000   '# in MB
	
	If IsNull(xSubFolder) OR xSubFolder = "" Then Exit Function
	'# Windows does not allow a "." as first character so we use "-" to mimic UNIX behaviour
	If Left(xSubFolder,1) = "-"              Then Exit Function
	If Left(xSubFolder,1) = "."              Then Exit Function
	
	xDLST_URL = Replace(xFolderURL,SERVER_URL,"") & "/" & objSubFolder.Name	
	xURL      = SESSION("FILEIO_NODE_URL") & "/Index.asp?DLST_DIR=" & Server.URLEncode(xDLST_URL)

	'# ----------------------------------------------------------------
	'# Track what Folders exist in DLST_DIR
	'# Used by Include/clsContent.asp -> Include/clsSearchFullText.php
	'# Do not log DRIVE: as this may change
	'# path example: /QWiki/FLWPC/ALU AMI/access ami web with end user login.docx
	'# - Is this the best way to log folders?
	'# ----------------------------------------------------------------
	If APPLICATION_SEARCH_ENABLED = True Then
		xKey   = objSubFolder.Name
		xValue = objSubFolder.Name
		bRet   = objFTS.DictionaryAdmin("Folder", "Add", xKey, xValue, "")
		If MyDebug = TRUE Then Response.Write "<li class=tiny> objSubFolder.Name: <strong>" & objSubFolder.Name & "</strong></li>"
	End If

	'# --------------------------------------------------------
	'# --------------------------------------------------------
	If FILEIO_FOLDER_MODE = FILEIO_FOLDERS_TABULAR Then
		
		'xFolderSize     = objSubFolder.Size / 1000000   '# in MB
		xFolderSize     = objSubFolder.Size / 1000      '# in KB
		
		Response.Write "<tr><td><a href='" & xURL & "'>" & objSubFolder.Name & "</a></td><td width=70>" & FormatDateTime(objSubFolder.DateLastModified,vbShortDate) & "</td><td width=70>" & xFolderSize & "</td></tr>" & vbCrLf

	ElseIf FILEIO_NODE_ENABLED = TRUE Then
		xDLST_URL = Replace(xFolderURL,SERVER_URL,"") & "/" & objSubFolder.Name	
		
		Response.Write "<li class=md><B><a href='" & SESSION("FILEIO_NODE_URL") & "/Index.asp?DLST_DIR=" & Server.URLEncode(xDLST_URL) & "'>" & objSubFolder.Name & "</a></B><BR>"
	Else
		Response.Write "<li class=lg><B><a href='" & objSubFolder.Name & "'>" & objSubFolder.Name & "</a></B><BR>"
	End If

	FILEIO_ListSubFolders_Callback = TRUE
End Function

'# ------------------------------------------------
'# xMode:  to-be-defined
'# xDir:   must be FQ path e.g.: "D:\ProjectWeb\cpdr\documents"
'# ------------------------------------------------
Function FILEIO_ListFiles (xMode, xPath, xFolderURL, ByRef objFSO, ByRef objFolder, xOptions)
	Dim objFile
	FILEIO_ListFiles = False
	
	If LCase(xMode) <> "tabular" Then Response.wRite "<ul>"
	For Each objFile in objFolder.Files
		bRet = FILEIO_ListFiles_Callback(xMode, xPath, xFolderURL, objFSO, objFolder, objFile)
	Next
	If LCase(xMode) <> "tabular" Then Response.Write "</ul>"
	
	FILEIO_ListFiles = TRUE
End Function

'# ----------------------------------------------------------------
'# Default callback to display files in xPath (CWD)
'# Function may be overridden by application to customize behaviour
'# ----------------------------------------------------------------
Function FILEIO_ListFiles_Callback(xMode, xPath, xFolderURL, objFSO, objFolder, objFile)
	Dim xFile, xSpaces
	Dim xDirPath

	'# must pad spaces in columns so arrow is not placed on text until css correction can be applied
	If JQUERY_TABLESORTER_ENABLED = TRUE Then xSpaces = "&nbsp;&nbsp;&nbsp"
	xFile = LCase(objFile.Name)
	'# Response.Write "<li>objFile.Name("&xFile&")</li>"
	If IsNull(xFile) OR xFile = ""   Then Exit Function
	If InStr(xFile,".asp") > 0       Then Exit Function
	If InStr(xFile,".htm") > 0 And InStr(xFile,"index.htm") =0 Then Exit Function
	If InStr(xFile,".php") > 0       Then Exit Function
	If InStr(xFile,"synctoy") > 0    Then Exit Function
	If InStr(xFile,"thumbs.db") > 0  Then Exit Function
	'# Windows does not allow a "." as first character so we use "-" to mimic UNIX behaviour
	If Left(xFile,1) = "-"           Then Exit Function
	If Left(xFile,1) = "."           Then Exit Function
	If Left(xFile,1) = "~"           Then Exit Function
	
	'# ----------------------------------------------------------------
	'# NOTE:  jquery->tablesorter does not like spaces or other characters embedded into table cell!!!
	'# Other sorting may also not work as expected. Will have to test/verify
	'# ----------------------------------------------------------------
	
	'# See:  Global.asp
	bRet              = gfQwikiDirectory(xDirectory, xDirPath, xDirectoryURL, xOptions)
	QWIKI_REPORT_PROG = "QwikiReports.asp"
	
	'# ----------------------------------------------------------------
	'# Track what files exist in DLST_DIR
	'# Used by Include/clsContent.asp -> Include/clsSearchFullText.php
	'# Do not log DRIVE: as this may change
	'# path example: /QWiki/FLWPC/ALU AMI/access ami web with end user login.docx
	'# - Is this the best way to log files?
	'# See:  Full Text Search
	'# ----------------------------------------------------------------
	If APPLICATION_SEARCH_ENABLED = True Then
		xKey   = xDirectory & "/" & xFile
		xValue = xKey
		bRet   = objFTS.DictionaryAdmin("File", "Add", xKey, xValue, "")
		If MyDebug = TRUE Then Response.Write "<li class=tiny> File Path: <strong>" & xKey & "</strong></li>"
	End If
	
	'# ----------------------------------------------------------------
	'# ----------------------------------------------------------------
	If LCase(xMode) = "tabular" Then
		If FILEIO_NODE_ENABLED = TRUE Then
			Response.Write "<tr><td><B>&nbsp;&nbsp;<a href='" & xFolderURL & "/" & objFile.Name & "'>" & objFile.Name & "</a>&nbsp;&nbsp;</B></td><td width=70>" & FormatDateTime(objFile.DateLastModified,vbShortDate) & "</td><td>" & objFile.Size & "</td>"

			If FILEIO_ADMIN_ENABLED = TRUE Then 
				Response.Write "<td>" &_
					"<a href='" & APPLICATION_URL_PATH & "/bin/FileIO.asp?ManageFile=" & Server.URLEncode(xPath & objFile.Name) & "' target='_blank' title='File Operations:  Archive, Copy, Rename, Delete, Uncompress'>Manage</a>" & xSpaces
								
				'# call-back application to include other options...
				'# FILEIO_CALLBACK() should be overridden by application-specific function
				bRet = FILEIO_CALLBACK("QwikiAdminCustom",objFolder,objFile)
				
				
				'# ---------------------------------------------------------------------
				'# ELE HACK
				'# Only show Reporting link for .csv file types
				'# ---------------------------------------------------------------------
				If InStr(LCase(objFile.Name),".csv") > 1 Then
				
					'# Until we can figure out how to include dynamically, hard-code specific options
					xAPP    = xDirPath & "/" & "." & QWIKI_REPORT_PROG
					xAppURL = xDirectoryURL & "/"  & "." & QWIKI_REPORT_PROG & "?DLST_DIR=" & Server.URLEncode(Request("DLST_DIR")) & "&File=" & Server.URLEncode(objFile.Name)
					If objFSO.FileExists(xAPP) Then
						Response.Write "<a href='" & xAppURL & "' target='_blank' title='Operations: Custom Application Reports'>Reporting</a>" & xSpaces
					End If
					
					xAPP    = xDirPath & "/" & "-" & QWIKI_REPORT_PROG
					xAppURL = xDirectoryURL & "/"  & "-" & QWIKI_REPORT_PROG & "?DLST_DIR=" & Server.URLEncode(Request("DLST_DIR")) & "&File=" & Server.URLEncode(objFile.Name)
					If objFSO.FileExists(xAPP) Then
						Response.Write "<a href='" & xAppURL & "' target='_blank' title='Operations: Custom Application Reports'>Reporting</a>" & xSpaces
					End If
				End If

				Response.Write "</td>"
			End If
			
			Response.Write "</tr>" & vbCrLf
		Else
			Response.Write "<tr><td><B>&nbsp;&nbsp;<a href='" & objFile.Name & "'>" & objFile.Name & "</a>&nbsp;&nbsp;</B></td><td width=70>" & FormatDateTime(objFile.DateLastModified,vbShortDate) & "</td><td>" & objFile.Size & "</td>"
			
			If FILEIO_ADMIN_ENABLED = TRUE Then 
				Response.Write "<td>" &_
					"&nbsp;" & xSpaces
				
				'# call-back application to include other options...
				'# FILEIO_CALLBACK() should be overridden by application-specific function
				bRet = FILEIO_CALLBACK("AdminCustom",objFolder,objFile)
				
				Response.Write "</td>"
			End If
			Response.Write "</tr>" & vbCrLf
		End If
	
	'# ----------------------------------------------------------------
	'# Display a simple <li> list of files
	'# ----------------------------------------------------------------
	Else
		If FILEIO_NODE_ENABLED = TRUE Then
			Response.Write "<li><a href='" & xFolderURL & "/" & objFile.Name & "'>" & objFile.Name & "</a><BR>" & vbCrLf
		Else
			Response.Write "<li><a href='" & objFile.Name & "'>" & objFile.Name & "</a><BR>" & vbCrLf
		End If
	End If
End Function

'# ----------------------------------------------------------------
'# Copy a file to a new name
'# http://www.codeproject.com/KB/asp/readfile.aspx
'# ----------------------------------------------------------------
Function FILEIO_CopyFile(xMode, xFromFilePath, xToFilePath, xOptions)
	Dim Filepath, TextStream, objFile
	Const ForReading = 1, ForWriting = 2, ForAppending = 3
	Const TristateUseDefault = -2, TristateTrue = -1, TristateFalse = 0
	
	'# MyDebug = True
	
	FILEIO_CopyFile = FALSE
	xContents       = Null
	bRet            = FILEIO_OpenFS(objFSO)
	
	If Not objFSO.FileExists(xFromFilePath) Then
		Response.Write "<h3><i><font color=red> File: " & xFromFilePath & ", does not exist</font></i></h3>"
		Exit Function
	End If
	
	If objFSO.FileExists(xToFilePath) Then
		If MyDebug = TRUE Then Response.Write "<li>Info: new file already exists, deleting existing new file: " & xToFilePath & "</li>"
		set objFile = objFSO.GetFile(xToFilePath)
		objFile.Delete TRUE
		Set objFile = Nothing
	End If

	Response.wRite "<li>xFromFilePath("&xFromFilePath&")</li>"
	Response.wRite "<li>xToFilePath("&xToFilePath&")</li>"

	bRet = objFSO.CopyFile(xFromFilePath, xToFilePath, TRUE)
	
	If objFSO.FileExists(xToFilePath) Then
		Response.Write "<span class=info> File copy succeeded</span><br>"
		FILEIO_CopyFile = True
	Else
		Response.Write "<span class=title> File copy failed</span><br>"
		FILEIO_CopyFile = False
	End If

	Set objFSO      = Nothing
End Function

'# ----------------------------------------------------------------
'# Rename file to a new name
'# Could use the ModeFile() method
'# ----------------------------------------------------------------
Function FILEIO_RenameFile(xMode, xFromFilePath, xToFilePath, xOptions)
	Dim Filepath, TextStream, objFile
	Const ForReading = 1, ForWriting = 2, ForAppending = 3
	Const TristateUseDefault = -2, TristateTrue = -1, TristateFalse = 0
	
	'# MyDebug = True
	
	FILEIO_RenameFile = FALSE
	xContents         = Null
	bRet              = FILEIO_OpenFS(objFSO)
	
	If Not objFSO.FileExists(xFromFilePath) Then
		Response.Write "<h3><i><font color=red> File: " & xFromFilePath & ", does not exist</font></i></h3>"
		Exit Function
	End If
	
	If objFSO.FileExists(xToFilePath) Then
		Response.Write "<h4><font color=red>...A file with the same name already exists, NOT performing copy/rename operation<br>New file name:&nbsp;" & xToFilePath & "</font></h4>"
		Exit Function

		set objFile = objFSO.GetFile(xToFilePath)
		objFile.Delete TRUE
		Set objFile = Nothing
	End If

	'#  --> Could use the MoveFile() method if you wanted...
	bRet = objFSO.CopyFile(xFromFilePath, xToFilePath, TRUE)
	
	If objFSO.FileExists(xToFilePath) Then
		Response.Write "<span class=info> File copy succeeded</span><br>"
	Else
		Response.Write "<span class=title> File copy failed</span><br>"
	End If
	
	Response.write "<li class=sm>...deleting original file</li>"
	set objFile = objFSO.GetFile(xFromFilePath)
	objFile.Delete TRUE
	Set objFile = Nothing

	If objFSO.FileExists(xFromFilePath) Then
		Response.Write "<li><font color=red>...ERROR: was not able to successfully delete original file name.  You may want to manually delete the file.</font></li>"
	Else
		Response.Write "<li>...successfully deleted original file name</li>"
	End If


	Set objFSO        = Nothing
	FILEIO_RenameFile = TRUE
End Function

'# ----------------------------------------------------------------
'# Delete a file from the server
'# ----------------------------------------------------------------
Function FILEIO_DeleteFile(xMode, xFromFilePath, xOptions)
	Dim Filepath, TextStream, objFile
	Const ForReading = 1, ForWriting = 2, ForAppending = 3
	Const TristateUseDefault = -2, TristateTrue = -1, TristateFalse = 0
	
	'# MyDebug = True
	
	'Response.Write "<li>About to create objFSO</li>"
	FILEIO_DeleteFile = FALSE
	xContents         = Null
	bRet              = FILEIO_OpenFS(objFSO)
	
	'Response.Write "<li>About to FileExists</li>"
	If Not objFSO.FileExists(xFromFilePath) Then
		Response.Write "<h3><i><font color=red> File: " & xFromFilePath & ", does not exist</font></i></h3>"
		Exit Function
	End If

	'Response.Write "<li>About to DF</li>"
	bRet = objFSO.DeleteFile(xFromFilePath, TRUE)
	
	If objFSO.FileExists(xFromFilePath) Then
		Response.Write "<span class=title>ERROR:  file deletion failed</span><br>"
		FILEIO_DeleteFile = TRUE
	Else
		Response.Write "<span class=info>Successfully deleted the file</span><br>"
		FILEIO_DeleteFile = FALSE
	End If

	Set objFSO        = Nothing
End Function

'# ----------------------------------------------------------------
'# Archive a file in the: CWD/Save directory
'# Append file file a:  _YYYY-MM-DD date stamp
'# ----------------------------------------------------------------
Function FILEIO_ArchiveFile(xMode, xFromFilePath, xOptions)
	Dim objFile, xToFileName, aFile
	Const ForReading = 1, ForWriting = 2, ForAppending = 3
	Const TristateUseDefault = -2, TristateTrue = -1, TristateFalse = 0
	MyDebug = FALSE
	
	FILEIO_ArchiveFile = FALSE
	xContents          = Null
	bRet               = FILEIO_OpenFS(objFSO)
	
	If Not objFSO.FileExists(xFromFilePath) Then
		Response.Write "<h3><i><font color=red>ERROR File: " & xFromFilePath & ", does not exist</font></i></h3>"
		Exit Function
	End If
	
	Set objFile = objFSO.GetFile(xFromFilePath)
	
	Response.write "<li>Copying from file name:  "&objFile.Name&"</li>"
	
	If MyDebug = TRUE Then Response.write "<li>objFile.ParentFolder("&objFile.ParentFolder&")</li>"
	'Response.write "<li>objFile.Type("&objFile.Type&")</li>"
	
	xDateStamp = "_YYYY-MM-DD"
	xDateStamp = "_" & DatePart("YYYY",Date()) & "-" & Right("0"&DatePart("M",Date()),2) & "-" & Right("0"&DatePart("D",Date()),2)
	If MyDebug = TRUE Then Response.write "<li>xDateStamp("&xDateStamp&")</li>"
	
	xToFolder = ""
	If InStr(objFile.Name,".") <= 0 Then
		xToFileName = objFile.Name & xDateStamp
	Else
		aFile         = Split(objFile.Name,".")
		xToFileName   = aFile(0) & xDateStamp & "." & aFile(1)
		Set objFolder = objFile.ParentFolder
		xToFolder     = objFile.ParentFolder & "\Save\"
		xToFileName   = objFile.ParentFolder & "\Save\" & xToFileName
	End If
	
	'# Create Save folder if it does not exist already - ele 6/28/2012
	If xToFolder <> "" Then
		if Not objFSO.FolderExists(xToFolder) = TRUE Then
			Set f = objFSO.CreateFolder(xToFolder)
			If Not objFSO.FolderExists(xToFolder) = TRUE Then bRet = gfERROR("ERROR: objFSO.CreateFolder(./Save) failed","exit")
			Response.Write "<li>Created SAVE folder: " & xToFolder & "</li>"
			Set f = Nothing
		End If
	End If	
	
	'# ----------------------------
	'# ----------------------------
	Response.write "<li>Copying to new file:  " & xToFileName & "</li>"

	If objFSO.FileExists(xToFileName) Then
		Response.Write "<br><span class=title>WARNING:  archived file already exists, not archiving the file again</span><br><br>"
		If MyDebug <> TRUE Then Exit Function
	End If

	bRet = FILEIO_CopyFile(xMode, xFromFilePath, xToFileName, xOptions)

	'# ----------------------------
	'# Print success status
	'# Optionally delete orginal file after successfull archive
	'# ----------------------------
	
	'# CopyFile() closes objFSO :-(
	bRet = FILEIO_OpenFS(objFSO)
	
	If objFSO.FileExists(xToFileName) Then
		Response.Write "<span class=info>Successfully archived selected file</span><br>"
		If ARCHIVE_DELETE_ORIGINAL = "on" Then
			Response.Write "<span class=title>Automatically deleting original file</span><br>"
			bRet = FILEIO_DeleteFile(xMode, xFromFilePath, xOptions)
		End If
		FILEIO_ArchiveFile = TRUE
	Else
		Response.Write "<span class=title>ERROR: new file does not exist, archive failed</span><br>"
		FILEIO_ArchiveFile = FALSE
	End If
	
	If IsArray(aFile) Then Erase aFile
	Set objFile        = Nothing
	Set objFSO         = Nothing
End Function

'# ----------------------------------------------------------------
'# Read file contents into xContents
'# http://www.codeproject.com/KB/asp/readfile.aspx
'# ----------------------------------------------------------------
Function FILEIO_ReadFile(xMode, xFilePath, ByRef  xContents)
	Dim Filepath, TextStream
	Const ForReading = 1, ForWriting = 2, ForAppending = 3
	Const TristateUseDefault = -2, TristateTrue = -1, TristateFalse = 0
	
	FILEIO_ReadFile = FALSE
	xContents       = Null
	bRet            = FILEIO_OpenFS(objFSO)
	
	'Response.Write "<li>xFilePath("&xFilePath&")</li>"
	If InStr(xFilePath,":\") < 1 Then
		xFilepath = Server.MapPath(xFilePath)
	End If

	If Not objFSO.FileExists(xFilePath) Then
		Response.Write "<h3><i><font color=red> File: " & xFilepath & ", does not exist</font></i></h3>"
		If MyDebug = TRUE Then Response.Write "<h3><i><font color=red> File: " & xFilePath & ", does not exist</font></i></h3>"
		xContents  = ""
		Set objFSO = Nothing
		Exit Function
	End If

	Set TextStream = objFSO.OpenTextFile(xFilePath, ForReading, False, TristateUseDefault)
	xContents = TextStream.ReadAll
	If MyDebug = TRUE Then Response.write "<pre>" & Contents & "</pre><hr>"
	
	TextStream.Close
	Set TextStream  = nothing
	Set objFSO      = Nothing
	FILEIO_ReadFile = TRUE
End Function


'# ----------------------------------------------------------------
'# Get rid of non-ascii characters
'# http://stackoverflow.com/questions/37024107/ddg#37025007
'# ----------------------------------------------------------------
Private Function GetStrippedText(txt)
    Dim regEx
    Set regEx = CreateObject("vbscript.regexp")
    regEx.Pattern = "[^\u0000-\u007F]"
    GetStrippedText = regEx.Replace(txt, "")
End Function


'# ----------------------------------------------------------------
'# Write file contents into xContents
'# http://www.devguru.com/technologies/vbscript/quickref/filesystemobject.html
'# http://forums.datamation.com/intranet-journal/130-writing-text-file-using-vbscript.html
'# ----------------------------------------------------------------
Function FILEIO_WriteFile(xMode, xFilePath, xContents)
	Dim objFSO, Filepath, TextStream
	
	MyDebug = False
	FILEIO_WriteFile = FALSE
	
	If IsEmpty(xContents) OR IsNull(xContents) Then
		Response.Write "<h3><i><font color=red>Error: Updated  content IsNull() or IsEmpty()</font></i></h3>"
		Exit Function
	End If

	Const ForReading = 1, ForWriting = 2, ForAppending = 8
	Const TristateUseDefault = -2, TristateTrue = -1, TristateFalse = 0
	
	bRet = FILEIO_OpenFS(objFSO)

	'# ELE: remove non-ascii characters
	xContents = GetStrippedText(xContents)
	
	If MyDebug = TRUE Then 
		Response.Write "<li>FILEIO_WriteFile():  processing file: xFilePath("&xFilePath&")</li>"
		Response.write "<hr>" & xContents & "<hr>"
	End If
		
	If xMode = "Append" Then
		'# ERROR:  TextStream below will fail.
		Set objFSO      = Nothing
		Response.Write "FILEIO_WriteFile(): APPREND mode not supported.  Get SA Help"
		Response.End
		Set objTextFile = objFSO.OpenTextFile(xFilePath, ForAppending, True)

	ElseIf objFSO.FileExists(xFilePath) Then
		Set TextStream  = objFSO.OpenTextFile(xFilePath, ForWriting, True, TristateUseDefault)
	Else
		Set TextStream = objFSO.CreateTextFile(xFilePath)
	End If
	
	TextStream.WriteLine(xContents)
		
	TextStream.Close
	Set TextStream   = Nothing
	Set objFSO       = Nothing
	
	FILEIO_WriteFile = True
	
End Function

'# ----------------------------------------------------------------
'# Write file contents into xContents
'# http://forums.datamation.com/intranet-journal/130-writing-text-file-using-vbscript.html
'# ----------------------------------------------------------------
Function FILEIO_CreateFolder(CreateDirectory, DirectoryName, xOptions)
	Dim objFSO, xFolder
	FILEIO_CreateFolder = FALSE
	bRet                = FILEIO_OpenFS(objFSO)
	xFolder             = CreateDirectory & DirectoryName
	If objFSO.FolderExists(xFolder) Then
		Response.Write "<h3>Error: Can't older since it already exists</h3>"
		Set objFSO = Nothing
		Exit Function
	End If
	Response.Write "<h3>Creating new folder:<li>" & xFolder & "</li></h3>"
	objFSO.CreateFolder(xFolder)
	If objFSO.FolderExists(xFolder) Then
		Response.Write "<h3>Success: folder was successfully created</h3>"
		Set objFSO = Nothing
		Exit Function
	End If
	Set objFSO          = Nothing
	FILEIO_CreateFolder = TRUE
End Function

'# ----------------------------------------------------------------
'# -Content.asp is dynamically created / updated using the HTTP editor
'# Display content in xForlderPath or Application Root (if enabled)
'# Content is assumed to be in a file called:  -Content.asp
'# Qwiki does not display programs with "-" or "." as leading characters
'#   or those with numerous illegal postfix characters either (by design)
'# ----------------------------------------------------------------
Function FILEIO_DisplayContent(xMode, xDP2, xFolderURL, xOptions)
	Dim objFSO, xFP, xFound, xPostfix, FILEIO_CONTENT_EXEC_ENABLED
	Dim MyDebug
	Dim SaveMe

	MyDebug = False
	
	Const ForReading = 1, ForWriting = 2, ForAppending = 3
	Const TristateUseDefault = -2, TristateTrue = -1, TristateFalse = 0
	
	FILEIO_DisplayContent = FALSE
	
	'# -----------------------------------
	'# -----------------------------------
	bRet = FILEIO_OpenFS(objFSO)
	
	'# -----------------------------------
	'# detect in priority-order
	'# -----------------------------------
	xFound                      = False 
	FILEIO_CONTENT_EXEC_ENABLED = False
	If objFSO.FileExists(xDP2 & "-Content.asp") Then
		xFound              = TRUE 
		xPostfix            = ".asp"
		FILEIO_CONTENT_EXEC_ENABLED = True
	ElseIf objFSO.FileExists(xDP2 & "-Content.html") Then
		xFound              = TRUE 
		xPostfix            = ".html"
		FILEIO_CONTENT_EXEC_ENABLED = False
	ElseIf objFSO.FileExists(xDP2 & "-Content.php") Then
		xFound              = TRUE 
		xPostfix            = ".php"
		FILEIO_CONTENT_EXEC_ENABLED = True
	ElseIf objFSO.FileExists(xDP2 & "-Content.aspx") Then
		xFound              = TRUE 
		xPostfix            = ".aspx"
		FILEIO_CONTENT_EXEC_ENABLED = True
	ElseIf objFSO.FileExists(xDP2 & "-Content.pl") Then
		xFound              = TRUE 
		xPostfix            = ".pl"
		FILEIO_CONTENT_EXEC_ENABLED = True
	End If
	
	'# -----------------------------------
	'# pass all options to calling page
	'# -----------------------------------
	xURL2  = "?"
	xAmper = ""
	For Each xItem In Request.QueryString()
		xURL2 = xURL2 & xAmper & xItem & "=" & Request.QueryString(xItem)
		xAmper = "&"
	Next

	'# This may not work correct when posting large amounts of data :-(
	For Each xItem In Request.Form()
		xURL2 = xURL2 & xAmper & xItem & "=" & Request.Form(xItem)
		xAmper = "&"
	Next
	If MyDebug = True Then Response.write "<li>FILEIO_DisplayContent(): xURL2(" & xURL2 & ")</li>"

	'# -----------------------------------
	'# -----------------------------------
	If xFound = True Then
		xFilePath       = xDP2   & "-Content"  & xPostfix
		xURL            = xFolderURL & "/-Content" & xPostfix & xURL2      '# pass URI get arguments.  Some require them.
		xURI            = xFolderURL & "/-Content" & xPostfix

		'# ----------------------------------------------------------------------
		'# ----------------------------------------------------------------------
		If FILEIO_CONTENT_EXEC_ENABLED Then
			If MyDebug = TRUE Then Response.write "<li>...FILEIO_CONTENT_EXEC_ENABLED  xURL ("&xURL&") </li>"
			
			'# -------------------------------------------------------------------
			'# client browser loads -Content.asp using JavaScript synchronous Ajax
			'# -------------------------------------------------------------------
			bRet = gfClientBrowserAjaxGet(xURL,"")
			
			'# -------------------------------------------------------------------
			'# Log content into FTS database on each page load
			'# Issue:  could be a performance issue
			'# See:  Editor.asp which updates each time Content is changed
			'# TODO:  Keep array and only update page once per day (per user?)
			'# -------------------------------------------------------------------
			If APPLICATION_SEARCH_ENABLED = True Then
				If Not objFTS.odFTS_Updated.Exists(xURL) Then
					If gfServerXMLHTTP_ORIG("GET", xURL, "", xResult, "", "", "", xRetMsg) Then
						objFTS.FTS_BODY  = xResult
					End If
				End If
			End If
					
			FILEIO_DisplayContent = True
			
			Exit Function

		End If

		'# ----------------------------------------------------------------------
		'# Default reads file and dumps to stdout
		'# ----------------------------------------------------------------------
		Set TextStream = objFSO.OpenTextFile(xFilePath, ForReading, False, TristateUseDefault)
		Response.Write TextStream.ReadAll
		TextStream.Close
		FILEIO_DisplayContent = True
		Set TextStream        = Nothing
		
		'# Should we be doing this w/o proper cleanup?  See:  FILEIO_OpenFS() to see if objFSO is kept open?
		Set objFSO            = Nothing
		
		Exit Function
	End If
	
	Set objFSO = Nothing
End Function

'# ----------------------------------------------------------------
'# Display Index.xxx in an IFRAME after -Content.xxx
'# ----------------------------------------------------------------
Function FILEIO_DisplayPageContent(xPrefix, xDirPath, xDirectoryURL, xIframeOptions, xOptions)
	Dim objFSO, xFound, bRet, xURL, MyDebug
	MyDebug = TRUE
	
	xFileName                   = ""
	FILEIO_DisplayPageContent   = False
	bRet                        = FILEIO_OpenFS(objFSO)

	If objFSO.FileExists(xDirPath & xPrefix & ".asp") Then
		xFileName           = xPrefix & ".asp"
	ElseIf objFSO.FileExists(xDirPath & xPrefix & ".html") Then
		xFileName            = xPrefix & ".html"
	ElseIf objFSO.FileExists(xDirPath & xPrefix & ".htm") Then
		xFileName           = xPrefix & ".htm"
	ElseIf objFSO.FileExists(xDirPath & xPrefix & ".php") Then
		xFileName           = xPrefix & ".php"
	ElseIf objFSO.FileExists(xDirPath & xPrefix & ".aspx") Then
		xFileName           = xPrefix & ".aspx"
	ElseIf objFSO.FileExists(xDirPath & xPrefix & ".pl") Then
		xFileName           = xPrefix & ".pl"
	End If
	Set objFSO = Nothing
	
	If xFileName = "" Then
		Exit Function
	End If
	
	xURL  = xDirectoryURL & "/" & xFileName
	If MyDebug = TRUE Then Response.write "<li>xFileName("&xFileName&") xURL(" & xURL & ")</li>"	
	
	Response.Write "<h2>Information Content</h3>"

	Response.Write "<a href=""#"" onclick=""document.getElementById('iFrame').contentDocument.location.reload(true); return false;"" title=""Click to load a fresh copy of the index web page.  This is required if the file was recently updated and the current iFrame is already in your browsers cache..."">Refresh</a>" & vbCrLf
	Response.Write "&nbsp;&nbsp;<a href=""" & xURL & """  >View Page</a>" & vbCrLf
	
	'#  http://stackoverflow.com/questions/153152/resizing-an-iframe-based-on-content
	'# Requires JS in Inxex.asp.   Need to move to this or common Qwiki .js
'#	Response.Write "&nbsp;&nbsp;<a href=""#"" OnClick=""autoResize('iFrame');"" >Maximize</a>" & vbCrLf
	
	Response.Write "<br><iframe id=iFrame src='" & xURL & "' " & xIframeOptions & "></iframe><p>"
	
	'# -------------------------------------------------------------------
	'# NOT VALIDATED YET
	'# Need to test and validate this extension
	'# -------------------------------------------------------------------
	If APPLICATION_SEARCH_ENABLED = True And Session("EmployeeCSL") = "xele" Then			
		If gfServerXMLHTTP_ORIG("GET", xURL, "", xResult, "", "", "", xRetMsg) Then			
			objFTS.FTS_BODY  = xResult
		End If
	End If
	
	Response.Write "<h1>done FILEIO_DisplayPageContent</h1>"
	FILEIO_DisplayPageContent  = True
End Function

'# ----------------------------------------------------------------
'# QwikiCustom is created by external applications in specific directories
'# This content is inserted after Qwiki "Content" (see above) before Content/Folders section
'# Display Custom Content in xForlderPath
'# Content is assumed to be in a file called:  -QwikiContent.[asp|html|php|aspx|pl|cgi]
'# Rem:  Qwiki does not display programs with "-" or "." as leading characters
'# ----------------------------------------------------------------
Function FILEIO_DisplayCustom(xMode, xFolderPath, xFolderURL, xOptions)
	Dim objFSO, xFP, xFound, xPostfix, FILEIO_CONTENT_EXEC_ENABLED
	Dim MyDebug
	
	MyDebug = False
	
	Const ForReading = 1, ForWriting = 2, ForAppending = 3
	Const TristateUseDefault = -2, TristateTrue = -1, TristateFalse = 0
	
	FILEIO_DisplayCustom = FALSE
	xURL                 = ""
	xFilePath            = ""

	bRet = FILEIO_OpenFS(objFSO)
	
	'# -----------------------------------
	'# Determine the LAST -QwikiCustom that may exist in the folder path
	'# -----------------------------------
	xCustomPath     = ""
	If MyDebug = TRUE Then Response.Write "<li>xFolderPath:  " & xFolderPath & "</li>"
	For Each xPath In Split(xFolderPath,"\")
		If xPath <> "\" And xPath <> "" Then 
			xCustomPath = xCustomPath & xPath & "\"

			If MyDebug = TRUE Then Response.Write "<li>xCustomPath:  " & xCustomPath & "</li>"

			'# -----------------------------------
			'# detect in priority-order
			'# -----------------------------------
			xFound                      = False 
						
			If objFSO.FileExists(xCustomPath & "-QwikiCustom.asp") Then
				xFound              = TRUE 
				xPostfix            = ".asp"
			ElseIf objFSO.FileExists(xCustomPath & "-QwikiCustom.html") Then
				xFound              = TRUE 
				xPostfix            = ".html"
			ElseIf objFSO.FileExists(xCustomPath & "-QwikiCustom.php") Then
				xFound              = TRUE 
				xPostfix            = ".php"
			ElseIf objFSO.FileExists(xCustomPath & "-QwikiCustom.aspx") Then
				xFound              = TRUE 
				xPostfix            = ".aspx"
			ElseIf objFSO.FileExists(xCustomPath & "-QwikiCustom.pl") Then
				xFound              = TRUE 
				xPostfix            = ".pl"
			ElseIf objFSO.FileExists(xCustomPath & "-QwikiCustom.cgi") Then
				xFound              = TRUE 
				xPostfix            = ".pl"
			End If
			If xFound = TRUE Then 
				FILEIO_CONTENT_EXEC_ENABLED = True
				xFilePath       = xCustomPath & "-QwikiCustom" & xPostfix
				
				'# -----------------------------------
				'# Convert PATH to URL
				'# "/Qwiki/" is a core requirement and is hard-coded in may places.  May be not ideal but it's that way...
				'# -----------------------------------
				xURL = Replace(xCustomPath, APPLICATION_DIRECTORY_PATH, SERVER_URL&"/Qwiki/")
				xURL = Replace(xURL, "\", "/")
				xURL = xURL & "-QwikiCustom" & xPostfix
				
				If MyDebug = TRUE Then Response.write "<li>FILEIO_DisplayCustom() xCustomPath(" & xCustomPath & ") xFolderPath(" & xFolderPath & ") xFolderURL(" & xFolderURL & ") xPostfix("&xPostfix&") </li>"
			End If
		End If
	Next
	If MyDebug = TRUE Then Response.write "<li>FILEIO_DisplayCustom(): xURL("&xURL&") xFilePath(" & xFilePath & ") </li>"
	
	'# -----------------------------------
	'# If -QwikiCustom exists then execute it or just include it locally
	'# Maybe we should always execute .html too?
	'# -----------------------------------
	If xURL <> "" Then
		'# -----------------------------------
		'# pass original URL options to calling page
		'# TODO - Should encode options in URL2
		'# -----------------------------------
		xURL2  = "?"
		xAmper = ""
		For Each xItem In Request.QueryString()
			xURL2 = xURL2 & xAmper & xItem & "=" & Request.QueryString(xItem)
			xAmper = "&"
		Next
		For Each xItem In Request.Form()
			xURL2 = xURL2 & xAmper & xItem & "=" & Request.Form(xItem)
			xAmper = "&"
		Next
		If MyDebug = True Then Response.write "<li>FILEIO_DisplayCustom(): xURL2(" & xURL2 & ")</li>"
		
		xURL            = xURL & xURL2
		If MyDebug = TRUE Then Response.write "<li>FILEIO_DisplayCustom(): xURL(" & xURL & ") FILEIO_CONTENT_EXEC_ENABLED(" & FILEIO_CONTENT_EXEC_ENABLED & ")</li>"
		If FILEIO_CONTENT_EXEC_ENABLED = True Then
			If gfServerXMLHTTP("GET", xURL, xResult, "", xTimeout, xOptions, xRetMsg) = True Then
				Response.Write xResult
				FILEIO_DisplayCustom  = True
			Else
				Response.wRite "<li>ERROR: gfServerXMLHTTP() failed to retrieve page content: " & xURL & "</li>"
			End If
		Else
			Set TextStream = objFSO.OpenTextFile(xFilePath, ForReading, False, TristateUseDefault)
			Response.Write TextStream.ReadAll
			TextStream.Close
			FILEIO_DisplayCustom  = True
			Set TextStream        = Nothing
		End If
	End If

	Set objFSO = Nothing
End Function

'# ----------------------------------------------------------------
'# Example not tested:  http://www.naterice.com/articles/47
'# 
'# This function executes the command line version of WinZip and reports whether
'# the archive exists after WinZip exits.  If it exists then it returns true. If
'# not it returns an error message.
'# ----------------------------------------------------------------
Function Zip(sFile,sArchiveName)

	Set oFSO   = WScript.CreateObject("Scripting.FileSystemObject")
	Set oShell = WScript.CreateObject("Wscript.Shell")

	'--------Find Working Directory--------
	aScriptFilename = Split(Wscript.ScriptFullName, "\")
	sScriptFilename = aScriptFileName(Ubound(aScriptFilename))
	sWorkingDirectory = Replace(Wscript.ScriptFullName, sScriptFilename, "")

	'-------Ensure we can find WZZIP.exe------
	If oFSO.FileExists(sWorkingDirectory & " " & "WZZIP.EXE") Then
	sWinZipLocation = ""
	ElseIf oFSO.FileExists("C:\program files\WinZip\WZZIP.EXE") Then
	sWinZipLocation = "C:\program files\WinZip\"
	Else
	Zip = "Error: Couldn't find WZZIP.EXE"
	Exit Function
	End If
	'--------------------------------------

	oShell.Run """" & sWinZipLocation & "wzzip.exe"" -ex -r -p -whs -ybc """ & _
	sArchiveName & """ """ & sFile & """", 0, True 

	If oFSO.FileExists(sArchiveName) Then
	Zip = 1
	Else
	Zip = "Error: Archive Creation Failed."
	End If
End Function

'# ----------------------------------------------------------------
'# Example not tested:  http://blog.peter-johnson.com.au/?p=204
'# ----------------------------------------------------------------
Sub MoveToZip(InFilename, OutFilename)
	Dim FSO 
	Set FSO = CreateObject("Scripting.FileSystemObject")
	Dim Timeout 
	Timeout = 0
	FSO.CreateTextFile(OutFilename, true).WriteLine "PK" & Chr(5) & Chr(6) & String(18, 0)
	Dim Shell 
	Set Shell = CreateObject("Shell.Application")
	Dim ZipFile
	Set ZipFile = Shell.NameSpace(OutFilename)
	ZipFile.CopyHere InFilename
	Do Until ZipFile.items.Count = 1  or Timeout > TimeoutMins * 600
		Wscript.Sleep 100
		Timeout = Timeout + 1
	Loop
	If MoveMode and ZipFile.items.Count = 1 Then FSO.DeleteFile(InFilename)
	Set Shell = Nothing
	Set FSO = Nothing
	Set ZipFile = Nothing
End Sub

'# ------------------------------------------------
'# xPath   must be FQ path e.g.: "D:\ProjectWeb\cpdr\documents.txt"
'# ------------------------------------------------
Function FILEIO_FileExists (xPath, xOptions)
	FILEIO_FileExists = False
	bRet              = FILEIO_OpenFS(objFSO)
	If objFSO.FileExists(xPath) Then FILEIO_FileExists = TRUE
	Set objFS0        = Nothing
End Function

'# ------------------------------------------------
'# Check if a logo exists in the CWD
'# ------------------------------------------------
Function FILEIO_LogoExists (xPath, xLogoPrefix, ByRef xLogoName, xOptions)
	FILEIO_LogoExists = False

	bRet              = FILEIO_OpenFS(objFSO)
	
	If xLogoPrefix = "" Then xLogoPrefix = "-ContentLogo."
	xLogoName      = ""
	
	If objFSO.FileExists(xPath&xLogoPrefix&"png") Then
		xLogoName         = xLogoPrefix&"png"
		FILEIO_LogoExists = True
		
	ElseIf objFSO.FileExists(xPath&xLogoPrefix&"jpg") Then
		xLogoName         = xLogoPrefix&"jpg"
		FILEIO_LogoExists = True
		
	ElseIf objFSO.FileExists(xPath&xLogoPrefix&"gif") Then
		xLogoName         = xLogoPrefix&"gif"
		FILEIO_LogoExists = True
	End If
	
	'# Check parent folder
	If Session("EmployeeCSL") = "xele" And FILEIO_LogoExists = False And ( xOptions = "" Or xOptions < 2 ) Then
		Response.Write "<li>xPath("&xPath&")</li>"
		Set xFolder = objFSO.GetFolder(xPath)
		Response.Write "<li>xFolder("&xFolder&")</li>"
		Response.Write "<li>xPath("&xPath&")</li>"
		xPPath = xFolder.ParentFolder
		If xOptions = "" Then
			xOptions = 1
		Else
			xOptions = xOptions + 1
		End If
		 If xPPath <> "" Then FILEIO_LogoExists = FILEIO_LogoExists (xPPath, xLogoPrefix, xLogoName, xOptions)
	End If

	Set objFS0        = Nothing
End Function

%>
