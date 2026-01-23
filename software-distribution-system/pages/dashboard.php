<?php
// Get statistics
$totalApps = $conn->query("SELECT COUNT(*) as count FROM applications")->fetch_assoc()['count'];
$totalDownloads = $conn->query("SELECT SUM(download_count) as total FROM applications")->fetch_assoc()['total'] ?? 0;
$recentUploads = $conn->query("SELECT * FROM applications ORDER BY upload_date DESC LIMIT 5");
$recentInstalls = $conn->query("SELECT i.*, a.name as app_name FROM installations i 
                                JOIN applications a ON i.application_id = a.id 
                                ORDER BY i.installation_date DESC LIMIT 5");
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Dashboard</h2>
        <div>
            <a href="?page=upload" class="btn btn-primary">
                <i class="bi bi-upload"></i> Upload Application
            </a>
            <a href="?page=add-installation" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Record Installation
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-collection"></i> Total Applications</h5>
                    <h2 class="card-text"><?php echo $totalApps; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-download"></i> Total Downloads</h5>
                    <h2 class="card-text"><?php echo $totalDownloads; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Content -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Recent Uploads</h5>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Application</th>
                                <th>Version</th>
                                <th>OS</th>
                                <th>Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = $recentUploads->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['name']); ?></td>
                                <td><?php echo htmlspecialchars($app['version']); ?></td>
                                <td><span class="badge bg-info"><?php echo $app['operating_system']; ?></span></td>
                                <td><?php echo date('Y-m-d', strtotime($app['upload_date'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Recent Installations</h5>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>PC Name</th>
                                <th>Application</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($install = $recentInstalls->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($install['employee_name']); ?></td>
                                <td><?php echo htmlspecialchars($install['pc_name']); ?></td>
                                <td><?php echo htmlspecialchars($install['app_name']); ?></td>
                                <td><?php echo $install['installation_date']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>