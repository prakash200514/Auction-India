<?php
require_once '../includes/config.php';

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Current tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'players';

// Handle Player Add
if (isset($_POST['add_player'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $base_price = $_POST['base_price'];
    
    $stmt = $conn->prepare("INSERT INTO players (name, category, base_price) VALUES (?, ?, ?)");
    $stmt->bind_param("ssd", $name, $category, $base_price);
    $stmt->execute();
}

// Handle Team Deletion
if (isset($_GET['delete_team'])) {
    $team_id = $_GET['delete_team'];
    $conn->query("DELETE FROM users WHERE id = $team_id AND role = 'team'");
    header("Location: dashboard.php?tab=teams");
    exit();
}

// Handle Budget Update
if (isset($_POST['update_budget'])) {
    $team_id = $_POST['team_id'];
    $new_budget = $_POST['budget'];
    $stmt = $conn->prepare("UPDATE users SET budget = ? WHERE id = ?");
    $stmt->bind_param("di", $new_budget, $team_id);
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
        header("Location: dashboard.php");
        exit();
    }
}

// Data fetching
$players = $conn->query("SELECT p.*, u.team_name as bought_by FROM players p LEFT JOIN users u ON p.team_id = u.id ORDER BY p.id DESC");
$teams = $conn->query("SELECT id, name, team_name, budget, (SELECT COUNT(*) FROM players WHERE team_id = users.id) as player_count FROM users WHERE role = 'team'");
$stats = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='sold' THEN 1 ELSE 0 END) as sold FROM players")->fetch_assoc();
$active_player = $conn->query("SELECT p.* FROM players p JOIN settings s ON s.active_player_id = p.id WHERE s.id = 1")->fetch_assoc();
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
        .badge-unsold { background: #747d8c; color: #fff; }
        
        .action-btn { padding: 8px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem; margin-right: 5px; cursor: pointer; border: none; }
        .btn-start { background: var(--primary-color); color: #000; }
        .btn-reset { background: var(--accent-color); color: #fff; }
        .btn-delete { background: #ff4757; color: #fff; }
        
        .nav-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--glass-border); padding-bottom: 10px; }
        .tab-link { color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 8px; transition: 0.3s; }
        .tab-link.active { background: var(--primary-color); color: #000; font-weight: 600; }
        
        .bid-monitor { position: sticky; top: 20px; max-height: 500px; overflow-y: auto; }
        .bid-item { padding: 10px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center; }
        .bid-item:first-child { background: rgba(212, 175, 55, 0.1); border-radius: 8px; }
    </style>
</head>
<body>
    <nav class="navbar glass">
        <a href="#" class="logo">IPL ADMIN</a>
        <div class="nav-links">
            <a href="dashboard.php?action=reset" class="btn-reset" onclick="return confirm('Reset EVERYTHING? This will delete all bids and reset team budgets.')">Reset Auction</a>
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
                <div class="card-title">Live Status</div>
                <h2 style="font-size: 2rem; color: var(--primary-color);"><?php echo $active_player ? 'BIDDING: ' . $active_player['name'] : 'IDLE'; ?></h2>
            </div>
        </div>

        <div class="nav-tabs">
            <a href="?tab=players" class="tab-link <?php echo $tab == 'players' ? 'active' : ''; ?>">Manage Players</a>
            <a href="?tab=teams" class="tab-link <?php echo $tab == 'teams' ? 'active' : ''; ?>">Manage Teams</a>
        </div>

        <?php if ($tab == 'players'): ?>
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

                <?php if ($active_player): ?>
                <div class="card-title" style="margin-top: 30px;">Live Bid History</div>
                <div class="bid-monitor" id="bidMonitor">
                    <p style="text-align: center; color: #888;">Loading bids...</p>
                </div>
                <?php endif; ?>
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
                            <th>Winner</th>
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
                            <td><?php echo $p['bought_by'] ? $p['bought_by'] . ' (₹' . number_format($p['current_price']) . ')' : '-'; ?></td>
                            <td>
                                <?php if($p['status'] == 'available' || $p['status'] == 'unsold'): ?>
                                    <a href="dashboard.php?action=start_bidding&id=<?php echo $p['id']; ?>" class="action-btn btn-start">Start Bid</a>
                                <?php elseif($p['status'] == 'bidding'): ?>
                                    <button onclick="finalizePlayer(<?php echo $p['id']; ?>)" class="action-btn btn-start" style="background: #2ed573;">Sold/Unsold</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <!-- Team Management Tab -->
        <div class="card glass">
            <div class="card-title">Team Management</div>
            <table>
                <thead>
                    <tr>
                        <th>Team Name</th>
                        <th>Owner</th>
                        <th>Budget Remaining</th>
                        <th>Players Bought</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($t = $teams->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo $t['team_name']; ?></strong></td>
                        <td><?php echo $t['name']; ?></td>
                        <td>
                            <form method="POST" style="display: flex; gap: 10px;">
                                <input type="hidden" name="team_id" value="<?php echo $t['id']; ?>">
                                <input type="number" name="budget" class="form-control" value="<?php echo $t['budget']; ?>" style="width: 150px; padding: 5px;">
                                <button type="submit" name="update_budget" class="action-btn btn-start" style="padding: 5px 10px;">Update</button>
                            </form>
                        </td>
                        <td><?php echo $t['player_count']; ?></td>
                        <td>
                            <a href="?tab=teams&delete_team=<?php echo $t['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Delete this team? All their players will be released.')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        async function finalizePlayer(id) {
            if(confirm('Finalize this player status? If there are bids, it will be marked as SOLD to the highest bidder. If no bids, it will be marked as UNSOLD.')) {
                const response = await fetch(`../api/auction_api.php?action=sell_player&id=${id}`);
                const result = await response.json();
                if(result.success) {
                    location.reload();
                }
            }
        }

        <?php if ($active_player): ?>
        async function updateBids() {
            try {
                const response = await fetch(`../api/auction_api.php?action=get_all_bids`);
                const data = await response.json();
                const monitor = document.getElementById('bidMonitor');
                
                if (data.bids && data.bids.length > 0) {
                    monitor.innerHTML = data.bids.map((bid, index) => `
                        <div class="bid-item">
                            <span>${index === 0 ? '🏆 ' : ''}<strong>${bid.team_name}</strong></span>
                            <span>₹${parseInt(bid.bid_amount).toLocaleString()}</span>
                        </div>
                    `).join('');
                } else {
                    monitor.innerHTML = '<p style="text-align: center; color: #888;">No bids yet</p>';
                }
            } catch (e) {
                console.error("Error fetching bids", e);
            }
        }
        setInterval(updateBids, 2000);
        updateBids();
        <?php endif; ?>
    </script>
</body>
</html>
