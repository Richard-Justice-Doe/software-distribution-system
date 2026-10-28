<?php
// Saves the Record Installation form. index.php runs this before any HTML so the redirect works;
// on validation errors it falls through and add_installation.php re-renders the form.
$errors = [];

$employee = sanitizeInput($_POST['employee_name'] ?? '');
$pc = sanitizeInput($_POST['pc_name'] ?? '');
$appId = intval($_POST['application_id'] ?? 0);
$date = sanitizeInput($_POST['installation_date'] ?? '');
$remarks = sanitizeInput($_POST['remarks'] ?? '');

if (empty($employee)) $errors[] = "Employee name required";
if (empty($pc)) $errors[] = "PC name required";
if ($appId <= 0) $errors[] = "Application required";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = "Valid date required";

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
        error_log("Installation insert failed: " . $stmt->error);
        $errors[] = "Database error: could not save the record";
    }
}

if (!empty($errors)) {
    $_SESSION['error'] = implode("<br>", $errors);
}
