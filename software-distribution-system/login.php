<?php
require_once __DIR__ . '/config.php';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit();
}

if (is_logged_in()) {
    header("Location: index.php");
    exit();
}

$conn = db_connect();
// No database, or one installed before accounts existed: run the installer to create the admin
if (!$conn || !$conn->query("SELECT 1 FROM users LIMIT 1")) {
    header("Location: install.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: index.php");
            exit();
        }
        $error = 'Invalid username or password.';
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - GREL Software Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
    body {
        background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
    }

    .card {
        border-radius: 15px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        border: none;
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4"><i class="bi bi-cloud-arrow-up-fill"></i> GREL Software Hub</h3>

                        <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required autofocus
                                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-box-arrow-in-right"></i> Sign In
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
