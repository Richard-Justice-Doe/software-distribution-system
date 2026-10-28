<?php
// index.php runs this before any HTML so only the file is sent
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM applications WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();

    if ($app && file_exists($app['file_path'])) {
        // Update download count
        $updateSql = "UPDATE applications SET download_count = download_count + 1 WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('i', $id);
        $updateStmt->execute();

        // Don't hold multi-GB files in output buffers or block other requests on the session
        while (ob_get_level()) {
            ob_end_clean();
        }
        session_write_close();
        set_time_limit(0);

        // Quotes and control characters would break the header
        $downloadName = preg_replace('/["\\\\\x00-\x1F\x7F]/', '_', $app['original_file_name']);

        // Send file
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($app['file_path']));
        header('X-Content-Type-Options: nosniff');

        // Stream in 8KB pieces so large files on the network share don't fill memory
        $file = fopen($app['file_path'], 'rb');
        if ($file) {
            while (!feof($file)) {
                print fread($file, 1024 * 8);
                flush();
            }
            fclose($file);
        }
        exit();
    }
}

$_SESSION['error'] = "File not found";
header("Location: ?page=applications");
exit();
?>
