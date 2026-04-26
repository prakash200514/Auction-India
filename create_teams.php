<?php
require_once 'includes/config.php';

$teams = [
    ['name' => 'Mukesh Ambani', 'email' => 'mumbai@ipl.com', 'team' => 'Mumbai Indians'],
    ['name' => 'SRK', 'email' => 'kolkata@ipl.com', 'team' => 'KKR']
];

foreach ($teams as $t) {
    $password = password_hash('team123', PASSWORD_DEFAULT);
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $t['email']);
    $check->execute();
    if ($check->get_result()->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, team_name) VALUES (?, ?, ?, 'team', ?)");
        $stmt->bind_param("ssss", $t['name'], $t['email'], $password, $t['team']);
        $stmt->execute();
    }
}
echo "Teams created.";
?>
