<?php
session_start();
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

                        <?php
                        // Database configuration
                        $db_host = 'localhost';
                        $db_user = 'root';
                        $db_pass = '';
                        $db_name = 'software_distribution';
                        
                        $conn = new mysqli($db_host, $db_user, $db_pass);
                        
                        if ($conn->connect_error) {
                            echo '<div class="alert alert-danger">';
                            echo '<h4>❌ Database Connection Failed</h4>';
                            echo '<p>Error: ' . $conn->connect_error . '</p>';
                            echo '<p>Please check your MySQL credentials in XAMPP.</p>';
                            echo '</div>';
                        } else {
                            // Create database
                            if ($conn->query("CREATE DATABASE IF NOT EXISTS $db_name") === TRUE) {
                                echo '<div class="alert alert-success">✅ Database created successfully</div>';
                                
                                // Select database
                                $conn->select_db($db_name);
                                
                                // Create tables
                                $sql = "
                                CREATE TABLE IF NOT EXISTS applications (
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
                                );
                                
                                CREATE TABLE IF NOT EXISTS installations (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    employee_name VARCHAR(255) NOT NULL,
                                    pc_name VARCHAR(255) NOT NULL,
                                    application_id INT NOT NULL,
                                    installation_date DATE NOT NULL,
                                    remarks TEXT,
                                    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
                                );
                                ";
                                if ($conn->multi_query($sql)) {
                                    echo '<div class="alert alert-success">✅ Database tables created successfully</div>';
                                    
                                    // Add sample data
                                    $sampleData = "
                                    INSERT INTO applications (name, version, operating_system, description, file_name, original_file_name, file_path, file_size, download_count) VALUES
                                    ('Google Chrome', '120.0.6099.110', 'Windows', 'Fast and secure web browser', 'chrome.exe', 'chrome_installer.exe', 'uploads/chrome.exe', '85.2 MB', 45),
                                    ('Visual Studio Code', '1.85.0', 'Windows', 'Code editor redefined and optimized', 'vscode.exe', 'vscode_installer.exe', 'uploads/vscode.exe', '72.5 MB', 32),
                                    ('MySQL Workbench', '8.0.34', 'Windows', 'Database design and administration tool', 'mysql_workbench.exe', 'mysql_workbench.exe', 'uploads/mysql_workbench.exe', '41.8 MB', 18);
                                    
                                    INSERT INTO installations (employee_name, pc_name, application_id, installation_date, remarks) VALUES
                                    ('John Smith', 'PC-IT-001', 1, '2024-01-10', 'Installed for development team'),
                                    ('Sarah Johnson', 'PC-SALES-005', 2, '2024-01-11', 'Required for sales reporting');
                                    ";
                                    
                                    if ($conn->multi_query($sampleData)) {
                                        echo '<div class="alert alert-success">✅ Sample data added successfully</div>';
                                    }
                                } else {
                                    echo '<div class="alert alert-danger">❌ Error creating tables: ' . $conn->error . '</div>';
                                }
                                
                                // Create uploads directory
                                if (!file_exists('uploads')) {
                                    mkdir('uploads', 0755, true);
                                    file_put_contents('uploads/.htaccess', 'Deny from all');
                                    echo '<div class="alert alert-success">✅ Uploads directory created</div>';
                                }
                                // Create uploads and chunks directories
if (!file_exists('uploads')) {
    mkdir('uploads', 0755, true);
    file_put_contents('uploads/.htaccess', 'Deny from all');
    echo '<div class="alert alert-success">✅ Uploads directory created</div>';
}

if (!file_exists('chunks')) {
    mkdir('chunks', 0755, true);
    file_put_contents('chunks/.htaccess', 'Deny from all');
    echo '<div class="alert alert-success">✅ Chunks directory created</div>';
}
                                
                                echo '<div class="alert alert-success mt-3">';
                                echo '<h4>🎉 Installation Complete!</h4>';
                                echo '<p>Software Distribution System has been successfully installed.</p>';
                                echo '<div class="mt-3">';
                                echo '<a href="index.php" class="btn btn-primary btn-lg">Go to Application</a>';
                                echo '</div>';
                                echo '</div>';
                                
                            } else {
                                echo '<div class="alert alert-danger">❌ Error creating database: ' . $conn->error . '</div>';
                            }
                            
                            $conn->close();
                        }
                        ?>

                        <div class="mt-4">
                            <h5>📋 Next Steps:</h5>
                            <ol>
                                <li>Click "Go to Application" to access the system</li>
                                <li>Upload your software installers</li>
                                <li>Track installations across your company</li>
                                <li>Access from network: <code>http://[YOUR-IP]/software-distribution-system/</code>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>