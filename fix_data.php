<?php
require_once 'includes/config.php';
$conn->query("UPDATE players SET team_id = 1 WHERE id = 1");
$conn->query("UPDATE bids SET user_id = 1 WHERE id IN (1,2)");
echo "Fixed Kohli.";
?>
