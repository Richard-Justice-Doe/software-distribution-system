<?php
// Handle search and filter
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$os = isset($_GET['os']) ? sanitizeInput($_GET['os']) : 'all';
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if ($os !== 'all') {
    $where[] = "operating_system = ?";
    $params[] = $os;
    $types .= 's';
}

// Add limit and offset parameters
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

// Get applications
$whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
$sql = "SELECT * FROM applications $whereClause ORDER BY upload_date DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
} else {
    // If no parameters, bind limit and offset directly
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$applications = $stmt->get_result();

// Get total count
if (empty($where)) {
    $countSql = "SELECT COUNT(*) as total FROM applications";
    $countResult = $conn->query($countSql);
} else {
    $countSql = "SELECT COUNT(*) as total FROM applications WHERE " . implode(' AND ', $where);
    $countStmt = $conn->prepare($countSql);
    
    // Remove limit and offset parameters for count query
    $countParams = array_slice($params, 0, -2);
    $countTypes = substr($types, 0, -2);
    
    if (!empty($countTypes)) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
}

$total = $countResult->fetch_assoc()['total'];
$totalPages = ceil($total / $perPage);
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Applications</h2>
        <a href="?page=upload" class="btn btn-primary">
            <i class="bi bi-upload"></i> Upload New Application
        </a>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="page" value="applications">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search applications..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="os" class="form-select">
                        <option value="all" <?php echo $os == 'all' ? 'selected' : ''; ?>>All Operating Systems</option>
                        <option value="Windows" <?php echo $os == 'Windows' ? 'selected' : ''; ?>>Windows</option>
                        <option value="Linux" <?php echo $os == 'Linux' ? 'selected' : ''; ?>>Linux</option>
                        <option value="macOS" <?php echo $os == 'macOS' ? 'selected' : ''; ?>>macOS</option>
                        <option value="Multi-platform" <?php echo $os == 'Multi-platform' ? 'selected' : ''; ?>>
                            Multi-platform</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                    <?php if (!empty($search) || $os !== 'all'): ?>
                    <a href="?page=applications" class="btn btn-outline-secondary w-100 mt-2">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Applications List -->
    <div class="card">
        <div class="card-body">
            <?php if ($applications->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Application Name</th>
                            <th>Version</th>
                            <th>OS</th>
                            <th>File Size</th>
                            <th>Downloads</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($app = $applications->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($app['name']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo strlen($app['description']) > 50 ? 
                                          htmlspecialchars(substr($app['description'], 0, 50)) . '...' : 
                                          htmlspecialchars($app['description']); ?>
                                </small>
                            </td>
                            <td><?php echo htmlspecialchars($app['version']); ?></td>
                            <td><span class="badge bg-info"><?php echo $app['operating_system']; ?></span></td>
                            <td><?php echo $app['file_size']; ?></td>
                            <td><span class="badge bg-success"><?php echo $app['download_count']; ?></span></td>
                            <td><?php echo date('Y-m-d', strtotime($app['upload_date'])); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="?page=download&id=<?php echo $app['id']; ?>" class="btn btn-sm btn-success"
                                        title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <form action="?page=delete" method="POST" class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete this application?')">
                                        <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link"
                            href="?page=applications&search=<?php echo urlencode($search); ?>&os=<?php echo $os; ?>&p=<?php echo $page - 1; ?>">
                            Previous
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link"
                            href="?page=applications&search=<?php echo urlencode($search); ?>&os=<?php echo $os; ?>&p=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link"
                            href="?page=applications&search=<?php echo urlencode($search); ?>&os=<?php echo $os; ?>&p=<?php echo $page + 1; ?>">
                            Next
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-info text-center">
                <h5>No applications found</h5>
                <p>Upload your first application to get started!</p>
                <a href="?page=upload" class="btn btn-primary">Upload Application</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>