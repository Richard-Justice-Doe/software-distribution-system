<?php
require_once __DIR__ . '/config.php';

$messages = [];   // [type, text] pairs shown as alerts
$showForm = false;
$installed = false;

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($db_host, $db_user, $db_pass);

if ($conn->connect_error) {
    error_log("Install: database connection failed: " . $conn->connect_error);
    $messages[] = ['danger', '❌ Database connection failed. Check the MySQL credentials in config.php and that MySQL is running in XAMPP.'];
} else {
    // Once an admin account exists, the installer is locked
    $alreadyInstalled = false;
    if ($conn->select_db($db_name)) {
        $result = $conn->query("SELECT COUNT(*) AS total FROM users");
        $alreadyInstalled = $result && $result->fetch_assoc()['total'] > 0;
    }

    if ($alreadyInstalled) {
        $messages[] = ['info', 'ℹ️ The system is already installed. Sign in to continue.'];
        $installed = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $errors = [];
        if (!csrf_valid()) $errors[] = 'Your session expired. Please try again.';
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) $errors[] = 'Username must be 3-50 letters, numbers, dots, dashes or underscores.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if ($errors) {
            foreach ($errors as $error) {
                $messages[] = ['danger', '❌ ' . htmlspecialchars($error)];
            }
            $showForm = true;
        } else {
            $statements = [
                "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
                "USE `$db_name`",
                "CREATE TABLE IF NOT EXISTS applications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    version VARCHAR(50) NOT NULL,
                    operating_system ENUM('Windows', 'Linux', 'macOS', 'Multi-platform') NOT NULL,
                    description TEXT,
                    file_name VARCHAR(255) NOT NULL,
                    original_file_name VARCHAR(255) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    file_size VARCHAR(50) NOT NULL,
                    download_count INT DEFAULT 0,
                    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS installations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_name VARCHAR(255) NOT NULL,
                    pc_name VARCHAR(255) NOT NULL,
                    application_id INT NOT NULL,
                    installation_date DATE NOT NULL,
                    remarks TEXT,
                    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
                )",
                "CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
            ];

            $ok = true;
            foreach ($statements as $sql) {
                if (!$conn->query($sql)) {
                    error_log("Install: query failed: " . $conn->error);
                    $messages[] = ['danger', '❌ Error creating the database: ' . htmlspecialchars($conn->error)];
                    $ok = false;
                    break;
                }
            }

            if ($ok) {
                $messages[] = ['success', '✅ Database and tables created successfully'];

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
                $stmt->bind_param('ss', $username, $hash);
                if ($stmt->execute()) {
                    $messages[] = ['success', '✅ Admin account "' . htmlspecialchars($username) . '" created'];
                    $installed = true;
                } else {
                    $messages[] = ['danger', '❌ Error creating admin account: ' . htmlspecialchars($stmt->error)];
                    $showForm = true;
                }
            }

            // Uploads (fallback storage) and chunks (in-progress uploads) must not be web-readable
            foreach (['Uploads' => LOCAL_UPLOADS, 'Chunks' => CHUNKS_DIR] as $label => $dir) {
                if (ensure_private_dir($dir)) {
                    $messages[] = ['success', "✅ $label directory ready"];
                } else {
                    $messages[] = ['warning', "⚠️ Could not create $label directory: " . htmlspecialchars($dir)];
                }
            }
        }
    } else {
        $showForm = true;
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Software Distribution System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
    }

    .card {
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        border: none;
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body p-5">
                        <h1 class="text-center mb-4">🚀 Software Distribution System - Installation</h1>

                        <?php foreach ($messages as [$type, $text]): ?>
                        <div class="alert alert-<?php echo $type; ?>"><?php echo $text; ?></div>
                        <?php endforeach; ?>

                        <?php if ($showForm): ?>
                        <p>Create the administrator account used to sign in.</p>
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label">Admin Username</label>
                                <input type="text" name="username" class="form-control" required
                                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password (at least 8 characters)</label>
                                <input type="password" name="password" class="form-control" required minlength="8">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="8">
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg">Install</button>
                        </form>
                        <?php endif; ?>

                        <?php if ($installed): ?>
                        <div class="alert alert-success mt-3">
                            <h4>🎉 Ready!</h4>
                            <a href="login.php" class="btn btn-primary btn-lg mt-2">Sign In</a>
                        </div>

                        <div class="mt-4">
                            <h5>📋 Next Steps:</h5>
                            <ol>
                                <li>Sign in with the admin account</li>
                                <li>Upload your software installers</li>
                                <li>Track installations across your company</li>
                                <li>Access from network: <code>http://[YOUR-IP]/software-distribution-system/</code></li>
                            </ol>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
