<?php
require_once 'includes/config.php';
$_SESSION['role'] = 'admin'; // Mock admin
$_GET['action'] = 'sell_player';
$_GET['id'] = 1;

// Simulate auction_api.php logic
$player_id = $_GET['id'];
echo "Finalizing player $player_id...\n";

$high_bid = $conn->query("SELECT * FROM bids WHERE player_id = $player_id ORDER BY bid_amount DESC LIMIT 1")->fetch_assoc();

if ($high_bid) {
    echo "High bid found.\n";
    $winner_id = $high_bid['user_id'];
    $amount = $high_bid['bid_amount'];
    $conn->query("UPDATE players SET status = 'sold', team_id = $winner_id, current_price = $amount WHERE id = $player_id");
    $conn->query("UPDATE users SET budget = budget - $amount WHERE id = $winner_id");
} else {
    echo "No bids found. Marking as unsold.\n";
    $conn->query("UPDATE players SET status = 'unsold' WHERE id = $player_id");
}

$conn->query("UPDATE settings SET active_player_id = NULL, auction_status = 'not_started' WHERE id = 1");

echo "Done. Status: " . $conn->error . "\n";
?>
