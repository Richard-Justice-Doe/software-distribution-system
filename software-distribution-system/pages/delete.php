<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // Get file path
    $sql = "SELECT file_path FROM applications WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $app = $result->fetch_assoc();
        
        // Delete file from network share
        if (file_exists($app['file_path'])) {
            unlink($app['file_path']);
        }
        
        // Delete from database
        $deleteSql = "DELETE FROM applications WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param('i', $id);
        
        if ($deleteStmt->execute()) {
            $_SESSION['success'] = "Application deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete application";
        }
    } else {
        $_SESSION['error'] = "Application not found";
    }
}

header("Location: ?page=applications");
exit();
?>