<?php
// Get installations with pagination
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$sql = "SELECT i.*, a.name as app_name FROM installations i 
        JOIN applications a ON i.application_id = a.id 
        ORDER BY i.installation_date DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$installations = $stmt->get_result();

// Get total count
$totalResult = $conn->query("SELECT COUNT(*) as total FROM installations");
$total = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($total / $perPage);
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Installation Records</h2>
        <a href="?page=add-installation" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> New Installation Record
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>PC Name</th>
                            <th>Application</th>
                            <th>Installation Date</th>
                            <th>Remarks</th>
                            <th>Recorded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($install = $installations->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($install['employee_name']); ?></td>
                            <td><?php echo htmlspecialchars($install['pc_name']); ?></td>
                            <td><?php echo htmlspecialchars($install['app_name']); ?></td>
                            <td><?php echo $install['installation_date']; ?></td>
                            <td><?php echo substr(htmlspecialchars($install['remarks']), 0, 50); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($install['recorded_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=installations&p=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>