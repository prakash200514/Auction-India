<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($action == 'get_status') {
    // Get active player info
    $settings = $conn->query("SELECT s.*, p.name, p.category, p.base_price, p.status FROM settings s LEFT JOIN players p ON s.active_player_id = p.id WHERE s.id = 1")->fetch_assoc();
    
    if (!$settings['active_player_id']) {
        echo json_encode(['status' => 'no_active_player']);
        exit();
    }

    // Get current high bid
    $high_bid = $conn->query("SELECT b.*, u.team_name, u.name FROM bids b JOIN users u ON b.user_id = u.id WHERE b.player_id = " . $settings['active_player_id'] . " ORDER BY b.bid_amount DESC LIMIT 1")->fetch_assoc();

    $bidder_name = !empty($high_bid['team_name']) ? $high_bid['team_name'] : ($high_bid['name'] ?? 'No bids yet');
    $current_price = $high_bid ? $high_bid['bid_amount'] : $settings['base_price'];
    $next_bid = $current_price + 100000; // Increment by 1 Lakh

    echo json_encode([
        'status' => 'active',
        'player' => [
            'id' => $settings['active_player_id'],
            'name' => $settings['name'],
            'category' => $settings['category'],
            'base_price' => $settings['base_price'],
            'current_price' => $current_price,
            'next_bid' => $next_bid,
            'high_bidder' => $bidder_name
        ],
        'user_budget' => $conn->query("SELECT budget FROM users WHERE id = $user_id")->fetch_assoc()['budget']
    ]);
} 

elseif ($action == 'place_bid') {
    if ($_SESSION['role'] !== 'team') {
        echo json_encode(['error' => 'Only Teams can place bids!']);
        exit();
    }
    $player_id = $_POST['player_id'];
    $bid_amount = $_POST['amount'];

    // Validate
    $user = $conn->query("SELECT budget FROM users WHERE id = $user_id")->fetch_assoc();
    $high_bid = $conn->query("SELECT MAX(bid_amount) as max_bid FROM bids WHERE player_id = $player_id")->fetch_assoc();
    $player = $conn->query("SELECT base_price FROM players WHERE id = $player_id")->fetch_assoc();

    $current_max = $high_bid['max_bid'] ?? $player['base_price'];

    if ($bid_amount <= $current_max) {
        echo json_encode(['error' => 'Bid must be higher than current price']);
    } elseif ($bid_amount > $user['budget']) {
        echo json_encode(['error' => 'Insufficient budget!']);
    } else {
        $stmt = $conn->prepare("INSERT INTO bids (player_id, user_id, bid_amount) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $player_id, $user_id, $bid_amount);
        if ($stmt->execute()) {
            echo json_encode(['success' => 'Bid placed successfully']);
        } else {
            echo json_encode(['error' => 'Database error']);
        }
    }
}

elseif ($action == 'sell_player' && $_SESSION['role'] == 'admin') {
    $player_id = $_GET['id'];
    $high_bid = $conn->query("SELECT * FROM bids WHERE player_id = $player_id ORDER BY bid_amount DESC LIMIT 1")->fetch_assoc();

    if ($high_bid) {
        $winner_id = $high_bid['user_id'];
        $amount = $high_bid['bid_amount'];

        $conn->query("UPDATE players SET status = 'sold', team_id = $winner_id, current_price = $amount WHERE id = $player_id");
        $conn->query("UPDATE users SET budget = budget - $amount WHERE id = $winner_id");
        $conn->query("UPDATE settings SET active_player_id = NULL, auction_status = 'not_started' WHERE id = 1");
        echo json_encode(['success' => 'Player sold successfully']);
    } else {
        echo json_encode(['error' => 'No bids found for this player. Use "Unsold" instead.']);
    }
}

elseif ($action == 'unsold_player' && $_SESSION['role'] == 'admin') {
    $player_id = $_GET['id'];
    $conn->query("UPDATE players SET status = 'unsold' WHERE id = $player_id");
    $conn->query("UPDATE settings SET active_player_id = NULL, auction_status = 'not_started' WHERE id = 1");
    echo json_encode(['success' => 'Player marked as unsold']);
}

elseif ($action == 'get_all_bids') {
    $settings = $conn->query("SELECT active_player_id FROM settings WHERE id = 1")->fetch_assoc();
    if (!$settings['active_player_id']) {
        echo json_encode(['bids' => []]);
        exit();
    }
    
    $player_id = $settings['active_player_id'];
    $bids = $conn->query("SELECT b.*, u.team_name, u.name FROM bids b JOIN users u ON b.user_id = u.id WHERE b.player_id = $player_id ORDER BY b.bid_amount DESC");
    
    $bid_list = [];
    while($row = $bids->fetch_assoc()) {
        $row['display_name'] = !empty($row['team_name']) ? $row['team_name'] : $row['name'];
        $bid_list[] = $row;
    }
    echo json_encode(['bids' => $bid_list]);
}
?>
