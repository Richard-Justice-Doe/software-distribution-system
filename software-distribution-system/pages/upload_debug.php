<?php
// upload_debug.php - DEBUG VERSION

// Clear all output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

error_log("=== UPLOAD DEBUG START ===");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

try {
    // Check if it's a chunk upload
    if (isset($_POST['is_chunk_upload'])) {
        // Simple validation
        if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file chunk received');
        }
        
        $chunkNumber = $_POST['chunkNumber'] ?? 'unknown';
        $totalChunks = $_POST['totalChunks'] ?? 'unknown';
        
        error_log("Chunk $chunkNumber/$totalChunks received");
        error_log("File size: " . $_FILES['chunk']['size'] . " bytes");
        error_log("Temp file: " . $_FILES['chunk']['tmp_name']);
        
        // Return simple success response
        echo json_encode([
            'success' => true,
            'message' => 'Chunk uploaded successfully',
            'data' => [
                'chunkNumber' => $chunkNumber,
                'totalChunks' => $totalChunks
            ]
        ]);
        exit;
    }
    
    // If not a chunk upload, return error
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request type',
        'data' => []
    ]);
    exit;
    
} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ]);
    exit;
}