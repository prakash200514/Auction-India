<?php
require_once 'includes/config.php';

$email = 'admin@ipl.com';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$name = 'System Admin';
$role = 'admin';

$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();

if ($check->get_result()->num_rows == 0) {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $password, $role);
    if ($stmt->execute()) {
        echo "Admin created: $email / admin123";
    } else {
        echo "Error creating admin.";
    }
} else {
    echo "Admin already exists: $email / admin123 (if password wasn't changed)";
}
?>
