<?php

// --------------------------------------------
// Extend clsProfile and overload 2 functions that encrypt/decrypt data flows
// --------------------------------------------
class QWProfile extends clsProfile {
	public function outgoingdata ($xStr) {
		global $objENC;
		return $objENC->encryptData($xStr);
	}
	public function incommingData ($xStr) {
		global $objENC;
		return $objENC->decryptData($xStr);
	}
}

// --------------------------------------------
// --------------------------------------------
class clsProfile {
	
	public function __construct($aConfig) {
		$this->MyDebug = false;
		$this->config  = $aConfig;
        $this->INIT(null);
    }

    public function __destruct() {
		if ($this->MyDebug) {
			echo "<h2 class=info>clsProfile Debug</h2>";
			$this->dump($this->config);
			echo "<h2 class=info>_REQUEST</h2>";
			$this->dump($_REQUEST);
			echo "<h2 class=info>_SESSION</h2>";
			$this->dump($_SESSION);
		}
    }
    
    function dump($var) {
        echo "<div class=dbg><pre>";
        print_r($var);
        echo "</pre></div>";
    }

    // --------------------------------------------
    // INIT must be called on each page load to initialize some config[] options
    // --------------------------------------------
    function INIT($aOptions) {	
		
		// Is user already logged in?
		if ($this->isLoggedOn(null)) {
			//return true;
		}

		// clsAuth authentication server submits information to clsProfile contianing user information in an encrypted key (after login)
		// $this->incommingData() is overloaded by QWProfile and will decrypt the data sent by clsAuth.php
		// TODO:  initialize local user data and log user into qw ($this->logInUser?)
		if (isset($_POST['AuthKey'])) {
			$this->config['Auth']['AuthKey']          = $_POST['AuthKey'];
			$this->config['Auth']['AuthKeyDecrypted'] = $this->incommingData($this->config['Auth']['AuthKey']);
			$this->logInUser(null);
		}
		
		// Perform specific Actions initiated by native clsProfile methods
		if (isset($this->config['Options']['profileAction']) ) {
			switch ($this->config['Options']['profileAction']) {
				case 'logout':
					$this->logOutUser(null);
					break;
				case 'login':
					$this->logInUser(null);
					break;
				case 'options':
					$this->showAdminPage(null);
					break;
				default:
					echo "<li>ERROR:  Unknown profile action: "	. $this->config['Options']['profileAction'] . "</li>";
					exit;
					break;
			}
		}

		// Show Profile management page
		if (isset($this->config['Options']['Profile'])) {
			$this->showAdminPage(null);
			exit;
		}
	
		return false;
	}

	// --------------------------------------------
    // Placeholder functions that can be overloaded by application, typically to encrypt/decrypt the data
	// GET, POST, or PUT options are the target to prevent snooping of actual file information
	// Let's try to not natively embed encryption methods into this class
	// --------------------------------------------
    public function outgoingData(string $xStr) {
		return($xStr);
	}
	public function incommingData(string $xStr) {
		return($xStr);
	}
	
	// --------------------------------------------
    // --------------------------------------------
    function isLoggedOn($aOptions){
		global $objENC;
		if (isset($_SESSION['QWUser'])) {
			$this->config['User'] = json_decode($this->incommingData($_SESSION['QWUser']), true);
		}
		if (!isset($this->config['User'])) return false;
		if (!isset($this->config['User']['email']) || !isset($this->config['User']['uid']) ) return false;
		return true;	
	}

	// --------------------------------------------
	// Authentication server returned with a successful login
	// TODO:  should support different methods to obtain logged-in user information
    // --------------------------------------------
    function logInUser($aOptions){

		$this->logOutUser(null);
		
		// Method 1:  composer phpauth/phpauth implemented by clsAuth.php
		// AuthKey is encrypted json returned by clsAuth.php
		$_SESSION['QWUser']   = $this->config['Auth']['AuthKey'];   
		$this->config['User'] = json_decode($this->incommingData($_SESSION['QWUser']), true);    
		
		return true;		
	}

	// --------------------------------------------
	// Unset clsProfile User
    // --------------------------------------------
    function logOutUser($aOptions){	
		if (isset($_SESSION['QWUser']))                         unset($_SESSION['QWUser']);
		if (isset($this->config['User']))                       unset($this->config['User']);
		if (isset($this->config['Auth']['AuthKey']))            unset($this->config['AuthKey']);
		if (isset($this->config['Auth']['AuthKeyDecrypted']))   unset($this->config['AuthKeyDecrypted']);

		// temp legacy stuff
		if (isset($_SESSION['QWPUser']))   unset($_SESSION['QWPUser']);

		return true;
	}
	
	// --------------------------------------------
    // --------------------------------------------
    function showAdminPage($aOptions) {
		$this->PROFILE_FORM_HEAD();
		$this->PROFILE_FORM_BODY();
		$this->PROFILE_FORM_TAIL();
	}
	
	// --------------------------------------------
    // --------------------------------------------
    function PROFILE_FORM_HEAD(){
		global $objENC;
		global $objQW;

		$xKey = "PROFILE_filePath=" . $this->config['Options']['PROFILE_filePath'];

		echo "<h2>Qwiki User Profile</h2>";
		if ($this->isLoggedOn(null)) {
			echo "<button class='button' id=profileLogout onclick='GoToURL(\"" . $this->config['Auth']['logoutURL'] . "\",\"_self\"); return false;'>Qwiki Logout</button>";
		} else {
			echo "<button class='button' id=profileLogin onclick='GoToURL(\"" . $this->config['Auth']['loginURL'] . "\",\"_self\"); return false;'>Qwiki Login</button>";
		}
		
		$objQW->ReturnToQwikiButton(null);

		
		echo "<form name='ProfileForm' method='POST' action='"   . $this->config['submitAction'] . "'>";
		echo "<input name='PROFILE_Key' type=hidden value='"     . $this->outgoingData($xKey)     . "'>";
		
		echo "<br><table>";
		echo "<tr><td colspan=20>";
	}    

	// --------------------------------------------
    // --------------------------------------------
    function PROFILE_FORM_BODY() {
		if (!$this->isLoggedOn(null)) return false;
        echo "<h3>User Summary</h3>";
		return $this->showCurrentUser(null);
	}

	// --------------------------------------------
    // --------------------------------------------
    function PROFILE_FORM_TAIL(){
		echo "</form>";

		echo "<h3>Notes:</h3><ul>";
			echo "<li>Qwiki never has access to your password.  It is stored as a hashed value on the authentication server.  It cannot be decrypted by Qwiki at any time.</li>";
            echo "<li>After a user successfully logs in on the authentication server it returns encrypted user information to Qwiki.</li>";
            echo "<li>Qwiki maintains additional information such as the <b>Security Roles</b> they have been granted</li>";
            
		echo "</ul>";
	}
    
    public function showCurrentUser($aOptions) {
        echo '<div id="currentUserInfo" class="comment" style="display:inline-block;"><table border=1 cellpadding=5 cellspacing=5>' .
        '<tr><td align=right>Email:</td>' . '<td align=left><b>' . $this->config['User']['email'] . '</b></td></tr>' . 
        '<tr><td align=right>Username:</td>' . '<td align=left><b>' . $this->config['User']['Username'] . '</b></td></tr>' . 
        '<tr><td align=right>First:</td>' . '<td align=left><b>' . $this->config['User']['First'] . '</b></td></tr>' . 
        '<tr><td align=right>Last:</td>' . '<td align=left><b>' . $this->config['User']['Last'] . '</b></td></tr>' . 
        '<tr><td align=right>Phone Number:</td>' . '<td align=left><b>' . $this->config['User']['Phone'] . '</b></td></tr>' . 
        '<tr><td align=right>ID:</td>' . '<td align=left><b>' . $this->config['User']['id'] . '</b></td></tr>' . 
        '<tr><td align=right>UID:</td>' . '<td align=left><b>' . $this->config['User']['uid'] . '</b></td></tr>' . 
        '<tr><td align=right>Date Last Modification:</td>' . '<td align=left><b>' . $this->config['User']['dtModification'] . '</b></td></tr>' . 
        '<tr><td align=right>Date Last Login:</td>' . '<td align=left><b>' . $this->config['User']['dtLastUpdate'] . '</b></td></tr>' . 
        '<tr><td align=right>Active:</td>' . '<td align=left><b>' . $this->config['User']['isactive'] . '</b></td></tr>' . 
        '</table></div>';
    }
}

?>
