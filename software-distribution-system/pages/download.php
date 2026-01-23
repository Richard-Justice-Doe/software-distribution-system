<?php
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
        
        // Send file
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $app['original_file_name'] . '"');
        header('Content-Length: ' . filesize($app['file_path']));
        
        // Use readfile for network share
        if (@readfile($app['file_path']) === false) {
            // Alternative method for network files
            $file = fopen($app['file_path'], 'rb');
            if ($file) {
                while (!feof($file)) {
                    print fread($file, 1024 * 8);
                    flush();
                }
                fclose($file);
            }
        }
        exit();
    }
}

$_SESSION['error'] = "File not found";
header("Location: ?page=applications");
exit();
?>