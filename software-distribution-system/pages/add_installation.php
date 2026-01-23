<?php
// Get applications for dropdown
$apps = $conn->query("SELECT id, name, version FROM applications ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    $employee = sanitizeInput($_POST['employee_name'] ?? '');
    $pc = sanitizeInput($_POST['pc_name'] ?? '');
    $appId = intval($_POST['application_id'] ?? 0);
    $date = sanitizeInput($_POST['installation_date'] ?? '');
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    
    if (empty($employee)) $errors[] = "Employee name required";
    if (empty($pc)) $errors[] = "PC name required";
    if ($appId <= 0) $errors[] = "Application required";
    if (empty($date)) $errors[] = "Date required";
    
    if (empty($errors)) {
        $sql = "INSERT INTO installations (employee_name, pc_name, application_id, installation_date, remarks) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssiss', $employee, $pc, $appId, $date, $remarks);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Installation recorded!";
            header("Location: ?page=installations");
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>Record New Installation</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Employee Name *</label>
                            <input type="text" name="employee_name" class="form-control" required
                                value="<?php echo isset($_POST['employee_name']) ? htmlspecialchars($_POST['employee_name']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">PC Name *</label>
                            <input type="text" name="pc_name" class="form-control" required
                                placeholder="e.g., PC-IT-001"
                                value="<?php echo isset($_POST['pc_name']) ? htmlspecialchars($_POST['pc_name']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Application *</label>
                            <select name="application_id" class="form-select" required>
                                <option value="">Select Application</option>
                                <?php while ($app = $apps->fetch_assoc()): ?>
                                <option value="<?php echo $app['id']; ?>"
                                    <?php echo isset($_POST['application_id']) && $_POST['application_id'] == $app['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($app['name'] . ' (' . $app['version'] . ')'); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Installation Date *</label>
                            <input type="date" name="installation_date" class="form-control" required
                                value="<?php echo isset($_POST['installation_date']) ? $_POST['installation_date'] : date('Y-m-d'); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control"
                                rows="3"><?php echo isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="?page=installations" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>