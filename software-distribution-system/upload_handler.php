// Start the upload - UPDATED VERSION
function startUpload() {
    isUploading = true;
    uploadStartTime = Date.now();
    
    // Show progress section
    document.getElementById('progressSection').style.display = 'block';
    document.getElementById('uploadBtn').disabled = true;
    document.getElementById('progressStatus').textContent = 'Starting upload to network share...';
    
    // Disable form inputs
    document.querySelectorAll('#uploadForm input, #uploadForm select, #uploadForm textarea, #uploadForm button').forEach(el => {
        el.disabled = true;
    });
    
    // Create FormData
    const formData = new FormData(document.getElementById('uploadForm'));
    
    // Log FormData contents for debugging
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + (pair[0] === 'file' ? pair[1].name : pair[1]));
    }
    
    // Create XMLHttpRequest for progress tracking
    const xhr = new XMLHttpRequest();
    
    // Track upload progress
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percent = (e.loaded / e.total) * 100;
            updateProgress(percent, e.loaded, e.total);
            document.getElementById('progressStatus').textContent = 'Uploading to network share...';
        }
    });
    
    // Handle completion
    xhr.addEventListener('load', function() {
        console.log("Response received:", xhr.responseText);
        try {
            const response = JSON.parse(xhr.responseText);
            
            if (response.success) {
                document.getElementById('progressStatus').textContent = 'Upload complete!';
                document.getElementById('uploadProgress').className = 'progress-bar bg-success';
                document.getElementById('uploadProgress').textContent = 'Complete!';
                
                showNotification('✅ File uploaded successfully to network share!', 'success');
                
                // Redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = '?page=applications';
                }, 3000);
            } else {
                throw new Error(response.message || 'Upload failed');
            }
        } catch (error) {
            console.error("Error parsing response:", error, "Raw response:", xhr.responseText);
            document.getElementById('progressStatus').textContent = 'Upload failed';
            showNotification('Upload failed: ' + error.message + '. Check browser console for details.', 'error');
            resetFormState();
        }
    });
    
    // Handle errors
    xhr.addEventListener('error', function() {
        console.error("XHR error occurred");
        document.getElementById('progressStatus').textContent = 'Network error';
        showNotification('Network error occurred during upload. Check console.', 'error');
        resetFormState();
    });
    
    // Handle timeout
    xhr.addEventListener('timeout', function() {
        console.error("Upload timeout");
        document.getElementById('progressStatus').textContent = 'Upload timeout';
        showNotification('Upload timed out. Please try again.', 'error');
        resetFormState();
    });
    
    // Set timeout (5 minutes for large files)
    xhr.timeout = 300000;
    
    // Open and send request
    xhr.open('POST', 'upload_handler.php');
    xhr.send(formData);
}