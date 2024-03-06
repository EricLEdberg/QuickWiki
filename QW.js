// ----------------------------------------------------------------------------------
// This JavaScript library contains many functions originally devloped & used in other programs
// Most are not used by Qwiki.
// At some point I may prune the out of this JS script
// Eric Edberg 2/24
// ----------------------------------------------------------------------------------

// ----------------------------------------------------------------------------------
// Global.js should be loaded as the first item after the HEAD.
// This var initializes the start of the page load time
// Used by clsTimer.asp to track client load times
// ----------------------------------------------------------------------------------
var PageLoadStartTime = (new Date()).getTime();

function GoToURL(url,target) {
	if (target == "") target="_self";
	if (url != "") open(url,target);
}
	
// ----------------------------------------------------------------------------------
// Show or hide based on html element ID
// istrID is a pointer to an image that will optionally be toggled (indicating show/hide options)
// ----------------------------------------------------------------------------------
function toggleBlock(pstrID, istrID){
  var myDiv = document.getElementById(pstrID);
  if (myDiv){
	if (myDiv.style.display == 'none'){
	  showBlock(pstrID, istrID);
	} else{
	  hideBlock(pstrID, istrID);
	}
  }
}
function showBlock(pstrID, istrID){
  var myDiv = document.getElementById(pstrID);
  if (myDiv){
	myDiv.style.display = 'block';
	var myImage = document.getElementById(istrID);
	if (myImage){
	  myImage.src = '/APP/images/arrowblue.gif';
	  myImage.alt = 'Hide';
	}
  }
}
function hideBlock(pstrID, istrID){
  var myDiv = document.getElementById(pstrID);
  if (myDiv){
	myDiv.style.display = 'none';
	var myImage = document.getElementById(istrID);
	if (myImage){
	  myImage.src = '/APP/images/arrowright.gif';
	  myImage.alt = 'Show';
	}
  }
}

// ----------------------------------------------------------------------------------
// ----------------------------------------------------------------------------------
function ElementGetID(id) {
	if (document.getElementById) {
  		return document.getElementById(id);
    } else if (document.all) {
	   return document.all[id];
    }
}
function ElementToggleVisibility(title) {
	var element = getPageElement(title);
	if (ElementIsVisible(element)) {
        var new_style = 'none';
    } else {
		var new_style = 'block';
    }
	element.style.display = new_style;
}
function ElementIsVisible(element) {
    if ((!element.style.display) || (element.style.display == getDisplayStyle())) {
        return true;
    } else {
        return false;
    }
}
function getPageElement(id) {
	if (document.getElementById) {
  		return document.getElementById(id);
    } else if (document.all) {
	   return document.all[id];
    }
}
function toggleVisibility(title, create_cookie, use_inline){
	var element = getPageElement(title);
	if (isElementVisible(element)) {
        var new_style = 'none';
    } else {
		// some browsers use 'inline' to display... We don't support that yet.
        //var new_style = getDisplayStyle(use_inline);
		var new_style = 'block';
    }
	element.style.display = new_style;
}
function isElementVisible(element) {
    if ((!element.style.display) || (element.style.display == getDisplayStyle())) {
        return true;
    } else {
        return false;
    }
}
function getDisplayStyle(use_inline){
 	// kind of hackish, but it works perfectly with IE6 and Mozilla 1.1
	if (use_inline == true) {
		return 'inline';
	} else {
		return 'block';
	}
}


// ----------------------------------------------------
// GET content from remote server and execute callback on success
// ----------------------------------------------------
function fetchURL(xURL, callback, xID) {
    var xhr = new XMLHttpRequest();
    
	xhr.timeout = 10000;  // 10s

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                // Successful response, invokes callback with the content
                loadContentIntoID(xID, xhr.responseText);
            } else {
                // Handle errors if needed
                console.error('Failed to fetch the page. Status:', xhr.status);
				loadContentIntoID(xID,'Error uploading files. Please try again.');
            }
        }
    };
    
    // xhr.open('POST', xURL, true);
	xhr.open('GET', xURL, true);
    xhr.send();

}
// load text (content) into a element ID
function loadContentIntoID(xID, xContent) {
    var xElement = document.getElementById(xID);
    if (xElement !== null) xElement.innerHTML = xContent;
}


// ----------------------------------------------------
// ----------------------------------------------------
function returnFileSize(number) {
	if(number < 1024) {
	  return number + 'bytes';
	} else if(number > 1024 && number < 1048576) {
	  return (number/1024).toFixed(1) + 'KB';
	} else if(number > 1048576) {
	  return (number/1048576).toFixed(1) + 'MB';
	}
}

