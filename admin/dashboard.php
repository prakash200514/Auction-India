<?php
require_once '../includes/config.php';

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle Player Add
if (isset($_POST['add_player'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $base_price = $_POST['base_price'];
    
    $stmt = $conn->prepare("INSERT INTO players (name, category, base_price) VALUES (?, ?, ?)");
    $stmt->bind_param("ssd", $name, $category, $base_price);
    $stmt->execute();
}

// Handle Auction Control
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'start_bidding' && isset($_GET['id'])) {
        $player_id = $_GET['id'];
        $conn->query("UPDATE players SET status = 'bidding' WHERE id = $player_id");
        $conn->query("UPDATE settings SET active_player_id = $player_id, auction_status = 'active' WHERE id = 1");
    } elseif ($_GET['action'] == 'reset') {
        $conn->query("UPDATE players SET status = 'available', team_id = NULL, current_price = NULL");
        $conn->query("UPDATE settings SET active_player_id = NULL, auction_status = 'not_started' WHERE id = 1");
        $conn->query("DELETE FROM bids");
        $conn->query("UPDATE users SET budget = 100000000.00 WHERE role = 'team'");
    }
}

$players = $conn->query("SELECT * FROM players ORDER BY id DESC");
$stats = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='sold' THEN 1 ELSE 0 END) as sold FROM players")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - IPL Auction</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--glass-border); }
        th { color: var(--primary-color); }
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; }
        .badge-available { background: #2ed573; color: #fff; }
        .badge-sold { background: #ff4757; color: #fff; }
        .badge-bidding { background: #ffa502; color: #fff; }
        .action-btn { padding: 8px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem; margin-right: 5px; cursor: pointer;}
        .btn-start { background: var(--primary-color); color: #000; }
        .btn-reset { background: var(--accent-color); color: #fff; }
    </style>
</head>
<body>
    <nav class="navbar glass">
        <a href="#" class="logo">IPL ADMIN</a>
        <div class="nav-links">
            <a href="dashboard.php">Players</a>
            <a href="dashboard.php?action=reset" class="btn-reset" onclick="return confirm('Reset EVERYTHING?')">Reset Auction</a>
            <a href="../logout.php">Logout</a>
        </div>
    </nav>

    <div class="container animate-fade-in">
        <div class="stats-grid">
            <div class="card glass">
                <div class="card-title">Total Players</div>
                <h2 style="font-size: 2rem;"><?php echo $stats['total']; ?></h2>
            </div>
            <div class="card glass">
                <div class="card-title">Players Sold</div>
                <h2 style="font-size: 2rem;"><?php echo $stats['sold'] ?? 0; ?></h2>
            </div>
            <div class="card glass">
                <div class="card-title">Auction Status</div>
                <h2 style="font-size: 2rem; color: var(--primary-color);">LIVE</h2>
            </div>
        </div>

        <div class="grid">
            <!-- Add Player Form -->
            <div class="card glass">
                <div class="card-title">Add New Player</div>
                <form method="POST">
                    <div class="form-group">
                        <label>Player Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <option>Batsman</option>
                            <option>Bowler</option>
                            <option>All-rounder</option>
                            <option>Wicketkeeper</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Base Price (₹)</label>
                        <input type="number" name="base_price" class="form-control" required>
                    </div>
                    <button type="submit" name="add_player" class="btn-premium">Add Player</button>
                </form>
            </div>

            <!-- Player List -->
            <div class="card glass" style="grid-column: span 2;">
                <div class="card-title">Player Management</div>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Base Price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($p = $players->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $p['name']; ?></td>
                            <td><?php echo $p['category']; ?></td>
                            <td>₹<?php echo number_format($p['base_price']); ?></td>
                            <td><span class="badge badge-<?php echo $p['status']; ?>"><?php echo strtoupper($p['status']); ?></span></td>
                            <td>
                                <?php if($p['status'] == 'available'): ?>
                                    <a href="dashboard.php?action=start_bidding&id=<?php echo $p['id']; ?>" class="action-btn btn-start">Start Bid</a>
                                <?php elseif($p['status'] == 'bidding'): ?>
                                    <button onclick="finalizePlayer(<?php echo $p['id']; ?>)" class="action-btn btn-start" style="background: #2ed573;">Sold/Finalize</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        async function finalizePlayer(id) {
            if(confirm('Finalize this sale?')) {
                const response = await fetch(`../api/auction_api.php?action=sell_player&id=${id}`);
                const result = await response.json();
                if(result.success) {
                    location.reload();
                }
            }
        }
    </script>
</body>
</html>
