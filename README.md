QwikWiki (QW) is unlike other wiki's and is much "quicker".  It was originally developed in the mid-2000s to quickly display and disseminate information (test plans, automated regression test results, methods and procedures, etc.) located on already-secure corporte servers.  This information was partly published by many different applications and support teams in one common location.  In 2024, Eric L. Edberg re-wrote the original Qwiki application solely in PHP for personal use across multiple different architectures.

In essence, QwikWiki is a web-based visual representation of a directory structure. It lists the FOLDERS and FILES that reside in a ROOT folder.  The user may select a sub-folder to recursively decend the directory or select a file to download (or archive, rename, copy or delete).

Qwiki also enables additional features on each page (directory) load:

- HTML Content:  
  - After the folders and files in the cwd are displayed, optional html-formatted content (contained in a file: .qwcontent.php), is displayed
  - The built-in html editor: CKEDIT, allows authorized users to create & update this content
  - It allows users to quickly add notes, procedrues, and information to the page
- Inline Page Viewer
  - If a web page was previously saved in the directory as: index.xxx, it is dynamically displayed in an IFrame
  - It allows users to save other web pages locally and display it on a Qwiki page
    
Other capabilities are supported:

- Upload Files:
  - User may upload new files into the directory
- Full Text Search:
  - An optional Full text Search MySQL database provides the ability to search for data contained in the folder or html content
  - As users traverse Qwiki directories the FTS information is updated once per day
- User Authentication:
  -  Supports user authentication using a stand-alone implementation of:  composer PHPAuth/PHPAuth
  -  More information TBP
