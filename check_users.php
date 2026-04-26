<?php
require_once 'includes/config.php';
$res = $conn->query("SELECT id, name, role, team_name FROM users");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
