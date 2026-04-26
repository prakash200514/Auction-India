<?php
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'team') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$squad = $conn->query("SELECT * FROM players WHERE team_id = $user_id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user['team_name']; ?> Dashboard - IPL Auction</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .auction-arena { display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; }
        .player-card { text-align: center; padding: 40px; }
        .player-name { font-size: 3rem; font-weight: 800; color: var(--primary-color); margin-bottom: 10px; }
        .player-cat { font-size: 1.2rem; opacity: 0.8; margin-bottom: 30px; }
        .bid-info { display: flex; justify-content: space-around; margin-bottom: 40px; }
        .bid-box { padding: 20px; border-radius: 12px; background: rgba(255,255,255,0.05); flex: 1; margin: 0 10px; }
        .bid-box span { display: block; font-size: 0.9rem; opacity: 0.7; }
        .bid-box h3 { font-size: 1.8rem; color: #fff; }
        .budget-card { background: linear-gradient(135deg, var(--secondary-color), #0a1535); border: 1px solid var(--primary-color); }
        .squad-list { margin-top: 30px; }
        .squad-item { display: flex; justify-content: space-between; padding: 15px; border-bottom: 1px solid var(--glass-border); }
    </style>
</head>
<body>
    <nav class="navbar glass">
        <a href="#" class="logo"><?php echo strtoupper($user['team_name']); ?></a>
        <div class="nav-links">
            <span style="color: var(--primary-color); font-weight: 600;">Budget: ₹<span id="navBudget"><?php echo number_format($user['budget']); ?></span></span>
            <a href="../logout.php">Logout</a>
        </div>
    </nav>

    <div class="container animate-fade-in">
        <div class="auction-arena">
            <!-- Active Player Section -->
            <div id="activeAuction" class="card glass">
                <div id="noPlayer" style="display: none; padding: 50px; text-align: center;">
                    <h2>Waiting for Admin to start bidding...</h2>
                </div>
                <div id="playerDetails">
                    <div class="player-card">
                        <div class="player-cat" id="pCat">BATSMAN</div>
                        <h1 class="player-name" id="pName">PLAYER NAME</h1>
                        <div class="bid-info">
                            <div class="bid-box">
                                <span>Base Price</span>
                                <h3 id="pBase">₹0</h3>
                            </div>
                            <div class="bid-box" style="border: 1px solid var(--primary-color);">
                                <span>Current Bid</span>
                                <h3 id="pCurrent">₹0</h3>
                            </div>
                        </div>
                        <div style="margin-bottom: 20px; font-weight: 600;">
                            High Bidder: <span id="pHigher" style="color: var(--primary-color);">Team Name</span>
                        </div>
                        <button id="bidBtn" onclick="placeBid()" class="btn-premium" style="max-width: 300px;">PLACE BID (₹<span id="nextBidAmount">0</span>)</button>
                    </div>
                </div>
            </div>

            <!-- Team Info & Squad -->
            <div>
                <div class="card glass budget-card">
                    <div class="card-title" style="color: #fff;">Remaining Budget</div>
                    <h1 style="font-size: 2.5rem;">₹<span id="mainBudget"><?php echo number_format($user['budget']); ?></span></h1>
                </div>

                <div class="card glass squad-list">
                    <div class="card-title">Your Squad</div>
                    <div id="squadContainer">
                        <?php if($squad->num_rows > 0): ?>
                            <?php while($s = $squad->fetch_assoc()): ?>
                                <div class="squad-item">
                                    <span><?php echo $s['name']; ?> (<?php echo $s['category']; ?>)</span>
                                    <span style="color: var(--primary-color);">₹<?php echo number_format($s['current_price']); ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="opacity: 0.5; padding: 20px;">No players bought yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let activePlayerId = null;
        let nextBidValue = 0;

        async function updateStatus() {
            try {
                const response = await fetch('../api/auction_api.php?action=get_status');
                const data = await response.json();

                if (data.status === 'no_active_player') {
                    document.getElementById('playerDetails').style.display = 'none';
                    document.getElementById('noPlayer').style.display = 'block';
                    activePlayerId = null;
                } else {
                    document.getElementById('playerDetails').style.display = 'block';
                    document.getElementById('noPlayer').style.display = 'none';
                    
                    document.getElementById('pName').innerText = data.player.name;
                    document.getElementById('pCat').innerText = data.player.category.toUpperCase();
                    document.getElementById('pBase').innerText = '₹' + new Intl.NumberFormat().format(data.player.base_price);
                    document.getElementById('pCurrent').innerText = '₹' + new Intl.NumberFormat().format(data.player.current_price);
                    document.getElementById('pHigher').innerText = data.player.high_bidder;
                    document.getElementById('nextBidAmount').innerText = new Intl.NumberFormat().format(data.player.next_bid);
                    
                    activePlayerId = data.player.id;
                    nextBidValue = data.player.next_bid;

                    // Update budgets
                    const formattedBudget = new Intl.NumberFormat().format(data.user_budget);
                    document.getElementById('navBudget').innerText = formattedBudget;
                    document.getElementById('mainBudget').innerText = formattedBudget;
                }
            } catch (error) {
                console.error("Error fetching status:", error);
            }
        }

        async function placeBid() {
            if (!activePlayerId) return;

            const formData = new FormData();
            formData.append('player_id', activePlayerId);
            formData.append('amount', nextBidValue);

            const response = await fetch('../api/auction_api.php?action=place_bid', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.error) {
                alert(result.error);
            } else {
                updateStatus();
            }
        }

        // Poll every 2 seconds
        setInterval(updateStatus, 2000);
        updateStatus();
    </script>
</body>
</html>
