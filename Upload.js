
const input   = document.querySelector('input');
const preview = document.querySelector('.preview');
var   winParent = window.opener;

input.style.opacity = 0;

input.addEventListener('change', updateImageDisplay);

function submitUpload() {
	uploadFiles();
}

function submitCancel() {
	var myDiv = null;
	hideBlock('buttonSubmit');
	hideBlock('status');
	myDiv = document.getElementById('status');
	myDiv.innerHTML = "";
	myDiv = document.getElementById('uploadFiles');
	mydiv.innerHTML = "";
	
}

function submitClose() {
	
	window.close();
	if (window.opener && !window.opener.closed) {
		window.opener.location.reload();
	}
	
}


function updateImageDisplay() {
  
  while(preview.firstChild) {
	preview.removeChild(preview.firstChild);
  }

  const curFiles = input.files;
  
  if(curFiles.length === 0) {
	const para = document.createElement('p');
	para.textContent = 'No files selected';
	preview.appendChild(para);
	hideBlock('buttonSubmit');
	
  } else {
	const list = document.createElement('ol');
	preview.appendChild(list);

	for(const file of curFiles) {
	  const listItem = document.createElement('li');
	  const para     = document.createElement('p');

	  if(validFileType(file)) {
		para.textContent = `${file.name}, size: ${returnFileSize(file.size)}`;
		const image      = document.createElement('img');
		image.src        = URL.createObjectURL(file);

		listItem.appendChild(image);
		listItem.appendChild(para);
	  } else {
		
		// para.textContent = `File name ${file.name}: Not a valid file type. Update your selection.`;
		para.textContent = `${file.name}, size: ${returnFileSize(file.size)} `;
		listItem.appendChild(para);
	  }

	  list.appendChild(listItem);
	}
	
	showBlock('buttonSubmit');
  }
}

// https://developer.mozilla.org/en-US/docs/Web/Media/Formats/Image_types
const fileTypes = [
	'image/apng',
	'image/bmp',
	'image/gif',
	'image/jpeg',
	'image/pjpeg',
	'image/png',
	'image/svg+xml',
	'image/tiff',
	'image/webp',
	`image/x-icon`
];

function validFileType(file) {
  return fileTypes.includes(file.type);
}

function returnFileSize(number) {
  if(number < 1024) {
	return number + 'bytes';
  } else if(number > 1024 && number < 1048576) {
	return (number/1024).toFixed(1) + 'KB';
  } else if(number > 1048576) {
	return (number/1048576).toFixed(1) + 'MB';
  }
}

function showBlock(pstrID){
  var myDiv = document.getElementById(pstrID);
  if (myDiv){
	myDiv.style.display = 'inline-block';
  }
}

function hideBlock(pstrID, istrID){
  var myDiv = document.getElementById(pstrID);
  if (myDiv){
	myDiv.style.display = 'none';
  }
}

// ------------------------------------------------------
// ------------------------------------------------------
function uploadFiles() {
    const timeoutDuration = 10000;                       // milliseconds
	var formData          = new FormData();
	
	var uploadKey     = document.getElementById('uploadKey');
   	if (uploadKey !== null) {
		formData.append('uploadKey',uploadKey.value);
	}
	
    var files    = document.getElementById('uploadFiles').files;
    for (var i = 0; i < files.length; i++) {
      
	  var file     = files[i];
      var fileSize = file.size;
      var fileType = file.type;  
  
      // Check file size and type
      if (fileSize > 1024 * 1024) { // Limit file size to 1MB
        alert('File size exceeds the limit (1MB).');
        return;
      }
	  
      if (!['image/png', 'image/jpg', 'image/jpeg', 'image/gif', 'text/plain', 'application/pdf', 'application/msword', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'].includes(fileType)) {
        alert('Invalid file type. Only .txt, .pdf, .doc, and .docx are allowed.');
        return;
      }
  
      formData.append('files[]', file);
    }
  
	// ------------------------------------------------------
	// See:  https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest_API/Using_XMLHttpRequest
    // ------------------------------------------------------
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'Upload.php', true);
    xhr.timeout = timeoutDuration;
	
	xhr.onload = function() {
      if (xhr.status === 200) {
        document.getElementById('status').innerHTML = xhr.responseText;
      } else {
        document.getElementById('status').innerHTML = 'Error uploading files. Please try again.';
      }
    };
	xhr.onerror = function () {
		console.error('Request failed');
	};
	xhr.ontimeout = function () {
		console.error('Request timed out');
	};
	
	xhr.send(formData);
	
  }
 
  