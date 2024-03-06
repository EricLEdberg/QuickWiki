<?php 

class clsDownloader {
    private $Config;

    public function __construct($aConfig) {
        $this->Config = $aConfig;
    }
    
    // NOTE:  destruct() is not visabled (or may be not even called) on successful download
    //        Not sure if it's just the output that is lost?  It executes in debug mode however.
    //        Need to test...
    public function __destruct() {
        $this->dump($this->Config);
    }
    
    function dump($var) {
        echo "<div class=dbg><pre>";
        print_r($var);
        echo "</pre></div>";
    }
    
    // --------------------------------------------
    // Download file from local file system
    // TODO:  Verify that there are no ".." characters in path
    // TODO:  Optionally verify that path is relaitve to a ROOT folder (aka chroot)  
    // --------------------------------------------
    public function downloadFile($xFilePath) {

        $this->Config['FilePath'] = $xFilePath;
        
        // Check if file exists and is readable
        $this->Config['FileRealPath'] = realpath($this->Config['FilePath']);         
        if (!file_exists($this->Config['FilePath']) || !is_readable($this->Config['FilePath']) || (!$this->Config['FileRealPath'])) {
            header("HTTP/1.1 404 Not Found");
            echo "File was not found or it's not readable";
            exit;
        }

        // Clear and flush output buffer
        ob_clean();
        flush();

        // Was content previously written to client?
        if (headers_sent()) {
            die("Headers already sent to client. Cannot initiate download.");
        }
        
        // Get file information
        $this->Config['FileName'] = basename($this->Config['FilePath']);
        $this->Config['FileSize'] = filesize($this->Config['FilePath']);
        $this->Config['FileType'] = mime_content_type($this->Config['FilePath']);

        if ($this->Config['debug']) exit;

        // -----------------------------------------
        // -----------------------------------------
        
        header("Content-Type: " . $this->Config['FileType'] . "");
        header("Content-Disposition: attachment; filename=\"" . $this->Config['FileName'] . "\"");
        header("Content-Length: " . $this->Config['FileSize']);
        header("Content-Description: File Transfer");
             
        // -----------------------------------------
        // -----------------------------------------
        $file = fopen($this->Config['FilePath'], "rb");

        if (!$file) {
            header("HTTP/1.1 500 Internal Server Error");
            echo "Error opening file.";
        }

        while (!feof($file)) {
            echo fread($file, 1024);
            flush();
        }
        fclose($file);

    }
    
    // --------------------------------------------
    // TODO: upgrade clsclsDownloader to download URLs
    // PLACEHOLDER 
    // --------------------------------------------
    public function downloadURL ($aOptions) {
        echo "ERROR:  downloadURL() not supported";
        die();
        readfile($this->url);
    }
    
    // --------------------------------------------
    // TEST PROTOTYPE:  Securely identify content type of a file
    // See:   https://www.php.net/manual/en/function.mime-content-type.php
    // See:   http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types
    // --------------------------------------------
    public function deriveContentType () {
        
        // Testing get file_type by file inspection
        // $file_info = new finfo(FILEINFO_MIME);  // object oriented approach!
        // $mime_type = $file_info->buffer(file_get_contents($this->Config['FilePath']));  // e.g. gives "image/jpeg"
   
        // Get file extension
        // $fileExtension = pathinfo($this->url, PATHINFO_EXTENSION);


        // Set content type based on file extension
        switch ($fileExtension) {
            case 'pdf':
                $contentType = 'application/pdf';
                break;
            case 'jpg':
            case 'jpeg':
                $contentType = 'image/jpeg';
                break;
            case 'png':
                $contentType = 'image/png';
                break;
            case 'gif':
                $contentType = 'image/gif';
                break;
            default:
                $contentType = 'application/octet-stream'; // Default to binary
        }

        return $contentType;
    }

    // --------------------------------------------
    // --------------------------------------------
    public function testclsDownloader($aOptions) {

         $this->downloadFile("Images/Icon_Home.png");

    }
}

// $aOptions     = array();
// $objDownloder = new clsDownloader($aOptions);
// $objDownloder->downloadFile("Images/Icon_Home.png");
// $objDownloder->testclsDownloader($aOptions);

?>
