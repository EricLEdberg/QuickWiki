QwikWiki (QW) was originally developed in the mid-2000s to disseminate information such as:  documents, test plans, automated integration/regression test results, methods and procedures, and content created by users located on secure corporte servers.  This data was created and published by many diverse teams, automated applications, support staff and manually published in a common directory path.  Our teams required a web-based tool allowing users to quickly locate and view this data.

QW is a web-based visual representation of a directory structure. It lists the FOLDERS and FILES that reside in a ROOT folder.  The user may then select a sub-folder to recursively decend the root directory viewing html content and select a file(s) to download.  QW is similar to other basic wiki's and displays user created html content that optionally resides in each folder.  

Qwiki performs these actions on each page load which displays the contents of the selected folder:

- Lists the FOLDERS in a columnar list:
  - Each sub-folder is hyperlinked allowing users to descend the root of the QW

- Lists the FILES in each folder in a table:
  - Allowed file types are listed along with their size and last modified date
  - A Manage option allows authorized users to copy, rename, archive or delete each file

- HTML content is displayed:
  - Optional html-formatted content contained in a file: .qwContent.php, in each folder is displayed
  - The integrated html CKEDITOR library is used to create and edit user content
  - It allows users to add notes, procedures, and information about the content that resides in the folder

- Web pages saved locally are displayed:
  - Web pages that were saved as: index.xxx, in the current folder are loaded in an html IFRAME
  - This feature allows users or automated programs to save web content into a folder and have it displayed embedded into the qwiki page
    
Other features are enabled when a page/folder loads:

- Create Folder:
  - Web users may manually create a new sub-folder

- Upload Files:
  - User may upload new files into the directory

- Full Text Search:
  - An optional Full text Search MySQL database provides the ability to search for data contained in the folder or html content
  - As users traverse Qwiki directories FTS indexes that page and updates it database
  - Each page is updated once per day per user to avoid overhead but still provide a shelf-healing search capability

- User Authentication:
  -  Supports user authentication using a stand-alone implementation of:  composer PHPAuth/PHPAuth
  -  More information TBP
