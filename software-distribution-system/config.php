<?php
// config.php - shared settings and helpers for every entry point
// (index.php, install.php, login.php, pages/chunked_upload_handler.php)

// Show errors in the server log, never in the page
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database configuration
// Change these from the XAMPP defaults before putting the system on a shared network.
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'software_distribution';

// Storage
// XAMPP runs on 172.20.0.62, so this is a local folder ("Public Downloads" in File Explorer)
define('NETWORK_SHARE', 'C:\\Users\\Public\\Downloads\\');
define('NETWORK_SHARE_MAPPED', 'Z:\\uploads\\'); // Backup if mapped drive works
define('LOCAL_UPLOADS', __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR); // Fallback when the share is down
define('CHUNKS_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'chunks' . DIRECTORY_SEPARATOR);
define('MAX_FILE_SIZE', 25 * 1024 * 1024 * 1024); // 25GB in bytes
define('ALLOWED_EXTENSIONS', ['exe', 'msi', 'zip', 'deb', 'rar', 'tar', 'gz', 'bz2', '7z', 'iso', 'dmg']);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

/**
 * Connect to the application database, or return null if it is not installed yet
 */
function db_connect() {
    global $db_host, $db_user, $db_pass, $db_name;

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return null;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_valid() {
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

/**
 * Make a folder that the web server refuses to serve directly
 */
function ensure_private_dir($dir) {
    if (!file_exists($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }
    if (!file_exists($dir . '.htaccess')) {
        file_put_contents($dir . '.htaccess', "Require all denied\n");
    }
    return true;
}

function is_allowed_extension($filename) {
    return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ALLOWED_EXTENSIONS, true);
}
