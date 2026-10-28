<?php
// upload.php - CHUNKED UPLOAD TO NETWORK SHARE

// If not an upload request, show the form
showUploadForm();

// ============================================================================
// FUNCTIONS
// ============================================================================

/**
 * Show upload form
 */
function showUploadForm() {
    // Get system limits
    $phpUploadMax = ini_get('upload_max_filesize');
    $phpPostMax = ini_get('post_max_size');
    $maxSizeReadable = '25GB';
    
    // Define allowed extensions
    $allowedExtensions = ['exe', 'msi', 'zip', 'deb', 'rar', 'tar', 'gz', 'bz2', '7z', 'iso', 'dmg'];
    
    // Test network share accessibility
    $networkShareAccessible = @is_dir(NETWORK_SHARE);
    ?>
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <!-- System Status -->
                <div class="alert alert-success mb-4">
                    <h6><i class="bi bi-check-circle"></i> Chunked Upload System Ready</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-0">
                                <li><strong>Max File Size:</strong> <?php echo $maxSizeReadable; ?></li>
                                <li><strong>Upload Method:</strong> Chunked (5MB chunks)</li>
                                <li><strong>Resumable:</strong> Yes</li>
                                <li><strong>Network Share:</strong> <?php echo $networkShareAccessible ? '✅ Accessible' : '❌ Not Accessible'; ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0">
                                <li><strong>PHP Upload Limit:</strong> <?php echo $phpUploadMax; ?></li>
                                <li><strong>PHP Post Limit:</strong> <?php echo $phpPostMax; ?></li>
                                <li><strong>Chunk Size:</strong> 5MB</li>
                                <li><strong>Auto-retry:</strong> 3 attempts per chunk</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-upload"></i> Upload New Application</h4>
                        <p class="mb-0">Files are uploaded in 5MB chunks for reliability</p>
                    </div>
                    <div class="card-body">
                        <?php if (!$networkShareAccessible): ?>
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-exclamation-triangle"></i> Network Share Warning</h6>
                            <p>The network share <code><?php echo htmlspecialchars(NETWORK_SHARE); ?></code> may not be accessible.</p>
                            <a href="?page=upload&debug=1" class="btn btn-sm btn-outline-warning">Test Connection</a>
                        </div>
                        <?php endif; ?>

                        <!-- Main Upload Form -->
                        <form id="uploadForm" method="POST" enctype="multipart/form-data">
                            <!-- Application Details -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Application Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required
                                        placeholder="e.g., Visual Studio, Adobe Creative Cloud" id="appName">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Version <span class="text-danger">*</span></label>
                                    <input type="text" name="version" class="form-control" required
                                        placeholder="e.g., 2024.1, 15.0.0" id="appVersion">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Operating System <span class="text-danger">*</span></label>
                                    <select name="os" class="form-select" required id="appOS">
                                        <option value="">Select OS</option>
                                        <option value="Windows">Windows</option>
                                        <option value="Linux">Linux</option>
                                        <option value="macOS">macOS</option>
                                        <option value="Multi-platform">Multi-platform</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-4">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"
                                    placeholder="Application description, installation notes, requirements..." id="appDescription"></textarea>
                            </div>

                            <!-- File Selection -->
                            <div class="mb-4">
                                <label class="form-label">Installer File <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="file" name="file" class="form-control" id="fileInput" required
                                        accept="<?php echo '.' . implode(',.', $allowedExtensions); ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="clearFileSelection()">
                                        <i class="bi bi-x-circle"></i> Clear
                                    </button>
                                </div>

                                <div class="form-text">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Allowed extensions:</strong><br>
                                            <small><?php echo implode(', ', $allowedExtensions); ?></small>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <strong>Max file size:</strong> <?php echo $maxSizeReadable; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Selected File Info -->
                                <div id="fileInfo" class="mt-3" style="display: none;">
                                    <div class="alert alert-secondary">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <strong><i class="bi bi-file-earmark"></i> Selected File:</strong><br>
                                                <span id="fileName" class="text-break"></span>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <strong>Size:</strong> <span id="fileSize"></span><br>
                                                <small><strong>Chunks:</strong> <span id="chunkCount">0</span></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Upload Progress -->
                                <div id="progressSection" class="mt-3" style="display: none;">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="bi bi-cloud-upload"></i> Chunked Upload Progress</h6>
                                            
                                            <!-- Progress Bar -->
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <small>Overall Progress</small>
                                                    <small><span id="uploadPercent">0%</span></small>
                                                </div>
                                                <div class="progress" style="height: 20px;">
                                                    <div id="uploadProgress" class="progress-bar progress-bar-striped progress-bar-animated" 
                                                         style="width: 0%"></div>
                                                </div>
                                            </div>

                                            <!-- Upload Details -->
                                            <div id="progressDetails" class="small">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        Status: <span id="progressStatus" class="badge bg-info">Ready</span>
                                                    </div>
                                                    <div class="col-md-3 text-center">
                                                        Chunk: <span id="currentChunk">0</span>/<span id="totalChunks">0</span>
                                                    </div>
                                                    <div class="col-md-3 text-center">
                                                        Speed: <span id="uploadSpeed">-</span>
                                                    </div>
                                                    <div class="col-md-3 text-end">
                                                        Time: <span id="uploadTime">-</span>
                                                    </div>
                                                </div>
                                                <div class="row mt-2">
                                                    <div class="col-md-12">
                                                        <span id="uploadedSize">0 B</span> of <span id="totalSize">0 B</span>
                                                        <div class="progress mt-1" style="height: 5px;">
                                                            <div id="chunkProgress" class="progress-bar bg-warning" style="width: 0%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row mt-2">
                                                    <div class="col-md-12">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" id="autoRetry" checked>
                                                            <label class="form-check-label" for="autoRetry">Auto-retry failed chunks</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                <div>
                                    <a href="?page=applications" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Back
                                    </a>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-danger me-2" onclick="resetForm()">
                                        <i class="bi bi-trash"></i> Reset
                                    </button>
                                    <button type="button" class="btn btn-primary btn-lg" id="uploadBtn" onclick="startUpload()">
                                        <i class="bi bi-upload"></i> Start Upload
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Upload Instructions -->
                        <div class="alert alert-light mt-4">
                            <h6><i class="bi bi-info-circle"></i> How Chunked Upload Works:</h6>
                            <ol class="mb-0">
                                <li>Select your application file (up to 25GB)</li>
                                <li>Fill in the application details</li>
                                <li>Click "Start Upload" - file is split into 5MB chunks</li>
                                <li>Each chunk is uploaded separately</li>
                                <li>Failed chunks are automatically retried (max 3 times)</li>
                                <li>Chunks are reassembled on the server</li>
                                <li>Final file is saved to network share</li>
                                <li>File information is saved to database</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    // Global variables for chunked upload
    let file = null;
    let uploadStartTime = null;
    let isUploading = false;
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB chunks
    let currentChunk = 0;
    let totalChunks = 0;
    let fileIdentifier = ''; // letters, digits and underscores only - the server rejects anything else
    let retryCount = 0;
    const MAX_RETRIES = 3;
    let uploadStats = {
        chunksCompleted: 0,
        bytesUploaded: 0,
        startTime: null
    };

    // Format bytes to human readable format
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    // Format time
    function formatTime(seconds) {
        if (seconds < 60) {
            return Math.round(seconds) + 's';
        } else if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            const secs = Math.round(seconds % 60);
            return minutes + 'm ' + secs + 's';
        } else {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            return hours + 'h ' + minutes + 'm';
        }
    }

    // Generate unique identifier for file
    function generateFileIdentifier() {
        return 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    // Show file information
    function showFileInfo(selectedFile) {
        file = selectedFile;
        totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        
        // Update UI
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = formatBytes(file.size);
        document.getElementById('chunkCount').textContent = totalChunks + ' chunks';
        document.getElementById('fileInfo').style.display = 'block';
        document.getElementById('totalChunks').textContent = totalChunks;
        document.getElementById('totalSize').textContent = formatBytes(file.size);
        
        // Show upload button
        document.getElementById('uploadBtn').disabled = false;
        
        console.log(`File selected: ${file.name} (${formatBytes(file.size)}), ${totalChunks} chunks`);
    }

    // Show notification
    function showNotification(message, type = 'info') {
        const alertClass = {
            'info': 'alert-info',
            'success': 'alert-success',
            'warning': 'alert-warning',
            'error': 'alert-danger'
        }[type] || 'alert-info';

        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} alert-dismissible fade show mt-3`;
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.querySelector('.card-body').prepend(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // Update progress display
    function updateProgress() {
        if (!file || !uploadStats.startTime) return;
        
        const percentComplete = (uploadStats.bytesUploaded / file.size) * 100;
        const elapsedTime = (Date.now() - uploadStats.startTime) / 1000;
        
        // Update overall progress
        document.getElementById('uploadProgress').style.width = percentComplete + '%';
        document.getElementById('uploadProgress').textContent = Math.round(percentComplete) + '%';
        document.getElementById('uploadPercent').textContent = Math.round(percentComplete) + '%';
        document.getElementById('uploadedSize').textContent = formatBytes(uploadStats.bytesUploaded);
        
        // Update chunk progress
        const chunkPercent = (currentChunk / totalChunks) * 100;
        document.getElementById('chunkProgress').style.width = chunkPercent + '%';
        document.getElementById('currentChunk').textContent = currentChunk + 1;
        
        // Calculate speed and ETA
        if (elapsedTime > 0) {
            const uploadSpeed = uploadStats.bytesUploaded / elapsedTime;
            document.getElementById('uploadSpeed').textContent = formatBytes(uploadSpeed) + '/s';
            
            if (uploadSpeed > 0) {
                const remainingBytes = file.size - uploadStats.bytesUploaded;
                const eta = remainingBytes / uploadSpeed;
                document.getElementById('uploadTime').textContent = formatTime(eta);
            }
        }
    }

    // Upload a single chunk
    function uploadChunk(chunkIndex) {
        const start = chunkIndex * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const chunk = file.slice(start, end);
        
        console.log(`Uploading chunk ${chunkIndex + 1}/${totalChunks} (${formatBytes(chunk.size)})`);
        document.getElementById('progressStatus').textContent = `Uploading chunk ${chunkIndex + 1}/${totalChunks}`;
        document.getElementById('progressStatus').className = 'badge bg-primary';
        
        // Create FormData for chunk
        const formData = new FormData();
        formData.append('chunkNumber', chunkIndex);
        formData.append('totalChunks', totalChunks);
        formData.append('fileSize', file.size);
        formData.append('fileName', file.name);
        formData.append('identifier', fileIdentifier);
        formData.append('csrf_token', <?php echo json_encode(csrf_token()); ?>);

        // Add form data
        formData.append('name', document.getElementById('appName').value);
        formData.append('version', document.getElementById('appVersion').value);
        formData.append('os', document.getElementById('appOS').value);
        formData.append('description', document.getElementById('appDescription').value);
        
        // Add chunk file
        formData.append('chunk', chunk, `chunk_${chunkIndex}`);
        
        // Create XMLHttpRequest
        const xhr = new XMLHttpRequest();
        
        // Relative to index.php, which renders this page
        xhr.open('POST', 'pages/chunked_upload_handler.php');
        
        // In the xhr.onload handler, update this section:
xhr.onload = function() {
    try {
        const response = JSON.parse(xhr.responseText);
        
        if (response.success) {
            retryCount = 0; // Reset retry count on success
            
            // Update stats
            uploadStats.chunksCompleted++;
            uploadStats.bytesUploaded += chunk.size;
            
            if (chunkIndex === totalChunks - 1) {
                // Last chunk uploaded - server will assemble
                console.log('All chunks uploaded! Server is assembling file...');
                document.getElementById('progressStatus').textContent = 'Assembling file...';
                document.getElementById('progressStatus').className = 'badge bg-warning';
                
                // The server response already indicates success, so we're done
                document.getElementById('progressStatus').textContent = 'Upload complete!';
                document.getElementById('progressStatus').className = 'badge bg-success';
                document.getElementById('uploadProgress').className = 'progress-bar bg-success';
                document.getElementById('uploadProgress').textContent = 'Complete!';
                
                showNotification('✅ File uploaded successfully to network share!', 'success');
                
                // Redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = '?page=applications';
                }, 2000);
            } else {
                // Upload next chunk
                currentChunk = response.data.nextChunk || currentChunk + 1;
                updateProgress();
                
                // Small delay between chunks
                setTimeout(() => {
                    uploadChunk(currentChunk);
                }, 100);
            }
        } else {
            throw new Error(response.message || 'Chunk upload failed');
        }
    } catch (error) {
        console.error(`Chunk ${chunkIndex + 1} upload error:`, error);
        
        if (retryCount < MAX_RETRIES && document.getElementById('autoRetry').checked) {
            retryCount++;
            console.log(`Retrying chunk ${chunkIndex + 1} (attempt ${retryCount}/${MAX_RETRIES})...`);
            document.getElementById('progressStatus').textContent = `Retrying chunk ${chunkIndex + 1} (${retryCount}/${MAX_RETRIES})`;
            document.getElementById('progressStatus').className = 'badge bg-warning';
            
            // Retry after delay
            setTimeout(() => {
                uploadChunk(chunkIndex);
            }, 2000);
        } else {
            document.getElementById('progressStatus').textContent = 'Upload failed';
            document.getElementById('progressStatus').className = 'badge bg-danger';
            showNotification(`Upload failed on chunk ${chunkIndex + 1}: ${error.message}`, 'error');
            resetFormState();
        }
    }
};
        
        xhr.onerror = function() {
            console.error(`Network error on chunk ${chunkIndex + 1}`);
            
            if (retryCount < MAX_RETRIES && document.getElementById('autoRetry').checked) {
                retryCount++;
                console.log(`Retrying chunk ${chunkIndex + 1} after network error (attempt ${retryCount}/${MAX_RETRIES})...`);
                document.getElementById('progressStatus').textContent = `Network error, retrying... (${retryCount}/${MAX_RETRIES})`;
                document.getElementById('progressStatus').className = 'badge bg-warning';
                
                // Retry after delay
                setTimeout(() => {
                    uploadChunk(chunkIndex);
                }, 3000);
            } else {
                document.getElementById('progressStatus').textContent = 'Network error';
                document.getElementById('progressStatus').className = 'badge bg-danger';
                showNotification('Network error occurred during upload. Please try again.', 'error');
                resetFormState();
            }
        };
        
        xhr.ontimeout = function() {
            console.error(`Timeout on chunk ${chunkIndex + 1}`);
            
            if (retryCount < MAX_RETRIES && document.getElementById('autoRetry').checked) {
                retryCount++;
                console.log(`Retrying chunk ${chunkIndex + 1} after timeout (attempt ${retryCount}/${MAX_RETRIES})...`);
                document.getElementById('progressStatus').textContent = `Timeout, retrying... (${retryCount}/${MAX_RETRIES})`;
                document.getElementById('progressStatus').className = 'badge bg-warning';
                
                // Retry after longer delay
                setTimeout(() => {
                    uploadChunk(chunkIndex);
                }, 5000);
            } else {
                document.getElementById('progressStatus').textContent = 'Upload timeout';
                document.getElementById('progressStatus').className = 'badge bg-danger';
                showNotification('Upload timed out. Please try again.', 'error');
                resetFormState();
            }
        };
        
        // Set timeout (60 seconds per chunk)
        xhr.timeout = 60000;
        
        // Track upload progress for this chunk
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                // Update chunk progress bar
                const chunkPercent = (e.loaded / e.total) * 100;
                document.getElementById('chunkProgress').style.width = chunkPercent + '%';
            }
        };
        
        // Send chunk
        xhr.send(formData);
    }

    // Start the upload
    function startUpload() {
        // Validate form first
        const name = document.getElementById('appName').value;
        const version = document.getElementById('appVersion').value;
        const os = document.getElementById('appOS').value;
        
        if (!name || !version || !os) {
            showNotification('Please fill all required fields.', 'warning');
            return;
        }
        
        if (!file) {
            showNotification('Please select a file first.', 'warning');
            return;
        }
        
        if (isUploading) {
            showNotification('Upload is already in progress.', 'warning');
            return;
        }
        
        if (!confirm(`Upload "${file.name}" (${formatBytes(file.size)}) in ${totalChunks} chunks?`)) {
            return;
        }
        
        // Initialize upload
        isUploading = true;
        currentChunk = 0;
        retryCount = 0;
        fileIdentifier = generateFileIdentifier();
        uploadStats = {
            chunksCompleted: 0,
            bytesUploaded: 0,
            startTime: Date.now()
        };
        
        // Show progress section
        document.getElementById('progressSection').style.display = 'block';
        document.getElementById('uploadBtn').disabled = true;
        document.getElementById('progressStatus').textContent = 'Initializing upload...';
        document.getElementById('progressStatus').className = 'badge bg-info';
        
        // Disable form inputs
        document.querySelectorAll('#uploadForm input, #uploadForm select, #uploadForm textarea, #uploadForm button').forEach(el => {
            if (el.id !== 'autoRetry') {
                el.disabled = true;
            }
        });
        
        console.log(`Starting chunked upload of ${file.name} (${formatBytes(file.size)})`);
        console.log(`File identifier: ${fileIdentifier}`);
        console.log(`Total chunks: ${totalChunks}`);
        
        // Start uploading first chunk
        setTimeout(() => {
            uploadChunk(currentChunk);
        }, 500);
    }

    // Reset form state after upload (success or failure)
    function resetFormState() {
        isUploading = false;
        document.getElementById('uploadBtn').disabled = false;
        document.querySelectorAll('#uploadForm input, #uploadForm select, #uploadForm textarea, #uploadForm button').forEach(el => {
            el.disabled = false;
        });
    }

    // Event Listeners
    document.getElementById('fileInput').addEventListener('change', function(e) {
        const selectedFile = this.files[0];
        if (selectedFile) {
            // Validate file extension
            const allowedExtensions = <?php echo json_encode($allowedExtensions); ?>;
            const fileExt = selectedFile.name.split('.').pop().toLowerCase();
            
            if (!allowedExtensions.includes(fileExt)) {
                showNotification(`File type .${fileExt} is not allowed.`, 'error');
                this.value = '';
                return;
            }
            
            // Validate file size (25GB limit)
            const maxSize = 25 * 1024 * 1024 * 1024; // 25GB in bytes
            if (selectedFile.size > maxSize) {
                showNotification('File size exceeds maximum limit of 25GB.', 'error');
                this.value = '';
                return;
            }
            
            showFileInfo(selectedFile);
            showNotification(`"${selectedFile.name}" selected (${formatBytes(selectedFile.size)}) - Will be uploaded in ${Math.ceil(selectedFile.size / CHUNK_SIZE)} chunks`, 'success');
        } else {
            clearFileSelection();
        }
    });

    // Clear file selection
    function clearFileSelection() {
        document.getElementById('fileInput').value = '';
        document.getElementById('fileInfo').style.display = 'none';
        document.getElementById('progressSection').style.display = 'none';
        file = null;
        isUploading = false;
        document.getElementById('uploadBtn').disabled = false;
        currentChunk = 0;
        totalChunks = 0;
        retryCount = 0;
        console.log("File selection cleared");
    }

    // Reset form
    function resetForm() {
        if (isUploading && !confirm('Upload in progress. Cancel and reset?')) {
            return;
        }
        
        if (confirm('Reset form? All entered data will be lost.')) {
            document.getElementById('uploadForm').reset();
            clearFileSelection();
            console.log("Form reset");
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        console.log("Upload page loaded with chunked upload system");
        console.log("Chunk size: " + formatBytes(CHUNK_SIZE));
        console.log("PHP Upload Limit: <?php echo ini_get('upload_max_filesize'); ?>");
        console.log("PHP Post Limit: <?php echo ini_get('post_max_size'); ?>");
        
        <?php if (!$networkShareAccessible): ?>
        showNotification('Network share may not be accessible. Uploads may fail.', 'warning');
        <?php endif; ?>
    });
    </script>
    <?php
}
?>