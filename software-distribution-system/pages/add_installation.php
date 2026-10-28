<?php
// Get applications for dropdown
$apps = $conn->query("SELECT id, name, version FROM applications ORDER BY name");
// Form submissions are saved by add_installation_save.php before this page renders
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
                        <?php echo csrf_field(); ?>
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
                                value="<?php echo isset($_POST['installation_date']) ? htmlspecialchars($_POST['installation_date']) : date('Y-m-d'); ?>">
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