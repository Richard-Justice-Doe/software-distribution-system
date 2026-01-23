<?php
// upload_handler.php - NETWORK SHARE UPLOAD HANDLER
// This file should be in the root directory

// Start session to get database functions
session_start();

// Include database connection and functions
require_once 'index.php'; // This will give us access to database and functions

// Disable all output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON header IMMEDIATELY
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

// Check if it's a POST request with file
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    json_response(false, 'Invalid request');
}

// Initialize database connection
global $conn;
if (!$conn || $conn->connect_error) {
    json_response(false, 'Database connection failed');
}

try {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $version = trim($_POST['version'] ?? '');
    $os = trim($_POST['os'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validate required fields
    if (empty($name) || empty($version) || empty($os)) {
        throw new Exception('Please fill all required fields');
    }
    
    $file = $_FILES['file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
        ];
        throw new Exception($errors[$file['error']] ?? 'Upload error');
    }
    
    // Validate file
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Invalid file upload');
    }
    
    $filename = basename($file['name']);
    $fileSize = $file['size'];
    $tempFile = $file['tmp_name'];
    
    // Validate file extension
    $allowedExtensions = ['exe', 'msi', 'zip', 'deb', 'rar', 'tar', 'gz', 'bz2', '7z', 'iso', 'dmg'];
    $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedExtensions)) {
        throw new Exception("File type .{$fileExt} is not allowed");
    }
    
    // Validate file size (25GB limit)
    $maxSize = 25 * 1024 * 1024 * 1024;
    if ($fileSize > $maxSize) {
        throw new Exception('File exceeds 25GB limit');
    }
    
    // Check PHP upload limits
    $phpUploadMax = parseSizeToBytes(ini_get('upload_max_filesize'));
    $phpPostMax = parseSizeToBytes(ini_get('post_max_size'));
    $effectiveLimit = min($phpUploadMax, $phpPostMax);
    
    if ($fileSize > $effectiveLimit) {
        throw new Exception("File size exceeds PHP limit of " . formatBytes($effectiveLimit));
    }
    
    // Generate unique filename for network storage
    $uniqueName = uniqid('app_', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    // Save file to network share
    $finalPath = saveToNetworkShare($tempFile, $uniqueName);
    
    if (!$finalPath) {
        throw new Exception('Failed to save file to network share. Check server logs for details.');
    }
    
    // Verify file was saved
    if (!file_exists($finalPath) || filesize($finalPath) !== $fileSize) {
        unlink($finalPath);
        throw new Exception('File verification failed on network share');
    }
    
    // Format file size for display
    $fileSizeFormatted = formatBytes($fileSize);
    
    // Save to database
    $sql = "INSERT INTO applications (name, version, operating_system, description, 
            file_name, original_file_name, file_path, file_size) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Rollback: delete file from network share
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param('ssssssss', $name, $version, $os, $description,
        $uniqueName, $filename, $finalPath, $fileSizeFormatted);
    
    if (!$stmt->execute()) {
        // Rollback: delete file from network share
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }
        throw new Exception('Database save error: ' . $stmt->error);
    }
    
    $fileId = $stmt->insert_id;
    $stmt->close();
    
    // Success response
    json_response(true, 'File uploaded successfully to network share', [
        'fileId' => $fileId,
        'fileName' => $filename,
        'fileSize' => $fileSizeFormatted,
        'filePath' => $finalPath,
        'storageLocation' => 'Network Share (\\\\172.20.1.212\\public\\uploads\\)'
    ]);
    
} catch (Exception $e) {
    // Error response
    json_response(false, $e->getMessage());
}
?>