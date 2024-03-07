QwikWiki (QW) is unlike other wiki's and is much "quicker", or possibly just simpler.  This web tool was originally developed in the mid-2000s to display and disseminate information (test plans, automated regression test results, methods and procedures, etc.) located on secure corporte servers.  The information was created and published by many different teams and automated applications into one common directory path.

QwikWiki is a web-based visual representation of a directory structure. It lists the FOLDERS and FILES that reside in a ROOT folder.  The user may then select a sub-folder to recursively decend the root directory or select a file to download (or archive, rename, copy or delete).

Qwiki performs additional features on each page load:

- HTML Content:
  - After the folders and files in the cwd are displayed, optional html-formatted content (optionally located in a file: .qwContent.php in each directory), is displayed
  - A built-in html editor: CKEDITOR, is used to create and edit user content
  - This feature allows users to quickly add notes, procedures, and information that will display on the qwiki age
- Inline Page Viewer
  - Web pages that were saved in the directory being viewed, named: index.xxx,  are loaded into an html iframe embedded in the page
  - This feature allows users, or automated programs, to save web contents in a folder and have it displayed on the qwiki page
    
Other features are supported:

- Upload Files:
  - User may upload new files into the directory
- Full Text Search:
  - An optional Full text Search MySQL database provides the ability to search for data contained in the folder or html content
  - As users traverse Qwiki directories FTS indexes that page and updates it database
  - Each page is updated once per day per user to avoid overhead but still provide a shelf-healing search capability
- User Authentication:
  -  Supports user authentication using a stand-alone implementation of:  composer PHPAuth/PHPAuth
  -  More information TBP
