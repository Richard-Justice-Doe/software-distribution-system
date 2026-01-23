<?php
// chunked_upload_handler.php - IMPROVED VERSION FOR LARGE FILES

// DISABLE EVERYTHING that might output HTML
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Enable detailed logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload_chunks.log');

// Increase limits for large files
set_time_limit(0);
ignore_user_abort(true);

// Clear output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON header FIRST
header('Content-Type: application/json; charset=utf-8');

// Function to send JSON response
function json_response($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Log the request
error_log("=== CHUNKED UPLOAD HANDLER CALLED ===");
error_log("Time: " . date('Y-m-d H:i:s'));
error_log("Memory usage: " . formatBytes(memory_get_usage()));

try {
    // Check if this is a chunk upload
    $chunkNumber = (int)($_POST['chunkNumber'] ?? -1);
    $totalChunks = (int)($_POST['totalChunks'] ?? 0);
    $fileSize = (int)($_POST['fileSize'] ?? 0);
    $fileName = $_POST['fileName'] ?? '';
    $fileIdentifier = $_POST['identifier'] ?? '';
    
    error_log("Chunk info: $chunkNumber/$totalChunks, File: $fileName, Size: " . formatBytes($fileSize));
    
    if (empty($fileName) || empty($fileIdentifier) || $chunkNumber < 0) {
        error_log("ERROR: Missing required parameters");
        json_response(false, 'Missing required parameters');
    }
    
    // Validate chunk number
    if ($chunkNumber >= $totalChunks) {
        error_log("ERROR: Invalid chunk number: $chunkNumber >= $totalChunks");
        json_response(false, 'Invalid chunk number');
    }
    
    // Create chunks directory if it doesn't exist
    $chunksDir = __DIR__ . '/chunks/' . $fileIdentifier . '/';
    if (!file_exists($chunksDir)) {
        if (!mkdir($chunksDir, 0777, true)) {
            error_log("ERROR: Cannot create chunks directory: " . $chunksDir);
            json_response(false, 'Cannot create temporary directory');
        }
        error_log("Created chunks directory: " . $chunksDir);
    }
    
    // If this is NOT the last chunk, just save it
    if ($chunkNumber !== $totalChunks - 1) {
        // Check if file chunk was uploaded
        if (!isset($_FILES['chunk'])) {
            error_log("ERROR: No chunk file uploaded for chunk $chunkNumber");
            json_response(false, 'No file chunk received');
        }
        
        $chunkFile = $_FILES['chunk'];
        
        // Check for upload errors
        if ($chunkFile['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = getUploadErrorMessage($chunkFile['error']);
            error_log("ERROR: Chunk $chunkNumber upload error: $errorMsg");
            json_response(false, $errorMsg);
        }
        
        // Validate chunk
        if (!is_uploaded_file($chunkFile['tmp_name'])) {
            error_log("ERROR: Invalid chunk upload for chunk $chunkNumber");
            json_response(false, 'Invalid chunk upload');
        }
        
        // Save chunk with a unique name to prevent overwrites
        $chunkPath = $chunksDir . 'chunk_' . str_pad($chunkNumber, 6, '0', STR_PAD_LEFT);
        
        // Move uploaded file
        if (!move_uploaded_file($chunkFile['tmp_name'], $chunkPath)) {
            $error = error_get_last();
            error_log("ERROR: Failed to save chunk $chunkNumber to: $chunkPath - " . ($error['message'] ?? 'Unknown error'));
            json_response(false, 'Failed to save chunk');
        }
        
        $chunkSize = filesize($chunkPath);
        error_log("Chunk $chunkNumber saved: $chunkPath (" . formatBytes($chunkSize) . ")");
        
        // Count chunks we have so far
        $chunksFound = countChunksInDirectory($chunksDir);
        
        json_response(true, 'Chunk uploaded successfully', [
            'chunkNumber' => $chunkNumber,
            'totalChunks' => $totalChunks,
            'receivedChunks' => $chunksFound,
            'nextChunk' => $chunkNumber + 1
        ]);
        
    } else {
        // THIS IS THE LAST CHUNK - Save it first, then assemble
        error_log("LAST CHUNK received: $chunkNumber/$totalChunks");
        
        // Save the last chunk
        if (isset($_FILES['chunk'])) {
            $chunkFile = $_FILES['chunk'];
            
            if ($chunkFile['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = getUploadErrorMessage($chunkFile['error']);
                error_log("ERROR: Last chunk upload error: $errorMsg");
                json_response(false, $errorMsg);
            }
            
            if (!is_uploaded_file($chunkFile['tmp_name'])) {
                error_log("ERROR: Invalid last chunk upload");
                json_response(false, 'Invalid chunk upload');
            }
            
            $chunkPath = $chunksDir . 'chunk_' . str_pad($chunkNumber, 6, '0', STR_PAD_LEFT);
            
            if (!move_uploaded_file($chunkFile['tmp_name'], $chunkPath)) {
                $error = error_get_last();
                error_log("ERROR: Failed to save last chunk: " . ($error['message'] ?? 'Unknown error'));
                json_response(false, 'Failed to save last chunk');
            }
            
            error_log("Last chunk saved: $chunkPath (" . formatBytes(filesize($chunkPath)) . ")");
        }
        
        // Now assemble the file
        error_log("Assembling file from chunks...");
        
        // Check if we have all chunks
        $chunksFound = countChunksInDirectory($chunksDir);
        error_log("Chunks found: $chunksFound, Expected: $totalChunks");
        
        if ($chunksFound !== $totalChunks) {
            error_log("ERROR: Missing chunks. Found: $chunksFound, Expected: $totalChunks");
            
            // List missing chunks
            $missingChunks = [];
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $chunksDir . 'chunk_' . str_pad($i, 6, '0', STR_PAD_LEFT);
                if (!file_exists($chunkPath)) {
                    $missingChunks[] = $i;
                }
            }
            error_log("Missing chunks: " . implode(', ', $missingChunks));
            
            json_response(false, "Missing chunks. Received $chunksFound of $totalChunks chunks");
        }
        
        // Verify chunk sizes before assembly
        error_log("Verifying chunk sizes...");
        $totalSize = 0;
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = $chunksDir . 'chunk_' . str_pad($i, 6, '0', STR_PAD_LEFT);
            if (!file_exists($chunkPath)) {
                error_log("ERROR: Chunk $i missing during verification");
                json_response(false, "Chunk $i is missing");
            }
            $chunkSize = filesize($chunkPath);
            $totalSize += $chunkSize;
            error_log("Chunk $i size: " . formatBytes($chunkSize));
        }
        
        error_log("Total chunks size: " . formatBytes($totalSize) . ", Expected: " . formatBytes($fileSize));
        
        if ($totalSize !== $fileSize) {
            error_log("ERROR: Size mismatch. Expected: $fileSize, Actual: $totalSize");
            json_response(false, "File size mismatch. Expected: " . formatBytes($fileSize) . ", Actual: " . formatBytes($totalSize));
        }
        
        // Assemble the file using STREAMING to save memory
        error_log("Starting file assembly using streaming...");
        $assembledFilePath = assembleFileStreaming($chunksDir, $fileName, $totalChunks, $fileSize);
        
        if (!$assembledFilePath) {
            error_log("ERROR: File assembly failed");
            json_response(false, 'Failed to assemble file from chunks');
        }
        
        error_log("File assembled successfully: " . $assembledFilePath . " (" . formatBytes(filesize($assembledFilePath)) . ")");
        
        // Now save to network share and database
        error_log("Processing assembled file...");
        $result = processAssembledFile($assembledFilePath, $fileName, $fileSize, $_POST);
        
        if ($result['success']) {
            // Clean up chunks
            cleanupChunks($chunksDir);
            
            // Clean up assembled temp file
            if (file_exists($assembledFilePath)) {
                unlink($assembledFilePath);
            }
            
            error_log("File processing completed successfully!");
            json_response(true, 'File uploaded successfully to network share', [
                'fileId' => $result['fileId'],
                'fileName' => $fileName,
                'fileSize' => formatBytes($fileSize),
                'filePath' => $result['finalPath']
            ]);
        } else {
            error_log("ERROR: File processing failed: " . $result['message']);
            json_response(false, $result['message']);
        }
    }
    
} catch (Exception $e) {
    error_log("EXCEPTION in upload handler: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    json_response(false, 'Server error: ' . $e->getMessage());
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function getUploadErrorMessage($errorCode) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'Chunk too large (server limit)',
        UPLOAD_ERR_FORM_SIZE => 'Chunk too large (form limit)',
        UPLOAD_ERR_PARTIAL => 'Chunk only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No chunk uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write chunk to disk',
        UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
    ];
    return $errors[$errorCode] ?? "Upload error (code: $errorCode)";
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function countChunksInDirectory($dir) {
    if (!file_exists($dir)) {
        return 0;
    }
    
    $count = 0;
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && strpos($file, 'chunk_') === 0) {
            $count++;
        }
    }
    
    return $count;
}

function assembleFileStreaming($chunksDir, $fileName, $totalChunks, $expectedSize) {
    error_log("Streaming assembly started for $fileName");
    
    // Create a temporary file in system temp directory
    $tempDir = sys_get_temp_dir();
    $tempFilePath = $tempDir . '/assembled_' . uniqid() . '_' . $fileName;
    
    error_log("Creating temp file: $tempFilePath");
    
    // Open temp file for writing
    $tempFile = fopen($tempFilePath, 'wb');
    if (!$tempFile) {
        error_log("ERROR: Cannot open temp file for writing: $tempFilePath");
        return false;
    }
    
    // Process chunks in order
    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkPath = $chunksDir . 'chunk_' . str_pad($i, 6, '0', STR_PAD_LEFT);
        
        if (!file_exists($chunkPath)) {
            error_log("ERROR: Missing chunk $i at $chunkPath");
            fclose($tempFile);
            unlink($tempFilePath);
            return false;
        }
        
        // Open chunk file
        $chunkFile = fopen($chunkPath, 'rb');
        if (!$chunkFile) {
            error_log("ERROR: Cannot open chunk $i for reading: $chunkPath");
            fclose($tempFile);
            unlink($tempFilePath);
            return false;
        }
        
        // Stream chunk content to temp file
        while (!feof($chunkFile)) {
            $buffer = fread($chunkFile, 8192); // 8KB chunks for streaming
            fwrite($tempFile, $buffer);
        }
        
        fclose($chunkFile);
        
        // Delete chunk immediately to save space
        unlink($chunkPath);
        
        // Log progress every 25 chunks
        if ($i % 25 === 0 || $i === $totalChunks - 1) {
            error_log("Assembly progress: " . ($i + 1) . "/$totalChunks chunks processed");
        }
    }
    
    fclose($tempFile);
    
    // Verify final file size
    $actualSize = filesize($tempFilePath);
    if ($actualSize !== $expectedSize) {
        error_log("ERROR: Size mismatch after assembly. Expected: $expectedSize, Actual: $actualSize");
        unlink($tempFilePath);
        return false;
    }
    
    error_log("File assembly completed successfully: $tempFilePath (" . formatBytes($actualSize) . ")");
    return $tempFilePath;
}

function cleanupChunks($chunksDir) {
    if (!file_exists($chunksDir)) {
        return;
    }
    
    $files = scandir($chunksDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $chunksDir . $file;
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }
    }
    
    rmdir($chunksDir);
    error_log("Cleaned up chunks directory: $chunksDir");
}

function processAssembledFile($tempFilePath, $originalFileName, $fileSize, $formData) {
    error_log("Processing assembled file: $tempFilePath");
    
    // Database configuration
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'software_distribution';
    
    // Create database connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        error_log("ERROR: Database connection failed: " . $conn->connect_error);
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    $conn->set_charset("utf8mb4");
    
    // Network share path
    $networkShare = '\\\\172.20.1.212\\public\\uploads\\';
    
    // Check network share
    if (!@is_dir($networkShare)) {
        error_log("ERROR: Network share not accessible: $networkShare");
        
        // Try to create local backup directory
        $localBackup = __DIR__ . '/uploads_backup/';
        if (!file_exists($localBackup)) {
            mkdir($localBackup, 0777, true);
        }
        $networkShare = $localBackup;
        error_log("Using local backup: $networkShare");
    }
    
    // Generate unique filename
    $uniqueName = uniqid('app_', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalFileName);
    $finalPath = $networkShare . $uniqueName;
    
    error_log("Copying to: $finalPath");
    
    // Copy using chunked copy for large files
    if (!copyFileChunked($tempFilePath, $finalPath)) {
        $error = error_get_last();
        error_log("ERROR: Failed to copy to destination: " . ($error['message'] ?? 'Unknown error'));
        $conn->close();
        return ['success' => false, 'message' => 'Failed to save file to destination'];
    }
    
    // Verify copy
    if (!file_exists($finalPath) || filesize($finalPath) !== $fileSize) {
        error_log("ERROR: File verification failed. Expected: $fileSize, Actual: " . (file_exists($finalPath) ? filesize($finalPath) : 'File missing'));
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }
        $conn->close();
        return ['success' => false, 'message' => 'File verification failed'];
    }
    
    error_log("File copied successfully to: $finalPath");
    
    // Get form data
    $name = trim($formData['name'] ?? '');
    $version = trim($formData['version'] ?? '');
    $os = trim($formData['os'] ?? '');
    $description = trim($formData['description'] ?? '');
    
    // Validate required fields
    if (empty($name) || empty($version) || empty($os)) {
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }
        $conn->close();
        return ['success' => false, 'message' => 'Missing required application information'];
    }
    
    // Save to database
    $fileSizeFormatted = formatBytes($fileSize);
    $sql = "INSERT INTO applications (name, version, operating_system, description, 
            file_name, original_file_name, file_path, file_size) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("ERROR: Database prepare failed: " . $conn->error);
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }
        $conn->close();
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $stmt->bind_param('ssssssss', $name, $version, $os, $description,
        $uniqueName, $originalFileName, $finalPath, $fileSizeFormatted);
    
    if (!$stmt->execute()) {
        error_log("ERROR: Database execute failed: " . $stmt->error);
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Database save error: ' . $stmt->error];
    }
    
    $fileId = $stmt->insert_id;
    $stmt->close();
    $conn->close();
    
    error_log("File saved to database successfully. ID: $fileId");
    
    return [
        'success' => true,
        'fileId' => $fileId,
        'finalPath' => $finalPath,
        'message' => 'File processed successfully'
    ];
}

function copyFileChunked($source, $destination) {
    // For large files, copy in chunks
    $chunkSize = 10 * 1024 * 1024; // 10MB chunks
    
    $sourceHandle = fopen($source, 'rb');
    if (!$sourceHandle) {
        return false;
    }
    
    $destHandle = fopen($destination, 'wb');
    if (!$destHandle) {
        fclose($sourceHandle);
        return false;
    }
    
    while (!feof($sourceHandle)) {
        $chunk = fread($sourceHandle, $chunkSize);
        fwrite($destHandle, $chunk);
    }
    
    fclose($sourceHandle);
    fclose($destHandle);
    
    return true;
}
?>