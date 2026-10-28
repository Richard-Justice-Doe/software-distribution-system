<?php
require_once __DIR__ . '/config.php';

// Define parseSizeToBytes function FIRST
if (!function_exists('parseSizeToBytes')) {
    function parseSizeToBytes($size) {
        if (empty($size)) return 0;
        if (is_numeric($size)) return (int)$size;
        
        $size = trim($size);
        $unit = strtolower(substr($size, -1));
        $value = (float)substr($size, 0, -1);
        
        if (!in_array($unit, ['g', 'm', 'k', 't', 'p'])) {
            return (int)$size;
        }
        
        switch ($unit) {
            case 'g': return (int)($value * 1024 * 1024 * 1024);
            case 'm': return (int)($value * 1024 * 1024);
            case 'k': return (int)($value * 1024);
            default: return (int)$value;
        }
    }
}

// DIAGNOSTIC: Check current limits
$phpUploadMax = ini_get('upload_max_filesize');
$phpPostMax = ini_get('post_max_size');
$actualLimit = min(
    parseSizeToBytes($phpUploadMax),
    parseSizeToBytes($phpPostMax)
);

// Log current configuration
error_log("PHP Configuration - upload_max_filesize: " . $phpUploadMax);
error_log("PHP Configuration - post_max_size: " . $phpPostMax);
error_log("PHP Configuration - memory_limit: " . ini_get('memory_limit'));
error_log("PHP Configuration - max_execution_time: " . ini_get('max_execution_time'));
error_log("Effective upload limit: " . formatBytes($actualLimit));

// Try to override PHP settings for large files
@ini_set('upload_max_filesize', '25600M');
@ini_set('post_max_size', '25600M');
@ini_set('memory_limit', '2048M');
@ini_set('max_execution_time', '3600');
@ini_set('max_input_time', '3600');

// Define constants for network storage (share paths, size and type limits live in config.php)
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));
define('LOCAL_TEMP_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'software_upload_temp' . DIRECTORY_SEPARATOR);
define('CHUNK_SIZE', 5 * 1024 * 1024); // 5MB, matches the chunk size the upload page sends

// Add these constants for chunked uploads
define('MAX_RETRIES', 3);
define('CHUNK_RETRY_DELAY', 2000); // 2 seconds between retries

// FIXED: Proper array syntax for ALLOWED_MIME_TYPES
define('ALLOWED_MIME_TYPES', array(
    'application/x-msdownload',      // .exe
    'application/x-dosexec',         // .exe (Windows executable)
    'application/x-msi',             // .msi
    'application/zip',               // .zip
    'application/x-debian-package',  // .deb
    'application/x-rar-compressed',  // .rar
    'application/x-rar',             // .rar
    'application/x-tar',             // .tar
    'application/x-gzip',            // .gz
    'application/x-bzip2',           // .bz2
    'application/x-7z-compressed',   // .7z
    'application/x-iso9660-image',   // .iso
    'application/x-apple-diskimage', // .dmg
    'application/octet-stream'       // fallback for binary files
));

// Create database connection (credentials are in config.php)
$conn = db_connect();
if (!$conn) {
    header("Location: install.php");
    exit();
}

// Everything below requires a signed-in user
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

// Helper functions
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Values are stored as typed; pages escape them with htmlspecialchars when displaying
function sanitizeInput($data) {
    return trim((string)$data);
}

// Function to test network share access
function testNetworkShareAccess() {
    $testContent = 'Test write access at ' . date('Y-m-d H:i:s');
    $testFileName = 'test_access_' . time() . '.txt';
    
    $results = [];
    
    // Test UNC path first
    $uncPath = NETWORK_SHARE . $testFileName;
    if (@file_put_contents($uncPath, $testContent)) {
        $results[] = "✓ UNC path (" . NETWORK_SHARE . ") is accessible and writable";
        unlink($uncPath);
    } else {
        $results[] = "✗ UNC path (" . NETWORK_SHARE . ") is NOT accessible. Error: " . (error_get_last()['message'] ?? 'Unknown error');
    }
    
    // Test mapped drive
    $mappedPath = NETWORK_SHARE_MAPPED . $testFileName;
    if (@file_put_contents($mappedPath, $testContent)) {
        $results[] = "✓ Mapped drive (" . NETWORK_SHARE_MAPPED . ") is accessible and writable";
        unlink($mappedPath);
    } else {
        $results[] = "✗ Mapped drive (" . NETWORK_SHARE_MAPPED . ") is NOT accessible";
    }
    
    return implode("<br>", $results);
}

/**
 * Save file directly to network share
 */
function saveToNetworkShare($tempFile, $filename) {
    // Clean the filename
    $filename = basename($filename);
    error_log("Saving file to network share: $filename");
    
    // Ensure source file exists and is readable
    if (!file_exists($tempFile) || !is_readable($tempFile)) {
        error_log("ERROR: Source file doesn't exist or is not readable: $tempFile");
        return false;
    }
    
    $fileSize = filesize($tempFile);
    error_log("File size: " . formatBytes($fileSize) . " ($fileSize bytes)");
    
    // For very large files (>2GB), use chunked copy
    if ($fileSize > 2 * 1024 * 1024 * 1024) {
        error_log("Using chunked copy for large file...");
        return copyLargeFileChunked($tempFile, $filename, $fileSize);
    }
    
    // Method 1: Try direct copy to UNC path
    $uncPath = NETWORK_SHARE . $filename;
    error_log("Method 1: Trying direct copy to UNC path: $uncPath");
    
    // Check if UNC path is accessible
    if (@is_dir(NETWORK_SHARE)) {
        if (@copy($tempFile, $uncPath)) {
            // Verify the copy was successful
            if (file_exists($uncPath) && filesize($uncPath) === $fileSize) {
                error_log("SUCCESS: File successfully saved to UNC path");
                return $uncPath;
            } else {
                error_log("WARNING: Copy appeared successful but file verification failed");
            }
        } else {
            $error = error_get_last();
            error_log("WARNING: Direct copy to UNC failed: " . ($error['message'] ?? 'Unknown error'));
        }
    } else {
        error_log("WARNING: UNC path is not accessible as a directory: " . NETWORK_SHARE);
    }
    
    // Method 2: Try mapped drive
    $mappedPath = NETWORK_SHARE_MAPPED . $filename;
    error_log("Method 2: Trying mapped drive: $mappedPath");
    
    if (@is_dir(NETWORK_SHARE_MAPPED)) {
        if (@copy($tempFile, $mappedPath)) {
            if (file_exists($mappedPath) && filesize($mappedPath) === $fileSize) {
                error_log("SUCCESS: File successfully saved to mapped drive");
                return $mappedPath;
            }
        } else {
            $error = error_get_last();
            error_log("WARNING: Copy to mapped drive failed: " . ($error['message'] ?? 'Unknown error'));
        }
    } else {
        error_log("WARNING: Mapped drive is not accessible: " . NETWORK_SHARE_MAPPED);
    }
    
    // Method 3: Use file_get_contents/file_put_contents (for files under 500MB)
    if ($fileSize < 500 * 1024 * 1024) {
        error_log("Method 3: Trying file_get_contents method (for files under 500MB)");
        $content = @file_get_contents($tempFile);
        if ($content !== false) {
            // Try UNC path first
            if (@file_put_contents($uncPath, $content) !== false) {
                if (file_exists($uncPath) && filesize($uncPath) === $fileSize) {
                    error_log("SUCCESS: File saved using file_put_contents to UNC");
                    return $uncPath;
                }
            }
            // Try mapped drive
            if (@file_put_contents($mappedPath, $content) !== false) {
                if (file_exists($mappedPath) && filesize($mappedPath) === $fileSize) {
                    error_log("SUCCESS: File saved using file_put_contents to mapped drive");
                    return $mappedPath;
                }
            }
        }
    }
    
    // Method 4: For Windows servers, use command line utilities
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        error_log("Method 4: Trying Windows command line methods");
        
        // Convert paths to Windows format
        $sourceWin = str_replace('/', '\\', realpath($tempFile));
        $destDirWin = str_replace('/', '\\', dirname($uncPath));
        
        // Method 4a: Use copy command (simplest)
        if (@is_dir($destDirWin)) {
            $destPathWin = $destDirWin . '\\' . $filename;
            $command = 'copy "' . $sourceWin . '" "' . $destPathWin . '" /Y';
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && @file_exists($uncPath) && filesize($uncPath) === $fileSize) {
                error_log("SUCCESS: File saved using Windows copy command");
                return $uncPath;
            }
        }
        
        // Method 4b: Use xcopy (handles directories better)
        if (@is_dir($destDirWin)) {
            $command = 'xcopy "' . $sourceWin . '" "' . $destDirWin . '" /Y /Q /C';
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && @file_exists($uncPath) && filesize($uncPath) === $fileSize) {
                error_log("SUCCESS: File saved using xcopy");
                return $uncPath;
            }
        }
        
        // Method 4c: Use robocopy (most reliable for large files)
        if (@is_dir($destDirWin)) {
            $command = 'robocopy "' . dirname($sourceWin) . '" "' . $destDirWin . '" "' . basename($sourceWin) . '" /MOV /R:1 /W:2';
            exec($command, $output, $returnVar);
            
            // Robocopy returns 0-1 for success
            if ($returnVar <= 1 && @file_exists($uncPath) && filesize($uncPath) === $fileSize) {
                error_log("SUCCESS: File saved using robocopy");
                return $uncPath;
            }
        }
    }
    
    // All methods failed
    $lastError = error_get_last();
    error_log("CRITICAL ERROR: All file save methods failed. Last error: " . 
              ($lastError['message'] ?? 'Unknown error'));
    
    return false;
}

/**
 * Copy large files in chunks to avoid memory issues
 */
function copyLargeFileChunked($source, $filename, $fileSize) {
    $chunkSize = 100 * 1024 * 1024; // 100MB chunks
    $readHandle = fopen($source, 'rb');
    
    if (!$readHandle) {
        error_log("ERROR: Cannot open source file for reading: $source");
        return false;
    }
    
    // Try UNC path first
    $uncPath = NETWORK_SHARE . $filename;
    $writeHandle = fopen($uncPath, 'wb');
    
    if (!$writeHandle) {
        // Try mapped drive
        $mappedPath = NETWORK_SHARE_MAPPED . $filename;
        $writeHandle = fopen($mappedPath, 'wb');
        
        if (!$writeHandle) {
            fclose($readHandle);
            error_log("ERROR: Cannot open any destination for writing");
            return false;
        } else {
            $finalPath = $mappedPath;
        }
    } else {
        $finalPath = $uncPath;
    }
    
    // Copy in chunks
    $copiedBytes = 0;
    while (!feof($readHandle)) {
        $chunk = fread($readHandle, $chunkSize);
        fwrite($writeHandle, $chunk);
        $copiedBytes += strlen($chunk);
        
        // Log progress every 1GB
        if ($copiedBytes % (1024 * 1024 * 1024) < $chunkSize) {
            $progress = round(($copiedBytes / $fileSize) * 100, 2);
            error_log("Copy progress: $progress% ($copiedBytes/$fileSize bytes)");
        }
    }
    
    fclose($readHandle);
    fclose($writeHandle);
    
    // Verify copy
    if (file_exists($finalPath) && filesize($finalPath) === $fileSize) {
        error_log("SUCCESS: Large file saved to: $finalPath");
        return $finalPath;
    } else {
        error_log("ERROR: Copy verification failed");
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }
        return false;
    }
}

// Handle routing
$page = isset($_GET['page']) ? sanitizeInput($_GET['page']) : 'dashboard';

// Debug network access on upload page (optional)
if ($page == 'upload' && isset($_GET['debug'])) {
    $_SESSION['debug_info'] = testNetworkShareAccess();
}

// Create local temp directory if needed
if (!file_exists(LOCAL_TEMP_PATH)) {
    if (!mkdir(LOCAL_TEMP_PATH, 0777, true)) {
        $_SESSION['error'] = "Cannot create temp directory. Check permissions on: " . LOCAL_TEMP_PATH;
    } else {
        error_log("INFO: Created temp directory: " . LOCAL_TEMP_PATH);
    }
}

// Check if network share directory exists - CREATE IF NOT
if (!@is_dir(NETWORK_SHARE)) {
    error_log("WARNING: Network share directory does not exist or is not accessible: " . NETWORK_SHARE);
}

// Check if chunks directory exists for resumable uploads
if ($page == 'upload' && !ensure_private_dir(CHUNKS_DIR)) {
    error_log("WARNING: Cannot create chunks directory: " . CHUNKS_DIR);
}

// Every form submission must carry this session's CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid()) {
    $_SESSION['error'] = "Your session expired. Please try again.";
    header("Location: ?page=" . urlencode($page));
    exit();
}

// Actions that send headers (file download, redirects) must run before any HTML is output
if ($page === 'download') {
    include 'pages/download.php';
} elseif ($page === 'delete') {
    include 'pages/delete.php';
} elseif ($page === 'add-installation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'pages/add_installation_save.php';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software Distribution System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .sidebar {
        min-height: 100vh;
        background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
        color: white;
    }
    .sidebar a {
        color: white;
        text-decoration: none;
        padding: 10px 15px;
        display: block;
        border-radius: 5px;
        margin-bottom: 5px;
    }
    .sidebar a:hover,
    .sidebar a.active {
        background-color: rgba(255, 255, 255, 0.1);
        transform: translateX(5px);
    }
    .stat-card {
        transition: transform 0.3s, box-shadow 0.3s;
        border: none;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
    }
    .alert {
        border-radius: 8px;
        border: none;
    }
    .progress {
        height: 25px;
    }
    .debug-info {
        background-color: #f8f9fa;
        border-left: 4px solid #17a2b8;
        padding: 15px;
        margin: 15px 0;
        font-family: monospace;
        font-size: 0.9em;
    }
    .file-size-warning {
        color: #dc3545;
        font-weight: bold;
    }
    .storage-info {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-4">
                    <h3 class="text-center mb-4"><i class="bi bi-cloud-arrow-up-fill"></i> GREL Software Hub</h3>
                    <nav class="nav flex-column">
                        <a href="?page=dashboard" class="<?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a href="?page=applications" class="<?php echo $page == 'applications' ? 'active' : ''; ?>">
                            <i class="bi bi-collection"></i> Applications
                        </a>
                        <a href="?page=installations" class="<?php echo $page == 'installations' ? 'active' : ''; ?>">
                            <i class="bi bi-clipboard-check"></i> Installations
                        </a>
                        <hr class="text-white">
                        <a href="?page=upload" class="<?php echo $page == 'upload' ? 'active' : ''; ?>">
                            <i class="bi bi-upload"></i> Upload Application
                        </a>
                        <a href="?page=add-installation" class="<?php echo $page == 'add-installation' ? 'active' : ''; ?>">
                            <i class="bi bi-plus-circle"></i> Record Installation
                        </a>
                        <!-- Debug link -->
                        <a href="?page=upload&debug=1" class="mt-4 text-warning">
                            <i class="bi bi-bug"></i> Test Network Access
                        </a>
                        <hr class="text-white">
                        <a href="login.php?logout=1">
                            <i class="bi bi-box-arrow-right"></i> Sign Out (<?php echo htmlspecialchars($_SESSION['username']); ?>)
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($_SESSION['warning'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($_SESSION['debug_info'])): ?>
                <div class="debug-info alert alert-info">
                    <h6><i class="bi bi-info-circle"></i> Network Share Access Test Results:</h6>
                    <?php echo $_SESSION['debug_info']; unset($_SESSION['debug_info']); ?>
                </div>
                <?php endif; ?>

                <?php
                // Include page content
                switch ($page) {
                    case 'dashboard':
                        include 'pages/dashboard.php';
                        break;
                    case 'applications':
                        include 'pages/applications.php';
                        break;
                    case 'upload':
                        include 'pages/upload.php';
                        break;
                    case 'installations':
                        include 'pages/installations.php';
                        break;
                    case 'add-installation':
                        include 'pages/add_installation.php';
                        break;
                    default:
                        include 'pages/dashboard.php';
                }
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>    <script>
    // Auto-dismiss alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            if (alert.classList.contains('show')) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        });
    }, 5000);
    
    // File size validation
    document.getElementById('fileInput')?.addEventListener('change', function(e) {
        const file = this.files[0];
        const maxSize = <?php echo MAX_FILE_SIZE; ?>; // 25GB in bytes
        
        if (file && file.size > maxSize) {
            alert('File size exceeds the maximum limit of <?php echo formatBytes(MAX_FILE_SIZE); ?>');
            this.value = ''; // Clear the file input
        }
    });
    </script>
</body>
</html>
<?php 
// Close database connection
if (isset($conn) && $conn) {
    $conn->close();
}
?>