<?php
require_once 'includes/config.php';
$res = $conn->query("SELECT id, name, status FROM players WHERE name LIKE '%Kohli%'");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
$sett = $conn->query("SELECT * FROM settings WHERE id = 1")->fetch_assoc();
print_r($sett);
?>
