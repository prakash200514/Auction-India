<?php
require_once 'includes/config.php';
echo "--- PLAYERS ---\n";
$res = $conn->query("SELECT id, name, status, team_id, current_price FROM players");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "--- USERS (Teams) ---\n";
$res = $conn->query("SELECT id, name, team_name, budget FROM users WHERE role = 'team'");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "--- BIDS ---\n";
$res = $conn->query("SELECT * FROM bids");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
